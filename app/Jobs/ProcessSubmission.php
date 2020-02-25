<?php

namespace App\Jobs;

use App\Submission;
use App\Traits\ExtraProcessing;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessSubmission implements ShouldQueue
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

    /**
     * Create a new job instance.
     * @param Submission $submission
     * @return void
     */
    public function __construct(Submission $submission)
    {
        $this->submission = $submission->withoutRelations();
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $details = $this->submission->calculateScore(true);

        if ($details === false) {
            Log::error("Submission->calculateScore() failed, ID: {$this->submission->id}.");
            throw new RuntimeException("Submission->calculateScore() failed, ID: {$this->submission->id}.");
        }

        $this->submission->save();

        Log::debug("Process Submission Calculated Score Successfully Score: {$this->submission->score}, ID: {$this->submission->id}.");

        $this->extraProcessing(false, $details);
    }

    public function failed(Exception $exception)
    {
        Log::alert("ProcessSubmission failed for Submission: {$this->submission->id}, Exception: {$exception->getMessage()}.");
    }
}
