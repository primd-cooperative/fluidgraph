<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Element;
use FluidGraph\NodeResults;
use FluidGraph\EdgeResults;

/**
 * A type of relationship that links to/from many nodes with many edges per node.
 */
abstract class LinkManyMany extends FluidGraph\Relationship
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
	public function for(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		return $this->all()->for($node, ...$nodes);
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
		return $this->of($class)->get($class);
	}


	/**
	 *
	 */
	public function set(Node $node, array $data = []): static
	{
		$this->validateNode($node);

		$hash = $this->resolveEdge($node, $data);

		$this->active[$hash]->assign($data);

		return $this;
	}


	/**
	 *
	 */
	public function unset(Node|Edge $entity): static
	{
		if ($entity instanceof Edge) {
			unset($this->active[spl_object_hash($entity)]);

		} else {
			foreach ($this->of($entity) as $edge) {
				unset($this->active[spl_object_hash($edge)]);
			}

		}

		return $this;
	}
}
