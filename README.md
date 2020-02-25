# vigilant-form
for Form Scoring and Processing.

*Work In Progress*, functional and actively in use in production, but needs clean up and more documentation.

## So what is VigilantForm?
VigilantForm is my attempt to keep junk form submissions from getting put in my CRM.

So rather then putting form submissions directly into my CRM, I push them to VigilantForm.

VigilantForm scores the submission, based on whatever scoring logic you choose; some examples include:
* checking if they passed the honeypot test
* checking how quickly the form was submitted
* checking if the email is valid (syntax and dns check)
* checking if the phone number is reasonable
* checking if required fields are filled out
* checking origin of the ip-address (via ipstack.com)
* looking for bad input, like "http://" in the name field
* and so on

After scoring is complete, the form submission is graded, and you can take different custom actions depending on the grade.

For example, I push quality form submissions into my CRM, but form submissions which need review go to Discord, with links to approve/reject;
meanwhile junk form submissions get logged to a file for periodic review, and spam form submsissions quietly get trashed.

The process of looking up geo-location of the ip address, calcuating the score, and running the custom processing is asynchronous from the form submission.
So the API for storing a form submission should always returns promptly, and the extra processing can start being worked on nearly instantaneously.

## So how do you set it up?
Create the project, configure the database and access keys, migrate the database, queue worker, setup apache/nginx, and customize process and scoring files to suite needs.

```bash
composer create-project digitaladapt/vigilant-form <DESTINATION_FOLDER>
```

Within the `.env` file, update the `DB_*` values to the database you want to use.

You also need set `CLIENT_ID` and `CLIENT_SECRET` to long unique strings, which you will need when you go to store a form submission into the system.

If you want to lookup geo-location of the IP Addresses of form submissions, setup a (free or paid) access key with ipstack.com, which goes into `IPSTACK_key`.
Otherwise you can not score based on continent/country/region.

Create the database tables by running the migrate command:
```bash
php artisan migrate
```

You'll also need to setup a queue worker: https://laravel.com/docs/6.x/queues
Recommend you use redis and set `QUEUE_CONNECTION` to "redis", but the default database will work just fine.
While it's strongly recommended to come up with a way to ensure the queue:work process is always running,
a simple cron can be a nice quick way to test things out, before setting up something more robust:
```
* * * * * php /path/to/project/artisan queue:work --stop-when-empty
```

Setup your web server, root directory is the public folder within the project.
if you are using nginx, add a location fallback of index.php, like so:
```nginx
location / {
    try_files $uri $uri/ /index.php?$request_uri;
}
```

If you visit the project in your web browser, you should expect to get a 405 Method Not Allowed.
The root route only permits POST requests, for storing form submissions.

Websites which want to push form submissions into your instance of vigilant-forms can use the library digitaladapt/vigilant-form-kit.
https://packagist.org/packages/digitaladapt/vigilant-form-kit
Which will help you collect and submit the form and meta data.
The API requirements for vigilant-form submissions can be found in app/Http/Requests/SubmissionStoreRequest.php.

#### Notes
* Anytime you modify any of the scoring or process php files, you should restart your queue `php artisan queue:restart`, otherwise your changes will not be fully realized.
* Within the database, an IP Address record can be reused across multiple form submissions, to avoid repeatedly looking up the same inforamtion; as a result, the ip field is immutable once set.
* You should monitor for job failures `php artisan queue:failed` and strongly recommend you setup `LOG_SLACK_WEBHOOK_URL` to have important logs sent to Slack/Discord.

### How Processing Works
By default within the process folder, there is a php file for each grade that can result from grading.
Those files by default do nothing, but can be leveraged to perform whatever operations are desired upon a form submission being given a particular grade.
Given that it is a php file within a Laravel framework project, you can write whatever code you need, but some utilities have been included.

For example, you could send the details of a submission via a Discord webhook and Email:
Make sure to update your .env for sending emails, before attempting to use the Mail Facade.
```php
use App\Mail\SubmissionProcessed;
use App\Utilities\Discord;
use Illuminate\Support\Facades\Mail;

/* use global to access the submission */
global $submission;

/* use global to access reprocess (set to true if submissions grade was overridden by user action) */
global $reprocess;

/* use global to access details (array which scoring rules applied to the submission, may be empty) */
global $details;

$color     = 'ff0000'; /* red */
$webhook   = 'https://discordapp.com/api/webhooks/<YOUR_WEBHOOK_URL>';
$toEmails  = [
    '<YOUR_EMAIL_ADDRESS>',
];

/* notification information */
$title       = $submission->type->websiteTitle();
$description = $submission->message;
$fields      = $submission->fields()->scoring()->pluck('input', 'field')->toArray();
$meta        = [
    'timestamp'   => $submission->created_at->format('D, M jS, Y \a\t g:ia'),
    'ip_address ' => $submission->ipAddress->ip,
    'ip_location' => "{$submission->ipAddress->country} > {$submission->ipAddress->region}",
    'uuid'        => $submission->uuid,
] + $submission->origins()->whereIn('field', [
    'utm_source', 'utm_term', 'utm_campaign', 'utm_adgroup', 'utm_medium',
])->pluck('input', 'field')->toArray();

/* send notification via chat */
$discord = new Discord($webhook, $color);
$discord->title       = $title;
$discord->description = $description;
$discord->fields      = $fields;
$discord->details     = $details;
$discord->meta        = $meta;
$discord->send(); /* sent in real-time */

/* also queue up the email, but only if not reprocessing */
if (!$reprocess) {
    $email = new SubmissionProcessed();
    $email->subject     = 'New Submission Arrived';
    $email->title       = $title;
    $email->description = $description;
    $email->fields      = $fields;
    $email->details     = $details;
    $email->meta        = $meta;
    Mail::to($toEmails)->queue($email); /* queued as separate job */
}

return true;
```

