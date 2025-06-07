<?php

namespace FluidGraph;

use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Status;
use FluidGraph\Element;
use FluidGraph\Graph;
use FluidGraph\Relationship\Mode;
use FluidGraph\Relationship\Index;

use InvalidArgumentException;
use DateTime;
use Closure;
use FluidGraph\Relationship\Method;

/**
 * @template T of Edge
 */
abstract class Relationship
{
	/**
	 * @var array<T>
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
	 * @var array<T>
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
	protected DateTime $loadTime;


	/**
	 *
	 */
	protected Method $method = Method::TO;


	/**
	 * The edge type that defines the relationship
	 *
	 * @var class-string<T>
	 */
	public protected(set) string $kind;


	/**
	 * The subject for this relationship.
	 *
	 * This can be used when resolving relationships or determining merge effects.  For example
	 * If the subject node was released, a specific type of relationship could detach target nodes.
	 */
	public protected(set) Node $subject;





	/**
	 * Construct a new Relationship
	 *
	 * @param class-string<T> $kind
	 * @param array<class-string<Node>> $targets
	 */
	public function __construct(
		Node $subject,
		string $kind,
		/**
         * The Node entity which this relationship is concerned with, if empty, any type is supported.
         *
         * @var array<class-string>
         */
        public protected(set) array $concerns = [],
		public protected(set) Mode $mode = Mode::LAZY
	) {
		if (!is_subclass_of($kind, Edge::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot create relationship of non-edge kind "%s"',
				$kind
			));
		}

		$this->kind     = $kind;
		$this->subject  = $subject;
	}

	/**
	 *
	 */
	public function __debugInfo()
	{
		return array_filter(
			get_object_vars($this),
			fn($key) => !in_array(
					$key,
					[
						'graph'
					]
				),
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
				if ($edge->for($this->method, $node)) {
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
				if ($edge->for($this->method, $node)) {
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
		if ($this->subject->identity() && !isset($this->loadTime)) {
			$this->loader = function() use ($graph) {
				unset($this->loader);

				$concerns  = implode('|', $this->concerns);
				$subject   = $this->subject::class;

				if ($this->method == Method::TO) {
					$match = 'MATCH (n1:%s)-[r:%s]->(n2:%s)';
				} else {
					$match = 'MATCH (n1:%s)<-[r:%s]-(n2:%s)';
				}

				$edges  = $graph
					->run($match, $subject, $this->kind, $concerns)
					->run('WHERE id(n1) = $subject')
					->run('RETURN n1, n2, r')
					->set('subject', $this->subject->identity())
					->get()
					->of($this->kind)
					->as($this->kind)
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
		for($class = static::class; $class != Relationship::class; $class = get_parent_class($class)) {
			foreach (class_uses($class) as $trait) {
				if (!in_array(Relationship\MergeHook::class, class_uses($trait))) {
					continue;
				}

				$parts  = explode('\\', $trait);
				$method = 'merge' . end($parts);

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
	protected function index(Element\Node|Node|string $node, Index $index = Index::ACTIVE): string|false
	{
		$index = $index->value;

		foreach ($this->$index as $i => $edge) {
			if ($edge->for($this->method, $node)) {
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

		if ($this->concerns && !array_intersect($target->__element__->labels(), $this->concerns)) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include a concern of class "%s" on "%s"',
				$target::class,
				static::class
			));
		}
	}
}
