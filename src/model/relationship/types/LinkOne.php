<?php

namespace FluidGraph\Relationship;

use FluidGraph\Edge;
use FluidGraph\Node;
use FluidGraph\Entity;

/**
 * A type of relationship that links to one node with one edge.
 * @extends \FluidGraph\Relationship
 */
trait LinkOne
{
	/**
	 * Get the related node entity when it is of the specified type and labels.
	 *
	 * If a related node exists but does not match the class/labels, null will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N> $class
	 * @return N
	 */
	public function of(string $class, string ...$labels): ?Node
	{
		$edge = reset($this->active);

		if (!$edge) {
			return NULL;
		}

		if (!in_array($class, $edge->__element__->target->classes())) {
			return NULL;
		}

		if ($labels && !array_intersect($labels, $edge->__element__->target->labels())) {
			return NULL;
		}

		return $edge->__element__->target->as($class);
	}


	/**
	 *
	 */
	public function set(Node $concern, array $data = []): static
	{
		$this->validate($concern);

		$hash = spl_object_hash($concern->__element__);

		if (!isset($this->active[$hash])) {
			$this->unset();

			if (isset($this->loaded[$hash])) {
				$this->active[$hash] = $this->loaded[$hash];

			} else {
				//
				// No existing edge found, so we'll create a new one.
				//

				if ($this->reverse) {
					$source = $concern;
					$target = $this->subject;
				} else {
					$source = $this->subject;
					$target = $concern;
				}

				$edge = $this->kind::make($data, Entity::MAKE_ASSIGN);

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
	public function unset(): static
	{
		$this->active = [];

		return $this;
	}
}
