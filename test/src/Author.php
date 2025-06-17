<?php

namespace FluidGraph\Testing;

use FluidGraph\Node;
use FluidGraph\Scope;
use FluidGraph\Matching;
use FluidGraph\Direction;
use FluidGraph\Reference;

use FluidGraph\Relationship\One;
use FluidGraph\Relationship\Many;
use FluidGraph\Relationship\Mode;
use FluidGraph\Relationship\Order;

class Author extends Node
{
	public protected(set) Many $writings;

	public protected(set) One $publishedBy;

	public function __construct(
		public string $penName
	) {
		$this->publishedBy = One::having(
			$this,
			PublishedBy::class,
			Reference::to,
			Matching::all,
			[
				Publisher::class
			],
			Mode::eager
		);

		$this->writings = Many::having(
			$this,
			Wrote::class,
			Reference::to,
			Matching::any,
			[
				Book::class
			],
			Mode::lazy
		);

		$this->writings->sort(
			Order::on(Scope::concern, Direction::asc, 'date')
		);
	}
}
