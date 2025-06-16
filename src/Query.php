<?php

namespace FluidGraph;

use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\v5\structures\DateTimeZoneId;

use InvalidArgumentException;
use RuntimeException;
use DateTime;
use Closure;

class Query
{
	use HasGraph;

	const REGEX_EXPANSION = '#@([a-zA-Z][a-zA-Z0-9]*)(?:\(([a-zA-Z][a-zA-Z0-9]*)\))?#';

	/**
	 *
	 */
	public protected(set) array $concerns;

	/**
	 *
	 */
	public protected(set) int $limit = -1;

	/**
	 *
	 */
	public protected(set) int $offset = 0;

	/**
	 *
	 */
	public protected(set) array $orders = [];

	/**
	 *
	 */
	public protected(set) string $pattern;

	/**
	 *
	 */
	public protected(set) Closure $terms;

	/**
	 * An instance of the base where clause builder to clone
	 */
	public protected(set) Where $where;

	/**
	 * @var array<Element>
	 */
	protected array $results;

	/**
	 * @var array
	 */
	protected array $responses;

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
	 * @template N of Node
	 * @param null|class-string<N> $class
	 * @return NodeResults<N>|EdgeResults<N>|Results<N>|N
	 */
	public function get(null|string $class = NULL, int ...$index): NodeResults|Node|EdgeResults|Edge|Results
	{
		if (is_null($class)) {
			$class = $this->concerns;
		}

		return $this->getRaw(...$index)->as($class);
	}


	/**
	 *
	 */
	public function getRaw(int ...$index): Results|Element
	{
		if (!isset($this->results)) {
			if (isset($this->concerns)) {
				if (isset($this->pattern)) {
					$this->run('MATCH %s', $this->pattern);
				} else {
					$this->run(
						'MATCH (%1$s1),()-[%1$s2]-() UNWIND [%1$s1,%1$s2] AS %1$s WITH %1$s',
						Scope::concern->value
					);
				}

				if (isset($this->terms) && $conditions = call_user_func($this->terms)) {
					$this->run('WHERE %s', $conditions);
				}

				$this->run('RETURN DISTINCT %s', Scope::concern->value);

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

					$this->run('ORDER BY %s', implode(',', $orders));
				}

				if ($this->limit >= 0) {
					$this->run('LIMIT %s', $this->limit);
				}

				if ($this->offset > 0) {
					$this->run('SKIP %s', $this->offset);
				}
			}

			$this->results = array_map(
				$this->graph->resolve(...),
				$this->pull(Signature::RECORD)
			);
		}

		if (count($index) == 1) {
			return $this->results[$index[0]];

		} elseif (count($index) == 0) {
			return new Results($this->results);

		} else {
			return new Results(
				array_filter(
					$this->results,
					fn($key) => in_array($key, $index),
					ARRAY_FILTER_USE_KEY
				)
			);
		}
	}


	/**
	 *
	 */
	public function match(string ...$concerns): static
	{
		return $this->init(Matching::all, ...$concerns);
	}


	/**
	 *
	 */
	public function matchAny(string ...$concerns): static
	{
		return $this->init(Matching::any, ...$concerns);
	}


	/**
	 *
	 */
	public function pull(Signature ...$signatures): array
	{
		if (!isset($this->responses)) {
			$cypher          = $this->compile();
			$protocol        = $this->graph->protocol;
			$this->responses = iterator_to_array(
				$protocol->run($cypher, $this->parameters)->pull()->getResponses()
			);

			foreach ($this->responses as $response) {
				if ($response->signature == Signature::FAILURE) {
					throw new InvalidArgumentException(sprintf(
						'%s in "%s"',
						$response->content['message'] ?? 'Unknown Error',
						$cypher
					));
				}

				if ($response->signature == Signature::IGNORED) {
					throw new InvalidArgumentException(sprintf(
						'%s in "%s"',
						$response->content['message'] ?? 'Unknown Error',
						$cypher
					));
				}
			}
		}

		if (count($signatures)) {
			$responses = array_values(
				array_filter(
					$this->responses,
					fn($response) => in_array($response->signature, $signatures)
				)
			);

			if (!count($responses)) {
				return [];
			}

			if ($signatures == [Signature::RECORD]) {
				return $this->unwind(...$responses);
			}

			return $responses;
		}

		return $this->responses;
	}


	/**
	 *
	 */
	public function run(string $statement, mixed ...$args): static
	{
		$this->statements[] = sprintf($statement, ...array_map(
			fn($arg) => $arg instanceof Closure ? $arg() : $arg,
			$args
		));

		return $this;
	}


	/**
	 *
	 */
	public function set(string $name, mixed $value): static
	{
		$this->parameters[$name] = $this->prepare($value);

		return $this;
	}


	/**
	 *
	 */
	public function setAll(array $parameters): static
	{
		$this->parameters = array_replace($this->parameters, $this->prepare($parameters));

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
	public function where(Closure|array $terms)
	{
		if (is_array($terms)) {
			$terms = fn($all, $eq) => $all(...$eq($terms));
		}

		$this->terms = $this->where->scope(Scope::concern->value, $terms);

		return $this;
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
		if (isset($this->concerns)) {
			throw new RuntimeException(sprintf(
				'Cannot re-initialize existing query'
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
			} elseif (is_array($item)) {
				array_push($records, ...$this->unwind(...$item));
			} else {
				array_push($records, $item);
			}
		}

		return $records;
	}
}
