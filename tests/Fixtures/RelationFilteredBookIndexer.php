<?php

namespace Betalabs\EngineApiHandler\Tests\Fixtures;

use Betalabs\EngineApiHandler\ApiHandler\AbstractIndexHandler;
use Illuminate\Database\Eloquent\Builder;

/**
 * Indexer that enables the relation filters ("_filter-approach").
 */
class RelationFilteredBookIndexer extends AbstractIndexHandler
{
    protected function buildQuery(): Builder
    {
        return $this->filterQuery(Book::query());
    }
}
