<?php

namespace FluidGraph;

use ArrayObject;
use RuntimeException;
use Bolt\enum\Signature;

class Queue
{
	use HasGraph;
	use DoesWith;

	/**
	 * @var ArrayObject<Element\Edge>
	 */
	protected ArrayObject $edges;

	/**
	 * @var ArrayObject<Element\Node>
	 */
	protected ArrayObject $nodes;

	protected array $nodeOperations;

	protected array $edgeOperations;

	protected bool $spent = FALSE;


	/**
	 *
	 */
	public function __construct()
	{
		$this->reset();
	}


	/**
	 *
	 */
	public function manage(ArrayObject $nodes, ArrayObject $edges): static
	{
		$this->nodes = $nodes;
		$this->edges = $edges;

		return $this;
	}

	/**
	 * @param ArrayObject<Element\Node> $nodes
	 * @param ArrayObject<Element\Edge> $edges
	 */
	public function merge(): static
	{
		$visit_nodes = [
			'new' => [],
			'old' => [],
		];

		foreach ($this->nodes as $identity => $node) {
			$visit_nodes['new'][$identity] = $node->status;
		}

		while($visit_nodes['new']) {
			foreach ($visit_nodes['new'] as $identity => $status) {
				$node = $this->nodes[$identity];

				foreach ($node->relationships() as $relationship) {
					$relationship->merge($node);
				}
			}

			foreach ($this->nodes as $identity => $node) {
				$is_new = isset($visit_nodes['new'][$identity]);
				$is_old = isset($visit_nodes['old'][$identity]);

				if (!$is_new && !$is_old) {
					$visit_nodes['new'][$identity] = $node->status;

				} elseif ($is_old && $visit_nodes['old'][$identity] !== $node->status) {
					$visit_nodes['new'][$identity] = $node->status;

					unset($visit_nodes['old'][$identity]);

				} else {
					$visit_nodes['old'][$identity] = $node->status;

					unset($visit_nodes['new'][$identity]);

				}
			}
		}

		foreach ($this->nodes as $identity => $node) {
			$operation = match ($node->status) {
				Status::INDUCTED => Operation::CREATE,
				Status::ATTACHED => Operation::UPDATE,
				Status::RELEASED => Operation::DELETE,
			};

			$this->nodeOperations[$operation->value][] = $identity;
		}

		foreach ($this->edges as $identity => $edge) {
			$operation = match ($edge->status) {
				Status::INDUCTED => Operation::CREATE,
				Status::ATTACHED => Operation::UPDATE,
				Status::RELEASED => Operation::DELETE,
			};

			$this->edgeOperations[$operation->value][] = $identity;
		}

		$this->spent = FALSE;

		return $this;
	}

	/**
	 *
	 */
	public function reset(): static
	{
		$this->nodeOperations = [
			Operation::CREATE->value => [],
			Operation::UPDATE->value => [],
			Operation::DELETE->value => [],
		];

		$this->edgeOperations = [
			Operation::CREATE->value => [],
			Operation::UPDATE->value => [],
			Operation::DELETE->value => [],
		];

		return $this;
	}


	/**
	 *
	 */
	public function run(bool $force = FALSE): static
	{
		if ($this->spent) {
			throw new RuntimeException(sprintf(
				'Must re-merge before re-running the queue.'
			));
		}

		if (count($this->nodeOperations[Operation::CREATE->value])) {
			$this->doNodeCreates();
		}

		if (count($this->nodeOperations[Operation::UPDATE->value])) {
			$this->doNodeUpdates();
		}

		if (count($this->edgeOperations[Operation::CREATE->value])) {
			$this->doEdgeCreates();
		}

		if (count($this->edgeOperations[Operation::UPDATE->value])) {
			$this->doEdgeUpdates();
		}

		if (count($this->edgeOperations[Operation::DELETE->value])) {
			$this->doEdgeDeletes();
		}

		if (count($this->nodeOperations[Operation::DELETE->value])) {
			$this->doNodeDeletes();
		}

		$this->spent = TRUE;

		return $this->reset();
	}


	/**
	 *
	 */
	protected function doEdgeCreates(): void
	{
		$query      = $this->graph->query;
		$identities = $this->edgeOperations[Operation::CREATE->value];


		$i = 0; foreach ($identities as $identity) {
			$edge = $this->edges[$identity];

			$query
				->run('MATCH (%s) WHERE id(%s) = $%s', "f$i", "f$i", "fd$i")
				->with("fd$i", $edge->source->identity)
			;

			$query
				->run('MATCH (%s) WHERE id(%s) = $%s', "t$i", "t$i", "td$i")
				->with("td$i", $edge->target->identity)
			;

			$i++;
		}

		$i = 0; foreach ($identities as $identity) {
			$edge = $this->edges[$identity];
			$key  = $edge->key();

			foreach ($edge->classes() as $class) {
				$class::onCreate($edge);

				$key = $edge->key();
			}

			if ($key) {
				$query
					->run('CREATE (%s)-[%s:%s {@%s}]->(%s)', "f$i", "i$i", $edge->signature(Status::FASTENED), "d$i", "t$i")
					->with("k$i", $key)
					->run('ON CREATE SET @%s(%s)', "d$i", "i$i")
					->run('ON MATCH SET @%s(%s)', "d$i", "i$i")
					->with("d$i", array_diff_key($edge->properties(), $key))
				;

			} else {
				$query
					->run('CREATE (%s)-[%s:%s {@%s}]->(%s)', "f$i", "i$i", $edge->signature(Status::FASTENED), "d$i", "t$i")
					->with("d$i", $edge->properties())
				;

			}

			$i++;
		}

		$query->run(
			'RETURN %s',
			implode(',', array_map(fn($i) => "i$i", range(0, $i - 1)))
		);

		foreach ($query->pull(Signature::RECORD) as $i => $record) {
			if (isset($this->edges[$record->element_id])) {

				//
				// Duplicate merge, re-fasten the entity
				//

				$this->graph->fasten(
					$this->edges[$identities[$i]]->entity,
					$this->edges[$record->element_id]
				);

			} else {
				$this->edges[$record->element_id] = $this->edges[$identities[$i]];
			}

			$this->graph->resolve($record)->status = Status::ATTACHED;

			unset($this->edges[$identities[$i]]);
		}
	}


