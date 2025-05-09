<?php

namespace FluidGraph;


abstract class Node extends Element
{
	/**
	 *
	 */
	public function labels(): array
	{
		return isset($this->__content__)
			? $this->__content__->labels
			: []
		;
	}
}
