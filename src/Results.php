<?php

namespace FluidGraph;

use ArrayObject;
use Doctrine\Common\Cache\Psr6\InvalidArgument;
use InvalidArgumentException;

/**
 * @template T
 * @extends ArrayObject<T>
 */
class Results extends ArrayObject
{
	/**
	 * @return null|static|T
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
	 *
	 */
	public function filter(string|callable $filter): static
	{
		if (is_string($filter)) {
			$filter = function(mixed $result) use ($filter) {
				return (string) $result == (string) $filter;
			};
		}

		return new static(array_values(array_filter(
			$this->getArrayCopy(),
			$filter
		)));
	}


	/**
	 *
	 */
	public function first(): mixed
	{
		return $this[0] ?? NULL;
	}


	/**
	 * TODO: Fix and Test
	 */
	public function in(array|self $set): bool
	{
		if (!is_array($set)) {
			$set = $set->getArrayCopy();
		}

		return count(array_intersect($this->getArrayCopy(), $set)) == count($this);
	}


	/**
	 *
	 */
	public function last(): mixed
	{
		return $this[count($this) - 1] ?? NULL;
	}


	/**
	 *
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
	 *
	 */
	public function unwrap()
	{
		return $this->getArrayCopy();
	}
}
