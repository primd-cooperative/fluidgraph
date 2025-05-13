<?php

namespace FluidGraph;

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
	 *
	 */
	public function for(Element\Node|Node|string $node): bool
	{
		if (!isset($this->__element__->target)) {
			return FALSE;
		}

		if (is_string($node)) {
			if (!isset($this->__element__->target->labels[$node])) {
				return FALSE;
			}

			return in_array($this->__element__->target->labels[$node], [
				Status::FASTENED,
				Status::INDUCTED,
				Status::ATTACHED
			]);
		}

		if ($node instanceof Node) {
			$node = $node->__element__;
		}

		return $node === $this->__element__->target;
	}
}
