<?php

namespace FluidGraph\Element;

use FluidGraph;
use FluidGraph\Entity;
use FluidGraph\Element;

/**
 * @template T of Element
 * @extends FluidGraph\Results<T>
 */
class Results extends FluidGraph\Results
{
	/**
	 * Convert results
	 *
	 * @template E of Entity
	 * @param array<class-string<E>>|class-string<E> $classes The entity class to instantiate as.
	 * @param array<string, mixed> $defaults Default values for entity construction (if necessary)
	 * @return Entity\Results<E>
	 */
	public function as(array|string $classes, array $defaults = []): Entity\Results
	{
		return new Entity\Results(
			array_map(
				fn($result) => $result->as($classes, $defaults),
				$this->getArrayCopy()
			)
		);
	}


	/**
	 *
	 */
	public function assign(array $data): static
	{
		foreach ($this as $element) {
			$element->assign($data);
		}

		return $this;
	}


	/**
	 *
	 */
	public function by(string|callable $indexer): static
	{
		$results = [];

		if (is_string($indexer)) {
			$indexer = (fn($result) => $result->active[$indexer]);
		}

		foreach ($this as $result) {
			$results[$indexer($result)] = $result;
		}

		return new static($results);
	}


	/**
	 *
	 */
	public function map(string|callable $transformer): FluidGraph\Results
	{
		if (is_string($transformer)) {
			$transformer = fn(Element $result) => $result->active[$transformer] ?? NULL;
		}

		return parent::map($transformer);
	}


	/**
	 * @template E of Entity
	 * @param Element|Entity|string-class<E> $match
	 * @param Element|Entity|string-class<E> ...$matches
	 * @return static<E>
	 */
	public function of(Element|Entity|string $match, Element|Entity|string ...$matches): static
	{
		array_unshift($matches, $match);

		$filter = (fn($result) => $result->of(...$matches));

		return parent::when($filter);
	}


	/**
	 * @template E of Entity
	 * @param Element|Entity|string-class<E> $match
	 * @param Element|Entity|string-class<E> ...$matches
	 * @return static<E>
	 */
	public function ofAny(Element|Entity|string $match, Element|Entity|string ...$matches): static
	{
		array_unshift($matches, $match);

		$filter = (fn($result) => $result->ofAny(...$matches));

		return parent::when($filter);
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
						$condition = fn() => isset($result->active[$property]);
					} else {
						$condition = fn() => $result->active[$property] ?? NULL == $condition;
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
}
