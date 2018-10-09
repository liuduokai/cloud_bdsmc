<?php

namespace App\Events;

class SmsEvent extends Event
{
    public $msg;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($msg)
    {
        //
        $this->msg = $msg;
    }
}
