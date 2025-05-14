<?php

namespace FluidGraph\Relationship;

use FluidGraph;

/**
 * A ToOne is a relationship to a single node independent of the source node.
 *
 * When the source node is attached, so is the target node.
 * When the source node is detached, the target node lives on.
 */
class ToOne extends FluidGraph\Relationship
{
	use AttachIncluded;
	use DetachExcluded;
	use LinkOne;
}
