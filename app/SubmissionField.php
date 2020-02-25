<?php

namespace App;

use App\Traits\StringLimit;
use App\Utilities\Functions;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Holds user supplied information (form fields).
 */
class SubmissionField extends Model
{
    /* Configuration */

    use StringLimit;

    protected static $stringLimits = [
        'field' => 50,
        'input' => 255,
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['field', 'input'];

    protected $touches = ['submission'];

    /* Attribute Mutators */

    public function setInputAttribute($value)
    {
        $this->attributes['input'] = Functions::trimToNull($value);
    }

    /* Relationships */

    public function submission()
    {
        return $this->belongsTo('App\Submission');
    }

    /* Scopes */

    public function scopeScoring($query)
    {
        $prefix = config('app.nonscoring_prefix', '\\_');
        return $query->where('field', 'not like',  "{$prefix}%");
    }

    public function scopeNonscoring($query)
    {
        $prefix = config('app.nonscoring_prefix', '\\_');
        return $query->where('field', 'like',  "{$prefix}%");
    }
}
