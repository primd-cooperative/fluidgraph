<?php

namespace FluidGraph\Relationship;

use FluidGraph;

trait HasMany
{
	use AbstractRelationship;

	public function add(FluidGraph\Node|string ...$nodes): static
	{
		return $this;
	}
}
