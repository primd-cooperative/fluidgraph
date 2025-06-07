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
	 * Get the properties which identify this element uniquely.
	 *
	 * @return array<string>
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

		for(
			$class = static::class;
			$class && $class != self::class;
			$class = get_parent_class($class)
		) {
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

		if (count(Element::changes($element))) {
			for(
				$class = static::class;
				$class && $class != self::class;
				$class = get_parent_class($class)
			) {
				$results = self::doHooks($class, Entity\UpdateHook::class, $element);

				foreach ($results as $property => $value) {
					$element->active[$property] = $value;
				}
			}
		}

		return $results;
	}


	/**
	 * @param class-string $hook
	 * @return array<string, mixed>
	 */
	static protected function doHooks(string $class, string $hook, Element $element): array
	{
		$results = [];

		foreach (class_uses($class) ?: [] as $trait) {
			$results = array_replace($results, self::doHooks($trait, $hook, $element));

			if (!in_array($hook, class_uses($trait))) {
				continue;
			}

			$parts  = explode('\\', $trait);
			$method = match($hook) {
				Entity\CreateHook::class => 'create' . end($parts),
				Entity\UpdateHook::class => 'update' . end($parts),
				default => FALSE
			};

			if (!$method || !method_exists($trait, $method)) {
				throw new InvalidArgumentException(sprintf(
					'Invalid hook "%s" specified, method "%s" does not exist',
					$trait,
					$method
				));
			}

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

		$this->with(function() use ($keys, $clone): void {
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
	 * Instantiate an entity as another type of entity
	 *
	 * If an existing entity expressing this element exists, it will be returned.  If not a new
	 * one will be created using the active element properties for construction with a fallback
	 * to defaults provided.
	 *
	 * @param class-string<Entity> $class The entity class to instantiate as
	 * @param array<string, mixed> $defaults Default values for entity construction (if necessary)
	 */
	public function as(string $class, array $defaults = []): Entity
	{
		return $this->__element__->as($class, $defaults);
	}


	/**
	 * Assign data to the entity/element in a safe/bulk manner
	 * @param array<string, mixed> $data
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

		$this->__element__->assign($data);

		return $this;
	}


	/**
	 * Get the identity of the element.
	 *
	 * A null identity implies the element is not attached to or persisted in the graph yet.
	 */
	public function identity(): int|null
	{
		return $this->__element__->identity();
	}


	/**
	 * Determine whether or not this entity is an expression of another entity or element or class
	 *
	 * @param Entity|Element|class-string $essence
	 */
	public function is(Entity|Element|string $essence): bool
	{
		return $this->__element__->is($essence);
	}


	/**
	 * Get the status of the element or check if status is one of...
	 *
	 * A null implies that the element has not been attached to the graph yet.
	 */
	public function status(Status ...$statuses): Status|bool|null
	{
		return $this->__element__->status(...$statuses);
	}
}
