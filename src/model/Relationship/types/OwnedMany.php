<?php

namespace FluidGraph\Relationship;

/**
 * A relationship that allows many owned nodes connected by a single edge.
 *
 * When the source node is attached, so are the related nodes/edges.
 * When the source node is detached, so are the related nodes/edges.
 */
class OwnedMany extends LinkOneMany
{
	use AttachIncluded;
	use DetachExcluded;
	use DetachRelated;
}
