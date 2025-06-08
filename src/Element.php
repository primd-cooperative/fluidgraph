<?php

namespace FluidGraph;

/**
 * The content base provides the common properties for edge and node contents.
 *
 * Content can be thought of as the ontological "being" of an element.  The model edges / nodes
 * are simply expressions of this content, and map their properties to the content.
 */
abstract class Element
{
	use HasGraph;
	use DoesWith;

	/**
	 * The identity of the element as it is or was in the graph.
	 */
	public int $identity;

	/**
	 * The active properties of the element (as managed by/on its models)
	 * @var array<string, mixed>
	 */
	public array $active = [];

	/**
	 * The labels of the element
	 * @var array<string, Status>
	 */
	public array $labels = [];

	/**
	 * The loaded properties of the element (as retreived from the graph)
	 * @var array<string, mixed>
	 */
	public array $loaded = [];

	/**
	 * The status of the element.
	 */
	public ?Status $status = NULL;

	/**
	 * The latest entity instance of the content
	 *
	 * @var array<Entity>
	 */
	public array $entities = [];


	/**
	 * Get the changes to this element by comparing active to loaded values.
	 *
	 * @return array<string, mixed> The properties which have changed and their current values
	 */
	static public function changes(self $element): array
	{
		$changes = self::properties($element);

		foreach ($changes as $property => $value) {
			if (!array_key_exists($property, $element->loaded)) {
				continue;
			}

			if ($value != $element->loaded[$property]) {
				continue;
			}

			unset($changes[$property]);
		}

		return $changes;
	}


	/**
	 * Get the element classes for this content based on labels.
	 *
	 * @return array<class-string<Entity>> The valid entity types
	 */
	static public function classes(self $element): array
	{
		$classes = [];

		foreach (self::labels($element) as $label) {
			if (!class_exists($label)) {
				continue;
			}

			if (!is_subclass_of($label, Entity::class, TRUE)) {
				continue;
			}

			$classes[] = $label;
		}

		return $classes;
	}


	/**
	 * Fasten the element to an entity.
	 *
	 * This converts entity properties to references on the element and sets the element on the
	 * entity itself.  We use visible class properties from the element scope to determine which
	 * properties to look for, but defer to the values currently set on the entity instance.
	 *
	 * NOTE: This will not gracefully handle static properties and all properties that map to the
	 * graph must be publicly readable (although can be protected or privately set).  They,
	 * however, cannot contain property hooks.
	 *
	 */
	static public function fasten(self $element, ?Entity $entity = NULL): void
	{
		if (!$entity) {
			foreach ($element->entities as $entity) {
				self::fasten($element, $entity);
			}

		} else {
			$properties = array_filter(
				get_class_vars($entity::class),
				fn($property) => !in_array($property, [
						'__element__'
					]),
				ARRAY_FILTER_USE_KEY
			);

			if (!isset($element->status)) {
				$element->status = Status::FASTENED;
			}

			if ($entity instanceof Edge) {
				if (!isset($element->labels[$entity::class])) {
					$element->labels[$entity::class] = Status::FASTENED;
				}

			} else {
				for (
					$class = $entity::class;
					$class && $class != Node::class;
					$class = get_parent_class($class)
				) {
					if (!isset($element->labels[$class]) && !$entity::getClass($class)->isAbstract()) {
						$element->labels[$class] = Status::FASTENED;
					}
				}
			}

			//
			// The following executes the callback within the scope of the entity allowing us to
			// set __element__ and assign references to protected/privately set properties.
			//

			$entity->with(
				function(Element $element, $properties): void {
					/**
					 * @var Entity $this
					 */

					$this->__element__ = $element;

					foreach ($properties as $property => $value) {
						if (!array_key_exists($property, $element->active)) {
							$element->active[$property] = $this->$property ?? $value;
						}

						unset($this->$property);

						$this->$property = &$element->active[$property];
					}
				},
				$element,
				$properties
			);

			if (!isset($element->entities[$entity::class])) {
				$element->entities[$entity::class] = $entity;
			}
		}
	}


