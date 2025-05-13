<?php
namespace FluidGraph;

use ArrayAccess;
use RuntimeException;
use InvalidArgumentException;

/**
 *
 */
class Result
{
	use HasGraph;

	/**
	 *
	 */
	public function __construct(
		private Element $element
	) {}


	/**
	 * Get the result as an instance of a given class
	 *
	 * @template T of Element
	 * @param class-string<T> $class
	 * @return T
	 *
	 */
	public function as(string $class): ?Node
	{
		if (!$this->element) {
			return NULL;
		}

		if (!class_exists($class)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s," no such class exists',
				$class
			));
		}

		if ($this->isNode() && !is_subclass_of($class, Node::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s" from non-Node result',
				$class
			));
		}

		if ($this->isEdge() && !is_subclass_of($class, Edge::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s" from non-Edge result',
				$class
			));
		}

		if (!in_array($class, array_keys($this->element->labels))) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s," invalid kind for element with labels: %s',
				$class,
				implode(', ', array_keys($this->element->labels))
			));
		}

		$entity = $this->graph->make(
			$class,
			$this->element->loaded,
			Builder::SKIP_CHECKS | Builder::SKIP_ASSIGN
		);

		$this->graph->fasten($entity, $this->element);

		return $entity;
	}


	/**
	 * Determine whether or not the result is an edge
	 */
	public function isEdge(): bool
	{
		return $this->element instanceof Element\Edge;
	}


	/**
	 * Determine whether or not the result is a node
	 */
	public function isNode(): bool
	{
		return $this->element instanceof Element\Node;
	}


	/**
	 * Get the raw result (as returned by the Graph)
	 */
	public function raw(): Element
	{
		return $this->element;
	}
}
