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
	static public function dateModified(Element $content): void
	{
		$content->active['dateModified'] = new DateTime();
	}
}
