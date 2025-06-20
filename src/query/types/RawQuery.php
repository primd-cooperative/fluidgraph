<?php

namespace FluidGraph\Query;

use FluidGraph;
use FluidGraph\Results;

/**
 *
 */
class RawQuery extends FluidGraph\Query
{
	/**
	 * @var Results<mixed>
	 */
	protected Results $results;

	/**
	 *
	 */
	public function add(string $statement, mixed ...$args): static
	{
		return $this->append($statement, ...$args);
	}


	/**
	 * Get the records and resolve them via the graph.
	 *
	 * Resolving will convert any actual Node/Edge responses into elements and register them with
	 * the graph.  If you need to perform manual resolution or work with raw return data, use
	 * the `records()` method instead.
	 *
	 * @return Results<mixed>
	 */
	public function results(): Results
	{
		if (!isset($this->results)) {
			$this->results = new Results($this->records());
		}

		return $this->results;
	}
}
