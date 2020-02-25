<?php

namespace App\Traits;

use App\Submission;
use Exception;

trait RegradeEvent
{
    protected function regradeEvent(Submission $submission, string $grade, bool $cancel = false)
    {
        $file = config('app.regrade_file');
        if ($file) {
            $file = base_path($file);
            if (is_file($file)) {
                try {
                    $this->isolation($file, $submission, $grade, $cancel);
                } catch (Exception $exception) {
                    /* do nothing */
                }
            } else {
                Log::error("Include File Missing: '{$file}'.");
            }
        } else {
            Log::error("Configuration Missing: 'app.regrade_file'.");
        }
    }

    /**
     * Include the given file, with our submission exposed as a global, then cleanup.
     * @param string $file
     * @param Submission $theSubmission
     * @param string $theGrade
     * @param bool $isCancel
     * @see https://www.php.net/manual/en/function.unset.php
     */
    protected function isolation(string $file, Submission $theSubmission, string $theGrade, bool $isCancel)
    {
        /* expose the submission as $submission, just for our include, then cleanup */
        global $submission;
        global $grade;
        global $cancel;

        $submission = $theSubmission;
        $grade      = $theGrade;
        $cancel     = $isCancel;

        include $file;

        unset($GLOBALS['submission']);
        unset($GLOBALS['grade']);
        unset($GLOBALS['cancel']);
    }
}
