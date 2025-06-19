<?php

namespace FluidGraph\Element;

use FluidGraph;
use FluidGraph\Entity;
use FluidGraph\Element;
use FluidGraph\EdgeResults;
use FluidGraph\NodeResults;
use InvalidArgumentException;

/**
 * @template T of Element
 * @extends FluidGraph\Results<T>
 */
class Results extends FluidGraph\Results
{
	/**
	 * Get the element results as an array of entities of a particular class.
	 *
	 * @template E of Entity
	 * @param null|array|class-string<E> $class The entity class to instantiate as.
	 * @param array<string, mixed> $defaults Default values for entity construction (if necessary)
	 * @return NodeResults<E>|EdgeResults<E>|Entity\Results<E>
	 */
	public function as(null|array|string $class, array $defaults = []): Entity\Results
	{
		if (is_string($class)) {
			return match(TRUE) {
				is_subclass_of($class, FluidGraph\Node::class, TRUE) => new NodeResults(
					array_map(
						fn($result) => $result->as($class, $defaults),
						$this->getArrayCopy()
					)
				),

				is_subclass_of($class, FluidGraph\Edge::class, TRUE) => new EdgeResults(
					array_map(
						fn($result) => $result->as($class, $defaults),
						$this->getArrayCopy()
					)
				),

				default => throw new InvalidArgumentException(sprintf(
					'Cannot make results as "%s", must be Node or Edge class',
					$class
				))
			};
		}

		return new Entity\Results(
			array_map(
				fn($result) => $result->as($class, $defaults),
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
	 * Get all the elments matching all concerns as entities
	 *
	 * The list of concerns acts as preferred classes for instantiation.
	 *
	 * @template E of Entity
	 * @param null|array<string-class<E>>|string-class<E> $class
	 * @return NodeResults<E>|EdgeResults<E>|Entity\Results<E>
	 */
	public function get(null|array|string $concerns = NULL): Entity\Results
	{
		$matches = array_filter(!is_array($concerns) ? [$concerns] : $concerns);

		return $matches
			? $this->of(...$concerns)->as($concerns)
			: $this->as(NULL)
		;
	}


	/**
	 * Get all the elments matching any concerns as entities
	 *
	 * The list of concerns acts as preferred classes for instantiation.
	 *
	 * @template E of Entity
	 * @param null|array<string-class<E>>|string-class<E> $class
	 * @return NodeResults<E>|EdgeResults<E>|Entity\Results<E>
	 */
	public function getAny(null|array|string $concerns = NULL): Entity\Results
	{
		$matches = array_filter(!is_array($concerns) ? [$concerns] : $concerns);

		return $matches
			? $this->ofAny(...$concerns)->as($concerns)
			: $this->as(NULL)
		;
	}


	/**
	 *
	 */
	public function map(string|callable $transformer): FluidGraph\Results
	{
		if (is_string($transformer)) {
			$transformer = fn(Element $result) => $result->active[$transformer] ?? NULL;
		}

		return new FluidGraph\Results(array_map(
			$transformer,
			$this->getArrayCopy()
		));
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
