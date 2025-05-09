<?php

namespace FluidGraph;

use Bolt\Bolt;
use Bolt\protocol\IStructure;
use Bolt\protocol\V5_2 as Protocol;
use Bolt\protocol\v5\structures as Struct;

use ArrayObject;
use ReflectionProperty;
use RuntimeException;
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
	public protected(set) Protocol $protocol;

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
	public protected(set) Queue $queue {
		get {
			return clone $this->queue;
		}
		set (Queue $queue) {
			$this->queue = $queue;
		}
	}

	/**
	 * An instance of the base where clause builder to clone
	 */
	public protected(set) Where $where {
		get {
			return clone $this->where;
		}
		set (Where $where) {
			$this->where = $where;
		}
	}

	/**
	 *
	 */
	protected ReflectionProperty $content;

	/**
	 * @var ArrayObject<Content\Edge>
	 */
	protected ArrayObject $edges;

	/**
	 * @var ArrayObject<Content\Node>
	 */
	protected ArrayObject $nodes;

	/**
	 *
	 */
	public function __construct(
		array $login,
		Bolt $bolt,
		Query $query,
		Queue $queue,
		Where $where,
	) {
		$this->where    = $where;
		$this->queue    = $queue->on($this);
		$this->query    = $query->on($this);
		$this->protocol = $bolt->setProtocolVersions(5.2)->build();
		$this->content  = new ReflectionProperty(Element::class, '__content__');
		$this->nodes    = new ArrayObject();
		$this->edges    = new ArrayObject();

		$response = $this->protocol->hello()->getResponse();
		$response = $this->protocol->logon($login)->getResponse();
	}


	public function attach(Element ...$elements): static
	{
		foreach ($elements as $element) {
			if (!$element->status()) {
				$class    = get_class($element);
				$identity = spl_object_hash($element);

				if ($element instanceof Node) {
					$target = &$this->nodes;

					if (!isset($target[$identity])) {
						$target[$identity] = new Content\Node();
					}
				}

				if ($element instanceof Edge) {
					$target = &$this->edges;

					if (!isset($target[$identity])) {
						$target[$identity] = new Content\Edge();
					}
				}

				$content = $target[$identity];

				$content->labels[$class] = Status::INDUCTED;

				foreach (get_object_vars($element) as $property => $value) {
					$content->operative->$property = $value;

					if ($value instanceof Relationship) {
						$value->on($this);
					}
				}

				$this->fasten($content, $element);
			}
		}

		return $this;
	}


	public function detach(Element ...$elements)
	{
		foreach ($elements as $element) {
			$this->content->getValue($element)->status = Status::RELEASED;
		}
	}


	/**
	 * Fasten an element to its content.
	 *
	 * This converts entity properties to references and sets the content on the element.  If the
	 * content doesn't contain a corresponding property, it is created with the value on the
	 * entity at present.
	 */
	public function fasten(Content\Base $content, Element $element): static
	{
		foreach (get_object_vars($element) as $property => $value) {
			if (!property_exists($content->operative, $property)) {
				$content->operative->$property = $element->$property;
			}

			$element->$property = &$content->operative->$property;
		}

		$this->content->setValue($element, $content);

		return $this;
	}


	/**
	 * Match multiple nodes or edges and have them returned as an instance of a given class.
	 *
	 * The type of elements (node or edge) being matched is determined by the class.
	 *
	 * @template T of Element
	 * @param class-string<T> $class
	 * @return ArrayObject<T>
	 */
	public function match(string $class, callable|array|int $terms = [], ?array $order = NULL, int $limit = -1, int $skip = 0): ArrayObject
	{
		if (is_int($terms)) {
			return $this->match(
				$class,
				function($where) use ($terms) {
					return $where->id($terms);
				},
				$order,
				$limit,
				$skip
			);

		} elseif (is_array($terms)) {
			return $this->match(
				$class,
				function($where) use ($terms) {
					return $where->all(...$where->eq($terms));
				},
				$order,
				$limit,
				$skip
			);

		} else {
			$query = $this->run('MATCH (n:%s)', $class);
			$apply = $terms($this->where->use('n', $query))();

			if ($apply) {
				$query->run('WHERE %s', $apply);
			}

			$query->run('RETURN n');

			if ($order) {
				$query->run('ORDER BY');
			}

			if ($limit >= 0) {
				$query->run('LIMIT %s', $limit);
			}

			if ($skip > 0) {
				$query->run('SKIP %s', $skip);
			}

			return $query->get()->as($class);
		}
	}


	/**
	 * Match a single node or edge and have it returned as an instance of a given class.
	 *
	 * The type of element (node or edge) being matched is determined by the class.
	 *
	 * @template T of Element
	 * @param class-string<T> $class
	 * @return ?T
	 */
	public function matchOne(string $class, callable|array|int $query): ?Element
	{
		$results = $this->match($class, $query, [], 2, 0);

		if (count($results) > 1) {
			throw new RuntimeException(sprintf(
				'Match returned more than one result'
			));
		}

		return $results[0];
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
				$identity = $structure->element_id;
				$storage  = &$this->nodes;

				if (!isset($storage[$identity])) {
					$storage[$identity] = new Content\Node($identity);
				}

				break;

			case Struct\Relationship::class:
				$identity = $structure->element_id;
				$storage  = &$this->edges;

				if (!isset($storage[$identity])) {
					$storage[$identity] = new Content\Edge($identity);
				}
				break;

			default:
				throw new RuntimeException(sprintf(
					'Cannot resolve property of type "%s"',
					get_class($structure)
				));
		}

		$content = $storage[$identity];

		$content->identity = $identity;

		if ($content->status != Status::RELEASED) {
			$content->status = Status::ATTACHED;
		}

		foreach ($structure->labels as $label) {
			// TODO: Update Labels
		}

		foreach ($structure->properties as $property => $value) {
			if ($value instanceof IStructure) {
				$value = $this->resolve($value);
			}

			if (!property_exists($content->operative, $property)) {
				$content->operative->$property = $value;
			}

			if (property_exists($content->original, $property)) {
				if ($content->operative->$property == $content->original->$property) {
					$content->operative->$property = $value;
				}
			}

			$content->original->$property = is_object($value)
				? clone $value
				: $value
			;
		}

		return $content;
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
