<?php

namespace FluidGraph;

/**
 * Available query scopes and their corresponding aliases
 */
enum Scope: string
{
	case subject  = 's';
	case concern  = 'c';
	case relation = 'r';
	case object   = 'o';
}
