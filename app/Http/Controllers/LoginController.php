<?php

namespace App\Http\Controllers;

use App;
use App\Http\Controllers\Controller;
use App\User;
use Artisan;
use Auth;
use DB;
use File;
use Google_Client;
use Hash;
use HTML;
use Illuminate\Http\Request;
use Minify;
use Response;
use Session;
use Socialite;
use Storage;
use URL;
use phpseclib\Crypt\RSA;
use SimpleXMLElement;
use Shihjay2\OpenIDConnectUMAClient;
use GuzzleHttp;

class LoginController extends Controller {

    /**
    * Authentication of users
    */

    public function accept_invitation(Request $request, $id)
    {
        $query = DB::table('users')->where('password', '=', $id)->first();
        if ($query) {
            // $expires = strtotime($query->created_at) + 86400;
            // if ($expires > time() || $query->created_at == null) {
                if ($request->isMethod('post')) {
                    $this->validate($request, [
                        'username' => 'unique:users,username',
                        'password' => 'min:4',
                        'confirm_password' => 'min:4|same:password',
                        'secret_question' => 'required',
                        'secret_answer' => 'required'
                    ]);
                    if ($request->input('username') == '') {
                        $data['username'] = $this->gen_uuid();
                        $data['password'] = sha1($username);
                    } else {
                        $data['username'] = $request->input('username');
                        $data['password'] = substr_replace(Hash::make($request->input('password')),"$2a",0,3);
                        $data['secret_question'] = $request->input('secret_question');
                        $data['secret_answer'] = $request->input('secret_answer');
                    }
                    if ($query->created_at == null) {
                        $data['created_at'] = date('Y-m-d H:i:s');
                    }
                    DB::table('users')->where('password', '=', $id)->update($data);
                    $this->audit('Update');
                    Session::put('message_action', 'Your account has been activated.  Please log in');
                    return redirect()->route('login');
                } else {
                    $data['code'] = $id;
                    $data['assets_js'] = $this->assets_js();
                    $data['assets_css'] = $this->assets_css();
                    return view('accept_invite', $data);
                }
            // } else {
            //     $error = 'Your invitation code expired.';
            //     return $error;
            // }
        } else {
            $error = 'Your invitation code is invalid';
            return $error;
        }
    }

    public function api_check(Request $request, $practicehandle)
	{
		if ($practicehandle == '0') {
			$query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		} else {
			$query = DB::table('practiceinfo')->where('practicehandle', '=', $practicehandle)->first();
		}
		$result = 'No';
		if ($query) {
			if (Schema::hasColumn('practiceinfo', 'practice_api_key')) {
				$result = 'Yes';
			}
		}
		return $result;
	}

    public function api_login(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('npi', '=', $data['npi'])->where('practice_api_key', '=', $data['api_key'])->first();
        $statusCode = 200;
        if ($practice) {
			$password = Hash::make(time());
			$data1 = [
				'username' => $practice->practice_api_key,
				'password' => $password,
				'group_id' => '99',
				'practice_id' => $practice->practice_id
			];
			DB::table('users')->insert($data1);
			$this->audit('Add');
			$response = [
				'error' => false,
				'message' => 'Login successful',
				'username' => $practice->practice_api_key,
				'password' => $password
			];
		} else {
			$response = [
				'error' => true,
				'message' => 'Login incorrect!'
			];
		}
        return response()->json($response, $statusCode);
	}

	public function api_logout(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('npi', '=', $data['npi'])->where('practice_api_key', '=', $data['api_key'])->first();
        $statusCode = 200;
        if ($practice) {
			DB::table('users')->where('group_id', '=', '99')->where('practice_id', '=', $practice->practice_id)->delete();
			$this->audit('Delete');
			$response = [
				'error' => false,
				'message' => 'Logout successful'
			];
		} else {
			$response = [
				'error' => true,
				'message' => 'Login incorrect!'
			];
		}
        return response()->json($response, $statusCode);
	}

    public function api_register(Request $request)
	{
		$data = $request->all();
		if ($data['practicehandle'] == '0') {
			$query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		} else {
			$query = DB::table('practiceinfo')->where('practicehandle', '=', $data['practicehandle'])->first();
		}
		unset($data['practicehandle']);
		$practice_id = $query->practice_id;
		$patient_data = [
			'api_key' => $data['api_key'],
			'url' => $data['url']
		];
		unset($data['api_key']);
		unset($data['url']);
		$patient_query = DB::table('demographics')->where('lastname', '=', $data['lastname'])->where('firstname', '=', $data['firstname'])->where('DOB', '=', $data['DOB'])->where('sex', '=', $data['sex'])->first();
		if ($patient_query) {
			// Patient exists
			$pid = $patient_query->pid;
			$return['status'] = 'Patient already exists, updated API Key and URL';
		} else {
			// If patient doesn't exist, create a new one
			$pid = DB::table('demographics')->insertGetId($data);
			$this->audit('Add');
			$data1 = [
				'billing_notes' => '',
				'imm_notes' => '',
				'pid' => $pid,
				'practice_id' => $practice_id
			];
			DB::table('demographics_notes')->insert($data1);
			$this->audit('Add');
			$data2 = [
				'pid' => $pid,
				'practice_id' => $practice_id
			];
			DB::table('demographics_relate')->insert($data2);
			$this->audit('Add');
			$directory = $query->documents_dir . $pid;
			mkdir($directory, 0775);
			$return['status'] = 'Patient newly created in the chart.';
		}
		DB::table('demographics_relate')->where('pid', '=', $pid)->where('practice_id', '=', $practice_id)->update($patient_data);
		$this->audit('Update');
		return $return;
	}

    public function as_sync(Request $request)
    {
        $message = '';
        $query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        if ($query) {
            if ($query->patient_centric == 'y') {
                if ($query->uma_client_id == $request->input('client_id') && $query->uma_client_secret == $request->input('client_secret')) {
                    $data = [
                        'email' => $request->input('email'),
                        'phone_cell' => $request->input('sms')
                    ];
                    DB::table('demographics')->where('pid', '=', '1')->update($data);
                    $this->audit('Update');
                    $message = 'Contact data synchronized';
                }
            }
        }
        return $message;
    }

