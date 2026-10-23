<?php

namespace Betalabs\EngineApiHandler\Tests\Fixtures;

use Betalabs\EngineApiHandler\ApiHandler\AbstractIndexHandler;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indexer with setJoins(): its filters are qualified with TABLE_NAME.
 */
class JoinedBookIndexer extends AbstractIndexHandler
{
    protected const TABLE_NAME = 'books';

    protected function buildQuery(): Builder
    {
        return $this->setJoins(Book::query()->select('books.*', 'authors.name'));
    }

    protected function setJoins(Builder $query): Builder
    {
        return $query->join('authors', 'authors.id', '=', 'books.author_id');
    }
}
