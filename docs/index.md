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
more target Nodes.  The relationship knows of the source Entity Node, while its edges know only
of the source Element Node and the target Element Nodes.

## Basic Usage

Create a new Entity classes (Edges and Nodes) defining their properties and relationships.  All
properties on your entities MUST be publicly readable.  They can have `protected(set)` or
`private(set)`, however, note that you CANNOT use property hooks.

```php
use FluidGraph\Node;
use FluidGraph\Edge;
use FluidGraph\Entity;
use FluidGraph\Relationship;

class FriendsWith extends Edge
{
	use Entity\DateCreated;
	use Entity\DateModified;

	public string $description;
}

class Person extends Node
{
	use Entity\DateCreated;
	use Entity\DateModified;
	use Entity\Id\Uuid7;

	public private(set) Relationship\ToMany $friends;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
	) {
		$this->friends = new Relationship\ToMany(
			$this,
			FriendsWith::class,
			[
				self::class
			]
		);
	}
}
```

Now use your entities as basic objects:

```php
$matt = new Person(firstName: 'Matt');
$jill = new Person(firstName: 'Jill');

$matt->friends->set($jill, [
	'description' => 'Best friends forever!'
]);

$graph->attach($matt)->queue->merge()->run();
```

In the above example, there's no need to attach the other objects, as they will cascade.