    public function google_auth(Request $request)
    {
        $file = File::get(base_path() . '/.google');
        $file_arr = json_decode($file, true);
        $client_id = $file_arr['web']['client_id'];
        $client_secret = $file_arr['web']['client_secret'];
        $open_id_url = 'https://accounts.google.com';
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $url = route('google_auth');
        $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
        $oidc->startSession();
        $oidc->setRedirectURL($url);
        $oidc->addScope('openid');
        $oidc->addScope('email');
        $oidc->addScope('profile');
        $oidc->authenticate();
        $name = $oidc->requestUserInfo('name');
        $email = $oidc->requestUserInfo('email');
        //$npi = $oidc->requestUserInfo('npi');
        $access_token = $oidc->getAccessToken();
        $user = DB::table('users')->where('uid', '=', $oidc->requestUserInfo('sub'))->first();
        if ($user) {
            Auth::loginUsingId($user->id);
            $practice = DB::table('practiceinfo')->where('practice_id', '=', $user->practice_id)->first();
            Session::put('user_id', $user->id);
            Session::put('group_id', $user->group_id);
            Session::put('practice_id', $user->practice_id);
            Session::put('version', $practice->version);
            Session::put('practice_active', $practice->active);
            Session::put('displayname', $user->displayname);
            Session::put('documents_dir', $practice->documents_dir);
            Session::put('rcopia', $practice->rcopia_extension);
            Session::put('mtm_extension', $practice->mtm_extension);
            Session::put('patient_centric', $practice->patient_centric);
            Session::put('oidc_auth_access_token', $access_token);
            setcookie("login_attempts", 0, time()+900, '/');
            return redirect()->intended('/');
        } else {
            // If patient-centric, confirm if user request is registered to pNOSH first
            if ($practice->patient_centric == 'y') {
                // Flush out all previous errored attempts.
                if (Session::has('uma_error')) {
                    Session::forget('uma_error');
                }
                // Check if there is an invite first
                $invite_query = DB::table('uma_invitation')->where('email', '=', $email)->where('invitation_timeout', '>', time())->first();
                if (!$invite_query) {
                    // No invitation, expired invitation, or access
                    return redirect()->route('uma_invitation_request');
                }
                $name_arr = explode(' ', $invite_query->name);
                // Add resources associated with new provider user to pNOSH UMA Server
                $resource_set_id_arr = explode(',', $invite_query->resource_set_ids);
                foreach ($resource_set_id_arr as $resource_set_id) {
                    $uma_query = DB::table('uma')->where('resource_set_id', '=', $resource_set_id)->get();
                    $scopes = array();
                    if ($uma_query->count()) {
                        // Register all scopes for resource sets for now
                        foreach ($uma_query as $uma_row) {
                            $scopes[] = $uma_row->scope;
                        }
                    }
                    $this->uma_policy($resource_set_id, $email, $invite_query->name, $scopes);
                }
                // Remove invite
                DB::table('uma_invitation')->where('id', '=', $invite_query->id)->delete();
                $this->audit('Delete');
                Session::put('firstname', $name_arr[0]);
                Session::put('lastname', $name_arr[1]);
                Session::put('username', $oidc->requestUserInfo('sub'));
                Session::put('middle', '');
                Session::put('displayname', $name);
                Session::put('email', $email);
                Session::put('npi', '');
                Session::put('practice_choose', 'y');
                Session::put('uid', $oidc->requestUserInfo('sub'));
                Session::put('oidc_auth_access_token', $access_token);
                return redirect()->route('practice_choose');
            } else {
                // No registered mdNOSH user for this NOSH instance - punt back to login page.
                return redirect()->intended('/');
            }
        }
    }

    public function googleoauth(Request $request)
    {
        $url = route('googleoauth');
        $google = new Google_Client();
        $google->setRedirectUri($url);
        $google->setApplicationName('NOSH ChartingSystem');
        $google->setClientID(env('GOOGLE_KEY'));
        $google->setClientSecret(env('GOOGLE_SECRET'));
        $google->setAccessType('offline');
        $google->setApprovalPrompt('force');
        $google->setScopes(array('https://mail.google.com/'));
        if (isset($_REQUEST["code"])) {
            $credentials = $google->authenticate($_GET['code']);
            $data['google_refresh_token'] = $credentials['refresh_token'];
            DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->update($data);
            return redirect()->route('setup_mail_test');
        } else {
            $authUrl = $google->createAuthUrl();
            header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
            exit;
        }
    }

    public function login(Request $request, $type='all')
    {
        if (Auth::guest()) {
            if ($request->isMethod('post')) {
                $default_practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                if ($default_practice->patient_centric == 'y') {
                    $this->validate($request, [
                        'username' => 'required',
                        'password' => 'required'
                    ]);
                } else {
                    $this->validate($request, [
                        'username' => 'required',
                        'password' => 'required',
                        'practice_id' => 'required'
                    ]);
                }
                $username = $request->input('username');
                $password = $request->input('password');
                $credentials = [
                    "username" => $username,
                    "password" => $password,
                    "active" => '1'
                ];
                if ($default_practice->patient_centric == 'y') {
                    $user = DB::table('users')->where('username', '=', $username)->where('active', '=', '1')->first();
                } else {
                    $practice_id = $request->input('practice_id');
                    $credentials['practice_id'] = $practice_id;
                    $user = DB::table('users')->where('username', '=', $username)->where('active', '=', '1')->where('practice_id', '=', $practice_id)->first();
                }
                if (Auth::attempt($credentials)) {
                    // Authentication successful
                    $practice = DB::table('practiceinfo')->where('practice_id', '=', $user->practice_id)->first();
                    Session::put('user_id', $user->id);
                    Session::put('group_id', $user->group_id);
                    Session::put('practice_id', $user->practice_id);
                    Session::put('version', $practice->version);
                    Session::put('practice_active', $practice->active);
                    Session::put('displayname', $user->displayname);
                    Session::put('documents_dir', $practice->documents_dir);
                    Session::put('rcopia', $practice->rcopia_extension);
                    Session::put('mtm_extension', $practice->mtm_extension);
                    Session::put('patient_centric', $practice->patient_centric);
                    setcookie("login_attempts", 0, time()+900, '/');
                    if ($user->group_id == '1') {
                        Session::forget('pid');
                        Session::forget('eid');
                    }
                    if ($practice->patient_centric == 'n') {
                        return redirect()->intended('/');
                    } else {
                        if ($user->group_id != '100' && $user->group_id != '1') {
                            $pid = DB::table('demographics')->first();
                            $this->setpatient($pid->pid);
                            return redirect()->intended('/');
                        } else {
                            $url_hieofoneas = str_replace('/nosh', '/resources/' . $practice->uma_client_id, URL::to('/'));
                            Session::put('url_hieofoneas', $url_hieofoneas);
                            return redirect()->intended('/');
                        }
                    }
                } else {
                    // Authentication failed - present login page again
                    if (array_key_exists('login_attempts', $_COOKIE)) {
                        $attempts = $_COOKIE['login_attempts'] + 1;
                    } else {
                        $attempts = 1;
                    }
                    setcookie("login_attempts", $attempts, time()+900, '/');
                    return redirect()->back()->withErrors(['tryagain' => 'Try again']);
                }
            } else {
                $data['assets_js'] = $this->assets_js('login');
                $data['assets_css'] = $this->assets_css('login');
                $practice1 = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                if ($practice1) {
                    // Present login page
                    Session::put('version', $practice1->version);
                    $practice_id = Session::get('practice_id');
                    if ($practice_id == FALSE) {
                        $data['practice_id'] = '1';
                    } else  {
                        $data['practice_id'] = $practice_id;
                    }
                    $data['patient_centric'] = $practice1->patient_centric;
                    if ($data['patient_centric'] == 'y' || $data['patient_centric'] == 'yp') {
                        if ($type == 'provider') {
                            $data['pnosh_provider'] = 'y';
                        } else {
                            $data['pnosh_provider'] = 'n';
                            $data['login_form'] = 'n';
                        }
                    } else {
                        $data['login_form'] = 'y';
                    }
                    $data['demo'] = 'n';
                    if (route('dashboard') == 'http://demo.noshchartingsystem.com:444/nosh') {
                        $data['demo'] = 'y';
                    }
                    if ($data['patient_centric'] == 'n') {
                        $practices = DB::table('practiceinfo')->get();
                        $data['practice_list'] = '';
                        if ($practices->count()) {
                            foreach ($practices as $practice) {
                                $data['practice_list'] .= '<option value="' . $practice->practice_id . '"';
                                if (Session::has('practice_id')) {
                                    if (Session::get('practice_id') == $practice->practice_id) {
                                        $data['practice_list'] .= ' selected';
                                    }
                                }
                                $data['practice_list'] .= '>' . $practice->practice_name . '</option>';
                            }
                        }
                    }
                    if ((array_key_exists('login_attempts', $_COOKIE)) && ($_COOKIE['login_attempts'] >= 5)){
                        $data['attempts'] = "You have reached the number of limits to login.  Wait 15 minutes then try again.";
                    } else {
                        if (!array_key_exists('login_attempts', $_COOKIE)) {
                            setcookie("login_attempts", 0, time()+900, '/');
                        }
                    }
                    $data['message_action'] = Session::get('message_action');
                    Session::forget('message_action');
                    if (file_exists(base_path() . '/.version')) {
                        $data['noshversion'] = file_get_contents(base_path() . '/.version');
                    } else {
                        $version = $this->github_all();
                        $data['noshversion'] = $version[0]['sha'];
                    }
                    return view('auth.login', $data);
                } else {
                    // Not installed yet, go to install page
                    if (file_exists(base_path() . '/.patientcentric')) {
                        return redirect()->route('install', ['patient']);
                    } else {
                        return redirect()->route('install', ['practice']);
                    }
                }
            }
        } else {
            // Already logged in
            return redirect()->intended('/');
        }
    }

