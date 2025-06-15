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
use FluidGraph\Node;
use FluidGraph\Entity

class Person extends Node
{
	use Entity\Id\Uuid7;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
	) {}
}
```

Traits are used to provide built-in common functionality and usually represent hooks.  The example above uses the `Uuid7` trait to identify the entity.  This will provide a property of `id` and automatically generate the UUID `onCreate`.

Create an Edge class:

```php
use FluidGraph\Edge;
use FluidGraph\Entity;

class FriendsWith extends Edge
{
	use Entity\DateCreated;
	use Entity\DateModified;

	public string $description;
}
```

Similar to above, the `DateCreated` hook trait adds a property of `dateCreated` set `onCreate` and the `DateModified` hook trait adds a property of `dateModified` when an edge property changes `onUpdate`.

Add a relationship between people:

```php
use FluidGraph\Node;
use FluidGraph\Like;
use FluidGraph\Entity;

use FluidGraph\Relationship\Many;
use FluidGraph\Relationship\Link;

class Person extends Node
{
	use Entity\Id\Uuid7;

    public protected(set) Many $friends;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
	) {
		$this->friendships = Many::having(
			$this,
			FriendsWith::class,
            Link::to,
            Like::any,
			[
				Person::class
			]
		);
	}
}
```

> Note: All properties on your entities **MUST** be publicly readable.  They can have `protected(set)` or `private(set)`, however, note that you **CANNOT** use property hooks.  FluidGraph avoids reflection where possible, but due to its use of per-property references, property hooks are not available.

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
$graph->attach($matt)->save();
```

> Note: There is no need to attach `$jill` or the `FriendsWith` edge, as these are cascaded from `$matt` being attached.  Without the relationship, `$jill` would need to be attached separately to persist.

Find a person:

```php
$matt = $graph->findOne(Person::class, ['firstName' => 'Matt']);
```

Get their friends:

```php
$friends = $matt->friendships->get();
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
| fastened             | The entity or element is bound to its other half, that's it. |
| inducted             | The entity or element is ready and waiting to be merged with the graph. |
| attached             | The entity or element has been merged with and is attached to the graph |
| released             | The entity or element is ready and waiting to be removed from the graph. |
| detached             | The entity or element has been merged with and is detached from the graph |

You can easily check if the status is of one or more types by passing arguments, in which case `status()` will return `TRUE` if the status is any one of the types, `FALSE` otherwise:

```php
use FluidGraph\Status;

$entity_or_element->status(Status::attached, ...)
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

#### Entity's Element is Labeled

```php
$entity->is(Person::class);
```

#### Element is Labeled

```php
$element->is(Person::class);
```

Because Entities can express the same Element without the need for polymorphism you can, for example, have a totally different Node class, such as `Author` and check whether or not they are the same as a `Person`:

```php
if ($person->is(Author::class)) {
	// Do things knowing the person is an author
}
```

### As

As mentioned before, different Entities can express the same Element.  Because Nodes can carry multiple distinct labels, this effectively means that you can transform one Node into another (adding properties and relationships) in a dynamic an horizontal fashion.

A person becomes an author:

```php
$author = $person->as(Author::class, ['penName' => 'Hairy Poster']);

$author->is(Person::class); // TRUE
$person->is(Author::class); // TRUE
```

> NOTE: The `Person` object is not changed, rather, in this example a new `Author` object is created and the person/author share the same graph Node, the same Element (in FluidGraph).  When working with a `Person` you only have access to the properties and relationships of a `Person`.  The `as()` method allows you to gracefully cast the Entity type to access other properties and relationships.

