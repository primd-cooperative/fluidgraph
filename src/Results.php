<?php

namespace FluidGraph;

use ArrayObject;
use Doctrine\Common\Cache\Psr6\InvalidArgument;
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
	 * @param null|array|class-string<E> $class The entity class to instantiate as.
	 * @param array<string, mixed> $defaults Default values for entity construction (if necessary)
	 * @return NodeResults<E>|EntityResults<E>|Results<E>
	 */
	public function as(null|array|string $class, array $defaults = []): static
	{
		if (is_string($class)) {
			return match(TRUE) {
				is_subclass_of($class, Node::class, TRUE) => new NodeResults(
					array_map(
						fn($result) => $result->as($class, $defaults),
						$this->getArrayCopy()
					)
				),

				is_subclass_of($class, Edge::class, TRUE) => new EdgeResults(
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

		return new self(
			array_map(
				fn($result) => $result->as($class, $defaults),
				$this->getArrayCopy()
			)
		);
	}


	/**
	 *
	 */
	public function of(Element|Entity|string $class): static
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
