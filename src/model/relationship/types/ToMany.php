<?php

namespace FluidGraph\Relationship;

use FluidGraph;

/**
 * A cluster is a relationship to many nodes independent of the source node.
 *
 * When the source node is attached, so are the target nodes.
 * When the source node is detached, the target nodes live on.
 */
class ToMany extends FluidGraph\Relationship\LinkMany
{
	use AttachIncluded;
	use DetachExcluded;
}
