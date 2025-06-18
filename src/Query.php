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
	 *
	 */
	public protected(set) array $concerns;


	/**
	 *
	 */
	public protected(set) string $pattern;


	/**
	 *
	 */
	public protected(set) Where $where;

	/**
	 *
	 */
	public private(set) array $meta = [];

	/**
	 *
	 */
	protected private(set) array $records;

	/**
	 *
	 */
	protected private(set) bool $running = FALSE;

	/**
	 *
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
	 *
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
	 * @param ?class-string<E> $class
	 * @return NodeResults<T|E>|EdgeResults<T|E>|ElementResults
	 */
	public function get(?string $class = NULL): NodeResults|EdgeResults|Element\Results
	{
		$results = $this->results();

		if (is_null($class)) {
			return $results->as($this->concerns);
		} else {
			return $results->of($class)->as($class);
		}
	}


	/**
	 * @template T of Entity
	 * @param class-string<T> ...$concerns
	 * @return static<T>
	 */
	public function match(string ...$concerns): static
	{
		return $this->init(Matching::all, ...$concerns);
	}


	/**
	 * @template T of Entity
	 * @param class-string<T> ...$concerns
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

		if (isset($this->records[$index])) {
			return $this->records[$index];
		}

		return NULL;
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
	 * @return Results<T>|Element\Results<T>
	 */
	public function results(): Results|Element\Results
	{
		if (!isset($this->results)) {
			$results_type  = isset($this->concerns) ? Element\Results::class : Results::class;
			$this->results = new $results_type(array_map(
				$this->graph->resolve(...),
				$this->records()
			));
		}

		return $this->results;
	}


	/**
	 *
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
		$this->records = $this->unwind(
			...array_filter(
				$responses,
				fn($response) => $response->signature == Signature::RECORD
			)
		);

		return $this;
	}


	/**
	 * @return static<T>
	 */
	public function set(string $name, mixed $value): static
	{
		$this->parameters[$name] = $this->prepare($value);

		return $this;
	}


	/**
	 * @return static<T>
	 */
	public function setAll(array $parameters): static
	{
		$this->parameters = array_replace($this->parameters, $this->prepare($parameters));

		return $this;
	}


	/**
	 *
	 */
	protected function compose(): string
	{
		if (isset($this->concerns)) {
			if (isset($this->pattern)) {
				$this->add('MATCH %s', $this->pattern);
			} else {
				$this->add(
					'MATCH (%1$s1),()-[%1$s2]-() UNWIND [%1$s1,%1$s2] AS %1$s WITH %1$s',
					Scope::concern->value
				);
			}

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
	 *
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
	 *
	 */
	protected function init(Matching $method, string ...$concerns): static
	{
		if (count($this->statements)) {
			throw new RuntimeException(sprintf(
				'Cannot re-initialize existing query, already initialized with match()'
			));
		}

		if (count($concerns)) {
			$has_nodes = FALSE;
			$has_edges = FALSE;

			foreach ($concerns as $i => $concern) {
				if (class_exists($concern)) {
					$has_nodes = $has_nodes | is_a($concern, Node::class, TRUE);
					$has_edges = $has_edges | is_a($concern, Edge::class, TRUE);

					if (!is_a($concern, Entity::class, TRUE)) {
						throw new InvalidArgumentException(sprintf(
							'Invalid class concern "%s" specifed, must extend Entity',
							$concern
						));
					}

					if (get_parent_class($concern) == Entity::class) {
						unset($concerns[$i]);
					}
				}
			}

			if ($has_nodes && $has_edges) {
				throw new InvalidArgumentException(sprintf(
					'Cannot match mixed classes of Node and Edge from: %s',
					implode(', ', $concerns)
				));
			}

			if ($has_edges) {
				$this->pattern = sprintf(
					'(n1)-[%s]-(n2)',
					count($concerns)
						? Scope::concern->value . ':' . implode($method->value, $concerns)
						: Scope::concern->value
				);

			} else {
				$this->pattern = sprintf(
					'(%s)',
					count($concerns)
						? Scope::concern->value . ':' . implode($method->value, $concerns)
						: Scope::concern->value
				);

			}
		}

		$this->concerns = $concerns;

		return $this;
	}


	/**
	 *
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
	 *
	 */
	protected function unwind(mixed ...$items): array
	{
		$records = [];

		foreach ($items as $item) {
			if ($item instanceof Response) {
				array_push($records, ...$this->unwind(...$item->content));
			} else {
				array_push($records, $item);
			}
		}

		return $records;
	}
}
