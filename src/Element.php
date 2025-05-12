<?php

namespace FluidGraph;

use InvalidArgumentException;

/**
 *
 */
abstract class Element
{
	use DoesWith;

	/**
	 *
	 */
	abstract public protected(set) ?Content\Element $__content__ {
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
	 *
	 */
	static public function onCreate(Content\Element $content): void
	{
		for($class = static::class; $class != self::class; $class = get_parent_class($class)) {
			self::doHooks($class, Element\CreateHook::class, $content);
		}
	}


	/**
	 *
	 */
	static public function onUpdate(Content\Element $content): void
	{
		for($class = static::class; $class != self::class; $class = get_parent_class($class)) {
			self::doHooks($class, Element\UpdateHook::class, $content);
		}
	}


	/**
	 *
	 */
	static protected function doHooks(string $class, string $hook, Content\Element $content): void
	{
		foreach (class_uses($class) as $trait) {
			self::doHooks($trait, $hook, $content);

			if (!in_array($hook, class_uses($trait))) {
				continue;
			}

			$parts  = explode('\\', $trait);
			$method = lcfirst(end($parts));

			static::$method($content);
		}
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
				'__content__',
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

			$this->__content__ = NULL;
		});
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
		if (!isset($this->__content__->identity)) {
			return NULL;
		}

		return $this->__content__->identity;
	}


	/**
	 * Determine whether or not this element is an expression of another element or element content
	 */
	public function is(Element|Content\Element $element): bool
	{
		if ($element instanceof Element) {
			if ($this === $element) {
				return TRUE;
			}

			if ($this->__content__ === $element->__content__) {
				return TRUE;
			}

		} else {
			if ($this->__content__ === $element) {
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
			return in_array($this->__content__->status, $statuses);
		}

		return $this->__content__->status;
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
					'__content__'
				]);
			},
			ARRAY_FILTER_USE_KEY
		);
	}
}
