<?php

namespace FluidGraph\Element;

use FluidGraph;
use FluidGraph\Status;

use InvalidArgumentException;

/**
 *
 */
class Node extends FluidGraph\Element
{
	/**
	 * Get any relationships for this node
	 *
	 * @return array<FluidGraph\Relationship>
	 */
	static public function relationships(self $node): array
	{
		return array_filter(
			$node->active,
			fn($value) => $value instanceof FluidGraph\Relationship
		);
	}


	/**
	 * @template T of FluidGraph\Node
	 * @param null|array|class-string<T> $class
	 * @param array<string, mixed> $defaults
	 * @return T
	 */
	public function as(null|array|string $class = NULL, array $defaults = []): FluidGraph\Node
	{
		if (is_string($class)) {
			if (!class_exists($class)) {
				throw new InvalidArgumentException(sprintf(
					'Cannot make "%s," no such class exists',
					$class
				));
			}

			if (!is_subclass_of($class, FluidGraph\Node::class, TRUE)) {
				throw new InvalidArgumentException(sprintf(
					'Cannot make "%s" from non-Node result',
					$class
				));
			}
		}

		return parent::as($class, $defaults);
	}


	/**
	 * Attach one or more labels to the node element
	 */
	public function label(string $label, string ...$labels): static
	{
		array_unshift($labels, $label);

		foreach ($labels as $label) {
			if (!isset($this->labels[$label])) {
				$this->labels[$label] = Status::fastened;
				continue;
			}

			if ($this->labels[$label] == Status::released) {
				$this->labels[$label] = Status::attached;
				continue;
			}
		}

		return $this;
	}


	/**
	 * Remove all or multiple labels from the node element
	 */
	public function unlabel(string ...$labels): static
	{
		if (!count($labels)) {
			$labels = array_diff(array_keys($this->labels), self::classes($this));
		}

		foreach ($labels as $label) {
			if (!isset($this->labels[$label])) {
				continue;
			}

			if ($this->labels[$labels] == Status::attached) {
				$this->labels[$labels] = Status::released;
				continue;
			}

			if (in_array($this->labels[$labels], [Status::fastened, Status::inducted])) {
				unset($this->labels[$labels]);
				continue;
			}
		}

		return $this;
	}
}
