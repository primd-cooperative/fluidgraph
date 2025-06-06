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
	public function label(string ...$labels): static
	{
		foreach ($labels as $label) {
			if (!isset($this->__element__->labels[$label])) {
				$this->__element__->labels[$label] = Status::FASTENED;
				continue;
			}

			if ($this->__element__->labels[$label] == Status::RELEASED) {
				$this->__element__->labels[$label] = Status::ATTACHED;
				continue;
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function like(string ...$labels): bool
	{
		$intersection = array_intersect($labels, array_keys($this->__element__->labels));

		if (count($intersection) == count($labels)) {
			return TRUE;
		}

		return FALSE;
	}


	/**
	 *
	 */
	public function likeAny(string ...$labels): bool
	{
		$intersection = array_intersect($labels, array_keys($this->__element__->labels));

		if (count($intersection)) {
			return TRUE;
		}

		return FALSE;
	}


	/**
	 * Remove one or more labels from the element
	 */
	public function unlabel(string ...$labels): static
	{
		foreach ($labels as $labels) {
			if (!isset($this->__element__->labels[$labels])) {
				continue;
			}

			if ($this->__element__->labels[$labels] == Status::ATTACHED) {
				$this->__element__->labels[$labels] = Status::RELEASED;
				continue;
			}

			if (in_array($this->__element__->labels[$labels], [Status::FASTENED, Status::INDUCTED])) {
				unset($this->__element__->labels[$labels]);
				continue;
			}
		}

		return $this;
	}
}
