<?php

namespace FluidGraph\Testing;

use FluidGraph\Node;

class Publisher extends Node
{
	public function __construct(
		public string $name
	) { }
}
