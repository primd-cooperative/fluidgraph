<?php

namespace FluidGraph;

enum Operation: int {
	case CREATE = 0;
	case UPDATE = 1;
	case DELETE = 2;
}
