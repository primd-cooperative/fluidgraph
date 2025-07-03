<?php

namespace FluidGraph;

use FluidGraph\Relationship\Mode;

use InvalidArgumentException;
use Countable;

/**
 * The content base provides the common properties for edge and node contents.
 *
 * Content can be thought of as the ontological "being" of an element.  The model edges / nodes
 * are simply expressions of this content, and map their properties to the content.
 */
abstract class Element implements Countable
{
	use HasGraph;
	use DoesWith;

	/**
	 * The identity of the element as it is or was in the graph.
	 */
	public int $identity;

	/**
	 * The active properties of the element (as managed by/on its models)
	 *
	 * @var array<string, mixed>
	 */
	public array $active = [];

	/**
	 * The labels of the element
	 *
	 * @var array<class-string|string, Status>
	 */
	public array $labels = [];

	/**
	 * The loaded properties of the element (as retreived from the graph)
	 *
	 * @var array<string, mixed>
	 */
	public array $loaded = [];

	/**
	 * The status of the element.
	 */
	public ?Status $status = NULL;

	/**
	 * The list of instantiated entity instances of this element indexed by class
	 *
	 * @var array<class-string<Entity>, Entity>
	 */
	public array $entities = [];


	/**
	 * Get the changes to an element by comparing active to loaded values.
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
	 * Get the valid classes for an element based on its labels
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
				$element->status = Status::fastened;
			}

			if ($entity instanceof Edge) {
				if (!isset($element->labels[$entity::class])) {
					$element->labels[$entity::class] = Status::fastened;
				}

			} else {
				for (
					$class = $entity::class;
					$class && $class != Node::class;
					$class = get_parent_class($class)
				) {
					if (!isset($element->labels[$class]) && !$entity::getClass($class)->isAbstract()) {
						$element->labels[$class] = Status::fastened;
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
	 * Get the key properties for an element based on its classes.
	 *
	 * @param array<class-string> &
	 * @return array<string, mixed>
	 */
	static public function key(self $element, array &$classes = []): array
	{
		$key        = [];
		$properties = [];

		foreach (self::classes($element) as $class) {
			$class_key  = $class::key();
			$properties = array_merge($properties, $class::key());

			if (count($class_key)) {
				$classes[] = $class;
			}
		}

		foreach (array_unique($properties) as $property) {
			if (array_key_exists($property, $element->active)) {
				$key[$property] = $element->active[$property];
			} else {
				$key[$property] = NULL;
			}
		}

		sort($classes);

		return $key;
	}


	/**
	 * Get the labels (or labels matching specific statuses) for an element
	 * @return array<string>
	 */
	static public function labels(self $element, Status ...$statuses): array
	{
		if (!count($statuses)) {
			$statuses = [Status::fastened, Status::attached];
		}

		return array_keys(
			array_filter(
				$element->labels,
				fn(Status $status) => in_array($status, $statuses, TRUE)
			)
		);
	}


	/**
	 * Get a mapping of properties to values for an element.
	 *
	 * @return array<string, mixed>
	 */
	static public function properties(self $element, bool $exclude_null = FALSE): array
	{
		$properties = array_filter(
			$element->active,
			fn($value) => !$value instanceof Relationship
		);

		if ($exclude_null) {
			$properties = array_filter($properties, fn($value) => !is_null($value));
		}

		return $properties;
	}


	/**
	 * Get the signature (colon separated list) for an element
	 */
	static public function signature(self $element, Status ...$statuses): string
	{
		if (!count($statuses)) {
			$statuses = [Status::attached, Status::released];
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
			fn($key) => !in_array($key, ['graph', 'entities']),
			ARRAY_FILTER_USE_KEY
		);
	}


