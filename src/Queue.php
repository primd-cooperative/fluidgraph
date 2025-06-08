<?php

namespace FluidGraph;

use HRTime;
use ArrayObject;
use RuntimeException;
use Bolt\enum\Signature;

class Queue
{
	use HasGraph;

	const CREATE = 'create';
	const UPDATE = 'update';
	const DELETE = 'delete';

	/**
	 * @var ArrayObject<string, Element\Edge>
	 */
	protected ArrayObject $edges;

	/**
	 * @var ArrayObject<string, Element\Node>
	 */
	protected ArrayObject $nodes;

	/**
	 * @var array<string, array<string>>
	 */
	protected array $nodeOperations;

	/**
	 * @var array<string, array<string>>
	 */
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
	public function merge(array &$info = []): static
	{
		/**
		 * @disregard P1009
		 */
		$start       = hrtime(TRUE);
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

				foreach (Element\Node::relationships($node) as $relationship) {
					$relationship->merge($this->graph);
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
				Status::INDUCTED => static::CREATE,
				Status::ATTACHED => static::UPDATE,
				Status::RELEASED => static::DELETE,
			};

			$this->nodeOperations[$operation][] = $identity;
		}

		foreach ($this->edges as $identity => $edge) {
			$operation = match ($edge->status) {
				Status::INDUCTED => static::CREATE,
				Status::ATTACHED => static::UPDATE,
				Status::RELEASED => static::DELETE,
			};

			$this->edgeOperations[$operation][] = $identity;
		}

		foreach (['nodeOperations' => 'nodes', 'edgeOperations' => 'edges'] as $type => $list) {
			foreach ($this->$type[static::UPDATE] as $i => $identity) {
				$element = $this->$list[$identity];
				$diffs   = Element::changes($element);

				if (empty($diffs)) {
					unset($this->$type[static::UPDATE][$i]);
					continue;
				}

				foreach (Element::classes($element) as $class) {
					$class::onUpdate($element);
				}
			}
		}

		$this->spent = FALSE;
		$info        = [
			'time'  => (hrtime(TRUE) - $start) / 1_000_000_000,
			'nodes' => $this->nodeOperations,
			'edges' => $this->edgeOperations,
		];

