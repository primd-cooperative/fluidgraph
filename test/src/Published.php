<?php

use FluidGraph\Edge;

class Published extends Edge
{
	public function __construct(
		public DateTime $date
	) { }
}
