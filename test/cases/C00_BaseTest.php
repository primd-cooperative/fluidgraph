<?php

namespace FluidGraph\Testing;

use stdClass;

use FluidGraph\Graph;

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Bolt\protocol\V5_2;
use FluidGraph\Results;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEmpty;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

class C00_BaseTest extends TestCase
{
	static protected stdClass $data;

	static protected Graph $graph;

	protected function log(string $template, mixed ...$args)
	{
		fwrite(STDERR, sprintf(PHP_EOL . $template . PHP_EOL, ...$args));
	}

	protected function setUp(): void
	{
		if (!isset(static::$data)) {
			static::$data  = new stdClass();
			static::$graph = new Graph(
				[
					'scheme'      => 'basic',
					'principal'   => 'memgraph',
					'credentials' => 'password'
				],
				new Bolt(new StreamSocket('127.0.0.1', 31337))
			);
		}
	}

	public function testBaseResults()
	{
		$results = new Results([0, 1, 2, 3, 4, 5]);

		assertSame(NULL, $results->at(10));

		assertEquals(0, $results->at(0));

		assertEquals(3, count($results->at(0, 1, 2)));

		assertEquals(
			[0, 2, 4],
			$results->at(0, 2, 4)->unwrap()
		);

		assertEquals(
			[0, 1, 2, 3, 4, 5],
			$results->unwrap()
		);

		assertEquals(0, $results->first());

		assertEquals(5, $results->last());

		assertEquals(
			['s0', 's1', 's2', 's3', 's4', 's5'],
			$results->map('s%s')->unwrap()
		);

		assertEquals(
			['c0', 'c1', 'c2', 'c3', 'c4', 'c5'],
			$results->map(fn($v) => 'c' . $v)->unwrap()
		);

		assertEquals(
			[2, 3, 4],
			$results->slice(2, 3)->unwrap()
		);

		assertEquals(
			[2, 3, 4],
			$results->when([2, 3, 4])->unwrap()
		);

		assertEquals(
			[1, 3, 5],
			$results->when(fn($v) => $v & 1)->unwrap()
		);
	}

	public function testConnection()
	{
		assertInstanceOf(V5_2::class, static::$graph->protocol);

	}

	public function testQueryResults()
	{
		$results = static::$graph
			->query('UNWIND [0, 1, 2, 3, 4, 5, 6] AS res RETURN res')
			->results()
		;

		assertEquals(7, count($results));

		assertEquals(3, $results->at(3));

		assertEquals(
			[1, 2, 3],
			$results->at(1, 2, 3)->unwrap()
		);

		assertEquals(
			[4, 5, 6],
			$results->slice(4, 3)->unwrap()
		);

		assertEquals(0, $results->first());

		assertEquals(6, $results->last());

		assertEquals('test 1', $results->map('test %s')->at(1));

		assertEquals(
			[0, 2, 4, 6, 8, 10, 12],
			$results->map(fn($r) => $r * 2)->unwrap()
		);

		assertEquals(
			[1, 3, 5],
			$results->when(fn($r) => $r & 1)->unwrap()
		);

		assertEquals(
			[2, 4, 5],
			$results->when([17, 5, 26, 30, 4, 100, 2])->unwrap()
		);

		assertNull($results->at(10));

		assertEmpty($results->at(20, 33, 40));
	}

	public function testMatchQuery()
	{
		$node_results = static::$graph->findNodes();
		$edge_results = static::$graph->findEdges();

		assertCount(0, $node_results);
		assertCount(0, $edge_results);
	}
}
