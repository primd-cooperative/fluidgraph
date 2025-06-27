<?php

namespace FluidGraph\Events;

use FluidGraph\Element\Node;

class NodeCreate
{
	public function __construct(
		public Node $node
	) {	}
}
