<?php

namespace FluidGraph\Entity;

use DateTime;
use FluidGraph\Element;

trait DateCreated
{
	use CreateHook;

	public protected(set) ?DateTime $dateCreated;

	/**
	 *
	 */
	static public function createDateCreated(Element $element): array
	{
		return [
			'dateCreated' => new DateTime()
		];
	}
}
