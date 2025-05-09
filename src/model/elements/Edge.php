<?php

namespace FluidGraph;

abstract class Edge extends Element
{
	/**
	 *
	 */
	public function target(): ?Content\Node
	{
		return $this->__content__
			? $this->__content__->target
			: NULL
		;
	}


	/**
	 *
	 */
	public function source(): ?Content\Node
	{
		return $this->__content__
			? $this->__content__->source
			: NULL
		;
	}
}
