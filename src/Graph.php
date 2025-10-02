<?php

namespace FluidGraph;

use Bolt\Bolt;
use Bolt\protocol\IStructure;
use Bolt\protocol\V5_2 as Protocol;
use Bolt\protocol\v5\structures as Struct;

use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

use ArrayObject;
use RuntimeException;
use InvalidArgumentException;
use UnexpectedValueException;
use DateTimeZone;
use DateTime;
use Stringable;

/**
 *
 */
class Graph
{
	/**
	 *
	 */
	public protected(set) CacheItemPoolInterface $cache;

	/**
	 *
	 */
	public protected(set) EventDispatcherInterface $emitter;

	/**
	 *
	 */
	public protected(set) LoggerInterface $logger;

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
	 * An instance of the base queue implementation
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
	 *
	 */
	public function __construct(
		private readonly array $login,
		private readonly Bolt $bolt,
		?Queue $queue = NULL
	) {
		$this->nodes = new ArrayObject();
		$this->edges = new ArrayObject();

		if (!$queue) {
			$queue = new Queue();
		}

		$this->queue = $queue->on($this)->manage($this->nodes, $this->edges);
	}


	/**
	 * Attach an element or entity to the graph
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
	 * Detach and entity or element from the graph
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
	 * Execute a query and get mixed results
	 *
	 * @param string $statement An `sprintf()` style string with placeholders for `$args`
	 * @param mixed ...$values The values for filling palceholders in the `$statement`
	 * */
	public function exec(string $statement, mixed ...$values): Results
	{
		return $this->query($statement, ...$values)->results();
	}


