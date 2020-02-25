<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Fields ending in "_uuid" or a field named "uuid" will automatically get set when saving the model.
 * Will not overwrite any existing value.
 */
trait AutoUUID
{
    protected static function bootAutoUUID()
    {
        static::creating(function ($model) {
            foreach ($model->attributes as $field => $value) {
                if (($field === 'uuid' || Str::endsWith($field, '_uuid')) && !$value) {
                    $model->attributes[$field] = (string)Str::uuid();
                }
            }
        });
    }
}
