<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    public function __construct()
    {
        if (env('TRUSTED_PROXIES') !== null) {
            if (env('TRUSTED_PROXIES') == '*') {
                $this->proxies = '*';
            } else {
                $this->proxies = explode(',', env('TRUSTED_PROXIES'));
            }
        }
    }
}
