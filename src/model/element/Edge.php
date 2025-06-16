<?php

namespace FluidGraph\Element;

use FluidGraph;
use FluidGraph\Relationship\Reference;

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
	 * @param null|array|class-string<T>
	 * @param array<string, mixed> $data
	 * @return T
	 */
	public function as(null|array|string $class = NULL, array $data = []): FluidGraph\Edge
	{
		if (is_string($class)) {
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
		foreach ($types ?: [Reference::from, Reference::to] as $type) {
			/**
			 * @var Node
			 */
			$element = match(TRUE) {
				$type == Reference::to   => $this->target,
				$type == Reference::from => $this->source
			};

			if (!$element->is($node)) {
				return FALSE;
			}
		}

		return TRUE;
	}
}
