<?php

namespace FluidGraph\Testing;

use function PHPUnit\Framework\assertTrue;

class C99_EndTest extends AbstractTest
{
	protected function tearDown(): void
	{
		static::$graph->exec(
			'MATCH (n:%s) DETACH DELETE n;',
			implode(
				'|',
				array_map(
					fn($class) => str_replace('\\', '_', $class),
					[
						Publisher::class,
						Person::class,
						Author::class,
						Book::class,
					]
				)
			)
		);
	}

	public function testCleanup()
	{
		assertTrue(TRUE);
	}
}
