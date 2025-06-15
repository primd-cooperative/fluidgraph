<?php

namespace FluidGraph\Relationship;

/**
 * A ToOne is a relationship to a single node independent of the source node.
 *
 * When the source node is attached, so is the target node.
 * When the source node is detached, the target node lives on.
 */
class One extends LinkOneOne
{
	use AttachIncluded;
	use DetachExcluded;
}
