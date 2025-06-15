<?php

namespace FluidGraph\Element;

use FluidGraph;
use FluidGraph\Relationship\Link;

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
	 * @param ?class-string<T>
	 * @param array<string, mixed> $data
	 * @return T
	 */
	public function as(?string $class = NULL, array $data = []): FluidGraph\Edge
	{
		if (!is_null($class)) {
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
		}

		return parent::as($class, $data);
	}


	/**
	 *
	 */
	public function for(FluidGraph\Node|Node|string $node, Link ...$types): bool
	{
		foreach ($types ?: [Link::from, Link::to] as $type) {
			/**
			 * @var Node
			 */
			$element = match(TRUE) {
				$type == Link::to   => $this->target,
				$type == Link::from => $this->source
			};

			if (!$element->is($node)) {
				return FALSE;
			}
		}

		return TRUE;
	}
}
