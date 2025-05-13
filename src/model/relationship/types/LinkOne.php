<?php

namespace FluidGraph\Relationship;

use FluidGraph;

/**
 * A type of relationship that links to one node with one edge.
 */
trait LinkOne
{
	use AbstractRelationship;

	/**
	 *
	 */
	public function set(FluidGraph\Node $target, array $data = []): static
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
				$edge   = $this
					->make($this->type, $data, FluidGraph\Builder::SKIP_CHECKS)
					->with(function() use (&$source, &$target) {
						//
						// If this lines shows error it's because tooling can tell the scope;
						//

						$this->__element__->source = &$source->__element__;
						$this->__element__->target = &$target->__element__;
					})
				;
			}

			$this->unset();

			$this->included[] = $edge;
		}

		return $this;
	}


	/**
	 *
	 */
	public function unset(): static
	{
		//
		// We always pop the current edge, as this is a to-one relationship
		//

		$edge = array_pop($this->included);

		if ($edge && $edge->identity()) {

			//
			// If the current edge is persisted, we need to exclude it.  If it wasn't then
			// it doesn't matter as it was never "real" or was already deleted.
			//

			$this->excluded[] = $edge;
		}

		return $this;
	}
}
