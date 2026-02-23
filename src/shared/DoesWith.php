<?php

namespace FluidGraph;

use Closure;

trait DoesWith
{
	/**
	 * @param-closure-this static $callback
	 * @param-closure-scope static $callback
	 */
	public function with(Closure $callback, mixed ...$args): static
	{
		$callback->bindTo($this, $this)(...$args);

		return $this;
	}
}
