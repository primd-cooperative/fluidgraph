<?php

namespace FluidGraph;


abstract class Node extends Element
{
	/**
	 *
	 */
	public function labels(): array
	{
		if (!isset($this->__content__)) {
			return [];
		}

		return array_keys(
			array_filter(
				$this->__content__->labels,
				function($status) {
					return in_array($status, [
						Status::FASTENED,
						Status::INDUCTED,
						Status::ATTACHED
					]);
				}
			)
		);
	}
}
