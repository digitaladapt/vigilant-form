<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubmissionProcessed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @var string */
    public $subject;

    /** @var array ['name' => '', 'url' => '', 'icon_url' => ''] */
    public $author;

    /** @var string */
    public $title;

    /** @var string */
    public $description;

    /** @var array ['field' => 'value', ...] */
    public $fields;

    /** @var array ['list of strings', ...] */
    public $details;

    /** @var array ['field' => 'value', ...] */
    public $meta;

    /** @var array ['url' => 'title', ...] */
    public $links;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subject)->view('emails.submission.processed');
    }
}
