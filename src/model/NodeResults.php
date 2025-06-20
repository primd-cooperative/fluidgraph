<?php

namespace FluidGraph;

/**
 * @template T of Node
 * @extends Entity\Results<T>
 */
class NodeResults extends Entity\Results
{
	/**
	 * Get the node entities of() the specified classes.
	 *
	 * @template N of Node
	 * @param class-string<N>|string $concern
	 * @param class-string<N>|string ...$concerns
	 * @return NodeResults<N>
	 */
	public function get(string $concern, string ...$concerns): NodeResults
	{
		$nodes = [];
		$index = [];

		array_unshift($concerns, $concern);

		foreach ($this as $node) {
			$hash = spl_object_hash($node);

			if (!isset($index[$hash])) {
				if ($node->ofAny(...$concerns)) {
					$nodes[]      = $node->as($concerns);
					$index[$hash] = TRUE;

				} else {
					$index[$hash] = FALSE;

				}
			}
		}

		return new static($nodes)->using($this->relationship);
	}


	/**
	 * Get the node entities ofAny() of the specified classes.
	 *
	 * @template N of Node
	 * @param class-string<N>|string $concern
	 * @param class-string<N>|string ...$concerns
	 * @return NodeResults<N>
	 */
	public function getAny(string $concern, string ...$concerns): NodeResults
	{
		$nodes = [];
		$index = [];

		array_unshift($concerns, $concern);

		foreach ($this as $node) {
			$hash = spl_object_hash($node);

			if (!isset($index[$hash])) {
				if ($node->ofAny(...$concerns)) {
					$nodes[]      = $node->as($concerns);
					$index[$hash] = TRUE;

				} else {
					$index[$hash] = FALSE;

				}
			}
		}

		return new static($nodes)->using($this->relationship);
	}


	/**
	 *
	 */
	public function unset(Node $node): static
	{
		if ($this->relationship) {
			$this->relationship->unset($node);
		}

		$copy = $this->getArrayCopy();
		$key  = array_search($node, $copy, TRUE);

		if ($key) {
			$this->removed[] = $key;
		}

		return $this;
	}
}
