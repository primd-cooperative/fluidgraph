<?php

namespace FluidGraph;

enum Like: string
{
	case any = '|';
	case all = '&';
}
