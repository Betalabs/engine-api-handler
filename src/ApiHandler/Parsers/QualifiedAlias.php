<?php

namespace Betalabs\EngineApiHandler\ApiHandler\Parsers;

use Illuminate\Support\Arr;

trait QualifiedAlias
{

    /** @var array */
    private $fieldAliases = [];

    /**
     * Return qualified key replaced with alias
     *
     * @param $key
     * @return mixed|null
     */
    private function replaceQualifiedAlias($key)
    {

        if(!is_string($key)) {
            return $key;
        }

        $table = Arr::first(explode('.', $key));

        // Avoid "-" when sorting desc
        if(strpos($table, '-') === 0) {
            $table = substr($table, 1);
        }

        if($fieldAliasKey = array_search($table, $this->fieldAliases)) {
            return str_replace($table, $fieldAliasKey, $key);
        }

        return $key;

    }

}