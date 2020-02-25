<?php

namespace App\Utilities;

use App\Submission;
use Carbon\CarbonInterval;
use DateInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

abstract class Functions
{
    /**
     * Convert the given time into a CarbonInterval, required format: "[[H:]i:]s[.u]".
     * @param float|int|string|DateInterval|CarbonInterval $time
     * @return CarbonInterval
     */
    public static function timeToCarbonInterval($time)
    {
        $output = null;

        if ($time instanceof CarbonInterval) {
            $output = $time;
        } elseif ($time instanceof DateInterval) {
            $output = CarbonInterval::instance($time);
        } elseif (is_numeric($time)) {
            /* number_format() is needed so we handle both formats "s" and "s.u" at once */
            $output = CarbonInterval::createFromFormat('s.u', number_format($time, 6, '.', ''));
        } elseif (is_string($time)) {
            if (preg_match('/\d+:\d{1,2}:\d{1,2}\.\d*/', $time)) {
                $output = CarbonInterval::createFromFormat('H:i:s.u', $time);
            } elseif (preg_match('/\d+:\d{1,2}:\d{1,2}/', $time)) {
                $output = CarbonInterval::createFromFormat('H:i:s', $time);
            } elseif (preg_match('/\d+:\d{1,2}\.\d*/', $time)) {
                $output = CarbonInterval::createFromFormat('i:s.u', $time);
            } elseif (preg_match('/\d+:\d{1,2}/', $time)) {
                $output = CarbonInterval::createFromFormat('i:s', $time);
            }
        }

        if ($output instanceof CarbonInterval) {
            /* cascade() to normalize the time, settings() to enforce database compatibility */
            $output->cascade()->settings(['toStringFormat' => '%H:%I:%S.%F']);
            return $output;
        }

        throw new InvalidArgumentException('Invalid time given, unable to convert to CarbonInterval.');
    }

    /**
     * Convert associated array into nested array:
     * ['a' => 'b', ...] into [[$keyName => 'a', $valueName => 'b'], [...]]
     * @param array $input
     * @param string $keyName
     * @param string $valueName
     * @param array $extra Optional, additional key/value pairs to add to each row.
     */
    public static function arrayToColumns(array $input, string $keyName, string $valueName, array $extra = null)
    {
        if (is_null($extra)) {
            $extra = [];
        }

        $output = [];
        foreach ($input as $key => $value) {
            $output[] = array_merge($extra, [
                $keyName   => $key,
                $valueName => $value,
            ]);
        }
        return $output;
    }

    /**
     * @param string|null $value
     * @return string|null
     */
    public static function trimToNull(?string $value)
    {
        $value = trim($value);
        if (strlen($value) < 1) {
            $value = null;
        }
        return $value;
    }

    public static function submissionRegradeUrl(Submission $submission, string $grade, bool $abort = false)
    {
        $parameters = [
            'updated_at' => $submission->updated_at_url(),
            'uuid'       => $submission->uuid,
            'grade'      => $grade
        ];
        if ($abort) {
            $parameters['abort'] = true;
        }
        return route('submission.update', $parameters);
    }

    public static function contains(?string $haystack, string $needle, bool $strict = false): bool
    {
        if (!$strict) {
            $haystack = mb_strtolower($haystack);
            $needle   = mb_strtolower($needle);
        }
        return Str::contains($haystack, $needle);
    }

    public static function endsWith(?string $haystack, string $needle, bool $strict = false): bool
    {
        if (!$strict) {
            $haystack = mb_strtolower($haystack);
            $needle   = mb_strtolower($needle);
        }
        return Str::endsWith($haystack, $needle);
    }

    public static function arrayContains(?string $haystack, array $needles, bool $strict = false): int
    {
        $count = 0;
        foreach ($needles as $needle) {
            $count += (int)static::contains($haystack, $needle, $strict);
        }
        return $count;
    }

    public static function arrayEndsWith(?string $haystack, array $needles, bool $strict = false): int
    {
        $count = 0;
        foreach ($needles as $needle) {
            $count += (int)static::endsWith($haystack, $needle, $strict);
        }
        return $count;
    }

    public static function arrayMissing(?string $haystack, array $needles, bool $strict = false): int
    {
        $count = 0;
        foreach ($needles as $needle) {
            /* notice negation */
            $count += (int)!static::contains($haystack, $needle, $strict);
        }
        return $count;
    }

    public static function queryOrWhere(string $column, ?string $op, array $values): callable
    {
        if ($op === null) {
            $op = '=';
        }
        return (function (Builder $builder) use ($column, $op, $values) {
            $value = array_pop($values);
            $builder->where($column, $op, $value);
            foreach ($values as $value) {
                $builder->orWhere($column, $op, $value);
            }
        });
    }

    public static function queryAndWhere(string $column, ?string $op, array $values): callable
    {
        if ($op === null) {
            $op = '=';
        }
        return (function (Builder $builder) use ($column, $op, $values) {
            $value = array_pop($values);
            $builder->where($column, $op, $value);
            foreach ($values as $value) {
                $builder->where($column, $op, $value);
            }
        });
    }
}
