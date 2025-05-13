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
	static public function uuid7(Element $content): void
	{
		if (!isset($content->active['id'])) {
			$content->active['id'] = Uuid::uuid7();
		}
	}
}
