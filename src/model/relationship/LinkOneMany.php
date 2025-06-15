<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use FluidGraph\Node;
use FluidGraph\Element;
use FluidGraph\EdgeResults;
use FluidGraph\NodeResults;

/**
 * A type of relationship that links to/from many nodes with one edge per node.
 */
abstract class LinkOneMany extends FluidGraph\Relationship
{
	/**
	 * Get all edge entities for this relationship, regardless what they correspond to
	 *
	 * @return EdgeResults<E>
	 */
	public function all(): EdgeResults
	{
		return new EdgeResults(array_values($this->active))->using($this->type);
	}


	/**
	 * Get all edge entities for this relationship that corresponds to all node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return EdgeResults<E>

	 */
	public function of(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		return $this->all()->of($node, ...$nodes);
	}


	/**
	 * Get all edge entities for this relationship that corresponds to any node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return EdgeResults<E>
	 */
	public function ofAny(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		return $this->all()->ofAny($node, ...$nodes);
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
		return is_null($class)
			? $this->all()->get($class)
			: $this->of($class)->get($class)
		;
	}


	/**
	 *
	 */
	public function set(Node $node, array $data = []): static
	{
		$this->validateNode($node);

		$hash = $this->getIndex($node);

		if (!$hash) {
			$hash = $this->resolveEdge($node, $data);
		}

		$this->active[$hash]->assign($data);

		return $this;
	}


	/**
	 *
	 */
	public function unset(Node $node): static
	{
		unset($this->active[$this->getIndex($node)]);

		return $this;
	}
}
