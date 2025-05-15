<?php

namespace FluidGraph\Relationship;

use FluidGraph\Graph;
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
	public function detachRelated(Graph $graph)
	{
		if ($this->source->status() == Status::RELEASED) {
			$graph->detach(...array_map(
				function($edge) {
					return $edge()->target;
				},
				$this->included
			));
		}

		$graph->detach(...array_map(
			function($edge) {
				return $edge()->target;
			},
			$this->excluded
		));
	}
}
