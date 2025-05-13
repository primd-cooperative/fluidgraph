<?php

namespace FluidGraph;

use InvalidArgumentException;
use ReflectionClass;

trait DoesMake
{

	/**
	 * @template T of Element
	 * @param class-string<T> $class
	 * @return T
	 */
	public function make(string $class, array $data = [], int $flags = 0): Element
	{
		if (!$flags & Maker::SKIP_CHECKS) {
			if (!class_exists($class)) {
				throw new InvalidArgumentException(sprintf(
					'Cannot make "%s," no such class exists',
					$class
				));
			}

			if (!is_subclass_of($class, Element::class, TRUE)) {
				throw new InvalidArgumentException(sprintf(
					'Cannot make "%s" from non-element class',
					$class
				));
			}
		}

		$reflection  = new ReflectionClass($class);
		$constructor = $reflection->getConstructor();
		$required    = [];

		if ($constructor) {
			$parameters  = $constructor->getParameters();
			$missing    = [];

			foreach ($parameters as $parameter) {
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
					$class,
					implode(', ', $missing)
				));
			}
		}

		if (!$flags & Maker::SKIP_ASSIGN) {
			return new $class(...$required)->assign(array_filter(
				$data,
				function ($key) use ($required) {
					return !in_array($key, $required);
				},
				ARRAY_FILTER_USE_KEY
			));
		} else {
			return new $class(...$required);
		}
	}
}
