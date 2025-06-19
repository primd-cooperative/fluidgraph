<?php

namespace FluidGraph\Testing;

use FluidGraph\Element;
use FluidGraph\Node;
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
		$results = static::$graph->query
			->match(Node::class)
			->where([
				'name' => 'Cynthia Bullwork'
			])
			->take(1)
			->skip(0)
			->results()
		;

		$node = $results->at(0);

		assertEquals(0, count($node));
		assertEquals(Element\Node::class, $node::class);
		assertEquals(TRUE, $node->is(Person::class));
		assertEquals(Person::class, $node->as(NULL)::class);
	}

	public function testFindOne()
	{
		$person = static::$graph->findOne(Person::class, ['name' => 'Cynthia Bullwork']);

		assertNotEmpty($person);
		assertSame($person, static::$data->person);
	}


	public function testFindAll()
	{
		$person = static::$graph->findOne(Person::class, ['name' => 'Cynthia Bullwork']);
		$people = static::$graph->findAll(Person::class);

		assertEquals(1, count($people));
		assertSame($person, $people->last());
		assertSame($person, $people->first());
		assertSame($person, $people->at(0));
	}


	public function testMatchOneForeignUpdate()
	{
		static::$graph
			->run("MATCH (a:%s {name: 'Cynthia Bullwork'}) SET a.age = 38", Person::class)
		;

		$person = static::$graph->query
			->match(Person::class)
			->where(['name' => 'Cynthia Bullwork'])
			->get()
			->at(0)
		;

		assertSame(38, $person->age);
	}

	public function testMatchOneForeignUpdateConflict()
	{
		$person = static::$graph->findOne(Person::class, ['name' => 'Cynthia Bullwork']);

		$person->setAge(40);

		$query = static::$graph
			->run("MATCH (a:%s {name: 'Cynthia Bullwork'}) SET a.age = 39", Person::class)
		;

		$person = static::$graph->findOne(Person::class, ['name' => 'Cynthia Bullwork']);

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
