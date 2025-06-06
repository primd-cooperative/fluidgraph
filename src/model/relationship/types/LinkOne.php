<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;

/**
 * A type of relationship that links to one node with one edge.
 * @extends \FluidGraph\Relationship
 */
trait LinkOne
{
	use Link;

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
		$edge     = reset($this->active);
		$property = match ($this->method) {
			Method::TO   => 'target',
			Method::FROM => 'source'
		};

		if (!$edge) {
			return NULL;
		}

		if (!in_array($class, $edge->__element__->$property->classes())) {
			return NULL;
		}

		if ($labels && !array_intersect($labels, $edge->__element__->$property->labels())) {
			return NULL;
		}

		return $edge->__element__->$property->as($class);
	}


	/**
	 *
	 */
	public function set(Node $concern, array $data = []): static
	{
		$this->validate($concern);

		$hash = $this->index($concern);

		if (!$hash) {
			$this->unset();

			$hash = $this->realize($concern, $data);
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
