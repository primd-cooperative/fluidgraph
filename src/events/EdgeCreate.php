<?php

namespace FluidGraph\Events;

use FluidGraph\Element\Edge;

class EdgeCreate
{
	public function __construct(
		public Edge $edge
	) {	}
}
