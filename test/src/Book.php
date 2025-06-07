<?php

use FluidGraph\Node;
use FluidGraph\Relationship\Mode;
use FluidGraph\Relationship\ToOne;

class Book extends Node
{
	public ToOne $authorship;

	public function __construct(
		public protected(set) string $name,
		public protected(set) int $pages
	) {
		$this->authorship = new ToOne(
			$this,
			Authored::class,
			[
				Author::class
			],
			Mode::EAGER
		);
	}
}
