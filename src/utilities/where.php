<?php

namespace FluidGraph\Where;

use FluidGraph\Scope;

/**
 * @return callable:callable|null
 */
function any(callable ...$parts): callable
{
	return fn($where) => $where->any(...$parts);
}

/**
 * @return callable:callable|null
 */
function with(Scope|string $alias, ?callable $callback): callable
{
	return fn($where) => $where->with($alias, $callback);
}


/**
 * @return callable:callable|array
 */
function eq(array|string|callable $condition, mixed $value = NULL): callable
{
	return fn($where) => $where->eq($condition, $value);
}


/**
 * @return callable:callable|array
 */
function null(array|string|callable $condition): callable
{
	return fn($where) => $where->null($condition);
}
