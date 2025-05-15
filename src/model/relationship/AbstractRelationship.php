<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Status;
use FluidGraph\Element;
use FluidGraph\Graph;

use InvalidArgumentException;

/**
 * @template T of Edge
 */
trait AbstractRelationship
{
	/**
	 * @var array<Edge<T>>
	 */
	protected array $excluded = [];

	/**
	 * @var array<Edge<T>>
	 */
	protected array $included = [];

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
	 * Load the edges/nodes for the relationship.
	 *
	 * This method should be implemented by a concrete relationship implementation which can
	 * choose to implement more advanced loading features such as Eager and Lazy loading.
	 *
	 * Called from Element::as() -- for Node elements only, Edge elements do not have relationships.
	 */
	abstract public function load(Graph $graph): static;

	/**
	 * Merge the relationship into the graph object.
	 *
	 * This method should be implemented by a concrete relationship implementation which can
	 * choose to implement more advanced merging features such as Merge hooks.
	 *
	 * Called from Queue::merge().
	 */
	abstract public function merge(Graph $graph): static;


	/**
	 * Construct a new Relationship
	 *
	 * @param class-string<Edge> $type
	 * @param array<class-string<Node>> $targets
	 */
	public function __construct(
		Node $source,
		string $type,
		array $targets = []
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
			foreach ($this->included as $edge) {
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
		foreach ($this->excluded as $i => $edge) {
			if ($edge->status() == Status::DETACHED) {
				unset($this->excluded[$i]);
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
			if ($this->includes($node) === FALSE) {
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
			if ($this->includes($node) !== FALSE) {
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
			return $this->included;
		}

		foreach ($nodes as $node) {
			foreach ($this->included as $edge) {
				if ($edge->for($node)) {
					$edges[] = $edge;
				}
			}
		}

		return $edges;
	}


	/**
	 * Determine, by position, whether or not a node is excluded from the current relationship.
	 */
	protected function excludes(Element\Node|Node|string $node): int|false
	{
		foreach ($this->excluded as $i => $edge) {
			if ($edge->for($node)) {
				return $i;
			}
		}

		return FALSE;
	}


	/**
	 * Determine, by position, whether or not a node is included in the current relationship.
	 */
	protected function includes(Element\Node|Node|string $node): int|false
	{
		foreach ($this->included as $i => $edge) {
			if ($edge->for($node)) {
				return $i;
			}
		}

		return FALSE;
	}


	/**
	 * Validate a target against basic rules.
	 *
	 * - No Detatched Targets
	 * - No Targets of Unsupported Types
	 */
	protected function validate(Node $target) {
		if ($target->status() == Status::DETACHED) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include a detached target "%s" on "%s"',
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
