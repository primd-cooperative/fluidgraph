<?php

namespace FluidGraph;

use ArrayObject;
use Bolt\enum\Signature;
use Bolt\protocol\IStructure;
use Bolt\protocol\Response;
use Bolt\protocol\v5\structures\DateTimeZoneId;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use DateTime;

class Query
{
	use HasGraph;

	const REGEX_EXPANSION = '#@([a-zA-Z][a-zA-Z0-9]*)(?:\(([a-zA-Z][a-zA-Z0-9]*)\))?#';

	/**
	 * @var array<Element>
	 */
	protected array $results;


	/**
	 * @var array
	 */
	protected array $responses;


	/**
	 * An instance of the base where clause builder to clone
	 */
	public protected(set) Where $where;

	/**
	 *
	 */
	public function __construct(
		protected array $statements = [],
		protected array $parameters = [],
	) {}


	/**
	 *
	 */
	public function __clone() {
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
	public function compile(): string
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
	public function get(int ...$index): Results|Element
	{
		if (!isset($this->results)) {
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
	 * Match multiple nodes or edges and have them returned as an instance of a given class.
	 *
	 * The type of elements (node or edge) being matched is determined by the class.
	 *
	 * @template T of Entity
	 * @param class-string<T> $class
	 * @return Results<T>
	 */
	public function match(string $class, callable|array|int $terms = [], ?array $order = NULL, int $limit = -1, int $skip = 0): Results
	{
		if (is_int($terms)) {
			return $this->match(
				$class,
				fn($id) => $id($terms),
				$order,
				$limit,
				$skip
			);

		} elseif (is_array($terms)) {
			return $this->match(
				$class,
				fn($all, $eq) => $all(...$eq($terms)),
				$order,
				$limit,
				$skip
			);

		} else {
			match(TRUE) {
				is_subclass_of($class, Node::class, TRUE) => $this->run('MATCH (i:%s)', $class),
				is_subclass_of($class, Edge::class, TRUE) => $this->run('MATCH (n1)-[i:%s]->(n2)', $class),
			};

			$conditions = $this->where->scope('i', $terms);

			if ($conditions) {
				$this->run('WHERE %s', $conditions);
			}

			$this->run('RETURN i');

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
	 * @template T of Entity
	 * @param class-string<T> $class
	 * @return ?T
	 */
	public function matchOne(string $class, callable|array|int $query): ?Entity
	{
		$results = $this->match($class, $query, [], 2, 0);

		if (count($results) > 1) {
			throw new RuntimeException(sprintf(
				'Match returned more than one result'
			));
		}

		return $results[0] ?? NULL;
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
