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
	 * @template E of Entity
	 * @param ?string-class E
	 * @return Element\Results<E>|Entity\Results<T>
	 */
	public function get(?string $class = NULL): Entity\Results|Element\Results
	{
		return $class
			? $this->of($class)->as($class)
			: $this->as(NULL)
		;
	}


	/**
	 *
	 */
	public function map(string|callable $transformer): FluidGraph\Results
	{
		if (is_string($transformer)) {
			$transformer = function(Element $result) use ($transformer) {
				if (!isset($result->active[$transformer])) {
					return NULL;
				}

				return $result->active[$transformer];
			};
		}

		return new FluidGraph\Results(array_map(
			$transformer,
			$this->getArrayCopy()
		));
	}


	/**
	 *
	 */
	public function of(Element|Entity|string $match, Element|Entity|string ...$matches): static
	{
		array_unshift($matches, $match);

		$filter = function($result) use ($matches) {
			return $result->of(...$matches);
		};

		return parent::filter($filter);
	}


	/**
	 *
	 */
	public function ofAny(Element|Entity|string $match, Element|Entity|string ...$matches): static
	{
		array_unshift($matches, $match);

		$filter = function($result) use ($matches) {
			return $result->ofAny(...$matches);
		};

		return parent::filter($filter);
	}


	/**
	 *
	 */
	public function when(array|callable $filter): static|FluidGraph\Results
	{
		if (is_array($filter)) {
			$filter = function($result) use ($filter) {
				foreach ($filter as $property => $condition) {
					if (is_numeric($property)) {
						$property  = $condition;
						$condition = fn() => isset($result->active[$property]);
					}

					if (is_callable($condition)) {
						if (!$condition()) {
							return FALSE;
						}

					} else {
						$value = $result->active[$property] ?? NULL;

						if ($value != $condition) {
							return FALSE;
						}
					}
				}
			};
		}

		return parent::filter($filter);
	}
}
