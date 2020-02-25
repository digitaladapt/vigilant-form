<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class SubmissionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        /* request must have auth.id and auth.secret, and they must match our config */
        $auth = $this->post('auth');
        $id = $auth['id'] ?? false;
        $secret = $auth['secret'] ?? false;

        return ($id && $secret && $id === config('app.client_id') && $secret === config('app.client_secret'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'fields'   => 'required|array|min:1|max:100',
            'fields.*' => 'nullable|string', /* $_POST works, each html form field should exist (nullable) */

            'auth'        => 'required|array',
            'auth.id'     => 'required|string', /* should match .env CLIENT_ID */
            'auth.secret' => 'required|string', /* should match .env CLIENT_SECRET */

            'meta'                => 'required|array',
            'meta.ip_address'     => 'required|ip', /* either IPv4 or IPv6 */
            'meta.user_agent'     => 'nullable|string',
            'meta.http_headers'   => 'required|array|min:1|max:100', /* if no http headers, use: [''] */
            'meta.http_headers.*' => 'nullable|string', /* taken directly from $_SERVER */
            'meta.honeypot'       => 'required|boolean', /* true means they fell for the honeypot */
            'meta.duration'       => 'required|numeric|min:-2|max:2592000', /* time in seconds up to 30 days */
            /* note: sql time type has limit of about 34 days */
            /* -1.0 second is to indicate that no duration was able to be calculated */

            'source'         => 'required|array',
            'source.website' => 'required|string', /* name of website which submitted the form */
            'source.title'   => 'required|string', /* name of the form within that website */

            'links'          => 'required|array',
            'links.referral' => 'nullable|string', /* full uri of referring website (nullable) */
            'links.landing'  => 'nullable|string', /* full uri of first page on this website */
            'links.submit'   => 'nullable|string', /* full uri of page which had form */
        ];
    }
}
