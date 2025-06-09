<?php

namespace FluidGraph\Relationship;

/**
 * A relationship to many nodes where all target nodes are owned by the source.
 *
 * When the source node is attached, so are the target nodes.
 * When the source node is detached, so are the target nodes.
 */
class ToOwnedMany extends LinkOneMany
{
	protected Method $method = Method::TO;

	use AttachIncluded;
	use DetachExcluded;
	use DetachRelated;
}
