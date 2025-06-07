<?php

namespace FluidGraph;

use ArrayObject;

/**
 * @extends ArrayObject<Element>
 */
class Results extends ArrayObject
{
	/**
	 * Filter the results by one or more labels.
	 *
	 * This is useful when you want to query multiple return types for loading elements but only
	 * want to get a subset.  Most commonly used for relationship loading which will generally
	 * attempt to load the source and target nodes, but only needs the edges instantiated.
	 */
	public function of(string ...$labels): Results
	{
		return new static(
			array_filter(
				$this->getArrayCopy(),
				fn(Element $element) => array_intersect($labels, Element::labels($element))
			)
		);
	}


	/**
	 * Get the element results as an array of entities of a particular class.
	 *
	 * @param class-string<Entity> $class The entity class to instantiate as.
	 * @param array<string, mixed> $defaults Default values for entity construction (if necessary)
	 * @return array<T>
	 */
	public function as(string $class, array $defaults = []): array
	{
		return array_map(
			fn($result) => $result->as($class, $defaults),
			$this->getArrayCopy()
		);
	}
}