	/**
	 * Get the key properties for this element based on element classes.
	 *
	 * @return array<string, mixed>
	 */
	static public function key(self $element): array
	{
		$key        = [];
		$properties = [];

		foreach (self::classes($element) as $class) {
			$properties = array_merge($properties, $class::key());
		}

		foreach (array_unique($properties) as $property) {
			if (array_key_exists($property, $element->active)) {
				$key[$property] = $element->active[$property];
			} else {
				$key[$property] = NULL;
			}
		}

		return $key;
	}


	/**
	 * Get the labels (or labels matching specific statuses) for this element
	 * @return array<string>
	 */
	static public function labels(self $element, Status ...$statuses): array
	{
		if (!count($statuses)) {
			$statuses = [Status::FASTENED, Status::ATTACHED];
		}

		return array_keys(
			array_filter(
				$element->labels,
				fn(Status $status) => in_array($status, $statuses, TRUE)
			)
		);
	}


	/**
	 * Get a mapping of properties to values for this element.
	 *
	 * @return array<string, mixed>
	 */
	static public function properties(self $element): array
	{
		return array_filter(
			$element->active,
			fn($value) => !$value instanceof Relationship
		);
	}


	/**
	 * Get the signature (colon separated list) for this element
	 */
	static public function signature(self $element, Status ...$statuses): string
	{
		if (!count($statuses)) {
			$statuses = [Status::ATTACHED, Status::RELEASED];
		}

		return implode(':', self::labels($element, ...$statuses));
	}


	/**
	 * Instantiate a new element
	 *
	 * If the element is being insantiated by an entity either during its construction or when an
	 * attempt is made to access its element, the entity should pass itself into the constructor
	 * so that it can be fastened immediately.
	 */
	public function __construct(?Entity $entity = NULL)
	{
		if ($entity) {
			self::fasten($this, $entity);
		}
	}


	/**
	 * {@inheritDoc}
	 */
	public function __debugInfo()
	{
		return array_filter(
			get_object_vars($this),
			fn($key) => !in_array(
					$key,
					[
						'graph',
						'entities',
					]
				),
			ARRAY_FILTER_USE_KEY
		);
	}


	/**
	 * Instantiate the element as an entity
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
		if (!isset($this->entities[$class])) {
			self::fasten($this, $class::make($this->active + $defaults));
		}

		if ($this instanceof Element\Node) {
			foreach (Element\Node::relationships($this) as $relationship) {
				$relationship->load($this->graph);
			}
		}

		return $this->entities[$class];
	}


	/**
	 * Assign data to the element in a bulk manner
	 *
	 * @param array<string, mixed> $data
	 */
	public function assign(array $data): static
	{
		foreach ($data as $property => $value) {
			$this->active[$property] = $value;
		}

		return $this;
	}


	/**
	 *
	 */
	public function identity(): int|null
	{
		return $this->identity ?? NULL;
	}



	/**
	 * Determine whether or not this element is an expression of another entity, element, or label
	 *
	 * @param Entity|Element|class-string $essence
	 * @param bool $use_labels Whether or not we should check all labels (not just classes)
	 */
	public function is(Entity|Element|string $essence): bool
	{
		return match(TRUE) {
			$essence instanceof Element => $this === $essence,
			$essence instanceof Entity  => $this === $essence->__element__,
			default => in_array($essence, self::classes($this))
		};
	}


	/**
	 * Get the status for, or whether or not a status matches, this element
	 */
	public function status(Status ...$statuses): Status|bool|null
	{
		if (count($statuses) == 1) {
			return $this->status === $statuses[0];
		}

		if ($statuses) {
			return in_array($this->status, $statuses);
		}

		return $this->status;
	}
}
