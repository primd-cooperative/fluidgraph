<?php

namespace FluidGraph;

enum Matching: string
{
	case any = '|';
	case all = ':';
}
