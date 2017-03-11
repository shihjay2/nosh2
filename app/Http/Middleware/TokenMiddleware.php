<?php

namespace App\Http\Middleware;

use Closure;
use DB;
use Response;

class TokenMiddleware
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
      $error_array = [];
      if ($request->header('Authorization')) {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $query = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
        if ($query) {
          // Check if not expired
          $expires = strtotime($query->expires);
          if ($expires > time()) {
            return $next($request);
          } else {
            $error_array[] = [
              'invalid_token' => 'Token provided was expired'
            ];
          }
        } else {
          $error_array[] = [
            'invalid_token' => 'Token provided was invalid'
          ];
        }
      } else {
        $error_array[] = [
          'no_token' => 'No authentication token was provided in request'
        ];
      }
      $error = [
        'errors' => $error_array
      ];
      return Response::json($error, 401);
    }
}
