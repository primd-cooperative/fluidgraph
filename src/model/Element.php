<?php

namespace FluidGraph;

use RuntimeException;

abstract class Element
{
	protected ?Content\Base $__content__ = NULL;

	/**
	 *
	 */
	static public function key(): array
	{
		return [];
	}


	/**
	 *
	 */
	public function __clone(): void
	{
		unset($this->__content__);
	}

	/**
	 *
	 */
	public function id(): int|null
	{
		return !is_null($this->__content__)
			? $this->__content__->identity
			: NULL
		;
	}


	/**
	 *
	 */
	public function status(): Status|null
	{
		return !is_null($this->__content__)
			? $this->__content__->status
			: NULL
		;
	}


	/**
	 * Update the data on an element
	 */
	public function update(array $data): static
	{
		foreach ($data as $property => $value) {
			if (str_starts_with($property, '__') && str_ends_with($property, '__')) {
				continue;
			}

			$this->$property = $value;
		}

		return $this;
	}
}
