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

    // public function __construct()
    // {
    //     if (getenv('TRUSTED_PROXIES')) {
    //         if (env('TRUSTED_PROXIES') !== null) {
    //             if (env('TRUSTED_PROXIES') == '*') {
    //                 $this->proxies = '*';
    //             } else {
    //                 $this->proxies = explode(',', env('TRUSTED_PROXIES'));
    //             }
    //         } else {
    //             throw new NotFoundException();
    //         }
    //     } else {
    //         $data = ['TRUSTED_PROXIES' => ''];
    //         $env = file_get_contents(base_path() . '/.env');
	// 		$env = preg_split('/\s+/', $env);;
	// 		foreach((array)$data as $key => $value){
    //             $new = true;
	// 			foreach($env as $env_key => $env_value){
	// 				$entry = explode("=", $env_value, 2);
	// 				if($entry[0] == $key){
	// 					$env[$env_key] = $key . "=" . $value;
    //                     $new = false;
	// 				} else {
	// 					$env[$env_key] = $env_value;
	// 				}
	// 			}
    //             if ($new == true) {
	// 				$env[$key] = $key . "=" . $value;
	// 			}
	// 		}
	// 		$env = implode("\n", $env);
	// 		file_put_contents(base_path() . '/.env', $env);
    //         throw new NotFoundException();
    //     }
    // }
}
