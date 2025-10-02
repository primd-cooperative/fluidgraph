<?php

namespace FluidGraph\Entity\Id;

use FluidGraph\Entity;
use FluidGraph\Element;

trait Hash
{
	use Entity\Id;
	use Entity\CreateHook;

	/**
	 * @return array<string, array<callable>>
	 */
	abstract static public function getHashKeys(): array;

	/**
	 *
	 */
	static public function createHash(Element|Entity $element): array
	{
		if ($element instanceof Entity) {
			$element = $element->__element__;
		}

		$results = [];

		foreach (static::getHashKeys() as $field => $transforms) {
			if (is_numeric($field)) {
				$field      = $transforms;
				$transforms = [];
			}

			$results[$field] = $element->active[$field];

			foreach ($transforms as $callback) {
				$results[$field] = $callback($results[$field]);
			}
		}

		return [
			'id' => md5(implode(':', $results))
		];
	}
}
