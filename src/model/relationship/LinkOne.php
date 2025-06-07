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
	 * Get the related node element as a given class.
	 *
	 * If a related node element exists but does not match the class/labels, null will be returned.
	 *
	 * @template N of Node
	 * @param class-string<N> $concern
	 * @return N
	 */
	public function as(string $concern): ?Element\Node
	{
		$edge     = reset($this->active);
		$property = match ($this->method) {
			Method::TO   => 'target',
			Method::FROM => 'source'
		};

		if (!$edge) {
			return NULL;
		}

		if (!in_array($concern, $edge->__element__->$property->labels())) {
			return NULL;
		}

		return $edge->__element__->$property;
	}


	/**
	 * Get the edge or the edge for one or more nodes or node types.
	 *
	 * @param Element\Node|Node|class-string $nodes
	 * @return array<T>
	 */
	public function for(Element\Node|Node|string ...$nodes): ?Edge
	{
		$edge = $this->get();

		if ($edge) {
			if (count($nodes)) {
				foreach ($nodes as $node) {
					if ($edge->for($this->method, $node)) {
						return $edge;
					}
				}

			} else {
				return $edge;
			}
		}

		return NULL;
	}


	/**
	 *
	 */
	public function get(): ?Edge
	{
		return reset($this->active) ?: NULL;
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
