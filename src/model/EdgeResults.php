<?php

namespace FluidGraph;

/**
 * @template T of Edge
 * @extends Entity\Results<T>
 */
class EdgeResults extends Entity\Results
{
	/**
	 *
	 */
	protected ?Relationship $relationship = NULL;


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
				if (!$node->of($match, ...$matches)) {
					continue;
				}
			}

			$edges[] = $edge;
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

					continue;
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

		return new NodeResults($nodes);
	}


	/**
	 *
	 */
	public function merge(): static
	{
		if ($this->relationship) {
			$this->relationship->merge(TRUE);
		}

		return $this;
	}


	/**
	 *
	 */
	public function using(?Relationship $relationship): static
	{
		$this->relationship = $relationship;

		return $this;
	}

	/**
	 *
	 */
	protected function getReferencedNodes($edge)
	{
		if ($this->relationship) {
			$type = $this->relationship->type;
		} else {
			$type = Reference::either;
		}

		return match ($type) {
			Reference::to     => [$edge->__element__->target],
			Reference::from   => [$edge->__element__->source],
			Reference::either => [
				$edge->__element__->target,
				$edge->__element__->source
			]
		};
	}
}
