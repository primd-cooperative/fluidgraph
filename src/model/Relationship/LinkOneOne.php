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
	 * Get the edge entity for this relationship if its node corresponds to ALL node/label matches
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return E
	 */
	public function for(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): ?Edge
	{
		$results = parent::for($match, ...$matches);

		if (count($results) > 1) {
			throw new UnexpectedValueException(sprintf(
				'Relationship limited to one Edge Entity but returned more than one'
			));
		}

		return $results->at(0);
	}


	/**
	 * Get the edge entity for this relationship if its node corresponds to ANY node/label matches
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return E
	 */
	public function forAny(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): ?Edge
	{
		$results = parent::forAny($match, ...$matches);

		if (count($results) > 1) {
			throw new UnexpectedValueException(sprintf(
				'Relationship limited to one Edge Entity but returned more than one'
			));
		}

		return $results->at(0);
	}


	/**
	 * Get the related node entity of() the specified classes.
	 *
	 * If a related node entity exists but does not match ALL of the concerns, NULL will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N>|string ...$concerns
	 * @return ?N
	 */
	public function get(string ...$concerns): ?Node
	{
		$results = parent::get(...$concerns);

		if (count($results) > 1) {
			throw new UnexpectedValueException(sprintf(
				'Relationship limited to one Node Entity returned more than one linked Node'
			));
		}

		return $results->at(0);
	}


	/**
	 * Get the related node entity ofAny() of the specified classes.
	 *
	 * If a related node entity exists but does not match ANY of the concerns, NULL will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N>|string ...$concerns
	 * @return ?N
	 */
	public function getAny(string ...$concerns): ?Node
	{
		$results = parent::getAny(...$concerns);

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

		$hash = $this->getIndex(Index::active, $node);

		if (!$hash) {
			$this->unset();

			$hash = $this->resolveEdge($node, $data);

		} else {
			if (is_array($data)) {
				$this->active[$hash]->assign($data);
			}
		}

		return $this;
	}
}
