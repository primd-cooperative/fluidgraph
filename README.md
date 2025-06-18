# FluidGraph

FluidGraph is an Object Graph Manager (OGM) for memgraph (though in principle could also work with Neo4j with a bit of modification).  It borrows some concepts from Doctrine (a PHP ORM for relational databases), but aims to "rethink" many of those concepts in the graph paradigm.

This project is part of the Primd application stack and is copyright Primd Cooperative, licensed MIT. Primd Cooperative is a worker-owned start-up aiming to revolutionize hiring, learning, and work itself. For more information, or to support our work:

- See what we do: https://primd.app
- Become a Patreon Member: https://patreon.com/primd
- Star and Share this Repository

## Installation

```
composer require primd/fluidgraph
```

## Basic Concepts

FluidGraph employs a bit of a "spiritual ontology" in order to talk about its core concepts.  The **Entity** world is effectively the natural world.  These are your concrete models and the things you interact with.

The **Element** world is the supernatural world.  Entities are the embodiment of an Element.  A single Element can be "fastened" to more than one Entity, such that a single graph node might be expressed as multiple different object classes.  Each possible expression constitutes a label (although you can add arbitrary labels as well).  The underlying elements are not generally meant to be interacted with (unless you're doing more advanced development).

Throughout this documentation we may refer to Nodes (generally) or use terms like "Node Entity" as opposed to "Node Element."

> NOTE: FluidGraph is still **beta**.  Not all 1.0 features are implemented, but the API is considered relatively stable.

## Basic Usage

### Instantiating the Graph

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

### Creating a Node Entity

All properties on your entities **MUST** be publicly readable.  They can have `protected(set)` or `private(set)`, however, note that you **CANNOT** use property hooks due to FluidGraph's use of per-property references.

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

Traits like `FluidGraph\Entity\Id\Uuid7` are used to provide built-in common functionality and usually represent hooks.  The example above uses the `Uuid7` trait provides an `id` property on the Node Entity and automatically generate the value `onCreate`.

### Creating an Edge Entity

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

### Adding Relationships

To create a new relationship you need add the relationship as a property on your node and instantiate it on `__construct()` using  the `having()` method on the appropriate class.  The class determines the behavior of the relationship.  Currently supported classes include:

| FluidGraph\Relationship\* | Description                                                  |
| ------------------------- | ------------------------------------------------------------ |
| Many                      | A relationship from the subject to many Nodes, via single Edge |
| One                       | A relationship from the subject to One Node, via a single Edge |
| OwnedMany                 | Same as Many, but related Nodes are removed if the subject is removed |
| OwnedOne                  | Same as One, but the related Node is remoed if the subject is removed |

We can return to our `Person` entity and add a few lines (note the used namespaces).

```php
use FluidGraph\Node;
use FluidGraph\Entity;
use FluidGraph\Matching;
use FluidGraph\Reference;

use FluidGraph\Relationship\Many;

class Person extends Node
{
	use Entity\Id\Uuid7;

	// ADDED:
	public protected(set) Many $friendships;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
	) {
		// ADDED:
		$this->friendships = Many::having(
			$this,
			FriendsWith::class,
			Reference::to,
			Matching::any,
			[
				Person::class
			]
		);
	}
}
```

The full list of arguments for the `having()` method are as follows:

| Argument (In Order) | Description                                                  |
| ------------------- | ------------------------------------------------------------ |
| subject             | The subject of the relationship, always `$this`              |
| kind                | The Edge Entity class used for linking to the Nodes of concern |
| type                | The `Reference` direction of the links, `to` and `from` are currently supported. |
| rule                | How Nodes are matched against the concerns, `any` and `all` are currently supported. |
| concerns            | The classes/labels of the Nodes linked by the relationship.  Depending on the rule they will either need to have `any` or `all` classes/labels included in the array. |
| mode                | How the Edge Entities and Node Entities of the relationship are loaded, `lazy`, `eager`, and `manual` are currently supported. |

### Using Nodes and Relationships

You instantiate your nodes as you would any basic object.  Required arguments obviously depends on how you have defined your properties and your `__construct()` method, although we generally recommend getting in the habit of using named parameters here:

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

The `array` passed as the second argument to `set()` provides requisite construction parameters.  You can also add non-construction parameters to assign to the Edge Entity.  If the Node Entity passed as the first argument is already part of the relationship, the linking Edge Entities will be updated with values passed in the second argument.

You can get the Node Entities of a relationship back using the `get()` method:

```php
foreach ($matt->friendships->get(Person::class) as $person) {
	echo 'Hi' . $person->firstName . PHP_EOL;
}
```

When you `unset()` on a relationship the corresponding Edge is Released (and if the relationship is an owning relationship, related Nodes can be Released automatically):

```php
$matt->friendships->unset($jill);
```

If the Relationship is a `FluidGraph\Relationship\One` the Entity argument can be excluded, if not it only unsets if the entity matches.

> NOTE: It's possible to have multiple Edges to/from the same nodes.  While not yet supported, there would be additional methods for releasing individual edges. Which leads us to our next subject...

### Persisting Changes

In order to persist our changes we need to `attach()` entities to the graph and `save()` it.

```php
$graph->attach($matt)->save();
```

The corresponding Edge Entities and related Node Entities will have their persistence cascaded automatically.  If there is no relationship between `$matt` and `$jill`, then `$jill` would need to be attached separately in order to be persisted.

### Simple Querying

#### Find A Single Record of Type

A contrived example that works only because we only have a single person named "Matt."  Traditionally you'd want to use the `id` or some set of properties that provide uniqueness.  The `findOne()` method **WILL** throw an exception if the provided terms result in more than a single match.

```php
$matt = $graph->findOne(Person::class, [
	'firstName' => 'Matt'
]);
```

#### Find All Records of Type

```php
use FluidGraph\Order;
use FluidGraph\Direction;

$people = $graph->findAll(Person::class, [
	Order::by(Direction::asc, 'lastName')
]);
```

### Working with Entities and Elements

#### Identity

To get the graph identy of a Node or Edge you can use the `identity()` method.  Identities should not be used to compare nodes or entities for a few reasons:

- In Memgraph, the identities of Nodes and Edges can overlap.  That is, while no Node will have the same identity as another Node, it can have the same identity as an Edge and vice versa.
- If the Node or Edge is not yet persisted, then `identity()` will return `NULL` rendering two separate non-persisted nodes equal if compared via `identity()`.

More often than not, `identity()` is a quick way to check if an Entity or Element has been persisted.  It can also be used as a fast and indexable key in related databases that store additional data related to the entity / element.  In this hypothetical example, we might use Doctrine replositories to store notifications for people in our graph:

```php
foreach ($notifications->findByPerson($person->identity()) as $notification) {
	// Do things with notifications
}
```

#### Assign

You can bulk assign data to Entities and Elements using the `assign()` method.  Assigning to Elements in this fashion is not recommended, as there is no way to validate the properties being set.  By contrast, when assigning to an Entity, the keys of the array are validated against the Entity's known properties:

```php
$entity_or_element->assign([
	// Will work on both
	'validProperty' => 10,

	// Only works on Elements
	'invalidProperty' => 10
])
```

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

#### Is

Determine whether or not an Entity or Element is the same-ish as another:

```php
$entity_or_element->is($entity_or_element_or_label);
```

This returns `TRUE` in given the following modes and outcomes:

##### Entities share the same Element

```php
$entity->is($entity);
```

##### Entity expresses a given Element

```php
$entity->is($element);
```

##### Element is the same as another Element

```php
$element->is($element);
```

##### Entity's Element is Labeled

```php
$entity->is(Person::class);
```

##### Element is Labeled

```php
$element->is(Person::class);
```

Because Entities can express the same Element without the need for polymorphism you can, for example, have a totally different Node class, such as `Author` and check whether or not they are the same as a `Person`:

```php
if ($person->is(Author::class)) {
	// Do things knowing the person is an author
}
```

#### Of and OfAny

Similar and related to `is()` the `of()` and `ofAny()` methods check whether or not an Entity or Element is the same as a number of arguments.

Using `of()` will return `TRUE` if the Entity or Element `is()` **ALL** of the arguments passed:

```php
$entity_or_element->of(Author::class, Archivable::Archived)
```

Using `ofAny()` will return `TRUE` if the Entity or Element `is()` **ANY** of the arguments passed.  In this example, we check, essentially, if it is in an array of other Nodes:

```php
$entity_or_element->ofAny(...$nodes)
```

### As

As mentioned earlier, different Entities can express the same Element.  Because Nodes can carry multiple distinct labels, this effectively means that you can transform one Node into another (adding properties and relationships) in a dynamic an horizontal fashion.

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

### Advanced Relationships

We already covered the most basic use and working with relationships.  However, relationships as you can probably guess are a very powerful feature of FluidGraph.

Different relationship classes can have slightly different methods and variations depending on their nature.  For example, using the aforementioned `get()` method on a `Many` relationship will, as noted, provide an iterable result set.  On a `One` relationship, however, the `get()` method returns a Node directly, or `NULL` if no matching Node is related.

Similar to this is working with the Edges themselves.  If you need to get all Edge Entities from a `Many` relationships you can use the `all()` method:

```php
foreach($author->writings->all() as $wrote) {
	// Do things with $wrote
}
```

To get the Edge Entity from a `One` relationship you can use the `any()` method which will return either the Edge Entity or `NULL` if there is no related Node and, therefore, corresponding Edge:

```php
if ($edge = $entity->relationship->any()) {
	// Do things with the $edge
}
```

In order to discover the Edges associated only with specific Nodes, Node Types, and Labels, you can use the `for()` and `forAny()` methods on relationships.  These work by filtering based on the return results of the aforementioned `of()` and `ofAny()` methods on the Nodes in the relationship:

Finding Edges for a specific `$person`:

```php
foreach($person->friendships->for($person) as $friends_with) {
	// Working with an Edge to a specific friend
}
```

Finding Edges for a number of people:

```php
foreach($person->friendships->forAny(...$people) as $friends_with) {

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
use FluidGraph\Where\Eq;

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

If you want to find multiple nodes or edges you can simply use the `find()` method.  In addition to the where conditions you can provide `$orders`, `$limit` and `$skip` parameters:

```php
use FluidGraph\Order;
use FluidGraph\Direction;

$people = $graph->find(
	Person::class,
	10,
	10,
	function ($eq) {
		return $eq('firstName', 'Matthew');
	},
	[
		Order::by(Direction::asc, 'lastName')
	]
);
```

An alternative way of defining this would be as follows:

```php
use FluidGraph\Order;
use FluidGraph\Direction;

$people = $graph->query
	->match(Person::class)
	->take(10)
	->skip(10)
	->where(
		function ($eq) {
			return $eq('firstName', 'Matt');
		}
	)
	->sort(
		Order::by(Direction::asc, 'lastName')
	])
	->get()
