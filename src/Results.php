<?php

namespace FluidGraph;

use ArrayObject;

/**
 * @template T of mixed
 * @extends ArrayObject<T>
 */
class Results extends ArrayObject
{
	/**
	 * Get the item(s) at one or more positions
	 *
	 * If a single offset is passed the item in that position will returned, or NULL will be
	 * returned if no item is in that position.  If more than one offset is passed, the result
	 * will be the same result set containing only those items.
	 *
	 * @return null|T|static<T>
	 */
	public function at(int $offset, int ...$offsets): mixed
	{
		$items = [];

		array_unshift($offsets, $offset);

		if (count($offsets) == 1) {
			return $this[$offset] ?? NULL;

		} else {
			foreach ($offsets as $offset) {
				if (isset($this[$offset])) {
					$items[] = $this[$offset];
				}
			}

			return new static($items);
		}
	}


	/**
	 * Filter the results by a callable or an array.
	 *
	 * The default array behavior is to return only results which are in the passed array. Child
	 * result types are allowed to overload this behavior depending on the nature of their
	 * results.
	 */
	public function filter(array|callable $filter): static
	{
		if (is_array($filter)) {
			$filter = function(mixed $result) use ($filter) {
				return in_array($result, $filter);
			};
		}

		return new static(array_values(array_filter(
			$this->getArrayCopy(),
			$filter
		)));
	}


	/**
	 * Get the first item in the results
	 *
	 * If results are empty, NULL will be returned
	 *
	 * @return ?T
	 */
	public function first(): mixed
	{
		return $this[0] ?? NULL;
	}


	/**
	 * Get the first item in the results
	 *
	 * If the results are empty, NULL will be returned
	 *
	 * @return ?T
	 */
	public function last(): mixed
	{
		return $this[count($this) - 1] ?? NULL;
	}


	/**
	 * Return a new base result set containing the results of the mapping transformation
	 *
	 * The mapping transformation can be either a callable or a string.  The standard behavior
	 * for a string argument is to map to sprintf() where the %s in the string will be replaced
	 * with the string representation of the item.  Child result types are allowed to overload
	 * this behavior depending on the nature of their results.
	 */
	public function map(string|callable $transformer): self
	{
		if (is_string($transformer)) {
			$transformer = function(mixed $result) use ($transformer) {
				return sprintf($transformer, $result);
			};
		}

		return new self(array_values(array_map(
			$transformer,
			$this->getArrayCopy()
		)));
	}


	/**
	 *
	 */
	public function slice(int $start, int $count): static
	{
		return new static(array_slice($this->getArrayCopy(), $start, $count));
	}


	/**
	 * Get the copy of the internal results array
	 *
	 * @return array<T>
	 */
	public function unwrap(): array
	{
		return $this->getArrayCopy();
	}
}
