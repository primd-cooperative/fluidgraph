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
	 *
	 */
	public function from(Element\Node|Node|string $node): bool
	{
		return $this->for(Method::FROM, $node);
	}


	/**
	 *
	 */
	public function for(Method $method, Element\Node|Node|string $node): bool
	{
		$property = match ($method) {
			Method::TO   => 'target',
			Method::FROM => 'source'
		};

		if (!isset($this->__element__->$property)) {
			return FALSE;
		}

		if (is_string($node)) {
			if (!isset($this->__element__->$property->labels[$node])) {
				return FALSE;
			}

			return in_array($this->__element__->$property->labels[$node], [
				Status::FASTENED,
				Status::INDUCTED,
				Status::ATTACHED
			]);
		}

		if ($node instanceof Node) {
			$node = $node->__element__;
		}

		return $node === $this->__element__->$property;
	}


	/**
	 *
	 */
	public function to(Element\Node|Node|string $node): bool
	{
		return $this->for(Method::TO, $node);
	}
}