;
```

### Manual Relationships

Now that we've introduced a bit of querying, let's talk about more manual relationships.  When creating a relationship you can specify a `FluidGraph\Relationship\Mode` of that relationship.  The `lazy` and `eager` members of this enum are largely handled for you, and the only difference between the two is whether or not the Edges and Nodes of that relationship are loaded immediately after the subject Node is realize or when the relationship is accessed in some way.

For finer control and large relationships, you will want to use the `manual` mode.  This mode requires you to establish the various query parameters and manually load in the Edges/Nodes you're working with:

```php
$person->friendships->take(10)->load()
```

The above will load only the first `10` relationships.  From there, you can work with them as you normally would.

Manual relationships are commonly used for very large relationship sets that may be revealed on something like an infinite scrolling page with subsequent requests getting a limited number at different offsets.  Because of this, it's also very common that you want consistent types of related nodes.

If your relationship allows uses `Matching::any` with a number of different Node Entity classes as concerns, you may want to limit the loading to only a specific type:

```php
$person->friendships->take(10)->skip($offset)->load(Person::class)
```

##### Forking Relationships

An alternative approach is the fork the relationship.  A forked relationship basically enables you to create an isolated clone of the relationship with its own records.  To do this you can use the `find()` method just as you would on the Graph to get specific nodes instead:

```php
$friends = $person->friendships->find(Person::class, 10);
```

It is, however, extremely **IMPORTANT** to note that you fork a relationship this has two **MAJOR** implications:

1. Any use of the `set()` or `unset()` methods will not result in changes being persisted unless you merge the fork back into the apex relationship using `merge()`
2. Any Edge Entities or the related Nodes will not be available on the apex relationship, again, unless the fork has been merged.

For example, although we can get all of our `Person` friends above for use, the following would not work as anticipated:

```php
$friends = $person->friendships->find(Person::class, 10);

foreach ($friends as $friend) {
	if ($friend->is(Archivable::archived)) {
		$friends->unset($friend);
	}
}

$graph->save();
```

Neither would something like:

```php
$person->friendships->find(Person::class, 10);

foreach ($person->friendships->get(Person::class) as $person) {
	// Do things with friendly person
}
```

To merge a relationship fork back into the apex relationship using our first example:

```php
$friends = $person->friendships->find(Person::class, 10);

foreach ($friends as $friend) {
	if ($friend->is(Archivable::archived)) {
		$friends->unset($friend);
	}
}

// ADDED:
$friends->merge();

$graph->save();
```

The expanded form of forked relationships uses the full `match()` and `matchAny()` style that is common to Queries:

```php
$friends = $person->friendships
	->match(Person::class)
	->take(10)
	->skip(10)
	->where(
		function ($eq) {
			return $eq('firstName', 'Matt');
		}
	)
	->sort(
		Order::by(Direction::asc, 'lastName')
	])
	->get()
```



