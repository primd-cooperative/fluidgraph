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
		foreach ([$this->loaded, $this->active] as $set) {
			foreach ($set as $hash => $edge) {
				$invalid_edge = $edge->__element__->status(
					Status::RELEASED,
					Status::DETACHED
				);

				if ($invalid_edge) {
					$graph->detach($edge->__element__->target);
				}
			}
		}
	}
}
