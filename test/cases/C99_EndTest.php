<?php

use function PHPUnit\Framework\assertTrue;

class C99_EndTest extends C00_BaseTest
{
	protected function tearDown(): void
	{
		static::$graph->run('MATCH (n:Person|Author|Book|Publisher) DETACH DELETE n;')->pull();
	}

	public function testCleanup()
	{
		assertTrue(TRUE);
	}
}
