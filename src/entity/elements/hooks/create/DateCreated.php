<?php

namespace FluidGraph\Element;

use DateTime;
use FluidGraph\Content;

trait DateCreated
{
	use CreateHook;

	public protected(set) ?DateTime $dateCreated;

	/**
	 *
	 */
	static public function dateCreated(Content\Element $content): void
	{
		$content->active['dateCreated'] = new DateTime();
	}
}
