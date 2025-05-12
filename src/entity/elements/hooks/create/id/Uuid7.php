<?php

namespace FluidGraph\Element\Id;

use FluidGraph\Element;
use FluidGraph\Content;
use Ramsey\Uuid\Uuid;

trait Uuid7
{
	use Element\Id;
	use Element\CreateHook;

	/**
	 *
	 */
	static public function uuid7(Content\Element $content): void
	{
		if (!isset($content->active['id'])) {
			$content->active['id'] = Uuid::uuid7();
		}
	}
}
