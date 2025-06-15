<?php

namespace FluidGraph\Testing;

use FluidGraph\Relationship\Many;
use FluidGraph\Relationship\Link;
use FluidGraph\Relationship\Mode;

use FluidGraph\Node;
use FluidGraph\Like;

class Author extends Node
{
	public Many $writings;


	public function __construct(
		public string $penName
	) {
		$this->writings = Many::having(
			$this,
			Wrote::class,
			Link::to,
			Like::any,
			[
				Book::class
			],
			Mode::lazy
		);
	}
}
