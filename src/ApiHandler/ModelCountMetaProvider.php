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
        $this->builder->getQuery()->joins = null;
        $this->builder->getQuery()->orders = null;
        $this->builder->getQuery()->offset = null;
        if($this->builder instanceof Builder) {
            return $this->builder->count();
        }
        return null;
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

