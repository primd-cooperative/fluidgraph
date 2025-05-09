<?php

namespace FluidGraph;

Enum Status
{
	case INDUCTED; // An entity which is pending attachment to the graph
	case ATTACHED; // An entity which has been attached to the graph
	case RELEASED; // An entity which is pending detachment from the graph
	case DETACHED; // An entity which has been detached from the graph
}
