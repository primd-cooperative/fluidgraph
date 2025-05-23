<?php

namespace FluidGraph\Relationship;

use FluidGraph\Edge;
use FluidGraph\Node;
use FluidGraph\Entity;

/**
 * A type of relationship that links to many nodes with one edge per node.
 */
trait LinkMany
{
	use AbstractRelationship;

	/**
	 * Get the related node entities when they are of the specified class and labels.
	 *
	 * If a related nodes exist but do not match the class/labels, an empty array will be returned.
	 *
	 * @param class-string<T of Node>
	 * @return array<T>
	 */
	public function of(string $class, string ...$labels): array
	{
		$results = [];

		foreach ($this->active as $edge) {
			if (!in_array($class, $edge->__element__->target->classes())) {
				continue;
			}

			if ($labels && !array_intersect($labels, $edge->__element__->target->labels())) {
				continue;
			}

			$results[] = $edge->__element__->target->as($class);
		}

		return $results;
	}

	/**
	 *
	 */
	public function set(Node $target, array $data = []): static
	{
		$this->validate($target);

		$hash = spl_object_hash($target->__element__);

		if (!isset($this->active[$hash])) {
			if (isset($this->loaded[$hash])) {
				$this->active[$hash] = $this->loaded[$hash];

			} else {
				//
				// No existing edge found, so we'll create a new one.
				//

				$source = $this->source;
				$edge   = $this->type::make($data, Entity::MAKE_ASSIGN);

				$edge->with(
					function(Node $source, Node $target) {
						/**
						 * @var Edge $this
						 */
						$this->__element__->source = $source;
						$this->__element__->target = $target;
					},
					$source,
					$target
				);

				$this->active[$hash] = $edge;
			}
		}

		$this->active[$hash]->assign($data);

		return $this;
	}


	/**
	 *
	 */
	public function unset(Node $target): static
	{
		$hash = spl_object_hash($target->__element__);

		if (isset($this->active[$hash])) {
			unset($this->active[$hash]);
		}

		return $this;
	}
}
