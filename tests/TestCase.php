<?php

namespace Betalabs\EngineApiHandler\Tests;

use Betalabs\EngineApiHandler\Tests\Fixtures\Author;
use Betalabs\EngineApiHandler\Tests\Fixtures\Book;
use Betalabs\EngineApiHandler\Tests\Fixtures\BookIndexer;
use Betalabs\EngineApiHandler\Tests\Fixtures\JoinedBookIndexer;
use Betalabs\EngineApiHandler\Tests\Fixtures\RelationFilteredBookIndexer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Marcelgwerder\ApiHandler\ApiHandlerServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

// The parsers type check the apps' App\Http\Requests\FieldStructure
require_once __DIR__ . '/Stubs/FieldStructure.php';
require_once __DIR__ . '/Stubs/ExtraFieldStructure.php';

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [ApiHandlerServiceProvider::class];
    }

    protected function defineRoutes($router)
    {
        Route::get('/books', function () {
            return (new BookIndexer())->execute();
        });

        Route::get('/books/relation-filtered', function () {
            return (new RelationFilteredBookIndexer())->execute();
        });

        Route::get('/books/joined', function () {
            return (new JoinedBookIndexer())->execute();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel sends PHP deprecations to a log by default; throw them
        // instead so a deprecated call fails the suite.
        $this->withoutDeprecationHandling();

        Schema::create('authors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('author_id');
            $table->string('title');
            $table->unsignedInteger('pages');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->unsignedInteger('tag_id');
            $table->morphs('taggable');
        });

        $ana = Author::query()->create(['name' => 'Ana']);
        $bruno = Author::query()->create(['name' => 'Bruno']);
        $inactive = Author::query()->create(['name' => 'Inactive', 'active' => false]);

        Book::query()->create(['author_id' => $ana->id, 'title' => 'Alpha', 'pages' => 100]);
        Book::query()->create(['author_id' => $bruno->id, 'title' => 'Bravo', 'pages' => 300]);
        Book::query()->create(['author_id' => $ana->id, 'title' => 'Charlie', 'pages' => 200]);
        Book::query()->create(['author_id' => $inactive->id, 'title' => 'Hidden', 'pages' => 50]);
    }
}
