<?php

namespace FluidGraph;

use Bolt\enum\Signature;
use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Status;
use FluidGraph\Element;
use FluidGraph\Graph;
use FluidGraph\Relationship\Mode;
use FluidGraph\Relationship\Index;
use FluidGraph\Relationship\Method;

use InvalidArgumentException;
use Countable;
use DateTime;
use Closure;
use FluidGraph\Relationship\Direction;
use FluidGraph\Relationship\Order;

/**
 * @template E of Edge
 */
abstract class Relationship implements Countable
{
	use HasGraph;

	/**
	 * @var array<E>
	 */
	protected array $active = [] {
		&get {
			if (isset($this->loader)) {
				$this->loadTime = call_user_func($this->loader);
			}

			return $this->active;
		}
	}

	/**
	 * @var array<E>
	 */
	protected array $loaded = [] {
		&get {
			if (isset($this->loader)) {
				$this->loadTime = call_user_func($this->loader);
			}

			return $this->loaded;
		}
	}

	/**
	 * @var Closure:DateTime
	 */
	protected Closure $loader;


	/**
	 * When the relationship was loaded
	 */
	protected DateTime $loadTime;


	/**
	 *
	 */
	protected int $limit = 0;


	/**
	 *
	 */
	protected Method $method = Method::TO;


	/**
	 *
	 */
	protected int $offset = 0;


	/**
	 *
	 */
	protected array $ordering = [];


	/**
	 * Construct a new Relationship
	 *
	 * @param Node $subject The subject for this relationship.
	 * @param class-string<E> $kind The edge type that defines the relationship
	 * @param array<class-string<Node>> $concerns
	 */
	public function __construct(
		public protected(set) Node $subject,
		public protected(set) string $kind,
		public protected(set) array $concerns = [],
		public protected(set) Mode $mode = Mode::LAZY
	) {
		if (!is_subclass_of($kind, Edge::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot create relationship of non-edge kind "%s"',
				$kind
			));
		}

