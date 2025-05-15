<?php

namespace FluidGraph\Relationship;

use FluidGraph\Graph;
use FluidGraph\Status;

/**
 *
 */
trait AttachIncluded
{
	use MergeHook;

	/**
	 *
	 */
	public function attachIncluded(Graph $graph)
	{
		$valid_source = $this->source->__element__->status(
			Status::INDUCTED,
			Status::ATTACHED
		);

		foreach ($this->active as $edge) {
			$valid_target = !$edge->__element__->target->status(
				Status::RELEASED,
				Status::DETACHED
			);

			if ($valid_source && $valid_target) {
				$graph->attach($edge);
			}
		}
	}
}
