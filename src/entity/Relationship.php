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
			if ($this->includes($node) === FALSE) {
				return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 * {@inheritDoc}
	 */
	public function containsAny(Content\Node|Node|string ...$nodes): bool
	{
		foreach ($nodes as $node) {
			if ($this->includes($node) !== FALSE) {
				return TRUE;
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


	/**
	 * {@inheritDoc}
	 */
	protected function includes(Content\Node|Node|string $node): int|false
	{
		foreach ($this->included as $i => $edge) {
			if ($edge->for($node)) {
				return $i;
			}
		}

		return FALSE;
	}


	/**
	 * {@inheritDoc}
	 */
	protected function excludes(Content\Node|Node|string $node): int|false
	{
		foreach ($this->excluded as $i => $edge) {
			if ($edge->for($node)) {
				return $i;
			}
		}

		return FALSE;
	}


	/**
	 *
	 */
	protected function graphOr(string $class, $message): Graph
	{
		if (!isset($this->graph)) {
			throw new $class($message);
		}

		return $this->graph;
	}
}
