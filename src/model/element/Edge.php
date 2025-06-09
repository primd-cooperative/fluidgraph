<?php

namespace FluidGraph\Element;

use FluidGraph;
use FluidGraph\Relationship\Method;
use InvalidArgumentException;

/**
 * Content which is particular to an edge.
 */
class Edge extends FluidGraph\Element
{
	/**
	 * The source node from which this edge originates
	 */
	public Node|FluidGraph\Node|null $source = NULL {
		get {
			if ($this->source instanceof FluidGraph\Node) {
				return $this->source->__element__;
			}

			return $this->source;
		}
	}

	/**
	 * The target node to which this edge points
	 */
	public Node|FluidGraph\Node|null $target = NULL{
		get {
			if ($this->target instanceof FluidGraph\Node) {
				return $this->target->__element__;
			}

			return $this->target;
		}
	}

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


	/**
	 *
	 */
	public function for(FluidGraph\Node|Node|string $node, Method ...$methods): bool
	{
		foreach ($methods ?: [Method::FROM, Method::TO] as $method) {
			/**
			 * @var Node
			 */
			$element = match(TRUE) {
				$method == Method::TO   => $this->target,
				$method == Method::FROM => $this->source
			};

			if (!$element->like($node)) {
				return FALSE;
			}
		}

		return TRUE;
	}
}
