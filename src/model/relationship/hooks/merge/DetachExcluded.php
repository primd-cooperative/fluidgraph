<?php

namespace FluidGraph\Relationship;

/**
 *
 */
trait DetachExcluded
{
	use MergeHook;

	/**
	 *
	 */
	public function detachExcluded()
	{
		$this->graph->detach(...$this->excluded);
	}
}
