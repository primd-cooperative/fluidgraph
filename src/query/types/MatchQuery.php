<?php

namespace FluidGraph\Query;

use FluidGraph;
use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Scope;
use FluidGraph\Entity;
use FluidGraph\Element;
use FluidGraph\Matching;
use FluidGraph\Reference;
use InvalidArgumentException;

/**
 * @template T of Entity
 */
class MatchQuery extends FluidGraph\Query
{
	use FluidGraph\DoesMatch;

	/**
	 * @var array<class-string<T>|string>
	 */
	public protected(set) ?array $concerns = [];

	/**
	 *
	 */
	protected string $pattern;

	/**
	 * @var Element\Results<T>
	 */
	protected Element\Results $results;

	/**
	 *
	 */
	protected string $scope;

	/**
	 * Initialize a match query (for element selection).
	 *
	 * This is used by match() and matchAny() in order to select elements, based on concerns we'll
	 * determine what the match pattern looks like for our cypher query.
	 *
	 * @param array<class-string<T>|string> $concerns
	 */
	public function __construct(Scope|string $scope = Scope::concern, Matching $rule = Matching::all, array $concerns = [], Reference $type = Reference::either)
	{
		parent::__construct();

		$edge_matches   = 0;
		$node_matches   = 0;
		$this->concerns = $concerns;

		if ($scope instanceof Scope) {
			$this->scope = $scope->value;
		}

		foreach ($concerns as $i => $concern) {
			if (is_a($concern, Edge::class, TRUE)) {
				$edge_matches++;

				if ($concern == Edge::class) {
					unset($this->concerns[$i]);
				}

			} else {
				$node_matches++;

				if ($concern == Node::class) {
					unset($this->concerns[$i]);
				}
			}
		}

		if ($edge_matches && $node_matches) {
			throw new InvalidArgumentException(sprintf(
				'Cannot match on mixed node/edge types: %s',
				implode(', ', $this->concerns)
			));

		} elseif ($edge_matches) {
			$this->pattern = edge($this->scope, $this->concerns, $type);

		} else {
			$this->pattern = node($this->scope, $this->concerns, $rule);

		}
	}


	/**
	 *
	 */
	public function having(array|string $left = [], array|string $right = []): static
	{
		settype($left, 'array');
		settype($right, 'array');

		$left = implode('', array_map(
			function(string $stop): string {
				if (str_starts_with($stop, '<')) {
					return substr($stop, 1) . '>';
				}

				if (str_ends_with($stop, '>')) {
					return '<' . substr($stop, 0, -1);
				}

				return $stop;
			},
			array_reverse($left)
		));

		$right = implode('', $right);

		$this->pattern = $left . $this->pattern. $right;

		return $this;
	}


	/**
	 * Get the records and resolve them via the graph.
	 *
	 * Resolving will convert any actual Node/Edge responses into elements and register them with
	 * the graph.  If you need to perform manual resolution or work with raw return data, use
	 * the `records()` method instead.
	 *
	 * @return Element\Results
	 */
	public function results(): Element\Results
	{
		if (!isset($this->results)) {
			$this->results = new Element\Results(array_map(
				$this->graph->resolve(...),
				$this->records()
			));
		}

		return $this->results;
	}


	/**
	 * Compose a complete query
	 */
	protected function compile(): string
	{
		if (in_array(substr($this->pattern, 0, 1), ['-', '<'])) {
			$this->pattern = '()' . $this->pattern;
		}

		if (in_array(substr($this->pattern, -1, 1), ['-', '>'])) {
			$this->pattern = $this->pattern . '()';
		}

		$this->append('MATCH %s', $this->pattern);

		if (isset($this->terms)) {
			$scope = $this->where->with($this->scope, $this->terms);
			$terms = call_user_func($scope);

			if ($terms) {
				$this->append('WHERE %s', $terms);
			};
		}

		$this->append('RETURN DISTINCT %s', $this->scope);

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

			$this->append('ORDER BY %s', implode(',', $orders));
		}

		if ($this->offset > 0) {
			$this->append('SKIP %s', $this->offset);
		}

		if ($this->limit >= 0) {
			$this->append('LIMIT %s', $this->limit);
		}

		return parent::compile();
	}
}
