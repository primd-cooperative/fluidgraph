<?php

namespace FluidGraph;

use RuntimeException;

/**
 *
 */
abstract class Relationship
{
	use HasGraph;

	public protected(set) string $type;

	public protected(set) array $kind;

	public protected(set) Mode $mode;

	protected array $included = [];

	protected array $excluded = [];


	/**
	 *
	 */
	public function __construct(
		string $type,
		Mode $mode = Mode::EAGER,
		string|array $kind = []
	) {
		$this->type  = $type;
		$this->mode  = $mode;
		$this->kind  = $kind;
	}


	/**
	 * Exclude an edge in this relatonship.
	 */
	public function exclude(Edge ...$edges)
	{
		foreach ($edges as $i => $edge) {
			if (get_class($edge) != $this->type) {
				unset($edges[$i]);
				continue;
			}

			if (!in_array(get_class($edge->target()), $this->kind)) {
				unset($edge[$i]);
				continue;
			}


			$position = array_search($edge, $this->included, TRUE);

			if ($position !== FALSE) {
				unset($this->included[$position]);
			}
		}

		array_push($this->excluded, ...$edges);
	}

	/**
	 * Include an edge in this relationship.
	 *
	 * Included edges must have a matching type and a target of a matching kind for the
	 * relationship.
	 */
	public function include(Edge ...$edges)
	{
		foreach ($edges as $edge) {
			if (get_class($edge) != $this->type) {
				throw new RuntimeException(sprintf(
					'Cannot include edge of type "%s", must be "%s".',
					get_class($edge),
					$this->type
				));
			}

			if (!in_array(get_class($edge->target()), $this->kind)) {
				throw new RuntimeException(sprintf(
					'Cannot include edge with target of "%s", must be one of: %s.',
					get_class($edge->target()),
					implode(', ', $this->kind)
				));
			}

			$position = array_search($edge, $this->excluded, TRUE);

			if ($position !== FALSE) {
				unset($this->excluded[$position]);
			}
		}

		array_push($this->included, ...$edges);
	}


	/**
	 * Iterate through all merge hook traits and run them.
	 *
	 * Called from Queue on merge()
	 */
	final public function merge(Operation $operation): static
	{
		foreach (class_uses($this) as $trait) {
			if (!in_array(Relationship\MergeHook::class, class_uses($trait))) {
				continue;
			}

			$method = lcfirst(end(explode('\\', $trait)));

			$this->$method($this->graph, $operation);
		}

		return $this;
	}


	/**
	 * Update the edges whose targets are one of any such node or label
	 */
	public function update(array $data, Node|string ...$nodes)
	{

	}
}
