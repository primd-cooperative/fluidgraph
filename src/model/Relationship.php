<?php

namespace FluidGraph;

use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Status;
use FluidGraph\Element;
use FluidGraph\Graph;
use FluidGraph\Relationship\Mode;

use InvalidArgumentException;
use DateTime;
use Closure;

/**
 * @template T of Edge
 */
abstract class Relationship
{
	static protected bool $inverse = FALSE;

	/**
	 * @var array<Edge<T>>
	 */
	protected array $active = [] {
		&get {
			if (isset($this->loader)) {
				$this->loadTime = call_user_func($this->loader);
			}

			return $this->active;
		}
	}

	/**
	 * @var array<Edge<T>>
	 */
	protected array $loaded = [] {
		&get {
			if (isset($this->loader)) {
				$this->loadTime = call_user_func($this->loader);
			}

			return $this->loaded;
		}
	}

	/**
	 * @var Closure:DateTime
	 */
	protected Closure $loader;

	/**
	 *
	 */
	protected Graph $loadGraph;


	/**
	 * When the relationship was loaded
	 */
	public protected(set) DateTime $loadTime;

	/**
	 *
	 */
	public protected(set) Mode $mode;

	/**
	 * The source entity Node for this relationship.
	 *
	 * This can be used when resolving relationships or determining merge effects.  For example
	 * If the source node was released, a specific type of relationship could detach target nodes.
	 */
	public protected(set) Node $source;

	/**
	 * The target Node entity classes this relationship allows for, if empty, any type is supported.
	 *
	 * @var array<class-string>
	 */
	public protected(set) array $targets = [];

	/**
	 * The edge type that defines the relationship
	 *
	 * @var class-string<T>
	 */
	public protected(set) string $type;


	/**
	 * Construct a new Relationship
	 *
	 * @param class-string<Edge> $type
	 * @param array<class-string<Node>> $targets
	 */
	public function __construct(
		Node $source,
		string $type,
		array $targets = [],
		Mode $mode = Mode::LAZY
	) {
		if (!is_subclass_of($type, Edge::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot create relationship of non-edge type "%s"',
				$type
			));
		}

		$this->type    = $type;
		$this->source  = $source;
		$this->targets = $targets;
		$this->mode    = $mode;
	}

	/**
	 *
	 */
	public function __debugInfo()
	{
		return array_filter(
			get_object_vars($this),
			function($key) {
				return !in_array(
					$key,
					[
						'graph'
					]
				);
			},
			ARRAY_FILTER_USE_KEY
		);
	}


	/**
	 * Assign data to the edges whose targets are one of any such node or label
	 */
	public function assign(array $data, Node|Element\Node|string ...$nodes): static
	{
		foreach ($nodes as $node) {
			foreach ($this->active as $edge) {
				if ($edge->for($node)) {
					$edge->assign($data);
				}
			}
		}

		return $this;
	}


	/**
	 * Clean the relationship of detached nodes.
	 */
	public function clean(): static
	{
		foreach ($this->loaded as $i => $edge) {
			if ($edge->__element__->status == Status::DETACHED) {
				unset($this->loaded[$i]);
			}
		}

		return $this;
	}


	/**
	 * Determine whether or not the relationship contains all of a set of nodes or node types.
	 */
	public function contains(Node|Element\Node|string ...$nodes): bool
	{
		foreach ($nodes as $node) {
			if ($this->index($node) === FALSE) {
				return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 * Determine whether or not the relationship contains any of a set of nodes or node types.
	 */
	public function containsAny(Node|Element\Node|string ...$nodes): bool
	{
		foreach ($nodes as $node) {
			if ($this->index($node) !== FALSE) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Get an array of all the edges for one or more nodes or node types.
	 *
	 * @return array<T>
	 */
	public function for(Element\Node|Node|string ...$nodes): array
	{
		$edges = [];

		if (empty($nodes)) {
			return $this->active;
		}

		foreach ($nodes as $node) {
			foreach ($this->active as $edge) {
				if ($edge->for($node)) {
					$edges[] = $edge;
				}
			}
		}

		return $edges;
	}


	/**
	 * Load the edges/nodes for the relationship.
	 *
	 * This method should be implemented by a concrete relationship implementation which can
	 * choose to implement more advanced loading features such as Eager and Lazy loading.
	 *
	 * Called from Element::as() -- for Node elements only, Edge elements do not have relationships.
	 */
	public function load(Graph $graph): static
	{
		if ($this->source->identity() && !isset($this->loadTime)) {
			$this->loader = function() use ($graph) {
				unset($this->loader);

				$target = implode('|', $this->targets);
				$source = $this->source::class;

				if (static::$inverse) {
					$match = 'MATCH (n1:%s)<-[r:%s]-(n2:%s)';
				} else {
					$match = 'MATCH (n1:%s)-[r:%s]->(n2:%s)';
				}

				$edges  = $graph
					->run($match, $source, $this->type, $target)
					->run('WHERE id(n1) = $source')
					->run('RETURN n1, n2, r')
					->set('source', $this->source->identity())
					->get()
					->of($this->type)
					->as($this->type)
				;

				$this->loaded    = [];
				$this->loadGraph = $graph;

				foreach ($edges as $edge) {
					$hash = spl_object_hash($edge->__element__);

					$this->loaded[$hash] = $edge;
					$this->active[$hash] = $edge;
				}

				return new DateTime();
			};

			if ($this->mode == Mode::EAGER) {
				$this->loadTime = call_user_func($this->loader);
			}
		}

		return $this;
	}


	/**
	 * Merge the relationship into the graph object.
	 *
	 * This method should be implemented by a concrete relationship implementation which can
	 * choose to implement more advanced merging features such as Merge hooks.
	 *
	 * Called from Queue::merge().
	 */
	public function merge(Graph $graph): static
	{
		for($class = get_class($this); $class != Relationship::class; $class = get_parent_class($class)) {
			foreach (class_uses($class) as $trait) {
				if (!in_array(Relationship\MergeHook::class, class_uses($trait))) {
					continue;
				}

				$parts  = explode('\\', $trait);
				$method = lcfirst(end($parts));

				$this->$method($graph);
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function reload(Graph $graph): static
	{
		unset($this->loadTime);

		$this->load($graph);

		if (isset($this->loader)) {
			$this->loadTime = call_user_func($this->loader);
		}

		return $this;
	}


	/**
	 * Determine, by index, whether or not a node is included in the current relationship.
	 */
	protected function index(Element\Node|Node|string $node): string|false
	{
		foreach ($this->active as $i => $edge) {
			if ($edge->for($node)) {
				return $i;
			}
		}

		return FALSE;
	}


	/**
	 * Validate a target against basic rules.
	 *
	 * - No Released or Detatched Targets
	 * - No Targets of Unsupported Types
	 */
	protected function validate(Node $target)
	{
		if ($target->status(Status::RELEASED, Status::DETACHED)) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include released or detached target "%s" on "%s"',
				$target::class,
				static::class
			));
		}

		if ($this->targets && !in_array($target::class, $this->targets)) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include a target of class "%s" on "%s"',
				$target::class,
				static::class
			));
		}
	}
}