	/**
	 * Instantiate an element as a specific type of entity or as a prefferred class
	 *
	 * If an existing entity expressing this element exists, it will be returned.  If not a new
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
		if (!is_string($concerns)) {
			$class = $this->getPreferredClass($concerns);

			if (!$class) {
				throw new InvalidArgumentException(sprintf(
					'Cannot instantiate element from preferred types, must specify one of: %s',
					implode(', ', static::classes($this))
				));
			}
		} else {
			$class = $concerns;
		}

		if (!isset($this->entities[$class])) {
			self::fasten($this, $class::make($this->active + $defaults));

			if ($this instanceof Element\Node) {
				foreach (Element\Node::relationships($this) as $relationship) {
					$relationship->on($this->graph);

					if ($relationship->mode != Mode::manual) {
						$relationship->load();
					}
				}
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
	 * Enable elements to be counted as if they were collections/results
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
	 * Get the in-Graph identity of the element.
	 *
	 * For new Elements, i.e. those which are fastened to new Entities, this will be `NULL` until
	 * they are attached and merged/saved in the actual graph.
	 */
	public function identity(): int|null
	{
		return $this->identity ?? NULL;
	}


	/**
	 * Determine whether or not this element is an expression of another entity, element, or label
	 *
	 * @param Element|Entity|class-string $match
	 */
	public function is(Element|Entity|string $match): bool
	{
		if ($match instanceof Entity) {
			$match = $match->__element__;
		}

		if ($match instanceof Element) {
			if ($this === $match) {
				return TRUE;
			}

			$local_classes = [];
			$match_classes = [];
			$required_key  = self::key($match, $match_classes);
			$available_key = array_filter($required_key, fn($v) => !is_null($v));

			// If there's no available key, we do not consider them the same
			if (!$available_key) {
				return FALSE;
			}

			// If the key is not complete, we do not consider them the same
			if (count($required_key) != count($available_key)) {
				return FALSE;
			}

			// If the keys do not match, they are not the same
			if ($required_key != self::key($this, $local_classes)) {
				return FALSE;
			}

			// If there is not overlap in contributing classes, we don't consider them the same
			if ($match_classes != $local_classes) {
				return FALSE;
			}

			return TRUE;

		} else {
			return in_array($match, self::labels($this));
		}
	}


	/**
	 * Determine whether or not this element `is()` ALL of the provided arguments.
	 *
	 * @param Element|Entity|class-string $match
	 * @param Element|Entity|class-string ...$matches
	 */
	public function of(Element|Entity|string $match, Element|Entity|string ...$matches): bool
	{
		array_unshift($matches, $match);

		foreach ($matches as $match) {
			if (!$this->is($match)) {
				return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 * Determine whether or not this element `is()` ANY of the provided arguments.
	 *
	 * @param Element|Entity|class-string $match
	 * @param Element|Entity|class-string ...$matches
	 */
	public function ofAny(Element|Entity|string $match, Element|Entity|string ...$matches): bool
	{
		array_unshift($matches, $match);

		foreach ($matches as $match) {
			if (!$this->is($match)) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Get the status of the element
	 *
	 * If no states are passed, then this method returns the current Status.  If states are passed
	 * it determines if the element has a matching status to one of the arguments.
	 **/
	public function status(Status ...$statuses): Status|bool
	{
		if (count($statuses) == 1) {
			return $this->status === $statuses[0];
		}

		if ($statuses) {
			return in_array($this->status, $statuses);
		}

		return $this->status;
	}


	/**
	 * Get a preferred class (one that the element can instantiate as) from a list of candiates
	 *
	 * If the candidates are null or an empty array, then the preferred class will be the first
	 * real class of which the element is aware.  Otherwise, it will check to see which of all
	 * the candidate classes it is and return the first matching item.
	 *
	 * This method can return NULL, which suggests the element cannot be instantiated as that
	 * class unless forced.
	 *
	 * @param array<class-string|string> $candidates
	 * @return null|class-string
	 */
	protected function getPreferredClass(?array $candidates): ?string
	{
		$class      = NULL;
		$candidates = array_unique(array_filter(
			$candidates ?: [],
			fn($candidate) => is_subclass_of($candidate, Entity::class, TRUE)
		));

		if (empty($candidates)) {
			$class = static::classes($this)[0] ?? NULL;

		} else {
			foreach ($candidates as $candidate) {
				if ($this->is($candidate)) {
					$class = $candidate;
					break;
				}
			}

		}

		return $class;
	}
}
