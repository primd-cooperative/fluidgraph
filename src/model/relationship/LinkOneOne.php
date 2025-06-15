<?php

namespace FluidGraph\Relationship;

use FluidGraph;
use FluidGraph\Edge;
use FluidGraph\Node;
use FluidGraph\Element;

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
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return E
	 */
	public function of(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): ?Edge
	{
		$edge = $this->any();

		if ($edge) {
			array_unshift($nodes, $node);

			foreach ($nodes as $node) {
				if (!$edge->for($node, $this->type)) {
					return NULL;
				}
			}

			return $edge;
		}

		return NULL;
	}


	/**
	 * Get the edge entity for this relationship only if it corresponds to one or more node(s)/label(s)
	 *
	 * @param Element\Node|Node|class-string $node
	 * @param Element\Node|Node|class-string $nodes
	 * @return E
	 */
	public function for(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): ?Edge
	{
		$edge = $this->any();

		if ($edge) {
			array_unshift($nodes, $node);

			foreach ($nodes as $node) {
				if ($edge->for($node, $this->type)) {
					return $edge;
				}
			}
		}

		return NULL;
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
	public function get(?string $class): ?Node
	{
		$edge = $this->of($class);

		if ($edge) {
			return match ($this->type) {
				Link::to   => $edge->__element__->target->as($class),
				Link::from => $edge->__element__->source->as($class)
			};
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
	public function unset(?Node $node = NULL): static
	{
		if (!$node || $this->getIndex($node)) {
			$this->active = [];
		}

		return $this;
	}
}
