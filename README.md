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

## Installation

Install the package via composer

```bash
$ composer require betalabs/engine-api-handler
```
    
