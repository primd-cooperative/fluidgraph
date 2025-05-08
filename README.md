# FluidGraph

FluidGraph is an Object Graph Manager (OGM) for memgraph (though in principle should also work with
Neo4j).  It borrows a lot of concepts from Doctrine (a PHP ORM for relational databases), but aims
to "rethink" many of those concepts in the graph paradigm.

This project is part of the Primd application stack and is copyright Primd Cooperative. Primd
Cooperative is a worker-owned start-up aiming to revolutionize hiring, learning, and work itself.

For more information, or to support our work see:

- https://primd.app
- https://patreon.com/primd

## Usage and Documentation

FluidGraph is still **pre-alpha** software, many features are incomplete and documentation is
effectively non-existent.  As an open-source driven startup, we want to allow people to learn and
use our work at the earliest opportunity.  We believe, at this stage, the basic skeleton of what
we're doing is in place enough for the curious observer to dig in and see how things connect
together.

If you're waiting for a ready made solution, star and follow.  We'll get there!

## Why?

Primd uses PHP for effectively all "front-end" code.  In this context "front-end" refers to
rendering back-end data to users.  User interactions are still handled by JavaScript, which may or
may not include requests that re-render server-side.  This is accomplished through HTMX and
AlpineJS.

Primd uses Memgraph for handling complex data relationships which often need to be presented to or
modified by users.

While there are existing solutions modeled after Doctrine, we also encountered the following:

1. Many solutions are much older and unmaintained.
2. Those soltuions are **too** specific to Doctrine, not accounting for differences between graph
vs. traditional relational databases.
3. Those solutions are specific to Neo4j.

In practice, the Bolt protocol and Cypher are common "standards" for working with graph databases.
We opted to use the excellent work of Michal Štefaňák (https://github.com/stefanak-michal) to try
and make something that was a bit more flexible.

## Key Features

FluidGraph maintains something of a Data Mapper pattern in that entities (nodes/edges) are not
strongly coupled to the graph.  In fact, nodes and edges are _basically_ DTOs with a managed
reference.  This is achieved through a few different ways.

### Property Mapping

Many people familiar with Doctrine or Data Mapper patterns more generally may be familiar with the
concept of an Identity Map.  Basically, what this means is that each entity gets indexed by its
identity and if you query an entity with the same identity, you receive the same object in return.

FluidGraph takes this concept a bit further.  The graph representation maintains an identity map,
however an individual entity (object representation) also maintains mapped properties.  In practice
this means that two entities representing the same graph node will share properties.  This is
important because "labels" act as both tags and interfaces (of sorts).  So you can imagine that a
"Person" is also "Locatable" (i.e. has a canonical location), while a Business/Employer has very
different properties, it also shares this concept of being "Locatable."

Both a "Person" and a "Business" may be represented as a "Locatable" entity.  If the "Locatable"
expression of either is updated, any entity representing the same node as either a "Person" or a
"Business" should also see those changes.  Hence, the Node "content" is held distinct from the
entity, and the entity simply maps its properties to the "content."

### Relationships (Distinct from Edges)

A relationship in FluidGraph is a collection of one or more edges.  Relationships are not simply
defined by the edges they contain, but are also defined by their behavior.  Relationships may have
different traits which augment how the edges and the target nodes are treated.  For example, an
"owning" relationship may say that if the source is deleted, all related targets should also be
deleted.  The edge is merely the connection.

Similarly, it's possible to have the same relationship to a variety of different nodes.  For
example, a person may have many suggestions.  In the context of Primd someone could be suggested
a Capacity (Skill or Ability), but they may also be suggested a training Provider, or a Person as
Colleague, or an Institution as an Educator, etc.  In this context, the realtionship is primary and
the target node type is secondary.  For this reason, relationships can allow any number of target
node types (kinds).



