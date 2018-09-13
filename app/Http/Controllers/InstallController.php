<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
// use App\Libraries\OpenIDConnectUMAClient;
use Artisan;
use Auth;
use Config;
use Crypt;
use Date;
use DateTime;
use DateTimeZone;
use DB;
use File;
use Form;
use Google_Client;
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
use Shihjay2\OpenIDConnectUMAClient;
use SoapBox\Formatter\Formatter;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use URL;

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

    public function install(Request $request, $type)
    {
        $query = DB::table('practiceinfo')->first();
        if ($query) {
            return redirect()->route('dashboard');
        }
        // Tag version number for baseline prior to updating system in the future
        if (!File::exists(base_path() . "/.version")) {
            // First time after install
            $result = $this->github_all();
            File::put(base_path() . '/.version', $result[0]['sha']);
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
                $pnosh_url = $request->root();
                $pnosh_url = str_replace(array('http://','https://'), '', $pnosh_url);
                $root_url = explode('/', $pnosh_url);
                $root_url1 = explode('.', $root_url[0]);
                $final_root_url = $root_url1[1] . '.' . $root_url1[2];
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
                Session::put('documents_dir', $data2['documents_dir']);
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
                'default_value' => $email
            ];
            if ($type == 'patient') {
                $items[] = [
                    'name' => 'pt_username',
                    'label' => 'Portal Username',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $pt_username
                ];
                $items[] = [
                    'name' => 'lastname',
                    'label' => 'Last Name',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $lastname
                ];
                $items[] = [
                    'name' => 'firstname',
                    'label' => 'First Name',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $firstname
                ];
                $items[] = [
                    'name' => 'DOB',
                    'label' => 'Date of Birth',
                    'type' => 'date',
                    'required' => true,
                    'default_value' => $dob
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

    public function pnosh_install(Request $request)
    {
        $query = DB::table('practiceinfo')->first();
        if ($query) {
            return 'Error - Already installed';
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
        // $this->validate($request, [
        //     'password' => 'min:4',
        //     'confirm_password' => 'min:4|same:password',
        // ]);
        $username = $request->input('username');
        $password = substr_replace(Hash::make($request->input('password')),"$2a",0,3);
        $email = $request->input('email');
        $practice_name = "NOSH for Patient: " . $request->input('firstname') . ' ' . $request->input('lastname');
        $street_address1 = $request->input('address');
        $street_address2 = '';
        $phone = '';
        $fax = '';
        $patient_centric = 'y';
        $city = $request->input('city');
        $state = $request->input('state');
        $zip = $request->input('zip');
        $documents_dir = '/noshdocuments/';
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
            'vivacare' => '',
            'version' => '2.0.0',
            'active' => 'Y',
            'patient_centric' => $patient_centric
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
                            $bytes = 13 * 64;
                            $rx_hash = substr(substr(substr($ret, 18), $bytes), 0, -56);
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
        $message_action = 'Check to see in your registered e-mail account if you have recieved it.  If not, please come back to the E-mail Service page and try again.';
        try {
            $this->send_mail('emails.blank', $data_message, 'Test E-mail', $practice->email, Session::get('practice_id'));
        } catch(\Exception $e){
            $message_action = 'Error - There is an error in your configuration.  Please try again.';
            Session::put('message_action', $message_action);
            return redirect()->route('setup_mail');
        }
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
                    // $user = DB::table('users')->where('id', '=', '2')->first();
                    // Auth::loginUsingId($user->id);
                    // $practice1 = DB::table('practiceinfo')->where('practice_id', '=', $user->practice_id)->first();
                    // Session::put('user_id', $user->id);
                    // Session::put('group_id', $user->group_id);
                    // Session::put('practice_id', $user->practice_id);
                    // Session::put('version', $practice1->version);
                    // Session::put('practice_active', $practice1->active);
                    // Session::put('displayname', $user->displayname);
                    // Session::put('documents_dir', $practice1->documents_dir);
                    // Session::put('rcopia', $practice1->rcopia_extension);
                    // Session::put('mtm_extension', $practice1->mtm_extension);
                    // Session::put('patient_centric', $practice1->patient_centric);
                    // Session::put('uma_auth_access_token', $access_token);
                    // Session::put('uport_id', $uport_id);
                    // $url_hieofoneas = str_replace('/nosh', '/resources/' . $practice1->uma_client_id, URL::to('/'));
                    // Session::put('url_hieofoneas', $url_hieofoneas);
                    // setcookie("login_attempts", 0, time()+900, '/');
                    return redirect($open_id_url);
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
                Session::put('message_action', 'Error - The URL you entered is not valid.');
                return redirect()->back();
            }
        } else {
            $data['panel_header'] = 'HIE of One Authorization Server Registration';
            $items[] = [
                'name' => 'uri',
                'label' => 'URL of your HIE of One Authorization Server',
                'type' => 'text',
                'required' => true,
                'value' => 'https://',
                'default_value' => 'https://'
            ];
            $form_array = [
                'form_id' => 'uma_form',
                'action' => route('uma_patient_centric_designate'),
                'items' => $items,
                'save_button_label' => 'Submit'
            ];
            $data['content'] = '<p>An Authorization Server has not been found in the same domain as your NOSH ChartingSystem installation.</p><p>You will need to designate the URL of your Authorization Server to proceed with using NOSH ChartingSystem</p>';
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
        if ($type !== '') {
            if ($type == 'composer_install') {
                $install = new Process("/usr/local/bin/composer install");
                $install->setWorkingDirectory(base_path());
                $install->setEnv(['COMPOSER_HOME' => '/usr/local/bin/composer']);
                $install->setTimeout(null);
                $install->run();
                $return = nl2br($install->getOutput());
            }
            if ($type == 'migrate') {
                $migrate = new Process("php artisan migrate --force");
                $migrate->setWorkingDirectory(base_path());
                $migrate->setTimeout(null);
                $migrate->run();
                $return = nl2br($migrate->getOutput());
            }
        } else {
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
                $return = "System Updated with version " . $result[0]['sha'] . " from " . $current_version;
                $migrate = new Process("php artisan migrate --force");
                $migrate->setWorkingDirectory(base_path());
                $migrate->setTimeout(null);
                $migrate->run();
                $return .= '<br>' .  nl2br($migrate->getOutput());
                if ($composer == true) {
                    $install = new Process("/usr/local/bin/composer install");
                    $install->setWorkingDirectory(base_path());
                    $install->setEnv(['COMPOSER_HOME' => '/usr/local/bin/composer']);
                    $install->setTimeout(null);
                    $install->run();
                    $return .= '<br>' . nl2br($install->getOutput());
                }
            } else {
                $return = "No update needed";
            }
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
    }
}
