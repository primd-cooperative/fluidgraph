<?php

namespace FluidGraph;

trait HasGraph
{
	public protected(set) Graph $graph;

	/**
	 *
	 */
	public function on(Graph $graph): static
	{
		$this->graph = $graph;

		return $this;
	}
}
