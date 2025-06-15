<?php

namespace FluidGraph\Testing;

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
		$info  = array();
		$queue = static::$graph->queue;

		$queue->merge($info);

		$this->log('%s:%s Merge completed in: %f', __FUNCTION__, __LINE__, $info['time']);

		assertCount(1, $info['nodes']['create'], 1);
	}

	public function testQueueRun()
	{
		$person = static::$data->person;

		static::$graph->queue->run();

		assertEquals(Status::attached, $person->status());
	}

	public function testMatchOne()
	{
		$person = static::$graph->findOne(Person::class, ['name' => 'Cynthia Bullwork']);

		assertNotEmpty($person);
		assertSame($person, static::$data->person);
	}

	public function testMatchOneForeignUpdate()
	{
		static::$graph
			->run("MATCH (a:%s {name: 'Cynthia Bullwork'}) SET a.age = 38", Person::class)
			->pull()
		;

		$person = static::$graph->findOne(Person::class, ['name' => 'Cynthia Bullwork']);

		assertSame(38, $person->age);
	}

	public function testMatchOneForeignUpdateConflict()
	{
		$person = static::$graph->findOne(Person::class, ['name' => 'Cynthia Bullwork']);

		$person->setAge(40);

		static::$graph
			->run("MATCH (a:%s {name: 'Cynthia Bullwork'}) SET a.age = 39", Person::class)
			->pull()
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
