# FluidGraph

FluidGraph is an Object Graph Manager (OGM) for memgraph (though in principle could also work with Neo4j).  It borrows a lot of concepts from Doctrine (a PHP ORM for relational databases), but aims to "rethink" many of those concepts in the graph paradigm.

This project is part of the Primd application stack and is copyright Primd Cooperative, licensed MIT. Primd Cooperative is a worker-owned start-up aiming to revolutionize hiring, learning, and work itself. For more information, or to support our work:

- See what we do: https://primd.app
- Become a Patreon Member: https://patreon.com/primd
- Star and Share this Repository

## Installation

```
composer require primd/fluidgraph
```

## Basic Concepts

FluidGraph borrows a bit of a "spiritual ontology" in order to talk about its core concepts.  The **Entity** world is effectively the natural world.  These are your concrete models and the things you interact with.

The **Element** world is the spiritual world.  Entities are fastened to an Element.  A single Element can be expressed in one or more Entities.  These are the underlying graph data and not generally meant to be interacted with (unless you're doing more advanced development).

Both Entities and Elements have different forms, namely Nodes and Edges, i.e. there is a "Node Element" as well as a "Node Entity."

> NOTE: FluidGraph is still **alpha** and is subject to fairly rapid changes, though we'll try not to break documented APIs.

## Basic Usage

Instantiating a graph:

```php
use Bolt\Bolt;
use Bolt\connection\StreamSocket;

$graph = new FluidGraph\Graph(
    [
        'scheme'      => 'basic',
        'principal'   => 'memgraph',
        'credentials' => 'password'
    ],
    new Bolt(new StreamSocket())
);
```

Create a Node class:

```php
class Person extends FluidGraph\Node
{
	use FluidGraph\Entity\Id\Uuid7;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
	) {}
}
```

Traits are used to provide built-in common functionality and usually represent hooks.  The example above uses the `Uuid7` trait to identify the entity.  This will provide a key of `id` and automatically generate the UUID `onCreate`.

Create an Edge class:

```php
class FriendsWith extends FluidGraph\Edge
{
	use FluidGraph\Entity\DateCreated;
	use FluidGraph\Entity\DateModified;

	public string $description;
}
```

Add a relationship between people:

```php
class Person extends FluidGraph\Node
{
	use FluidGraph\Entity\Id\Uuid7;

    public protected(set) FluidGraph\Relationship\ToMany $friends;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
	) {
		$this->friendships = new FluidGraph\Relationship\ToMany(
			$this,
			FriendsWith::class,
			[
				self::class
			]
		);
	}
}
```

> Note: All properties on your entities MUST be publicly readable.  They can have `protected(set)` or `private(set)`, however, note that you CANNOT use property hooks.  FluidGraph avoids reflection where possible, but due to how it uses per-property references, hooks are not viable.

Instantiate nodes:

```php
$matt = new Person(firstName: 'Matt');
$jill = new Person(firstName: 'Jill');
```

Set the relationship between them:

```php
$matt->friendships->set($jill, [
	'description' => 'Best friends forever!'
]);
```

Attach, merge changes into the queue, and execute it:

```php
$graph->attach($matt)->queue->merge()->run();
```

> Note: There is no need to attach `$jill` or the `FriendsWith` edge, as these are cascaded from `$matt` being attached.  Without the relationship, `$jill` would need to be attached separately to persist.

Find a person:

```php
$matt = $graph->query->matchOne(Person::class, ['firstName' => 'Matt']);
```

Get their friends:

```php
$friends = $matt->friendships->get(Person::class);
```

Get the friendships (The actual `FriendsWith` edges):

```php
$friendships = $matt->friendships->all();
```

>  Note: The available methods and return values depend on the relationship type.  A `ToMany` has `all()` while a `ToOne` has `any()` for example.

### Working with Entities and Elements

#### Status

To determine the status of an Entity or Element you can use the `status()` method which, with no arguments will return a `FluidGraph\Status` or `NULL` if somehow an Entity has not been fastened.

```php
$entity_or_element->status()
```

Status types:

| FluidGraph\Status::* | Description                                                  |
| -------------------- | ------------------------------------------------------------ |
| FASTENED             | The entity or element is bound to its other half, that's it. |
| INDUCTED             | The entity or element is ready and waiting to be merged with the graph. |
| ATTACHED             | The entity or element has been merged with and is attached to the graph |
| RELEASED             | The entity or element is ready and waiting to be removed from the graph. |
| DETACHED             | The entity or element has been merged with and is detached from the graph |

You can easily check if the status is of one or more types by passing arguments, in which case `status()` will return `TRUE` if the status is any one of the types, `FALSE` otherwise:

```php
$entity_or_element->status(FluidGraph\Status::ATTACHED, ...)
```

### Is

Determine whether or not an Entity or Element is the same as another-ish:

```php
$entity_or_element->is($entity_or_element_or_class);
```

This returns `TRUE` in given the following modes and outcomes:

#### Entities share the same Element

```php
$entity->is($entity);
```

#### Entity expresses a given Element

```php
$entity->is($element);
```

#### Element is the same as another Element

```php
$element->is($element);
```

#### Entity's Element is Labeled as a Class

```php
$entity->is(Person::class);
```

#### Element is Labeled as a Class

```php
$element->is(Person::class);
```

Because Entities can express the same Element without the need for polymorphism you can, for example, have a totally different Node class, such as `Author` and check whether or not they are the same as a `Person`:

```php
if ($person->is(Author::class)) {
	// Do things knowing the person is an author
}
```

##### Like

Available **for Nodes only** (as they can have more than one label), is the `like()` and `likeAny()` methods which will observe both classes as well as arbitrary labels that may be common:

```php
$entity->like(Person::class, Archivable::ARCHIVED);
```

It is strongly recommended that you use constants for labels.  How or where you implement them depends on how they are shared across Nodes.  In the example above we have a separate `Archivable` Trait which could be used by various classes.

### As

As mentioned before, different Entities can express the same Element.  This effectively means that you can transform one Entity into another (adding properties and relationships) in a dynamic an horizontal fashion.

A person becomes an author:

```php
$book = new Book(name: 'FluidGraph for Fun and Profit');

$person->as(Author::class, ['penName' => 'Hairy Poster'])->writings->set($book);
```

> NOTE: The `Person` object is not changed, rather, in this example a new `Author` object is created and the person/author share the same graph Node, the same Element (in FluidGraph).  When working with a `Person` you only have access to the properties and relationships of a `Person`.  The `as()` method allows you to gracefully change the Entity type.

When using `as()` to create a new Entity expression of an existing Entity/Element, you need to pass any required arguments for instantiations (required by it's `__construct()` method) as the second parameter.  If no properties are required, this can be excluded. If the `Author` object is already fastened to the underlying Element, then you can simply switch between them:

```php
if ($person->is(Author::class)) {
    $author = $person->as(Author::class);
    
    foreach ($author->writings->get(Book::class) as $book) {
        // Do things with their books
    }
}
```



