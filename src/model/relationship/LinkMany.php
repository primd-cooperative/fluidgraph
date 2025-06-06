<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FluidGraph\Element;
use FluidGraph\Relationship;

/**
 * A type of relationship that links to many nodes with one edge per node.
 */
abstract class LinkMany extends Relationship
{
	/**
	 * Get an array of all the edges for one or more nodes or node types.
	 *
	 * @return array<T>
	 */
	public function for(Element\Node|Node|string ...$nodes): array
	{
		$edges = [];

		if (empty($nodes)) {
			return $this->active;
		}

		foreach ($nodes as $node) {
			foreach ($this->active as $edge) {
				if ($edge->for($this->method, $node)) {
					$edges[] = $edge;
				}
			}
		}

		return $edges;
	}


	/**
	 * Get the related node entities when they are of the specified class and labels.
	 *
	 * If a related nodes exist but do not match the class/labels, an empty array will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N> $class
	 * @return array<N>
	 */
	public function of(string $class, string ...$labels): array
	{
		$results  = [];
		$property = match ($this->method) {
			Method::TO   => 'target',
			Method::FROM => 'source'
		};

		foreach ($this->active as $edge) {
			if (!in_array($class, $edge->__element__->$property->classes())) {
				continue;
			}

			if ($labels && !array_intersect($labels, $edge->__element__->$property->labels())) {
				continue;
			}

			$results[] = $edge->__element__->$property->as($class);
		}

		return $results;
	}

	/**
	 *
	 */
	public function set(Node $concern, array $data = []): static
	{
		$this->validate($concern);

		$hash = $this->index($concern);

		if (!$hash) {
			$hash = $this->realize($concern, $data);
		}

		$this->active[$hash]->assign($data);

		return $this;
	}


	/**
	 *
	 */
	public function unset(Node $concern): static
	{
		$hash = $this->index($concern);

		if ($hash) {
			unset($this->active[$hash]);
		}

		return $this;
	}
}
