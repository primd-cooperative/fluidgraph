<?php

namespace FluidGraph;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

trait DoesMake
{
	const MAKE_ASSIGN = 1;

	/**
	 * @var array<class-string, ReflectionClass>
	 */
	static private $classes = [];

	/**
	 * @var array<class-string, ReflectionMethod|null>
	 */
	static private $constructors = [];

	/**
	 * @var array<class-string, array<ReflectionParameter>>
	 */
	static private $parameters = [];

	/**
	 * @template T of Entity
	 * @param class-string<T> $class
	 * @return T
	 */
	static public function make(array $data = [], int $flags = 0): Entity
	{
		$required = [];
		$missing  = [];

		foreach (static::getParameters(static::class) as $parameter) {
			$name = $parameter->getName();

			if (!$parameter->isPromoted()) {
				continue;
			}

			if ($parameter->isOptional()) {
				continue;
			}

			if (!array_key_exists($name, $data)) {
				$missing[] = $name;
			}

			$required[$name] = $data[$name];
		}

		if (count($missing)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot make "%s" from result, missing required values for: %s',
				static::class,
				implode(', ', $missing)
			));
		}

		if ($flags & static::MAKE_ASSIGN) {
			return new static(...$required)->assign(array_filter(
				$data,
				function ($key) use ($required) {
					return !in_array($key, $required);
				},
				ARRAY_FILTER_USE_KEY
			));
		} else {
			return new static(...$required);
		}
	}


	/**
	 *
	 */
	static protected function getClass(string $class): ReflectionClass
	{
		if (!array_key_exists($class, self::$classes)) {
			if (!class_exists($class)) {
				throw new InvalidArgumentException(sprintf(
					'Cannot make "%s": no such class exists',
					$class
				));
			}

			if (!is_subclass_of($class, self::class, TRUE)) {
				throw new InvalidArgumentException(sprintf(
					'Cannot make of "%s": not a child of "%s"',
					$class,
					self::class
				));
			}

			self::$classes[$class] = new ReflectionClass($class);
		}

		return self::$classes[$class];
	}


	/**
	 *
	 */
	static protected function getConstructor(string $class): ReflectionMethod|null
	{
		if (!array_key_exists($class, self::$constructors)) {
			self::$constructors[$class] = static::getClass($class)->getConstructor();
		}

		return self::$constructors[$class];
	}

	/**
	 *
	 */
	static protected function getParameters(string $class): array
	{
		if (!array_key_exists($class, self::$parameters)) {
			self::$parameters[$class] = static::getConstructor($class)
				? self::$constructors[$class]->getParameters()
				: [];
		}

		return self::$parameters[$class];
	}
}
