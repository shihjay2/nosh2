<?php

namespace App\Http\Middleware;

use Closure;
use DB;
use Response;
use Shihjay2\OpenIDConnectUMAClient;
use URL;

class Fhir
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
        $payload = $request->header('Authorization');
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $open_id_url = $practice->uma_uri;
        $client_id = $practice->uma_client_id;
        $client_secret = $practice->uma_client_secret;
        $url = $request->url();
        // Remove parameters if it exists
        $url_array = explode('?', $url);
        $url = $url_array[0];
        // Trim any trailing Slashes
        $url = rtrim($url, '/');
        // No RPT or inactive RPT
        $proceed = false;
        // Check first if there is a resource registered for sensitive filter
        $sens_query = DB::table('uma')->where('scope', '=', $url)->first();
        if ($sens_query) {
            $proceed = true;
            $resource_set_id = $sens_query->resource_set_id;
        } else {
            // Check if end fragment of URL is an integer and strip it out
            $path = parse_url($url, PHP_URL_PATH);
            $pathFragments = explode('/', $path);
            $end = end($pathFragments);
            if (is_numeric($end)) {
                $pathFragments1 = explode('/', $url);
                $sliced = array_slice($pathFragments1, 0, -1);
                $url = implode('/', $sliced);
            }
            $query = DB::table('uma')->where('scope', '=', $url)->first();
            if ($query) {
                $proceed = true;
                $resource_set_id = $query->resource_set_id;
            }
        }
        if ($payload) {
            // RPT, Perform Token Introspection
            $rpt = str_replace('Bearer ', '', $payload);
            $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
            $oidc->setUMA(true);
            $oidc->refreshToken($practice->uma_refresh_token);
            if ($oidc->getRefreshToken() != '') {
                $refresh_data['uma_refresh_token'] = $oidc->getRefreshToken();
                DB::table('practiceinfo')->where('practice_id', '=', '1')->update($refresh_data);
                $this->audit('Update');
            }
            $result_rpt = $oidc->introspect($rpt);
            if ($result_rpt['active'] == true) {
                if ($proceed == true) {
                    if ($result_rpt['permissions'][0]['resource_id'] == $resource_set_id) {
                        return $next($request);
                    }
                }
            }
        }
        if ($proceed == true) {
            // Look for additional scopes for resource_set_id
            $header = [
                'WWW-Authenticate' => 'UMA realm = "pNOSH_UMA", as_uri = "' . $practice->uma_uri . '"'
            ];
            $statusCode = 403;
            $query1 = DB::table('uma')->where('resource_set_id', '=', $resource_set_id)->get();
            $scopes = [];
            foreach ($query1 as $row1) {
                $scopes[] = $row1->scope;
            }
            $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
            $oidc->setUMA(true);
            $oidc->refreshToken($practice->uma_refresh_token,true);
            if ($oidc->getRefreshToken() != '') {
                $refresh_data['uma_refresh_token'] = $oidc->getRefreshToken();
                DB::table('practiceinfo')->where('practice_id', '=', '1')->update($refresh_data);
                $this->audit('Update');
            }
            $permission_ticket = $oidc->permission_request($resource_set_id, $scopes);
            if (isset($permission_ticket['error'])) {
                $response = [
                    'error' => $permission_ticket['error'],
                    'error_description' => $permission_ticket['error_description']
                ];
            } else {
                $response = [
                    'ticket' => $permission_ticket['ticket']
                ];
            }
        } else {
            $response = [
                'error' => 'invalid_scope',
                'error_description' => 'At least one of the scopes included in the request was not registered previously by this resource server.'
            ];
        }
        return Response::json($response, $statusCode, $header);
    }
}
