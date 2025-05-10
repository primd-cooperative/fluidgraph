<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use ArrayObject;

/**
 * @template T of FluidGraph\Edge
 */
trait AbstractRelationship
{
	/**
	 * @var array<FluidGraph\Edge>
	 */
	protected ArrayObject $included;

	/**
	 * @var array<FluidGraph\Edge>
	 */
	protected ArrayObject $excluded;

	/**
	 * How this relationship should be populated when loaded from the graph
	 */
	public protected(set) FluidGraph\Mode $mode;

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
	 * Check if there are any edges pointing to one or more nodes or kinds
	 */
	abstract public function contains(FluidGraph\Content\Node|FluidGraph\Node|string ...$nodes): bool;

	/**
	 * Get an array of all the edges pointing to a one or more nodes or kinds
	 *
	 * @return array<T>
	 */
	abstract public function for(FluidGraph\Content\Node|FluidGraph\Node|string ...$nodes): array;


	/**
	 * Construct a new Relationship
	 *
	 * @param class-string<FluidGraph\Edge> $type
	 * @param array<class-string<FluidGraph\Node>> $targets
	 */
	public function __construct(string $type, FluidGraph\Mode $mode = FluidGraph\Mode::EAGER, array $targets = [])
	{
		$this->type     = $type;
		$this->mode     = $mode;
		$this->targets  = $targets;
		$this->included = new ArrayObject();
		$this->excluded = new ArrayObject();
	}
}
