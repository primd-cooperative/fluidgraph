<?php

namespace FluidGraph;

use DateTime;
use InvalidArgumentException;

/**
 * Relationships represent a collection of edges
 */
abstract class Relationship
{
	use Relationship\AbstractRelationship;

	/**
	 *
	 */
	public function load(Graph $graph): static
	{
		$loader = function() use ($graph) {
			$source = $this->source::class;
			$target = implode('|', $this->targets);
			$edges  = $graph
				->run('MATCH (n1:%s)-[r:%s]->(n2:%s)', $source, $this->type, $target)
				->run('RETURN n1, n2, r')
				->get()
				->of($this->type)
				->as($this->type)
			;

			array_push(
				$this->included,
				...array_filter(
					$edges,
					function($edge) {
						foreach ($this->excluded as $excluded) {
							if ($edge->is($excluded)) {
								return FALSE;
							}
						}

						return TRUE;
					}
				)
			);

			$this->loaded = new DateTime();
		};

		if ($this->mode == Mode::EAGER) {
			$loader();
		}

		return $this;
	}

	/**
	 * Merge the relationship into the graph object.
	 *
	 * This allows for relationships to control the the behavior and status of their edges and
	 * nodes.  It works by iterating through all MergeHook traits and running them.
	 *
	 * Called from Queue on merge().
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
}
