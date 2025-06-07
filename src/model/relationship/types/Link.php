<?php

namespace FluidGraph\Relationship;

use FluidGraph\Edge;
use FluidGraph\Node;
use FluidGraph\Entity;

/**
 * A type of relationship that links to many nodes with one edge per node.
 */
trait Link
{
	/**
	 *
	 */
	public function realize(Node $concern, array $data = []): string
	{
		$hash = $this->index($concern, Index::LOADED);

		if ($hash) {
			$this->active[$hash] = $this->loaded[$hash];

		} else {
			$edge = $this->kind::make($data, Entity::MAKE_ASSIGN);
			$hash = spl_object_hash($edge);

			if ($this->method == Method::TO) {
				$source = $this->subject;
				$target = $concern;
			} else {
				$source = $concern;
				$target = $this->subject;
			}

			$edge->with(
				function(Node $source, Node $target): void {
					/**
					 * @var Edge $this
					 */
					$this->__element__->source = $source;
					$this->__element__->target = $target;
				},
				$source,
				$target
			);

			$this->active[$hash] = $edge;
		}

		return $hash;
	}
}
