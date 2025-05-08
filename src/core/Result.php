<?php
namespace FluidGraph;

use ArrayAccess;
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
		protected Content\Base $element
	) {}


	/**
	 * Get the result as an instance of a given class
	 */
	public function as(string $class): ?Node
	{
		if (!$this->element) {
			return NULL;
		}

		return $this->graph->make($this->element, $class);
	}


	/**
	 * Determine whether or not the result is an edge
	 */
	public function isEdge(): bool
	{
		return $this->element instanceof Content\Edge;
	}


	/**
	 * Determine whether or not the result is a node
	 */
	public function isNode(): bool
	{
		return $this->element instanceof Content\Node;
	}


	/**
	 * {@inheritDoc}
	 */
	public function offsetExists(mixed $offset): bool
	{
		return property_exists($this->element->original, $offset);
	}


	/**
	 * {@inheritDoc}
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->offsetExists($offset) ? $this->element->original->$offset : NULL;
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
		return $this->element;
	}
}
