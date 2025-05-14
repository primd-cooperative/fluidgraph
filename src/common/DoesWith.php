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
		$callback->bindTo($this, $this)();

		return $this;
	}
}
