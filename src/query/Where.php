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
	protected array $mode = [];

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
	public function dateTime(DateTime|string|callable $term): callable
	{
		if ($term instanceof DateTime) {
			$term = $term->format('c');
		}

		return $this->wrap(__FUNCTION__, $term);
	}


	/**
	 *
	 */
	public function eq(array|string|callable $term, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '=', $term, $value);
	}


	/**
	 *
	 */
	public function gt(array|string|callable $term, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '>', $term, $value);
	}


	/**
	 *
	 */
	public function gte(array|string|callable $term, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '>=', $term, $value);
	}


	/**
	 *
	 */
	public function id(Node|Element\Node|int $node): callable
	{
		if ($node instanceof Node || $node instanceof Element\Node) {
			$node = $node->identity();
		}

		return fn() => sprintf('id(%s) = %s', $this->alias, $this->param($node)());
	}


	/**
	 *
	 */
	public function like(array|string|callable $term, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '=~', $term, $value);
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
	public function lt(array|string|callable $term, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '<', $term, $value);
	}


	/**
	 *
	 */
	public function lte(array|string|callable $term, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '<=', $term, $value);
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
	public function neq(array|string|callable $term, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '!=', $term, $value);
	}


	/**
	 *
	 */
	public function null(array|string|callable $term): callable|array
	{
		if (is_array($term)) {
			$term = array_combine($term, array_pad([], count($term), $this->literal('NULL')));
		}

		return $this->expand(__FUNCTION__, 'IS', $term, $this->literal('NULL'));
	}


	/**
	 *
	 */
	public function of(string $label): callable
	{
		return fn() => sprintf('%s:%s', $this->alias, $label);
	}


	/**
	 *
	 */
	public function source(Node|Element\Node|int $node): callable
	{
		if ($node instanceof Node || $node instanceof Element\Node) {
			$node = $node->identity();
		}

		return fn() => sprintf('id(startNode(%s)) = %s', $this->alias, $this->param($node)());
	}


	/**
	 *
	 */
	public function target(Node|Element\Node|int $node): callable
	{
		if ($node instanceof Node || $node instanceof Element\Node) {
			$node = $node->identity();
		}

		return fn() => sprintf('id(endNode(%s)) = %s', $this->alias, $this->param($node)());
	}


	/**
	 *
	 */
	public function total(string|callable|null $term = NULL): callable
	{
		if (is_null($term)) {
			$term = $this->literal($this->alias);
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
				return sprintf('%s %s %s', $this->field($condition)(), $operator, $this->param($value)());
			};
		}
	}


	/**
	 *
	 */
	protected function field(string|callable $name): callable
	{
		return function() use ($name) {
			$this->mode[] = 'field';

			return $this->resolve($name);
		};
	}


	/**
	 *
	 */
	protected function literal(string $argument): callable
	{
		return function() use ($argument) {
			$this->mode[] = 'literal';

			return $this->resolve($argument);
		};
	}


	/**
	 *
	 */
	protected function param(mixed $value): callable
	{
		return function() use ($value) {
			$this->mode[] = 'param';

			return $this->resolve($value);
		};
	}


	/**
	 *
	 */
	protected function reduce(string|callable $condition, string $join = ','): string
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
	protected function resolve(mixed $argument): string
	{
		if (is_callable($argument)) {
			return $this->reduce($argument);
		}

		switch (array_pop($this->mode)) {
			case 'field':
				return sprintf('%s.%s', $this->alias, $argument);

			case 'param':
				$this->index++;
				$this->query->set('p' . $this->index, $argument);

				return '$p' . $this->index;

			default:
				return $argument;
		}
	}


	/**
	 *
	 */
	protected function wrap(string $function, mixed $argument): callable
	{
		return function() use ($function, $argument) {
			return sprintf('%s(%s)', $function, $this->resolve($argument));
		};
	}
}
