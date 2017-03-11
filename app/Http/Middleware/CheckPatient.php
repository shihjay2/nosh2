<?php

namespace App\Http\Middleware;

use Closure;

class CheckPatient
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
        if ($request->session()->has('pid')) {
            return $next($request);
        } else {
            return redirect()->route('dashboard');
        }
    }
}
