<?php

namespace FluidGraph\Relationship;

use FluidGraph;

/**
 * A type of relationship that links to many nodes with one edge per node.
 */
trait LinkMany
{
	use AbstractRelationship;

	use AbstractRelationship;

	/**
	 *
	 */
	public function set(FluidGraph\Node $target, array $data = []): static
	{
		if ($target->status() == FluidGraph\Status::DETACHED) {
			throw new InvalidArgumentException(sprintf(
				'You cannot set a detached target "%s" on "%s"',
				$target::class,
				static::class
			));
		}

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
					->make($this->type, $data, FluidGraph\Maker::SKIP_CHECKS)
					->with(function() use (&$source, &$target) {
						//
						// If this lines shows error it's because tooling can tell the scope;
						//

						$this->__content__->source = &$source->__content__;
						$this->__content__->target = &$target->__content__;
					})
				;
			}

			$this->included[] = $edge;
		}

		return $this;
	}


	/**
	 *
	 */
	public function unset(FluidGraph\Node $target): static
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
