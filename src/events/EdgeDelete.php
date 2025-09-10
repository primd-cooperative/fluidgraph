<?php

namespace FluidGraph\Events;

use FluidGraph\Element\Edge;

class EdgeDelete
{
	public function __construct(
		public Edge $edge
	) {	}
}
