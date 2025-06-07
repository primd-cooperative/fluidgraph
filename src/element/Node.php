<?php

namespace FluidGraph\Element;

use FluidGraph;
use InvalidArgumentException;

/**
 *
 */
class Node extends FluidGraph\Element
{
	/**
	 * @template T of FluidGraph\Node
	 * @param class-string<T> $class
	 * @param array<string, mixed> $defaults
	 * @return T
	 */
	public function as(string $class, array $defaults = []): FluidGraph\Node
	{
		if (!class_exists($class)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s," no such class exists',
				$class
			));
		}

		if (!is_subclass_of($class, FluidGraph\Node::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s" from non-Node result',
				$class
			));
		}

		return parent::as($class, $defaults);
	}


	/**
	 * Get any relationships for this node
	 *
	 * @return array<FluidGraph\Relationship>
	 */
	public function relationships(): array
	{
		return array_filter(
			$this->active,
			fn($value) => $value instanceof FluidGraph\Relationship
		);
	}
}
