<?php

namespace App;

use App\Jobs\ResolveIP;
use App\Traits\{AutoUUID, SimpleEnum, StringLimit};
use App\Utilities\Functions;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jenssegers\Agent\Agent;

/**
 * Basis of a lead that is submitted.
 * Has Forms, Marketings, Links, and an IPAddress.
 */
class Submission extends Model
{
    /* Configuration */

    use AutoUUID;
    use SimpleEnum;
    use StringLimit;

    protected static $deviceAllows = [
        'desktop' => true,
        'tablet'  => true,
        'phone'   => true,
        'robot'   => true,
        'other'   => true,
    ];

    protected static $scoreMinimum = 1;
    protected static $scoreMaximum = 1000000;

    protected static $gradeAllows = [
        /* number is the max score allowed for a grade */
        'ungraded' => -1,      /* any negative number    */
        'perfect'  => 9,       /*      0 to under     10 */
        'quality'  => 99,      /*     10 to under    100 */
        'review'   => 999,     /*    100 to under  1,000 */
        'junk'     => 9999,    /*  1,000 to under 10,000 */
        'ignore'   => 1000000, /* 10,000 and up          */
    ];

    protected static $stringLimits = [
        'platform' => 25,
        'browser'  => 25,
    ];

    protected $attributes = [
        'uuid'   => null,
        'score'  => -1,
        'grade'  => 'ungraded',
        'synced' => false,
    ];

