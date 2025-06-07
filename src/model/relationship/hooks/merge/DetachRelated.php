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
	public function mergeDetachRelated(Graph $graph): void
	{
		foreach ([$this->loaded, $this->active] as $set) {
			foreach ($set as $edge) {
				$invalid_edge = $edge->__element__->status(
					Status::RELEASED,
					Status::DETACHED
				);

				if ($invalid_edge) {
					if ($this->method == Method::TO) {
						$graph->detach($edge->__element__->target);
					} else {
						$graph->detach($edge->__element__->source);
					}
				}
			}
		}
	}
}
