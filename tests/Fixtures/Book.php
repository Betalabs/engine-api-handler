<?php

namespace Betalabs\EngineApiHandler\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $guarded = [];

    /**
     * @Relation
     */
    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @Relation
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
