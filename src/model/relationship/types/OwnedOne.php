<?php

namespace FluidGraph\Relationship;

/**
 * A relationship that allows one owned node connected by a single edge.
 *
 * When the source node is attached, so is the related node/edge.
 * When the source node is detached, so is the related node/edge.
 */
class OwnedOne extends LinkOneOne
{
	use AttachIncluded;
	use DetachExcluded;
	use DetachRelated;
}
