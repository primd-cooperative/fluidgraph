<?php

namespace FluidGraph;

use Bolt\enum\Signature;

use FluidGraph\Relationship\Mode;
use FluidGraph\Relationship\Link;
use FluidGraph\Relationship\Index;
use FluidGraph\Relationship\Order;

use InvalidArgumentException;
use RuntimeException;
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
	private static $apex;


	/**
	 *
	 */
	private int $limit = 0;


	/**
	 *
	 */
	private int $offset = 0;


	/**
	 * @param array<Order>
	 */
	private array $orders = [];


	/**
	 *
	 */
	private ?Closure $terms = NULL;


	static public function having(
		Node $subject,
		string $kind,
		Link $type,
		Like $rule = Like::all,
		array $concerns = [],
		Mode $mode = Mode::lazy
	): static {
		return new static(...func_get_args());
	}


	/**
	 *
	 */
	public function __clone()
	{
		$this->active = [];
		$this->loaded = [];

		unset($this->loadTime);
		unset($this->loader);
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
	 *
	 */
	public function count(Like|string $like_or_concern = '', string ...$concerns): int
	{
		$use_graph = !is_null($this->subject->identity()) && (
			$this->mode == Mode::manual || ($this->mode == Mode::lazy && !isset($this->loadTime))
		);

		if ($use_graph) {
			return $this->getGraphCount($like_or_concern, ...$concerns);

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

		if ($this->mode == Mode::manual) {
			$concerns = array_filter($nodes, fn($node) => is_string($node));
			$nodes    = array_filter($nodes, fn($node) => !is_string($node));
			$query    = $this->getGraphQuery(Like::all, ...$concerns);
			$alias    = Scope::concern->value;
			$where    = $query->where;

			if (count($nodes)) {
				$query
					->run('AND %s', $where->scope($alias, function($any, $id) use ($nodes) {
						return $any(...array_map($id, $nodes));
					}))
					->run('RETURN %s', $where->scope($alias, function($count, $eq) use ($nodes) {
						return $eq($count, count($nodes));
					}))
				;

			} else {
				$query->run('RETURN %s', $where->scope($alias, function($gte, $count) {
					return $gte($count, 1);
				}));

			}

			return $query->pull(Signature::RECORD)[0];

		} else {
			foreach ($nodes as $node) {
				if ($this->getIndex($node) === FALSE) {
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

		if ($this->mode == Mode::manual) {
			$concerns = array_filter($nodes, fn($node) => is_string($node));
			$nodes    = array_filter($nodes, fn($node) => !is_string($node));
			$query    = $this->getGraphQuery(Like::any, ...$concerns);
			$alias    = Scope::concern->value;
			$where    = $query->where;

			if (count($nodes)) {
				$query
					->run('AND %s', $where->scope($alias, function($any, $id) use ($nodes) {
						return $any(...array_map($id, $nodes));
					}))
					->run('RETURN %s', $where->scope($alias, function($count, $gte) use ($nodes) {
						return $gte($count, 1);
					}))
				;

			} else {
				$query->run('RETURN %s', $where->scope($alias, function($gte, $count) {
					return $gte($count, 1);
				}));

			}

			return $query->pull(Signature::RECORD)[0];

		} else {
			foreach ($nodes as $node) {
				if ($this->getIndex($node) !== FALSE) {
					return TRUE;
				}
			}

		}

		return FALSE;
	}


	public function find(Closure $terms, ?array $order = NULL, ?int $limit = NULL, ?int $offset = NULL): static
	{
		$this->terms = $terms;

		$this->load();

		$this->terms = NULL;

		return $this;
	}


	/**
	 *  Reload the relationship
	 */
	public function flush(): static
	{
		if (isset($this->loadTime)) {
			unset($this->loadTime);

			$this->load();
		}

		return $this;
	}


	/**
	 * Load the edges/nodes for the relationship.
	 *
	 * Called from Element::as() -- for Node elements only, Edge elements do not have relationships.
	 */
	public function load(Like|string $like_or_concern = '', string ...$concerns): static
	{
		if (!isset($this->graph)) {
			return $this;
		}

		if (is_null($this->subject->identity())) {
			return $this;
		}

		if (!isset($this->loadTime)) {
			$this->loader = function() use ($like_or_concern, $concerns) {
				unset($this->loader);

				$query = $this->getGraphQuery($like_or_concern, ...$concerns);

				if ($this->terms) {
					$query->run('AND (%s)', $query->where->scope(Scope::concern->value, $this->terms));
				}

				$query->run(
					'RETURN %s, %s, %s',
					Scope::subject->value,
					Scope::concern->value,
					Scope::relation->value
				);

				if (count($this->orders)) {
					$orders = [];

					foreach ($this->orders as $order) {
						$orders[] = sprintf(
							'%s.%s %s',
							$order->alias,
							$order->field,
							$order->direction
						);
					}

					$query->run('ORDER BY %s', implode(',', $orders));
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

					if ($this->mode == Mode::manual) {
						$this->offset++;
					}
				}

				return new DateTime();
			};

			switch ($this->mode) {
				case Mode::eager:
					$this->loadTime = call_user_func($this->loader);
					break;

				case Mode::manual:
					call_user_func($this->loader);
					break;
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function match(string ...$concerns): static
	{
		$clone = $this->getClone(__FUNCTION__, $concerns);

		$clone->rule = Like::all;

		return $clone;
	}


	/**
	 *
	 */
	public function matchAny(string ...$concerns): static
	{
		$clone = $this->getClone(__FUNCTION__, $concerns);

		$clone->rule = Like::any;

		return $clone;
	}


	/**
	 * Merge the relationship into the graph object.
	 *
	 * This method should be implemented by a concrete relationship implementation which can
	 * choose to implement more advanced merging features such as Merge hooks.
	 *
	 * Called from Queue::merge().
	 */
	public function merge(): static
	{
		if (isset($this->apex)) {
			$removed = array_diff_key($this->loaded, $this->active);

			$this->purge();

			$this->apex->active = array_merge($this->apex->active, $this->active);
			$this->apex->loaded = array_merge($this->apex->loaded, $this->loaded);

			foreach ($removed as $hash => $edge) {
				if (isset($this->apex->active[$hash])) {
					unset($this->apex->active[$hash]);
				}
			}
		}

		if (!isset($this->apex) && isset($this->graph)) {
			for($class = static::class; $class != Relationship::class; $class = get_parent_class($class)) {
				foreach (class_uses($class) as $trait) {
					if (!in_array(Relationship\MergeHook::class, class_uses($trait))) {
						continue;
					}

					$parts  = explode('\\', $trait);
					$method = 'merge' . end($parts);

					$this->$method();
				}
			}
		}

		return $this;
	}


	/**
	 * Clean the relationship of detached and unused edges
	 */
	public function purge(): static
	{
		foreach ($this->loaded as $i => $edge) {
			if ($edge->__element__->status == Status::detached) {
				unset($this->loaded[$i]);
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function skip(int $offset): static
	{
		$this->offset = $offset;

		return $this;
	}


	/**
	 *
	 */
	public function sort(Order ...$orders): static
	{
		$this->orders = $orders;

		return $this;
	}


	/**
	 *
	 */
	public function take(int $limit): static
	{
		$this->limit = $limit;

		return $this;
	}


	/**
	 *
	 */
	public function where(?Closure $conditions): static
	{
		$this->terms = $conditions;

		return $this;
	}


	/**
	 * Construct a new Relationship
	 *
	 * @param Node $subject The subject for this relationship.
	 * @param class-string<E> $kind The edge type that defines the relationship
	 * @param array<class-string<Node>> $concerns
	 */
	protected function __construct(
		public protected(set) Node $subject,
		public protected(set) string $kind,
		public protected(set) Link $type,
		public protected(set) Like $rule = Like::all,
		public protected(set) array $concerns = [],
		public protected(set) Mode $mode = Mode::lazy,
	) {
		if (!is_subclass_of($kind, Edge::class, TRUE)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot create relationship of non-edge kind "%s"',
				$kind
			));
		}
	}


	/**
	 *
	 */
	protected function getClone(string $function, array $concerns): static
	{
		$this->validateApex($function);
		$this->validateConcerns($concerns);

		$clone = clone $this;

		$clone->apex     = $this;
		$clone->concerns = $concerns;

		return $clone;
	}


	/**
	 *
	 */
	protected function getGraphQuery(Like|string $like_or_concern = '', string ...$concerns): Query
	{
		if (!$like_or_concern instanceof Like) {
			$like = $this->rule;

			if ($like_or_concern) {
				array_unshift($concerns, $like_or_concern);
			}

		} else {
			$like = $like_or_concern;
		}

		return $this->graph
			->run(
				match ($this->type) {
					Link::to   => 'MATCH (s:%s)-[r:%s]->(c:%s)',
					Link::from => 'MATCH (s:%s)<-[r:%s]-(c:%s)'
				},
				$this->subject::class,
				$this->kind,
				implode(
					$like->value,
					$concerns ?: $this->concerns
				)
			)
			->run('WHERE id(s) = $subject')
			->set('subject', $this->subject->identity())
		;
	}


	protected function getGraphCount(Like|string $like_or_concern = '', string ...$concerns): int
	{
		return (int) $this
			->getGraphQuery($like_or_concern, ...$concerns)
			->run('RETURN COUNT(r) AS total')
			->pull(Signature::RECORD)[0]
		;
	}


	/**
	 * Determine, by index, whether or not a node is included in the current relationship.
	 *
	 * Note, this will only return the first index, it's possible that a node exists more than once.
	 */
	protected function getIndex(Element\Node|Node|string $node, Index $index = Index::ACTIVE): string|false
	{
		$index = $index->value;

		foreach ($this->$index as $i => $edge) {
			if ($edge->for($node, $this->type)) {
				return $i;
			}
		}

		return FALSE;
	}


	/**
	 *
	 */
	protected function resolveEdge(Node $concern, array $data = []): string
	{
		$hash = $this->getIndex($concern, Index::LOADED);

		if ($hash) {
			$this->active[$hash] = $this->loaded[$hash];

		} else {
			$edge = $this->kind::make($data, Entity::MAKE_ASSIGN);
			$hash = spl_object_hash($edge);

			if ($this->type == Link::to) {
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
	 *
	 */
	protected function validateConcerns(array $concerns)
	{
		$diff = array_diff($concerns, $this->concerns);

		// TODO: Implement
	}


	/**
	 *
	 */
	protected function validateApex(string $function): void
	{
		if (isset($this->apex)) {
			throw new RuntimeException(sprintf(
				'Cannot use "%s()" on non-apex relationship',
				$function
			));
		}
	}


	/**
	 * Validate a target against basic rules.
	 *
	 * - No Released or Detatched Targets
	 * - No Targets of Unsupported Types
	 */
	protected function validateNode(Node $target): void
	{
		if ($target->status(Status::released, Status::detached)) {
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
