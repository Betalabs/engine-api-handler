<?php

namespace Betalabs\EngineApiHandler\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    protected $guarded = [];

    /**
     * @Relation
     */
    public function books()
    {
        return $this->hasMany(Book::class);
    }
}
