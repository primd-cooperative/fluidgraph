<?php

namespace FluidGraph\Relationship;

/**
 * Constrains and provides references for the indexes available on relationships
 */
enum Index: string
{
	case active = 'active';
	case loaded = 'loaded';
}
