<?php

namespace FluidGraph;

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
	 * Get related node entities for() the specified class as Results.
	 *
	 * If related node entities exist but do not match the class, an empty array will be returned.
	 *
	 * @template N of Node
	 * @param null|array|class-string<N> $class
	 * @return NodeResults<N>
	 */
	public function get(null|array|string $class = NULL): NodeResults
	{
		$nodes = [];
		$index = [];

		foreach ($this as $edge) {
			foreach ($this->getReferencedNodes($edge) as $node) {
				$hash = spl_object_hash($node);

				if (!isset($index[$hash])) {
					if (!is_string($class) || $node->is($class)) {
						$nodes[]      = $node->as($class);
						$index[$hash] = TRUE;

					} else {
						$index[$hash] = FALSE;

					}
				}
			}
		}

		return new NodeResults($nodes)->using($this->relationship);
	}


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
