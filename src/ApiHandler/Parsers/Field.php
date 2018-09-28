<?php

namespace Betalabs\EngineApiHandler\ApiHandler\Parsers;

class Field
{

    use QualifiedAlias;

    /**
     * Define fields for select
     *
     * @param $fields
     * @param $fieldAliases
     * @return string
     */
    public function define($fields, $fieldAliases)
    {

        $this->fieldAliases = $fieldAliases;

        $fields = explode(',', $fields);

        $newFields = [];

        foreach($fields as $field) {
            $newFields[] = $this->replaceQualifiedAlias($field);
        }

        return implode(',', $newFields);

    }

}