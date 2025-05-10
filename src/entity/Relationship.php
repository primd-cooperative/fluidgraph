<?php

namespace FluidGraph;

use ArrayObject;
use RuntimeException;

/**
 * Relationships represent a collection of edges
 */
abstract class Relationship
{
	use Relationship\AbstractRelationship;

	/**
	 * Assign data to the edges whose targets are one of any such node or label
	 */
	public function assign(array $data, Content\Node|Node|string ...$nodes): static
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
	public function contains(Content\Node|Node|string ...$nodes): bool
	{
		foreach ($nodes as $node) {
			foreach ($this->included as $edge) {
				if ($edge->for($node)) {
					return TRUE;
				}
			}
		}

		return FALSE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function for(Content\Node|Node|string ...$nodes): array
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
	 * Merge the relationship into the graph object.
	 *
	 * This allows for relationships to control the the behavior and status of their edges and
	 * nodes.  It works by iterating through all MergeHook traits and running them.
	 *
	 * Called from Queue on merge().
	 */
	public function merge(Content\Node $source): static
	{
		//
		// TODO: update the source for all edges that need it.
		//

		for($class = get_class($this); $class != Relationship::class; $class = get_parent_class($class)) {
			foreach (class_uses($class) as $trait) {
				if (!in_array(Relationship\MergeHook::class, class_uses($trait))) {
					continue;
				}

				$parts  = explode('\\', $trait);
				$method = lcfirst(end($parts));

				$this->$method();
			}
		}

		return $this;
	}
}
