<?php

namespace FluidGraph\Relationship;

/**
 * The possible loading behaviors for relationships
 */
enum Mode
{
	case lazy;
	case eager;
	case manual;
}
