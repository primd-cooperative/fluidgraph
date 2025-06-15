<?php

namespace FluidGraph\Testing;

use DateTime;
use FluidGraph\Edge;

class Wrote extends Edge
{
	public function __construct(
		public DateTime $date
	) { }
}
