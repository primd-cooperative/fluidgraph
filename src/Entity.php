<?php

namespace FluidGraph;

use Countable;
use InvalidArgumentException;

/**
 *
 */
abstract class Entity implements Countable
{
	use DoesWith;
	use DoesMake;

	/**
	 * @var ?Element<static>
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
	 * Run all create hook traits on an element
	 *
	 * This method will add or replace properties according to the array structures returned
	 * by each hook trait and return the complete/merged results of all hooks.
	 *
	 * @return array<string, mixed>
	 */
	static public function onCreate(Element $element): array
	{
		$results = self::doHooks(static::class, Entity\CreateHook::class, $element);

		return  $results;
	}


	/**
	 * @return array<string, mixed>
	 */
	static public function onUpdate(Element $element): array
	{
		$results = [];

		if (count(Element::changes($element))) {
			$results = self::doHooks(static::class, Entity\UpdateHook::class, $element);
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

		while ($class && $class != self::class) {
			foreach (class_uses($class) ?: [] as $trait) {
				$results = array_replace($results, self::doHooks($trait, $hook, $element));

				if (!in_array($hook, [$trait, ...class_uses($trait)])) {
					continue;
				}

				$parts  = explode('\\', $hook == $trait ? $class : $trait);
				$method = match($hook) {
					Entity\CreateHook::class => 'create' . end($parts),
					Entity\UpdateHook::class => 'update' . end($parts),
					default => FALSE
				};

				if (!$method || !method_exists($class, $method)) {
					throw new InvalidArgumentException(sprintf(
						'Invalid hook "%s" specified, method "%s" does not exist on "%s"',
						$trait,
						$method,
						$class
					));
				}

				foreach (static::$method($element) as $property => $value) {
					if ($hook == Entity\CreateHook::class && isset($element->active[$property])) {
						continue;
					}

					$results[$property] = $element->active[$property] = $value;
				}
			}

			$class = get_parent_class($class);
		}


		return $results;
	}


	/**
	 * Clone an entity
	 *
	 * This will create a copy of an entity, removing its content and key properties.  If the
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
	 * Instantiate an entity as another specific type of entity or as a prefferred class
	 *
	 * If an existing entity expressing the same element exists, it will be returned.  If not a new
	 * one will be created using the active element properties for construction with a fallback
	 * to defaults provided.
	 *
	 * @template E of Entity
	 * @param null|array<class-string<E>|string>|class-string<E>|string $concerns
	 * @param array<string, mixed> $defaults Default values for entity construction (if necessary)
	 * @return E
	 */
	public function as(null|array|string $concerns = NULL, array $defaults = []): Entity
	{
		return $this->__element__->as($concerns, $defaults);
	}


	/**
	 * Assign data to the entity in a bulk manner
	 *
	 * Unlike on the Element class, this will validate that the keys of the array are valid
	 * properties for the entity making this function safe.
	 *
	 * @param array<string, mixed> $data
	 */
	public function assign(array $data, bool $ignore_inaccessible = FALSE): static
	{
		$keys         = array_keys($data);
		$properties   = array_keys((array) $this);
		$inaccessible = array_diff($keys, $properties);

		if (count($inaccessible)) {
			if ($ignore_inaccessible) {
				foreach ($inaccessible as $key) {
					unset($data[$key]);
				}

			} else {
				throw new InvalidArgumentException(sprintf(
					'Cannot update inaccessible properties: %s',
					implode(', ', $inaccessible)
				));
			}
		}

		$this->__element__->assign($data);

		return $this;
	}


	/**
	 * Enable entities to be counted as if they were collections/results
	 *
	 * This allows people to use PHP's built in `count()` function to determine if the object
	 * they are dealing with contains more than one item.  For fluid APIs, this is useful because
	 * neither a collection nor an entity/element can have any further actions performed that are
	 * meaningful if the count is 0.  That is, it's a simple way to check and break on common
	 * calls like `of()`.
	 */
	public function count(): int
	{
		return 0;
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
	 * Determine whether or not this entity is an expression of another entity, element, or label
	 *
	 * @param Element|Entity|class-string $match
	 */
	public function is(Element|Entity|string $match): bool
	{
		return $this->__element__->is($match);
	}


	/**
	 * Determine whether or not this element `is()` ALL of the provided arguments.
	 *
	 * @param Element|Entity|class-string $match
	 * @param Element|Entity|class-string ...$matches
	 */
	public function of(Element|Entity|string $match, Element|Entity|string ...$matches): bool
	{
		return $this->__element__->of($match, ...$matches);
	}


	/**
	 * Determine whether or not this element `is()` ANY of the provided arguments.
	 *
	 * @param Element|Entity|class-string $match
	 * @param Element|Entity|class-string ...$matches
	 */
	public function ofAny(Element|Entity|string $match, Element|Entity|string ...$matches): bool
	{
		return $this->__element__->ofAny($match, ...$matches);
	}


	/**
	 * Get the status of the entity
	 *
	 * If no states are passed, then this method returns the current Status.  If states are passed
	 * it determines if the entity has a matching status to one of the arguments.
	 */
	public function status(Status ...$statuses): Status|bool
	{
		return $this->__element__->status(...$statuses);
	}
}
