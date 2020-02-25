<?php

namespace App;

use App\Traits\{SimpleEnum, StringLimit};
use App\Utilities\Functions;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Holds url metadata for a submission (referral, etc).
 */
class SubmissionLink extends Model
{
    /* Configuration */

    use SimpleEnum;
    use StringLimit;

    protected static $typeAllows = [
        'referral' => true,
        'landing'  => true,
        'submit'   => true,
    ];

    protected static $stringLimits = [
        'url' => 1000,
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['type', 'url'];

    protected $touches = ['submission'];

    /* Relationships */

    public function submission()
    {
        return $this->belongsTo('App\Submission');
    }

    /* Attribute Mutators */

    public function setTypeAttribute($value)
    {
        $this->enumAttributeSet('type', $value);
    }

    public function setUrlAttribute($value)
    {
        $this->attributes['url'] = Functions::trimToNull($value);
    }

}
