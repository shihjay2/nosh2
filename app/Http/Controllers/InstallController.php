<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use App\Libraries\OpenIDConnectClient;
use Artisan;
use Auth;
use Config;
use Crypt;
use Date;
use DB;
use File;
use Form;
use Hash;
use HTML;
use Htmldom;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Imagick;
use Laravel\LegacyEncrypter\McryptEncrypter;
use QrCode;
use Response;
use Schema;
use Session;
use URL;
use DateTime;
use DateTimeZone;
use SoapBox\Formatter\Formatter;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Google_Client;

class InstallController extends Controller {

    public function backup()
    {
        $row2 = DB::table('practiceinfo')->first();
        $dir = $row2->documents_dir;
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

    public function google_start(Request $request)
    {
        $query = DB::table('practiceinfo')->first();
        if ($request->isMethod('post')) {
            $file = $request->file('file_input');
            $json = file_get_contents($file->getRealPath());
            if (json_decode($json) == NULL) {
                Session::put('message_action', 'Error - This is not a json file.  Try again');
                return redirect()->route('google_start');
            }
            $config_file = base_path() . '/.google';
            if (file_exists($config_file)) {
                unlink($config_file);
            }
            $directory = base_path();
            $new_name = ".google";
            $file->move($directory, $new_name);
            Session::put('message_action', 'Google JSON file uploaded successfully');
            if (! $query) {
                if (file_exists(base_path() . '/.patientcentric')) {
                    return redirect()->route('install', ['patient']);
                } else {
                    return redirect()->route('install', ['practice']);
                }
            } else {
                return redirect()->route('dashboard');
            }
        } else {
            $data['panel_header'] = 'Upload Google JSON File for GMail Integration';
            $data['document_upload'] = route('google_start');
            $type_arr = ['json'];
            $data['document_type'] = json_encode($type_arr);
            $text = "<p>You're' here because you have not installed a Google OAuth2 Client ID file.  You'll need to set this up first before configuring NOSH Charting System.'</p>";
            if ($query) {
                $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
                $dropdown_array['default_button_text_url'] = route('dashboard');
                $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                $text = "<p>A Google OAuth2 Client ID file is already installed.  Uploading a new file will overwrite the existing file!</p>";
            }
            $data['content'] = '<div class="alert alert-success">' . $text . '<ul><li>Instructions are on ' . HTML::link('https://github.com/shihjay2/nosh-in-a-box/wiki/How-to-get-Gmail-to-work-with-NOSH', 'this Wiki page.', ['target'=>'_blank']) . ' Please refer to it carefully.</li><li>Once you have you JSON file, upload it here.</li></ul></div>';
            $data['assets_js'] = $this->assets_js('document_upload');
            $data['assets_css'] = $this->assets_css('document_upload');
            return view('document_upload', $data);
        }
    }

    public function install(Request $request, $type)
    {
        $query = DB::table('practiceinfo')->first();
        if ($query) {
            return redirect()->route('dashboard');
        }
        // Tag version number for baseline prior to updating system in the future
        if (file_exists(base_path() . '/.version')) {
            // First time after install
            $result = $this->github_all();
            File::put(base_path() . '/.version', $result[0]['sha']);
        }
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'password' => 'min:4',
                'confirm_password' => 'min:4|same:password',
            ]);
            $smtp_user = $request->input('smtp_user');
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
            $city = $request->input('city');
            $state = $request->input('state');
            $zip = $request->input('zip');
            $documents_dir = $request->input('documents_dir');
            // Clean up documents directory string
            $check_string = substr($documents_dir, -1);
            if ($check_string != '/') {
                $documents_dir .= '/';
            }
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
                'documents_dir' => $documents_dir,
                'fax_type' => '',
                'smtp_user' => $smtp_user,
                'vivacare' => '',
                'version' => '2.0.0',
                'active' => 'Y',
                'patient_centric' => $patient_centric
            ];
            DB::table('practiceinfo')->insert($data2);
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
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip
                ];
                $pid = DB::table('demographics')->insertGetId($patient_data);
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
                $directory = $documents_dir . $pid;
                mkdir($directory, 0775);
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
            Auth::attempt(['username' => $username, 'password' => $request->input('password'), 'active' => '1', 'practice_id' => '1']);
            Session::put('user_id', $user_id);
            Session::put('group_id', '1');
            Session::put('practice_id', '1');
            Session::put('version', $data2['version']);
            Session::put('practice_active', $data2['active']);
            Session::put('displayname', $displayname);
            Session::put('documents_dir', $data2['documents_dir']);
            Session::put('patient_centric', $data2['patient_centric']);
            return redirect()->route('dashboard');
        } else {
            $data['panel_header'] = 'NOSH ChartingSystem Installation';
            $items[] = [
                'name' => 'username',
                'label' => 'Administrator Username',
                'type' => 'text',
                'required' => true,
                'default_value' => 'admin'
            ];
            $items[] = [
                'name' => 'password',
                'label' => 'Administrator Password',
                'type' => 'password',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'confirm_password',
                'label' => 'Confirm Password',
                'type' => 'password',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'default_value' => null
            ];
            if ($type == 'patient') {
                $items[] = [
                    'name' => 'pt_username',
                    'label' => 'Portal Username',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'lastname',
                    'label' => 'Last Name',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'firstname',
                    'label' => 'First Name',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'DOB',
                    'label' => 'Date of Birth',
                    'type' => 'date',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'gender',
                    'label' => 'Gender',
                    'type' => 'select',
                    'select_items' => $this->array_gender(),
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'address',
                    'label' => 'Street Address',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
            } else {
                $items[] = [
                    'name' => 'practice_name',
                    'label' => 'Practice Name',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'street_address1',
                    'label' => 'Street Address',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'street_address2',
                    'label' => 'Street Address Line 2',
                    'type' => 'text',
                    'default_value' => null
                ];
            }
            $items[] = [
                'name' => 'city',
                'label' => 'City',
                'type' => 'text',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'state',
                'label' => 'State',
                'type' => 'select',
                'select_items' => $this->array_states(),
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'zip',
                'label' => 'Zip',
                'type' => 'text',
                'required' => true,
                'default_value' => null
            ];
            if ($type !== 'patient') {
                $items[] = [
                    'name' => 'phone',
                    'label' => 'Phone',
                    'type' => 'text',
                    'phone' => true,
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'fax',
                    'label' => 'Fax',
                    'type' => 'text',
                    'phone' => true,
                    'required' => true,
                    'default_value' => null
                ];
            }
            $documents_dir = '/noshdocuments/';
            if (file_exists(base_path() . '/.noshdir')) {
                $documents_dir = trim(File::get(base_path() . '/.noshdir'));
            }
            $items[] = [
                'name' => 'documents_dir',
                'label' => 'Documents Directory',
                'type' => 'text',
                'required' => true,
                'default_value' => $documents_dir
            ];
            $items[] = [
                'name' => 'smtp_user',
                'label' => 'Gmail Username for Sending Email',
                'type' => 'text',
                'required' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'install_form',
                'action' => route('install', [$type]),
                'items' => $items,
                'save_button_label' => 'Install'
            ];
            $data['content'] = '<p>Please fill out the entries to complete the installation of NOSH ChartingSystem.</p><p>You will need to establish a Google Gmail account to be able to send e-mail from the system for patient appointment reminders, non-Protected Health Information messages, and faxes.</p>';
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
                Session::put('message_action', 'Fixed database connection');
                return redirect()->route('dashboard');
            } else {
                Session::put('message_action', 'Error - Incorrect username/password for your MySQL database.  Try again');
                return redirect()->route('install_fix');
            }
        } else {
            $items[] = [
                'name' => 'db_username',
                'label' => 'Database Username',
                'type' => 'text',
                'required' => true,
                'default_value' => env('DB_USERNAME')
            ];
            $items[] = [
                'name' => 'db_password',
                'label' => 'Database Password',
                'type' => 'password',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'confirm_db_password',
                'label' => 'Confirm Password',
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
            $data['panel_header'] = 'Database Connection Fix';
            $data['content'] = $this->form_build($form_array);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function prescription_pharmacy_view(Request $request, $id, $ret='')
    {
        $data['hash'] = '';
        $data['ajax'] = route('dashboard');
        $data['ajax1'] = route('dashboard');
        $data['uport_need'] = '';
        $data['uport_id'] = '';
        $data['panel_header'] = 'Prescription Validation';
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
                        $outcome = '';
                        $items[] = [
                            'name' => 'rx_json',
                            'label' => 'Prescription in FHIR JSON',
                            'type' => 'textarea',
                            // 'readonly' => true,
                            'default_value' => $query->json
                        ];
                        $items[] = [
                            'name' => 'hash',
                            'label' => 'Prescription Hash',
                            'type' => 'text',
                            'readonly' => true,
                            'default_value' => $hash
                        ];
                        $items[] = [
                            'name' => 'tx_hash',
                            'label' => 'Blockchain Timestamp Receipt',
                            'type' => 'text',
                            'readonly' => true,
                            'default_value' => $query->transaction
                        ];
                        if ($request->isMethod('post')) {
                            $data['uport_need'] = 'validate';
                            Session::put('rx_json', $request->input('rx_json'));
                            Session::put('hash', hash('sha256', $request->input('rx_json')));
                        }
                        if ($ret !== '') {
                            $items[0]['default_value'] = Session::get('rx_json');
                            $items[1]['default_value'] = Session::get('hash');
                            Session::forget('rx_json');
                            Session::forget('hash');
                            $bytes = 4 * 64;
                            $rx_hash = substr(substr($ret, 10), $bytes);
                            $items[] = [
                                'name' => 'rx_hash',
                                'label' => 'Prescription Hash from Blockchain',
                                'type' => 'text',
                                'readonly' => true,
                                'default_value' => $rx_hash
                            ];
                            $outcome = '<div class="alert alert-danger"><strong>Presciption Invalid</strong> - It may have been tampered with.</div>';
                            if ($rx_hash == $items[1]['default_value']) {
                                $outcome = '<div class="alert alert-success"><strong>Prescription is Signed and Valid</strong></div>';
                            }
                        }
                        $form_array = [
                            'form_id' => 'prescription_form',
                            'action' => route('prescription_pharmacy_view', [$id]),
                            'items' => $items,
                            'save_button_label' => 'Validate',
                            'remove_cancel' => true
                        ];
                        $data['content'] .= $this->form_build($form_array);
                    } else {
                        $outcome = '<div class="alert alert-danger"><strong>Presciption Invalid</strong> - Prescription has not been signed electronically by uPort.</div>';
                    }
                } else {
                    $outcome = '<div class="alert alert-danger"><strong>Presciption Filled</strong> - Prescription has been inactivated.</div>';
                }
            } else {
                $outcome = '<div class="alert alert-danger"><strong>Presciption Invalid</strong> - This medication was never prescribed.</div>';
            }
        } else {
            $outcome = '<div class="alert alert-danger"><strong>Presciption Invalid</strong> - No prescription exists.</div>';
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

    public function uma_patient_centric(Request $request)
    {
        $query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $open_id_url = str_replace('/nosh', '', URL::to('/'));
        if ($query->patient_centric == 'y') {
            if ($query->uma_client_id == '') {
                // Register as resource server
                $patient = DB::table('demographics')->where('pid', '=', '1')->first();
                $client_name = 'Patient NOSH for ' .  $patient->firstname . ' ' . $patient->lastname . ', DOB: ' . date('Y-m-d', strtotime($patient->DOB));
                $url = route('uma_auth');
                $oidc = new OpenIDConnectClient($open_id_url);
                $oidc->setClientName($client_name);
                $oidc->setRedirectURL($url);
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
                $oidc->register(true,true);
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
                    $url = route('uma_patient_centric');
                    $oidc = new OpenIDConnectClient($open_id_url, $client_id, $client_secret);
                    $oidc->setRedirectURL($url);
                    $oidc->addScope('openid');
                    $oidc->addScope('email');
                    $oidc->addScope('profile');
                    $oidc->addScope('offline_access');
                    $oidc->addScope('uma_protection');
                    $oidc->authenticate(true,'user1');
                    $user_data['uid']  = $oidc->requestUserInfo('sub');
                    DB::table('users')->where('id', '=', '2')->update($user_data);
                    $access_token = $oidc->getAccessToken();
                    if ($oidc->getRefreshToken() != '') {
                        $refresh_data['uma_refresh_token'] = $oidc->getRefreshToken();
                        DB::table('practiceinfo')->where('practice_id', '=', '1')->update($refresh_data);
                        $this->audit('Update');
                    }
                    // Register resource sets
                    $uma = DB::table('uma')->first();
                    if (!$uma) {
                        $resource_set_array[] = [
                            'name' => 'Patient',
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
                            'name' => 'My Conditions',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-condition.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Condition',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Medication List',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-pharmacy.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/MedicationStatement',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Allergy List',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-allergy.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/AllergyIntolerance',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Immunization List',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-immunizations.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Immunization',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'My Encounters',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-medical-records.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Encounter',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Family History',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-family-practice.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/FamilyHistory',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'My Documents',
                            'icon' => 'https://cloud.noshchartingsystem.com/i-file.png',
                            'scopes' => [
                                URL::to('/') . '/fhir/Binary',
                                'view',
                                'edit'
                            ]
                        ];
                        $resource_set_array[] = [
                            'name' => 'Observations',
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
                }
            }
        }
        return redirect()->route('dashboard');
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

    public function update_system(Request $request)
    {
        $current_version = File::get(base_path() . '/.version');
        $result = $this->github_all();
        $composer = false;
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
                                if ($filename == 'composer.json') {
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
            Artisan::call('migrate', array('--force' => true));
            File::put(base_path() . '/.version', $result[0]['sha']);
            if ($composer == true) {
                putenv('COMPOSER_HOME=/usr/local/bin/composer');
                $install = new Process("/usr/local/bin/composer install");
                $install->setWorkingDirectory(base_path());
                $install->run();
            }
            return "System Updated with version " . $result[0]['sha'] . " from " . $current_version;
        } else {
            return "No update needed";
        }
    }

    public function test1(Request $request)
    {
    }
}
