<?php

namespace FluidGraph\Events;

use FluidGraph\Element\Node;

class NodeDelete
{
	public function __construct(
		public Node $node
	) {	}
}
