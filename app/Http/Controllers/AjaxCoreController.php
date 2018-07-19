<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use Date;
use DB;
use File;
use Form;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use QrCode;
use Session;
use URL;

class AjaxCoreController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('csrf');
    }

    public function add_cpt(Request $request)
    {
        $cpt = DB::table('cpt')->where('cpt', '=', $request->input('cpt'))->first();
        if ($cpt) {
            $data = [
                'cpt' => $cpt->cpt,
                'cpt_description' => $cpt->cpt_description,
                'cpt_charge' => $cpt->cpt_charge,
                'practice_id' => Session::get('practice_id')
            ];
        } else {
            $data = [
                'cpt' => $request->input('cpt'),
                'cpt_charge' => $request->input('cpt_charge'),
                'practice_id' => Session::get('practice_id'),
                'unit' => $request->input('unit')
            ];
        }
        if ($request->has('type')) {
            if ($request->input('type') == 'favorite') {
                $data['favorite'] = '1';
            }
        }
        DB::table('cpt_relate')->insert($data);
        return 'OK';
    }

    public function check_cpt(Request $request)
    {
        $return = 'y';
        $query = DB::table('cpt_relate')->where('cpt', '=', $request->input('cpt'))->where('practice_id', '=', Session::get('practice_id'))->where('favorite', '=', '1')->first();
        if ($query) {
            $return = 'n';
        }
        $query1 = DB::table('cpt')->where('cpt', '=', $request->input('cpt'))->first();
        if ($query1) {
            $return = 'n';
        }
        return $return;
    }

    public function document_delete(Request $request)
    {
        unlink(Session::get('file_path_temp'));
        Session::forget('file_path_temp');
        return 'true';
    }

    public function education(Request $request)
    {
        $data = $this->healthwise_view($request->input('url'));
        return $data;
    }

    public function image_dimensions(Request $request)
    {
        $result = getimagesize($request->input('file'));
        $data = [
            'width' => $result[0],
            'height' => $result[1]
        ];
        return $data;
    }

    public function last_page(Request $request, $hash)
    {
        if (Session::has('last_page')) {
            $core = explode('#', Session::get('last_page'));
            Session::put('last_page', $core[0] . '#' . $hash);
            if (Session::has('eid')) {
                $core1 = explode('#', Session::get('last_page_encounter'));
                Session::put('last_page_encounter', $core1[0] . '#' . $hash);
                Session::put('action_redirect', $core1[0] . '#' . $hash);
            }
        }
        return 'OK';
    }

    public function messaging_session(Request $request)
    {
        $arr = [
            'pid' => $request->input('pid'),
            'patient_name' => $request->input('patient_name'),
            'message_to' => $request->input('message_to'),
            'cc' => $request->input('cc'),
            'message_from' => $request->input('message_from'),
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
            't_messages_id' => $request->input('t_messages_id'),
            'practice_id' => $request->input('practice_id'),
            'message_id' => $request->input('message_id')
        ];
        Session::put('session_message', $arr);
        return 'OK';
    }

    public function notification(Request $request)
    {
        if (Session::has('notifications')) {
            $data = Session::get('notifications');
        } else {
            $data = [
                'appt' => '',
                'appt_arr' => [],
                'appt_header' => 'Appointment Alert',
                'alert' => '',
                'alert_arr' => [],
                'alert_header' => 'Alert Notification'
            ];
        }
        $pid = Session::get('pid');
        $user_id = Session::get('user_id');
        $practice_id = Session::get('practice_id');
        $start_time = time() - 86400;
        $end_time = time() + 86400;
        $query = DB::table('schedule')->where('pid', '=', Session::get('pid'))
            ->whereBetween('start', [$start_time, $end_time])
            ->first();
        if ($query) {
            if ($query->notes != '') {
                if (!in_array($query->notes, $data['appt_arr'])) {
                    $data['appt'] = $query->notes;
                    $data['appt_arr'][] = $query->notes;
                } else {
                    $data['appt'] = '';
                }
            }
        }
        $query1 = DB::table('alerts')
            ->where('pid', '=', $pid)
            ->where('alert_date_complete', '=', '0000-00-00 00:00:00')
            ->where('alert_reason_not_complete', '=', '')
            ->where('practice_id', '=', $practice_id)
            ->get();
        if ($query1) {
            foreach ($query1 as $row1) {
                $alert_date = $this->human_to_unix($row1->alert_date_active);
                if ($alert_date >= $start_time && $alert_date <= $end_time) {
                    $alert = $row1->alert . ': ' . $row1->alert_description;
                    if (!in_array($alert, $data['alert_arr'])) {
                        $data['alert_arr'][] = $alert;
                        $data['alert'] = implode('<br>', $data['alert_arr']);
                    } else {
                        $data['alert'] = '';
                    }
                }
            }
        }
        Session::put('notifications', $data);
        return $data;
    }

    public function progress(Request $request)
    {
        $progress = '0';
        $file = public_path() . '/temp/' . $request->input('id');
        if (file_exists($file)) {
            sleep(2);
            $progress = File::get($file);
        }
        return $progress;
    }

    public function read_message(Request $request)
    {
        $data['read'] = 'y';
        $message = DB::table('messaging')->where('message_id', '=', $request->input('id'))->first();
        DB::table('messaging')->where('message_id', '=', $request->input('id'))->update($data);
        $this->audit('Update');
        $origin = DB::table('messaging')->where('body', '=', $message->body)->where('message_id', '!=', $request->input('id'))->where('mailbox', '=', '0')->first();
        if ($origin) {
            DB::table('messaging')->where('message_id', '=', $origin->message_id)->update($data);
            $this->audit('Update');
        }
        return 'Message read';
    }

    public function superquery_tag_view(Request $request)
    {
        $table = $request->input('table');
        $row = DB::table($table)->where($request->input('index'), '=', $request->input('id'))->first();
        $row1 = DB::table('demographics')->where('pid', '=', $request->input('pid'))->first();
        $html = '<strong>Patient:</strong>  ' . $row1->firstname . " " . $row1->lastname . '<br><br>';
        if ($table == 'messaging') {
            $html .= '<strong>Date:</strong>  ' . date('m/d/Y', $this->human_to_unix($row->date)) . '<br><br><strong>Subject:</strong>  ' . $row->subject . '<br><br><strong>Message:</strong> ' . nl2br($row->body);
        }
        if ($table == 'schedule') {
            $html .= '<strong>Start Date:</strong>  ' . date('m/d/Y h:i A', $row->start) . '<br><br><strong>End Date:</strong>  ' . date('m/d/Y h:i A', $row->end) . '<br><br><strong>Visit Type:</strong> ' . $row->visit_type . '<br><br><strong>Reason:</strong> ' . $row->reason . '<br><br><strong>Status:</strong> ' . $row->status;
        }
        if ($table == 'mtm') {
            if ($row->mtm_date_completed != '') {
                $html .= '<strong>Date Completed:</strong>  ' . date('m/d/Y', $this->human_to_unix($row->mtm_date_completed));
            }
            $html .= '<br><br><strong>Description:</strong>  ' . nl2br($row->mtm_description) . '<br><br><strong>Recommendations:</strong>  ' . nl2br($row->mtm_recommendations) . '<br><br><strong>Beneficiary Notes:</strong>  ' . nl2br($row->mtm_beneficiary_notes) . '<br><br><strong>Action:</strong>  ' . nl2br($row->mtm_action) . '<br><br><strong>Outcomes:</strong>  ' . nl2br($row->mtm_outcomes) . '<br><br><strong>Related Conditions:</strong>  ' . nl2br($row->mtm_related_conditions);
        }
        return $html;
    }

    public function update_cpt(Request $request)
    {
        $cpt = DB::table('cpt_relate')->where('cpt', '=', $request->input('cpt'))->where('practice_id', '=', Session::get('practice_id'))->first();
        if ($cpt) {
            $data = [
                'cpt_charge' => $request->input('cpt_charge'),
                'unit' => $request->input('unit')
            ];
            DB::table('cpt_relate')->where('cpt', '=', $cpt->cpt)->update($data);
            $message = 'CPT updated';
        } else {
            $data['cpt'] = $request->input('cpt');
            $data['practice_id'] = Session::get('practice_id');
            DB::table('cpt_relate')->insert($data);
            $message = 'CPT added to practice';
        }
        return $message;
    }

    // Future Functions
    public function hieofone()
    {
        $arr['response'] = 'y';
        $arr['message'] = "Credentials transferred!";
        $url = 'https://noshchartingsystem.com/nosh-sso/noshadduser';
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        if ($request->input('username') != '') {
            $username = $request->input('username');
        } else {
            $username = $user->username;
        }
        $provider = DB::table('providers')->where('id', '=', Session::get('user_id'))->first();
        $data = [
            'username' => $username,
            'password' => $user->password,
            'email' => $user->email,
            'npi' => $provider->npi,
            'name' => $user->displayname,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'middle' => $user->middle
        ];
        $result = $this->send_api_data($url, $data, '', '');
        if ($result['url_error'] != '') {
            $arr['response'] = 'n';
            $arr['message'] = $result['url_error'];
        } else {
            if ($result['error'] == true) {
                $arr['response'] = 'n';
                $arr['message'] = $result['message'];
            } else {
                $new_data = array(
                    'uid' => $result['uid']
                );
                DB::table('users')->where('id', '=', Session::get('user_id'))->update($new_data);
                $this->audit('Update');
            }
        }
        echo json_encode($arr);
    }

}
