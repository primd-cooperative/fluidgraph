<?php

namespace FluidGraph;

use FluidGraph\Relationship\Link;

/**
 * @template T of Edge
 * @extends Entity\Results<T>
 */
class EdgeResults extends Entity\Results
{
	/**
	 * @var array<Type>
	 */
	protected array $types = [Link::to, Link::from];


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
					if (!$edge->for($node, ...$this->types)) {
						continue 2;
					}
				}

				$edges[] = $edge;
			}
		}

		return new static($edges)->using(...$this->types);
	}


	/**
	 * Get all edge entities for this relationship that corresponds to any node(s)/label(s) as Results
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return static<T>
	 */
	public function ofAny(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		$edges = [];

		if (count($this)) {
			array_unshift($nodes, $node);

			foreach ($nodes as $node) {
				foreach ($this as $edge) {
					if ($edge->for($node, ...$this->types)) {
						$edges[] = $edge;
						continue 2;
					}
				}
			}
		}

		return new static($edges)->using(...$this->types);
	}


	/**
	 * Get related node entities of() the specified class as Results.
	 *
	 * If related node entities exist but do not match the class, an empty array will be returned.
	 *
	 * @template N of Node
	 * @param ?class-string<N> $class
	 * @return NodeResults<N>
	 */
	public function get(?string $class = NULL): NodeResults
	{
		$nodes = [];
		$index = [];

		foreach ($this as $edge) {
			foreach ($this->types as $type) {
				$node = match ($type) {
					Link::to   => $edge->__element__->target->as($class),
					Link::from => $edge->__element__->soruce->as($class)
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
	public function using(Link ...$types): static
	{
		$this->types = $types;

		return $this;
	}
}
