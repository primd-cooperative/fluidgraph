<?php

namespace FluidGraph\Entity;

trait Id
{
	public protected(set) ?string $id;

	static public function key(): array
	{
		return ['id'];
	}
}
