<?php

use PHPUnit\Framework\TestCase;

use FluidGraph\Graph;
use FluidGraph\Query;
use FluidGraph\Queue;
use FluidGraph\Status;

use Bolt\Bolt;
use Bolt\protocol\V5_2;
use Bolt\connection\StreamSocket;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;

class BaseTest extends TestCase
{
	static protected stdClass $data;

	static protected Graph $graph;

	protected function setUp(): void
	{
		if (!isset(static::$graph)) {
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

	protected function tearDown(): void
	{
		static::$graph->run('MATCH (n:Author|Book|Publisher) DETACH DELETE n;')->get();
	}

	public function testConnection()
	{
		assertInstanceOf(V5_2::class, static::$graph->protocol);
	}

	public function testNodeCreation()
	{
		static::$data->author = new Author('Cynthia Bullwork', 37);

		assertEquals(Status::FASTENED, static::$data->author->status());
	}

	public function testNodeAttach()
	{
		static::$graph->attach(static::$data->author);

		assertEquals(Status::INDUCTED, static::$data->author->status());
	}
}
