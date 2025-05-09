<?php

namespace FluidGraph;

use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\v5\structures\DateTimeZoneId;
use InvalidArgumentException;
use DateTime;

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
	 *
	 */
	public function __construct(
		protected array $statements = [],
		protected array $parameters = [],
	) {}


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
