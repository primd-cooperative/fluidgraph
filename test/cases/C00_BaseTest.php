<?php

use PHPUnit\Framework\TestCase;

use FluidGraph\Graph;
use FluidGraph\Query;
use FluidGraph\Queue;

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Bolt\protocol\V5_2;

use function PHPUnit\Framework\assertInstanceOf;

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
				new Bolt(new StreamSocket()),
				new Query(),
				new Queue()
			);
		}
	}

	public function testConnection()
	{
		assertInstanceOf(V5_2::class, static::$graph->protocol);
	}
}
