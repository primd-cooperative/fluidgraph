<?php

namespace FluidGraph;

use ArrayObject;

/**
 * @template T
 * @extends ArrayObject<T>
 */
class Results extends ArrayObject
{
	/**
	 * Get the element results as an array of entities of a particular class.
	 *
	 *
	 * @param class-string<Entity> $class The entity class to instantiate as.
	 * @param array<string, mixed> $defaults Default values for entity construction (if necessary)
	 * @return Results<T>
	 */
	public function as(string $class, array $defaults = []): static
	{
		return new self(array_map(
			fn($result) =>
				$result->as($class, $defaults),
			$this->getArrayCopy()
		));
	}


	/**
	 *
	 */
	public function by(string|callable $index): static
	{
		$results = [];

		if (is_string($index)) {
			$index = function($result) use ($index) {
				return $result->$index;
			};
		}

		foreach ($this as $result) {
			$results[$index($result)] = $result;
		}

		return new Results($results);
	}


	/**
	 *
	 */
	public function is(Element|Entity|string $class): static
	{
		return $this->filter(fn($result) => $result->is($class));
	}


	/**
	 *
	 */
	public function filter(callable $condition): static
	{
		return new Results(array_filter(
			$this->getArrayCopy(),
			$condition
		));
	}
}
