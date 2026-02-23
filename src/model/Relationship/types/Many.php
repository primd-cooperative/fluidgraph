<?php

namespace FluidGraph\Relationship;

use FluidGraph\Edge;

/**
 * A relationship that allows many nodes connected by a single edge.
 *
 * When the source node is attached, so are the related nodes/edges.
 * When the source node is detached, so are the related edges, the related nodes live on.
 *
 * @template T of Edge
 * @extends LinkOneMany<T>
 */
class Many extends LinkOneMany
{
	use AttachIncluded;
	use DetachExcluded;
}
