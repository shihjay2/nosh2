<?php

namespace App\Http\Middleware;

use Closure;
use DB;
use File;
use Response;
use Session;
use URL;

class PostAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $install = DB::table('practiceinfo')->first();
        // Check if e-mail service is setup
        if (env('MAIL_HOST') == 'mailtrap.io') {
            return redirect()->route('setup_mail');
        }
        // Check if Google refresh token registered if Google is used as e-mail service
        if (env('MAIL_HOST') == 'smtp.gmail.com') {
            if ($install->google_refresh_token == '') {
                if (route('dashboard') != 'http://localhost/nosh') {
                    return redirect()->route('googleoauth');
                }
            }
        }
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        // Migrate old NOSH standard template to current
        if ($practice->encounter_template == 'standardmedical' || $practice->encounter_template == 'standardmedical1') {
            $update['encounter_template'] = 'medical';
            DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->update($update);
        }
        $messages = DB::table('messaging')->where('mailbox', '=', Session::get('user_id'))->where('read', '=', null)->count();
        Session::put('messages_count', $messages);
        Session::put('notification_run', 'true');
        // Check if pNOSH for provider that patient's demographic supplementary tables exist
        if (Session::get('patient_centric') == 'yp') {
            $relate = DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->first();
            if (!$relate) {
                $data1 = [
    				'billing_notes' => '',
    				'imm_notes' => '',
    				'pid' => Session::get('pid'),
    				'practice_id' => Session::get('practice_id')
    			];
    			DB::table('demographics_notes')->insert($data1);
            }
        }
        return $next($request);
    }
}
