<?php

namespace FluidGraph\Relationship;

use FluidGraph\Edge;
use FluidGraph\Node;
use FluidGraph\Entity;

/**
 * A type of relationship that links to many nodes with one edge per node.
 */
trait LinkMany
{
	use AbstractRelationship;

	/**
	 *
	 */
	public function set(Node $target, array $data = []): static
	{
		$this->validate($target);

		if ($pos = $this->includes($target) !== FALSE) {

			//
			// An edge for the node we're setting is already included, just update it.
			//

			$this->included[$pos]->assign($data);

		} else {
			if ($pos = $this->excludes($target) !== FALSE) {

				//
				// An existing edge for this node was already excluded, let's use that and
				// make sure it's no longer excluded.
				//

				$edge = $this->excluded[$pos]->assign($data);

				unset($this->excluded[$pos]);

			} else {

				//
				// No existing edge found, so we'll create a new one.
				//

				$source = $this->source;
				$edge   = $this->type::make($data, Entity::MAKE_ASSIGN);

				$edge->with(
					function(Node &$source, Node &$target) {
						/**
						 * @var Edge $this
						 */
						$this->__element__->source = &$source->__element__;
						$this->__element__->target = &$target->__element__;
					},
					$source,
					$target
				);
			}

			$this->included[] = $edge;
		}

		return $this;
	}


	/**
	 *
	 */
	public function unset(Node $target): static
	{
		if ($pos = $this->includes($target) !== FALSE) {
			$edge = $this->included[$pos];

			if ($edge->identity()) {
				//
				// If the current edge is persisted, we need to exclude it.  If it wasn't then
				// it doesn't matter as it was never "real" or was already deleted.
				//

				$this->excluded[] = $edge;
			}

			unset($this->included[$pos]);
		}

		return $this;
	}
}
