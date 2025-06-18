<?php

namespace FluidGraph;

/**
 * @template T of Node
 * @extends Entity\Results<T>
 */
class NodeResults extends Entity\Results
{
	protected array $removed = [];

	// TODO: add as(), of(), ofAny()

	public function unset(Node $node): static
	{
		if ($this->relationship) {
			$this->relationship->unset($node);
		}

		$copy = $this->getArrayCopy();
		$key  = array_search($node, $copy, TRUE);

		if ($key) {
			$this->removed[] = $key;
		}

		return $this;
	}
}
