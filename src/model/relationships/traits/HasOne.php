<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;

trait HasOne
{
	public function set(Node $node)
	{
		if ($this->contains($node)) {

		}
		$content = $this->graph->fasten($node)->content->getValue($node);

		$this->include()
	}
}
