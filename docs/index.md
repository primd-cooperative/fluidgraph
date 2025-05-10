# FluidGraph


## Installation

```
composer require primd/fluidgraph
```

## Basic Usage

Create a new Entity class by defining its properties and relationships.  All properties on your
entities MUST be public.  These are effectivey DTOs and Proxies:

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
$person = $graph->init(new Person(firstName: 'Bob'));
```
