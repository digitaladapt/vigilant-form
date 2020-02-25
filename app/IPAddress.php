<?php

namespace App;

use App\Exceptions\ImmutableException;
use App\Traits\StringLimit;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\Log;

/**
 * An IPAddress record can be reused across multiple records, within a reasonable timelimit.
 * Once a record is old enough, for the same ip, a new record will be generated,
 * and its corresponding details will be looked up again.
 */
class IPAddress extends Model
{
    /* Configuration */

    use StringLimit;

    protected static $stringLimits = [
        'hostname'  => 255,
        'continent' => 50,
        'country'   => 50,
        'region'    => 50,
        'city'      => 50,
    ];

    protected $table = 'ip_addresses';

    protected $casts = [
        'latitude'  => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public static function findOrCreateByIP(string $ip)
    {
        $model = static::findCurrentByIP($ip);
        if (!$model) {
            $model = new static();
            $model->ip = $ip;
            /* do NOT resolve the new model */
            $model->save();
        }
        return $model;
    }

    public static function findCurrentByIP(string $ip)
    {
        return static::where('ip', $ip)
            ->where('created_at', '>=', new Carbon(config('app.ip_ttl')))
            ->latest()
            ->first();
    }

    /* Relationships */

    public function submissions()
    {
        return $this->hasMany('App\Submission');
    }

    /* Attribute Mutators */

    public function setIpAttribute($value)
    {
        if (!empty($this->attributes['ip'])) {
            Log::error('Attempted to override IPAddress->ip with new value, field is immutable.');
            throw new ImmutableException('IPAddress->ip is immutable once it has been set.');
        }

        /* only allow 0-9, ":", and "." ipv4: 1.1.1.1, IPv6: 1234::5678 */
        $this->attributes['ip'] = preg_replace('/[^0-9:\.]/', '', $value);
    }

    public function setLatitudeAttribute($value)
    {
        /* round to fit our database, and remove useless precision */
        $this->attributes['latitude'] = round($value, 8);
    }

    public function setLongitudeAttribute($value)
    {
        /* round to fit our database, and remove useless precision */
        $this->attributes['longitude'] = round($value, 8);
    }

    /* Functions */

    public function isResolved()
    {
        return (bool)$this->hostname;
    }

    /**
     * Will lookup geo-location of this IP Address, need to save() afterwards to keep changes.
     * @throws LogicException When this model does not have "ip" set, can not lookup what we do not have.
     * @return bool Returns true on success, false otherwise.
     */
    public function resolve()
    {
        $access_key = config('app.ipstack_key');
        if ($access_key === null) {
            /* can not resolve, so skip attempt  */
            Log::error("IPStack Access Key not configured, continuing without resolving IP Address location.");
            return true;
        }

        if (empty($this->ip)) {
            Log::error('Called IPAddress->resolve() without first setting ip.');
            throw new LogicException('IPAddress->ip must be set prior to calling IPAddress->resolve().');
        }

        if ($this->isResolved()) {
            /* if already resolved, nothing to do */
            return true;
        }

        $guzzle = new Client([
            'allow_redirects' => false,
            'base_uri'        => 'http://api.ipstack.com/',
            'timeout'         => 60.0,
        ]);

        try {
            $response = $guzzle->get($this->ip, ['query' => [
                'fields'     => 'main',
                'hostname'   => 1,
                'access_key' => $access_key,
            ]]);
            $data = json_decode((string)$response->getBody());

            if (isset($data->ip)) {
                $this->hostname  = $data->hostname;
                $this->continent = $data->continent_name;
                $this->country   = $data->country_name;
                $this->region    = $data->region_name;
                $this->city      = $data->city;
                $this->latitude  = $data->latitude;
                $this->longitude = $data->longitude;
                return true;
            } else {
                Log::warning("Response failed, ip to location, IP: {$this->ip}, Status: {$response->getStatusCode()}, Body: {$response->getBody()}");
            }
        } catch (RequestException $exception) {
            Log::warning("Request failed, ip to location, IP: {$this->ip}, Exception: {$exception}" . ($exception->hasResponse() ? ", Response: {$exception->getResponse()->getBody()}" : ''));
        }

        return false;
    }
}
