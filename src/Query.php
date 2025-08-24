<?php

namespace FluidGraph;

use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\v5\structures\DateTimeZoneId;

use InvalidArgumentException;
use RuntimeException;
use DateTime;
use Closure;
use Psr\Log\LogLevel;

/**
 *
 */
abstract class Query
{
	use HasGraph;

	const REGEX_EXPANSION = '#@([a-zA-Z][a-zA-Z0-9]*)(?:\(([a-zA-Z][a-zA-Z0-9]*)\))?#';

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
	public protected(set) Where $where;


	/**
	 * @param array<string> $statements
	 * @param array<string, mixed> $parameters
	 */
	public function __construct(
		protected array $statements = [],
		protected array $parameters = [],
	) {
		$this->where = new Where($this);
	}


	/**
	 *
	 */
	public function __debugInfo()
	{
		return array_filter(
			get_object_vars($this),
			fn($key) => !in_array($key, [
				'graph',
				'where'
			]),
			ARRAY_FILTER_USE_KEY
		);
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
	 * Run the query.
	 *
	 * This will execute the query, pull, and collect the unpacked responses as records which are
	 * accessible in raw form via `records()`.
	 */
	public function run(): static
	{
		$cypher_code = $this->compile();

		if (isset($this->graph->logger)) {
			$this->graph->logger->log(LogLevel::DEBUG, sprintf('Query: %s', $cypher_code), $this->parameters);
		}

		$responses = iterator_to_array(
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

		$this->records = $this->unpack(
			array_filter(
				$responses,
				fn($response) => $response->signature == Signature::RECORD
			)
		);

		if (isset($this->graph->logger)) {
			$this->graph->logger->log(LogLevel::DEBUG, sprintf('Results: %s', count($this->records)));
		}

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
	 *
	 */
	protected function append(string $statement, mixed ...$args): static
	{
		$this->statements[] = sprintf($statement, ...array_map(
			fn($arg) => $arg instanceof Closure ? $arg() : $arg,
			$args
		));

		return $this;
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
