<?php

use FluidGraph\Status;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

class C02_SimpleEdgeTest extends C00_BaseTest
{
	public function testLocalToOneRelations()
	{
		$book = static::$data->book = new Book('Hairy Poster', 328);

		assertFalse($book->authorship->contains(static::$data->author));

		$book->authorship->set(static::$data->author);

		assertTrue($book->authorship->contains(static::$data->author));

		$book->authorship->unset();

		assertFalse($book->authorship->contains(static::$data->author));
	}


	public function testImplicitEdgeAttach()
	{
		$info   = [];
		$book   = static::$data->book;
		$author = static::$data->author;

		$book->authorship->set($author);

		static::$graph->attach($book)->queue->merge($info);

		assertCount(1, $info['nodes']['create']);
		assertCount(1, $info['edges']['create']);

		assertEquals(Status::INDUCTED, $book->authorship->of($author)->status());
	}


	public function testRelatedQueueRun()
	{
		static::$graph->queue->run();

		$book   = static::$data->book;
		$author = static::$data->author;

		assertEquals(Status::ATTACHED, $book->status());
		assertEquals(Status::ATTACHED, $book->authorship->of($author)->status());
	}
}
