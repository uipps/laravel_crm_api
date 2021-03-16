<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data_arr;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        //
        $this->data_arr = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        $request = $this->data_arr;
        try {
            Mail::send('emails.create_staff', $request, function ($sendMail) use ($request) {
                $sendMail->to($request['email'])->subject('CRM系统初始密码');
            });
        } catch (\Exception $e) {
            \Log::error('send mail error! ' . print_r(Mail::failures(), true) . ' ' . $e->getMessage());
        }
    }
}
