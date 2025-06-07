<?php

use FluidGraph\Node;

class Author extends Node
{
	public function __construct(
		public protected(set) string $name,
		public protected(set) int $age
	) {}

	public function setAge(int $years)
	{
		$this->age = $years;
	}
}