    public function login_uport(Request $request)
    {
        if ($request->has('uport')) {
            $user = DB::table('users')->where('email', '=', $request->input('email'))->first();
            if ($user) {
                // Save uport id stub
                Auth::loginUsingId($user->id);
                $practice = DB::table('practiceinfo')->where('practice_id', '=', $user->practice_id)->first();
                Session::put('user_id', $user->id);
                Session::put('group_id', $user->group_id);
                Session::put('practice_id', $user->practice_id);
                Session::put('version', $practice->version);
                Session::put('practice_active', $practice->active);
                Session::put('displayname', $user->displayname);
                Session::put('documents_dir', $practice->documents_dir);
                Session::put('rcopia', $practice->rcopia_extension);
                Session::put('mtm_extension', $practice->mtm_extension);
                Session::put('patient_centric', $practice->patient_centric);
                Session::put('uport_id', $request->input('uport'));
                Session::save();
                setcookie("login_attempts", 0, time()+900, '/');
                $return['url'] =  route('dashboard');
                $return['message'] = 'OK';
            } else {
                $return['message'] = 'You are not authorized to access NOSH';
            }
        } else {
            $return['message'] = 'Please contact the administrator for assistance.';
        }
        return $return;
    }

    public function logout()
    {
        if (Session::has('uma_auth_access_token')) {
            $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
            $client_id = $practice->uma_client_id;
            $client_secret = $practice->uma_client_secret;
            $open_id_url = $practice->uma_uri;
            $url = route('uma_logout');
            $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
            $oidc->startSession();
            $oidc->setRedirectURL($url);
            $oidc->setAccessToken(Session::get('uma_auth_access_token'));
            $oidc->revoke();
            Session::forget('uma_auth_access_token');
            $params = [
                'redirect_uri' => URL::to('logout')
            ];
            $open_id_url .= '/remote_logout?' . http_build_query($params, null, '&');
            return redirect($open_id_url);
        }
        if (Session::has('oidc_auth_access_token')) {
            $open_id_url = 'http://noshchartingsystem.com/oidc';
            $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
            $client_id = $practice->uma_client_id;
            $client_secret = $practice->uma_client_secret;
            $url = route('oidc_logout');
            $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
            $oidc->startSession();
            $oidc->setRedirectURL($url);
            $oidc->setAccessToken(Session::get('oidc_auth_access_token'));
            $oidc->revoke();
            Session::forget('oidc_auth_access_token');
            $params = [
                'redirect_uri' => URL::to('logout')
            ];
            $open_id_url .= '/remote_logout?' . http_build_query($params, null, '&');
            return redirect($open_id_url);
        }
        $this->clean_uma_sessions();
        Auth::logout();
        Session::forget('group_id');
        Session::forget('notifications');
        Session::forget('notification_run');
        Session::forget('uport_id');
        Session::forget('rx_json');
        // Session::flush();
        return redirect()->route('login');
    }

