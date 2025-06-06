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
	public function mergeAttachIncluded(Graph $graph): void
	{
		$valid_subject = $this->subject->__element__->status(
			Status::INDUCTED,
			Status::ATTACHED
		);

		foreach ($this->active as $edge) {
			if ($this->method == Method::TO) {
				$valid_concern = !$edge->__element__->target->status(
					Status::RELEASED,
					Status::DETACHED
				);
			} else {
				$valid_concern = !$edge->__element__->source->status(
					Status::RELEASED,
					Status::DETACHED
				);
			}

			if ($valid_subject && $valid_concern) {
				$graph->attach($edge);
			}
		}
	}
}
