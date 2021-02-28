<?php

namespace App\Http\Controllers;

use App;
use Illuminate\Support\Arr;
// use App\Libraries\OpenIDConnectUMAClient;
use Artisan;
use Auth;
use Config;
use PragmaRX\Countries\Package\Countries;
use Crypt;
use Date;
use DateTime;
use DateTimeZone;
use DB;
use File;
use Form;
use SoapBox\Formatter\Formatter;
use Google_Client;
use Hash;
use HTML;
use KubAT\PhpSimple\HtmlDomParser;
use Imagick;
use Laravel\LegacyEncrypter\McryptEncrypter;
use Illuminate\Support\MessageBag;
use Shihjay2\OpenIDConnectUMAClient;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use QrCode;
use Illuminate\Http\Request;
use App\Http\Requests;
use Response;
use Schema;
use Session;
use Illuminate\Support\Facades\Storage;
use URL;

use Jose\Component\Core\JWK;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;

class InstallController extends Controller {

    public function backup()
    {
        $dir = Storage::path('');
        $file = $dir . "noshbackup_" . time() . ".sql";
        $command = "mysqldump -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . env('DB_DATABASE') . " > " . $file;
        system($command);
        $files = glob($dir . "*.sql");
        foreach ($files as $file_row) {
            $explode = explode("_", $file_row);
            $time = intval(str_replace(".sql","",$explode[1]));
            $month = time() - 604800;
            if ($time < $month) {
                unlink($file_row);
            }
        }
        DB::delete('delete from extensions_log where DATE_SUB(CURDATE(), INTERVAL 30 DAY) >= timestamp');
        $this->clean_temp_dir();
        $this->healthwise_compile();
    }

