<?php

namespace FluidGraph;

use Closure;
use InvalidArgumentException;

/**
 *
 */
abstract class Entity
{
	use DoesWith;
	use DoesMake;

	/**
	 *
	 */
	abstract public protected(set) ?Element $__element__ {
		get;
		set;
	}

	/**
	 *
	 */
	static public function key(): array
	{
		return [];
	}


	/**
	 * @return array<string, mixed>
	 */
	static public function onCreate(Element $element): array
	{
		$results = [];

		for($class = static::class; $class != self::class; $class = get_parent_class($class)) {
			$results = array_replace(
				$results,
				self::doHooks($class, Entity\CreateHook::class, $element)
			);
		}

		foreach ($results as $property => $value) {
			if (!isset($element->active[$property])) {
				$element->active[$property] = $value;
			}
		}

		return $results;
	}


	/**
	 * @return array<string, mixed>
	 */
	static public function onUpdate(Element $element): array
	{
		$results = [];

		if (count($element->changes())) {
			for($class = static::class; $class != self::class; $class = get_parent_class($class)) {
				$results = self::doHooks($class, Entity\UpdateHook::class, $element);

				foreach ($results as $property => $value) {
					$element->active[$property] = $value;
				}
			}
		}

		return $results;
	}


	/**
	 * @return array<string, mixed>
	 */
	static protected function doHooks(string $class, string $hook, Element $element): array
	{
		$results = [];

		foreach (class_uses($class) as $trait) {
			$results = array_replace($results, self::doHooks($trait, $hook, $element));

			if (!in_array($hook, class_uses($trait))) {
				continue;
			}

			$parts  = explode('\\', $trait);
			$method = lcfirst(end($parts));

			$results = array_replace($results, static::$method($element));
		}

		return $results;
	}


	/**
	 * Clone an element
	 *
	 * This will create a copy of an element, removing its content and key properties.  If the
	 * element is a node, it will clone the relationship, however, edges and nodes will be
	 * dropped as edges do not contain information about connecting node expressions.
	 */
	public function __clone(): void
	{
		$keys = static::key();

		$clone = array_diff(
			array_keys(get_object_vars($this)),
			[
				'__element__',
				...$keys
			]
		);

		$this->with(function() use ($keys, $clone) {
			foreach ($keys as $property) {
				unset($this->$property);
			}

			foreach ($clone as $property) {
				$value = $this->$property;

				unset($this->$property);

				$this->$property = is_object($value)
					? clone $value
					: $value
				;
			}

			$this->__element__ = NULL;
		});
	}

	/**
	 *
	 */
	public function __invoke(Closure $callback): static
	{
		$callback->bindTo($this, $this)();

		return $this;
	}


	/**
	 * Assign data to the entity/element in a safe/bulk manner
	 */
	public function assign(array $data): static
	{
		$keys         = array_keys($data);
		$properties   = array_keys((array) $this);
		$inaccessible = array_diff($keys, $properties);

		if (count($inaccessible)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot update inaccessible properties: %s',
				implode(', ', $inaccessible)
			));
		}

		foreach ($keys as $property) {
			$this[$property] = $data[$property];
		}

		return $this;
	}


	/**
	 * Get the identity of the element.
	 *
	 * A null identity implies the element is not attached to or persisted in the graph yet.
	 */
	public function identity(): int|null
	{
		if (!isset($this->__element__->identity)) {
			return NULL;
		}

		return $this->__element__->identity;
	}


	/**
	 * Determine whether or not this element is an expression of another element or element content
	 */
	public function is(Entity|Element $element): bool
	{
		if ($element instanceof Entity) {
			if ($this === $element) {
				return TRUE;
			}

			if ($this->__element__ === $element->__element__) {
				return TRUE;
			}

		} else {
			if ($this->__element__ === $element) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Get the status of the element or check if status is one of...
	 *
	 * A null implies that the element has not been attached to the graph yet.
	 */
	public function status(Status ...$statuses): Status|bool|null
	{
		if (count($statuses)) {
			return in_array($this->__element__->status, $statuses);
		}

		return $this->__element__->status;
	}


	/**
	 *
	 */
	public function values(): array
	{
		return array_filter(
			get_object_vars($this) + get_class_vars($this::class),
			function($key) {
				return !in_array($key, [
					'__element__'
				]);
			},
			ARRAY_FILTER_USE_KEY
		);
	}
}
