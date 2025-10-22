<?php

namespace FluidGraph\Entity\Id\Hash;

use FluidGraph\Entity\Id;

trait Name
{
	use Id\Hash;

	public string $name;

	/**
	 *
	 */
	static public function getHashKeys(): array
	{
		return [
			'name' => [
				'trim',
				'strtolower',
				fn($value) => str_replace([' ', '-'], ['', ''], $value)
			]
		];
	}
}
