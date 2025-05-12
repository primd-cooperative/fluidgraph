<?php

namespace FluidGraph\Entity;

use FluidGraph;

interface OnCreate
{
	/**
	 *
	 */
	static public function onCreate(FluidGraph\Content\Node $content): void;
}
