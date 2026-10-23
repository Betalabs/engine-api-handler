<?php


namespace Betalabs\EngineApiHandler\ApiHandler;

use Marcelgwerder\ApiHandler\MetaProvider;
use Illuminate\Database\Eloquent\Builder;

class ModelCountMetaProvider extends MetaProvider
{
    protected $title = 'Base-Model-Total'; 
    
    /**
     * @var \Illuminate\Database\Eloquent\Builder 
     */
    protected $builder;

    /**
     * Get the meta information
     *
     * @return string
     */
    public function get()
    {
        // A relation (not an Eloquent builder) has no count here; checked
        // first because its getQuery() has no "bindings" property
        if(!$this->builder instanceof Builder) {
            return null;
        }

        $this->builder->getQuery()->joins = null;
        $this->builder->getQuery()->orders = null;
        $this->builder->getQuery()->offset = null;

        // Bindings of the removed joins/orders must go too: otherwise the
        // count query sends more values than placeholders (PDO HY093)
        $this->builder->getQuery()->bindings['join'] = [];
        $this->builder->getQuery()->bindings['order'] = [];

        return $this->builder->count();
    }

    /**
     * @param Illuminate\Database\Eloquent\Builder $builder
     * @return Betalabs\EngineApiHandler\ApiHandler\ModelCountMetaProvider
     */
    public function setBuilder($builder)
    {
        $this->builder = clone $builder;
        return $this;
    }

}

