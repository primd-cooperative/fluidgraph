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
	protected array $entities = [];

	/**
	 *
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
						'entity'
					]
				);
			},
			ARRAY_FILTER_USE_KEY
		);
	}


	/**
	 *
	 */
	public function as(string $class): Entity
	{
		if (!isset($this->entities[$class])) {
			$this->entities[$class] = $class::make($this->active);

			$this->fasten($this->entities[$class]);
		}

		return $this->entities[$class];
	}


	/**
	 * Get the changes to this element by comparing active to loaded values.
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
	 * Get the labels (or labels matching specific statuses) for this element
	 */
	public function labels(Status ...$statuses): array
	{
		if (!count($statuses)) {
			$statuses = [Status::FASTENED, Status::ATTACHED];
		}

		return array_keys(array_filter(
			$this->labels,
			function (Status $status) use ($statuses) {
				return in_array($status, $statuses, TRUE);
			}
		));
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
		if ($statuses) {
			return in_array($this->status, $statuses);
		}

		return $this->status;
	}


	/**
	 * Fasten the content to an entity.
	 *
	 * This converts entity properties to references and sets the content on the element.  If the
	 * content doesn't contain a corresponding property, it is created with the value on the
	 * entity at present.  If no content is provided, new content will be created depending on the
	 * element type.
	 */
	protected function fasten(Entity $entity): void
	{
		if (!isset($this->status)) {
			$this->status = Status::FASTENED;
		}

		if (!isset($this->labels[$entity::class])) {
			$this->labels[$entity::class] = Status::FASTENED;
		}

		$entity->with(
			function(Element $element, $properties) {
				foreach ($properties as $property => $value) {
					if (!array_key_exists($property, $element->active)) {
						$element->active[$property] = isset($this->$property)
							? $this->$property
							: NULL;
					}

					unset($this->$property);

					$this->$property = &$element->active[$property];
				}
			},
			$this,
			array_filter(
				get_class_vars($entity::class),
				function($property) {
					return !in_array($property, [
						'__element__'
					]);
				},
				ARRAY_FILTER_USE_KEY
			)
		);

		if (!isset($this->entities[$entity::class])) {
			$this->entities[$entity::class] = $entity;
		}
	}
}
