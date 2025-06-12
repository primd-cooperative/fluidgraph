<?php

namespace FluidGraph;

use DateTime;

class Where
{
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


	public function dateTime(DateTime|string $date)
	{
		if ($date instanceof DateTime) {
			$date = $date->format('c');
		}

		return $this->wrap('dateTime', $date);
	}

	public function eq(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '=', $condition, $value);
	}

	public function gte(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		return $this->expand(__FUNCTION__, '>=', $condition, $value);
	}

	public function id(int $term): callable
	{
		return fn() => sprintf('id(%s) = %s', $this->alias, $this->param($term));
	}


	public function md5(string|callable $property): callable
	{
		return $this->wrap('util_module.md5', $property);
	}


	public function sourceNode(Node $node): callable
	{
		return fn() => sprintf('id(startNode(%s)) = %s', $this->alias, $this->param($node->identity()));
	}


	public function targetNode(Node $node): callable
	{
		return fn() => sprintf('id(endNode(%s)) = %s', $this->alias, $this->param($node->identity()));
	}


	public function upper(string|callable $property): callable
	{
		return $this->wrap('toupper', $property);
	}


	public function uses(Query $query): static
	{
		$this->query = $query;

		return $this;
	}


	public function var(string $alias): static
	{
		$this->alias = $alias;

		return $this;
	}


	protected function expand(string $function, string $operator, array|string|callable $condition, mixed $value = NULL): callable|array
	{
		if (is_array($condition)) {
			$hooks = [];

			foreach ($condition as $condition => $value) {
				$hooks[] = $this->$function($condition, $value);
			}

			return $hooks;

		} else {
			return function() use ($operator, $condition, $value) {
				if (is_callable($value)) {
					$value = $value(TRUE);
				} else {
					$value = $this->param($value);
				}

				if (is_callable($condition)) {
					return sprintf('%s %s %s', $condition(), $operator, $value);
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
				return sprintf($function . '(%s)', $property());
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
			$this->index++;
			$this->query->set('p' . $this->index, $value);

			return '$p' . $this->index;
		}
	}
}

