<?php

namespace App\Http\Requests;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Minimal copy of the apps' App\Http\Requests\FieldStructure (Engine-Enotas,
 * nfe): only the methods the parsers call.
 */
class FieldStructure
{
    public function __construct(
        private string $field,
        private ?Relation $relation = null,
        private string $modelRelation = '',
        private ?string $value = null
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getRelation(): Relation
    {
        return $this->relation;
    }

    public function getModelRelation(): string
    {
        return $this->modelRelation;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
