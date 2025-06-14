<?php

namespace FluidGraph;

use Bolt\Bolt;
use Bolt\protocol\IStructure;
use Bolt\protocol\V5_2 as Protocol;
use Bolt\protocol\v5\structures as Struct;

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
	private readonly ReflectionProperty $union;


	/**
	 *
	 */
	public function __construct(
		private readonly array $login,
		private readonly Bolt $bolt,
		?Query $query = NULL,
		?Queue $queue = NULL
	) {
		$this->nodes = new ArrayObject();
		$this->edges = new ArrayObject();
		$this->union = new ReflectionProperty(Entity::class, '__element__');

		if (!$query) {
			$query = new Query();
		}

		if (!$queue) {
			$queue = new Queue();
		}

		$this->queue = $queue->on($this)->manage($this->nodes, $this->edges);
		$this->query = $query->on($this);
	}


	/**
	 *
	 */
	public function attach(Element|Entity ...$elements): static
	{
		foreach ($elements as $element) {
			if ($element instanceof Entity) {
				$element = $element->__element__;
			}

			switch ($element->status) {
				case Status::fastened:
					$identity = spl_object_hash($element);

					if ($element instanceof Element\Node) {
						$target = $this->nodes;
					} elseif ($element instanceof Element\Edge) {
						$target = $this->edges;

						$this->attach($element->target);
						$this->attach($element->source);

					} else {
						throw new InvalidArgumentException(sprintf(
							'Unknown element type "%s" on attach()',
							$element !== null ? $element::class : self::class
						));
					}

					$target[$identity] = $element->on($this);
					$element->status   = Status::inducted;
					break;

				case Status::released:
					$element->status = Status::attached;
					break;

				case Status::detached:
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
				$element = $element->__element__;
			}

			switch ($element->status) {
				case Status::inducted:
					$identity = spl_object_hash($element);
					$target   = match(TRUE) {
						$element instanceof Element\Node => $this->nodes,
						$element instanceof Element\Edge => $this->edges,
					};

					$element->status = Status::fastened;

					unset($target[$identity]);

					break;

				case Status::attached:
					$element->status = Status::released;
			}
		}

		return $this;
	}


	/**
	 * Match multiple nodes or edges and have them returned as an instance of a given class.
	 *
	 * The type of elements (node or edge) being matched is determined by the class.
	 *
	 * @template T of Entity
	 * @param class-string<T> $class
	 * @param array<Order> $orders
	 * @return Results<T>
	 */
	public function find(string $class, ?int $limit = NULL, ?int $offset = NULL, callable|array $terms = [], ?array $orders = NULL): Results
	{
		$query = $this->query->match($class);

		if (!is_null($limit)) {
			$query->take($limit);
		}

		if (!is_null($offset)) {
			$query->skip($offset);
		}

		if (!empty($terms)) {
			$query->where($terms);
		}

		if (!is_null($orders)) {
			$query->sort(...$orders);
		}

		return $query->getRaw()->as($class);
	}


	/**
	 *
	 */
	public function findAll(string $class, ?array $orders = NULL): Results
	{
		return $this->find($class, NULL, NULL, [], $orders);
	}


	/**
	 * Find a single node or edge and have it returned as an instance of a given class.
	 *
	 * The type of element (node or edge) being matched is determined by the class.
	 *
	 * @template T of Entity
	 * @param class-string<T> $class
	 * @return ?T
	 */
	public function findOne(string $class, callable|array|int $terms): ?Entity
	{
		if (is_int($terms)) {
			$terms = function($id) use ($terms) {
				return $id($terms);
			};
		}

		$results = $this->find($class, 2, 0, $terms, []);

		if (count($results) > 1) {
			throw new RuntimeException(sprintf(
				'Trying to match a unique result returned more than one result'
			));
		}

		return $results[0] ?? NULL;
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
		switch($structure::class) {
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
				$storage  = $this->nodes;

				if (!isset($this->nodes[$identity])) {
					$this->nodes[$identity] = new Element\Node()->on($this);
				}

				break;

			case Struct\Relationship::class:
				$labels   = [$structure->type];
				$identity = $structure->element_id;
				$storage  = $this->edges;

				if (!isset($this->edges[$identity])) {
					$this->edges[$identity] = new Element\Edge()->on($this);

					if (isset($this->nodes[$structure->startNodeId])) {
						$source = $this->nodes[$structure->startNodeId];
					} else {
						$source = $this->nodes[$structure->startNodeId] = new Element\Node();
					}

					if (isset($this->nodes[$structure->endNodeId])) {
						$target = $this->nodes[$structure->endNodeId];
					} else {
						$target = $this->nodes[$structure->endNodeId] = new Element\Node();
					}

					$this->edges[$identity]->with(
						function(Element $source, Element $target): void {
							/**
							 * @var Element\Edge $this
							 */
							$this->source = $source;
							$this->target = $target;
						},
						$source,
						$target
					);
				}
				break;

			default:
				throw new RuntimeException(sprintf(
					'Cannot resolve property of type "%s"',
					$structure::class
				));
		}

		$element = $storage[$identity];

		if (!isset($element->identity)) {
			$element->identity = $identity;
		}

		if ($element->status != Status::released) {
			$element->status = Status::attached;
		}

		foreach ($labels as $label) {
			$element->labels[str_replace('_', '\\', $label)] = Status::attached;
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


	/**
	 *
	 */
	public function save(): static
	{
		$this->queue->merge()->run();

		return $this;
	}
}
