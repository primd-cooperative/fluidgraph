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
			new Book(name: 'Lord of The Pings 2', pages: 2314), [
				'date' => new DateTime('July 1st 2007')
			]
		);

		$author->writings->set(
			new Book(name: 'Lord of The Pings 3', pages: 2), [
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
				'Lord of The Pings 3',
				'Lord of The Pings 2'
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

	public function testEdgeFor()
	{
		$books  = static::$graph->findAll(Book::class);
		$author = static::$data->person->as(Author::class);

		foreach ($books as $book) {
			if ($book->name == 'Lord of The Pings 3') {
				$edge = $author->writings->for($book)->at(0);

				assertEquals(new DateTime('July 1st 2003'), $edge->date);
			}
		}
	}


	public function testFind()
	{
		$author = static::$data->person->as(Author::class);
		$books  = $author->writings->find(Book::class, 2);

		assertEquals(2, count($books));
	}


	public function testMerge()
	{
		$author = static::$data->person->as(Author::class);
		$books  = $author->writings->find(Book::class, 3);

		foreach ($books as $book) {
			if ($book->name == 'Lord of The Pings 1') {
				$books->unset($book);
			}
		}

		$books->merge();

		assertEquals(2, count($books));
		assertEquals(2, count($author->writings));

		static::$graph->save();

		$books = static::$graph->find(Book::class);

		assertEquals(2, count($books));
	}
}
