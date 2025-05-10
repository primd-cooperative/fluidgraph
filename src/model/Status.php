<?php

namespace FluidGraph;

Enum Status
{
	case FASTENED; // An entity which has only been fastened to content.
	case INDUCTED; // An entity which is pending attachment to the graph
	case ATTACHED; // An entity which has been attached to the graph
	case RELEASED; // An entity which is pending detachment from the graph
	case DETACHED; // An entity which has been detached from the graph
}
