<?php

use FluidGraph\Node;

class Book extends Node
{
	public function __construct(
		public string $name,
		public int $pages
	) { }
}
