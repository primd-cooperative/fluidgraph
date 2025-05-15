<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use FluidGraph\Mode;
use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Status;
use FluidGraph\Element;
use FluidGraph\Graph;


use InvalidArgumentException;
use DateTime;
use FluidGraph\Results;

/**
 * @template T of Edge
 */
trait AbstractRelationship
{
	/**
	 * @var array<Edge>
	 */
	protected array $excluded = [];

	/**
	 * @var array<Edge>
	 */
	protected array $included = [];

	/**
	 * The last time the relationship was loaded, NULL if never loaded.
	 */
	public protected(set) ?DateTime $loaded = NULL;

	/**
	 * How this relationship should be populated when loaded from the graph
	 */
	public protected(set) Mode $mode;

	/**
	 * THe source node for this relationship
	 */
	public protected(set) Node $source;

	/**
	 * The types of targets this relationship allows, if empty, any target
	 */
	public protected(set) array $targets = [];

	/**
	 * The edge type that defines the relationship
	 *
	 * @var class-string<T>
	 */
	public protected(set) string $type;

	/**
	 *
	 */
	abstract public function load(Graph $graph): static;

	/**
	 *
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
		array $targets = [],
		Mode $mode = Mode::EAGER,
	) {
		if (!is_subclass_of($type, Edge::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot create relationship of non-edge type "%s"',
				$type
			));
		}

		$this->type     = $type;
		$this->source   = $source;
		$this->targets  = $targets;
		$this->mode     = $mode;
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
	 *
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
	 * {@inheritDoc}
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
	 * {@inheritDoc}
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
	 *
	 */
	public function of(string ...$labels): Results
	{
		$nodes = new Results(
			array_map(
				function(Element\Edge $edge): Element\Node {
					return $edge->target;
				},
				$this->included
			)
		);

		return $nodes->of(...$labels);
	}


	/**
	 * Get an array of all the edges for one or more nodes or kinds
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
	 * Determine by position whether or not a node is excluded from the current relationship
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
	 * Determine by position whether or not a node is included in the current relationship
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
	 * Validate a target
	 */
	protected function validate(Node $target) {
		if ($target->status() == Status::DETACHED) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include a detached target "%s" on "%s"',
				$target::class,
				static::class
			));
		}

		if (!in_array($target::class, $this->targets)) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include a target of class "%s" on "%s"',
				$target::class,
				static::class
			));
		}
	}
}
