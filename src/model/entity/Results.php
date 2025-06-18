<?php

namespace FluidGraph\Entity;

use FluidGraph\Entity;
use FluidGraph\Element;
use FluidGraph\Relationship;

/**
 * @template T of Entity
 * @extends Element\Results<T>
 */
class Results extends Element\Results
{
	/**
	 *
	 */
	protected ?Relationship $relationship = NULL;


	/**
	 *
	 */
	protected array $removed = [];


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


	/**
	 *
	 */
	public function merge(): static
	{
		if ($this->relationship) {
			$this->relationship->merge(TRUE);
		}

		$copy = $this->getArrayCopy();

		while ($key = array_pop($this->removed) !== NULL) {
			unset($copy[$key]);
		}

		$this->exchangeArray(array_values($copy));

		return $this;
	}


	/**
	 *
	 */
	public function using(?Relationship $relationship): static
	{
		$this->relationship = $relationship;

		return $this;
	}
}
