<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use Date;
use Form;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use PragmaRX\Countries\Package\Countries;
use Schema;
use Session;
use URL;

class AjaxInstallController extends Controller {

    /**
    * NOSH ChartingSystem Schedule Ajax Functions
    */

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('csrf');
    }

    public function get_state_data(Request $request)
    {
        $country = $request->input('country');
        $states = Countries::where('name.common', $country)
            ->first()
            ->hydrateStates()
            ->states
            ->sortBy('name')
            ->pluck('name', 'postal')
            ->toArray();
        if ($country == 'Philippines') {
            $states['MNL'] = 'Metro Manila';
            asort($states);
        }
        return $states;
    }


}
