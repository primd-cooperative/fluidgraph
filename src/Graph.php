<?php

namespace FluidGraph;

use Bolt\Bolt;
use Bolt\protocol\IStructure;
use Bolt\protocol\V5_2 as Protocol;
use Bolt\protocol\v5\structures as Struct;

use ArrayObject;
use RuntimeException;
use InvalidArgumentException;
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
	 *
	 * @var Query<mixed>
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
	 * @var ArrayObject<string, Element\Edge>
	 */
	protected ArrayObject $edges;

	/**
	 * @var ArrayObject<string, Element\Node>
	 */
	protected ArrayObject $nodes;


	/**
	 * @param Query<mixed> $query
	 */
	public function __construct(
		private readonly array $login,
		private readonly Bolt $bolt,
		?Query $query = NULL,
		?Queue $queue = NULL
	) {
		$this->nodes = new ArrayObject();
		$this->edges = new ArrayObject();

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
						default => throw new InvalidArgumentException(sprintf(
							'Cannot detach entity with uknown element of type "%s"',
							gettype($element)
						))
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
	 * @template E of Entity
	 * @param null|array<class-string<E>|string>|class-string<E>|string $concerns
	 * @param callable|array<string, mixed> $terms
	 * @param array<Order> $orders
	 * @return Entity\Results<E>
	 */
	public function find(null|array|string $concerns = NULL, ?int $limit = NULL, int $offset = 0, callable|array $terms = [], array $orders = []): Entity\Results
	{
		settype($concerns, 'array');

		$query = $this->query;

		$query->match(...$concerns)->take($limit)->skip($offset)->where($terms)->sort(...$orders);

		return $query->get();
	}


	/**
	 * @template E of Entity
	 * @param null|array<class-string<E>|string>|class-string<E>|string $concerns
	 * @param array<Order> $orders
	 * @return Entity\Results<E>
	 */
	public function findAll(null|array|string $concerns = NULL, array $orders = []): Entity\Results
	{

		return $this->find($concerns, NULL, 0, [], $orders);
	}


	/**
	 * Find a single node or edge and have it returned as an instance of a given class.
	 *
	 * The type of element (node or edge) being matched is determined by the class.
	 *
	 * @template E of Entity
	 * @param null|array<class-string<E>|string>|class-string<E>|string $concerns
	 * @param callable|array<string, mixed> $terms
	 * @return ?E
	 */
	public function findOne(null|array|string $concerns = NULL, callable|array|int $terms = []): ?Entity
	{
		if (is_int($terms)) {
			$terms = (fn($id) => $id($terms));
		}

		$results = $this->find($concerns, 2, 0, $terms, []);

		if (count($results) > 1) {
			throw new RuntimeException(sprintf(
				'Trying to match a unique result returned more than one result'
			));
		}

		return $results->at(0);
	}


	/**
	 * Resolve structures returned from bolt protocol into usable forms.
	 *
	 * TODO: replace property resolution with a plugin system where resolvers can be registered
	 *
	 * The core functionality of this is designed to convert record structures such as nodes
	 * and relationships (edges) into their content representations.
	 */
	public function resolve(mixed $structure): mixed
	{
		if (is_object($structure)) {
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
					) ?: NULL;

					if ($value) {
						$value->setTimeZone($zone);
					}

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

		} else {
			$element = $structure;
		}

		return $element;
	}


	/**
	 * Initiate a new query with a statement and arguments.
	 *
	 * Query statements operate via `sprintf` underneath the hood.  The arguments passed here are
	 * for placeholder replacement.  For actual query parameters, use the `with()` call on the
	 * returned query.
	 *
	 * @return Query<mixed>
	 */
	public function run(string $statement, mixed ...$args): Query
	{
		return $this->query->add($statement, ...$args)->run();
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
