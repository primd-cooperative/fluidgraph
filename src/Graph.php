<?php

namespace FluidGraph;

use Bolt\Bolt;
use Bolt\protocol\IStructure;
use Bolt\protocol\V5_2 as Protocol;
use Bolt\protocol\v5\structures as Struct;

use ArrayObject;
use Closure;
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
	 *
	 */
	public protected(set) ReflectionProperty $content;

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
	private Bolt $bolt;

	/**
	 * @var array
	 */
	private array $login;

	/**
	 *
	 */
	public function __construct(
		array $login,
		Bolt $bolt,
		Query $query,
		Queue $queue
	) {
		$this->nodes    = new ArrayObject();
		$this->edges    = new ArrayObject();
		$this->content  = new ReflectionProperty(Element::class, '__content__');
		$this->queue    = $queue->on($this)->manage($this->nodes, $this->edges);
		$this->query    = $query->on($this);
		$this->login    = $login;
		$this->bolt     = $bolt;
	}


	/**
	 *
	 */
	public function attach(Element ...$elements): static
	{
		foreach ($elements as $element) {
			$content = $element->__content__;

			if (!$content->status) {
				$this->fasten($element);
			}

			switch ($content->status) {
				case Status::FASTENED:
					$identity = spl_object_hash($element);

					if ($content instanceof Content\Node) {
						$target = $this->nodes;
					} elseif ($content instanceof Content\Edge) {
						$target = $this->edges;

						if (isset($content->target->entity)) {
							$this->attach($content->target->entity);
						}

						if (isset($content->source->entity)) {
							$this->attach($content->source->entity);
						}

					} else {
						throw new InvalidArgumentException(sprintf(
							'Unknown element type "%s" on attach()',
							get_class($content)
						));
					}

					$target[$identity] = $content;
					$content->status   = Status::INDUCTED;
					break;

				case Status::RELEASED:
					$content->status = Status::ATTACHED;
					break;

				case Status::DETACHED:
					throw new InvalidArgumentException(sprintf(
						'Cannot attached already detached element: %s',
						$content->identity
					));
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function detach(Element|Content\Element ...$elements): static
	{
		foreach ($elements as $element) {
			if ($element instanceof Content\Element) {
				$content = $element;
			} else {
				$content = $element->__content__;
			}

			switch ($element->status()) {
				case Status::INDUCTED:
					$identity = spl_object_hash($element);
					$target   = match(TRUE) {
						$element instanceof Node => $this->nodes,
						$element instanceof Edge => $this->edges,
					};

					$content->status = Status::FASTENED;

					unset($target[$identity]);

					break;

				case Status::ATTACHED:
					$content->status = Status::RELEASED;
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
	public function fasten(Element $element, ?Content\Element $content = NULL): static
	{
		if (!$content) {
			$content = $element->__content__;
		} else {
			$this->content->setRawValue($element, $content);
		}

		foreach ($element->values() as $property => $value) {
			if (!array_key_exists($property, $content->active)) {
				$content->active[$property] = $value;
			}

			if ($value instanceof Relationship) {
				$value->on($this);
			}

			Closure::bind(
				function () use ($content, $property) {
					unset($this->$property);

					$this->$property = &$content->active[$property];
				},
				$element,
				$element
			)();
		}

		if (!isset($content->labels[$element::class])) {
			$content->labels[$element::class] = Status::FASTENED;
		}

		if (!isset($content->status)) {
			$content->status = Status::FASTENED;
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
				$identity = $structure->element_id;
				$storage  = &$this->nodes;

				if (!isset($storage[$identity])) {
					$storage[$identity] = new Content\Node();
				}

				break;

			case Struct\Relationship::class:
				$identity = $structure->element_id;
				$storage  = &$this->edges;

				if (!isset($storage[$identity])) {
					$storage[$identity] = new Content\Edge();
				}
				break;

			default:
				throw new RuntimeException(sprintf(
					'Cannot resolve property of type "%s"',
					get_class($structure)
				));
		}

		$content = $storage[$identity];

		if (!isset($content->identity)) {
			$content->identify($identity);
		}

		if ($content->status != Status::RELEASED) {
			$content->status = Status::ATTACHED;
		}

		foreach ($structure->labels as $label) {
			$content->labels[$label] = Status::ATTACHED;
		}

		foreach ($structure->properties as $property => $value) {
			if ($value instanceof IStructure) {
				$value = $this->resolve($value);
			}

			if (!array_key_exists($property, $content->active)) {
				$content->active[$property] = $value;
			}

			if (array_key_exists($property, $content->loaded)) {
				if ($content->active[$property] == $content->loaded[$property]) {
					$content->active[$property] = $value;
				}
			}

			$content->loaded[$property] = is_object($value)
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
