<?php

namespace FluidGraph\Relationship;

enum Mode
{
	case lazy;
	case eager;
	case manual;
}
