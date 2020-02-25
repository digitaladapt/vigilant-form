<?php

namespace App;

use App\Traits\{AutoUUID, StringLimit};
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * An Account allows us to identify when multiple Submissions are associatedwith a single person or company.
 */
class Account extends Model
{
    /* Configuration */

    use AutoUUID;
    use StringLimit;

    protected static $stringLimits = [
        'external_key' => 50,
    ];

    protected $attributes = [
        'uuid' => null,
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['external_key'];

    /* Relationships */

    public function submissions()
    {
        return $this->hasMany('App\Submission');
    }

    public function fields()
    {
        return $this->hasMany('App\AccountField');
    }

    /* Functions */

    public function field(string $field)
    {
        return $this->fields()->where('field', $field)->pluck('input')->first();
    }

    public function setField(string $field, $value)
    {
        /* if an account_field belonging to this account with the given field exists,
         * use it, otherwise create it with the value specified */
        $field = $this->fields()->firstOrNew(['field' => $field]);
        $field->input = $value;
        $field->save();
    }
}
