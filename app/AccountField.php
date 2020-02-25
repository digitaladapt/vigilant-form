<?php

namespace App;

use App\Traits\StringLimit;
use App\Utilities\Functions;
use Illuminate\Database\Eloquent\Model;

/**
 * Holds externally supplied information (account information).
 */
class AccountField extends Model
{
    /* Configuration */

    use StringLimit;

    protected static $stringLimits = [
        'field' => 50,
        'input' => 255,
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['field', 'input'];

    protected $touches = ['account'];

    /* Attribute Mutators */

    public function setInputAttribute($value)
    {
        $this->attributes['input'] = Functions::trimToNull($value);
    }

    /* Relationships */

    public function account()
    {
        return $this->belongsTo('App\Account');
    }
}
