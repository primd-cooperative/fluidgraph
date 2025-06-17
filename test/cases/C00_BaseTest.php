<?php

namespace FluidGraph\Testing;

use stdClass;

use FluidGraph\Graph;

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Bolt\protocol\V5_2;

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEmpty;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertNull;

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

	public function testConnection()
	{
		assertInstanceOf(V5_2::class, static::$graph->protocol);
	}

	public function testMatchQuery()
	{
		$el_results = static::$graph->query->match()->results();
		$en_results = static::$graph->query->match()->results();

		assertEquals(0, count($el_results));
		assertEquals(0, count($en_results));
	}


	public function testResults()
	{
		$results = static::$graph
			->run('UNWIND [0, 1, 2, 3, 4, 5, 6] AS res RETURN res')
			->results()
		;

		assertEquals(7, count($results));
		assertEquals(3, $results->at(3));

		assertEquals([1, 2, 3], $results->at(1, 2, 3)->unwrap());
		assertEquals([4, 5, 6], $results->slice(4, 3)->unwrap());

		assertEquals(0, $results->first());
		assertEquals(6, $results->last());

		assertEquals('test 1', $results->map('test %s')->at(1));
		assertEquals([0, 2, 4, 6, 8, 10, 12], $results->map(fn($r) => $r * 2)->unwrap());

		assertEquals([1, 3, 5], $results->filter(fn($r) => $r & 1)->unwrap());
		assertEquals([2, 4, 5], $results->filter([17, 5, 26, 30, 4, 100, 2])->unwrap());

		assertNull($results->at(10));
		assertEmpty($results->at(20, 33, 40));
	}
}
