<?php

namespace Betalabs\EngineApiHandler\ApiHandler\Parsers;

use Illuminate\Database\Query\Builder;
//use App\Http\Requests\FieldStructure;
use Illuminate\Database\Query\Expression;

class Sort
{

    use QualifiedAlias;

    /**
     * Parse sort
     *
     * @param $sortParam
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $fieldAliases
     */
    public function parse(
        $sortParam,
        Builder $query,
        array $fieldAliases
    ) {

        $this->fieldAliases = $fieldAliases;

        if (is_string($sortParam)) {
            $sortParam = explode(',', $sortParam);
        }

        foreach ($sortParam as $sortElem) {

            /*if ($sortElem instanceof FieldStructure) {
                $sortElem = $sortElem->getValue() . $sortElem->getField();
            }*/

            if (!$sortElem instanceof Expression) {
                //Check if ascending or descending(-) sort
                if (preg_match('/^-.+/', $sortElem)) {
                    $direction = 'desc';
                } else {
                    $direction = 'asc';
                }

                $pair = [preg_replace('/^-/', '', $sortElem), $direction];

                //Only add the sorts that are on the base resource
                call_user_func_array([$query, 'orderBy'], $pair);
            } else {
                $query->orderBy($sortElem);
            }
        }

    }

}