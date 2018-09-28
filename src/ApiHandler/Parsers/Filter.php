<?php

namespace Betalabs\EngineApiHandler\ApiHandler\Parsers;

use Illuminate\Database\Query\Builder;

class Filter
{

    use QualifiedAlias;

    /**
     * @var \Illuminate\Database\Query\Builder
     */
    private $query;

    /**
     * Parse the remaining filter params
     *
     * This method was override because its default behavior is to "concat"
     * the search parameters using AND, but in some cases in the application
     * OR approach is needed.
     *
     * To change the approach just add "_filter-approach=or" in the GET
     * parameter.
     *
     * @param  array $filterParams
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $fieldAliases
     *
     * @return void
     */
    public function parseFilter(
        $filterParams,
        Builder $query,
        array $fieldAliases
    ) {

        $this->query = $query;
        $this->fieldAliases = $fieldAliases;

        $approach = isset($filterParams['_filter-approach']) ? $filterParams['_filter-approach'] : 'and';
        unset($filterParams['_filter-approach']);

        /*
         * If the approach is not being changed so the original method is
         * called
         */
        if ($approach != 'or') {
            $this->parseFilterApproachedWithAnd($filterParams);
            return;
        }

        $this->parseFilterApproachedWithOr($filterParams);

    }

    /**
     * Used to parse filter parameters into query builder using AND statements
     *
     * @param $filterParams
     */
    private function parseFilterApproachedWithAnd($filterParams)
    {
        $supportedPostfixes = [
            'st' => '<',
            'gt' => '>',
            'min' => '>=',
            'max' => '<=',
            'lk' => 'LIKE',
            'not-lk' => 'NOT LIKE',
            'in' => 'IN',
            'not-in' => 'NOT IN',
            'not' => '!=',
        ];

        $filterParams = $this->filterParamsAlias($filterParams);

        $supportedPrefixesStr = implode('|', $supportedPostfixes);
        $supportedPostfixesStr = implode('|', array_keys($supportedPostfixes));

        foreach ($filterParams as $filterParamKey => $filterParamValue) {

            $keyMatches = [];

            //Matches every parameter with an optional prefix and/or postfix
            //e.g. not-title-lk, title-lk, not-title, title
            $keyRegex = '/^(?:(' . $supportedPrefixesStr . ')-)?(.*?)(?:-(' . $supportedPostfixesStr . ')|$)/';

            preg_match($keyRegex, $filterParamKey, $keyMatches);

            if (!isset($keyMatches[3])) {
                if (strtolower(trim($filterParamValue)) == 'null') {
                    $comparator = 'NULL';
                } else {
                    $comparator = '=';
                }
            } else {
                if (strtolower(trim($filterParamValue)) == 'null') {
                    $comparator = 'NOT NULL';
                } else {
                    $comparator = $supportedPostfixes[$keyMatches[3]];
                }
            }

            $column = $keyMatches[2];

            if ($comparator == 'IN') {
                $values = explode(',', $filterParamValue);

                $this->query->whereIn($column, $values);
            } else if ($comparator == 'NOT IN') {
                $values = explode(',', $filterParamValue);

                $this->query->whereNotIn($column, $values);
            } else {
                $values = explode('|', $filterParamValue);

                if (count($values) > 1) {
                    $this->query->where(function ($query) use ($column, $comparator, $values) {
                        foreach ($values as $value) {
                            if ($comparator == 'LIKE' || $comparator == 'NOT LIKE') {
                                $value = preg_replace('/(^\*|\*$)/', '%', $value);
                            }

                            //Link the filters with AND of there is a "not" and with OR if there's none
                            if ($comparator == '!=' || $comparator == 'NOT LIKE') {
                                $query->where($column, $comparator, $value);
                            } else {
                                $query->orWhere($column, $comparator, $value);
                            }
                        }
                    });
                } else {
                    $value = $values[0];

                    if ($comparator == 'LIKE' || $comparator == 'NOT LIKE') {
                        $value = preg_replace('/(^\*|\*$)/', '%', $value);
                    }

                    if (count($columns = explode('|', $column)) == 1) {
                        if ($comparator == 'NULL' || $comparator == 'NOT NULL') {
                            $this->query->whereNull($column, 'and', $comparator == 'NOT NULL');
                        } else {
                            $this->query->where($column, $comparator, $value);
                        }
                    } else {
                        $this->query->where(function ($query) use ($columns, $comparator, $value) {
                            foreach ($columns as $column) {
                                if ($comparator == 'NULL' || $comparator == 'NOT NULL') {
                                    $query->orWhereNull($column, 'and', $comparator == 'NOT NULL');
                                } else {
                                    $query->orWhere($column, $comparator, $value);
                                }
                            }
                        });
                    }
                }
            }
        }
    }

