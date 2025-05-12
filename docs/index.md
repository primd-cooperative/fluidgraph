# FluidGraph


## Installation

```
composer require primd/fluidgraph
```

## Basic Usage

Create a new Entity class by defining its properties and relationships.  All properties on your
entities MUST be publicly redadable.  They can be protected/private for setting, however, note
that you MUST NOT use property hooks.

```php
<?php

use FluidGraph\Node;
use FluidGraph\Element;
use FluidGraph\Relationship;
use FluidGraph\Mode;

class Person extends FluidGraph\Node
{
	use Element\DateCreated;
	use Element\DateModified;
	use Element\Id\Uuid7;

	public private(set) Relationship\ToMany $suggestions;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
	) {
		$this->suggestions = new Relationship\ToMany(
			$this,
			Suggested::class,
			[
				Claim::class
			],
			Mode::LAZY
		);
	}
}
```

In the above example, we create a new node class which

You can initialize an entity using its native class constructor and received the fastened instance
back.  The fastened instance is backed by a `Content\Node` or `Content\Edge` depending on its type
and its relationships are linked to the graph instance.

```php
$person = $graph->init(new Person(firstName: 'Bob'));
```
