<?php

namespace FluidGraph\Entity;

use FluidGraph\Entity;
use FluidGraph\Element;

/**
 * @template T of Entity
 * @extends Element\Results<T>
 */
abstract class Results extends Element\Results
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


	/**
	 *
	 */
	public function map(string|callable $transformer): static
	{
		if (is_string($transformer)) {
			$transformer = function(Entity $result) use ($transformer) {
				if (!isset($result->entity->$transformer)) {
					return NULL;
				}

				return  $result->$transformer;
			};
		}

		return new static(array_map(
			$transformer,
			$this->getArrayCopy()
		));
	}
}
