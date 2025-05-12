<?php

namespace FluidGraph;

/**
 *
 */
abstract class Edge extends Element
{
	/**
	 * @var Content\Edge
	 */
	public protected(set) ?Content\Element $__content__ {
		get {
			if (!isset($this->__content__)) {
				$this->__content__ = new Content\Edge($this);
			}

			return $this->__content__;
		}
	}

	/**
	 *
	 */
	public function for(Content\Node|Node|string $node): bool
	{
		if (!isset($this->__content__->target)) {
			return FALSE;
		}

		if (is_string($node)) {
			if (!isset($this->__content__->target->labels[$node])) {
				return FALSE;
			}

			return in_array($this->__content__->target->labels[$node], [
				Status::FASTENED,
				Status::INDUCTED,
				Status::ATTACHED
			]);
		}

		if ($node instanceof Node) {
			$node = $node->__content__;
		}

		return $node === $this->__content__->target;
	}
}