    /**
     * Used to parse filter parameters into query builder using OR statements
     *
     * @param $filterParams
     */
    private function parseFilterApproachedWithOr($filterParams)
    {
        $supportedPostfixes = [
            'st' => '<',
            'gt' => '>',
            'min' => '>=',
            'max' => '<=',
            'lk' => 'LIKE',
            'not-lk' => 'NOT LIKE',
            'in' => 'IN',
            'not-in' => 'NOT IN',
            'not' => '!=',
        ];

        $filterParams = $this->filterParamsAlias($filterParams);

        $supportedPrefixesStr = implode('|', $supportedPostfixes);
        $supportedPostfixesStr = implode('|', array_keys($supportedPostfixes));

        $orWhere = [];

        foreach ($filterParams as $filterParamKey => $filterParamValue) {

            $keyMatches = [];

            //Matches every parameter with an optional prefix and/or postfix
            //e.g. not-title-lk, title-lk, not-title, title
            $keyRegex = '/^(?:(' . $supportedPrefixesStr . ')-)?(.*?)(?:-(' . $supportedPostfixesStr . ')|$)/';

            preg_match($keyRegex, $filterParamKey, $keyMatches);

            if (!isset($keyMatches[3])) {
                if (strtolower(trim($filterParamValue)) == 'null') {
                    $comparator = 'NULL';
                } else {
                    $comparator = '=';
                }
            } else {
                if (strtolower(trim($filterParamValue)) == 'null') {
                    $comparator = 'NOT NULL';
                } else {
                    $comparator = $supportedPostfixes[$keyMatches[3]];
                }
            }

            $column = $keyMatches[2];

            if ($comparator == 'IN') {
                $values = explode(',', $filterParamValue);

                $this->query->whereIn($column, $values);
            } else if ($comparator == 'NOT IN') {
                $values = explode(',', $filterParamValue);

                $this->query->whereNotIn($column, $values);
            } else {
                $values = explode('|', $filterParamValue);

                if (count($values) > 1) {
                    $this->query->where(function ($query) use ($column, $comparator, $values) {
                        foreach ($values as $value) {
                            if ($comparator == 'LIKE' || $comparator == 'NOT LIKE') {
                                $value = preg_replace('/(^\*|\*$)/', '%', $value);
                            }

                            //Link the filters with AND of there is a "not" and with OR if there's none
                            if ($comparator == '!=' || $comparator == 'NOT LIKE') {
                                $query->where($column, $comparator, $value);
                            } else {
                                $orWhere[] = [$column, $comparator, $value];
                            }
                        }
                    });
                } else {
                    $value = $values[0];

                    if ($comparator == 'LIKE' || $comparator == 'NOT LIKE') {
                        $value = preg_replace('/(^\*|\*$)/', '%', $value);
                    }

                    if ($comparator == 'NULL' || $comparator == 'NOT NULL') {
                        $this->query->orWhereNull($column, 'and', $comparator == 'NOT NULL');
                    } else {
                        $orWhere[] = [$column, $comparator, $value];
                    }
                }
            }
        }

        /*
         * It is necessary for virtual entity record (VER). Using VER there is
         * a where to filter only records of the right virtual entity. When we
         * add new wheres to filter data the virtual entity reference must be used.
         *
         * If we add only a `orWhere` as it was before will be added an OR in
         * the statement and it might retrieve some other virtual entity data
         * and not filter records.
         *
         * This way there will be something like that:
         * `WHERE virtual_entity_id = 1 AND (foo=bar OR hello=world)`
         *
         * The other (wrong) way will be:
         * `WHERE virtual_entity_id = 1 OR foo=bar OR hello=world`
         */
        $this->query->where(function ($q) use ($orWhere) {

            foreach ($orWhere as $where) {
                $q->orWhere($where[0], $where[1], $where[2]);
            }

        });

    }

    /**
     * Replace table name to the alias
     *
     * @param array $filterParams
     *
     * @return array
     */
    private function filterParamsAlias($filterParams)
    {

        foreach ($filterParams as $key => $filterParam) {
            array_forget($filterParams, $key);
            $filterParams[$this->replaceQualifiedAlias($key)] = $filterParam;
        }

        return $filterParams;

    }

}