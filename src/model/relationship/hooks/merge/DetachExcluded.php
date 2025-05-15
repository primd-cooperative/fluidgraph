<?php

namespace FluidGraph\Relationship;

use FluidGraph\Graph;

/**
 *
 */
trait DetachExcluded
{
	use MergeHook;

	/**
	 *
	 */
	public function detachExcluded(Graph $graph)
	{
		$graph->detach(...$this->excluded);
	}
}
