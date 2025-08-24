<?php

namespace FluidGraph;

use Closure;

use function FluidGraph\Where\all;
use function FluidGraph\Where\eq;

trait DoesMatch
{
	/**
	 *
	 */
	protected private(set) int $limit = -1;


	/**
	 *
	 */
	protected private(set) int $offset = 0;


	/**
	 * @param array<Order>
	 */
	protected private(set) array $orders = [];


	/**
	 *
	 */
	protected private(set) ?Closure $terms = NULL;


	/**
	 *
	 */
	public function skip(int $offset): static
	{
		$this->offset = $offset;

		return $this;
	}


	/**
	 *
	 */
	public function sort(Order ...$orders): static
	{
		$this->orders = $orders;

		return $this;
	}


	/**
	 *
	 */
	public function take(?int $limit): static
	{
		if (is_null($limit)) {
			$limit = -1;
		}

		$this->limit = $limit;

		return $this;
	}


	/**
	 *
	 */
	public function where(callable|array $terms): static
	{
		if (!empty($terms)) {
			if (is_array($terms)) {
				$terms = fn() => all(eq($terms));
			}

			$this->terms = $terms;
		}

		return $this;
	}
}