    public function oidc(Request $request)
    {
        $open_id_url = 'http://noshchartingsystem.com/oidc';
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        if (route('dashboard') == 'http://noshchartingsystem.com/nosh' || route('dashboard') == 'http://www.noshchartingsystem.com/nosh' || route('dashboard') == 'https://shihjay.xyz/nosh' || route('dashboard') == 'https://agropper.xyz/nosh') {
            if ($practice->openidconnect_client_id == '') {
                if ($practice->patient_centric == 'y') {
                    $patient = DB::table('demographics')->first();
                    $dob = date('m/d/Y', strtotime($patient->DOB));
                    $client_name = 'PatientNOSH for ' . $patient->firstname . ' ' . $patient->lastname . ' (DOB: ' . $dob . ')';
                } else {
                    $client_name = 'PracticeNOSH for ' . $practice->practice_name;
                }
                $open_id_url = 'http://noshchartingsystem.com/oidc';
                $url = route('oidc');
                $oidc = new OpenIDConnectUMAClient($open_id_url);
                $oidc->startSession();
                $oidc->setClientName($client_name);
                $oidc->setRedirectURL($url);
                $oidc->register();
                $client_id = $oidc->getClientID();
                $client_secret = $oidc->getClientSecret();
                $data_oidc = [
                    'openidconnect_client_id' => $client_id,
                    'openidconnect_client_secret' => $client_secret
                ];
                DB::table('practiceinfo')->where('practice_id', '=', '1')->update($data_oidc);
                $this->audit('Update');
            } else {
                $client_id = $practice->openidconnect_client_id;
                $client_secret = $practice->openidconnect_client_secret;
            }
        } else {
            return redirect()->route('login');
        }
        $url = route('oidc');
        $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
        $oidc->startSession();
        $oidc->setRedirectURL($url);
        $oidc->addScope('openid');
        $oidc->addScope('email');
        $oidc->addScope('profile');
        $oidc->authenticate();
        $firstname = $oidc->requestUserInfo('given_name');
        $lastname = $oidc->requestUserInfo('family_name');
        $email = $oidc->requestUserInfo('email');
        $npi = $oidc->requestUserInfo('npi');
        $access_token = $oidc->getAccessToken();
        if ($npi != '') {
            $provider = DB::table('providers')->where('npi', '=', $npi)->first();
            if ($provider) {
                $user = DB::table('users')->where('id', '=', $provider->id)->first();
            } else {
                $user = false;
            }
        } else {
            $user = DB::table('users')->where('uid', '=', $oidc->requestUserInfo('sub'))->first();
        }
        if ($user) {
            Auth::loginUsingId($user->id);
            $practice1 = DB::table('practiceinfo')->where('practice_id', '=', $user->practice_id)->first();
            Session::put('user_id', $user->id);
            Session::put('group_id', $user->group_id);
            Session::put('practice_id', $user->practice_id);
            Session::put('version', $practice1->version);
            Session::put('practice_active', $practice1->active);
            Session::put('displayname', $user->displayname);
            Session::put('documents_dir', $practice1->documents_dir);
            Session::put('rcopia', $practice1->rcopia_extension);
            Session::put('mtm_extension', $practice1->mtm_extension);
            Session::put('patient_centric', $practice1->patient_centric);
            Session::put('oidc_auth_access_token', $access_token);
            setcookie("login_attempts", 0, time()+900, '/');
            return redirect()->intended('/');
        } else {
            // If patient-centric, confirm if user request is registered to pNOSH first
            if ($practice->patient_centric == 'y') {
                // Flush out all previous errored attempts.
                if (Session::has('uma_error')) {
                    Session::forget('uma_error');
                }
                // Check if there is an invite first
                $invite_query = DB::table('uma_invitation')->where('email', '=', $email)->where('invitation_timeout', '>', time())->first();
                if (!$invite_query) {
                    // No invitation, expired invitation, or access
                    $view_data1['header'] = 'There Is A Problem!';
                    $view_data1['content'] = "<div>You have tried to login to this patient's personal electronic health record but you do not have sufficient priviledges to access it.<br>There are several reasons for this.<br>";
                    $view_data1['content'] .= "<ul><li>You were not given an invitation by this patient for access.</li><li>Your invitation has expired.  If so, please contact the patient directly.</li><li>If you previously had access, your acesss has been revoked by the patient.</li></ul></div>";
                    return view('welcome', $view_data1);
                }
                // Add resources associated with new provider user to pNOSH UMA Server
                $resource_set_id_arr = explode(',', $invite_query->resource_set_ids);
                foreach ($resource_set_id_arr as $resource_set_id) {
                    $uma_query = DB::table('uma')->where('resource_set_id', '=', $resource_set_id)->get();
                    $scopes = [];
                    if ($uma_query) {
                        // Register all scopes for resource sets for now
                        foreach ($uma_query as $uma_row) {
                            $scopes[] = $uma_row->scope;
                        }
                    }
                    $this->uma_policy($resource_set_id, $email, $invite_query->name, $scopes);
                }
                // Remove invite
                DB::table('uma_invitation')->where('id', '=', $invite_query->id)->delete();
                $this->audit('Delete');
                // Get Practice NPI from Oauth credentials and check if practice already loaded
                $practice_npi = $oidc->requestUserInfo('practice_npi');
                $practice_id = false;
                $practice_npi_array_null = [];
                if ($practice_npi != '') {
                    $practice_npi_array = explode(' ', $practice_npi);
                    foreach ($practice_npi_array as $practice_npi_item) {
                        $practice_query = DB::table('practiceinfo')->where('npi', '=', $practice_npi_item)->first();
                        if ($practice_query) {
                            $practice_id = $practice_query->practice_id;
                        } else {
                            $practice_npi_array_null[] = $practice_npi_item;
                        }
                    }
                } else {
                    $view_data2['header'] = 'No Practice NPI Registered!';
                    $view_data2['content'] = "<div>Please have one registered on mdNOSH to continue.</div>";
                    return view('welcome', $view_data2);
                }
                if ($practice_id == false) {
                    // No practice is registered to pNOSH yet so let's add it
                    if (count($practice_npi_array_null) == 1) {
                        // Only 1 NPI associated with provider, great!
                        $practice_arr = $this->npi_lookup($practice_npi_array_null[0]);
                        if ($practice_arr['type'] == 'Practice') {
                            $practicename = $practice_arr['practice_name'];
                        } else {
                            $practicename = $practice_arr['first_name'] . ' ' . $practice_arr['last_name'] . ', ' . $practice_arr['title'];
                        }
                        $street_address1 = $practice_arr['address'];
                        $city = $practice_arr['city'];
                        $state = $practice_arr['state'];
                        $zip = $practice_arr['zip'];
                        $practice_data = [
                            'npi' => $practice_npi_array_null[0],
                            'practice_name' => $practicename,
                            'street_address1' => $street_address1,
                            'city' => $city,
                            'state' => $state,
                            'zip' => $zip,
                            'documents_dir' => $practice->documents_dir,
                            'version' => $practice->version,
                            'active' => 'Y',
                            'fax_type' => '',
                            'vivacare' => '',
                            'patient_centric' => 'yp',
                            'smtp_user' => $practice->smtp_user,
                            'smtp_pass' => $practice->smtp_pass
                        ];
                        $practice_id = DB::table('practiceinfo')->insertGetId($practice_data);
                        $this->audit('Add');
                    } else {
                        // Ask for provider to choose which practice to link with pNOSH
                        Session::put('practice_npi_array', implode(',', $practice_npi_array_null));
                        Session::put('firstname', $firstname);
                        Session::put('lastname', $lastname);
                        Session::put('username', $oidc->requestUserInfo('sub'));
                        Session::put('middle', $oidc->requestUserInfo('middle_name'));
                        Session::put('displayname', $oidc->requestUserInfo('name'));
                        Session::put('email', $email);
                        Session::put('npi', $npi);
                        Session::put('practice_choose', 'y');
                        Session::put('uid', $oidc->requestUserInfo('sub'));
                        Session::put('oidc_auth_access_token', $access_token);
                        return redirect()->route('practice_choose');
                    }
                }
                // Finally, add user to pNOSH
                $data = [
                    'username' => $oidc->requestUserInfo('sub'),
                    'firstname' => $firstname,
                    'middle' => $oidc->requestUserInfo('middle_name'),
                    'lastname' => $lastname,
                    'displayname' => $oidc->requestUserInfo('name'),
                    'email' => $email,
                    'group_id' => '2',
                    'active'=> '1',
                    'practice_id' => $practice_id,
                    'secret_question' => 'Use mdNOSH Gateway to reset your password!',
                    'uid' => $oidc->requestUserInfo('sub')
                ];
                $id = DB::table('users')->insertGetId($data);
                $this->audit('Add');
                $data1 = [
                    'id' => $id,
                    'npi' => $npi,
                    'practice_id' => $practice_id
                ];
                DB::table('providers')->insert($data1);
                $this->audit('Add');
                $user1 = DB::table('users')->where('id', '=', $id)->first();
                Auth::loginUsingId($user1->id);
                $practice2 = DB::table('practiceinfo')->where('practice_id', '=', $user1->practice_id)->first();
                Session::put('user_id', $user1->id);
                Session::put('group_id', $user1->group_id);
                Session::put('practice_id', $user1->practice_id);
                Session::put('version', $practice2->version);
                Session::put('practice_active', $practice2->active);
                Session::put('displayname', $user1->displayname);
                Session::put('documents_dir', $practice2->documents_dir);
                Session::put('rcopia', $practice2->rcopia_extension);
                Session::put('mtm_extension', $practice2->mtm_extension);
                Session::put('patient_centric', $practice2->patient_centric);
                Session::put('oidc_auth_access_token', $access_token);
                setcookie("login_attempts", 0, time()+900, '/');
                return redirect()->intended('/');
            } else {
                // No registered mdNOSH user for this NOSH instance - punt back to login page.
                return redirect()->intended('/');
            }
        }
    }

