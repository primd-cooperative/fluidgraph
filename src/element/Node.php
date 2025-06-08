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
	 * @param class-string<T> $class
	 * @param array<string, mixed> $defaults
	 * @return T
	 */
	public function as(string $class, array $defaults = []): FluidGraph\Node
	{
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
				$this->labels[$label] = Status::FASTENED;
				continue;
			}

			if ($this->labels[$label] == Status::RELEASED) {
				$this->labels[$label] = Status::ATTACHED;
				continue;
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function like(string $label, string ...$labels): bool
	{
		array_unshift($labels, $label);

		$intersection = array_intersect($labels, array_keys($this->labels));

		if (count($intersection) == count($labels)) {
			return TRUE;
		}

		return FALSE;
	}


	/**
	 *
	 */
	public function likeAny(string $label, string ...$labels): bool
	{
		array_unshift($labels, $label);

		$intersection = array_intersect($labels, array_keys($this->labels));

		if (count($intersection)) {
			return TRUE;
		}

		return FALSE;
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

			if ($this->labels[$labels] == Status::ATTACHED) {
				$this->labels[$labels] = Status::RELEASED;
				continue;
			}

			if (in_array($this->labels[$labels], [Status::FASTENED, Status::INDUCTED])) {
				unset($this->labels[$labels]);
				continue;
			}
		}

		return $this;
	}
}
