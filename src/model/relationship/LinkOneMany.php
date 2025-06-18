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
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return EdgeResults<E>

	 */
	public function for(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): EdgeResults
	{
		return $this->all()->for($match, ...$matches);
	}


	/**
	 * Get all edge entities for this relationship that corresponds to any node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return EdgeResults<E>
	 */
	public function forAny(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): EdgeResults
	{
		return $this->all()->forAny($match, ...$matches);
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
}
