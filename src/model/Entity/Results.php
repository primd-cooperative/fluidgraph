<?php

namespace FluidGraph\Entity;

use FluidGraph;
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
		if (is_string($indexer)) {
			$indexer = (fn($result) => $result->$indexer);
		}

		return parent::by($indexer);
	}


	/**
	 *
	 */
	public function map(string|callable $transformer): FluidGraph\Results
	{
		if (is_string($transformer)) {
			$transformer = function(Entity $result) use ($transformer) {
				return  $result->$transformer ?? NULL;
			};
		}

		return parent::map($transformer);
	}


	/**
	 * Merge changes upstream
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
	 * {@inheritDoc}
	 *
	 * This method overloads array filtering behavior such that if the key is numeric an element
	 * will fail to match the filtering if the property represented by the value is not set.  If
	 * the key is not numeric, the key is used as the property and the element will fail to match
	 * if the current property value on the element is not equal to the array item value.
	 */
	public function when(array|callable $filter): static
	{
		if (is_array($filter)) {
			$filter = function($result) use ($filter) {
				foreach ($filter as $property => $condition) {
					if (is_numeric($property)) {
						$property  = $condition;
						$condition = fn() => isset($result->$property);
					} else {
						$condition = fn() => $result->$property ?? NULL == $condition;
					}

					if (!$condition()) {
						return FALSE;
					}

					return TRUE;
				}
			};
		}

		return parent::when($filter);
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
