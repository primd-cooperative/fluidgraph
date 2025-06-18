<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Element;
use FluidGraph\Reference;
use FluidGraph\EdgeResults;
use FluidGraph\Relationship;
use UnexpectedValueException;

/**
 * A type of relationship that links to/from one node with one edge.
 */
abstract class LinkManyOne extends Relationship
{
	/**
	 * Get all edge entities for this relationship that corresponds to all node(s)/label(s)
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
	 * Get all edge entities for this relationship that corresponds to any node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return EdgeResults<E>
	 */
	public function forAny(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): EdgeResults
	{
		return $this->all()->forAny($node, ...$nodes);
	}


	/**
	 * Get the related node entity for() the specified class as that class.
	 *
	 * If a related node entity exists but does not match the class, NULL will be returned.
	 *
	 * @template N of Node
	 * @param ?class-string<N> $class
	 * @return ?N
	 */
	public function get(?string $class = NULL): ?Node
	{
		$results = parent::get($class);

		if (count($results) > 1) {
			throw new UnexpectedValueException(sprintf(
				'Relationship limited to one Node Entity returned more than one linked Node'
			));
		}

		return $results->at(0);
	}


	/**
	 *
	 */
	public function set(Node $node, array|Edge $data = []): static
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
	public function unset(null|Node|Edge $entity = NULL): static
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
