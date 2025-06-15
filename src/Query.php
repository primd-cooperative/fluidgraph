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
	 *
	 */
	public protected(set) string $items;

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
	 * Match multiple nodes or edges and have them returned as an instance of a given class.
	 *
	 * The type of elements (node or edge) being matched is determined by the class.
	 *
	 * @template T of Entity
	 * @param class-string<T> $class
	 * @return Results<T>
	 */
	public function find(string $class, callable|array $terms = [], ?array $order = NULL, ?int $limit = NULL, ?int $offset = NULL): Results
	{
		if (is_array($terms)) {
			$terms = fn($all, $eq) => $all(...$eq($terms));
		}

		$query = new static()->on($this->graph)->match($class);

		if (!empty($terms)) {
			$query->where($terms);
		}

		if (!is_null($order)) {
			$query->sort($order);
		}

		if (!is_null($limit)) {
			$query->take($limit);
		}

		if (!is_null($offset)) {
			$query->skip($offset);
		}

		return $query->get()->as($class);
	}


	/**
	 * Find a single node or edge and have it returned as an instance of a given class.
	 *
	 * The type of element (node or edge) being matched is determined by the class.
	 *
	 * @template T of Entity
	 * @param class-string<T> $class
	 * @return ?T
	 */
	public function findOne(string $class, callable|array|int $query): ?Entity
	{
		if (is_int($query)) {
			$query = function($id) use ($query) {
				return $id($query);
			};
		}

		$results = $this->find($class, $query, [], 2, 0);

		if (count($results) > 1) {
			throw new RuntimeException(sprintf(
				'Trying to match a unique result returned more than one result'
			));
		}

		return $results[0] ?? NULL;
	}


	/**
	 *
	 */
	public function get(int ...$index): Results|Element
	{
		if (!isset($this->results)) {
			if (isset($this->items)) {
				$this->run('MATCH %s', $this->items);

				if ($conditions = call_user_func($this->terms)) {
					$this->run('WHERE %s', $conditions);
				}

				$this->run('RETURN *');

				if ($this->orders) {
					// TODO: order
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
	public function match(string ...$labels): static
	{
		if (count($labels)) {
			$this->items = sprintf('(i:%s)', implode(Like::all->value, $labels));
		} else {
			$this->items = '(i)';
		}

		return $this;
	}


	/**
	 *
	 */
	public function matchAny(string ...$labels): static
	{
		if (count($labels)) {
			$this->items = sprintf('(i:%s)', implode(Like::any->value, $labels));
		} else {
			$this->items = '(i)';
		}

		return $this;
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
	public function sort(array ...$orders): static
	{
		array_push($this->orders, ...$orders);

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
	public function where(Closure $terms)
	{
		$this->terms = $this->where->scope('i', $terms);

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
