<?php

namespace FluidGraph\Testing;

use FluidGraph\Node;
use FluidGraph\Status;
use FluidGraph\Results;
use FluidGraph\Element;

use Bolt\protocol\V5_2;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEmpty;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertNotEmpty;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

class C01_SimpleTest extends AbstractTest
{
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


	public function testNodeCreation()
	{
		$person = static::$data->person = new Person(name: 'Cynthia Bullwork', age: 37);

		assertTrue($person->is($person));
		assertTrue($person->is(Person::class));
		assertTrue($person->is($person->__element__));
		assertEquals(Status::fastened, $person->status());
	}

	public function testNodeAttach()
	{
		$person = static::$data->person;

		static::$graph->attach($person);

		assertEquals(Status::inducted, $person->status());
	}

	public function testNodeMerge()
	{
		$info  = [];
		$queue = static::$graph->queue;

		$queue->merge($info);

		assertCount(1, $info['nodes']['create'], 1);
	}

	public function testQueueRun()
	{
		$person = static::$data->person;

		static::$graph->queue->run();

		assertEquals(Status::attached, $person->status());
		assertNotEmpty($person->id);
	}

	public function testMatch()
	{
		$results = static::$graph
			->match([Node::class], 1, 0, [
				'name' => 'Cynthia Bullwork'
			])
		;

		$node = $results->at(0);

		assertEquals(0, count($node));
		assertEquals(Element\Node::class, $node::class);
		assertEquals(TRUE, $node->is(Person::class));
		assertEquals(Person::class, $node->as(NULL)::class);
	}

	public function testFindOne()
	{
		$person = static::$graph->findNode(Person::class, ['name' => 'Cynthia Bullwork']);

		assertNotEmpty($person);
		assertSame($person, static::$data->person);
	}


	public function testFindAll()
	{
		$person = static::$graph->findNode(Person::class, ['name' => 'Cynthia Bullwork']);
		$people = static::$graph->findNodes(Person::class);

		assertEquals(1, count($people));
		assertSame($person, $people->last());
		assertSame($person, $people->first());
		assertSame($person, $people->at(0));
	}


	public function testMatchOneForeignUpdate()
	{
		static::$graph
			->exec("MATCH (a:%s {name: 'Cynthia Bullwork'}) SET a.age = 38", Person::class)
		;

		$person = static::$graph->findNode(Person::class, [
				'name' => 'Cynthia Bullwork'
			])
		;

		assertSame(38, $person->age);
	}

	public function testMatchOneForeignUpdateConflict()
	{
		$person = static::$graph->findNode(Person::class, [
			'name' => 'Cynthia Bullwork'
		]);

		$person->setAge(40);

		static::$graph
			->exec("MATCH (a:%s {name: 'Cynthia Bullwork'}) SET a.age = 39", Person::class)
		;

		$person = static::$graph->findNode(Person::class, ['name' => 'Cynthia Bullwork']);

		assertSame($person, static::$data->person);
		assertSame(40, $person->age);
	}

	public function testAsEntity()
	{
		/**
		 * @var Person
		 */
		$person = static::$data->person;
		$author = $person->as(Author::class, ['penName' => 'Hairy Poster']);

		assertTrue($person->is(Author::class));
		assertTrue($author->is(Person::class));
	}
}
