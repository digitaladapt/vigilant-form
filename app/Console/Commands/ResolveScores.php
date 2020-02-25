<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSubmission;
use App\Submission;
use Illuminate\Console\Command;

class ResolveScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resolve:scores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resolve any outstanding Scores.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $submissions = Submission::where('grade', 'ungraded')->get();
        foreach ($submissions as $submission) {
            dispatch(new ProcessSubmission($submission));
        }
    }
}
