<?php

namespace FluidGraph;

/**
 *
 */
abstract class Node extends Entity
{
	/**
	 * @var Element\Node
	 */
	public protected(set) ?Element $__element__ {
 		get {
			if (!isset($this->__element__)) {
				$this->__element__ = new Element\Node($this);
			}

			return $this->__element__;
		}
	}


	/**
	 * Attach one or more labels to the node
	 */
	public function label(string $label, string ...$labels): static
	{
		$this->__element__->label($label, ...$labels);

		return $this;
	}


	/**
	 *
	 */
	public function like(string $label, string ...$labels): bool
	{
		return $this->__element__->like($label, ...$labels);
	}


	/**
	 *
	 */
	public function likeAny(string $label, string ...$labels): bool
	{
		return $this->__element__->likeAny($label, ...$labels);
	}


	/**
	 * Remove one or more labels from the element
	 */
	public function unlabel(string ...$labels): static
	{
		$this->__element__->unlabel(...$labels);

		return $this;
	}
}
