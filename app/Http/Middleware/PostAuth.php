<?php

namespace App\Http\Middleware;

use Closure;
use DB;
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
        // Check if Google refresh token registered
        if ($install->google_refresh_token == '') {
            if (route('dashboard') != 'http://localhost/nosh') {
                $google = File::get(base_path() . "/.google");
                if ($google !== '') {
                    return redirect()->route('googleoauth');
                }
            }
        }
        $messages = DB::table('messaging')->where('mailbox', '=', Session::get('user_id'))->where('read', '=', null)->count();
        Session::put('messages_count', $messages);
        Session::put('notification_run', 'true');

        return $next($request);
    }
}
