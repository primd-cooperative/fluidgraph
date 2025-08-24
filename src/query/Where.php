<?php

namespace FluidGraph;

use DateTime;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;

/**
 * A "where" clause builder that works using collapsing closures
 */
class Where
{
	/**
	 * @var array<string, ReflectionMethod>
	 */
	static protected $methods = [];

	/**
	 *
	 */
	protected string $alias;

	/**
	 *
	 */
	protected int $index = 0;

	/**
	 *
	 */
	public function __construct(protected Query $query) {}

	/**
	 *
	 */
	public function all(callable ...$parts): callable|null
	{
		if (!count($parts)) {
			return NULL;
		}

		return fn() => '(' . implode(' AND ', array_map(fn($part) => $this->reduce($part, ' AND '), $parts)) . ')';
	}


	/**
	 *
	 */
	public function any(callable ...$parts): callable|null
	{
		if (!count($parts)) {
			return NULL;
		}

		return fn() => '(' . implode(' OR ', array_map(fn($part) => $this->reduce($part, ' OR '), $parts)) . ')';
	}


	/**
	 *
	 */
	public function dateTime(DateTime|string $term): callable
	{
		if ($term instanceof DateTime) {
			$term = $term->format('c');
		}

		return $this->wrap(__FUNCTION__, $term);
	}


	/**
	 *
	 */
	public function eq(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '=', $condition, $value);
	}


	/**
	 *
	 */
	public function gt(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '>', $condition, $value);
	}


	/**
	 *
	 */
	public function gte(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '>=', $condition, $value);
	}


	/**
	 *
	 */
	public function id(Node|Element\Node|int $node): callable
	{
		return fn() => sprintf('id(%s) = %s', $this->alias, $this->param($node));
	}


	/**
	 *
	 */
	public function like(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '=~', $value);
	}


	/**
	 *
	 */
	public function lower(string|callable $term): callable
	{
		return $this->wrap('toLower', $term);
	}


	/**
	 *
	 */
	public function lt(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '<', $condition, $value);
	}


	/**
	 *
	 */
	public function lte(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '<=', $condition, $value);
	}


	/**
	 *
	 */
	public function md5(string|callable $term): callable
	{
		return $this->wrap('util_module.md5', $term);
	}


	/**
	 *
	 */
	public function neq(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '!=', $condition, $value);
	}


	/**
	 *
	 */
	public function null(array|string|callable $condition): callable|array
	{
		if (is_array($condition)) {
			$condition = array_combine(
				$condition,
				array_pad([], count($condition), fn() => 'NULL')
			);
		}

		return $this->expand(__FUNCTION__, 'IS', $condition, fn() => 'NULL');
	}


	/**
	 *
	 */
	public function source(Node|Element\Node|int $node): callable
	{
		return fn() => sprintf('id(startNode(%s)) = %s', $this->alias, $this->param($node));
	}


	/**
	 *
	 */
	public function target(Node|Element\Node|int $node): callable
	{
		return fn() => sprintf('id(endNode(%s)) = %s', $this->alias, $this->param($node));
	}


	/**
	 *
	 */
	public function total(?string $term = NULL): callable
	{
		if (is_null($term)) {
			$term = $this->alias;
		}

		return $this->wrap('count', $term);
	}


	/**
	 *
	 */
	public function upper(string|callable $term): callable
	{
		return $this->wrap('toUpper', $term);
	}


	/**
	 *
	 */
	public function with(Scope|string $alias, ?callable $callback): callable
	{
		if ($alias instanceof Scope) {
			$alias = $alias->value;
		}

		if (!count(static::$methods)) {
			$methods = new ReflectionClass($this)->getMethods();

			foreach ($methods as $method) {
				if ($method->isPublic()) {
					static::$methods[strtolower($method->getName())] = $method;
				}
			}
		}

		return function() use ($alias, $callback) {
			$ref = NULL;

			if (isset($this->alias)) {
				$ref = $this->alias;
			}

			$this->alias = $alias;

			$parameters = new ReflectionFunction($callback)->getParameters();
			$arguments  = [];

			foreach ($parameters as $parameter) {
				$param  = $parameter->getName();

				if ($parameter->getType() == $this::class) {
					$arguments[$param] = $this;

				} else {
					$method = strtolower($param);

					if (!isset(static::$methods[$method])) {
						throw new InvalidArgumentException(sprintf(
							'Cannot scope closure with method "%s", not available',
							$param
						));
					}

					$arguments[$param] = static::$methods[$method]->getClosure($this);
				}
			}

			$result = $this->reduce($callback(...$arguments));

			if ($ref) {
				$this->alias = $ref;
			} else {
				unset($this->alias);
			}

			return $result;
		};
	}


	/**
	 *
	 */
	protected function expand(string $function, string $operator, array|string|callable $condition, mixed $value = NULL): callable|array
	{
		if (is_array($condition)) {
			$parts = [];

			foreach ($condition as $condition => $value) {
				$parts[] = $this->$function($condition, $value);
			}

			return $parts;

		} else {
			return function() use ($operator, $condition, $value) {
				if (is_callable($value)) {
					$value = $value(TRUE);
				} else {
					$value = $this->param($value);
				}

				if (is_callable($condition)) {
					return sprintf('%s %s %s', $this->reduce($condition), $operator, $value);
				} else {
					return sprintf('%s.%s %s %s', $this->alias, $condition, $operator, $value);

				}
			};
		}
	}


	/**
	 *
	 */
	protected function param(mixed $value = NULL): string
	{
		if (!func_num_args()) {
			return '$p' . $this->index;

		} else {
			if ($value instanceof Node || $value instanceof Element\Node) {
				$value = $value->identity();
			}

			$this->index++;
			$this->query->set('p' . $this->index, $value);

			return '$p' . $this->index;
		}
	}


	/**
	 *
	 */
	protected function reduce(callable $condition, string $join = ','): string
	{
		while (is_callable($condition)) {
			$condition = $condition($this);

			if (is_array($condition)) {
				return implode($join, array_map($this->reduce(...), $condition));
			}
		}

		return $condition;
	}


	/**
	 *
	 */
	protected function wrap(string $function, string|callable $property): callable
	{
		return function($is_param = FALSE) use ($function, $property) {
			if (is_callable($property)) {
				return sprintf($function . '(%s)', $property($is_param));
			} else {
				if ($is_param) {
					return sprintf($function . '(%s)', $this->param($property));
				} else {
					return sprintf($function . '(%s.%s)', $this->alias, $property);
				}
			}
		};
	}
}
