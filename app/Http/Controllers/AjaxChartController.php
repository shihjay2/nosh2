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

    public function set_ccda_data(Request $request)
    {
        $data = $request->all();
        Session::put('ccda', $data);
        $columns = Schema::getColumnListing($data['type']);
        $row_index = $columns[0];
        $subtype = '';
        if ($data['type'] == 'issues') {
            $subtype = 'pl';
        }
        return route('chart_form', [$data['type'], $row_index, '0', $subtype]);
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

    public function test_reminder()
    {
        $row = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $to = $row->reminder_to;
        $result = 'No reminder method set.';
        if ($to != '') {
            if ($row->reminder_method == 'Cellular Phone') {
                $data_message['item'] = 'This is a test';
                $message = view('emails.blank', $data_message)->render();
                $this->textbelt($row->phone_cell, $message, Session::get('practice_id'));
                $result = 'SMS sent successfully.';
            } else {
                $data_message['item'] = 'This is a test';
                $this->send_mail('emails.blank', $data_message, 'Test Notification', $to, Session::get('practice_id'));
                $result = 'Email sent successfully.';
            }
        }
        return $result;
    }
}
