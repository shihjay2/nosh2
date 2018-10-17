<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use Form;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use NaviOcean\Laravel\NameParser;
use QrCode;
use Schema;
use Session;
use URL;

class AjaxChartController extends Controller
{

    /**
    * NOSH ChartingSystem Chart Ajax Functions
    */

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
         $this->middleware('auth');
         $this->middleware('csrf');
         $this->middleware('patient');
    }

    public function electronic_sign_gas(Request $request)
    {
        $ether_data = [
            'description' => 'Get Ether',
            'public' => 1,
            'files' => [
                'file.txt' => ['content' => $request->input('uportId')]
            ]
        ];
        $data_string = json_encode($ether_data);
        $url = 'https://api.github.com/gists';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode($response, TRUE);
        $gistlink = $decoded['html_url'];

        // $ether_data = [
        //     // 'toWhom' => '0xb65e3a3027fa941eec63411471d90e6c24b11ed1',
        //     'toWhom' => Session::get('uport_id')
        // ];
        // $url = 'https://ropsten.faucet.b9lab.com/tap';
        // $ch = curl_init($url);
        // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ether_data));
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //     'Content-Type: application/json'
        // ]);
        // $result = curl_exec($ch);
    }

    public function electronic_sign_login(Request $request)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $name = $request->input('name');
        $parser = new NameParser();
        $name_arr = $parser->parse_name($name);
        if ($user->firstname == $name_arr['fname'] && $user->lastname == $name_arr['lname']) {
            $return['message'] = 'OK';
            Session::put('uport_id', $request->input('uport'));
            $ether_data = [
                // 'toWhom' => '0xb65e3a3027fa941eec63411471d90e6c24b11ed1',
                'toWhom' => $request->input('uport')
            ];
            $url = 'https://ropsten.faucet.b9lab.com/tap';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ether_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            $result = curl_exec($ch);
        } else {
            $return['message'] = 'Error - Identity does not match';
        }
        return $return;
    }

    public function electronic_sign_process(Request $request, $table, $index, $id)
    {
        $message_arr = [
            'rx_list' => 'Prescription digitally signed',
            'orders' => 'Order digitally signed'
        ];
        $data['transaction'] = $request->input('txHash');
        DB::table($table)->where($index, '=', $id)->update($data);
        $this->audit('Update');
        $to = Session::get('prescription_notification_to');
        Session::forget('prescription_notification_to');
        $this->prescription_notification($id, $to);
        Session::put('message_action', $message_arr[$table]);
        $return['message'] = 'OK';
        $return['url'] = Session::get('last_page');
        return $return;
    }

    public function get_appointments(Request $request)
    {
        $start_time = time() - 604800;
        $end_time = time() + 604800;
        $query = DB::table('schedule')->where('provider_id', '=', $request->input('id'))
            ->where('pid', '=', Session::get('pid'))
            ->whereBetween('start', array($start_time, $end_time))
            ->get();
        $data = [];
        if ($query) {
            foreach ($query as $row) {
                $key = $row->visit_type . ',' . $row->appt_id;
                $value = date('Y-m-d H:i:s A', $row->start) . ' (Appt ID: ' . $row->appt_id . ')';
                $data[$key] = $value;
            }
        }
        return $data;
    }

    public function remove_smart_on_fhir(Request $request)
    {
        DB::table('refresh_tokens')->where('practice_id', '=', '1')->where('endpoint_uri', '=', $request->input('url'))->delete();
        $this->audit('Delete');
        return 'Removed connection to patient portal';
    }

    public function set_ccda_data(Request $request)
    {
        $data = $request->all();
        if (Session::get('patient_centric') == 'y') {
            if ($data['type'] == 'rx_list') {
                $reason = 'N/A';
                if ($data['reason'] !== '') {
                    $reason = $data['reason'];
                }
                $data1 = [
                    'rxl_date_prescribed' => '',
                    'rxl_date_inactive' => '',
                    'rxl_date_old' => ''
                ];
                $data1['rxl_medication'] = $data['name'];
                $data1['rxl_dosage'] = $data['dosage'];
                $data1['rxl_dosage_unit'] = $data['dosage-unit'];
                $data1['rxl_route'] = $data['route'];
                $data1['rxl_reason'] = $reason;
                $data1['rxl_ndcid'] = $data['code'];
                $data1['rxl_date_active'] = date('Y-m-d', $this->human_to_unix($data['date']));
                $data1['rxl_instructions'] = $data['administration'];
                if (isset($data['from'])) {
                    $data1['rxl_instructions'] .= '; Obtained via FHIR from ' . $data['from'];
                }
                $source_index = 'rxl_id';
            }
            if ($data['type'] == 'issues') {
                $data1['issue'] = $data['name'] . ' [' . $data['code'] . ']';
                $data1['issue_date_active'] = date('Y-m-d', $this->human_to_unix($data['date']));
                $data1['type'] = 'pl';
                $data1['issue_date_inactive'] = '';
                if (isset($data['from'])) {
                    $data1['notes'] = 'Obtained via FHIR from ' . $data['from'];
                }
                $source_index = 'issue_id';
            }
            if ($data['type'] == 'allergies') {
                $data1['allergies_med'] = $data['name'];
                $data1['allergies_reaction'] = $data['reaction'];
                $data1['allergies_date_active'] = date('Y-m-d', $this->human_to_unix($data['date']));
                $data1['meds_ndcid'] = $this->rxnorm_search($data['name']);
                $data1['allergies_date_inactive'] = '';
                if (isset($data['from'])) {
                    $data1['notes'] = 'Obtained via FHIR from ' . $data['from'];
                }
                $source_index = 'allergies_id';
            }
            if ($data['type'] == 'immunizations') {
                $data1['imm_immunization'] = $data['name'];
                $data1['imm_route'] = $data['route'];
                $data1['imm_date'] = date('Y-m-d', $this->human_to_unix($data['date']));
                $data1['imm_vis'] = '';
                if (isset($data['code'])) {
                    $data1['imm_cvxcode'] = $data['code'];
                }
                if (isset($data['sequence'])) {
                    $data1['imm_sequence'] = $data['sequence'];
                }
                $source_index = 'imm_id';
            }
            $data1['pid'] = Session::get('pid');
            $source_id = DB::table($data['type'])->insertGetId($data1);
            if (isset($data['from'])) {
                $data_sync = [
                    'pid' => Session::get('pid'),
                    'action' => 'Added ' . $data['name'],
                    'from' => 'Synchronized via FHIR from ' . $data['from'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'source_id' => $source_id,
                    'source_index' => $source_index
                ];
                DB::table('data_sync')->insert($data_sync);
            }
            Session::put('message_action', 'Added ' . $data['name']);
            return Session::put('last_page');
        } else {
            Session::put('ccda', $data);
            $columns = Schema::getColumnListing($data['type']);
            $row_index = $columns[0];
            $subtype = '';
            if ($data['type'] == 'issues') {
                $subtype = 'pl';
            }
            return route('chart_form', [$data['type'], $row_index, '0', $subtype]);
        }
    }

    public function set_chart_queue(Request $request)
    {
        if ($request->input('type') == 'remove') {
            DB::table('hippa')->where('hippa_id', '=', $request->input('id'))->delete();
            $this->audit('Delete');
            $message = 'Item removed';
        } else {
            $id_arr = explode(',', $request->input('id'));
            $data = [
                'other_hippa_id' => $id_arr[2],
                'pid' => Session::get('pid'),
                'practice_id' => Session::get('practice_id')
            ];
            $data[$id_arr[0]] = $id_arr[1];
            DB::table('hippa')->insert($data);
            $this->audit('Add');
            $message = "Item added to queue!";
        }
        return $message;
    }

    public function t_messaging_session(Request $request)
    {
        $arr = [
            't_messages_subject' => $request->input('t_messages_subject'),
            't_messages_message' => $request->input('t_messages_message'),
            't_messages_dos' => $request->input('t_messages_dos'),
            't_messages_provider' => $request->input('t_messages_provider'),
            't_messages_signed' => $request->input('t_messages_signed'),
            't_messages_to' => $request->input('t_messages_to'),
            't_messages_from' => $request->input('t_messages_from'),
            'pid' => $request->input('pid'),
            'practice_id' => $request->input('practice_id'),
            't_messages_id' => $request->input('t_messages_id')
        ];
        Session::put('session_t_message', $arr);
        return 'OK T-message' . $arr['t_messages_id'];
    }

    public function test_reminder()
    {
        $row = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $to = $row->reminder_to;
        $result = trans('nosh.no_reminder');
        if ($to != '') {
            $data_message['item'] = trans('nosh.test_reminder');
            if ($row->reminder_method == 'Cellular Phone') {
                $message = view('emails.blank', $data_message)->render();
                $this->textbelt($row->phone_cell, $message, Session::get('practice_id'));
                $result = trans('nosh.sms_success');
            } else {
                $this->send_mail('emails.blank', $data_message, 'Test Notification', $to, Session::get('practice_id'));
                $result = trans('nosh.email_success');
            }
        }
        return $result;
    }
}