    protected $casts = [
        'honeypot' => 'boolean',
        'score'    => 'integer',
        'synced'   => 'boolean',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /* Static Functions */

    /**
     * @param array $fields All form fields, including empty fields.
     * @param array $meta "ip_address", "user_agent", "http_headers" (array), "honeypot" (bool), and "duration" (float:seconds).
     * @param array $source "website", and "title".
     * @param array $links "referral", "landing", and "submit".
     */
    public static function createByForm(array $fields, array $meta, array $source, array $links)
    {
        $model = new static();
        $model->storeForm($fields, $meta, $source, $links);
        return $model;
    }

    /* Relationships */

    public function ipAddress()
    {
        return $this->belongsTo('App\IPAddress');
    }
    public function origins()
    {
        return $this->hasMany('App\SubmissionOrigin');
    }

    public function links()
    {
        return $this->hasMany('App\SubmissionLink');
    }

    public function fields()
    {
        return $this->hasMany('App\SubmissionField');
    }

    public function account()
    {
        return $this->belongsTo('App\Account');
    }

    public function type()
    {
        return $this->belongsTo('App\SubmissionType', 'submission_type_id');
    }

    /* Attribute Mutators */

    public function setDeviceAttribute($value)
    {
        $this->enumAttributeSet('device', $value);
    }

    public function setGradeAttribute($value)
    {
        $this->enumAttributeSet('grade', $value);
    }

    public function setDurationAttribute($value)
    {
        $this->attributes['duration'] = Functions::timeToCarbonInterval($value);
    }

    public function getDurationAttribute($value)
    {
        return Functions::timeToCarbonInterval($value);
    }

    public function getHasUtmSourceAttribute($value)
    {
        return $this->origins()->where('field', 'utm_source')->count() > 0;
    }

    /* Functions */

    public function field(string $field)
    {
        return $this->fields()->where('field', $field)->pluck('input')->first();
    }

    public function updated_at_url()
    {
        return $this->updated_at->format('Ymd-His-') . substr($this->updated_at->format('u'), 0, 4);
    }

    public function calculateScore($detailed = false)
    {
        $details = [];
        $scoreCap = null; /* maximum score allowed, based on a rules limitation */
        $rules = null;
        $file = base_path('scoring.php');
        if ($file && is_file($file)) {
            $rules = include $file;
        }

        if (is_iterable($rules)) {
            $this->score = 0;

            foreach ($rules as $rule) {
                if (isset($rule['score'], $rule['check']) && (isset($rule['fields']) || isset($rule['property']))) {
                    $thisProperty = null;
                    $score    = $rule['score'];
                    $check    = $rule['check'];
                    $name     = $rule['name']     ?? 'Unnamed rule';
                    $values   = $rule['values']   ?? null;
                    $property = $rule['property'] ?? null;
                    $fields   = $rule['fields']   ?? null;
                    $limit    = $rule['limit']    ?? null;
                    $count    = 0; /* number of times this rule matched this submission */

                    if ($property) {
                        if (Str::contains($property, '.')) {
                            [$prop, $sub] = explode('.', $property, 2);
                            $thisProperty = $this->$prop->$sub ?? null;
                        } else {
                            $thisProperty = $this->$property ?? null;
                        }
                    }

                    switch ($check) {
                        case 'regexp_count_over':
                            if (count($values) !== 2 || !is_string($values[0]) || !is_int($values[1]) || $values[1] < 0) {
                                Log::warning('Scoring rule check "regexp_count_over" needs $values to be ["<regexp>", <non-negative-int>].');
                                break;
                            }
                            /* remove anything that matches the given regexp, count how many characters were removed, and see if over given limit */
                            if ($property) {
                                $count += (int)((mb_strlen($thisProperty) - mb_strlen(preg_replace("%{$values[0]}%ui", '', $thisProperty))) > $values[1]);
                            } else {
                                $query = $this->fields()->scoring()->whereRaw("(length(input) - length(regexp_replace(input, ?, ''))) > ?", $values);
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += (int)((mb_strlen($this->message) - mb_strlen(preg_replace("%{$values[0]}%ui", '', $this->message))) > $values[1]);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'not_regexp':
                            if ($property) {
                                $count += (int)!preg_match("%{$values}%ui", $thisProperty);
                            } else {
                                $query = $this->fields()->scoring()->where('input', 'not regexp', $values);
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += (int)(!preg_match("%{$values}%ui", $this->message) && $this->message !== null);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'regexp':
                            if ($property) {
                                $count += (int)preg_match("%{$values}%ui", $thisProperty);
                            } else {
                                $query = $this->fields()->scoring()->where('input', 'regexp', $values);
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += (int)preg_match("%{$values}%ui", $this->message);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'contains':
                            if ($property) {
                                $count += Functions::arrayContains($thisProperty, $values);
                            } else {
                                $query = $this->fields()->scoring()->where(Functions::queryOrWhere('input', 'like',
                                    array_map(function ($value) { return "%{$value}%"; }, $values)
                                ));
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += Functions::arrayContains($this->message, $values);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'missing':
                            if ($property) {
                                /* missing on message is special, must ignore null */
                                if ($property === 'message') {
                                    $count += (int)(Functions::arrayMissing($thisProperty, $values) && $thisProperty !== null);
                                } else {
                                    $count += Functions::arrayMissing($thisProperty, $values);
                                }
                            } else {
                                $query = $this->fields()->scoring()->where(Functions::queryOrWhere('input', 'not like',
                                    array_map(function ($value) { return "%{$value}%"; }, $values)
                                ));

                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += (Functions::arrayMissing($this->message, $values) * (int)($this->message !== null));
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'ends_with':
                            if ($property) {
                                $count += Functions::arrayEndsWith($thisProperty, $values);
                            } else {
                                $query = $this->fields()->scoring()->where(Functions::queryOrWhere('input', 'like',
                                    array_map(function ($value) { return "%{$value}"; }, $values)
                                ));
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += Functions::arrayEndsWith($this->message, $values);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'is_bool':
                            if ($property) {
                                $count += (int)((bool)$thisProperty === (bool)$values);
                            } else {
                                $query = $this->fields()->scoring()->whereRaw('IF(input, 1, 0) = ?', [(int)(bool)$values]);
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += (int)((bool)$this->message === (bool)$values);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'is_empty':
                            if ($property) {
                                /* is-empty on message is special, must ignore null */
                                if ($property === 'message') {
                                    $count += (int)(empty($thisProperty) && $thisProperty !== null);
                                } else {
                                    $count += (int)empty($thisProperty);
                                }
                            } else {
                                $query = $this->fields()->scoring()->whereNull('input');
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    /* again, is-empty on message is special, must ignore null */
                                    $count += (int)(empty($this->message) && $this->message !== null);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'length_under':
                            if ($property) {
                                $count += (int)mb_strlen($thisProperty) < (int)$values;
                            } else {
                                $query = $this->fields()->scoring()->whereRaw('LENGTH(input) < ?', [(int)$values]);
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += (int)(mb_strlen($this->message) < (int)$values && $this->message !== null);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'length_over':
                            if ($property) {
                                $count += (int)mb_strlen($thisProperty) > (int)$values;
                            } else {
                                $query = $this->fields()->scoring()->whereRaw('LENGTH(input) > ?', [(int)$values]);
                                if (!is_iterable($fields)) { /* all fields, also check message */
                                    $count += (int)(mb_strlen($this->message) > (int)$values);
                                } else { /* not all fields, limit to given fields */
                                    $query->whereIn('field', $fields);
                                }
                                $count += $query->count();
                            }
                            break;
                        case 'less_than':
                            if ($property === 'duration') {
                                /* less-than on duration is special, must call $this->duration->lessThan() */
                                $count += (int)$this->duration->lessThan($values);
                            } else {
                                Log::warning('Scoring rule check "less_than" attempted on an item besides property of "duration".');
                            }
                            break;
                        case 'email':
                            if (is_iterable($fields)) {
                                $inputs = $this->fields()->scoring()->whereIn('field', $fields)->pluck('input');
                                foreach ($inputs as $input) {
                                    if (Validator::make(['email' => $input], ['email' => 'email:strict,dns,spoof'])->fails()) {
                                        $count++;
                                        Log::debug("Email Failed Validation: '{$input}'.");
                                    }
                                }
                            } else {
                                Log::warning('Scoring rule check "email" attempted on an item besides fields of array.');
                            }
                            break;
                        default:
                            Log::debug("Unknown rule check type: '{$check}'.");
                            break;
                    }

                    if ($detailed && $count > 0) {
                        $mod = $score > 0 ? 'added' : 'removed';
                        $occasion = $count > 1 ? "on {$count} occasions, each" : 'which';
                        $details[] = "{$name} {$occasion} {$mod} {$score} points.";
                    }

                    if ($limit !== null && $count > 0) {
                        if ($scoreCap === null) {
                            $scoreCap = $limit;
                        } else {
                            /* if multiple limits are applied, we go with the smallest */
                            $scoreCap = min($scoreCap, $limit);
                        }

                        if ($detailed) {
                            $details[] = "MAXIMUM SCORE SET TO {$limit}, BY RULE: {$name}.";
                        }
                    }

                    /* multiply count (times rule matched this rule) by rule's score */
                    $this->score += $score * $count;
                }
            }

            if ($scoreCap !== null && $this->score > $scoreCap) {
                $this->score = $scoreCap;
            }

            Log::debug("Score: {$this->score}.");

            /* enforce score minimum and maximum range */
            if ($this->score < static::$scoreMinimum) {
                $this->score = static::$scoreMinimum;
            } elseif ($this->score > static::$scoreMaximum ) {
                $this->score = static::$scoreMaximum;
            }

            $this->calculateGrade();
            return $detailed ? $details : true; /* $rules was iterable */
        }

        /* score was unchanged */
        return false;
    }

    /* Protected Functions */

    /**
     * @param array $fields All form fields, including empty fields.
     * @param array $meta "ip_address", "user_agent", "http_headers" (array), "honeypot" (bool), and "duration" (float:seconds).
     * @param array $source "website", and "title".
     * @param array $links "referral", "landing", and "submit".
     */
    protected function storeForm(array $fields, array $meta, array $source, array $links)
    {
        /* $this->score and $this->account are handled later */
        $this->ipAddress()->associate(IPAddress::findOrCreateByIP($meta['ip_address']));
        $this->type()->associate(SubmissionType::firstOrCreate($source));
        $this->parseUserAgent($meta['user_agent'], $meta['http_headers']); /* sets device, platform, and browser */
        $this->honeypot = $meta['honeypot'];
        $this->duration = $meta['duration'];
        $this->save(); /* must save before we can create dependent records */
        $this->createFields($fields);
        $this->createLinks($links); /* sets links and origins */
        $this->save();

        /* dispatch to the backend queue, which will resolve-ip, and then process-submission */
        dispatch(new ResolveIP($this->ipAddress, $this));
    }

    protected function parseUserAgent(string $userAgent, array $httpHeaders)
    {
        $agent = new Agent();
        $agent->setUserAgent($userAgent);
        $agent->setHttpHeaders($httpHeaders);

        if ($agent->isDesktop()) {
            $this->device = 'desktop';
        } elseif ($agent->isTablet()) {
            $this->device = 'tablet';
        } elseif ($agent->isPhone()) {
            $this->device = 'phone';
        } elseif ($agent->isRobot()) {
            $this->device = 'robot';
        } else {
            $this->device = 'other';
        }

        $this->platform = $agent->platform();
        $this->browser  = $agent->browser();
    }

    protected function createFields(array $fields)
    {
        /* message (or comment[s]) field is stored differently, so remove from list if present after processing */
        /* we prefer using the message field, and given both, we'll only keep message, this is intentional */
        foreach (['comments', 'comment', 'message'] as $text) {
            if (array_key_exists($text, $fields)) {
                /* notice we are trimming to blank string, instead of null for message, this is intentional */
                $this->message = trim($fields[$text]);
                unset($fields[$text]);
            }
        }

        $this->fields()->createMany(Functions::arrayToColumns($fields, 'field', 'input'));
    }

    protected function createLinks(array $urls)
    {
        if (isset($urls['landing'])) {
            /* "https://example.com?a=b&..." turned into ['a' => 'b', ...] */
            parse_str(parse_url($urls['landing'], PHP_URL_QUERY), $origins);
            $this->createOrigins($origins);
        }

        $this->links()->createMany(Functions::arrayToColumns($urls, 'type', 'url'));
    }

    protected function createOrigins(array $origins)
    {
        $this->origins()->createMany(Functions::arrayToColumns($origins, 'field', 'input'));
    }

    protected function calculateGrade()
    {
        if ($this->grade === 'ungraded') {
            foreach (static::$gradeAllows as $grade => $score) {
                if ($this->score <= $score) {
                    $this->grade = $grade;
                    break;
                }
            }
            Log::debug("Grade set to '{$this->grade}', based on Score: {$this->score}.");
        }
    }
}
