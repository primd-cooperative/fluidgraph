<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use FluidGraph\Scope;
use FluidGraph\Direction;

/**
 * An order type specific to relationships where the scope is configurable
 */
class Order extends FluidGraph\Order
{
	/**
	 * Create a new relationship with the desired scope, direction and field.
	 */
	static public function on(Scope $scope, Direction $direction, string $field): static
	{
		return new static($scope->value, $direction->value, $field);
	}
}
