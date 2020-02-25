<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Fields specified in static::$stringLimits = [] will be truncated if over the value specified,
 * and a warning will be logged, complete with the data we were unable to persist.
 * This is allow data overflow to be handled as gracefully as possible.
 */
trait StringLimit
{
    protected static function bootStringLimit()
    {
        static::saving(function ($model) {
            foreach ($model->attributes as $field => $value) {
                if (isset(static::$stringLimits[$field]) && mb_strlen($value) > static::$stringLimits[$field]) {
                    Log::warning("String Limit Exceeded, Property: '" . static::class . "->{$field}(" . static::$stringLimits[$field] . ")', Value: '{$value}'.");
                    $model->attributes[$field] = mb_substr($value, 0, static::$stringLimits[$field]);
                }
            }
        });
    }
}
