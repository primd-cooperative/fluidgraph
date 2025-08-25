<?php

namespace FluidGraph\Where;

use FluidGraph\Node;
use FluidGraph\Scope;
use FluidGraph\Element;
use DateTime;

/**
 * @return callable:callable|null
 */
function all(callable ...$parts): callable
{
	return fn($where) => $where->all(...$parts);
}

/**
 * @return callable:callable|null
 */
function any(callable ...$parts): callable
{
	return fn($where) => $where->any(...$parts);
}

/**
 * @return callable:callable
 */
function dateTime(DateTime|string|callable $term): callable
{
	return fn($where) => $where->dateTime($term);
}

/**
 * @return callable:callable|array
 */
function eq(array|string|callable $term, mixed $value = NULL): callable
{
	return fn($where) => $where->eq($term, $value);
}

/**
 * @return callable:callable|array
 */
function gt(array|string|callable $term, mixed $value = NULL): callable
{
	return fn($where) => $where->gt($term, $value);
}

/**
 * @return callable:callable|array
 */
function gte(array|string|callable $term, mixed $value = NULL): callable
{
	return fn($where) => $where->gte($term, $value);
}

/**
 * @return callable:callable|string
 */
function id(Node|Element\Node|int $node): callable
{
	return fn($where) => $where->id($node);
}

/**
 * @return callable:callable|array
 */
function like(array|string|callable $term, mixed $value = NULL): callable
{
	return fn($where) => $where->like($term, $value);
}

/**
 * @return callable:callable
 */
function lower(string|callable $term): callable
{
	return fn($where) => $where->lower($term);
}

/**
 * @return callable:callable|array
 */
function lt(array|string|callable $term, mixed $value = NULL): callable
{
	return fn($where) => $where->gt($term, $value);
}

/**
 * @return callable:callable|array
 */
function lte(array|string|callable $term, mixed $value = NULL): callable
{
	return fn($where) => $where->gte($term, $value);
}

/**
 * @return callable:callable
 */
function md5(string|callable $term): callable
{
	return fn($where) => $where->md5($term);
}

/**
 * @return callable:callable|array
 */
function neq(array|string|callable $term, mixed $value = NULL): callable
{
	return fn($where) => $where->neq($term, $value);
}

/**
 * @return callable:callable|array
 */
function null(array|string|callable $term): callable
{
	return fn($where) => $where->null($term);
}


/**
 * @return callable:string
 */
function param(mixed $value = NULL): callable
{
	return fn($where) => $where->param($value);
}


/**
 * @return callable:callable
 */
function source(Node|Element\Node|int $node): callable
{
	return fn($where) => $where->source($node);
}

/**
 * @return callable:callable
 */
function target(Node|Element\Node|int $node): callable
{
	return fn($where) => $where->target($node);
}

/**
 * @return callable:callable
 */
function total(?string $term = NULL): callable
{
	return fn($where) => $where->total($term);
}

/**
 * @return callable:callable
 */
function upper(string|callable $term): callable
{
	return fn($where) => $where->upper($term);
}

/**
 * @return callable:callable
 */
function with(Scope|string $alias, ?callable $callback): callable
{
	return fn($where) => $where->with($alias, $callback);
}