	protected function doEdgeDeletes(): void
	{

	}



	protected function doEdgeUpdates(): void
	{

	}


	/**
	 *
	 */
	protected function doNodeCreates(): void
	{
		$query      = $this->graph->query;
		$identities = $this->nodeOperations[Operation::CREATE->value];

		$i = 0; foreach ($identities as $identity) {
			$node = $this->nodes[$identity];
			$key  = $node->key();

			foreach ($node->classes() as $class) {
				$class::onCreate($node);

				$key = $node->key();
			}

			if ($key) {
				$query
					->run('MERGE (%s:%s {@%s})', "i$i", $node->signature(Status::FASTENED), "k$i")
					->with("k$i", $key)
					->run('ON CREATE SET @%s(%s)', "d$i", "i$i")
					->run('ON MATCH SET @%s(%s)', "d$i", "i$i")
					->with("d$i", array_diff_key($node->properties(), $key))
				;
			} else {
				$query
					->run('CREATE (%s:%s {@%s})', "i$i", $node->signature(Status::FASTENED), "d$i")
					->with("d$i", $node->properties())
				;
			}

			$i++;
		}

		$query->run(
			'RETURN %s',
			implode(',', array_map(fn($i) => "i$i", range(0, $i - 1)))
		);

		foreach ($query->pull(Signature::RECORD) as $i => $record) {
			if (isset($this->nodes[$record->element_id])) {

				//
				// Duplicate merge, re-fasten the entity
				//

				$this->graph->fasten(
					$this->nodes[$identities[$i]]->entity,
					$this->nodes[$record->element_id]
				);

			} else {
				$this->nodes[$record->element_id] = $this->nodes[$identities[$i]];
			}

			$content = $this->graph->resolve($record);

			unset($this->nodes[$identities[$i]]);
		}
	}


	protected function doNodeDeletes(): void
	{
		$matchers   = [];
		$identities = $this->nodeOperations[Operation::DELETE->value];
		$query      = $this->graph->query;
		$where      = $query->where->var('n');

		foreach ($identities as $identity) {
			$matchers[] = $where->id($identity);
		}

		$result = $query
			->run('MATCH (n) WHERE %s DETACH DELETE n', $where->any(...$matchers)())
			->pull(Signature::SUCCESS)
		;

		foreach ($identities as $identity) {
			$this->nodes[$identity]->status = Status::DETACHED;

			unset($this->nodes[$identity]);
		}
	}


	protected function doNodeUpdates(): void
	{
		$diffs      = [];
		$query      = $this->graph->query;
		$identities = $this->nodeOperations[Operation::UPDATE->value];

		$i = 0; foreach ($identities as $identity) {
			$node      = $this->nodes[$identity];
			$diffs[$i] = $node->changes();

			if (!count($diffs[$i])) {
				continue;
			}

			foreach ($node->classes() as $class) {
				$class::onUpdate($node);

				$diffs[$i] = $node->changes();
			}

			$query
				->run('MATCH (%s) WHERE id(%s) = $%s', "i$i", "i$i", "n$i")
				->with("n$i", $identity)
			;

			$i++;
		}

		$i = 0; foreach ($identities as $identity) {
			$node = $this->nodes[$identity];

			if (!count($diffs[$i])) {
				continue;
			}

			$query
				->run('SET @%s(%s)', "d$i", "i$i")
				->with("d$i", $diffs[$i])
			;

			if ($plus_signature = $node->signature(Status::FASTENED)) {
				$query->run('SET %s:%s', "i$i", $plus_signature);
			}

			if ($less_signature = $node->signature(Status::RELEASED)) {
				$query->run('REMOVE %s:%s', "i$i", $less_signature);
			}
		}

		$query->run(
			'RETURN %s',
			implode(',', array_map(fn($i) => "i$i", array_keys(array_filter($diffs))))
		);

		foreach ($query->pull(Signature::RECORD) as $record) {
			$content = $this->graph->resolve($record);

			foreach ($content->labels as $label => $status) {
				if ($status != Status::ATTACHED) {
					unset($content->labels[$label]);
				}
			}
		}
	}
}

