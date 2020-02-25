<?php

namespace App;

use App\Traits\StringLimit;
use App\Utilities\Functions;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Holds marking origin information.
 */
class SubmissionOrigin extends Model
{
    /* Configuration */

    use StringLimit;

    protected static $stringLimits = [
        'field' => 50,
        'input' => 120,
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
}
