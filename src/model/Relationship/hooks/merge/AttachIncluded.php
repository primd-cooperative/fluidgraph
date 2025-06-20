<?php

namespace FluidGraph\Relationship;

use FluidGraph\Status;
use FluidGraph\Reference;

/**
 *
 */
trait AttachIncluded
{
	use MergeHook;

	/**
	 *
	 */
	public function mergeAttachIncluded(): void
	{
		$valid_subject = $this->subject->__element__->status(
			Status::inducted,
			Status::attached
		);

		foreach ($this->active as $edge) {
			if ($this->type == Reference::to) {
				$valid_concern = !$edge->__element__->target->status(
					Status::released,
					Status::detached
				);
			} else {
				$valid_concern = !$edge->__element__->source->status(
					Status::released,
					Status::detached
				);
			}

			if ($valid_subject && $valid_concern) {
				$this->graph->attach($edge);
			}
		}
	}
}
