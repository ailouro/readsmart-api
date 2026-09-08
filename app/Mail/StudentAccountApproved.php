<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentAccountApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $student;
    public $defaultPassword;

    public function __construct($student, $defaultPassword)
    {
        $this->student = $student;
        $this->defaultPassword = $defaultPassword;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ReadSmart: Student Account Approved! 🎉',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.student_approved', // Gagawa tayo ng view para dito mamaya
        );
    }
}