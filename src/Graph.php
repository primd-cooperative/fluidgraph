<?php

namespace FluidGraph;

use Bolt\Bolt;
use Bolt\protocol\IStructure;
use Bolt\protocol\V5_2 as Protocol;
use Bolt\protocol\v5\structures as Struct;

use Closure;
use ArrayObject;
use RuntimeException;
use InvalidArgumentException;
use ReflectionProperty;
use DateTimeZone;
use DateTime;

/**
 *
 */
class Graph
{
	use DoesMake;
	use DoesWith;

	/**
	 * The underlying Bolt protocol acccess
	 */
	public protected(set) Protocol $protocol {
		get {
			if (!isset($this->protocol)) {
				$this->protocol = $this->bolt->setProtocolVersions(5.2)->build();

				$this->protocol->hello()->getResponse();
				$this->protocol->logon($this->login)->getResponse();
			}

			return $this->protocol;
		}
		set(Protocol $protocol) {
			$this->protocol = $protocol;
		}
	}

	/**
	 * An instance of the base query implementation to clone
	 */
	public protected(set) Query $query {
		get {
			return clone $this->query;
		}
		set (Query $query) {
			$this->query = $query;
		}
	}

	/**
	 * An instance of the base queue implementation to clone
	 */
	public protected(set) Queue $queue;

	/**
	 * @var ArrayObject<Element\Edge>
	 */
	protected ArrayObject $edges;

	/**
	 * @var ArrayObject<Element\Node>
	 */
	protected ArrayObject $nodes;

	/**
	 *
	 */
	private Bolt $bolt;

	/**
	 * @var array
	 */
	private array $login;

	/**
	 *
	 */
	private ReflectionProperty $union;

	/**
	 *
	 */
	public function __construct(
		array $login,
		Bolt $bolt,
		Query $query,
		Queue $queue
	) {
		$this->nodes = new ArrayObject();
		$this->edges = new ArrayObject();
		$this->union = new ReflectionProperty(Entity::class, '__element__');
		$this->queue = $queue->on($this)->manage($this->nodes, $this->edges);
		$this->query = $query->on($this);
		$this->login = $login;
		$this->bolt  = $bolt;
	}


