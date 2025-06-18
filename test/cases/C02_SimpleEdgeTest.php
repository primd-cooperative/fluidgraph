<?php

namespace FluidGraph\Testing;

use DateTime;
use Exception;
use FluidGraph\Status;
use InvalidArgumentException;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

/**
 *
 */
class C02_SimpleEdgeTest extends C00_BaseTest
{
	public function testLocalToManyRelations()
	{
		$book   = static::$data->book = new Book('The Cave of Blunder', 328);
		$author = static::$data->person->as(Author::class);

		assertFalse($author->writings->contains($book));

		$author->writings->set($book, ['date' => new DateTime('July 1st, 1982')]);

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

		assertEquals(Status::attached, $author->status());
		assertEquals(Status::inducted, $book->status());

	}


	public function testRelatedQueueRun()
	{
		$book = static::$data->book;

		static::$graph->queue->run();

		assertEquals(Status::attached, $book->status());
	}


	public function testMultiAttach()
	{
		$author = static::$data->person->as(Author::class);

		try {
			$author->writings->set(new Book(name: 'Failed', pages: 5));
		} catch (Exception $e) {
			assertInstanceOf(InvalidArgumentException::class, $e);
		}

		$author->writings->set(
			new Book(name: 'Lord of The Pings 1', pages: 523), [
				'date' => new DateTime('July 1st 2001')
			]
		);

		$author->writings->set(
			new Book(name: 'Lord of the Pings 2', pages: 2314), [
				'date' => new DateTime('July 1st 2007')
			]
		);

		$author->writings->set(
			new Book(name: 'Lord of the Pings 3', pages: 2), [
				'date' => new DateTime('July 1st 2003')
			]
		);

		static::$graph->save();

		assertEquals(4, count($author->writings));
	}

	public function testFlush()
	{
		$author = static::$data->person->as(Author::class);

		$author->writings->flush();

		assertEquals(4, count($author->writings));
	}

	public function testGetOrder()
	{
		$author = static::$data->person->as(Author::class);

		foreach ($author->writings->get() as $i => $book) {
			$titles = [
				'The Cave of Blunder',
				'Lord of The Pings 1',
				'Lord of the Pings 3',
				'Lord of the Pings 2'
			];

			assertEquals($titles[$i], $book->name);
		}
	}

	public function testUnset()
	{
		$author = static::$data->person->as(Author::class);

		foreach ($author->writings->get() as $i => $book) {
			if ($book->name == 'The Cave of Blunder') {
				$author->writings->unset($book);
			}
		}

		assertEquals(3, count($author->writings));

		static::$graph->save();

		$author->writings->flush();

		assertEquals(3, count($author->writings));
	}


}
