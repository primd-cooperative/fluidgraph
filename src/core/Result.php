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

		if ($this->element instanceof Content\Node && !is_subclass_of($class, Node::class, TRUE)) {
			throw new RuntimeException(sprintf(
				'Cannot make "%s" from non-Node result',
				$class
			));
		}

		if ($this->element instanceof Content\Edge && !is_subclass_of($class, Edge::class, TRUE)) {
			throw new RuntimeException(sprintf(
				'Cannot make "%s" from non-Edge result',
				$class
			));
		}

		$element = new $class(...array_reduce(
			array_keys(get_class_vars($class)),
			function ($properties, $property) {
				if (array_key_exists($property, $this->element->original)) {
					$properties[$property] = $this->element->original[$property];
				}

				return $properties;
			},
			[]
		));

		$this->graph->fasten($this->element, $element);

		return $element;
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
		return array_key_exists($offset, $this->element->original);
	}


	/**
	 * {@inheritDoc}
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->offsetExists($offset) ? $this->element->original[$offset] : NULL;
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
