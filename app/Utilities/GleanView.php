<?php

namespace App\Utilities;

use App\Exceptions\GleanViewException;
use App\Submission;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class GleanView
{
    /** @var array */
    protected $queue;

    /** @var ResponseInterface|null */
    protected $response;

    public function __construct()
    {
        $this->queue    = [];
        $this->response = null;
    }

    /**
     * @param array $filter Something like ['email' => <some@email.address>]
     * @return array Returns array with "id" containing either a UUID or null (if none found).
     * @throws GleanViewException if anything goes wrong.
     */
    public function findFormFill(array $filter)
    {
        $this->reset();
        $this->queue[] = [
            'module'      => 'form_fill',
            'function'    => 'find',
            'record-data' => $filter,
        ];

        $record = $this->runQueued(true);

        /* expect array with "id" to contain either 36 character string or null */
        if (is_array($record) && array_key_exists('id', $record) && (strlen($record['id']) === 36 || is_null($record['id']))) {
            return $record;
        }

        $exception = new GleanViewException(null, ['form_fill::find response malformed.'], $this->response);
        Log::warning("GleanViewException: {$exception}");
        throw $exception;
    }

    public function storeFormFill(Submission $submission, array $translations = null, string $uuid_field = null, string $origin_prefix = null)
    {
        /* add message to the list of fields (to be translated) */
        $submissionFields = $submission->fields()->pluck('input', 'field')->toArray();
        $submissionFields['message'] = $submission->message;

        if ($uuid_field) {
            $submissionFields[$uuid_field] = $submission->uuid;
        }

        /* if a field is "translated" the field is renamed before being stored in GleanView */
        $fields = [];
        foreach ($submissionFields as $field => $input) {
            if (isset($translations[$field])) {
                $fields[$translations[$field]] = $input;
            } else {
                $fields[$field] = $input;
            }
        }

        /* if given an origin_prefix, include origin information in form_fill::store, blank is allowed */
        if ($origin_prefix !== null) {
            foreach ($submission->origins()->pluck('input', 'field') as $field => $input) {
                /* if field name collision, use the submission field over the origin */
                if (!array_key_exists($field, $fields)) {
                    $fields[$origin_prefix . $field] = $input;
                }
            }
        }

        $this->reset();
        $this->queue[] = [
            'module'      => 'form_fill',
            'function'    => 'store',
            'record-data' => [
                'client_source_id' => config('app.gleanview_client_source_id'),
                'form_id'          => $submission->type->external_key,
                'ip_address'       => $submission->ipAddress->ip,
                'form_data'        => $fields,
            ],
        ];

        $record = $this->runQueued(true);

        /* expect array with "inserted_count" to be greater than zero */
        if (is_array($record) && array_key_exists('inserted_count', $record) && $record['inserted_count'] > 0) {
            return $record;
        }

        $exception = new GleanViewException(null, ['form_fill::store response malformed.'], $this->response);
        Log::warning("GleanViewException: {$exception}");
        throw $exception;
    }

    /**
     * @param array $filter Something like ['email' => <some@email.address>]
     * @param array $fields Something like ['stage', 'email' => 'us']
     * @return array Returns an array with each of the provided fields present.
     * @throws GleanViewException if anything goes wrong.
     */
    public function getAccountByFormSubmission(array $filter, array $fields)
    {
        $fields = $this->expandFields($fields);
        $this->reset();
        $this->queue[] = [
            'module'     => 'form_submission',
            'function'   => 'get-record',
            'filter'     => $filter,
            'field-list' => [
                /* not sure what is with the "alias=t0" stuff */
                'account_id' => ['alias' => 't0', 'field' => 'account_id'],
            ],
            'options' => [
                /* prefixes: "!" means desc, "*" means non-empty, so we are looking for the first non-empty account_id by date_modified */
                'order' => ['*account_id', '!date_modified', '!date_entered'],
            ],
        ];

        $this->queue[] = [
            'module'   => 'account',
            'function' => 'get-record',
            'filter'   => [
                'id' => [
                    /* not sure what the expression means */
                    'source' => [
                        ['offset' => 0, 'field' => 'account_id']
                    ],
                    'expression' => '%0%',
                ],
            ],
            'field-list' => $fields,
            'related-module-list' => ['user'],
        ];

        $data = $this->runQueued();

        $record = end($data);

        /* expect array with each of the provided fields present (permitted to contain extra data) */
        if (is_array($record) && count(array_intersect_key($record, $fields)) === count($fields)) {
            return $record;
        }

        $exception = new GleanViewException(null, ['accout-by-form_submission::get-record response malformed.'], $this->response);
        Log::warning("GleanViewException: {$exception}");
        throw $exception;
    }

    /**
     * Prepare for another request to go through.
     * @return array the current queue, before resetting it.
     */
    protected function reset(): array
    {
        $queue = $this->queue;
        $this->response = null;
        $this->queue = [];
        return $queue;
    }

    /**
     * @param array $fields Something like ['stage', 'email' => 'us']
     * @return array Normalized for field-list, like ['stage' => 't0', 'email' => 'us']
     */
    protected function expandFields(array $fields) {
        $output = [];
        foreach ($fields as $index => $value) {
            /* ["a"] is converted to ["a" => "t0"] */
            [$field, $alias] = is_int($index) ? [$value, 't0'] : [$index, $value];
            $output[$field] = ['field' => $field, 'alias' => $alias];
        }
        return $output;
    }

    /**
     * @param bool $first Optional, set to true to get just the first record from the queue response.
     * @return array|null List of all response, just first response if $first is true, or null on error.
     */
    protected function runQueued(bool $first = false)
    {
        $queue = $this->reset();
        if (count($queue) < 1) {
            $exception = new GleanViewException(null, ['Nothing queued to run.']);
            Log::warning("GleanViewException: {$exception}");
            throw $exception;
        }

        Log::debug("GleanView Queue: " . json_encode($queue));

        $guzzle = new Client([
            'allow_redirects' => false,
            'base_uri'        => config('app.gleanview_url'),
            'timeout'         => 60.0,
            'headers'         => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:72.0) Gecko/20100101 Firefox/72.0', /* Firefox 72 on Windows 10 */
            ],
        ]);

        try {
            /* notice GleanView takes a "multipart/form-data" with a field "request" with a json value,
             * rather then taking a body of json directly, which can be a bit counterintuitive */
            $this->response = $guzzle->post('', ['multipart' => [[
                'name'     => 'request',
                'contents' => json_encode([
                    'credentials' => [
                        'email'    => config('app.gleanview_email'),
                        'password' => config('app.gleanview_password'),
                    ],
                    'queue' => $queue,
                ]),
            ]]]);
            $data = json_decode((string)$this->response->getBody(), true);

            Log::debug("GleanView Response: {$this->response->getBody()}");

            /* basic response validation, should always receive an array with a record for each request that was sent in the queue */
            if (isset($data) && is_array($data) && count($data) === count($queue)) {
                $output = [];

                foreach ($data as $record) {
                    /* response record validation, each should have {"code": 200, "data": <mixed>}, should have "message" if code isn't 200 */
                    if (!isset($record['code'], $record['data']) || $record['code'] !== 200) {
                        /* if any record within data is malformed or has an error code, throw an exception */
                        $this->throwException($data);
                    }

                    if ($first) {
                        /* if we only want the first piece, send it back now */
                        return $record['data'];
                    }
                    $output[] = $record['data'];
                }

                return $output;
            } else {
                $exception = new GleanViewException(null, ['Malformed response.'], $this->response);
                Log::warning("GleanViewException: {$exception}");
                throw $exception;
            }
        } catch (RequestException $exception) {
            $gvException = new GleanViewException(null, ['Invalid response.'], $this->response ?? null);
            Log::warning("GleanViewException: {$exception}");
            throw $exception;
        }
    }

    /**
     * @param array $data decode response from GleanView which we found a fault in.
     * @throws GleanViewException
     */
    protected function throwException(array $data)
    {
        $codes = [];
        $messages = [];

        foreach ($data as $record) {
            $codes[]    = $record['code']    ?? null;
            $messages[] = $record['message'] ?? 'Record without message.';
        }

        $exception = new GleanViewException($codes, $messages, $this->response);
        Log::warning("GleanViewException: {$exception}");
        throw $exception;
    }
}
