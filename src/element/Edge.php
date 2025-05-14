<?php

namespace FluidGraph\Element;

use FluidGraph;

/**
 * Content which is particular to an edge.
 */
class Edge extends FluidGraph\Element
{
	/**
	 * The source node from which this edge originates
	 */
	public protected(set) Node $source;

	/**
	 * The target node to which this edge points
	 */
	public protected(set) Node $target;
}
