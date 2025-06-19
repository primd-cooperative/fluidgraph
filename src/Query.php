<?php

namespace FluidGraph;

use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\v5\structures\DateTimeZoneId;

use InvalidArgumentException;
use RuntimeException;
use DateTime;
use Closure;

/**
 * @template T of mixed
 */
class Query
{
	use HasGraph;
	use DoesMatch;

	const REGEX_EXPANSION = '#@([a-zA-Z][a-zA-Z0-9]*)(?:\(([a-zA-Z][a-zA-Z0-9]*)\))?#';

	/**
	 * @var array<class-string<T>|string>
	 */
	public protected(set) ?array $concerns = NULL;


	/**
	 *
	 */
	public protected(set) string $pattern;


	/**
	 *
	 */
	public protected(set) Where $where;

	/**
	 * @var array<string, mixed>
	 */
	public private(set) array $meta = [];

	/**
	 * @var array<mixed>
	 */
	protected private(set) array $records;

	/**
	 *
	 */
	protected private(set) bool $running = FALSE;

	/**
	 * @var Results<T>|Element\Results<T>
	 */
	protected private(set) Results|Element\Results $results;


	/**
	 *
	 */
	public function __clone()
	{
		$this->__construct();
	}


	/**
	 * @param array<string> $statements
	 * @param array<string, mixed> $parameters
	 */
	public function __construct(
		protected array $statements = [],
		protected array $parameters = [],
	) {
		$this->where = new Where()->uses($this);
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
						'graph',
						'where'
					]
				),
			ARRAY_FILTER_USE_KEY
		);
	}


	/**
	 *
	 */
	public function add(string $statement, mixed ...$args): static
	{
		if (isset($this->concerns) && !$this->running) {
			throw new RuntimeException(sprintf(
				'Cannot re-initialize existing query, already initialized with run()'
			));
		}

		$this->statements[] = sprintf($statement, ...array_map(
			fn($arg) => $arg instanceof Closure ? $arg() : $arg,
			$args
		));

		return $this;
	}


	/**
	 * @template E of Entity
	 * @param null|array<class-string<E>|string>|class-string<E> $concerns
	 * @return NodeResults<T|E>|EdgeResults<T|E>|Entity\Results<T|E>
	 */
	public function get(null|array|string $concerns = NULL): NodeResults|EdgeResults|Entity\Results
	{
		if (!isset($this->concerns)) {
			throw new RuntimeException(sprintf(
				'Cannot call get() on a query not initialized with match()'
			));
		}

		return !$concerns
			? $this->results()->get($this->concerns)
			: $this->results()->get($concerns)
		;
	}


	/**
	 * @template T of Entity
	 * @param class-string<T>|string ...$concerns
	 * @return static<T>
	 */
	public function match(string ...$concerns): static
	{
		return $this->init(Matching::all, ...$concerns);
	}


	/**
	 * @template T of Entity
	 * @param class-string<T>|string ...$concerns
	 * @return static<T>
	 */
	public function matchAny(string ...$concerns): static
	{
		return $this->init(Matching::any, ...$concerns);
	}


	/**
	 *
	 */
	public function record(int $index): mixed
	{
		if (!isset($this->records)) {
			$this->run();
		}

		return $this->records[$index] ?? NULL;
	}


	/**
	 *
	 */
	public function records(int ...$indexes): array
	{
		if (!isset($this->records)) {
			$this->run();
		}

		if (count($indexes)) {
			$records = [];

			foreach ($indexes as $index) {
				if (isset($this->records[$index])) {
					$records[] = $this->records[$index];
				}
			}

			return $records;
		}

		return $this->records;
	}


	/**
	 * Get the records and resolve them via the graph.
	 *
	 * Resolving will convert any actual Node/Edge responses into elements and register them with
	 * the graph.  If you need to perform manual resolution or work with raw return data, use
	 * the `records()` method instead.
	 *
	 * @template E of Element
	 * @return ($this is object{concerns: null} ? Results<T> : Element\Results<E>)
	 */
	public function results(): Results|Element\Results
	{
		if (!isset($this->results)) {
			if (!isset($this->concerns)) {
				$this->results = new Results(array_map(
					$this->graph->resolve(...),
					$this->records()
				));

			} else {
				$this->results = new Element\Results(array_map(
					$this->graph->resolve(...),
					$this->records()
				));

			}
		}

		return $this->results;
	}


	/**
	 * Run the query.
	 *
	 * This will execute the query, pull, and collect the unpacked responses as records which are
	 * accessible in raw form via `records()`.
	 */
	public function run(): static
	{
		$this->running = TRUE;
		$cypher_code   = $this->compose();
		$responses     = iterator_to_array(
			$this->graph->protocol->run($cypher_code, $this->parameters)->pull()->getResponses()
		);

		foreach ($responses as $response) {
			switch ($response->signature) {
				case Signature::NONE:
				case Signature::IGNORED:
				case Signature::FAILURE:
					throw new RuntimeException(sprintf(
						'%s in "%s"',
						$response->content['message'] ?? 'Unknown failure',
						$cypher_code
					));

				case Signature::SUCCESS:
					$this->meta = $response->content;
			};
		}

		$this->running = FALSE;
		$this->records = $this->unpack(
			array_filter(
				$responses,
				fn($response) => $response->signature == Signature::RECORD
			)
		);

		return $this;
	}


	/**
	 * Set a single parameter for the query.
	 *
	 * This allows you to add or replace existing properties ad-hoc.
	 */
	public function set(string $name, mixed $value): static
	{
		$this->parameters[$name] = $this->prepare($value);

		return $this;
	}


	/**
	 * Set all the query parameters at once.
	 *
	 * Note, this does not merge parameters, it replaces the entire parameter array with only
	 * the keys (as parameter names) and values (as parameter value) in the array.
	 */
	public function setAll(array $parameters): static
	{
		$this->parameters = array_replace($this->parameters, $this->prepare($parameters));

		return $this;
	}


	/**
	 * Compose a complete query
	 *
	 * If match() or matchAny() was used and there are concerns or other query constraints set
	 * like where(), take(), or skip(), etc, it will crate the query from these values.  Otherwise,
	 * this function acts simply as a proxy to compile all statements passed to add().
	 *
	 * It is not possible to mix match-type queries and added statements.  Attempts to call one
	 * once the other has been called will result in an exception.
	 */
	protected function compose(): string
	{
		if (isset($this->concerns)) {
			$this->add('MATCH %s', $this->pattern);

			if (isset($this->terms) && $conditions = call_user_func($this->terms)) {
				$this->add('WHERE %s', $conditions);
			}

			$this->add('RETURN DISTINCT %s', Scope::concern->value);

			if ($this->orders) {
				$orders = [];

				foreach ($this->orders as $order) {
					$orders[] = sprintf(
						'%s.%s %s',
						$order->alias,
						$order->field,
						$order->direction
					);
				}

				$this->add('ORDER BY %s', implode(',', $orders));
			}

			if ($this->limit >= 0) {
				$this->add('LIMIT %s', $this->limit);
			}

			if ($this->offset > 0) {
				$this->add('SKIP %s', $this->offset);
			}
		}

		return $this->compile();
	}


	/**
	 * Compile a query by converting classes to labels and filling all expansions
	 *
	 * Expansions allow for a syntax such as `SET @d(i)` where `d` is the name of the actual
	 * parameter and `i` is the cypher query variable or `(i:Label {@d})`.  This allows you to pass
	 * an associative array as a parameter that will expand into something like either
	 * `i.property = $d.property` or `{property: $d.property}` (respectively).
	 */
	protected function compile(): string
	{
		return implode(" ", array_map(
			function($statement) {
				$statement = str_replace('\\', '_', $statement);

				if (preg_match_all(static::REGEX_EXPANSION, $statement, $matches, PREG_SET_ORDER)) {
					foreach ($matches as $match) {
						$expansion = $match[0];
						$parameter = $match[1];
						$prefix    = $match[2] ?? NULL;

						if (!isset($this->parameters[$parameter])) {
							throw new InvalidArgumentException(sprintf(
								'Parameter %s cannot be expanded with @, does not exist',
								$parameter
							));
						}

						if (is_scalar($this->parameters[$parameter])) {
							throw new InvalidArgumentException(sprintf(
								'Parameter %s cannot be expanded with @, is not array or object',
								$parameter
							));
						}

						$statement = str_replace(
							$expansion,
							sprintf(
								'%s',
								implode(',', array_map(
									function($property) use ($prefix, $parameter) {
										if (!$prefix) {
											return sprintf(
												'%s:$%s.%s',
												$property,
												$parameter,
												$property
											);
										} else {
											return sprintf(
												'%s=$%s.%s',
												$prefix . '.' . $property,
												$parameter,
												$property
											);
										}
									},
									array_keys($this->parameters[$parameter])
								))
							),
							$statement
						);
					}
				}

				return $statement;
			},
			$this->statements
		));
	}


	/**
	 * Initialize a match query (for element selection).
	 *
	 * This is used by match() and matchAny() in order to select elements, based on concerns we'll
	 * determine what the match pattern looks like for our cypher query.
	 *
	 * @param class-string<T>|string ...$concerns
	 */
	protected function init(Matching $method, string ...$concerns): static
	{
		if (count($this->statements)) {
			throw new RuntimeException(sprintf(
				'Cannot re-initialize existing query, already initialized with match()'
			));
		}

		$match_nodes   = !count($concerns);
		$match_edges   = !count($concerns);
		$node_concerns = [];
		$edge_concerns = [];

		foreach ($concerns as $i => $concern) {
			if (is_a($concern, Edge::class, TRUE)) {
				$match_edges = TRUE;

				if ($concern != Edge::class) {
					$edge_concerns[] = $concern;
				} else {
					unset($concerns[$i]);
				}

			} else {
				$match_nodes = TRUE;

				if ($concern != Node::class) {
					$node_concerns[] = $concern;
				} else {
					unset($concerns[$i]);
				}
			}
		}

		if ($match_edges && $match_nodes) {
			$this->pattern = sprintf(
				'(%1$s1%2$s),()-[%1$s2%3$s]-() UNWIND [%1$s1,%1$s2] AS %1$s WITH %1$s',
				Scope::concern->value,
				count($node_concerns)
					? ':' . implode($method->value, $node_concerns)
					: '',
				count($edge_concerns)
					? ':' . implode('|', $edge_concerns)
					: ''
			);

		} elseif ($match_edges) {
			$this->pattern = sprintf(
				'()-[%s%s]-()',
				Scope::concern->value,
				count($edge_concerns)
					? ':' . implode('|', $edge_concerns)
					: ''
			);

		} else {
			$this->pattern = sprintf(
				'(%s%s)',
				Scope::concern->value,
				count($node_concerns)
					? ':' . implode($method->value, $node_concerns)
					: ''
			);

		}

		$this->concerns = $concerns;

		return $this;
	}


	/**
	 * Prepare a property into a format that Bolt can understand.
	 *
	 * If the property is an array, all of the elements in that array will be prepared as necessary.
	 */
	protected function prepare(mixed $property): mixed
	{
		if (is_array($property)) {
			return array_map([$this, 'prepare'], $property);
		}

		if ($property instanceof DateTime) {
			return new DateTimeZoneId(
				intval($property->format('U')),
				intval($property->format('u') * 1000),
				$property->format('e')
			);
		}

		return $property;
	}


	/**
	 * Unpack record content recursively until it's no longer a Response object.
	 *
	 * @return array<mixed>
	 */
	protected function unpack(mixed $items): array
	{
		$records = [];

		foreach ($items as $item) {
			if ($item instanceof Response) {
				array_push($records, ...$this->unpack($item->content));
			} else {
				array_push($records, $item);
			}
		}

		return $records;
	}
}
