<?php

namespace FluidGraph\Relationship;

use FluidGraph\Edge;

/**
 * A relationship that allows one owned node connected by a single edge.
 *
 * When the source node is attached, so is the related node/edge.
 * When the source node is detached, so is the related node/edge.
 *
 * @template T of Edge
 * @extends LinkOneOne<T>
 */
class OwnedOne extends LinkOneOne
{
	use AttachIncluded;
	use DetachExcluded;
	use DetachRelated;
}
