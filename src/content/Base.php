<?php

namespace FluidGraph\Content;

use stdClass;
use FluidGraph\Status;

abstract class Base
{
	public function __construct(
		public ?int $identity = NULL,
		public stdClass $operative = new stdClass(),
		public stdClass $original = new stdClass(),
		public Status $status = Status::UNMERGED,
		public array $labels = [],
	) {}
}
