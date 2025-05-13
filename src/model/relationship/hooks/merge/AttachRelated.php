<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use RuntimeException;

trait AttachRelated
{
	use MergeHook;

	public function attachRelated()
	{
		$this->graph->attach(...$this->included);
	}
}
