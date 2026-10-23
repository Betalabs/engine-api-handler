<?php

namespace Betalabs\EngineApiHandler\Tests;

use Betalabs\EngineApiHandler\ApiHandler\ApiHandler;
use Betalabs\EngineApiHandler\ApiHandler\ModelCountMetaProvider;
use Betalabs\EngineApiHandler\Tests\Fixtures\Book;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Config;

class IndexHandlerTest extends TestCase
{
    public function testListsWithJoinAndCountHeaders()
    {
        $response = $this->getJson('/books?_sort=id');

        $response->assertOk();
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], array_column($response->json(), 'title'));
        $this->assertSame(['Ana', 'Bruno', 'Ana'], array_column($response->json(), 'name'));
        $this->assertSame('3', $response->headers->get('Meta-Total-Count'));
        $this->assertSame('3', $response->headers->get('Meta-Filter-Count'));
        // Base-Model-Total counts the base model without the joins
        $this->assertSame('4', $response->headers->get('Base-Model-Total'));
    }

    public function testFilterSortAndPagination()
    {
        $this->assertSame(['Bravo'], array_column($this->getJson('/books?title=Bravo')->json(), 'title'));
        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], array_column($this->getJson('/books?_sort=-pages')->json(), 'title'));
        $this->assertSame(['Alpha', 'Charlie'], array_column($this->getJson('/books?pages-max=200&_sort=id')->json(), 'title'));

        $page = $this->getJson('/books?_sort=id&_limit=1&_offset=1');
        $this->assertSame(['Bravo'], array_column($page->json(), 'title'));
        $this->assertSame('3', $page->headers->get('Meta-Filter-Count'));
    }

    public function testSortByRelationField()
    {
        $response = $this->getJson('/books?_fields=id,title,author->name&_sort=-authors->name');

        $response->assertOk();
        $this->assertSame('Bravo', $response->json()[0]['title']);
        $this->assertSame(['Bruno', 'Ana', 'Ana'], array_column($response->json(), 'name'));

        $ascending = $this->getJson('/books?_fields=id,title,author->name&_sort=authors->name');
        $this->assertSame('Bravo', $ascending->json()[2]['title']);
    }

    public function testRelationFiltersWithAndOrApproach()
    {
        $and = $this->getJson('/books/relation-filtered?_fields=id,title,author->name&author->name=Ana&_filter-approach=and&_sort=id');
        $this->assertSame(['Alpha', 'Charlie'], array_column($and->json(), 'title'));

        $or = $this->getJson('/books/relation-filtered?_fields=id,title,author->name&author->name=Ana&_filter-approach=or&_sort=id');
        $this->assertSame(['Alpha', 'Charlie'], array_column($or->json(), 'title'));

        $like = $this->getJson('/books/relation-filtered?_fields=id,title,author->name&author->name-lk=*run*&_filter-approach=and&_sort=id');
        $this->assertSame(['Bravo'], array_column($like->json(), 'title'));
    }

    public function testRelationFiltersAreIgnoredWithoutApproach()
    {
        $response = $this->getJson('/books/relation-filtered?_fields=id,title,author->name&author->name=Ana&_sort=id');

        $this->assertSame(['Alpha', 'Bravo', 'Charlie', 'Hidden'], array_column($response->json(), 'title'));
    }

    public function testBaseModelCountDropsJoinAndOrderBindings()
    {
        // The count removes the joins/orders, so their bindings must go too:
        // MySQL rejects extra bindings (SQLite silently ignores them)
        $builder = Book::query()
            ->select('books.*')
            ->join('authors', function (JoinClause $join) {
                $join->on('authors.id', '=', 'books.author_id');
                $join->where('authors.active', true);
            })
            ->where('pages', '>=', 100)
            ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', ['Charlie']);

        $provider = (new ModelCountMetaProvider('Base-Model-Total'))->setBuilder($builder);
        $this->assertSame(3, $provider->get());

        $countQuery = (new \ReflectionProperty(ModelCountMetaProvider::class, 'builder'))->getValue($provider)->getQuery();
        $this->assertSame(substr_count($countQuery->toSql(), '?'), count($countQuery->getBindings()));
        $this->assertSame([100], $countQuery->getBindings());
    }

    public function testParseMultipleDefaultsToTheRequestParams()
    {
        $this->app['request']->query->replace(['title' => 'Charlie']);

        $titles = (new ApiHandler())->parseMultiple(Book::query())->getResult()->pluck('title')->all();

        $this->assertSame(['Charlie'], $titles);
    }

    public function testParseSingleDefaultsToTheRequestParams()
    {
        $this->app['request']->query->replace(['_fields' => 'id,title']);

        $book = (new ApiHandler())->parseSingle(Book::query(), 2)->getResult();

        $this->assertSame(['id', 'title'], array_keys($book->getAttributes()));
        $this->assertSame('Bravo', $book->title);
    }

    public function testEnvelopeMetaIsRenamedToMetaTotal()
    {
        Config::set('apihandler.envelope', true);

        $response = $this->getJson('/books?_sort=id&_config=meta-total');

        $response->assertOk();
        // Each count overwrites the previous one: the last is Base-Model-Total
        $this->assertSame(['meta-total' => 4], $response->json('meta'));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], array_column($response->json('data'), 'title'));
    }

    public function testIndexerWithJoinsQualifiesTheFilters()
    {
        // "id" alone would be ambiguous between books and authors
        $response = $this->getJson('/books/joined?id=2');

        $response->assertOk();
        $this->assertSame([['Bravo', 'Bruno']], array_map(fn ($b) => [$b['title'], $b['name']], $response->json()));
    }

    public function testOrApproachWithSeveralRelationFields()
    {
        $response = $this->getJson('/books/relation-filtered?_fields=id,title,author->name,author->id&author->name=Ana&author->id=2&_filter-approach=or&_sort=id');

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], array_column($response->json(), 'title'));
    }
}
