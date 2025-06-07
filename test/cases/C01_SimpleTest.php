<?php

use FluidGraph\Status;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNotEmpty;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

class C01_SimpleTest extends C00_BaseTest
{
	public function testNodeCreation()
	{
		$author = static::$data->author = new Author('Cynthia Bullwork', 37);

		assertTrue($author->is($author));
		assertTrue($author->is(Author::class));
		assertTrue($author->is($author->__element__));
		assertEquals(Status::FASTENED, $author->status());
	}

	public function testNodeAttach()
	{
		$author = static::$data->author;

		static::$graph->attach($author);

		assertEquals(Status::INDUCTED, $author->status());
	}

	public function testNodeMerge()
	{
		$info  = array();
		$queue = static::$graph->queue;

		$queue->merge($info);

		$this->log('%s:%s Merge completed in: %f', __FUNCTION__, __LINE__, $info['time']);

		assertCount(1, $info['nodes']['create'], 1);
	}

	public function testQueueRun()
	{
		$author = static::$data->author;

		static::$graph->queue->run();

		assertEquals(Status::ATTACHED, $author->status());
	}

	public function testMatchOne()
	{
		$author = static::$data->author;

		static::$graph->attach($author)->queue->merge()->run();

		$author = static::$graph->query->matchOne(Author::class, ['name' => 'Cynthia Bullwork']);

		assertNotEmpty($author);
		assertSame($author, static::$data->author);
	}

	public function testMatchOneForeignUpdate()
	{
		static::$graph
			->run("MATCH (a:Author {name: 'Cynthia Bullwork'}) SET a.age = 38")
			->pull()
		;

		$author = static::$graph->query->matchOne(Author::class, ['name' => 'Cynthia Bullwork']);

		assertSame(38, $author->age);
	}

	public function testMatchOneForeignUpdateConflict()
	{
		$author = static::$graph->query->matchOne(Author::class, ['name' => 'Cynthia Bullwork']);

		$author->setAge(40);

		static::$graph
			->run("MATCH (a:Author {name: 'Cynthia Bullwork'}) SET a.age = 39")
			->pull()
		;

		$author = static::$graph->query->matchOne(Author::class, ['name' => 'Cynthia Bullwork']);

		assertSame(40, $author->age);
	}
}
