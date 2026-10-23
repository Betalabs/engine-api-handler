<?php

namespace Betalabs\EngineApiHandler\Tests\Fixtures;

use Betalabs\EngineApiHandler\ApiHandler\AbstractIndexHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Same shape as the apps' indexers: a join with a bound condition (e.g. the
 * tenant), which is what broke the count query.
 */
class BookIndexer extends AbstractIndexHandler
{
    protected function buildQuery(): Builder
    {
        return Book::query()
            ->select('books.*', 'authors.name')
            ->join('authors', function (JoinClause $join) {
                $join->on('authors.id', '=', 'books.author_id');
                $join->where('authors.active', true);
            });
    }
}
