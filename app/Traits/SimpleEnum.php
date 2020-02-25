<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Fields can call $this->enumAttributeSet() to ensure that $field
 * is limited to one of the static::${field}Allows values.
 */
trait SimpleEnum
{
    protected function enumAttributeSet(string $field, string $value)
    {
        if (isset(static::${"{$field}Allows"}[$value])) {
            $this->attributes[$field] = $value;
        } else {
            Log::debug("Unallowed value, Property: '" . static::class . "->{$field}', Value: '{$value}'.");
        }
    }
}