When using `as()` to create a new instance of an existing Node Element, you need to pass any required arguments for instantiation (required by it's `__construct()` method) as the second parameter.  If no properties are required, this can be excluded. If the `Author` object is already fastened to the underlying Node Element, then you can simply switch between them.

A subsequent save of the graph will persist the `Author` Label as well as the related properties to the database.

```php
$graph->save();
```

> NOTE: At present `as()` exists on Edges as well, however, edges cannot have more than one Label, so the behavior is not particularly defined.  One approach that may be taken is to allow an `$edge->as()` call to create a new type of Edge between the same source and target Nodes.  Another would be to change the type/label entirely.

### Working with Relationships

Relationships are collections of Edges.  To understand these better, we'll give a bit more definition to our `Author` class:

```php
<?php

use FluidGraph\Node;
use FluidGraph\Like;
use FluidGraph\Mode;

use FluidGraph\Relationship\Many;
use FluidGraph\Relationship\Link;

    
class Author extends Node
{
	public Many $writings;

	public function __construct(
		public string $penName
	) {
		$this->writings = Many::having(
			$this,
			Wrote::class,
            Link::to,
            Like::any,
			[
				Book::class
			],
            Mode::lazy
		);
	}
}

```

To create a new relationship you need to use the `having()` method on the appropriate class.  The class determines the behavior of the relationship (such linking to one or many Nodes, whether or not those Nodes are considered owned, as well as whether or not each Entity can have more than one Edge linking it).  Relationships also have a number of properties that describe them.  In order of arguments above:

1. A subject (the Node from which they originate, `$this` when defined)
2. A kind (the class of their Edge Entities and the label for the Edge Element)
3. A type (either `to` or `from` as expressed by the `Link` enum), this will determine the edge direction of the relationship in the graph.
4. A method (either `any` or `all` as expressed by the `Like` enum).  This will determine whether or not the related entities must express any or all of the concerns.
5. A list of concerns (the labels of the related Node Entities).
6. A mode (one of `lazy`, `eager`, or `manual` as expressed by the `Mode` enum).  This will determine how Edges and Nodes for this relationship are loaded.

In order to add this relationship, we need to define our Edge Entity `Wrote`.  Edges can have their own properties, but in this case we'll keep it simple:

```php
<?php

use FluidGraph\Edge;

class Wrote extends FluidGraph\Edge {}
```

We can add our corresponding `Book` node, as well:

```php
class Book extends FluidGraph\Node
{
	public function __construct(
		public string $name,
		public int $pages
	) { }
}
```

With these classes in place, we can now define books on our `Author`:

```php
$book = new Book(name: 'The Cave of Blunder', pages: 13527);

$author->writings->set($book);
```

If our Edge had properties, we could define those properties when we set the `$book`:

```php
$author->writings->set($book, [
    'dateStarted'  => new DateTime('September 17th, 1537'),
    'dateFinished' => new DateTime('June 1st, 1804')
]);
```

Similar to using `as()`, when we set an Entity on a given relationship, any arguments required to `__construct()` the edge would need to be passed.  You can update the existing edge using the same method.

To get Nodes out of a relationship you use the corresponding `get()` method.  For example, to get every `Book` written by an `Author`:

```php
foreach($author->writings->get(Book::class) as $book) {
    // Do things with Books
}
```

When you `unset()` on a relationship the corresponding Edge is Released (and if the relationship is an owning relationship, related Nodes can be Released automatically):

```php
$author->writings->unset($book);
```

If the Relationship is a `ToOne` the Entity argument is excluded.

> NOTE: It's possible to have multiple Edges to/from the same nodes.  While not yet supported, there would be additional methods for releasing individual edges. Which leads us to our next subject...

#### Getting Edges

Because Edges can have their own properties and/or you may need to remove a specific Edge from a relationship without destroying all relationships between two Nodes you occasionally may need to be able to obtain the edges themselves.  In our running example these would be the `Wrote` object(s).

If you need to get all Edge Entities from a `ToMany` or `FromMany` relationships you can use the `all()` method:

```php
foreach($author->writings->all() as $wrote) {
    // Do things with $wrote
}
```

> NOTE: the return value of `all()` is a `FluidGraph\Result` objects, which has additional filtering and other abilities, but generally speaking operates like an array by extending `ArrayObject`.

To get the Edge Entity from a `ToOne` or `FromOne` relationship you can use the `any()` method which will return either the Edge Entity or `NULL` if there is no relationship.

```php
if ($edge = $entity->relationship->any()) {
	// Do things with the $edge
}
```

In order to discover the Edges associated only with specific Nodes, Node Types, and Labels, you can use the `of()` and `ofAny()` methods.  Both take multiple arguments of either Node Entities, Node Elements, or strings and collected the Edges that correspond to Nodes `is()` the argument.  The only distinction is:

1. The `of()` method only returns Edges whose Node corresponds to **all** arguments.
2. The `ofAny()` method returns Edges whose Node corresponds to **any** arguments.

Accordingly, for a single argument, these methods are effectively equivalent and using multiple Node or Node Element arguments for `of()` will effectively return no results.

Finding Edges for a specific `$person`:

```php
foreach($person->friendships->of($person) as $friends_with) {
	// Working with an Edge to a specific friend
}
```

Finding Edges to all friends who are of type `Author`:

```php
foreach($person->friendships->of(Author::class) as $friends_with) {
    // Working with an Edge to a friend which is() an Author
}
```

> Note: No validation is done against the relationships concerns, because even though it will allow setting Nodes of the supported types, different Nodes Entities can share a common Node Element.

Finding Edges to all friends who are of type `Author` **and** labeled as `Archived` using `of()`:

```php
foreach($person->friendships->of(Author::class, Archivable::archived) as $friends_with) {
    // Working with an edge to friend which is() an Author AND is() 'Archived'
}
```

Using the same argument with `ofAny()` would result in finding Edges to all friends who are of type `Author` **or** labeled as `Archived`: 

```php
foreach($person->friendships->ofAny(Author::class, Archivable::archived) as $friends_with) {
    // Working with an edge to friend which is() an Author OR is() 'Archived'
}
```

### Advanced Querying

Querying in FluidGraph ultimately uses the `Where` class to construct composite callbacks which resolve to the final query.  An instance of a `Where` is generated for every `Query` and an instance of a query is generated whenever the `query` property on the `Graph` object is access.  A similar query to the one we showed at the beginning would be as follows:

```php
$id = '01976f54-66b3-7744-a593-44259dce9651';

$person = $graph->findOne(Person::class, function($eq) use ($id) {
	return $eq('id', $id);
});
```

The arguments to the callback are how you request the functions you intend to use and they correspond to the public instance methods available on the `Where` class.  In the example above, we're testing for equality, so we add `$eq` to request the `Where::eq()` method as a callback.

This method has pitfalls as it relates to code completion and typing which may be resolved at a later date by doing something like the following:

```php
use FLuidGraph\Where\Eq;

$person = $graph->findOne(Person::class, function(Eq $eq) use ($id) {
	return $eq('id', $id);
});
```

Furthermore, at present, we're adding methods as we need them. This includes methods that correspond to Memgraph MAGE functions:

```php
return $eq($upper($md5('id')), md5($id));
```

To build `AND` and `OR` conditions you can use the `$all` and `$any` callbacks respectively:

```php
$person = $graph->findOne(Person::class, function($all, $eq) {
	return $any(
        $eq('email', 'mattsah@example.com'),
    	$all(
        	$eq('firstName', 'Matthew'),
            $eq('lastName', 'Sahagian')
        ),
    );
})
```

The above conditions translate to:

```sql
WHERE c.email = 'mattsah' OR (c.firstName = 'Matthew' AND c.lastName = 'Sahagian')
```

Using `findOne` will automatically use no ordering, limit the results to `2`, skip `0`and throw an exception if more than one result is returned, hence, you should be ensuring that your queries when using it provide for uniqueness.

#### Matching Multiple Entities

If you want to find multiple nodes or edges you can simply use the `find()` method.  In addition to the where conditions you can provide `$order`, `$limit` and `$skip` parameters:

```php
$people = $graph->find(
    Person::class,
    function ($eq) {
    	return $eq('firstName', 'Matthew');
    },
    [
        'lastName' => FluidGraph\Direction::asc
    ],
    10,
    10
);
```

An alternative way of defining this would be as follows:

```php
$people = $graph->query
    ->match(Person::class)
    ->where(
        function ($eq) {
            return $eq('firstName', 'Matthew');
        }    
    )
    ->order([
        'lastName' => FluidGraph\Direction::asc
    ])
    ->limit(10)
    ->skip(10)
	->get()
;
```

### Advanced Relationships

Now that we've introduced a bit of querying, let's talk about more advanced relationships.  When creating a relationship you can specify a `FluidGraph\Relationship\Mode` of that relationship.  The `LAZY` and `EAGER` members of this enum are largely handled for you, and the only critical difference is whether or not the Edges and Nodes of that relationship are loaded immediately after the subject Node is realize or when the relationship is accessed in some way.  For finer control and large relationships, you will want to use the `MANUAL` mode.

This mode requires you to establish the various query parameters and manually load in the Edges/Nodes you're working with:

```php
$friends_named_matt = $person
    ->friendships
    ->match(Person::class)
    ->where(function($scope) {
      return $scope(FluidGraph\Scope::concern, function($eq) {
          return $eq('firstName', 'Matthew');
      });  
    })
    ->load()
    ->get()
;
```

