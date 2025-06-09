<?php

namespace FluidGraph\Testing;

use FluidGraph\Status;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

class C02_SimpleEdgeTest extends C00_BaseTest
{
	public function testLocalToOneRelations()
	{
		$book   = static::$data->book = new Book('The Cave of Blunder', 328);
		$author = static::$data->person->as(Author::class);

		assertFalse($author->writings->contains($book));

		$author->writings->set($book);

		assertTrue($author->writings->contains($book));
	}


	public function testImplicitEdgeAttach()
	{
		$info = [];
		$book   = static::$data->book;
		$author = static::$data->person->as(Author::class);

		static::$graph->queue->merge($info);

		assertCount(1, $info['nodes']['create']);
		assertCount(1, $info['edges']['create']);

		assertEquals(Status::ATTACHED, $author->status());
		assertEquals(Status::INDUCTED, $book->status());

	}


	public function testRelatedQueueRun()
	{
		$book   = static::$data->book;
		$author = static::$data->person->as(Author::class);

		static::$graph->queue->run();

		assertEquals(Status::ATTACHED, $book->status());
	}
}
