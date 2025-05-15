<?php

namespace FluidGraph;

use Closure;

trait DoesWith
{
	/**
	 * @param Closure<static> $callback
	 */
	public function with(Closure $callback, mixed ...$args): static
	{
		$callback->bindTo($this, $this)(...$args);

		return $this;
	}
}
