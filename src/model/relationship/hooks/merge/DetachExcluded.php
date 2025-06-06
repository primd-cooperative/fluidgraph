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
		$valid_subject = $this->subject->__element__->status(
			Status::INDUCTED,
			Status::ATTACHED
		);

		foreach ($this->loaded as $hash => $edge) {
			if (!$this->reverse) {
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

			if ($valid_subject && $valid_concern && !isset($this->active[$hash])) {
				$graph->detach($edge);
			}
		}
	}
}
