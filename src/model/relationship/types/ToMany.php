<?php

namespace FluidGraph\Relationship;

/**
 * A cluster is a relationship to many nodes independent of the source node.
 *
 * When the source node is attached, so are the target nodes.
 * When the source node is detached, the target nodes live on.
 */
class ToMany extends LinkOneMany
{
	protected Method $method = Method::TO;

	use AttachIncluded;
	use DetachExcluded;
}
