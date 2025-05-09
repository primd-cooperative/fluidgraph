<?php

namespace FluidGraph\Content;

/**
 * Content which is particular to an edge.
 */
class Edge extends Base
{
	/**
	 * The source node from which this edge originates
	 */
	public ?Node $source = NULL;

	/**
	 * The target node to which this edge points
	 */
	public ?Node $target = NULL;

}
