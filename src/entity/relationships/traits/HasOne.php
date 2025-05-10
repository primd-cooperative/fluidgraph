<?php

namespace FluidGraph\Relationship;

use FluidGraph;

trait HasOne
{
	use AbstractRelationship;

	public function set(FluidGraph\Node $node, array $data = []): static
	{
		if (!$this->contains($node)) {
			$this->for($node)[0]->assign($data);
		}

		return $this;
	}
}
