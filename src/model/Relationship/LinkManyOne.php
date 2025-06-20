<?php

namespace FluidGraph\Relationship;

use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Relationship;

use UnexpectedValueException;

/**
 * A type of relationship that links to/from one node with one edge.
 */
abstract class LinkManyOne extends Relationship
{
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
		}

		$this->active[$hash]->assign($data);

		return $this;
	}
}