    public function oidc_check_patient_centric(Request $request)
    {
        $query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        return $query->patient_centric;
    }

    public function oidc_logout(Request $request)
    {
        $open_id_url = 'http://noshchartingsystem.com/oidc';
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $client_id = $practice->uma_client_id;
        $client_secret = $practice->uma_client_secret;
        $url = route('oidc_logout');
        $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
        $oidc->startSession();
        $oidc->setRedirectURL($url);
        $oidc->setAccessToken(Session::get('oidc_auth_access_token'));
        $oidc->revoke();
        Session::forget('oidc_auth_access_token');
        return redirect()->intended('logout');
    }

    public function oidc_register_client(Request $request)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        if ($practice->patient_centric == 'y') {
            $patient = DB::table('demographics')->first();
            $dob = date('m/d/Y', strtotime($patient->DOB));
            $client_name = 'PatientNOSH for ' . $patient->firstname . ' ' . $patient->lastname . ' (DOB: ' . $dob . ')';
        } else {
            $client_name = 'PracticeNOSH for ' . $practice->practice_name;
        }
        $open_id_url = 'http://noshchartingsystem.com/oidc';
        $url = route('oidc');
        $oidc = new OpenIDConnectUMAClient($open_id_url);
        $oidc->startSession();
        $oidc->setClientName($client_name);
        $oidc->setRedirectURL($url);
        $oidc->register();
        $client_id = $oidc->getClientID();
        $client_secret = $oidc->getClientSecret();
        $data = [
            'openidconnect_client_id' => $client_id,
            'openidconnect_client_secret' => $client_secret
        ];
        DB::table('practiceinfo')->where('practice_id', '=', '1')->update($data);
        $this->audit('Update');
        return redirect()->route('dashboard');
    }

    public function oidc_api()
    {
        $open_id_url = 'http://noshchartingsystem.com:8888/openid-connect-server-webapp/';
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $client_id = $practice->openidconnect_client_id;
        $client_secret = $practice->openidconnect_client_secret;
        $url = route('oidc_api');
        $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
        $oidc->startSession();
        $oidc->setRedirectURL($url);
        $oidc->authenticate();
        $firstname = $oidc->requestUserInfo('given_name');
        $lastname = $oidc->requestUserInfo('family_name');
        $email = $oidc->requestUserInfo('email');
        $npi = $oidc->requestUserInfo('npi');
        $access_token = substr($oidc->getAccessToken(),0,255);
        if ($npi != '') {
            $provider = DB::table('providers')->where('npi', '=', $npi)->first();
            if ($provider) {
                $user = DB::table('users')->where('id', '=', $provider->id)->first();
            } else {
                $user = false;
            }
        } else {
            $user = DB::table('users')->where('uid', '=', $oidc->requestUserInfo('sub'))->first();
        }
        if ($user) {
            Auth::loginUsingId($user->id);
            $user_data = [
                'oauth_token' => $access_token,
                'oauth_token_secret' => time() + 7200  //2 hour time limit
            ];
            DB::table('users')->where('id', '=', $user->id)->update($user_data);
            $this->audit('Update');
            $response['token'] = $access_token;
            $response['user'] = [
                'uid' => $oidc->requestUserInfo('sub'),
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'npi' => $npi,
                'api_token' => $access_token
            ];
            $statusCode = 200;
        } else {
            $statusCode = 401;
            $response['error'] = true;
            $response['message'] = 'Not an approved user for this system';
            $response['code'] = 401;
        }
        return Response::json($response, $statusCode);
    }

    public function password_email(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'email' => 'required',
            ]);
            $query = DB::table('users')->where('email', '=', $request->input('email'))->where('active', '=', '1')->first();
            if ($query) {
                $data['password'] = $this->gen_secret();
                DB::table('users')->where('id', '=', $query->id)->update($data);
                $this->audit('Update');
                $url = route('password_reset_response', [$data['password']]);
                $data2['message_data'] = 'This message is to notify you that you have reset your password with mdNOSH Gateway.<br>';
                $data2['message_data'] .= 'To finish this process, please click on the following link or point your web browser to:<br>';
                $data2['message_data'] .= $url;
                $this->send_mail('auth.emails.generic', $data2, 'Reset password to NOSH ChartingSystem', $request->input('email'), $query->practice_id);
                $message = 'Password reset.  Check your email for further instructions';
            } else {
                $message = 'Error - Email address provided not known';
            }
            Session::put('message_action', $message);
            return redirect()->route('login');
        } else {
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('password', $data);
        }
    }

    public function password_reset_response(Request $request, $id)
    {
        $query = DB::table('users')->where('password', '=', $id)->first();
        if ($query) {
            // $expires = strtotime($query->updated_at) + 7200;
            // if ($expires > time()) {
                if ($request->isMethod('post')) {
                    $this->validate($request, [
                        'password' => 'min:4',
                        'confirm_password' => 'min:4|same:password',
                        'secret_answer' => 'required'
                    ]);
                    if ($query->secret_answer == $request->input('secret_answer')) {
                        $data['password'] = substr_replace(Hash::make($request->input('password')),"$2a",0,3);
                        DB::table('users')->where('id', '=', $query->id)->update($data);
                        $this->audit('Update');
                        Session::put('message_action', 'Pasword updated.  Please log in');
                        return redirect()->route('login');
                    } else {
                        return 'Your response is incorrect.';
                    }
                } else {
                    $data['code'] = $id;
                    $data['secret_question'] = $query->secret_question;
                    $data['assets_js'] = $this->assets_js();
                    $data['assets_css'] = $this->assets_css();
                    return view('changepassword', $data);
                }
            // } else {
                // return 'Your code expired.  Contact your administrator to have your password reset again.';
            // }
        } else {
            return 'Your code is invalid.';
        }
    }

    public function practice_choose(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'practice_npi_select' => 'required'
            ]);
            $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
            $practice_arr = $this->npi_lookup($request->input('practice_npi_select'));
            if ($practice_arr['type'] == 'Practice') {
                $practicename = $practice_arr['practice_name'];
            } else {
                $practicename = $practice_arr['first_name'] . ' ' . $practice_arr['last_name'] . ', ' . $practice_arr['title'];
            }
            $street_address1 = $practice_arr['address'];
            $city = $practice_arr['city'];
            $state = $practice_arr['state'];
            $zip = $practice_arr['zip'];
            $practice_data = [
                'npi' => $request->input('practice_npi_select'),
                'practice_name' => $practicename,
                'street_address1' => $street_address1,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'documents_dir' => $practice->documents_dir,
                'version' => $practice->version,
                'active' => 'Y',
                'fax_type' => '',
                'vivacare' => '',
                'patient_centric' => 'yp',
                'smtp_user' => $practice->smtp_user,
                'smtp_pass' => $practice->smtp_pass
            ];
            $practice_id = DB::table('practiceinfo')->insertGetId($practice_data);
            $this->audit('Add');
            $data = [
                'username' => Session::get('username'),
                'firstname' => Session::get('firstname'),
                'middle' => Session::get('middle'),
                'lastname' => Session::get('lastname'),
                'displayname' => Session::get('displayname'),
                'email' => Session::get('email'),
                'group_id' => '2',
                'active'=> '1',
                'practice_id' => $practice_id,
                'uid' => Session::get('uid'),
                'secret_question' => 'Use mdNOSH to reset your password!',
            ];
            $id = DB::table('users')->insertGetId($data);
            $this->audit('Add');
            if ($request->has('npi')) {
                $npi = $request->input('npi');
            } else {
                $npi = Session::get('npi');
            }
            $data1 = [
                'id' => $id,
                'npi' => $npi,
                'practice_id' => $practice_id
            ];
            DB::table('providers')->insert($data1);
            $this->audit('Add');
            //$this->syncuser(Session::get('oidc_auth_access_token'));
            $user1 = DB::table('users')->where('id', '=', $id)->first();
            Auth::loginUsingId($user1->id);
            $practice1 = DB::table('practiceinfo')->where('practice_id', '=', $user1->practice_id)->first();
            Session::put('user_id', $user1->id);
            Session::put('group_id', $user1->group_id);
            Session::put('practice_id', $user1->practice_id);
            Session::put('version', $practice1->version);
            Session::put('practice_active', $practice1->active);
            Session::put('displayname', $user1->displayname);
            Session::put('documents_dir', $practice1->documents_dir);
            Session::put('rcopia', $practice1->rcopia_extension);
            Session::put('mtm_extension', $practice1->mtm_extension);
            Session::put('patient_centric', $practice1->patient_centric);
            setcookie("login_attempts", 0, time()+900, '/');
            Session::forget('practice_npi_array');
            Session::forget('practice_choose');
            Session::forget('username');
            Session::forget('firstname');
            Session::forget('middle');
            Session::forget('lastname');
            Session::forget('email');
            Session::forget('npi');
            return redirect()->intended('/');
        } else {
            if (Session::has('practice_choose')) {
                if (Session::get('practice_choose') == 'y') {
                    if (Session::has('practice_npi_array')) {
                        $practice_npi_array1 = explode(',', Session::get('practice_npi_array'));
                        $form_select_array = [];
                        foreach ($practice_npi_array1 as $practice_npi_item1) {
                            $form_select_array[$practice_npi_item1] = $practice_npi_item1;
                        }
                        $view_data1['content'] = "<div class='well'><p>Your identity has more than one associated practice NPI's.</p><p>Choose a practice NPI you want to associate with this patient's NOSH service.</p></div>";
                        $form_array1 = [
                            'form_id' => 'practice_choose',
                            'action' => URL::to('practice_choose'),
                            'items' => [
                                [
                                    'name' => 'practice_npi_select',
                                    'label' => 'Practice NPI',
                                    'type' => 'select',
                                    'required' => true,
                                    'value' => '',
                                    'default_value' => '',
                                    'select_items' => $form_select_array
                                ]
                            ],
                            'origin' => 'previous URL',
                            'save_button_label' => 'Select Practice'
                        ];
                        $view_data1['content'] .= $this->form_build($form_array1);
                    } else {
                        $view_data1['content'] = "<div class='well'><p>Enter your NPI and a practice NPI you want to associate with this patient's NOSH service.</p><p>You can verify your NPI number <a href='http://npinumberlookup.org/' target='_blank'>here</a></p></div>";
                        $form_array1 = [
                            'form_id' => 'practice_choose',
                            'action' => URL::to('practice_choose'),
                            'items' => [
                                [
                                    'name' => 'npi',
                                    'label' => 'NPI',
                                    'type' => 'text',
                                    'required' => true
                                ],
                                [
                                    'name' => 'practice_npi_select',
                                    'label' => 'Practice NPI',
                                    'type' => 'text',
                                    'required' => true
                                ]
                            ],
                            'origin' => 'previous URL',
                            'save_button_label' => 'Submit'
                        ];
                        $view_data1['content'] .= $this->form_build($form_array1);
                    }
                    $view_data1['panel_header'] = 'Choose a Practice';
                    $view_data1['assets_js'] = $this->assets_js();
                    $view_data1['assets_css'] = $this->assets_css();
                    return view('welcome', $view_data1);
                } else {
                    return redirect()->intended('/');
                }
            } else {
                return redirect()->intended('/');
            }
        }
    }

    public function practice_logo_login(Request $request)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $request->input('practice_id'))->first();
        $html = '<i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>';
        if ($practice->practice_logo !== '' && $practice->practice_logo !== null) {
            if (file_exists(public_path() . '/'. $practice->practice_logo)) {
                $html = HTML::image($practice->practice_logo, 'Practice Logo', array('border' => '0'));
            }
        }
        return $html;
    }

    public function register_user(Request $request)
    {
        if (Auth::guest()) {
            if ($request->isMethod('post')) {
                $default_practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                $this->validate($request, [
                    'numberReal' => 'required',
                    'numberRealHash' => 'required',
                    'lastname' => 'required',
                    'firstname' => 'required',
                    'dob' => 'required',
                    'email' => 'required|unique:users,email',
                    'username1' => 'required|unique:users,username',
                    'password1' => 'min:4',
                    'confirm_password1' => 'min:4|same:password1',
                    'secret_question' => 'required',
                    'secret_answer' => 'required'
                ]);
                if ($this->rpHash($request->input('numberReal')) == $request->input('numberRealHash')) {
                    $registration_code = $request->input('registration_code');
                    if ($registration_code != '') {
                        $result = DB::table('demographics')->where('registration_code', '=', $registration_code)
                            ->where('firstname', '=', $request->input('firstname'))
                            ->where('lastname', '=', $request->input('lastname'))
                            ->where('DOB', '=', date('Y-m-d H:i:s', $this->human_to_unix($request->input('dob'))))
                            ->first();
                        if ($result) {
                            $displayname = $request->input('firstname') . " " . $request->input('lastname');
                            $demographics_relate = DB::table('demographics_relate')->where('pid', '=', $result->pid)->get();
                            $arr['response'] = "1";
                            foreach ($demographics_relate as $demographics_relate_row) {
                                $row1 = DB::table('practiceinfo')->where('practice_id', '=', $demographics_relate_row->practice_id)->first();
                                if ($demographics_relate_row->id == '' || $demographics_relate_row->id == '0' || is_null($demographics_relate_row->id)) {
                                    $data1 = [
                                        'username' => $request->input('username1'),
                                        'password' => substr_replace(Hash::make($request->input('password1')),"$2a",0,3),
                                        'firstname' => $request->input('firstname'),
                                        'lastname' => $request->input('lastname'),
                                        'email' => $request->input('email'),
                                        'group_id' => '100',
                                        'active' => '1',
                                        'displayname' => $displayname,
                                        'practice_id' => $demographics_relate_row->practice_id,
                                        'secret_question' => $request->input('secret_question'),
                                        'secret_answer' => $request->input('secret_answer')
                                    ];
                                    $arr['id'] = DB::table('users')->insertGetId($data1);
                                    $this->audit('Add');
                                    $data2['id'] = $arr['id'];
                                    DB::table('demographics_relate')->where('demographics_relate_id', '=', $demographics_relate_row->demographics_relate_id)->update($data2);
                                    $this->audit('Update');
                                    $data_message1['practicename'] = $row1->practice_name;
                                    $data_message1['username'] = $request->input('username1');
                                    $data_message1['url'] = route('login');
                                    $this->send_mail('emails.loginregistrationconfirm', $data_message1, 'Patient Portal Registration Confirmation', $request->input('email'), $demographics_relate_row->practice_id);
                                } else {
                                    $arr['response'] = "5";
                                    $row2 = DB::table('users')->where('id', '=', $demographics_relate_row->id)->first();
                                    $data_message['practicename'] = $row1->practice_name;
                                    $data_message['username'] = $row2->username;
                                    $data_message['url'] = route('login');
                                    $this->send_mail('emails.loginregistration', $data_message, 'Patient Portal Registration Message', $request->input('email'), $demographics_relate_row->practice_id);
                                }
                            }
                            Session::put('message_action', 'Your account has been activated.  Please log in');
                            return redirect()->route('login');
                        } else {
                            if (array_key_exists('login_attempts', $_COOKIE)) {
                                $attempts = $_COOKIE['login_attempts'] + 1;
                            } else {
                                $attempts = 1;
                            }
                            setcookie("login_attempts", $attempts, time()+900, '/');
                            return redirect()->back()->withErrors(['tryagain' => 'Try again']);
                        }
                    } else {
                        $row3 = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                        $displayname = Session::get('displayname');
                        $data_message2 = [
                            'firstname' => $request->input('firstname'),
                            'lastname' => $request->input('lastname'),
                            'dob' => $request->input('dob'),
                            'username' => $request->input('username1'),
                            'email' => $request->input('email')
                        ];
                        $this->send_mail('emails.loginregistrationrequest', $data_message2, 'New User Request', $row3->email, '1');
                        $view_data1['header'] = 'Registration Sent';
                        $view_data1['content'] = "<div>Your registration information has been sent to the administrator and you will receive your registration code within 48-72 hours by e-mail after confirmation of your idenity.<br>Thank you!</div>";
                        return view('welcome', $view_data1);
                    }
                } else {
                    if (array_key_exists('login_attempts', $_COOKIE)) {
                        $attempts = $_COOKIE['login_attempts'] + 1;
                    } else {
                        $attempts = 1;
                    }
                    setcookie("login_attempts", $attempts, time()+900, '/');
                    return redirect()->back()->withErrors(['tryagain' => 'Try again']);
                }
            } else {
                return redirect()->route('login');
            }
        } else {
            return redirect()->route('/');
        }
    }

    public function remote_logout(Request $request)
	{
		Auth::logout();
		Session::flush();
		return redirect($request->input('redirect_uri'));
	}

    public function reset_demo(Request $request)
    {
        if (route('dashboard') == 'https://shihjay.xyz/nosh') {
            $practice = DB::table('practiceinfo')->first();
            $file = '/noshdocuments/demo.sql';
            $file1 = '/noshdocuments/demo_oidc.sql';
            $command = "mysql -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " nosh < " . $file;
            $command1 = "mysql -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " oidc < " . $file1;
            system($command);
            system($command1);
            Auth::logout();
            Session::flush();
            $directory_url = 'https://cloud.noshchartingsystem.com/hiedirectory/reset_demo';
            return redirect($directory_url);
        } else {
            return redirect()->route('dashboard');
        }
    }

    public function smart_on_fhir_list(Request $request)
    {
        $connected = DB::table('refresh_tokens')->where('practice_id', '=', '1')->get();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $connected_arr = [];
        $url = 'https://open.epic.com/MyApps/EndpointsJson';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
        $result = curl_exec($ch);
        $result_array = json_decode($result, true);
        if ($connected->count()) {
            foreach ($connected as $connect_row) {
                if ($connect_row->pnosh !== null && $connect_row->pnosh !== '') {
                    if ($connect_row->pnosh == 'Medicare Benefits') {
                        $connected_arr[] = [
                            'org_name' => $connect_row->pnosh,
                            'endpoint_uri' => route('cms_bluebutton'),
                            'endpoint_uri_raw' => $connect_row->endpoint_uri,
                            'client_id' => $connect_row->client_id
                        ];
                    } else {
                        $id = array_search($connect_row->endpoint_uri, array_column($result_array['Entries'], 'FHIRPatientFacingURI'));
                        $client_id = $practice->openepic_client_id;
                        if ($connect_row->endpoint_uri == 'https://open-ic.epic.com/argonaut/api/FHIR/Argonaut/') {
                            $client_id = $practice->openepic_sandbox_client_id;
                        }
                        $connected_arr[] = [
                            'org_name' => $connect_row->pnosh,
                            'endpoint_uri' => route('fhir_connect', [$id]),
                            'endpoint_uri_raw' => $connect_row->endpoint_uri,
                            'client_id' => $client_id
                        ];
                    }
                }
            }
        }
        return $connected_arr;
    }

    public function start($practicehandle=null)
    {
        if ($practicehandle !== null) {
            $practice = DB::table('practiceinfo')->where('practicehandle', '=', $practicehandle)->first();
            if ($practice) {
                Session::put('practice_id', $practice->practice_id);
            }
        }
        return redirect()->route('login');
    }

    public function transactions(Request $request)
    {
        $query = DB::table('rx_list')->whereNotNull('transaction')->orderBy('rxl_date_prescribed', 'desc');
        $result = $query->get();
        $return = [];
        $return['count'] = $query->count();
        $i = 0;
        $provider = [];
        if ($return['count']) {
            foreach ($result as $row) {
                if ($i < 10) {
                    $return['transactions'][] = [
                        'transaction' => $row->transaction,
                        'date' => $this->human_to_unix($row->rxl_date_prescribed),
                        'provider' => $row->rxl_provider
                    ];
                }
                if (isset($provider[$row->id])) {
                    $provider[$row->id]++;
                } else {
                    $provider[$row->id] = 1;
                }
                $i++;
            }
            arsort($provider);
            array_slice($provider, 0, 5);
            foreach ($provider as $provider_row_k => $provider_row_v) {
                $query1 = DB::table('users')->where('id', '=', $provider_row_k)->first();
                $return['providers'][] = [
                    'provider' => $query1->displayname,
                    'count' => $provider_row_v
                ];
            }
        }
        return $return;
    }


    // Patient-centric, UMA login
    public function uma_auth()
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $client_id = $practice->uma_client_id;
        $client_secret = $practice->uma_client_secret;
        $open_id_url = $practice->uma_uri;
        $url = route('uma_auth');
        $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
        $oidc->startSession();
        $oidc->setRedirectURL($url);
        $oidc->setSessionName('pnosh');
        if ($practice->uma_refresh_token == '') {
            $oidc->addScope('openid');
            $oidc->addScope('email');
            $oidc->addScope('profile');
            $oidc->addScope('offline_access');
            $oidc->addScope('uma_protection');
        } else {
            $oidc->addScope('openid');
            $oidc->addScope('email');
            $oidc->addScope('profile');
        }
        $oidc->setUMA(true);
        $oidc->authenticate();
        $firstname = $oidc->requestUserInfo('given_name');
        $lastname = $oidc->requestUserInfo('family_name');
        $email = $oidc->requestUserInfo('email');
        $npi = $oidc->requestUserInfo('npi');
        $uport_id = $oidc->requestUserInfo('uport_id');
        $access_token = $oidc->getAccessToken();
        if ($npi != '') {
            if ($npi !== '1234567890') {
                $provider = DB::table('providers')->where('npi', '=', $npi)->first();
                if ($provider) {
                    $user = DB::table('users')->where('id', '=', $provider->id)->first();
                } else {
                    $user = false;
                }
            } else {
                $user = DB::table('users')->where('email', '=', $email)->first();
            }
        } else {
            $user = DB::table('users')->where('uid', '=', $oidc->requestUserInfo('sub'))->first();
        }
        if ($user) {
            Auth::loginUsingId($user->id);
            $practice1 = DB::table('practiceinfo')->where('practice_id', '=', $user->practice_id)->first();
            Session::put('user_id', $user->id);
            Session::put('group_id', $user->group_id);
            Session::put('practice_id', $user->practice_id);
            Session::put('version', $practice1->version);
            Session::put('practice_active', $practice1->active);
            Session::put('displayname', $user->displayname);
            Session::put('documents_dir', $practice->documents_dir);
            Session::put('rcopia', $practice1->rcopia_extension);
            Session::put('mtm_extension', $practice1->mtm_extension);
            Session::put('patient_centric', $practice1->patient_centric);
            Session::put('uma_auth_access_token', $access_token);
            Session::put('uport_id', $uport_id);
            $url_hieofoneas = str_replace('/nosh', '/resources/' . $practice1->uma_client_id, URL::to('/'));
            Session::put('url_hieofoneas', $url_hieofoneas);
            if ($practice1->patient_centric == 'y') {
                if ($practice1->uma_uri !== null && $practice1->uma_uri !== '') {
                    Session::put('uma_uri', $practice1->uma_uri);
                }
            }
            setcookie("login_attempts", 0, time()+900, '/');
            return redirect(Session::get('url.intended'));
        } else {
            $practice_npi = $npi;
            // $practice_id = false;
            // if ($practice_npi != '') {
            //     $practice_query = DB::table('practiceinfo')->where('npi', '=', $practice_npi)->first();
            //     if ($practice_query) {
            //         $practice_id = $practice_query->practice_id;
            //     }
            //     if ($practice_id == false) {
            //         $practice_arr = $this->npi_lookup($practice_npi);
            //         if (isset($practice_arr['type'])) {
            //             if ($practice_arr['type'] == 'Practice') {
            //                 $practicename = $practice_arr['practice_name'];
            //             } else {
            //                 $practicename = $practice_arr['first_name'] . ' ' . $practice_arr['last_name'] . ', ' . $practice_arr['title'];
            //             }
            //             $street_address1 = $practice_arr['address'];
            //             $city = $practice_arr['city'];
            //             $state = $practice_arr['state'];
            //             $zip = $practice_arr['zip'];
            //             $practice_data = [
            //                 'npi' => $practice_npi,
            //                 'practice_name' => $practicename,
            //                 'street_address1' => $street_address1,
            //                 'city' => $city,
            //                 'state' => $state,
            //                 'zip' => $zip,
            //                 'documents_dir' => $practice->documents_dir,
            //                 'version' => $practice->version,
            //                 'active' => 'Y',
            //                 'fax_type' => '',
            //                 'vivacare' => '',
            //                 'patient_centric' => 'yp',
            //                 'smtp_user' => $practice->smtp_user,
            //                 'smtp_pass' => $practice->smtp_pass
            //             ];
            //             $practice_id = DB::table('practiceinfo')->insertGetId($practice_data);
            //             $this->audit('Add');
            //         } else {
            //             return redirect()->route('uma_invitation_request');
            //         }
            //     }
            // } else {
            //     return redirect()->route('uma_invitation_request');
            // }

            // Temporary Hayward Demo code
            $practice_data = [
                'npi' => $practice_npi,
                'practice_name' => 'Demo Practice',
                'street_address1' => '123 Main',
                'city' => 'Busytown',
                'state' => 'MA',
                'zip' => '12345',
                'documents_dir' => $practice->documents_dir,
                'version' => $practice->version,
                'active' => 'Y',
                'fax_type' => '',
                'vivacare' => '',
                'patient_centric' => 'yp',
                'smtp_user' => $practice->smtp_user,
                'smtp_pass' => $practice->smtp_pass
            ];
            $practice_id = DB::table('practiceinfo')->insertGetId($practice_data);
            $this->audit('Add');
            $data = [
                'username' => $oidc->requestUserInfo('sub'),
                'firstname' => $firstname,
                'middle' => $oidc->requestUserInfo('middle_name'),
                'lastname' => $lastname,
                'displayname' => $oidc->requestUserInfo('name'),
                'email' => $email,
                'group_id' => '2',
                'active'=> '1',
                'practice_id' => $practice_id,
                'secret_question' => 'Use HIEofOne to reset your password!',
                'uid' => $oidc->requestUserInfo('sub')
            ];
            $id = DB::table('users')->insertGetId($data);
            $this->audit('Add');
            $data1 = [
                'id' => $id,
                'npi' => $npi,
                'practice_id' => $practice_id
            ];
            DB::table('providers')->insert($data1);
            $this->audit('Add');
            $user1 = DB::table('users')->where('id', '=', $id)->first();
            Auth::loginUsingId($user1->id);
            $practice2 = DB::table('practiceinfo')->where('practice_id', '=', $user1->practice_id)->first();
            Session::put('user_id', $user1->id);
            Session::put('group_id', $user1->group_id);
            Session::put('practice_id', $user1->practice_id);
            Session::put('version', $practice2->version);
            Session::put('practice_active', $practice2->active);
            Session::put('displayname', $user1->displayname);
            Session::put('documents_dir', $practice2->documents_dir);
            Session::put('rcopia', $practice2->rcopia_extension);
            Session::put('mtm_extension', $practice2->mtm_extension);
            Session::put('patient_centric', $practice2->patient_centric);
            Session::put('uma_auth_access_token', $access_token);
            Session::put('uport_id', $uport_id);
            setcookie("login_attempts", 0, time()+900, '/');
            return redirect(Session::get('url.intended'));
        }
    }

    public function uma_invitation_request()
	{
		$practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		$data['email'] = $practice->email . '?Subject=Invitation%20Request';
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('uma_invitation_request', $data);
	}

    public function uma_logout(Request $request)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $client_id = $practice->uma_client_id;
        $client_secret = $practice->uma_client_secret;
        $open_id_url = $practice->uma_uri;
        $url = route('uma_logout');
        $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
        $oidc->startSession();
        $oidc->setRedirectURL($url);
        $oidc->setAccessToken(Session::get('uma_auth_access_token'));
        $oidc->revoke();
        Session::forget('uma_auth_access_token');
        return redirect()->intended('logout');
    }
}
