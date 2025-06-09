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
		return new EdgeResults(array_values($this->active))->using($this->method);
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
	public function for(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		return $this->all()->for($node, ...$nodes);
	}



	/**
	 * Get the related node entity of() the specified class as that class.
	 *
	 * If a related node entity exists but does not match the class, NULL will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N> $class
	 * @param class-string<N> $classes
	 * @return ?N
	 */
	public function get(string $class): ?Node
	{
		if (count($this->active)) {

		}
		$edge = reset($this->active);

		if ($edge) {
			return match ($this->method) {
				Method::TO   => $edge->__element__->target->as($class),
				Method::FROM => $edge->__element__->source->as($class)
			};
		}

		return NULL;
	}


	/**
	 *
	 */
	public function set(Node $node, array $data = []): static
	{
		$this->validate($node);

		$hash = $this->index($node);

		if (!$hash) {
			$this->unset();

			$hash = $this->realize($node, $data);
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
			if (!$entity || $this->index($entity)) {
				$this->active = [];
			}
		}

		return $this;
	}
}
