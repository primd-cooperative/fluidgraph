<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use FluidGraph\Edge;
use FluidGraph\Node;
use FluidGraph\Element;
use FluidGraph\Reference;
use LogicException;
use UnexpectedValueException;

/**
 * A type of relationship that links to/from one node with one edge.
 */
abstract class LinkOneOne extends FluidGraph\Relationship
{
	/**
	 * Get the edge entity for this relationship, regardless what it corresponds to.
	 */
	public function any(): ?Edge
	{
		return reset($this->active) ?: NULL;
	}


	/**
	 * Get the edge entity for this relationship only if it corresponds to all node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return E
	 */
	public function for(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): ?Edge
	{
		$edge = $this->any();

		if ($edge) {
			if ($edge->for($this->type, $match, ...$matches)) {
				return $edge;
			}
		}

		return NULL;
	}


	/**
	 * Get the edge entity for this relationship only if it corresponds to one or more node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return E
	 */
	public function forAny(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): ?Edge
	{
		$edge = $this->any();

		if ($edge) {
			if ($edge->forAny($this->type, $match, ...$matches)) {
				return $edge;
			}
		}

		return NULL;
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
	public function unset(null|Node|Edge $node = NULL): static
	{
		if (!$node || $this->getIndex($node)) {
			$this->active = [];
		}

		return $this;
	}
}
