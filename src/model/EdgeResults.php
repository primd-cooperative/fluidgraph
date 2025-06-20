<?php

namespace FluidGraph;

use InvalidArgumentException;

/**
 * @template T of Edge
 * @extends Entity\Results<T>
 */
class EdgeResults extends Entity\Results
{
	/**
	 * Get all edge entities for this relationship that correspond to all node(s)/label(s) as Results
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return static<T>
	 */
	public function for(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): EdgeResults
	{
		$edges = [];

		foreach ($this as $edge) {
			foreach ($this->getReferencedNodes($edge) as $node) {
				if ($node->of($match, ...$matches)) {
					$edges[] = $edge;

					continue 2;
				}
			}

		}

		return new static($edges)->using($this->relationship);
	}


	/**
	 * Get all edge entities for this relationship that corresponds to any node(s)/label(s) as Results
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return static<T>
	 */
	public function forAny(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): EdgeResults
	{
		$edges = [];

		foreach ($this as $edge) {
			foreach ($this->getReferencedNodes($edge) as $node) {
				if ($node->ofAny($match, ...$matches)) {
					$edges[] = $edge;

					continue 2;
				}
			}
		}

		return new static($edges)->using($this->relationship);
	}


	/**
	 * Get the related node entities of() the specified classes.
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

		foreach ($this as $edge) {
			foreach ($this->getReferencedNodes($edge) as $node) {
				$hash = spl_object_hash($node);

				if (!isset($index[$hash])) {
					if ($node->of(...$concerns)) {
						$nodes[]      = $node->as($concerns);
						$index[$hash] = TRUE;

					} else {
						$index[$hash] = FALSE;

					}
				}
			}
		}

		return new NodeResults($nodes)->using($this->relationship);
	}


	/**
	 * Get the related node entities ofAny() of the specified classes.
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

		foreach ($this as $edge) {
			foreach ($this->getReferencedNodes($edge) as $node) {
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
		}

		return new NodeResults($nodes)->using($this->relationship);
	}


	/**
	 *
	 */
	public function unset(Edge $edge): static
	{
		if ($this->relationship) {
			$this->relationship->unset($edge);
		}

		$copy = $this->getArrayCopy();
		$key  = array_search($edge, $copy, TRUE);

		if ($key) {
			$this->removed[] = $key;
		}

		return $this;
	}


	/**
	 *
	 */
	protected function getReferencedNodes($edge)
	{
		if ($this->relationship) {
			return match ($this->relationship->type) {
				Reference::to     => [$edge->__element__->target],
				Reference::from   => [$edge->__element__->source],
				Reference::either => [
					$this->relationship->subject->is($edge->__element__->target)
						? $edge->__element__->source
						: $edge->__element__->target
				]
			};
		}

		return [
			$edge->__element__->target,
			$edge->__element__->source
		];
	}
}
