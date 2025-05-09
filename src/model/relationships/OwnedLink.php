<?php

namespace FluidGraph;

/**
 * An owned link is a relationship to a single node where the target node is owned by the source.
 *
 * When the source node is attached, so is the target node.
 * When the source node is detached, so is the target node.
 */
class OwnedLink extends Link
{
	use Relationship\DetachRelated;
}
