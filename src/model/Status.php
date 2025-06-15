<?php

namespace FluidGraph;

Enum Status
{
	case fastened; // An entity which has only been fastened to content.
	case inducted; // An entity which is pending attachment to the graph
	case attached; // An entity which has been attached to the graph
	case released; // An entity which is pending detachment from the graph
	case detached; // An entity which has been detached from the graph
}
