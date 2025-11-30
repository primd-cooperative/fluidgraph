<?php

namespace FluidGraph;

use FluidGraph\Relationship\Mode;
use FluidGraph\Relationship\Index;
use FluidGraph\Relationship\Order;

use function FluidGraph\Where\{any, eq, gte, id, total};

use InvalidArgumentException;
use RuntimeException;
use Countable;
use DateTime;
use Closure;


/**
 * @template T of Edge
 */
abstract class Relationship implements Countable
{
	use HasGraph;
	use DoesMatch;

	/**
	 * @var array<E>
	 */
	protected array $active = [] {
		&get {
			if (isset($this->loader)) {
				call_user_func($this->loader);
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
				call_user_func($this->loader);
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
	abstract public function set(Node $node, array|Edge $data = []): static;


	/**
	 * Createa a new relationship
	 *
	 * @template T of Edge
	 * @param class-string<T> $kind
	 * @param class-string|array<class-string> $concerns
	 * @return static<T>
	 */
	static public function having(Node $subject, string $kind, Reference $type, Matching $rule = Matching::all, array|string $concerns = [], Mode $mode = Mode::lazy): static
	{
		settype($concerns, 'array');

		return new static($subject, $kind, $type, $rule, $concerns, $mode);
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
			fn($key) => !in_array($key, [
				'graph'
			]),
			ARRAY_FILTER_USE_KEY
		);
	}


	/**
	 * Get all edge entities for this relationship, regardless what they correspond to
	 *
	 * @return EdgeResults<T>
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
	public function contains(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): bool
	{
		array_unshift($matches, $match);

		$use_graph = !is_null($this->subject->identity()) && (
			$this->mode == Mode::manual || ($this->mode == Mode::lazy && !isset($this->loadTime))
		);

		if ($use_graph) {
			$concerns = array_filter($matches, fn($match) => is_string($match));
			$nodes    = array_filter($matches, fn($match) => !is_string($match));
			$query    = $this->getGraphQuery(Matching::all, ...$concerns);
			$alias    = Scope::concern->value;
			$where    = $query->where;

			if (count($nodes)) {
				$query
					->add('AND %s', $where->with($alias, fn() => any(...array_map(id(...), $nodes))))
					->add('RETURN %s', $where->with($alias, fn() => eq(total(), count($nodes))))
				;

			} else {
				$query->add('RETURN %s', $where->with($alias, fn() => gte(total(), 1)));

			}

			return $query->record(0);

		} else {
			foreach ($matches as $match) {
				if ($this->getIndex(Index::active, $match) === FALSE) {
					return FALSE;
				}
			}
		}

		return TRUE;
	}


	/**
	 * Determine whether or not the relationship contains any of a set of nodes or node types.
	 */
	public function containsAny(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): bool
	{
		array_unshift($matches, $match);

		$use_graph = !is_null($this->subject->identity()) && (
			$this->mode == Mode::manual || ($this->mode == Mode::lazy && !isset($this->loadTime))
		);

		if ($use_graph) {
			$concerns = array_filter($matches, fn($match) => is_string($match));
			$nodes    = array_filter($matches, fn($match) => !is_string($match));
			$query    = $this->getGraphQuery(Matching::any, ...$concerns);
			$alias    = Scope::concern->value;
			$where    = $query->where;

			if (count($nodes)) {
				$query
					->add('AND %s', $where->with($alias, fn() => any(...array_map(id(...), $nodes))))
					->add('RETURN %s', $where->with($alias, fn() => gte(total(), 1)))
				;

			} else {
				$query->add('RETURN %s', $where->with($alias, fn() => gte(total(), 1)));

			}

			return $query->record(0);

		} else {
			foreach ($matches as $match) {
				if ($this->getIndex(Index::active, $match) !== FALSE) {
					return TRUE;
				}
			}

		}

		return FALSE;
	}


	/**
	 *
	 */
	public function find(string|array $concerns, ?int $limit = NULL, int $offset = 0, callable|array $terms = [], ?array $orders = NULL): NodeResults|Node|null
	{
		if (is_null($orders)) {
			$orders = $this->orders;
		}

		$clone = $this->match($concerns)->take($limit)->skip($offset)->where($terms)->sort(...$orders);

		$clone->load();

		return $clone->get();
	}

	/**
	 *
	 */
	public function findAny(string|array $concerns, ?int $limit = NULL, int $offset = 0, callable|array $terms = [], ?array $orders = NULL): NodeResults|Node|null
	{
		settype($concerns, 'array');

		if (is_null($orders)) {
			$orders = $this->orders;
		}

		$clone = $this->matchAny($concerns)->take($limit)->skip($offset)->where($terms)->sort(...$orders);

		$clone->load();

		return $clone->getAny();
	}


	/**
	 *
	 */
	public function findFor(string|array $concerns, ?int $limit = NULL, int $offset = 0, callable|array $terms = [], ?array $orders = []): EdgeResults|Edge|null
	{
		settype($concerns, 'array');

		$clone = $this->match($concerns)->take($limit)->skip($offset)->where($terms)->sort(...$orders);

		$clone->load();

		return $clone->all();
	}


	/**
	 *
	 */
	public function findForAny(string|array $concerns, ?int $limit = NULL, int $offset = 0, callable|array $terms = [], ?array $orders = []): EdgeResults|Edge|null
	{
		settype($concerns, 'array');

		$clone = $this->matchAny($concerns)->take($limit)->skip($offset)->where($terms)->sort(...$orders);

		$clone->load();

		return $clone->all();
	}


	/**
	 * @return E
	 */
	public function first(): ?Edge
	{
		return reset($this->active) ?: NULL;
	}


	/**
	 * Reload the relationship
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
	 * Get all edge entities for this relationship whose node corresponds to all node/label matches
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return EdgeResults<T>|T|null

	 */
	public function for(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): EdgeResults|Edge|null
	{
		return $this->all()->for($match, ...$matches);
	}


	/**
	 * Get all edge entities for this relationship whose node corresponds to any node/label matches
	 *
	 * @param Element\Node|Node|class-string $match
	 * @param Element\Node|Node|class-string $matches
	 * @return EdgeResults<T>|T|null
	 */
	public function forAny(Element\Node|Node|string $match, Element\Node|Node|string ...$matches): EdgeResults|Edge|null
	{
		return $this->all()->forAny($match, ...$matches);
	}


	/**
	 * Get the related node entities of() the specified classes.
	 *
	 * @template N of Node
	 * @param class-string<N>|string ...$concerns
	 * @return NodeResults<N>|N|null
	 */
	public function get(string ...$concerns): NodeResults|Node|null
	{
		if (empty($concerns)) {
			$concerns = $this->concerns;

			if (isset($this->apex) && (empty($concerns) || $this->apex->rule == Matching::all)) { # We only add apex concerns if the rule matches our pattern
				$concerns = array_merge($concerns, $this->apex->concerns);
			}
		}

		return $this->all()->get(...$concerns);
	}


	/**
	 * Get the related node entitities ofAny() of the specified concerns.
	 *
	 * @template N of Node
	 * @param class-string<N>|string ...$concerns
	 * @return NodeResults<N>|N|null
	 */
	public function getAny(string ...$concerns): NodeResults|Node|null
	{
		if (empty($concerns)) {
			$concerns = $this->concerns;

			if (isset($this->apex) && (empty($concerns) || $this->apex->rule == Matching::any)) {
				$concerns = array_merge($concerns, $this->apex->concerns);
			}
		}

		return $this->all()->getAny(...$concerns);
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
	public function load(): static
	{
		if (!isset($this->graph)) {
			return $this;
		}

		if (is_null($this->subject->identity())) {
			return $this;
		}

		if (!isset($this->loadTime)) {
			$this->loader = function() {
				unset($this->loader);

				$query = $this->getGraphQuery();

				if ($this->terms) {
					$query->add(
						'AND (%s)',
						new Where($query)->with(
							Scope::concern->value,
							$this->terms
						)
					);
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

				if ($this->offset > 0) {
					$query->add('SKIP %s', $this->offset);
				}

				if ($this->limit >= 0) {
					$query->add('LIMIT %s', $this->limit);
				}

				$this->loaded = [];

				foreach ($query->results()->map($this->graph->resolve(...)) as $result) {
					if (!$result instanceof Element\Edge) {
						continue;
					}

					$edge = $result->as($this->kind);
					$hash = spl_object_hash($edge->__element__);

					$this->loaded[$hash] = $edge;
					$this->active[$hash] = $edge;
				}

				$this->loadTime = new DateTime();
			};

			switch ($this->mode) {
				case Mode::eager:
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
	public function match(string|array $concerns): static
	{
		settype($concerns, 'array');

		$clone = $this->getClone(__FUNCTION__, $concerns);

		$clone->rule = Matching::all;

		return $clone;
	}


	/**
	 *
	 */
	public function matchAny(string|array $concerns): static
	{
		settype($concerns, 'array');

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
	 * Called from Queue::merge() or relations NodeResults/EdgeResults.
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

			return $this->apex;
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
		foreach ([Index::active, Index::loaded] as $index) {
			$index = $index->value;

			foreach ($this->$index as $i => $edge) {
				if ($edge->__element__->status == Status::detached) {
					unset($this->loaded[$i]);
				}
			}
		}

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
	public function unset(null|Node|Edge $entity = NULL, Node|Edge ...$entities): static
	{
		if ($entity) {
			array_unshift($entities, $entity);
		}

		if (count($entities)) {
			foreach ($entities as $entity) {
				switch (TRUE) {
					case $entity instanceof Edge:
						unset($this->active[spl_object_hash($entity)]);
						break;

					case $entity instanceof Node:
						foreach ($this->getIndexes(Index::active, $entity) as $hash) {
							unset($this->active[$hash]);
						}
						break;
				}
			}

		} else {
			$this->active = [];

		}

		return $this;
	}


	/**
	 * Construct a new Relationship
	 *
	 * @param Node $subject The subject for this relationship.
	 * @param class-string<T> $kind The edge type that defines the relationship
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
		$clone->mode     = Mode::lazy;
		$clone->concerns = $concerns;

		return $clone;
	}


	/**
	 *
	 */
	protected function getGraphQuery(Matching|string $rule_or_concern = '', string ...$concerns): Query\RawQuery
	{
		$rule = $this->rule;

		if (!$rule_or_concern instanceof Matching) {
			if ($rule_or_concern) {
				array_unshift($concerns, $rule_or_concern);
			}

		} else {
			if (count($concerns)) {
				$rule = $rule_or_concern;
			}
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
				$concern_query ? '(%s) AND (%s)' : '%s',
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

		return new Query\RawQuery()
			->on($this->graph)
			->add(
				match ($this->type) {
					Reference::to     => 'MATCH (s:%s)-[r:%s]->(c)',
					Reference::from   => 'MATCH (s:%s)<-[r:%s]-(c)',
					Reference::either => 'MATCH (s:%s)<-[r:%s]->(c)'
				},
				$this->subject::class,
				$this->kind,
			)
			->add('WHERE (%s) AND id(s) = $subject', $concern_query)
			->set('subject', $this->subject->identity())
		;
	}


	/**
	 *
	 */
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
	protected function getIndex(Index $index, Element\Node|Node|string $match): string|false
	{
		$index = $index->value;

		return array_find_key($this->$index, fn($edge) => $edge->for($this->type, $match)) ?? FALSE;
	}


	/**
	 *
	 */
	protected function getIndexes(Index $index, Element\Node|Node $match, Element\Node|Node ...$matches): array
	{
		$index = $index->value;

		return array_keys(array_filter($this->$index, fn($edge) => $edge->for($this->type, $match, ...$matches)));
	}


	/**
	 *
	 */
	protected function resolveEdge(Node $node, array|Edge $data = []): string
	{
		if ($data instanceof Edge) {
			$edge = $data;
		} else {
			$edge = $this->kind::make($data, Entity::MAKE_ASSIGN);
		}

		if ($this->type == Reference::from) {
			$source = $node;
			$target = $this->subject;
		} else {
			$source = $this->subject;
			$target = $node;
		}

		$edge->with(
			function(Node $source, Node $target): void {
				/**
				 * @var Edge $this
				 */
				if ($this->identity()) {
					if ($this->__element__->source->identity() != $source->identity()) {
						throw new InvalidArgumentException(sprintf(
							'Cannot move existing edge (ID: %s) to source (ID: %s)',
							$this->identity(),
							$source->identity()
						));
					}

					if ($this->__element__->target->identity() != $target->identity()) {
						throw new InvalidArgumentException(sprintf(
							'Cannot move existing edge (ID: %s) to target (ID: %s)',
							$this->identity(),
							$target->identity()
						));
					}
				}

				$this->__element__->source = $source;
				$this->__element__->target = $target;
			},
			$source,
			$target
		);

		$this->active[$hash = spl_object_hash($edge)] = $edge;

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
			$labels    = Element::labels($target->__element__);
			$intersect = array_intersect($labels, $this->concerns);
			$valid     = match ($this->rule) {
				Matching::all => count($intersect) == count($this->concerns),
				Matching::any => count($intersect) >= 1
			};

			if (isset($this->apex)) {
				$valid &= $this->apex->validateNode($target, TRUE);
			}

			if (!$valid) {
				throw new InvalidArgumentException(sprintf(
					'Relationship cannot use node of type "%s", not in concerns: %s',
					get_class($target),
					join(', ', $this->concerns)
				));
			}
		}
	}
}
