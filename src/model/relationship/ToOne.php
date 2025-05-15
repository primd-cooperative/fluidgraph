<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FluidGraph\Relationship;

/**
 * A ToOne is a relationship to a single node independent of the source node.
 *
 * When the source node is attached, so is the target node.
 * When the source node is detached, the target node lives on.
 */
class ToOne extends Relationship
{
	use AttachIncluded;
	use DetachExcluded;
	use LinkOne;

	/**
	 * Get the related node entity when it is of the specified type and labels.
	 *
	 * If a related node exists but does not match the class/labels, null will be returned.
	 *
	 * @param class-string<T of Node>
	 * @return T
	 */
	public function of(string $class, string ...$labels): ?Node
	{
		$edge = reset($this->included);

		if (!$edge) {
			return NULL;
		}

		if (!in_array($class, $edge->__element__->target->classes())) {
			return NULL;
		}

		if ($labels && !array_intersect($labels, $edge->__element__->target->labels())) {
			return NULL;
		}

		return $edge->__element__->target->as($class);
	}
}
