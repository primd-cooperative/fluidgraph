<?php

namespace FluidGraph;

use DateTime;

/**
 * Relationships represent a collection of edges
 */
abstract class Relationship
{
	use Relationship\AbstractRelationship;

	protected DateTime $loaded;

	/**
	 * {@inheritDoc}
	 */
	public function load(Graph $graph): static
	{
		if (!isset($loaded)) {
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
