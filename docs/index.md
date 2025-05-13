# FluidGraph


## Installation

```
composer require primd/fluidgraph
```

## Basic Concepts

FluidGraph borrows something like a "spiritual ontology" in order to talk about its core
concepts.  The "Entity world" is effectively the natural world.  The "Element world" is the
spiritual world.  Entities are bound to an Element.  Both Entities and Elements have different
forms, namely Nodes and Edges.

**Element**: A formless essential representation of a graph object.

**Entity**: A formless expression of a graph object.  Mulitple entities can join in union
with the same Element.

**Node**: The essential nature of an Entity or Element representing a graph node.  In the FluidGraph
namespace an Entity Node is simply a `Node` while an Element Node is an `Element\Node`.

**Edge**: The essential nature of an Entity or Element representing a graph edge.  In the FluidGraph
namespace an Entity Edge is simply an `Edge` while an Element Node is an `Element\Edge`.

**Relationship**: A collection of one or more Edges connecting a source Node, pointing to one or
more target Nodes.

## Basic Usage

Create a new Entity class by defining its properties and relationships.  All properties on your
entities MUST be publicly redadable.  They can be protected/private for setting, however, note
that you MUST NOT use property hooks.

```php
<?php

use FluidGraph\Node;
use FluidGraph\Entity;
use FluidGraph\Relationship;
use FluidGraph\Mode;

class Person extends FluidGraph\Node
{
	use Entity\DateCreated;
	use Entity\DateModified;
	use Entity\Id\Uuid7;

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
back.  The fastened instance is backed by a `Element\Node` or `Element\Edge` depending on its type
and its relationships are linked to the graph instance.

```php
$person = $graph->init(new Person(firstName: 'Bob'));
```
