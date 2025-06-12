<?php

namespace FluidGraph\Relationship;

enum Mode
{
	case LAZY;
	case EAGER;
	case MANUAL;
}
