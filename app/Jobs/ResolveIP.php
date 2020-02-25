<?php

namespace App\Jobs;

use App\{IPAddress, Submission};
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ResolveIP implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** attempt this job up to 5 times */
    public $tries = 5;

    /** each job attempt may take up to 2 minutes */
    public $timeout = 120;

    /** if this attempt fails, wait 1 second before trying again */
    public $retryAfter = 1;

    protected $ipAddress;

    protected $submission;

    /**
     * Create a new job instance.
     * @param IPAddress $ipAddress
     * @param Submission $submission
     * @return void
     */
    public function __construct(IPAddress $ipAddress, Submission $submission)
    {
        $this->ipAddress  = $ipAddress->withoutRelations();
        $this->submission = $submission->withoutRelations();
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        $success = $this->ipAddress->resolve();

        if (!$success) {
            Log::error("IPAddress->resolve() failed, IP: '{$this->ipAddress->ip}', ID: {$this->ipAddress->id}");
            throw new RuntimeException("IPAddress->resolve() failed, IP: '{$this->ipAddress->ip}', ID: {$this->ipAddress->id}");
        }

        $this->ipAddress->save();

        Log::debug("Resolve IP Successful IP: {$this->ipAddress->ip}, ID: {$this->ipAddress->id}.");

        dispatch(new ProcessSubmission($this->submission));
    }

    public function failed(Exception $exception)
    {
        Log::alert("ResolveIP failed for ID: {$this->ipAddress->id}, Exception: {$exception->getMessage()}.");
    }
}
