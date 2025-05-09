<?php

namespace FluidGraph;

/**
 * An owned cluster is a relationship to many nodes where all target nodes are owned by the source.
 *
 * When the source node is attached, so are the target nodes.
 * When the source node is detached, so are the target nodes.
 */
class OwnedCluster extends Cluster
{
	use Relationship\DetachRelated;
}
