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
	public function mergeDetachRelated(): void
	{
		foreach ([$this->loaded, $this->active] as $set) {
			foreach ($set as $edge) {
				$invalid_edge = $edge->__element__->status(
					Status::released,
					Status::detached
				);

				if ($invalid_edge) {
					if ($this->type == Reference::to) {
						$this->graph->detach($edge->__element__->target);
					} else {
						$this->graph->detach($edge->__element__->source);
					}
				}
			}
		}
	}
}
