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
	static public function on(Scope $scope, Direction $direction, string $field)
	{
		return new static($scope->value, $direction->value, $field);
	}
}
