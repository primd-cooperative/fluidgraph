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
	static public function dateModified(Element $element): void
	{
		$element->active['dateModified'] = new DateTime();
	}
}
