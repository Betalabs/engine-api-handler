<?php

namespace Betalabs\EngineApiHandler\ApiHandler\Parsers;

use App\Http\Requests\FieldStructure;

class With
{

    /**
     * Define with parameters
     *
     * @param $fields
     * @return null|string
     */
    public function define($fields)
    {

        if(!is_array($fields)) {
            return null;
        }

        $with = [];

        foreach($fields as $field) {

            if(!($field instanceof FieldStructure)) {
                continue;
            }

            $with[] = $field->getModelRelation();

        }

        $withOriginal = [];

        if(isset($this->params['_with'])) {
            $withOriginal = explode(',', $this->params['_with']);
        }

        $with = array_merge($withOriginal, $with);
        $with = array_unique($with);

        return implode(',', $with);

    }

}