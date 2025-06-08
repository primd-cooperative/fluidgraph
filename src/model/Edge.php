<?php

namespace FluidGraph;

use FluidGraph\Relationship\Method;

/**
 *
 */
abstract class Edge extends Entity
{
	/**
	 * @var Element\Edge
	 */
	public protected(set) ?Element $__element__ {
 		get {
			if (!isset($this->__element__)) {
				$this->__element__ = new Element\Edge($this);
			}

			return $this->__element__;
		}
	}


	/**
	 * Determine wehther or not this edge
	 */
	public function from(Element\Node|Node|string $node): bool
	{
		return $this->of($node, Method::FROM);
	}


	/**
	 *
	 */
	public function for(Element\Node|Node|string $node, Method ...$methods): bool
	{
		return $this->__element__->for($node, ...$methods);
	}


	/**
	 *
	 */
	public function of(Element\Node|Node|string $node, Method ...$methods): bool
	{
		return $this->__element__->of($node, ...$methods);
	}


	/**
	 *
	 */
	public function to(Element\Node|Node|string $node): bool
	{
		return $this->of($node, Method::TO);
	}
}
