<?php

namespace FluidGraph\Relationship;

use FluidGraph;

/**
 * A cluster is a relationship to many nodes independent of the source node.
 *
 * When the source node is attached, so are the target nodes.
 * When the source node is detached, the target nodes live on.
 */
class ToMany extends FluidGraph\Relationship
{
	use AttachIncluded;
	use DetachExcluded;
	use LinkMany;

	/**
	 * Get the related node entities when they are of the specified class and labels.
	 *
	 * If a related nodes exist but do not match the class/labels, an empty array will be returned.
	 *
	 * @param class-string<T of Node>
	 * @return array<T>
	 */
	public function of(string $class, string ...$labels): array
	{
		return array_filter(
			$this->included,
			function($edge) use ($class, $labels) {
				if (!in_array($class, $edge->__element__->target->classes())) {
					return NULL;
				}

				if ($labels && !array_intersect($labels, $edge->__element__->target->labels())) {
					return NULL;
				}
			}
		);
	}
}
