<?php

namespace FluidGraph\Entity\Id;

use FluidGraph\Element;
use FluidGraph\Entity;
use Ramsey\Uuid\Uuid;

trait Uuid7
{
	use Entity\Id;
	use Entity\CreateHook;

	/**
	 *
	 */
	static public function createUuid7(Element $element): array
	{
		return [
			'id' => Uuid::uuid7()
		];
	}
}
