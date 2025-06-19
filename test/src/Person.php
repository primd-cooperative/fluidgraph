<?php

namespace FluidGraph\Testing;

use FluidGraph\Node;
use FluidGraph\Entity;

class Person extends Node
{
	use Entity\Id\Uuid7;

	public function __construct(
		public protected(set) string $name,
		public protected(set) int $age
	) { }

	public function setAge(int $years)
	{
		$this->age = $years;
	}

	public function setName(string $value)
	{
		$this->name = $value;
	}
}
