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
}
