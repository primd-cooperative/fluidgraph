<?php

namespace FluidGraph\Relationship;

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
	public function mergeDetachExcluded(): void
	{
		$valid_subject = $this->subject->__element__->status(
			Status::inducted,
			Status::attached
		);

		foreach ($this->loaded as $hash => $edge) {
			if ($this->type == Link::to) {
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

			if ($valid_subject && $valid_concern && !isset($this->active[$hash])) {
				$this->graph->detach($edge);
			}
		}
	}
}
