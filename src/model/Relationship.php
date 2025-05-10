<?php

namespace FluidGraph;

use ArrayObject;
use RuntimeException;

/**
 * Relationships represent a collection of edges
 */
abstract class Relationship
{
	use HasGraph;

	/**
	 * Construct a new Relationship
	 */
	public function __construct(
		/**
		 * The edge type that defines the relationship
		 */
		public protected(set) string $type,

		/**
		 *
		 */
		public protected(set) Mode $mode = Mode::EAGER,

		/**
		 *
		 */
		public protected(set) string|array $kind = [],

		/**
		 *
		 */
		protected $included = new ArrayObject(),

		/**
		 *
		 */
		protected $excluded = new ArrayObject()
	) {}


	/**
	 * Check if there are any edges pointing to one or more nodes or kinds
	 */
	public function contains(Node|string ...$nodes): bool
	{
		return FALSE;
	}

	/**
	 * Get an array of all the edges pointing to a one or more nodes or kinds
	 */
	public function for(Node|string $node): array
	{
		return [];
	}













	/**
	 * Exclude an edge in this relatonship.
	 */
	public function exclude(Edge ...$edges)
	{
		foreach ($edges as $i => $edge) {
			$content = $this->graph->content->getValue($edge);

			if (get_class($edge) != $this->type) {
				unset($edges[$i]);
				continue;
			}

			if (!in_array(get_class($content->target), $this->kind)) {
				unset($edge[$i]);
				continue;
			}


			$position = array_search($edge, $this->included, TRUE);

			if ($position !== FALSE) {
				unset($this->included[$position]);
			}
		}

		array_push($this->excluded, ...$edges);
	}

	/**
	 * Include an edge in this relationship.
	 *
	 * Included edges must have a matching type and a target of a matching kind for the
	 * relationship.
	 */
	public function include(Edge ...$edges)
	{
		foreach ($edges as $edge) {
			if (get_class($edge) != $this->type) {
				throw new RuntimeException(sprintf(
					'Cannot include edge of type "%s", must be "%s".',
					get_class($edge),
					$this->type
				));
			}

			if (!in_array(get_class($edge->target()), $this->kind)) {
				throw new RuntimeException(sprintf(
					'Cannot include edge with target of "%s", must be one of: %s.',
					get_class($edge->target()),
					implode(', ', $this->kind)
				));
			}

			$position = array_search($edge, $this->excluded, TRUE);

			if ($position !== FALSE) {
				unset($this->excluded[$position]);
			}
		}

		array_push($this->included, ...$edges);
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


	/**
	 * Update the edges whose targets are one of any such node or label
	 */
	public function update(array $data, Node|string ...$nodes)
	{

	}
}
