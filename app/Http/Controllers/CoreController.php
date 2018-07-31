<?php

namespace App\Http\Controllers;

use App;
use App\Events\ProcessEvent;
use App\Http\Requests;
use Date;
use DB;
use Event;
use File;
use Form;
use Hash;
use HTML;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Imagick;
use Minify;
use Shihjay2\OpenIDConnectUMAClient;
use shihjay2\tcpdi_merger\MyTCPDI;
use shihjay2\tcpdi_merger\Merger;
use QrCode;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Schema;
use Session;
use SoapBox\Formatter\Formatter;
use URL;
use ZipArchive;

class CoreController extends Controller
{
    public function __construct()
    {
        $this->middleware('checkinstall');
        $this->middleware('auth');
        $this->middleware('postauth');
    }

    public function add_patient(Request $request)
    {
        if ($request->isMethod('post')) {
            $practice_id = Session::get('practice_id');
            $data = [
                'lastname' => $request->input('lastname'),
                'firstname' => $request->input('firstname'),
                'DOB' => date('Y-m-d', strtotime($request->input('DOB'))),
                'sex' => $request->input('sex'),
                'active' => '1',
                'sexuallyactive' => 'no',
                'tobacco' => 'no',
                'pregnant' => 'no'
            ];
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
            $result = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
            $directory = $result->documents_dir . $pid;
            mkdir($directory, 0775);
            Session::put('message_action', $data['lastname'] . ' ' . $data['firstname'] . ' added');
            $this->setpatient($pid);
            return redirect()->route('patient');
        } else {
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
                'name' => 'sex',
                'label' => 'Gender',
                'type' => 'select',
                'required' => true,
                'select_items' => $this->array_gender(),
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'add_patient_form',
                'action' => route('add_patient'),
                'items' => $items,
                'save_button_label' => 'Save'
            ];
            $data['panel_header'] = 'Add New Patient';
            $data['content'] = $this->form_build($form_array);
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function addressbook(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $return = '';
        $type_arr = [
            'all' => ['All', 'fa-globe']
        ];
        $type_query = DB::table('addressbook')->select('specialty')->distinct()->orderBy('specialty', 'asc')->get();
        if ($type_query->count()) {
            foreach ($type_query as $type_row) {
                $type_arr[$type_row->specialty] = [$type_row->specialty, 'fa-sitemap'];
            }
        }
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        $items = [];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('addressbook', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $list_array = [];
        $query = DB::table('addressbook');
        if ($type !== 'all') {
            $query->where('specialty', '=', $type);
        }
        $query->orderBy('displayname', 'asc');
        $result = $query->get();
        $columns = Schema::getColumnListing('addressbook');
        $row_index = $columns[0];
        if ($result->count()) {
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . $row->displayname . '</b>';
                if ($type == 'all') {
                    $arr['label'] .= ' - ' . $row->specialty;
                }
                $arr['edit'] = route('core_form', ['addressbook', $row_index, $row->$row_index]);
                $arr['delete'] = route('core_action', ['table' => 'addressbook', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                $list_array[] = $arr;
            }
        }
        if (! empty($list_array)) {
            $return .= $this->result_build($list_array, $type . '_list');
        } else {
            $return .= ' None.';
        }
        $dropdown_array1 = [
            'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => 'New Entry',
            'icon' => 'fa-plus',
            'url' => route('core_form', ['addressbook', $row_index, '0'])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        $data['panel_header'] = 'Address Book';
        Session::put('last_page', $request->fullUrl());
        if (Session::has('download_now')) {
            $data['download_now'] = route('download_now');
        }
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function api_practice(Request $request)
	{
        $practice_id = Session::get('practice_id');
        if ($request->isMethod('post')) {
            if ($request->input('submit') !== 'no_sync') {
        		$url_check = false;
        		$url_reason = '';
        		$api_key = uniqid('nosh', true);
        		$register_code = uniqid();
        		$patient_portal = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        		$patient = DB::table('demographics')->first();
        		$register_data = json_decode(json_encode($patient), true);
        		$register_data['api_key'] = $api_key;
        		$register_data['url'] = URL::to('/');
        		$pos = stripos($request->input('practice_url'), 'cloud.noshchartingsystem.com');
        		if ($pos !== false) {
        			$url_explode = explode('/', $request->input('practice_url'));
        			$url = 'https://cloud.noshchartingsystem.com/nosh/api_check/' . $url_explode[5];
        			$url1 = 'https://cloud.noshchartingsystem.com/nosh/api_register';
        			$register_data['practicehandle'] = $url_explode[5];
        		} else {
        			$url = $request->input('practice_url') . '/api_check/0';
        			$url1 = $request->input('practice_url') . '/api_register';
        			$register_data['practicehandle'] = '0';
        		}
        		$ch = curl_init();
        		curl_setopt($ch,CURLOPT_URL, $url);
        		curl_setopt($ch,CURLOPT_FAILONERROR,1);
        		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        		curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
        		$result = curl_exec($ch);
        		if(curl_errno($ch)){
        			$url_reason = 'Error: ' . curl_error($ch);
        		} else {
        			if ($result !== 'No') {
        				$url_check = true;
        			} else {
        				$url_reason = 'mdNOSH is not set up to accept API connections.  Please update the mdNOSH installation.';
        			}
        		}
        		if ($url_check == false) {
        			$status = 'n';
        			//$data_message['temp_url'] = rtrim($patient_portal->patient_portal, '/') . '/practiceregister/' . $register_code;
        			$message = 'Error: Problem with contacting the URL problem for NOSH integration.  Please check if you have NOSH and that the URL provided is correct.';
        			if ($url_reason != '') {
        				$message .= '  ' . $url_reason;
        			}
        		} else {
        			$data = [
        				'practice_api_key' => $api_key,
        				'active' => 'Y',
        				'practice_registration_key' => $register_code,
        				'practice_registration_timeout' => time() + 86400,
        				'practice_api_url' => $request->input('practice_url')
        			];
        			DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data);
                    $this->audit('Update');
        			$data2['api_key'] = $api_key;
        			DB::table('demographics_relate')->where('practice_id', '=', $practice_id)->where('pid', '=', $patient->pid)->update($data2);
        			$this->audit('Update');
        			//$data_message['temp_url'] = rtrim($patient_portal->patient_portal, '/') . '/practiceregisternosh/' . $register_code;
        			// Send API key to mdNOSH;
        			$result = $this->api_data_send($url1, $register_data, '', '');
        			$status = 'y';
        			$message = 'Practice added with mdNOSH integration.';
        			$message .= '  Response from server: ' . $result['status'];
        		}
    		    //$this->send_mail('emails.apiregister', $data_message, 'NOSH ChartingSystem API Registration', $request->input('email'), '1');
            } else {
                $data3['practice_api_url'] = 'nosync';
                DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data);
                $this->audit('Update');
                $status = 'y';
                $message = 'No mdNOSH integration set.';
            }
            Session::put('message_action', $message);
            if ($status == 'n') {
                return route('api_practice');
            } else {
                return route('pnosh_provider_redirect');
            }
        } else {
            $data['panel_header'] = 'Integrate with your Practice NOSH (mdNOSH)';
            $items[] = [
                'name' => 'practice_url',
                'label' => 'URL of Practice NOSH (mdNOSH)',
                'type' => 'text',
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'api_practice',
                'action' => route('api_practice'),
                'items' => $items,
                'save_button_label' => 'Save',
                'add_save_button' => [
                    'no_sync' => 'No Integration'
                ]
            ];
            $data['content'] = '<div class="alert alert-success"><h5>Integration Tips</h5><ul>';
            $data['content'] .= '<li>If this patient does not exist in your Practice NOSH, the patient will be added.</li>';
            $data['content'] .= '<li>If the patient already exsits, this will link the this Patient NOSH to your Practice NOSH</li>';
            $data['content'] .= '</ul></div>';
            $data['content'] .= $this->form_build($form_array);
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
	}

    public function audit_logs(Request $request)
    {
        $query = DB::table('audit')->where('practice_id', '=', Session::get('practice_id'))->orderBy('timestamp', 'desc')->paginate(20);
        if ($query->count()) {
            $list_array = [];
            foreach ($query as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->timestamp)) . '</b> - ' . $row->displayname . '<br>' . $row->query;
                $list_array[] = $arr;
            }
            $return = $this->result_build($list_array, 'audit_list');
            $return .= $query->links();
        } else {
            $return = 'None.';
        }
        $data['saas_admin'] = 'y';
        $data['panel_header'] = 'Audit Logs';
        $data['content'] = $return;
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function billing_list(Request $request, $type, $pid)
    {
        if (Session::has('pid')) {
            if (Session::get('pid') !== $pid) {
                $this->setpatient($pid);
            }
        } else {
            $this->setpatient($pid);
        }
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $result = [];
        if ($type == 'encounters') {
            $encounters = DB::table('encounters')->where('pid', '=', $pid)->where('addendum', '=', 'n')->where('practice_id', '=', Session::get('practice_id'))->orderBy('encounter_DOS', 'desc')->get();
            if ($encounters->count()) {
                foreach ($encounters as $encounter) {
                    $action = '<a href="' . route('billing_payment_history', [$encounter->eid, 'eid']) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Payment History"><i class="fa fa-history fa-lg"></i></a>';
                    $action .= '<a href="' . route('billing_make_payment', [$encounter->eid, 'eid']) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Make Payment"><i class="fa fa-usd fa-lg"></i></a>';
                    $billing = DB::table('billing')->where('eid', '=', $encounter->eid)->first();
                    if ($billing) {
                        $insurance_id_1 = $billing->insurance_id_1;
                        $insurance_id_2 = $billing->insurance_id_2;
                        $action .= '<a href="' . route('print_invoice1', [$encounter->eid, $billing->insurance_id_1, $billing->insurance_id_2]) . '" class="btn fa-btn nosh-no-load" role="button" data-toggle="tooltip" title="Print Invoice"><i class="fa fa-print fa-lg"></i></a>';
                    }
                    $arr = [
                        'id' => $encounter->eid,
                        'date' => date('Y-m-d', $this->human_to_unix($encounter->encounter_DOS)),
                        'reason' => $encounter->encounter_cc,
                        'balance' => 0,
                        'charges' => 0,
                        'action' => $action,
                        'click' => route('encounter_billing', [$encounter->eid])
                    ];
                    $query = DB::table('billing_core')->where('eid', '=', $encounter->eid)->get();
                    if ($query->count()) {
                        $charge = 0;
                        $payment = 0;
                        foreach ($query as $row) {
                            $charge += $row->cpt_charge * $row->unit;
                            $payment += $row->payment;
                        }
                        $arr['balance'] = $charge - $payment;
                        $arr['charges'] = $charge;
                    }
                    $result[] = $arr;
                }
            }
            $dropdown_array = [
                'items_button_text' => 'Encounters'
            ];
            $items[] = [
                'type' => 'item',
                'label' => 'Miscellaneous Bills',
                'icon' => 'fa-shopping-basket',
                'url' => route('billing_list', ['misc', $pid])
            ];
            $items[] = [
                'type' => 'item',
                'label' => 'CMS Bluebutton Data',
                'icon' => 'fa-money',
                'url' => route('cms_bluebutton')
            ];
        } else {
            $others = DB::table('billing_core')->where('pid', '=', $pid)->where('eid', '=', '0')->where('payment', '=', '0')->where('practice_id', '=', Session::get('practice_id'))->orderBy('dos_f', 'desc')->get();
            if ($others->count()) {
                foreach ($others as $other) {
                    $action = '<a href="' . route('billing_payment_history', [$other->other_billing_id, 'other_billing_id']) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Payment History"><i class="fa fa-history fa-lg"></i></a>';
                    $action .= '<a href="' . route('billing_make_payment', [$other->other_billing_id, 'other_billing_id']) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Make Payment"><i class="fa fa-usd fa-lg"></i></a>';
                    $action .= '<a href="' . route('print_invoice2', [$other->other_billing_id, $pid]) . '" class="btn fa-btn nosh-no-load" role="button" data-toggle="tooltip" title="Print Invoice"><i class="fa fa-print fa-lg"></i></a>';
                    $action .= '<a href="' . route('billing_delete_invoice', [$other->billing_core_id]) . '" class="btn btn-danger fa-btn nosh-delete" role="button" data-toggle="tooltip" title="Delete Invoice"><i class="fa fa-trash fa-lg"></i></a>';
                    $arr = [
                        'id' => $other->other_billing_id,
                        'date' => date('Y-m-d', $this->human_to_unix($other->dos_f)),
                        'reason' => $other->reason,
                        'balance' => 0,
                        'unit' => $other->unit,
                        'charges' => $other->cpt_charge,
                        'action' => $action,
                        'click' => route('billing_details', [$other->billing_core_id])
                    ];
                    $query = DB::table('billing_core')->where('other_billing_id', '=', $other->other_billing_id)->get();
                    if ($query->count()) {
                        $charge = $other->cpt_charge * $other->unit;
                        $payment = 0;
                        foreach ($query as $row) {
                            $payment += $row->payment;
                        }
                        $arr['balance'] = $charge - $payment;
                    }
                    $result[] = $arr;
                }
            }
            $dropdown_array = [
                'items_button_text' => 'Miscellaneous Bills'
            ];
            $items[] = [
                'type' => 'item',
                'label' => 'Encounters',
                'icon' => 'fa-stethoscope',
                'url' => route('billing_list', ['encounters', $pid])
            ];
            $items[] = [
                'type' => 'item',
                'label' => 'CMS Bluebutton Data',
                'icon' => 'fa-money',
                'url' => route('cms_bluebutton')
            ];
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $return = '';
        $edit = $this->access_level('1');
        if (! empty($result)) {
            $head_arr = [
                'Date' => 'date',
                'Reason' => 'reason',
                'Charge' => 'charges',
                'Balance' => 'balance',
                'Action' => 'action'
            ];
            $return .= '<div class="alert alert-success">';
            $return .= $this->total_balance($pid);
            $return .= '<h5>Click on a row to get details of the invoice.</h5></div>';
            $return .= '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
            foreach ($head_arr as $head_row_k => $head_row_v) {
                $return .= '<th>' . $head_row_k . '</th>';
            }
            $return .= '</tr></thead><tbody>';
            foreach ($result as $row) {
                $return .= '<tr>';
                foreach ($head_arr as $head_row_k => $head_row_v) {
                    if ($head_row_k !== 'Action') {
                        $return .= '<td class="nosh-click" data-nosh-click="' . $row['click'] . '">' . $row[$head_row_v] . '</td>';
                    } else {
                        $return .= '<td>' . $row[$head_row_v] . '</td>';
                    }
                }
                $return .= '</tr>';
            }
            $return .= '</tbody></table>';
        } else {
            $return .= ' No invoices.';
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Billing';
        $data = array_merge($data, $this->sidebar_build('chart'));
        if ($edit == true) {
            if ($type == 'misc') {
                $dropdown_array1 = [
                    'items_button_icon' => 'fa-bars'
                ];
                $items1 = [];
                $items1[] = [
                    'type' => 'item',
                    'label' => 'Add Miscellanous Bill',
                    'icon' => 'fa-plus',
                    'url' => route('billing_details', ['new'])
                ];
                $items1[] = [
                    'type' => 'item',
                    'label' => 'Edit Billing Notes',
                    'icon' => 'fa-pencil',
                    'url' => route('billing_notes')
                ];
                $dropdown_array1['items'] = $items1;
                $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
            } else {
                $dropdown_array1 = [
                    'items_button_icon' => 'fa-pencil'
                ];
                $items1 = [];
                $items1[] = [
                    'type' => 'item',
                    'label' => 'Edit Billing Notes',
                    'icon' => 'fa-pencil',
                    'url' => route('billing_notes')
                ];
                $dropdown_array1['items'] = $items1;
                $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
            }
        }
        Session::put('last_page', $request->fullUrl());
        Session::put('billing_last_page', $request->fullUrl());
        $data['billing_active'] = true;
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function core_action(Request $request, $table, $action, $id, $index, $subtype='')
    {
        $date_convert_array = [
            'imm_expiration',
            'date_purchase',
            'date'
        ];
        $date_convert_array1 = [
            'dos_f',
            'dos_t'
        ];
        $table_message_arr = [
            'addressbook' => 'Address Book Entry ',
            'vaccine_inventory' => 'Vaccine ',
            'messaging' => 'Message Sent and ',
            'recipients' => 'Recipient ',
            'providers' => 'Provider Entry ',
            'users' => 'User ',
            'practiceinfo' => 'Practice Setting ',
            'calendar' => 'Visit Type '
        ];
        $multiple_select_arr = [
            'message_to',
            'cc'
        ];
        $duplicate_tables = [
            'addressbook'
        ];
        $provider_column_arr = [
            'specialty',
            'license',
            'license_state',
            'npi',
            'npi_taxonomy',
            'upin',
            'dea',
            'medicare',
            'tax_id',
            'peacehealth_id',
            'rcopia_username',
            'schedule_increment',
            'practice_id'
        ];
        $message = '';
        if (isset($table_message_arr[$table])) {
            $message = $table_message_arr[$table];
        }
        $arr = [];
        $mailbox = [];
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $data = $request->all();
        unset($data['_token']);
        foreach ($date_convert_array as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] !== '') {
                    $data[$key] = date('Y-m-d H:i:s', strtotime($data[$key]));
                }
            }
        }
        foreach ($date_convert_array1 as $key1) {
            if (array_key_exists($key1, $data)) {
                if ($data[$key1] !== '') {
                    $data[$key1] = date('m/d/Y', strtotime($data[$key1]));
                }
            }
        }
        foreach ($multiple_select_arr as $key2) {
            if (array_key_exists($key2, $data)) {
                if ($table == 'messaging') {
                    foreach ($data[$key2] as $arr_item) {
                        $mailbox[] = $this->get_id($arr_item);
                    }
                }
                $data[$key2] = implode(';', $data[$key2]);
            }
        }

        foreach ($duplicate_tables as $duplicate_table) {
            if ($duplicate_table == $table) {
                // $this->validate($request, [
                //     'username1' => 'required|unique:users,username',
                // ]);
            }
        }
        if ($table == 'addressbook') {
            $data['displayname'] = $request->input('facility');
            if ($subtype == 'Referral') {
                if($request->input('firstname') == '' && $request->input('lastname') == '') {
                    $data['displayname'] = $request->input('facility');
                } else {
                    if($request->input('suffix') == '') {
                        $data['displayname'] = $request->input('firstname') . ' ' . $request->input('lastname');
                    } else {
                        $data['displayname'] = $request->input('firstname') . ' ' . $request->input('lastname') . ', ' . $request->input('suffix');
                    }
                }
                $data['specialty'] = $request->input('specialty');
            }
        }
        if ($table == 'vaccine_inventory') {
            $this->validate($request, [
                'quantity' => 'numeric'
            ]);
        }
        if ($table == 'vaccine_temp') {
            $this->validate($request, [
                'temp' => 'numeric'
            ]);
        }
        if ($table == 'supplement_inventory') {
            $this->validate($request, [
                'quantity1' => 'numeric'
            ]);
            if ($data['cpt'] == '') {
                $cpt_query = DB::table('cpt_relate')->where('cpt', 'LIKE', '%sp%')->select('cpt')->get();
                if ($cpt_query->count()) {
                    $cpt_array = [];
                    $i = 0;
                    foreach ($cpt_query as $cpt_row) {
                        $cpt_array[$i]['cpt'] = $cpt_row->cpt;
                        $i++;
                    }
                    rsort($cpt_array);
                    $cpt_num = str_replace("sp", "", $cpt_array[0]['cpt']);
                    $cpt_num_new = $cpt_num + 1;
                    $cpt_num_new = str_pad($cpt_num_new, 3, "0", STR_PAD_LEFT);
                    $data['cpt'] = 'sp' . $cpt_num_new;
                } else {
                    $data['cpt'] = 'sp001';
                }
            }
        }
        if ($table == 'messaging') {
            if (isset($data['patient_name'])) {
                if ($data['patient_name'] !== '') {
                    $data['subject'] = $data['subject'] . ' [RE: ' . $data['patient_name'] . ']';
                }
            }
            $data['mailbox'] = '0';
            $data['status'] = 'Sent';
            if (isset($data['submit'])) {
                if ($data['submit'] == 'draft') {
                    $data['status'] = 'Draft';
                }
                unset($data['submit']);
            } else {
                $data['status'] = 'Sent';
                foreach ($mailbox as $mailbox_row) {
                    if ($mailbox_row !== '') {
                        $send_data = [
                            'pid' => $data['pid'],
                            'patient_name' => $data['patient_name'],
                            'message_to' => $data['message_to'],
                            'message_from' => $data['message_from'],
                            'subject' => $data['subject'],
                            'body' => $data['body'],
                            't_messages_id' => $data['t_messages_id'],
                            'status' => 'Sent',
                            'mailbox' => $mailbox_row,
                            'practice_id' => $data['practice_id']
                        ];
                        if (isset($data['cc'])) {
                            $send_data['cc'] = $data['cc'];
                        }
                        $send_id = DB::table('messaging')->insertGetId($send_data);
                        $this->audit('Add');
                        if (Session::has('messaging_add_photo')) {
                            $message_add_photo_arr = Session::get('messaging_add_photo');
                            foreach ($message_add_photo_arr as $photo_file_path) {
                                if ($data['pid'] !== '') {
                                    $directory = Session::get('documents_dir') . $data['pid'] . '/';
                                } else {
                                    $directory = Session::get('documents_dir');
                                }
                                $new_name = str_replace(public_path() . '/temp/', '', $photo_file_path);
                                $new_photo_file_path = $directory . $new_name;
                                if (!file_exists($new_photo_file_path)) {
                                    File::move($photo_file_path, $new_photo_file_path);
                                }
                                $image_data = [
                                    'image_location' => $new_photo_file_path,
                                    'pid' => $data['pid'],
                                    'message_id' => $send_id,
                                    'image_description' => 'Photo Uploaded ' . date('F jS, Y'),
                                    'id' => Session::get('user_id')
                                ];
                                $image_id = DB::table('image')->insertGetId($image_data);
                                $this->audit('Add');
                            }
                        }
                        $user_row = DB::table('users')->where('id', '=',$mailbox_row)->first();
                        if ($user_row->group_id === '100') {
                            $data_message['patient_portal'] = $practice->patient_portal;
                            $this->send_mail('emails.newmessage', $data_message, 'New Message in your Patient Portal', $user_row->email, Session::get('practice_id'));
                        }
                    }
                }
                if (isset($data['t_messages_id'])) {
                    if ($data['t_messages_id'] !== '' && $data['t_messages_id'] !== '0' && $data['t_messages_id'] !== null) {
                        $row = DB::table('users')->where('id', '=', $data['message_from'])->first();
                        $displayname = $row->displayname . ' (' . $row->id . ')';
                        $t_message = DB::table('t_messages')->where('t_messages_id', '=', $data['t_messages_id'])->first();
                        $message = $t_message->t_messages_message . "\n\r" . 'On ' . date('Y-m-d', $this->human_to_unix($data['date'])) . ', ' . $displayname . ' wrote:' . "\n---------------------------------\n" . $data['body'];
                        $data1 = [
                            't_messages_message' => $message,
                            't_messages_to' => ''
                        ];
                        DB::table('t_messages')->where('t_messages_id', '=', $t_messages_id)->update($data1);
                        $this->audit('Update');
                    }
                }
            }
        }
        if ($table == 'providers') {
            $this->validate($request, [
                'schedule_increment' => 'numeric'
            ]);
        }
        if ($table == 'practiceinfo') {
            if (Session::get('group_id') == '1') {
                if ($subtype == 'information') {
                    if (isset($data['patient_portal'])) {
                        $data['patient_portal'] = rtrim($data['patient_portal'], '/');
                    }
                    $practices = DB::table('practiceinfo')->where('practice_id', '!=', '1')->get();
                    if ($practices->count()) {
                        foreach ($practices as $practice_row) {
                            if ($practice_row->patient_portal != '') {
                                $portal_array = explode("/", $practice_row->patient_portal);
                                $practices_data = [
                                    'smtp_user' => $data['smtp_user'],
                                    'patient_portal' => $data['patient_portal'] . "/" . $portal_array[4]
                                ];
                                DB::table('practiceinfo')->where('practice_id', '=', $practice_row->practice_id)->update($practices_data);
                                $this->audit('Update');
                            }
                        }
                    }
                }
            }
        }
        if ($table == 'calendar') {
            if ($id !== '0') {
                $calendar = DB::table('calendar')->where('calendar_id', '=', $id)->first();
                $calendar_data['active'] = 'n';
                if ($action == 'save') {
                    DB::table('calendar')->where('calendar_id', '=', $id)->update($calendar_data);
                    $id = '0';
                    unset($data['calendar_id']);
                }
            }
        }
        if ($table == 'repeat_schedule') {
            if ($id == '0') {
                $data['repeat'] = '604800';
                $data['until'] = '0';
            }
        }
        if ($action == 'save') {
            if ($table == 'users') {
                if ($id == '0') {
                    $this->validate($request, [
                        'username' => 'unique:users,username',
                        // 'email' => 'required|unique:users,email'
                    ]);
                    // For new users, invitation code will be generated and queried upon acceptance of invite
                    $data['password'] = $this->gen_secret();
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $url = URL::to('accept_invitation') . '/' . $data['password'];
                    $email['message_data'] = 'You are invited to use the NOSH ChartingSystem for ' . $practice->practice_name . '.<br>Go to ' . $url . ' to get registered.';
                    $this->send_mail('auth.emails.generic', $email, 'Invitation to NOSH ChartingSystem', $data['email'], Session::get('practice_id'));
                }
                $data['displayname'] = $data['firstname'] . " " . $data['lastname'];
                if ($data['title'] !== ''){
                    $data['displayname'] = $data['firstname'] . " " . $data['lastname'] . ", " . $data['title'];
                }
                if ($subtype == '2') {
                    foreach ($provider_column_arr as $key3) {
                        if (array_key_exists($key3, $data)) {
                            $provider_data[$key3] = $data[$key3];
                            if ($key3 !== 'practice_id') {
                                unset($data[$key3]);
                            }
                        }
                    }
                }
            }
            if ($id == '0') {
                unset($data[$index]);
                $row_id1 = DB::table($table)->insertGetId($data);
                $this->audit('Add');
                $arr['message'] = $message . 'added!';
                if ($table == 'messaging') {
                    if ($data['status'] == 'Draft') {
                        $arr['message'] = 'Message saved as draft';
                    } else {
                        $arr['message'] = 'Message saved and sent';
                    }
                    if (Session::has('messaging_add_photo')) {
                        $message_add_photo_arr = Session::get('messaging_add_photo');
                        Session::forget('messaging_add_photo');
                        foreach ($message_add_photo_arr as $photo_file_path) {
                            if ($data['pid'] !== '') {
                                $directory = Session::get('documents_dir') . $data['pid'] . '/';
                            } else {
                                $directory = Session::get('documents_dir');
                            }
                            $new_name = str_replace(public_path() . '/temp/', '', $photo_file_path);
                            $new_photo_file_path = $directory . $new_name;
                            if (!file_exists($new_photo_file_path)) {
                                File::move($photo_file_path, $new_photo_file_path);
                            }
                            $image_data = [
                                'image_location' => $new_photo_file_path,
                                'pid' => $data['pid'],
                                'message_id' => $row_id1,
                                'image_description' => 'Photo Uploaded ' . date('F jS, Y'),
                                'id' => Session::get('user_id')
                            ];
                            $image_id = DB::table('image')->insertGetId($image_data);
                            $this->audit('Add');
                        }
                    }
                }
                if ($subtype == '2') {
                    $provider_data['id'] = $row_id1;
                    DB::table('providers')->insert($provider_data);
                }
            } else {
                DB::table($table)->where($index, '=', $id)->update($data);
                $this->audit('Update');
                $arr['message'] = $message . 'updated!';
                if ($table == 'messaging') {
                    if ($data['status'] == 'Draft') {
                        $arr['message'] = 'Message saved as draft';
                    } else {
                        $arr['message'] = 'Message saved and sent';
                    }
                }
                if ($subtype == '2') {
                    DB::table('providers')->where('id', '=', $id)->update($provider_data);
                }
            }
        }
        if ($action == 'inactivate') {
            if ($table == 'vaccine_inventory') {
                $data1['quantity'] = '0';
            }
            if ($table == 'supplement_inventory') {
                $data1['quantity1'] = '0';
            }
            if ($table == 'users') {
                $data1['active'] = '0';
            }
            if ($table == 'calendar') {
                $data1['active'] = 'n';
            }
            DB::table($table)->where($index, '=', $id)->update($data1);
            $this->audit('Update');
            // foreach ($api_tables as $api_table) {
            //     if ($api_table == $table) {
            //         $this->api_data('update', $table, $index, $id);
            //     }
            // }
            $arr['message'] = $message . 'inactivated!';
        }
        if ($action == 'reactivate') {
            if ($table == 'vaccine_inventory') {
                $data2['quantity'] = '1';
            }
            if ($table == 'supplement_inventory') {
                $data2['quantity1'] = '1';
            }
            if ($table == 'users') {
                $data2['active'] = '1';
            }
            if ($table == 'calendar') {
                $data2['active'] = 'y';
            }
            DB::table($table)->where($index, '=', $id)->update($data2);
            $this->audit('Update');
            // foreach ($api_tables as $api_table) {
            //     if ($api_table == $table) {
            //         $this->api_data('update', $table, $index, $id);
            //     }
            // }
            $arr['message'] = $message . 'reactivated!';
        }
        if ($action == 'delete') {
            if ($table == 'received') {
                $file = DB::table($table)->where($index, '=', $id)->first();
                if (file_exists($file->filePath)) {
                    unlink($file->filePath);
                }
            }
            if ($table == 'messaging') {
                $images = DB::table('image')->where('message_id', '=', $id)->get();
                if ($images->count()) {
                    foreach ($images as $image) {
                        DB::table('image')->where('image_id', '=', $image->image_id)->delete();
                        $this->audit('Delete');
                        $images1 = DB::table('image')->where('image_location', '=', $image->image_location)->first();
                        if (!$images1) {
                            // Clean up any unlinked images
                            if (file_exists($image->image_location)) {
                                unlink($image->image_location);
                            }
                        }
                    }
                }
            }
            DB::table($table)->where($index, '=', $id)->delete();
            $this->audit('Delete');
            // foreach ($api_tables as $api_table) {
            //     if ($api_table == $table) {
            //         $this->api_data('delete', $table, $index, $id);
            //     }
            // }
            $arr['message'] = $message . 'deleted!';
        }
        if ($action == 'complete') {
            if ($table == 'alerts') {
                $data6['alert_date_complete'] = date('Y-m-d H:i:s');
                $orders_query = DB::table($table)->where($index, '=', $id)->first();
                if ($orders_query->orders_id != '') {
                    $data7['orders_completed'] = 'Yes';
                    DB::table('orders')->where('orders_id', '=', $orders_query->orders_id)->update($data7);
                    $this->audit('Update');
                }
            }
            if ($table == 'orders') {
                $data6['orders_completed'] = '1';
                $alerts_query = DB::table('alerts')->where('orders_id', '=', $id)->first();
                if ($alerts_query) {
                    $data8['alert_date_complete'] = date('Y-m-d H:i:s');
                    DB::table('alerts')->where('alert_id', '=', $alerts_query->alert_id)->update($data8);
                    $this->audit('Update');
                }
            }
            DB::table($table)->where($index, '=', $id)->update($data6);
            $this->audit('Update');
            $arr['message'] = $message . 'marked as completed!';
        }
        $arr['response'] = 'OK';
        Session::put('message_action', $arr['message']);
        if ($table == 'recipients') {
            return redirect(Session::get('messaging_last_page'));
        }
        if ($table == 'addressbook' && $subtype == 'Referral') {
            return redirect(Session::get('addressbook_last_page'));
        }
        if ($table == 'practiceinfo') {
            return redirect()->route('setup');
        }
        return redirect(Session::get('last_page'));
    }

    public function core_form(Request $request, $table, $index, $id, $subtype='')
    {
        if ($id == '0') {
            $result = [];
            $items[] = [
                'name' => $index,
                'type' => 'hidden',
                'required' => true,
                'default_value' => null
            ];
        } else {
            $result = DB::table($table)->where($index, '=', $id)->first();
            if ($table == 'messaging') {
                if ($subtype !== '') {
                    $items[] = [
                        'name' => $index,
                        'type' => 'hidden',
                        'required' => true,
                        'default_value' => null
                    ];
                } else {
                    $items[] = [
                        'name' => $index,
                        'type' => 'hidden',
                        'required' => true,
                        'default_value' => $id
                    ];
                }
            } else {
                $items[] = [
                    'name' => $index,
                    'type' => 'hidden',
                    'required' => true,
                    'default_value' => $id
                ];
            }
        }
        $form_function = 'form_' . $table;
        $items = array_merge($items, $this->{$form_function}($result, $table, $id, $subtype));
        // Address Book
        if ($table == 'addressbook') {
            if ($subtype == 'Referral') {
                $data['search_specialty'] = 'specialty';
            }
            if ($id == '0') {
                $data['panel_header'] = 'Add Address Book Entry';
                if ($subtype !== '') {
                    $data['panel_header'] = 'Add ' . $subtype . ' Entry';
                }
            } else {
                $data['panel_header'] = 'Edit Address Book Entry';
                if ($subtype !== '') {
                    $data['panel_header'] = 'Edit ' . $subtype . ' Entry';
                }
            }
        }
        // Vaccines
        if ($table == 'vaccine_inventory') {
            $data['search_immunization'] = 'imm_immunization';
            $data['search_cpt'] = 'cpt';
            if ($id == '0') {
                $data['panel_header'] = 'Add Vaccine Entry';
            } else {
                $data['panel_header'] = 'Edit Vaccine Entry';
            }
        }
        if ($table == 'vaccine_temp') {
            if ($id == '0') {
                $data['panel_header'] = 'Add Temperature';
            } else {
                $data['panel_header'] = 'Edit Temperature';
            }
        }
        // Supplements inventory
        if ($table == 'supplement_inventory') {
            $data['search_immunization'] = 'imm_immunization';
            $data['search_cpt'] = 'cpt';
            if ($id == '0') {
                $data['panel_header'] = 'Add Supplement Entry';
            } else {
                $data['panel_header'] = 'Edit Supplement Entry';
            }
        }
        // Messaging
        if ($table == 'messaging') {
            $data['template_content'] = 'test';
            $data['search_patient1'] = 'pid';
            if ($id == '0') {
                $data['panel_header'] = 'New Message';
            } else {
                $data['panel_header'] = 'Edit Message';
            }
            $dropdown_array = [
               'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
               'type' => 'item',
               'label' => 'Add Photo/Image',
               'icon' => 'fa-camera',
               'url' => route('messaging_add_photo', [$id]),
               'id' => 'nosh_messaging_add_photo'
            ];
            $dropdown_array['items'] = $items1;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            Session::put('message_photo_last_page', $request->fullUrl());
        }
        // Recipients
        if ($table == 'recipients') {
            $data['search_addressbook'] = 'faxnumber';
            if ($id == '0') {
                $data['panel_header'] = 'New Fax Recipient';
            } else {
                $data['panel_header'] = 'Edit Fax Recipient';
            }
        }
        // Providers
        if ($table == 'providers') {
            $data['search_specialty'] = 'specialty';
            $data['panel_header'] = 'My Information';
        }
        // Practice information
        if ($table == 'practiceinfo') {
            if ($subtype == 'information') {
                $data['panel_header'] = 'Practice Information';
            }
            if ($subtype == 'settings') {
                $data['panel_header'] = 'Practice Settings';
            }
            if ($subtype == 'billing') {
                $data['panel_header'] = 'Billing';
            }
            if ($subtype == 'extensions') {
                $data['panel_header'] = 'Extensions';
            }
            if ($subtype == 'schedule') {
                $data['panel_header'] = 'Schedule Setup';
            }
        }
        // Users
        if ($table == 'users') {
            $user_arr = $this->array_groups();
            if ($subtype == '2') {
                $data['search_specialty'] = 'specialty';
            }
            if ($id == '0') {
                $data['panel_header'] = 'New ' . $user_arr[$subtype]. ' User';
            } else {
                $data['panel_header'] = 'Edit ' . $user_arr[$subtype] . ' User';
            }
        }
        // Visit Types
        if ($table == 'calendar') {
            if ($id == '0') {
                $data['panel_header'] = 'New Visit Type';
            } else {
                $data['panel_header'] = 'Edit Visit Type';
            }
        }
        // Schedule Exeptions
        if ($table == 'repeat_schedule') {
            if ($id == '0') {
                $data['panel_header'] = 'New Schedule Exemption';
            } else {
                $data['panel_header'] = 'Edit Schedule Exemption';
            }
        }
        $form_array = [
            'form_id' => $table . '_form',
            'action' => route('core_action', ['table' => $table, 'action' => 'save', 'index' => $index, 'id' => $id]),
            'items' => $items,
            'save_button_label' => 'Save'
        ];
        if (Session::has('addressbook_last_page')) {
            $form_array['origin'] = Session::get('addressbook_last_page');
            Session::forget('addressbook_last_page');
        }
        if ($table == 'messaging') {
            $form_array['save_button_label'] = 'Send';
            $form_array['add_save_button'] = ['draft' => 'Save as Draft'];
            if ($subtype !== '') {
                $form_array['action'] = route('core_action', ['table' => $table, 'action' => 'save', 'index' => $index, 'id' => '0']);
            }
        }
        if ($table == 'recipients') {
            $form_array['origin'] = Session::get('messaging_last_page');
        }
        if ($table == 'users') {
            $form_array['action'] = route('core_action', ['table' => $table, 'action' => 'save', 'index' => $index, 'id' => $id, 'subtype' => $subtype]);
        }
        $data['content'] = $this->form_build($form_array);
        if ($table == 'messaging') {
            $images = DB::table('image')->where('message_id', '=', $id)->get();
            $images1 = [];
            if (Session::has('messaging_add_photo')) {
                $images1 = Session::get('messaging_add_photo');
            }
            if ($images->count() || count($images1) > 0) {
                $data['content'] .= '<br><h5>Images:</h5><div class="list-group gallery">';
                if ($images->count()) {
                    foreach ($images as $image) {
                        $file_path1 = '/temp/' . time() . '_' . basename($image->image_location);
                        $file_path = public_path() . $file_path1;
                        copy($image->image_location, $file_path);
                        $data['content'] .= '<div class="col-sm-4 col-xs-6 col-md-3 col-lg-3"><a class="thumbnail fancybox nosh-no-load" rel="ligthbox" href="' . url('/') . $file_path1 . '">';
                        $data['content'] .= '<img class="img-responsive" alt="" src="' . url('/') . $file_path1 . '" />';
                        $data['content'] .= '<div class="text-center"><small class="text-muted">' . basename($image->image_location) . '</small></div></a>';
                        $data['content'] .= '<a href="' . route('messaging_delete_photo', [$image->image_id]) . '" class="nosh-photo-delete close-icon btn btn-danger"><i class="glyphicon glyphicon-remove"></i></a></div>';
                    }
                }
                if (count($images1) > 0) {
                    foreach ($images1 as $image1) {
                        $file_path1 = str_replace(public_path(), '', $image1);
                        $data['content'] .= '<div class="col-sm-4 col-xs-6 col-md-3 col-lg-3"><a class="thumbnail fancybox nosh-no-load" rel="ligthbox" href="' . url('/') . $file_path1 . '">';
                        $data['content'] .= '<img class="img-responsive" alt="" src="' . url('/') . $file_path1 . '" />';
                        $data['content'] .= '<div class="text-center"><small class="text-muted">' . $image1 . '</small></div></a>';
                        $data['content'] .= '<a href="' . route('messaging_delete_photo', [$image1, 'session']) . '" class="nosh-photo-delete close-icon btn btn-danger"><i class="glyphicon glyphicon-remove"></i></a></div>';
                    }
                }
                $data['content'] .= '</div>';
            }
        }
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        if (Session::has('pid')) {
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            $data = array_merge($data, $this->sidebar_build('chart'));
            return view('chart', $data);
        } else {
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function configure_form_delete(Request $request, $type)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $formatter = Formatter::make($user->forms, Formatter::YAML);
        $array = $formatter->toArray();
        unset($array[$type]);
        $formatter1 = Formatter::make($array, Formatter::ARR);
        $data1['forms'] = $formatter1->toYaml();
        DB::table('users')->where('id', '=', Session::get('user_id'))->update($data1);
        Session::put('message_action', 'Form deleted');
        return redirect()->route('configure_form_list');
    }

    public function configure_form_details(Request $request, $type)
    {
        $id = Session::get('user_id');
        $user = DB::table('users')->where('id', '=', $id)->first();
        $formatter = Formatter::make($user->forms, Formatter::YAML);
        $array = $formatter->toArray();
        if ($request->isMethod('post')) {
            if ($type == '0') {
                $type = $request->input('forms_title');
            }
            if ($type !== $request->input('forms_title')) {
                $old_type = $type;
                $type = $request->input('forms_title');
                $array[$type] = $array[$old_type];
                unset($array[$old_type]);
            }
            $array[$type]['forms_title'] = $request->input('forms_title');
            $array[$type]['forms_destination'] = $request->input('forms_destination');
            if ($request->input('gender') !== '') {
                $array[$type]['gender'] = $request->input('gender');
            }
            if ($request->input('age') !== '') {
                $array[$type]['age'] = $request->input('age');
            }
            if ($request->has('forms_scoring')) {
                $array[$type]['scoring'] = $request->input('forms_scoring');
            }
            $formatter1 = Formatter::make($array, Formatter::ARR);
            $data['forms'] = $formatter1->toYaml();
            DB::table('users')->where('id', '=', $id)->update($data);
            Session::put('message_action', 'Form added');
            return redirect()->route('configure_form_show', [$type]);
        } else {
            $data['panel_header'] = 'Configure Form';
            $data['content'] = '';
            $gender = null;
            $age = null;
            if ($type == '0') {
                $forms_title = null;
                $forms_destination = null;
            } else {
                $forms_title = $array[$type]['forms_title'];
                $forms_destination = $array[$type]['forms_destination'];
                if (isset($array[$type]['gender'])) {
                    $gender = $array[$type]['gender'];
                }
                if (isset($array[$type]['age'])) {
                    $age = $array[$type]['age'];
                }
                if (isset($array[$type]['scoring'])) {
                    $data['content'] .= '<div class="alert alert-success"><h5>Scoring Algorithm</h5><ul>';
                    $forms_scoring_arr = [];
                    foreach ($array[$type]['scoring'] as $score_row_k=>$score_row_v) {
                        $data['content'] .= '<li>' . $score_row_k . ': ' . $score_row_v . '</li>';
                    }
                    $data['content'] .= '</ul></div>';
                }
            }
            $gender_arr = [
                '' => 'All Genders',
                'm' => 'Male Only',
                'f' => 'Female Only',
                'u' => 'Undifferentiated Only'
            ];
            $age_arr = [
                '' => 'All Ages',
                'adult' => 'Adult Only',
                'child' => 'Child Only'
            ];
            $items[] = [
                'name' => 'forms_title',
                'label' => 'Form Title',
                'type' => 'text',
                'required' => true,
                'default_value' => $forms_title
            ];
            $items[] = [
                'name' => 'gender',
                'label' => 'Gender Association',
                'type' => 'select',
                'select_items' => $gender_arr,
                'default_value' => $gender
            ];
            $items[] = [
                'name' => 'age',
                'label' => 'Age Association',
                'type' => 'select',
                'select_items' => $age_arr,
                'default_value' => $age
            ];
            $form_array = [
                'form_id' => 'patient_form_item',
                'action' => route('configure_form_details', [$type]),
                'items' => $items,
                'save_button_label' => 'Save'
            ];
            $data['content'] .= $this->form_build($form_array);
            $dropdown_array1 = [
               'items_button_icon' => 'fa-cog'
            ];
            $items1 = [];
            $items1[] = [
               'type' => 'item',
               'label' => 'Configure Scoring Algorithm',
               'icon' => 'fa-cog',
               'url' => route('configure_form_scoring_list', [$type])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array1);
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function configure_form_edit(Request $request, $type, $item)
    {
        $id = Session::get('user_id');
        $user = DB::table('users')->where('id', '=', $id)->first();
        $formatter = Formatter::make($user->forms, Formatter::YAML);
        $array = $formatter->toArray();
        if ($request->isMethod('post')) {
            $data['input'] = $request->input('form_type');
            $data['text'] = $request->input('form_label');
            $search_arr = [' ', ':', '?', ',', ';'];
            $replace_arr = ['_', '', '', '', ''];
            $data['name'] = str_replace($search_arr, $replace_arr, strtolower($request->input('form_label')));
            if ($request->has('form_options')) {
                $data['options'] = $request->input('form_options');
            }
            if ($item == 'new') {
                $array[$type][] = $data;
                $message = 'Form item added';
            } else {
                $array[$type][$item] = $data;
                $message = 'Form item updated';
            }
            $formatter1 = Formatter::make($array, Formatter::ARR);
            $data1['forms'] = $formatter1->toYaml();
            DB::table('users')->where('id', '=', $id)->update($data1);
            Session::put('message_action', $message);
            return redirect()->route('configure_form_show', [$type]);
        } else {
            $form_options = null;
            if ($item == 'new') {
                $data['panel_header'] = 'Add Form Item';
                $form_label = null;
                $form_type = null;
            } else {
                $data['panel_header'] = 'Edit Form Item';
                $form_label = $array[$type][$item]['text'];
                $form_type = $array[$type][$item]['input'];
                if (isset($array[$type][$item]['options'])) {
                    $form_options = $array[$type][$item]['options'];
                }
            }
            $type_arr = [
                'text' => 'Text Input',
                'select' => 'Dropdown List',
                'checkbox' => 'Checkbox',
                'radio' => 'Radio Button'
            ];
            $items[] = [
                'name' => 'form_label',
                'label' => 'Form Label',
                'type' => 'text',
                'default_value' => $form_label
            ];
            $items[] = [
                'name' => 'form_type',
                'label' => 'Input Type',
                'type' => 'select',
                'select_items' => $type_arr,
                'default_value' => $form_type
            ];
            $items[] = [
                'name' => 'form_options',
                'label' => 'Options',
                'type' => 'text',
                'default_value' => $form_options
            ];
            $form_array = [
                'form_id' => 'patient_form_item',
                'action' => route('configure_form_edit', [$type, $item]),
                'items' => $items,
                'save_button_label' => 'Save'
            ];
            $data['content'] = '<div class="alert alert-success"><h5>Scoring Tips</h5><ul>';
            $data['content'] .= '<li>Scoring of each response only applies to the checkbox and radio button input types</li>';
            $data['content'] .= '<li>The first listed option has a scoring value of 0, the second listed option has a scoring value of 1, etc.</li>';
            $data['content'] .= '</ul></div>';
            $data['content'] .= $this->form_build($form_array);
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function configure_form_list(Request $request)
    {
        $list_array = [];
        $return = '';
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $data['panel_header'] = 'My Forms';
        if ($user->forms == null || $user->forms == '') {
            $data1['forms'] = File::get(resource_path() . '/forms.yaml');
            DB::table('users')->where('id', '=', Session::get('user_id'))->update($data1);
            $yaml = $data1['forms'];
        } else {
            $yaml = $user->forms;
        }
        $formatter = Formatter::make($yaml, Formatter::YAML);
        $array = $formatter->toArray();
        foreach ($array as $row_k => $row_v) {
            $arr = [];
            $arr['label'] = $row_k;
            $arr['edit'] = route('configure_form_show', [$row_k]);
            $arr['delete'] = route('configure_form_delete', [$row_k]);
            $list_array[] = $arr;
        }
        if (! empty($list_array)) {
            $return .= $this->result_build($list_array, 'forms_list');
        } else {
            $return .= ' None.';
        }
        $dropdown_array1 = [
           'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
           'type' => 'item',
           'label' => 'Add Form',
           'icon' => 'fa-plus',
           'url' => route('configure_form_details', ['0'])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function configure_form_remove(Request $request, $type, $item)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $formatter = Formatter::make($user->forms, Formatter::YAML);
        $array = $formatter->toArray();
        unset($array[$type][$item]);
        $formatter1 = Formatter::make($array, Formatter::ARR);
        $data1['forms'] = $formatter1->toYaml();
        DB::table('users')->where('id', '=', Session::get('user_id'))->update($data1);
        Session::put('message_action', 'Form item removed');
        return redirect()->route('configure_form_show', [$type]);
    }

    public function configure_form_scoring(Request $request, $type, $item)
    {
        $id = Session::get('user_id');
        $user = DB::table('users')->where('id', '=', $id)->first();
        $formatter = Formatter::make($user->forms, Formatter::YAML);
        $array = $formatter->toArray();
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'min' => 'numeric',
                'max' => 'numeric'
            ]);
            $new_item = $request->input('min') . '-' . $request->input('max');
            if ($item == 'new') {
                $message = 'Scoring item added';
            } else {
                if ($item !== $new_item) {
                    unset($array[$type]['scoring'][$item]);
                }
                $message = 'Scoring item updated';
            }
            $array[$type]['scoring'][$new_item] = $request->input('description');
            $formatter1 = Formatter::make($array, Formatter::ARR);
            $data1['forms'] = $formatter1->toYaml();
            DB::table('users')->where('id', '=', $id)->update($data1);
            Session::put('message_action', $message);
            return redirect()->route('configure_form_scoring_list', [$type]);
        } else {
            $min = null;
            $max = null;
            $description = null;
            if ($item !== 'new') {
                $range = explode('-', $item);
                $min = $range[0];
                $max = $range[1];
                $description = $array[$type]['scoring'][$item];
            }
            $items[] = [
                'name' => 'min',
                'label' => 'Minimum Value',
                'type' => 'text',
                'required' => true,
                'default_value' => $min
            ];
            $items[] = [
                'name' => 'max',
                'label' => 'Maximum Value',
                'type' => 'text',
                'required' => true,
                'default_value' => $max
            ];
            $items[] = [
                'name' => 'description',
                'label' => 'Description',
                'type' => 'text',
                'default_value' => $description
            ];
            $form_array = [
                'form_id' => 'patient_form_scoring',
                'action' => route('configure_form_scoring', [$type, $item]),
                'items' => $items,
                'save_button_label' => 'Save'
            ];
            $data['panel_header'] = 'Edit Scoring Algorithm';
            $data['content'] = $this->form_build($form_array);
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function configure_form_scoring_delete(Request $request, $type, $item)
    {
        $id = Session::get('user_id');
        $user = DB::table('users')->where('id', '=', $id)->first();
        $formatter = Formatter::make($user->forms, Formatter::YAML);
        $array = $formatter->toArray();
        unset($array[$type]['scoring'][$item]);
        $formatter1 = Formatter::make($array, Formatter::ARR);
        $data1['forms'] = $formatter1->toYaml();
        DB::table('users')->where('id', '=', $id)->update($data1);
        Session::put('message_action', $message);
        return redirect()->route('configure_form_scoring_list', [$type]);
    }

    public function configure_form_scoring_list(Request $request, $type)
    {
        $return = '';
        $data['panel_header'] = 'Configure Scoring Algorithm';
        $id = Session::get('user_id');
        $user = DB::table('users')->where('id', '=', $id)->first();
        $formatter = Formatter::make($user->forms, Formatter::YAML);
        $array = $formatter->toArray();
        if (isset($array[$type]['scoring'])) {
            $list_array = [];
            foreach ($array[$type]['scoring'] as $row_k => $row_v) {
                $arr = [];
                $arr['label'] = $row_k . ':' . $row_v;
                $arr['edit'] = route('configure_form_scoring', [$type, $row_k]);
                $arr['delete'] = route('configure_form_scoring_delete', [$type, $row_k]);
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'scoring_list');
        } else {
            $return .= ' None.';
        }
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => Session::get('last_page')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $dropdown_array1 = [
           'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
           'type' => 'item',
           'label' => '',
           'icon' => 'fa-plus',
           'url' => route('configure_form_scoring', [$type, 'new'])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function configure_form_show(Request $request, $type)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $formatter = Formatter::make($user->forms, Formatter::YAML);
        $array = $formatter->toArray();
        $items = [];
        foreach ($array[$type] as $row_k => $row_v) {
            if ($row_k !== 'forms_title' && $row_k !== 'forms_destination' && $row_k !== 'scoring' && $row_k !== 'gender' && $row_k !== 'age') {
                $form_item = [
                    'name' => $row_v['name'],
                    'label' => $row_v['text'],
                    'type' => $row_v['input'],
                    'required' => true,
                    'default_value' => null
                ];
                if ($row_v['input'] == 'checkbox' || $row_v['input'] == 'radio' || $row_v['input'] == 'select') {
                    $options = [];
                    if (isset($row_v['options'])) {
                        $options_arr = explode(',', $row_v['options']);
                        foreach ($options_arr as $options_item)
                        $options[$options_item] = $options_item;
                    }
                    if ($row_v['input'] == 'select') {
                        $form_item['select_items'] = $options;
                    } else {
                        $form_item['section_items'] = $options;
                    }
                }
                $items[$row_k] = $form_item;
            }
        }
        $form_array = [
            'form_id' => 'patient_form',
            'action' => route('configure_form_list'),
            'items' => $items,
            'save_button_label' => 'Save',
            'origin' => route('configure_form_list')
        ];
        $data['content'] = '<div class="alert alert-success"><h5>Scoring Tips</h5><ul>';
        $data['content'] .= '<li>Scoring of each response only applies to the checkbox and radio button input types</li>';
        $data['content'] .= '<li>The first listed option has a scoring value of 0, the second listed option has a scoring value of 1, etc.</li>';
        $data['content'] .= '</ul></div>';
        $data['content'] .= $this->form_build($form_array, true, $type);
        $dropdown_array = [
            'default_button_text' => 'Add Form Element',
            'default_button_text_url' => route('configure_form_edit', [$type, 'new']),
            'default_button_id' => 'add_form_element'
        ];
        $items1[] = [
            'type' => 'item',
            'label' => 'Configure Form',
            'icon' => 'fa-cog',
            'url' => route('configure_form_details', [$type])
        ];
        $dropdown_array['items'] = $items1;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['panel_header'] = 'Form Editor';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function dashboard(Request $request)
    {
        $data['title'] = 'NOSH ChartingSystem';
        $user_id = Session::get('user_id');
        if (Session::get('group_id') == '100') {
            $row = DB::table('demographics_relate')->where('id', '=', $user_id)->first();
            $this->setpatient($row->pid);
            return redirect()->intended('patient');
        }
        if (Session::get('group_id') != '100' && Session::get('patient_centric') == 'yp') {
            return redirect()->route('pnosh_provider_redirect');
        }
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $practice_id = Session::get('practice_id');
        $data['practiceinfo'] = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        $result = DB::table('users')->where('id', '=', $user_id)->first();
        $data['displayname'] = $result->displayname;
        $displayname = $result->displayname;
        $fax_query = DB::table('received')->where('practice_id', '=', $practice_id)->count();
        $from = $displayname . ' (' . $user_id . ')';
        if (Session::get('group_id') == '2') {
            $data['number_messages'] = DB::table('messaging')->where('mailbox', '=', $user_id)->where('read', '=', null)->count();
            $data['number_scans'] = DB::table('scans')->where('practice_id', '=', $practice_id)->count();
            if ($data['practiceinfo']->fax_type !== '') {
                $data['number_faxes'] = DB::table('received')->where('practice_id', '=', $practice_id)->count();
            }
            $data['number_appts'] = $this->getNumberAppts($user_id);
            $data['number_t_messages'] = DB::table('t_messages')
                ->join('demographics', 't_messages.pid', '=', 'demographics.pid')
                ->where('t_messages.t_messages_from', '=', $from)
                ->where('t_messages.t_messages_signed', '=', 'No')
                ->count();
            $data['number_encounters'] = DB::table('encounters')
                ->join('demographics', 'encounters.pid', '=', 'demographics.pid')
                ->where('encounters.encounter_provider', '=', $displayname)
                ->where('encounters.encounter_signed', '=', 'No')
                ->count();
            $data['number_reminders'] = DB::table('alerts')
                ->join('demographics', 'alerts.pid', '=', 'demographics.pid')
                ->where('alerts.alert_provider', '=', $user_id)
                ->where('alerts.alert_date_complete', '=', '0000-00-00 00:00:00')
                ->where('alerts.alert_reason_not_complete', '=', '')
                ->where(function($query_array) {
                    $query_array->where('alerts.alert', '=', 'Laboratory results pending')
                    ->orWhere('alerts.alert', '=', 'Radiology results pending')
                    ->orWhere('alerts.alert', '=', 'Cardiopulmonary results pending')
                    ->orWhere('alerts.alert', '=', 'Referral pending')
                    ->orWhere('alerts.alert', '=', 'Laboratory results pending - NEED TO OBTAIN')
                    ->orWhere('alerts.alert', '=', 'Radiology results pending - NEED TO OBTAIN')
                    ->orWhere('alerts.alert', '=', 'Cardiopulmonary results pending - NEED TO OBTAIN')
                    ->orWhere('alerts.alert', '=', 'Reminder')
                    ->orWhere('alerts.alert', '=', 'REMINDER');
                })
                ->count();
            $data['number_bills'] = DB::table('encounters')->where('bill_submitted', '=', 'No')->where('user_id', '=', $user_id)->count();
            $data['number_tests'] = DB::table('tests')->whereNull('pid')->where('practice_id', '=', $practice_id)->count();
            if($data['practiceinfo']->mtm_extension == 'y') {
                $mtm_users_array = explode(",", $data['practiceinfo']->mtm_alert_users);
                if (in_array($user_id, $mtm_users_array)) {
                    $data['mtm_alerts'] = DB::table('alerts')->where('alert_date_complete', '=', '0000-00-00 00:00:00')
                        ->where('alert_reason_not_complete', '=', '')
                        ->where('alert', '=', 'Medication Therapy Management')
                        ->where('practice_id', '=', $practice_id)
                        ->count();
                    $data['mtm_alerts_status'] = "y";
                } else {
                    $data['mtm_alerts_status'] = "n";
                }
            } else {
                $data['mtm_alerts_status'] = "n";
            }
            $data['panel_header'] = 'Inventory Alerts';
            $data['content'] = $this->vaccine_supplement_alert($practice_id);
        }
        if (Session::get('group_id') == '3') {
            $data['number_messages'] = DB::table('messaging')->where('mailbox', '=', $user_id)->where('read', '=', null)->count();
            $data['number_scans'] = DB::table('scans')->where('practice_id', '=', $practice_id)->count();
            if ($data['practiceinfo']->fax_type !== '') {
                $data['number_faxes'] = DB::table('received')->where('practice_id', '=', $practice_id)->count();
            }
            $data['number_t_messages'] = DB::table('t_messages')
                ->join('demographics', 't_messages.pid', '=', 'demographics.pid')
                ->where('t_messages.t_messages_from', '=', $from)
                ->where('t_messages.t_messages_signed', '=', 'No')
                ->count();
            $data['number_reminders'] = DB::table('alerts')
                ->join('demographics', 'alerts.pid', '=', 'demographics.pid')
                ->where('alerts.alert_provider', '=', $user_id)
                ->where('alerts.alert_date_complete', '=', '0000-00-00 00:00:00')
                ->where('alerts.alert_reason_not_complete', '=', '')
                ->where(function($query_array) {
                    $query_array->where('alerts.alert', '=', 'Laboratory results pending')
                    ->orWhere('alerts.alert', '=', 'Radiology results pending')
                    ->orWhere('alerts.alert', '=', 'Cardiopulmonary results pending')
                    ->orWhere('alerts.alert', '=', 'Referral pending')
                    ->orWhere('alerts.alert', '=', 'Laboratory results pending - NEED TO OBTAIN')
                    ->orWhere('alerts.alert', '=', 'Radiology results pending - NEED TO OBTAIN')
                    ->orWhere('alerts.alert', '=', 'Cardiopulmonary results pending - NEED TO OBTAIN')
                    ->orWhere('alerts.alert', '=', 'Reminder')
                    ->orWhere('alerts.alert', '=', 'REMINDER');
                })
                ->count();
            $data['number_bills'] = DB::table('encounters')->where('bill_submitted', '=', 'No')->where('user_id', '=', $user_id)->count();
            $data['number_tests'] = DB::table('tests')->whereNull('pid')->where('practice_id', '=', $practice_id)->count();
            $data['panel_header'] = 'Inventory Alerts';
            $data['content'] = $this->vaccine_supplement_alert($practice_id);
        }
        if (Session::get('group_id') == '4') {
            $data['number_messages'] = DB::table('messaging')->where('mailbox', '=', $user_id)->where('read', '=', null)->count();
            $data['number_bills'] = DB::table('encounters')->where('bill_submitted', '=', 'No')->where('user_id', '=', $user_id)->count();
            $data['number_scans'] = DB::table('scans')->where('practice_id', '=', $practice_id)->count() + $fax_query;
            if ($data['practiceinfo']->fax_type !== '') {
                $data['number_faxes'] = DB::table('received')->where('practice_id', '=', $practice_id)->count();
            }
        }
        if (Session::get('group_id') == '1') {
            if ($practice_id == '1') {
                $data['saas_admin'] = 'y';
            }
            if (Session::get('patient_centric') !== 'y') {
                $users = DB::table('users')->where('group_id', '=', '2')->where('practice_id', '=', Session::get('practice_id'))->first();
                if (!$users) {
                    $data['users_needed'] = 'y';
                }
                $schedule = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->whereNull('minTime')->first();
                if ($schedule) {
                    $data['schedule_needed'] = 'y';
                }
            }
            if (Session::has('download_now')) {
                $data['download_now'] = route('download_now');
            }
            if (Session::has('download_ccda_entire')) {
                $data['download_progress'] = Session::get('download_ccda_entire');
                Session::forget('download_ccda_entire');
            }
            if (Session::has('download_charts_entire')) {
                $data['download_progress'] = Session::get('download_charts_entire');
                Session::forget('download_charts_entire');
            }
            if (Session::has('database_export')) {
                $data['download_progress'] = Session::get('database_export');
                Session::forget('database_export');
            }
            if (Session::has('download_csv_demographics')) {
                $data['download_progress'] = Session::get('download_csv_demographics');
                Session::forget('download_csv_demographics');
            }
            if (!isset($data['users_needed']) && !isset($data['schedule_needed'])) {
                $data['admin_ok'] = 'y';
            }
            $query = DB::table('audit')->where('practice_id', '=', Session::get('practice_id'))->orderBy('timestamp', 'desc')->paginate(20);
            if ($query->count()) {
                $list_array = [];
                foreach ($query as $row) {
                    $arr = [];
                    $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->timestamp)) . '</b> - ' . $row->displayname . '<br>' . $row->query;
                    $list_array[] = $arr;
                }
                $return = $this->result_build($list_array, 'audit_list');
                $return .= $query->links();
            } else {
                $return = 'None.';
            }
            $data['panel_header'] = 'Audit Logs';
            $data['content'] = $return;
        }
        $data['weekends'] = 'false';
        $data['schedule_increment'] = '15';
        if ($data['practiceinfo']->weekends == '1') {
            $data['weekends'] = 'true';
        }
        $data['minTime'] = ltrim($data['practiceinfo']->minTime,"0");
        $data['maxTime'] = ltrim($data['practiceinfo']->maxTime,"0");
        if (Session::get('group_id') == '2') {
            $provider = DB::table('providers')->where('id', '=', Session::get('user_id'))->first();
            $data['schedule_increment'] = $provider->schedule_increment;
        }
        if ($data['practiceinfo']->fax_type != "") {
            $data1['fax'] = true;
        } else {
            $data1['fax'] = false;
        }
        Session::put('last_page', $request->fullUrl());
        // $data['name'] = 'Test';
        // $data['template_content'] = 'test';

        // $data['back'] = '<div class="btn-group"><button type="button" class="btn btn-primary">Action</button><button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button>';
        // $data['back'] .= '<ul class="dropdown-menu"><li><a href="#">Action</a></li><li><a href="#">Another action</a></li><li><a href="#">Something else here</a></li><li role="separator" class="divider"></li><li><a href="#">Separated link</a></li></ul></div>';
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('welcome', $data);
    }

    public function dashboard_encounters(Request $request)
    {
        $query = DB::table('encounters')
            ->join('demographics', 'encounters.pid', '=', 'demographics.pid')
            ->where('encounters.user_id', '=', Session::get('user_id'))
            ->where('encounters.encounter_signed', '=', 'No')
            ->get();
        if ($query->count()) {
            $list_array = [];
            $encounter_type = $this->array_encounter_type();
            foreach ($query as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->encounter_DOS)) . '</b> - ' . $encounter_type[$row->encounter_template] . ' - ' . $row->encounter_cc . '<br>Patient: ' . $row->firstname . ' ' . $row->lastname . ' (DOB: ' . date('m/d/Y', strtotime($row->DOB)) . ')';
                $arr['edit'] = route('superquery_patient', ['encounter', $row->pid, $row->eid]);
                $list_array[] = $arr;
            }
            $return = $this->result_build($list_array, 'encounters_list');
        } else {
            $return = 'None.';
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Encounters to be Completed';
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => route('dashboard')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function dashboard_reminders(Request $request)
    {
        $query = DB::table('alerts')
            ->join('demographics', 'alerts.pid', '=', 'demographics.pid')
            ->where('alerts.alert_provider', '=', Session::get('user_id'))
            ->where('alerts.alert_date_complete', '=', '0000-00-00 00:00:00')
            ->where('alerts.alert_reason_not_complete', '=', '')
            ->where(function($query_array) {
                $query_array->where('alerts.alert', '=', 'Laboratory results pending')
                ->orWhere('alerts.alert', '=', 'Radiology results pending')
                ->orWhere('alerts.alert', '=', 'Cardiopulmonary results pending')
                ->orWhere('alerts.alert', '=', 'Referral pending')
                ->orWhere('alerts.alert', '=', 'Laboratory results pending - NEED TO OBTAIN')
                ->orWhere('alerts.alert', '=', 'Radiology results pending - NEED TO OBTAIN')
                ->orWhere('alerts.alert', '=', 'Cardiopulmonary results pending - NEED TO OBTAIN')
                ->orWhere('alerts.alert', '=', 'Reminder')
                ->orWhere('alerts.alert', '=', 'REMINDER');
            })
            ->get();
        if ($query->count()) {
            $list_array = [];
            foreach ($query as $row) {
                $arr = [];
                $arr['label'] = $row->alert . ' (Due ' . date('m/d/Y', $this->human_to_unix($row->alert_date_active)) . ') - ' . $row->alert_description . '<br>Patient: '  .$row->firstname . ' ' . $row->lastname . ' (DOB: ' . date('m/d/Y', strtotime($row->DOB)) . ')';
                $arr['view'] = route('superquery_patient', ['chart_form', $row->pid, 'alerts', 'alert_id', $row->alert_id]);
                $list_array[] = $arr;
            }
            $return = $this->result_build($list_array, 'reminders_list');
        } else {
            $return = 'None.';
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Reminders';
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => route('dashboard')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function dashboard_tests(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('tests')
            ->whereNull('pid')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->get();
        if ($query->count()) {
            $list_array = [];
            foreach ($query as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->test_datetime)) . '</b> - ' . $row->test_name;
                $arr['complete'] = route('dashboard_tests_reconcile', [$row->tests_id]);
                $arr['delete'] = route('core_action', ['table' => 'tests', 'action' => 'delete', 'index' => 'tests_id', 'id' => $row->tests_id]);
                $list_array[] = $arr;
            }
            $return = $this->result_build($list_array, 'results_list');
        } else {
            $return = 'None.';
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Test Results to be Reviewed';
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => route('dashboard')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function dashboard_tests_reconcile(Request $request, $id)
    {
        $test = DB::table('tests')->where('tests_id', '=', $id)->first();
        $like_test = DB::table('tests')
            ->whereNull('pid')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->where('test_unassigned', '=', $test->test_unassigned)
            ->get();
        if ($request->isMethod('post')) {
            $pid = $request->input('pid');
            $results = [];
            $results[] = json_decode(json_encode($test), true);
            $data = [
                'pid' => $pid,
                'test_unassigned' => ''
            ];
            DB::table('tests')->where('tests_id', '=', $id)->update($data);
            $this->audit('Update');
            $provider_id = $test->test_provider_id;
            $from = $test->test_from;
            $test_type = $test->test_type;
            if ($like_test->count()) {
                foreach ($like_test as $like_item) {
                    DB::table('tests')->where('tests_id', '=', $like_item->tests_id)->update($data);
                    $this->audit('Update');
                    $results[] = json_decode(json_encode($like_item), true);
                }
            }
            $patient_row = DB::table('demographics')->where('pid', '=', $pid)->first();
            $dob_message = date("m/d/Y", strtotime($patient_row->DOB));
            $patient_name =  $patient_row->lastname . ', ' . $patient_row->firstname . ' (DOB: ' . $dob_message . ') (ID: ' . $pid . ')';
            $practice_row = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $directory = $practice_row->documents_dir . $pid;
            $file_path = $directory . '/tests_' . time() . '.pdf';
            $html = $this->page_intro('Test Results', Session::get('practice_id'));
            $html .= $this->page_results($pid, $results, $patient_name, $from);
            $this->generate_pdf($html, $file_path);
            $pages_data = [
                'documents_url' => $file_path,
                'pid' => $pid,
                'documents_type' => $test_type,
                'documents_desc' => 'Test results for ' . $patient_name,
                'documents_from' => $from,
                'documents_date' => date("Y-m-d H:i:s", time())
            ];
            if (Session::get('group_id') == '2') {
                $pages_data['documents_viewed'] = Session::get('displayname');
            }
            $documents_id = DB::table('documents')->insertGetId($pages_data);
            $this->audit('Add');
            if (Session::get('group_id') == '3') {
                $provider_row = DB::table('users')->where('id', '=', $provider_id)->first();
                $provider_name = $provider_row->firstname . " " . $provider_row->lastname . ", " . $provider_row->title . " (" . $provider_id . ")";
                $body = "Test results for " . $patient_name . "\n\n";
                foreach ($results as $results_row1) {
                    $body .= $results_row1['test_name'] . ": " . $results_row1['test_result'] . ", Units: " . $results_row1['test_units'] . ", Normal reference range: " . $results_row1['test_reference'] . ", Date: " . $results_row1['test_datetime'] . "\n";
                }
                $body .= "\n" . $from;
                $data_message = [
                    'pid' => $pid,
                    'message_to' => $provider_name,
                    'message_from' => Session::get('user_id'),
                    'subject' => 'Test results for ' . $patient_name,
                    'body' => $body,
                    'patient_name' => $patient_name,
                    'status' => 'Sent',
                    'mailbox' => $provider_id,
                    'practice_id' => Session::get('practice_id'),
                    'documents_id' => $documents_id
                ];
                DB::table('messaging')->insert($data_message);
                $this->audit('Add');
                $message = 'Tests reconciled, saved in patient chart, and provider alerted';
            }
            $message = 'Tests reconciled and saved in patient chart';
            Session::put('message_action', $message);
            return redirect()->route('dashboard_tests');
        } else {
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $test_arr = $this->array_test_flag();
            $return = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Date</th><th>Patient</th><th>Test</th><th>Result</th><th>Unit</th><th>Range</th><th>Flag</th></thead><tbody>';
            $return .= '<tr';
            $class_arr = [];
            if ($test->test_flags == "HH" || $test->test_flags == "LL" || $test->test_flags == "H" || $test->test_flags == "L") {
                $class_arr[] = 'danger';
            }
            if (!empty($class_arr)) {
                $return .= ' class="' . implode(' ', $class_arr) . '"';
            }
            $return .= '><td>' . date('Y-m-d', $this->human_to_unix($test->test_datetime)) . '</td>';
            $return .= '<td>' . $test->test_unassigned . '</td>';
            $return .= '<td>' . $test->test_name . '</td>';
            $return .= '<td>' . $test->test_result . '</td>';
            $return .= '<td>' . $test->test_units . '</td>';
            $return .= '<td>' . $test->test_reference . '</td>';
            $return .= '<td>' . $test_arr[$test->test_flags] . '</td></tr>';
            if ($like_test->count()) {
                foreach ($like_test as $like_item) {
                    $return .= '<tr';
                    $class_arr = [];
                    if ($like_item->test_flags == "HH" || $like_item->test_flags == "LL" || $like_item->test_flags == "H" || $like_item->test_flags == "L") {
                        $class_arr[] = 'danger';
                    }
                    if (!empty($class_arr)) {
                        $return .= ' class="' . implode(' ', $class_arr) . '"';
                    }
                    $return .= '><td>' . date('Y-m-d', $this->human_to_unix($like_item->test_datetime)) . '</td>';
                    $return .= '<td>' . $like_item->test_unassigned . '</td>';
                    $return .= '<td>' . $like_item->test_name . '</td>';
                    $return .= '<td>' . $like_item->test_result . '</td>';
                    $return .= '<td>' . $like_item->test_units . '</td>';
                    $return .= '<td>' . $like_item->test_reference . '</td>';
                    $return .= '<td>' . $test_arr[$like_item->test_flags] . '</td></tr>';
                }
            }
            $return .= '</tbody></table></div>';
            $data['search_patient1'] = 'pid';
            $patient_name = '';
            $pid = null;
            if (Session::has('reconcile_pid')) {
                $pid = Session::get('reconcile_pid');
                Session::forget('reconcile_pid');
                $row = DB::table('demographics')->where('pid', '=', $pid)->first();
                $dob = date('m/d/Y', strtotime($row->DOB));
                $patient_name = $row->lastname . ', ' . $row->firstname . ' (DOB: ' . $dob . ') (ID: ' . $row->pid . ')';
            }
            $intro = '<div class="form-group" id="patient_name_div"><label class="col-md-3 control-label">Patient</label><div class="col-md-8"><p class="form-control-static" id="patient_name">' . $patient_name . '</p></div></div>';
            $items[] = [
                'name' => 'pid',
                'type' => 'hidden',
                'required' => true,
                'default_value' => $pid
            ];
            $form_array = [
                'form_id' => 'reconcile_form',
                'action' => route('dashboard_tests_reconcile', [$id]),
                'items' => $items,
                'intro' => $intro,
                'save_button_label' => 'Save'
            ];
            $return .= $this->form_build($form_array);
            $data['content'] = $return;
            $data['panel_header'] = 'Reconcile' . $test->test_name;
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function dashboard_t_messages(Request $request)
    {
        $from = Session::get('displayname') . ' (' . Session::get('user_id') . ')';
        $query = DB::table('t_messages')
            ->join('demographics', 't_messages.pid', '=', 'demographics.pid')
            ->where('t_messages.t_messages_from', '=', $from)
            ->where('t_messages.t_messages_signed', '=', 'No')
            ->get();
        if ($query->count()) {
            $list_array = [];
            foreach ($query as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->t_messages_dos)) . '</b> - ' . $row->t_messages_subject . '<br>Patient: '  .$row->firstname . ' ' . $row->lastname . ' (DOB: ' . date('m/d/Y', strtotime($row->DOB)) . ')';
                $arr['edit'] = route('superquery_patient', ['chart_form', $row->pid, 't_messages', 't_messages_id', $row->t_messages_id]);
                $list_array[] = $arr;
            }
            $return = $this->result_build($list_array, 't_messages_list');
        } else {
            $return = 'None.';
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Telephone Messages to be Completed';
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => route('dashboard')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function database_export(Request $request, $track_id='')
    {
        if ($track_id !== '') {
            File::put(public_path() . '/temp/' . $track_id, '0');
            ini_set('memory_limit','196M');
            ini_set('max_execution_time', 300);
            $zip_file_name = time() . '_noshexport_' . Session::get('practice_id') . '.zip';
            $zip_file = public_path() . '/temp/' . $zip_file_name;
            $zip = new ZipArchive;
            $zip->open($zip_file, ZipArchive::CREATE);
            $documents_dir = Session::get('documents_dir');
            $database = env('DB_DATABASE') . "_copy";
            $connect = mysqli_connect('localhost', env('DB_USERNAME'), env('DB_PASSWORD'));
            if ($connect) {
                if (mysqli_select_db($connect, $database)) {
                    $sql = "DROP DATABASE " . $database;
                    mysqli_query($connect,$sql);
                }
                $sql = "CREATE DATABASE " . $database;
                if (mysqli_query($connect,$sql)) {
                    $command = "mysqldump --no-data -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . env('DB_DATABASE') . " | mysql -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . $database;
                    system($command);
                    Schema::connection('mysql2')->drop('audit');
                    Schema::connection('mysql2')->drop('ci_sessions');
                    Schema::connection('mysql2')->drop('cpt');
                    Schema::connection('mysql2')->drop('curr_associationrefset_d');
                    Schema::connection('mysql2')->drop('curr_attributevaluerefset_f');
                    Schema::connection('mysql2')->drop('curr_complexmaprefset_f');
                    Schema::connection('mysql2')->drop('curr_concept_f');
                    Schema::connection('mysql2')->drop('curr_description_f');
                    Schema::connection('mysql2')->drop('curr_langrefset_f');
                    Schema::connection('mysql2')->drop('curr_relationship_f');
                    Schema::connection('mysql2')->drop('curr_simplemaprefset_f');
                    Schema::connection('mysql2')->drop('curr_simplerefset_f');
                    Schema::connection('mysql2')->drop('curr_stated_relationship_f');
                    Schema::connection('mysql2')->drop('curr_textdefinition_f');
                    Schema::connection('mysql2')->drop('cvx');
                    Schema::connection('mysql2')->drop('extensions_log');
                    Schema::connection('mysql2')->drop('gc');
                    Schema::connection('mysql2')->drop('groups');
                    Schema::connection('mysql2')->drop('guardian_roles');
                    Schema::connection('mysql2')->drop('icd9');
                    Schema::connection('mysql2')->drop('icd10');
                    Schema::connection('mysql2')->drop('lang');
                    Schema::connection('mysql2')->drop('meds_full');
                    Schema::connection('mysql2')->drop('meds_full_package');
                    Schema::connection('mysql2')->drop('migrations');
                    Schema::connection('mysql2')->drop('npi');
                    Schema::connection('mysql2')->drop('orderslist1');
                    Schema::connection('mysql2')->drop('pos');
                    Schema::connection('mysql2')->drop('sessions');
                    Schema::connection('mysql2')->drop('snomed_procedure_imaging');
                    Schema::connection('mysql2')->drop('snomed_procedure_path');
                    Schema::connection('mysql2')->drop('supplements_list');
                    File::put(public_path() . '/temp/' . $track_id, '10');
                    $practiceinfo = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
                    $practiceinfo_data = (array) $practiceinfo;
                    $practiceinfo_data['practice_id'] = '1';
                    DB::connection('mysql2')->table('practiceinfo')->insert($practiceinfo_data);
                    if ($practiceinfo->practice_logo != '') {
                        $practice_logo_file = public_path() . '/assets/images/' . $practiceinfo->practice_logo;
                        $localPath4 = str_replace($documents_dir,'/',$practice_logo_file);
                        if (file_exists($practice_logo_file)) {
                            $zip->addFile($practice_logo_file,$localPath4);
                        }
                    }
                    $addressbook = DB::table('addressbook')->get();
                    if ($addressbook->count()) {
                        foreach ($addressbook as $addressbook_row) {
                            DB::connection('mysql2')->table('addressbook')->insert((array) $addressbook_row);
                        }
                    }
                    $calendar = DB::table('calendar')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($calendar->count()) {
                        foreach ($calendar as $calendar_row) {
                            DB::connection('mysql2')->table('calendar')->insert((array) $calendar_row);
                        }
                    }
                    DB::connection('mysql2')->table('calendar')->update(['practice_id' => '1']);
                    $cpt_relate = DB::table('cpt_relate')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($cpt_relate->count()) {
                        foreach ($cpt_relate as $cpt_relate_row) {
                            DB::connection('mysql2')->table('cpt_relate')->insert((array) $cpt_relate_row);
                        }
                    }
                    DB::connection('mysql2')->table('cpt_relate')->update(['practice_id' => '1']);
                    $pid_arr = [];
                    $demographics_relate = DB::table('demographics_relate')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($demographics_relate) {
                        foreach ($demographics_relate as $demographics_relate_row) {
                            DB::connection('mysql2')->table('demographics_relate')->insert((array) $demographics_relate_row);
                            $pid_arr[] = $demographics_relate_row->pid;
                        }
                    }
                    DB::connection('mysql2')->table('demographics_relate')->update(['practice_id' => '1']);
                    $era = DB::table('era')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($era->count()) {
                        foreach ($era as $era_row) {
                            DB::connection('mysql2')->table('era')->insert((array) $era_row);
                        }
                    }
                    DB::connection('mysql2')->table('era')->update(['practice_id' => '1']);
                    $messaging = DB::table('messaging')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($messaging->count()) {
                        foreach ($messaging as $messaging_row) {
                            DB::connection('mysql2')->table('messaging')->insert((array) $messaging_row);
                        }
                    }
                    DB::connection('mysql2')->table('messaging')->update(['practice_id' => '1']);
                    $orderslist = DB::table('orderslist')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($orderslist->count()) {
                        foreach ($orderslist as $orderslist_row) {
                            DB::connection('mysql2')->table('orderslist')->insert((array) $orderslist_row);
                        }
                    }
                    DB::connection('mysql2')->table('orderslist')->update(['practice_id' => '1']);
                    $provider_id_arr = [];
                    $providers = DB::table('providers')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($providers->count()) {
                        foreach ($providers as $providers_row) {
                            DB::connection('mysql2')->table('providers')->insert((array) $providers_row);
                            $provider_id_arr[] = $providers_row->id;
                            if ($providers_row->signature != '') {
                                $signature_file = $providers_row->signature;
                                $localPath5 = str_replace($documents_dir,'/',$signature_file);
                                if (file_exists($signature_file)) {
                                    $zip->addFile($signature_file,$localPath5);
                                }
                            }
                        }
                    }
                    DB::connection('mysql2')->table('providers')->update(['practice_id' => '1']);
                    $procedurelist = DB::table('procedurelist')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($procedurelist->count()) {
                        foreach ($procedurelist as $procedurelist_row) {
                            DB::connection('mysql2')->table('procedurelist')->insert((array) $procedurelist_row);
                        }
                    }
                    DB::connection('mysql2')->table('procedurelist')->update(['practice_id' => '1']);
                    $received = DB::table('received')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($received->count()) {
                        foreach ($received as $received_row) {
                            DB::connection('mysql2')->table('received')->insert((array) $received_row);
                            if ($received_row->filePath != '') {
                                $localPath3 = str_replace($documents_dir,'/',$scans_row->filePath);
                                if (file_exists($received_row->filePath)) {
                                    $zip->addFile($received_row->filePath,$localPath3);
                                }
                            }
                        }
                    }
                    DB::connection('mysql2')->table('received')->update(['practice_id' => '1']);
                    $scans = DB::table('scans')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($scans->count()) {
                        foreach ($scans as $scans_row) {
                            DB::connection('mysql2')->table('scans')->insert((array) $scans_row);
                            if ($scans_row->filePath != '') {
                                $localPath2 = str_replace($documents_dir,'/',$scans_row->filePath);
                                if (file_exists($scans_row->filePath)) {
                                    $zip->addFile($scans_row->filePath,$localPath2);
                                }
                            }
                        }
                    }
                    DB::connection('mysql2')->table('scans')->update(['practice_id' => '1']);
                    $job_id_arr = [];
                    $sendfax = DB::table('sendfax')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($sendfax->count()) {
                        foreach ($sendfax as $sendfax_row) {
                            DB::connection('mysql2')->table('sendfax')->insert((array) $sendfax_row);
                            $job_id_arr[] = $sendfax_row->job_id;
                        }
                    }
                    DB::connection('mysql2')->table('sendfax')->update(['practice_id' => '1']);
                    $supplement_inventory = DB::table('supplement_inventory')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($supplement_inventory->count()) {
                        foreach ($supplement_inventory as $supplement_inventory_row) {
                            DB::connection('mysql2')->table('supplement_inventory')->insert((array) $supplement_inventory_row);
                        }
                    }
                    DB::connection('mysql2')->table('supplement_inventory')->update(['practice_id' => '1']);
                    $tags_id_arr = [];
                    $tags_relate = DB::table('tags_relate')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($tags_relate->count()) {
                        foreach ($tags_relate as $tags_relate_row) {
                            DB::connection('mysql2')->table('tags_relate')->insert((array) $tags_relate_row);
                            $tags_id_arr[] = $tags_relate_row->tags_id;
                        }
                    }
                    DB::connection('mysql2')->table('tags_relate')->update(['practice_id' => '1']);
                    $templates = DB::table('templates')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($templates->count()) {
                        foreach ($templates as $templates_row) {
                            DB::connection('mysql2')->table('templates')->insert((array) $templates_row);
                        }
                    }
                    DB::connection('mysql2')->table('templates')->update(['practice_id' => '1']);
                    $users = DB::table('users')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($users->count()) {
                        foreach ($users as $users_row) {
                            DB::connection('mysql2')->table('users')->insert((array) $users_row);
                        }
                    }
                    DB::connection('mysql2')->table('users')->update(['practice_id' => '1']);
                    $vaccine_inventory = DB::table('vaccine_inventory')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($vaccine_inventory) {
                        foreach ($vaccine_inventory as $vaccine_inventory_row) {
                            DB::connection('mysql2')->table('vaccine_inventory')->insert((array) $vaccine_inventory_row);
                        }
                    }
                    DB::connection('mysql2')->table('vaccine_inventory')->update(['practice_id' => '1']);
                    $vaccine_temp = DB::table('vaccine_temp')->where('practice_id', '=', Session::get('practice_id'))->get();
                    if ($vaccine_temp->count()) {
                        foreach ($vaccine_temp as $vaccine_temp_row) {
                            DB::connection('mysql2')->table('vaccine_temp')->insert((array) $vaccine_temp_row);
                        }
                    }
                    DB::connection('mysql2')->table('vaccine_temp')->update(['practice_id' => '1']);
                    File::put(public_path() . '/temp/' . $track_id, '20');
                    if (!empty($pid_arr)) {
                        $i = 0;
                        $pid_count = count($pid_arr);
                        foreach ($pid_arr as $pid) {
                            $demographics = DB::table('demographics')->where('pid', '=', $pid)->first();
                            DB::connection('mysql2')->table('demographics')->insert((array) $demographics);
                            $alerts = DB::table('alerts')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($alerts->count()) {
                                foreach ($alerts as $alerts_row) {
                                    DB::connection('mysql2')->table('alerts')->insert((array) $alerts_row);
                                }
                                DB::connection('mysql2')->table('alerts')->update(['practice_id' => '1']);
                            }
                            $allergies = DB::table('allergies')->where('pid', '=', $pid)->get();
                            if ($allergies->count()) {
                                foreach ($allergies as $allergies_row) {
                                    DB::connection('mysql2')->table('allergies')->insert((array) $allergies_row);
                                }
                            }
                            $billing_core1 = DB::table('billing_core')->where('pid', '=', $pid)->where('eid', '=', '0')->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($billing_core1->count()) {
                                foreach ($billing_core1 as $billing_core1_row) {
                                    DB::connection('mysql2')->table('billing_core')->insert((array) $billing_core1_row);
                                }
                                DB::connection('mysql2')->table('billing_core')->update(['practice_id' => '1']);
                            }
                            $demographics_notes = DB::table('demographics_notes')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($demographics_notes->count()) {
                                foreach ($demographics_notes as $demographics_notes_row) {
                                    DB::connection('mysql2')->table('demographics_notes')->insert((array) $demographics_notes_row);
                                }
                                DB::connection('mysql2')->table('demographics_notes')->update(['practice_id' => '1']);
                            }
                            $documents = DB::table('documents')->where('pid', '=', $pid)->get();
                            if ($documents->count()) {
                                foreach ($documents as $documents_row) {
                                    DB::connection('mysql2')->table('documents')->insert((array) $documents_row);
                                }
                            }
                            $eid_arr = [];
                            $encounters = DB::table('encounters')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($encounters->count()) {
                                foreach ($encounters as $encounters_row) {
                                    DB::connection('mysql2')->table('encounters')->insert((array) $encounters_row);
                                    $eid_arr[] = $encounters_row->eid;
                                }
                                DB::connection('mysql2')->table('encounters')->update(['practice_id' => '1']);
                            }
                            $forms = DB::table('forms')->where('pid', '=', $pid)->get();
                            if ($forms->count()) {
                                foreach ($forms as $forms_row) {
                                    DB::connection('mysql2')->table('forms')->insert((array) $forms_row);
                                }
                            }
                            $hippa = DB::table('hippa')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($hippa->count()) {
                                foreach ($hippa as $hippa_row) {
                                    DB::connection('mysql2')->table('hippa')->insert((array) $hippa_row);
                                }
                                DB::connection('mysql2')->table('hippa')->update(['practice_id' => '1']);
                            }
                            $hippa_request = DB::table('hippa_request')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($hippa_request->count()) {
                                foreach ($hippa_request as $hippa_request_row) {
                                    DB::connection('mysql2')->table('hippa_request')->insert((array) $hippa_request_row);
                                }
                                DB::connection('mysql2')->table('hippa_request')->update(['practice_id' => '1']);
                            }
                            $immunizations = DB::table('immunizations')->where('pid', '=', $pid)->get();
                            if ($immunizations->count()) {
                                foreach ($immunizations as $immunizations_row) {
                                    DB::connection('mysql2')->table('immunizations')->insert((array) $immunizations_row);
                                }
                            }
                            $insurance = DB::table('insurance')->where('pid', '=', $pid)->get();
                            if ($insurance->count()) {
                                foreach ($insurance as $insurance_row) {
                                    DB::connection('mysql2')->table('insurance')->insert((array) $insurance_row);
                                }
                            }
                            $issues = DB::table('issues')->where('pid', '=', $pid)->get();
                            if ($issues->count()) {
                                foreach ($issues as $issues_row) {
                                    DB::connection('mysql2')->table('issues')->insert((array) $issues_row);
                                }
                            }
                            $mtm = DB::table('mtm')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($mtm->count()) {
                                foreach ($mtm as $mtm_row) {
                                    DB::connection('mysql2')->table('mtm')->insert((array) $mtm_row);
                                }
                                DB::connection('mysql2')->table('mtm')->update(['practice_id' => '1']);
                            }
                            $orders = DB::table('orders')->where('pid', '=', $pid)->get();
                            if ($orders->count()) {
                                foreach ($orders as $orders_row) {
                                    DB::connection('mysql2')->table('orders')->insert((array) $orders_row);
                                }
                            }
                            $rx_list = DB::table('rx_list')->where('pid', '=', $pid)->get();
                            if ($rx_list->count()) {
                                foreach ($rx_list as $rx_list_row) {
                                    DB::connection('mysql2')->table('rx_list')->insert((array) $rx_list_row);
                                }
                            }
                            $sup_list = DB::table('sup_list')->where('pid', '=', $pid)->get();
                            if ($sup_list->count()) {
                                foreach ($sup_list as $sup_list_row) {
                                    DB::connection('mysql2')->table('sup_list')->insert((array) $sup_list_row);
                                }
                            }
                            $tests = DB::table('tests')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($tests->count()) {
                                foreach ($tests as $tests_row) {
                                    DB::connection('mysql2')->table('tests')->insert((array) $tests_row);
                                }
                                DB::connection('mysql2')->table('tests')->update(['practice_id' => '1']);
                            }
                            $t_messages = DB::table('t_messages')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            if ($t_messages->count()) {
                                foreach ($t_messages as $t_messages_row) {
                                    DB::connection('mysql2')->table('t_messages')->insert((array) $t_messages_row);
                                }
                                DB::connection('mysql2')->table('t_messages')->update(['practice_id' => '1']);
                            }
                            $rootPath = realpath($documents_dir . $pid);
                            if (file_exists($rootPath)) {
                                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath), RecursiveIteratorIterator::SELF_FIRST);
                                foreach ($files as $name => $file) {
                                    if(in_array(substr($file, strrpos($file, '/')+1), ['.', '..'])) {
                                        continue;
                                    } else {
                                        if (is_dir($file) === true) {
                                            continue;
                                        } else {
                                            $filePath = $file->getRealPath();
                                            $localPath = str_replace($documents_dir,'/',$filePath);
                                            if ($filePath != '' && file_exists($filePath)) {
                                                $zip->addFile($filePath,$localPath);
                                            }
                                        }
                                    }
                                }
                            }
                            $i++;
                            $percent = round($i/$pid_count*50) + 20;
                            File::put(public_path() . '/temp/' . $track_id, $percent);
                        }
                    }
                    if (!empty($provider_id_arr)) {
                        foreach ($provider_id_arr as $provider_id) {
                            $repeat_schedule = DB::table('repeat_schedule')->where('provider_id', '=', $provider_id)->get();
                            if ($repeat_schedule->count()) {
                                foreach ($repeat_schedule as $repeat_schedule_row) {
                                    DB::connection('mysql2')->table('repeat_schedule')->insert((array) $repeat_schedule_row);
                                }
                            }
                        }
                    }
                    if (!empty($job_id_arr)) {
                        foreach ($job_id_arr as $job_id) {
                            $pages = DB::table('pages')->where('job_id', '=', $job_id)->get();
                            if ($pages->count()) {
                                foreach ($pages as $pages_row) {
                                    DB::connection('mysql2')->table('pages')->insert((array) $pages_row);
                                }
                            }
                            $recipients = DB::table('recipients')->where('job_id', '=', $job_id)->get();
                            if ($recipients->count()) {
                                foreach ($recipients as $recipients_row) {
                                    DB::connection('mysql2')->table('recipients')->insert((array) $recipients_row);
                                }
                            }
                            $rootPath1 = realpath($documents_dir . 'sentfax/' . $job_id);
                            if (file_exists($rootPath1)) {
                                $files1 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath1), RecursiveIteratorIterator::SELF_FIRST);
                                foreach ($files1 as $name1 => $file1) {
                                    if(in_array(substr($file1, strrpos($file1, '/')+1), ['.', '..'])) {
                                        continue;
                                    } else {
                                        if (is_dir($file1) === true) {
                                            continue;
                                        } else {
                                            $filePath1 = $file1->getRealPath();
                                            $localPath1 = str_replace($documents_dir,'/',$filePath1);
                                            if ($filePath1 != '' && file_exists($filePath1)) {
                                                $zip->addFile($filePath1,$localPath1);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if (!empty($tags_id_arr)) {
                        foreach ($tags_id_arr as $tags_id) {
                            $tags = DB::table('tags')->where('tags_id', '=', $tags_id)->get();
                            if ($tags->count()) {
                                foreach ($tags as $tags_row) {
                                    $tagstest = DB::connection('mysql2')->table('tags')->where('tags_id', '=', $tags_id)->first();
                                    if (!$tagstest) {
                                        DB::connection('mysql2')->table('tags')->insert((array) $tags_row);
                                    }
                                }
                            }
                        }
                    }
                    if (!empty($eid_arr)) {
                        $j = 0;
                        $eid_count = count($eid_arr);
                        foreach ($eid_arr as $eid) {
                            $assessment = DB::table('assessment')->where('eid', '=', $eid)->first();
                            if ($assessment) {
                                DB::connection('mysql2')->table('assessment')->insert((array) $assessment);
                            }
                            $billing = DB::table('billing')->where('eid', '=', $eid)->get();
                            foreach ($billing as $billing_row) {
                                DB::connection('mysql2')->table('billing')->insert((array) $billing_row);
                            }
                            $billing_core2 = DB::table('billing_core')->where('pid', '=', $pid)->where('eid', '=',  $eid)->where('practice_id', '=', Session::get('practice_id'))->get();
                            foreach ($billing_core2 as $billing_core2_row) {
                                DB::connection('mysql2')->table('billing_core')->insert((array) $billing_core2_row);
                            }
                            DB::connection('mysql2')->table('billing_core')->update(['practice_id' => '1']);
                            $hpi = DB::table('hpi')->where('eid', '=', $eid)->first();
                            if ($hpi) {
                                DB::connection('mysql2')->table('hpi')->insert((array) $hpi);
                            }
                            $image = DB::table('image')->where('eid', '=', $eid)->get();
                            if ($image->count()) {
                                foreach ($image as $image_row) {
                                    DB::connection('mysql2')->table('image')->insert((array) $image_row);
                                }
                            }
                            $labs = DB::table('labs')->where('eid', '=', $eid)->first();
                            if ($labs) {
                                DB::connection('mysql2')->table('labs')->insert((array) $labs);
                            }
                            $other_history = DB::table('other_history')->where('eid', '=', $eid)->get();
                            if ($other_history->count()) {
                                foreach ($other_history as $other_history_row) {
                                    DB::connection('mysql2')->table('other_history')->insert((array) $other_history_row);
                                }
                            }
                            $pe = DB::table('pe')->where('eid', '=', $eid)->first();
                            if ($pe) {
                                DB::connection('mysql2')->table('pe')->insert((array) $pe);
                            }
                            $plan = DB::table('plan')->where('eid', '=', $eid)->first();
                            if ($plan) {
                                DB::connection('mysql2')->table('plan')->insert((array) $plan);
                            }
                            $procedure = DB::table('procedure')->where('eid', '=', $eid)->first();
                            if ($procedure) {
                                DB::connection('mysql2')->table('procedure')->insert((array) $procedure);
                            }
                            $ros = DB::table('ros')->where('eid', '=', $eid)->first();
                            if ($ros) {
                                DB::connection('mysql2')->table('ros')->insert((array) $ros);
                            }
                            $rx = DB::table('rx')->where('eid', '=', $eid)->first();
                            if ($rx) {
                                DB::connection('mysql2')->table('rx')->insert((array) $rx);
                            }
                            $vitals = DB::table('vitals')->where('eid', '=', $eid)->first();
                            if ($vitals) {
                                DB::connection('mysql2')->table('vitals')->insert((array) $vitals);
                            }
                            $i++;
                            $percent1 = round($j/$eid_count*25) + 70;
                            File::put(public_path() . '/temp/' . $track_id, $percent);
                        }
                    }
                    $sqlfilename = time() . '_noshexport.sql';
                    $sqlfile = public_path() . '/temp/' . $sqlfilename;
                    $command = "mysqldump -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . $database . " > " . $sqlfile;
                    system($command);
                    if (!file_exists($sqlfile)) {
                        sleep(2);
                    }
                    $zip->addFile($sqlfile, $sqlfilename);
                    $mess = "Export file created successfully!";
                } else {
                    $mess = "Error creating database: " . mysqli_error($connect);
                }
            }
            mysqli_close($connect);
            $zip->close();
            Session::forget('database_export');
            $headers = [
                'Set-Cookie' => 'fileDownload=true; path=/',
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'max-age=60, must-revalidate',
                'Content-Disposition' => 'attachment; filename="' . $zip_file_name . '"'
            ];
            return response()->download($zip_file, $zip_file_name, $headers);
        } else {
            $track_id = time() . '_' . Session::get('user_id') . '_track';
            Session::put('database_export', route('database_export', [$track_id]));
            return redirect()->route('dashboard');
        }
    }

    public function database_import(Request $request)
    {
        ini_set('memory_limit','196M');
        ini_set('max_execution_time', 300);
        if ($request->isMethod('post')) {
            $file = $request->input('backup');
            $command = "mysql -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . env('DB_DATABASE') . " < " . $file;
            system($command);
            $message = "Restoring backup database successful";
            Session::put('message_action', $message);
            return redirect(Session::get('last_page'));
        } else {
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
			$dir = $practice->documents_dir;
			$files = glob($dir . "*.sql");
			arsort($files);
            $backups_arr = [];
			foreach ($files as $file) {
				$explode = explode("_", $file);
				$time = intval(str_replace(".sql","",$explode[1]));
                $backups_arr[$file] = date("Y-m-d H:i:s", $time);
			}
            $items[] = [
                'name' => 'backup',
                'label' => 'Select Backup Database to Restore',
                'type' => 'select',
                'select_items' => $backups_arr,
                'required' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'database_form',
                'action' => route('database_import'),
                'items' => $items,
                'save_button_label' => 'Backup'
            ];
            $data['content'] = $this->form_build($form_array);
            $data['saas_admin'] = 'y';
            $data['panel_header'] = trans('nosh.database_import');
            $data['document_upload'] = route('database_import');
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function database_import_cloud(Request $request)
    {
        ini_set('memory_limit','196M');
        ini_set('max_execution_time', 300);
        if ($request->isMethod('post')) {
            $file = $request->file('file_input');
            $directory = public_path() . '/temp';
            $new_name = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($directory,$file->getClientOriginalName());
            $zip = new ZipArchive;
            $open = $zip->open($directory . '/' . $file->getClientOriginalName());
            if ($open === TRUE) {
                $sqlsearch = glob($directory . '/*_noshexport.sql');
                if (! empty($sqlsearch)) {
                    foreach ($sqlsearch as $sqlfile) {
                        $command = "mysql -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . env('DB_DATABASE') . " < " . $sqlfile;
                        system($command);
                        unlink($sqlfile);
                    }
                    $zip->extractTo(Session::get('documents_dir'));
                    $zip->close();
                    unlink($directory . '/' . $file->getClientOriginalName());
                    $message = "Upload and importing NOSH export file successful!";
                } else {
                    unlink($directory . '/' . $file->getClientOriginalName());
                    $message = "Error - This is not a proper ZIP file.  Missing SQL file";
                }
            } else {
                $message = 'Error - Unable to open ZIP file.';
            }
            Session::put('message_action', $message);
            return redirect(Session::get('last_page'));
        } else {
            $data['panel_header'] = trans('nosh.database_import_cloud');
            $data['document_upload'] = route('database_import_cloud');
            $type_arr = ['zip'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['assets_js'] = $this->assets_js('document_upload');
            $data['assets_css'] = $this->assets_css('document_upload');
            return view('document_upload', $data);
        }
    }

    public function database_import_file(Request $request)
    {
        ini_set('memory_limit','196M');
        ini_set('max_execution_time', 300);
        if ($request->isMethod('post')) {
            $file = $request->file('file_input');
            $directory = public_path() . '/temp';
            $file->move($directory, $file->getClientOriginalName());
            $new_file = $directory . '/' . $file->getClientOriginalName();
            $command = "mysql -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . env('DB_DATABASE') . " < " . $new_file;
            system($command);
            unlink($new_file);
            $message = "Restoring backup database successful";
            Session::put('message_action', $message);
            return redirect(Session::get('last_page'));
        } else {
            $data['saas_admin'] = 'y';
            $data['panel_header'] = trans('nosh.database_import_file');
            $data['document_upload'] = route('database_import_file');
            $type_arr = ['sql'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['assets_js'] = $this->assets_js('document_upload');
            $data['assets_css'] = $this->assets_css('document_upload');
            return view('document_upload', $data);
        }
    }

    public function download_ccda_entire(Request $request, $track_id='')
    {
        if ($track_id !== '') {
            File::put(public_path() . '/temp/' . $track_id, '0');
            ini_set('memory_limit','196M');
            ini_set('max_execution_time', 300);
            $practice_id = Session::get('practice_id');
            $query = DB::table('demographics_relate')->where('practice_id', '=', $practice_id)->get();
            $zip_file_name = time() . '_ccda_' . $practice_id . '.zip';
            $zip_file = public_path() . '/temp/' . $zip_file_name;
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                exit("Cannot open <$zip_file>\n");
            }
            $files_array = [];
            $i = 0;
            $count = $query->count();
            File::put(public_path() . '/temp/' . $track_id, '1');
            if ($query->count()) {
                foreach ($query as $row) {
                    $filename = time() . '_ccda_' . $row->pid . ".xml";
                    $file = public_path() . '/temp/' . $filename;
                    $query1 = DB::table('demographics')->where('pid', '=', $row->pid)->first();
                    if ($query1) {
                        $ccda = $this->generate_ccda('',$row->pid);
                        File::put($file, $ccda);
                        $files_array[$i]['file'] = $file;
                        $files_array[$i]['filename'] = $filename;
                        $i++;
                    }
                    $percent = round($i/$count*100);
                    File::put(public_path() . '/temp/' . $track_id, $percent);
                }
            }
            foreach ($files_array as $ccda1) {
                $zip->addFile($ccda1['file'], $ccda1['filename']);
            }
            $zip->close();
            while(!file_exists($zip_file)) {
                sleep(2);
            }
            Session::forget('download_ccda_entire');
            $headers = [
                'Set-Cookie' => 'fileDownload=true; path=/',
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'max-age=60, must-revalidate',
                'Content-Disposition' => 'attachment; filename="' . $zip_file_name . '"'
            ];
            return response()->download($zip_file, $zip_file_name, $headers);
        } else {
            $track_id = time() . '_' . Session::get('user_id') . '_track';
            Session::put('download_ccda_entire', route('download_ccda_entire', [$track_id]));
            return redirect(Session::get('last_page'));
        }
    }

    public function download_charts_entire(Request $request, $track_id='')
    {
        if ($track_id !== '') {
            File::put(public_path() . '/temp/' . $track_id, '0');
            ini_set('memory_limit','196M');
            ini_set('max_execution_time', 300);
            $practice_id = Session::get('practice_id');
            $query = DB::table('demographics_relate')->where('practice_id', '=', $practice_id)->get();
            $total = $query->count();
            $files_array = [];
            $i = 0;
            $data = [];
            $zip_file_name = time() . '_charts_' . $practice_id . '.zip';
            $zip_file = public_path() . '/temp/' . $zip_file_name;
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                exit("Cannot open <$zip_file>\n");
            }
            File::put(public_path() . '/temp/' . $track_id, '1');
            if ($query->count()) {
                foreach ($query as $row) {
                    $file = $this->print_chart('', $row->pid, 'all');
                    $files_array[$i]['file'] = $file;
                    $files_array[$i]['filename'] = str_replace(public_path() . '/temp/', '', $file);
                    $i++;
                    $percent = round($i/$total*100);
                    File::put(public_path() . '/temp/' . $track_id, $percent);
                }
            }
            foreach ($files_array as $chart1) {
                $zip->addFile($chart1['file'], $chart1['filename']);
            }
            $zip->close();
            while(!file_exists($zip_file)) {
                sleep(2);
            }
            Session::forget('download_charts_entire');
            $headers = [
                'Set-Cookie' => 'fileDownload=true; path=/',
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'max-age=60, must-revalidate',
                'Content-Disposition' => 'attachment; filename="' . $zip_file_name . '"'
            ];
            return response()->download($zip_file, $zip_file_name, $headers);
        } else {
            $track_id = time() . '_' . Session::get('user_id') . '_track';
            Session::put('download_charts_entire', route('download_charts_entire', [$track_id]));
            return redirect(Session::get('last_page'));
        }
    }

    public function download_csv_demographics(Request $request, $track_id='')
    {
        if ($track_id !== '') {
            File::put(public_path() . '/temp/' . $track_id, '0');
            ini_set('memory_limit','196M');
            ini_set('max_execution_time', 300);
            $practice_id = Session::get('practice_id');
            $query = DB::table('demographics_relate')->where('practice_id', '=', $practice_id)->get();
            $total = count($query);
            $i=0;
            $csv = "Last Name;First Name;Gender;Date of Birth;Address;City;State;Zip;Home Phone;Work Phone;Cell Phone";
            if ($query->count()) {
                foreach ($query as $row) {
                    $row1 = DB::table('demographics')
                        ->select('lastname', 'firstname', 'sex', 'DOB', 'address', 'city', 'state', 'zip', 'phone_home', 'phone_work', 'phone_cell')
                        ->where('pid', '=', $row->pid)
                        ->first();
                    $csv .= "\n";
                    $array = json_decode(json_encode($row1), true);
                    $csv .= implode(";", $array);
                    $i++;
                    $percent = round($i/$total*100);
                    File::put(public_path() . '/temp/' . $track_id, $percent);
                }
            } else {
                File::put(public_path() . '/temp/' . $track_id, '100');
            }
            $file_name = time() . '_' . Session::get('user_id') . '_demographics.csv';
            $file_path = public_path() . '/temp/' . $file_name;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            File::put($file_path, $csv);
            Session::forget('download_csv_demographics');
            $headers = [
                'Set-Cookie' => 'fileDownload=true; path=/',
                'Content-Type' => 'text/csv',
                'Cache-Control' => 'max-age=60, must-revalidate',
                'Content-Disposition' => 'attachment; filename="' . $file_name . '"'
            ];
            return response()->download($file_path, $file_name, $headers);
        } else {
            $track_id = time() . '_' . Session::get('user_id') . '_track';
            Session::put('download_csv_demographics', route('download_csv_demographics', [$track_id]));
            return redirect(Session::get('last_page'));
        }
    }

    public function download_now(Request $request)
    {
        $file_path = Session::get('download_now');
        Session::forget('download_now');
        $file_name = str_replace(public_path() . '/temp/', '', $file_path);
        $headers = [
            'Set-Cookie' => 'fileDownload=true; path=/',
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'max-age=60, must-revalidate',
            'Content-Disposition' => 'attachment; filename="' . $file_name . '"'
        ];
        return response()->download($file_path, $file_name, $headers);
    }

    public function event_encounter(Request $request, $appt_id)
    {
        $query = DB::table('encounters')->where('appt_id', '=', $appt_id)->first();
        $query1 = DB::table('schedule')->where('appt_id', '=', $appt_id)->first();
        if ($query) {
            $edit = $this->access_level('2');
            if ($edit == true && $query->encounter_signed == 'No') {
                return redirect()->route('superquery_patient', ['encounter', $query1->pid, $query->eid]);
            } else {
                return redirect()->route('superquery_patient', ['encounter_view', $query1->pid, $query->eid]);
            }
        } else {
            $user_query = DB::table('users')->where('id', '=', $query1->provider_id)->first();
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $data = [
                'pid' => $query1->pid,
                'appt_id' => $appt_id,
                'encounter_age' => Session::get('age'),
                'encounter_type' => $query1->visit_type,
                'encounter_signed' => 'No',
                'addendum' => 'n',
                'user_id' => $query1->user_id,
                'practice_id' => Session::get('practice_id'),
                'encounter_location' => $practice->default_pos_id,
                'encounter_template' => $practice->encounter_template,
                'encounter_cc' => $query1->reason,
                'encounter_role' => 'Primary Care Provider',
                'encounter_provider' => $user_query->displayname,
                'encounter_DOS' => date('Y-m-d H:i:s', $query1->start),
                'encounter_condition_work' => 'No',
                'encounter_condition_auto' => 'No'
            ];
            $eid = DB::table('encounters')->insertGetId($data);
            $this->audit('Add');
            // $this->api_data('add', 'encounters', 'eid', $eid);
            $data2['status'] = 'Attended';
            DB::table('schedule')->where('appt_id', '=', $appt_id)->update($data2);
            $this->audit('Update');
            $data3['addendum_eid'] = $eid;
            DB::table('encounters')->where('eid', '=', $eid)->update($data3);
            $this->audit('Update');
            // $this->api_data('update', 'encounters', 'eid', $eid);
            Session::put('message_action', 'Encounter created.');
            return redirect()->route('superquery_patient', ['encounter', $query1->pid, $eid]);
        }
    }

    public function fax_action(Request $request, $action, $id, $pid, $subtype='')
    {
        ini_set('memory_limit', '196M');
        $fix = [];
        $need_address = false;
        $action_arr = [
            'rx_list' => [
                'function' => 'print_medication',
                'index' => 'rxl_id',
                'message' => 'Prescription faxed',
                'title' => 'Prescription/Refill Authorization'
            ],
            'orders' => [
                'function' => 'print_orders',
                'index' => 'orders_id',
                'message' => 'Order faxed',
                'title' => 'Order'
            ],
            'hippa' => [
                'function' => 'print_chart',
                'index' => 'hippa_id',
                'message' => 'Patient chart faxed',
                'title' => 'Patient Chart'
            ],
            'hippa_request' => [
                'function' => 'print_chart_request_action',
                'index' => 'hippa_request_id',
                'message' => 'Records release request faxed',
                'title' => 'Records Relase Request'
            ]
        ];
        if ($subtype !== '') {
            $file_path = $this->{$action_arr[$action]['function']}($id, $pid, $subtype);
        } else {
            $file_path = $this->{$action_arr[$action]['function']}($id, $pid, false);
        }
        $query = DB::table($action)->where($action_arr[$action]['index'], '=', $id)->first();
        $address_id = $query->address_id;
        $row2 = DB::table('addressbook')->where('address_id', '=', $address_id)->first();
        if ($action == 'orders') {
            if ($query->orders_labs !== '') {
                $action_arr[$action]['title'] = "Laboratory Order";
            }
            if ($query->orders_radiology !== '') {
                $action_arr[$action]['title'] = "Imaging Order";
            }
            if ($print_orders->orders_cp !== '') {
                $action_arr[$action]['title'] = "Cardiopulmonary Order";
            }
            if ($print_orders->orders_referrals !== '') {
                $action_arr[$action]['title'] = "Referral Order";
            }
        }
        if ($row2) {
            if ($row2->fax !== '' && $row2->fax !== null) {
                $result_id = $this->fax_document($pid, $action_arr[$action]['title'], 'yes', $file_path, '', $row2->fax, $row2->displayname, '', 'yes');
                $message_arr[] = $action_arr[$action]['message'];
            } else {
                $need_address = true;
            }
        } else {
            $need_address = true;
        }
        if ($need_address == true) {
            $new_arr = [];
            if (Session::has('fax_queue')) {
                $new_arr = Session::get('fax_queue');
            }
            $new_arr1 = [
                'function' => $action_arr[$action]['function'],
                'id' => $id,
                'pid' => $pid,
                'address_id' => $address_id
            ];
            if ($subtype !== '') {
                $new_arr1['type'] = $subtype;
            }
            $new_arr[] = $new_arr1;
            $fix[] = $address_id;
            Session::put('fax_queue', $new_arr);
            Session::put('fax_queue_count', count($new_arr));
            $message_arr[] = 'Error - Recipient in your fax does not have a fax number.';
        }
        $message = implode('<br>', $message_arr);
        Session::put('message_action', $message);
        if (! empty($fix)) {
            $fix_address_id = $fix[0];
            Session::put('addressbook_last_page', $request->fullUrl());
            return redirect()->route('core_form', ['addressbook', 'address_id', $fix_address_id, 'faxonly']);
        } else {
            return redirect(Session::get('last_page'));
        }
    }

    public function fax_queue(Request $request, $action, $id, $pid, $subtype='')
    {
        ini_set('memory_limit', '196M');
        $arr = [];
        if (Session::has('fax_queue')) {
            $arr = Session::get('fax_queue');
        }
        if ($action == 'run') {
            Session::forget('fax_queue');
            Session::forget('fax_queue_count');
            // Generate individual pdfs
            $group_arr = [];
            $fix = [];
            $med_count = 0;
            if ($arr) {
                foreach ($arr as $item) {
                    if (isset($item['type'])) {
                        if ($item['function'] == 'print_medication') {
                            if ($item['type'] == 'single') {
                                $group_arr[$item['address_id']]['pdf_arr'][] = $this->{$item['function']}($item['id'], $item['pid'], $item['type'], false);
                            } else {
                                if (Session::has('print_medication_combined')) {
                                    $print_medication_combined_arr = Session::get('print_medication_combined');
                                } else {
                                    $print_medication_combined_arr = [];
                                }
                                $print_medication_combined_arr[] = [
                                    'id' => $item['id'],
                                    'pid' => $item['pid']
                                ];
                                Session::put('print_medication_combined', $print_medication_combined_arr);
                                $med_count++;
                            }
                        } else {
                            $group_arr[$item['address_id']]['pdf_arr'][] = $this->{$item['function']}($item['id'], $item['pid'], $item['type']);
                        }
                    } else {
                        $group_arr[$item['address_id']]['pdf_arr'][] = $this->{$item['function']}($item['id'], $item['pid'], false);
                    }
                    $group_arr[$item['address_id']]['pdf_arr'][] = $this->{$item['function']}($item['id'], $item['pid'], false);
                    $group_arr[$item['address_id']]['id'] = $item['id'];
                    $group_arr[$item['address_id']]['pid'] = $item['pid'];
                    $group_arr[$item['address_id']]['function'] = $item['function'];
                    if ($item['function'] == 'print_medication') {
                        $group_arr[$item['address_id']]['title'] = 'Prescription/Refill Authorization';
                        $group_arr[$item['address_id']]['message'] = 'Prescription faxed';

                    }
                    if ($item['function'] == 'print_orders') {
                        $print_orders = DB::table('orders')->where('orders_id', '=', $item['id'])->first();
                        if ($print_orders->orders_labs !== '') {
                            $group_arr[$item['address_id']]['title'] = "Laboratory Order";
                        }
                        if ($print_orders->orders_radiology !== '') {
                            $group_arr[$item['address_id']]['title'] = "Imaging Order";
                        }
                        if ($print_orders->orders_cp !== '') {
                            $group_arr[$item['address_id']]['title'] = "Cardiopulmonary Order";
                        }
                        if ($print_orders->orders_referrals !== '') {
                            $group_arr[$item['address_id']]['title'] = "Referral Order";
                        }
                        $group_arr[$item['address_id']]['message'] = 'Order faxed';
                    }
                    if ($item['function'] == 'print_chart') {
                        $group_arr[$item['address_id']]['title'] = "Patient Chart";
                        $group_arr[$item['address_id']]['message'] = 'Patient chart faxed';
                    }
                    if ($item['function'] == 'print_chart_request') {
                        $group_arr[$item['address_id']]['title'] = "Record Request";
                        $group_arr[$item['address_id']]['message'] = 'Record request faxed';
                    }
                }
                if ($med_count > 0) {
                    $group_arr[$item['address_id']]['pdf_arr'][] = $this->print_medication_combined(false);
                }
            } else {
                return back();
            }
            $message_arr = [];
            foreach ($group_arr as $address_id => $row) {
                // Merge pdfs into 1
                $need_address = false;
                $pdf = new Merger(false);
                foreach ($row['pdf_arr'] as $pdf_item) {
                    $pdf->addFromFile($pdf_item, 'all');
                }
                $file_path = public_path() . '/temp/' . time() . '_fax_queue.pdf';
                $pdf->merge();
                $pdf->save($file_path);
                $row2 = DB::table('addressbook')->where('address_id', '=', $address_id)->first();
                if ($row2) {
                    if ($row2->fax !== '' && $row2->fax !== null) {
                        $result_id = $this->fax_document($row['pid'], $row['title'], 'yes', $file_path, '', $row2->fax, $row2->displayname, '', 'yes');
                        $message_arr[] = $row['message'];
                    } else {
                        $need_address = true;
                    }
                } else {
                    $need_address = true;
                }
                if ($need_address == true) {
                    $new_arr = [];
                    if (Session::has('fax_queue')) {
                        $new_arr = Session::get('fax_queue');
                    }
                    $new_arr[] = [
                        'function' => $row['function'],
                        'id' => $row['id'],
                        'pid' => $row['pid'],
                        'address_id' => $address_id
                    ];
                    $fix[] = $address_id;
                    Session::put('fax_queue', $new_arr);
                    Session::put('fax_queue_count', count($new_arr));
                    $message_arr[] = 'Error - Recipient in your fax queue does not have a fax number.';
                }
            }
            $message = implode('<br>', $message_arr);
            Session::put('message_action', $message);
            if (! empty($fix)) {
                $fix_address_id = $fix[0];
                Session::put('addressbook_last_page', $request->fullUrl());
                return redirect()->route('core_form', ['addressbook', 'address_id', $fix_address_id, 'faxonly']);
            } else {
                return redirect(Session::get('last_page'));
            }
        } else {
            $action_arr = [
                'rx_list' => [
                    'url' => [
                        'function' => 'print_medication',
                        'id' => $id,
                        'pid' => $pid,
                        'type' => $subtype
                    ],
                    'index' => 'rxl_id',
                    'message' => 'Prescription sent to fax queue'
                ],
                'orders' => [
                    'url' => [
                        'function' => 'print_orders',
                        'id' => $id,
                        'pid' => $pid
                    ],
                    'index' => 'orders_id',
                    'message' => 'Order sent to fax queue'
                ],
                'hippa' => [
                    'url' => [
                        'function' => 'print_chart',
                        'id' => $id,
                        'pid' => $pid,
                        'type' => $subtype
                    ],
                    'index' => 'hippa_id',
                    'message' => 'Chart sent to fax queue'
                ],
                'hippa_request' => [
                    'url' => [
                        'function' => 'print_chart_request',
                        'id' => $id,
                        'pid' => $pid
                    ],
                    'index' => 'hippa_request_id',
                    'message' => 'Records request sent to fax queue'
                ]
            ];
            $query = DB::table($action)->where($action_arr[$action]['index'], '=', $id)->first();
            $action_arr[$action]['url']['address_id'] = $query->address_id;
            $action_url = $action_arr[$action]['url'];
            $arr[] = $action_url;
            Session::put('fax_queue', $arr);
            Session::put('fax_queue_count', count($arr));
            $message_action = '';
            if (Session::has('message_action')) {
                $message_action = Session::get('message_action');
            }
            $message_action .= '<br>' . $action_arr[$action]['message'];
            Session::put('message_action', $message_action);
            return redirect(Session::get('last_page'));
        }
    }

    public function financial(Request $request, $type='queue')
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $return = '';
        $type_arr = [
            'queue' => ['Bills to Submit', 'fa-list'],
            'processed' => ['Processed Bills', 'fa-check'],
            'outstanding' => ['Outstanding Balances', 'fa-exclamation'],
            'era' => ['ERA 835', 'fa-refresh'],
            'monthly_report' => ['Monthly Financial Report', 'fa-table'],
            'yearly_report' => ['Yearly Financial Report', 'fa-table'],
            'query_payment' => ['Custom Report by Payment Type', 'fa-question'],
            'query_cpt' => ['Custom Report by Procedure Code', 'fa-question']
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('financial', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = [];
        $signed_arr = [
            'No' => 'Draft',
            'Yes' => 'Signed'
        ];
        $batch_arr = [
            'No' => 'None',
            'Pend' => 'Print Image',
            'HCFA' => 'HCFA-1500'
        ];
        if ($type == 'queue') {
            $query = DB::table('encounters')
                ->join('demographics', 'encounters.pid', '=', 'demographics.pid')
                ->where('encounters.bill_submitted', '!=', 'Done')
                ->where('encounters.addendum', '=', 'n')
                ->where('encounters.practice_id', '=', Session::get('practice_id'))
                ->orderBy('encounters.encounter_DOS', 'desc')
                ->get();
            if ($query->count()) {
                foreach ($query as $row) {
                    $action = '<a href="' . route('financial_queue', ['Pend', $row->eid]) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Add to Print Image Queue"><i class="fa fa-plus-square fa-lg"></i></a>';
                    $action .= '<a href="' . route('financial_queue', ['HCFA', $row->eid]) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Add to HCFA-1500 Queue"><i class="fa fa-plus-circle fa-lg"></i></a>';
                    $action .= '<a href="' . route('printimage_single', [$row->eid]) . '" class="btn fa-btn nosh-no-load" role="button" data-toggle="tooltip" title="Print Image"><i class="fa fa-file-image-o fa-lg"></i></a>';
                    $action .= '<a href="' . route('generate_hcfa', [$row->eid]) . '" class="btn fa-btn nosh-no-load" role="button" data-toggle="tooltip" title="Print HCFA-1500"><i class="fa fa-file-text-o fa-lg"></i></a>';
                    $result[] = [
                        'date' => date('Y-m-d', $this->human_to_unix($row->encounter_DOS)),
                        'signed' => $signed_arr[$row->encounter_signed],
                        'batch_type' => $batch_arr[$row->bill_submitted],
                        'lastname' => $row->lastname,
                        'firstname' => $row->firstname,
                        'encounter_cc' => $row->encounter_cc,
                        'action' => $action,
                        'click' => route('encounter_billing', [$row->eid])
                    ];
                }
            }
            $head_arr = [
                'Date of Service' => 'date',
                'Status' => 'signed',
                'Batch Type' => 'batch_type',
                'Last Name' => 'lastname',
                'First Name' => 'firstname',
                'Chief Complaint' => 'encounter_cc',
                'Action' => 'action'
            ];
            $batch = 0;
            $batch_query1 = DB::table('encounters')
                ->where('bill_submitted', '=', 'Pend')
                ->where('addendum', '=', 'n')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->get();
            if ($batch_query1->count()) {
                $batch++;
            }
            $batch_query2 = DB::table('encounters')
                ->where('bill_submitted', '=', 'HCFA')
                ->where('addendum', '=', 'n')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->get();
            if ($batch_query2->count()) {
                $batch++;
            }
            if ($batch > 0) {
                // if ($type == 'misc') {
                    $dropdown_array1 = [
                        'items_button_icon' => 'fa-print'
                    ];
                    $items1 = [];
                    if ($batch_query1->count()) {
                        $items1[] = [
                            'type' => 'item',
                            'label' => 'Print Print Image from Queue',
                            'icon' => 'fa-file-image-o',
                            'url' => route('print_batch', ['Pend'])
                        ];
                    }
                    if ($batch_query2->count()) {
                        $items1[] = [
                            'type' => 'item',
                            'label' => 'Print HCFA-1500 from Queue',
                            'icon' => 'fa-file-text-o',
                            'url' => route('print_batch', ['HCFA'])
                        ];
                    }
                    $dropdown_array1['items'] = $items1;
                    $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
                // }
            }
        }
        if ($type == 'processed') {
            $query = DB::table('encounters')
                ->join('demographics', 'encounters.pid', '=', 'demographics.pid')
                ->where('encounters.bill_submitted', '=', 'Done')
                ->where('encounters.addendum', '=', 'n')
                ->where('encounters.practice_id', '=', Session::get('practice_id'))
                ->orderBy('encounters.encounter_DOS', 'desc')
                ->get();
            if ($query->count()) {
                foreach ($query as $row) {
                    $query1 = DB::table('billing_core')->where('eid', '=', $row->eid)->get();
                    $balance = 0;
                    $charges = 0;
                    if ($query1->count()) {
                        $charge = 0;
                        $payment = 0;
                        foreach ($query1 as $row1) {
                            $charge += $row1->cpt_charge * $row1->unit;
                            $payment += $row1->payment;
                        }
                        $balance = $charge - $payment;
                        $charges = $charge;
                    }
                    $action = '<a href="' . route('financial_patient', ['billing_payment_history', $row->pid, $row->eid]) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Payment History"><i class="fa fa-history fa-lg"></i></a>';
                    $action .= '<a href="' . route('financial_patient', ['billing_make_payment', $row->pid, $row->eid]) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Make Payment"><i class="fa fa-usd fa-lg"></i></a>';
                    $action .= '<a href="' . route('financial_resubmit', [$row->eid]) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Resubmit Bill"><i class="fa fa-repeat fa-lg"></i></a>';
                    $result[] = [
                        'date' => date('Y-m-d', $this->human_to_unix($row->encounter_DOS)) ,
                        'lastname' => $row->lastname,
                        'firstname' => $row->firstname,
                        'encounter_cc' => $row->encounter_cc,
                        'charges' => $charges,
                        'balance' => $balance,
                        'action' => $action,
                        'click' => route('financial_patient', ['billing_payment_history', $row->pid, $row->eid])
                    ];
                }
            }
            $head_arr = [
                'Date of Service' => 'date',
                'Last Name' => 'lastname',
                'First Name' => 'firstname',
                'Chief Complaint' => 'encounter_cc',
                'Charges' => 'charges',
                'Total Balance' => 'balance',
                'Action' => 'action'
            ];
        }
        if ($type == 'outstanding') {
            $query = DB::table('demographics')
                ->join('demographics_relate', 'demographics.pid', '=', 'demographics_relate.pid')
                ->where('demographics_relate.practice_id', '=', Session::get('practice_id'))
                ->get();
            $count = 0;
            $full_array = [];
            if ($query->count()) {
                foreach ($query as $row) {
                    $notes = DB::table('demographics_notes')->where('pid', '=', $row->pid)->where('practice_id', '=', Session::get('practice_id'))->first();
                    $query_a = DB::table('encounters')->where('pid', '=', $row->pid)->where('addendum', '=', 'n')->get();
                    $balance = 0;
                    if ($query_a->count()) {
                        foreach ($query_a as $row_a) {
                            $query_b = DB::table('billing_core')->where('eid', '=', $row_a->eid)->get();
                            if ($query_b) {
                                $charge = 0;
                                $payment = 0;
                                foreach ($query_b as $row_b) {
                                    $charge += $row_b->cpt_charge * $row_b->unit;
                                    $payment += $row_b->payment;
                                }
                                $balance += $charge - $payment;
                            }
                        }
                    }
                    $query_c = DB::table('billing_core')->where('pid', '=', $row->pid)->where('eid', '=', '0')->where('payment', '=', '0')->get();
                    $balance1 = 0;
                    if ($query_c->count()) {
                        foreach ($query_c as $row_c) {
                            $query_d = DB::table('billing_core')->where('other_billing_id', '=', $row_c->other_billing_id)->get();
                            if ($query_d) {
                                $charge1 = $row_c->cpt_charge * $row_c->unit;
                                $payment1 = 0;
                                foreach ($query_d as $row_d) {
                                    $payment1 += $row_d->payment;
                                }
                                $balance1 += $charge1 - $payment1;
                            }
                        }
                    }
                    $totalbalance = $balance + $balance1;
                    // if ($totalbalance >= 0.01 || $notes->billing_notes != '') {
                    if ($totalbalance >= 0.01) {
                        $count++;
                        $result[] = [
                            'pid' => $row->pid,
                            'lastname' => $row->lastname,
                            'firstname' => $row->firstname,
                            'balance' => $totalbalance,
                            'billing_notes' => $notes->billing_notes,
                            'click' => route('billing_list', ['encounters', $row->pid])
                        ];
                    }
                }
            }
            $head_arr = [
                'ID' => 'pid',
                'Last Name' => 'lastname',
                'First Name' => 'firstname',
                'Balance' => 'balance',
                // 'Notes' => 'billing_notes'
            ];
        }
        if ($type == 'era') {
            $query = DB::table('era')->where('practice_id', '=', Session::get('practice_id'))->orderBy('era_date', 'desc')->get();
            if ($query->count()) {
                foreach ($query as $row) {
                    $result[] = [
                        'id' => $row->era_id,
                        'date' => date('Y-m-d', $this->human_to_unix($row->era_date)),
                        'click' => route('financial_era', [$row->era_id])
                    ];
                }
            }
            $head_arr = [
                'ID' => 'id',
                'Date' => 'date'
            ];
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => 'Upload ERA 835',
                'icon' => 'fa-upload',
                'url' => route('financial_upload_era')
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if ($type == 'monthly_report') {
            $query = DB::table('encounters')
                ->select(DB::raw("DATE_FORMAT(encounter_DOS, '%Y-%m') as month, COUNT(*) as patients_seen"))
                ->where('addendum', '=', 'n')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->groupBy('month')
                ->orderby('month', 'desc')
                ->get();
            if ($query->count()) {
                foreach ($query as $row_obj) {
                    $row['patients_seen'] = $row_obj->patients_seen;
                    $row['month'] = $row_obj->month;
                    $month_piece = explode("-", $row_obj->month);
                    $year = $month_piece[0];
                    $month = $month_piece[1];
                    $row['total_billed'] = 0;
                    $row['total_payments'] = 0;
                    $row['dnka'] = 0;
                    $row['lmc'] = 0;
                    $query1a = DB::table('encounters')
                        ->select('eid')
                        ->where(DB::raw('YEAR(encounter_DOS)'), '=', $year)
                        ->where(DB::raw('MONTH(encounter_DOS)'), '=', $month)
                        ->where('addendum', '=', 'n')
                        ->where('practice_id', '=', Session::get('practice_id'))
                        ->get();
                    foreach ($query1a as $row1) {
                        $query2 = DB::table('billing_core')->where('eid', '=', $row1->eid)->get();
                        if ($query2) {
                            $charge = 0;
                            $payment = 0;
                            foreach ($query2 as $row2) {
                                if ($row2->payment_type != "Write-Off") {
                                    $charge += $row2->cpt_charge * $row2->unit;
                                    $payment += $row2->payment;
                                }
                            }
                            $row['total_billed'] += $charge;
                            $row['total_payments'] += $payment;
                        }
                    }
                    $query1b = DB::table('schedule')
                        ->join('providers', 'providers.id', '=', 'schedule.provider_id')
                        ->where(DB::raw("FROM_UNIXTIME(schedule.end, '%Y')"), '=', $year)
                        ->where(DB::raw("FROM_UNIXTIME(schedule.end, '%m')"), '=', $month)
                        ->where('providers.practice_id', '=', Session::get('practice_id'))
                        ->get();
                    foreach ($query1b as $row3) {
                        if ($row3->status == "DNKA") {
                            $row['dnka'] += 1;
                        }
                        if ($row3->status == "LMC") {
                            $row['lmc'] += 1;
                        }
                    }
                    $row['click'] = route('financial_insurance', [$row['month']]);
                    $result[] = $row;
                }
            }
            $head_arr = [
                'Month' => 'month',
                'Patients Seen' => 'patients_seen',
                'Total Billed' => 'total_billed',
                'Total Payments' => 'total_payments',
                'DNKA' => 'dnka',
                'LMC' => 'lmc'
            ];
        }
        if ($type == 'yearly_report') {
            $query = DB::table('encounters')
                ->select(DB::raw("DATE_FORMAT(encounter_DOS, '%Y') as year, COUNT(*) as patients_seen"))
                ->where('addendum', '=', 'n')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get();
            if ($query->count()) {
                foreach ($query as $row_obj) {
                    $row['patients_seen'] = $row_obj->patients_seen;
                    $row['year'] = $row_obj->year;
                    $row['total_billed'] = 0;
                    $row['total_payments'] = 0;
                    $row['dnka'] = 0;
                    $row['lmc'] = 0;
                    $query1a = DB::table('encounters')
                        ->select('eid')
                        ->where(DB::raw('YEAR(encounter_DOS)'), '=', $row['year'])
                        ->where('addendum', '=', 'n')
                        ->where('practice_id', '=', Session::get('practice_id'))
                        ->get();
                    foreach ($query1a as $row1) {
                        $query2 = DB::table('billing_core')->where('eid', '=', $row1->eid)->get();
                        if ($query2) {
                            $charge = 0;
                            $payment = 0;
                            foreach ($query2 as $row2) {
                                if ($row2->payment_type != "Write-Off") {
                                    $charge += $row2->cpt_charge * $row2->unit;
                                    $payment += $row2->payment;
                                }
                            }
                            $row['total_billed'] += $charge;
                            $row['total_payments'] += $payment;
                        }
                    }
                    $query1b = DB::table('schedule')
                        ->join('providers', 'providers.id', '=', 'schedule.provider_id')
                        ->where(DB::raw("FROM_UNIXTIME(schedule.end, '%Y')"), '=', $row['year'])
                        ->where('providers.practice_id', '=', Session::get('practice_id'))
                        ->get();
                    foreach ($query1b as $row3) {
                        if ($row3->status == "DNKA") {
                            $row['dnka'] += 1;
                        }
                        if ($row3->status == "LMC") {
                            $row['lmc'] += 1;
                        }
                    }
                    $row['click'] = route('financial_insurance', [$row['year']]);
                    $result[] = $row;
                }
            }
            $head_arr = [
                'Year' => 'year',
                'Patients Seen' => 'patients_seen',
                'Total Billed' => 'total_billed',
                'Total Payments' => 'total_payments',
                'DNKA' => 'dnka',
                'LMC' => 'lmc'
            ];
        }
        if ($type == 'query_payment' || $type == 'query_cpt') {
            if ($type == 'query_payment') {
                $items3[] = [
                    'name' => 'type',
                    'type' => 'hidden',
                    'default_value' => 'payment_type'
                ];
                $items3[] = [
                    'name' => 'variables[]',
                    'label' => 'Variables',
                    'type' => 'select',
                    'required' => true,
                    'select_items' => $this->array_payment_type(),
                    'multiple' => true,
                    'selectpicker' => true,
                    'default_value' => null
                ];
            }
            if ($type == 'query_cpt') {
                $items3[] = [
                    'name' => 'type',
                    'type' => 'hidden',
                    'default_value' => 'cpt'
                ];
                $items3[] = [
                    'name' => 'variables[]',
                    'label' => 'Variables',
                    'type' => 'select',
                    'required' => true,
                    'select_items' => $this->array_procedure_codes(),
                    'multiple' => true,
                    'selectpicker' => true,
                    'default_value' => null
                ];
            }
            $items3[] = [
                'name' => 'year[]',
                'label' => 'Year',
                'type' => 'select',
                'required' => true,
                'select_items' => $this->array_payment_year(),
                'multiple' => true,
                'selectpicker' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'financial_query_form',
                'action' => route('financial_query'),
                'items' => $items3,
                'save_button_label' => 'Run Report',
                'add_save_button' => [
                    'print' => 'Print Report'
                ]
            ];
            $return .= $this->form_build($form_array);
        }
        if (! empty($result)) {
            if ($type == 'monthly_report' || $type == 'yearly_report') {
                $return .= '<div class="alert alert-success"><h5>Click on a row to get the insurance specifics for each time period.</h5></div>';
            }
            $return .= '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
            foreach ($head_arr as $head_row_k => $head_row_v) {
                $return .= '<th>' . $head_row_k . '</th>';
            }
            $return .= '</tr></thead><tbody>';
            foreach ($result as $row) {
                $return .= '<tr>';
                foreach ($head_arr as $head_row_k => $head_row_v) {
                    if ($head_row_k !== 'Action') {
                        if (isset($row['click'])) {
                            $return .= '<td class="nosh-click" data-nosh-click="' . $row['click'] . '">' . $row[$head_row_v] . '</td>';
                        } else {
                            $return .= '<td>' . $row[$head_row_v] . '</td>';
                        }
                    } else {
                        $return .= '<td>' . $row[$head_row_v] . '</td>';
                    }
                }
                $return .= '</tr>';
            }
            $return .= '</tbody></table>';
        } else {
            if ($type !== 'query_cpt' && $type !== 'query_payment') {
                $return .= ' No data.';
            }
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Financial';
        Session::put('last_page', $request->fullUrl());
        if (Session::has('download_now')) {
            $data['download_now'] = route('download_now');
        }
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function financial_era(Request $request, $era_id)
    {
        setlocale(LC_MONETARY, 'en_US.UTF-8');
        $era = DB::table('era')->where('era_id', '=', $era_id)->first();
        $claim = json_decode(unserialize($era->era), true);
        $html = '<div class="table-responsive"><table id="era_grid" class="table table-striped">';
        $html .= '<thead><tr><th style="width:200px">Check Details</th><th style="width:350px">Payer Details</th><th style="width:350px">Payee Details</th></tr></thead><tbody>';
        $html .= '<tr><td>Check Amount: ' . money_format('%n', $claim['check_amount']) .'<br><br>Check Number: ' . $claim['check_number'] .'<br><br>Check Date: ' . date('m/d/Y', $claim['check_date']) . '<br><br>Production Date: ' . date('m/d/Y', $claim['production_date']) . '</td>';
        $html .= '<td>Name: ' . $claim['payer_name'] . '<br><br>Tax ID: ' . $claim['payer_tax_id'] . '<br><br>Address: ' . $claim['payer_street'] . '<br>' . $claim['payer_city'] . ', ' . $claim['payer_state'] . ' ' . $claim['payer_zip'] . '</td>';
        $html .= '<td>Name: ' . $claim['payee_name'] . '<br><br>Tax ID: ' . $claim['payee_tax_id'] . '<br><br>Address: ' . $claim['payee_street'] . '<br>' . $claim['payee_city'] . ', ' . $claim['payee_state'] . ' ' . $claim['payee_zip'] . '</td></tr></tbody></table>';
        if (! empty($claim['claim'])) {
            $i = 0;
            $html .= '<br><table id="era_grid_' . $i .'" class="table table-striped">';
            $html .= '<thead><tr><th style="width:200px">Claim Details</th><th style="width:200px">Patient Details</th><th>Line Item Details</th></tr></thead><tbody>';
            foreach ($claim['claim'] as $row) {
                if ($row['claim_status_code'] == '1') {
                    $claim_status_code = 'Processed as primary';
                } elseif ($row['claim_status_code'] == '2') {
                    $claim_status_code = 'Processed as secondary';
                } elseif ($row['claim_status_code'] == '3') {
                    $claim_status_code = 'Processed as tertiary';
                } elseif ($row['claim_status_code'] == '22') {
                    $claim_status_code = 'Reversal of previous payment';
                } else {
                    $claim_status_code = $row['claim_status_code'];
                }
                if ($row['claim_forward'] == 0) {
                    $claim_forward = '';
                } else {
                    $claim_forward = '<br>Claim forwarded to another insurer.';
                }
                $html .= '<tr><td>Amount Charged: ' . money_format('%n', $row['amount_charged']);
                if ($row['amount_approved'] != '') {
                    $html .= '<br><br>Amount Approved: ' . money_format('%n', $row['amount_approved']);
                }
                if ($row['amount_patient'] != '') {
                    $html .= '<br><br>Amount assigned to Patient: ' . money_format('%n', $row['amount_patient']);
                }
                $html .='<br><br>Date of Service: ' . date('m/d/Y', $row['dos']) . '<br><br>Claim Status: ' . $claim_status_code . '<br><br>Claim ID: ' . $row['payer_claim_id'] . $claim_forward . '</td>';
                $html .= '<td>Patient: ' . $row['patient_lastname'] . ', ' . $row['patient_firstname'] . ' ' . $row['patient_middle'] . '<br><br>Insurance: ' . $row['payer_insurance'] . '<br><br>Patient Member ID: ' . $row['patient_member_id'] . '<br><br>Subscriber: ' . $row['subscriber_lastname'] . ', ' . $row['subscriber_firstname'] . ' ' . $row['subscriber_middle'] .'</td>';
                $html .= '<td><ul>';
                if (! empty($row['item'])) {
                    foreach ($row['item'] as $row1) {
                        $html .= '<li>CPT: ' . $row1['cpt'];
                        if ($row1['modifier'] != '') {
                            $html .= ', Modifier: ' . $row1['modifier'];
                        }
                        $html .= ', Charge: ' . money_format('%n', $row1['charge']) . ', Paid: ' . money_format('%n', $row1['paid']) . ', Allowed: ' . money_format('%n', $row1['allowed']);
                        if (! empty($row1['adjustment'])) {
                            $j = 1;
                            foreach ($row1['adjustment'] as $row2) {
                                $html .= '<br><br>Adjustment Reason # ' . $j . ': ' . $this->claim_reason_code($row2['reason_cpt']);;
                                $html .= '<br>Adjustment Amount # ' . $j . ': ' . money_format('%n', $row2['amount']);
                                $j++;
                            }
                        }
                        $html .= '</li>';
                    }
                }
                $html .= '</ul></td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => Session::get('last_page')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = $html;
        $data['panel_header'] = 'ERA 835 Details';
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function financial_era_form(Request $request)
    {
        $arr = Session::get('era');
        if ($request->isMethod('post')) {
            if ($request->input('submit') !== 'ignore') {
                $era = DB::table('era')->where('era_id', '=', $arr[0]['era_id'])->first();
                $claim = json_decode(unserialize($era->era), true);
                $claim_num = $arr[0]['claim_num'];
                $data = [
                    'eid' => $request->input('eid'),
                    'other_billing_id' => '',
                    'pid' => $request->input('pid'),
                    'dos_f' => date('m/d/Y', $claim['claim'][$claim_num]['dos']),
                    'payment' => $claim['claim'][$claim_num]['amount_approved'],
                    'payment_type' => 'Insurance Payment, Check #: ' . $claim['check_number'] . ', ERA #: ' . $arr[0]['era_id'],
                    'practice_id' => Session::get('practice_id')
                ];
                DB::table('billing_core')->insert($data);
                $this->audit('Add');
                if ($claim['claim'][$claim_num]['amount_patient'] == '') {
                    $claim['claim'][$claim_num]['amount_patient'] = 0;
                }
                $adjtotal = $claim['claim'][$claim_num]['amount_charged'] - $claim['claim'][$claim_num]['amount_approved'] - $claim['claim'][$claim_num]['amount_patient'];
                if ($adjtotal != 0) {
                    $data1 = [
                        'eid' => $request->input('eid'),
                        'other_billing_id' => '',
                        'pid' => $request->input('pid'),
                        'dos_f' => date('m/d/Y', $claim['claim'][$claim_num]['dos']),
                        'payment' => $adjtotal,
                        'payment_type' => 'Insurance Adjustment, ERA #: ' . $arr[0]['era_id'],
                        'practice_id' => Session::get('practice_id')
                    ];
                    DB::table('billing_core')->insert($data1);
                    $this->audit('Add');
                }
                Session::put('message_action', 'Claim associated');
            }
            unset($arr[0]);
            $arr = array_values($arr);
            if (! empty($arr)) {
                Session::put('era', $arr);
                return redirect()->route('financial_era_form');
            } else {
                Session::forget('era');
                return redirect()->route('financial', ['era']);
            }
        } else {
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['search_patient1'] = 'pid';
            $items[] = [
                'name' => 'pid',
                'type' => 'hidden',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'eid',
                'label' => 'Encounter',
                'type' => 'select',
                'required' => true,
                'select_items' => ['' => 'Search Patient First'],
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'era_form',
                'action' => route('financial_era_form'),
                'items' => $items,
                'save_button_label' => 'Save',
                'add_save_button' => [
                    'ignore' => 'Ignore this Claim'
                ]
            ];
            $return = '<div class="alert alert-success"><h5>ERA Details</h5>';
            $return .= '<strong>First Name:</strong> ' . $arr[0]['patient_firstname'];
            $return .= '<br><strong>Last Name:</strong>' . $arr[0]['patient_lastname'];
            $return .= '<br><strong>Date of Service:</strong>' . $arr[0]['dos'];
            $return .= '<br><strong>Amount Charged:</strong>' . $arr[0]['amount_charged'];
            $return .= '<br><strong>Amount Approved:</strong>' . $arr[0]['amount_approved'];
            $return .= '</div>';
            $return .= $this->form_build($form_array);
            $data['content'] = $return;
            $data['panel_header'] = 'Reconcile ERA 835 Claim';
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function financial_insurance(Request $request, $id)
    {
        $result = [];
        $return = '';
        $query = DB::table(DB::raw('billing as t1'))
            ->leftJoin(DB::raw('insurance as t2'), 't1.insurance_id_1', '=', 't2.insurance_id')
            ->leftJoin(DB::raw('encounters as t3'), 't1.eid', '=', 't3.eid')
            ->select(DB::raw("t2.insurance_plan_name as insuranceplan, COUNT(*) as ins_patients_seen"));
        $id_arr = explode("-", $id);
        if (count($id_arr) > 1) {
            $query->where(DB::raw("YEAR(t3.encounter_DOS)"), '=', $id_arr[0])->where(DB::raw("MONTH(t3.encounter_DOS)"), '=', $id_arr[1]);
            $data['panel_header'] = 'Monthly Insurance Data';
        } else {
            $query->where(DB::raw("YEAR(t3.encounter_DOS)"), '=', $id);
            $data['panel_header'] = 'Yearly Insurance Data';
        }
        $query->where('t3.addendum', '=', 'n')
            ->where('t3.practice_id', '=', Session::get('practice_id'))
            ->groupBy('insuranceplan');
        $query_result = $query->get();
        if ($query_result->count()) {
            foreach ($query_result as $query_row) {
                if (is_null($query_row->insuranceplan)) {
                    $query_row->insuranceplan = 'Cash Only';
                }
                $result[] = [
                    'insurance' => $query_row->insuranceplan,
                    'patients_seen' => $query_row->ins_patients_seen
                ];
            }
        }
        $head_arr = [
            'Insurance' => 'insurance',
            'Patients Seen' => 'patients_seen',
        ];
        if (! empty($result)) {
            $return .= '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
            foreach ($head_arr as $head_row_k => $head_row_v) {
                $return .= '<th>' . $head_row_k . '</th>';
            }
            $return .= '</tr></thead><tbody>';
            foreach ($result as $row) {
                $return .= '<tr>';
                foreach ($head_arr as $head_row_k => $head_row_v) {
                    if ($head_row_k !== 'Action') {
                        if (isset($row['click'])) {
                            $return .= '<td class="nosh-click" data-nosh-click="' . $row['click'] . '">' . $row[$head_row_v] . '</td>';
                        } else {
                            $return .= '<td>' . $row[$head_row_v] . '</td>';
                        }
                    } else {
                        $return .= '<td>' . $row[$head_row_v] . '</td>';
                    }
                }
                $return .= '</tr>';
            }
            $return .= '</tbody></table>';
        } else {
            $return .= ' No data.';
        }
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => Session::get('last_page')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = $return;
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function financial_patient(Request $request, $action, $pid, $eid)
    {
        if (Session::has('pid')) {
            if (Session::get('pid') !== $pid) {
                $this->setpatient($pid);
            }
        } else {
            $this->setpatient($pid);
        }
        return redirect()->route($action, [$eid, 'eid']);
    }

    public function financial_query(Request $request)
    {
        $result = [];
        $practice_id = Session::get('practice_id');
        $query_text1 = DB::table('billing_core')->where('practice_id', '=', $practice_id);
        $variables_array = $request->input('variables');
        $type = $request->input('type');
        $i = 0;
        $j = 0;
        foreach ($variables_array as $variable) {
            if ($i == 0) {
                $query_text1->where($type, '=', $variable);
            } else {
                $query_text1->orWhere($type, '=', $variable);
            }
            $i++;
        }
        $year_array = $request->input('year');
        $query_text1->where(function($query_array1) use ($year_array, $j) {
            foreach ($year_array as $year) {
                if ($j == 0) {
                    $query_array1->where('dos_f', 'LIKE', "%$year%");
                } else {
                    $query_array1->orWhere('dos_f', 'LIKE', "%$year%");
                }
                $j++;
            }
        });
        $query = $query_text1->get();
        if ($query->count()) {
            foreach ($query as $records_row) {
                $query2_row = DB::table('demographics')->where('pid', '=', $records_row->pid)->first();
                if ($type == 'payment_type') {
                    $type1 = $records_row->payment_type;
                    $amount = $records_row->payment;
                } else {
                    $type1 = "CPT code: " . $records_row->cpt;
                    $amount = $records_row->cpt_charge;
                }
                $result[] = [
                    'billing_core_id' => $records_row->billing_core_id,
                    'dos_f' => $records_row->dos_f,
                    'lastname' => $query2_row->lastname,
                    'firstname' => $query2_row->firstname,
                    'amount' => $amount,
                    'type' => $type1
                ];
            }
        }
        if ($request->input('submit') == 'print') {
            if (! empty($result)) {
                $file_path = public_path() . '/temp/' . time() . '_' . Session::get('user_id') . '_financialquery.pdf';
                $html = $this->page_intro('Financial Query Results', Session::get('practice_id'));
                $html .= $this->page_financial_results($result);
                $this->generate_pdf($html, $file_path);
                while(!file_exists($file_path)) {
                    sleep(2);
                }
                Session::put('download_now', $file_path);
                return redirect(Session::get('last_page'));
            } else {
                Session::put('message_action', 'Error - no results');
                return redirect(Session::get('last_page'));
            }
        } else {
            $head_arr = [
                'ID' => 'billing_core_id',
                'Date of Service' => 'dos_f',
                'Last Name' => 'lastname',
                'First Name' => 'firstname',
                'Amount' => 'amount',
                'Payment Type' => 'type',
            ];
            if (! empty($result)) {
                $return = '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
                foreach ($head_arr as $head_row_k => $head_row_v) {
                    $return .= '<th>' . $head_row_k . '</th>';
                }
                $return .= '</tr></thead><tbody>';
                foreach ($result as $row) {
                    $return .= '<tr>';
                    foreach ($head_arr as $head_row_k => $head_row_v) {
                        if ($head_row_k !== 'Action') {
                            if (isset($row['click'])) {
                                $return .= '<td class="nosh-click" data-nosh-click="' . $row['click'] . '">' . $row[$head_row_v] . '</td>';
                            } else {
                                $return .= '<td>' . $row[$head_row_v] . '</td>';
                            }
                        } else {
                            $return .= '<td>' . $row[$head_row_v] . '</td>';
                        }
                    }
                    $return .= '</tr>';
                }
                $return .= '</tbody></table>';
            } else {
                if ($type !== 'query') {
                    $return .= ' No results.';
                }
            }
            $data['content'] = $return;
            $dropdown_array = [
                'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
                'default_button_text_url' => Session::get('last_page')
            ];
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['panel_header'] = 'Financial Query';
            Session::put('last_page', $request->fullUrl());
            if (Session::has('download_now')) {
                $data['download_now'] = route('download_now');
            }
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function financial_queue(Request $request, $type, $eid)
    {
        $data['bill_submitted'] = $type;
        DB::table('encounters')->where('eid', '=', $eid)->update($data);
        $this->audit('Update');
        if ($type == 'Pend') {
            $message = "Billed encounter added to the print image queue";
        } else {
            $message = "Billed encounter added to the print HCFA-1500 queue";
        }
        Session::put('message_action', $message);
        return redirect(Session::get('last_page'));
    }

    public function financial_resubmit(Request $request, $eid)
    {
        $row = DB::table('billing')->where('eid', '=', $eid)->first();
        $message = "Error - No bill for this encounter";
        if ($row) {
            if ($row->insurance_id_1 == '0' || $row->insurance_id_1 == '') {
                $message = "Error - No insurance was assigned, cannot be resubmitted";
            } else {
                $data['bill_submitted'] = 'No';
                DB::table('encounters')->where('eid', '=', $eid)->update($data);
                $this->audit('Update');
                $message = "Billed changed to unsent status";
            }
        }
        Session::put('message_action', $message);
        return redirect(Session::get('last_page'));
    }

    public function financial_upload_era(Request $request)
    {
        if ($request->isMethod('post')) {
            $file = $request->file('file_input');
            // $pid = Session::get('pid');
            // $directory = Session::get('documents_dir') . $pid;
            $new_directory = public_path() . '/temp';
            $new_name = time() . '_' . str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '.era';
            $file->move($new_directory, $new_name);
            $file_path = $new_directory . '/' . $new_name;
            $era = File::get($file_path);
            $result = $this->parse_era($era);
            if (isset($result['invalid'])) {
                unlink($file_path);
                Session::put('message_action', 'Error - ' . $result['invalid']);
                return redirect(Session::get('last_page'));
            } else {
                $era_data = [
                    'era' => serialize(json_encode($result)),
                    'practice_id' => Session::get('practice_id')
                ];
                $era_id = DB::table('era')->insertGetId($era_data);
                $this->audit('Add');
                $message_arr = [];
                $arr = [];
                if (isset($result['claim'])) {
                    $i = 0;
                    $j = 0;
                    foreach ($result['claim'] as $claim) {
                        if (isset($claim['bill_Box26'])) {
                            $pos = stripos($claim['bill_Box26'], '_');
                            if ($pos !== false) {
                                $identity = explode('_', $claim['bill_Box26']);
                                $addendum_check = DB::table('encounters')->where('eid', '=', $identity[1])->first();
                                if ($addendum_check) {
                                    if ($addendum_check->addendum == 'y') {
                                        $get_current_eid = DB::table('encounters')->where('addendum_eid', '=', $addendum_check->addendum_eid)->where('addendum', '=', 'n')->first();
                                        $eid = $get_current_eid->eid;
                                    } else {
                                        $eid = $identity[1];
                                    }
                                    $data = [
                                        'eid' => $eid,
                                        'other_billing_id' => '',
                                        'pid' => $identity[0],
                                        'dos_f' => date('m/d/Y', $claim['dos']),
                                        'payment' => $claim['amount_approved'],
                                        'payment_type' => 'Insurance Payment, Check #: ' . $result['check_number'] . ', ERA # ' . $era_id,
                                        'practice_id' => Session::get('practice_id')
                                    ];
                                    DB::table('billing_core')->insert($data);
                                    $this->audit('Add');
                                    $adjtotal = $claim['amount_charged'] - $claim['amount_approved'] - $claim['amount_patient'];
                                    if ($adjtotal != 0) {
                                        $data1 = [
                                            'eid' => $eid,
                                            'other_billing_id' => '',
                                            'pid' => $identity[0],
                                            'dos_f' => date('m/d/Y', $claim['dos']),
                                            'payment' => $adjtotal,
                                            'payment_type' => 'Insurance Adjustment, ERA #: ' . $era_id,
                                            'practice_id' => Session::get('practice_id')
                                        ];
                                        DB::table('billing_core')->insert($data1);
                                        $this->audit('Add');
                                    }
                                    $patient = DB::table('demographics')->where('pid', '=', $identity[0])->first();
                                    $message_arr[] = 'Payment added for encounter date of service of ' . date('Y-m-d', $claim['dos']) . 'for ' . $patient->firstname . ' ' . $patient->lastname;
                                }
                            } else {
                                $arr[$j]['claim_num'] = $i;
                                $arr[$j]['era_id'] = $era_id;
                                $arr[$j]['patient_lastname'] = $claim['patient_lastname'];
                                $arr[$j]['patient_firstname'] = $claim['patient_firstname'];
                                $arr[$j]['dos'] = date('m/d/Y', $claim['dos']);
                                $arr[$j]['amount_charged'] = money_format('%n', $claim['amount_charged']);
                                $arr[$j]['amount_approved'] = money_format('%n', $claim['amount_approved']);
                                if (isset($claim['item'])) {
                                    $k = 0;
                                    foreach ($claim['item'] as $item) {
                                        $arr[$j]['cpt'][$k] = $item['cpt'];
                                        $k++;
                                    }
                                }
                                $j++;
                            }
                        }
                        $i++;
                    }
                }
                unlink($file_path);
                if (! empty($message_arr)) {
                    Session::put('message_action', implode('<br>', $message_arr));
                }
                if (! empty($arr)) {
                    Session::put('era', $arr);
                    return redirect()->route('financial_era_form');
                }
                return redirect(Session::get('last_page'));
            }
        } else {
            $data['panel_header'] = 'Upload An ERA 835 File';
            $data['document_upload'] = route('financial_upload_era');
            $type_arr = ['835', 'txt', 'era'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['assets_js'] = $this->assets_js('document_upload');
            $data['assets_css'] = $this->assets_css('document_upload');
            return view('document_upload', $data);
        }
    }

    public function generate_hcfa($eid)
    {
        $file_path = $this->hcfa($eid);
        if ($file_path) {
            return response()->download($file_path)->deleteFileAfterSend(true);
        } else {
            Session::put('message_action', 'Error - No HCFA to print.');
            return redirect(Session::get('last_page'));
        }
    }

    public function messaging(Request $request, $type='inbox')
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $return = '';
        $type_arr = [
            'inbox' => ['Inbox', 'fa-inbox'],
            'drafts' => ['Drafts', 'fa-pencil-square-o'],
            'outbox' => ['Sent Messages', 'fa-upload'],
            'separator' => 'separator',
            'scans' => ['Scans', 'fa-file-o']
        ];
        if ($practice->fax_type !== '') {
            $type_arr['separator1'] = 'separator';
            $type_arr['faxes'] = ['Faxes', 'fa-fax'];
            $type_arr['faxes_draft'] = ['Draft Faxes', 'fa-share-square'];
            $type_arr['faxes_sent'] = ['Sent Faxes', 'fa-share-square-o'];
        }
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($value == 'separator') {
                $items[] = [
                    'type' => 'separator'
                ];
            } else {
                if ($key !== $type) {
                    $items[] = [
                        'type' => 'item',
                        'label' => $value[0],
                        'icon' => $value[1],
                        'url' => route('messaging', [$key])
                    ];
                }
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $list_array = [];
        if ($type == 'inbox') {
            $query = DB::table('messaging')->where('mailbox', '=', Session::get('user_id'))->orderBy('date', 'desc')->get();
            $columns = Schema::getColumnListing('messaging');
            $row_index = $columns[0];
            if ($query->count()) {
                foreach ($query as $row) {
                    $arr = [];
                    $user = DB::table('users')->where('id', '=', $row->message_from)->first();
                    $arr['label'] = '<b>' . $user->displayname . '</b> - ' . $row->subject . ' - ' . date('Y-m-d', $this->human_to_unix($row->date));
                    $arr['view'] = route('messaging_view', [$row->$row_index]);
                    $arr['delete'] = route('core_action', ['table' => 'messaging', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    if ($row->read == 'n' || $row->read == null) {
                        $arr['active'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
        }
        if ($type == 'drafts') {
            $query = DB::table('messaging')->where('message_from', '=', Session::get('user_id'))->where('mailbox', '=', '0')->where('status', '=', 'Draft')->orderBy('date', 'desc')->get();
            $columns = Schema::getColumnListing('messaging');
            $row_index = $columns[0];
            if ($query->count()) {
                foreach ($query as $row) {
                    $arr = [];
                    $arr['label'] = '<b>' . str_replace(';', '; ', $row->message_to) . '</b> - ' . $row->subject . ' - ' . date('Y-m-d', $this->human_to_unix($row->date));
                    $arr['edit'] = route('core_form', ['messaging', $row_index, $row->$row_index]);
                    $arr['delete'] = route('core_action', ['table' => 'messaging', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    $list_array[] = $arr;
                }
            }
        }
        if ($type == 'outbox') {
            $query = DB::table('messaging')->where('message_from', '=', Session::get('user_id'))->where('mailbox', '=', '0')->where('status', '=', 'Sent')->orderBy('date', 'desc')->get();
            $columns = Schema::getColumnListing('messaging');
            $row_index = $columns[0];
            if ($query->count()) {
                foreach ($query as $row) {
                    $arr = [];
                    $arr['label'] = '<b>' . str_replace(';', '; ', $row->message_to) . '</b> - ' . $row->subject . ' - ' . date('Y-m-d', $this->human_to_unix($row->date));
                    $arr['view'] = route('messaging_view', [$row->$row_index]);
                    if ($row->read == 'y') {
                        $arr['active'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
        }
        if ($type == 'inbox' || $type == 'drafts' || $type == 'outbox') {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => 'New Message',
                'icon' => 'fa-plus',
                'url' => route('core_form', ['messaging', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if ($type == 'faxes') {
            $query = DB::table('received')->where('practice_id', '=', Session::get('practice_id'))->orderBy('fileDateTime', 'desc')->get();
            $columns = Schema::getColumnListing('received');
            $row_index = $columns[0];
            if ($query->count()) {
                foreach ($query as $row) {
                    $arr = [];
                    $arr['label'] = '<b>' . $row->fileFrom . '</b> - ' . date('Y-m-d H:i:s', $this->human_to_unix($row->fileDateTime));
                    $arr['view'] = route('messaging_viewdoc', [$row->$row_index, 'received']);
                    $arr['edit'] = route('messaging_editdoc', [$row->$row_index, 'received']);
                    $arr['delete'] = route('core_action', ['table' => 'received', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    $list_array[] = $arr;
                }
            }
        }
        if ($type == 'faxes_draft') {
            $query = DB::table('sendfax')->where('faxdraft', '=', 'yes')->orWhereNull('faxdraft')->where('practice_id', '=', Session::get('practice_id'))->orderBy('job_id', 'desc')->get();
            $columns = Schema::getColumnListing('sendfax');
            $row_index = $columns[0];
            if ($query->count()) {
                foreach ($query as $row) {
                    $arr = [];
                    $arr['label'] = '<b>' . $row->job_id . '</b> - ' . $row->faxsubject;
                    $arr['edit'] = route('messaging_sendfax', [$row->$row_index]);
                    $list_array[] = $arr;
                }
            }
        }
        if ($type == 'faxes_sent') {
            $query = DB::table('sendfax')->whereNotNull('senddate')->where('practice_id', '=', Session::get('practice_id'))->orderBy('sentdate', 'desc')->get();
            $columns = Schema::getColumnListing('sendfax');
            $row_index = $columns[0];
            if ($query->count()) {
                foreach ($query as $row) {
                    $fax_to = '';
                    $recipients = DB::table('recipients')->where('job_id', '=', $row->job_id)->get();
                    foreach ($recipients as $recipient) {
                        if ($fax_to !== '') {
                            $fax_to .= '; ';
                        }
                        $fax_to .= $recipient->faxrecipient;
                    }
                    $arr = [];
                    $arr['label'] = '<b>' . $fax_to . '</b> - ' . $row->faxsubject . ' - ' . date('Y-m-d H:i:s', $this->human_to_unix($row->sentdate));
                    $arr['view'] = route('messaging_sendfax', [$row->$row_index]);
                    $list_array[] = $arr;
                }
            }
        }
        if ($type == 'faxes' || $type == 'faxes_draft' || $type == 'faxes_sent') {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => 'New Fax',
                'icon' => 'fa-plus',
                'url' => route('messaging_sendfax', ['0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if ($type == 'scans') {
            $query = DB::table('scans')->where('practice_id', '=', Session::get('practice_id'))->orderBy('fileDateTime', 'desc')->get();
            $columns = Schema::getColumnListing('scans');
            $row_index = $columns[0];
            if ($query->count()) {
                foreach ($query as $row) {
                    $arr = [];
                    $arr['label'] = '<b>' . $row->fileName . '</b> - ' . date('Y-m-d H:i:s', $this->human_to_unix($row->fileDateTime));
                    $arr['view'] = route('messaging_viewdoc', [$row->$row_index, 'scans']);
                    $arr['edit'] = route('messaging_editdoc', [$row->$row_index, 'scans']);
                    $arr['delete'] = route('core_action', ['table' => 'scans', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    $list_array[] = $arr;
                }
            }
        }
        if (! empty($list_array)) {
            $return .= $this->result_build($list_array, $type . '_list');
        } else {
            $return .= ' None.';
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Messaging';
        Session::put('last_page', $request->fullUrl());
        if (Session::has('download_now')) {
            $data['download_now'] = route('download_now');
        }
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function messaging_add_photo(Request $request, $message_id)
    {
        if ($request->isMethod('post')) {
            $file = $request->file('file_input');
            if ($message_id == '0') {
                $directory = public_path() . '/temp/';
                $new_name = time() . '_photo_' . Session::get('user_id') . "." . $file->getClientOriginalExtension();
                if (Session::has('messaging_add_photo')) {
                    $message_add_photo_arr = Session::get('messaging_add_photo');
                } else {
                    $message_add_photo_arr = [];
                }
                $file_path = $directory . $new_name;
                $message_add_photo_arr[] = $file_path;
                Session::put('messaging_add_photo', $message_add_photo_arr);
            } else {
                if (Session::has('pid')) {
                    $pid = Session::get('pid');
                    $directory = Session::get('documents_dir') . $pid . '/';
                } else {
                    $pid = '';
                    $directory = Session::get('documents_dir');
                }
                $new_name = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file_path = $directory . $new_name;
                $data = [
                    'image_location' => $file_path,
                    'pid' => $pid,
                    'message_id' => $message_id,
                    'image_description' => 'Photo Uploaded ' . date('F jS, Y'),
                    'id' => Session::get('user_id')
                ];
                $image_id = DB::table('image')->insertGetId($data);
                $this->audit('Add');
            }
            $file->move($directory, $new_name);
            return redirect(Session::get('message_photo_last_page'));
        } else {
            $data['panel_header'] = 'Upload A Photo/Image';
            $data['document_upload'] = route('messaging_add_photo', [$message_id]);
            $type_arr = ['png', 'jpg'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            $dropdown_array['default_button_text_url'] = Session::get('message_photo_last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('document_upload');
            $data['assets_css'] = $this->assets_css('document_upload');
            return view('document_upload', $data);
        }
    }

    public function messaging_delete_photo(Request $request, $id, $type='')
    {
        if ($type == 'session') {
            $arr = Session::get('messaging_add_photo');
            if (($key = array_search($id, $arr)) !== false) {
                unset($arr[$key]);
            }
            Session::put('messaging_add_photo', $arr);
        } else {
            DB::table('image')->where('image_id', '=', $id)->delete();
            $this->audit('Delete');
        }
        return redirect(Session::get('message_photo_last_page'));
    }

    public function messaging_editdoc(Request $request, $id, $type)
    {
        ini_set('memory_limit','196M');
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $directory = $practice->documents_dir . Session::get('pid') . "/";
        if ($request->isMethod('post')) {
            $arr = Session::get('messaging_editdoc_pages_list');
            $arr1 = Session::get('messaging_editdoc_pages');
            end($arr);
            $last_key = key($arr);
            reset($arr);
            if ($request->input('submit') == 'delete') {
                unlink($arr1[$request->input('image_path')]);
                unset($arr[$request->input('image_path')]);
                unset($arr1[$request->input('image_path')]);
                Session::put('messaging_editdoc_pages_list', $arr);
                Session::put('messaging_editdoc_pages', $arr1);
            } else {
                $image = imagecreatefrompng($request->input('image'));
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagejpeg($image, $arr1[$request->input('image_path')], 100);
            }
            Session::put('message_action', 'Page saved');
            if ($last_key == $request->input('image_path')) {
                return redirect()->route('messaging_editdoc_process', [$id, $type]);
            } else {
                while (!in_array(key($arr), [$request->input('image_path'), null])) {
                    next($arr);
                }
                next($arr);
                $next_key = key($arr);
                Session::put('messaging_editdoc_next', $next_key);
                return redirect()->route('messaging_editdoc', [$id, $type]);
            }
        } else {
            if (Session::has('messaging_editdoc')) {
                $data['message_action'] = Session::get('message_action');
                Session::forget('message_action');
                $arr = Session::get('messaging_editdoc_pages_list');
            } else {
                if ($type == 'received') {
                    $result = DB::table('received')->where('received_id', '=', $id)->first();
                    $file_path = $result->filePath;
                    $data['panel_header'] = date('Y-m-d H:i:s', $this->human_to_unix($result->fileDateTime)) . ' - ' . $result->fileFrom;
                }
                if ($type == 'sendfax') {
                    $result = DB::table('pages')->where('pages_id', '=', $id)->first();
                    $file_path = $result->file;
                    $data['panel_header'] = 'Preview';
                }
                if ($type == 'scans') {
                    $result = DB::table('scans')->where('scans_id', '=', $id)->first();
                    $file_path = $result->filePath;
                    $data['panel_header'] = date('Y-m-d H:i:s', $this->human_to_unix($result->fileDateTime)) . ' - ' . $result->fileName;
                }
                $name = time() . '_' . Session::get('user_id');
                Session::put('messaging_editdoc_name', $name);
                $temp_file_path = public_path() . '/temp/' . $name . '_doc.pdf';
                Session::put('messaging_editdoc', $temp_file_path);
                copy($file_path, $temp_file_path);
                while(!file_exists($temp_file_path)) {
                    sleep(2);
                }
                $arr = [];
                $arr1 = [];
                $imagick = new Imagick();
                $imagick->setResolution(100, 100);
                $imagick->readImage($temp_file_path);
                $page_count = $imagick->getNumberImages();
                $imagick->writeImages(public_path() . '/temp/' . $name . '_pages.jpg', false);
                $page = new Imagick();
                if ($page_count > 1) {
                    for ($i = 0; $i < $page_count; $i++) {
                        $name1 = $name . '_pages-' . $i . '.jpg';
                        $page_file = asset('temp/' . $name1);
                        $j = $i + 1;
                        $arr[$page_file] = 'Page ' . $j;
                        $arr1[$page_file] = public_path() . '/temp/' . $name1;
                    }
                } else {
                    $page_file = asset('temp/' . $name . '_pages.jpg');
                    $arr[$page_file] = 'Page 1';
                    $arr1[$page_file] = public_path() . '/temp/' . $name . '_pages.jpg';
                }
                Session::put('messaging_editdoc_pages_list', $arr);
                Session::put('messaging_editdoc_pages', $arr1);
            }
            $data['image_list'] = '';
            foreach ($arr as $k => $v) {
                if (Session::has('messaging_editdoc_next')) {
                    if ($k == Session::get('messaging_editdoc_next')) {
                        $data['image_list'] .= '<option value="' . $k . '" selected>' . $v . '</option>';
                        Session::forget('messaging_editdoc_next');
                    } else {
                        $data['image_list'] .= '<option value="' . $k . '">' . $v . '</option>';
                    }
                } else {
                    $data['image_list'] .= '<option value="' . $k . '">' . $v . '</option>';
                }
            }
            $data['image_list_title'] = 'Page Selector';
            $data['image_list_label'] = 'Select a page to edit:';
            $items[] = [
                'name' => 'image',
                'type' => 'hidden',
                'default_value' => null
            ];
            $items[] = [
                'name' => 'image_path',
                'type' => 'hidden',
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'image_form',
                'action' => route('messaging_editdoc', [$id, $type]),
                'items' => $items,
                'save_button_label' => 'Save',
                'add_save_button' => ['delete' => 'Discard Page'],
                'origin' => route('messaging_editdoc_cancel', [$id, $type])
            ];
            $data['content'] = $this->form_build($form_array);
            $data['panel_header'] = 'Annotate Document';
            $dropdown_array = [];
            $items = [];
            $items[] = [
                'type' => 'item',
                'label' => 'Back',
                'icon' => 'fa-chevron-left',
                'url' => Session::get('last_page')
            ];
            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $dropdown_array1 = [];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => 'Process Document',
                'icon' => 'fa-floppy-o',
                'url' => route('messaging_editdoc_process', [$id, $type])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
            $signature = DB::table('providers')->where('id', '=', Session::get('user_id'))->first();
            if ($signature) {
                if ($signature->signature !== '') {
                    if (file_exists($signature->signature)) {
                        $name = time() . '_signature.png';
                        $temp_path = public_path() .'/temp/' . $name;
                        $data['signature'] = asset('temp/' . $name);
                        copy($signature->signature, $temp_path);
                    }
                }
            }
            $data['assets_js'] = $this->assets_js('image');
            $data['assets_css'] = $this->assets_css('image');
            return view('image', $data);
        }
    }

    public function messaging_editdoc_cancel(Request $request, $id, $type)
    {
        $temp_file_path = Session::get('messaging_editdoc');
        $pages = Session::get('messaging_editdoc_pages');
        foreach ($pages as $page) {
            if (file_exists($page)) {
                unlink($page);
            }
        }
        unlink($temp_file_path);
        Session::forget('messaging_editdoc');
        Session::forget('messaging_editdoc_name');
        Session::forget('messaging_editdoc_pages');
        Session::forget('messaging_editdoc_pages_list');
        $origin = Session::get('last_page');
        if (Session::has('messaging_last_page')) {
            $origin = Session::get('messaging_last_page');
            // Session::forget('messaging_last_page');
        }
        return redirect($origin);
    }

    public function messaging_editdoc_process(Request $request, $id, $type)
    {
        if ($request->isMethod('post')) {
            $temp_file_path = Session::get('messaging_editdoc');
            $pages = Session::get('messaging_editdoc_pages');
            $page1 = new Imagick();
            foreach ($pages as $page) {
                $page1->readImage($page);
            }
            $name = Session::get('messaging_editdoc_name');
            unlink($temp_file_path);
            Session::forget('messaging_editdoc');
            Session::forget('messaging_editdoc_name');
            Session::forget('messaging_editdoc_pages');
            Session::forget('messaging_editdoc_pages_list');
            $page1->setImageFormat('pdf');
            $file_path = public_path() . '/temp/' . $name . '_new.pdf';
            $page1->writeImages($file_path, true);
            $pid = $request->input('pid');
            $patient = DB::table('demographics')->where('pid', '=', $pid)->first();
            $directory = Session::get('documents_dir') . $pid;
            $new_file_path = $directory . "/" . $name . '_new.pdf';
            copy($file_path, $new_file_path);
            $pages_data2 = [
                'documents_url' => $new_file_path,
                'pid' => $pid,
                'documents_type' => $request->input('documents_type'),
                'documents_desc' => $request->input('documents_desc'),
                'documents_from' => $request->input('documents_from'),
                'documents_viewed' => Session::get('displayname'),
                'documents_date' => date('Y-m-d', strtotime($request->input('documents_date')))
            ];
            $documents_id = DB::table('documents')->insertGetId($pages_data2);
            $this->audit('Add');
            $origin = Session::get('last_page');
            if (Session::has('messaging_last_page')) {
                $origin = Session::get('messaging_last_page');
            }
            if ($request->has('submit')) {
                if ($request->input('submit') == 'download') {
                    Session::put('download_now', $file_path);
                    Session::put('message_action', 'Document saved in patient chart');
                    return redirect($origin);
                }
                if ($request->input('submit') == 'fax') {
                    Session::put('fax_now', $file_path);
                    Session::put('fax_fileoriginal', $request->input('documents_type') . ' for ' . $patient->firstname . ' ' . $patient->lastname);
                    Session::put('message_action', 'Document saved in patient chart, ready to be faxed');
                    return redirect()->route('messaging_sendfax', ['0']);
                }
            } else {
                Session::put('message_action', 'Document saved in patient chart');
                return redirect($origin);
            }
        } else {
            $data['search_patient1'] = 'pid';
            $document_type_arr = [
                'Laboratory' => 'Laboratory',
                'Imaging' => 'Imaging',
                'Cardiopulmonary' => 'Cardiopulmonary',
                'Endoscopy' => 'Endoscopy',
                'Referrals' => 'Referrals',
                'Past_Records' => 'Past Records',
                'Other_Forms' => 'Other Forms',
                'Letters' => 'Letters',
                'ccda' => 'CCDAs',
                'ccr' => 'CCRs'
            ];
            $items[] = [
                'name' => 'pid',
                'type' => 'hidden',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'documents_from',
                'label' => 'From',
                'type' => 'text',
                'required' => true,
                'typeahead' => route('typeahead', ['table' => 'documents', 'column' => 'documents_from']),
                'default_value' => null
            ];
            $items[] = [
                'name' => 'documents_type',
                'label' => 'Type',
                'type' => 'select',
                'select_items' => $document_type_arr,
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'documents_desc',
                'label' => 'Description',
                'type' => 'textarea',
                'required' => true,
                'typeahead' => route('typeahead', ['table' => 'documents', 'column' => 'documents_desc']),
                'default_value' => null
            ];
            $items[] = [
                'name' => 'documents_date',
                'label' => 'Date',
                'type' => 'date',
                'required' => true,
                'default_value' => date('Y-m-d')
            ];
            $origin = Session::get('last_page');
            if (Session::has('messaging_last_page')) {
                $origin = Session::get('messaging_last_page');
            }
            $intro = '<div class="form-group" id="patient_name_div"><label class="col-md-3 control-label">Patient</label><div class="col-md-8"><p class="form-control-static" id="patient_name"></p></div></div>';
            $add_save['download'] = 'Save and Download';
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            if ($practice->fax_type !== '') {
                $add_save['fax'] = 'Save and Fax';
            }
            $form_array = [
                'form_id' => 'document_process_form',
                'action' => route('messaging_editdoc_process', [$id, $type]),
                'items' => $items,
                'save_button_label' => 'Save',
                'origin' => $origin,
                'intro' => $intro,
                'add_save_button' => $add_save
            ];
            $data['content'] = $this->form_build($form_array);
            $data['panel_header'] = 'Process and Assign Document';
            $dropdown_array = [];
            $items = [];
            $items[] = [
                'type' => 'item',
                'label' => 'Back',
                'icon' => 'fa-chevron-left',
                'url' => $origin
            ];
            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $dropdown_array1 = [];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => 'Process Document',
                'icon' => 'fa-floppy-o',
                'url' => route('messaging_editdoc_process', [$id, $type])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function messaging_export(Request $request, $id)
    {
        $query = DB::table('messaging')->where('message_id', '=', $id)->first();
        $message = 'Internal messaging with patient on: ' . date('Y-m-d', $this->human_to_unix($query->date)) . "\n\r" . $query->body;
        $message = str_replace("<br>", "", $message);
        $data = [
            't_messages_subject' => 'Internal messaging with patient: ' . $query->subject,
            't_messages_message' => $message,
            't_messages_dos' => date('Y-m-d H:i:s', time()),
            't_messages_provider' => Session::get('displayname'),
            't_messages_signed' => 'No',
            't_messages_from' => Session::get('displayname') . ' (' . Session::get('user_id') . ')',
            'pid' => $query->pid,
            'practice_id' => Session::get('practice_id')
        ];
        $t_messages_id = DB::table('t_messages')->insertGetId($data);
        $this->audit('Add');
        $images = DB::table('image')->where('message_id', '=', $id)->get();
        if ($images->count()) {
            foreach ($images as $image) {
                $image_data = [
                    'image_location' => $image->image_location,
                    'pid' => $query->pid,
                    't_messages_id' => $t_messages_id,
                    'image_description' => $image->image_description,
                    'id' => $image->id
                ];
                DB::table('image')->insert($image_data);
                $this->audit('Add');
            }
        }
        Session::put('message_action', 'Message exported to the chart as a telephone message');
        return redirect(Session::get('last_page'));
    }

    public function messaging_sendfax(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $data = [
                'faxsubject' => $request->input('faxsubject'),
                'faxcoverpage' => $request->input('faxcoverpage'),
                'faxmessage' => $request->input('faxmessage'),
                'faxdraft' => '',
                'senddate' => date('Y-m-d H:i:s'),
                'success' => '0',
                'attempts' => '0'
            ];
            if ($request->has('submit')) {
                $data['faxdraft'] = 'yes';
            }
            DB::table('sendfax')->where('job_id', '=', $id)->update($data);
            if ($request->has('submit')) {
                Session::put('message_action', 'Job ' . $id . ' saved as draft');
                return redirect(Session::get('last_page'));
            } else {
                $message = $this->send_fax($id, '', '');
                Session::put('message_action', $message);
                return redirect(Session::get('last_page'));
            }
        } else {
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            // Create job_id if new
            if ($id == '0') {
                $fax_data = [
                    'user' => Session::get('displayname'),
                    'practice_id' => Session::get('practice_id')
                ];
                $id = DB::table('sendfax')->insertGetId($fax_data);
                $this->audit('Add');
                $directory = Session::get('documents_dir') . 'sentfax/' . $id;
                mkdir($directory, 0777);
                $sendfax = [
                    'faxsubject' => null,
                    'faxcoverpage' => null,
                    'faxmessage' => null
                ];
                if (Session::has('fax_now')) {
                    $filename = Session::get('fax_now');
                    $sendfax['faxsubject'] = Session::get('fax_fileoriginal');
                    Session::forget('fax_now');
                    Session::forget('fax_fileoriginal');
                    $filename_parts = explode("/", $filename);
                    $fax_filename = $directory . "/" . end($filename_parts);
                    copy($filename, $fax_filename);
                    $pages_data = [
                        'file' => $fax_filename,
                        'file_original' => $sendfax['faxsubject'],
                        'file_size' => File::size($fax_filename),
                        'pagecount' => $this->pagecount($fax_filename),
                        'job_id' => $id
                    ];
                    DB::table('pages')->insert($pages_data);
                    $this->audit('Add');
                }
            } else {
                $query = DB::table('sendfax')->where('job_id', '=', $id)->first();
                $sendfax = [
                    'faxsubject' => $query->faxsubject,
                    'faxcoverpage' => $query->faxcoverpage,
                    'faxmessage' => $query->faxmessage
                ];
            }
            $return = '';
            $return .= $this->header_build('Recipients');
            $recipients = DB::table('recipients')->where('job_id', '=', $id)->get();
            if ($recipients->count()) {
                $list_array = [];
                $columns = Schema::getColumnListing('recipients');
                $row_index = $columns[0];
                foreach ($recipients as $recipient) {
                    $arr = [];
                    $arr['label'] = '<b>' . $recipient->faxrecipient . '</b> - ' . $recipient->faxnumber;
                    $arr['edit'] = route('core_form', ['recipients', $row_index, $recipient->$row_index, $id]);
                    $arr['delete'] = route('core_action', ['table' => 'recipients', 'action' => 'delete', 'index' => $row_index, 'id' => $recipient->$row_index]);
                    $list_array[] = $arr;
                }
                $return .= $this->result_build($list_array, 'recipients_list', true);
            }
            $return .= '</div></div>';
            $return .= $this->header_build('Pages');
            $pages = DB::table('pages')->where('job_id', '=', $id)->get();
            if ($pages->count()) {
                $list_array = [];
                $columns = Schema::getColumnListing('pages');
                $row_index = $columns[0];
                foreach ($pages as $pages_row) {
                    $arr = [];
                    $pagecount = $pages_row->pagecount . ' pages';
                    if ($pages_row->pagecount == '1') {
                        $pagecount = $pages_row->pagecount . ' page';
                    }
                    $arr['label'] = '<b>' . $pages_row->file_original . '</b> - ' . $pagecount;
                    $arr['view'] = route('messaging_viewdoc', [$pages_row->pages_id, 'sendfax', $request->fullUrl()]);
                    $arr['delete'] = route('core_action', ['table' => 'pages', 'action' => 'delete', 'index' => $row_index, 'id' => $pages_row->$row_index]);
                    $list_array[] = $arr;
                }
                $return .= $this->result_build($list_array, 'pages_list', true);
            } else {
                $return .= ' None.';
            }
            $return .= '</div></div>';
            $formitems[] = [
                'name' => 'faxsubject',
                'label' => 'Subject',
                'type' => 'text',
                'required' => true,
                'typeahead' => route('typeahead', ['table' => 'sendfax', 'column' => 'faxsubject']),
                'default_value' => $sendfax['faxsubject']
            ];
            $formitems[] = [
                'name' => 'faxcoverpage',
                'label' => 'Coverpage',
                'type' => 'checkbox',
                'value' => 'yes',
                'default_value' => $sendfax['faxcoverpage']
            ];
            $formitems[] = [
                'name' => 'faxmessage',
                'label' => 'Coverpage Message',
                'type' => 'textarea',
                'default_value' => $sendfax['faxmessage']
            ];
            $form_array = [
                'form_id' => 'fax_form',
                'action' => route('messaging_sendfax', [$id]),
                'items' => $formitems,
                'save_button_label' => 'Save',
                'add_save_button' => [
                    'draft' => 'Save as Draft'
                ]
            ];
            $return .= $this->header_build('Details');
            $return .= $this->form_build($form_array);
            $return .= '</div></div>';
            $dropdown_array = [
                'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
                'default_button_text_url' => Session::get('last_page')
            ];
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => 'Add Recipient',
                'icon' => 'fa-user-plus',
                'url' => route('core_form', ['recipients', 'sendlist_id', '0', $id])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => 'Add Page or Document',
                'icon' => 'fa-plus',
                'url' => route('messaging_sendfax_upload', [$id])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
            $data['content'] = $return;
            $data['panel_header'] = 'Fax Details - Job ' . $id;
            Session::put('messaging_last_page', $request->fullUrl());
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function messaging_sendfax_upload(Request $request, $job_id)
    {
        if ($request->isMethod('post')) {
            $file = $request->file('file_input');
            $directory = Session::get('documents_dir') . 'sentfax/' . $job_id . '/';
            $file_size = $file->getSize();
            $file_original = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.pdf';
            $file->move($directory, $file_original);
            $file_path = $directory . $file_original;
            while(!file_exists($file_path)) {
                sleep(2);
            }
            $pdftext = File::get($file_path);
            $pagecount = preg_match_all("/\/Page\W/", $pdftext, $dummy);
            $data = [
                'file' => $file_path,
                'file_original' => $file_original,
                'file_size' => $file_size,
                'pagecount' => $pagecount,
                'job_id' => $job_id
            ];
            DB::table('pages')->insert($data);
            $this->audit('Add');
            Session::get('message_action', 'PDF added to fax queue');
            return redirect(Session::get('last_page'));
        } else {
            $data['panel_header'] = 'Add PDF to Fax Queue';
            $data['document_upload'] = route('messaging_sendfax_upload', [$job_id]);
            $type_arr = ['pdf'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['assets_js'] = $this->assets_js('document_upload');
            $data['assets_css'] = $this->assets_css('document_upload');
            return view('document_upload', $data);
        }
    }

    public function messaging_view(Request $request, $id)
    {
        $query = DB::table('messaging')->where('message_id', '=', $id)->first();
        $user = DB::table('users')->where('id', '=', $query->message_from)->first();
        $columns = Schema::getColumnListing('messaging');
        $row_index = $columns[0];
        $return = '<div class="container" id="message_read_id" nosh-data-id="' . $id . '">';
        $return .= '<div class="row"><div class="col-md-2" style="margin:10px"><b>Subject</b></div><div class="col-md-8" style="margin:10px">' . $query->subject . '</div></div>';
        $return .= '<div class="row"><div class="col-md-2" style="margin:10px"><b>To</b></div><div class="col-md-8" style="margin:10px">' . str_replace(';', '; ', $query->message_to) . '</div></div>';
        if ($query->cc !== '' && $query->cc !== null) {
            $return .= '<div class="row"><div class="col-md-2" style="margin:10px"><b>CC</b></div><div class="col-md-8" style="margin:10px">' . str_replace(';', '; ', $query->cc) . '</div></div>';
        }
        $return .= '<div class="row"><div class="col-md-2" style="margin:10px"><b>From</b></div><div class="col-md-8" style="margin:10px">' . $user->displayname . '</div></div>';
        $return .= '<div class="row"><div class="col-md-2" style="margin:10px"><b>Message</b></div><div class="col-md-8" style="margin:10px">' . nl2br($query->body) . '</div></div>';
        $return .='</div>';
        $images = DB::table('image')->where('message_id', '=', $id)->get();
        if ($images->count()) {
            $return .= '<br><h5>Images:</h5><div class="list-group gallery">';
            foreach ($images as $image) {
                $file_path1 = '/temp/' . time() . '_' . basename($image->image_location);
                $file_path = public_path() . $file_path1;
                copy($image->image_location, $file_path);
                $return .= '<div class="col-sm-4 col-xs-6 col-md-3 col-lg-3"><a class="thumbnail fancybox nosh-no-load" rel="ligthbox" href="' . url('/') . $file_path1 . '">';
                $return .= '<img class="img-responsive" alt="" src="' . url('/') . $file_path1 . '" />';
                $return .= '<div class="text-center"><small class="text-muted">' . basename($image->image_location) . '</small></div></a>';
            }
            $return .= '</div>';
        }
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => Session::get('last_page')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $dropdown_array1 = [
            'items_button_icon' => 'fa-reply'
        ];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => 'Reply',
            'icon' => 'fa-reply',
            'url' => route('core_form', ['messaging', $row_index, $query->$row_index, 'reply'])
        ];
        $items1[] = [
            'type' => 'item',
            'label' => 'Reply All',
            'icon' => 'fa-reply-all',
            'url' => route('core_form', ['messaging', $row_index, $query->$row_index, 'reply_all'])
        ];
        $items1[] = [
            'type' => 'item',
            'label' => 'Forward',
            'icon' => 'fa-share',
            'url' => route('core_form', ['messaging', $row_index, $query->$row_index, 'forward'])
        ];
        if (Session::get('group_id') !== '100') {
            if ($query->pid !== '' || $query->pid !== null) {
                $items1[] = [
                    'type' => 'item',
                    'label' => 'Export to Telephone Message',
                    'icon' => 'fa-phone',
                    'url' => route('messaging_export', [$id])
                ];
            }
        }
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        $data['panel_header'] = 'Message';
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function messaging_viewdoc(Request $request, $id, $type)
    {
        if ($type == 'received') {
            $result = DB::table('received')->where('received_id', '=', $id)->first();
            $file_path = $result->filePath;
            $data['panel_header'] = date('Y-m-d H:i:s', $this->human_to_unix($result->fileDateTime)) . ' - ' . $result->fileFrom;
        }
        if ($type == 'sendfax') {
            $result = DB::table('pages')->where('pages_id', '=', $id)->first();
            $file_path = $result->file;
            $data['panel_header'] = 'Preview';
        }
        if ($type == 'scans') {
            $result = DB::table('scans')->where('scans_id', '=', $id)->first();
            $file_path = $result->filePath;
            $data['panel_header'] = 'Preview';
        }
        $name = time() . '_' . Session::get('user_id') . '_doc.pdf';
        $data['filepath'] = public_path() . '/temp/' . $name;
        copy($file_path, $data['filepath']);
        Session::put('file_path_temp', $data['filepath']);
        while(!file_exists($data['filepath'])) {
            sleep(2);
        }
        $data['document_url'] = asset('temp/' . $name);
        $dropdown_array = [];
        $items = [];
        $origin = Session::get('last_page');
        if (Session::has('messaging_last_page')) {
            $origin = Session::get('messaging_last_page');
            Session::forget('messaging_last_page');
        }
        $items[] = [
            'type' => 'item',
            'label' => 'Back',
            'icon' => 'fa-chevron-left',
            'url' => $origin
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $dropdown_array1 = [];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => '',
            'icon' => 'fa-edit',
            'url' => route('messaging_editdoc', [$id, $type])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['assets_js'] = $this->assets_js('documents');
        $data['assets_css'] = $this->assets_css('documents');
        return view('documents', $data);
    }

    public function password_change(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'old_password' => 'required',
                'password' => 'required|min:4',
                'confirm_password' => 'required|min:4|same:password',
            ]);
            $query = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
            if (Hash::check($request->input('old_password'), $query->password)) {
                $data['password'] = substr_replace(Hash::make($request->input('password')),"$2a",0,3);
                DB::table('users')->where('id', '=', Session::get('user_id'))->update($data);
                Session::put('message_action', 'Password changed');
                return redirect(Session::get('last_page'));
            } else {
                return redirect()->back()->withErrors(['tryagain' => 'Your current password was incorrect.  Try again.']);
            }
        } else {
            $items[] = [
                'name' => 'old_password',
                'label' => 'Current Password',
                'type' => 'password',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'password',
                'label' => 'New Password',
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
            $form_array = [
                'form_id' => 'password_change_form',
                'action' => route('password_change'),
                'items' => $items,
                'save_button_label' => 'Save',
            ];
            $data['content'] = $this->form_build($form_array);
            $data['panel_header'] = 'Change Password';
            $dropdown_array = [];
            $items = [];
            $items[] = [
                'type' => 'item',
                'label' => 'Back',
                'icon' => 'fa-chevron-left',
                'url' => Session::get('last_page')
            ];
            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function password_reset(Request $request, $id)
    {
        $query = DB::table('users')->where('id', '=', $id)->first();
        $data['password'] = $this->gen_secret();
        DB::table('users')->where('id', '=', $id)->update($data);
        $this->audit('Update');
        $url = route('password_reset_response', [$data['password']]);
        $data2['message_data'] = 'This message is to notify you that you have reset your password with mdNOSH Gateway.<br>';
        $data2['message_data'] .= 'To finish this process, please click on the following link or point your web browser to:<br>';
        $data2['message_data'] .= $url;
        $this->send_mail('auth.emails.generic', $data2, 'Reset password to NOSH ChartingSystem', $query->email, $query->practice_id);
        Session::put('message_action', 'Password reset.  Check your email for further instructions');
        return redirect(Session::get('last_page'));
    }

    public function pnosh_provider_redirect(Request $request)
    {
        $this->setpatient('1');
        $query = DB::table('demographics_relate')->where('practice_id', '=', Session::get('practice_id'))->where('pid', '=', '1')->first();
        if (!$query) {
            $query1 = DB::table('demographics_relate')->where('practice_id', '=', '1')->where('pid', '=', '1')->first();
            $data = [
                'pid' => '1',
                'practice_id' => Session::get('practice_id'),
                'id' => $query1->id
            ];
            DB::table('demographics_relate')->insert($data);
            $this->audit('Add');
        }
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $practice1 = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        if ($practice->google_refresh_token == '') {
            $data1['google_refresh_token'] = $practice1->google_refresh_token;
            DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->update($data1);
            $this->audit('Update');
        }
        if ($practice->email == '') {
            $data1['email'] = $practice1->email;
            DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->update($data1);
            $this->audit('Update');
        }
        if ($practice->practice_api_url == null) {
            // return redirect()->route('api_practice');
        }
        return redirect()->route('patient');
    }

    public function practice_cancel(Request $request)
    {
        if (Session::get('group_id') != '1') {
            return redirect()->route('dashboard');
        } else {
            if ($request->isMethod('post')) {
                $practice_id = $request->input('practice_id');
                $query1 = DB::table('users')->where('practice_id', '=', $practice_id)->where('group_id', '!=', '1')->get();
                if ($query1->count()) {
                    foreach ($query1 as $row1) {
                        $active = '0';
                        $disable = 'disable';
                        $password = Hash::make($disable);
                        $data = [
                            'active' => $active,
                            'password' => $password
                        ];
                        DB::table('users')->where('id', '=', $row1->id)->update($data);
                        $this->audit('Update');
                        $row2 = DB::table('demographics_relate')->where('id', '=', $row1->id)->where('practice_id', '=', $practice_id)->first();
                        if ($row2) {
                            $data1['id'] = null;
                            DB::table('demographics_relate')->where('demographics_relate_id', '=', $row2->demographics_relate_id)->update($data1);
                            $this->audit('Update');
                        }
                    }
                }
                $data2['active'] = 'N';
                DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data2);
                $this->audit('Update');
                Session::get('message_action', "Practice #" . $practice_id . " manually canceled!");
                return redirect()->route('dashboard');
            } else {
                $items[] = [
                    'name' => 'practice_id',
                    'label' => 'Pick Practice',
                    'type' => 'select',
                    'select_items' => $this->array_practices('y'),
                    'required' => true,
                    'default_value' => null
                ];
                $form_array = [
                    'form_id' => 'practice_cancel_form',
                    'action' => route('practice_cancel'),
                    'items' => $items,
                    'save_button_label' => 'Save'
                ];
                $data['panel_header'] = 'Cancel Practice';
                $data['content'] = $this->form_build($form_array);
                $data['assets_js'] = $this->assets_js();
                $data['assets_css'] = $this->assets_css();
                return view('core', $data);
            }
        }
    }

    public function practice_logo_upload(Request $request)
    {
        if ($request->isMethod('post')) {
            $file = $request->file('file_input');
            $directory = public_path() . '/assets/images';
            $new_name = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $new_name);
            $practice_logo = $directory . "/" . $new_name;
            $data['practice_logo'] = 'assets/images/' . $new_name;
            DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->update($data);
            $this->audit('Update');
            $img = $this->getImageFile($practice_logo);
            if (imagesx($img) > 350 || imagesy($img) > 100) {
                $width = imagesx($img);
                $height = imagesy($img);
                $scaledDimensions = $this->getDimensions($width,$height,350,100);
                $scaledWidth = $scaledDimensions['scaledWidth'];
                $scaledHeight = $scaledDimensions['scaledHeight'];
                $scaledImage = imagecreatetruecolor($scaledWidth, $scaledHeight);
                imagecopyresampled($scaledImage, $img, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $width, $height);
                $this->saveImage($scaledImage, $practice_logo);
            }
            Session::put('message_action', 'Practice logo updated');
            return redirect(Session::get('last_page'));
        } else {
            $data['panel_header'] = 'Upload Practice Logo';
            $data['document_upload'] = route('practice_logo_upload');
            $type_arr = ['jpg', 'jpeg', 'png', 'gif'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['assets_js'] = $this->assets_js('document_upload');
            $data['assets_css'] = $this->assets_css('document_upload');
            return view('document_upload', $data);
        }
    }

    public function prescription_view(Request $request, $id='')
    {
        $query = DB::table('rx_list')->where('rxl_id', '=', $id)->first();
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        if ($query) {
            if ($query->id !== null && $query->id !== '') {
                $data['content'] = '<div style="text-align: center;">';
                $url = route('prescription_pharmacy_view', [$id]);
                $data['content'] .= QrCode::size(300)->generate($url);
                $data['content'] .= '</div>';
                $data['content'] .= '<div style="text-align: center;"><a href="' . $url . '" target="_blank" class="nosh-no-load">Pharmacy Click Here</a></div>';
                $med = explode(' ', $query->rxl_medication);
                $data['rx'] = $this->goodrx_drug_search($med[0]);
                $data['link'] = $this->goodrx_information($query->rxl_medication, $query->rxl_dosage . $query->rxl_dosage_unit);
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
                $dropdown_array = [];
                $items = [];
                $items[] = [
                    'type' => 'item',
                    'label' => 'GoodRX',
                    'icon' => 'fa-chevron-down',
                    'url' => '#goodrx_container'
                ];
                $dropdown_array['items'] = $items;
                $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                return view('prescription', $data);
            } else {
                $data['content'] = 'This is not a prescribed medication.';
                $data['panel_header'] = 'Prescription Error';
                return view('core', $data);
            }
        } else {
            $data['content'] = 'This prescription has been dispensed.';
            $data['panel_header'] = 'Prescription Status';
            return view('core', $data);
        }
    }

    public function print_batch($type)
    {
        $query = DB::table('encounters')
            ->where('bill_submitted', '=', $type)
            ->where('addendum', '=', 'n')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->get();
        if ($query->count()) {
            if ($type == 'Pend') {
                $printimage = '';
                foreach ($query as $row) {
                    $printimage .= $this->printimage($row->eid);
                }
                $file_path = public_path() . '/temp/' . time() . '_' . Session::get('user_id') . '_printimage_batch.txt';
                File::put($file_path, $printimage);
            } else {
                $pdf_item_arr = [];
                $pdf = new Merger(false);
                foreach ($query as $row) {
                    $pdf_item = $this->hcfa($row->eid);
                    $pdf->addPDF($pdf_item, 'all');
                    $pdf_item_arr[] = $pdf_item;
                }
                $file_path = public_path() . '/temp/' . time() . '_' . Session::get('user_id') . '_printhcfa_batch.pdf';
                $pdf->merge();
                $pdf->save($file_path);
                foreach ($pdf_item_arr as $row1) {
                    unlink($row1);
                }
            }
            while(!file_exists($file_path)) {
                sleep(2);
            }
            return response()->download($file_path)->deleteFileAfterSend(true);
        } else {
            Session::put('message_action', 'Error - Nothing in print queue');
            return redirect(Session::get('last_page'));
        }
    }

    public function print_chart_admin(Request $request, $id)
    {
        $file_path = $this->print_chart('', $id, 'all');
        Session::put('download_now', $file_path);
        return redirect(Session::get('last_page'));
    }

    public function print_chart_request($id, $pid, $download=true)
    {
        $html = $this->page_hippa_request($id, $pid);
        $file_path = public_path() . "/temp/" . time() . "_recordsrequest_" . Session::get('user_id') . ".pdf";
        $this->generate_pdf($html, $file_path, 'footerpdf', '', '2');
        while(!file_exists($file_path)) {
            sleep(2);
        }
        if ($download == true) {
            return response()->download($file_path);
        } else {
            return $file_path;
        }
    }

    public function printimage_single($eid)
    {
        $new_template = $this->printimage($eid);
        $file_path = public_path() . '/temp/' . time() . '_printimage.txt';
        File::put($file_path, $new_template);
        while(!file_exists($file_path)) {
            sleep(2);
        }
        return response()->download($file_path);
    }

    public function print_invoice1($eid, $insurance_id_1, $insurance_id_2)
    {
        ini_set('memory_limit','196M');
        if ($insurance_id_1 !== '0') {
            $result = $this->billing_save_common($insurance_id_1, $insurance_id_2, $eid);
        }
        $file_path = public_path() . "/temp/" . time() . "_invoice_" . Session::get('user_id') . ".pdf";
        $html = $this->page_invoice1($eid);
        $this->generate_pdf($html, $file_path);
        while(!file_exists($file_path)) {
            sleep(2);
        }
        return response()->download($file_path);
    }

    public function print_invoice2($id, $pid)
    {
        ini_set('memory_limit','196M');
        $file_path = public_path() . "/temp/" . time() . "_invoice_" . Session::get('user_id') . ".pdf";
        $html = $this->page_invoice2($id, $pid);
        $this->generate_pdf($html, $file_path);
        while(!file_exists($file_path)) {
            sleep(2);
        }
        return response()->download($file_path);
    }

    public function print_medication($id, $pid, $download=true)
    {
        ini_set('memory_limit','196M');
        $html = $this->page_medication($id, $pid);
        $file_path = public_path() . "/temp/" . time() . "_rx_" . Session::get('user_id') . ".pdf";
        $this->generate_pdf($html, $file_path, 'footerpdf', '', '3');
        while(!file_exists($file_path)) {
            sleep(2);
        }
        if ($download == true) {
            return response()->download($file_path);
        } else {
            return $file_path;
        }
    }

    public function print_medication_combined($download=true)
    {
        $arr = Session::get('print_medication_combined');
        Session::forget('print_medication_combined');
        $new_arr = [];
        $pdf = new Merger(false);
        foreach ($arr as $k => $v) {
            $rx = DB::table('rx_list')->where('rxl_id', '=', $v['id'])->first();
            $new_arr[$v['pid']][$rx->id][] = $v['id'];
        }
        foreach ($new_arr as $pid => $provider_ids) {
            foreach ($provider_ids as $provider_id => $rxl_arr) {
                $rxl_chuck_arr = array_chunk($rxl_arr, 5);
                foreach ($rxl_chuck_arr as $rxl_arr1) {
                    $html = $this->page_medication_combined($pid, $rxl_arr1, $provider_id);
                    $temp_file_path = public_path() . "/temp/" . time() . "_rx_" . Session::get('user_id') . ".pdf";
                    $this->generate_pdf($html, $temp_file_path, 'footerpdf', '', '3');
                    $pdf->addFromFile($temp_file_path, 'all');
                }
            }
        }
        $file_path = public_path() . "/temp/" . time() . "_rx_" . Session::get('user_id') . ".pdf";
        $pdf->merge();
        $pdf->save($file_path);
        while(!file_exists($file_path)) {
            sleep(2);
        }
        if ($download == true) {
            return response()->download($file_path);
        } else {
            return $file_path;
        }
    }

    public function print_orders($id, $pid, $download=true)
    {
        ini_set('memory_limit','196M');
        $html = $this->page_orders($id, $pid);
        $file_path = public_path() . "/temp/" . time() . "_orders_" . Session::get('user_id') . ".pdf";
        $this->generate_pdf($html, $file_path);
        while(!file_exists($file_path)) {
            sleep(2);
        }
        if ($download == true) {
            return response()->download($file_path);
        } else {
            return $file_path;
        }
    }

    public function print_queue(Request $request, $action, $id, $pid, $subtype='')
    {
        ini_set('memory_limit', '196M');
        $arr = [];
        if (Session::has('print_queue')) {
            $arr = Session::get('print_queue');
        }
        if ($action == 'run') {
            Session::forget('print_queue');
            Session::forget('print_queue_count');
            // Generate individual pdfs
            $med_count = 0;
            if ($arr) {
                foreach ($arr as $item) {
                    if (isset($item['type'])) {
                        if ($item['function'] == 'print_medication') {
                            if ($item['type'] == 'single') {
                                $pdf_arr[] = $this->{$item['function']}($item['id'], $item['pid'], false);
                            } else {
                                if (Session::has('print_medication_combined')) {
                                    $print_medication_combined_arr = Session::get('print_medication_combined');
                                } else {
                                    $print_medication_combined_arr = [];
                                }
                                $print_medication_combined_arr[] = [
                                    'id' => $item['id'],
                                    'pid' => $item['pid']
                                ];
                                Session::put('print_medication_combined', $print_medication_combined_arr);
                                $med_count++;
                            }
                        } else {
                            $pdf_arr[] = $this->{$item['function']}($item['id'], $item['pid'], $item['type']);
                        }
                    } else {
                        $pdf_arr[] = $this->{$item['function']}($item['id'], $item['pid'], false);
                    }
                }
                if ($med_count > 0) {
                    $pdf_arr[] = $this->print_medication_combined(false);
                }
            } else {
                return back();
            }
            // Merge pdfs into 1
            $pdf = new Merger(false);
            foreach ($pdf_arr as $pdf_item) {
                $pdf->addFromFile($pdf_item, 'all');
            }
            $file_path = public_path() . '/temp/' . time() . '_print_queue.pdf';
            $pdf->merge();
            $pdf->save($file_path);
            return response()->download($file_path);
        } else {
            $action_arr = [
                'rx_list' => [
                    'url' => [
                        'function' => 'print_medication',
                        'id' => $id,
                        'pid' => $pid,
                        'type' => $subtype
                    ],
                    'message' => 'Prescription sent to print queue'
                ],
                'orders' => [
                    'url' => [
                        'function' => 'print_orders',
                        'id' => $id,
                        'pid' => $pid
                    ],
                    'message' => 'Order sent to print queue'
                ],
                'hippa' => [
                    'url' => [
                        'function' => 'print_chart',
                        'id' => $id,
                        'pid' => $pid,
                        'type' => $subtype
                    ],
                    'message' => 'Patient chart sent to print queue'
                ],
                'hippa_request' => [
                    'url' => [
                        'function' => 'print_chart_request',
                        'id' => $id,
                        'pid' => $pid
                    ],
                    'message' => 'Records request sent to print queue'
                ]
            ];
            $action_url = $action_arr[$action]['url'];
            $arr[] = $action_url;
            Session::put('print_queue', $arr);
            Session::put('print_queue_count', count($arr));
            $message_action = '';
            if (Session::has('message_action')) {
                $message_action = Session::get('message_action');
            }
            $message_action .= '<br>' . $action_arr[$action]['message'];
            Session::put('message_action', $message_action);
            return redirect(Session::get('last_page'));
        }
    }

    public function restore_backup(Request $request)
    {
        if ($request->isMethod('post')) {
            $file = $request->input('backup');
            $command = "mysql -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . env('DB_DATABASE') . " < " . $file;
            system($command);
            Session::put('message_action', 'Backup restored');
            return redirect()->route('dashboard');
        } else {
            $practice = DB::table('practiceinfo')->first();
            $dir = $practice->documents_dir;
            $files = glob($dir . "*.sql");
            $backup_arr = [];
            arsort($files);
            foreach ($files as $file) {
                $explode = explode("_", $file);
                $time = intval(str_replace(".sql","",$explode[1]));
                $backup_arr[$file] = date("Y-m-d H:i:s", $time);
            }
            $items[] = [
                'name' => 'backup',
                'label' => 'Select Backup',
                'type' => 'select',
                'select_items' => $backup_arr,
                'required' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'restore_backup_form',
                'action' => route('restore_backup'),
                'items' => $items,
                'save_button_label' => 'Restore'
            ];
            $data['panel_header'] = 'Restore Backup Database';
            $data['content'] = $this->form_build($form_array);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function schedule(Request $request, $provider_id='')
    {
        if ($provider_id == '') {
            // Check if this is the provider logging in
            $provider_query = DB::table('providers')->where('id', '=', Session::get('user_id'))->first();
            if ($provider_query) {
                $provider_id = $provider_query->id;
            }
        }
        $provider_arr = $this->array_providers();
        $data['provider_list'] = '<option value="">Select a provider</option>';
        foreach ($provider_arr as $provider_id_key => $provider_name) {
            $data['provider_list'] .= '<option value="' . $provider_id_key . '">' . $provider_name . '</option>';
        }
        if ($provider_id !== '') {
            // Show default schedule
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            if ($practice->weekends == '1') {
                $data['weekends'] = 'true';
            } else {
                $data['weekends'] = 'false';
            }
            $data['minTime'] = Date::parse($practice->minTime)->toTimeString();
            $data['maxTime'] = Date::parse($practice->maxTime)->toTimeString();
            $data['timezone'] = $practice->timezone;
            if (Session::has('pid')) {
                $data['pid'] = Session::get('pid');
                $patient_query = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
                $data['pt_name'] = $patient_query->lastname . ', ' . $patient_query->firstname . ' (DOB: ' . date('m/d/Y', strtotime($patient_query->DOB)) . ') (ID: ' . $patient_query->pid . ')';
            }
            $query = DB::table('calendar')
                ->where('active', '=', 'y')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->where(function($query_array1) use ($provider_id) {
                    $query_array1->where('provider_id', '=', '0')
                    ->orWhere('provider_id', '=', $provider_id);
                })
                ->get();
            $data['visit_type'] = '';
            if ($query->count()) {
                foreach ($query as $row) {
                    if ($row->visit_type !== 'Closed') {
                        $data['visit_type'] .= '<option value="' . $row->visit_type . '">' . $row->visit_type . '</option>';
                    }
                }
            }
            $data['provider_id'] = $provider_id;
            Session::put('provider_id', $provider_id);
        }
        // Just show provider selector
        $data['title'] = 'NOSH ChartingSystem';
        if (Session::has('pid')) {
            $data = array_merge($data, $this->sidebar_build('chart'));
        }
        $data['content'] = '';
        if (Session::get('group_id') != '100') {
            $data['colorlegend'] = 'yes';
        }
        $data['back'] = '<div class="btn-group"><button type="button" class="btn btn-primary">Action</button><button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button>';
        $data['back'] .= '<ul class="dropdown-menu"><li><a href="#">Action</a></li><li><a href="#">Another action</a></li><li><a href="#">Something else here</a></li><li role="separator" class="divider"></li><li><a href="#">Separated link</a></li></ul></div>';
        $data['assets_js'] = $this->assets_js('schedule');
        $data['assets_css'] = $this->assets_css('schedule');
        return view('schedule', $data);
    }

    public function schedule_provider_exceptions(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $return = '';
        $type_arr['0'] = 'All Providers';
        $type_arr = $type_arr + $this->array_providers();
        $dropdown_array = [
            'items_button_text' => $type_arr[$type]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key != $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value,
                    'icon' => 'fa-user',
                    'url' => route('schedule_provider_exceptions', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $query = DB::table('repeat_schedule');
        if ($type !== '0') {
            $query->where('provider_id', '=', $type);
        }
        $result = $query->get();
        $columns = Schema::getColumnListing('repeat_schedule');
        $row_index = $columns[0];
        $edit = $this->access_level('1');
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $provider = DB::table('users')->where('id', '=', $row->provider_id)->first();
                $arr['label'] = '<b>' . ucfirst($row->repeat_day) . '</b> - Start: ' . $row->repeat_start_time . ', End: ' . $row->repeat_end_time;
                if ($type == '0') {
                    $arr['label'] .= '<br>Provider: ' . $provider->displayname;
                }
                if ($type == Session::get('user_id') || Session::get('group_id') == '1') {
                    $arr['edit'] = route('core_form', ['repeat_schedule', $row_index, $row->$row_index, $type]);
                    $arr['delete'] = route('core_action', ['table' => 'repeat_schedule', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'repeat_schedule_list');
        } else {
            $return .= ' None.';
        }
        $dropdown_array1 = [
            'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => 'Add Provider Exception',
            'icon' => 'fa-plus',
            'url' => route('core_form', ['repeat_schedule', $row_index, '0', $type])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        $data['panel_header'] = 'Provider Exceptions for Schedule';
        Session::put('last_page', $request->fullUrl());
        if (Session::get('group_id') == '1') {
            if (Session::has('download_ccda_entire')) {
                $data['download_progress'] = Session::get('download_ccda_entire');
            }
            if (Session::has('download_charts_entire')) {
                $data['download_progress'] = Session::get('download_charts_entire');
            }
        }
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function schedule_visit_types(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $return = '';
        $type_arr = [
            'y' => ['Active', 'fa-check'],
            'n' => ['Inactive', 'fa-times'],
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($value == 'separator') {
                $items[] = [
                    'type' => 'separator'
                ];
            } else {
                if ($key !== $type) {
                    $items[] = [
                        'type' => 'item',
                        'label' => $value[0],
                        'icon' => $value[1],
                        'url' => route('schedule_visit_types', [$key])
                    ];
                }
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $query = DB::table('calendar')
            ->where('active', '=', $type)
            ->where('practice_id', '=', Session::get('practice_id'));
        $result = $query->get();
        $columns = Schema::getColumnListing('calendar');
        $row_index = $columns[0];
        $color_arr = [
            'colorblue' => '#3366cc',
            'colorred' => '#DC143C',
            'colororange' => '#FF6633',
            'coloryellow' => '#CCFF33',
            'colorpurple' => '#9966CC',
            'colorbrown' => '#996633',
            'colorblack' => '#000000'
        ];
        $duration_arr = $this->array_duration();
        $edit = $this->access_level('8');
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<span class="fa-btn"><i class="fa fa-square fa-lg" style="color:' . $color_arr[$row->classname] . '"></i> </span><b>' . $row->visit_type . '</b> - ';
                if ($row->duration !== null) {
                    $arr['label'] .= 'Duration: ' . $duration_arr[$row->duration]. ',';
                }
                if ($row->provider_id !== 0 && $row->provider_id !== null) {
                    $provider = DB::table('users')->where('id', '=', $row->provider_id)->first();
                    $arr['label'] .= '<br>Provider: ' . $provider->displayname;
                } else {
                    $arr['label'] .= '<br>All Providers';
                }
                if ($edit) {
                    $arr['edit'] = route('core_form', ['calendar', $row_index, $row->$row_index, $type]);
                    if ($row->active == 'y') {
                        $arr['inactivate'] = route('core_action', ['table' => 'calendar', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    } else {
                        $arr['reactivate'] = route('core_action', ['table' => 'calendar', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'calendar_list');
        } else {
            $return .= ' None.';
        }
        if ($edit) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => 'Add Visit Type',
                'icon' => 'fa-plus',
                'url' => route('core_form', ['calendar', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Visit Types';
        Session::put('last_page', $request->fullUrl());
        if (Session::get('group_id') == '1') {
            if (Session::has('download_ccda_entire')) {
                $data['download_progress'] = Session::get('download_ccda_entire');
            }
            if (Session::has('download_charts_entire')) {
                $data['download_progress'] = Session::get('download_charts_entire');
            }
        }
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function set_patient(Request $request, $pid)
    {
        $this->setpatient($pid);
        return redirect()->route('patient');
    }

    public function setup(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $result = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $return = '';
        if ($result) {
            if ($result->weight_unit == null) {
                return redirect()->route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'settings']);
            }
            if ($result->billing_street_address1 == null) {
                return redirect()->route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'billing']);
            }
            if ($result->birthday_extension == null) {
                return redirect()->route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'extensions']);
            }
            $state = $this->array_states();
            $unit_arr = [
                'in' => 'Inches',
                'cm' => 'Centimeters',
                'lbs' => 'Pounds',
                'kg' => 'Kilograms',
                'F' => 'Fahrenheit',
                'C' => 'Celcius',
                'n' => 'No',
                'y' => 'Yes',
                '' => 'No',
                'phaxio' => 'Phaxio'
            ];
            $header_arr = [
                'Practice Information' => route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'information']),
                'Practice Settings' => route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'settings']),
                'Billing' => route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'billing']),
                'Extensions' => route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'extensions']),
                'Practice Logo' => route('practice_logo_upload')
            ];
            $info_arr = [
                'Practice Name' => $result->practice_name,
                'Street Address' => $result->street_address1,
                'Street Address Line 2' => $result->street_address2,
                'City' => $result->city,
                'State' => $state[$result->state],
                'Zip' => $result->zip,
                'Phone' => $result->phone,
                'Fax' => $result->fax,
                'Email' => $result->email,
                'Website' => $result->website,
                'Gmail Account' => $result->smtp_user,
                'Patient Portal Address' => $result->patient_portal
            ];
            $encounter_type_arr = $this->array_encounter_type();
            // Remove depreciated encounter types for new encounters
            unset($encounter_type_arr['standardmedical']);
            unset($encounter_type_arr['standardmedical1']);
            $settings_arr = [
                'Primary Contact' => $result->primary_contact,
                'Practice NPI' => $result->npi,
                'Practice Medicare Number' => $result->medicare,
                'Practice Tax ID Number' => $result->tax_id,
                'Default Practice Location' => $result->default_pos_id,
                'Documents Directory' => $result->documents_dir,
                'Weight Unit' => $unit_arr[$result->weight_unit],
                'Height Unit' => $unit_arr[$result->height_unit],
                'Temperature Unit' => $unit_arr[$result->temp_unit],
                'Head Circumference Unit' => $unit_arr[$result->hc_unit],
                'Default Encounter Template' => $encounter_type_arr[$result->encounter_template],
                'Additional Message in Appointment Reminders' => $result->additional_message
            ];
            $billing_arr = [
                'Street Address' => $result->billing_street_address1,
                'Street Address Line 2' => $result->billing_street_address2,
                'City' => $result->billing_city,
                'State' => $state[$result->billing_state],
                'Zip' => $result->billing_zip,
            ];
            $appt_arr = [
                '604800' => '1 week',
                '1209600' => '2 weeks',
                '2629743' => '1 month',
                '5259486' => '2 months',
                '7889229' => '3 months',
                '15778458' => '6 months',
                '31556926' => '1 year'
            ];
            $extensions_arr = [
                'Fax Integration Enabled' => $unit_arr[$result->fax_type],
                'Phaxio API Key' => $result->phaxio_api_key,
                'Phaxio API Secret' => $result->phaxio_api_secret,
                'Birthday Message Enabled' => $unit_arr[$result->birthday_extension],
                'Birthday Message' => $result->birthday_message,
                'Appointment Reminder Enabled' => $unit_arr[$result->appointment_extension],
                'Appointment Interval' => $appt_arr[$result->appointment_interval],
                'Reminder Message' => $result->appointment_message,
                'SMS URL' => $result->sms_url
            ];
            $return = $this->header_build($header_arr, 'Practice Information');
            foreach ($info_arr as $key1 => $value1) {
                if ($value1 !== '' && $value1 !== null) {
                    $return .= '<div class="col-md-3"><b>' . $key1 . '</b></div><div class="col-md-8">' . $value1 . '</div>';
                }
            }
            $return .= '</div></div></div>';
            $return .= $this->header_build($header_arr, 'Practice Settings');
            foreach ($settings_arr as $key2 => $value2) {
                if ($value2 !== '' && $value2 !== null) {
                    $return .= '<div class="col-md-3"><b>' . $key2 . '</b></div><div class="col-md-8">' . $value2 . '</div>';
                }
            }
            $return .= '</div></div></div>';
            $return .= $this->header_build($header_arr, 'Billing');
            foreach ($billing_arr as $key3 => $value3) {
                if ($value3 !== '' && $value3 !== null) {
                    $return .= '<div class="col-md-3"><b>' . $key3 . '</b></div><div class="col-md-8">' . $value3 . '</div>';
                }
            }
            $return .= '</div></div></div>';
            $return .= $this->header_build($header_arr, 'Extensions');
            foreach ($extensions_arr as $key4 => $value4) {
                if ($value4 !== '' && $value4 !== null) {
                    $return .= '<div class="col-md-3"><b>' . $key4 . '</b></div><div class="col-md-8">' . $value4 . '</div>';
                }
            }
            $return .= '</div></div>';
            if ($result->birthday_extension == 'y') {
                $return .= '<div class="alert alert-success">';
                $return .= '<strong>The birthday message sent out will appear like this:</strong><br><br>SMS message:<br>Happy Birthday, {patient first name}, from ';
                $return .= $result->practice_name . '! Call ' . $result->phone . ' if you would like to schedule an appointment with your provider.<br><br>E-mail message:<br>Happy Birthday, {patients first name}, from ';
                $return .= $result->practice_name . '!<br>' . $result->birthday_message . '<br>If you would like to set up an appointment with your provider, please contact us at ';
                $return .= $result->phone . ' or reply to this e-mail at ' . $result->email;
                $return .= '</div>';
            }
            if ($result->appointment_extension == 'y') {
                $return .= '<div class="alert alert-info">';
                $return .= '<strong>The continuing care reminder message sent out will appear like this:</strong><br><br>SMS message:<br>Time for continuing care appointment with ';
                $return .= $result->practice_name . '. Call ' . $result->phone . ' to schedule an appointment or visit' . $result->patient_portal . ' to schedule online.<br><br>E-mail message:<br>Dear,  {patients first name},<br>It is time for your continuing care appointment with ';
                $return .= $result->practice_name . 'Please call us at ' . $result->phone . ' or visit ' . $result->patient_portal . ' to schedule your next appointment at your earliest convenience.<br>';
                $return .= $result->appointment_message . '<br>Thank you,<br>' . $result->practice_name . '<br>Phone: ' . $result->phone . '<br>Email: ' . $result->email;
                $return .= '</div>';
            }
            $return .= '</div>';
            $return .= $this->header_build($header_arr, 'Practice Logo');
            if ($result->practice_logo !== '') {
                if (file_exists(public_path() . '/' . $result->practice_logo)) {
                    $return .= HTML::image($result->practice_logo, 'Practice Logo', array('border' => '0'));
                } else {
                    $return .= 'None';
                }
            } else {
                $return .= 'None';
            }
            $return .= '</div></div></div>';
        } else {
            $return .= ' None.';
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Practice Setup';
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function superquery(Request $request, $type)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        if ($user->reports == null || $user->reports == '') {
            $array = [];
        } else {
            $yaml = $user->reports;
            $formatter = Formatter::make($yaml, Formatter::YAML);
            $array = $formatter->toArray();
        }
        $table = '';
        if ($request->isMethod('post')) {
            $practice_id = Session::get('practice_id');
            $search_field = $request->input('search_field');
            $search_op = $request->input('search_op');
            $search_desc = $request->input('search_desc');
            $search_join = $request->input('search_join');
            $search_active_only = $request->input('search_active_only');
            $search_no_insurance_only = $request->input('search_no_insurance_only');
            $search_gender = $request->input('search_gender');
            if ($request->input('submit') !== 'run') {
                $message = 'Report updated';
                if ($type == '0') {
                    $type = $request->input('title');
                    $message = 'Report created';
                }
                if ($type !== $request->input('title')) {
                    $old_type = $type;
                    $type = $request->input('title');
                    $array[$type] = $array[$old_type];
                    unset($array[$old_type]);
                }
                $array[$type]['search_active_only'] = $search_active_only;
                $array[$type]['search_no_insurance_only'] = $search_no_insurance_only;
                $array[$type]['search_gender'] = $search_gender;
                 for ($j = 0; $j < count($search_field); $j++) {
                    $array[$type][$j] = [
                        'search_field' => $search_field[$j],
                        'search_op' => $search_op[$j],
                        'search_desc' => $search_desc[$j],
                        'search_join' => $search_join[$j]
                    ];
                }
                $formatter1 = Formatter::make($array, Formatter::ARR);
                $data['reports'] = $formatter1->toYaml();
                DB::table('users')->where('id', '=', Session::get('user_id'))->update($data);
                Session::put('message_action', $message);
                if ($request->input('submit') == 'save') {
                    return redirect()->route('superquery_list');
                }
            } else {
                $array = [];
                $type = $request->input('title');
                $array[$type]['search_active_only'] = $search_active_only;
                $array[$type]['search_no_insurance_only'] = $search_no_insurance_only;
                $array[$type]['search_gender'] = $search_gender;
                 for ($j = 0; $j < count($search_field); $j++) {
                    $array[$type][$j] = [
                        'search_field' => $search_field[$j],
                        'search_op' => $search_op[$j],
                        'search_desc' => $search_desc[$j],
                        'search_join' => $search_join[$j]
                    ];
                }
            }
            $values = [];
            $item = [];
            $item['title'] = $type;
            $data['panel_header'] = $type;
            foreach ($array[$type] as $query_item_k => $query_item_v) {
                if ($query_item_k === 'search_active_only' || $query_item_k === 'search_no_insurance_only' || $query_item_k === 'search_gender') {
                    $item[$query_item_k] = $query_item_v;
                } else {
                    $values[] = $query_item_v;
                }
            }
            $query_text1 = DB::table('demographics')
                ->join('demographics_relate', 'demographics.pid', '=', 'demographics_relate.pid')
                ->select('demographics.pid','demographics.lastname','demographics.firstname','demographics.DOB')
                ->distinct()
                ->where('demographics_relate.practice_id', '=', $practice_id);
            for ($i = 0; $i < count($search_field); $i++) {
                if (isset($search_field[$i])) {
                    if ($search_field[$i] == 'age') {
                        $query_text1->where(function($query_array0) use ($search_op, $search_desc, $search_join, $i) {
                            $ago = strtotime($search_desc[$i] . " years ago");
                            $unix_target1 = $ago - 15778463;
                            $unix_target2 = $ago + 15778463;
                            $target1 = date('Y-m-d 00:00:00', $unix_target1);
                            $target2 = date('Y-m-d 00:00:00', $unix_target2);
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array0->whereBetween('demographics.DOB', [$target1, $target2]);
                                    } else {
                                        $query_array0->orWhereBetween('demographics.DOB', [$target1, $target2]);
                                    }
                                } else {
                                    $query_array0->whereBetween('demographics.DOB', [$target1, $target2]);
                                }
                            }
                            if($search_op[$i] == 'greater than') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array0->where('demographics.DOB', '<', $target1);
                                    } else {
                                        $query_array0->orWhere('demographics.DOB', '<', $target1);
                                    }
                                } else {
                                    $query_array0->where('demographics.DOB', '<', $target1);
                                }
                            }
                            if($search_op[$i] == 'less than') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array0->where('demographics.DOB', '>', $target2);
                                    } else {
                                        $query_array0->orWhere('demographics.DOB', '>', $target2);
                                    }
                                } else {
                                    $query_array0->where('demographics.DOB', '>', $target2);
                                }
                            }
                        });
                    }
                    if($search_field[$i] == 'insurance') {
                        $query_text1->join('insurance', 'insurance.pid', '=', 'demographics.pid');
                        $query_text1->where(function($query_array1) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array1->where('insurance.insurance_order', '=', 'Primary')
                                            ->where('insurance.insurance_plan_active', '=', 'Yes')
                                            ->where('insurance.insurance_plan_name', '=',  $search_desc[$i]);
                                    } else {
                                        $query_array1->where('insurance.insurance_order', '=', 'Primary')
                                            ->where('insurance.insurance_plan_active', '=', 'Yes')
                                            ->orWhere('insurance.insurance_plan_name', '=',  $search_desc[$i]);
                                    }
                                } else {
                                    $query_array1->where('insurance.insurance_order', '=', 'Primary')
                                        ->where('insurance.insurance_plan_active', '=', 'Yes')
                                        ->where('insurance.insurance_plan_name', '=',  $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array1->where('insurance.insurance_order', '=', 'Primary')
                                            ->where('insurance.insurance_plan_active', '=', 'Yes')
                                            ->where('insurance.insurance_plan_name', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array1->where('insurance.insurance_order', '=', 'Primary')
                                            ->where('insurance.insurance_plan_active', '=', 'Yes')
                                            ->orWhere('insurance.insurance_plan_name', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array1->where('insurance.insurance_order', '=', 'Primary')
                                        ->where('insurance.insurance_plan_active', '=', 'Yes')
                                        ->where('insurance.insurance_plan_name', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                        });
                    }
                    if($search_field[$i] == 'issue') {
                        $query_text1->join('issues', 'issues.pid', '=', 'demographics.pid');
                        $query_text1->where(function($query_array2) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array2->where('issues.issue', '=', $search_desc[$i]);
                                    } else {
                                        $query_array2->orWhere('issues.issue', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array2->where('issues.issue', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array2->where('issues.issue', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array2->orWhere('issues.issue', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array2->where('issues.issue', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] != "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array2->where('issues.issue', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array2->orWhere('issues.issue', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array2->where('issues.issue', '!=', $search_desc[$i]);
                                }
                            }
                            $query_array2->where('issues.issue_date_inactive', '=', '0000-00-00 00:00:00');
                        });
                    }
                    if($search_field[$i] == 'billing') {
                        $query_text1->join('billing_core', 'billing_core.pid', '=', 'demographics.pid');
                        $query_text1->where(function($query_array3) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array3->where('billing_core.cpt', '=', $search_desc[$i]);
                                    } else {
                                        $query_array3->orWhere('billing_core.cpt', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array3->where('billing_core.cpt', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] != "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array3->where('billing_core.cpt', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array3->orWhere('billing_core.cpt', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array3->where('billing_core.cpt', '!=', $search_desc[$i]);
                                }
                            }
                        });
                    }
                    if($search_field[$i] == 'rxl_medication') {
                        $query_text1->join('rx_list', 'rx_list.pid', '=', 'demographics.pid');
                        $query_text1->where(function($query_array4) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array4->where('rx_list.rxl_medication', '=', $search_desc[$i]);
                                    } else {
                                        $query_array4->orWhere('rx_list.rxl_medication', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array4->where('rx_list.rxl_medication', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array4->where('rx_list.rxl_medication', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array4->orWhere('rx_list.rxl_medication', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array4->where('rx_list.rxl_medication', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array4->where('rx_list.rxl_medication', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array4->orWhere('rx_list.rxl_medication', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array4->where('rx_list.rxl_medication', '!=', $search_desc[$i]);
                                }
                            }
                            $query_array4->where('rx_list.rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rx_list.rxl_date_old', '=', '0000-00-00 00:00:00');
                        });
                    }
                    if($search_field[$i] == 'imm_immunization') {
                        $query_text1->join('immunizations', 'immunizations.pid', '=', 'demographics.pid');
                        $query_text1->where(function($query_array5) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array5->where('immunizations.imm_immunization', '=', $search_desc[$i]);
                                    } else {
                                        $query_array5->orWhere('immunizations.imm_immunization', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array5->where('immunizations.imm_immunization', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array5->where('immunizations.imm_immunization', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array5->orWhere('immunizations.imm_immunization', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array5->where('immunizations.imm_immunization', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array5->where('immunizations.imm_immunization', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array5->orWhere('immunizations.imm_immunization', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array5->where('immunizations.imm_immunization', '!=', $search_desc[$i]);
                                }
                            }
                        });
                    }
                    if($search_field[$i] == 'sup_supplement') {
                        $query_text1->join('sup_list', 'sup_list.pid', '=', 'demographics.pid');
                        $query_text1->where(function($query_array6) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array6->where('sup_list.sup_supplement', '=', $search_desc[$i]);
                                    } else {
                                        $query_array6->orWhere('sup_list.sup_supplement', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array6->where('sup_list.sup_supplement', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array6->where('sup_list.sup_supplement', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array6->orWhere('sup_list.sup_supplement', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array6->where('sup_list.sup_supplement', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array6->where('sup_list.sup_supplement', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array6->orWhere('sup_list.sup_supplement', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array6->where('sup_list.sup_supplement', '!=', $search_desc[$i]);
                                }
                            }
                            $query_array6->where('sup_list.sup_date_inactive', '=', '0000-00-00 00:00:00');
                        });
                    }
                    if($search_field[$i] == 'zip') {
                        $query_text1->where(function($query_array7) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array7->where('demographics.zip', '=', $search_desc[$i]);
                                    } else {
                                        $query_array7->orWhere('demographics.zip', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array7->where('demographics.zip', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array7->where('demographics.zip', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array7->orWhere('demographics.zip', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array7->where('demographics.zip', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] != "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array7->where('demographics.zip', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array7->orWhere('demographics.zip', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array7->where('demographics.zip', '!=', $search_desc[$i]);
                                }
                            }
                        });
                    }
                    if($search_field[$i] == 'city') {
                        $query_text1->where(function($query_array8) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] != "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array8->where('demographics.city', '=', $search_desc[$i]);
                                    } else {
                                        $query_array8->orWhere('demographics.city', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array8->where('demographics.city', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array8->where('demographics.city', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array8->orWhere('demographics.city', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array8->where('demographics.city', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array8->where('demographics.city', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array8->orWhere('demographics.city', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array8->where('demographics.city', '!=', $search_desc[$i]);
                                }
                            }
                        });
                    }
                    if($search_field[$i] == 'month') {
                        $query_text1->where(function($query_array9) use ($search_op, $search_desc, $search_join, $i) {
                            $query_date = date('-m-', strtotime($search_desc[$i]));
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array9->where('demographics.DOB', 'LIKE', "%$query_date%");
                                    } else {
                                        $query_array9->orWhere('demographics.DOB', 'LIKE', "%$query_date%");
                                    }
                                } else {
                                    $query_array9->where('demographics.DOB', 'LIKE', "%$query_date%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array9->where('demographics.DOB', 'NOT LIKE', "%$query_date%");
                                    } else {
                                        $query_array9->orWhere('demographics.DOB', 'NOT LIKE', "%$query_date%");
                                    }
                                } else {
                                    $query_array9->where('demographics.DOB', 'NOT LIKE', "%$query_date%");
                                }
                            }
                        });
                    }
                    if ($search_field[$i] == 'bp_systolic') {
                        $query_text1->join('vitals', function($join){
                            $join->on('vitals.pid', '=', 'demographics.pid');
                            $join->whereRaw("vitals.vitals_date = (SELECT MAX(z.vitals_date) FROM vitals as z WHERE z.pid = vitals.pid AND z.bp_systolic != '')");
                        });
                        $query_text1->where(function($query_array10) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array10->where('vitals.bp_systolic', '=', $search_desc[$i]);
                                    } else {
                                        $query_array10->orWhere('vitals.bp_systolic', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array10->where('vitals.bp_systolic', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'greater than') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array10->where('vitals.bp_systolic', '>', $search_desc[$i]);
                                    } else {
                                        $query_array10->orWhere('vitals.bp_systolic', '>', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array10->where('vitals.bp_systolic', '>', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'less than') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array10->where('vitals.bp_systolic', '<', $search_desc[$i]);
                                    } else {
                                        $query_array10->orWhere('vitals.bp_systolic', '<', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array10->where('vitals.bp_systolic', '<', $search_desc[$i]);
                                }
                            }
                        });
                    }
                    if ($search_field[$i] == 'bp_diastolic') {
                        $query_text1->join('vitals', function($join){
                            $join->on('vitals.pid', '=', 'demographics.pid');
                            $join->whereRaw("vitals.vitals_date = (SELECT MAX(z.vitals_date) FROM vitals as z WHERE z.pid = vitals.pid AND z.bp_diastolic != '')");
                        });
                        $query_text1->where(function($query_array11) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array11->where('vitals.bp_diastolic', '=', $search_desc[$i]);
                                    } else {
                                        $query_array11->orWhere('vitals.bp_diastolic', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array11->where('vitals.bp_diastolic', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'greater than') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array11->where('vitals.bp_diastolic', '>', $search_desc[$i]);
                                    } else {
                                        $query_array11->orWhere('vitals.bp_diastolic', '>', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array11->where('vitals.bp_diastolic', '>', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'less than') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array11->where('vitals.bp_diastolic', '<', $search_desc[$i]);
                                    } else {
                                        $query_array11->orWhere('vitals.bp_diastolic', '<', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array11->where('vitals.bp_diastolic', '<', $search_desc[$i]);
                                }
                            }
                        });
                    }
                    if($search_field[$i] == 'test_name') {
                        $query_text1->join('tests as tests1', 'tests1.pid', '=', 'demographics.pid');
                        $query_text1->where(function($query_array12) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array12->where('tests1.test_name', '=', $search_desc[$i]);
                                    } else {
                                        $query_array12->orWhere('tests1.test_name', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array12->where('tests1.test_name', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array12->where('tests1.test_name', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array12->orWhere('tests1.test_name', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array12->where('tests1.test_name', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array12->where('tests1.test_name', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array12->orWhere('tests1.test_name', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array12->where('tests1.test_name', '!=', $search_desc[$i]);
                                }
                            }
                        });
                    }
                    if($search_field[$i] == 'test_code') {
                        $query_text1->join('tests as tests2', 'tests2.pid', '=', 'demographics.pid');
                        $query_text1->where(function($query_array13) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array13->where('tests2.test_code', '=', $search_desc[$i]);
                                    } else {
                                        $query_array13->orWhere('tests2.test_code', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array13->where('tests2.test_code', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'contains') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array13->where('tests2.test_code', 'LIKE', "%$search_desc[$i]%");
                                    } else {
                                        $query_array13->orWhere('tests2.test_code', 'LIKE', "%$search_desc[$i]%");
                                    }
                                } else {
                                    $query_array13->where('tests2.test_code', 'LIKE', "%$search_desc[$i]%");
                                }
                            }
                            if($search_op[$i] == 'not equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array13->where('tests2.test_code', '!=', $search_desc[$i]);
                                    } else {
                                        $query_array13->orWhere('tests2.test_code', '!=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array13->where('tests2.test_code', '!=', $search_desc[$i]);
                                }
                            }
                        });
                    }
                    if ($search_field[$i] == 'test_result') {
                        $query_text1->join('tests as tests3', function($join){
                            $join->on('tests3.pid', '=', 'demographics.pid');
                            $join->whereRaw("tests3.test_datetime = (SELECT MAX(z.test_datetime) FROM tests as z WHERE z.pid = tests3.pid AND z.test_result != '')");
                        });
                        $query_text1->where(function($query_array14) use ($search_op, $search_desc, $search_join, $i) {
                            if($search_op[$i] == 'equal') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array14->where('tests3.test_result', '=', $search_desc[$i]);
                                    } else {
                                        $query_array14->orWhere('tests3.test_result', '=', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array14->where('tests3.test_result', '=', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'greater than') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array14->where('tests3.test_result', '>', $search_desc[$i]);
                                    } else {
                                        $query_array14->orWhere('tests3.test_result', '>', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array14->where('tests3.test_result', '>', $search_desc[$i]);
                                }
                            }
                            if($search_op[$i] == 'less than') {
                                if($search_join[$i] !== "start") {
                                    if($search_join[$i] == 'AND') {
                                        $query_array14->where('tests3.test_result', '<', $search_desc[$i]);
                                    } else {
                                        $query_array14->orWhere('tests3.test_result', '<', $search_desc[$i]);
                                    }
                                } else {
                                    $query_array14->where('tests3.test_result', '<', $search_desc[$i]);
                                }
                            }
                        });
                    }
                }
            }
            if($search_active_only == "Yes") {
                $query_text1->where('demographics.active', '=', '1');
            }
            if($search_no_insurance_only == "Yes") {
                $query_text1->leftJoin('insurance as insurance1', 'insurance1.pid', '=', 'demographics.pid')->whereNull('insurance1.pid');
            }
            if($search_gender == "m" || $search_gender == "f" || $search_gender == "u") {
                $query_text1->where('demographics.sex', '=', $search_gender);
            }
            $result = $query_text1->get();
            if ($result->count()) {
                $list_array = [];
                foreach ($result as $row) {
                    $dob = date('m/d/Y', strtotime($row->DOB));
                    $arr['label'] = $row->lastname . ', ' . $row->firstname . ' (DOB: ' . $dob . ') (ID: ' . $row->pid . ')';
                    $arr['view'] = route('set_patient', [$row->pid]);
                    $list_array[] = $arr;
                }
                $table = '<h4>Results</h4>';
                $table .= $this->result_build($list_array, 'superquery_list');
            } else {
                $table = 'No result';
            }
        } else {
            $values = [];
            $values[] = [
                'search_field' => null,
                'search_op' => null,
                'search_desc' => null
            ];
            $item = [
                'search_active_only' => null,
                'search_no_insurance_only' => null,
                'search_gender' => 'both',
                'title' => null
            ];
            $data['panel_header'] = 'New Report';
            if ($type !== '0') {
                $values = [];
                $item = [];
                $item['title'] = $type;
                foreach ($array[$type] as $query_item_k => $query_item_v) {
                    if ($query_item_k === 'search_active_only' || $query_item_k === 'search_no_insurance_only' || $query_item_k === 'search_gender') {
                        $item[$query_item_k] = $query_item_v;
                    } else {
                        $values[] = $query_item_v;
                    }
                }
                $data['panel_header'] = $type;
            }
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
        }
        $intro = $this->query_build($values);
        $gender_arr['both'] = 'All';
        $gender_arr = array_merge($gender_arr, $this->array_gender());
        $items[] = [
            'name' => 'search_active_only',
            'label' => 'Active Patients Only',
            'type' => 'checkbox',
            'value' => 'Yes',
            'default_value' => $item['search_active_only']
        ];
        $items[] = [
            'name' => 'search_no_insurance_only',
            'label' => 'Patients Without Insurance',
            'type' => 'checkbox',
            'value' => 'Yes',
            'default_value' => $item['search_no_insurance_only']
        ];
        $items[] = [
            'name' => 'search_gender',
            'label' => 'Gender',
            'type' => 'select',
            'required' => true,
            'select_items' => $gender_arr,
            'default_value' => $item['search_gender']
        ];
        $items[] = [
            'name' => 'title',
            'label' => 'Report Title',
            'type' => 'text',
            'required' => true,
            'default_value' => $item['title']
        ];
        $form_array = [
            'form_id' => 'super_query_form',
            'action' => route('superquery', [$type]),
            'items' => $items,
            'save_button_label' => 'Save and Run Report',
            'intro' => $intro,
            'origin' => route('superquery_list'),
            'add_save_button' => [
                'run' => 'Run Report',
                'save' => 'Save Only'
            ]
        ];
        $data['content'] = $this->form_build($form_array);
        $data['content'] .= $table;
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function superquery_delete(Request $request, $type)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $yaml = $user->reports;
        $formatter = Formatter::make($yaml, Formatter::YAML);
        $array = $formatter->toArray();
        unset($array[$type]);
        $formatter1 = Formatter::make($array, Formatter::ARR);
        $data['reports'] = $formatter1->toYaml();
        DB::table('users')->where('id', '=', Session::get('user_id'))->update($data);
        Session::put('message_action', 'Report deleted');
        return redirect(Session::get('last_page'));
    }

    public function superquery_hedis(Request $request, $type)
    {
        $html = '';
        $type_arr = [
            'all' => ['All', 'fa-calendar'],
            'year' => ['Past Year', 'fa-calendar-o'],
            'spec' => ['Specified Time', 'fa-clock-o']
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('superquery_hedis', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['panel_header'] = 'HEDIS Report';
        if ($request->isMethod('post')) {
            if ($type == 'spec') {
                $type = date('m/d/Y', strtotime($request->input('time')));
            }
            $items = [];
            $items[] = [
                'name' => 'time',
                'label' => 'Choose Time Period for Audit to Begin',
                'type' => 'date',
                'required' => true,
                'default_value' => $request->input('time')
            ];
            $form_array = [
                'form_id' => 'superquery_hedis_form',
                'action' => route('superquery_hedis', ['spec']),
                'items' => $items,
                'save_button_label' => 'Run Report',
                'origin' => route('superquery_list')
            ];
            $html .= $this->form_build($form_array);
        } else {
            if ($type == 'spec') {
                $default_date = date('Y-m-d');
                if (Session::has('hedis_query_date')) {
                    $type = Session::get('hedis_query_date');
                    Session::forget('hedis_query_date');
                    $default_date = date('Y-m-d', strtotime($type));
                }
                $items = [];
                $items[] = [
                    'name' => 'time',
                    'label' => 'Choose Time Period for Audit to Begin',
                    'type' => 'date',
                    'required' => true,
                    'default_value' => date('Y-m-d')
                ];
                $form_array = [
                    'form_id' => 'superquery_hedis_form',
                    'action' => route('superquery_hedis', [$type]),
                    'items' => $items,
                    'save_button_label' => 'Run Report',
                    'origin' => route('superquery_list')
                ];
                $html .= $this->form_build($form_array);
            }
        }
        if ($type !== 'spec') {
            if ($type == 'all' || $type == 'year') {
                Session::put('hedis_query', $type);
            } else {
                Session::put('hedis_query', 'spec');
                Session::put('hedis_query_date', $type);
            }
            $demographics = DB::table('demographics_relate')->where('practice_id', '=', Session::get('practice_id'))->get();
            if ($demographics->count()) {
                $html .= '<h4>HEDIS Audit Results</h4>';
                $html .= '<div class="table-responsive"><table class="table table-striped">';
                $html .= '<thead><tr><th>Measure</th><th>Description</th><th>Result</th><th>Rectify</th></tr></thead><tbody>';
                $arr = [];
                $total_count = 0;
                foreach ($demographics as $demographic) {
                    $arr[$demographic->pid] = $this->hedis_audit($type, 'office', $demographic->pid);
                    $total_count++;
                }
                $measures = ['aba','wcc','cis','ima','hpv','lsc','bcs','ccs','col','chl','gso','cwp','uri','aab','spr','pce','asm','amr','cmc','pbh','cbp','cdc','art','omw','lbp','amm','add'];
                $counter = [];
                foreach ($measures as $measure) {
                    $counter[$measure]['count'] = 0;
                    $counter[$measure]['rectify'] = '';
                    if ($measure != 'cwp' && $measure != 'uri' && $measure != 'aab' && $measure != 'pce' && $measure != 'lbp') {
                        $counter[$measure]['goal'] = 0;
                    } else {
                        if ($measure == 'cwp') {
                            $counter[$measure]['test'] = 0;
                            $counter[$measure]['abx'] = 0;
                            $counter[$measure]['abx_no_test'] = 0;
                        }
                        if ($measure == 'uri' || $measure == 'aab') {
                            $counter[$measure]['abx'] = 0;
                        }
                        if ($measure == 'pce') {
                            $counter[$measure]['tx'] = 0;
                        }
                        if ($measure == 'lbp') {
                            $counter[$measure]['no_rad'] = 0;
                        }
                    }
                }
                foreach ($arr as $pid => $audit) {
                    $patient = DB::table('demographics')->where('pid', '=', $pid)->first();
                    $dob = date('m/d/Y', strtotime($patient->DOB));
                    $name = $patient->lastname . ', ' . $patient->firstname . ' (DOB: ' . $dob . ') (ID: ' . $patient->pid . ')';
                    $rectify = '<a href="' . route('superquery_patient', ['care_opportunities', $pid, 'hedis']) . '" class="btn fa-btn" role="button" data-toggle="tooltip" title="Open Chart"><i class="fa fa-arrow-right fa-lg"></i> ' . $name . '</a><br>';
                    foreach ($audit as $item => $row) {
                        $counter[$item]['count']++;
                        if ($item != 'cwp' && $item != 'uri' && $item != 'aab' && $item != 'pce' && $item != 'lbp') {
                            if($row['goal'] == 'y') {
                                $counter[$item]['goal']++;
                            } else {
                                $counter[$item]['rectify'] .= $rectify;
                            }
                        } else {
                            if ($item == 'cwp') {
                                $counter[$item]['test'] += $row['test'];
                                $counter[$item]['abx'] += $row['abx'];
                                $counter[$item]['abx_no_test'] += $row['abx_no_test'];
                            }
                            if ($item == 'uri' || $item == 'aab') {
                                $counter[$item]['abx'] += $row['abx'];
                            }
                            if ($item == 'pce') {
                                $counter[$item]['tx'] += $row['tx'];
                            }
                            if ($item == 'lbp') {
                                $counter[$item]['no_rad'] += $row['no_rad'];
                            }
                        }
                    }
                }
                foreach ($measures as $measure1) {
                    if ($measure1 != 'cwp' && $measure1 != 'uri' && $measure1 != 'aab' && $measure1 != 'pce' && $measure1 != 'lbp') {
                        if ($counter[$measure1]['count'] != 0) {
                            $counter[$measure1]['percent_goal'] = round($counter[$measure1]['goal']/$counter[$measure1]['count']*100);
                        } else {
                            $counter[$measure1]['percent_goal'] = 0;
                        }
                    } else {
                        if ($measure1 == 'cwp') {
                            if ($counter[$measure1]['count'] != 0) {
                                $counter[$measure1]['percent_test'] = round($counter[$measure1]['test']/$counter[$measure1]['count']*100);
                                $counter[$measure1]['percent_abx'] = round($counter[$measure1]['abx']/$counter[$measure1]['count']*100);
                                $counter[$measure1]['percent_abx_no_test'] = round($counter[$measure1]['abx_no_test']/$counter[$measure1]['count']*100);
                            } else {
                                $counter[$measure1]['percent_test'] = 0;
                                $counter[$measure1]['percent_abx'] = 0;
                                $counter[$measure1]['percent_abx_no_test'] = 0;
                            }
                        }
                        if ($measure1 == 'uri' || $measure1 == 'aab') {
                            if ($counter[$measure1]['count'] != 0) {
                                $counter[$measure1]['percent_abx'] = round($counter[$measure1]['abx']/$counter[$measure1]['count']*100);
                            } else {
                                $counter[$measure1]['percent_abx'] = 0;
                            }
                        }
                        if ($measure1 == 'pce') {
                            if ($counter[$measure1]['count'] != 0) {
                                $counter[$measure1]['percent_tx'] = round($counter[$measure1]['tx']/$counter[$measure1]['count']*100);
                            } else {
                                $counter[$measure1]['percent_tx'] = 0;
                            }
                        }
                        if ($measure1 == 'lbp') {
                            if ($counter[$measure1]['count'] != 0) {
                                $counter[$measure1]['percent_no_rad'] = round($counter[$measure1]['no_rad']/$counter[$measure1]['count']*100);
                            } else {
                                $counter[$measure1]['percent_no_rad'] = 0;
                            }
                        }
                    }
                }
                // ABA
                $html .= '<tr><td>Adult BMI Assessment</td><td>Percentage of members 18-74 who had their BMI and weight documented at an outpatient visit</td><td>' . $counter['aba']['percent_goal'] .'%</td><td>' . $counter['aba']['rectify'] .'</td></tr>';
                // WCC
                $html .= '<tr><td>Weight Assessment and Counseling for Nutrition and Physical Activity for Children and Adolescents</td><td>Percentage of members 3-17 who had an outpatient visit with a PCP or OB/GYN which included evidence of BMI documentation with corresponding height&weight, counseling for nutrition and/or counseling for physical activity</td><td>' . $counter['wcc']['percent_goal'] .'%</td><td>' . $counter['wcc']['rectify'] .'</td></tr>';
                // CIS
                $html .= '<tr><td>Childhood Immunization Status</td><td>Percentage of children two years of age with appropriate childhood immunizations</td><td>' . $counter['cis']['percent_goal'] .'%</td><td>' . $counter['cis']['rectify'] .'</td></tr>';
                // IMA
                $html .= '<tr><td>Immunizations for Adolescents</td><td>Percentage of adolescents 13 years of age with appropriate immunizations</td><td>' . $counter['ima']['percent_goal'] .'%</td><td>' . $counter['ima']['rectify'] .'</td></tr>';
                // HPV
                $html .= '<tr><td>Human Papillomavirus Vaccine for Female Adolescents</td><td>Percentage of female adolescents 13 years of age who had three doses of HPV vaccine between 9th and 13th birthdays</td><td>' . $counter['hpv']['percent_goal'] .'%</td><td>' . $counter['hpv']['rectify'] .'</td></tr>';
                // LSC
                $html .= '<tr><td>Lead Screening in Children</td><td>Percentage of children 2 years of age screened for lead poisoning</td><td>' . $counter['lsc']['percent_goal'] .'%</td><td>' . $counter['lsc']['rectify'] .'</td></tr>';
                // BCS
                $html .= '<tr><td>Breast Cancer Screening</td><td>Percentage of women 40-69 years of age who had a mammogram</td><td>' . $counter['bcs']['percent_goal'] .'%</td><td>' . $counter['bcs']['rectify'] .'</td></tr>';
                // CCS
                $html .= '<tr><td>Cervical Cancer Screening</td><td>Percentage of women 21-64 years of age who had a Pap test</td><td>' . $counter['ccs']['percent_goal'] .'%</td><td>' . $counter['ccs']['rectify'] .'</td></tr>';
                // COL
                $html .= '<tr><td>Colorectal Cancer Screening</td><td>Percentage of members 50-75 years of age who had appropriate screening for colorectal cancer</td><td>' . $counter['col']['percent_goal'] .'%</td><td>' . $counter['col']['rectify'] .'</td></tr>';
                // CHL
                $html .= '<tr><td>Chlamydia Screening in Women</td><td>Sexually active women 16-24 with annual chlamydia screening</td><td>' . $counter['chl']['percent_goal'] .'%</td><td>' . $counter['chl']['rectify'] .'</td></tr>';
                // GSO
                $html .= '<tr><td>Glaucoma Screening Older Adults</td><td>Sexually active women 1Percentage of members 65 or older who received a glaucoma eye exam (no prior history)</td><td>' . $counter['gso']['percent_goal'] .'%</td><td>' . $counter['gso']['rectify'] .'</td></tr>';
                // CWP
                $html .= '<tr><td>Appropriate Testing for Children With Pharyngitis</td><td>Percentage of children ages 2-18 diagnosed with pharyngitis, prescribed an antibiotic and tested for strep</td><td>';
                $html .= '<ul><li>Percentage tested: ' . $counter['cwp']['percent_test'] . '%</li>';
                $html .= '<li>Percentage treated with antibiotics: ' . $counter['cwp']['percent_abx'] . '%</li>';
                $html .= '<li>Percentage treated with antibiotics without testing: ' . $counter['cwp']['percent_abx_no_test'] . '%</li></ul>';
                $html .= '</td><td>' . $counter['cwp']['rectify'] .'</td></tr>';
                // URI
                $html .= '<tr><td>Appropriate Treatment for Children With Upper Respiratory Infection</td><td>Percentage of children 3 months-18 years diagnosed with ONLY upper respiratory infection diagnosis and NOT dispensed an antibiotic</td><td>';
                $html .= '<ul><li>Percentage treated with antibiotics: ' . $counter['uri']['percent_abx'] . '%</li></ul>';
                $html .= '</td><td>' . $counter['uri']['rectify'] .'</td></tr>';
                // AAB
                $html .= '<tr><td>Avoidance of Antibiotic Treatment for Adults with Acute Bronchitis</td><td>Percentage of adults 18-64 years diagnosed with acute bronchitis who were NOT dispensed an antibiotic</td><td>';
                $html .= '<ul><li>Percentage treated with antibiotics: ' . $counter['aab']['percent_abx'] . '%</li></ul>';
                $html .= '</td><td>' . $counter['aab']['rectify'] .'</td></tr>';
                // SPR
                $html .= '<tr><td>Use of Spirometry Testing in the Assessment and Diagnosis of COPD</td><td>Percentage of members age 40 and older w/ COPD and spirometry testing</td><td>' . $counter['spr']['percent_goal'] .'%</td><td>' . $counter['spr']['rectify'] .'</td></tr>';
                // PCE
                $html .= '<tr><td>Pharmacotherapy Management of COPD Exacerbation</td><td>Members dispensed systemic corticosteroid & bronchodilator after COPD exacerbation</td><td>';
                $html .= '<ul><li>Percentage treated for COPD exacerbations: ' . $counter['pce']['percent_tx'] . '%</li></ul>';
                $html .= '</td><td>' . $counter['pce']['rectify'] .'</td></tr>';
                // ASM and AMR
                $html .= '<tr><td>Use of Appropriate Medications for People with Asthma</td><td>Percentage of members 5-56 years with asthma and appropriately prescribed medications</td><td>' . $counter['asm']['percent_goal'] .'%</td><td>' . $counter['asm']['rectify'] .'</td></tr>';
                $html .= '<tr><td>Asthma Medication Ratio</td><td>Percentage of members 5-64 years with asthma who had a ratio of controller medications to total asthma medications of .5 or greater</td><td>' . $counter['amr']['percent_goal'] .'%</td><td>' . $counter['amr']['rectify'] .'</td></tr>';
                // CMC and PBH
                $html .= '<tr><td>Cholesterol Management for Patients With Cardiovascular Conditions</td><td>Percentage of members 18-75 who were discharged alive for acute myocardial infarction, coronary artery bypass graft or percutaneous coronary interventions, or who had a diagnosis of ischemic vascular diasease who had LDL-C screenings</td><td>' . $counter['cmc']['percent_goal'] .'%</td><td>' . $counter['cmc']['rectify'] .'</td></tr>';
                $html .= '<tr><td>Persistence of Beta-Blocker Treatment After a Heart Attack</td><td>Percentage of members 18 years or older, discharged with a diagnosis of acute myocardial infarction and received a beta-blocker treatment for 6 months</td><td>' . $counter['pbh']['percent_goal'] .'%</td><td>' . $counter['pbh']['rectify'] .'</td></tr>';
                // CBP
                $html .= '<tr><td>Controlling High Blood Pressure</td><td>Percentage of members 18-85 with a diagnosis of hypertension and whose blood pressure was controlled</td><td>' . $counter['cbp']['percent_goal'] .'%</td><td>' . $counter['cbp']['rectify'] .'</td></tr>';
                // CDC
                $html .= '<tr><td>Comprehensive Diabetes Care</td><td>The percentage of members 18-75 years of age with diabetes (type 1 or type 2) who had each of the following: 1) HbA1c, 2) LDL Screening, 3) Nephropathy Screening, 4) Retinal Eye Exam, 5) Blood Pressure control.</td><td>' . $counter['cdc']['percent_goal'] .'%</td><td>' . $counter['cdc']['rectify'] .'</td></tr>';
                // ART
                $html .= '<tr><td>Disease Modifying Anti-Rheumatic Drug Therapy for Rheumatoid Arthritis</td><td>Percentage of members w/ RA dispensed a DMARD</td><td>' . $counter['art']['percent_goal'] .'%</td><td>' . $counter['art']['rectify'] .'</td></tr>';
                // OMW
                $html .= '<tr><td>Osteoporosis Management in Women Who Had Fracture</td><td>Percentage of women 67 years or older who suffered a fracture and then a DEXA scan or osteoporosis medication within 6 months of incident</td><td>' . $counter['omw']['percent_goal'] .'%</td><td>' . $counter['omw']['rectify'] .'</td></tr>';
                // LBP
                $html .= '<tr><td>Osteoporosis Management in Women Who Had Fracture</td><td>Percentage of members with a primary diagnosis of low back pain who did not have an imaging study within 28 days of diagnosis</td><td>';
                $html .= '<ul><li>Percentage of instances where no imaging study was performed for a diagnosis of low back pain: ' . $counter['lbp']['percent_no_rad'] . '%</li></ul>';
                $html .= '</td><td>' . $counter['lbp']['rectify'] .'</td></tr>';
                // AMM
                $html .= '<tr><td>Antidepressant Medication Management</td><td>Percentage of members 18 years or older diagnosed with depression and treated with antidepressant meds</td><td>' . $counter['amm']['percent_goal'] .'%</td><td>' . $counter['amm']['rectify'] .'</td></tr>';
                // ADD
                $html .= '<tr><td>Follow-Up Care for Children Prescribed ADHD Medication</td><td>Percentage of children 6-12 with newly diagnosed ADHD who received the appropriate follow-up treatment and medication</td><td>' . $counter['add']['percent_goal'] .'%</td><td>' . $counter['add']['rectify'] .'</td></tr>';
                $html .= '</tbody></table>';
            } else {
                $html .= 'No results.';
            }
        }
        $dropdown_array1 = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => Session::get('last_page')
        ];
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['content'] = $html;
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function superquery_list(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $return = '<div class="alert alert-success">';
        $return .= $this->practice_stats();
        $return .= '</div>';
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $data['panel_header'] = 'My Reports';
        if ($user->reports == null || $user->reports == '') {
            $array = [];
        } else {
            $yaml = $user->reports;
            $formatter = Formatter::make($yaml, Formatter::YAML);
            $array = $formatter->toArray();
        }
        $list_array = [];
        $list_array[] = [
            'label' => 'Tag Search',
            'view' => route('superquery_tag'),
            'active' => true,
        ];
        $list_array[] = [
            'label' => 'HEDIS Report',
            'view' => route('superquery_hedis', ['all']),
            'active' => true
        ];
        if (! empty($array)) {
            foreach ($array as $row_k => $row_v) {
                $arr = [];
                $arr['label'] = $row_k;
                $arr['view'] = route('superquery', [$row_k]);
                $arr['delete'] = route('superquery_delete', [$row_k]);
                $list_array[] = $arr;
            }
        }
        $return .= $this->result_build($list_array, 'forms_list');
        $dropdown_array1 = [
           'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
           'type' => 'item',
           'label' => 'Add Report',
           'icon' => 'fa-plus',
           'url' => route('superquery', ['0'])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function superquery_patient(Request $request, $action, $pid, $id='', $id1='', $id2='', $id3='')
    {
        if (Session::has('pid')) {
            if (Session::get('pid') !== $pid) {
                $this->setpatient($pid);
            }
        } else {
            $this->setpatient($pid);
        }
        if ($id == '') {
            return redirect()->route($action);
        } else {
            $params[] = $id;
            if ($id1 !== '') {
                $params[] = $id1;
            }
            if ($id2 !== '') {
                $params[] = $id2;
            }
            if ($id3 !== '') {
                $params[] = $id3;
            }
            return redirect()->route($action, $params);
        }
    }

    public function superquery_tag(Request $request)
    {
        $html = '';
        $tags_arr = $this->array_tags();
        $data['search_patient1'] = 'pid';
        $patient_name = 'Optional';
        if ($request->isMethod('post') || Session::has('tags_array')) {
            $pid = '';
            if ($request->isMethod('post')) {
                $pid = $request->input('pid');
                $tags_array = $request->input('tags_array');
                Session::put('tags_array', $tags_array);
            } else {
                $tags_array = Session::get('tags_array');
            }
            if (Session::has('tags_pid')) {
                $pid = Session::get('tags_pid');
            }
            if ($pid !== null && $pid !== '') {
                $row = DB::table('demographics')->where('pid', '=', $pid)->first();
                $dob = date('m/d/Y', strtotime($row->DOB));
                $patient_name = $row->lastname . ', ' . $row->firstname . ' (DOB: ' . $dob . ') (ID: ' . $row->pid . ')';
            }
            $intro = '<div class="form-group" id="patient_name_div"><label class="col-md-3 control-label">Patient</label><div class="col-md-8"><p class="form-control-static" id="patient_name">' . $patient_name . '</p></div></div>';
            $items = [];
            $items[] = [
                'name' => 'pid',
                'type' => 'hidden',
                'default_value' => $pid
            ];
            $items[] = [
                'name' => 'tags_array[]',
                'label' => 'Search items with the following tag(s)',
                'type' => 'select',
                'select_items' => $tags_arr,
                'required' => true,
                'multiple' => true,
                'selectpicker' => true,
                'default_value' => $tags_array
            ];
            $form_array = [
                'form_id' => 'superquery_tag_form',
                'action' => route('superquery_tag'),
                'items' => $items,
                'save_button_label' => 'Run Report',
                'intro' => $intro,
                'origin' => route('superquery_list')
            ];
            $html .= $this->form_build($form_array);
            $practice_id = Session::get('practice_id');
            $query_text = DB::table('tags_relate');
            $query_text->where(function($query_array1) use ($tags_array) {
                $j = 0;
                foreach ($tags_array as $tag) {
                    if ($j == 0) {
                        $query_array1->where('tags_id', '=', $tag);
                    } else {
                        $query_array1->orWhere('tags_id', '=', $tag);
                    }
                    $j++;
                }
            });
            if ($pid !== null && $pid !== '') {
                $query_text->where('pid', '=', $pid);
                Session::put('tags_pid', $pid);
            } else {
                Session::forget('tags_pid');
            }
            $query = $query_text->get();
            $records1 = [];
            if ($query->count()) {
                $i = 0;
                foreach ($query as $row) {
                    if ($row->eid !== '') {
                        $row2 = DB::table('encounters')->where('eid', '=', $row->eid)->first();
                        if ($row2) {
                            $records1[$i]['doc_date'] = date('Y-m-d', $this->human_to_unix($row2->encounter_DOS));
                            $records1[$i]['doctype'] = 'Encounter';
                            $records1[$i]['click'] = route('superquery_patient', ['encounter_view', $row->pid, $row->eid]);
                            $records1[$i]['doctype_index'] = 'eid';
                            $records1[$i]['doc_id'] = $row->eid;
                        }
                    }
                    if ($row->t_messages_id !== '') {
                        $row3 = DB::table('t_messages')->where('t_messages_id', '=', $row->t_messages_id)->first();
                        if ($row3) {
                            $records1[$i]['doc_date'] = date('Y-m-d', $this->human_to_unix($row3->t_messages_date));
                            $records1[$i]['doctype'] = 'Telephone Message';
                            $records1[$i]['click'] = route('superquery_patient', ['t_message_view', $row->pid, $row->t_messages_id]);
                            $records1[$i]['doctype_index'] = 't_messages_id';
                            $records1[$i]['doc_id'] = $row->t_messages_id;
                        }
                    }
                    if ($row->message_id !== '') {
                        $row4 = DB::table('messaging')->where('message_id', '=', $row->message_id)->first();
                        if ($row4) {
                            $records1[$i]['doc_date'] = date('Y-m-d', $this->human_to_unix($row4->date));
                            $records1[$i]['doctype'] = 'Message';
                            $records1[$i]['clickview'] = route('superquery_tag_view');
                            $records1[$i]['doctype_table'] = 'messaging';
                            $records1[$i]['doctype_index'] = 'message_id';
                            $records1[$i]['doc_id'] = $row->message_id;
                        }
                    }
                    if ($row->documents_id !== '') {
                        $row5 = DB::table('documents')->where('documents_id', '=', $row->documents_id)->first();
                        if ($row5) {
                            if (file_exists($row5->documents_url)) {
                                $records1[$i]['doc_date'] = date('Y-m-d', $this->human_to_unix($row5->documents_date));
                                $records1[$i]['doctype'] = 'Documents';
                                $records1[$i]['click'] = route('superquery_patient', ['document_view', $row->pid, $row->documents_id]);
                                $records1[$i]['doctype_index'] = 'documents_id';
                                $records1[$i]['doc_id'] = $row->documents_id;
                            }
                        }
                    }
                    if ($row->hippa_id !== '') {
                        $row6 = DB::table('hippa')->where('hippa_id', '=', $row->hippa_id)->first();
                        if ($row6) {
                            $records1[$i]['doc_date'] = date('Y-m-d', $this->human_to_unix($row6->hippa_date_release));
                            $records1[$i]['doctype'] = 'Records Release';
                            $records1[$i]['click'] = route('superquery_patient', ['records_list', $row->pid, 'release']);
                            $records1[$i]['doctype_index'] = 'hippa_id';
                            $records1[$i]['doc_id'] = $row->hippa_id;
                        }
                    }
                    if ($row->appt_id !== '') {
                        $row7 = DB::table('schedule')->where('appt_id', '=', $row->appt_id)->first();
                        if ($row7) {
                            $records1[$i]['doc_date'] = date('Y-m-d', $this->human_to_unix($row7->timestamp));
                            $records1[$i]['doctype'] = 'Appointment';
                            $records1[$i]['clickview'] = route('superquery_tag_view');
                            $records1[$i]['doctype_table'] = 'schedule';
                            $records1[$i]['doctype_index'] = 'appt_id';
                            $records1[$i]['doc_id'] = $row->appt_id;
                        }
                    }
                    if ($row->tests_id !== '') {
                        $row8 = DB::table('tests')->where('tests_id', '=', $row->tests_id)->first();
                        if ($row8) {
                            $records1[$i]['doc_date'] = date('Y-m-d', $this->human_to_unix($row8->test_datetime));
                            $records1[$i]['doctype'] = 'Test Results';
                            $records1[$i]['click'] = route('superquery_patient', ['results_view', $row->pid, $row->tests_id]);
                            $records1[$i]['doctype_index'] = 'tests_id';
                            $records1[$i]['doc_id'] = $row->tests_id;
                        }
                    }
                    if ($row->mtm_id !== '') {
                        $row9 = DB::table('mtm')->where('mtm_id', '=', $row->mtm_id)->first();
                        if ($row9) {
                            $records1[$i]['doc_date'] = date('Y-m-d', $this->human_to_unix($row9->mtm_date_completed));
                            $records1[$i]['doctype'] = 'Medication Therapy Management';
                            $records1[$i]['clickview'] = route('superquery_tag_view');
                            $records1[$i]['doctype_table'] = 'mtm';
                            $records1[$i]['doctype_index'] = 'mtm_id';
                            $records1[$i]['doc_id'] = $row->mtm_id;
                        }
                    }
                    if (isset($records1[$i]['doc_date']) && isset($records1[$i]['doctype'])) {
                        $records1[$i]['index'] = $i;
                        $records1[$i]['pid'] = $row->pid;
                        $row1 = DB::table('demographics')->where('pid', '=', $row->pid)->first();
                        $records1[$i]['lastname'] = $row1->lastname;
                        $records1[$i]['firstname'] = $row1->firstname;
                    }
                    $i++;
                }
            }
            $head_arr = [
                'Last Name' => 'lastname',
                'First Name' => 'firstname',
                'Date' => 'doc_date',
                'Type' => 'doctype'
            ];
            if (! empty($records1)) {
                $html .= '<h4>Results</h4><h6>Click on row to show item</h6><div class="table-responsive"><table class="table table-striped"><thead><tr>';
                foreach ($head_arr as $head_row_k => $head_row_v) {
                    $html .= '<th>' . $head_row_k . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                foreach ($records1 as $row) {
                    $html .= '<tr>';
                    foreach ($head_arr as $head_row_k => $head_row_v) {
                        if ($head_row_k !== 'Action') {
                            if (isset($row['click'])) {
                                $html .= '<td class="nosh-click" data-nosh-click="' . $row['click'] . '">' . $row[$head_row_v] . '</td>';
                            } elseif (isset($row['clickview'])) {
                                $html .= '<td class="nosh-click-view" data-nosh-click="' . $row['clickview'] . '" data-nosh-index="' . $row['doctype_index'] . '" data-nosh-id="' . $row['doc_id'] . '" data-nosh-table="' . $row['doctype_table'] . '" data-nosh-pid="' . $row['pid'] . '">' . $row[$head_row_v] . '</td>';
                            } else {
                                $html .= '<td>' . $row[$head_row_v] . '</td>';
                            }
                        } else {
                            $html .= '<td>' . $row[$head_row_v] . '</td>';
                        }
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
            } else {
                $html .= ' No data.';
            }
        } else {
            $intro = '<div class="form-group" id="patient_name_div"><label class="col-md-3 control-label">Patient</label><div class="col-md-8"><p class="form-control-static" id="patient_name">' . $patient_name . '</p></div></div>';
            $items[] = [
                'name' => 'pid',
                'type' => 'hidden',
                'default_value' => null
            ];
            $items[] = [
                'name' => 'tags_array[]',
                'label' => 'Search items with the following tag(s)',
                'type' => 'select',
                'select_items' => $tags_arr,
                'required' => true,
                'multiple' => true,
                'selectpicker' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'superquery_tag_form',
                'action' => route('superquery_tag'),
                'items' => $items,
                'save_button_label' => 'Run Report',
                'intro' => $intro,
                'origin' => route('superquery_list')
            ];
            $html .= $this->form_build($form_array);
        }
        $data['panel_header'] = 'Tag Search';
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back',
            'default_button_text_url' => Session::get('last_page')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = $html;
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function supplements(Request $request, $type='inventory')
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $type_arr = [
            'inventory' => ['Supplement Inventory', 'fa-folder'],
            'old_inventory' => ['Past Supplement Inventory', 'fa-folder-o'],
            'sales_tax' => ['Configure Sales Tax', 'fa-money']
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('supplements', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = [];
        $return = '';
        if ($type == 'inventory') {
            $query = DB::table('supplement_inventory')
                ->where('quantity1', '>', '0')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('sup_description', 'asc')
                ->get();
            $columns = Schema::getColumnListing('supplement_inventory');
            $row_index = $columns[0];
            if ($query->count()) {
                $list_array = [];
                foreach ($query as $row) {
                    $arr['label'] = '<b>' . $row->sup_description . ', ' . $row->sup_strength . '</b><br><br><b>Quantity:</b> ' . $row->quantity1 . '<br><b>Date Purchased:</b> ' . date('Y-m-d', $this->human_to_unix($row->date_purchase));
                    $arr['edit'] = route('core_form', ['supplement_inventory', $row_index, $row->$row_index]);
                    $arr['inactivate'] = route('core_action', ['table' => 'supplement_inventory', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    $arr['delete'] = route('core_action', ['table' => 'supplement_inventory', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    $list_array[] = $arr;
                }
                $return .= $this->result_build($list_array, 'supplement_inventory_list');
            } else {
                $return .= 'No supplements.';
            }
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => '',
                'icon' => 'fa-plus',
                'url' => route('core_form', ['supplement_inventory', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if ($type == 'old_inventory') {
            $query = DB::table('supplement_inventory')
                ->where('quantity1', '<=', '0')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('sup_description', 'asc')
                ->get();
            $columns = Schema::getColumnListing('supplement_inventory');
            $row_index = $columns[0];
            if ($query->count()) {
                $list_array = [];
                foreach ($query as $row) {
                    $arr['label'] = '<b>' . $row->sup_description . ', ' . $row->sup_strength . '</b><br><br><b>Quantity:</b> ' . $row->quantity1 . '<br><b>Date Purchased:</b> ' . date('Y-m-d', $this->human_to_unix($row->date_purchase));
                    $arr['edit'] = route('core_form', ['supplement_inventory', $row_index, $row->$row_index]);
                    $arr['reactivate'] = route('core_action', ['table' => 'supplement_inventory', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    $arr['delete'] = route('core_action', ['table' => 'supplement_inventory', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    $list_array[] = $arr;
                }
                $return .= $this->result_build($list_array, 'old_supplement_inventory_list');
            } else {
                $return .= 'No supplements.';
            }
        }
        if ($type == 'sales_tax') {
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $items = [];
            $items[] = [
                'name' => 'sales_tax',
                'label' => 'Sales Tax %',
                'type' => 'text',
                'default_value' => $practice->sales_tax
            ];
            $form_array = [
                'form_id' => 'sales_tax_form',
                'action' => route('supplements_sales_tax'),
                'items' => $items,
                'save_button_label' => 'Save'
            ];
            $return .= $this->form_build($form_array);
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Supplements';
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function supplements_sales_tax(Request $request)
    {
        $this->validate($request, [
            'sales_tax' => 'numeric'
        ]);
        $data['sales_tax'] = $request->input('sales_tax');
        DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->update($data);
        Session::put('message_action', 'Sales Tax saved');
        return redirect(Session::get('last_page'));
    }

    public function uma_aat(Request $request)
    {
        // Check if call comes from rqp_claims redirect
        if (Session::has('uma_permission_ticket')) {
            if (isset($_REQUEST["authorization_state"])) {
                if ($_REQUEST["authorization_state"] != 'claims_submitted') {
                    if ($_REQUEST["authorization_state"] == 'not_authorized') {
                        $text = 'You are not authorized to have the desired authorization data added.';
                    }
                    if ($_REQUEST["authorization_state"] == 'request_submitted') {
                        $text = 'The authorization server needs additional information in order to determine whether you are authorized to have this authorization data.';
                    }
                    if ($_REQUEST["authorization_state"] == 'need_info') {
                        $text = 'The authorization server requires intervention by the patient to determine whether authorization data can be added. Try again later after receiving any information from the patient regarding updates on your access status.';
                    }
                    $data['panel_header'] = 'Error getting data';
                    $data['content'] = 'Description:<br>' . $text;
                    $dropdown_array = [];
                    $items = [];
                    $items[] = [
                        'type' => 'item',
                        'label' => 'Back',
                        'icon' => 'fa-chevron-left',
                        'url' => Session::get('last_page')
                    ];
                    $dropdown_array['items'] = $items;
                    $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                    $data['assets_js'] = $this->assets_js();
                    $data['assets_css'] = $this->assets_css();
                    return view('core', $data);
                } else {
                    // Great - move on!
                    return redirect()->route('uma_api');
                }
            } else {
                Session::forget('uma_permission_ticket');
            }
        }
        if (Session::has('uma_add_patient')) {
            $urlinit = Session::get('patient_uri');
        } else {
            $urlinit = Session::get('uma_resource_uri');
        }
        $result = $this->fhir_request($urlinit,true);
        if (isset($result['error'])) {
            $data['panel_header'] = 'Error getting data';
            $data['content'] = 'Description:<br>' . $result;
            $dropdown_array = [];
            $items = [];
            $items[] = [
                'type' => 'item',
                'label' => 'Back',
                'icon' => 'fa-chevron-left',
                'url' => Session::get('last_page')
            ];
            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
        $permission_ticket = $result['ticket'];
        Session::put('uma_permission_ticket', $permission_ticket);
        Session::save();
        $as_uri = $result['as_uri'];
        $url = route('uma_aat');
        // Requesting party claims
        $oidc = new OpenIDConnectUMAClient(Session::get('uma_uri'), Session::get('uma_client_id'), Session::get('uma_client_secret'));
        $oidc->startSession();
        $oidc->setRedirectURL($url);
        $oidc->rqp_claims($permission_ticket);
    }

    public function uma_add_patient(Request $request, $type='')
    {
        $message = 'Error - Adding patient canceled';
        if ($type == '') {
            $data = Session::get('uma_add_patient');
            $pid = DB::table('demographics')->insertGetId($data);
            $this->audit('Add');
            $data1 = [
                'billing_notes' => '',
                'imm_notes' => '',
                'pid' => $pid,
                'practice_id' => Session::get('practice_id')
            ];
            DB::table('demographics_notes')->insert($data1);
            $this->audit('Add');
            $data2 = [
                'pid' => $pid,
                'practice_id' => Session::get('practice_id')
            ];
            DB::table('demographics_relate')->insert($data2);
            $this->audit('Add');
            $result = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $directory = $result->documents_dir . $pid;
            mkdir($directory, 0775);
            $message = $data['lastname'] . ' ' . $data['firstname'] . ' added';
        }
        $this->clean_uma_sessions();
        Session::put('message_action', $message);
        return redirect()->route('uma_list');
    }

    public function uma_api(Request $request)
    {
        $as_uri = Session::get('uma_uri');
        if (!Session::has('rpt')) {
            // Send permission ticket + AAT to Authorization Server to get RPT
            $permission_ticket = Session::get('uma_permission_ticket');
            $client_id = Session::get('uma_client_id');
            $client_secret = Session::get('uma_client_secret');
            $url = route('uma_api');
            $oidc = new OpenIDConnectUMAClient($as_uri, $client_id, $client_secret);
            $oidc->startSession();
            $oidc->setSessionName('nosh');
            $oidc->setAccessToken(Session::get('uma_auth_access_token_nosh'));
            $oidc->setRedirectURL($url);
            $result1 = $oidc->rpt_request($permission_ticket);
            if (isset($result1['error'])) {
                // error - return something
                if ($result1['error'] == 'expired_ticket' || $result1['error'] == 'invalid_grant') {
                    // Session::forget('uma_aat');
                    Session::forget('uma_permission_ticket');
                    return redirect()->route('uma_aat');
                } else {
                    $data['panel_header'] = 'Error getting data';
                    $data['content'] = 'Description:<br>' . $result1['error'];
                    $dropdown_array = [];
                    $items = [];
                    $items[] = [
                        'type' => 'item',
                        'label' => 'Back',
                        'icon' => 'fa-chevron-left',
                        'url' => Session::get('last_page')
                    ];
                    $dropdown_array['items'] = $items;
                    $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                    $data['assets_js'] = $this->assets_js();
                    $data['assets_css'] = $this->assets_css();
                    return view('core', $data);
                }
            }
            if (isset($result1['errors'])) {
                $data['panel_header'] = 'Error getting data';
                $data['content'] = 'Description:<br>' . $result1['errors'];
                $dropdown_array = [];
                $items = [];
                $items[] = [
                    'type' => 'item',
                    'label' => 'Back',
                    'icon' => 'fa-chevron-left',
                    'url' => Session::get('last_page')
                ];
                $dropdown_array['items'] = $items;
                $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                $data['assets_js'] = $this->assets_js();
                $data['assets_css'] = $this->assets_css();
                return view('core', $data);
            }
            $rpt = $result1['access_token'];
            // Save RPT in session in case for future calls in same session
            Session::put('rpt', $rpt);
            Session::save();
        } else {
            $rpt = Session::get('rpt');
        }
        // Contact resource again, now with RPT
        if (Session::has('uma_add_patient')) {
            $urlinit = Session::get('patient_uri');
        } else {
            $urlinit = Session::get('uma_resource_uri');
        }
        $result3 = $this->fhir_request($urlinit,false,$rpt);
        if (isset($result3['ticket'])) {
            // New permission ticket issued, expire rpt session
            Session::forget('rpt');
            Session::put('uma_permission_ticket', $result3['ticket']);
            Session::save();
            // Get new RPT
            return redirect()->route('uma_api');
        }
        // Format the result into a nice display
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $title_array = $this->fhir_resources();
        $dropdown_array = [];
        $items = [];
        if (Session::has('uma_add_patient')) {
            $items[] = [
                'type' => 'item',
                'label' => 'Cancel',
                'icon' => 'fa-chevron-left',
                'url' => route('uma_add_patient', ['cancel'])
            ];
        } else {
            $items[] = [
                'type' => 'item',
                'label' => 'Back',
                'icon' => 'fa-chevron-left',
                'url' => Session::get('last_page')
            ];
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = 'None.';
        $data['panel_header'] = $title_array[Session::get('type')]['name'] . ' for ' . Session::get('uma_as_name');
        if (isset($result3['total'])) {
            if ($result3['total'] != '0') {
                foreach ($result3['entry'] as $entry) {
                    if (Session::has('uma_add_patient')) {
                        $data['content'] = '<ul class="list-group">';
                        $as_name = $entry['resource']['name'][0]['given'][0] . ' ' . $entry['resource']['name'][0]['family'][0] . ' (DOB: ' . date('Y-m-d', strtotime($entry['resource']['birthDate'])) . ')';
                        $data1 = Session::get('uma_add_patient');
                        $add_data1 = [
                            'lastname' => $entry['resource']['name'][0]['family'][0],
                            'firstname' => $entry['resource']['name'][0]['given'][0],
                            'DOB' => date('Y-m-d', strtotime($entry['resource']['birthDate'])),
                            'sex' => array_search($entry['resource']['gender']['coding'][0]['code'], $this->array_gender()),
                            'active' => '1',
                            'sexuallyactive' => 'no',
                            'tobacco' => 'no',
                            'pregnant' => 'no',
                            'hieofone_as_name' => $as_name
                        ];
                        $data1 = $data1 + $add_data1;
                        Session::put('uma_add_patient', $data1);
                        $dropdown_array1 = [
                            'items_button_icon' => 'fa-plus'
                        ];
                        $items1 = [];
                        $items1[] = [
                            'type' => 'item',
                            'label' => 'Add Patient',
                            'icon' => 'fa-plus',
                            'url' => route('uma_add_patient')
                        ];
                        $dropdown_array1['items'] = $items1;
                        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
                        $data['panel_header'] = 'Add Patient';
                        $data['content'] .= '<li class="list-group-item">' . $entry['resource']['text']['div'];
                        // Preview medication list
                        $urlinit1 = Session::get('medicationstatement_uri');
                        $result4 = $this->fhir_request($urlinit1,false,$rpt);
                        if (isset($result4['total'])) {
                            if ($result4['total'] != '0') {
                                $data['content'] .= '<strong>Medications</strong><ul>';
                                foreach ($result4['entry'] as $entry1) {
                                    $data['content'] .= '<li>' . $entry1['resource']['text']['div'] . '</li>';
                                }
                                $data['content'] .= '</ul>';
                            }
                        }
                        $data['content'] .= '</li>';
                        $data['content'] .= '</ul>';
                        $data['panel_header'] = $title_array[Session::get('type')]['name'] . ' for ' . $as_name;
                    } else  {
                        $data = $this->fhir_display($result3, Session::get('type'), $data);
                        // $data['content'] .= '<li class="list-group-item">' . $entry['resource']['text']['div'] . '</li>';
                    }
                }
            }
        }
        if (Session::get('uma_pid') == Session::get('pid')) {
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
        } else {
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
        }
        return view('core', $data);
    }

    public function uma_list(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'url' => 'required|url'
            ]);
            // Register to HIE of One AS - confirm it
            $this->clean_uma_sessions();
            $test_uri = rtrim($request->input('url'), '/') . "/.well-known/uma2-configuration";
            $url_arr = parse_url($test_uri);
            if (!isset($url_arr['scheme'])) {
                $test_uri = 'https://' . $test_uri;
            }
            $ch = curl_init($test_uri);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($httpcode>=200 && $httpcode<302){
                $url_arr = parse_url($test_uri);
                $as_uri = $url_arr['scheme'] . '://' . $url_arr['host'];
            } else {
                return redirect()->back()->withErrors(['url' => 'Try again, URL is invalid, httpcode: ' . $httpcode . ', URL: ' . $request->input('url')]);
            }
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $client_name = 'mdNOSH - ' . $practice->practice_name;
            $url1 = route('uma_auth');
            $oidc = new OpenIDConnectUMAClient($as_uri);
            $oidc->startSession();
            $oidc->setClientName($client_name);
            $oidc->setSessionName('nosh');
            $oidc->addRedirectURLs($url1);
            $oidc->addRedirectURLs(route('uma_api'));
            $oidc->addRedirectURLs(route('uma_aat'));
            $oidc->addRedirectURLs(route('uma_register_auth'));
            // $oidc->addRedirectURLs(route('uma_resources'));
            // $oidc->addRedirectURLs(route('uma_resource_view'));
            $oidc->addScope('openid');
            $oidc->addScope('email');
            $oidc->addScope('profile');
            $oidc->addScope('address');
            $oidc->addScope('phone');
            $oidc->addScope('offline_access');
            $oidc->addScope('uma_authorization');
            $oidc->setLogo('https://cloud.noshchartingsystem.com/SAAS-Logo.jpg');
            $oidc->setClientURI(str_replace('/uma_auth', '', $url1));
            $oidc->setUMA(true);
            $oidc->register();
            $client_id = $oidc->getClientID();
            $client_secret = $oidc->getClientSecret();
            $data1 = [
                'hieofone_as_client_id' => $client_id,
                'hieofone_as_client_secret' => $client_secret,
                'hieofone_as_url' => $as_uri
            ];
            Session::put('uma_add_patient', $data1);
            Session::save();
            return redirect()->route('uma_resource_view', ['new']);
        } else {
            $items[] = [
                'name' => 'url',
                'label' => "URL of Patient's Authorization Server",
                'type' => 'text',
                'required' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'uma_list_form',
                'action' => route('uma_list'),
                'items' => $items,
                'save_button_label' => 'Add New Patient'
            ];
            $data['panel_header'] = 'FHIR Connected Patients';
            $data['content'] = $this->form_build($form_array);
            $query = DB::table('demographics')->where('hieofone_as_url', '!=', '')->orWhere('hieofone_as_url', '!=', null)->get();
            if ($query->count()) {
                $list_array = [];
                foreach ($query as $row) {
                    $arr = [];
                    $dob = date('m/d/Y', strtotime($row->DOB));
                    $arr['label'] = $row->lastname . ', ' . $row->firstname . ' (DOB: ' . $dob . ') (ID: ' . $row->pid . ')';
                    $arr['view'] = route('uma_resources', [$row->pid]);
                    $arr['jump'] = $row->hieofone_as_url . '/nosh/uma_auth';
                    $list_array[] = $arr;
                }
                $data['content'] .= $this->result_build($list_array, 'fhir_list');
            }
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('core', $data);
        }
    }

    public function uma_register_auth(Request $request)
    {
        $oidc = new OpenIDConnectUMAClient(Session::get('uma_uri'), Session::get('uma_client_id'), Session::get('uma_client_secret'));
        $oidc->startSession();
        $oidc->setSessionName('nosh');
        $oidc->setRedirectURL(route('uma_register_auth'));
        $oidc->setSessionName('pnosh');
        $oidc->setUMA(true);
        $oidc->setUMAType('client');
        $oidc->authenticate();
        if (Session::has('uma_add_patient')) {
            $data = Session::get('uma_add_patient');
            $data['hieofone_as_refresh_token'] = $oidc->getRefreshToken();
            Session::put('uma_add_patient', $data);
            $resources = $oidc->get_resources(true);
            if (count($resources) > 0) {
                // Get the access token from the AS in anticipation for the RPT
                Session::put('uma_auth_access_token_nosh', $oidc->getAccessToken());
                Session::put('uma_auth_resources', $resources);
                $patient_urls = [];
                foreach ($resources as $resource) {
                    // Assume there is always a Trustee pNOSH resource and save it
                    if (strpos($resource['name'], 'from Trustee')) {
                        foreach ($resource['resource_scopes'] as $scope) {
                            $scope_arr = explode('/', $scope);
                            if (in_array('Patient', $scope_arr)) {
                                Session::put('patient_uri', $scope . '?subject:Patient=1');
                            }
                            if (in_array('MedicationStatement', $scope_arr)) {
                                Session::put('medicationstatement_uri', $scope);
                            }
                        }
                    }
                }
                return redirect()->route('uma_aat');
            } else {
                Session::put('message_action', 'Error - the authorization you were trying to connect to has no resources.');
                Session::forget('uma_add_patient');
                return redirect()->route('uma_list');
            }
        } else {
            $pid = Session::get('uma_resources_start');
            Session::forget('uma_resources_start');
            $update_data['hieofone_as_refresh_token'] = $oidc->getRefreshToken();
            DB::table('demographics')->where('pid', '=', $pid)->update($update_data);
            $this->audit('Update');
            return redirect()->route('uma_resources', [$pid]);
        }
    }

    public function uma_resources(Request $request, $id)
    {
        $patient = DB::table('demographics')->where('pid', '=', $id)->first();
        // Get access token from AS in anticipation for geting the RPT; if no refresh token before, get it too.
        if ($patient->hieofone_as_refresh_token == '' || $patient->hieofone_as_refresh_token == null) {
            Session::put('uma_resources_start', $id);
            return redirect()->route('uma_register_auth');
        }
        $oidc = new OpenIDConnectUMAClient($patient->hieofone_as_url, $patient->hieofone_as_client_id, $patient->hieofone_as_client_secret);
        $oidc->startSession();
        $oidc->setSessionName('nosh');
        $oidc->setUMA(true);
        $oidc->refreshToken($patient->hieofone_as_refresh_token);
        Session::put('uma_auth_access_token_nosh', $oidc->getAccessToken());
        $resources = $oidc->get_resources(true);
        Session::put('uma_auth_resources', $resources);
        $resources_array = $this->fhir_resources();
        $data['panel_header'] = $patient->firstname . ' ' . $patient->lastname . "'s Patient Summary";
        $data['content'] = 'No resources available yet.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $dropdown_array = [];
        $items = [];
        $items[] = [
            'type' => 'item',
            'label' => 'Back',
            'icon' => 'fa-chevron-left',
            'url' => route('uma_list')
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        // Look for pNOSH link through registered client to mdNOSH Gateway
        $data['content'] = '<div class="list-group">';
        $i = 0;
        foreach($resources as $resource) {
            foreach ($resource['resource_scopes'] as $scope) {
                if (parse_url($scope, PHP_URL_HOST) !== null) {
                    $fhir_arr = explode('/', $scope);
                    $resource_type = array_pop($fhir_arr);
                    if (strpos($resource['name'], 'from Trustee') && $i == 0) {
                        array_pop($fhir_arr);
                        $data['content'] .= '<a href="' . implode('/', $fhir_arr) . '/uma_auth" target="_blank" class="list-group-item nosh-no-load"><span style="margin:10px;">Patient Centered Health Record (pNOSH) for ' . $patient->hieofone_as_name . '</span><span class="label label-success">Patient Centered Health Record</span></a>';
                        $i++;
                    }
                    break;
                }
            }
            $data['content'] .= '<a href="' . route('uma_resource_view', [$resource['_id']]) . '" class="list-group-item"><i class="fa ' . $resources_array[$resource_type]['icon'] . ' fa-fw"></i><span style="margin:10px;">' . $resources_array[$resource_type]['name'] . '</span></a>';
        }
        $data['content'] .= '</div>';
        Session::put('uma_pid', $id);
        Session::put('last_page', $request->fullUrl());
        if ($id == Session::get('pid')) {
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
        } else {
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
        }
        return view('core', $data);
    }

    public function uma_resource_view(Request $request, $type)
    {
        if (Session::has('uma_add_patient')) {
            $data = Session::get('uma_add_patient');
            Session::put('uma_uri', $data['hieofone_as_url']);
            Session::put('uma_client_id', $data['hieofone_as_client_id']);
            Session::put('uma_client_secret', $data['hieofone_as_client_secret']);
            Session::put('type', 'Patient');
        } else {
            $patient = DB::table('demographics')->where('pid', '=', Session::get('uma_pid'))->first();
            Session::put('uma_uri', $patient->hieofone_as_url);
            Session::put('uma_client_id', $patient->hieofone_as_client_id);
            Session::put('uma_client_secret', $patient->hieofone_as_client_secret);
            Session::put('uma_as_name', $patient->hieofone_as_name);
            $resources = Session::get('uma_auth_resources');
            $key = array_search($type, array_column($resources, '_id'));
            foreach ($resources[$key]['resource_scopes'] as $scope) {
                if (parse_url($scope, PHP_URL_HOST) !== null) {
                    $fhir_arr = explode('/', $scope);
                    $resource_type = array_pop($fhir_arr);
                    Session::put('type', $resource_type);
                    if (strpos($resources[$key]['name'], 'from Trustee')) {
                        if ($resource_type == 'Patient') {
                            $scope .= '?subject:Patient=1';
                        }
                        Session::put('uma_resource_uri', $scope);
                        break;
                    } else {
                        Session::put('uma_resource_uri', $scope);
                    }
                    $name_arr = explode(' from ', $resources[$key]['name']);
                    Session::put('fhir_name', $name_arr[1]);
                }
            }
        }
        Session::save();
        if (Session::has('rpt')) {
            return redirect()->route('uma_api');
        } else {
            if (Session::has('uma_add_patient')) {
                return redirect()->route('uma_register_auth');
            } else {
                return redirect()->route('uma_aat');
            }
        }
    }

    public function users(Request $request, $type='2', $active='1')
    {
        if (Session::get('group_id') != '1') {
            Session::put('message_action', 'Error - You are not allowed to manage users');
            return redirect()->route('dashboard');
        }
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $return = '';
        $type_arr = [
            '2' => [
                '1' => ['Active Physician', 'fa-user'],
                '0' => ['Inactive Physician', 'fa-user-times']
            ],
            '3' => [
                '1' => ['Active Assistant', 'fa-user'],
                '0' => ['Inactive Assistant', 'fa-user-times']
            ],
            '4' => [
                '1' => ['Active Biller', 'fa-user'],
                '0' => ['Inactive Biller', 'fa-user-times']
            ],
            '100' => [
                '1' => ['Active Patient', 'fa-user'],
                '0' => ['Inactive Patient', 'fa-user-times']
            ]
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][$active][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                foreach ($value as $key1 => $value1) {
                    if ($key1 !== $active) {
                        $items[] = [
                            'type' => 'item',
                            'label' => $value1[0],
                            'icon' => $value1[1],
                            'url' => route('users', [$key, $key1])
                        ];
                    }
                }
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        if ($type == '2') {
            $query = DB::table('users')
                ->join('providers', 'providers.id', '=', 'users.id')
                ->where('users.group_id', '=', $type)
                ->where('users.active', '=', $active)
                ->where('users.practice_id', '=', Session::get('practice_id'));
        } elseif ($type == '100') {
            $query = DB::table('users')
                ->leftJoin('demographics_relate', 'users.id', '=', 'demographics_relate.id')
                ->select('users.*', 'demographics_relate.pid')
                ->where('users.group_id', '=', $type)
                ->where('users.active', '=', $active)
                ->where('users.practice_id', '=', Session::get('practice_id'));
        } else {
            $query = DB::table('users')
                ->where('group_id', '=', $type)
                ->where('active', '=', $active)
                ->where('practice_id', '=', Session::get('practice_id'));
        }
        $result = $query->get();
        $columns = Schema::getColumnListing('users');
        $row_index = $columns[0];
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . $row->displayname . '</b> - ' . $row->username;
                if ($row->secret_question == null) {
                    $arr['label'] .= '<br><a href="' . route('accept_invitation', [$row->password]) . '" target="_blank">' . route('accept_invitation', [$row->password]) . '</a>';
                }
                $arr['edit'] = route('core_form', ['users', $row_index, $row->$row_index, $type]);
                if ($active == '1') {
                    $arr['inactivate'] = route('core_action', ['table' => 'users', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    $arr['reset'] = route('password_reset', [$row->$row_index]);
                } else {
                    $arr['reactivate'] = route('core_action', ['table' => 'users', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, $type . '_'. $active . '_list');
        } else {
            $return .= ' None.';
        }
        $dropdown_array1 = [
            'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => 'Add Physician User',
            'icon' => 'fa-plus',
            'url' => route('core_form', ['users', $row_index, '0', '2'])
        ];
        $items1[] = [
            'type' => 'item',
            'label' => 'Add Assistant User',
            'icon' => 'fa-plus',
            'url' => route('core_form', ['users', $row_index, '0', '3'])
        ];
        $items1[] = [
            'type' => 'item',
            'label' => 'Add Billing User',
            'icon' => 'fa-plus',
            'url' => route('core_form', ['users', $row_index, '0', '4'])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        $data['panel_header'] = 'Users';
        Session::put('last_page', $request->fullUrl());
        if (Session::get('group_id') == '1') {
            if (Session::has('download_ccda_entire')) {
                $data['download_progress'] = Session::get('download_ccda_entire');
            }
            if (Session::has('download_charts_entire')) {
                $data['download_progress'] = Session::get('download_charts_entire');
            }
        }
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }

    public function user_signature(Request $request)
    {
        $signature = DB::table('providers')->where('id', '=', Session::get('user_id'))->first();
        if ($request->isMethod('post')) {
            $id = Session::get('user_id');
            $user = DB::table('users')->where('id', '=', $id)->first();
            $name = $user->firstname . " " . $user->lastname;
            if ($name !== $request->input('name')) {
                $message = "Error - Incorrect name!  Signature not saved.  Try again";
            } else {
                if (file_exists($signature->signature)) {
                    unlink($signature->signature);
                }
                $file_path = Session::get('documents_dir') . 'signature_' . $id . '_' . time() . '.png';
                $img = $this->sigJsonToImage($request->input('output'));
                imagepng($img, $file_path);
                imagedestroy($img);
                $data['signature'] = $file_path;
                DB::table('providers')->where('id', '=', $id)->update($data);
                $this->audit('Update');
                $message = "Signature created";
            }
            Session::put('message_action', $message);
            return redirect()->route('dashboard');
        } else {
            $return = '';
            $status = 'Add your signature:';
            if ($signature) {
                if ($signature->signature !== '') {
                    if (file_exists($signature->signature)) {
                        $name = time() . '_signature.png';
                        $temp_path = public_path() .'/temp/' . $name;
                        $url = asset('temp/' . $name);
                        copy($signature->signature, $temp_path);
                        $return .= '<div class="row"><div class="col-md-3 col-md-offset-4"><h5>Current Signature</h5>';
                        $return .= HTML::image($url, 'Signature', ['border' => '0']);
                        $return .= '</div></div>';
                        $status = 'Update your signature:';
                    }
                }
            }
            $items[] = [
                'name' => 'name',
                'label' => 'Print your Name for Verification',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'First Last',
                'default_value' => null
            ];
            $intro = '<div class="row"><div class="col-md-3 col-md-offset-4"><p class="drawItDesc">'. $status . '</p><ul class="sigNav"><li class="drawIt"><a href="#draw-it">Draw It</a></li><li class="clearButton"><a href="#clear">Clear</a></li></ul>';
            $intro .= '<div class="sig sigWrapper"><div class="typed"></div><canvas class="pad" width="198" height="55"></canvas><input type="hidden" name="output" class="output"></div></div></div><br>';
            $form_array = [
                'form_id' => 'signature_form',
                'action' => route('user_signature'),
                'items' => $items,
                'save_button_label' => 'Save',
                'intro' => $intro,
            ];
            $return .= $this->form_build($form_array);
            $data['content'] = $return;
            $data['panel_header'] = 'Signature';
            $data['assets_js'] = $this->assets_js('signature');
            $data['assets_css'] = $this->assets_css('signature');
            return view('core', $data);
        }
    }

    public function vaccines(Request $request, $type='inventory')
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $type_arr = [
            'inventory' => ['Vaccine Inventory', 'fa-folder'],
            'old_inventory' => ['Past Vaccine Inventory', 'fa-folder-o'],
            'vaccine_temp' => ['Vaccine Temperatures', 'fa-thermometer-half']
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('vaccines', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = [];
        $return = '';
        if ($type == 'inventory') {
            $query = DB::table('vaccine_inventory')
                ->where('quantity', '>', '0')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('imm_immunization', 'asc')
                ->get();
            $columns = Schema::getColumnListing('vaccine_inventory');
            $row_index = $columns[0];
            if ($query->count()) {
                $list_array = [];
                foreach ($query as $row) {
                    $arr['label'] = '<b>' . $row->imm_immunization . '</b><br><br><b>Quantity:</b> ' . $row->quantity . '<br><b>Date Purchased:</b> ' . date('Y-m-d', $this->human_to_unix($row->date_purchase));
                    $arr['edit'] = route('core_form', ['vaccine_inventory', $row_index, $row->$row_index]);
                    $arr['inactivate'] = route('core_action', ['table' => 'vaccine_inventory', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    $arr['delete'] = route('core_action', ['table' => 'vaccine_inventory', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    $list_array[] = $arr;
                }
                $return .= $this->result_build($list_array, 'vaccine_inventory_list');
            } else {
                $return .= 'No vaccines.';
            }
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => '',
                'icon' => 'fa-plus',
                'url' => route('core_form', ['vaccine_inventory', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if ($type == 'old_inventory') {
            $query = DB::table('vaccine_inventory')
                ->where('quantity', '<=', '0')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('imm_immunization', 'asc')
                ->get();
            $columns = Schema::getColumnListing('vaccine_inventory');
            $row_index = $columns[0];
            if ($query->count()) {
                $list_array = [];
                foreach ($query as $row) {
                    $arr['label'] = '<b>' . $row->imm_immunization . '</b><br><br><b>Quantity:</b> ' . $row->quantity . '<br><b>Date Purchased:</b> ' . date('Y-m-d', $this->human_to_unix($row->date_purchase));
                    $arr['edit'] = route('core_form', ['vaccine_inventory', $row_index, $row->$row_index]);
                    $arr['reactivate'] = route('core_action', ['table' => 'vaccine_inventory', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    $arr['delete'] = route('core_action', ['table' => 'vaccine_inventory', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    $list_array[] = $arr;
                }
                $return .= $this->result_build($list_array, 'old_vaccine_inventory_list');
            } else {
                $return .= 'No vaccines.';
            }
        }
        if ($type == 'vaccine_temp') {
            $query = DB::table('vaccine_temp')->where('practice_id', '=', Session::get('practice_id'))->orderBy('date', 'desc')->get();
            $columns = Schema::getColumnListing('vaccine_temp');
            $row_index = $columns[0];
            if ($query->count()) {
                foreach ($query as $row) {
                    $result[] = [
                        'date' => date('Y-m-d', $this->human_to_unix($row-> date)),
                        'temp' => $row->temp,
                        'action' => $row->action,
                        'delete' => $action = '<a href="' . route('core_action', ['table' => 'vaccine_temp', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]) . '" class="btn fa-btn nosh-delete" role="button" data-toggle="tooltip" title="Delete Temperature Entry"><i class="fa fa-trash fa-lg"></i></a>',
                        'click' => route('core_form', ['vaccine_temp', $row_index, $row->$row_index])
                    ];
                }
            }
            $head_arr = [
                'Date' => 'date',
                'Temperature' => 'temp',
                'Action' => 'action',
                '' => 'delete'
            ];
            if (! empty($result)) {
                $return .= '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
                foreach ($head_arr as $head_row_k => $head_row_v) {
                    $return .= '<th>' . $head_row_k . '</th>';
                }
                $return .= '</tr></thead><tbody>';
                foreach ($result as $row) {
                    $return .= '<tr>';
                    foreach ($head_arr as $head_row_k => $head_row_v) {
                        if ($head_row_k !== '') {
                            if (isset($row['click'])) {
                                $return .= '<td class="nosh-click" data-nosh-click="' . $row['click'] . '">' . $row[$head_row_v] . '</td>';
                            } else {
                                $return .= '<td>' . $row[$head_row_v] . '</td>';
                            }
                        } else {
                            $return .= '<td>' . $row[$head_row_v] . '</td>';
                        }
                    }
                    $return .= '</tr>';
                }
                $return .= '</tbody></table>';
            } else {
                $return .= ' No data.';
            }
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => 'Add Temperatre',
                'icon' => 'fa-plus',
                'url' => route('core_form', ['vaccine_temp', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        $data['content'] = $return;
        $data['panel_header'] = 'Vaccines';
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js();
        $data['assets_css'] = $this->assets_css();
        return view('core', $data);
    }
}
