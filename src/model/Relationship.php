<?php

namespace FluidGraph;

use DateTime;

/**
 * Relationships represent a collection of edges
 */
abstract class Relationship
{
	use Relationship\AbstractRelationship;

	protected DateTime $loadTime;

	/**
	 * {@inheritDoc}
	 */
	public function load(Graph $graph): static
	{
		if (!isset($this->loadTime)) {
			$target = implode('|', $this->targets);
			$source = $this->source::class;
			$edges  = $graph
				->run('MATCH (n1:%s)-[r:%s]->(n2:%s)', $source, $this->type, $target)
				->run('WHERE id(n1) = $source')
				->run('RETURN n1, n2, r')
				->set('source', $this->source->identity())
				->get()
				->of($this->type)
				->as($this->type)
			;

			$this->loaded   = [];
			$this->loadTime = new DateTime();

			foreach ($edges as $edge) {
				$hash = spl_object_hash($edge->__element__);

				$this->loaded[$hash] = $edge;
				$this->active[$hash] = $edge;
			}
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
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
