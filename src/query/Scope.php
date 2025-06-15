<?php

namespace FluidGraph;

enum Scope: string
{
	case subject  = 's';
	case concern  = 'c';
	case relation = 'r';
}
