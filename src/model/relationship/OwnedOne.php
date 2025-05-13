<?php

namespace FluidGraph\Relationship;

use FluidGraph;

/**
 * A relationship to a single node where the target node is owned by the source.
 *
 * When the source node is attached, so is the target node.
 * When the source node is detached, so is the target node.
 */
class OwnedOne extends FluidGraph\Relationship
{
	use AttachRelated;
	use DetachRelated;
	use LinkOne;
}
