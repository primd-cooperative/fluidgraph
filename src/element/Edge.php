<?php

namespace FluidGraph\Element;

use FluidGraph;
use InvalidArgumentException;

/**
 * Content which is particular to an edge.
 */
class Edge extends FluidGraph\Element
{
	/**
	 * The source node from which this edge originates
	 */
	public Node $source;

	/**
	 * The target node to which this edge points
	 */
	public Node $target;

	/**
	 * @type T of FluidGraph\Edge
	 * @param class-string<T>
	 * @param array<string, mixed> $data
	 * @return T
	 */
	public function as(string $class, array $data = []): FluidGraph\Edge
	{
		if (!class_exists($class)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s," no such class exists',
				$class
			));
		}

		if (!is_subclass_of($class, FluidGraph\Edge::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s" from non-Edge result',
				$class
			));
		}

		return parent::as($class, $data);
	}
}
