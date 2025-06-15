<?php

namespace FluidGraph\Testing;

use FluidGraph\Direction;
use FluidGraph\Relationship\Many;
use FluidGraph\Relationship\Link;
use FluidGraph\Relationship\Mode;

use FluidGraph\Node;
use FluidGraph\Like;
use FluidGraph\Relationship\One;
use FluidGraph\Relationship\Order;
use FluidGraph\Scope;

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
			Link::to,
			Like::all,
			[
				Publisher::class
			],
			Mode::eager
		);

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

		$this->writings->sort(
			Order::on(Scope::concern, 'date', Direction::asc)
		);
	}
}
