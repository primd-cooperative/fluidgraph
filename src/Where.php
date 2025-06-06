<?php

namespace FluidGraph;

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

		return function() use ($parts) {
			return '(' . implode(' AND ', array_map(fn($part) => $part(), $parts)) . ')';
		};
	}


	public function any(callable ...$parts): callable|null
	{
		if (!count($parts)) {
			return NULL;
		}

		return function() use ($parts) {
			return '(' . implode(' OR ', array_map(fn($part) => $part(), $parts)) . ')';
		};
	}


	public function eq(array|string|callable $condition, mixed $value = NULL): callable|array
	{
		if (is_array($condition)) {
			$hooks = [];

			foreach ($condition as $condition => $value) {
				$hooks[] = $this->eq($condition, $value);
			}

			return $hooks;

		} else {
			return function() use ($condition, $value) {
				if (is_callable($condition)) {
					return sprintf('%s = %s', $condition(), $this->param($value));
				} else {
					return sprintf('%s.%s = %s', $this->alias, $condition, $this->param($value));
				}
			};
		}
	}

	public function id(int $term): callable
	{
		return function() use ($term) {
			return sprintf('id(%s) = %s', $this->alias, $this->param($term));
		};
	}


	public function md5(string|callable $property): callable
	{
		return $this->wrap('util_module.md5', $property);
	}


	public function sourceNode(Node $node): callable
	{
		return function() use ($node) {
			return sprintf('id(startNode(%s)) = %s', $this->alias, $this->param($node->identity()));
		};
	}


	public function targetNode(Node $node): callable
	{
		return function() use ($node) {
			return sprintf('id(endNode(%s)) = %s', $this->alias, $this->param($node->identity()));
		};
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


	protected function wrap(string $function, string|callable $property): callable
	{
		return function() use ($function, $property) {
			if (is_callable($property)) {
				return sprintf($function . '(%s)', $property());
			} else {
				return sprintf($function . '(%s.%s)', $this->alias, $property);
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

