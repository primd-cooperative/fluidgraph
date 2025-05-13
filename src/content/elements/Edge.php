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
	public Node $source;

	/**
	 * The target node to which this edge points
	 */
	public Node $target;
}
