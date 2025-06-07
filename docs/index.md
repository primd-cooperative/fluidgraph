# FluidGraph


## Installation

```
composer require primd/fluidgraph
```

## Basic Concepts

FluidGraph borrows something like a "spiritual ontology" in order to talk about its core
concepts.  The "Entity world" is effectively the natural world.  The "Element world" is the
spiritual world.  Entities are fastened to an Element.  Both Entities and Elements have different
forms, namely Nodes and Edges.

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

	public protected(set) Relationship\ToMany $friends;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
	) {
		$this->friendships = new Relationship\ToMany(
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

$matt->friendships->set($jill, [
	'description' => 'Best friends forever!'
]);

$graph->attach($matt)->queue->merge()->run();
```

In the above example, there's no need to attach the other objects, as they will cascade.

### Status

Determine the status of an entity or element (returns one of `FluidGraph\Status::*` or `NULL` if
used on an entity and the entity has yet to be fastened to an element somehow):

```php
$entity_or_element->status()
```

Determine if the status is a particular status (return `TRUE` if the entity's status matches the
argument):

```php
$entity_or_element->status(FluidGraph\Status::ATTACHED)
```

Determine if the status is one of any type (return `TRUE` if the entity's status matches any one
of the arguments):

```php
$entity_or_element->status(FluidGraph\Status::ATTACHED, FluidGraph\Status::INDUCTED);
```

The statuses are as follows:

|Status::... |Description |
|-|-|
|FASTENED|The entity or element is bound to its other half, that's it.
|INDUCTED|The entity or element is ready and waiting to be attached to the graph.
|ATTACHED|The entity or element has been and is attached to the graph
|RELEASED|The entity or element is ready and waiting to be detached from the graph.
|DETACHED|The entity or element has been and is detached to teh graph

### Is

Determine whether or not an entity or element is of a certian essence.  An entity or element is of
a certain essence if:

1. Two given entities have the same element.
2. An entity has the given element.
3. Two given elements are the same.
4. A given element or entity's element has the corresponding class as a label.

```php
$entity_or_element->is($entity_or_element_or_class);
```

In the example below, we can determine if a Person entity is the same as an Author entity (in the
graph).  Note, this does not require polymorphism or for the objects to actually be the same object
because the comparison is done at the element level.

```php
$person->is($author);
```

We can also check if a person is an author:

```php
if ($person->is(Author::class)) {
	// Do things knowing the person is an author
}
```

### As

You can get an entity or an element as another entity type.  Using our previous example, if we want
to get the person `as` and author.  Again, note that this does not require polymorphism, allowing
for horizontal "transformation" of entities from one form to another.  Conflicting properties (same
name, different types) may result in odd behavior.

```php
if ($person->is(Author::class)) {
	$author = $person->as(Author::class);
}
```
