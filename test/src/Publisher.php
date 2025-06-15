<?php

namespace FluidGraph\Testing;

use FluidGraph\Like;
use FluidGraph\Node;
use FluidGraph\Relationship\Link;
use FluidGraph\Relationship\Many;
use FluidGraph\Relationship\Mode;

class Publisher extends Node
{
	public protected(set) Many $publishing;

	public function __construct(
		public string $name
	) {
		$this->publishing = Many::having(
			$this,
			PublishedBy::class,
			Link::from,
			Like::all,
			[
				Author::class
			],
			Mode::lazy
		);
	}
}
