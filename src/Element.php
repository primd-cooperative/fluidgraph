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
	/**
	 * The latest entity instance of the content
	 */
	readonly public Entity $entity;

	/**
	 * The identity of the element as it is or was in the graph.
	 */
	readonly public int $identity;

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
	 *
	 */
	public function __construct(?Entity $element = NULL)
	{
		if ($element) {
			$this->entity = $element;
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
	 * Get the identity of this element;
	 */
	public function identify(int $identity): static
	{
		$this->identity = $identity;

		return $this;
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

}
