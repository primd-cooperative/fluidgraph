<?php

namespace FluidGraph;

use DateTime;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;

class Where
{
	/**
	 * @var array<string, ReflectionMethod>
	 */
	static protected $methods = [];

	protected string $alias;

	protected int $index = 0;

	protected Query $query;

	public function all(callable ...$parts): callable|null
	{
		if (!count($parts)) {
			return NULL;
		}

		return fn() => '(' . implode(' AND ', array_map(fn($part) => $part(), $parts)) . ')';
	}


	public function any(callable ...$parts): callable|null
	{
		if (!count($parts)) {
			return NULL;
		}

		return fn() => '(' . implode(' OR ', array_map(fn($part) => $part(), $parts)) . ')';
	}

	public function count(?string $term = NULL): callable
	{
		if (!func_num_args()) {
			return fn() => sprintf('count(%s)', $this->alias);
		}

		return $this->wrap(__FUNCTION__, $term);
	}

	public function dateTime(DateTime|string $term)
	{
		if ($term instanceof DateTime) {
			$term = $term->format('c');
		}

		return $this->wrap(__FUNCTION__, $term);
	}

	public function eq(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '=', $condition, $value);
	}

	public function gte(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '>=', $condition, $value);
	}

	public function id(Node|Element\Node|int $node): callable|string
	{
		return fn() => sprintf('id(%s) = %s', $this->alias, $this->param($node));
	}


	public function md5(string|callable $term): callable
	{
		return $this->wrap('util_module.md5', $term);
	}


	public function source(Node|Element\Node|int $node): callable
	{
		return fn() => sprintf('id(startNode(%s)) = %s', $this->alias, $this->param($node));
	}


	public function target(Node|Element\Node|int $node): callable
	{
		return fn() => sprintf('id(endNode(%s)) = %s', $this->alias, $this->param($node));
	}


	public function upper(string|callable $term): callable
	{
		return $this->wrap('toupper', $term);
	}


	public function uses(Query $query): static
	{
		$this->query = $query;

		return $this;
	}


	public function scope(string $alias, ?callable $scope): callable
	{
		if (!count(static::$methods)) {
			$methods = new ReflectionClass($this)->getMethods();

			foreach ($methods as $method) {
				if ($method->isPublic()) {
					static::$methods[strtolower($method->getName())] = $method;
				}
			}
		}

		return function() use ($alias, $scope) {
			$ref = NULL;

			if (isset($this->alias)) {
				$ref = $this->alias;
			}

			$this->alias = $alias;

			$parameters = new ReflectionFunction($scope)->getParameters();
			$arguments  = [];

			foreach ($parameters as $parameter) {
				$param  = $parameter->getName();
				$method = strtolower($param);

				if (!isset(static::$methods[$method])) {
					throw new InvalidArgumentException(sprintf(
						'Cannot scope closure with method "%s", not available',
						$param
					));
				}

				$arguments[$param] = static::$methods[$method]->getClosure($this);
			}

			return $scope(...$arguments)();

			if ($ref) {
				$this->alias = $ref;
			} else {
				unset($this->alias);
			}
		};
	}


	protected function expand(string $function, string $operator, array|string|callable $condition, mixed $value = NULL): callable|array
	{
		if (is_array($condition)) {
			$parts = [];

			foreach ($condition as $condition => $value) {
				$parts[] = $this->$function($condition, $value);
			}

			return $parts;

		} else {
			return function() use ($function, $operator, $condition, $value) {
				if (is_callable($value)) {
					$value = $value(TRUE);
				} else {
					$value = $this->param($value);
				}

				if (is_callable($condition)) {
					while (is_callable($condition)) {
						$condition = $condition();
					}

					return sprintf('%s %s %s', $condition, $operator, $value);

				} else {
					return sprintf('%s.%s %s %s', $this->alias, $condition, $operator, $value);

				}
			};
		}
	}

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
}

