<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FLuidGraph\Edge;
use FluidGraph\Relationship;

/**
 * A type of relationship that links to/from many nodes with one edge per node.
 */
abstract class LinkOneMany extends Relationship
{
	/**
	 *
	 */
	public function set(Node $node, array|Edge $data = []): static
	{
		$this->validateNode($node);

		$hash = $this->getIndex(Index::active, $node);

		if (!$hash) {
			$hash = $this->resolveEdge($node, $data);
		}

		$this->active[$hash]->assign($data);

		return $this;
	}
}
