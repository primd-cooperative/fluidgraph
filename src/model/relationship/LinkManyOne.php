<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Element;
use FluidGraph\EdgeResults;
use FluidGraph\Relationship;

/**
 * A type of relationship that links to/from one node with one edge.
 */
abstract class LinkManyOne extends Relationship
{
	/**
	 * Get all edge entities for this relationship, regardless what they correspond to
	 *
	 * @return EdgeResults<E>
	 */
	public function all(): EdgeResults
	{
		return new EdgeResults(array_values($this->active))->using($this->type);
	}


	/**
	 * Get all edge entities for this relationship that corresponds to all node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return EdgeResults<E>

	 */
	public function of(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		return $this->all()->of($node, ...$nodes);
	}


	/**
	 * Get all edge entities for this relationship that corresponds to any node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return EdgeResults<E>
	 */
	public function ofAny(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		return $this->all()->ofAny($node, ...$nodes);
	}



	/**
	 * Get the related node entity of() the specified class as that class.
	 *
	 * If a related node entity exists but does not match the class, NULL will be returned.
	 *
	 * @template N of Node
	 * @param ?class-string<N> $class
	 * @return ?N
	 */
	public function get(?string $class = NULL): ?Node
	{
		$edge = reset($this->active);

		if ($edge) {
			$node = match ($this->type) {
				Reference::to   => $edge->__element__->target,
				Reference::from => $edge->__element__->source
			};

			if (!is_null($class)) {
				if (!$node->is($class)) {
					return NULL;
				}

			} else {
				$class = $this->concerns;

				if (isset($this->apex)) {
					$class = array_merge($class, $this->apex->concerns);
				}

			}

			return $node->as($class);
		}

		return NULL;
	}


	/**
	 *
	 */
	public function set(Node $node, array $data = []): static
	{
		$this->validateNode($node);

		$hash = $this->getIndex($node);

		if (!$hash) {
			$this->unset();

			$hash = $this->resolveEdge($node, $data);
		}

		$this->active[$hash]->assign($data);

		return $this;
	}


	/**
	 *
	 */
	public function unset(NULL|Node|Edge $entity = NULL): static
	{
		if ($entity instanceof Edge) {
			unset($this->active[spl_object_hash($entity)]);

		} else {
			if (!$entity || $this->getIndex($entity)) {
				$this->active = [];
			}
		}

		return $this;
	}
}
