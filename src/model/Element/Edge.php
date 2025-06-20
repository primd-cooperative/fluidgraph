<?php

namespace FluidGraph\Element;

use FluidGraph;
use FluidGraph\Reference;

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
	public function for(
		Reference $type,
		FluidGraph\Node|Node|string $match,
		FluidGraph\Node|Node|string ...$matches
	): bool {
		array_unshift($matches, $match);

		$types = match ($type) {
			Reference::either => [Reference::from, Reference::to],
			default           => [$type]
		};

		foreach ($types as $type) {
			/**
			 * @var Node
			 */
			$element = match(TRUE) {
				$type == Reference::to   => $this->target,
				$type == Reference::from => $this->source
			};

			if (!$element->of(...$matches)) {
				return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 *
	 */
	public function forAny(
		Reference $type,
		FluidGraph\Node|Node|string $match,
		FluidGraph\Node|Node|string ...$matches
	): bool {
		array_unshift($matches, $match);

		$types = match ($type) {
			Reference::either => [Reference::from, Reference::to],
			default           => [$type]
		};

		foreach ($types as $type) {
			/**
			 * @var Node
			 */
			$element = match(TRUE) {
				$type == Reference::to   => $this->target,
				$type == Reference::from => $this->source
			};

			if (!$element->ofAny(...$matches)) {
				return FALSE;
			}
		}

		return TRUE;
	}
}
