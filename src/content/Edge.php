<?php

namespace FluidGraph\Content;

/**
 * Content which is particular to an edge.
 */
class Edge extends Element
{
	/**
	 * The source node from which this edge originates
	 */
	readonly public Node $source;

	/**
	 * The target node to which this edge points
	 */
	readonly public Node $target;


	/**
	 *
	 */
	public function bindTarget(Node &$node): static
	{
		$this->target = &$node;

		return $this;
	}


	/**
	 *
	 */
	public function bindSource(Node &$node): static
	{
		$this->source = &$node;

		return $this;
	}

}