	/**
	 *
	 */
	public function attach(Element|Entity ...$elements): static
	{
		foreach ($elements as $element) {
			if ($element instanceof Entity) {
				$entity  = $element;
				$element = $entity->__element__;

				if (!$element->status) {
					$this->fasten($entity);
				}
			}

			switch ($element->status) {
				case Status::FASTENED:
					$identity = spl_object_hash($element);

					if ($element instanceof Element\Node) {
						$target = $this->nodes;
					} elseif ($element instanceof Element\Edge) {
						$target = $this->edges;

						if (isset($element->target->entity)) {
							$this->attach($element->target->entity);
						}

						if (isset($element->source->entity)) {
							$this->attach($element->source->entity);
						}

					} else {
						throw new InvalidArgumentException(sprintf(
							'Unknown element type "%s" on attach()',
							get_class($element)
						));
					}

					$target[$identity] = $element;
					$element->status   = Status::INDUCTED;
					break;

				case Status::RELEASED:
					$element->status = Status::ATTACHED;
					break;

				case Status::DETACHED:
					throw new InvalidArgumentException(sprintf(
						'Cannot attached already detached element: %s',
						$element->identity
					));
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function detach(Element|Entity ...$elements): static
	{
		foreach ($elements as $element) {
			if ($element instanceof Entity) {
				$entity  = $element;
				$element = $entity->__element__;
			}

			switch ($element->status) {
				case Status::INDUCTED:
					$identity = spl_object_hash($element);
					$target   = match(TRUE) {
						$element instanceof Element\Node => $this->nodes,
						$element instanceof Element\Edge => $this->edges,
					};

					$element->status = Status::FASTENED;

					unset($target[$identity]);

					break;

				case Status::ATTACHED:
					$element->status = Status::RELEASED;
			}
		}

		return $this;
	}


	/**
	 * Fasten an element to its content.
	 *
	 * This converts entity properties to references and sets the content on the element.  If the
	 * content doesn't contain a corresponding property, it is created with the value on the
	 * entity at present.  If no content is provided, new content will be created depending on the
	 * element type.
	 */
	public function fasten(Entity $entity, ?Element $element = NULL): static
	{
		if (!$element) {
			$element = $entity->__element__;
		} else {
			$this->union->setRawValue($entity, $element);
		}

		foreach ($entity->values() as $property => $value) {
			if (!array_key_exists($property, $element->active)) {
				$element->active[$property] = $value;
			}

			if ($value instanceof Relationship) {
				$value->on($this);
			}

			Closure::bind(
				function () use ($element, $property) {
					unset($this->$property);

					$this->$property = &$element->active[$property];
				},
				$entity,
				$entity
			)();
		}

		if (!isset($element->labels[$entity::class])) {
			$element->labels[$entity::class] = Status::FASTENED;
		}

		if (!isset($element->status)) {
			$element->status = Status::FASTENED;
		}

		return $this;
	}


	/**
	 * Initiate a merge by constructing a new queue and setting the nodes/edges to be merged.
	 */
	public function merge(): Queue
	{
		return $this->queue->merge(
			$this->nodes,
			$this->edges
		);
	}


	/**
	 * Resolve structures returned from bolt protocol into usable forms.
	 *
	 * TODO: replace property resolution with a plugin system where resolvers can be registered
	 *
	 * The core functionality of this is designed to convert record structures such as nodes
	 * and relationships (edges) into their content representations.
	 */
	public function resolve(IStructure $structure): mixed
	{
		switch(get_class($structure)) {
			case Struct\DateTimeZoneId::class:
				$zone  = new DateTimeZone($structure->tz_id);
				$value = DateTime::createFromFormat(
					'U.u',
					sprintf(
						'%d.%s',
						$structure->seconds,
						substr(sprintf('%09d', $structure->nanoseconds), 0, 6)
					),
					new DateTimeZone($structure->tz_id)
				);

				$value->setTimeZone($zone);

				return $value;

			case Struct\Node::class:
				$labels   = $structure->labels;
				$identity = $structure->element_id;
				$storage  = &$this->nodes;

				if (!isset($storage[$identity])) {
					$storage[$identity] = new Element\Node();
				}

				break;

			case Struct\Relationship::class:
				$labels   = [$structure->type];
				$identity = $structure->element_id;
				$storage  = &$this->edges;

				if (!isset($storage[$identity])) {
					$storage[$identity] = new Element\Edge();
				}
				break;

			default:
				throw new RuntimeException(sprintf(
					'Cannot resolve property of type "%s"',
					get_class($structure)
				));
		}

		$element = $storage[$identity];

		if (!isset($element->identity)) {
			$element->identify($identity);
		}

		if ($element->status != Status::RELEASED) {
			$element->status = Status::ATTACHED;
		}

		foreach ($labels as $label) {
			$element->labels[$label] = Status::ATTACHED;
		}

		foreach ($structure->properties as $property => $value) {
			if ($value instanceof IStructure) {
				$value = $this->resolve($value);
			}

			if (!array_key_exists($property, $element->active)) {
				$element->active[$property] = $value;
			}

			if (array_key_exists($property, $element->loaded)) {
				if ($element->active[$property] == $element->loaded[$property]) {
					$element->active[$property] = $value;
				}
			}

			$element->loaded[$property] = is_object($value)
				? clone $value
				: $value
			;
		}

		return $element;
	}


	/**
	 * Initiate a new query with a statement and arguments.
	 *
	 * Query statements operate via `sprintf` underneath the hood.  The arguments passed here are
	 * for placeholder replacement.  For actual query parameters, use the `with()` call on the
	 * returned query.
	 */
	public function run(string $statement, mixed ...$args): Query
	{
		return $this->query->run($statement, ...$args);
	}
}
