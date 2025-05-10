<?php

namespace FluidGraph;

use Bolt\enum\Signature;
use Bolt\protocol\v5\structures\DateTimeZoneId;
use InvalidArgumentException;
use DateTime;
use RuntimeException;

class Query
{
	use HasGraph;

	const REGEX_EXPANSION = '#@([a-zA-Z][a-zA-Z0-9]*)(?:\(([a-zA-Z][a-zA-Z0-9]*)\))?#';

	/**
	 * @var array
	 */
	protected array $results;


	/**
	 * @var array
	 */
	protected array $responses;


	/**
	 * An instance of the base where clause builder to clone
	 */
	public protected(set) Where $where {
		get {
			return clone $this->where;
		}
		set (Where $where) {
			$this->where = $where;
		}
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
	public function expand()
	{
		return [
			'cypher'     => $this->compile(),
			'parameters' => $this->parameters
		];
	}


	/**
	 *
	 */
	public function get(int ...$index): Results|Result
	{
		if (!isset($this->results)) {
			$cypher        = '';
			$this->results = [];

			foreach ($this->pull() as $response) {
				if ($response->signature != Signature::RECORD) {
					continue;
				}

				$this->results = array_merge(
					$this->results,
					array_map(
						$this->graph->resolve(...),
						$response->content
					)
				);
			}
		}

		if (count($index) == 1) {
			return new Result($this->results[$index[0]])->on($this->graph);

		} elseif (count($index) == 0) {
			return new Results(array_map(
				function ($element) {
					return new Result($element)->on($this->graph);
				},
				$this->results
			))->on($this->graph);

		} else {
			return new Results(array_map(
				function($element) {
					return new Result($element)->on($this->graph);
				},
				array_filter(
					$this->results,
					function($key) use ($index) {
						return in_array($key, $index);
					},
					ARRAY_FILTER_USE_KEY
				)
			))->on($this->graph);
		}
	}

	/**
	 * Match multiple nodes or edges and have them returned as an instance of a given class.
	 *
	 * The type of elements (node or edge) being matched is determined by the class.
	 *
	 * @template T of Element
	 * @param class-string<T> $class
	 * @return array<T>
	 */
	public function match(string $class, callable|array|int $terms = [], ?array $order = NULL, int $limit = -1, int $skip = 0): array
	{
		if (is_int($terms)) {
			return $this->match(
				$class,
				function($where) use ($terms) {
					return $where->id($terms);
				},
				$order,
				$limit,
				$skip
			);

		} elseif (is_array($terms)) {
			return $this->match(
				$class,
				function($where) use ($terms) {
					return $where->all(...$where->eq($terms));
				},
				$order,
				$limit,
				$skip
			);

		} else {
			$apply = $terms($this->where->var('n'));

			$this->run('MATCH (n:%s)', $class);

			if ($apply) {
				$conditions = $apply();

				if ($conditions) {
					$this->run('WHERE %s', $conditions);
				}
			}

			$this->run('RETURN n');

			if ($order) {
				$this->run('ORDER BY');
			}

			if ($limit >= 0) {
				$this->run('LIMIT %s', $limit);
			}

			if ($skip > 0) {
				$this->run('SKIP %s', $skip);
			}

			return $this->get()->as($class);
		}
	}


	/**
	 * Match a single node or edge and have it returned as an instance of a given class.
	 *
	 * The type of element (node or edge) being matched is determined by the class.
	 *
	 * @template T of Element
	 * @param class-string<T> $class
	 * @return ?T
	 */
	public function matchOne(string $class, callable|array|int $query): ?Element
	{
		$results = $this->match($class, $query, [], 2, 0);

		if (count($results) > 1) {
			throw new RuntimeException(sprintf(
				'Match returned more than one result'
			));
		}

		return $results[0];
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
			}
		}

		if (count($signatures)) {
			$responses = array_values(array_filter(
				$this->responses,
				function($response) use ($signatures) {
					return in_array($response->signature, $signatures);
				}
			));

			if (!count($responses)) {
				return [];
			} else {
				return count($signatures) == 1
					? $responses[0]->content
					: $responses
				;
			}
		}

		return $this->responses;
	}


	/**
	 *
	 */
	public function run(string $statement, mixed ...$args): static
	{
		unset($this->results);

		$this->statements[] = sprintf($statement, ...$args);

		return $this;
	}


	/**
	 *
	 */
	public function with(string $name, mixed $value): static
	{
		unset($this->results);

		$this->parameters[$name] = $this->prepare($value);

		return $this;
	}


	/**
	 *
	 */
	public function withAll(array $parameters): static
	{
		unset($this->results);

		$this->parameters = array_replace($this->parameters, $this->prepare($parameters));

		return $this;
	}


	/**
	 *
	 */
	protected function compile(): string
	{
		return implode("\n", array_map(
			function($statement) {
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
}
