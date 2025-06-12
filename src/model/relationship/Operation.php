<?php

namespace FluidGraph\Relationship;

enum Operation: string
{
	case ANY = '|';
	case ALL = '&';
}
