<?php

namespace FluidGraph;

use InvalidArgumentException;

/**
 *
 */
abstract class Element
{
	/**
	 *
	 */
	protected Content\Edge|Content\Node|null $__content__ = NULL;


	/**
	 *
	 */
	static public function key(): array
	{
		return [];
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
		$protected = ['__content__', ...static::key()];

		foreach (get_object_vars($this) as $property => $value) {
			unset($this->$property);

			if (in_array($property, $protected)) {
				continue;
			}

			$this->$property = is_object($value)
				? clone $value
				: $value;
		}
	}


	/**
	 * Get the identity of the element.
	 *
	 * A null identity implies the element is not attached to or persisted in the graph yet.
	 */
	public function identity(): int|null
	{
		return !is_null($this->__content__)
			? $this->__content__->identity
			: NULL
		;
	}


	/**
	 * Determine whether or not this element is an expression of another element or element content
	 */
	public function is(Element|Content\Base $element): bool
	{
		if ($element instanceof Element) {
			if ($this === $element) {
				return TRUE;
			}

			if ($this->__content__ && $this->__content__ === $element->__content__) {
				return TRUE;
			}

		} else {
			if ($this->__content__ && $this->__content__ === $element) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Get the status of the element.
	 *
	 * A null status implies that the element has not been attached to the graph yet.
	 */
	public function status(): Status|null
	{
		return !is_null($this->__content__)
			? $this->__content__->status
			: NULL
		;
	}


	/**
	 * Update the data on an element in a safe/bulk manner
	 */
	public function update(array $data): static
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
			$this->$property = $data[$property];
		}

		return $this;
	}
}
