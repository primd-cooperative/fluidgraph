<?php

namespace FluidGraph\Events;

use FluidGraph\Element\Edge;

class EdgeUpdate
{
	public function __construct(
		public Edge $edge
	) {	}
}
