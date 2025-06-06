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
	 */
	public array $active = [];

	/**
	 * The labels of the element
	 */
	public array $labels = [];

	/**
	 * The loaded properties of the element (as retreived from the graph)
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
	 * Instantiate a new element
	 *
	 * If the element is being insantiated by an entity either during its construction or when an
	 * attempt is made to access its element, the entity should pass itself into the constructor
	 * so that it can be fastened immediately.
	 */
	public function __construct(?Entity $entity = NULL)
	{
		if ($entity) {
			$this->fasten($entity);
		}
	}


	/**
	 * {@inheritDoc}
	 */
	public function __debugInfo()
	{
		return array_filter(
			get_object_vars($this),
			function($key) {
				return !in_array(
					$key,
					[
						'graph',
						'entities',
					]
				);
			},
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
			$this->fasten($class::make($this->active + $defaults));
		}

		if ($this instanceof Element\Node) {
			foreach ($this->relationships() as $relationship) {
				$relationship->load($this->graph);
			}
		}

		return $this->entities[$class];
	}


	/**
	 * Get the changes to this element by comparing active to loaded values.
	 *
	 * @return array<string, mixed> The properties which have changed and their current values
	 */
	public function changes(): array
	{
		$changes = $this->properties();

		foreach ($changes as $property => $value) {
			if (!array_key_exists($property, $this->loaded)) {
				continue;
			}

			if ($value != $this->loaded[$property]) {
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
	public function classes(): array
	{
		$classes = [];

		foreach ($this->labels() as $label) {
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
	public function fasten(?Entity $entity = NULL): static
	{
		if (!$entity) {
			foreach ($this->entities as $entity) {
				$this->fasten($entity);
			}

			return $this;
		}

		$properties = array_filter(
			get_class_vars($entity::class),
			function($property) {
				return !in_array($property, [
					'__element__'
				]);
			},
			ARRAY_FILTER_USE_KEY
		);

		if (!isset($this->status)) {
			$this->status = Status::FASTENED;
		}

		if ($entity instanceof Edge) {
			if (!isset($this->labels[$entity::class])) {
				$this->labels[$entity::class] = Status::FASTENED;
			}

		} else {
			for ($class = $entity::class; $class != Node::class; $class = get_parent_class($class)) {
				if (!isset($this->labels[$class]) && !$entity::getClass($class)->isAbstract()) {
					$this->labels[$class] = Status::FASTENED;
				}
			}
		}

		//
		// The following executes the callback within the scope of the entity allowing us to
		// set __element__ and assign references to protected/privately set properties.
		//

		$entity->with(
			function(Element $element, $properties) {
				/**
				 * @var Entity $this
				 */

				$this->__element__ = $element;

				foreach ($properties as $property => $value) {
					if (!array_key_exists($property, $element->active)) {
						$element->active[$property] = isset($this->$property)
							? $this->$property
							: $value;
					}

					unset($this->$property);

					$this->$property = &$element->active[$property];
				}
			},
			$this,
			$properties
		);

		if (!isset($this->entities[$entity::class])) {
			$this->entities[$entity::class] = $entity;
		}

		return $this;
	}


	/**
	 *
	 */
	public function identity(): int|null
	{
		return isset($this->identity)
			? $this->identity
			: NULL
		;
	}


	/**
	 * Get the key properties for this element based on element classes.
	 */
	public function key(): array
	{
		$key        = [];
		$properties = [];

		foreach ($this->classes() as $class) {
			$properties = array_merge($properties, $class::key());
		}

		foreach (array_unique($properties) as $property) {
			if (array_key_exists($property, $this->active)) {
				$key[$property] = $this->active[$property];
			}
		}

		return $key;
	}


	/**
	 * Get the labels (or labels matching specific statuses) for this element
	 */
	public function labels(Status ...$statuses): array
	{
		if (!count($statuses)) {
			$statuses = [Status::FASTENED, Status::ATTACHED];
		}

		return array_keys(
			array_filter(
				$this->labels,
				function (Status $status) use ($statuses) {
					return in_array($status, $statuses, TRUE);
				}
			)
		);
	}


	/**
	 * Get a mapping of properties to values for this element.
	 *
	 * @return array<string, mixed>
	 */
	public function properties(): array
	{
		return array_filter(
			$this->active,
			function($value) {
				return !$value instanceof Relationship;
			}
		);
	}


	/**
	 * Get the signature (colon separated list) for this element
	 */
	public function signature(Status ...$statuses): string
	{
		if (!count($statuses)) {
			$statuses = [Status::ATTACHED, Status::RELEASED];
		}

		return implode(':', $this->labels(...$statuses));
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
