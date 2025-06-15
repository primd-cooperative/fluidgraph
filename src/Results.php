<?php

namespace FluidGraph;

use ArrayObject;
use InvalidArgumentException;

/**
 * @template T of Element|Entity
 * @extends ArrayObject<T>
 */
class Results extends ArrayObject
{
	/**
	 * Get the element results as an array of entities of a particular class.
	 *
	 *
	 * @template E of Entity
	 * @param class-string<E> $class The entity class to instantiate as.
	 * @param array<string, mixed> $defaults Default values for entity construction (if necessary)
	 * @return NodeResults<E>|EntityResults<E>
	 */
	public function as(string $class, array $defaults = []): static
	{
		switch (TRUE) {
			case is_subclass_of($class, Node::class, TRUE):
				return new NodeResults(array_map(
					fn($result) => $result->as($class, $defaults),
					$this->getArrayCopy()
				));

			case is_subclass_of($class, Edge::class, TRUE):
				return new EdgeResults(array_map(
					fn($result) => $result->as($class, $defaults),
					$this->getArrayCopy()
				));

			default:
				throw new InvalidArgumentException(sprintf(
					'Cannot treat results as "%s", not a Node or Edge class',
					$class
				));
		}
	}


	/**
	 *
	 */
	public function is(Element|Entity|string $class): static
	{
		return $this->where(fn($result) => $result->is($class));
	}


	/**
	 *
	 */
	public function status(Status $status, Status ...$statuses): static
	{
		array_unshift($statuses, $status);

		return $this->where(fn($result) => $result->status($statuses));
	}


	/**
	 *
	 */
	public function where(callable $condition): static
	{
		return new static(array_filter(
			$this->getArrayCopy(),
			$condition
		));
	}
}
