<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FLuidGraph\Edge;
use FluidGraph\Element;
use FluidGraph\EdgeResults;
use FluidGraph\Relationship;

/**
 * A type of relationship that links to/from many nodes with one edge per node.
 */
abstract class LinkOneMany extends Relationship
{
	/**
	 * Get all edge entities for this relationship that corresponds to all node(s)/label(s)
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
	 * Get all edge entities for this relationship that corresponds to any node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return EdgeResults<E>
	 */
	public function forAny(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		return $this->all()->forAny($node, ...$nodes);
	}


	/**
	 *
	 */
	public function set(Node $node, array|Edge $data = []): static
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
	public function unset(null|Node|Edge $node): static
	{
		unset($this->active[$this->getIndex($node)]);

		return $this;
	}
}
