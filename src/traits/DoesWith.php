<?php

namespace FluidGraph;

use Closure;

trait DoesWith
{
	/**
	 *
	 */
	public function with(Closure $callback): static
	{
		Closure::bind($callback, $this);

		$callback();

		return $this;
	}
}
