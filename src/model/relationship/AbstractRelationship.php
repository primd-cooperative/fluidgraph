<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use FluidGraph\Element;
use InvalidArgumentException;

/**
 * @template T of FluidGraph\Edge
 */
trait AbstractRelationship
{
	use FluidGraph\HasGraph;
	use FluidGraph\DoesMake;
	use FluidGraph\DoesWith;

	/**
	 * @var array<FluidGraph\Edge>
	 */
	protected array $included = [];

	/**
	 * @var array<FluidGraph\Edge>
	 */
	protected array $excluded = [];

	/**
	 * How this relationship should be populated when loaded from the graph
	 */
	public protected(set) FluidGraph\Mode $mode;

	/**
	 * THe source node for this relationship
	 */
	public protected(set) FluidGraph\Node $source;

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
	 * Construct a new Relationship
	 *
	 * @param class-string<FluidGraph\Edge> $type
	 * @param array<class-string<FluidGraph\Node>> $targets
	 */
	public function __construct(
		FluidGraph\Node $source,
		string $type,
		array $targets = [],
		FluidGraph\Mode $mode = FluidGraph\Mode::EAGER,
	) {
		$this->source   = $source;
		$this->type     = $type;
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
	public function assign(array $data, FluidGraph\Node|Element\Node|string ...$nodes): static
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
	 * {@inheritDoc}
	 */
	public function contains(FluidGraph\Node|Element\Node|string ...$nodes): bool
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
	public function containsAny(FluidGraph\Node|Element\Node|string ...$nodes): bool
	{
		foreach ($nodes as $node) {
			if ($this->includes($node) !== FALSE) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * Get an array of all the edges pointing to a one or more nodes or kinds
	 *
	 * @return array<T>
	 */
	public function for(FluidGraph\Element\Node|FluidGraph\Node|string ...$nodes): array
	{
		$edges = [];

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
	 * Determine by position whether or not a node is included in the current relationship
	 */
	protected function includes(FluidGraph\Element\Node|FluidGraph\Node|string $node): int|false
	{
		foreach ($this->included as $i => $edge) {
			if ($edge->for($node)) {
				return $i;
			}
		}

		return FALSE;
	}

	/**
	 * Determine by position whether or not a node is excluded from the current relationship
	 */
	protected function excludes(FluidGraph\Element\Node|FluidGraph\Node|string $node): int|false
	{
		foreach ($this->excluded as $i => $edge) {
			if ($edge->for($node)) {
				return $i;
			}
		}

		return FALSE;
	}


	/**
	 * Validate a target
	 */
	protected function validate(FluidGraph\Node $target) {
		if ($target->status() == FluidGraph\Status::DETACHED) {
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
