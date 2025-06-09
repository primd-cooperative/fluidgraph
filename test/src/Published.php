<?php

namespace FluidGraph\Testing;

use DateTime;
use FluidGraph\Edge;

class Published extends Edge
{
	public function __construct(
		public DateTime $date
	) { }
}
