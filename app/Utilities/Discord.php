<?php

namespace App\Utilities;

use App\Utilities\Functions;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use LogicException;

class Discord
{
    /** @var string */
    protected $webhook;

    /** @var int */
    protected $color;

    /** @var bool */
    protected $sent;

    /** @var array ['name' => '', 'url' => '', 'icon_url' => ''] */
    public $author;

    /** @var string */
    public $title;

    /** @var string */
    public $description;

    /** @var array ['field' => 'value', ...] */
    public $fields;

    /** @var array ['list of strings', ...] */
    public $details;

    /** @var array ['field' => 'value', ...] */
    public $meta;

    /** @var array ['url' => 'title', ...] */
    public $links;

    /**
     * @param string $webhook url to submit to discord.
     * @param string $color 6-digit HEX color code (without "#").
     */
    public function __construct(string $webhook, string $color = null)
    {
        $this->webhook = $webhook;
        $this->color   = is_string($color) ? hexdec($color) : null;
        $this->sent    = false;

        if ($this->color < 0 || $this->color > 16777215) {
            $this->color = null;
        }
    }

    public function send(): bool
    {
        if ($this->sent) {
            throw new LogicException('A discord message can only be sent one time.');
        }

        $embeds = [[]];
        if ($this->author) {
            $embeds[0]['author'] = $this->author;
        }
        if ($this->title) {
            $embeds[0]['title'] = $this->title;
        }
        if ($this->description) {
            $embeds[0]['description'] = $this->description;
        }
        if ($this->fields) {
            /* Discord doesn't allow value to be null, so we will remove the nulls */
            $embeds[0]['fields'] = Functions::arrayToColumns(array_filter($this->fields, function ($value) {
                return $value !== null;
            }), 'name', 'value', ['inline' => true]);
        }
        if ($this->details) {
            $embeds[1] = ['description' => implode("\n", $this->details)];
        }
        if ($this->meta) {
            if (!isset($embeds[1])) {
                $embeds[1] = [];
            }
            /* Discord doesn't allow value to be null, so we will remove the nulls */
            $embeds[1]['fields'] = Functions::arrayToColumns(array_filter($this->meta, function ($value) {
                return $value !== null;
            }), 'name', 'value', ['inline' => true]);
        }
        if ($this->links) {
            foreach ($this->links as $url => $title) {
                $embeds[] = ['title' => $title, 'url' => $url];
            }
        }

        /* apply a color to the first (main) embed of each message, so they group together nicely */
        if (!is_null($this->color) && count($embeds) > 0) {
            $embeds[0]['color'] = $this->color;
        }

        $guzzle = new Client([
            'allow_redirects' => false,
            'base_uri'        => $this->webhook,
            'timeout'         => 60.0,
        ]);

        Log::debug("Discord submission: " . json_encode(['embeds' => $embeds]));

        try {
            $response = $guzzle->post('?wait=true', ['json' => [
                'embeds' => $embeds,
            ]]);
            $data = json_decode((string)$response->getBody());

            if (isset($data->id)) {
                /* sucessfully sent */
                $this->sent = true;
                return true;
            } else {
                Log::warning("Response failed, send discord webhook, Message: " . json_encode($this) . ", Status: {$response->getStatusCode()}, Body: {$response->getBody()}");
            }
        } catch (RequestException $exception) {
            Log::error("Request failed, send discord webhook, Message: " . json_encode($this) . ", Exception: {$exception}" . ($exception->hasResponse() ? ", Response: {$exception->getResponse()->getBody()}" : ''));
        }

        return false;
    }
}
