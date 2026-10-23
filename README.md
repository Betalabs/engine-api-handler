# engine-api-handler

<p>
    This package allows the search and filtering of related tables data
    using Marcel Gwerder's API handler. It only requires that you extend the class 
    AbstractIndexHandler, implementing the method buildQuery(), which must return Laravel's
    \Illuminate\Database\Eloquent\Builder object. Then, to return the query results, you must call
    the method execute() of that class.
</p>

<p>
    For the correct functioning of the package, you must separate the related tables using "->" in the URL, instead of 
    ".". You must also name the Eloquent methods of each related table using the exact same name of that table in the Database.
</p>
<p>
    You also have to put the file apihandler.php inside the config folder of your laravel project.
</p>

## Requirements

| | Version |
|---|---|
| PHP | 8.2 to 8.5 |
| Laravel | 10, 11 or 12 |
| betalabs/laravel-api-handler | 5.x |

Projects on Laravel 9 or older (or PHP below 8.2) must stay on version `4.x`.

Relation filters (`_filter-approach=and|or` with `relation->column` filters) are only applied when the indexer calls `$this->filterQuery($query)` inside `buildQuery()`.

## Installation

Install the package via composer

```bash
$ composer require betalabs/engine-api-handler
```
    

## Tests

```bash
composer install
vendor/bin/phpunit
```

The tests use Orchestra Testbench with an in-memory SQLite database.
