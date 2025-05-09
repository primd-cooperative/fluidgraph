<?php

namespace FluidGraph;

/**
 * A link is a relationship to a single node independent of the source node.
 *
 * When the source node is attached, so is the target node.
 * When the source node is detached, the target node lives on.
 */
class Link extends Relationship
{
	use Relationship\HasOne;
	use Relationship\AttachRelated;
}
