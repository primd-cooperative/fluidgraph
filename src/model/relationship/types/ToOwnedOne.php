<?php

namespace FluidGraph\Relationship;

/**
 * A relationship to a single node where the target node is owned by the source.
 *
 * When the source node is attached, so is the target node.
 * When the source node is detached, so is the target node.
 */
class ToOwnedOne extends LinkOneOne
{
	protected Method $method = Method::TO;

	use AttachIncluded;
	use DetachExcluded;
	use DetachRelated;
}
