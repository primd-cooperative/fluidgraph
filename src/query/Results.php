<?php

namespace FluidGraph;

use ArrayObject;

/**
 * @extends ArrayObject<Element>
 */
class Results extends ArrayObject
{
	/**
	 *
	 */
	public function of(string ...$labels): Results
	{
		return new self(array_filter(
			$this->getArrayCopy(),
			function($result) use ($labels) {
				return array_intersect($labels, $result->labels);
			}
		));
	}

	/**
	 *
	 */
	public function as(string $class): array
	{
		return array_map(
			function($result) use ($class) {
				return $result->as($class);
			},
			$this->getArrayCopy()
		);
	}
}
