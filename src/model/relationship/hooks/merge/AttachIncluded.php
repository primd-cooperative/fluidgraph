<?php

namespace FluidGraph\Relationship;

use FluidGraph\Graph;

/**
 *
 */
trait AttachIncluded
{
	use MergeHook;

	/**
	 *
	 */
	public function attachIncluded(Graph $graph)
	{
		$graph->attach(...$this->included);
	}
}
