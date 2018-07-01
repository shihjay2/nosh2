<?php

namespace App\Http\Middleware;

use Artisan;
use Closure;
use DB;
use Symfony\Component\Process\Process;
use Response;
use Schema;
use URL;

class CheckInstall
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
        // Check Database connection
        $env_file = base_path() . '/.env';
        if (file_exists($env_file)) {
            if (env('DB_DATABASE') == 'homestead') {
                return redirect()->route('update_env');
            } else {
                try {
                    DB::connection()->getPdo();
                } catch (\Exception $e) {
                    return redirect()->route('install_fix');
                }
            }
        } else {
            return redirect()->route('update_env');
        }

        // Check if version file exists
        if (!file_exists(base_path() . '/.version')) {
            return redirect()->route('set_version');
        }

        // Chcek if needing installation
        $install = DB::table('practiceinfo')->first();
        if (!$install) {
            if (file_exists(base_path() . '/.patientcentric')) {
                return redirect()->route('install', ['patient']);
            } else {
                return redirect()->route('install', ['practice']);
            }
        }

        // Check for updates
        define('STDIN',fopen("php://stdin","r"));
        if (!Schema::hasTable('migrations')) {
            $migrate = new Process("php artisan migrate:install --force");
            $migrate->setWorkingDirectory(base_path());
            $migrate->setTimeout(null);
            $migrate->run();
            // Artisan::call('migrate:install', ['--force' => true]);
        }
        $migrate1 = new Process("php artisan migrate --force");
        $migrate1->setWorkingDirectory(base_path());
        $migrate1->setTimeout(null);
        $migrate1->run();
        // Artisan::call('migrate', ['--force' => true]);
        $current_version = "2.0.0";
        if ($install->version < $current_version) {
            return redirect()->route('update_install');
        }

        // Check if OpenID Connect beta testing and register if not yet
        // if (route('dashboard') == 'https://hieofone.com/nosh' || route('dashboard') == 'https://cloud.noshchartingsystem.com/nosh' || route('dashboard') == 'https://noshchartingsystem.com/nosh' || route('dashboard') == 'https://www.noshchartingsystem.com/nosh' || route('dashboard') == 'https://shihjay.xyz/nosh' || route('dashboard') == 'https://agropper.xyz/nosh') {
            // if ($install->openidconnect_client_id == '') {
                // return redirect()->route('oidc_register_client');
            // }
        // }

        // Check if Google file for sending email via Gmail exists and transion to new e-mail
        $google_file = base_path() . '/.google';
        if (file_exists($google_file)) {
            return redirect()->route('google_start');
        }

        // Check if pNOSH instance
        if ($install->patient_centric == 'y') {
            if ($install->uma_refresh_token == '') {
                return redirect()->route('uma_patient_centric');
            }
        }

        return $next($request);
    }
}
