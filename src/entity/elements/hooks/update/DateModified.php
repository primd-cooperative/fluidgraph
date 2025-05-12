<?php

namespace FluidGraph\Element;

use DateTime;
use FluidGraph\Content;

trait DateModified
{
	use UpdateHook;

	public protected(set) ?DateTime $dateModified;

	/**
	 *
	 */
	static public function dateModified(Content\Element $content): void
	{
		$content->active['dateModified'] = new DateTime();
	}
}
