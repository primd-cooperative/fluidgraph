<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Relationship;

/**
 * A type of relationship that links to/from many nodes with many edges per node.
 */
abstract class LinkManyMany extends Relationship
{
	/**
	 *
	 */
	public function set(Node $node, array|Edge $data = []): static
	{
		$this->validateNode($node);
		$this->resolveEdge($node, $data);

		return $this;
	}
}
