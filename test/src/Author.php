<?php

use FluidGraph\Node;
use FluidGraph\Relationship\ToMany;

class Author extends Node
{
	public ToMany $writings;


	public function __construct(
		public string $penName
	) {
		$this->writings = new ToMany(
			$this,
			Wrote::class,
			[
				Book::class
			]
		);


	}
}
