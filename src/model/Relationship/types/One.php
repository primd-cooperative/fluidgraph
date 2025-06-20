<?php

namespace FluidGraph\Relationship;

/**
 * A relationship that allows one node connected by a single edge.
 *
 * When the source node is attached, so is the related node/edge.
 * When the source node is detached, so is the related edge, the related node lives on.
 */
class One extends LinkOneOne
{
	use AttachIncluded;
	use DetachExcluded;
}
