<?php

namespace FluidGraph;

/**
 * Relationships represent a collection of edges
 */
abstract class Relationship
{
	use Relationship\AbstractRelationship;

	/**
	 * Merge the relationship into the graph object.
	 *
	 * This allows for relationships to control the the behavior and status of their edges and
	 * nodes.  It works by iterating through all MergeHook traits and running them.
	 *
	 * Called from Queue on merge().
	 */
	public function merge(Element\Node $source): static
	{
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
