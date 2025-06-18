<?php

namespace FluidGraph;

use FluidGraph\Relationship\Mode;
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
	protected private(set) self $apex;


	/**
	 *
	 */
	protected private(set) int $limit = 0;


	/**
	 *
	 */
	protected private(set) int $offset = 0;


	/**
	 * @param array<Order>
	 */
	protected private(set) array $orders = [];


	/**
	 *
	 */
	protected private(set) ?Closure $terms = NULL;


	/**
	 *
	 */
	abstract public function for(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): null|Edge|EdgeResults;


	/**
	 *
	 */
	abstract public function forAny(Element\Node|Node|string $node, Element\Node|Node|string ...$nodes): null|Edge|EdgeResults;


	/**
	 *
	 */
	abstract public function set(Node $node, array|Edge $data = []): static;


	/**
	 *
	 */
	abstract public function unset(null|Node|Edge $entity): static;


	/**
	 *
	 */
	static public function having(Node $subject, string $kind, Reference $type, Matching $rule = Matching::all,	array $concerns = [], Mode $mode = Mode::lazy): static {
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
	 * Get all edge entities for this relationship, regardless what they correspond to
	 *
	 * @return EdgeResults<E>
	 */
	public function all(): EdgeResults
	{
		return new EdgeResults(array_values($this->active))->using($this);
	}


	/**
	 *
	 */
	public function count(Matching|string $rule_or_concern = '', string ...$concerns): int
	{
		$use_graph = !is_null($this->subject->identity()) && (
			$this->mode == Mode::manual || ($this->mode == Mode::lazy && !isset($this->loadTime))
		);

		if ($use_graph) {
			return $this->getGraphCount($rule_or_concern, ...$concerns);

		} else {
			if ($concerns) {
				$count = 0;

				foreach ($this->active as $edge) {
					foreach ($concerns as $concern) {
						if ($edge->for($this->type, $concern)) {
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
			$query    = $this->getGraphQuery(Matching::all, ...$concerns);
			$alias    = Scope::concern->value;
			$where    = $query->where;

			if (count($nodes)) {
				$query
					->add('AND %s', $where->scope($alias, function($any, $id) use ($nodes) {
						return $any(...array_map($id, $nodes));
					}))
					->add('RETURN %s', $where->scope($alias, function($count, $eq) use ($nodes) {
						return $eq($count, count($nodes));
					}))
				;

			} else {
				$query->add('RETURN %s', $where->scope($alias, function($gte, $count) {
					return $gte($count, 1);
				}));

			}

			return $query->record(0);

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
			$query    = $this->getGraphQuery(Matching::any, ...$concerns);
			$alias    = Scope::concern->value;
			$where    = $query->where;

			if (count($nodes)) {
				$query
					->add('AND %s', $where->scope($alias, function($any, $id) use ($nodes) {
						return $any(...array_map($id, $nodes));
					}))
					->add('RETURN %s', $where->scope($alias, function($count, $gte) use ($nodes) {
						return $gte($count, 1);
					}))
				;

			} else {
				$query->add('RETURN %s', $where->scope($alias, function($gte, $count) {
					return $gte($count, 1);
				}));

			}

			return $query->record(0);

		} else {
			foreach ($nodes as $node) {
				if ($this->getIndex($node) !== FALSE) {
					return TRUE;
				}
			}

		}

		return FALSE;
	}


	/**
	 *
	 */
	public function find(string $class, ?int $limit = NULL, int $offset = 0, callable|array $terms = [], ?array $orders = NULL): null|NodeResults|Node
	{
		$clone = $this->match($class)->take($limit)->skip($offset)->where($terms)->sort(...$orders);

		$clone->load();

		return $clone->get($class);
	}


	/**
	 * @return E
	 */
	public function first(): ?Edge
	{
		return reset($this->active) ?: NULL;
	}


	/**
	 *  Reload the relationship
	 */
	public function flush(): static
	{
		if (isset($this->loadTime)) {
			unset($this->loadTime);

			$this->active = [];
			$this->loaded = [];

			if ($this->mode != Mode::manual) {
				$this->load();
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function get(?string $class = NULL): null|NodeResults|Node
	{
		if (is_null($class)) {
			$class = $this->concerns;

			if (isset($this->apex)) {
				$class = array_merge($class, $this->apex->concerns);
			}

			return $this->all()->get($class);

		} else {
			return $this->for($class)->get($class);
		}
	}



	/**
	 * @return E
	 */
	public function last(): ?Edge
	{
		return end($this->active) ?: NULL;
	}

	/**
	 * Load the edges/nodes for the relationship.
	 *
	 * Called from Element::as() -- for Node elements only, Edge elements do not have relationships.
	 */
	public function load(Matching|string $rule_or_concern = '', string ...$concerns): static
	{
		if (!isset($this->graph)) {
			return $this;
		}

		if (is_null($this->subject->identity())) {
			return $this;
		}

		if (!isset($this->loadTime)) {
			$this->loader = function() use ($rule_or_concern, $concerns) {
				unset($this->loader);

				$query = $this->getGraphQuery($rule_or_concern, ...$concerns);

				if ($this->terms) {
					$query->add('AND (%s)', $query->where->scope(Scope::concern->value, $this->terms));
				}

				$query->add(
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

					$query->add('ORDER BY %s', implode(',', $orders));
				}

				if ($this->offset) {
					$query->add('SKIP %s', $this->offset);
				}

				if ($this->limit) {
					$query->add('LIMIT %s', $this->limit);
				}

				$this->loaded = [];

				foreach ($query->results() as $result) {
					if (!$result instanceof Element\Edge) {
						continue;
					}

					$edge = $result->as($this->kind);
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

		$clone->rule = Matching::all;

		return $clone;
	}


	/**
	 *
	 */
	public function matchAny(string ...$concerns): static
	{
		$clone = $this->getClone(__FUNCTION__, $concerns);

		$clone->rule = Matching::any;

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
	public function merge(bool $exclude_apex = FALSE): static
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

		if (!$exclude_apex && !isset($this->apex) && isset($this->graph)) {
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
	public function take(?int $limit): static
	{
		if (is_null($limit)) {
			$limit = -1;
		}

		$this->limit = $limit;

		return $this;
	}


	/**
	 *
	 */
	public function where(array|Closure $terms): static
	{
		if (is_array($terms)) {
			$terms = fn($all, $eq) => $all(...$eq($terms));
		}

		$this->terms = $terms;

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
		public protected(set) Reference $type,
		public protected(set) Matching $rule = Matching::all,
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

		$clone = clone $this;

		$clone->apex     = $this;
		$clone->concerns = $concerns;

		return $clone;
	}


	/**
	 *
	 */
	protected function getGraphQuery(Matching|string $rule_or_concern = '', string ...$concerns): Query
	{
		if (!$rule_or_concern instanceof Matching) {
			$rule = $this->rule;

			if ($rule_or_concern) {
				array_unshift($concerns, $rule_or_concern);
			}

		} else {
			$rule = $rule_or_concern;
		}

		$concern_query = implode(
			match ($rule) {
				Matching::any => ' OR ',
				Matching::all => ' AND '
			},
			array_map(
				fn($concern) => Scope::concern->value . ':' . $concern,
				$concerns ?: $this->concerns
			)
		);

		if (isset($this->apex)) {
			$concern_query = sprintf(
				$concern_query ? '%s AND (%s)' : '%s',
				implode(
					match ($this->apex->rule) {
						Matching::any => ' OR ',
						Matching::all => ' AND '
					},
					array_map(
						fn($concern) => Scope::concern->value . ':' . $concern,
						$this->apex->concerns
					)
				),
				$concern_query
			);
		}

		return $this->graph->query
			->add(
				match ($this->type) {
					Reference::to   => 'MATCH (s:%s)-[r:%s]->(c)',
					Reference::from => 'MATCH (s:%s)<-[r:%s]-(c)'
				},
				$this->subject::class,
				$this->kind,
			)
			->add('WHERE (%s) AND id(s) = $subject', $concern_query)
			->set('subject', $this->subject->identity())
		;
	}


	protected function getGraphCount(Matching|string $rule_or_concern = '', string ...$concerns): int
	{
		return (int) $this
			->getGraphQuery($rule_or_concern, ...$concerns)
			->add('RETURN COUNT(r) AS total')
			->record(0)
		;
	}


	/**
	 * Determine, by index, whether or not a node is included in the current relationship.
	 *
	 * Note, this will only return the first index, it's possible that a node exists more than once.
	 */
	protected function getIndex(Element\Node|Node $node, Index $index = Index::active): string|false
	{
		$index = $index->value;

		/**
		 * @var Edge $edge
		 */
		foreach ($this->$index as $i => $edge) {
			if ($edge->for($this->type, $node)) {
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
		$hash = $this->getIndex($concern, Index::loaded);

		if ($hash) {
			$this->active[$hash] = $this->loaded[$hash];

		} else {
			$edge = $this->kind::make($data, Entity::MAKE_ASSIGN);
			$hash = spl_object_hash($edge);

			if ($this->type == Reference::to) {
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
	protected function validateNode(Node $target, $skip_status = FALSE): void
	{
		if (!$skip_status && $target->status(Status::released, Status::detached)) {
			throw new InvalidArgumentException(sprintf(
				'Relationships cannot include released or detached target "%s" on "%s"',
				$target::class,
				static::class
			));
		}

		if (count($this->concerns)) {
			$intersect = array_intersect(Element::labels($target->__element__), $this->concerns);
			$valid     = match ($this->rule) {
				Matching::all => count($intersect) == count($this->concerns),
				Matching::any => count($intersect) >= 1
			};

			if (isset($this->apex)) {
				$valid = $valid & $this->apex->validateNode($target, TRUE);
			}

			if (!$valid) {
				throw new InvalidArgumentException(sprintf(
					'Relationship does not concern Node with insufficient concerns: %s',
					implode(', ', $intersect)
				));
			}
		}
	}
}
