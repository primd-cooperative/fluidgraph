<?php

namespace FluidGraph;

abstract class Edge extends Element
{
	protected Node $__target__;

	/**
	 * Attach one or more labels to the edge
	 */
	public function is(string $label): static
	{
		return $this;
	}


	/**
	 *
	 */
	public function isA(string $label): bool
	{
		return FALSE;
	}


	/**
	 *
	 */
	public function target(?Node $node = NULL): Node
	{
		if ($node) {
			$this->__target__ = $node;
		}

		return $this->__target__;
	}
}
