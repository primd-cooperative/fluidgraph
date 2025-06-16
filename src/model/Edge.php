<?php

namespace FluidGraph;

use FluidGraph\Relationship\Reference;

/**
 *
 */
abstract class Edge extends Entity
{
	public Element\Node $source {
		get {
			return $this->__element__->source;
		}
	}

	public Element\Node $target {
		get {
			return $this->__element__->target;
		}
	}

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
		return $this->for($node, Reference::from);
	}


	/**
	 *
	 */
	public function for(Element\Node|Node|string $node, Link ...$types): bool
	{
		return $this->__element__->for($node, ...$types);
	}


	/**
	 *
	 */
	public function to(Element\Node|Node|string $node): bool
	{
		return $this->for($node, Reference::to);
	}
}
