<?php

namespace FluidGraph\Relationship;

use FluidGraph\Status;

/**
 *
 */
trait DetachRelated
{
	use MergeHook;

	/**
	 *
	 */
	public function detachRelated()
	{
		if ($this->source->status() == Status::RELEASED) {
			$this->graph->detach(...array_map(
				function($edge) {
					return $edge->__element__->target;
				},
				$this->included
			));
		}

		$this->graph->detach(...array_map(
			function($edge) {
				return $edge->__element__->target;
			},
			$this->excluded
		));
	}
}
