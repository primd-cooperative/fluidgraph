<?php

namespace FluidGraph\Entity;

use DateTime;
use FluidGraph\Element;

trait DateModified
{
	use UpdateHook;

	public protected(set) ?DateTime $dateModified;

	/**
	 *
	 */
	static public function updateDateModified(Element $element): array
	{
		return [
			'dateModified' => new DateTime()
		];
	}
}
