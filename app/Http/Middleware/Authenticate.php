<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use Illuminate\Support\Facades\Auth;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->guest()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response('Unauthorized.', 401);
            } else {
                $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                if ($practice->patient_centric == 'y' || $practice->patient_centric == 'yp') {
                    return redirect()->guest('uma_auth');
                } else {
                    return redirect()->guest('login');
                }
            }
        }
        return $next($request);
    }
}
