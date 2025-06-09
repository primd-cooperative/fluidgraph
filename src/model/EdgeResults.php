<?php

namespace FluidGraph;

use FluidGraph\Relationship\Method;

/**
 * @template T of Edge
 * @extends Entity\Results<T>
 */
class EdgeResults extends Entity\Results
{
	/**
	 * @var array<Method>
	 */
	protected array $methods = [Method::TO, Method::FROM];


	/**
	 * Get all edge entities for this relationship that correspond to all node(s)/label(s) as Results
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return static<T>
	 */
	public function of(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		$edges = [];

		if (count($this)) {
			array_unshift($nodes, $node);

			foreach ($this as $edge) {
				foreach ($nodes as $node) {
					if (!$edge->for($node, ...$this->methods)) {
						continue 2;
					}
				}

				$edges[] = $edge;
			}
		}

		return new static($edges)->using(...$this->methods);
	}


	/**
	 * Get all edge entities for this relationship that corresponds to any node(s)/label(s) as Results
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return static<T>
	 */
	public function for(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		$edges = [];

		if (count($this)) {
			array_unshift($nodes, $node);

			foreach ($nodes as $node) {
				foreach ($this as $edge) {
					if ($edge->for($node, ...$this->methods)) {
						$edges[] = $edge;
						continue 2;
					}
				}
			}
		}

		return new static($edges)->using(...$this->methods);
	}


	/**
	 * Get related node entities of() the specified class as Results.
	 *
	 * If related node entities exist but do not match the class, an empty array will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N> $class
	 * @return NodeResults<N>
	 */
	public function get(string $class): NodeResults
	{
		$nodes = [];
		$index = [];

		foreach ($this as $edge) {
			foreach ($this->methods as $method) {
				$node = match ($method) {
					Method::TO   => $edge->__element__->target->as($class),
					Method::FROM => $edge->__element__->soruce->as($class)
				};

				$hash = spl_object_hash($node);

				if (!isset($index[$hash])) {
					if ($node->is($class)) {
						$nodes[]      = $node;
						$index[$hash] = TRUE;

					} else {
						$index[$hash] = FALSE;

					}
				}
			}
		}

		return new NodeResults($nodes);
	}


	/**
	 *
	 */
	public function using(Method ...$methods): static
	{
		$this->methods = $methods;

		return $this;
	}
}
