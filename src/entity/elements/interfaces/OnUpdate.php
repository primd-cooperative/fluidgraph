<?php

namespace FluidGraph\Entity;

use FluidGraph;

interface OnUpdate
{
	/**
	 *
	 */
	static public function onUpdate(FluidGraph\Content\Node $content): void;
}
