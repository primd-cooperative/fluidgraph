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
	static public function dateCreated(Element $content): void
	{
		$content->active['dateCreated'] = new DateTime();
	}
}
