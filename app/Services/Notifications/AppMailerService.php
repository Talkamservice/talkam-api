<?php

namespace App\Services\Notifications;

use App\Helpers\MethodsHelper;
use App\Jobs\AppMailerJob;

class AppMailerService
{
    /**
     * Global email helper
     *  @param $params['data']           = ['foo' => 'Hello John Doe!']; //optional
     *  @param  $params['to']*             = 'recipient@example.com'; //required
     *  @param  $params['template_type']  = 'markdown';  //default is view
     *  @param  $params['template']*       = 'emails.app-mailer'; //path to the email template
     *  @param  $params['subject']*        = 'Some Awesome Subject'; //required
     *  @param  $params['from_email']     = 'johndoe@example.com';
     *  @param  $params['from_name']      = 'John Doe';
     *  @param  $params['cc_emails']      = ['email@mail.com', 'email1@mail.com'];
     *  @param  $params['bcc_emails']      = ['email@mail.com', 'email1@mail.com'];
     */
    public static function send(array $data)
    {
        try {
            MethodsHelper::dispatchJob(new AppMailerJob($data));
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Global email helper
     *
     * @param  $params['data']  = ['foo' => 'Hello John Doe!']; //optional
     * @param  $params['to']*  = 'recipient@example.com'; //required
     * @param  $params['template_type']  = 'markdown';  //default is view
     * @param  $params['template']*  = 'emails.app-mailer'; //path to the email template
     * @param  $params['subject']*  = 'Some Awesome Subject'; //required
     * @param  $params['from_email']  = 'johndoe@example.com';
     * @param  $params['from_name']  = 'John Doe';
     * @param  $params['cc_emails']  = ['email@mail.com', 'email1@mail.com'];
     * @param  $params['bcc_emails']  = ['email@mail.com', 'email1@mail.com'];
     */
    public static function sendNow(array $data)
    {
        try {
            MethodsHelper::dispatchJobSync(new AppMailerJob($data));
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
