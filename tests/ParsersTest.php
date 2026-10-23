<?php

namespace Betalabs\EngineApiHandler\Tests;

use App\Http\Requests\ExtraFieldStructure;
use App\Http\Requests\FieldStructure;
use Betalabs\EngineApiHandler\ApiHandler\ApiHandler;
use Betalabs\EngineApiHandler\ApiHandler\ModelCountMetaProvider;
use Betalabs\EngineApiHandler\ApiHandler\Parser;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\Field;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\Filter;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\Join;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\Sort;
use Betalabs\EngineApiHandler\ApiHandler\Parsers\With;
use Betalabs\EngineApiHandler\Tests\Fixtures\Author;
use Betalabs\EngineApiHandler\Tests\Fixtures\Book;
use Betalabs\EngineApiHandler\Tests\Fixtures\Tag;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class ParsersTest extends TestCase
{
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    /**
     * The morph join compares the type with a MySQL escaped string ("A\\B"),
     * which SQLite does not unescape: a morph map keeps the type plain.
     */
    private function tagTheFirstBook(): void
    {
        Relation::morphMap(['book' => Book::class]);
        Book::query()->find(1)->tags()->attach(Tag::query()->create(['name' => 'classic'])->id);
    }

    private function filter(array $params): array
    {
        $query = DB::table('books')->orderBy('id');
        (new Filter())->parseFilter($params, $query, []);

        return $query->pluck('title')->all();
    }

    public function testFilterWithAndApproach()
    {
        $this->assertSame(['Alpha', 'Charlie'], $this->filter(['author_id-in' => '1']));
        $this->assertSame(['Bravo', 'Hidden'], $this->filter(['author_id-not-in' => '1']));
        $this->assertSame(['Alpha', 'Bravo'], $this->filter(['title' => 'Alpha|Bravo']));
        $this->assertSame(['Charlie', 'Hidden'], $this->filter(['title-not' => 'Alpha|Bravo']));
        $this->assertSame(['Alpha', 'Charlie'], $this->filter(['title-lk' => '*ha|*lie']));
        $this->assertSame(['Bravo'], $this->filter(['title-lk' => 'Bra*']));
        $this->assertSame([], $this->filter(['title' => 'null']));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie', 'Hidden'], $this->filter(['title-not' => 'null']));
        // Several columns in one key are combined with OR
        $this->assertSame(['Bravo'], $this->filter(['title|pages' => '300']));
        $this->assertSame([], $this->filter(['title|pages' => 'null']));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie', 'Hidden'], $this->filter(['title|pages-not' => 'null']));
    }

    public function testFilterWithOrApproach()
    {
        $or = ['_filter-approach' => 'or'];

        $this->assertSame(['Alpha', 'Hidden'], $this->filter($or + ['title' => 'Alpha', 'pages' => '50']));
        $this->assertSame(['Alpha', 'Charlie'], $this->filter($or + ['author_id-in' => '1']));
        $this->assertSame(['Bravo', 'Hidden'], $this->filter($or + ['author_id-not-in' => '1']));
        $this->assertSame(['Charlie'], $this->filter($or + ['title-lk' => '*lie']));
        $this->assertSame(['Alpha', 'Bravo'], $this->filter($or + ['title' => 'Alpha|Bravo']));
        $this->assertSame(['Alpha', 'Charlie'], $this->filter($or + ['title-lk' => '*ha|*lie']));
        $this->assertSame(['Bravo', 'Hidden'], $this->filter($or + ['title-not' => 'Alpha|Charlie']));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie', 'Hidden'], $this->filter($or + ['title-not' => 'null']));
        $this->assertSame([], $this->filter($or + ['title' => 'null']));
        $this->assertSame(['Alpha', 'Charlie'], $this->filter($or + ['title-not-lk' => 'Br*|Hi*']));

        // The OR group stays in parentheses, so a previous condition still applies
        $query = DB::table('books')->where('author_id', 1)->orderBy('id');
        (new Filter())->parseFilter($or + ['title' => 'Alpha', 'pages' => '300'], $query, []);
        $this->assertSame(['Alpha'], $query->pluck('title')->all());
    }

    public function testFilterSortAndFieldsReplaceTheTableAlias()
    {
        $aliases = ['abcde' => 'authors'];

        $query = DB::table('books');
        (new Filter())->parseFilter(['authors.name' => 'Ana', 3 => 'numeric key'], $query, $aliases);
        $this->assertSame('abcde.name', $query->wheres[0]['column']);

        $query = DB::table('books');
        (new Sort())->parse('-authors.name,title', $query, $aliases);
        $this->assertSame(['abcde.name', 'desc'], [$query->orders[0]['column'], $query->orders[0]['direction']]);
        $this->assertSame(['title', 'asc'], [$query->orders[1]['column'], $query->orders[1]['direction']]);

        $this->assertSame('id,abcde.name', (new Field())->define('id,authors.name', $aliases));
    }

    public function testSortAcceptsRawExpressions()
    {
        $query = DB::table('books');
        (new Sort())->parse([DB::raw('pages % 3'), '-id'], $query, []);

        $this->assertSame([300, 100, 50, 200], $query->pluck('pages')->map(fn ($p) => (int) $p)->all());
    }

    public function testWithDefinesTheRelationsOfTheFieldStructures()
    {
        $this->assertNull((new With())->define('id,title'));
        $this->assertSame('', (new With())->define(['id']));

        $fields = [
            new FieldStructure('author.name', null, 'author'),
            new FieldStructure('author.id', null, 'author'),
            'title',
        ];
        $this->assertSame('author', (new With())->define($fields));
    }

    public function testJoinAddsALeftJoinPerRelatedTable()
    {
        $book = new Book();
        $query = Book::query()->select('books.id', 'authors.name')->getQuery();

        $tables = (new Join())->parseAll($query, [
            new FieldStructure('author.name', $book->author()),
            new FieldStructure('author.id', $book->author()),
            new FieldStructure('title', $book->author()),
            new ExtraFieldStructure('extra.value', $book->author()),
            'plain',
        ]);

        $this->assertSame(['authors'], $tables);
        $this->assertCount(1, $query->joins);
        $this->assertSame('left', $query->joins[0]->type);
        $this->assertSame(['books.author_id', 'authors.id'], [$query->joins[0]->wheres[0]['first'], $query->joins[0]->wheres[0]['second']]);
        $this->assertSame('Ana', $query->orderBy('books.id')->first()->name);

        $author = new Author();
        $query = Author::query()->select('authors.name', 'books.title')->getQuery();
        (new Join())->parseAll($query, [], [new FieldStructure('books.title', $author->books())]);
        $this->assertSame(['books.author_id', 'authors.id'], [$query->joins[0]->wheres[0]['first'], $query->joins[0]->wheres[0]['second']]);
        $this->assertSame(['Alpha', 'Charlie'], $query->where('authors.id', 1)->orderBy('books.id')->pluck('title')->all());
    }

    public function testJoinOnAMorphToManyUsesAliasedPivotAndRelatedTables()
    {
        $sql = Book::query()->getQuery();
        (new Join())->parseAll($sql, [new FieldStructure('tags.name', (new Book())->tags())]);
        $this->assertStringContainsString('= "Betalabs\\\\EngineApiHandler\\\\Tests\\\\Fixtures\\\\Book"', $sql->toSql());

        $this->tagTheFirstBook();
        $join = new Join();
        $query = Book::query()->select('books.title')->getQuery();

        $join->parseAll($query, [], [], [new FieldStructure('tags.name', (new Book())->tags())]);

        $aliases = $join->getFieldAliases();
        $this->assertSame(['taggables', 'tags'], array_values($aliases));
        [$pivot, $tags] = array_keys($aliases);
        $query->addSelect($tags . '.name')->whereNotNull($tags . '.name');

        $this->assertSame([['title' => 'Alpha', 'name' => 'classic']], array_map(fn ($r) => (array) $r, $query->get()->all()));
        $this->assertStringContainsString('"taggables" as "' . $pivot . '"', $query->toSql());
    }

    public function testParserAcceptsARelationAndAdaptsNestedWheresForRelatedFields()
    {
        $titles = (new ApiHandler())->parseMultiple(Author::query()->find(1)->books(), [], ['_sort' => '-id'])
            ->getResult()
            ->pluck('title')
            ->all();
        $this->assertSame(['Charlie', 'Alpha'], $titles);

        $parser = new Parser(Book::query(), ['title' => 'Alpha|Bravo', 'pages' => '300']);
        $parser->parse([], true, ['author->name' => 'Ana']);
        $nested = array_values(array_filter($parser->builder->getQuery()->wheres, fn ($w) => $w['type'] == 'Nested'));
        $this->assertSame('or', $nested[0]['boolean']);
    }

    public function testParserLoadsAnyRelationMethod()
    {
        // Unlike the base parser, no "@Relation" annotation is required
        $this->tagTheFirstBook();
        $book = (new ApiHandler())->parseSingle(Book::query(), 1, ['_with' => 'tags'])->getResult();

        $this->assertSame(['classic'], $book->tags->pluck('name')->all());
    }

    public function testModelCountIsNullWithoutAnEloquentBuilder()
    {
        $provider = (new ModelCountMetaProvider('Base-Model-Total'))->setBuilder(Author::query()->find(1)->books());

        $this->assertNull($provider->get());
    }
}
