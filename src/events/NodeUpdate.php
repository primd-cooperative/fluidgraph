<?php

namespace FluidGraph\Events;

use FluidGraph\Element\Node;

class NodeUpdate
{
	public function __construct(
		public Node $node
	) {	}
}
