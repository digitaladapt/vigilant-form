<?php

namespace App\Jobs;

use App\Submission;
use App\Traits\ExtraProcessing;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReprocessSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use ExtraProcessing;

    /** attempt this job up to 12 times */
    public $tries = 12;

    /** each job attempt may take up to 5 minutes */
    public $timeout = 300;

    /** if this attempt fails, wait 30 seconds before trying again */
    public $retryAfter = 30;

    protected $submission;

    protected $new_grade;

    protected $updated_at;

    /**
     * Create a new job instance.
     * @param Submission $submission
     * @return void
     */
    public function __construct(Submission $submission, string $new_grade, CarbonInterface $updated_at)
    {
        $this->submission = $submission->withoutRelations();
        $this->new_grade = $new_grade;
        $this->updated_at = $updated_at;
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        if ($this->submission->updated_at > $this->updated_at) {
            /* if this job was cancelled */
            Log::info("Job Cancelled, Submission: {$this->submission->id} From-Grade: '{$this->submission->grade}', To-Grade: '{$this->new_grade}'.");
        } else {
            /* if this job was NOT cancelled */
            $this->submission->grade = $this->new_grade;

            $this->extraProcessing(true, ['[reprocessing] grade was manually set.']);

            /* only save changes if the extra processing did not throw an exception */
            $this->submission->save();
        }
    }

    public function failed(Exception $exception)
    {
        Log::alert("ReprocessSubmission failed for Submission: {$this->submission->id}, Grade: {$this->new_grade}, Exception: {$exception->getMessage()}.");
    }
}