		$this->kind     = $kind;
		$this->subject  = $subject;
	}

	/**
	 *
	 */
	public function __debugInfo()
	{
		return array_filter(
			get_object_vars($this),
			fn($key) => !in_array(
					$key,
					[
						'graph'
					]
				),
			ARRAY_FILTER_USE_KEY
		);
	}


	/**
	 * Assign data to the edges whose targets are one of any such node or label
	 */
	public function assign(array $data, Node|Element\Node|string ...$nodes): static
	{
		foreach ($nodes as $node) {
			foreach ($this->active as $edge) {
				if ($edge->for($node, $this->method)) {
					$edge->assign($data);
				}
			}
		}

		return $this;
	}


	/**
	 * Clean the relationship of detached nodes.
	 */
	public function clean(): static
	{
		foreach ($this->loaded as $i => $edge) {
			if ($edge->__element__->status == Status::DETACHED) {
				unset($this->loaded[$i]);
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function count(string ...$concerns): int
	{
		$use_graph = $this->subject->identity() && (
			$this->mode == Mode::MANUAL || ($this->mode == Mode::LAZY && !$this->loadTime)
		);

		if ($use_graph) {
			return $this->getGraphCount(...$concerns);
		} else {
			if ($concerns) {
				$count = 0;

				foreach ($this->active as $edge) {
					foreach ($concerns as $concern) {
						if ($edge->for($concern)) {
							$count++;
							break;
						}
					}
				}

				return $count;

			} else {
				return count($this->active);

			}
		}
	}


	protected function getGraphQuery(string ...$concerns)
	{
		return $this->graph
			->run(
				match ($this->method) {
					Method::TO   => 'MATCH (s:%s)-[r:%s]->(c:%s)',
					Method::FROM => 'MATCH (s:%s)<-[r:%s]-(c:%s)'
				},
				$this->subject::class,
				$this->kind,
				implode(
					'|',
					$concerns ?: $this->concerns
				)
			)
			->run('WHERE id(s) = $subject')
			->set('subject', $this->subject->identity())
		;
	}


	protected function getGraphCount(string ...$concerns): int
	{
		return (int) $this
			->getGraphQuery(...$concerns)
			->run('RETURN COUNT(r) AS total')
			->pull(Signature::RECORD)[0]
		;
	}


	/**
	 * Determine whether or not the relationship contains all of a set of nodes or node types.
	 */
	public function contains(Node|Element\Node|string ...$nodes): bool
	{
		foreach ($nodes as $node) {
			if ($this->index($node) === FALSE) {
				return FALSE;
			}
		}

		return TRUE;
	}


	/**
	 * Determine whether or not the relationship contains any of a set of nodes or node types.
	 */
	public function containsAny(Node|Element\Node|string ...$nodes): bool
	{
		foreach ($nodes as $node) {
			if ($this->index($node) !== FALSE) {
				return TRUE;
			}
		}

		return FALSE;
	}


	public function limit(int $limit): static
	{
		$this->limit = $limit;

		return $this;
	}


	/**
	 * Load the edges/nodes for the relationship.
	 *
	 * This method should be implemented by a concrete relationship implementation which can
	 * choose to implement more advanced loading features such as Eager and Lazy loading.
	 *
	 * Called from Element::as() -- for Node elements only, Edge elements do not have relationships.
	 */
	public function load(string ...$concerns): static
	{
		if (!$this->subject->identity()) {
			return $this;
		}

		if ($this->mode == Mode::MANUAL && !func_num_args()) {
			return $this;
		}

		if (!isset($this->loadTime)) {
			$this->loader = function() use ($concerns) {
				unset($this->loader);

				$query = $this->getGraphQuery(...$concerns)->run('RETURN s, c, r');

				if (count($this->ordering)) {
					$ordering = [];

					foreach ($this->ordering as $order) {
						$ordering[] = sprintf(
							'%s.%s %s',
							match ($order[0]) {
								Order::SUBJECT => 's',
								Order::CONCERN => 'c',
							},
							$order[2],
							$order[1]->value
						);
					}

					$query->run('ORDER BY %s', implode(',', $ordering));
				}

				if ($this->offset) {
					$query->run('SKIP %s', $this->offset);
				}

				if ($this->limit) {
					$query->run('LIMIT %s', $this->limit);
				}

				$this->loaded = [];

				foreach ($query->get()->is($this->kind)->as($this->kind) as $edge) {
					$hash = spl_object_hash($edge->__element__);

					$this->loaded[$hash] = $edge;
					$this->active[$hash] = $edge;

					if ($this->mode == Mode::MANUAL) {
						$this->offset++;
					}
				}

				return new DateTime();
			};

			switch ($this->mode) {
				case Mode::EAGER:
					$this->loadTime = call_user_func($this->loader);
					break;

				case Mode::MANUAL:
					call_user_func($this->loader);
					break;
			}
		}

		return $this;
	}


	/**
	 * Merge the relationship into the graph object.
	 *
	 * This method should be implemented by a concrete relationship implementation which can
	 * choose to implement more advanced merging features such as Merge hooks.
	 *
	 * Called from Queue::merge().
	 */
	public function merge(Graph $graph): static
	{
		for($class = static::class; $class != Relationship::class; $class = get_parent_class($class)) {
			foreach (class_uses($class) as $trait) {
				if (!in_array(Relationship\MergeHook::class, class_uses($trait))) {
					continue;
				}

				$parts  = explode('\\', $trait);
				$method = 'merge' . end($parts);

				$this->$method($graph);
			}
		}

		return $this;
	}


	public function offset(int $offset): static
	{
		$this->offset = $offset;

		return $this;
	}



	/**
	 *
	 */
	public function orderBy(Order $order, Direction $direction, string $property): static
	{
		$this->ordering[] = func_get_args();

		return $this;
	}


	/**
	 *
	 */
	public function reload()
	{
		if (isset($this->loadTime)) {
			unset($this->loadTime);

			$this->load();
		}

		return $this;
	}


	/**
	 * Determine, by index, whether or not a node is included in the current relationship.
	 *
	 * Note, this will only return the first index, it's possible that a node exists more than once.
	 */
	protected function index(Element\Node|Node|string $node, Index $index = Index::ACTIVE): string|false
	{
		$index = $index->value;

		foreach ($this->$index as $i => $edge) {
			if ($edge->for($node, $this->method)) {
				return $i;
			}
		}

		return FALSE;
	}


	/**
	 *
	 */
	protected function realize(Node $concern, array $data = []): string
	{
		$hash = $this->index($concern, Index::LOADED);

		if ($hash) {
			$this->active[$hash] = $this->loaded[$hash];

		} else {
			$edge = $this->kind::make($data, Entity::MAKE_ASSIGN);
			$hash = spl_object_hash($edge);

			if ($this->method == Method::TO) {
				$source = $this->subject;
				$target = $concern;
			} else {
				$source = $concern;
				$target = $this->subject;
			}

			$edge->with(
				function(Node $source, Node $target): void {
					/**
					 * @var Edge $this
					 */
					$this->__element__->source = $source;
					$this->__element__->target = $target;
				},
				$source,
				$target
			);

			$this->active[$hash] = $edge;
		}

		return $hash;
	}


	/**
	 * Validate a target against basic rules.
	 *
	 * - No Released or Detatched Targets
	 * - No Targets of Unsupported Types
	 */
	protected function validate(Node $target)
	{
		if ($target->status(Status::RELEASED, Status::DETACHED)) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include released or detached target "%s" on "%s"',
				$target::class,
				static::class
			));
		}

		if ($this->concerns && !array_intersect(Element::labels($target->__element__), $this->concerns)) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include a concern of class "%s" on "%s"',
				$target::class,
				static::class
			));
		}
	}
}
