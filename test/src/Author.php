<?php

use FluidGraph\Node;

class Author extends Node
{
	public function __construct(
		protected string $name,
		protected int $age
	) {}
}
