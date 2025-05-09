<?php

namespace FluidGraph\Content;

use FluidGraph\Status;

/**
 * The content base provides the common properties for edge and node contents.
 *
 * Content can be thought of as the ontological "being" of an element.  The model edges / nodes
 * are simply expressions of this content, and map their properties to the content.
 */
abstract class Base
{
	/**
	 * The identity of the element as it is or was in the graph.
	 */
	public ?int $identity = NULL;

	/**
	 * The labels of the element
	 */
	public array $labels = [];

	/**
	 * The operative properties of the element (as managed by/on its models)
	 */
	public array $operative = [];

	/**
	 * The original properties of the element (as retreived from the graph)
	 */
	public array $original = [];

	/**
	 * The status of the element.
	 */
	public Status $status = Status::INDUCTED;
}
