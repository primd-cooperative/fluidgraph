<?php

namespace FluidGraph;

Enum Status: string
{
	case fastened = 'fastened'; // An entity which has only been fastened to content.
	case inducted = 'inducted'; // An entity which is pending attachment to the graph
	case attached = 'attached'; // An entity which has been attached to the graph
	case released = 'released'; // An entity which is pending detachment from the graph
	case detached = 'detached'; // An entity which has been detached from the graph
}
