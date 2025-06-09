<?php

namespace FluidGraph\Testing;

use stdClass;

use FluidGraph\Graph;

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Bolt\protocol\V5_2;

use PHPUnit\Framework\TestCase;

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
				new Bolt(new StreamSocket())
			);
		}
	}

	public function testConnection()
	{
		assertInstanceOf(V5_2::class, static::$graph->protocol);
	}
}
