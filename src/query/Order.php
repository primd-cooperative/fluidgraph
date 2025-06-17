<?php

namespace FluidGraph;

use FluidGraph\Scope;
use FluidGraph\Direction;

use InvalidArgumentException;

/**
 * An order type common to all queries where where the scope is not configurable by default
 */
class Order
{
	/**
	 * Create a new relationship with a fixed scope (concern only), direction and field.
	 */
	static public function by(Direction $direction, string $field): self
	{
		return new self(Scope::concern->value, $direction->value, $field);
	}


	/**
	 * Validate that the desired field exists on one or another classes
	 *
	 * @throws InvalidArgumentException When the field cannot be found on any provided classes
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
	 * Prevent external construction as the scope should not normally be configurable
	 */
	protected function __construct(
		public protected(set) string $alias,
		public protected(set) string $direction,
		public protected(set) string $field
	) {}
}
