<?php

namespace FluidGraph\Relationship;

trait DetachRelated
{
	use MergeHook;

	public function detachRelated()
	{
		$this->graph->detach(...$this->excluded);
	}
}
