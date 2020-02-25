<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait ExtraProcessing
{
    protected function extraProcessing($reprocess = false, array $details = null)
    {
        if (is_null($details)) {
            $details = [];
        }

        $file = config("app.{$this->submission->grade}_file");
        if ($file) {
            $file = base_path($file);
            if (is_file($file)) {
                $this->submission->fresh(); /* after touch() or save(), need to fresh() before generating submission urls */
                $success = $this->isolation($file, $reprocess, $details);
                if ($success) {
                    Log::debug("Submission Extra Successfully Completed Grade: '{$this->submission->grade}', ID: {$this->submission->id}.");
                } else {
                    Log::error("Submission Extra Cancelled Grade: '{$this->submission->grade}', ID: {$this->submission->id}.");
                }
            } else {
                Log::error("Include File Missing: '{$file}'.");
            }
        } else {
            Log::error("Configuration Missing: 'app.{$this->submission->grade}_file'.");
        }
    }

    /**
     * Include the given file, with our submission exposed as a global, then cleanup.
     * @param string $file
     * @param bool $isReprocess
     * @param array $theDetails
     * @return bool|mixed Returns whatever the file returned, which should be a bool.
     * @see https://www.php.net/manual/en/function.unset.php
     */
    protected function isolation(string $file, bool $isReprocess, array $theDetails)
    {
        /* expose the submission as $submission, just for our include, then cleanup */
        global $submission;
        global $reprocess;
        global $details;

        $submission = $this->submission;
        $reprocess  = $isReprocess;
        $details    = $theDetails;

        $result = include $file;

        unset($GLOBALS['submission']);
        unset($GLOBALS['reprocess']);
        unset($GLOBALS['details']);

        return $result;
    }
}
