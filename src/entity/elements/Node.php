<?php

namespace FluidGraph;

use RuntimeException;

abstract class Node extends Element
{
	/**
	 * @var Content\Node
	 */
	public protected(set) ?Content\Base $__content__ {
		get {
			if (!isset($this->__content__)) {
				$this->__content__ = new Content\Node($this);
			}

			return $this->__content__;
		}
	}


	/**
	 * Attach one or more labels to the node
	 */
	public function label(string ...$labels): static
	{
		foreach ($labels as $label) {
			if (!isset($this->__content__->labels[$label])) {
				$this->__content__->labels[$label] = Status::INDUCTED;
				continue;
			}

			if ($this->__content__->labels[$label] == Status::RELEASED) {
				$this->__content__->labels[$label] = Status::ATTACHED;
				continue;
			}

			if ($this->__content__->labels[$label] == Status::DETACHED) {
				$this->__content__->labels[$label] = Status::INDUCTED;
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
		$intersection = array_intersect($labels, array_keys($this->__content__->labels));

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
		$intersection = array_intersect($labels, array_keys($this->__content__->labels));

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
			if (!isset($this->__content__->labels[$labels])) {
				continue;
			}

			if ($this->__content__->labels[$labels] == Status::ATTACHED) {
				$this->__content__->labels[$labels] = Status::RELEASED;
				continue;
			}

			if (in_array($this->__content__->labels[$labels], [Status::FASTENED, Status::INDUCTED])) {
				unset($this->__content__->labels[$labels]);
				continue;
			}
		}

		return $this;
	}
}