	/**
	 * Get a single edge
	 *
	 * @template E of Edge
	 * @param array<class-string<E>|string>|class-string<E>|string $concerns
	 * @param array<string, mixed>|callable|int $terms
	 * @return ?E
	 */
	public function findEdge(array|string $concerns = [], array|callable|int $terms = []): ?Edge
	{
		settype($concerns, 'array');

		$invalid = array_filter(
			$concerns,
			fn($concern) => !is_subclass_of($concern, Edge::class, TRUE)
		);

		if (count($invalid)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot call edge() with invalid concerns: %s',
				implode(',', $invalid)
			));
		}

		if (is_int($terms)) {
			$terms = fn($id) => $id($terms);
		}

		$results = $this
			->matchAny([Edge::class, ...$concerns])
			->where($terms)
			->take(2)
			->skip(0)
			->results()
		;

		if (count($results) > 1) {
			throw new UnexpectedValueException(sprintf(
				'Call for edge() returned more than one result'
			));
		}

		return $results->as($concerns)->at(0);
	}


	/**
	 * Get multiple edges
	 *
	 * @template E of Edge
	 * @param array<class-string<E>|string>|class-string<E>|string $concerns
	 * @param array<string, mixed>|callable $terms
	 * @param array<Order> $orders
	 * @return EdgeResults<E>
	 */
	public function findEdges(array|string $concerns = [], ?int $limit = NULL, int $offset = 0, array|callable $terms = [], array $orders = []): EdgeResults
	{
		settype($concerns, 'array');

		$invalid = array_filter(
			$concerns,
			fn($concern) => !is_subclass_of($concern, Edge::class, TRUE)
		);

		if (count($invalid)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot call edges() with invalid concerns: %s',
				implode(',', $invalid)
			));
		}

		$results = $this
			->matchAny([Edge::class, ...$concerns])
			->where($terms)
			->take($limit)
			->skip($offset)
			->sort(...$orders)
			->results()
		;

		return new EdgeResults($results->as($concerns)->unwrap());
	}


	/**
	 * Get a single node
	 *
	 * @template N of Node
	 * @param array<class-string<N>|string>|class-string<N>|string $concerns
	 * @param array<string, mixed>|callable|int $terms
	 * @return ?N
	 */
	public function findNode(array|string $concerns = [], array|callable|int $terms = []): ?Node
	{
		settype($concerns, 'array');

		$invalid = array_filter(
			$concerns,
			fn($concern) => is_subclass_of($concern, Edge::class, TRUE)
		);

		if (count($invalid)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot call node() with invalid concerns: %s',
				implode(',', $invalid)
			));
		}

		if (is_int($terms)) {
			$terms = fn($id) => $id($terms);
		}

		$results = $this
			->matchAny([Node::class, ...$concerns])
			->where($terms)
			->take(2)
			->skip(0)
			->results()
		;

		if (count($results) > 1) {
			throw new UnexpectedValueException(sprintf(
				'Call for node() returned more than one result'
			));
		}

		return $results->as($concerns)->at(0);
	}


	/**
	 * Get multiple nodes
	 *
	 * @template E of Node
	 * @param array<class-string<E>|string>|class-string<E>|string $concerns
	 * @param array<string, mixed>|callable $terms
	 * @param array<Order> $orders
	 * @return NodeResults<E>
	 */
	public function findNodes(array|string $concerns = [], ?int $limit = NULL, int $offset = 0, array|callable $terms = [], array $orders = []): NodeResults
	{
		settype($concerns, 'array');

		$invalid = array_filter(
			$concerns,
			fn($concern) => is_subclass_of($concern, Edge::class, TRUE)
		);

		if (count($invalid)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot call nodes() with invalid concerns: %s',
				implode(',', $invalid)
			));
		}

		$results = $this
			->matchAny([Node::class, ...$concerns])
			->where($terms)
			->take($limit)
			->skip($offset)
			->sort(...$orders)
			->results()
		;

		return new NodeResults($results->as($concerns)->unwrap());
	}


	/**
	 * Output log messages if the logger is set
	 */
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		if ($this->logger) {
			$this->logger->log($level, $message, $context);
		}
	}


	/**
	 *
	 */
	public function getCacheItemPool(): ?CacheItemPoolInterface
	{
		return isset($this->cache) ? $this->cache : NULL;
	}


	/**
	 *
	 */
	public function getEventsEmitter(): ?EventDispatcherInterface
	{
		return isset($this->emitter) ? $this->emitter : NULL;
	}


	/**
	 *
	 */
	public function getLogger(): ?LoggerInterface
	{
		return isset($this->logger) ? $this->logger : NULL;
	}


	/**
	 * Initialize a new match query for ALL `$concerns`
	 */
	public function match(string|array $concerns, Reference $type = Reference::either): Query\MatchQuery
	{
		settype($concerns, 'array');

		return new Query\MatchQuery(Scope::concern, Matching::all, $concerns, $type)->on($this);
	}


	/**
	 * Initialize a new match query for ANY `$concerns`
	 */
	public function matchAny(string|array $concerns, Reference $type = Reference::either): Query\MatchQuery
	{
		settype($concerns, 'array');

		return new Query\MatchQuery(Scope::concern, Matching::any, $concerns, $type)->on($this);
	}


	/**
	 * Initialize a new raw query
	 *
	 * @param string $statement An `sprintf()` style string with placeholders for `$args`
	 * @param mixed ...$values The values for filling palceholders in the `$statement`
	 */
	public function query(string $statement, mixed ...$values): Query\RawQuery
	{
		return new Query\RawQuery()->on($this)->add($statement, ...$values);
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
						$source = $this->findNode([], $structure->startNodeId)->__element__;
					}

					if (isset($this->nodes[$structure->endNodeId])) {
						$target = $this->nodes[$structure->endNodeId];
					} else {
						$target = $this->findNode([], $structure->endNodeId)->__element__;
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
	 * Save the current runtime element state(s) into the graph
	 */
	public function save(): static
	{
		$this->queue->merge()->run();

		return $this;
	}


	/**
	 *
	 */
	public function setCacheItemPool(CacheItemPoolInterface $cache): static
	{
		$this->cache = $cache;

		return $this;
	}


	/**
	 *
	 */
	public function setEventsEmitter(EventDispatcherInterface $emitter): static
	{
		$this->emitter = $emitter;

		return $this;
	}


	/**
	 *
	 */
	public function setLogger(LoggerInterface $logger): static
	{
		$this->logger = $logger;

		return $this;
	}
}
