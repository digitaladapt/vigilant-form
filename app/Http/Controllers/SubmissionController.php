<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmissionStoreRequest;
use App\Jobs\ReprocessSubmission;
use App\Submission;
use App\Traits\RegradeEvent;
use App\Utilities\{Discord, Functions};
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    use RegradeEvent;

    /**
     * Store a newly created resource in storage.
     * SubmissionStoreRequest will block any request which failed authorization or validation.
     * @param  \App\Http\Requests\SubmissionStoreRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SubmissionStoreRequest $request)
    {
        $data = $request->validated();
        $submission = Submission::createByForm($data['fields'], $data['meta'], $data['source'], $data['links']);
        if ($submission->id > 0 && $submission->isClean()) {
            return [
                'message' => 'Submission successfully stored.',
                'success' => true,
                'submission' => $submission->uuid,
            ];
        }
        return [
            'message' => 'A failure has occurred.',
            'errors' => ['error' => 'Unknown error, please review the application log.'],
        ];
    }

    /**
     * Update the specified resource in storage.
     * @param  string  $updated_at In the url-friendly format: "Ymd-His-u"
     * @param  string  $uuid
     * @param  string  $grade
     * @param  string  $abort
     * @return \Illuminate\Http\Response
     */
    public function update($updated_at, $uuid, $grade, $abort = null)
    {
        $submission = Submission::where('uuid', $uuid)->first();

        /* if given uuid is invalid */
        if (!$submission) {
            return view('submisson.update', ['message' => 'Unable to locate submission.']);
        }

        /* if abort is requested */
        if ($abort) {
            /* update timestamp to stop the queued job */
            $submission->touch();

            /* announce that someone cancelled the regrade job */
            $this->regradeEvent($submission, $grade, true);

            return view('submission.update', ['message' => "Successfully **cancelled** changing grade from **{$submission->grade}** to **{$grade}.**"]);
        }

        /* if this will have no effect */
        if ($submission->grade === $grade) {
            return view('submission.update', ['message' => "Submission already graded as **{$grade}**, _no change is needed._"]);
        }

        if ($submission->updated_at_url() !== $updated_at) {
            return view('submission.update', [
                'message'  => "Someone has modified the submission already,\n\ndo you want to continue changing grade from **{$submission->grade}** to **{$grade}?**",
                'continue' => Functions::submissionRegradeUrl($submission, $grade),
            ]);
        }

        /* announce that someone clicked on a regrade link */
        $this->regradeEvent($submission, $grade);

        $submission->touch();
        $submission->fresh(); /* after touch() or save(), need to fresh() before generating submission urls */

        ReprocessSubmission::dispatch($submission, $grade, $submission->updated_at)->delay(now()->addSeconds(16));

        return view('submission.update', [
            'message' => "Submission will be updated from **{$submission->grade}** to **{$grade}**;\n\nif that was an accident, _there is a cancel button below._",
            'cancel'  => Functions::submissionRegradeUrl($submission, $grade, true),
        ]);
    }
}
