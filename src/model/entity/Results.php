<?php

namespace FluidGraph\Entity;

use FluidGraph;

/**
 * @template T of Entity
 * @extends FluidGraph\Results<T>
 */
abstract class Results extends FluidGraph\Results
{
	/**
	 *
	 */
	public function by(string|callable $indexer): static
	{
		$results = [];

		if (is_string($indexer)) {
			$indexer = function($result) use ($indexer) {
				return $result->$indexer;
			};
		}

		foreach ($this as $result) {
			$results[$indexer($result)] = $result;
		}

		return new static($results);
	}
}
