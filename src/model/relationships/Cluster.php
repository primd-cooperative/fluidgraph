<?php

namespace FluidGraph;

/**
 * A cluster is a relationship to many nodes independent of the source node.
 *
 * When the source node is attached, so are the target nodes.
 * When the source node is detached, the target nodes live on.
 */
class Cluster extends Relationship
{
	use Relationship\HasMany;
	use Relationship\AttachRelated;
}
