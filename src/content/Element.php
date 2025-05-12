<?php

namespace FluidGraph\Content;

use FluidGraph;

/**
 * The content base provides the common properties for edge and node contents.
 *
 * Content can be thought of as the ontological "being" of an element.  The model edges / nodes
 * are simply expressions of this content, and map their properties to the content.
 */
abstract class Element
{
	/**
	 * The latest entity instance of the content
	 */
	public ?FluidGraph\Element $entity;

	/**
	 * The identity of the element as it is or was in the graph.
	 */
	readonly public int $identity;

	/**
	 * The active properties of the element (as managed by/on its models)
	 */
	public array $active = [];


	/**
	 * The labels of the element
	 */
	public array $labels = [];

	/**
	 * The loaded properties of the element (as retreived from the graph)
	 */
	public array $loaded = [];

	/**
	 * The status of the element.
	 */
	public ?FluidGraph\Status $status = NULL;


	/**
	 *
	 */
	public function __construct(?FluidGraph\Element $element = NULL)
	{
		if ($element) {
			$this->entity = $element;
			$this->labels[$element::class] = FluidGraph\Status::FASTENED;
		}

	}


	/**
	 *
	 */
	public function __debugInfo()
	{
		return array_filter(
			get_object_vars($this),
			function($key) {
				return !in_array(
					$key,
					[
						'graph',
						'entity'
					]
				);
			},
			ARRAY_FILTER_USE_KEY
		);
	}


	/**
	 *
	 */
	public function identify(int $identity): static
	{
		$this->identity = $identity;

		return $this;
	}
}
