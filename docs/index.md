# FluidGraph


## Installation

```
composer require primd/fluidgraph
```

## Basic Usage

Create a new Entity class by defining its properties and relationships:

```php
use FluidGraph\Node;
use Ramsey\Uuid\Uuid;

class Person extends Node
{
	public DateTime $dateCreated;

	public string $id;

	public function __construct(
		public ?string $firstName = NULL,
		public ?string $lastName = NULL,
		public OwnedCluster $suggestions = new OwnedCluster(
			Suggested::class,
			Mode::LAZY,
			[Claim::class]
		)
	) {
		$this->id = Uuid::uuid7();
		$this->dateCreated = new DateTime();
	}
}
```

You can initialize an entity using its native class constructor and received the fastened instance
back.  The fastened instance is backed by a `Content\Node` or `Content\Edge` depending on its type
and its relationships are linked to the graph instance.

```php
$person = $graph->init(new Person(firstName = 'Bob'));
```