		return $this;
	}

	/**
	 *
	 */
	public function reset(): static
	{
		$this->nodeOperations = [
			static::CREATE => [],
			static::UPDATE => [],
			static::DELETE => [],
		];

		$this->edgeOperations = [
			static::CREATE => [],
			static::UPDATE => [],
			static::DELETE => [],
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

		if (count($this->nodeOperations[static::CREATE])) {
			$this->doNodeCreates();
		}

		if (count($this->nodeOperations[static::UPDATE])) {
			$this->doNodeUpdates();
		}

		if (count($this->edgeOperations[static::CREATE])) {
			$this->doEdgeCreates();
		}

		if (count($this->edgeOperations[static::UPDATE])) {
			$this->doEdgeUpdates();
		}

		if (count($this->edgeOperations[static::DELETE])) {
			$this->doEdgeDeletes();
		}

		if (count($this->nodeOperations[static::DELETE])) {
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
		$identities = $this->edgeOperations[static::CREATE];

		foreach ($identities as $i => $identity) {
			$edge = $this->edges[$identity];

			$query
				->run('MATCH (%s) WHERE id(%s) = $%s', "f$i", "f$i", "fd$i")
				->set("fd$i", $edge->source->identity)
			;

			$query
				->run('MATCH (%s) WHERE id(%s) = $%s', "t$i", "t$i", "td$i")
				->set("td$i", $edge->target->identity)
			;
		}

		$i = 0; foreach ($identities as $identity) {
			$edge    = $this->edges[$identity];
			$created = [];

			foreach (Element::classes($edge) as $class) {
				$created = $class::onCreate($edge);
			}

			if ($key = Element::key($edge)) {
				$query
					->run('MERGE (%s)-[%s:%s {@%s}]->(%s)', "f$i", "i$i", Element::signature($edge, Status::FASTENED), "k$i", "t$i")
					->set("k$i", $key)
					->run('ON CREATE SET @%s(%s)', "c$i", "i$i")
					->run('ON MATCH SET @%s(%s)', "m$i", "i$i")
					->set("c$i", array_diff_key(Element::properties($edge), $key))
					->set("m$i", array_diff_key(Element::properties($edge), $key, $created))
				;

			} else {
				$query
					->run('CREATE (%s)-[%s:%s {@%s}]->(%s)', "f$i", "i$i", Element::signature($edge, Status::FASTENED), "d$i", "t$i")
					->set("d$i", Element::properties($edge))
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
				// Duplicate merge, re-fasten the entities
				//

				foreach ($this->edges[$identities[$i]]->entities as $entity) {
					Element::fasten($this->edges[$record->element_id], $entity);
				}

			} else {
				$this->edges[$record->element_id] = $this->edges[$identities[$i]];
			}

			$element = $this->graph->resolve($record);

			unset($this->edges[$identities[$i]]);
		}
	}


	protected function doEdgeDeletes(): void
	{
		$matchers   = [];
		$identities = $this->edgeOperations[static::DELETE];
		$query      = $this->graph->query;
		$where      = $query->where->var('e');

		foreach ($identities as $identity) {
			$matchers[] = $where->id($identity);
		}

		$responses = $query
			->run('MATCH (n1)-[e]->(n2) WHERE %s DELETE e', $where->any(...$matchers)())
			->pull(Signature::SUCCESS)
		;

		foreach ($identities as $identity) {
			$this->edges[$identity]->status = Status::DETACHED;

			unset($this->edges[$identity]);
		}
	}



	protected function doEdgeUpdates(): void
	{
		$query      = $this->graph->query;
		$identities = $this->edgeOperations[static::UPDATE];

		$i = 0; foreach ($identities as $identity) {
			$query
				->run('MATCH (%s)-[%s]->(%s) WHERE id(%s) = $%s', "f$i", "i$i", "t$i", "i$i", "e$i")
				->set("e$i", $identity)
			;

			$i++;
		}

		$i = 0; foreach ($identities as $identity) {
			$edge = $this->edges[$identity];

			$query
				->run('SET @%s(%s)', "d$i", "i$i")
				->set("d$i", Element::changes($edge))
			;

			//
			// TODO: Renamed edge?
			//

			$i++;
		}

		$query->run(
			'RETURN %s',
			implode(',', array_map(fn($i) => "i$i", range(0, $i - 1)))
		);

		foreach ($query->pull(Signature::RECORD) as $record) {
			$element = $this->graph->resolve($record);
		}
	}


	/**
	 *
	 */
	protected function doNodeCreates(): void
	{
		$query      = $this->graph->query;
		$identities = $this->nodeOperations[static::CREATE];

		$i = 0; foreach ($identities as $identity) {
			$node    = $this->nodes[$identity];
			$created = [];

			foreach (Element::classes($node) as $class) {
				$created = $class::onCreate($node);
			}

			if ($key = Element::key($node)) {
				$query
					->run('MERGE (%s:%s {@%s})', "i$i", Element::signature($node, Status::FASTENED), "k$i")
					->set("k$i", $key)
					->run('ON CREATE SET @%s(%s)', "c$i", "i$i")
					->run('ON MATCH SET @%s(%s)', "m$i", "i$i")
					->set("c$i", array_diff_key(Element::properties($node), $key))
					->set("m$i", array_diff_key(Element::properties($node), $key, $created))
				;
			} else {
				$query
					->run('CREATE (%s:%s {@%s})', "i$i", Element::signature($node, Status::FASTENED), "d$i")
					->set("d$i", Element::properties($node))
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
				// Duplicate merge, re-fasten the entities
				//

				foreach ($this->nodes[$identities[$i]]->entities as $entity) {
					Element::fasten($this->nodes[$record->element_id], $entity);
				}

			} else {
				$this->nodes[$record->element_id] = $this->nodes[$identities[$i]];
			}

			$element = $this->graph->resolve($record);

			unset($this->nodes[$identities[$i]]);
		}
	}


	protected function doNodeDeletes(): void
	{
		$matchers   = [];
		$identities = $this->nodeOperations[static::DELETE];
		$query      = $this->graph->query;
		$where      = $query->where->var('n');

		foreach ($identities as $identity) {
			$matchers[] = $where->id($identity);
		}

		$responses = $query
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
		$query      = $this->graph->query;
		$identities = $this->nodeOperations[static::UPDATE];

		$i = 0; foreach ($identities as $identity) {
			$query
				->run('MATCH (%s) WHERE id(%s) = $%s', "i$i", "i$i", "n$i")
				->set("n$i", $identity)
			;

			$i++;
		}

		$i = 0; foreach ($identities as $identity) {
			$node = $this->nodes[$identity];

			$query
				->run('SET @%s(%s)', "d$i", "i$i")
				->set("d$i", Element::changes($node))
			;

			if ($plus_signature = Element::signature($node, Status::FASTENED)) {
				$query->run('SET %s:%s', "i$i", $plus_signature);
			}

			if ($less_signature = Element::signature($node, Status::RELEASED)) {
				$query->run('REMOVE %s:%s', "i$i", $less_signature);
			}

			$i++;
		}

		$query->run(
			'RETURN %s',
			implode(',', array_map(fn($i) => "i$i", range(0, $i - 1)))
		);

		foreach ($query->pull(Signature::RECORD) as $record) {
			$element = $this->graph->resolve($record);

			foreach ($element->labels as $label => $status) {
				if ($status != Status::ATTACHED) {
					unset($element->labels[$label]);
				}
			}
		}
	}
}

