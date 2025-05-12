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
	 * @var ArrayObject<Content\Edge>
	 */
	protected ArrayObject $edges;

	/**
	 * @var ArrayObject<Content\Node>
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
	 * @param ArrayObject<Content\Node> $nodes
	 * @param ArrayObject<Content\Edge> $edges
	 */
	public function merge(): static
	{
		$visit_nodes = [];

		foreach ($this->nodes as $identity => $node) {
			$visit_nodes[$identity] = $node->status;
		}

		while($visit_nodes) {
			foreach ($visit_nodes as $identity => $status) {
				$node = $this->nodes[$identity];

				foreach ($this->getRelationships($node, $status) as $relationship) {
					$relationship->merge($node);
				}
			}

			foreach ($this->nodes as $identity => $node) {
				if (!isset($visit_nodes[$identity]) || $visit_nodes[$identity] !== $node->status) {
					$visit_nodes[$identity] = $node->status;
				} else {
					unset($visit_nodes[$identity]);
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
		$i     = 0;
		$query = $this->graph->query;

		foreach ($this->edgeOperations[Operation::CREATE->value] as $identity => $edge) {
			$from = $this->edgeOperations[Operation::CREATE->value][$edge];

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

		$i = 0;

		foreach ($this->edgeOperations[Operation::CREATE] as $identity => $edge) {
			$query
				->run('CREATE (%s)-[%s:%s {@%s}]->(%s)', "f$i", "i$i", $this->getSignature($edge), "d$i", "t$i")
				->with("d$i", $this->getProperties($edge))
			;

			$i++;
		}

		$query->run(
			'RETURN %s',
			implode(',', array_map(fn($i) => "i$i", range(0, $i - 1)))
		);
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
			$key  = $this->getKey($node);

			foreach ($this->getClasses($node) as $class) {
				$class::onCreate($node);

				$key = $this->getKey($node);
			}

			if ($key) {
				$query
					->run('MERGE (%s:%s {@%s})', "i$i", $this->getSignature($node), "k$i")
					->with("k$i", $key)
					->run('ON CREATE SET @%s(%s)', "d$i", "i$i")
					->run('ON MATCH SET @%s(%s)', "d$i", "i$i")
					->with("d$i", array_diff_key($this->getProperties($node), $key))
				;
			} else {
				$query
					->run('CREATE (%s:%s {@%s})', "i$i", $this->getSignature($node), "d$i")
					->with("d$i", $this->getProperties($node))
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

			$this->graph->resolve($record)->status = Status::ATTACHED;

			unset($this->nodes[$identities[$i]]);
		}
	}


	protected function doNodeDeletes(): void
	{
		$matchers   = [];
		$identities = $this->nodeOperations[Operation::DELETE->value];
		$query      = $this->graph->query;
		$where      = $query->where->var('n');

		$i = 0; foreach ($identities as $identity) {
			$node       = $this->nodes[$identity];
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
			$diffs[$i] = $this->getChanges($node);

			if (!count($diffs[$i])) {
				continue;
			}

			foreach ($this->getClasses($node) as $class) {
				$class::onUpdate($node);

				$diffs[$i] = $this->getChanges($node);
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

			if ($plus_signature = $this->getSignature($node, Status::INDUCTED)) {
				$query->run('SET %s:%s', "i$i", $plus_signature);
			}

			if ($less_signature = $this->getSignature($node, Status::RELEASED)) {
				$query->run('REMOVE %s:%s', "i$i", $less_signature);
			}
		}

		$query->run(
			'RETURN %s',
			implode(',', array_map(fn($i) => "i$i", array_keys(array_filter($diffs))))
		);

		foreach ($query->pull(Signature::RECORD) as $record) {
			$this->graph->resolve($record);
		}
	}


	/**
	 *
	 */
	protected function getChanges(Content\Element $content): array
	{
		$changes = $this->getProperties($content);

		foreach ($changes as $property => $value) {
			if (!array_key_exists($property, $content->loaded)) {
				continue;
			}

			if ($value != $content->loaded[$property]) {
				continue;
			}

			unset($changes[$property]);
		}

		return $changes;
	}


	/**
	 *
	 */
	protected function getClasses(Content\Element $content): array
	{
		$classes = [];

		foreach ($this->getLabels($content) as $label) {
			if (!class_exists($label)) {
				continue;
			}

			if (!is_subclass_of($label, Element::class, TRUE)) {
				continue;
			}

			$classes[] = $label;
		}

		return $classes;
	}


	/**
	 *
	 */
	protected function getKey(Content\Element $content): array
	{
		$key        = [];
		$properties = [];

		foreach ($this->getClasses($content) as $class) {
			$properties = array_merge($properties, $class::key());
		}

		foreach (array_unique($properties) as $property) {
			if (array_key_exists($property, $content->active)) {
				$key[$property] = $content->active[$property];
			}
		}

		return $key;
	}


	/**
	 *
	 */
	protected function getLabels(Content\Element $content, Status ...$statuses): array
	{
		if (!count($statuses)) {
			$statuses = [Status::INDUCTED, Status::ATTACHED];
		}

		return array_keys(array_filter(
			$content->labels,
			function (Status $status) use ($statuses) {
				return in_array($status, $statuses, TRUE);
			}
		));
	}


	/**
	 * @return array<mixed>
	 */
	protected function getProperties(Content\Element $content): array
	{
		return array_filter(
			$content->active,
			function($value) {
				return !$value instanceof Relationship;
			}
		);
	}


	/**
	 * @return array<Relationship>
	 */
	protected function getRelationships(Content\Element $content): array
	{
		return array_filter(
			$content->active,
			function($value) {
				return $value instanceof Relationship;
			}
		);
	}


	protected function getSignature(Content\Element $content, Status ...$statuses): string
	{
		return implode(':', $this->getLabels($content, ...$statuses));
	}
}

