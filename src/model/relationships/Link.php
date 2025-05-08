<?php

namespace FluidGraph;

class Link extends Relationship
{
	protected ?Edge $edge = NULL;


	public function onMerge(Operation $operation, Graph $graph)
	{
		if ($operation == Operation::DELETE) {
			$graph->detach(...$this->edges);
		} else {
			$graph->attach(...$this->edges);
		}
	}

}
