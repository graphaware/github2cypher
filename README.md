## Github Events To Cypher

This library leverages the [GithubEvent](https://github.com/ikwattro/github-event) library and convert github event objects
to Neo4j Cypher Queries.

## Requirements

* PHP 5.4+

## Installation

Add this library to your project dependencies :

```bash
composer require ikwattro/github2cypher
```

## Usage

The usage of the library is straightforward, instantiate the `Github2CypherConverter` class and pass your **GithubEvent** objects
to the convert method :

```php

use Ikwattro\Github2Cypher\Github2CypherConverter;

$converter = new Github2CypherConverter();

// Assuming you have a collection of GithubEvent objects

foreach ($events as $event) {
    $statements = $converter->convert($event);
    // Returns a collection of statements of the form ['query' => 'MATCH xxx...', 'params' => ['p1' => 'v1', 'p2' => 'v2', ..]
}
```

You can then use [NeoClient](https://github.com/neoxygen/neo4j-neoclient) to import the queries into [Neo4j](http://neo4j.com).

##  Indexes and Constraints

If you plan to import the queries into Neo4j (which is the goal), you can create the needed indexes and unique constraints easily.

These two methods returns you an array of indexes or constraints to create on labels/properties :

* `getInitialSchemaIndexes();`
* `getInitialSchemaConstraints();`


#### Author

[Christophe Willemsen](https://twitter.com/ikwattro) 

#### License

Released under the MIT License. Please read the LICENSE file attached with this package.