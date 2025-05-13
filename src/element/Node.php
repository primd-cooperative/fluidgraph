<?php

namespace FluidGraph\Element;

use FluidGraph;

/**
 *
 */
class Node extends FluidGraph\Element
{
	/**
	 * Get any relationships for this node
	 *
	 * @return array<Relationship>
	 */
	public function relationships(): array
	{
		return array_filter(
			$this->active,
			function($value) {
				return $value instanceof FluidGraph\Relationship;
			}
		);
	}
}
