<?php

namespace FluidGraph\Query;

use FluidGraph\Scope;
use FluidGraph\Matching;
use FluidGraph\Reference;

function edge(Scope|string $scope, array|string $concerns, Reference $type = Reference::either): string
{
	settype($concerns, 'array');

	if ($scope instanceof Scope) {
		$scope = $scope->value;
	}

	return sprintf(
		'%s-[%s%s]-%s',
		$type == Reference::from
			? '<'
			: '',
		$scope,
		count($concerns)
			? ':' . implode('|', $concerns)
			: '',
		$type == Reference::to
			? '>'
			: ''
	);
}


function node(Scope|string $scope, array|string $concerns, Matching $rule = Matching::all): string
{
	settype($concerns, 'array');

	if ($scope instanceof Scope) {
		$scope = $scope->value;
	}

	return sprintf(
		'(%s%s)',
		$scope,
		count($concerns)
			? ':' . implode($rule->value, $concerns)
			: ''
	);
}
