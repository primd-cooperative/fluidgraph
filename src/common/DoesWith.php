<?php

namespace FluidGraph;

use Closure;

trait DoesWith
{
	/**
	 * @param Closure<static> $callback
	 */
	public function with(Closure $callback): static
	{
		Closure::bind($callback, $this, static::class)();

		return $this;
	}
}
