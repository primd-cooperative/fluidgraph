<?php

namespace FluidGraph\Relationship;

use FluidGraph\Edge;
use FluidGraph\Node;
use FluidGraph\Element;
use FluidGraph\Relationship;

/**
 * A type of relationship that links to one node with one edge.
 * @extends \FluidGraph\Relationship
 */
abstract class LinkOne extends Relationship
{
	/**
	 * Get the related node entity of() the specified class as() that class.
	 *
	 * If a related node entity exists but does not match the class, NULL will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N> $class
	 * @return ?N
	 */
	public function as(string $class): ?Node
	{
		$edge = $this->of($class);

		if ($edge) {
			return match ($this->method) {
				Method::TO   => $edge->__element__->target->as($class),
				Method::FROM => $edge->__element__->source->as($class)
			};
		}

		return NULL;
	}


	/**
	 * Get edge entity for this relationship, regardless what it corresponds to.
	 */
	public function get(): ?Edge
	{
		$edge = reset($this->active);

		if ($edge) {
			return $edge->as($this->kind);
		}

		return NULL;
	}


	/**
	 * Get the edge entity for this relationship only if it corresponds to all node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $essence
	 * @param Element\Node|Node|class-string $essences
	 * @return E
	 */
	public function for(Element\Node|Node|string $essence, Element\Node|Node|string ...$essences): ?Edge
	{
		if (count($this->active)) {
			$edge = $this->get();

			array_unshift($essences, $essence);

			foreach ($essences as $essence) {
				if (!$edge->for($essence, $this->method)) {
					return NULL;
				}
			}

			return $edge;
		}

		return NULL;
	}


	/**
	 * Get the edge entity for this relationship only if it corresponds to all node(s)/class(es)
	 *
	 * @param Element\Node|Node|class-string $essence
	 * @param Element\Node|Node|class-string $essences
	 * @return E
	 */
	public function of(Element\Node|Node|string $essence, Element\Node|Node|string ...$essences): ?Edge
	{
		if (count($this->active)) {
			$edge = $this->get();

			array_unshift($essences, $essence);

			foreach ($essences as $essence) {
				if (!$edge->of($essence, $this->method)) {
					return NULL;
				}
			}

			return $edge;
		}

		return NULL;
	}


	/**
	 *
	 */
	public function set(Node $concern, array $data = []): static
	{
		$this->validate($concern);

		$hash = $this->index($concern);

		if (!$hash) {
			$this->unset();

			$hash = $this->realize($concern, $data);
		}

		$this->active[$hash]->assign($data);

		return $this;
	}


	/**
	 *
	 */
	public function unset(): static
	{
		$this->active = [];

		return $this;
	}
}
