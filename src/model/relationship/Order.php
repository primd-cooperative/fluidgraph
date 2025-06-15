<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use FluidGraph\Scope;
use FluidGraph\Direction;

class Order extends FluidGraph\Order
{
	/**
	 *
	 */
	static public function on(Scope $scope, string $field, Direction $direction)
	{
		return new static($scope->value, $field, $direction->value);
	}
}
