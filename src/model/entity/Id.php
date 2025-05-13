<?php

namespace FluidGraph\Entity;

trait Id
{
	public private(set) ?string $id;

	static public function key(): array
	{
		return ['id'];
	}
}
