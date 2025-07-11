<?php

namespace FluidGraph\Testing;

use FluidGraph\Node;
use FluidGraph\Matching;
use FluidGraph\Reference;

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
			Reference::from,
			Matching::all,
			[
				Author::class
			],
			Mode::lazy
		);
	}
}
