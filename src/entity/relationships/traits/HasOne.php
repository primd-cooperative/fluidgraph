<?php

namespace FluidGraph\Relationship;

use FluidGraph;

trait HasOne
{
	use AbstractRelationship;

	/**
	 *
	 */
	public function set(FluidGraph\Node $node, array $data = []): static
	{
		if ($pos = $this->includes($node) !== FALSE) {

			//
			// An edge for the node we're setting is already included, just update it.
			//

			$this->included[$pos]->assign($data);

		} else {
			if ($pos = $this->excludes($node) !== FALSE) {

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

				$edge = $this->make($this->type, $data, FluidGraph\Maker::SKIP_CHECKS);

				$edge->__content__->bindTarget($node->__content__);
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
