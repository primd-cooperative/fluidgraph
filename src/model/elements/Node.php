<?php

namespace FluidGraph;

use RuntimeException;

abstract class Node extends Element
{
	/**
	 * Attach one or more labels to the node
	 */
	public function is(string ...$label): static
	{
		if (is_null($this->__content__)) {
			throw new RuntimeException(sprintf(
				'Cannot add labels prior to node being attached'
			));
		}

		foreach ($label as $name) {
			if (!isset($this->__content__->labels[$name])) {
				$this->__content__->labels[$name] = Status::UNMERGED;
				continue;
			}

			if ($this->__content__->labels[$name] == Status::DETACHED) {
				$this->__content__->labels[$name] = Status::ATTACHED;
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function isA(string $label): bool
	{
		return FALSE;
	}


	/**
	 *
	 */
	public function isAll(string ...$label): bool
	{
		return FALSE;
	}


	/**
	 *
	 */
	public function isAny(string ...$label): bool
	{
		return FALSE;
	}


	/**
	 * Remove one or more labels from the element
	 */
	public function isNot(string ...$label): static
	{
		if (is_null($this->__content__)) {
			throw new RuntimeException(sprintf(
				'Cannot remove labels prior to node being attached'
			));
		}

		foreach ($label as $name) {
			if (!isset($this->__content__->labels[$name])) {
				continue;
			}

			if ($this->__content__->labels[$name] == Status::ATTACHED) {
				$this->__content__->labels[$name] = Status::DETACHED;
			}

			if ($this->__content__->labels[$name] == Status::UNMERGED) {
				unset($this->__content__->labels[$name]);
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function isNotA(string $label): bool
	{
		return FALSE;
	}


	/**
	 *
	 */
	public function isNotAll(string ...$label): bool
	{
		return FALSE;
	}


	/**
	 *
	 */
	public function isNotAny(string ...$label): bool
	{
		return FALSE;
	}

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
