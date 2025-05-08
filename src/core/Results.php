<?php

namespace FluidGraph;

use ArrayObject;

/**
 *
 */
class Results extends ArrayObject
{
	use HasGraph;

	/**
	 *
	 */
	public function as(string $class): ArrayObject
	{
		return new ArrayObject(array_map(
			function($result) use ($class) {
				return $result->as($class);
			},
			$this->getArrayCopy()
		));
	}

	/**
	 *
	 */
	public function raw(): ArrayObject
	{
		return new ArrayObject(array_map(
			function($result) {
				return $result->raw();
			},
			$this->getArrayCopy()
		));
	}
}
