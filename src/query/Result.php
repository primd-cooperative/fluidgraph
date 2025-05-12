<?php
namespace FluidGraph;

use ArrayAccess;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

/**
 *
 */
class Result implements ArrayAccess
{
	use HasGraph;

	/**
	 *
	 */
	public function __construct(
		protected Content\Base $content
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
		if (!$this->content) {
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

		if (!in_array($class, array_keys($this->content->labels))) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s," invalid kind for element with labels: %s',
				$class,
				implode(', ', array_keys($this->content->labels))
			));
		}

		$this->graph->fasten(
			$element = $this->graph->make(
				$class,
				$this->content->loaded,
				Maker::SKIP_CHECKS | Maker::SKIP_ASSIGN
			),
			$this->content
		);

		return $element;
	}


	/**
	 * Determine whether or not the result is an edge
	 */
	public function isEdge(): bool
	{
		return $this->content instanceof Content\Edge;
	}


	/**
	 * Determine whether or not the result is a node
	 */
	public function isNode(): bool
	{
		return $this->content instanceof Content\Node;
	}


	/**
	 * {@inheritDoc}
	 */
	public function offsetExists(mixed $offset): bool
	{
		return array_key_exists($offset, $this->content->loaded);
	}


	/**
	 * {@inheritDoc}
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->offsetExists($offset) ? $this->content->loaded[$offset] : NULL;
	}


	/**
	 * {@inheritDoc}
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new RuntimeException(
			'Cannot set offset "%s" on result, results are read-only',
			$offset
		);
	}


	/**
	 * {@inheritDoc}
	 */
	public function offsetUnset(mixed $offset): void
	{
		throw new RuntimeException(
			'Cannot unset offset "%s" on result, results are read-only',
			$offset
		);
	}


	/**
	 * Get the raw result (as returned by the Graph)
	 */
	public function raw(): Content\Base
	{
		return $this->content;
	}
}
