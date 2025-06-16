<?php

namespace FluidGraph;

use FluidGraph\Direction;
use FluidGraph\Scope;
use InvalidArgumentException;

class Order
{
	/**
	 *
	 */
	static public function by(Direction $direction, string $field): self
	{
		return new self(Scope::concern->value, $direction->value, $field);
	}


	/**
	 *
	 */
	public function validate(string ...$classes): static
	{
		foreach ($classes as $class) {
			if (!class_exists($class, FALSE)) {
				continue;
			}

			if (property_exists($class, $this->field)) {
				return $this;
			}
		}

		throw new InvalidArgumentException(sprintf(
			'Cannot order by "%s", property does not exist',
			$this->field
		));
	}


	/**
	 *
	 */
	protected function __construct(
		public protected(set) string $alias,
		public protected(set) string $direction,
		public protected(set) string $field
	)
	{

	}
}