    public function gnap_patient_centric(Request $request)
    {
        $query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        if ($query->patient_centric == 'y') {
            $query1 = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
            if ($query1->gnap_client_id == '') {
                // Register with AS


                if ($query->uma_uri == '' || $query->uma_uri == null) {
                    // Check if AS is on the same $domain_name
                    $check_open_id_url = str_replace('/nosh', '/.well-known/uma2-configuration', URL::to('/'));
                    $ch = curl_init();
                    curl_setopt($ch,CURLOPT_URL, $check_open_id_url);
                    curl_setopt($ch,CURLOPT_FAILONERROR,1);
                    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
                    if (file_exists(base_path() . '/fakelerootx1.pem')) {
                        curl_setopt($ch, CURLOPT_CAINFO, base_path() . '/fakelerootx1.pem');
                    }
                    $check_exec = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close ($ch);
                    if ($httpCode == 404 || $httpCode == 0) {
                        return redirect()->route('uma_patient_centric_designate');
                    }
                    $data['uma_uri'] = str_replace('/nosh', '', URL::to('/'));
                } else {
                    $data['uma_uri'] = $query->uma_uri;
                }
                // Register as resource server
                $patient = DB::table('demographics')->where('pid', '=', '1')->first();
                // $client_name = 'Patient NOSH for ' .  $patient->firstname . ' ' . $patient->lastname . ', DOB: ' . date('Y-m-d', strtotime($patient->DOB));
                $client_name = 'Patient NOSH for ' .  $patient->firstname . ' ' . $patient->lastname;
                $url = route('uma_auth');
                $oidc = new OpenIDConnectUMAClient($data['uma_uri']);
                $oidc->startSession();
                $oidc->setClientName($client_name);
                $oidc->setSessionName('pnosh');
                $oidc->addRedirectURLs($url);
                $oidc->addRedirectURLs(route('uma_api'));
                $oidc->addRedirectURLs(route('uma_logout'));
                $oidc->addRedirectURLs(route('uma_patient_centric'));
                $oidc->addRedirectURLs(route('uma_register_auth'));
                $oidc->addRedirectURLs(route('oidc'));
                $oidc->addRedirectURLs(route('oidc_api'));
                $oidc->addRedirectURLs(str_replace('uma_auth', 'fhir', $url));
                $oidc->addScope('openid');
                $oidc->addScope('email');
                $oidc->addScope('profile');
                $oidc->addScope('address');
                $oidc->addScope('phone');
                $oidc->addScope('offline_access');
                $oidc->addScope('uma_protection');
                $oidc->addScope('uma_authorization');
                $oidc->addGrantType('authorization_code');
                $oidc->addGrantType('password');
                $oidc->addGrantType('client_credentials');
                $oidc->addGrantType('implicit');
                $oidc->addGrantType('jwt-bearer');
                $oidc->addGrantType('refresh_token');
                $oidc->setLogo('https://cloud.noshchartingsystem.com/SAAS-Logo.jpg');
                $oidc->setClientURI(str_replace('/uma_auth', '', $url));
                $oidc->setUMA(true);
                $oidc->register();
                $data['uma_client_id']  = $oidc->getClientID();
                $data['uma_client_secret'] = $oidc->getClientSecret();
                DB::table('practiceinfo')->where('practice_id', '=', '1')->update($data);
                $this->audit('Update');
                return redirect()->route('uma_patient_centric');
            } else {
                if ($query->uma_refresh_token == '') {
                    // Get refresh token and link patient with user
                    $client_id = $query->uma_client_id;
                    $client_secret = $query->uma_client_secret;
                    $open_id_url = $query->uma_uri;
                    $url = route('uma_patient_centric');
                    $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
                    $oidc->startSession();
                    $oidc->setRedirectURL($url);
                    $oidc->setSessionName('pnosh');
                    $oidc->setUMA(true);
                    $oidc->setUMAType('resource_server');
                    $oidc->authenticate();
                    $user_data['uid']  = $oidc->requestUserInfo('sub');
                    DB::table('users')->where('id', '=', '2')->update($user_data);
                    $access_token = $oidc->getAccessToken();
                    $uport_id = $oidc->requestUserInfo('uport_id');
                    if ($oidc->getRefreshToken() != '') {
                        $refresh_data['uma_refresh_token'] = $oidc->getRefreshToken();
                        DB::table('practiceinfo')->where('practice_id', '=', '1')->update($refresh_data);
                        $this->audit('Update');
                    }
                    // Register resource sets
                    $uma = DB::table('uma')->first();
                    if (!$uma) {
                        // First time
                        $resource_set_array[] = [
                            'name' => 'Patient from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-patient.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Patient',
                                URL::to('/') . '/fhir/Medication',
                                URL::to('/') . '/fhir/Practitioner',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Conditions from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-condition.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Condition',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Medications from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-pharmacy.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/MedicationStatement',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Allergies from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-allergy.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/AllergyIntolerance',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Immunizations from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-immunizations.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Immunization',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Encounters from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-medical-records.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Encounter',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Family History from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-family-practice.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/FamilyHistory',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Documents from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-file.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Binary',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Observations from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-cardiology.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Observation',
                                'view',
                                'edit'
                            ]
                        ];
                        foreach ($resource_set_array as $resource_set_item) {
                            $response = $oidc->resource_set($resource_set_item['name'], $resource_set_item['icon'], $resource_set_item['scopes']);
                            if (isset($response['resource_set_id'])) {
                                foreach ($resource_set_item['scopes'] as $scope_item) {
                                    $response_data1 = [
                                        'resource_set_id' => $response['resource_set_id'],
                                        'scope' => $scope_item,
                                        'user_access_policy_uri' => $response['user_access_policy_uri']
                                    ];
                                    DB::table('uma')->insert($response_data1);
                                    $this->audit('Add');
                                }
                            }
                        }
                    }
                    // Login as owner
                    $user = DB::table('users')->where('id', '=', '2')->first();
                    Auth::loginUsingId($user->id);
                    $practice1 = DB::table('practiceinfo')->where('practice_id', '=', $user->practice_id)->first();
                    Session::put('user_id', $user->id);
                    Session::put('group_id', $user->group_id);
                    Session::put('practice_id', $user->practice_id);
                    Session::put('version', $practice1->version);
                    Session::put('practice_active', $practice1->active);
                    Session::put('displayname', $user->displayname);
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
                    // return redirect($open_id_url);
                }
            }
        }
        return redirect()->route('dashboard');
    }

    public function google_start(Request $request)
    {
        $google_file = base_path() . '/.google';
        $file = File::get($google_file);
        if ($file !== '') {
            $file_arr = json_decode($file, true);
            $practice = DB::table('practiceinfo')->first();
            $mail_arr = [
                'MAIL_DRIVER' => 'smtp',
                'MAIL_HOST' => 'smtp.gmail.com',
                'MAIL_PORT' => 465,
                'MAIL_ENCRYPTION' => 'ssl',
                'MAIL_USERNAME' => $practice->smtp_user,
                'MAIL_PASSWORD' => '',
                'GOOGLE_KEY' => $file_arr['web']['client_id'],
                'GOOGLE_SECRET' => $file_arr['web']['client_secret'],
                'GOOGLE_REDIRECT_URI' => route('googleoauth'),
                'MAILGUN_DOMAIN' => '',
                'MAILGUN_SECRET' => ''
            ];
            $this->changeEnv($mail_arr);
        }
        unlink($google_file);
        return redirect()->route('dashboard');
        // $query = DB::table('practiceinfo')->first();
        // if ($request->isMethod('post')) {
        //     $file = $request->file('file_input');
        //     $json = file_get_contents($file->getRealPath());
        //     if (json_decode($json) == NULL) {
        //         Session::put('message_action', 'Error - This is not a json file.  Try again');
        //         return redirect()->route('google_start');
        //     }
        //     $config_file = base_path() . '/.google';
        //     if (file_exists($config_file)) {
        //         unlink($config_file);
        //     }
        //     $directory = base_path();
        //     $new_name = ".google";
        //     $file->move($directory, $new_name);
        //     Session::put('message_action', 'Google JSON file uploaded successfully');
        //     if (! $query) {
        //         if (file_exists(base_path() . '/.patientcentric')) {
        //             return redirect()->route('install', ['patient']);
        //         } else {
        //             return redirect()->route('install', ['practice']);
        //         }
        //     } else {
        //         return redirect()->route('dashboard');
        //     }
        // } else {
        //     $data['panel_header'] = 'Upload Google JSON File for GMail Integration';
        //     $data['document_upload'] = route('google_start');
        //     $type_arr = ['json'];
        //     $data['document_type'] = json_encode($type_arr);
        //     $text = "<p>You're' here because you have not installed a Google OAuth2 Client ID file.  You'll need to set this up first before configuring NOSH Charting System.'</p>";
        //     if ($query) {
        //         $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
        //         $dropdown_array['default_button_text_url'] = route('dashboard');
        //         $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        //         $text = "<p>A Google OAuth2 Client ID file is already installed.  Uploading a new file will overwrite the existing file!</p>";
        //     }
        //     $data['content'] = '<div class="alert alert-success">' . $text . '<ul><li>Instructions are on ' . HTML::link('https://github.com/shihjay2/nosh-in-a-box/wiki/How-to-get-Gmail-to-work-with-NOSH', 'this Wiki page.', ['target'=>'_blank']) . ' Please refer to it carefully.</li><li>Once you have you JSON file, upload it here.</li></ul></div>';
        //     $data['assets_js'] = $this->assets_js('document_upload');
        //     $data['assets_css'] = $this->assets_css('document_upload');
        //     return view('document_upload', $data);
        // }
    }

    public function immunization_verify(Request $request, $id, $ret='', $did='')
    {
        $data['hash'] = '';
        $data['ajax'] = route('dashboard');
        $data['ajax1'] = route('dashboard');
        $data['uport_need'] = '';
        $data['uport_id'] = '';
        $data['panel_header'] = trans('noshform.immunization_view3');
        $data['url'] = route('immunization_verify', [$id]);
        $query = DB::table('immunizations')->where('imm_id', '=', $id)->first();
        $query1 = DB::table('fhir_json')->where('table', '=', 'immunizations')->where('index', '=', $id)->first();
        $data['content'] = '';
        $outcome = '';
        if ($query) {
            if ($query->id !== null && $query->id !== '') {
                if ($query1->transaction !== '' && $query1->transaction !== null) {
                    $data['tx_hash'] = $query->transaction;
                    $data['imm_json'] = $query->json;
                    $hash = hash('sha256', $query->json);
                    $etherscan_uri = "https://rinkeby.etherscan.io/tx/" . $query->transaction;
                    $ch = curl_init();
                    curl_setopt($ch,CURLOPT_URL, $etherscan_uri);
                    curl_setopt($ch,CURLOPT_FAILONERROR,1);
                    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
                    $return_data = curl_exec($ch);
                    curl_close($ch);
                    $html = HTMLDomParser::str_get_html($return_data);
                    $data_row = $html->find('span[id=rawinput]',0);
                    $data['inputdata'] = $data_row->innertext;
                    $outcome = '';
                    $items[] = [
                        'name' => 'imm_json',
                        'label' => trans('noshform.imm_json'),
                        'type' => 'textarea',
                        // 'readonly' => true,
                        'default_value' => $query->json
                    ];
                    $items[] = [
                        'name' => 'hash',
                        'label' => trans('noshform.imm_hash'),
                        'type' => 'text',
                        'readonly' => true,
                        'default_value' => $hash
                    ];
                    $items[] = [
                        'name' => 'tx_hash',
                        'label' => trans('noshform.tx_hash'),
                        'type' => 'text',
                        'readonly' => true,
                        'default_value' => $query->transaction
                    ];
                    $items[] = [
                        'name' => 'tx_data',
                        'label' => trans('noshform.tx_data'),
                        'type' => 'textarea',
                        'readonly' => true,
                        'default_value' => $data['inputdata']
                    ];
                    if ($request->isMethod('post')) {
                        $data['uport_need'] = 'validate';
                        Session::put('imm_json', $request->input('imm_json'));
                        Session::put('hash', hash('sha256', $request->input('imm_json')));
                    }
                    if (Session::has('imm_json')) {
                        if ($ret !== '') {
                            $items[0]['default_value'] = Session::get('imm_json');
                            $items[1]['default_value'] = Session::get('hash');
                            Session::forget('imm_json');
                            Session::forget('hash');
                            // $bytes = 13 * 64;
                            // $rx_hash = substr(substr(substr($ret, 18), $bytes), 0, -56);
                            $imm_hash1 = $ret;
                            $items[] = [
                                'name' => 'imm_hash1',
                                'label' => trans('noshform.imm_hash1'),
                                'type' => 'text',
                                'readonly' => true,
                                'default_value' => $imm_hash1
                            ];
                            $items[] = [
                                'name' => 'did',
                                'label' => trans('noshform.did'),
                                'type' => 'text',
                                'readonly' => true,
                                'default_value' => $did
                            ];
                            $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.immunization_view4') . '</strong> - ' . trans('noshform.immunization_view5') . '.</div>';
                            if ($rx_hash == $items[1]['default_value']) {
                                $outcome = '<div class="alert alert-success"><strong>' . trans('noshform.immunization_view6') . '</strong></div>';
                            }
                        }
                    }
                    $form_array = [
                        'form_id' => 'immunization_form',
                        'action' => route('immunization_verify', [$id]),
                        'items' => $items,
                        'save_button_label' => trans('noshform.validate'),
                        'remove_cancel' => true
                    ];
                    $data['content'] .= $this->form_build($form_array);
                } else {
                    $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.prescription_pharmacy_view1') . '</strong> - ' . trans('noshform.prescription_pharmacy_view4') . '.</div>';
                }
            } else {
                $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.prescription_pharmacy_view1') . '</strong> - ' . trans('noshform.prescription_pharmacy_view7') . '.</div>';
            }
        } else {
            $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.prescription_pharmacy_view1') . '</strong> - ' . trans('noshform.prescription_pharmacy_view8') . '.</div>';
        }
        $data['content'] .= $outcome;
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('credible', $data);
    }

    public function install(Request $request, $type)
    {
        $query = DB::table('practiceinfo')->first();
        if ($query) {
            return redirect()->route('dashboard');
        }
        // Tag version number for baseline prior to updating system in the future
        if (env('DOCKER') == null || env('DOCKER') == '0') {
            if (env('DOCKER') == null) {
                $env_arr['DOCKER'] = '0';
                $this->changeEnv($env_arr);
            }
            if (!File::exists(base_path() . "/.version")) {
                // First time after install
                $result = $this->github_all();
                File::put(base_path() . '/.version', $result[0]['sha']);
            }
        }
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'password' => 'min:4',
                'confirm_password' => 'min:4|same:password',
            ]);
            $username = $request->input('username');
            $password = substr_replace(Hash::make($request->input('password')),"$2a",0,3);
            $email = $request->input('email');
            if ($type == 'practice') {
                $practice_name = $request->input('practice_name');
                $street_address1 = $request->input('street_address1');
                $street_address2 = $request->input('street_address2');
                $phone = $request->input('phone');
                $fax = $request->input('fax');
                $patient_centric = 'n';
            } else {
                $practice_name = "NOSH for Patient: " . $request->input('firstname') . ' ' . $request->input('lastname');
                $street_address1 = $request->input('address');
                $street_address2 = '';
                $phone = '';
                $fax = '';
                $patient_centric = 'y';
            }
            $country = $request->input('country');
            $city = $request->input('city');
            $state = $request->input('state');
            $zip = $request->input('zip');
            $data1 = [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'group_id' => '1',
                'displayname' => 'Administrator',
                'active' => '1',
                'practice_id' => '1'
            ];
            $user_id = DB::table('users')->insertGetId($data1);
            $this->audit('Add');
            // Insert practice
            $data2 = [
                'practice_name' => $practice_name,
                'street_address1' => $street_address1,
                'street_address2' => $street_address2,
                'country' => $country,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'phone' => $phone,
                'fax' => $fax,
                'email' => $email,
                'fax_type' => '',
                'vivacare' => '',
                'version' => '2.0.0',
                'active' => 'Y',
                'patient_centric' => $patient_centric
            ];
            $practice_id = DB::table('practiceinfo')->insertGetId($data2, 'practice_id');
            $this->audit('Add');
            $data_jwk = [];
            $data_jwk['private_jwk'] = JWKFactory::createRSAKey(
            4096, // Size in bits of the key. We recommend at least 2048 bits.
            [
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $this->gen_uuid()
            ]);
            $data_jwk['public_jwk'] = json_encode($data_jwk['private_jwk']->toPublic());
            $data_jwk['private_jwk'] = json_encode($data_jwk['private_jwk']);
            $data_jwk['practice_id'] = $practice_id;
            DB::table('practiceinfo_plus')->insert($data_jwk);
            $this->audit('Add');
            // Insert patient
            if ($type == 'patient') {
                $dob = date('Y-m-d', strtotime($request->input('DOB')));
                $displayname = $request->input('firstname') . " " . $request->input('lastname');
                $patient_data = [
                    'lastname' => $request->input('lastname'),
                    'firstname' => $request->input('firstname'),
                    'DOB' => $dob,
                    'sex' => $request->input('gender'),
                    'active' => '1',
                    'sexuallyactive' => 'no',
                    'tobacco' => 'no',
                    'pregnant' => 'no',
                    'address' => $street_address1,
                    'country' => $country,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip
                ];
                $pid = DB::table('demographics')->insertGetId($patient_data, 'pid');
                $this->audit('Add');
                $patient_data1 = [
                    'billing_notes' => '',
                    'imm_notes' => '',
                    'pid' => $pid,
                    'practice_id' => '1'
                ];
                DB::table('demographics_notes')->insert($patient_data1);
                $this->audit('Add');
                $patient_data2 = [
                    'username' => $request->input('pt_username'),
                    'firstname' => $request->input('firstname'),
                    'middle' => '',
                    'lastname' => $request->input('lastname'),
                    'title' => '',
                    'displayname' => $displayname,
                    'email' => $request->input('email'),
                    'group_id' => '100',
                    'active'=> '1',
                    'practice_id' => '1',
                    'password' => $this->gen_uuid()
                ];
                $patient_user_id = DB::table('users')->insertGetId($patient_data2);
                $this->audit('Add');
                $patient_data3 = [
                    'pid' => $pid,
                    'practice_id' => '1',
                    'id' => $patient_user_id
                ];
                DB::table('demographics_relate')->insert($patient_data3);
                $this->audit('Add');
                Storage::makeDirectory($pid);
                $pnosh_url = $request->root();
                $pnosh_url = str_replace(array('http://','https://'), '', $pnosh_url);
                $root_url = explode('/', $pnosh_url);
                $root_url1 = explode('.', $root_url[0]);
                $root_url1 = array_slice($root_url1, -2, 2, false);
                $final_root_url = implode('.', $root_url1);
                if (env('DOCKER') == '0') {
                    if ($final_root_url == 'hieofone.org') {
                        $mailgun_url = 'https://dir.' . $final_root_url . '/mailgun';
                        $params = ['uri' => $root_url[0]];
                        $post_body = json_encode($params);
                        $content_type = 'application/json';
                        $ch = curl_init();
                        curl_setopt($ch,CURLOPT_URL, $mailgun_url);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            "Content-Type: {$content_type}",
                            'Content-Length: ' . strlen($post_body)
                        ]);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                        curl_setopt($ch,CURLOPT_FAILONERROR,1);
                        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
                        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
                        $mailgun_secret = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close ($ch);
                        if ($httpCode !== 404 && $httpCode !== 0) {
                            if ($mailgun_secret !== 'Not authorized.' && $mailgun_secret !== 'Try again.') {
                                $mail_arr = [
                                    'MAIL_DRIVER' => 'mailgun',
                                    'MAILGUN_DOMAIN' => 'mg.hieofone.org',
                                    'MAILGUN_SECRET' => $mailgun_secret,
                                    'MAIL_HOST' => '',
                                    'MAIL_PORT' => '',
                                    'MAIL_ENCRYPTION' => '',
                                    'MAIL_USERNAME' => '',
                                    'MAIL_PASSWORD' => '',
                                    'GOOGLE_KEY' => '',
                                    'GOOGLE_SECRET' => '',
                                    'GOOGLE_REDIRECT_URI' => ''
                                ];
                                $this->changeEnv($mail_arr);
                            } else {

                            }
                        }
                    }
                }
            } else {
                $displayname = 'Administrator';
            }
            // Insert groups
            $groups_data[] = [
                'id' => '1',
                'title' => 'admin',
                'description' => 'Administrator'
            ];
            $groups_data[] = [
                'id' => '2',
                'title' => 'provider',
                'description' => 'Provider'
            ];
            $groups_data[] = [
                'id' => '3',
                'title' => 'assistant',
                'description' => 'Assistant'
            ];
            $groups_data[] = [
                'id' => '4',
                'title' => 'billing',
                'description' => 'Billing'
            ];
            $groups_data[] = [
                'id' => '100',
                'title' => 'patient',
                'description' => 'Patient'
            ];
            foreach ($groups_data as $group) {
                DB::table('groups')->insert($group);
                $this->audit('Add');
            }
            // Insert default calendar class
            $calendar = [
                'visit_type' => 'Closed',
                'classname' => 'colorblack',
                'active' => 'y',
                'practice_id' => '1'
            ];
            DB::table('calendar')->insert($calendar);
            $this->audit('Add');
            if ($type == 'practice') {
                Auth::attempt(['username' => $username, 'password' => $request->input('password'), 'active' => '1', 'practice_id' => '1']);
                Session::put('user_id', $user_id);
                Session::put('group_id', '1');
                Session::put('practice_id', '1');
                Session::put('version', $data2['version']);
                Session::put('practice_active', $data2['active']);
                Session::put('displayname', $displayname);
                Session::put('patient_centric', $data2['patient_centric']);
            }
            return redirect()->route('dashboard');
        } else {
            $pt_username = null;
            if (array_key_exists('pnosh_username', $_COOKIE)) {
                $pt_username = $_COOKIE['pnosh_username'];
            }
            $lastname = null;
            if (array_key_exists('pnosh_lastname', $_COOKIE)) {
                $lastname = $_COOKIE['pnosh_lastname'];
            }
            $firstname = null;
            if (array_key_exists('pnosh_firstname', $_COOKIE)) {
                $firstname = $_COOKIE['pnosh_firstname'];
            }
            $dob = null;
            if (array_key_exists('pnosh_dob', $_COOKIE)) {
                $dob = $_COOKIE['pnosh_dob'];
            }
            $email = null;
            if (array_key_exists('pnosh_email', $_COOKIE)) {
                $email = $_COOKIE['pnosh_email'];
            }
            $data['panel_header'] = 'NOSH ChartingSystem Installation';
            $items[] = [
                'name' => 'username',
                'label' => trans('noshform.admin_username'),
                'type' => 'text',
                'required' => true,
                'default_value' => 'admin'
            ];
            $items[] = [
                'name' => 'password',
                'label' => trans('noshform.admin_password'),
                'type' => 'password',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'confirm_password',
                'label' => trans('noshform.confirm_password'),
                'type' => 'password',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'email',
                'label' => trans('noshform.email'),
                'type' => 'email',
                'required' => true,
                'default_value' => $email
            ];
            if ($type == 'patient') {
                $items[] = [
                    'name' => 'pt_username',
                    'label' => trans('noshform.pt_username'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $pt_username
                ];
                $items[] = [
                    'name' => 'lastname',
                    'label' => trans('noshform.lastname'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $lastname
                ];
                $items[] = [
                    'name' => 'firstname',
                    'label' => trans('noshform.firstname'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $firstname
                ];
                $items[] = [
                    'name' => 'DOB',
                    'label' => trans('noshform.DOB'),
                    'type' => 'date',
                    'required' => true,
                    'default_value' => $dob
                ];
                $items[] = [
                    'name' => 'gender',
                    'label' => trans('noshform.sex'),
                    'type' => 'select',
                    'select_items' => $this->array_gender(),
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'address',
                    'label' => trans('noshform.street_address1'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
            } else {
                $items[] = [
                    'name' => 'practice_name',
                    'label' => trans('noshform.practice_name'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'street_address1',
                    'label' => trans('noshform.street_address1'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'street_address2',
                    'label' => trans('noshform.street_address2'),
                    'type' => 'text',
                    'default_value' => null
                ];
            }
            $items[] = [
                'name' => 'country',
                'label' => trans('noshform.country'),
                'type' => 'select',
                'select_items' => $this->array_country(),
                'required' => true,
                'default_value' => 'United States',
                'class' => 'country'
            ];
            $items[] = [
                'name' => 'city',
                'label' => trans('noshform.city'),
                'type' => 'text',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'state',
                'label' => trans('noshform.state'),
                'type' => 'select',
                'select_items' => $this->array_states(),
                'required' => true,
                'default_value' => null,
                'class' => 'state'
            ];
            $items[] = [
                'name' => 'zip',
                'label' => trans('noshform.zip'),
                'type' => 'text',
                'required' => true,
                'default_value' => null
            ];
            if ($type !== 'patient') {
                $items[] = [
                    'name' => 'phone',
                    'label' => trans('noshform.phone'),
                    'type' => 'text',
                    'phone' => true,
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'fax',
                    'label' => trans('noshform.fax'),
                    'type' => 'text',
                    'phone' => true,
                    'required' => true,
                    'default_value' => null
                ];
            }
            $form_array = [
                'form_id' => 'install_form',
                'action' => route('install', [$type]),
                'items' => $items,
                'save_button_label' => 'Install'
            ];
            $data['content'] = '<p>' . trans('noshform.install1') . '</p>';
            $data['content'] .= $this->form_build($form_array);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function install_fix(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'db_password' => 'min:4',
                'confirm_db_password' => 'min:4|same:db_password',
            ]);
            $db_username = $request->input('db_username');
            $db_password = $request->input('db_password');
            $connect = mysqli_connect('localhost', $db_username, $db_password);
            $db = mysqli_select_db($connect, env('DB_DATABASE'));
            if ($db) {
                $path = base_path() . '/.env';
                if (file_exists($path)) {
                    file_put_contents($path, str_replace(
                        'DB_USERNAME=' . env('DB_USERNAME'), 'DB_USERNAME='. $db_username, file_get_contents($path)
                    ));
                    file_put_contents($path, str_replace(
                        'DB_PASSWORD=' . env('DB_PASSWORD'), 'DB_PASSWORD='. $db_password, file_get_contents($path)
                    ));
                }
                Session::put('message_action', trans('noshform.install_fix1'));
                return redirect()->route('dashboard');
            } else {
                Session::put('message_action', trans('noshform.error') . ' - ' . trans('noshform.install_fix2'));
                return redirect()->route('install_fix');
            }
        } else {
            $items[] = [
                'name' => 'db_username',
                'label' => trans('noshform.db_username'),
                'type' => 'text',
                'required' => true,
                'default_value' => env('DB_USERNAME')
            ];
            $items[] = [
                'name' => 'db_password',
                'label' => trans('noshform.db_password'),
                'type' => 'password',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'confirm_db_password',
                'label' => trans('noshform.confirm_password'),
                'type' => 'password',
                'required' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'install_fix_form',
                'action' => route('install_fix'),
                'items' => $items,
                'save_button_label' => 'Save'
            ];
            $data['panel_header'] = trans('noshform.install_fix3');
            $data['content'] = $this->form_build($form_array);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function jwk_install(Request $request)
    {
        $query = DB::table('practiceinfo')->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $data = [];
                $data['private_jwk'] = JWKFactory::createRSAKey(
                4096, // Size in bits of the key. We recommend at least 2048 bits.
                [
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => $this->gen_uuid()
                ]);
                $data['public_jwk'] = json_encode($data['private_jwk']->toPublic());
                $data['private_jwk'] = json_encode($data['private_jwk']);
                $data['practice_id'] = $row->practice_id;
                DB::table('practiceinfo_plus')->insert($data);
                $this->audit('Add');
            }
        }
        return redirect()->route('dashboard');
    }

    public function pnosh_install(Request $request)
    {
        $query = DB::table('practiceinfo')->first();
        if ($query) {
            return 'Error - Already installed';
        }
        if (env('DOCKER') == null || env('DOCKER') == '0') {
            if (env('DOCKER') == null) {
                $env_arr['DOCKER'] = '0';
                $this->changeEnv($env_arr);
            }
            if (!File::exists(base_path() . '/.patientcentric')) {
                return 'Error - Not pNOSH';
            }
            // Tag version number for baseline prior to updating system in the future
            if (!File::exists(base_path() . "/.version")) {
                // First time after install
                $result = $this->github_all();
                File::put(base_path() . '/.version', $result[0]['sha']);
            }
        } else {
            if (env('PATIENTCENTRIC') != '1') {
                return 'Error - Not pNOSH';
            }
        }
        // $this->validate($request, [
        //     'password' => 'min:4',
        //     'confirm_password' => 'min:4|same:password',
        // ]);
        $username = $request->input('username');
        $password = substr_replace(Hash::make($request->input('password')),"$2a",0,3);
        $email = $request->input('email');
        $mobile = $request->input('mobile');
        $practice_name = "NOSH for Patient: " . $request->input('firstname') . ' ' . $request->input('lastname');
        $street_address1 = $request->input('address');
        $street_address2 = '';
        $phone = '';
        $fax = '';
        $patient_centric = 'y';
        $city = $request->input('city');
        $state = $request->input('state');
        $zip = $request->input('zip');
        // Insert Administrator
        $data1 = [
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'group_id' => '1',
            'displayname' => 'Administrator',
            'active' => '1',
            'practice_id' => '1'
        ];
        $user_id = DB::table('users')->insertGetId($data1);
        $this->audit('Add');
        // Insert practice
        $data2 = [
            'practice_name' => $practice_name,
            'street_address1' => $street_address1,
            'street_address2' => $street_address2,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'phone' => $phone,
            'fax' => $fax,
            'email' => $email,
            'fax_type' => '',
            'vivacare' => '',
            'version' => '2.0.0',
            'active' => 'Y',
            'patient_centric' => $patient_centric,
            'weight_unit' => 'lbs',
            'height_unit' => 'in',
            'temp_unit' => 'F',
            'hc_unit' => 'cm',
            'default_pos_id' => '11'
        ];
        DB::table('practiceinfo')->insert($data2);
        $this->audit('Add');
        // Insert patient
        $dob = date('Y-m-d', strtotime($request->input('DOB')));
        $displayname = $request->input('firstname') . " " . $request->input('lastname');
        $patient_data = [
            'lastname' => $request->input('lastname'),
            'firstname' => $request->input('firstname'),
            'DOB' => $dob,
            'sex' => $request->input('gender'),
            'active' => '1',
            'sexuallyactive' => 'no',
            'tobacco' => 'no',
            'pregnant' => 'no',
            'address' => $street_address1,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'email' => $email,
            'phone_cell' => $mobile
        ];
        $pid = DB::table('demographics')->insertGetId($patient_data, 'pid');
        $this->audit('Add');
        $patient_data1 = [
            'billing_notes' => '',
            'imm_notes' => '',
            'pid' => $pid,
            'practice_id' => '1'
        ];
        DB::table('demographics_notes')->insert($patient_data1);
        $this->audit('Add');
        $patient_data2 = [
            'username' => $request->input('pt_username'),
            'firstname' => $request->input('firstname'),
            'middle' => '',
            'lastname' => $request->input('lastname'),
            'title' => '',
            'displayname' => $displayname,
            'email' => $request->input('email'),
            'group_id' => '100',
            'active'=> '1',
            'practice_id' => '1',
            'password' => $this->gen_uuid()
        ];
        $patient_user_id = DB::table('users')->insertGetId($patient_data2);
        $this->audit('Add');
        $patient_data3 = [
            'pid' => $pid,
            'practice_id' => '1',
            'id' => $patient_user_id
        ];
        DB::table('demographics_relate')->insert($patient_data3);
        $this->audit('Add');
        $patient_data4 = [
            'pid' => $pid,
            'date_added' => date('Y-m-d H:i:s'),
            'gnap_uri' => $request->input('gnap_uri'),
            'gnap_client_id' => $request->input('gnap_client_id')
        ];
        DB::table('demographics_plus')->insert($patient_data4);
        $this->audit('Add');
        Storage::makeDirectory($pid);
        $postData = $request->post();
        $img = Arr::has($postData, 'photo_data');
        if ($img) {
            $img_data = substr($request->input('photo_data'), strpos($request->input('photo_data'), ',') + 1);
            $img_data = base64_decode($img_data);
            $file_path = Storage::path($pid . "/" . $request->input('photo_filename'));
            $patient_data5['photo'] = $file_path;
            File::put($file_path, $img_data);
            DB::table('demographics')->where('pid', '=', $pid)->update($patient_data5);
            $this->audit('Add');
        }
        if (env('DOCKER') == '0') {
            $mail_arr = [
                'MAIL_DRIVER' => 'mailgun',
                'MAILGUN_DOMAIN' => 'mg.hieofone.org',
                'MAILGUN_SECRET' => $request->input('mailgun_secret'),
                'MAIL_HOST' => '',
                'MAIL_PORT' => '',
                'MAIL_ENCRYPTION' => '',
                'MAIL_USERNAME' => '',
                'MAIL_PASSWORD' => '',
                'GOOGLE_KEY' => '',
                'GOOGLE_SECRET' => '',
                'GOOGLE_REDIRECT_URI' => ''
            ];
            $this->changeEnv($mail_arr);
        }
        // Insert groups
        $groups_data[] = [
            'id' => '1',
            'title' => 'admin',
            'description' => 'Administrator'
        ];
        $groups_data[] = [
            'id' => '2',
            'title' => 'provider',
            'description' => 'Provider'
        ];
        $groups_data[] = [
            'id' => '3',
            'title' => 'assistant',
            'description' => 'Assistant'
        ];
        $groups_data[] = [
            'id' => '4',
            'title' => 'billing',
            'description' => 'Billing'
        ];
        $groups_data[] = [
            'id' => '100',
            'title' => 'patient',
            'description' => 'Patient'
        ];
        foreach ($groups_data as $group) {
            DB::table('groups')->insert($group);
            $this->audit('Add');
        }
        // Insert default calendar class
        $calendar = [
            'visit_type' => 'Closed',
            'classname' => 'colorblack',
            'active' => 'y',
            'practice_id' => '1'
        ];
        DB::table('calendar')->insert($calendar);
        $this->audit('Add');
        return 'Success';
    }

    public function prescription_pharmacy_view(Request $request, $id, $ret='', $did='')
    {
        $data['hash'] = '';
        $data['ajax'] = route('dashboard');
        $data['ajax1'] = route('dashboard');
        $data['uport_need'] = '';
        $data['uport_id'] = '';
        $data['panel_header'] = trans('noshform.prescription_pharmacy_view9');
        $data['url'] = route('prescription_pharmacy_view', [$id]);
        $query = DB::table('rx_list')->where('rxl_id', '=', $id)->first();
        $data['content'] = '';
        $outcome = '';
        if ($query) {
            if ($query->id !== null && $query->id !== '') {
                if ($query->rxl_date_old == '0000-00-00 00:00:00') {
                    ini_set('memory_limit','196M');
                    $html = $this->page_medication($id, $query->pid);
                    $name = time() . "_rx.pdf";
                    $file_path = public_path() . "/temp/" . $name;
                    $this->generate_pdf($html, $file_path, 'footerpdf', '', '2', '', 'void');
                    while(!file_exists($file_path)) {
                        sleep(2);
                    }
                    $imagick = new Imagick();
                    $imagick->setResolution(100, 100);
                    $imagick->readImage($file_path);
                    $imagick->writeImages(public_path() . '/temp/' . $name . '_pages.png', false);
                    $data['rx_jpg'] = asset('temp/' . $name . '_pages.png');
                    $data['document_url'] = asset('temp/' . $name);
                    if ($query->transaction !== '' && $query->transaction !== null) {
                        $data['tx_hash'] = $query->transaction;
                        $data['rx_json'] = $query->json;
                        $hash = hash('sha256', $query->json);
                        $etherscan_uri = "https://rinkeby.etherscan.io/tx/" . $query->transaction;
                        $ch = curl_init();
                        curl_setopt($ch,CURLOPT_URL, $etherscan_uri);
                        curl_setopt($ch,CURLOPT_FAILONERROR,1);
                        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
                        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
                        $return_data = curl_exec($ch);
                        curl_close($ch);
                        $html = HTMLDomParser::str_get_html($return_data);
                        $data_row = $html->find('span[id=rawinput]',0);
                        $data['inputdata'] = $data_row->innertext;
                        $outcome = '';
                        $items[] = [
                            'name' => 'rx_json',
                            'label' => trans('noshform.rx_json'),
                            'type' => 'textarea',
                            // 'readonly' => true,
                            'default_value' => $query->json
                        ];
                        $items[] = [
                            'name' => 'hash',
                            'label' => trans('noshform.prescription_hash'),
                            'type' => 'text',
                            'readonly' => true,
                            'default_value' => $hash
                        ];
                        $items[] = [
                            'name' => 'tx_hash',
                            'label' => trans('noshform.tx_hash'),
                            'type' => 'text',
                            'readonly' => true,
                            'default_value' => $query->transaction
                        ];
                        $items[] = [
                            'name' => 'tx_data',
                            'label' => trans('noshform.tx_data'),
                            'type' => 'textarea',
                            'readonly' => true,
                            'default_value' => $data['inputdata']
                        ];
                        if ($request->isMethod('post')) {
                            $data['uport_need'] = 'validate';
                            Session::put('rx_json', $request->input('rx_json'));
                            Session::put('hash', hash('sha256', $request->input('rx_json')));
                        }
                        if (Session::has('rx_json')) {
                            if ($ret !== '') {
                                $items[0]['default_value'] = Session::get('rx_json');
                                $items[1]['default_value'] = Session::get('hash');
                                Session::forget('rx_json');
                                Session::forget('hash');
                                // $bytes = 13 * 64;
                                // $rx_hash = substr(substr(substr($ret, 18), $bytes), 0, -56);
                                $rx_hash = $ret;
                                $items[] = [
                                    'name' => 'rx_hash',
                                    'label' => trans('noshform.rx_hash'),
                                    'type' => 'text',
                                    'readonly' => true,
                                    'default_value' => $rx_hash
                                ];
                                $items[] = [
                                    'name' => 'did',
                                    'label' => trans('noshform.did'),
                                    'type' => 'text',
                                    'readonly' => true,
                                    'default_value' => $did
                                ];
                                $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.prescription_pharmacy_view1') . '</strong> - ' . trans('noshform.prescription_pharmacy_view2') . '.</div>';
                                if ($rx_hash == $items[1]['default_value']) {
                                    $outcome = '<div class="alert alert-success"><strong>' . trans('noshform.prescription_pharmacy_view3') . '</strong></div>';
                                }
                            }
                        }
                        $form_array = [
                            'form_id' => 'prescription_form',
                            'action' => route('prescription_pharmacy_view', [$id]),
                            'items' => $items,
                            'save_button_label' => trans('noshform.validate'),
                            'remove_cancel' => true
                        ];
                        $data['content'] .= $this->form_build($form_array);
                    } else {
                        $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.prescription_pharmacy_view1') . '</strong> - ' . trans('noshform.prescription_pharmacy_view4') . '.</div>';
                    }
                } else {
                    $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.prescription_pharmacy_view5') . '</strong> - ' . trans('noshform.prescription_pharmacy_view6') . '.</div>';
                }
            } else {
                $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.prescription_pharmacy_view1') . '</strong> - ' . trans('noshform.prescription_pharmacy_view7') . '.</div>';
            }
        } else {
            $outcome = '<div class="alert alert-danger"><strong>' . trans('noshform.prescription_pharmacy_view1') . '</strong> - ' . trans('noshform.prescription_pharmacy_view8') . '.</div>';
        }
        $data['content'] .= $outcome;
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('uport1', $data);
    }

    public function set_version(Request $request)
    {
        $result = $this->github_all();
        File::put(base_path() . '/.version', $result[0]['sha']);
        return redirect()->route('dashboard');
    }

    public function setup_mail(Request $request)
    {
        if (Session::get('group_id') == '1') {
            if ($request->isMethod('post')) {
                $this->validate($request, [
                    'mail_type' => 'required'
                ]);
                $mail_arr = [
                    'gmail' => [
                        'MAIL_DRIVER' => 'smtp',
                        'MAIL_HOST' => 'smtp.gmail.com',
                        'MAIL_PORT' => 465,
                        'MAIL_ENCRYPTION' => 'ssl',
                        'MAIL_USERNAME' => $request->input('mail_username'),
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => $request->input('google_client_id'),
                        'GOOGLE_SECRET' => $request->input('google_client_secret'),
                        'GOOGLE_REDIRECT_URI' => route('googleoauth')
                    ],
                    'mailgun' => [
                        'MAIL_DRIVER' => 'mailgun',
                        'MAILGUN_DOMAIN' => $request->input('mailgun_domain'),
                        'MAILGUN_SECRET' => $request->input('mailgun_secret'),
                        'MAIL_HOST' => '',
                        'MAIL_PORT' => '',
                        'MAIL_ENCRYPTION' => '',
                        'MAIL_USERNAME' => '',
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ],
                    'sparkpost' => [
                        'MAIL_DRIVER' => 'sparkpost',
                        'SPARKPOST_SECRET' => $request->input('sparkpost_secret'),
                        'MAIL_HOST' => '',
                        'MAIL_PORT' => '',
                        'MAIL_ENCRYPTION' => '',
                        'MAIL_USERNAME' => '',
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ],
                    'ses' => [
                        'MAIL_DRIVER' => 'ses',
                        'SES_KEY' => $request->input('ses_key'),
                        'SES_SECRET' => $request->input('ses_secret'),
                        'MAIL_HOST' => '',
                        'MAIL_PORT' => '',
                        'MAIL_ENCRYPTION' => '',
                        'MAIL_USERNAME' => '',
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ],
                    'unique' => [
                        'MAIL_DRIVER' => 'smtp',
                        'MAIL_HOST' => $request->input('mail_host'),
                        'MAIL_PORT' => $request->input('mail_port'),
                        'MAIL_ENCRYPTION' => $request->input('mail_encryption'),
                        'MAIL_USERNAME' => $request->input('mail_username'),
                        'MAIL_PASSWORD' => $request->input('mail_password'),
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ],
                    'none' => [
                        'MAIL_DRIVER' => 'none',
                        'MAILGUN_DOMAIN' => '',
                        'MAILGUN_SECRET' => '',
                        'MAIL_HOST' => '',
                        'MAIL_PORT' => '',
                        'MAIL_ENCRYPTION' => '',
                        'MAIL_USERNAME' => '',
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ],
                ];
                $this->changeEnv($mail_arr[$request->input('mail_type')]);
                $clear = new Process(["php artisan config:clear"]);
                if ($request->input('mail_type') == 'gmail') {
                    return redirect()->route('googleoauth');
                } else {
                    if ($request->input('mail_type') !== 'none') {
                        return redirect()->route('setup_mail_test');
                    } else {
                        return redirect()->route('dashboard');
                    }
                }
            } else {
                $data2['noheader'] = true;
                $data2['assets_js'] = $this->assets_js();
                $data2['assets_css'] = $this->assets_css();
                $data2['mail_type'] = '';
                $data2['mail_host'] = env('MAIL_HOST');
                $data2['mail_port'] = env('MAIL_PORT');
                $data2['mail_encryption'] = env('MAIL_ENCRYPTION');
                $data2['mail_username'] = env('MAIL_USERNAME');
                $data2['mail_password'] = env('MAIL_PASSWORD');
                $data2['google_client_id'] = env('GOOGLE_KEY');
                $data2['google_client_secret'] = env('GOOGLE_SECRET');
                $data2['mail_username'] = env('MAIL_USERNAME');
                $data2['mailgun_domain'] = env('MAILGUN_DOMAIN');
                $data2['mailgun_secret'] = env('MAILGUN_SECRET');
                $data2['mail_type'] == 'sparkpost';
                $data2['sparkpost_secret'] = env('SPARKPOST_SECRET');
                $data2['ses_key'] = env('SES_KEY');
                $data2['ses_secret'] = env('SES_SECRET');
                if (env('MAIL_DRIVER') == 'smtp') {
                    if (env('MAIL_HOST') == 'smtp.gmail.com') {
                        $data2['mail_type'] = 'gmail';
                    } else {
                        $data2['mail_type'] = 'unique';
                    }
                } else {
                    $data2['mail_type'] = env('MAIL_DRIVER');
                }
                $data2['message_action'] = Session::get('message_action');
                Session::forget('message_action');
                return view('setup_mail', $data2);
            }
        } else {
            return redirect()->route('dashboard');
        }
    }

    public function setup_mail_test(Request $request)
    {
        $data_message['item'] = 'This is a test';
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $message_action = trans('noshform.setup_mail_test1');
        $mail = $this->send_mail('emails.blank', $data_message, 'Test E-mail', $practice->email, Session::get('practice_id'));
        if (!$mail) {
            return redirect()->route('setup_mail');
        }
        // try {
        //     $this->send_mail('emails.blank', $data_message, 'Test E-mail', $practice->email, Session::get('practice_id'));
        // } catch(\Exception $e){
        //     $message_action = trans('noshform.error') . ' - ' . trans('noshform.setup_mail_test2');
        //     Session::put('message_action', $message_action);
        //     return redirect()->route('setup_mail');
        // }
        Session::put('message_action', $message_action);
        return redirect()->route('dashboard');
    }

    public function uma_patient_centric(Request $request)
    {
        $query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        if ($query->patient_centric == 'y') {
            if ($query->uma_client_id == '') {
                // Chcek if AS set previously in uma_patient_centric_designate
                if ($query->uma_uri == '' || $query->uma_uri == null) {
                    // Check if AS is on the same $domain_name
                    $check_open_id_url = str_replace('/nosh', '/.well-known/uma2-configuration', URL::to('/'));
                    $ch = curl_init();
                    curl_setopt($ch,CURLOPT_URL, $check_open_id_url);
                    curl_setopt($ch,CURLOPT_FAILONERROR,1);
                    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
                    if (file_exists(base_path() . '/fakelerootx1.pem')) {
                        curl_setopt($ch, CURLOPT_CAINFO, base_path() . '/fakelerootx1.pem');
                    }
                    $check_exec = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close ($ch);
                    if ($httpCode == 404 || $httpCode == 0) {
                        return redirect()->route('uma_patient_centric_designate');
                    }
                    $data['uma_uri'] = str_replace('/nosh', '', URL::to('/'));
                } else {
                    $data['uma_uri'] = $query->uma_uri;
                }
                // Register as resource server
                $patient = DB::table('demographics')->where('pid', '=', '1')->first();
                // $client_name = 'Patient NOSH for ' .  $patient->firstname . ' ' . $patient->lastname . ', DOB: ' . date('Y-m-d', strtotime($patient->DOB));
                $client_name = 'Patient NOSH for ' .  $patient->firstname . ' ' . $patient->lastname;
                $url = route('uma_auth');
                $oidc = new OpenIDConnectUMAClient($data['uma_uri']);
                $oidc->startSession();
                $oidc->setClientName($client_name);
                $oidc->setSessionName('pnosh');
                $oidc->addRedirectURLs($url);
                $oidc->addRedirectURLs(route('uma_api'));
                $oidc->addRedirectURLs(route('uma_logout'));
                $oidc->addRedirectURLs(route('uma_patient_centric'));
                $oidc->addRedirectURLs(route('uma_register_auth'));
                $oidc->addRedirectURLs(route('oidc'));
                $oidc->addRedirectURLs(route('oidc_api'));
                $oidc->addRedirectURLs(str_replace('uma_auth', 'fhir', $url));
                $oidc->addScope('openid');
                $oidc->addScope('email');
                $oidc->addScope('profile');
                $oidc->addScope('address');
                $oidc->addScope('phone');
                $oidc->addScope('offline_access');
                $oidc->addScope('uma_protection');
                $oidc->addScope('uma_authorization');
                $oidc->addGrantType('authorization_code');
                $oidc->addGrantType('password');
                $oidc->addGrantType('client_credentials');
                $oidc->addGrantType('implicit');
                $oidc->addGrantType('jwt-bearer');
                $oidc->addGrantType('refresh_token');
                $oidc->setLogo('https://cloud.noshchartingsystem.com/SAAS-Logo.jpg');
                $oidc->setClientURI(str_replace('/uma_auth', '', $url));
                $oidc->setUMA(true);
                $oidc->register();
                $data['uma_client_id']  = $oidc->getClientID();
                $data['uma_client_secret'] = $oidc->getClientSecret();
                DB::table('practiceinfo')->where('practice_id', '=', '1')->update($data);
                $this->audit('Update');
                return redirect()->route('uma_patient_centric');
            } else {
                if ($query->uma_refresh_token == '') {
                    // Get refresh token and link patient with user
                    $client_id = $query->uma_client_id;
                    $client_secret = $query->uma_client_secret;
                    $open_id_url = $query->uma_uri;
                    $url = route('uma_patient_centric');
                    $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
                    $oidc->startSession();
                    $oidc->setRedirectURL($url);
                    $oidc->setSessionName('pnosh');
                    $oidc->setUMA(true);
                    $oidc->setUMAType('resource_server');
                    $oidc->authenticate();
                    $user_data['uid']  = $oidc->requestUserInfo('sub');
                    DB::table('users')->where('id', '=', '2')->update($user_data);
                    $access_token = $oidc->getAccessToken();
                    $uport_id = $oidc->requestUserInfo('uport_id');
                    if ($oidc->getRefreshToken() != '') {
                        $refresh_data['uma_refresh_token'] = $oidc->getRefreshToken();
                        DB::table('practiceinfo')->where('practice_id', '=', '1')->update($refresh_data);
                        $this->audit('Update');
                    }
                    // Register resource sets
                    $uma = DB::table('uma')->first();
                    if (!$uma) {
                        // First time
                        $resource_set_array[] = [
                            'name' => 'Patient from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-patient.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Patient',
                                URL::to('/') . '/fhir/Medication',
                                URL::to('/') . '/fhir/Practitioner',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Conditions from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-condition.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Condition',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Medications from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-pharmacy.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/MedicationStatement',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Allergies from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-allergy.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/AllergyIntolerance',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Immunizations from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-immunizations.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Immunization',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Encounters from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-medical-records.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Encounter',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Family History from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-family-practice.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/FamilyHistory',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Documents from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-file.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Binary',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Observations from Trustee',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-cardiology.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Observation',
                                'view',
                                'edit'
                            ]
                        ];
                        foreach ($resource_set_array as $resource_set_item) {
                            $response = $oidc->resource_set($resource_set_item['name'], $resource_set_item['icon'], $resource_set_item['scopes']);
                            if (isset($response['resource_set_id'])) {
                                foreach ($resource_set_item['scopes'] as $scope_item) {
                                    $response_data1 = [
                                        'resource_set_id' => $response['resource_set_id'],
                                        'scope' => $scope_item,
                                        'user_access_policy_uri' => $response['user_access_policy_uri']
                                    ];
                                    DB::table('uma')->insert($response_data1);
                                    $this->audit('Add');
                                }
                            }
                        }
                    }
                    // Login as owner
                    $user = DB::table('users')->where('id', '=', '2')->first();
                    Auth::loginUsingId($user->id);
                    $practice1 = DB::table('practiceinfo')->where('practice_id', '=', $user->practice_id)->first();
                    Session::put('user_id', $user->id);
                    Session::put('group_id', $user->group_id);
                    Session::put('practice_id', $user->practice_id);
                    Session::put('version', $practice1->version);
                    Session::put('practice_active', $practice1->active);
                    Session::put('displayname', $user->displayname);
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
                    // return redirect($open_id_url);
                }
            }
        }
        return redirect()->route('dashboard');
    }

    public function uma_patient_centric_designate(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'uri' => 'required'
            ]);
            $pre_url = rtrim($request->input('uri'), '/');
            $open_id_url = $pre_url . '/.well-known/uma2-configuration';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $open_id_url);
            curl_setopt($ch,CURLOPT_FAILONERROR,1);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_TIMEOUT, 60);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
            $domain_name = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close ($ch);
            if ($httpCode !== 404  && $httpCode !== 0) {
                $uma_data['uma_uri'] = $pre_url;
                $uma_data['uma_client_id'] = '';
                $uma_data['uma_client_secret'] = '';
                DB::table('practiceinfo')->where('practice_id', '=', '1')->update($uma_data);
                $this->audit('Update');
                return redirect()->route('uma_patient_centric');
            } else {
                Session::put('message_action', trans('noshform.error') . ' - ' . trans('noshform.uma_patient_centric_designate1') . '.');
                return redirect()->back();
            }
        } else {
            $data['panel_header'] = trans('noshform.uma_patient_centric_designate2');
            $items[] = [
                'name' => 'uri',
                'label' => trans('noshform.uri'),
                'type' => 'text',
                'required' => true,
                'value' => 'https://',
                'default_value' => 'https://'
            ];
            $form_array = [
                'form_id' => 'uma_form',
                'action' => route('uma_patient_centric_designate'),
                'items' => $items,
                'save_button_label' => trans('noshform.submit')
            ];
            $data['content'] = '<p>' . trans('noshform.uma_patient_centric_designate3') . '</p><p>' . trans('noshform.uma_patient_centric_designate4') . '</p>';
            $data['content'] .= $this->form_build($form_array);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            return view('core', $data);
        }
    }

    public function update()
    {
        $practice = DB::table('practiceinfo')->first();
        if ($practice->version < "2.0.0") {
            $this->update200();
        }
        return redirect()->route('dashboard');
    }

    public function update_env()
    {
        $base_arr = explode('/', base_path());
        end($base_arr);
        $key = key($base_arr);
        $base_arr[$key] = 'nosh-cs';
        $config_file = implode('/', $base_arr) . '/.env.php';
        if (file_exists($config_file)) {
            $config = require($config_file);
            $database = $config['mysql_database'];
            $username = $config['mysql_username'];
            $password = $config['mysql_password'];
            $connect = mysqli_connect('localhost', $config['mysql_username'], $config['mysql_password']);
            $db = mysqli_select_db($connect, $config['mysql_database']);
            if ($db) {
                $path = base_path() . '/.env';
                if (file_exists($path)) {
                    file_put_contents($path, str_replace(
                        'DB_DATABASE=' . env('DB_DATABASE'), 'DB_DATABASE='. $config['mysql_database'], file_get_contents($path)
                    ));
                    file_put_contents($path, str_replace(
                        'DB_USERNAME=' . env('DB_USERNAME'), 'DB_USERNAME='. $config['mysql_username'], file_get_contents($path)
                    ));
                    file_put_contents($path, str_replace(
                        'DB_PASSWORD=' . env('DB_PASSWORD'), 'DB_PASSWORD='. $config['mysql_password'], file_get_contents($path)
                    ));
                }
                Session::put('message_action', 'Fixed database connection');
                return redirect()->route('dashboard');
            } else {
                return 'Your installation went horribly wrong (missing .env file).  Start from the beginning.';
            }

        } else {
            return 'Your installation went horribly wrong (missing .env file).  Start from the beginning.';
        }
    }

    public function update_system(Request $request, $type='')
    {
        if (env('DOCKER') == null || env('DOCKER') == '0') {
            if (env('DOCKER') == null) {
                $env_arr['DOCKER'] = '0';
                $this->changeEnv($env_arr);
            }
            ini_set('memory_limit','196M');
            ini_set('max_execution_time', '300');
            $current_version = File::get(base_path() . "/.version");
            $composer = false;
            if (Session::has('user_locale')) {
                App::setLocale(Session::get('user_locale'));
            }
            if ($type !== '') {
                if ($type == 'composer_install' || $type == 'migrate' || $type == 'clear_cache') {
                    if ($type == 'composer_install') {
                        $install = new Process(["/usr/local/bin/composer install"]);
                        $install->setWorkingDirectory(base_path());
                        $install->setEnv(['COMPOSER_HOME' => '/usr/local/bin/composer']);
                        $install->setTimeout(null);
                        $install->run();
                        $return = nl2br($install->getOutput());
                    }
                    if ($type == 'migrate') {
                        $migrate = new Process(["php artisan migrate --force"]);
                        $migrate->setWorkingDirectory(base_path());
                        $migrate->setTimeout(null);
                        $migrate->run();
                        $return = nl2br($migrate->getOutput());
                    }
                    if ($type == 'clear_cache') {
                        $clear_cache = new Process(["php artisan cache:clear"]);
                        $clear_cache->setWorkingDirectory(base_path());
                        $clear_cache->setTimeout(null);
                        $clear_cache->run();
                        $return = nl2br($clear_cache->getOutput());
                        $clear_view = new Process(["php artisan view:clear"]);
                        $clear_view->setWorkingDirectory(base_path());
                        $clear_view->setTimeout(null);
                        $clear_view->run();
                        $return .= '<br>' . nl2br($clear_view->getOutput());
                    }
                } else {
                    $result1 = $this->github_single($type);
                    if (isset($result1['files'])) {
                        foreach ($result1['files'] as $row1) {
                            $filename = base_path() . "/" . $row1['filename'];
                            if ($row1['status'] == 'added' || $row1['status'] == 'modified' || $row1['status'] == 'renamed') {
                                $github_url = str_replace(' ', '%20', $row1['raw_url']);
                                if ($github_url !== '') {
                                    $file = file_get_contents($github_url);
                                    $parts = explode('/', $row1['filename']);
                                    array_pop($parts);
                                    $dir = implode('/', $parts);
                                    if (!is_dir(base_path() . "/" . $dir)) {
                                        if ($parts[0] == 'public') {
                                            mkdir(base_path() . "/" . $dir, 0777, true);
                                        } else {
                                            mkdir(base_path() . "/" . $dir, 0755, true);
                                        }
                                    }
                                    file_put_contents($filename, $file);
                                    if ($row1['filename'] == 'composer.json' || $row1['filename'] == 'composer.lock') {
                                        $composer = true;
                                    }
                                }
                            }
                            if ($row1['status'] == 'removed') {
                                if (file_exists($filename)) {
                                    unlink($filename);
                                }
                            }
                        }
                        define('STDIN',fopen("php://stdin","r"));
                        File::put(base_path() . "/.version", $type);
                        $return = trans('noshform.update_system1') . " " . $type . " " . trans('noshform.from1') . " " . $current_version;
                        $migrate = new Process(["php artisan migrate --force"]);
                        $migrate->setWorkingDirectory(base_path());
                        $migrate->setTimeout(null);
                        $migrate->run();
                        $return .= '<br>' . nl2br($migrate->getOutput());
                        if ($composer == true) {
                            $install = new Process(["/usr/local/bin/composer install"]);
                            $install->setWorkingDirectory(base_path());
                            $install->setEnv(['COMPOSER_HOME' => '/usr/local/bin/composer']);
                            $install->setTimeout(null);
                            $install->run();
                            $return .= '<br>' .nl2br($install->getOutput());
                        }
                    } else {
                        $return = trans('noshform.update_system2');
                    }
                }
            } else {
                $result = $this->github_all();
                if ($current_version != $result[0]['sha']) {
                    $arr = [];
                    foreach ($result as $row) {
                        $arr[] = $row['sha'];
                        if ($current_version == $row['sha']) {
                            break;
                        }
                    }
                    $arr2 = array_reverse($arr);
                    foreach ($arr2 as $sha) {
                        $result1 = $this->github_single($sha);
                        if (isset($result1['files'])) {
                            foreach ($result1['files'] as $row1) {
                                $filename = base_path() . '/' . $row1['filename'];
                                if ($row1['status'] == 'added' || $row1['status'] == 'modified' || $row1['status'] == 'renamed') {
                                    $github_url = str_replace(' ', '%20', $row1['raw_url']);
                                    if ($github_url !== '') {
                                        $file = file_get_contents($github_url);
                                        $parts = explode('/', $row1['filename']);
                                        array_pop($parts);
                                        $dir = implode('/', $parts);
                                        if (!is_dir(base_path() . '/' . $dir)) {
                                            if ($parts[0] == 'public') {
                                                mkdir(base_path() . '/' . $dir, 0777, true);
                                            } else {
                                                mkdir(base_path() . '/' . $dir, 0755, true);
                                            }
                                        }
                                        file_put_contents($filename, $file);
                                        if ($row1['filename'] == 'composer.json' || $row1['filename'] == 'composer.lock') {
                                            $composer = true;
                                        }
                                    }
                                }
                                if ($row1['status'] == 'removed') {
                                    if (file_exists($filename)) {
                                        unlink($filename);
                                    }
                                }
                            }
                        }
                    }
                    define('STDIN',fopen("php://stdin","r"));
                    File::put(base_path() . '/.version', $result[0]['sha']);
                    $return = trans('noshform.update_system1') . " " . $result[0]['sha'] . " " . trans('noshform.from1') . " " . $current_version;
                    $migrate = new Process(["php artisan migrate --force"]);
                    $migrate->setWorkingDirectory(base_path());
                    $migrate->setTimeout(null);
                    $migrate->run();
                    $return .= '<br>' .  nl2br($migrate->getOutput());
                    if ($composer == true) {
                        $install = new Process(["/usr/local/bin/composer install"]);
                        $install->setWorkingDirectory(base_path());
                        $install->setEnv(['COMPOSER_HOME' => '/usr/local/bin/composer']);
                        $install->setTimeout(null);
                        $install->run();
                        $return .= '<br>' . nl2br($install->getOutput());
                    }
                    $clear_cache = new Process(["php artisan cache:clear"]);
                    $clear_cache->setWorkingDirectory(base_path());
                    $clear_cache->setTimeout(null);
                    $clear_cache->run();
                    $return .= '<br>' . nl2br($clear_cache->getOutput());
                    $clear_view = new Process(["php artisan view:clear"]);
                    $clear_view->setWorkingDirectory(base_path());
                    $clear_view->setTimeout(null);
                    $clear_view->run();
                    $return .= '<br>' . nl2br($clear_view->getOutput());
                } else {
                    $return = trans('noshform.update_system3');
                }
            }
        } else {
            $return = "Update function disabled";
        }
        if (Auth::guest()) {
            return $return;
        } else {
            Session::put('message_action', $return);
            return redirect(Session::get('last_page'));
        }
    }

    public function test1(Request $request)
    {
        Storage::makeDirectory('directory');
        chmod(Storage::path('directory'), 0777);
        // Storage::deleteDirectory('directory');
        $pid = '1';
        return Storage::path($pid);
        $private_key = JWKFactory::createRSAKey(
        4096, // Size in bits of the key. We recommend at least 2048 bits.
        [
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $this->gen_uuid()
        ]);
        $public_key = $private_key->toPublic();
        return $public_key;
        $time = time();
        $algorithmManager = new AlgorithmManager([
            new RS256(),
        ]);
        $jwsBuilder = new JWSBuilder(
            $algorithmManager
        );
        $serializer = new CompactSerializer();
        // Our key.
        $jwk = new JWK([
            'kty' => 'RSA',
            'kid' => 'bilbo.baggins@hobbiton.example',
            'use' => 'sig',
            'n' => 'n4EPtAOCc9AlkeQHPzHStgAbgs7bTZLwUBZdR8_KuKPEHLd4rHVTeT-O-XV2jRojdNhxJWTDvNd7nqQ0VEiZQHz_AJmSCpMaJMRBSFKrKb2wqVwGU_NsYOYL-QtiWN2lbzcEe6XC0dApr5ydQLrHqkHHig3RBordaZ6Aj-oBHqFEHYpPe7Tpe-OfVfHd1E6cS6M1FZcD1NNLYD5lFHpPI9bTwJlsde3uhGqC0ZCuEHg8lhzwOHrtIQbS0FVbb9k3-tVTU4fg_3L_vniUFAKwuCLqKnS2BYwdq_mzSnbLY7h_qixoR7jig3__kRhuaxwUkRz5iaiQkqgc5gHdrNP5zw',
            'e' => 'AQAB',
            'd' => 'bWUC9B-EFRIo8kpGfh0ZuyGPvMNKvYWNtB_ikiH9k20eT-O1q_I78eiZkpXxXQ0UTEs2LsNRS-8uJbvQ-A1irkwMSMkK1J3XTGgdrhCku9gRldY7sNA_AKZGh-Q661_42rINLRCe8W-nZ34ui_qOfkLnK9QWDDqpaIsA-bMwWWSDFu2MUBYwkHTMEzLYGqOe04noqeq1hExBTHBOBdkMXiuFhUq1BU6l-DqEiWxqg82sXt2h-LMnT3046AOYJoRioz75tSUQfGCshWTBnP5uDjd18kKhyv07lhfSJdrPdM5Plyl21hsFf4L_mHCuoFau7gdsPfHPxxjVOcOpBrQzwQ',
            'p' => '3Slxg_DwTXJcb6095RoXygQCAZ5RnAvZlno1yhHtnUex_fp7AZ_9nRaO7HX_-SFfGQeutao2TDjDAWU4Vupk8rw9JR0AzZ0N2fvuIAmr_WCsmGpeNqQnev1T7IyEsnh8UMt-n5CafhkikzhEsrmndH6LxOrvRJlsPp6Zv8bUq0k',
            'q' => 'uKE2dh-cTf6ERF4k4e_jy78GfPYUIaUyoSSJuBzp3Cubk3OCqs6grT8bR_cu0Dm1MZwWmtdqDyI95HrUeq3MP15vMMON8lHTeZu2lmKvwqW7anV5UzhM1iZ7z4yMkuUwFWoBvyY898EXvRD-hdqRxHlSqAZ192zB3pVFJ0s7pFc',
            'dp' => 'B8PVvXkvJrj2L-GYQ7v3y9r6Kw5g9SahXBwsWUzp19TVlgI-YV85q1NIb1rxQtD-IsXXR3-TanevuRPRt5OBOdiMGQp8pbt26gljYfKU_E9xn-RULHz0-ed9E9gXLKD4VGngpz-PfQ_q29pk5xWHoJp009Qf1HvChixRX59ehik',
            'dq' => 'CLDmDGduhylc9o7r84rEUVn7pzQ6PF83Y-iBZx5NT-TpnOZKF1pErAMVeKzFEl41DlHHqqBLSM0W1sOFbwTxYWZDm6sI6og5iTbwQGIC3gnJKbi_7k_vJgGHwHxgPaX2PnvP-zyEkDERuf-ry4c_Z11Cq9AqC2yeL6kdKT1cYF8',
            'qi' => '3PiqvXQN0zwMeE-sBvZgi289XP9XCQF3VWqPzMKnIgQp7_Tugo6-NZBKCQsMf3HaEGBjTVJs_jcK8-TRXvaKe-7ZMaQj8VfBdYkssbu0NKDDhjJ-GtiseaDVWt7dcH0cfwxgFUHpQh7FoCrjFJ6h6ZEpMF6xmujs4qMpPz8aaI4',
        ]);
        // The payload we want to sign
        $payload = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'My service',
            'aud' => 'Your application',
            'resources' => ['dolphin-metadata'],
            'interact' => [
                'redirect' => true,
                'callback' => [
                    'method' => 'redirect',
                    'uri' => 'https://client.foo',
                    'nonce' => 'VJLO6A4CAYLBXHTR0KRO'
                ]
            ],
            'client' => [
                'proof' => 'jwsd',
                'key' => [
                    'jwk' => [
                        'kty' => 'RSA',
                        'e' => 'AQAB',
                        'kid' => 'xyz-1',
                        'alg' => 'RS256',
                        'n' => 'kOB5rR4Jv0GMeLaY6_It_r3ORwdf8ci_JtffXyaSx8xYJCNaOKNJn_Oz0YhdHbXTeWO5AoyspDWJbN5w_7bdWDxgpD-y6jnD1u9YhBOCWObNPFvpkTM8LC7SdXGRKx2k8Me2r_GssYlyRpqvpBlY5-ejCywKRBfctRcnhTTGNztbbDBUyDSWmFMVCHe5mXT4cL0BwrZC6S-uu-LAx06aKwQOPwYOGOslK8WPm1yGdkaA1uF_FpS6LS63WYPHi_Ap2B7_8Wbw4ttzbMS_doJvuDagW8A1Ip3fXFAHtRAcKw7rdI4_Xln66hJxFekpdfWdiPQddQ6Y1cK2U3obvUg7w'
                    ]
                ]
            ],
            'display' => [
                'name' => 'My Client Display Name',
                'uri' => 'https://example.net/client'
            ]
        ];
        $jws = $jwsBuilder
            ->create()                               // We want to create a new JWS
            ->withPayload(json_encode($payload), true)            // /!\ Here is the change! We set the payload and we indicate it is detached
            ->addSignature($jwk, ['alg' => 'RS256']) // We add a signature with a simple protected header
            ->build();
        $token = $serializer->serialize($jws, 0);
        $statusCode = 200;
        return response()->json($payload, $statusCode)->header('Content-Type', 'application/json')->header('Detached-JWS', $token);
    }
}
