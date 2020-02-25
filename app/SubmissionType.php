<?php

namespace App;

use App\Traits\StringLimit;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * List of the different types of websites and forms we collect.
 */
class SubmissionType extends Model
{
    /* Configuration */

    use StringLimit;

    protected static $stringLimits = [
        'website'      => 50,
        'title'        => 50,
        'external_key' => 50,
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['website', 'title'];

    /* Relationships */

    public function submissions()
    {
        return $this->hasMany('App\Submission');
    }

    /* Functions */

    public function websiteTitle()
    {
        return "{$this->website} {$this->title}";
    }
}
