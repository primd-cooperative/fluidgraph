<?php

namespace FluidGraph;

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
	 *
	 */
	public function for(
		Reference $type,
		Element\Node|Node|string $match,
		Element\Node|Node|string ...$matches
	): bool {
		return $this->__element__->for($type, $match, ...$matches);
	}


	/**
	 *
	 */
	public function forAny(
		Reference $type,
		Element\Node|Node|string $match,
		Element\Node|Node|string ...$matches
	): bool {
		return $this->__element__->forAny($type, $match, ...$matches);
	}
}