### How Scoring Works
The higher the score, the more likely the content is spam/junk.
The score gets broken down into 5 grades:
* perfect: score      0 to         9
* quality: score     10 to        99
* review:  score    100 to       999
* junk:    score  1,000 to     9,999
* ignore:  score 10,000 to 1,000,000

The scoring.php file determines what logic is applied to determine the score for each submission.
The file returns an array of rules, allowing you to specify which fields or property to check, what type of check to perform, and how many points to add to the score, if the submission matches.
It is also possible to limit the maximum score, if desired, and rules may also have negative scoring, improving the score for good content.

each rule must contain:
"name" what you want to call this rule,
"score" which determines how many points are given for each volation,
either "fields" or "property" (what data to review),
* "fields" either an array of field names, or true to mean all fields (which also includes the "message" property).
* "property" is a string in the dot "." notation of the desired property "ipAddress.country" "duration", "honeypot", etc.
"check", which must be one of the following:
* "regexp"            ("values" string, no "%" allowed, to make regex work between php/sql there must be a reserved delimiter),
* "not_regexp"        ("values" string, no "%" allowed, to make regex work between php/sql there must be a reserved delimiter),
* "regexp_count_over" ("values" array with regexp string and count, if regexp occurs over count times, the rule is triggered),
* "contains"          ("values" array of strings),
* "ends_with"         ("values" array of strings),
* "is_bool"           ("values" true/false),
* "is_empty"          ("values" *not* required, only counts when field was offered in form, but not filled out),
* "email"             ("values" *not* required, checks email address and performs simple dns mx check),
* "missing"           ("values" array of strings),
* "less_than"         ("values" a string, for property "duration" only),
* "length_under"      ("values" number of letters),
* "length_over"       ("values" number of letters),
"values" (except when "check" is "is_empty" or "email") is required and should correspond to the check specified.

optionally a "limit" (integer) may be specified, if the rule matches, the maximum score that the form submission can be is the given limit.

Note: *all* checks performed are case-insensative (including regexp),
also note *all* checks work on utf-8 data (length is characters not bytes),
finally: for all field checks, if the form didn't contain the specified field, the submission can *not* match the rule by that field (ever);
this means that only some forms ask for a field, a rule only applies to submissions from forms which contained that field.

Example: two forms, both have "email", but only one has "phone", a rule which gives points for a
"missing" "phone" will not give points to any submission from the form without a "phone" field.

Example scoring.php:
```php
return [
    [
        // if either field contains either string, the submission will be graded as "ignore"
        'name'   => 'name/org has url',
        'score'  => 10000,
        'fields' => ['full_name', 'company'],
        'check'  => 'contains',
        'values' => ['http://', 'https://'],
    ],
    [
        // if the email field is not a valid email, the submission will be graded as "junk"
        'name'   => 'email is invalid',
        'score'  => 1000,
        'fields' => ['email'],
        'check'  => 'email',
    ],
    [
        // if the phone field fails the regular expression, the submission will be graded as "review"
        // this regexp assumes phone is unformatted and is a 10 digit North American phone number.
        // see: https://en.wikipedia.org/wiki/North_American_Numbering_Plan
        'name'   => 'phone is invalid',
        'score'  => 100,
        'fields' => ['phone'],
        'check'  => 'not_regexp',
        'values' => '^[2-9][0-9]{2}[2-9][0-9]{6}$',
    ],
    [
        // if any field is empty, the submission will be graded as "quality"
        'name'   => 'a field was left empty',
        'score'  => 10,
        'fields' => true, /* all fields (including property "message") */
        'check'  => 'is_empty',
    ],

    // if a submission passes all of these rules it will be graded as "perfect"

    // it is also possible to improve the score with rules that improve the score
    [
        // if the ip geo-location country is USA, the submission may be upgraded from "quality" back to "perfect"
        'name'     => '[positive] ip_address country is USA',
        'score'    => -10,
        'property' => 'ipAddress.country',
        'check'    => 'contains',
        'values'   => ['United States'],
    ],

    // it is also possible to limit the maximum score
    [
        // if form submission seems to have originated from paid marketing, the submission may be upgraded
        // from "review"/"quality" to "perfec" and disallow grades of "ignore" and "junk".
        'name'     => '[positive] has utm_source',
        'score'    => -100,
        'property' => 'hasUtmSource',
        'check'    => 'is_bool',
        'values'   => true,
        'limit'    => 999,
    ],
];
```
