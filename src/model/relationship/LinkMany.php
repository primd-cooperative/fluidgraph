<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FluidGraph\Element;
use FluidGraph\Results;
use FluidGraph\Relationship;

/**
 * A type of relationship that links to many nodes with one edge per node.
 */
abstract class LinkMany extends Relationship
{
	/**
	 * Get related node entities of() the specified class as() Results.
	 *
	 * If related node entities exist but do not match the class, an empty array will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N> $class
	 * @return Results<N>
	 */
	public function as(string $class): Results
	{
		$nodes = [];

		foreach ($this->of($class) as $edge) {
			$nodes[] = match ($this->method) {
				Method::TO   => $edge->__element__->target->as($class),
				Method::FROM => $edge->__element__->soruce->as($class)
			};
		}

		return new Results($nodes);
	}


	/**
	 * Get all edge entities for this relationship, regardless what they correspond to as Results.
	 *
	 * @return Results<E>
	 */
	public function get(): Results
	{
		return new Results(array_values($this->active));
	}


	/**
	 * Get all edge entities for this relationship that corresponds to all node(s)/classes(s) as() Results
	 *
	 * @param Element\Node|Node|class-string $essence
	 * @param Element\Node|Node|class-string $essences
	 * @return array<E>
	 */
	public function of(Element\Node|Node|string $essence, Element\Node|Node|string ...$essences): array
	{
		if (count($this->active)) {
			$edges = [];

			array_unshift($essences, $essence);

			foreach ($essences as $essence) {
				foreach ($this->active as $edge) {
					if ($edge->of($essence, $this->method)) {
						$edges[] = $edge;
					}
				}
			}

			return $edges;
		}

		return [];
	}



	/**
	 * Get all edge entities for this relationship that corresponds to all node(s)/label(s) as() Results
	 *
	 * @param Element\Node|Node|class-string $essence
	 * @param Element\Node|Node|class-string $essences
	 * @return Results<E>

	 */
	public function for(Element\Node|Node|string $essence, Element\Node|Node|string ...$essences): Results
	{
		$edges = [];

		if (count($this->active)) {
			array_unshift($essences, $essence);

			foreach ($essences as $essence) {
				foreach ($this->active as $edge) {
					if ($edge->for($essence, $this->method)) {
						$edges[] = $edge;
					}
				}
			}
		}

		return new Results($edges);
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
