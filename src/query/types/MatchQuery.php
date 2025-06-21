<?php

namespace FluidGraph\Query;

use FluidGraph;
use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Scope;
use FluidGraph\Entity;
use FluidGraph\Element;
use FluidGraph\Matching;

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
	public protected(set) string $pattern;


	/**
	 * @var Element\Results<T>
	 */
	protected Element\Results $results;


	/**
	 * Initialize a match query (for element selection).
	 *
	 * This is used by match() and matchAny() in order to select elements, based on concerns we'll
	 * determine what the match pattern looks like for our cypher query.
	 *
	 * @param class-string<T>|string ...$concerns
	 */
	public function __construct(Matching $method, string ...$concerns)
	{
		parent::__construct();

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
		$this->append('MATCH %s', $this->pattern);

		if (isset($this->terms)) {
			$scope = $this->where->scope(Scope::concern->value, $this->terms);
			$terms = call_user_func($scope);

			if ($terms) {
				$this->append('WHERE %s', $terms);
			};
		}

		$this->append('RETURN DISTINCT %s', Scope::concern->value);

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

		if ($this->limit >= 0) {
			$this->append('LIMIT %s', $this->limit);
		}

		if ($this->offset > 0) {
			$this->append('SKIP %s', $this->offset);
		}

		return parent::compile();
	}
}
