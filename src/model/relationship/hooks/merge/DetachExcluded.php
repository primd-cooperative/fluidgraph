<?php

namespace FluidGraph\Relationship;

use FluidGraph\Graph;
use FluidGraph\Status;

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
		$valid_source = $this->source->__element__->status(
			Status::INDUCTED,
			Status::ATTACHED
		);

		foreach ($this->loaded as $hash => $edge) {
			$valid_target = !$edge->__element__->target->status(
				Status::RELEASED,
				Status::DETACHED
			);

			if ($valid_source && $valid_target && !isset($this->active[$hash])) {
				$graph->detach($edge);
			}
		}
	}
}
