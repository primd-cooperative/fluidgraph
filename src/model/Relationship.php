<?php

namespace FluidGraph;

use Bolt\enum\Signature;

use FluidGraph\Relationship\Mode;
use FluidGraph\Relationship\Index;
use FluidGraph\Relationship\Method;
use FluidGraph\Relationship\Direction;
use FluidGraph\Relationship\Operation;
use FluidGraph\Relationship\Order;

use InvalidArgumentException;
use Countable;
use DateTime;
use Closure;

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
	 *
	 */
	protected ?Closure $where = NULL;


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
	public function count(Operation|string $operation_or_concern = '', string ...$concerns): int
	{
		$use_graph = !is_null($this->subject->identity()) && (
			$this->mode == Mode::MANUAL || ($this->mode == Mode::LAZY && !isset($this->loadTime))
		);

		if ($use_graph) {
			return $this->getGraphCount($operation_or_concern, ...$concerns);
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





	/**
	 * Determine whether or not the relationship contains all of a set of nodes or node types.
	 */
	public function contains(Node|Element\Node|string $node, Node|Element\Node|string ...$nodes): bool
	{
		array_unshift($nodes, $node);

		if ($this->mode == Mode::MANUAL) {
			$concerns = array_filter($nodes, fn($node) => is_string($node));
			$nodes    = array_filter($nodes, fn($node) => !is_string($node));
			$query    = $this->getGraphQuery(Operation::ALL, ...$concerns);

			if (count($nodes)) {
				$query
					->run('AND %s', $query->where->scope('c', function($any, $id) use ($nodes) {
						return $any(...array_map($id, $nodes));
					}))
					->run('RETURN %s', $query->where->scope('c', function($count, $eq) use ($nodes) {
						return $eq($count, count($nodes));
					}))
				;

			} else {
				$query->run('RETURN %s', $query->where->scope('c', function($gte, $count) {
					return $gte($count, 1);
				}));

			}

			return $query->pull(Signature::RECORD)[0];

		} else {
			foreach ($nodes as $node) {
				if ($this->index($node) === FALSE) {
					return FALSE;
				}
			}
		}

		return TRUE;
	}


	/**
	 * Determine whether or not the relationship contains any of a set of nodes or node types.
	 */
	public function containsAny(Node|Element\Node|string $node, Node|Element\Node|string ...$nodes): bool
	{
		array_unshift($nodes, $node);

		if ($this->mode == Mode::MANUAL) {
			$concerns = array_filter($nodes, fn($node) => is_string($node));
			$nodes    = array_filter($nodes, fn($node) => !is_string($node));
			$query    = $this->getGraphQuery(Operation::ANY, ...$concerns);

			if (count($nodes)) {
				$query
					->run('AND %s', $query->where->scope('c', function($any, $id) use ($nodes) {
						return $any(...array_map($id, $nodes));
					}))
					->run('RETURN %s', $query->where->scope('c', function($count, $gte) use ($nodes) {
						return $gte($count, 1);
					}))
				;

			} else {
				$query->run('RETURN %s', $query->where->scope('c', function($gte, $count) {
					return $gte($count, 1);
				}));

			}

			return $query->pull(Signature::RECORD)[0];

		} else {
			foreach ($nodes as $node) {
				if ($this->index($node) !== FALSE) {
					return TRUE;
				}
			}

		}

		return FALSE;
	}


	public function fetch(Closure $conditions): static
	{
		$this->where = $conditions;

		$this->load();

		$this->where = NULL;

		return $this;
	}


	public function limit(int $limit): static
	{
		$this->limit = $limit;

		return $this;
	}


	/**
	 * Load the edges/nodes for the relationship.
	 *
	 * Called from Element::as() -- for Node elements only, Edge elements do not have relationships.
	 */
	public function load(Operation|string $operation_or_concern = '', string ...$concerns): static
	{
		if (!isset($this->graph)) {
			return $this;
		}

		if (is_null($this->subject->identity())) {
			return $this;
		}

		if (!isset($this->loadTime)) {
			$this->loader = function() use ($operation_or_concern, $concerns) {
				unset($this->loader);

				$query = $this->getGraphQuery($operation_or_concern, ...$concerns);

				if ($this->where) {
					$query->run('AND (%s)', $query->where->scope('r', $this->where));
				}

				$query->run('RETURN s, c, r');

				if (count($this->ordering)) {
					$ordering = [];

					foreach ($this->ordering as $order) {
						$ordering[] = sprintf(
							'%s.%s %s',
							match ($order[0]) {
								Order::SUBJECT  => 's',
								Order::CONCERN  => 'c',
								Order::RELATION => 'r',
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
	public function reload(): static
	{
		if (isset($this->loadTime)) {
			unset($this->loadTime);

			$this->load();
		}

		return $this;
	}


	/**
	 *
	 */
	public function where(?Closure $conditions): static
	{
		$this->where = $conditions;

		return $this;
	}


	protected function getGraphQuery(Operation|string $operation_or_concern = '', string ...$concerns)
	{
		if (!$operation_or_concern instanceof Operation) {
			$operation = Operation::ANY;

			if ($operation_or_concern) {
				array_unshift($concerns, $operation_or_concern);
			}

		} else {
			$operation = $operation_or_concern;
		}

		return $this->graph
			->run(
				match ($this->method) {
					Method::TO   => 'MATCH (s:%s)-[r:%s]->(c:%s)',
					Method::FROM => 'MATCH (s:%s)<-[r:%s]-(c:%s)'
				},
				$this->subject::class,
				$this->kind,
				implode(
					$operation->value,
					$concerns ?: $this->concerns
				)
			)
			->run('WHERE id(s) = $subject')
			->set('subject', $this->subject->identity())
		;
	}


	protected function getGraphCount(Operation|string $operation_or_concern = '', string ...$concerns): int
	{
		return (int) $this
			->getGraphQuery($operation_or_concern, ...$concerns)
			->run('RETURN COUNT(r) AS total')
			->pull(Signature::RECORD)[0]
		;
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
