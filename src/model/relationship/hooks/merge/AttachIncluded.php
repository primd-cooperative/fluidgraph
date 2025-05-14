<?php

namespace FluidGraph\Relationship;

/**
 *
 */
trait AttachIncluded
{
	use MergeHook;

	/**
	 *
	 */
	public function attachIncluded()
	{
		$this->graph->attach(...$this->included);
	}
}
