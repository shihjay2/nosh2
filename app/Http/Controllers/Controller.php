<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

use App\Libraries\Phaxio;
use Cezpdf;
use Config;
use Date;
use DB;
use Excel;
use Exception;
use File;
use Form;
use Google_Client;
use GuzzleHttp;
use HTML;
use Htmldom;
use Laravel\LegacyEncrypter\McryptEncrypter;
use Mail;
use PDF;
use PragmaRX\Countries\Package\Countries;
use Request;
use Schema;
use shihjay2\tcpdi_merger\MyTCPDI;
use shihjay2\tcpdi_merger\Merger;
use Shihjay2\OpenIDConnectUMAClient;
use Swift_Mailer;
use Swift_SmtpTransport;
use Session;
use SoapBox\Formatter\Formatter;
use URL;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // ACL filters
    // 1 = Providers, Assistants, Billers
    // 2 = Providers, Assistants
    // 3 = Providers
    // 4 = Patients
    // 5 = Admin
    // 6 = Admin + Patient Centric Providers
    // 7 = Providers, Assistants, Patient
    // 8 = Providers, Assistanats, Admin
    protected function access_level($type)
    {
        if ($type == '1') {
            if (Session::get('group_id') == '2' || Session::get('group_id') == '3' || Session::get('group_id') == '4') {
                return true;
            }
        }
        if ($type == '2') {
            if (Session::get('group_id') == '2' || Session::get('group_id') == '3') {
                return true;
            }
        }
        if ($type == '3') {
            if (Session::get('group_id') == '2') {
                return true;
            }
        }
        if ($type == '4') {
            if (Session::get('group_id') == '100') {
                return true;
            }
        }
        if ($type == '5') {
            if (Session::get('group_id') == '1') {
                return true;
            }
        }
        if ($type == '6') {
            if (Session::get('group_id') == '2' && Session::get('patient_centric') == 'yp') {
                return true;
            } elseif (Session::get('group_id') == '1') {
                return true;
            }
        }
        if ($type == '7') {
            if (Session::get('group_id') == '2' || Session::get('group_id') == '3' || Session::get('group_id') == '100') {
                return true;
            }
        }
        if ($type == '8') {
            if (Session::get('group_id') == '2' || Session::get('group_id') == '3' || Session::get('group_id') == '1') {
                return true;
            }
        }
        return false;
    }

    protected function actions_build($table, $index, $id, $column='actions')
    {
        $return = '';
        $query = DB::table($table)->where($index, '=', $id)->first();
        if ($query) {
            if ($query->{$column} !== '' && $query->{$column} !== null) {
                if ($this->yaml_check($query->{$column})) {
                    $formatter = Formatter::make($query->{$column}, Formatter::YAML);
                    $arr = $formatter->toArray();
                    $list_array = [];
                    foreach ($arr as $k=>$v) {
                        if ($column == 'proc_description') {
                            $arr['label'] = '<b>' . $v['timestamp'] . ':</b> ' . $v['type'];
                        } else {
                            $arr['label'] = '<b>' . $v['timestamp'] . ':</b> ' . $v['action'];
                        }
                        $arr['edit'] = route('action_edit', [$table, $index, $id, $k, $column]);
                        $list_array[] = $arr;
                    }
                    $return .= $this->result_build($list_array, 'actions_list');
                } else {
                    $return .= $query->{$column};
                }
            }
        }
        return $return;
    }

    protected function add_closed($repeat_start, $repeat_end, $end, $day, $events)
    {
        while ($repeat_start <= $end) {
            $events[] = [
                'id' => $day,
                'title' => 'Closed',
                'start' => date('c', $repeat_start),
                'end' => date('c', $repeat_end),
                'className' => 'colorblack',
                'editable' => false,
                'reason' => 'Closed',
                'status' => 'Closed',
                'notes' => ''
            ];
            $repeat_start = $repeat_start + 604800;
            $repeat_end = $repeat_end + 604800;
        }
        return $events;
    }

    protected function add_closed1($day, $minTime, $day2, $events, $start, $end)
    {
        $repeat_start = strtotime('this ' . $day . ' ' . $minTime, $start);
        $repeat_end = strtotime('this ' . $day . ' ' . $day2, $start);
        $events = $this->add_closed($repeat_start, $repeat_end, $end, $day, $events);
        return $events;
    }

    protected function add_closed2($day, $maxTime, $day2, $events, $start, $end)
    {
        $repeat_start = strtotime('this ' . $day . ' ' . $day2, $start);
        $repeat_end = strtotime('this ' . $day . ' ' . $maxTime, $start);
        $events = $this->add_closed($repeat_start, $repeat_end, $end, $day, $events);
        return $events;
    }

    protected function add_closed3($day, $minTime, $maxTime, $events, $start, $end)
    {
        $repeat_start = strtotime('this ' . $day . ' ' . $minTime, $start);
        $repeat_end = strtotime('this ' . $day . ' ' . $maxTime, $start);
        $events = $this->add_closed($repeat_start, $repeat_end, $end, $day, $events);
        return $events;
    }

    protected function add_mtm_alert($pid, $type)
    {
        $practice_id = Session::get('practice_id');
        if ($type == 'issues') {
            $query = DB::table('issues')->where('pid', '=', $pid)->where('issue_date_inactive', '=', '0000-00-00 00:00:00')->first();
        }
        if ($type == 'medications') {
            $query = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->first();
        }
        if($query) {
            $query1 = DB::table('alerts')
                ->where('pid', '=', $pid)
                ->where('alert_date_complete', '=', '0000-00-00 00:00:00')
                ->where('alert_reason_not_complete', '=', '')
                ->where('alert', '=', 'Medication Therapy Management')
                ->where('practice_id', '=', $practice_id)
                ->first();
            if (!$query1) {
                $data = [
                    'alert' => 'Medication Therapy Management',
                    'alert_description' => 'Medication therapy management is needed due to more than 2 active medications or issues.',
                    'alert_date_active' => date('Y-m-d H:i:s', time()),
                    'alert_date_complete' => '',
                    'alert_reason_not_complete' => '',
                    'pid' => $pid,
                    'practice_id' => $practice_id
                ];
                DB::table('alerts')->insert($data);
                $this->audit('Add');
            }
        }
        return true;
    }

    protected function age_calc($num, $type)
    {
        if ($type == 'year') {
            $a = 31556926*$num;
        }
        if ($type == 'month') {
            $a = 2629743*$num;
        }
        $b = time() - $a;
        return $b;
    }

    protected function alert_message_send()
    {
        $i = 0;
        $query = DB::table('alerts')
            ->where('alert_send_message', '=', 'y')
            ->where('alert_date_complete', '=', '0000-00-00 00:00:00')
            ->where('alert_reason_not_complete', '=', '')
            ->where('alert_date_active', '<=', date('Y-m-d H:i:s', time() + 604800))
            ->get();
        if ($query) {
            foreach ($query as $alert) {
                $row = DB::table('demographics')->where('pid', '=', $alert->pid)->first();
                $row_relate = DB::table('demographics_relate')
                    ->where('pid', '=', $alert->pid)
                    ->where('practice_id', '=', $alert->practice_id)
                    ->first();
                $practice = DB::table('practiceinfo')->where('practice_id', '=', $alert->practice_id)->first();
                $from = $alert->alert_provider;
                $patient_name = $row->lastname . ', ' . $row->firstname . ' (DOB: ' . date('m/d/Y', strtotime($row->DOB)) . ') (ID: ' . $row->pid . ')';
                $patient_name1 = $row->lastname . ', ' . $row->firstname . ' (ID: ' . $row->pid . ')';
                $subject = $alert->alert;
                $body = $alert->alert_description;
                $data = [
                    'pid' => $alert->pid,
                    'patient_name' => $patient_name,
                    'message_to' => $patient_name1,
                    'cc' => '',
                    'message_from' => $from,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'Sent',
                    'mailbox' => $row_relate->id,
                    'practice_id' => $alert->practice_id
                ];
                DB::table('messaging')->insert($data);
                $this->audit('Add');
                $data1a = [
                    'pid' => $alert->pid,
                    'patient_name' => $patient_name,
                    'message_to' => $patient_name1,
                    'cc' => '',
                    'message_from' => $from,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'Sent',
                    'mailbox' => '0',
                    'practice_id' => $alert->practice_id
                ];
                DB::table('messaging')->insert($data1a);
                $this->audit('Add');
                if ($row->email != '') {
                    $data_message['patient_portal'] = $practice->patient_portal;
                    $this->send_mail('emails.newmessage', $data_message, 'New Message in your Patient Portal', $row->email, $alert->practice_id);
                }
                $data2['alert_send_message'] = 's';
                DB::table('alerts')->where('alert_id', '=', $alert->alert_id)->update($data2);
                $i++;
            }
        }
        return $i;
    }

    protected function api_data($action, $table, $primary, $id)
	{
		$check = DB::table('demographics_relate')->where('pid', '=', Session::get('pid'))->whereNotNull('api_key')->first();
		if ($check) {
			$row = DB::table($table)->where($primary, '=', $id)->first();
            $row_data = json_decode(json_encode($row), true);
			unset($row_data[$primary]);
			$remote_id = '0';
			$proceed = true;
			if ($action == 'update' || $action == 'delete') {
				$check1 = DB::table('api_queue')
					->where('table', '=', $table)
					->where('local_id', '=', $id)
					->where('remote_id', '!=', '0')
					->where('action', '!=', 'delete')
					->first();
				if ($check1) {
					$remote_id = $check1->remote_id;
				} else {
					if ($action == 'delete') {
						$action = 'add';
					} else {
						$proceed = false;
					}
				}
			}
			$json_data = [
				'api_key' => $check->api_key,
				'table' => $table,
				'primary' => $primary,
				'remote_id' => $remote_id,
				'action' => $action,
				'data' => $row_data
			];
			$json = serialize(json_encode($json_data));
			$practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
			$login_data = [
				'api_key' => $check->api_key,
				'npi' => $practice->npi
			];
			$login = serialize(json_encode($login_data));
			$data = [
				'table' => $table,
				'primary' => $primary,
				'local_id' => $id,
				'remote_id' => $remote_id,
				'action' => $action,
				'json' => $json,
				'login' => $login,
				'url' => $check->url,
				'api_key' => $check->api_key
			];
			if ($proceed == true) {
				DB::table('api_queue')->insert($data);
				$this->audit('Add');
			}
		}
	}

    protected function api_data_send($url, $data, $username, $password)
    {
        if (is_array($data)) {
            $data_string = json_encode($data);
        } else {
            $data_string = $data;
        }
        $ch = curl_init($url);
        if ($username != '') {
            curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)]
        );
        $result = curl_exec($ch);
        $result_arr = json_decode($result, true);
        if(curl_errno($ch)){
            $result_arr['url_error'] = 'Error:' . curl_error($ch);
        } else {
            $result_arr['url_error'] = '';
        }
        curl_close($ch);
        return $result_arr;
    }

    protected function api_process()
    {
        $i = 0;
        $apis = DB::table('api_queue')->where('success', '=', 'n')->get();
        foreach ($apis as $api) {
            $json_data = unserialize($api->json);
            $login_data = unserialize($api->login);
            $login_url = $api->url . '/api_login';
            $logout_url = $api->url . '/api_logout';
            $api_url = $api->url . '/api/v1/' . $api->action;
            $login = $this->api_data_send($login_url, $login_data, '', '');
            $data['success'] = 'n';
            if ($login['url_error'] == '') {
                if (isset($login['error'])) {
                    if ($login['error'] == true) {
                        $data['response'] = 'Error: ' . $login['message'];
                    } else {
                        $result = $this->api_data_send($api_url, $json_data, $login['username'], $login['password']);
                        if ($result['url_error'] == '') {
                            if (isset($result['error'])) {
                                if ($result['error'] == true) {
                                    $data['response'] = 'Error: ' . $result['message'];
                                } else {
                                    $data['success'] = 'y';
                                    $data['remote_id'] = $result['remote_id'];
                                    $data['response'] = $result['message'];
                                    $i++;
                                }
                            } else {
                                $data['response'] = 'Error: not a valid connection, check your URL';
                            }
                        } else {
                            $data['response'] = $result['url_error'];
                        }
                        $logout = $this->api_data_send($logout_url, $login_data, '', '');
                    }
                } else {
                    $data['response'] = 'Error: not a valid connection, check your URL';
                }
            } else {
                $data['response'] = $login['url_error'];
            }
            DB::table('api_queue')->where('id', '=', $api->id)->update($data);
            $this->audit('Update');
        }
    }

    protected function appointment_reminder($practice_id)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        if ($practice->timezone != null) {
            date_default_timezone_set($practice->timezone);
        }
        $date = date('Y-m-d');
        $i = 0;
        $query = DB::table('demographics')
            ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
            ->select('demographics.firstname', 'demographics.reminder_to', 'demographics.reminder_method', 'demographics.preferred_provider')
            ->where('demographics_relate.practice_id', '=', $practice_id)
            ->where('demographics.active', '=', '1')
            ->where('demographics.reminder_to', '!=', '')
            ->where('demographics_relate.appointment_reminder', '=', $date)
            ->get();
        if ($query) {
            foreach ($query as $row) {
                $to = $row->reminder_to;
                if ($to != '') {
                    $data_message['phone'] = $practice->phone;
                    $data_message['email'] = $practice->email;
                    $data_message['appointment_message'] = $practice->appointment_message;
                    $data_message['patientname'] = $row->firstname;
                    $data_message['patient_portal'] = $practice->patient_portal;
                    if ($row->preferred_provider == '') {
                        $data_message['doctor'] = $practice->practice_name;
                    } else {
                        $data_message['doctor'] = $row->preferred_provider;
                    }
                    if ($row->reminder_method == 'Cellular Phone') {
                        $this->send_mail(array('text' => 'emails.appointmentremindertext'), $data_message, 'Continuing Care Reminder', $to, $practice_id);
                    } else {
                        $this->send_mail('emails.appointmentreminder', $data_message, 'Continuing Care Reminder', $to, $practice_id);
                    }
                    $i++;
                }
            }
        }
        $data['appointment_sent_date'] = date('Y-m-d');
        DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data);
        $this->audit('Update');
        return $i;
    }

    protected function appointment_screen($practice_id)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        if ($practice->timezone != null) {
            date_default_timezone_set($practice->timezone);
        }
        $i = 0;
        $query = DB::table('demographics')
            ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
            ->select('demographics_relate.appointment_reminder', 'demographics.pid', 'demographics_relate.demographics_relate_id')
            ->where('demographics_relate.practice_id', '=', $practice_id)
            ->where('demographics.active', '=', '1')
            ->where('demographics.reminder_to', '!=', '')
            ->get();
        if ($query) {
            foreach ($query as $row) {
                $row2 = DB::table('schedule')->where('pid', '=', $row->pid)->where('start', '>', time())->first();
                if (isset($row2->start)) {
                    $data['appointment_reminder'] = 'n';
                    DB::table('demographics_relate')->where('demographics_relate_id', '=', $row->demographics_relate_id)->update($data);
                    $this->audit('Update');
                } else {
                    if ($row->appointment_reminder == 'n') {
                        $newdate = time() + $practice->appointment_interval;
                        $data['appointment_reminder'] = date('Y-m-d', $newdate);
                        DB::table('demographics_relate')->where('demographics_relate_id', '=', $row->demographics_relate_id)->update($data);
                        $this->audit('Update');
                        $i++;
                    }
                }
            }
        }
        return $i;
    }

    protected function array_assessment()
    {
        $return = [
            'assessment_other' => [
                'standardmtm' => 'SOAP Note',
                'standard' => 'Additional Diagnoses'
            ],
            'assessment_ddx' => [
                'standardmtm' => 'MAP2',
                'standard' => 'Differential Diagnoses Considered'
            ],
            'assessment_notes' => [
                'standardmtm' => 'Pharmacist Note',
                'standard' => 'Assessment Discussion'
            ]
        ];
        return $return;
    }

    protected function array_assessment_billing($eid)
    {
        $data = DB::table('assessment')->where('eid', '=', $eid)->first();
        $return = [];
        // $return[''] = 'Choose Assessment';
        if ($data) {
            $a = 'A';
            for ($i=1; $i<=12; $i++) {
                $col = 'assessment_' . $i;
                if ($data->{$col} !== '' && $data->{$col} !== null) {
                    $return[$a] = $a . ' - ' . $data->{$col};
                    $a++;
                }
            }
        }
        return $return;
    }

    protected function array_billing($type='')
    {
        $arr = [
            'eid' => [
                'id' => 'eid',
                'name' => 'Encounter ID'
            ],
            'pid' => [
                'id' => 'pid',
                'name' => 'Patient ID'
            ],
            'insurance_id_1' => [
                'id' => 'insurance_id_1',
                'name' => 'Insurance ID 1'
            ],
            'insurance_id_2' => [
                'id' => 'insurance_id_2',
                'name' => 'Insurance ID 2'
            ],
            'bill_complex' => [
                'id' => 'bill_complex',
                'name' => 'Visit Complexity'
            ],
            'bill_Box11C' => [
                'id' => 'bill_Box11C',
                'hcfa' => '^Bx11c*********************^',
                'name' => 'Insurance Plan Name',
                'len' => 28
            ],
            'bill_payor_id' => [
                'id' => 'bill_payor_id',
                'hcfa' => '^Pay^',
                'name' => 'Payor ID',
                'len' => 5
            ],
            'bill_ins_add1' => [
                'id' => 'bill_ins_add1',
                'hcfa' => '^InsuranceAddress*************^',
                'name' => 'Insurance Street Address Line 1',
                'len' => 31
            ],
            'bill_ins_add2' => [
                'id' => 'bill_ins_add2',
                'hcfa' => '^InsuranceAddress2************^',
                'name' => 'Insurance Street Address Line 2',
                'len' => 31
            ],
            'bill_Box1' => [
                'id' => 'bill_Box1',
                'hcfa' => '^Bx1****************************************^',
                'name' => 'Insurance Type',
                'len' => 45
            ],
            'bill_Box1P' => [
                'id' => 'bill_Box1P',
                'hcfa' => '',
                'name' => 'Insurance Type Formal'
            ],
            'bill_Box1A' => [
                'id' => 'bill_Box1A',
                'hcfa' => '^Bx1a**********************^',
                'name' => 'Insured ID Number',
                'len' => 28
            ],
            'bill_Box2' => [
                'id' => 'bill_Box2',
                'hcfa' => '^Bx2***********************^',
                'name' => 'Patient Name',
                'len' => 28
            ],
            'bill_Box3A' => [
                'id' => 'bill_Box3A',
                'hcfa' => '^Bx3a****^',
                'name' => 'Patient Date of Birth',
                'len' => 10
            ],
            'bill_Box3B' => [
                'id' => 'bill_Box3B',
                'hcfa' => '^Bx3b^',
                'name' => 'Patient Gender',
                'len' => 6
            ],
            'bill_Box3BP' => [
                'id' => 'bill_Box3BP',
                'hcfa' => '',
                'name' => 'Patient Gender Formal'
            ],
            'bill_Box4' => [
                'id' => 'bill_Box4',
                'hcfa' => '^Bx4***********************^',
                'name' => 'Insured Name',
                'len' => 28
            ],
            'bill_Box5A' => [
                'id' => 'bill_Box5A',
                'hcfa' => '^Bx5a**********************^',
                'name' => 'Patient Address',
                'len' => 28
            ],
            'bill_Box6' => [
                'id' => 'bill_Box6',
                'hcfa' => '^Bx6**********^',
                'name' => 'Patient Relationship to Insured',
                'len' => 15
            ],
            'bill_Box6P' => [
                'id' => 'bill_Box6P',
                'hcfa' => '',
                'name' => 'Patient Relationship to Insured Formal'
            ],
            'bill_Box7A' => [
                'id' => 'bill_Box7A',
                'hcfa' => '^Bx7a**********************^',
                'name' => 'Insured Address',
                'len' => 28
            ],
            'bill_Box5B' => [
                'id' => 'bill_Box5B',
                'hcfa' => '^Bx5b******************^',
                'name' => 'Patient City',
                'len' => 24
            ],
            'bill_Box5C' => [
                'id' => 'bill_Box5C',
                'hcfa' => '^5^',
                'name' => 'Patient State',
                'len' => 3
            ],
            'bill_Box7B' => [
                'id' => 'bill_Box7B',
                'hcfa' => '^Bx7b*****************^',
                'name' => 'Insured City',
                'len' => 23
            ],
            'bill_Box7C' => [
                'id' => 'bill_Box7C',
                'hcfa' => '^7*^',
                'name' => 'Insured State',
                'len' => 4
            ],
            'bill_Box5D' => [
                'id' => 'bill_Box5D',
                'hcfa' => '^Bx5d******^',
                'name' => 'Patient Zip',
                'len' => 12
            ],
            'bill_Box5E'  => [
                'id' => 'bill_Box5E',
                'hcfa' => '^Bx5e********^',
                'name' => 'Patient Phone',
                'len' => 14
            ],
            'bill_Box7D' => [
                'id' => 'bill_Box7D',
                'hcfa' => '^Bx7d******^',
                'name' => 'Insured Zip',
                'len' => 12
            ],
            'bill_Box7E' => [
                'id' => 'bill_Box7E',
                'hcfa' => '^Bx7e*******^',
                'name' => 'Insured Phone',
                'len' => 13
            ],
            'bill_Box9' => [
                'id' => 'bill_Box9',
                'hcfa' => '^Bx9***********************^',
                'name' => 'Other Insured Name',
                'len' => 28
            ],
            'bill_Box11' => [
                'id' => 'bill_Box11',
                'hcfa' => '^Bx11**********************^',
                'name' => 'Insured Group Number',
                'len' => 28
            ],
            'bill_Box9A' => [
                'id' => 'bill_Box9A',
                'hcfa' => '^Bx9a**********************^',
                'name' => 'Other Insured Group Number',
                'len' => 28
            ],
            'bill_Box10A' => [
                'id' => 'bill_Box10A',
                'hcfa' => '^Bx10a^',
                'name' => 'Condition Employment',
                'len' => 7
            ],
            'bill_Box10AP' => [
                'id' => 'bill_Box10AP',
                'hcfa' => '',
                'name' => 'Condition Employment Formal'
            ],
            'bill_Box11A1' => [
                'id' => 'bill_Box11A1',
                'hcfa' => '^Bx11a***^',
                'name' => 'Insured Date of Birth',
                'len' => 10
            ],
            'bill_Box11A2' => [
                'id' => 'bill_Box11A2',
                'hcfa' => '^Bx11aa^',
                'name' => 'Insured Gender',
                'len' => 8
            ],
            'bill_Box11A2P' => [
                'id' => 'bill_Box11A2P',
                'hcfa' => '',
                'name' => 'Insured Gender Formal'
            ],
            'bill_Box10B1' => [
                'id' => 'bill_Box10B1',
                'hcfa' => '^Bx10b^',
                'name' => 'Condition Auto Accident',
                'len' => 7
            ],
            'bill_Box10B1P' => [
                'id' => 'bill_Box10B1P',
                'hcfa' => '',
                'name' => 'Condition Auto Accident Formal'
            ],
            'bill_Box10B2' => [
                'id' => 'bill_Box10B2',
                'hcfa' => '^b^',
                'name' => 'Condition Auto Accident State',
                'len' => 3
            ],
            'bill_Box11B' => [
                'id' => 'bill_Box11B',
                'hcfa' => '^Bx11b*********************^',
                'name' => 'Insured Employer',
                'len' => 28
            ],
            'bill_Box10C' => [
                'id' => 'bill_Box10C',
                'hcfa' => '^Bx10c^',
                'name' => 'Condition Other Accident',
                'len' => 7
            ],
            'bill_Box10CP' => [
                'id' => 'bill_Box10CP',
                'hcfa' => '',
                'name' => 'Condition Other Accident Formal'
            ],
            'bill_Box9D' => [
                'id' => 'bill_Box9D',
                'hcfa' => '^Bx9d**********************^',
                'name' => 'Other Insurance Plan Name',
                'len' => 28
            ],
            'bill_Box11D' => [
                'id' => 'bill_Box11D',
                'hcfa' => '^B11d^',
                'name' => 'Another Health Benefit Plan',
                'len' => 6
            ],
            'bill_Box11DP' => [
                'id' => 'bill_Box11DP',
                'hcfa' => '',
                'name' => 'Another Health Benefit Plan Formal'
            ],
            'bill_Box17' => [
                'id' => 'bill_Box17',
                'hcfa' => '^Bx17**********************^',
                'name' => 'Referring Provider',
                'len' => 28
            ],
            'bill_Box17A' => [
                'id' => 'bill_Box17A',
                'hcfa' => '^Bx17a**********^',
                'name' => 'Provider NPI',
                'len' => 17
            ],
            'bill_Box21A' => [
                'id' => 'bill_Box21A',
                'hcfa' => '@',
                'name' => 'ICD Type',
                'len' => 1
            ],
            'bill_Box21_1' => [
                'id' => 'bill_Box21_1',
                'hcfa' => '^Bx21a*^',
                'name' => 'ICD1',
                'len' => 8
            ],
            'bill_Box21_2' => [
                'id' => 'bill_Box21_2',
                'hcfa' => '^Bx21b*^',
                'name' => 'ICD2',
                'len' => 8
            ],
            'bill_Box21_3' => [
                'id' => 'bill_Box21_3',
                'hcfa' => '^Bx21c*^',
                'name' => 'ICD3',
                'len' => 8
            ],
            'bill_Box21_4' => [
                'id' => 'bill_Box21_4',
                'hcfa' => '^Bx21d*^',
                'name' => 'ICD4',
                'len' => 8
            ],
            'bill_Box21_5' => [
                'id' => 'bill_Box21_5',
                'hcfa' => '^Bx21e*^',
                'name' => 'ICD5',
                'len' => 8
            ],
            'bill_Box21_6' => [
                'id' => 'bill_Box21_6',
                'hcfa' => '^Bx21f*^',
                'name' => 'ICD6',
                'len' => 8
            ],
            'bill_Box21_7' => [
                'id' => 'bill_Box21_7',
                'hcfa' => '^Bx21g*^',
                'name' => 'ICD7',
                'len' => 8
            ],
            'bill_Box21_8' => [
                'id' => 'bill_Box21_8',
                'hcfa' => '^Bx21h*^',
                'name' => 'ICD8',
                'len' => 8
            ],
            'bill_Box21_9' => [
                'id' => 'bill_Box21_9',
                'hcfa' => '^Bx21i*^',
                'name' => 'ICD9',
                'len' => 8
            ],
            'bill_Box21_10' => [
                'id' => 'bill_Box21_10',
                'hcfa' => '^Bx21j*^',
                'name' => 'ICD10',
                'len' => 8
            ],
            'bill_Box21_11' => [
                'id' => 'bill_Box21_11',
                'hcfa' => '^Bx21k*^',
                'name' => 'ICD11',
                'len' => 8
            ],
            'bill_Box21_12' => [
                'id' => 'bill_Box21_12',
                'hcfa' => '^Bx21l*^',
                'name' => 'ICD12',
                'len' => 8
            ],
            'bill_DOS1F' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'dos_f',
                'hcfa' => '^DOS1F*^',
                'name' => 'DOS F',
                'len' => 8
            ],
            'bill_DOS1T' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'dos_t',
                'hcfa' => '^DOS1T*^',
                'name' => 'DOS T',
                'len' => 8
            ],
            'bill_DOS2F' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'dos_f',
                'hcfa' => '^DOS2F*^',
                'name' => 'DOS F',
                'len' => 8
            ],
            'bill_DOS2T' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'dos_t',
                'hcfa' => '^DOS2T*^',
                'name' => 'DOS T',
                'len' => 8
            ],
            'bill_DOS3F' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'dos_f',
                'hcfa' => '^DOS3F*^',
                'name' => 'DOS F',
                'len' => 8
            ],
            'bill_DOS3T' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'dos_t',
                'hcfa' => '^DOS3T*^',
                'name' => 'DOS T',
                'len' => 8
            ],
            'bill_DOS4F' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'dos_f',
                'hcfa' => '^DOS4F*^',
                'name' => 'DOS F',
                'len' => 8
            ],
            'bill_DOS4T' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'dos_t',
                'hcfa' => '^DOS4T*^',
                'name' => 'DOS T',
                'len' => 8
            ],
            'bill_DOS5F' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'dos_f',
                'hcfa' => '^DOS5F*^',
                'name' => 'DOS F',
                'len' => 8
            ],
            'bill_DOS5T' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'dos_t',
                'hcfa' => '^DOS5T*^',
                'name' => 'DOS T',
                'len' => 8
            ],
            'bill_DOS6F' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'dos_f',
                'hcfa' => '^DOS6F*^',
                'name' => 'DOS F',
                'len' => 8
            ],
            'bill_DOS6T' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'dos_t',
                'hcfa' => '^DOS6T*^',
                'name' => 'DOS T',
                'len' => 8
            ],
            'bill_Box24B1' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'pos',
                'hcfa' => '^a1*^',
                'name' => 'POS',
                'len' => 5
            ],
            'bill_Box24B2' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'pos',
                'hcfa' => '^a2*^',
                'name' => 'POS',
                'len' => 5
            ],
            'bill_Box24B3' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'pos',
                'hcfa' => '^a3*^',
                'name' => 'POS',
                'len' => 5
            ],
            'bill_Box24B4' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'pos',
                'hcfa' => '^a4*^',
                'name' => 'POS',
                'len' => 5
            ],
            'bill_Box24B5' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'pos',
                'hcfa' => '^a5*^',
                'name' => 'POS',
                'len' => 5
            ],
            'bill_Box24B6' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'pos',
                'hcfa' => '^a6*^',
                'name' => 'POS',
                'len' => 5
            ],
            'bill_Box24D1' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'cpt',
                'hcfa' => '^CT1*^',
                'name' => 'CPT',
                'len' => 6
            ],
            'bill_Box24D2' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'cpt',
                'hcfa' => '^CT2*^',
                'name' => 'CPT',
                'len' => 6
            ],
            'bill_Box24D3' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'cpt',
                'hcfa' => '^CT3*^',
                'name' => 'CPT',
                'len' => 6
            ],'bill_Box24D4' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'cpt',
                'hcfa' => '^CT4*^',
                'name' => 'CPT',
                'len' => 6
            ],
            'bill_Box24D5' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'cpt',
                'hcfa' => '^CT5*^',
                'name' => 'CPT',
                'len' => 6
            ],
            'bill_Box24D6' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'cpt',
                'hcfa' => '^CT6*^',
                'name' => 'CPT',
                'len' => 6
            ],
            'bill_Modifier1' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'modifier',
                'hcfa' => '^d1*******^',
                'name' => 'Modifier',
                'len' => 11
            ],
            'bill_Modifier2' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'modifier',
                'hcfa' => '^d2*******^',
                'name' => 'Modifier',
                'len' => 11
            ],
            'bill_Modifier3' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'modifier',
                'hcfa' => '^d3*******^',
                'name' => 'Modifier',
                'len' => 11
            ],
            'bill_Modifier4' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'modifier',
                'hcfa' => '^d4*******^',
                'name' => 'Modifier',
                'len' => 11
            ],
            'bill_Modifier5' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'modifier',
                'hcfa' => '^d5*******^',
                'name' => 'Modifier',
                'len' => 11
            ],
            'bill_Modifier6' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'modifier',
                'hcfa' => '^d6*******^',
                'name' => 'Modifier',
                'len' => 11
            ],
            'bill_Box24E1' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'icd_pointer',
                'hcfa' => '^e1^',
                'name' => 'Diagnosis Pointer',
                'len' => 4
            ],
            'bill_Box24E2' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'icd_pointer',
                'hcfa' => '^e2^',
                'name' => 'Diagnosis Pointer',
                'len' => 4
            ],
            'bill_Box24E3' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'icd_pointer',
                'hcfa' => '^e3^',
                'name' => 'Diagnosis Pointer',
                'len' => 4
            ],
            'bill_Box24E4' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'icd_pointer',
                'hcfa' => '^e4^',
                'name' => 'Diagnosis Pointer',
                'len' => 4
            ],
            'bill_Box24E5' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'icd_pointer',
                'hcfa' => '^e5^',
                'name' => 'Diagnosis Pointer',
                'len' => 4
            ],
            'bill_Box24E6' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'icd_pointer',
                'hcfa' => '^e6^',
                'name' => 'Diagnosis Pointer',
                'len' => 4
            ],
            'bill_Box24F1' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'cpt_charge1',
                'k' => 'unit1',
                'hcfa' => '^f1****^',
                'name' => 'Charges',
                'len' => 8
            ],
            'bill_Box24F2' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'cpt_charge1',
                'k' => 'unit1',
                'hcfa' => '^f2****^',
                'name' => 'Charges',
                'len' => 8
            ],
            'bill_Box24F3' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'cpt_charge1',
                'k' => 'unit1',
                'hcfa' => '^f3****^',
                'name' => 'Charges',
                'len' => 8
            ],
            'bill_Box24F4' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'cpt_charge1',
                'k' => 'unit1',
                'hcfa' => '^f4****^',
                'name' => 'Charges',
                'len' => 8
            ],
            'bill_Box24F5' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'cpt_charge1',
                'k' => 'unit1',
                'hcfa' => '^f5****^',
                'name' => 'Charges',
                'len' => 8
            ],
            'bill_Box24F6' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'cpt_charge1',
                'k' => 'unit1',
                'hcfa' => '^f6****^',
                'name' => 'Charges',
                'len' => 8
            ],
            'bill_Box24G1' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'unit',
                'hcfa' => '^g1*^',
                'name' => 'Units',
                'len' => 5
            ],
            'bill_Box24G2' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'unit',
                'hcfa' => '^g2*^',
                'name' => 'Units',
                'len' => 5
            ],
            'bill_Box24G3' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'unit',
                'hcfa' => '^g3*^',
                'name' => 'Units',
                'len' => 5
            ],
            'bill_Box24G4' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'unit',
                'hcfa' => '^g4*^',
                'name' => 'Units',
                'len' => 5
            ],
            'bill_Box24G5' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'unit',
                'hcfa' => '^g5*^',
                'name' => 'Units',
                'len' => 5
            ],
            'bill_Box24G6' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'unit',
                'hcfa' => '^g6*^',
                'name' => 'Units',
                'len' => 5
            ],
            'bill_Box24J1' => [
                'id' => 'cpt_final',
                'i' => 0,
                'j' => 'npi',
                'hcfa' => '^j1*******^',
                'name' => 'NPI',
                'len' => 11
            ],
            'bill_Box24J2' => [
                'id' => 'cpt_final',
                'i' => 1,
                'j' => 'npi',
                'hcfa' => '^j2*******^',
                'name' => 'NPI',
                'len' => 11
            ],
            'bill_Box24J3' => [
                'id' => 'cpt_final',
                'i' => 2,
                'j' => 'npi',
                'hcfa' => '^j3*******^',
                'name' => 'NPI',
                'len' => 11
            ],
            'bill_Box24J4' => [
                'id' => 'cpt_final',
                'i' => 3,
                'j' => 'npi',
                'hcfa' => '^j4*******^',
                'name' => 'NPI',
                'len' => 11
            ],
            'bill_Box24J5' => [
                'id' => 'cpt_final',
                'i' => 4,
                'j' => 'npi',
                'hcfa' => '^j5*******^',
                'name' => 'NPI',
                'len' => 11
            ],
            'bill_Box24J6' => [
                'id' => 'cpt_final',
                'i' => 5,
                'j' => 'npi',
                'hcfa' => '^j6*******^',
                'name' => 'NPI',
                'len' => 11
            ],
            'bill_Box25' => [
                'id' => 'bill_Box25',
                'hcfa' => '^Bx25*********^',
                'name' => 'Clinic Tax ID',
                'len' => 15
            ],
            'bill_Box26' => [
                'id' => 'bill_Box26',
                'hcfa' => '^Bx26********^',
                'name' => 'Patient ID + Encounter ID',
                'len' => 14
            ],
            'bill_Box27' => [
                'id' => 'bill_Box27',
                'hcfa' => '^Bx27^',
                'name' => 'Accept Assignment',
                'len' => 6
            ],
            'bill_Box27P' => [
                'id' => 'bill_Box27P',
                'hcfa' => '',
                'name' => 'Accept Assignment Formal'
            ],
            'bill_Box28' => [
                'id' => 'bill_Box28',
                'hcfa' => '^Bx28***^',
                'name' => 'Total Charges',
                'len' => 9
            ],
            'bill_Box29' => [
                'id' => 'bill_Box29',
                'hcfa' => '^Bx29**^',
                'name' => 'Amount Paid',
                'len' => 8
            ],
            'bill_Box31' => [
                'id' => 'bill_Box31',
                'hcfa' => '^Bx31***************^',
                'name' => 'Signature',
                'len' => 21
            ],
            'bill_Box32A' => [
                'id' => 'bill_Box32A',
                'hcfa' => '^Bx32a*******************^',
                'name' => 'Clinic Name',
                'len' => 26
            ],
            'bill_Box32B' => [
                'id' => 'bill_Box32B',
                'hcfa' => '^Bx32b*******************^',
                'name' => 'Clinic Address 1',
                'len' => 26
            ],
            'bill_Box32C' => [
                'id' => 'bill_Box32C',
                'hcfa' => '^Bx32c*******************^',
                'name' => 'Clinic Address 2',
                'len' => 26
            ],
            'bill_Box32D' => [
                'id' => 'bill_Box32D',
                'hcfa' => '^Bx32d***^',
                'name' => 'Clinic NPI',
                'len' => 10
            ],
            'bill_Box33A' => [
                'id' => 'bill_Box33A',
                'hcfa' => '^Bx33a******^',
                'name' => 'Clinic Phone',
                'len' => 13
            ],
            'bill_Box33B' => [
                'id' => 'bill_Box33B',
                'hcfa' => '^Bx33b**********************^',
                'name' => 'Billing Provider',
                'len' => 29
            ],
            'bill_Box33C' => [
                'id' => 'bill_Box33C',
                'hcfa' => '^Bx33c**********************^',
                'name' => 'Billing Address 1',
                'len' => 29
            ],
            'bill_Box33D' => [
                'id' => 'bill_Box33D',
                'hcfa' => '^Bx33d**********************^',
                'name' => 'Billing Address 2',
                'len' => 29
            ],
            'bill_Box33E' => [
                'id' => 'bill_Box32D',
                'hcfa' => '^Bx33e***^',
                'name' => 'Billing NPI',
                'len' => 10
            ]
        ];
        $return = [];
        if ($type == 'no_insurance') {
            foreach ($arr as $k => $v) {
                if (!isset($v['hcfa'])) {
                    $return[$k] = $v;
                }
            }
        } elseif ($type == 'length' || $type == 'hcfa') {
            foreach ($arr as $k1 => $v1) {
                if (isset($v1['len'])) {
                    if ($type == 'length') {
                        $return[$k1] = $v1['len'];
                    } else {
                        $return[$k1] = $v1['hcfa'];
                    }
                }
            }
        } else {
            $return = $arr;
        }
        return $return;
    }

    protected function array_color()
    {
        $return = [
            'colorblue' => 'Blue',
            'colorred' => 'Red',
            'colororange' => 'Orange',
            'coloryellow' => 'Yellow',
            'colorpurple' => 'Purple',
            'colorbrown' => 'Brown',
            'colorblack' => 'Black'
        ];
        return $return;
    }

    protected function array_country()
    {
        $arr = Countries::all()->pluck('name.common')->toArray();
        $return = array_combine($arr, $arr);
        return $return;
    }

    protected function array_duration()
    {
        $return = [
            '900' => '15 minutes',
            '1200' => '20 minutes',
            '1800' => '30 minutes',
            '2400' => '40 minutes',
            '2700' => '45 minutes',
            '3600' => '60 minutes',
            '4500' => '75 minutes',
            '4800' => '80 minutes',
            '5400' => '90 minutes',
            '6000' => '100 minutes',
            '6300' => '105 minutes',
            '7200' => '120 minutes'
        ];
        return $return;
    }

    protected function array_encounter_type()
    {
        $return = [
            'medical' => 'Medical Encounter',
            'phone' => 'Phone Encounter',
            'virtual' => 'Virtual Encounter',
            'standardmedical1' => 'Standard Medical Visit V2', // Depreciated
            'standardmedical' => 'Standard Medical Visit V1', // Depreciated
            'standardpsych' => 'Annual Psychiatric Evaluation',
            'standardpsych1' => 'Psychiatric Encounter',
            'clinicalsupport' => 'Clinical Support Visit',
            'standardmtm' => 'Medical Therapy Management Encounter'
        ];
        return $return;
    }

    protected function array_ethnicity()
    {
        $ethnicity = [
            '' => '',
            'Hispanic or Latino' => '2135-2',
            'Not Hispanic or Latino' => '2186-5'
        ];
        return $ethnicity;
    }

    protected function array_gender()
    {
        $gender = [
            'm' => 'Male',
            'f' => 'Female',
            'u' => 'Undifferentiated'
        ];
        return $gender;
    }

    protected function array_gender1()
    {
        $gender = [
            'Male' => 'Male',
            'Female' => 'Female',
            'Undifferentiated' => 'Undifferentiated'
        ];
        return $gender;
    }

    protected function array_gender2()
    {
        $gender = [
            'm' => 'M',
            'f' => 'F',
            'u' => 'UN'
        ];
        return $gender;
    }

    protected function array_gc()
    {
        $return = [
            'weight-age' => [
                'f' => 'wfa_girls_p_exp.txt',
                'm' => 'wfa_boys_p_exp.txt'
            ],
            'height-age' => [
                'f' => 'lhfa_girls_p_exp.txt',
                'm' => 'lhfa_boys_p_exp.txt'
            ],
            'head-age' => [
                'f' => 'hcfa_girls_p_exp.txt',
                'm' => 'hcfa_boys_p_exp.txt'
            ],
            'bmi-age' => [
                'f' => 'bfa_girls_p_exp.txt',
                'm' => 'bfa_boys_p_exp.txt'
            ],
            'weight-length' => [
                'f' => 'wfl_girls_p_exp.txt',
                'm' => 'wfl_boys_p_exp.txt'
            ],
            'weight-height' => [
                'f' => 'wfh_girls_p_exp.txt',
                'm' => 'wfh_boys_p_exp.txt'
            ]
        ];
        return $return;
    }

    protected function array_groups()
    {
        $user_arr = [
            '2' => 'Physician',
            '3' => 'Assistant',
            '4' => 'Billing',
            '100' => 'Patient'
        ];
        return $user_arr;
    }

    protected function array_insurance_active()
    {
        $return['Bill Client'] = 'Bill Client';
        $query = DB::table('insurance')->where('pid', '=', Session::get('pid'))->orderBy('insurance_order', 'asc')->where('insurance_plan_active', '=', 'Yes')->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $payor_id = "Unknown";
                $insurance = DB::table('addressbook')->where('address_id', '=', $row->address_id)->first();
                if ($insurance->insurance_plan_payor_id !== "" || $insurance->insurance_plan_payor_id !== null) {
                    $payor_id = $insurance->insurance_plan_payor_id;
                }
                $key = $row->insurance_plan_name . '; Payor ID: ' . $payor_id . '; ID: ' . $row->insurance_id_num;
                if ($row->insurance_group !== '') {
                    $key .= '; Group: ' . $row->insurance_group;
                }
                $key .= '; ' . $row->insurance_insu_lastname . ', ' . $row->insurance_insu_firstname;
                $return[$key] = $row->insurance_plan_name;
            }
        }
        return $return;
    }

    protected function array_labs()
    {
        $ua = [
            'labs_ua_urobili' => 'Urobilinogen',
            'labs_ua_bilirubin' => 'Bilirubin',
            'labs_ua_ketones' => 'Ketones',
            'labs_ua_glucose' => 'Glucose',
            'labs_ua_protein' => 'Protein',
            'labs_ua_nitrites' => 'Nitrites',
            'labs_ua_leukocytes' => 'Leukocytes',
            'labs_ua_blood' => 'Blood',
            'labs_ua_ph' => 'pH',
            'labs_ua_spgr' => 'Specific Gravity',
            'labs_ua_color' => 'Color',
            'labs_ua_clarity'=> 'Clarity'
        ];
        $single = [
            'labs_upt' => 'Urine HcG',
            'labs_strep' => 'Rapid Strep',
            'labs_mono' => 'Mono Spot',
            'labs_flu' => 'Rapid Influenza',
            'labs_microscope' => 'Micrscopy',
            'labs_glucose' => 'Fingerstick Glucose',
            'labs_other' => 'Other'
        ];
        $return = [
            'ua' => $ua,
            'single' => $single
        ];
        return $return;
    }

    protected function array_locale()
    {
        $locale = [
            // 'ab' => 'Abkhazian',
            // 'aa' => 'Afar',
            // 'af' => 'Afrikaans',
            // 'ak' => 'Akan',
            // 'sq' => 'Albanian',
            // 'am' => 'Amharic',
            // 'ar' => 'Arabic',
            // 'an' => 'Aragonese',
            // 'hy' => 'Armenian',
            // 'as' => 'Assamese',
            // 'av' => 'Avaric',
            // 'ae' => 'Avestan',
            // 'ay' => 'Aymara',
            // 'az' => 'Azerbaijani',
            // 'bm' => 'Bambara',
            // 'ba' => 'Bashkir',
            // 'eu' => 'Basque',
            // 'be' => 'Belarusian',
            // 'bn' => 'Bengali',
            // 'bh' => 'Bihari languages',
            // 'bi' => 'Bislama',
            // 'bs' => 'Bosnian',
            // 'br' => 'Breton',
            // 'bg' => 'Bulgarian',
            // 'my' => 'Burmese',
            // 'ca' => 'Catalan, Valencian',
            // 'km' => 'Central Khmer',
            // 'ch' => 'Chamorro',
            // 'ce' => 'Chechen',
            // 'ny' => 'Chichewa, Chewa, Nyanja',
            // 'zh' => 'Chinese',
            // 'cu' => 'Church Slavonic, Old Bulgarian, Old Church Slavonic',
            // 'cv' => 'Chuvash',
            // 'kw' => 'Cornish',
            // 'co' => 'Corsican',
            // 'cr' => 'Cree',
            // 'hr' => 'Croatian',
            // 'cs' => 'Czech',
            // 'da' => 'Danish',
            // 'dv' => 'Divehi, Dhivehi, Maldivian',
            // 'nl' => 'Dutch, Flemish',
            // 'dz' => 'Dzongkha',
            'en' => 'English',
            // 'eo' => 'Esperanto',
            // 'et' => 'Estonian',
            // 'ee' => 'Ewe',
            // 'fo' => 'Faroese',
            // 'fj' => 'Fijian',
            // 'fi' => 'Finnish',
            // 'fr' => 'French',
            // 'ff' => 'Fulah',
            // 'gd' => 'Gaelic, Scottish Gaelic',
            // 'gl' => 'Galician',
            // 'lg' => 'Ganda',
            // 'ka' => 'Georgian',
            // 'de' => 'German',
            // 'ki' => 'Gikuyu, Kikuyu',
            // 'el' => 'Greek (Modern)',
            // 'kl' => 'Greenlandic, Kalaallisut',
            // 'gn' => 'Guarani',
            // 'gu' => 'Gujarati',
            // 'ht' => 'Haitian, Haitian Creole',
            // 'ha' => 'Hausa',
            // 'he' => 'Hebrew',
            // 'hz' => 'Herero',
            // 'hi' => 'Hindi',
            // 'ho' => 'Hiri Motu',
            // 'hu' => 'Hungarian',
            // 'is' => 'Icelandic',
            // 'io' => 'Ido',
            // 'ig' => 'Igbo',
            // 'id' => 'Indonesian',
            // 'ia' => 'Interlingua (International Auxiliary Language Association)',
            // 'ie' => 'Interlingue',
            // 'iu' => 'Inuktitut',
            // 'ik' => 'Inupiaq',
            // 'ga' => 'Irish',
            // 'it' => 'Italian',
            // 'ja' => 'Japanese',
            // 'jv' => 'Javanese',
            // 'kn' => 'Kannada',
            // 'kr' => 'Kanuri',
            // 'ks' => 'Kashmiri',
            // 'kk' => 'Kazakh',
            // 'rw' => 'Kinyarwanda',
            // 'kv' => 'Komi',
            // 'kg' => 'Kongo',
            // 'ko' => 'Korean',
            // 'kj' => 'Kwanyama, Kuanyama',
            // 'ku' => 'Kurdish',
            // 'ky' => 'Kyrgyz',
            // 'lo' => 'Lao',
            // 'la' => 'Latin',
            // 'lv' => 'Latvian',
            // 'lb' => 'Letzeburgesch, Luxembourgish',
            // 'li' => 'Limburgish, Limburgan, Limburger',
            // 'ln' => 'Lingala',
            // 'lt' => 'Lithuanian',
            // 'lu' => 'Luba-Katanga',
            // 'mk' => 'Macedonian',
            // 'mg' => 'Malagasy',
            // 'ms' => 'Malay',
            // 'ml' => 'Malayalam',
            // 'mt' => 'Maltese',
            // 'gv' => 'Manx',
            // 'mi' => 'Maori',
            // 'mr' => 'Marathi',
            // 'mh' => 'Marshallese',
            // 'ro' => 'Moldovan, Moldavian, Romanian',
            // 'mn' => 'Mongolian',
            // 'na' => 'Nauru',
            // 'nv' => 'Navajo, Navaho',
            // 'nd' => 'Northern Ndebele',
            // 'ng' => 'Ndonga',
            // 'ne' => 'Nepali',
            // 'se' => 'Northern Sami',
            // 'no' => 'Norwegian',
            // 'nb' => 'Norwegian Bokmål',
            // 'nn' => 'Norwegian Nynorsk',
            // 'ii' => 'Nuosu, Sichuan Yi',
            // 'oc' => 'Occitan (post 1500)',
            // 'oj' => 'Ojibwa',
            // 'or' => 'Oriya',
            // 'om' => 'Oromo',
            // 'os' => 'Ossetian, Ossetic',
            // 'pi' => 'Pali',
            // 'pa' => 'Panjabi, Punjabi',
            // 'ps' => 'Pashto, Pushto',
            // 'fa' => 'Persian',
            // 'pl' => 'Polish',
            // 'pt' => 'Portuguese',
            // 'qu' => 'Quechua',
            // 'rm' => 'Romansh',
            // 'rn' => 'Rundi',
            // 'ru' => 'Russian',
            // 'sm' => 'Samoan',
            // 'sg' => 'Sango',
            // 'sa' => 'Sanskrit',
            // 'sc' => 'Sardinian',
            // 'sr' => 'Serbian',
            // 'sn' => 'Shona',
            // 'sd' => 'Sindhi',
            // 'si' => 'Sinhala, Sinhalese',
            // 'sk' => 'Slovak',
            // 'sl' => 'Slovenian',
            // 'so' => 'Somali',
            // 'st' => 'Sotho, Southern',
            // 'nr' => 'South Ndebele',
            'es' => 'Spanish, Castilian',
            // 'su' => 'Sundanese',
            // 'sw' => 'Swahili',
            // 'ss' => 'Swati',
            // 'sv' => 'Swedish',
            // 'tl' => 'Tagalog',
            // 'ty' => 'Tahitian',
            // 'tg' => 'Tajik',
            // 'ta' => 'Tamil',
            // 'tt' => 'Tatar',
            // 'te' => 'Telugu',
            // 'th' => 'Thai',
            // 'bo' => 'Tibetan',
            // 'ti' => 'Tigrinya',
            // 'to' => 'Tonga (Tonga Islands)',
            // 'ts' => 'Tsonga',
            // 'tn' => 'Tswana',
            // 'tr' => 'Turkish',
            // 'tk' => 'Turkmen',
            // 'tw' => 'Twi',
            // 'ug' => 'Uighur, Uyghur',
            // 'uk' => 'Ukrainian',
            // 'ur' => 'Urdu',
            // 'uz' => 'Uzbek',
            // 've' => 'Venda',
            // 'vi' => 'Vietnamese',
            // 'vo' => 'Volap_k',
            // 'wa' => 'Walloon',
            // 'cy' => 'Welsh',
            // 'fy' => 'Western Frisian',
            // 'wo' => 'Wolof',
            // 'xh' => 'Xhosa',
            // 'yi' => 'Yiddish',
            // 'yo' => 'Yoruba',
            // 'za' => 'Zhuang, Chuang',
            // 'zu' => 'Zulu'
            'phl' => 'Philippines'
        ];
        return $locale;
    }

    protected function array_marital()
    {
        $marital = [
            'Single' => 'Single',
            'Married' => 'Married',
            'Common law' => 'Common law',
            'Domestic partner' => 'Domestic partner',
            'Registered domestic partner' => 'Registered domestic partner',
            'Interlocutory' => 'Interlocutory',
            'Living together' => 'Living together',
            'Legally Separated' => 'Legally Separated',
            'Divorced' => 'Divorced',
            'Separated' => 'Separated',
            'Annulled' => 'Annulled',
            'Widowed' => 'Widowed',
            'Other' => 'Other',
            'Unknown' => 'Unknown',
            'Unmarried' => 'Unmarried',
            'Unreported' => 'Unreported'
        ];
        return $marital;
    }

    protected function array_marital1()
    {
        $marital = [
            'Single' => 'S',
            'Married' => 'M',
            'Common law' => 'C',
            'Domestic partner' => 'P',
            'Registered domestic partner' => 'R',
            'Interlocutory' => 'I',
            'Living together' => 'G',
            'Legally Separated' => 'E',
            'Divorced' => 'D',
            'Separated' => 'A',
            'Annulled' => 'N',
            'Widowed' => 'O',
            'Other' => 'O',
            'Unknown' => 'U',
            'Unmarried' => 'B',
            'Unreported' => 'T'
        ];
        return $marital;
    }

    protected function array_modifier()
    {
        $return = [
            '' => '',
            '25' => '25 - Significant, Separately Identifiable E & M Service.',
            '52' => '52 - Reduced Service .',
            '59' => '59 - Distinct Procedural Service.'
        ];
        return $return;
    }

    protected function array_oh()
    {
        $return = [
            'oh_pmh' => 'Past Medical History',
            'oh_psh' => 'Past Surgical History',
            'oh_fh' => 'Family History',
            'oh_sh' => 'Social History',
            'oh_diet' => 'Diet',
            'oh_physical_activity' => 'Physical Activity',
            'oh_etoh' => 'Alcohol Use',
            'oh_tobacco' => 'Tobacco Use',
            'oh_drugs' => 'Illicit Drug Use',
            'oh_employment' => 'Employment/School',
            'oh_psychosocial' => 'Psychosocial History',
            'oh_developmental' => 'Developmental History',
            'oh_medtrials' => 'Past Medication Trials',
            'oh_meds' => 'Medications',
            'oh_supplements' => 'Supplements',
            'oh_allergies' => 'Allergies',
            'oh_results' => 'Reveiwed Results'
        ];
        return $return;
    }

    protected function array_orders_provider($type, $specialty='')
    {
        $return = [];
        $query = DB::table('addressbook');
        if ($type == 'Referral') {
            if ($specialty != "all") {
                $query->where('specialty', '=', $specialty);
            } else {
                $query->where('specialty', '!=', 'Pharmacy')
                    ->where('specialty', '!=', 'Laboratory')
                    ->where('specialty', '!=', 'Radiology')
                    ->where('specialty', '!=', 'Cardiopulmonary')
                    ->where('specialty', '!=', 'Insurance');
            }
        } else {
            $query->where('specialty', '=', $type);
        }
        $result = $query->orderBy('displayname', 'asc')->get();
        if ($type == 'Referral') {
            $return[''] = 'Select Provider';
        }
        if ($result->count()) {
            foreach ($result as $row) {
                if ($type == 'Referral') {
                    $return[$row->address_id] = $row->specialty . ': ' . $row->displayname;
                } elseif ($type == 'Pharmacy') {
                    $return[''] = 'Select Pharmacy';
                } else {
                    $return[$row->address_id] = $row->displayname;
                }
            }
        }
        return $return;
    }

    protected function array_payment_type()
    {
        $data = [];
        $query = DB::table('billing_core')->where('practice_id', '=', Session::get('practice_id'))->whereNotNull('payment_type')->select('payment_type')->distinct()->orderBy('payment_type', 'asc')->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $data[$row->payment_type] = $row->payment_type;
            }
        }
        return $data;
    }

    protected function array_payment_year()
    {
        $data = [];
        $query = DB::table('billing_core')->where('practice_id', '=', Session::get('practice_id'))->select('dos_f')->distinct()->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $date_array = explode("/", $row->dos_f);
                if (isset($date_array[2])) {
                    if (array_search($date_array[2], $data) === false) {
                        $data[$date_array[2]] = $date_array[2];
                    }
                }
            }
        }
        krsort($data);
        return $data;
    }

    protected function array_pe()
    {
        $return = [
            "pe" => "",
            "pe_gen1" => "General",
            "pe_eye1" => "Eye - Conjunctiva and Lids",
            "pe_eye2" => "Eye - Pupil and Iris",
            "pe_eye3" => "Eye - Fundoscopic",
            "pe_ent1" => "ENT - External Ear and Nose",
            "pe_ent2" => "ENT - Canals and Tympanic Membranes",
            "pe_ent3" => "ENT - Hearing Assessment",
            "pe_ent4" => "ENT - Sinuses, Mucosa, Septum, and Turbinates",
            "pe_ent5" => "ENT - Lips, Teeth, and Gums",
            "pe_ent6" => "ENT - Oropharynx",
            "pe_neck1" => "Neck - General",
            "pe_neck2" => "Neck - Thryoid",
            "pe_resp1" => "Respiratory - Effort",
            "pe_resp2" => "Respiratory - Percussion",
            "pe_resp3" => "Respiratory - Palpation",
            "pe_resp4" => "Respiratory - Auscultation",
            "pe_cv1" => "Cardiovascular - Palpation",
            "pe_cv2" => "Cardiovascular - Auscultation",
            "pe_cv3" => "Cardiovascular - Carotid Arteries",
            "pe_cv4" => "Cardiovascular - Abdominal Aorta",
            "pe_cv5" => "Cardiovascular - Femoral Arteries",
            "pe_cv6" => "Cardiovascular - Extremities",
            "pe_ch1" => "Chest - Inspection",
            "pe_ch2" => "Chest - Palpation",
            "pe_gi1" => "Gastrointestinal - Masses and Tenderness",
            "pe_gi2" => "Gastrointestinal - Liver and Spleen",
            "pe_gi3" => "Gastrointestinal - Hernia",
            "pe_gi4" => "Gastrointestinal - Anus, Perineum, and Rectum",
            "pe_gu1" => "Genitourinary - Genitalia",
            "pe_gu2" => "Genitourinary - Urethra",
            "pe_gu3" => "Genitourinary - Bladder",
            "pe_gu4" => "Genitourinary - Cervix",
            "pe_gu5" => "Genitourinary - Uterus",
            "pe_gu6" => "Genitourinary - Adnexa",
            "pe_gu7" => "Genitourinary - Scrotum",
            "pe_gu8" => "Genitourinary - Penis",
            "pe_gu9" => "Genitourinary - Prostate",
            "pe_lymph1" => "Lymphatic - Neck",
            "pe_lymph2" => "Lymphatic - Axillae",
            "pe_lymph3" => "Lymphatic - Groin",
            "pe_ms1" => "Musculoskeletal - Gait and Station",
            "pe_ms2" => "Musculoskeletal - Digit and Nails",
            "pe_ms3" => "Musculoskeletal - Shoulder",
            "pe_ms4" => "Musculoskeletal - Elbow",
            "pe_ms5" => "Musculoskeletal - Wrist",
            "pe_ms6" => "Musculoskeletal - Hand",
            "pe_ms7" => "Musculoskeletal - Hip",
            "pe_ms8" => "Musculoskeletal - Knee",
            "pe_ms9" => "Musculoskeletal - Ankle",
            "pe_ms10" => "Musculoskeletal - Foot",
            "pe_ms11" => "Musculoskeletal - Cervical Spine",
            "pe_ms12" => "Musculoskeletal - Thoracic and Lumbar Spine",
            "pe_neuro1" => "Neurological - Cranial Nerves",
            "pe_neuro2" => "Neurological - Deep Tendon Reflexes",
            "pe_neuro3" => "Neurological - Sensation and Motor",
            "pe_psych1" => "Psychiatric - Judgement",
            "pe_psych2" => "Psychiatric - Orientation",
            "pe_psych3" => "Psychiatric - Memory",
            "pe_psych4" => "Psychiatric - Mood and Affect",
            'pe_constitutional1' => 'Psychiatric - Constitutional',
            'pe_mental1' => 'Psychiatric - Mental Status Examination',
            "pe_skin1" => "Skin - Inspection",
            "pe_skin2" => "Skin - Palpation"
        ];
        return $return;
    }

    protected function array_plan()
    {
        $return = [
            'plan' => 'Recommendations',
            'followup' => 'Followup',
            'goals' => 'Goals/Measures',
            'tp' => 'Treatment Plan Notes',
            'duration' => 'Counseling and face-to-face time consists of more than 50 percent of the visit.  Total face-to-face time is '
        ];
        return $return;

    }

    protected function array_pos()
    {
        $return = [
            '1' => 'Pharmacy',
            '3' => 'School',
            '4' => 'Homeless Shelter',
            '5' => 'Indian Health Service - Free-standing Facility',
            '6' => 'Indian Health Service - Provider-based Facility',
            '7' => 'Tribal 638 - Free-standing Facility',
            '8' => 'Tribal 638 - Provider-based Facility',
            '9' => 'Prison\/Correctional Facility',
            '11' => 'Office',
            '12' => 'Home',
            '13' => 'Assisted Living Facility',
            '14' => 'Group Home',
            '15' => 'Mobile Unit',
            '16' => 'Temporary Lodging',
            '17' => 'Walk-in Retail Health Clinic',
            '20' => 'Urgent Care Facility',
            '21' => 'Inpatient Hospital',
            '22' => 'Outpatient Hospital',
            '23' => 'Emergency Room - Hospital',
            '24' => 'Ambulatory Surgical Center',
            '25' => 'Birthing Center',
            '26' => 'Military Treatment Facility',
            '31' => 'Skilled Nursing Facility',
            '32' => 'Nursing Facility',
            '33' => 'Custodial Care Facility',
            '34' => 'Hospice',
            '41' => 'Ambulance - Land',
            '42' => 'Ambulance - Air or Water',
            '49' => 'Independent Clinic',
            '50' => 'Federally Qualified Health Center',
            '51' => 'Inpatient Psychiatric Facility',
            '52' => 'Psychiatric Facility - Partial Hospitalization',
            '53' => 'Community Mental Health Center',
            '54' => 'Intermediate Care Facility',
            '55' => 'Residential Substance Abuse Treatment Facility',
            '56' => 'Psychiatric',
            '57' => 'Non-residential Substance Abuse Treatment Facility',
            '60' => 'Mass Immunization Center',
            '61' => 'Comprehensive Inpatient Rehabilitation Facility',
            '62' => 'Comprehensive Outpatient Rehabilitation Facility',
            '65' => 'End-Stage Renal Disease Treatment Facility',
            '71' => 'Public Health Clinic',
            '72' => 'Rural Health Clinic',
            '81' => 'Independent Laboratory',
            '99' => 'Other Place of Service'
        ];
        return $return;
    }

    protected function array_practices($active='')
    {
        $query = DB::table('practiceinfo');
        if ($active == 'y') {
            $query->where('active', 'Y');
        }
        $result = $query->get();
        $return = [];
        if ($result->count()) {
            foreach ($result as $row) {
                $return[$row->practice_id] = $row->practice_name;
            }
        }
        return $return;
    }

    protected function array_procedure()
    {
        $return = [
            'proc_type' => 'Procedure',
            'proc_description' => 'Description of Procedure',
            'proc_complications' => 'Complications',
            'proc_ebl' => 'Estimated Blood Loss'
        ];
        return $return;
    }

    protected function array_procedure_codes()
    {
        $data = [];
        $query = DB::table('billing_core')->where('practice_id', '=', Session::get('practice_id'))->select('cpt')->orderBy('cpt', 'asc')->distinct()->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $data[$row->cpt] = $row->cpt;
            }
        }
        return $data;
    }

    protected function array_providers()
    {
        $return = [];
        $query = DB::table('users')
            ->where('group_id', '=', '2')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->where('active', '=', '1')
            ->select('displayname', 'id')
            ->distinct()
            ->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $return[$row->id] = $row->displayname;
            }
        }
        return $return;
    }

    protected function array_race()
    {
        $race = [
            '' => '',
            'American Indian or Alaska Native' => '1002-5',
            'Asian' => '2028-9',
            'Black or African American' => '2054-5',
            'Native Hawaiian or Other Pacific Islander' => '2076-8',
            'White' => '2106-3'
        ];
        return $race;
    }

    protected function array_reminder_interval()
    {
        $method = [
            'Default' => 'Default (48 hours prior)',
            '24' => '24 hours prior',
            '12' => '12 hours prior',
            '6' => '6 hours prior',
            '3' => '3 hours prior'
        ];
        return $method;
    }

    protected function array_reminder_method()
    {
        $method = [
            '' => '',
            'Email' => 'Email',
            'Cellular Phone' => 'Cellular Phone'
        ];
        return $method;
    }

    protected function array_ros()
    {
        $return = [
            'ros' => '',
            'ros_gen' => 'General',
            'ros_eye' => 'Eye',
            'ros_ent' => 'Ears, Nose, Throat',
            'ros_resp' => 'Respiratory',
            'ros_cv' => 'Cardiovascular',
            'ros_gi' => 'Gastrointestinal',
            'ros_gu' => 'Genitourinary',
            'ros_mus' => 'Musculoskeletal',
            'ros_neuro' => 'Neurological',
            'ros_psych' => 'Psychological',
            'ros_heme' => 'Hematological, Lymphatic',
            'ros_endocrine' => 'Endocrine',
            'ros_skin' => 'Skin',
            'ros_wcc' => 'Well Child Check',
            'ros_psych1' => 'Depression',
            'ros_psych2' => 'Anxiety',
            'ros_psych3' => 'Bipolar',
            'ros_psych4' => 'Mood Disorders',
            'ros_psych5' => 'ADHD',
            'ros_psych6' => 'PTSD',
            'ros_psych7' => 'Substance Related Disorder',
            'ros_psych8' => 'Obsessive Compulsive Disorder',
            'ros_psych9' => 'Social Anxiety Disorder',
            'ros_psych10' => 'Autistic Disorder',
            'ros_psych11' => "Asperger's Disorder"
        ];
        return $return;
    }

    protected function array_route()
    {
        $yaml = File::get(resource_path() . '/routes.yaml');
        $formatter = Formatter::make($yaml, Formatter::YAML);
        $arr = $formatter->toArray();
        $route[''] = '';
        foreach ($arr as $row) {
            $route[$row['desc']] = $row['desc'];
        }
        asort($route);
        return $route;
    }

    protected function array_route1()
    {
        $route = [
            '' => ['', ''],
            'by mouth' => ['C1522409', 'Oropharyngeal Route of Administration'],
            'per rectum' => ['C1527425', 'Rectal Route of Administration'],
            'transdermal' => ['C0040652', 'Transdermal Route of Administration'],
            'subcutaneously' => ['C1522438', 'Subcutaneous Route of Administration'],
            'intramuscularly' => ['C1556154', 'Intravascular Route of Administration'],
            'intravenously' => ['C2960476', 'Intramuscular Route of Administration']
        ];
        $yaml = File::get(resource_path() . '/routes.yaml');
        $formatter = Formatter::make($yaml, Formatter::YAML);
        $arr = $formatter->toArray();
        foreach ($arr as $row) {
            $route[$row['desc']] = [$row['code'], $row['desc']];
        }
        return $route;
    }

    protected function array_rx()
    {
        $return = [
            'rx_rx' => 'Prescriptions Given',
            'rx_supplements' => 'Supplements Recommended',
            'rx_immunizations' => 'Immunizations Given'
        ];
        return $return;
    }

    protected function array_specialty()
    {
        $return = [];
        $query = DB::table('addressbook')
            ->where('specialty', '!=', 'Pharmacy')
            ->where('specialty', '!=', 'Laboratory')
            ->where('specialty', '!=', 'Radiology')
            ->where('specialty', '!=', 'Cardiopulmonary')
            ->where('specialty', '!=', 'Insurance')
            ->select('specialty')
            ->distinct()
            ->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $return[$row->specialty] = $row->specialty;
            }
        }
        return $return;
    }

    protected function array_states($country='United States')
    {
        $states = [
            '' => '',
        ];
        $states1 = Countries::where('name.common', $country)
            ->first()
            ->hydrateStates()
            ->states
            ->sortBy('name')
            ->pluck('name', 'postal')
            ->toArray();
        $states = array_merge($states, $states1);
        $states_old = [
            '' => '',
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AS' => 'America Samoa',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'District of Columbia',
            'FM' => 'Federated States of Micronesia',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'GU' => 'Guam',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MH' => 'Marshall Islands',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PW' => 'Palau',
            'PA' => 'Pennsylvania',
            'PR' => 'Puerto Rico',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VI' => 'Virgin Island',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming'
        ];
        return $states;
    }

    protected function array_supplement_inventory()
    {
        $data = [];
        $query = DB::table('supplement_inventory')
            ->where('quantity1', '>', '0')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->orderBy('sup_description', 'asc')
            ->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $data[$row->supplement_id] = $row->sup_description . ', ' . $row->sup_strength . ' (' . $row->quantity1 . ')';
            }
        }
        return $data;
    }

    protected function array_tags()
    {
        $data = [];
        $query = DB::table('tags')
            ->join('tags_relate', 'tags_relate.tags_id', '=', 'tags.tags_id')
            ->select('tags.tags_id','tags.tag')
            ->where('tags_relate.practice_id', '=', Session::get('practice_id'))
            ->distinct()
            ->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $data[$row->tags_id] = $row->tag;
            }
        }
        return $data;
    }

    protected function array_template()
    {
        $data = [];
        if (Session::has('pid')) {
            $pid = Session::get('pid');
            $patient = DB::table('demographics')->where('pid', '=', $pid)->first();
            $gender_arr = [
                'm' => 'he',
                'f' => 'she',
                'u' => $patient->firstname
            ];
            $lastvisit = "";
            $encounter = DB::table('encounters')->where('pid', '=', $pid)
                ->where('eid', '!=', '')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('eid', 'desc')
                ->first();
            if ($encounter) {
                $lastvisit =  $patient->firstname . ' was last seen by me on ' . date('F jS, Y', strtotime($encounter->encounter_DOS));
            }
            $problem = 'Active Issues:';
            $problems = DB::table('issues')->where('pid', '=', $pid)->where('issue_date_inactive', '=', '0000-00-00 00:00:00')->get();
            if ($problems) {
                foreach ($problems as $problems_row) {
                    $problem .= "\n". $problems_row->issue;
                }
            } else {
                $problem .= ' None.';
            }
            $med = 'Active Medications:';
            $meds = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->get();
            if ($meds) {
                foreach ($meds as $meds_row) {
                    if ($meds_row->rxl_sig == '') {
                        $med .= "\n" . $meds_row->rxl_medication . ' ' . $meds_row->rxl_dosage . ' ' . $meds_row->rxl_dosage_unit . ', ' . $meds_row->rxl_instructions . ' for ' . $meds_row->rxl_reason;
                    } else {
                        $med .= "\n" . $meds_row->rxl_medication . ' ' . $meds_row->rxl_dosage . ' ' . $meds_row->rxl_dosage_unit . ', ' . $meds_row->rxl_sig . ', ' . $meds_row->rxl_route . ', ' . $meds_row->rxl_frequency . ' for ' . $meds_row->rxl_reason;
                    }
                }
            } else {
                $meds .= ' None.';
            }
            $imm = 'Immunizations:';
            $imms = DB::table('immunizations')->where('pid', '=', $pid)->orderBy('imm_immunization', 'asc')->orderBy('imm_sequence', 'asc')->get();
            if ($imms) {
                foreach ($imms as $imms_row) {
                    $sequence = '';
                    if ($imms_row->imm_sequence == '1') {
                        $sequence = ', first,';
                    }
                    if ($imms_row->imm_sequence == '2') {
                        $sequence = ', second,';
                    }
                    if ($imms_row->imm_sequence == '3') {
                        $sequence = ', third,';
                    }
                    if ($imms_row->imm_sequence == '4') {
                        $sequence = ', fourth,';
                    }
                    if ($imms_row->imm_sequence == '5') {
                        $sequence = ', fifth,';
                    }
                    $imm .= "\n" . $imms_row->imm_immunization . $sequence . ' given on ' . date('F jS, Y', $this->human_to_unix($imms_row->imm_date));
                }
            } else {
                $imm .= ' None.';
            }
            $allergy = 'Allergies:';
            $allergies = DB::table('allergies')->where('pid', '=', $pid)->where('allergies_date_inactive', '=', '0000-00-00 00:00:00')->get();
            if ($allergies) {
                foreach ($allergies as $allergies_row) {
                    $allergy .= "\n" . $allergies_row->allergies_med . ' - ' . $allergies_row->allergies_reaction;
                }
            } else {
                $allergy .= ' No known allergies.';
            }
            $data = [
                '*~patient~*' => $patient->firstname . ' ' . $patient->lastname . ' (Date of Birth: ' . date('F jS, Y', $this->human_to_unix($patient->DOB)) . ')',
                '*~firstname~*' => $patient->firstname,
                '*~fullname~*' => $patient->firstname . ' ' . $patient->lastname,
                '*~he/she~*' => $gender_arr[$patient->sex],
                '*~He/She~*' => ucfirst($gender_arr[$patient->sex]),
                '*~letterintro~*' => 'This letter is in regards to ' . $patient->firstname . ' ' . $patient->lastname . ' (Date of Birth: ' . date('F jS, Y', $this->human_to_unix($patient->DOB)) . '), who is a patient of mine.' . $lastvisit,
                '*~problems~*' => $problem,
                '*~meds~*' => $med,
                '*~allergies~*' => $allergy,
                '*~imm~*' => $imm
            ];
        }
        return $data;
    }

    protected function array_test_flag()
    {
        $test_arr = [
            '' => '',
            'L' => 'Below low normal',
            'H' => 'Above high normal',
            'LL' => 'Below low panic limits',
            'HH' => 'Above high panic limits',
            '<' => 'Below absolute low-off instrument scale',
            '>' => 'Above absolute high-off instrument scale',
            'N' => 'Normal',
            'A' => 'Abnormal',
            'AA' => 'Very abnormal',
            'U' => 'Significant change up',
            'D' => 'Significant change down',
            'B' => 'Better',
            'W' => 'Worse',
            'S' => 'Susceptible',
            'R' => 'Resistant',
            'I' => 'Intermediate',
            'MS' => 'Moderately susceptible',
            'VS' => 'Very susceptible'
        ];
        return $test_arr;
    }

    protected function array_users($type='1')
    {
        $return = [];
        $query = DB::table('users')
            ->where('group_id', '!=', '100')
            ->where('group_id', '!=', '1')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->where('active', '=', '1')
            ->select('displayname', 'id')
            ->distinct()
            ->get();
        if ($query->count()) {
            foreach ($query as $row) {
                if ($type == '1') {
                    $return[$row->id] = $row->displayname;
                }
                if ($type == '2') {
                    $value = $row->displayname . ' (' . $row->id . ')';
                    $return[$value] = $row->displayname;
                }
            }
        }
        return $return;
    }

    protected function array_users_all($type='1')
    {
        $return = [];
        if (Session::get('group_id') == '100') {
            if (Session::get('patient_centric') == 'y') {
                $query = DB::table('users')->where('group_id', '!=', '100')->where('group_id', '!=', '1')->where('active', '=', '1')->get();
            } else {
                $query = DB::table('users')->where('group_id', '!=', '100')->where('group_id', '!=', '1')->where('practice_id', '=', Session::get('practice_id'))->where('active', '=', '1')->get();
            }
        } else {
            if (Session::get('patient_centric') == 'yp') {
                $query = DB::table('users')->where('group_id', '!=', '1')->where('active', '=', '1')->get();
            } else {
                $query = DB::table('users')->where('group_id', '!=', '1')->where('practice_id', '=', Session::get('practice_id'))->where('active', '=', '1')->get();
            }
        }
        if ($query->count()) {
            foreach ($query as $row) {
                if ($type == '1') {
                    $return[$row->id] = $row->displayname;
                }
                if ($type == '2') {
                    $value = $row->displayname . ' (' . $row->id . ')';
                    $return[$value] = $row->displayname;
                }
            }
        }
        return $return;
    }

    protected function array_vaccine_inventory()
    {
        $data = [];
        $query = DB::table('vaccine_inventory')
            ->where('quantity', '>', '0')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->orderBy('imm_immunization', 'asc')
            ->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $data[$row->vaccine_id] = $row->imm_immunization . ' (' . $row->quantity . ')';
            }
        }
        return $data;
    }

    protected function array_vitals()
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $return = [
            'weight' => [
                'name' => 'Weight',
                'unit' => $practice->weight_unit
            ],
            'height' => [
                'name' => 'Height',
                'unit' => $practice->height_unit
            ],
            'headcircumference' => [
                'name' => 'HC',
                'unit' => $practice->hc_unit
            ],
            'BMI' => [
                'min' => '19',
                'max' => '30',
                'name' => 'BMI',
                'unit' => 'kg/m2'
            ],
            'temp' => [
                'min' => [
                    'F' => '93',
                    'C' => '34'
                ],
                'max' => [
                    'F' => '100.4',
                    'C' => '38'
                ],
                'name' => 'Temp',
                'unit' => $practice->temp_unit
            ],
            'bp_systolic' => [
                'min' => '80',
                'max' => '140',
                'name' => 'SBP',
                'unit' => 'mmHg'
            ],
            'bp_diastolic' => [
                'min' => '50',
                'max' => '90',
                'name' => 'DBP',
                'unit' => 'mmHg'
            ],
            'pulse' => [
                'min' => '50',
                'max' => '140',
                'name' => 'Pulse',
                'unit' => 'bpm'
            ],
            'respirations' => [
                'min' => '10',
                'max' => '35',
                'name' => 'Resp',
                'unit' => 'bpm'
            ],
            'o2_sat' => [
                'min' => '90',
                'max' => '100',
                'name' => 'O2 Sat',
                'unit' => 'percent'
            ]
        ];
        return $return;
    }

    protected function array_vitals1()
    {
        $return = [
            'wt_percentile' => 'Weight to Age Percentile',
            'ht_percentile' => 'Height to Age Percentile',
            'wt_ht_percentile' => 'Weight to Height Percentile',
            'hc_percentile' => 'Head Circumference to Age Percentile',
            'bmi_percentile' => 'BMI to Age Percentile'
        ];
        return $return;
    }

    protected function ascvd_calc()
    {
        $url = 'http://aha.indicoebm.com/api/RiskCalculatorManager/GetBaselineRiskResult';
        $row = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $missing_arr = [
            'Patient race not specified',
            'Patient tobacco status not specified',
            'No historical blood pressure readings',
            'No historical HDL cholesterol values',
            'No historical LDL cholesterol values',
            'No historical total cholesterol values'
        ];
        $gender_arr = $this->array_gender2();
        $gender = $gender_arr[$row->sex];
        $age_date = Date::parse($row->DOB);
        $age = $age_date->diffinYears();
        $race = 'WH';
        $proceed = true;
        if ($row->race !== '' && $row->race !== null) {
            unset($missing_arr[0]);
            if ($row->race == 'Black or African American') {
                $race = 'AA';
            }
        }
        $smoker = false;
        if ($row->tobacco !== '' && $row->tobacco !== null) {
            unset($missing_arr[1]);
            if ($row->tobacco == 'yes') {
                $smoker = true;
            }
        }
        $diabetes = false;
        $diabetes_q = $this->hedis_issue_query(Session::get('pid'), ['E08', 'E09', 'E10', 'E11', 'E13']);
        if ($diabetes_q) {
            $diabetes = true;
        }
        $aspirin = false;
        $aspirin_q = DB::table('rx_list')->where('pid', '=', Session::get('pid'))->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->where('rxl_medication', 'LIKE', "%aspirin%")->first();
        if ($aspirin_q) {
            $aspirin = true;
        }
        $sbp = '90';
        $vitals = DB::table('vitals')->where('pid', '=', Session::get('pid'))->orderBy('vitals_date', 'desc')->first();
        if ($vitals) {
            unset($missing_arr[2]);
            if ($vitals->bp_systolic > 200) {
                $sbp = '200';
            } elseif ($vitals->bp_systolic < 90) {
                $spb = '90';
            } else {
                $spb = $vitals->bp_systolic;
            }
        }
        $htn = false;
        $chol = false;
        $rx = DB::table('rx_list')->where('pid', '=', Session::get('pid'))->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->get();
        if ($rx->count()) {
            $htn_url = 'https://rxnav.nlm.nih.gov/REST/rxclass/classMembers.json?classId=N0000001616&relaSource=NDFRT&rela=may_treat';
            $htn_ch = curl_init();
            curl_setopt($htn_ch,CURLOPT_URL, $htn_url);
            curl_setopt($htn_ch,CURLOPT_FAILONERROR,1);
            curl_setopt($htn_ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($htn_ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($htn_ch,CURLOPT_TIMEOUT, 15);
            $htn_json = curl_exec($htn_ch);
            curl_close($htn_ch);
            $htn_group = json_decode($htn_json, true);
            $htn_arr = [];
            foreach ($htn_group['drugMemberGroup']['drugMember'] as $htn_item) {
                $htn_arr[] = strtolower($htn_item['minConcept']['name']);
            }
            $chol_url = 'https://rxnav.nlm.nih.gov/REST/rxclass/classMembers.json?classId=N0000001592&relaSource=NDFRT&rela=may_treat';
            $chol_ch = curl_init();
            curl_setopt($chol_ch,CURLOPT_URL, $chol_url);
            curl_setopt($chol_ch,CURLOPT_FAILONERROR,1);
            curl_setopt($chol_ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($chol_ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($chol_ch,CURLOPT_TIMEOUT, 15);
            $chol_json = curl_exec($chol_ch);
            curl_close($chol_ch);
            $chol_group = json_decode($chol_json, true);
            $chol_arr = [];
            foreach ($chol_group['drugMemberGroup']['drugMember'] as $chol_item) {
                $chol_arr[] = strtolower($chol_item['minConcept']['name']);
            }
            foreach ($rx as $rx_item) {
                $rx_name = explode(' ', $rx_item->rxl_medication);
                $rx_name_first = strtolower($rx_name[0]);
                if (in_array($rx_name_first, $htn_arr)) {
                    $htn = true;
                }
                if (in_array($rx_name_first, $chol_arr)) {
                    $chol = true;
                }
            }
        }
        $hdl = '45';
        $ldl = '90';
        $tc = '190';
        $hdl_query = DB::table('tests')->where('pid', '=', Session::get('pid'))->where('test_code', '=', '2085-9')->orderBy('test_datetime', 'desc')->first();
        if ($hdl_query) {
            unset($missing_arr[3]);
            $hdl = $hdl_query->test_result;
        }
        $ldl_query = DB::table('tests')->where('pid', '=', Session::get('pid'))->where('test_code', '=', '13457-7')->orderBy('test_datetime', 'desc')->first();
        if ($ldl_query) {
            unset($missing_arr[4]);
            $ldl = $ldl_query->test_result;
        }
        $tc_query = DB::table('tests')->where('pid', '=', Session::get('pid'))->where('test_code', '=', '2093-3')->orderBy('test_datetime', 'desc')->first();
        if ($tc_query) {
            unset($missing_arr[5]);
            $tc = $tc_query->test_result;
        }
        if (empty($missing_arr)) {
            $data = [
                'AspirinTherapy' => $aspirin,
                'CurrentSmoker' => $smoker,
                'Gender' => $gender,
                'HDLCholestrol' => $hdl, // numeric,
                'HistoryofDiabetes' => $diabetes,
                'LDLCholestrol' => $ldl, // numeric
                'Race' => $race,
                'SystolicBloodPressure' => $sbp, //numeric
                'TotalCholestrol' => $tc, //numeric
                'TreatmentforHypertension' => $htn, // boolean
                'TreatmentwithStatin' => $chol, //boolean
                'age' => $age
            ];
            $data_string = json_encode($data);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
            );
            $result = curl_exec($ch);
            $result_arr = json_decode($result, true);
        } else {
            $result_arr['status'] = 'missing';
            $result_arr['message'] = '<div class="alert alert-danger"><strong>Unable to calculate ASCVD risk score due to missing items:</strong><br><ul>';
            foreach ($missing_arr as $missing_item) {
                $result_arr['message'] .= '<li>' . $missing_item . '</li>';
            }
            $result_arr['message'] .= '</ul></div>';
        }
        return $result_arr;
    }

    protected function assets_css($type='')
    {
        $return = [
            '/assets/css/font-awesome.min.css',
            '/assets/css/bootstrap.min.css',
            '/assets/css/toastr.min.css',
            '/assets/css/nosh-timeline.css',
            '/assets/css/bootstrap-select.min.css',
            '/assets/css/bootstrap-tagsinput.css',
            '/assets/css/bootstrap-datetimepicker.css',
            '/assets/css/jquery.fancybox.css'
        ];
        if ($type == 'chart') {
        }
        if ($type == 'schedule') {
            $return[] = '/assets/css/fullcalendar.min.css';
        }
        if ($type == 'document_upload') {
            $return[] = '/assets/css/fileinput.min.css';
        }
        if ($type == 'login') {
            $return[] = '/assets/css/jquery.realperson.css';
        }
        if ($type == 'signature') {
            $return[] = '/assets/css/jquery.signaturepad.css';
        }
        $return[] = '/assets/css/main.css';
        return $return;
    }

    protected function assets_js($type='')
    {
        $return = [
            '/assets/js/jquery-3.1.1.min.js',
            '/assets/js/bootstrap.min.js',
            '/assets/js/moment.min.js',
            '/assets/js/jquery.maskedinput.min.js',
            '/assets/js/toastr.min.js',
            '/assets/js/bootstrap3-typeahead.min.js',
            '/assets/js/jquery.cookie.js',
            '/assets/js/bootstrap-list-filter.min.js',
            '/assets/js/jquery-idleTimeout.js',
            '/assets/js/bootstrap-tagsinput.js',
            '/assets/js/jquery.selectboxes.js',
            '/assets/js/bootstrap-select.min.js',
            '/assets/js/bootstrap-datetimepicker.min.js',
            '/assets/js/jquery.fileDownload.js',
            '/assets/js/jstz-1.0.4.min.js',
            '/assets/js/jquery.fancybox.js'
        ];
        if ($type == 'chart') {
            $return[] = '/assets/js/bluebutton.js';
            $return[] = '/assets/js/pediatric-immunizations.min.js';
        }
        if ($type == 'schedule') {
            $return[] = '/assets/js/fullcalendar.min.js';
        }
        if ($type == 'document_upload') {
            $return[] = '/assets/js/canvas-to-blob.min.js';
            $return[] = '/assets/js/sortable.min.js';
            $return[] = '/assets/js/purify.min.js';
            $return[] = '/assets/js/fileinput.min.js';
        }
        if ($type == 'documents') {
            $return[] = '/assets/js/pdfobject.min.js';
        }
        if ($type == 'image') {
            $return[] = '/assets/js/jcanvas.min.js';
        }
        if ($type == 'login') {
            $return[] = '/assets/js/jquery.realperson.js';
        }
        if ($type == 'sigma') {
            $return[] = '/assets/js/sigma.min.js';
            // $return[] = '/assets/js/plugins/sigma.layout.forceAtlas2.min.js';
            // $return[] = '/assets/js/plugins/sigma.layout.noverlap.min.js';
            $return[] = '/assets/js/plugins/sigma.parsers.json.min.js';
            $return[] = '/assets/js/plugins/sigma.plugins.dragNodes.min.js';
            // $return[] = '/assets/js/plugins/sigma.renderers.customEdgeShapes.min.js';
            // $return[] = '/assets/js/plugins/sigma.renderers.edgeDots.min.js';
            // $return[] = '/assets/js/plugins/sigma.renderers.edgeLabels.min.js';
            // $return[] = '/assets/js/plugins/sigma.renderers.parallelEdges.min.js';
        }
        if ($type == 'signature') {
            $return[] = '/assets/js/jquery.signaturepad.min.js';
        }
        return $return;
    }

    /**
    * Audit
    * @param string  $action - Add, Update, Delete
    * @return Response
    */
    protected function audit($action)
    {
        $queries = DB::getQueryLog();
        $sql = end($queries);
        if (!empty($sql['bindings'])) {
            $pdo = DB::getPdo();
            foreach ($sql['bindings'] as $binding) {
                $sql['query'] = preg_replace('/\?/', $pdo->quote($binding), $sql['query'], 1);
            }
        }
        $data = [
            'user_id' => Session::get('user_id'),
            'displayname' => Session::get('displayname'),
            'pid' => Session::get('pid'),
            'group_id' =>  Session::get('group_id'),
            'action' => $action,
            'query' => $sql['query'],
            'practice_id' => Session::get('practice_id')
        ];
        DB::table('audit')->insert($data);
        return true;
    }

    protected function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function billing_save_common($insurance_id_1, $insurance_id_2, $eid)
    {
        DB::table('billing')->where('eid', '=', $eid)->delete();
        $this->audit('Delete');
        $pid = Session::get('pid');
        $practiceInfo = DB::table('practiceinfo')->where('practice_id', '=',Session::get('practice_id'))->first();
        $encounterInfo = DB::table('encounters')->where('eid', '=', $eid)->first();
        $bill_complex = $encounterInfo->bill_complex;
        $row = DB::table('demographics')->where('pid', '=', $pid)->first();
        if ($insurance_id_1 == '0' || $insurance_id_1 == '') {
            $b0 = $this->array_billing('no_insurance');
            foreach ($b0 as $b0_k => $b0_v) {
                $data0[$b0_k] = ${$b0_v['id']};
            }
            DB::table('billing')->insert($data0);
            $this->audit('Add');
            $data_encounter['bill_submitted'] = 'Done';
            DB::table('encounters')->where('eid', '=', $eid)->update($data_encounter);
            $this->audit('Update');
            return 'Billing Saved!';
            exit ( 0 );
        }
        $data_encounter['bill_submitted'] = 'No';
        DB::table('encounters')->where('eid', '=', $eid)->update($data_encounter);
        $this->audit('Update');
        $b1 = $this->array_billing('length');
        $b2 = $this->array_billing();
        $result1 = DB::table('insurance')->where('insurance_id', '=', $insurance_id_1)->first();
        $bill_Box11C = $this->string_format($result1->insurance_plan_name, $b1['bill_Box11C']);
        $bill_Box1A = $this->string_format($result1->insurance_id_num, $b1['bill_Box1A']);
        $bill_Box4 = $this->string_format($result1->insurance_insu_lastname . ', ' . $result1->insurance_insu_firstname, $b1['bill_Box4']);
        $result2 = DB::table('addressbook')->where('address_id', '=', $result1->address_id)->first();
        if ($result2->insurance_plan_type == 'Medicare') {
            $bill_Box1 = "X                                            ";
            $bill_Box1P = 'Medicare';
        }
        if ($result2->insurance_plan_type == 'Medicaid') {
            $bill_Box1 = "       X                                     ";
            $bill_Box1P = 'Medicaid';
        }
        if ($result2->insurance_plan_type == 'Tricare') {
            $bill_Box1 = "              X                              ";
            $bill_Box1P = 'Tricare';
        }
        if ($result2->insurance_plan_type == 'ChampVA') {
            $bill_Box1 = "                       X                     ";
            $bill_Box1P = 'ChampVA';
        }
        if ($result2->insurance_plan_type == 'Group Health Plan') {
            $bill_Box1 = "                              X              ";
            $bill_Box1P = 'Group Health Plan';
        }
        if ($result2->insurance_plan_type == 'FECA') {
            $bill_Box1 = "                                      X      ";
            $bill_Box1P = 'FECA';
        }
        if ($result2->insurance_plan_type == 'Other') {
            $bill_Box1 = "                                            X";
            $bill_Box1P = 'Other';
        }
        $bill_payor_id = $this->string_format($result2->insurance_plan_payor_id, $b1['bill_payor_id']);
        $bill_ins_add1 = $result2->street_address1;
        if ($result2->street_address2 !== '') {
            $bill_ins_add1 .= ', ' . $result2->street_address2;
        }
        $bill_ins_add1 = $this->string_format($bill_ins_add1, $b1['bill_ins_add1']);
        $bill_ins_add2 = $this->string_format($result2->city . ', ' . $result2->state . ' ' . $result2->zip, $b1['bill_ins_add2']);
        if ($result2->insurance_plan_assignment == 'Yes') {
            $bill_Box27 = "X     ";
            $bill_Box27P = "Yes";
        } else {
            $bill_Box27 = "     X";
            $bill_Box27P = "No";
        }
        if ($result1->insurance_relationship == 'Self') {
            $bill_Box6 = "X              ";
            $bill_Box6P = "SelfBox6";
        }
        if ($result1->insurance_relationship == 'Spouse') {
            $bill_Box6 = "     X         ";
            $bill_Box6P = "Spouse";
        }
        if ($result1->insurance_relationship == 'Child') {
            $bill_Box6 = "         X     ";
            $bill_Box6P = "Child";
        }
        if ($result1->insurance_relationship == 'Other') {
            $bill_Box6 = "              X";
            $bill_Box6P = "Other";
        }
        $bill_Box7A = $this->string_format($result1->insurance_insu_address, $b1['bill_Box7A']);
        $bill_Box7B = $this->string_format($result1->insurance_insu_city, $b1['bill_Box7B']);
        $bill_Box7C = $this->string_format($result1->insurance_insu_state, $b1['bill_Box7C']);
        $bill_Box7D = $this->string_format($result1->insurance_insu_zip, $b1['bill_Box7D']);
        $bill_Box7E = $this->string_format($result1->insurance_insu_phone, $b1['bill_Box7E'], 'phone');
        $bill_Box11 = $this->string_format($result1->insurance_group, $b1['bill_Box11']);
        $bill_Box11A1 = date('m d Y', $this->human_to_unix($result1->insurance_insu_dob));
        if ($result1->insurance_insu_gender == 'm') {
            $bill_Box11A2 = "X       ";
            $bill_Box11A2P = 'M';
        } elseif ($result1->insurance_insu_gender == 'f') {
            $bill_Box11A2 = "       X";
            $bill_Box11A2P = 'F';
        } else {
            $bill_Box11A2 = "        ";
            $bill_Box11A2P = 'U';
        }
        if ($insurance_id_2 == '' || $insurance_id_2 == '0') {
            $bill_Box9D = '';
            $bill_Box9 = '';
            $bill_Box9A = '';
            $bill_Box11D = '     X';
            $bill_Box11DP = 'No';
        } else {
            $result3 = DB::table('insurance')->where('insurance_id', '=', $insurance_id_2)->first();
            $bill_Box9D = $result3->insurance_plan_name;
            $bill_Box9 = $result3->insurance_insu_lastname . ', ' . $result3->insurance_insu_firstname;
            $bill_Box9A = $result3->insurance_group;
            $bill_Box11D = 'X     ';
            $bill_Box11DP = 'Yes';
        }
        $bill_Box9 = $this->string_format($bill_Box9, $b1['bill_Box9']);
        $bill_Box9A = $this->string_format($bill_Box9A, $b1['bill_Box9A']);
        $bill_Box9D = $this->string_format($bill_Box9D, $b1['bill_Box9D']);
        $bill_Box2 = $this->string_format($row->lastname . ', ' . $row->firstname, 28);
        $bill_Box3A = date('m d Y', $this->human_to_unix($row->DOB));
        if ($row->sex == 'm') {
            $bill_Box3B = "X     ";
            $bill_Box3BP = 'M';
        } elseif ($row->sex == 'f') {
            $bill_Box3B = "     X";
            $bill_Box3BP = 'F';
        } else {
            $bill_Box3B = "      ";
            $bill_Box3BP = 'U';
        }
        if ($row->employer != '') {
            $bill_Box11B = $row->employer;
        } else {
            $bill_Box11B = "";
        }
        $bill_Box11B = $this->string_format($bill_Box11B, $b1['bill_Box11B']);
        $bill_Box5A = $this->string_format($row->address, $b1['bill_Box5A']);
        $bill_Box5B = $this->string_format($row->city, $b1['bill_Box5B']);
        $bill_Box5C = $this->string_format($row->state, $b1['bill_Box5C']);
        $bill_Box5D = $this->string_format($row->zip, $b1['bill_Box5D']);
        $bill_Box5E = $this->string_format($row->phone_home, $b1['bill_Box5E'], 'phone');
        $work = $encounterInfo->encounter_condition_work;
        if ($work == 'Yes') {
            $bill_Box10A = "X      ";
            $bill_Box10AP = 'Yes';
        } else {
            $bill_Box10A = "      X";
            $bill_Box10AP = 'No';
        }
        $auto = $encounterInfo->encounter_condition_auto;
        if ($auto == 'Yes') {
            $bill_Box10B1 = "X      ";
            $bill_Box10B1P = 'Yes';
            $bill_Box10B2 = $encounterInfo->encounter_condition_auto_state;
        } else {
            $bill_Box10B1 = "      X";
            $bill_Box10B1P = 'No';
            $bill_Box10B2 = "";
        }
        $bill_Box10B2 = $this->string_format($bill_Box10B2, $b1['bill_Box10B2']);
        $other = $encounterInfo->encounter_condition_other;
        if ($other == 'Yes') {
            $bill_Box10C = "X      ";
            $bill_Box10CP = "Yes";
        } else {
            $bill_Box10C = "      X";
            $bill_Box10CP = 'No';
        }
        $provider = $encounterInfo->encounter_provider;
        $user_row = DB::table('users')->where('displayname', '=', $provider)->where('group_id', '=', '2')->first();
        $result4 = DB::table('providers')->where('id', '=', $user_row->id)->first();
        $npi = $result4->npi;
        if ($encounterInfo->referring_provider != 'Primary Care Provider' || $encounterInfo->referring_provider != '') {
            $bill_Box17 = $encounterInfo->referring_provider;
            $bill_Box17A = $encounterInfo->referring_provider_npi;
        } else {
            if ($encounterInfo->referring_provider != 'Primary Care Provider') {
                $bill_Box17 = '';
                $bill_Box17A = '';
            } else {
                $bill_Box17 = $provider;
                $bill_Box17A = $npi;
            }
        }
        $bill_Box17 = $this->string_format($bill_Box17, $b1['bill_Box17']);
        $bill_Box17A = $this->string_format($bill_Box17A, $b1['bill_Box17A']);
        $bill_Box21A = $practiceInfo->icd;
        if ($result2->insurance_box_31 == 'n') {
            $bill_Box31 = $provider;
        } else {
            $provider2 = DB::table('users')->where('id', '=', $encounterInfo->user_id)->first();
            $bill_Box31 = $provider2->lastname . ", " . $provider2->firstname;
        }
        $bill_Box31 = $this->string_format($bill_Box31, $b1['bill_Box31']);
        $bill_Box33B = $this->string_format($provider, $b1['bill_Box33B']);
        $pos = $encounterInfo->encounter_location;
        $bill_Box25 = $this->string_format($practiceInfo->tax_id, $b1['bill_Box25']);
        $bill_Box26 = $this->string_format($pid . '_' . $eid, $b1['bill_Box26']);
        $bill_Box32A = $this->string_format($practiceInfo->practice_name, $b1['bill_Box32A']);
        $bill_Box32B = $practiceInfo->street_address1;
        if ($practiceInfo->street_address2 != '') {
            $bill_Box32B .= ', ' . $practiceInfo->street_address2;
        }
        $bill_Box32B = $this->string_format($bill_Box32B, $b1['bill_Box32B']);
        $bill_Box32C = $this->string_format($practiceInfo->city . ', ' . $practiceInfo->state . ' ' . $practiceInfo->zip, $b1['bill_Box32C']);
        if ($result2->insurance_box_32a == 'n') {
            $bill_Box32D = $practiceInfo->npi;
        } else {
            $provider3 = DB::table('providers')->where('id', '=', $encounterInfo->user_id)->first();
            $bill_Box32D = $provider3->npi;
        }
        $bill_Box32D = $this->string_format($bill_Box32D, $b1['bill_Box32D']);
        $bill_Box33A = $this->string_format($practiceInfo->phone, $b1['bill_Box33A'], 'phone');
        $bill_Box33C = $practiceInfo->billing_street_address1;
        if ($practiceInfo->billing_street_address2 != '') {
            $bill_Box33C .= ', ' . $practiceInfo->billing_street_address2;
        }
        $bill_Box33C = $this->string_format($bill_Box33C, $b1['bill_Box33C']);
        $bill_Box33D = $this->string_format($practiceInfo->billing_city . ', ' . $practiceInfo->billing_state . ' ' . $practiceInfo->billing_zip, $b1['bill_Box33D']);
        $result5 = DB::table('billing_core')
            ->where('eid', '=', $eid)
            ->where('cpt', 'NOT LIKE', "sp%")
            ->orderBy('cpt_charge', 'desc')
            ->get();
        $num_rows5 = $result5->count();
        $result5 = json_decode(json_encode($result5), true);
        if ($num_rows5 > 0) {
            $result6 = DB::table('assessment')->where('eid', '=', $eid)->first();
            $bill_Box21_1 = $this->string_format($result6->assessment_icd1, $b1['bill_Box21_1']);
            $bill_Box21_2 = $this->string_format($result6->assessment_icd2, $b1['bill_Box21_2']);
            $bill_Box21_3 = $this->string_format($result6->assessment_icd3, $b1['bill_Box21_3']);
            $bill_Box21_4 = $this->string_format($result6->assessment_icd4, $b1['bill_Box21_4']);
            $bill_Box21_5 = $this->string_format($result6->assessment_icd5, $b1['bill_Box21_5']);
            $bill_Box21_6 = $this->string_format($result6->assessment_icd6, $b1['bill_Box21_6']);
            $bill_Box21_7 = $this->string_format($result6->assessment_icd7, $b1['bill_Box21_7']);
            $bill_Box21_8 = $this->string_format($result6->assessment_icd8, $b1['bill_Box21_8']);
            $bill_Box21_9 = $this->string_format($result6->assessment_icd9, $b1['bill_Box21_9']);
            $bill_Box21_10 = $this->string_format($result6->assessment_icd10, $b1['bill_Box21_10']);
            $bill_Box21_11 = $this->string_format($result6->assessment_icd11, $b1['bill_Box21_11']);
            $bill_Box21_12 = $this->string_format($result6->assessment_icd12, $b1['bill_Box21_12']);
            $i = 0;
            foreach ($result5 as $key5 => $value5) {
                $cpt_charge5[$key5] = $value5['cpt_charge'];
                $result5_arr[$key5] = $value5;
            }
            array_multisort($cpt_charge5, SORT_DESC, $result5_arr);
            while ($i < $num_rows5) {
                $cpt_final[$i] = $result5[$i];
                $cpt_final[$i]['dos_f'] = date("m d y", strtotime($cpt_final[$i]['dos_f']));
                $cpt_final[$i]['dos_t'] = date("m d y", strtotime($cpt_final[$i]['dos_t']));
                $cpt_final[$i]['pos'] = $this->string_format($pos, $b1['bill_Box24B1']);
                $cpt_final[$i]['cpt'] = $this->string_format($cpt_final[$i]['cpt'], $b1['bill_Box24D1']);
                $cpt_final[$i]['modifier'] = $this->string_format($cpt_final[$i]['modifier'], $b1['bill_Modifier1']);
                $cpt_final[$i]['unit1'] = $cpt_final[$i]['unit'];
                $cpt_final[$i]['unit'] = $this->string_format($cpt_final[$i]['unit'], $b1['bill_Box24G1']);
                $cpt_final[$i]['cpt_charge1'] = $cpt_final[$i]['cpt_charge'];
                $cpt_final[$i]['cpt_charge'] = number_format($cpt_final[$i]['cpt_charge'], 2, ' ', '');
                $cpt_final[$i]['cpt_charge'] = $this->string_format($cpt_final[$i]['cpt_charge'], $b1['bill_Box24F1']);
                $cpt_final[$i]['npi'] = $this->string_format($npi, $b1['bill_Box24J1']);
                $cpt_final[$i]['icd_pointer'] =  $this->string_format($cpt_final[$i]['icd_pointer'], $b1['bill_Box24E6']);
                $i++;
            }
            if ($num_rows5 < 6) {
                $array['dos_f'] = $this->string_format('', $b1['bill_DOS1F']);
                $array['dos_t'] = $this->string_format('', $b1['bill_DOS1T']);
                $array['pos'] = $this->string_format('', $b1['bill_Box24B1']);
                $array['cpt'] = $this->string_format('', $b1['bill_Box24D1']);
                $array['modifier'] = $this->string_format('', $b1['bill_Modifier1']);
                $array['unit1'] = '0';
                $array['unit'] = $this->string_format('', $b1['bill_Box24G1']);
                $array['cpt_charge1'] = '0';
                $array['cpt_charge'] = $this->string_format('', $b1['bill_Box24F1']);
                $array['npi'] = $this->string_format('', $b1['bill_Box24J1']);
                $array['icd_pointer'] =  $this->string_format('', $b1['bill_Box24E6']);
                $cpt_final = array_pad($cpt_final, 6, $array);
            }
            $bill_Box28 = 0;
            for ($n=0; $n<=5; $n++) {
                if ($cpt_final[$n]['cpt_charge1'] !== 0 && $cpt_final[$n]['unit1'] !== 0) {
                    $bill_Box28 += $cpt_final[$n]['cpt_charge1'] * $cpt_final[$n]['unit1'];
                }
            }
            $bill_Box28 = number_format($bill_Box28, 2, ' ', '');
            $bill_Box28 = $this->string_format($bill_Box28, $b1['bill_Box28']);
            $bill_Box29 = $this->string_format('0 00', $b1['bill_Box29']);
            $data1 = [];
            foreach ($b2 as $b2_k => $b2_v) {
                if (isset($b2_v['i'])) {
                    if (isset($b2_v['k'])) {
                        $data1[$b2_k] = number_format(${$b2_v['id']}[$b2_v['i']][$b2_v['j']] * ${$b2_v['id']}[$b2_v['i']][$b2_v['k']], 2, ' ', '');
                    } else {
                        $data1[$b2_k] = ${$b2_v['id']}[$b2_v['i']][$b2_v['j']];
                    }
                } else {
                    $data1[$b2_k] = ${$b2_v['id']};
                }
            }
            DB::table('billing')->insert($data1);
            $this->audit('Add');
            unset($cpt_final[0]);
            unset($cpt_final[1]);
            unset($cpt_final[2]);
            unset($cpt_final[3]);
            unset($cpt_final[4]);
            unset($cpt_final[5]);
            if ($num_rows5 > 6 && $num_rows5 < 11) {
                $k = 6;
                foreach ($cpt_final as $k=>$v) {
                    $l = $k - 6;
                    $cpt_final[$l] = $cpt_final[$k];
                    unset($cpt_final[$k]);
                    $k++;
                }
                $num_rows6 = count($cpt_final);
                if ($num_rows6 < 6) {
                    $array1['dos_f'] = $this->string_format('', $b1['bill_DOS1F']);
                    $array1['dos_t'] = $this->string_format('', $b1['bill_DOS1T']);
                    $array1['pos'] = $this->string_format('', $b1['bill_Box24B1']);
                    $array1['cpt'] = $this->string_format('', $b1['bill_Box24D1']);
                    $array1['modifier'] = $this->string_format('', $b1['bill_Modifier1']);
                    $array1['unit1'] = '0';
                    $array1['unit'] = $this->string_format('', $b1['bill_Box24G1']);
                    $array1['cpt_charge1'] = '0';
                    $array1['cpt_charge'] = $this->string_format('', $b1['bill_Box24F1']);
                    $array1['npi'] = $this->string_format('', $b1['bill_Box24J1']);
                    $array1['icd_pointer'] =  $this->string_format('', $b1['bill_Box24E6']);
                    $cpt_final = array_pad($cpt_final, 6, $array1);
                }
                $bill_Box28 = 0;
                for ($m=0; $m<=5; $m++) {
                    if ($cpt_final[$m]['cpt_charge1'] !== 0 && $cpt_final[$m]['unit1'] !== 0) {
                        $bill_Box28 += $cpt_final[$m]['cpt_charge1'] * $cpt_final[$m]['unit1'];
                    }
                }
                $bill_Box28 = number_format($bill_Box28, 2, ' ', '');
                $bill_Box28 = $this->string_format($bill_Box28, $b1['bill_Box28']);
                $bill_Box29 = $this->string_format('0 00', $b1['bill_Box29']);
                $data2 = [];
                foreach ($b2 as $b2_k => $b2_v) {
                    if (isset($b2_v['i'])) {
                        if (isset($b2_v['k'])) {
                            $data2[$b2_k] = number_format(${$b2_v['id']}[$b2_v['i']][$b2_v['j']] * ${$b2_v['id']}[$b2_v['i']][$b2_v['k']], 2, ' ', '');
                        } else {
                            $data2[$b2_k] = ${$b2_v['id']}[$b2_v['i']][$b2_v['j']];
                        }
                    } else {
                        $data2[$b2_k] = ${$b2_v['id']};
                    }
                }
                DB::table('billing')->insert($data2);
                $this->audit('Add');
                unset($cpt_final[0]);
                unset($cpt_final[1]);
                unset($cpt_final[2]);
                unset($cpt_final[3]);
                unset($cpt_final[4]);
                unset($cpt_final[5]);
            }
        } else {
            return "No CPT charges filed. Billing not saved.";
            exit (0);
        }
        return 'Billing saved and waiting to be submitted!';
    }

    protected function birthday_reminder($practice_id)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        if ($practice->timezone != null) {
            date_default_timezone_set($practice->timezone);
        }
        $date = date('-m-d');
        $i = 0;
        $query = DB::table('demographics')
            ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
            ->select('demographics.firstname', 'demographics.reminder_to', 'demographics.reminder_method')
            ->where('demographics_relate.practice_id', '=', $practice_id)
            ->where('demographics.active', '=', '1')
            ->where('demographics.reminder_to', '!=', '')
            ->where('DOB', 'LIKE', "%$date%")
            ->get();
        if ($query) {
            foreach ($query as $row) {
                $to = $row->reminder_to;
                if ($to != '') {
                    $data_message['clinic'] = $practice->practice_name;
                    $data_message['phone'] = $practice->phone;
                    $data_message['email'] = $practice->email;
                    $data_message['birthday_message'] = $practice->birthday_message;
                    $data_message['patientname'] = $row->firstname;
                    if ($row->reminder_method == 'Cellular Phone') {
                        $this->send_mail(array('text' => 'emails.birthdayremindertext'), $data_message, 'Happy Birthday, ' . $row->firstname, $to, $practice_id);
                    } else {
                        $this->send_mail('emails.birthdayreminder', $data_message, 'Happy Birthday, ' . $row->firstname, $to, $practice_id);
                    }
                    $i++;
                }
            }
        }
        $data['birthday_sent_date'] = date('Y-m-d');
        DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data);
        $this->audit('Update');
        return $i;
    }

    protected function bright_futures()
    {
        $url = 'https://brightfutures.aap.org/families/Pages/Resources-for-Families.aspx';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $html = new Htmldom($data);
        $data1['English'][''] = '';
        $data1['Spanish'][''] = '';
        if (isset($html)) {
            foreach ($html->find('div.bf-pdf-icon-inner') as $link) {
                $pdf = $link->find('a', 0);
                $pdf_url = $pdf->href;
                $pdf_label = $pdf->innertext;
                $lang = 'English';
                if (strpos($pdf_url,'spanish')) {
                    $lang = 'Spanish';
                }
                $data1[$lang][$pdf_url] = $pdf_label;
            }
        }
        return $data1;
    }

    protected function changeEnv($data = []){
		if(! empty($data)){
			// Read .env-file
			$env = file_get_contents(base_path() . '/.env');
			// Split string on every " " and write into array
			$env = preg_split('/\s+/', $env);;
			// Loop through given data
			foreach((array)$data as $key => $value){
                $new = true;
				// Loop through .env-data
				foreach($env as $env_key => $env_value){
					// Turn the value into an array and stop after the first split
					// So it's not possible to split e.g. the App-Key by accident
					$entry = explode("=", $env_value, 2);
					// Check, if new key fits the actual .env-key
					if($entry[0] == $key){
						// If yes, overwrite it with the new one
						$env[$env_key] = $key . "=" . $value;
                        $new = false;
					} else {
						// If not, keep the old one
						$env[$env_key] = $env_value;
					}
				}
                if ($new == true) {
					$env[$key] = $key . "=" . $value;
				}
			}
			// Turn the array back to an String
			$env = implode("\n", $env);
			// And overwrite the .env with the new data
			file_put_contents(base_path() . '/.env', $env);
			return true;
		} else {
			return false;
		}
	}

    protected function check_extension($extension, $practice_id)
    {
        $result = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        if ($result->$extension == 'y') {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    protected function clean_practice()
    {
        $query = DB::table('practiceinfo')->where('practice_id', '=', '1')->where('patient_centric', '=', 'y')->first();
        $i = 0;
        if ($query) {
            $time = time();
            $query1 = DB::table('practiceinfo')->where('practice_id', '!=', '1')->where('practice_registration_timeout', '<', $time)->get();
            if ($query1) {
                foreach ($query1 as $row1) {
                    if ($row1->practice_registration_timeout != '') {
                        DB::table('practiceinfo')->where('practice_id', '=', $row1->practice_id)->delete();
                        $this->audit('Delete');
                        DB::table('demographics_relate')->where('practice_id', '=', $row1->practice_id)->delete();
                        $this->audit('Delete');
                        $i++;
                    }
                }
            }
        }
        return $i;
    }

    protected function clean_temp_dir()
    {
        $dir = public_path() . '/temp';
        $files = scandir($dir);
        $count = count($files);
        $time = time() - 86400;
        for ($i = 2; $i < $count; $i++) {
            $line = explode('_', $files[$i]);
            $file = $dir . '/' . $files[$i];
            if (file_exists($file)) {
                if ($line[0] !== '.gitignore') {
                    if ($line[0] < $time) {
                        unlink($file);
                    }
                }
            }
        }
        return $count;
    }

    protected function clean_uma_sessions()
    {
        Session::forget('fhir_name');
        Session::forget('medicationstatement_uri');
        Session::forget('patient_uri');
        Session::forget('rpt');
        Session::forget('uma_add_patient');
        Session::forget('uma_as_name');
        Session::forget('uma_auth_access_token_nosh');
        Session::forget('uma_auth_resources');
        Session::forget('uma_client_id');
        Session::forget('uma_client_secret');
        Session::forget('uma_pid');
        Session::forget('uma_permission_ticket');
        Session::forget('uma_resource_uri');
        Session::forget('uma_resources_start');
        Session::forget('uma_uri');
    }

    protected function claim_reason_code($code)
    {
        $url = 'http://www.wpc-edi.com/reference/codelists/healthcare/claim-adjustment-reason-codes/';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $html = new Htmldom($data);
        $table = $html->find('table[id=codelist]',0);
        $description = '';
        foreach ($table->find('tr[class=current]') as $row) {
            $code_row = $row->find('td[class=code]',0);
            $description_row = $row->find('td[class=description]',0);
            $date_row = $row->find('span[class=dates]',0);
            if ($code == $code_row->innertext) {
                $description = $description_row->plaintext;
                $date = $date_row->plaintext;
                $description = trim(str_replace($date, '', $description));
                break;
            }
        }
        if ($description == '') {
            return $code . ', Code unknown';
        } else {
            return $description;
        }
    }

    protected function clinithink($text, $type)
    {
        $url = 'https://cloud.noshchartingsystem.com/noshapi/clinithink';
        $query['type'] = $type;
        if ($type == 'text') {
            $query['text'] = $text;
        }
        if ($type == 'crossmap') {
            $query['code'] = $text;
        }
        $message = http_build_query($query);
        $ch = curl_init();
        // $url = $url . "?" . $message;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    protected function closest_match($input, $compare_arr)
    {
        $shortest = -1;
        foreach ($compare_arr as $compare_k => $compare_v) {
            $compare_v = rtrim(preg_replace("/\([^)]+\)/","",$compare_v));
            $lev = levenshtein($input, $compare_v);
            if ($lev == 0) {
                $closest = $compare_k;
                $shortest = 0;
                // break out of the loop; we've found an exact match
                break;
            }
            if ($lev <= $shortest || $shortest < 0) {
                $closest = $compare_k;
                $shortest = $lev;
            }
        }
        return $closest;
    }

    protected function common_icd()
    {
        $subtypes = [];
        $core_url = 'http://www.nuemd.com/icd-10/common-codes';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $core_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $html = new Htmldom($data);
        $data1 = [];
        if (isset($html)) {
            foreach ($html->find('.specialtyList') as $item) {
                foreach ($item->find('li') as $item1) {
                    $link = $item1->find('a', 0);
                    $data1[] = [
                        'url' => $link->href,
                        'specialty' => $link->innertext
                    ];
                }
            }
        }
        $data3 = [];
        foreach ($data1 as $item2) {
            $base_url = 'http://www.nuemd.com';
            $url = $base_url . $item2['url'];
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, $url);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            $data2 = curl_exec($ch1);
            curl_close($ch1);
            $html1 = new Htmldom($data2);
            if (isset($html1)) {
                foreach ($html1->find('.codeList') as $item3) {
                    foreach ($item3->find('li') as $item4) {
                        $code = $item4->find('div.code', 0);
                        $desc = $item4->find('div.desc', 0);
                        $data3[$item2['specialty']][] = [
                            'code' => trim($code->plaintext),
                            'desc' => trim($desc->innertext)
                        ];
                    }
                }
            }
        }
        $formatter1 = Formatter::make($data3, Formatter::ARR);
        $text = $formatter1->toYaml();
        $file_path = resource_path() . '/common_icd.yaml';
        File::put($file_path, $text);
        return 'OK';
    }

    // protected function convert_cc()
    // {
    //     error_reporting(E_ALL ^ E_DEPRECATED);
    //     $query = DB::table('demographics')->select('pid', 'creditcard_number', 'creditcard_expiration', 'creditcard_type', 'creditcard_key')->get();
    //     if ($query->count()) {
    //         foreach ($query as $row) {
    //             if ($row->creditcard_key !== '') {
    //                 $legacy = new McryptEncrypter($row->creditcard_key, $cipher = MCRYPT_RIJNDAEL_256);
    //                 $number = $legacy->decrypt($row->creditcard_number);
    //                 $data['creditcard_number'] = encrypt($number);
    //                 DB::table('demographics')->where('pid', '=', $row->pid)->update($data);
    //             }
    //         }
    //     }
    //     return true;
    // }

    protected function convert_form()
    {
        $data = [];
        $pre_data = [];
        $search_arr = [' ', ':', '?', ',', ';'];
        $replace_arr = ['_', '', '', '', ''];
        $query = DB::table('templates')->where('category', '=', 'forms')->get();
        foreach ($query as $row) {
            $pre_data[] = [
                'data' => json_decode(unserialize($row->array),true),
                'scoring' => $row->scoring
            ];
        }
        foreach ($pre_data as $row1) {
            $form = [];
            $key = '';
            foreach ($row1['data']['html'] as $row2) {
                $item = [];
                if ($row2['type'] == 'hidden') {
                    if ($row2['name'] == 'forms_title') {
                        $form['forms_title'] = $row2['value'];
                        $key = $row2['value'];
                    }
                    if ($row2['name'] == 'forms_destination') {
                        $form['forms_destination'] = $row2['value'];
                    }
                }
                if ($row2['type'] == 'div') {
                    $options = [];
                    $text = '';
                    foreach ($row2['html'] as $row3) {
                        $item = [];
                        if ($row3['type'] == 'span') {
                            $text = $row3['html'];
                        }
                        if ($row3['type'] == 'radio' || $row3['type'] == 'checkbox' || $row3['type'] == 'select' || $row3['type'] == 'text') {
                            $item['input'] = $row3['type'];
                            $item['name'] = str_replace($search_arr, $replace_arr, strtolower($text));
                            $item['text'] = $text;
                            if ($row3['type'] == 'radio' || $row3['type'] == 'checkbox') {
                                $options[] = $row3['caption'];
                            }
                            if ($row3['type'] == 'select') {
                                foreach ($row3['options'] as $select_k => $select_v) {
                                    $options[] = $select_v;
                                }
                            }
                        }
                        if (! empty($options)) {
                            $item['options'] = implode(',', $options);
                        }
                    }
                }
                if (isset($item['name'])) {
                    $form[] = $item;
                }
            }
            if ($row1['scoring'] !== null) {
                $form['scoring'] = $row1['scoring'];
            }
            $data[$key] = $form;
        }
        $formatter1 = Formatter::make($data, Formatter::ARR);
        $text = $formatter1->toYaml();
        $file_path = resource_path() . '/forms.yaml';
        File::put($file_path, $text);
        return $data;
    }

    protected function convert_number($number)
    {
        if (($number < 0) || ($number > 999999999)) {
            return $number;
        }
        if (is_numeric($number) == false) {
            return $number;
        }
        $Gn = floor($number / 1000000);  /* Millions (giga) */
        $number -= $Gn * 1000000;
        $kn = floor($number / 1000);     /* Thousands (kilo) */
        $number -= $kn * 1000;
        $Hn = floor($number / 100);      /* Hundreds (hecto) */
        $number -= $Hn * 100;
        $Dn = floor($number / 10);       /* Tens (deca) */
        $n = $number % 10;               /* Ones */
        $res = "";
        if ($Gn) {
            $res .= $this->convert_number($Gn) . " Million";
        }
        if ($kn) {
            $res .= (empty($res) ? "" : " ") . $this->convert_number($kn) . " Thousand";
        }
        if ($Hn) {
            $res .= (empty($res) ? "" : " ") . $this->convert_number($Hn) . " Hundred";
        }
        $ones = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
        $tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
        if ($Dn || $n) {
            if (!empty($res)) {
                $res .= " and ";
            }
            if ($Dn < 2) {
                $res .= $ones[$Dn * 10 + $n];
            } else {
                $res .= $tens[$Dn];
                if ($n) {
                    $res .= "-" . $ones[$n];
                }
            }
        }
        if (empty($res)) {
            $res = "zero";
        }
        return $res;
    }

    protected function convert_template()
    {
        ini_set('memory_limit','196M');
        $file = true;
        $arr = [];
        $arr_options = [];
        $remove[] = '*~*';
        if ($file == true) {
            Config::set('excel.csv.delimiter', "\t");
            $reader = Excel::load(resource_path() . '/Default.txt');
            $file_result = $reader->get()->toArray();
            $specific = array_where($file_result, function($value, $key) {
                if (stripos($value['category'] , 'specific') !== false) {
                    return true;
                }
            });
            $query = array_where($file_result, function($value, $key) {
                if (stripos($value['category'] , 'text') !== false) {
                    return true;
                }
            });
        } else {
            $specific = DB::table('templates')->where('category', '=', 'specific')->get();
            $query = DB::table('templates')->where('category', '=', 'text')->get();
        }
        foreach ($specific as $row1) {
            if ($file == true) {
                $key = '*' . $row1['template_name'] . '*';
                $arr_options[$key][] = $row1['array'];
            } else {
                $key = '*' . $row1->template_name . '*';
                $arr_options[$key][] = $row1->array;
            }
            $remove[] = $key;
        }
        $arr_options = $this->super_unique($arr_options);
        $group_name_ros_arr = $this->array_ros();
        $group_name_pe_arr = $this->array_pe();
        foreach ($query as $row) {
            $item = [];
            if ($file == true) {
                $item['text'] = rtrim(str_replace($remove, '', $row['array']));
                $template_name = $row['template_name'];
                $group_name = $row['group'];
                if (strpos($row['template_name'], 'ros_') === 0) {
                    $template_name = 'ros';
                    $group_name = $group_name_ros_arr[$row['template_name']] . ' - ' . $row['group'];
                }
                if (strpos($row['template_name'], 'pe_') === 0) {
                    $template_name = 'pe';
                    $pe_arr = explode(' - ', $group_name_pe_arr[$row['template_name']]);
                    if (count($pe_arr) > 1) {
                        $group_name = $pe_arr[0] . ' - ' . $pe_arr[1];
                        $item['text'] = $row['group'] . ' - ' . $item['text'];
                    } else {
                        $group_name = $group_name_pe_arr[$row['template_name']] . ' - ' . $row['group'];
                    }
                }
                if (strpos($row['array'], '*~*')) {
                    $item['input'] = 'text';
                }
                foreach ($arr_options as $k=>$v) {
                    if (strpos($row['array'], $k)) {
                        $item['input'] = 'radio';
                        $item['options'] = implode(',', $v);
                    }
                }
                if ($row['default'] == 'normal') {
                    if (!isset($item['input'])) {
                        $item['normal'] = true;
                    }
                }
                if ($row['sex'] !== '' && $row['sex'] !== null) {
                    $item['gender'] = $row['sex'];
                }
                if ($row['age'] !== '' && $row['age'] !== null) {
                    $item['age'] = $row['age'];
                }
                if ($item['text'] !== '') {
                    $arr[$template_name][$group_name][] = $item;
                }
            } else {
                $item['text'] = rtrim(str_replace($remove, '', $row->array));
                $template_name = $row->template_name;
                $group_name = $row->group;
                if (strpos($row->template_name, 'ros_') === 0) {
                    $template_name = 'ros';
                    $group_name = $group_name_ros_arr[$row->template_name] . ' - ' . $row->group;
                }
                if (strpos($row->template_name, 'pe_') === 0) {
                    $template_name = 'pe';
                    $pe_arr = explode (' - ', $group_name_pe_arr[$row->template_name]);
                    if (count($pe_arr) > 1) {
                        $group_name = $pe_arr[0] . ' - ' . $row->group;
                        $item['text'] = $pe_arr[1] . ' - ' . $item['text'];
                    } else {
                        $group_name = $group_name_pe_arr[$row->template_name] . ' - ' . $row->group;
                    }
                }
                if (strpos($row->array, '*~*')) {
                    $item['input'] = 'text';
                }
                foreach ($arr_options as $k=>$v) {
                    if (strpos($row->array, $k)) {
                        $item['input'] = 'radio';
                        $item['options'] = implode(',', $v);
                    }
                }
                if ($row->default == 'normal') {
                    if (!isset($item['input'])) {
                        $item['normal'] = true;
                    }
                }
                if ($row->sex !== '' && $row->sex !== null) {
                    $item['gender'] = $row->sex;
                }
                if ($row->age !== '' && $row->age !== null) {
                    $item['age'] = $row->age;
                }
                if ($item['text'] !== '') {
                    $arr[$template_name][$group_name][] = $item;
                }
            }
        }
        $arr = $this->super_unique($arr);
        $formatter1 = Formatter::make($arr, Formatter::ARR);
        $text = $formatter1->toYaml();
        $file_path = resource_path() . '/test.yaml';
        File::put($file_path, $text);
        return $arr_options;
    }

    protected function copy_form($id)
    {
        $result = DB::table('forms')->where('forms_id', '=', $id)->first();
        $query = DB::table('hpi')->where('eid', '=', Session::get('eid'))->first();
        $data['forms'] = '';
        $message = '';
        if ($result->forms_content_text !== '' || $result->forms_content_text !== null) {
            if ($query) {
                if ($query->forms !== '' || $query->forms !== null) {
                    $data['forms'] .= $query->forms;
                }
            }
            if ($data['forms'] !== '') {
                $data['forms'] .= "\n\n";
            }
            $data['forms'] .= $result->forms_content_text;
            if ($query) {
                DB::table('hpi')->where('eid', '=', Session::get('eid'))->update($data);
                $this->audit('Update');
            } else {
                $data['eid'] = Session::get('eid');
                $data['pid'] = Session::get('pid');
                $data['encounter_provider'] = Session::get('encounter_provider');
                DB::table('hpi')->insert($data);
                $this->audit('Add');
            }
            $message = 'Form results copied to encounter';
        }
        return $message;
    }

    protected function correctnull($val)
    {
        if ($val == '') {
            $val = null;
        }
        return $val;
    }

    protected function cpt_search($code)
    {
        Config::set('excel.csv.delimiter', "\t");
        $reader = Excel::load(resource_path() . '/CPT.txt');
        $arr = $reader->noHeading()->get()->toArray();
        $return = '';
        foreach ($arr as $row) {
            if ($row[0] == $code) {
                $return = $row[3];
                break;
            }
        }
        return $return;
    }

    protected function cvx_search($code)
    {
        Config::set('excel.csv.delimiter', "|");
        $reader = Excel::load(resource_path() . '/cvx.txt');
        $arr = $reader->noHeading()->get()->toArray();
        $return = '';
        foreach ($arr as $row) {
            if ($row[4] == 'Active') {
                if (rtrim($row[0]) == $code) {
                    $return = ucfirst($row[2]);
                    break;
                }

            }
        }
        return $return;
    }

    /**
    * Dropdown build
    * @param array  $dropdown_array -
    * $dropdown_array = [
    *    'default_button_text' => 'split button with dropdown',
    *    'default_button_text_url' => URL::to('button_action'), requires default_button_text
    *    'default_button_id' => 'id of element',
    *    'items_button_text' => 'dropdown button text',
    *    'items_button_icon' => 'fa fa-icon',
    *    'items' => [
    *        [
    *            'type' => 'item', or separator or header or item
    *            'label' => 'Practice NPI', needed for item or header
    *            'icon' => 'fa-stethoscope',
    *            'id' => 'id of element',
    *            'url' => 'URL'
    *        ],
    *       [
    *            'type' => 'separator',
    *        ]
    *    ],
    *    'origin' => 'previous URL',
    *    'class' => 'btn-success'
    *    'new_window' => boolean
    * ];
    * @param int $id - Item key in database
    * @return Response
    */
    protected function dropdown_build($dropdown_array)
    {
        $class = 'btn-primary';
        if (isset($dropdown_array['class'])) {
            $class = $dropdown_array['class'];
        }
        $new_window = '';
        if (isset($dropdown_array['new_window'])) {
            $new_window = ' target="_blank"';
            $class .= ' nosh-no-load';
        }
        if (isset($dropdown_array['items'])) {
            $return = '<div class="btn-group">';
            if (count($dropdown_array['items']) == 1 && isset($dropdown_array['items_button_text']) == false) {
                if (isset($dropdown_array['default_button_text'])) {
                    $return .= '<a href="' . $dropdown_array['default_button_text_url'] . '" class="btn ' . $class . ' btn-sm">' . $dropdown_array['default_button_text'] . '</a><a href="' . $dropdown_array['items'][0]['url'] . '" class="btn ' . $class . ' btn-sm"><i class="fa ' . $dropdown_array['items'][0]['icon'] . ' fa-fw fa-btn"></i>' . $dropdown_array['items'][0]['label'] . '</a></div>';
                } else {
                    $return .= '<a href="' . $dropdown_array['items'][0]['url'] . '"';
                    if (isset($dropdown_array['items'][0]['id'])) {
                        $return .= ' id="' . $dropdown_array['items'][0]['id'] . '"';
                    }
                    $return .= ' class="btn ' . $class . ' btn-sm"><i class="fa ' . $dropdown_array['items'][0]['icon'] . ' fa-fw fa-btn"></i>' . $dropdown_array['items'][0]['label'] . '</a></div>';
                }
            } else {
                if (isset($dropdown_array['default_button_text'])) {
                    $return .= '<a href="' . $dropdown_array['default_button_text_url'] . '" class="btn ' . $class . ' btn-sm">' . $dropdown_array['default_button_text'] . '</a><button type="button" class="btn ' . $class . ' btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="caret"></span><span class="sr-only">Toggle Dropdown</span></button>';
                }
                if (isset($dropdown_array['items_button_text'])) {
                    $return .= '<button type="button" class="btn ' . $class . ' btn-sm dropdown-toggle" data-toggle="dropdown"><span class="fa-btn">' . $dropdown_array['items_button_text'] . '</span><span class="caret"></span></button>';
                }
                if (isset($dropdown_array['items_button_icon'])) {
                    $return .= '<button type="button" class="btn ' . $class . ' btn-sm dropdown-toggle" data-toggle="dropdown"><i class="fa ' . $dropdown_array['items_button_icon'] . ' fa-fw fa-btn"></i><span class="caret"></span></button>';
                }
                $return .= '<ul class="dropdown-menu dropdown-menu-right">';
                foreach ($dropdown_array['items'] as $row) {
                    if ($row['type'] == 'separator') {
                        $return .= '<li role="separator" class="divider"></li>';
                    }
                    if ($row['type'] == 'header') {
                        $return .= '<li class="dropdown-header">' . $row['label'] . '</li>';
                    }
                    if ($row['type'] == 'item') {
                        $return .= '<li><a href="' . $row['url'] . '"';
                        if (isset($row['id'])) {
                            $return .= ' id="' . $row['id'] . '"';
                        }
                        $return .= '><i class="fa ' . $row['icon'] . ' fa-fw fa-btn"></i>' . $row['label'] . '</a></li>';
                    }
                }
                $return .= '</ul></div>';
            }
        } else {
            $return = '<div class="btn-group"><a href="' . $dropdown_array['default_button_text_url'] . '"class="btn ' . $class . ' btn-sm"';
            if (isset($dropdown_array['default_button_id'])) {
                $return .= ' id="' . $dropdown_array['default_button_id'] . '"';
            }
            $return .= $new_window . '>' . $dropdown_array['default_button_text'] . '</a></div>';
        }
        return $return;
    }

    protected function encounters_view($eid, $pid, $practice_id, $modal=false, $addendum=false)
    {
        $encounterInfo = DB::table('encounters')->where('eid', '=', $eid)->first();
        $data['patientInfo'] = DB::table('demographics')->where('pid', '=', $pid)->first();
        $data['eid'] = $eid;
        $data['encounter_DOS'] = date('F jS, Y; h:i A', $this->human_to_unix($encounterInfo->encounter_DOS));
        $data['encounter_provider'] = $encounterInfo->encounter_provider;
        $data['date_signed'] = date('F jS, Y; h:i A', $this->human_to_unix($encounterInfo->date_signed));
        $data['age1'] = $encounterInfo->encounter_age;
        $data['dob'] = date('F jS, Y', $this->human_to_unix($data['patientInfo']->DOB));
        $date = Date::parse($data['patientInfo']->DOB);
        $age_arr = explode(',', $date->timespan());
        $data['age'] = ucwords($age_arr[0] . ' Old');
        $gender_arr = $this->array_gender();
        $data['gender'] = $gender_arr[$data['patientInfo']->sex];
        $data['encounter_cc'] = nl2br($encounterInfo->encounter_cc);
        $practiceInfo = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        $data['hpi'] = '';
        $data['ros'] = '';
        $data['oh'] = '';
        $data['vitals'] = '';
        $data['pe'] = '';
        $data['images'] = '';
        $data['labs'] = '';
        $data['procedure'] = '';
        $data['assessment'] = '';
        $data['orders'] = '';
        $data['rx'] = '';
        $data['plan'] = '';
        $data['billing'] = '';
        $hpiInfo = DB::table('hpi')->where('eid', '=', $eid)->first();
        if ($hpiInfo) {
            if (!is_null($hpiInfo->hpi) && $hpiInfo->hpi !== '') {
                $data['hpi'] = '<br><h4>History of Present Illness:</h4><p class="view">';
                $data['hpi'] .= nl2br($hpiInfo->hpi);
                $data['hpi'] .= '</p>';
            }
            if (!is_null($hpiInfo->situation) && $hpiInfo->situation !== '') {
                $data['hpi'] = '<br><h4>Situation:</h4><p class="view">';
                $data['hpi'] .= nl2br($hpiInfo->situation);
                $data['hpi'] .= '</p>';
            }
            if (!is_null($hpiInfo->forms) && $hpiInfo->forms !== '') {
                $data['hpi'] .= '<br><h4>Form Responses:</h4><p class="view">';
                $data['hpi'] .= nl2br($hpiInfo->forms);
                $data['hpi'] .= '</p>';
            }
        }
        $rosInfo = DB::table('ros')->where('eid', '=', $eid)->first();
        if ($rosInfo) {
            $data['ros'] = '<br><h4>Review of Systems:</h4><p class="view">';
            $ros_arr = $this->array_ros();
            foreach ($ros_arr as $ros_k => $ros_v) {
                if ($rosInfo->{$ros_k} !== '' && $rosInfo->{$ros_k} !== null) {
                    if ($ros_k !== 'ros') {
                        $data['ros'] .= '<strong>' . $ros_v . ': </strong>';
                    }
                    $data['ros'] .= nl2br($rosInfo->{$ros_k});
                    $data['ros'] .= '<br /><br />';
                }
            }
            $data['ros'] .= '</p>';
        }
        $ohInfo = DB::table('other_history')->where('eid', '=', $eid)->first();
        if ($ohInfo) {
            $oh_arr = $this->array_oh();
            $data['oh'] = '<br><h4>Other Pertinent History:</h4><p class="view">';
            foreach ($oh_arr as $oh_k => $oh_v) {
                if ($ohInfo->{$oh_k} !== '' && $ohInfo->{$oh_k} !== null) {
                    $data['oh'] .= '<strong>' . $oh_v . ': </strong>';
                    if ($oh_k == 'oh_fh') {
                        $ohInfo->{$oh_k} = str_replace('---', '', $ohInfo->{$oh_k});
                    }
                    $data['oh'] .= nl2br($ohInfo->{$oh_k});
                    $data['oh'] .= '<br /><br />';
                }
            }
            $data['oh'] .= '</p>';
        }
        $vitalsInfo = DB::table('vitals')->where('eid', '=', $eid)->first();
        if ($vitalsInfo) {
            $vitals_arr = $this->array_vitals();
            $vitals_arr1 = $this->array_vitals1();
            $data['vitals'] = '<br><h4>Vital Signs:</h4><p class="view">';
            $data['vitals'] .= '<strong>Date/Time:</strong>';
            $data['vitals'] .= $vitalsInfo->vitals_date . '<br>';
            foreach ($vitals_arr as $vitals_k => $vitals_v) {
                if (!empty($vitalsInfo->{$vitals_k})) {
                    if ($vitals_k !== 'bp_systolic' && $vitals_k !== 'bp_diastolic') {
                        $data['vitals'] .= '<strong>' . $vitals_v['name'] . ': </strong>';
                        if ($vitals_k == 'temp') {
                            $data['vitals'] .= $vitalsInfo->{$vitals_k} . ' ' . $vitals_v['unit'] . ', ' . $vitalsInfo->temp_method . '<br>';
                        } else {
                            $data['vitals'] .= $vitalsInfo->{$vitals_k} . ' ' . $vitals_v['unit'] . '<br>';
                        }
                    } elseif ($vitals_k == 'bp_systolic') {
                        $data['vitals'] .= '<strong>Blood Pressure: </strong>';
                        $data['vitals'] .= $vitalsInfo->bp_systolic . '/' . $vitalsInfo->bp_diastolic . ' mmHg, ' . $vitalsInfo->bp_position . '<br>';
                    }
                }
            }
            foreach ($vitals_arr1 as $vitals_k1 => $vitals_v1) {
                if (!empty($vitalsInfo->{$vitals_k1})) {
                    $data['vitals'] .= '<strong>' . $vitals_v1 . ': </strong>';
                    $data['vitals'] .= $vitalsInfo->{$vitals_k1} . '<br>';
                }
            }
            if (!empty($vitalsInfo->vitals_other)) {
                $data['vitals'] .= '<strong>Notes: </strong>';
                $data['vitals'] .= nl2br($vitalsInfo->vitals_other) . '<br>';
            }
            $data['vitals'] .= '</p>';
        }
        $peInfo = DB::table('pe')->where('eid', '=', $eid)->first();
        if ($peInfo) {
            $pe_arr = $this->array_pe();
            $data['pe'] = '<br><h4>Physical Exam:</h4><p class="view">';
            foreach ($pe_arr as $pe_k => $pe_v) {
                if ($peInfo->{$pe_k} !== '' && $peInfo->{$pe_k} !== null) {
                    if ($pe_k !== 'pe') {
                        $data['pe'] .= '<strong>' . $pe_v . ': </strong>';
                    }
                    $data['pe'] .= nl2br($peInfo->{$pe_k});
                    $data['pe'] .= '<br /><br />';
                }
            }
            $data['pe'] .= '</p>';
        }
        $imagesInfo = DB::table('image')->where('eid', '=', $eid)->get();
        $html = '';
        if ($imagesInfo->count()) {
            $data['images'] = '<br><h4>Images:</h4><p class="view">';
            $k = 0;
            foreach ($imagesInfo as $imagesInfo_row) {
                $directory = $practiceInfo->documents_dir . $pid . "/";
                $new_directory = public_path() . '/temp/';
                $new_directory1 = '/temp/';
                $file_path = str_replace($directory, $new_directory, $imagesInfo_row->image_location);
                $file_path1 = str_replace($directory, $new_directory1, $imagesInfo_row->image_location);
                copy($imagesInfo_row->image_location, $file_path);
                if ($k != 0) {
                    $data['images'] .= '<br><br>';
                }
                $data['images'] .= HTML::image($file_path1, 'Image', array('border' => '0'));
                if ($imagesInfo_row->image_description != '') {
                    $data['images'] .= '<br>' . $imagesInfo_row->image_description . '<br>';
                }
                $k++;
            }
        }
        $labsInfo = DB::table('labs')->where('eid', '=', $eid)->first();
        if ($labsInfo) {
            $labs_arr = $this->array_labs();
            $data['labs'] = '<br><h4>Laboratory Testing:</h4><p class="view">';
            if ($labsInfo->labs_ua_urobili != '' || $labsInfo->labs_ua_bilirubin != '' || $labsInfo->labs_ua_ketones != '' || $labsInfo->labs_ua_glucose != '' || $labsInfo->labs_ua_protein != '' || $labsInfo->labs_ua_nitrites != '' || $labsInfo->labs_ua_leukocytes != '' || $labsInfo->labs_ua_blood != '' || $labsInfo->labs_ua_ph != '' || $labsInfo->labs_ua_spgr != '' || $labsInfo->labs_ua_color != '' || $labsInfo->labs_ua_clarity != ''){
                $data['labs'] .= '<strong>Dipstick Urinalysis:</strong><br /><table>';
                foreach ($labs_arr['ua'] as $labs_ua_k => $labs_ua_v) {
                    if ($labsInfo->{$labs_ua_k} !== '' && $labsInfo->{$labs_ua_k} !== null) {
                        $data['labs'] .= '<tr><th align=\"left\">' . $labs_ua_v . ':</th><td align=\"left\">' . $labsInfo->{$labs_ua_k} . '</td></tr>';
                    }
                }
                $data['labs'] .= '</table>';
            }
            foreach ($labs_arr['single'] as $labs_single_k => $labs_single_v) {
                if ($labsInfo->{$labs_single_k} !== '' && $labsInfo->{$labs_single_k} !== null) {
                    $data['labs'] .= '<strong>' . $labs_single_v . ': </strong>';
                    $data['labs'] .= $labsInfo->{$labs_single_k};
                    $data['labs'] .= '<br /><br />';
                }
            }
            $data['labs'] .= '</p>';
        }
        $assessmentInfo = DB::table('assessment')->where('eid', '=', $eid)->first();
        if ($assessmentInfo) {
            $assessment_arr = $this->array_assessment();
            $data['assessment'] = '<br><h4>Assessment:</h4><p class="view">';
            for ($l = 1; $l <= 12; $l++) {
                $col0 = 'assessment_' . $l;
				// GYN 20181006: Add ICD code to assessment display
				$col1 = 'assessment_icd' . $l;
                if (!empty($assessmentInfo->{$col0})) {
                    if ($l > 1) {
                        $data['assessment'] .= '<br />';
                    }
                    $data['assessment'] .= '<strong>' . $assessmentInfo->{$col0};
					if (!empty($assessmentInfo->{$col1})) {
						$data['assessment'] .= ' [' . $assessmentInfo->{$col1} . ']';
					}
					$data['assessment'] .= '</strong><br />';
                }
            }
            foreach ($assessment_arr as $assessment_k => $assessment_v) {
                if ($assessmentInfo->{$assessment_k} !== '' && $assessmentInfo->{$assessment_k} !== null) {
                    if ($encounterInfo->encounter_template == 'standardmtm') {
                        $data['assessment'] .= '<strong>' . $assessment_v['standardmtm'] . ': </strong>';
                    } else {
                        $data['assessment'] .= '<strong>' . $assessment_v['standard'] . ': </strong>';
                    }
                    $data['assessment'] .= nl2br($assessmentInfo->{$assessment_k});
                    $data['assessment'] .= '<br /><br />';
                }
            }
            $data['assessment'] .= '</p>';
        }
        $procedureInfo = DB::table('procedure')->where('eid', '=', $eid)->first();
        if ($procedureInfo) {
            $procedure_arr = $this->array_procedure();
            $data['procedure'] = '<br><h4>Procedures:</h4><p class="view">';
            foreach ($procedure_arr as $procedure_k => $procedure_v) {
                if ($procedureInfo->{$procedure_k} !== '' && $procedureInfo->{$procedure_k} !== null) {
                    if ($procedure_k == 'proc_description') {
                        if ($this->yaml_check($procedureInfo->{$procedure_k})) {
                            $proc_search_arr = ['code:', 'timestamp:', 'procedure:', 'type:', '---' . "\n", '|'];
                            $proc_replace_arr = ['<b>Procedure Code:</b>', '<b>When:</b>', '<b>Procedure Description:</b>', '<b>Type:</b>', '', ''];
                            $data['procedure'] .= nl2br(str_replace($proc_search_arr, $proc_replace_arr, $procedureInfo->{$procedure_k}));
                        } else {
                            $data['procedure'] .= '<strong>' . $procedure_v . ': </strong>';
                            $data['procedure'] .= nl2br($procedureInfo->{$procedure_k});
                            $data['procedure'] .= '<br /><br />';
                        }
                    } else {
                        $data['procedure'] .= '<strong>' . $procedure_v . ': </strong>';
                        $data['procedure'] .= nl2br($procedureInfo->{$procedure_k});
                        $data['procedure'] .= '<br /><br />';
                    }
                }
            }
            $data['procedure'] .= '</p>';
        }
        $ordersInfo1 = DB::table('orders')->where('eid', '=', $eid)->get();
        if ($ordersInfo1->count()) {
            $data['orders'] = '<br><h4>Orders:</h4><p class="view">';
            $orders_lab_array = [];
            $orders_radiology_array = [];
            $orders_cp_array = [];
            $orders_referrals_array = [];
            foreach ($ordersInfo1 as $ordersInfo) {
                $address_row1 = DB::table('addressbook')->where('address_id', '=', $ordersInfo->address_id)->first();
                if ($address_row1) {
                    $orders_displayname = $address_row1->displayname;
                    if ($ordersInfo->orders_referrals != '') {
                        $orders_displayname = $address_row1->specialty . ': ' . $address_row1->displayname;
                    }
                } else {
                    $orders_displayname = 'Unknown';
                }
                if ($ordersInfo->orders_labs != '') {
                    $orders_lab_array[] = 'Orders sent to ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_labs) . '<br />';
                }
                if ($ordersInfo->orders_radiology != '') {
                    $orders_radiology_array[] = 'Orders sent to ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_radiology) . '<br />';
                }
                if ($ordersInfo->orders_cp != '') {
                    $orders_cp_array[] = 'Orders sent to ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_cp) . '<br />';
                }
                if ($ordersInfo->orders_referrals != '') {
                    $orders_referrals_array[] = 'Referral sent to ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_referrals) . '<br />';
                }
            }
            if (! empty($orders_lab_array)) {
                $data['orders'] .= '<strong>Labs: </strong><br>';
                foreach ($orders_lab_array as $lab_item) {
                    $data['orders'] .= $lab_item;
                }
            }
            if (! empty($orders_radiology_array)) {
                $data['orders'] .= '<strong>Imaging: </strong><br>';
                foreach ($orders_radiology_array as $radiology_item) {
                    $data['orders'] .= $radiology_item;
                }
            }
            if (! empty($orders_cp_array)) {
                $data['orders'] .= '<strong>Cardiopulmonary: </strong><br>';
                foreach ($orders_cp_array as $cp_item) {
                    $data['orders'] .= $cp_item;
                }
            }
            if (! empty($orders_referrals_array)) {
                $data['orders'] .= '<strong>Referrals: </strong><br>';
                foreach ($orders_referrals_array as $referrals_item) {
                    $data['orders'] .= $referrals_item;
                }
            }
            $data['orders'] .= '</p>';
        }
        $rxInfo = DB::table('rx')->where('eid', '=', $eid)->first();
        if ($rxInfo) {
            $rx_arr = $this->array_rx();
            $data['rx'] = '<br><h4>Prescriptions and Immunizations:</h4><p class="view">';
            foreach ($rx_arr as $rx_k => $rx_v) {
                if ($rxInfo->{$rx_k} !== '' && $rxInfo->{$rx_k} !== null) {
                    $data['rx'] .= '<strong>' . $rx_v . ': </strong><br>';
                    $data['rx'] .= nl2br($rxInfo->{$rx_k});
                    if ($rx_k == 'rx_immunizations') {
                        $data['rx'] .= 'CDC Vaccine Information Sheets given for each immunization and consent obtained.<br />';
                    }
                    $data['rx'] .= '<br /><br />';
                }
            }
            $data['rx'] .= '</p>';
        }
        $planInfo = DB::table('plan')->where('eid', '=', $eid)->first();
        if ($planInfo) {
            $plan_arr = $this->array_plan();
            $data['plan'] = '<br><h4>Plan:</h4><p class="view">';
            foreach ($plan_arr as $plan_k => $plan_v) {
                if ($planInfo->{$plan_k} !== '' && $planInfo->{$plan_k} !== null) {
                    $data['plan'] .= '<strong>' . $plan_v . ': </strong>';
                    $data['plan'] .= nl2br($planInfo->{$plan_k});
                    if ($plan_k == 'duration') {
                        $data['plan'] .= '  minutes';
                    }
                    $data['plan'] .= '<br /><br />';
                }
            }
            $data['plan'] .= '</p>';
        }
        $billing_query = DB::table('billing_core')->where('eid', '=', $eid)->get();
        if ($billing_query->count()) {
            $data['billing'] = '<p class="view">';
            $billing_count = 0;
            foreach ($billing_query as $billing_row) {
                if ($billing_count > 0) {
                    $data['billing'] .= ',' . $billing_row->cpt;
                } else {
                    $data['billing'] .= '<strong>CPT Codes: </strong>';
                    $data['billing'] .= $billing_row->cpt;
                }
                $billing_count++;
            }
            if ($encounterInfo->bill_complex != '') {
                $data['billing'] .= '<br><strong>Medical Complexity: </strong>';
                $data['billing'] .= nl2br($encounterInfo->bill_complex);
                $data['billing'] .= '<br /><br />';
            }
            $data['billing'] .= '</p>';
        }
        if ($encounterInfo->encounter_signed == 'No') {
            $data['status']    = 'Draft';
        } else {
            $data['status'] = 'Signed on ' . date('F jS, Y', $this->human_to_unix($encounterInfo->date_signed)) . '.';
        }
        if ($modal == true) {
            if ($addendum == true) {
                $data['addendum'] = true;
            } else {
                $data['addendum'] = false;
            }
            return view('encounter', $data);
        } else {
            return view('encounter', $data);
        }
    }

    protected function fax_document($pid, $type, $coverpage, $filename, $file_original, $faxnumber, $faxrecipient, $job_id, $sendnow)
    {
        $demo_row = DB::table('demographics')->where('pid', '=', $pid)->first();
        if ($job_id == '') {
            $fax_data = [
                'user' => Session::get('displayname'),
                'faxsubject' => $type . ' for ' . $demo_row->firstname . ' ' . $demo_row->lastname,
                'faxcoverpage' => $coverpage,
                'practice_id' => Session::get('practice_id')
            ];
            $job_id = DB::table('sendfax')->insertGetId($fax_data);
            $this->audit('Add');
            $fax_directory = Session::get('documents_dir') . 'sentfax/' . $job_id;
            mkdir($fax_directory, 0777);
        }
        $filename_parts = explode("/", $filename);
        $fax_filename = $fax_directory . "/" . end($filename_parts);
        copy($filename, $fax_filename);
        if ($file_original == '') {
            $file_original = $type . ' for ' . $demo_row->firstname . ' ' . $demo_row->lastname;
        }
        $pages_data = [
            'file' => $fax_filename,
            'file_original' => $file_original,
            'file_size' => File::size($fax_filename),
            'pagecount' => $this->pagecount($fax_filename),
            'job_id' => $job_id
        ];
        DB::table('pages')->insert($pages_data);
        $this->audit('Add');
        if ($sendnow == "yes") {
            $message = $this->send_fax($job_id, $faxnumber, $faxrecipient);
        } else {
            $message = $job_id;
        }
        return $message;
    }

    protected function fhir_display($result, $type, $data)
    {
        $title_array = $this->fhir_resources();
        $gender_arr = $this->array_gender();
        if ($type == 'Patient') {
            $data['content'] = '<div class="alert alert-success">';
            $data['content'] .= '<strong>Name:</strong> ' . $result['name'][0]['given'][0] . ' ' . $result['name'][0]['family'][0];
            $data['content'] .= '<br><strong>Date of Birth:</strong> ' . date('Y-m-d', strtotime($result['birthDate']));
            if (in_array(strtolower(substr($result['gender'],0,1)), $gender_arr)) {
                $data['content'] .= '<br><strong>Gender:</strong> ' . $gender_arr[strtolower(substr($result['gender'],0,1))];
            } else {
                $data['content'] .= '<br><strong>Gender:</strong> ' . $result['gender'];
            }
            $data['content'] .= '</div>';
            $data['content'] .= '<div class="list-group">';
            foreach ($title_array as $title_k=>$title_v) {
                if ($title_k !== 'Patient') {
                    $data['content'] .= '<a href="' . route('fhir_connect_display', [$title_k]) . '" class="list-group-item"><i class="fa ' . $title_v['icon'] . ' fa-fw"></i><span style="margin:10px;">' . $title_v['name'] . '</span></a>';
                }
            }
            $data['content'] .= '</div>';
        } else {
            $query = DB::table($title_array[$type]['table'])->where('pid', '=', Session::get('pid'))->orderBy($title_array[$type]['order'], 'asc');
            if ($type == 'Condition') {
                $query->where('issue_date_inactive', '=', '0000-00-00 00:00:00');
            }
            if ($type == 'MedicationStatement') {
                $query->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00');
            }
            if ($type == 'Immunization') {
                $query->orderBy('imm_sequence', 'asc');
            }
            if ($type == 'AllergyIntolerance') {
                $query->where('allergies_date_inactive', '=', '0000-00-00 00:00:00');
            }
            $result1 = $query->get();
            $list_array = [];
            if ($result1->count()) {
                $edit = $this->access_level('7');
                $columns = Schema::getColumnListing($title_array[$type]['table']);
                $row_index = $columns[0];
                if ($type == 'Condition') {
                    foreach($result1 as $row1) {
                        $arr = [];
                        $arr['label'] = $row1->issue;
                        if ($edit == true) {
                            $arr['edit'] = route('chart_form', ['rx_list', $row_index, $row1->$row_index]);
                        }
                        $list_array[] = $arr;
                    }
                }
                if ($type == 'MedicationStatement') {
                    foreach($result1 as $row1) {
                        $arr = [];
                        if ($row1->rxl_sig == '') {
                            $arr['label'] = $row1->rxl_medication . ' ' . $row1->rxl_dosage . ' ' . $row1->rxl_dosage_unit . ', ' . $row1->rxl_instructions . ' for ' . $row1->rxl_reason;
                        } else {
                            $arr['label'] = $row1->rxl_medication . ' ' . $row1->rxl_dosage . ' ' . $row1->rxl_dosage_unit . ', ' . $row1->rxl_sig . ', ' . $row1->rxl_route . ', ' . $row1->rxl_frequency . ' for ' . $row1->rxl_reason;
                        }
                        if ($edit == true) {
                            $arr['edit'] = route('chart_form', ['rx_list', $row_index, $row1->$row_index]);
                        }
                        $list_array[] = $arr;
                    }
                }
                if ($type == 'Immunization') {
                    $seq_array = [
                        '1' => ', first',
                        '2' => ', second',
                        '3' => ', third',
                        '4' => ', fourth',
                        '5' => ', fifth'
                    ];
                    foreach ($result1 as $row1) {
                        $arr = [];
                        $arr['label'] = $row1->imm_immunization;
                        if (isset($row1->imm_sequence)) {
                            if (isset($seq_array[$row1->imm_sequence])) {
                                $arr['label'] = $row1->imm_immunization . $seq_array[$row1->imm_sequence];
                            }
                        }
                        if ($edit == true) {
                            $arr['edit'] = route('chart_form', ['rx_list', $row_index, $row1->$row_index]);
                        }
                        $list_array[] = $arr;
                    }
                }
                if ($type == 'AllergyIntolerance') {
                    foreach ($result1 as $row1) {
                        $arr = [];
                        $arr['label'] = $row1->allergies_med . ' - ' . $row1->allergies_reaction;
                        if ($edit == true) {
                            $arr['edit'] = route('chart_form', ['rx_list', $row_index, $row1->$row_index]);
                        }
                        $list_array[] = $arr;
                    }
                }
            }
            if ($type == 'Condition') {
                if ($result['total'] > 0) {
                    foreach ($result['entry'] as $row2) {
                        if (isset($row2['resource']['clinicalStatus'])) {
                            if ($row2['resource']['clinicalStatus'] == 'active') {
                                foreach($row2['resource']['code']['coding'] as $code) {
                                    if ($code['system'] !== "http://snomed.info/sct") {
                                        $icd = (string) $code['code'];
                                        $icd_desc = $this->icd_search($icd);
                                        if ($icd_desc == '') {
                                            $icd_desc = (string) $code['display'];
                                        }
                                        $arr = [];
                                        $arr['label'] = $icd_desc;
                                        $arr['label_class'] = 'nosh-ccda-list';
                                        $arr['danger'] = true;
                                        $arr['label_data_arr'] = [
                                            'data-nosh-type' => 'issues',
                                            'data-nosh-name' => $icd_desc,
                                            'data-nosh-code' => $icd,
                                            'data-nosh-date' => (string) $row2['resource']['onsetDateTime'],
                                            'data-nosh-from' => Session::get('fhir_name')
                                        ];
                                        $list_array[] = $arr;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($type == 'MedicationStatement') {
                if ($result['total'] > 0) {
                    foreach ($result['entry'] as $row2) {
                        if (isset($row2['resource']['status'])) {
                            if ($row2['resource']['status'] == 'active') {
                                $arr = [];
                                $arr['label'] = (string) $row2['resource']['medicationCodeableConcept']['text'];
                                $arr['label_class'] = 'nosh-ccda-list';
                                $arr['danger'] = true;
                                $rx_date = [date('Y-m-d')];
                                if (isset($row2['resource']['effectivePeriod']['start'])) {
                                    $rx_date = explode('T', $row2['resource']['effectivePeriod']['start']);
                                }
                                $rx_norm = [
                                    'name' => (string) $row2['resource']['medicationCodeableConcept']['text'],
                                    'dosage' => '',
                                    'dosage_unit' => '',
                                    'ndcid' => ''
                                ];
                                if (isset($row2['resource']['medicationCodeableConcept']['coding'][0]['system'])) {
                                    if ($row2['resource']['medicationCodeableConcept']['coding'][0]['system'] == 'http://www.nlm.nih.gov/research/umls/rxnorm') {
                                        $rx_norm = $this->rxnorm_search1($row2['resource']['medicationCodeableConcept']['coding'][0]['code']);
                                    }
                                }
                                $reason = '';
                                if (isset($row2['resource']['reasonCode'][0]['coding'][0]['display'])) {
                                    $reason = $row2['resource']['reasonCode'][0]['coding'][0]['display'];
                                }
                                $route = '';
                                if (isset($row2['resource']['dosage'][0]['route']['coding'][0])) {
                                    if ($row2['resource']['dosage'][0]['route']['coding'][0]['system'] == 'http://snomed.info/sct') {
                                        $yaml = File::get(resource_path() . '/routes.yaml');
                                        $formatter = Formatter::make($yaml, Formatter::YAML);
                                        $route_arr = $formatter->toArray();
                                        $q = $row2['resource']['dosage'][0]['route']['coding'][0]['code'];
                                        $route_result = array_where($route_arr, function($value, $key) use ($q) {
                                            if (stripos($value['code'], $q) !== false) {
                                                return true;
                                            }
                                        });
                                        foreach ($route_result as $route_row) {
                                            $route = $route_row['desc'];
                                        }
                                    }
                                }
                                $administration = '';
                                if (isset($row2['resource']['dosage'][0]['text'])) {
                                    $administration = $row2['resource']['dosage'][0]['text'];
                                }
                                $arr['label_data_arr'] = [
                                    'data-nosh-type' => 'rx_list',
                                    'data-nosh-name' => $rx_norm['name'],
                                    'data-nosh-code' => $rx_norm['ndcid'],
                                    'data-nosh-dosage' => $rx_norm['dosage'],
                                    'data-nosh-dosage-unit' => $rx_norm['dosage_unit'],
                                    'data-nosh-route' => $route,
                                    'data-nosh-reason' => $reason,
                                    'data-nosh-date' => $rx_date[0],
                                    'data-nosh-administration' => $administration,
                                    'data-nosh-from' => Session::get('fhir_name')
                                ];
                                $list_array[] = $arr;
                            }
                        }
                    }
                }
            }
            if ($type == 'Immunization') {
                if ($result['total'] > 0) {
                    foreach ($result['entry'] as $row2) {
                        if (isset($row2['resource']['status'])) {
                            if ($row2['resource']['status'] == 'completed') {
                                $imm_immunization = $row2['resource']['vaccineCode']['text'];
                                $arr = [];
                                $arr['label'] = $imm_immunization;
                                $arr['label_class'] = 'nosh-ccda-list';
                                $arr['danger'] = true;
                                $imm_date = explode('T', $row2['resource']['date']);
                                $imm_code = '';
                                if (isset($row2['resource']['vaccineCode']['coding'][0]['code'])) {
                                    $imm_code = $row2['resource']['vaccineCode']['coding'][0]['code'];
                                }
                                $arr['label_data_arr'] = [
                                    'data-nosh-type' => 'immunizations',
                                    'data-nosh-name' =>  $imm_immunization,
                                    'data-nosh-route' => '',
                                    'data-nosh-date' => $imm_date[0],
                                    'data-nosh-code' => $imm_code,
                                    'data-nosh-sequence' => '',
                                    'data-nosh-from' => Session::get('fhir_name')
                                ];
                                $list_array[] = $arr;
                            }
                        }
                    }
                }
            }
            if ($type == 'AllergyIntolerance') {
                if ($result['total'] > 0) {
                    foreach ($result['entry'] as $row2) {
                        if (isset($row2['resource']['status'])) {
                            if ($row2['resource']['status'] == 'confirmed') {
                                $arr = [];
                                $arr['label'] = (string) $row2['resource']['substance']['text'];
                                $arr['label_class'] = 'nosh-ccda-list';
                                $arr['danger'] = true;
                                $allergy_date = explode('T', $row2['resource']['recordedDate']);
                                $reaction = '';
                                if (isset($row2['resource']['reaction'])) {
                                    $reaction = (string) $row2['resource']['reaction'][0]['manifestation'][0]['text'];
                                }
                                $arr['label_data_arr'] = [
                                    'data-nosh-type' => 'allergies',
                                    'data-nosh-name' => (string) $row2['resource']['substance']['text'],
                                    'data-nosh-reaction' => $reaction,
                                    'data-nosh-date' => $allergy_date[0],
                                    'data-nosh-from' => Session::get('fhir_name')
                                ];
                                $list_array[] = $arr;
                            }
                        }
                    }
                }
            }
            $data['content'] = '<div class="alert alert-success">';
            $data['content'] .= '<h5>Rows in red come from the patient portal and need to be reconciled.  Click on the row to accept.</h5>';
            $data['content'] .= '</div>';
            $data['content'] .= $this->result_build($list_array, $type . '_reconcile_list');
            // $data['content'] .= $result;
        }
        return $data;
    }

    protected function fhir_metadata($url)
    {
        $url .= 'metadata';
        $ch = curl_init();
        $return = [];
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
        $content_type = 'application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Accept: {$content_type}"
        ]);
        $metadata_json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($httpCode == 200) {
            $metadata = json_decode($metadata_json, true);
            // Get security URLs
            foreach ($metadata['rest'][0]['security']['extension'][0]['extension'] as $security_row) {
                if ($security_row['url'] == 'authorize') {
                    $return['auth_url'] = $security_row['valueUri'];
                }
                if ($security_row['url'] == 'token') {
                    $return['token_url'] = $security_row['valueUri'];
                }
            }
            // Get resources
            foreach ($metadata['rest'][0]['resource'] as $resource_row) {
                $return['resources'][] = $resource_row['type'];
            }
        } else {
            $return['error'] = true;
        }
        return $return;
    }

    protected function fhir_request($url, $response_header=false, $token='', $epic=false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if ($response_header == true) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
        } else {
            curl_setopt($ch, CURLOPT_HEADER, 0);
        }
        if ($token != '') {
            if ($epic == true) {
                $content_type = 'application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Accept: {$content_type}",
                    'Authorization: Bearer ' . $token
                ]);
            } else {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization: Bearer ' . $token
                ));
            }
        }
        $output = curl_exec($ch);
        // if ($response_header == true) {
            //$info = curl_getinfo($ch);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($output, 0, $header_size);
            $headers = $this->get_headers_from_curl_response($header);
            if (empty($headers)) {
                $result = json_decode($output, true);
            } else {
                $header_val_arr = explode(', ', $headers[0]['WWW-Authenticate']);
                $header_val_arr1 = explode('=', $header_val_arr[1]);
                $body = substr($output, $header_size);
                $result = json_decode($body, true);
                $result['as_uri'] = trim(str_replace('"', '', $header_val_arr1[1]));
            }
            //$result['error'] = $output;
        // } else {
        //    $result = json_decode($output, true);
        // }
        curl_close($ch);
        return $result;
    }

    protected function fhir_resources()
    {
        $return = [
            'Condition' => [
                'icon' => 'fa-bars',
                'name' => 'Conditions',
                'table' => 'issues',
                'order' => 'issue'
            ],
            'MedicationStatement' => [
                'icon' => 'fa-eyedropper',
                'name' => 'Medications',
                'table' => 'rx_list',
                'order' => 'rxl_medication'
            ],
            'AllergyIntolerance' => [
                'icon' => 'fa-exclamation-triangle',
                'name' => 'Allergies',
                'table' => 'allergies',
                'order' => 'allergies_med'
            ],
            'Immunization' => [
                'icon' => 'fa-magic',
                'name' => 'Immunizations',
                'table' => 'immunizations',
                'order' => 'imm_immunization'
            ],
            'Patient' => [
                'icon' => 'fa-user',
                'name' => 'Patient Information',
                'table' => 'demographics',
                'order' => 'pid'
            ],
            'Encounter' => [
                'icon' => 'fa-stethoscope',
                'name' => 'Encounters',
                'table' => 'encounters',
                'order' => 'encounter_cc'
            ],
            'FamilyHistory' => [
                'icon' => 'fa-sitemap',
                'name' => 'Family History',
                'table' => 'other_history',
                'order' => 'oh_fh'
            ],
            'Binary' => [
                'icon' => 'fa-file-text',
                'name' => 'Documents',
                'table' => 'documents',
                'order' => 'documents_desc'
            ],
            'Observation' => [
                'icon' => 'fa-flask',
                'name' => 'Observations',
                'table' => 'tests',
                'order' => 'test_name'
            ]
        ];
        return $return;
    }

    protected function fhir_response($data)
    {
        $code_arr = [
            'structure' => 'Structural Issue',
            'required' => 'Required element missing',
            'value' => 'Element value invalid',
            'invariant' => 'Validation rule failed',
            'security' => 'Security Problem',
            'login' => 'Login Required',
            'unknown' => 'Unknown User',
            'expired' => 'Session Expired',
            'forbidden' => 'Forbdden',
            'supressed' => 'Information Suppressed',
            'processing' => 'Processing Failure',
            'not-supported' => 'Content not suported',
            'duplicate' => 'Duplicate',
            'not-found' => 'Not Found',
            'too-long' => 'Content Too Long',
            'code-invalid' => 'Invalid Code',
            'extension' => 'Unacceptable Extension',
            'too-costly' => 'Operation Too Costly',
            'business-rule' => 'Business Rule Violation',
            'conflict' => 'Edit Version Conflict',
            'incomplete' => 'Incomplete Results',
            'transient' => 'Transient Issue',
            'lock-error' => 'Lock Error',
            'no-store' => 'No Store Available',
            'exception' => 'Exception',
            'timeout' => 'Timeout',
            'throttled' => 'Throttled',
            'informational' => 'Informational Note'
        ];
        if ($data == 'OK') {
            $text = [
                'status' => 'additional',
                'div' => "<div><p>All OK</p></div>"
            ];
            $issue[] = [
                'severity' => 'information',
                'code' => 'informational',
                'details' => [
                    'text' => 'All OK'
                ]
            ];
        } else {
            $text = [
                'status' => 'generated',
                'div' => "<div><p>" . $code_arr[$data] . "</p></div>"
            ];
            $issue[] = [
                'severity' => 'error',
                'code' => $data,
                'details' => [
                    'text' => $code_arr[$data]
                ]
            ];
        }
        $return = [
            'resourceType' => 'OperationOutcome',
            'id' => $this->gen_uuid(),
            'text' => $text,
            'issue' => $issue
        ];
        return $return;
    }

    protected function fhir_scopes_confidentiality()
    {
        $arr = [
            'conf/N' => 'Normal confidentiality',
            'conf/R' => 'Restricted confidentiality',
            'conf/V' => 'Very Restricted confidentiality'
        ];
        return $arr;
    }

    protected function fhir_scopes_sensitivities()
    {
        $arr = [
            'sens/ETH' => 'Substance abuse',
            'sens/PSY' => 'Psychiatry',
            'sens/GDIS' => 'Genetic disease',
            'sens/HIV' => 'HIV/AIDS',
            'sens/SCA' => 'Sickle cell anemia',
            'sens/SOC' => 'Social services',
            'sens/SDV' => 'Sexual assault, abuse, or domestic violence',
            'sens/SEX' => 'Sexuality and reproductive health',
            'sens/STD' => 'Sexually transmitted disease',
            'sens/DEMO' => 'All demographic information',
            'sens/DOB' => 'Date of birth',
            'sens/GENDER' => 'Gender and sexual orientation',
            'sens/LIVARG' => 'Living arrangement',
            'sens/MARST' => 'Marital status',
            'sens/RACE' => 'Race',
            'sens/REL' => 'Religion',
            'sens/B' => 'Business information',
            'sens/EMPL' => 'Employer',
            'sens/LOCIS' => 'Location',
            'sens/SSP' => 'Sensitive service provider',
            'sens/ADOL' => 'Adolescent',
            'sens/CEL' => 'Celebrity',
            'sens/DIAG' => 'Diagnosis',
            'sens/DRGIS' => 'Drug information',
            'sens/EMP' => 'Employee'
        ];
        return $arr;
    }

    /**
    * Form build
    * @param array  $form_array -
    * $form_array1 = [
    *    'form_id' => 'practice_choose',
    *    'action' => URL::to('practice_choose'),
    *    'items' => [
    *        [
    *            'name' => 'practice_npi_select',
    *            'label' => 'Practice NPI',
    *            'type' => 'select',
    *            'required' => true,
    *            'typeahead' => true,
    *            'value' => '',
    *            'default_value' => '',
    *            'select_items' => $form_select_array,
    *            'phone' => true,
    *            'class' => ''
    *       ],
    *       [
    *            'name' => 'my_textarea',
    *            'label' => 'Practice NPI',
    *            'type' => 'textarea',
    *            'textarea_short' => true,
    *            'typeahead' => true,
    *            'value' => '',
    *            'default_value' => '',
    *            'class' => ''
    *       ],
    *       [
    *            'name' => 'practice_npi_select1',
    *            'label' => 'Practice NPI1',
    *            'type' => 'select',
    *            'required' => true,
    *            'typeahead' => true,
    *            'value' => '',
    *            'default_value' => '',
    *            'multiple' => true,
    *            'selectpicker' => true,
    *            'tagsinput' => '',
    *            'select_items' => $form_select_array
    *        ]
    *    ],
    *    'origin' => 'previous URL',
    *    'save_button_label' => 'Select Practice',
    *    'intro' => 'html for intro',
    *    'add_save_button' => [
    *         'action' => 'Label of Action',
    *         'action2' => 'Label2 of Action2'
    *     ]
    * ];
    * @param int $id - Item key in database
    * @return Response
    */
    protected function form_build($form_array, $edit=false, $type='')
    {
        $return = '<form id="' . $form_array['form_id'] . '" class="form-horizontal nosh-form" role="form" method="POST" action="' . $form_array['action'] . '">';
        $return .= csrf_field();
        if (isset($form_array['intro'])) {
            $return .= $form_array['intro'];
        }
        $i = 0;
        foreach($form_array['items'] as $item_k => $item) {
            $item_attr = [];
            $item_attr['class'] = 'form-control';
            if (isset($item['required']) && $item['required'] == true) {
                $item_attr[] = 'required';
            }
            if (isset($item['readonly']) && $item['readonly'] == true) {
                $item_attr[] = 'readonly';
            }
            if (isset($item['placeholder'])) {
                $item_attr['placeholder'] = $item['placeholder'];
            }
            if (isset($item['multiple']) && $item['multiple'] == true) {
                $item_attr[] = 'multiple';
                if (isset($item['selectpicker']) && $item['selectpicker'] == true) {
                    $item_attr['class'] .= ' selectpicker';
                }
                if (isset($item['tagsinput'])) {
                    $item_attr['class'] .= ' tagsinput_select';
                    $item_attr['data-nosh-tagsinput'] = $item['tagsinput'];
                    $item_attr['id'] = str_replace('[]', '', $item['name']);
                }
            }
            if (isset($item['phone']) && $item['phone'] == true) {
                $item_attr['class'] .= ' nosh_phone';
            }
            if (isset($item['textarea_short']) && $item['textarea_short'] == true) {
                $item_attr['class'] .= ' nosh_textarea_short';
            }
            if (isset($item['typeahead'])) {
                $item_attr['autocomplete'] = 'off';
                $item_attr['data-nosh-typeahead'] = $item['typeahead'];
                $item_attr['class'] .= ' nosh-typeahead';
                $item_attr['data-provide'] = 'typeahead';
            }
            if (isset($item['datetime'])) {
                $item_attr['class'] .= ' nosh-datetime';
            }
            if (isset($item['time'])) {
                $item_attr['class'] .= ' nosh-time';
            }
            $default_value = null;
            if (isset($item['default_value'])) {
                $default_value = $item['default_value'];
            }
            if ($item['type'] == 'hidden') {
                $item_attr['id'] = $item['name'];
                $return .= Form::{$item['type']}($item['name'], $default_value, $item_attr);
            } elseif ($item['type'] == 'checkbox' || $item['type'] == 'radio'){
                $return .= '<div class="form-group">';
                if (isset($item['section_items'])) {
                    $return .= '<label class="col-md-3 control-label">' . $item['label'] . '</label>';
                    $return .= '<div class="col-md-8"><div class="' . $item['type'] . '">';
                    foreach ($item['section_items'] as $section_item_k => $section_item_v) {
                        $return .= '<label class="' . $item['type'] . '-inline">';
                        if (isset($item['value'])) {
                            if ($item['value'] == $section_item_k) {
                                $return .= Form::{$item['type']}($item['name'], $section_item_k, true);
                            } else {
                                $return .= Form::{$item['type']}($item['name'], $section_item_k);
                            }
                        } else {
                            $return .= Form::{$item['type']}($item['name'], $section_item_k);
                        }
                        $return .= $section_item_v .'</label>';
                    }
                    $return .= '</div></div>';
                    if ($edit == true) {
                        $return .= '<div class="col-md-1">';
                        $return .= '<a href="' . route('configure_form_edit', [$type, $item_k]) . '" type="button" class="btn fa-btn" data-toggle="tooltip" title="Edit Item"><i class="fa fa-pencil fa-lg"></i></a>';
                        $return .= '<a href="' . route('configure_form_remove', [$type, $item_k]) . '" type="button" class="btn fa-btn nosh-delete" data-toggle="tooltip" title="Delete Item"><i class="fa fa-trash fa-lg"></i></a>';
                        $return .= '</div>';
                    }
                    $return .= '</div>';
                } else {
                    $return .= '<div class="col-md-8 col-md-offset-3"><div class="' . $item['type'] . '"><label>';
                    if ($item['value'] == $default_value) {
                        $return .= Form::{$item['type']}($item['name'], $item['value'], true);
                    } else {
                        $return .= Form::{$item['type']}($item['name'], $item['value']);
                    }
                    $return .= $item['label'] . '</label></div></div></div>';
                }
            } else {
                $return .= '<div class="form-group';
                if (isset($item['class'])) {
                    $return .= ' ' . $item['class'];
                }
                $return .= '">';
                $return .= Form::label($item['name'], $item['label'], ['class' => 'col-md-3 control-label']);
                $return .= '<div class="col-md-8">';
                if ($item['type'] == 'password') {
                    $return .= Form::{$item['type']}($item['name'], $item_attr);
                } elseif ($item['type'] == 'select') {
                    $return .= Form::{$item['type']}($item['name'], $item['select_items'], $default_value, $item_attr);
                } else {
                    $return .= Form::{$item['type']}($item['name'], $default_value, $item_attr);
                }
                $return .= '</div>';
                if ($edit == true) {
                    $return .= '<div class="col-md-1">';
                    $return .= '<a href="' . route('configure_form_edit', [$type, $item_k]) . '" type="button" class="btn fa-btn" data-toggle="tooltip" title="Edit Item"><i class="fa fa-pencil fa-lg"></i></a>';
                    $return .= '<a href="' . route('configure_form_remove', [$type, $item_k]) . '" type="button" class="btn fa-btn nosh-delete" data-toggle="tooltip" title="Delete Item"><i class="fa fa-trash fa-lg"></i></a>';
                    $return .= '</div>';
                }
                $return .= '</div>';
            }
            $i++;
        }
        if (isset($form_array['outro'])) {
            $return .= $form_array['outro'];
        }
        $save_button_label = 'Save';
        if (isset($form_array['save_button_label'])) {
            $save_button_label = $form_array['save_button_label'];
        }
        if (isset($form_array['add_save_button'])) {
            $return .= '<div class="form-group"><div class="col-md-8 col-md-offset-2">';
        } else {
            $return .= '<div class="form-group"><div class="col-md-6 col-md-offset-4">';
        }
        $return .= '<button type="submit" class="btn btn-success nosh-button-submit" style="margin:10px"><i class="fa fa-btn fa-save"></i> ' . $save_button_label . '</button>';
        if (isset($form_array['add_save_button'])) {
            foreach ($form_array['add_save_button'] as $add_save_button_k => $add_save_button_v) {
                $return .= '<button type="submit" class="btn btn-success nosh-button-submit" style="margin:10px" name="submit" value="' . $add_save_button_k . '"><i class="fa fa-btn fa-save"></i> ' . $add_save_button_v . '</button>';
            }
        }
        if (isset($form_array['remove_cancel']) == false) {
            if (isset($form_array['origin'])) {
                $return .= '<a href="' . $form_array['origin'] . '" class="btn btn-danger" style="margin:10px"><i class="fa fa-btn fa-ban"></i> Cancel</a>';
            } else {
                $return .= '<a href="' . Session::get('last_page') . '" class="btn btn-danger" style="margin:10px"><i class="fa fa-btn fa-ban"></i> Cancel</a>';
            }
        }
        $return .= '</div></div>';
        $return .= '</form>';
        return $return;
    }

    protected function form_addressbook($result, $table, $id, $subtype)
    {
        $electronic_order_arr = [
            '' => 'Select Electronic Order Interface',
            'PeaceHealth' => 'PeaceHealth Labs'
        ];
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        if ($id == '0') {
            $data = [
                'displayname' => null,
                'lastname' => null,
                'firstname' => null,
                'prefix' => null,
                'suffix' => null,
                'facility' => null,
                'street_address1' => null,
                'street_address2' => null,
                'country' => $practice->country,
                'city' => null,
                'state' => null,
                'zip' => null,
                'phone' => null,
                'fax' => null,
                'comments' => null,
                'ordering_id' => null,
                'specialty' => null,
                'electronic_order' => null,
                'npi' => null
            ];
        } else {
            $data = [
                'displayname' => $result->displayname,
                'lastname' => $result->lastname,
                'firstname' => $result->firstname,
                'prefix' => $result->prefix,
                'suffix' => $result->suffix,
                'facility' => $result->facility,
                'street_address1' => $result->street_address1,
                'street_address2' => $result->street_address2,
                'country' => $result->country,
                'city' => $result->city,
                'state' => $result->state,
                'zip' => $result->zip,
                'phone' => $result->phone,
                'fax' => $result->fax,
                'comments' => $result->comments,
                'ordering_id' => $result->ordering_id,
                'specialty' => $result->specialty,
                'electronic_order' => $result->electronic_order,
                'npi' => $result->npi
            ];
        }
        if ($subtype == 'faxonly') {
            $items[] = [
                'name' => 'displayname',
                'type' => 'text',
                'readonly' => true,
                'required' => true,
                'default_value' => $data['displayname']
            ];
            $items[] = [
                'name' => 'fax',
                'label' => 'Fax',
                'type' => 'text',
                'phone' => true,
                'required' => true,
                'default_value' => $data['fax']
            ];
        } else {
            $items[] = [
                'name' => 'displayname',
                'type' => 'hidden',
                'required' => true,
                'default_value' => $data['displayname']
            ];
            if ($subtype == 'Laboratory' || $subtype == 'Radiology' || $subtype == 'Cardiopulmonary' || $subtype == 'Pharmacy' || $subtype == 'Insurance') {
                $data['specialty'] = $subtype;
                $items[] = [
                    'name' => 'facility',
                    'label' => 'Facility',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $data['facility']
                ];
                $items[] = [
                    'name' => 'specialty',
                    'type' => 'hidden',
                    'required' => true,
                    'default_value' => $data['specialty']
                ];
            } else {
                $items[] = [
                    'name' => 'firstname',
                    'label' => 'First Name',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $data['firstname']
                ];
                $items[] = [
                    'name' => 'lastname',
                    'label' => 'Last Name',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $data['lastname']
                ];
                $items[] = [
                    'name' => 'prefix',
                    'label' => 'Prefix',
                    'type' => 'text',
                    'default_value' => $data['prefix']
                ];
                $items[] = [
                    'name' => 'suffix',
                    'label' => 'Suffix',
                    'type' => 'text',
                    'default_value' => $data['suffix']
                ];
                $items[] = [
                    'name' => 'facility',
                    'label' => 'Facility',
                    'type' => 'text',
                    'default_value' => $data['facility']
                ];
                $items[] = [
                    'name' => 'specialty',
                    'label' => 'Specialty',
                    'type' => 'text',
                    'required' => true,
                    'typeahead' => route('typeahead', ['table' => $table, 'column' => 'specialty']),
                    'default_value' => $data['specialty']
                ];
                $items[] = [
                    'name' => 'npi',
                    'label' => 'NPI',
                    'type' => 'text',
                    'default_value' => $data['npi']
                ];
            }
            $items[] = [
                'name' => 'street_address1',
                'label' => 'Address',
                'type' => 'text',
                'required' => true,
                'default_value' => $data['street_address1']
            ];
            $items[] = [
                'name' => 'street_address2',
                'label' => 'Address 2',
                'type' => 'text',
                'default_value' => $data['street_address2']
            ];
            $items[] = [
                'name' => 'country',
                'label' => 'Country',
                'type' => 'select',
                'select_items' => $this->array_country(),
                'default_value' => $data['country'],
                'class' => 'country'
            ];
            $items[] = [
                'name' => 'city',
                'label' => 'City',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'city']),
                'default_value' => $data['city']
            ];
            $items[] = [
                'name' => 'state',
                'label' => 'State',
                'type' => 'select',
                'select_items' => $this->array_states($data['country']),
                'default_value' => $data['state'],
                'class' => 'state'
            ];
            $items[] = [
                'name' => 'zip',
                'label' => 'Zip',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'zip']),
                'default_value' => $data['zip']
            ];
            $items[] = [
                'name' => 'phone',
                'label' => 'Phone',
                'type' => 'text',
                'phone' => true,
                'default_value' => $data['phone']
            ];
            $items[] = [
                'name' => 'fax',
                'label' => 'Fax',
                'type' => 'text',
                'phone' => true,
                'default_value' => $data['fax']
            ];
            $items[] = [
                'name' => 'comments',
                'label' => 'Comments',
                'type' => 'text',
                'default_value' => $data['comments']
            ];
            if ($subtype == 'Laboratory' || $subtype == 'Radiology' || $subtype == 'Cardiopulmonary') {
                $items[] = [
                    'name' => 'ordering_id',
                    'label' => 'Provider/Clinic Identity',
                    'type' => 'text',
                    'default_value' => $data['ordering_id']
                ];
                $items[] = [
                    'name' => 'electronic_order',
                    'label' => 'Electronic Order Interface',
                    'type' => 'select',
                    'select_items' => $electronic_order_arr,
                    'default_value' => $data['electronic_order']
                ];
            }
        }
        return $items;
    }

    protected function form_alerts($result, $table, $id, $subtype)
    {
        $portal_active = false;
        $patient = DB::table('demographics_relate')
            ->where('pid', '=', Session::get('pid'))
            ->where('practice_id', '=', Session::get('practice_id'))
            ->whereNotNull('id')
            ->first();
        if ($patient) {
            $portal_active = true;
        }
        $users_query = DB::table('users')
            ->where('group_id', '!=', '100')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->select('id', 'displayname')
            ->get();
        if ($users_query->count()) {
            foreach ($users_query as $users_row) {
                $users_arr[$users_row->id] = $users_row->displayname;
            }
        }
        if ($id == '0') {
            $alert = [
                'alert' => null,
                'alert_description' => null,
                'alert_date_active' => date('Y-m-d'),
                'alert_date_complete' => null,
                'alert_reason_not_complete' => null,
                'alert_provider' => Session::get('user_id'),
                'orders_id' => null,
                'pid' => Session::get('pid'),
                'practice_id' => Session::get('practice_id'),
                'alert_send_message' => null
            ];
        } else {
            $alert = [
                'alert' => $result->alert,
                'alert_description' => $result->alert_description,
                'alert_date_active' => date('Y-m-d', $this->human_to_unix($result->alert_date_active)),
                'alert_date_complete' => $result->alert_date_complete,
                'alert_reason_not_complete' => $result->alert_reason_not_complete,
                'alert_provider' => $result->alert_provider,
                'orders_id' => $result->orders_id,
                'pid' => $result->pid,
                'practice_id' => $result->practice_id,
                'alert_send_message' => $result->alert_send_message
            ];
        }
        if ($subtype == '') {
            $items[] = [
                'name' => 'alert',
                'label' => 'Alert',
                'type' => 'text',
                'required' => true,
                'default_value' => $alert['alert']
            ];
            $items[] = [
                'name' => 'alert_provider',
                'label' => 'User or Provider to Alert',
                'type' => 'select',
                'select_items' => $users_arr,
                'default_value' => $alert['alert_provider']
            ];
            $items[] = [
                'name' => 'alert_description',
                'label' => 'Description',
                'type' => 'textarea',
                'required' => true,
                'default_value' => $alert['alert_description']
            ];
            $items[] = [
                'name' => 'alert_date_active',
                'label' => 'Due Date',
                'type' => 'date',
                'required' => true,
                'default_value' => $alert['alert_date_active']
            ];
            if ($portal_active == true) {
                $items[] = [
                    'name' => 'alert_send_message',
                    'label' => 'Message to Patient about Alert',
                    'type' => 'select',
                    'select_items' => [
                        'n' => 'No',
                        'y' => 'Yes',
                        's' => 'Message Sent'
                    ],
                    'default_value' => $alert['alert_send_message']
                ];
            }
            $items[] = [
                'name' => 'practice_id',
                'type' => 'hidden',
                'default_value' => $alert['practice_id']
            ];
        }
        if ($subtype == 'incomplete') {
            $items[] = [
                'name' => 'alert_reason_not_complete',
                'label' => 'Reason',
                'type' => 'text',
                'required' => true,
                'default_value' => $alert['alert_reason_not_complete']
            ];
        }
        return $items;
    }

    protected function form_allergies($result, $table, $id, $subtype)
    {
        $severity_arr = [
            'mild' => 'Mild',
            'moderate' => 'Moderate',
            'severe' => 'Severe'
        ];
        $allergies_provider = null;
        $provider_id = null;
        if (Session::get('group_id') == '2') {
            $allergies_provider = Session::get('displayname');
            $provider_id = Session::get('user_id');
        } else {
            if ($id !== '0') {
                $allergies_provider = $result->allergies_provider;
                $provider_id = $result->provider_id;
            }
        }
        if ($id == '0') {
            $allergy = [
                'allergies_med' => null,
                'allergies_reaction' => null,
                'allergies_severity' => null,
                'meds_ndcid' => null,
                'allergies_date_active' => date('Y-m-d'),
                'allergies_date_inactive' => '',
                'allergies_provider' => $allergies_provider,
                'notes' => null,
                'label' => null,
                'provider_id' => $provider_id
            ];
            if (Session::has('ccda')) {
                $ccda = Session::get('ccda');
                Session::forget('ccda');
                $allergy['allergies_med'] = $ccda['name'];
                $allergy['allergies_reaction'] = $ccda['reaction'];
                $allergy['allergies_date_active'] = date('Y-m-d', $this->human_to_unix($ccda['date']));
                $allergy['meds_ndcid'] = $this->rxnorm_search($ccda['name']);
                if (isset($ccda['from'])) {
                    $allergy['notes'] = 'Obtained via FHIR from ' . $ccda['from'];
                }
            }
        } else {
            $label = [];
            if ($result->label !== '' || $result->label !== null)  {
                $label = explode(";", $result->label);
            }
            $allergy = [
                'allergies_med' => $result->allergies_med,
                'allergies_reaction' => $result->allergies_reaction,
                'allergies_severity' => $result->allergies_severity,
                'meds_ndcid' => $result->meds_ndcid,
                'allergies_date_active' => date('Y-m-d', $this->human_to_unix($result->allergies_date_active)),
                'allergies_provider' => $allergies_provider,
                'notes' => $result->notes,
                'label' => $label,
                'provider_id' => $provider_id
            ];
            if ($result->allergies_date_inactive == '0000-00-00 00:00:00') {
                $allergy['allergies_date_inactive'] = '';
            } else {
                $allergy['allergies_date_inactive'] = date('Y-m-d', $this->human_to_unix($result->allergies_date_inactive));
            }
            if ($result->meds_ndcid == '' || $result->meds_ndcid !== '') {
                $allergy['meds_ndcid'] = $this->rxnorm_search($result->allergies_med);
            }
        }
        $items[] = [
            'name' => 'allergies_med',
            'label' => 'Substance or Medication',
            'type' => 'text',
            'required' => true,
            'default_value' => $allergy['allergies_med']
        ];
        $items[] = [
            'name' => 'allergies_reaction',
            'label' => 'Reaction',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'allergies_reaction']),
            'default_value' => $allergy['allergies_reaction']
        ];
        $items[] = [
            'name' => 'allergies_severity',
            'label' => 'Severity',
            'type' => 'select',
            'select_items' => $severity_arr,
            'default_value' => $allergy['allergies_severity']
        ];
        $items[] = [
            'name' => 'meds_ndcid',
            'label' => 'RXNorm ID',
            'type' => 'text',
            'readonly' => true,
            'default_value' => $allergy['meds_ndcid']
        ];
        $items[] = [
            'name' => 'allergies_date_active',
            'label' => 'Date Active',
            'type' => 'date',
            'required' => true,
            'default_value' => $allergy['allergies_date_active']
        ];
        $items[] = [
            'name' => 'notes',
            'label' => 'Notes',
            'type' => 'textarea',
            'default_value' => $allergy['notes']
        ];
        $items[] = [
            'name' => 'label[]',
            'label' => 'Sensitive Label',
            'type' => 'select',
            'select_items' => $this->fhir_scopes_sensitivities(),
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $allergy['label']
        ];
        $items[] = [
            'name' => 'allergies_date_inactive',
            'type' => 'hidden',
            'default_value' => $allergy['allergies_date_inactive']
        ];
        $items[] = [
            'name' => 'allergies_provider',
            'type' => 'hidden',
            'required' => true,
            'default_value' => $allergy['allergies_provider']
        ];
        $items[] = [
            'name' => 'provider_id',
            'type' => 'hidden',
            'default_value' => $allergy['provider_id']
        ];
        return $items;
    }

    protected function form_billing_core($result, $table, $id, $subtype)
    {
        if ($id == '0') {
            $encounter = DB::table('encounters')->where('eid', '=', Session::get('eid_billing'))->first();
            $eid = Session::get('eid_billing');
            // if (Session::has('eid')) {
            //     $encounter = DB::table('encounters')->where('eid', '=', Session::get('eid'))->first();
            //     $eid = Session::get('eid');
            // }
            // if (Session::has('eid_billing')) {
            //     $encounter = DB::table('encounters')->where('eid', '=', Session::get('eid_billing'))->first();
            //     $eid = Session::get('eid_billing');
            // }
            $default_date = date('Y-m-d', $this->human_to_unix($encounter->encounter_DOS));
            $cpt = [
                'cpt' => null,
                'cpt_charge' => null,
                'unit' => '1',
                'modifier' => null,
                'dos_f' => $default_date,
                'dos_t' => $default_date,
                'icd_pointer' => [],
                'eid' => $eid,
                'practice_id' => Session::get('practice_id')
            ];
        } else {
            $icd_pointer = [];
            if ($result->icd_pointer !== '' || $result->icd_pointer !== null)  {
                $icd_pointer = str_split($result->icd_pointer);
            }
            $cpt = [
                'cpt' => $result->cpt,
                'cpt_charge' => $result->cpt_charge,
                'unit' => $result->unit,
                'modifier' => $result->modifier,
                'dos_f' => date('Y-m-d', $this->human_to_unix($result->dos_f)),
                'dos_t' => date('Y-m-d', $this->human_to_unix($result->dos_t)),
                'icd_pointer' => $icd_pointer,
                'eid' => $result->eid,
                'practice_id' => $result->practice_id
            ];
        }
        $items[] = [
            'name' => 'cpt',
            'label' => 'Procedure Code',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'cpt']),
            'default_value' => $cpt['cpt']
        ];
        $items[] = [
            'name' => 'cpt_charge',
            'label' => 'Procedure Charge',
            'type' => 'text',
            'required' => true,
            'default_value' => $cpt['cpt_charge']
        ];
        $items[] = [
            'name' => 'unit',
            'label' => 'Unit(s)',
            'type' => 'text',
            'required' => true,
            'default_value' => $cpt['unit']
        ];
        $items[] = [
            'name' => 'modifier',
            'label' => 'Modifier',
            'type' => 'select',
            'select_items' => $this->array_modifier(),
            'default_value' => $cpt['modifier']
        ];
        $items[] = [
            'name' => 'dos_f',
            'label' => 'Date of Service From',
            'type' => 'date',
            'required' => true,
            'default_value' => $cpt['dos_f']
        ];
        $items[] = [
            'name' => 'dos_t',
            'label' => 'Date of Service To',
            'type' => 'date',
            'required' => true,
            'default_value' => $cpt['dos_t']
        ];
        $items[] = [
            'name' => 'icd_pointer[]',
            'label' => 'Diagnosis Pointer',
            'type' => 'select',
            'select_items' => $this->array_assessment_billing($cpt['eid']),
            'required' => true,
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $cpt['icd_pointer']
        ];
        $items[] = [
            'name' => 'eid',
            'type' => 'hidden',
            'required' => true,
            'default_value' => $cpt['eid']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'required' => true,
            'default_value' => $cpt['practice_id']
        ];
        return $items;
    }

    protected function form_calendar($result, $table, $id, $subtype)
    {
        $providers_arr['0'] = 'All Providers';
        $providers_arr = $providers_arr + $this->array_providers();
        if ($id == '0') {
            $data = [
                'visit_type' => null,
                'duration' => null,
                'classname' => null,
                'active' => 'y',
                'provider_id' => null,
                'practice_id' => Session::get('practice_id')
            ];
        } else {
            $data = [
                'visit_type' => $result->visit_type,
                'duration' => $result->duration,
                'classname' => $result->classname,
                'active' => $result->active,
                'provider_id' => $result->provider_id,
                'practice_id' => $result->practice_id
            ];
        }
        $items[] = [
            'name' => 'visit_type',
            'label' => 'Visit Type',
            'type' => 'text',
            'required' => true,
            'default_value' => $data['visit_type']
        ];
        $items[] = [
            'name' => 'duration',
            'label' => 'Duration',
            'type' => 'select',
            'select_items' => $this->array_duration(),
            'required' => true,
            'default_value' => $data['duration']
        ];
        $items[] = [
            'name' => 'classname',
            'label' => 'Color',
            'type' => 'select',
            'select_items' => $this->array_color(),
            'required' => true,
            'default_value' => $data['classname']
        ];
        $items[] = [
            'name' => 'provider_id',
            'label' => 'Provider',
            'type' => 'select',
            'select_items' => $providers_arr,
            'required' => true,
            'default_value' => $data['provider_id']
        ];
        $items[] = [
            'name' => 'active',
            'type' => 'hidden',
            'default_value' => $data['active']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'default_value' => $data['practice_id']
        ];
        return $items;
    }

    protected function form_demographics($result, $table, $id, $subtype)
    {
        if ($subtype == 'name') {
            $race_arr = [];
            foreach ($this->array_race() as $key1 => $value1) {
                $race_arr[$key1] = $key1;
            }
            $ethnicity_arr = [];
            foreach ($this->array_ethnicity() as $key2 => $value2) {
                $ethnicity_arr[$key2] = $key2;
            }
            $active_arr = [
                '0' => 'Inactive',
                '1' => 'Active'
            ];
            $identity_arr = [
                'lastname' => $result->lastname,
                'firstname' => $result->firstname,
                'nickname' => $result->nickname,
                'middle' => $result->middle,
                'title' => $result->title,
                'DOB' => date('Y-m-d', $this->human_to_unix($result->DOB)),
                'sex' => $result->sex,
                'patient_id' => $result->patient_id,
                'ss' => $result->ss,
                'race' => $result->race,
                'race_code' => $result->race_code,
                'marital_status' => $result->marital_status,
                'partner_name' => $result->partner_name,
                'employer' => $result->employer,
                'ethnicity' => $result->ethnicity,
                'ethnicity_code' => $result->ethnicity_code,
                'caregiver' => $result->caregiver,
                'active' => $result->active,
                'referred_by' => $result->referred_by,
                'language' => $result->language,
                'lang_code' => $result->lang_code
            ];
            $items[] = [
                'name' => 'lastname',
                'label' => 'Last Name',
                'type' => 'text',
                'required' => true,
                'default_value' => $identity_arr['lastname']
            ];
            $items[] = [
                'name' => 'firstname',
                'label' => 'First Name',
                'type' => 'text',
                'required' => true,
                'default_value' => $identity_arr['firstname']
            ];
            $items[] = [
                'name' => 'nickname',
                'label' => 'Nickname',
                'type' => 'text',
                'default_value' => $identity_arr['nickname']
            ];
            $items[] = [
                'name' => 'middle',
                'label' => 'Middle Name',
                'type' => 'text',
                'default_value' => $identity_arr['middle']
            ];
            $items[] = [
                'name' => 'title',
                'label' => 'Title',
                'type' => 'text',
                'default_value' => $identity_arr['title']
            ];
            $items[] = [
                'name' => 'DOB',
                'label' => 'Date of Birth',
                'type' => 'date',
                'required' => true,
                'default_value' => $identity_arr['DOB']
            ];
            $items[] = [
                'name' => 'sex',
                'label' => 'Gender',
                'type' => 'select',
                'required' => true,
                'select_items' => $this->array_gender(),
                'default_value' => $identity_arr['sex']
            ];
            $items[] = [
                'name' => 'patient_id',
                'label' => 'Patient ID',
                'type' => 'text',
                'default_value' => $identity_arr['patient_id']
            ];
            $items[] = [
                'name' => 'ss',
                'label' => 'SSN',
                'type' => 'text',
                'default_value' => $identity_arr['ss']
            ];
            $items[] = [
                'name' => 'race',
                'label' => 'Race',
                'type' => 'select',
                'select_items' => $race_arr,
                'default_value' => $identity_arr['race']
            ];
            $items[] = [
                'name' => 'race_code',
                'type' => 'hidden',
                'default_value' => $identity_arr['race_code']
            ];
            $items[] = [
                'name' => 'marital_status',
                'label' => 'Marital Status',
                'type' => 'select',
                'select_items' => $this->array_marital(),
                'default_value' => $identity_arr['marital_status']
            ];
            $items[] = [
                'name' => 'partner_name',
                'label' => 'Spouse/Partner Name',
                'type' => 'text',
                'default_value' => $identity_arr['partner_name']
            ];
            $items[] = [
                'name' => 'employer',
                'label' => 'Employer',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'employer']),
                'default_value' => $identity_arr['employer']
            ];
            $items[] = [
                'name' => 'ethnicity',
                'label' => 'Ethnicity',
                'type' => 'select',
                'select_items' => $ethnicity_arr,
                'default_value' => $identity_arr['ethnicity']
            ];
            $items[] = [
                'name' => 'ethnicity_code',
                'type' => 'hidden',
                'default_value' => $identity_arr['ethnicity_code']
            ];
            $items[] = [
                'name' => 'caregiver',
                'label' => 'Careiver(s)',
                'type' => 'text',
                'default_value' => $identity_arr['caregiver']
            ];
            $items[] = [
                'name' => 'active',
                'label' => 'Status',
                'type' => 'select',
                'required' => true,
                'select_items' => $active_arr,
                'default_value' => $identity_arr['active']
            ];
            $items[] = [
                'name' => 'referred_by',
                'label' => 'Referred By',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'referred_by']),
                'default_value' => $identity_arr['referred_by']
            ];
            $items[] = [
                'name' => 'language',
                'label' => 'Preferred Language',
                'type' => 'text',
                'default_value' => $identity_arr['language']
            ];
            $items[] = [
                'name' => 'lang_code',
                'type' => 'hidden',
                'default_value' => $identity_arr['lang_code']
            ];
        }
        if ($subtype == 'contacts') {
            $contact_arr = [
                'address' => $result->address,
                'country' => $result->country,
                'city' => $result->city,
                'state' => $result->state,
                'zip' => $result->zip,
                'email' => $result->email,
                'phone_home' => $result->phone_home,
                'phone_work' => $result->phone_work,
                'phone_cell' => $result->phone_cell,
                'emergency_contact' => $result->emergency_contact,
                'reminder_method' => $result->reminder_method,
                'reminder_interval' => $result->reminder_interval
            ];
            $items[] = [
                'name' => 'address',
                'label' => 'Address',
                'type' => 'text',
                'default_value' => $contact_arr['address']
            ];
            $items[] = [
                'name' => 'country',
                'label' => 'Country',
                'type' => 'select',
                'select_items' => $this->array_country(),
                'default_value' => $contact_arr['country'],
                'class' => 'country'
            ];
            $items[] = [
                'name' => 'city',
                'label' => 'City',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'city']),
                'default_value' => $contact_arr['city']
            ];
            $items[] = [
                'name' => 'state',
                'label' => 'State',
                'type' => 'select',
                'select_items' => $this->array_states($contact_arr['country']),
                'default_value' => $contact_arr['state'],
                'class' => 'state'
            ];
            $items[] = [
                'name' => 'zip',
                'label' => 'Zip',
                'type' => 'text',
                'default_value' => $contact_arr['zip']
            ];
            $items[] = [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'default_value' => $contact_arr['email']
            ];
            $items[] = [
                'name' => 'phone_home',
                'label' => 'Home Phone',
                'type' => 'text',
                'phone' => true,
                'default_value' => $contact_arr['phone_home']
            ];
            $items[] = [
                'name' => 'phone_work',
                'label' => 'Work Phone',
                'type' => 'text',
                'phone' => true,
                'default_value' => $contact_arr['phone_work']
            ];
            $items[] = [
                'name' => 'phone_cell',
                'label' => 'Mobile',
                'type' => 'text',
                'phone' => true,
                'default_value' => $contact_arr['phone_cell']
            ];
            $items[] = [
                'name' => 'emergency_contact',
                'label' => 'Emergency Contact',
                'type' => 'text',
                'default_value' => $contact_arr['emergency_contact']
            ];
            $items[] = [
                'name' => 'reminder_method',
                'label' => 'Appointment Reminder Method',
                'type' => 'select',
                'select_items' => $this->array_reminder_method(),
                'default_value' => $contact_arr['reminder_method']
            ];
            $items[] = [
                'name' => 'reminder_interval',
                'label' => 'Appointment Reminder Interval',
                'type' => 'select',
                'select_items' => $this->array_reminder_interval(),
                'default_value' => $contact_arr['reminder_interval']
            ];
        }
        if ($subtype == 'guardians') {
            $guardian_arr = [
                'guardian_lastname' => $result->guardian_lastname,
                'guardian_firstname' => $result->guardian_firstname,
                'guardian_relationship' => $result->guardian_relationship,
                'guardian_code' => $result->guardian_code,
                'guardian_address' => $result->guardian_address,
                'guardian_country' => $result->guardian_country,
                'guardian_city' => $result->guardian_city,
                'guardian_state' => $result->guardian_state,
                'guardian_zip' => $result->guardian_zip,
                'guardian_email' => $result->guardian_email,
                'guardian_phone_home' => $result->guardian_phone_home,
                'guardian_phone_work' => $result->guardian_phone_work,
                'guardian_phone_cell' => $result->guardian_phone_cell
            ];
            $items[] = [
                'name' => 'guardian_lastname',
                'label' => 'Last Name',
                'type' => 'text',
                'default_value' => $guardian_arr['guardian_lastname']
            ];
            $items[] = [
                'name' => 'guardian_firstname',
                'label' => 'First Name',
                'type' => 'text',
                'default_value' => $guardian_arr['guardian_firstname']
            ];
            $items[] = [
                'name' => 'guardian_relationship',
                'label' => 'Relationship',
                'type' => 'text',
                'default_value' => $guardian_arr['guardian_relationship']
            ];
            $items[] = [
                'name' => 'guardian_code',
                'type' => 'hidden',
                'default_value' => $guardian_arr['guardian_code']
            ];
            $items[] = [
                'name' => 'guardian_address',
                'label' => 'Address',
                'type' => 'text',
                'default_value' => $guardian_arr['guardian_address']
            ];
            $items[] = [
                'name' => 'guardian_country',
                'label' => 'Country',
                'type' => 'select',
                'select_items' => $this->array_country(),
                'default_value' => $guardian_arr['guardian_country'],
                'class' => 'country'
            ];
            $items[] = [
                'name' => 'guardian_city',
                'label' => 'City',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'guardian_city']),
                'default_value' => $guardian_arr['guardian_city']
            ];
            $items[] = [
                'name' => 'guardian_state',
                'label' => 'State',
                'type' => 'select',
                'select_items' => $this->array_states($guardian_arr['guardian_country']),
                'default_value' => $guardian_arr['guardian_state'],
                'class' => 'state'
            ];
            $items[] = [
                'name' => 'guardian_zip',
                'label' => 'Zip',
                'type' => 'text',
                'default_value' => $guardian_arr['guardian_zip']
            ];
            $items[] = [
                'name' => 'guardian_email',
                'label' => 'Email',
                'type' => 'email',
                'default_value' => $guardian_arr['guardian_email']
            ];
            $items[] = [
                'name' => 'guardian_phone_home',
                'label' => 'Home Phone',
                'type' => 'text',
                'phone' => true,
                'default_value' => $guardian_arr['guardian_phone_home']
            ];
            $items[] = [
                'name' => 'guardian_phone_work',
                'label' => 'Work Phone',
                'type' => 'text',
                'phone' => true,
                'default_value' => $guardian_arr['guardian_phone_work']
            ];
            $items[] = [
                'name' => 'guardian_phone_cell',
                'label' => 'Mobile',
                'type' => 'text',
                'phone' => true,
                'default_value' => $guardian_arr['guardian_phone_cell']
            ];
        }
        if ($subtype == 'other') {
            $other_arr = [
                'preferred_provider' => $result->preferred_provider,
                'preferred_pharmacy' => $result->preferred_pharmacy,
                'other1' => $result->other1,
                'other2' => $result->other2,
                'comments' => $result->comments,
                'pharmacy_address_id' => $result->pharmacy_address_id
            ];
            if ($result->pharmacy_address_id == '' || $result->pharmacy_address_id == null) {
                if ($result->preferred_pharmacy !== '') {
                    $pharmacy_query = DB::table('addressbook')->where('specialty', '=', 'Pharmacy')->where('displayname', '=', $result->preferred_pharmacy)->first();
                    if ($pharmacy_query) {
                        $other_arr['pharmacy_address_id'] = $pharmacy_query->address_id;
                    }
                }
            }
            $items[] = [
                'name' => 'preferred_provider',
                'label' => 'Preferred Provider',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => 'users', 'column' => 'displayname', 'subtype' => 'provider']),
                'default_value' => $other_arr['preferred_provider']
            ];
            $items[] = [
                'name' => 'pharmacy_address_id',
                'label' => 'Preferred Pharmacy',
                'type' => 'select',
                'select_items' => $this->array_orders_provider('Pharmacy'),
                'default_value' => $other_arr['pharmacy_address_id']
            ];
            // $items[] = [
            //     'name' => 'preferred_pharmacy',
            //     'label' => 'Preferred Pharmacy',
            //     'type' => 'text',
            //     'typeahead' => route('typeahead', ['table' => 'addressbook', 'column' => 'displayname', 'subtype' => 'pharmacy']),
            //     'default_value' => $other_arr['preferred_pharmacy']
            // ];
            $items[] = [
                'name' => 'other1',
                'label' => 'Other Field 1',
                'type' => 'text',
                'default_value' => $other_arr['other1']
            ];
            $items[] = [
                'name' => 'other2',
                'label' => 'Other Field 2',
                'type' => 'text',
                'default_value' => $other_arr['other2']
            ];
            $items[] = [
                'name' => 'comments',
                'label' => 'Comments',
                'type' => 'textarea',
                'default_value' => $other_arr['comments']
            ];
        }
        if ($subtype == 'cc') {
            $card_type_arr = [
                '' => 'Select a credit card type',
                'MasterCard' => 'MasterCard',
                'Visa' => 'Visa',
                'Discover' => 'Discover',
                'Amex'=>'American Express'
            ];
            if ($result->creditcard_number == '' || $result->creditcard_number == null) {
                $cc_arr = [
                    'creditcard_number' => null,
                    'creditcard_expiration' => null,
                    'creditcard_type' => null,
                    'creditcard_key' => null
                ];
            } else {
                $cc_arr = [
                    'creditcard_number' => decrypt($result->creditcard_number),
                    'creditcard_expiration' => $result->creditcard_expiration,
                    'creditcard_type' => $result->creditcard_type,
                    'creditcard_key' => $result->creditcard_key
                ];
            }
            $items[] = [
                'name' => 'creditcard_number',
                'label' => 'Card Number',
                'type' => 'text',
                'required' => true,
                'default_value' => $cc_arr['creditcard_number']
            ];
            $items[] = [
                'name' => 'creditcard_type',
                'label' => 'Type',
                'type' => 'select',
                'select_items' => $card_type_arr,
                'required' => true,
                'default_value' => $cc_arr['creditcard_type']
            ];
            $items[] = [
                'name' => 'creditcard_expiration',
                'label' => 'Expiration',
                'type' => 'text',
                'required' => true,
                'default_value' => $cc_arr['creditcard_expiration']
            ];
        }
        return $items;
    }

    protected function form_documents($result, $table, $id, $subtype)
    {
        $document_type_arr = [
            'Laboratory' => 'Laboratory',
            'Imaging' => 'Imaging',
            'Cardiopulmonary' => 'Cardiopulmonary',
            'Endoscopy' => 'Endoscopy',
            'Referrals' => 'Referrals',
            'Past_Records' => 'Past Records',
            'Other_Forms' => 'Other Forms',
            'Letters' => 'Letters',
            'Education' => 'Education',
            'ccda' => 'CCDAs',
            'ccr' => 'CCRs'
        ];
        if ($id == '0') {
            $document = [
                'documents_from' => null,
                'documents_type' => null,
                'documents_desc' => null,
                'documents_date' => date('Y-m-d'),
                'label' => null
            ];
        } else {
            $label = [];
            if ($result->label !== '' || $result->label !== null)  {
                $label = explode(";", $result->label);
            }
            $document = [
                'documents_from' => $result->documents_from,
                'documents_type' => $result->documents_type,
                'documents_desc' => $result->documents_desc,
                'documents_date' => date('Y-m-d', $this->human_to_unix($result->documents_date)),
                'label' => $label
            ];
        }
        $items[] = [
            'name' => 'documents_from',
            'label' => 'From',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'documents_from']),
            'default_value' => $document['documents_from']
        ];
        if ($document['documents_type'] == 'ccda' || $document['documents_type'] == 'ccr') {
            $items[] = [
                'name' => 'documents_type',
                'label' => 'Type',
                'type' => 'select',
                'select_items' => $document_type_arr,
                'required' => true,
                'readonly' => true,
                'default_value' => $document['documents_type']
            ];
        } else {
            $items[] = [
                'name' => 'documents_type',
                'label' => 'Type',
                'type' => 'select',
                'select_items' => $document_type_arr,
                'required' => true,
                'default_value' => $document['documents_type']
            ];
        }
        $items[] = [
            'name' => 'documents_desc',
            'label' => 'Description',
            'type' => 'textarea',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'documents_desc']),
            'default_value' => $document['documents_desc']
        ];
        $items[] = [
            'name' => 'documents_date',
            'label' => 'Date',
            'type' => 'date',
            'required' => true,
            'default_value' => $document['documents_date']
        ];
        $items[] = [
            'name' => 'label[]',
            'label' => 'Sensitive Label',
            'type' => 'select',
            'select_items' => $this->fhir_scopes_sensitivities(),
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $document['label']
        ];
        return $items;
    }

    protected function form_family_history($result, $arr, $id)
    {
        $rel_arr = [
            '' => '',
            'Father' => 'Father',
            'Mother' => 'Mother',
            'Brother' => 'Brother',
            'Sister' => 'Sister',
            'Son' => 'Son',
            'Daughter' => 'Daughter',
            'Spouse' => 'Spouse',
            'Partner' => 'Partner',
            'Paternal Uncle' => 'Paternal Uncle',
            'Paternal Aunt' => 'Paternal Aunt',
            'Maternal Uncle' => 'Maternal Uncle',
            'Maternal Aunt' => 'Maternal Aunt',
            'Maternal Grandfather' => 'Maternal Grandfather',
            'Maternal Grandmother' => 'Maternal Grandmother',
            'Paternal Grandfather' => 'Paternal Grandfather',
            'Paternal Grandmother' => 'Paternal Grandmother'
        ];
        $status_arr = [
            'Alive' => 'Alive',
            'Deceased' => 'Deceased'
        ];
        $name_arr[''] = '';
        $name_arr['Patient'] = 'Patient';
        if (! empty($arr)) {
            foreach($arr as $person) {
                $name_arr[$person['Name']] = $person['Name'];
            }
        }
        $items[] = [
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'required' => true,
            'default_value' => $result['name']
        ];
        $items[] = [
            'name' => 'relationship',
            'label' => 'Relationship',
            'type' => 'select',
            'select_items' => $rel_arr,
            'required' => true,
            'default_value' => $result['relationship']
        ];
        $items[] = [
            'name' => 'status',
            'label' => 'Living Status',
            'type' => 'select',
            'select_items' => $status_arr,
            'required' => true,
            'default_value' => $result['status']
        ];
        $items[] = [
            'name' => 'gender',
            'label' => 'Gender',
            'type' => 'select',
            'select_items' => $this->array_gender1(),
            'required' => true,
            'default_value' => $result['gender']
        ];
        $items[] = [
            'name' => 'date_of_birth',
            'label' => 'Date of Birth',
            'type' => 'date',
            'required' => true,
            'default_value' => $result['date_of_birth']
        ];
        $items[] = [
            'name' => 'marital_status',
            'label' => 'Marital Status',
            'type' => 'select',
            'select_items' => $this->array_marital(),
            'required' => true,
            'default_value' => $result['marital_status']
        ];
        $items[] = [
            'name' => 'mother',
            'label' => 'Mother',
            'type' => 'select',
            'select_items' => $name_arr,
            'default_value' => $result['mother']
        ];
        $items[] = [
            'name' => 'father',
            'label' => 'Father',
            'type' => 'select',
            'select_items' => $name_arr,
            'default_value' => $result['father']
        ];
        $medical_arr1 = explode("\n", $result['medical']);
        foreach ($medical_arr1 as $medical_item) {
            $medical_arr[$medical_item] = $medical_item;
        }
        $items[] = [
            'name' => 'medical[]',
            'label' => 'Medical history',
            'type' => 'select',
            'select_items' => $medical_arr,
            'required' => true,
            'multiple' => true,
            'tagsinput' => route('tagsinput_icd'),
            'default_value' => $medical_arr
        ];
        return $items;
    }

    protected function form_hippa($result, $table, $id, $subtype)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $role_arr = [
            '' => '',
            'Primary Care Provider' => 'Primary Care Provider',
            'Consulting Provider' => trans('nosh.consulting_provider'),
            'Referring Provider' => trans('nosh.referring_provider')
        ];
        $nosh_action_arr = [
            'chart_queue,encounters' => 'Select Records to Print',
            'download_ccda' => 'Download CCDA',
            'print_action,all' => 'Print All Records',
            'print_action,1year' => 'Print All Records from Past Year',
            'print_queue,all' => 'All Records to Print Queue',
            'print_queue,1year' => 'All Records from Past Year to Print Queue'
        ];
        if ($practice->fax_type !== '') {
            $nosh_action_arr['fax_action,all'] = 'Fax All Records';
            $nosh_action_arr['fax_action,1year'] = 'Fax All Records from Past Year';
            $nosh_action_arr['chart_queue,encounters'] .= ' or Fax';
            $nosh_action_arr['fax_queue,all'] = 'All Records to Fax Queue';
            $nosh_action_arr['fax_queue,1year'] = 'All Records from Past Year to Fax Queue';
        }
        if ($id == '0') {
            $data = [
                'hippa_date_release' => date('Y-m-d'),
                'pid' => Session::get('pid'),
                'hippa_reason' => null,
                'hippa_provider' => null,
                'hippa_role' => null,
                'other_hippa_id' => 0,
                'practice_id' => Session::get('practice_id'),
                'address_id' => null
            ];
        } else {
            $data = [
                'hippa_date_release' => date('Y-m-d', strtotime($result->hippa_date_release)),
                'pid' => $result->pid,
                'hippa_reason' => $result->hippa_reason,
                'hippa_provider' => $result->hippa_provider,
                'hippa_role' => $result->hippa_role,
                'other_hippa_id' => $result->other_hippa_id,
                'practice_id' => $result->practice_id,
                'address_id' => $result->address_id
            ];
        }
        $items[] = [
            'name' => 'hippa_reason',
            'label' => 'Reason',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'hippa_reason']),
            'default_value' => $data['hippa_reason']
        ];
        $items[] = [
            'name' => 'hippa_provider',
            'label' => 'Release To',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'hippa_provider']),
            'class' => 'nosh-data-address',
            'default_value' => $data['hippa_provider']
        ];
        $items[] = [
            'name' => 'hippa_role',
            'label' => 'Provider Role',
            'type' => 'select',
            'select_items' => $role_arr,
            'default_value' => $data['hippa_role']
        ];
        $items[] = [
            'name' => 'hippa_date_release',
            'label' => 'Date of Records Release',
            'type' => 'date',
            'required' => true,
            'default_value' => $data['hippa_date_release']
        ];
        $items[] = [
            'name' => 'pid',
            'type' => 'hidden',
            'default_value' => $data['pid']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'default_value' => $data['practice_id']
        ];
        $items[] = [
            'name' => 'other_hippa_id',
            'type' => 'hidden',
            'default_value' => $data['other_hippa_id']
        ];
        $items[] = [
            'name' => 'address_id',
            'type' => 'hidden',
            'default_value' => $data['address_id']
        ];
        $items[] = [
            'name' => 'nosh_action',
            'label' => 'Action after Saving',
            'type' => 'select',
            'select_items' => $nosh_action_arr,
            'required' => true
        ];
        return $items;
    }

    protected function form_hippa_request($result, $table, $id, $subtype)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $type_arr = [
            '' => 'Select Option',
            'General Medical Records' => 'General Medical Records',
            'Specific' => [
                'History and Physical' => 'History and Physical',
                'Medications and Therapy' => 'Medications and Therapy',
                'Lab, Imaging, or Cardiopulmonary Report' => 'Lab, Imaging, or Cardiopulmonary Report',
                'Operative Report' => 'Operative Report',
                'Accident or Injury' => 'Accident or Injury',
                'Immunizations' => 'Immunizations',
                'Other' => 'Other'
            ]
        ];
        $nosh_action_arr = [
            'print_action' => 'Print',
            'print_queue' => 'Add to Print Queue'
        ];
        if ($practice->fax_type !== '') {
            $nosh_action_arr['fax_action'] = 'Fax';
            $nosh_action_arr['fax_queue'] = 'Add to Fax Queue';
        }
        if ($id == '0') {
            $data = [
                'request_reason' => null,
                'request_type' => null,
                'request_to' => null,
                'history_physical' => null,
                'lab_type' => null,
                'lab_date' => null,
                'op' => null,
                'accident_f' => null,
                'accident_t' => null,
                'other' => null,
                'hippa_date_request' => date('Y-m-d'),
                'received' => 'No',
                'pid' => Session::get('pid'),
                'practice_id' => Session::get('practice_id'),
                'address_id' => null
            ];
        } else {
            $data = [
                'request_reason' => $result->request_reason,
                'request_type' => $result->request_type,
                'request_to' => $result->request_to,
                'history_physical' => date('Y-m-d', strtotime($result->history_physical)),
                'lab_type' => $result->lab_type,
                'lab_date' => date('Y-m-d', strtotime($result->lab_date)),
                'op' => $result->op,
                'accident_f' => date('Y-m-d', strtotime($result->accident_f)),
                'accident_t' => date('Y-m-d', strtotime($result->accident_t)),
                'other' => $result->other,
                'hippa_date_request' => date('Y-m-d', strtotime($result->hippa_date_request)),
                'received' => $result->received,
                'pid' => $result->pid,
                'practice_id' => $result->practice_id,
                'address_id' => $result->address_id
            ];
            if ($result->history_physical == '') {
                $data['history_physical'] = null;
            }
            if ($result->lab_date == '') {
                $data['lab_date'] = null;
            }
            if ($result->accident_f == '') {
                $data['accident_f'] = null;
            }
            if ($result->accident_t == '') {
                $data['accident_t'] = null;
            }
        }
        $items[] = [
            'name' => 'request_reason',
            'label' => 'Reason',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'request_reason']),
            'default_value' => $data['request_reason']
        ];
        $items[] = [
            'name' => 'request_type',
            'label' => 'Type',
            'type' => 'select',
            'required' => true,
            'select_items' => $type_arr,
            'default_value' => $data['request_type']
        ];
        $items[] = [
            'name' => 'request_to',
            'label' => 'Records Release To',
            'type' => 'text',
            'required' => true,
            'class' => 'nosh-data-address',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'request_to']),
            'default_value' => $data['request_to']
        ];
        $items[] = [
            'name' => 'hippa_date_request',
            'label' => 'Date of Request',
            'type' => 'date',
            'required' => true,
            'default_value' => $data['hippa_date_request']
        ];
        $items[] = [
            'name' => 'history_physical',
            'label' => 'Date of History and Physical',
            'type' => 'date',
            'default_value' => $data['history_physical']
        ];
        $items[] = [
            'name' => 'lab_type',
            'label' => 'Type of Test',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'lab_type']),
            'default_value' => $data['lab_type']
        ];
        $items[] = [
            'name' => 'lab_date',
            'label' => 'Date of Test',
            'type' => 'date',
            'default_value' => $data['lab_date']
        ];
        $items[] = [
            'name' => 'op',
            'label' => 'Type of Operation',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'op']),
            'default_value' => $data['op']
        ];
        $items[] = [
            'name' => 'accident_f',
            'label' => 'Date of Injury/Accident (from)',
            'type' => 'date',
            'default_value' => $data['accident_f']
        ];
        $items[] = [
            'name' => 'accident_t',
            'label' => 'Date of Injury/Accident (to)',
            'type' => 'date',
            'default_value' => $data['accident_t']
        ];
        $items[] = [
            'name' => 'other',
            'label' => 'Other',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'other']),
            'default_value' => $data['other']
        ];
        $items[] = [
            'name' => 'received',
            'label' => 'Received',
            'type' => 'checkbox',
            'value' => 'Yes',
            'default_value' => $data['received']
        ];
        $items[] = [
            'name' => 'pid',
            'type' => 'hidden',
            'default_value' => $data['pid']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'default_value' => $data['practice_id']
        ];
        $items[] = [
            'name' => 'address_id',
            'type' => 'hidden',
            'default_value' => $data['address_id']
        ];
        $items[] = [
            'name' => 'nosh_action',
            'label' => 'Action after Saving',
            'type' => 'select',
            'select_items' => $nosh_action_arr,
            'required' => true
        ];
        return $items;
    }

    protected function form_immunizations($result, $table, $id, $subtype)
    {
        $nosh_action_arr = [
            '' => 'Do Nothing',
            'inventory' => 'Pull from Vaccine Inventory'
        ];
        if (Session::get('group_id') == '100') {
            unset($nosh_action_arr['inventory']);
        }
        $sequence_arr = [
            '' => '',
            '1' => 'First',
            '2' => 'Second',
            '3' => 'Third',
            '4' => 'Fourth',
            '5' => 'Fifth'
        ];
        $site_arr = [
            '' => '',
            'Right Deltoid' => 'Right Deltoid',
            'Left Deltoid' => 'Left Deltoid',
            'Right Gluteus' => 'Right Gluteus',
            'Left Gluteus' => 'Left Gluteus',
            'Right Thigh' => 'Right Thigh',
            'Left Thigh' => 'Left Thigh'
        ];
        if ($id == '0') {
            $imm = [
                'imm_immunization' => null,
                'imm_sequence' => null,
                'imm_body_site' => null,
                'imm_route' => null,
                'imm_dosage' => null,
                'imm_dosage_unit' => null,
                'imm_lot' => null,
                'imm_expiration' => null,
                'imm_date' => date('Y-m-d'),
                'imm_elsewhere' => null,
                'imm_vis' => '',
                'imm_manufacturer' => null,
                'imm_provider' => Session::get('displayname'),
                'imm_cvxcode' => null
            ];
            if (Session::has('ccda')) {
                $ccda = Session::get('ccda');
                Session::forget('ccda');
                $imm['imm_immunization'] = $ccda['name'];
                $imm['imm_route'] = $ccda['route'];
                $imm['imm_date'] = date('Y-m-d', $this->human_to_unix($ccda['date']));
                if (isset($ccda['code'])) {
                    $imm['imm_cvxcode'] = $ccda['code'];
                }
                if (isset($ccda['sequence'])) {
                    $imm['imm_sequence'] = $ccda['sequence'];
                }
            }
        } else {
            if ($result->imm_expiration == '' && $result->imm_expiration == null) {
                $imm_expiration = null;
            } else {
                $imm_expiration = date('Y-m-d', strtotime($result->imm_expiration));
            }
            $imm = [
                'imm_immunization' => $result->imm_immunization,
                'imm_sequence' => $result->imm_sequence,
                'imm_body_site' => $result->imm_body_site,
                'imm_route' => $result->imm_route,
                'imm_dosage' => $result->imm_dosage,
                'imm_dosage_unit' => $result->imm_dosage_unit,
                'imm_lot' => $result->imm_lot,
                'imm_expiration' => $imm_expiration,
                'imm_date' => date('Y-m-d', strtotime($result->imm_date)),
                'imm_elsewhere' => $result->imm_elsewhere,
                'imm_vis' => $result->imm_vis,
                'imm_manufacturer' => $result->imm_manufacturer,
                'imm_provider' => Session::get('displayname'),
                'imm_cvxcode' => $result->imm_cvxcode
            ];
        }
        if ($imm['imm_elsewhere'] == 'Yes') {
            $elsewhere = true;
        } else {
            $elsewhere = false;
        }
        $items[] = [
            'name' => 'imm_immunization',
            'label' => 'Immunization',
            'type' => 'text',
            'required' => true,
            'default_value' => $imm['imm_immunization']
        ];
        if (Session::get('group_id') != '100') {
            $items[] = [
                'name' => 'imm_sequence',
                'label' => 'Sequence',
                'type' => 'select',
                'select_items' => $sequence_arr,
                'default_value' => $imm['imm_sequence']
            ];
            $items[] = [
                'name' => 'imm_elsewhere',
                'label' => 'Given Elsewhere',
                'type' => 'checkbox',
                'value' => 'Yes',
                'default_value' => $imm['imm_elsewhere']
            ];
            $items[] = [
                'name' => 'imm_vis',
                'label' => 'VIS Given',
                'type' => 'checkbox',
                'value' => 'Yes',
                'default_value' => $imm['imm_vis']
            ];
            $items[] = [
                'name' => 'imm_body_site',
                'label' => 'Body Site',
                'type' => 'select',
                'select_items' => $site_arr,
                'default_value' => $imm['imm_body_site']
            ];
            $items[] = [
                'name' => 'imm_route',
                'label' => 'Route',
                'type' => 'select',
                'select_items' => $this->array_route(),
                'default_value' => $imm['imm_route']
            ];
            $items[] = [
                'name' => 'imm_dosage',
                'label' => 'Dosage',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'imm_dosage']),
                'default_value' => $imm['imm_dosage']
            ];
            $items[] = [
                'name' => 'imm_dosage_unit',
                'label' => 'Dosage Unit',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'imm_dosage_unit']),
                'default_value' => $imm['imm_dosage_unit']
            ];
            $items[] = [
                'name' => 'imm_lot',
                'label' => 'Lot Number',
                'type' => 'text',
                'default_value' => $imm['imm_lot']
            ];
            $items[] = [
                'name' => 'imm_manufacturer',
                'label' => 'Manufacturer',
                'type' => 'text',
                'typeahead' => route('typeahead', ['table' => $table, 'column' => 'imm_manufacturer']),
                'default_value' => $imm['imm_manufacturer']
            ];
            $items[] = [
                'name' => 'imm_expiration',
                'label' => 'Expiration Date',
                'type' => 'date',
                'default_value' => $imm['imm_expiration']
            ];
        }
        $items[] = [
            'name' => 'imm_date',
            'label' => 'Date Active',
            'type' => 'date',
            'required' => true,
            'default_value' => $imm['imm_date']
        ];
        $items[] = [
            'name' => 'imm_cvxcode',
            'label' => 'CVX Code',
            'type' => 'text',
            'readonly' => true,
            'default_value' => $imm['imm_cvxcode']
        ];
        $items[] = [
            'name' => 'imm_provider',
            'type' => 'hidden',
            'required' => true,
            'default_value' => $imm['imm_provider']
        ];
        $items[] = [
            'name' => 'nosh_action',
            'label' => 'Action after Saving',
            'type' => 'select',
            'select_items' => $nosh_action_arr
        ];
        return $items;
    }

    protected function form_insurance($result, $table, $id, $subtype)
    {
        $insurance_order_arr = [
            '' => '',
            'Primary' => 'Primary',
            'Secondary' => 'Secondary',
            'Unassigned' => 'Unassigned'
        ];
        $insurance_relationship_arr = [
            '' => '',
            'Self' => 'Self',
            'Spouse' => 'Spouse',
            'Child' => 'Child',
            'Other' => 'Other'
        ];
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        if ($id == '0') {
            $insurance = [
                'insurance_plan_name' => null,
                'address_id' => null,
                'insurance_id_num' => null,
                'insurance_group' => null,
                'insurance_order' => null,
                'insurance_relationship' => null,
                'insurance_copay' => null,
                'insurance_deductible' => null,
                'insurance_comments' => null,
                'insurance_insu_lastname' => null,
                'insurance_insu_firstname' => null,
                'insurance_insu_dob' => null,
                'insurance_insu_gender' => null,
                'insurance_insu_address' => null,
                'insurance_insu_country' => $practice->country,
                'insurance_insu_city' => null,
                'insurance_insu_state' => null,
                'insurance_insu_zip' => null,
                'insurance_insu_phone' => null,
                'insurance_plan_active' => 'Yes',
                'pid' => Session::get('pid')
            ];
        } else {
            $insurance = [
                'insurance_plan_name' => $result->insurance_plan_name,
                'address_id' => $result->address_id,
                'insurance_id_num' => $result->insurance_id_num,
                'insurance_group' => $result->insurance_group,
                'insurance_order' => $result->insurance_order,
                'insurance_relationship' => $result->insurance_relationship,
                'insurance_copay' => $result->insurance_copay,
                'insurance_deductible' => $result->insurance_deductible,
                'insurance_comments' => $result->insurance_comments,
                'insurance_insu_lastname' => $result->insurance_insu_lastname,
                'insurance_insu_firstname' => $result->insurance_insu_firstname,
                'insurance_insu_dob' => date('Y-m-d', $this->human_to_unix($result->insurance_insu_dob)),
                'insurance_insu_gender' => $result->insurance_insu_gender,
                'insurance_insu_address' => $result->insurance_insu_address,
                'insurance_insu_country' => $result->insurance_insu_country,
                'insurance_insu_city' => $result->insurance_insu_city,
                'insurance_insu_state' => $result->insurance_insu_state,
                'insurance_insu_zip' => $result->insurance_insu_zip,
                'insurance_insu_phone' => $result->insurance_insu_phone,
                'insurance_plan_active' => $result->insurance_plan_active,
                'pid' => $result->pid
            ];
        }
        $items[] = [
            'name' => 'insurance_plan_name',
            'label' => 'Insurance Provider',
            'type' => 'text',
            'readonly' => true,
            'required' => true,
            'default_value' => $insurance['insurance_plan_name']
        ];
        $items[] = [
            'name' => 'address_id',
            'type' => 'hidden',
            'required' => true,
            'default_value' => $insurance['address_id']
        ];
        $items[] = [
            'name' => 'insurance_order',
            'label' => 'Insurance Priority',
            'type' => 'select',
            'select_items' => $insurance_order_arr,
            'default_value' => $insurance['insurance_order']
        ];
        $items[] = [
            'name' => 'insurance_id_num',
            'label' => 'ID Number',
            'type' => 'text',
            'required' => true,
            'default_value' => $insurance['insurance_id_num']
        ];
        $items[] = [
            'name' => 'insurance_group',
            'label' => 'Group Number',
            'type' => 'text',
            'default_value' => $insurance['insurance_group']
        ];
        $items[] = [
            'name' => 'insurance_relationship',
            'label' => 'Relationship',
            'type' => 'select',
            'select_items' => $insurance_relationship_arr,
            'default_value' => $insurance['insurance_relationship']
        ];
        $items[] = [
            'name' => 'insurance_insu_lastname',
            'label' => 'Insured Last Name',
            'type' => 'text',
            'required' => true,
            'default_value' => $insurance['insurance_insu_lastname']
        ];
        $items[] = [
            'name' => 'insurance_insu_firstname',
            'label' => 'Insured First Name',
            'type' => 'text',
            'required' => true,
            'default_value' => $insurance['insurance_insu_firstname']
        ];
        $items[] = [
            'name' => 'insurance_insu_dob',
            'label' => 'Insured Date of Birth',
            'type' => 'date',
            'required' => true,
            'default_value' => $insurance['insurance_insu_dob']
        ];
        $items[] = [
            'name' => 'insurance_insu_gender',
            'label' => 'Gender',
            'type' => 'select',
            'required' => true,
            'select_items' => $this->array_gender(),
            'default_value' => $insurance['insurance_insu_gender']
        ];
        $items[] = [
            'name' => 'insurance_insu_address',
            'label' => 'Insured Address',
            'type' => 'text',
            'required' => true,
            'default_value' => $insurance['insurance_insu_address']
        ];
        $items[] = [
            'name' => 'insurance_insu_country',
            'label' => 'Country',
            'type' => 'select',
            'select_items' => $this->array_country(),
            'default_value' => $insurance['insurance_insu_country'],
            'class' => 'country'
        ];
        $items[] = [
            'name' => 'insurance_insu_city',
            'label' => 'Insured City',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'insurance_insu_city']),
            'default_value' => $insurance['insurance_insu_city']
        ];
        $items[] = [
            'name' => 'insurance_insu_state',
            'label' => 'Insured State',
            'type' => 'select',
            'required' => true,
            'select_items' => $this->array_states($insurance['insurance_insu_country']),
            'default_value' => $insurance['insurance_insu_state'],
            'class' => 'state'
        ];
        $items[] = [
            'name' => 'insurance_insu_zip',
            'label' => 'Insured Zip',
            'type' => 'text',
            'required' => true,
            'default_value' => $insurance['insurance_insu_zip']
        ];
        $items[] = [
            'name' => 'insurance_insu_phone',
            'label' => 'Insured Phone',
            'type' => 'text',
            'phone' => true,
            'default_value' => $insurance['insurance_insu_phone']
        ];
        $items[] = [
            'name' => 'insurance_copay',
            'label' => 'Copay',
            'type' => 'text',
            'default_value' => $insurance['insurance_copay']
        ];
        $items[] = [
            'name' => 'insurance_deductible',
            'label' => 'Deductible',
            'type' => 'text',
            'default_value' => $insurance['insurance_deductible']
        ];
        $items[] = [
            'name' => 'insurance_comments',
            'label' => 'Comments',
            'type' => 'textarea',
            'default_value' => $insurance['insurance_comments']
        ];
        return $items;
    }

    protected function form_issues($result, $table, $id, $subtype)
    {
        $issue_type_arr = [
            'pl' => 'Problem List',
            'mh' => 'Medical History',
            'sh' => 'Surgical History'
        ];
        $issue_type_arr1 = [
            'Problem List' => 'Problem List',
            'Medical History' => 'Medical History',
            'Surgical History' => 'Surgical History'
        ];
        if ($id == '0') {
            $issue = [
                'issue' => null,
                'type' => $issue_type_arr[$subtype],
                'issue_date_active' => date('Y-m-d'),
                'issue_date_inactive' => '',
                'issue_provider' => Session::get('displayname'),
                'notes' => null,
                'label' => null
            ];
            if (Session::has('ccda')) {
                $ccda = Session::get('ccda');
                Session::forget('ccda');
                $issue['issue'] = $ccda['name'] . ' [' . $ccda['code'] . ']';
                $issue['issue_date_active'] = date('Y-m-d', $this->human_to_unix($ccda['date']));
                if (isset($ccda['from'])) {
                    $issue['notes'] = 'Obtained via FHIR from ' . $ccda['from'];
                }
            }
        } else {
            $label = [];
            if ($result->label !== '' || $result->label !== null)  {
                $label = explode(";", $result->label);
            }
            $issue = [
                'issue' => $result->issue,
                'type' => $result->type,
                'issue_date_active' => date('Y-m-d', $this->human_to_unix($result->issue_date_active)),
                'issue_provider' => $result->issue_provider,
                'notes' => $result->notes,
                'label' => $label
            ];
            if ($result->issue_date_inactive == '0000-00-00 00:00:00') {
                $issue['issue_date_inactive'] = '';
            } else {
                $issue['issue_date_inactive'] = date('Y-m-d', $this->human_to_unix($result->issue_date_inactive));
            }
        }
        $items[] = [
            'name' => 'issue',
            'label' => 'Condition',
            'type' => 'text',
            'required' => true,
            'default_value' => $issue['issue']
        ];
        $items[] = [
            'name' => 'type',
            'label' => 'Type',
            'type' => 'select',
            'required' => true,
            'select_items' => $issue_type_arr1,
            'default_value' => $issue['type']
        ];
        $items[] = [
            'name' => 'issue_date_active',
            'label' => 'Date Active',
            'type' => 'date',
            'required' => true,
            'default_value' => $issue['issue_date_active']
        ];
        $items[] = [
            'name' => 'notes',
            'label' => 'Notes',
            'type' => 'textarea',
            'default_value' => $issue['notes']
        ];
        $items[] = [
            'name' => 'issue_date_inactive',
            'type' => 'hidden',
            'default_value' => $issue['issue_date_inactive']
        ];
        $items[] = [
            'name' => 'issue_provider',
            'type' => 'hidden',
            'required' => true,
            'default_value' => $issue['issue_provider']
        ];
        $items[] = [
            'name' => 'label[]',
            'label' => 'Sensitive Label',
            'type' => 'select',
            'select_items' => $this->fhir_scopes_sensitivities(),
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $issue['label']
        ];
        return $items;
    }

    protected function form_messaging($result, $table, $id, $subtype)
    {
        if ($id == '0') {
            $messaging = [
                'pid' => null,
                'patient_name' => null,
                'message_to' => null,
                'cc' => null,
                'message_from' => Session::get('user_id'),
                'subject' => null,
                'body' => null,
                't_messages_id' => null,
                'practice_id' => Session::get('practice_id')
            ];
            if (Session::has('messaging_patient')) {
                $new_arr = Session::get('messaging_patient');
                Session::forget('messaging_patient');
                $messaging['pid'] = $new_arr['pid'];
                $messaging['message_to'] = $new_arr['message_to'];
                $messaging['patient_name'] = $new_arr['patient_name'];
            }
            if (Session::has('session_message')) {
                $new_arr = Session::get('session_message');
                Session::forget('session_message');
                unset($new_arr['message_id']);
                $messaging = $new_arr;
            }
        } else {
            $messaging = [];
            if (Session::has('session_message')) {
                $new_arr = Session::get('session_message');
                Session::forget('session_message');
                if ($subtype == '') {
                    if ($new_arr['message_id'] == $id) {
                        unset($new_arr['message_id']);
                        $messaging = $new_arr;
                    }
                } else {
                    unset($new_arr['message_id']);
                    $messaging = $new_arr;
                }
            }
            if (count($messaging) == 0) {
                $message_to = null;
                $cc = null;
                $subject = $result->subject;
                $body = $result->body;
                $from = $result->message_from;
                if ($result->message_to !== '' || $result->message_to !== null)  {
                    $message_to = explode(";", $result->message_to);
                }
                if ($result->cc !== '' || $result->cc !== null)  {
                    $cc = explode(";", $result->cc);
                }
                if ($subtype == 'reply') {
                    $user = DB::table('users')->where('id', '=', $result->message_from)->first();
                    $message_to[] = $user->displayname . ' (' . $user->id . ')';
                    $cc = null;
                    $from = Session::get('user_id');
                    $date = date('Y-m-d', $this->human_to_unix($result->date));
                    $subject = 'Re: ' . $result->subject;
                    $body = "\n\n" . 'On ' . $date . ', ' . $user->displayname . ' (' . $user->id . ')' . ' wrote:' . "\n---------------------------------\n" . $body;
                }
                if ($subtype == 'reply_all') {
                    $old_message_to = $message_to;
                    $curr_user = Session::get('displayname') . ' (' . Session::get('user_id') . ')';
                    foreach ($old_message_to as $old_message_k => $old_message_v) {
                        if ($old_message_v ==  $curr_user) {
                            unset($old_message_to[$old_message_k]);
                        }
                    }
                    $message_to = [];
                    $user = DB::table('users')->where('id', '=', $result->message_from)->first();
                    $message_to[] = $user->displayname . ' (' . $user->id . ')';
                    $message_to = array_merge($message_to, $old_message_to);
                    $from = Session::get('user_id');
                    $date = date('Y-m-d', $this->human_to_unix($result->date));
                    $subject = 'Re: ' . $result->subject;
                    $body = "\n\n" . 'On ' . $date . ', ' . $user->displayname . ' (' . $user->id . ')' . ' wrote:' . "\n---------------------------------\n" . $body;
                }
                if ($subtype == 'forward') {
                    $from = Session::get('user_id');
                    $message_to = null;
                    $cc = null;
                    $subject = 'Fwd: ' . $result->subject;
                    $body = "\n\n" . '--------Forwarded Message--------' . "\n" . $body;
                }
                $messaging = [
                    'pid' => $result->pid,
                    'patient_name' => $result->patient_name,
                    'message_to' => $message_to,
                    'cc' => $cc,
                    'message_from' => $from,
                    'subject' => $subject,
                    'body' => $body,
                    't_messages_id' => $result->t_messages_id,
                    'practice_id' => $result->practice_id
                ];
            }
        }
        $items[] = [
            'name' => 'pid',
            'type' => 'hidden',
            'default_value' => $messaging['pid']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'default_value' => $messaging['practice_id']
        ];
        $items[] = [
            'name' => 'message_from',
            'type' => 'hidden',
            'default_value' => $messaging['message_from']
        ];
        $items[] = [
            'name' => 't_messages_id',
            'type' => 'hidden',
            'default_value' => $messaging['t_messages_id']
        ];
        $items[] = [
            'name' => 'subject',
            'label' => 'Subject',
            'type' => 'text',
            'required' => true,
            'default_value' => $messaging['subject']
        ];
        if (Session::get('group_id') != '100') {
            $items[] = [
                'name' => 'patient_name',
                'label' => 'Concerning this Patient (optional)',
                'type' => 'text',
                'readonly' => true,
                'default_value' => $messaging['patient_name']
            ];
        } else {
            $items[] = [
                'name' => 'patient_name',
                'type' => 'hidden',
                'default_value' => $messaging['patient_name']
            ];
        }
        $items[] = [
            'name' => 'message_to[]',
            'label' => 'To',
            'type' => 'select',
            'select_items' => $this->array_users_all('2'),
            'required' => true,
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $messaging['message_to']
        ];
        $items[] = [
            'name' => 'cc[]',
            'label' => 'CC',
            'type' => 'select',
            'select_items' => $this->array_users_all('2'),
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $messaging['cc']
        ];
        $items[] = [
            'name' => 'body',
            'label' => 'Message',
            'type' => 'textarea',
            'required' => true,
            'default_value' => $messaging['body']
        ];
        return $items;
    }

    protected function form_orders($result, $table, $id, $subtype)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $type_arr = [
            'orders_labs' => ['Laboratory', 'Laboratory results pending.', 'orders_labs_icd', 'Lab Test(s)', 'Laboratory'],
            'orders_radiology' => ['Imaging', 'Imaging results pending.', 'orders_radiology_icd', 'Imaging Test(s)', 'Radiology'],
            'orders_cp' => ['Cardiopulmonary', 'Cardiopulmonary results pending.', 'orders_cp_icd', 'Cardiopulmonary Test(s)', 'Cardiopulmonary'],
            'orders_referrals' => ['Referrals', 'Referral pending.', 'orders_referrals_icd', 'Referral Details', 'Referral']
        ];
        $nosh_action_arr = [
            '' => 'Save Only',
            'print_action' => 'Print',
            'print_queue' => 'Add to Print Queue'
        ];
        if ($practice->fax_type !== '') {
            $nosh_action_arr['fax_action'] = 'Fax';
            $nosh_action_arr['fax_queue'] = 'Add to Fax Queue';
        }
        if ($id == '0') {
            $orders = [
                $subtype => null,
                $subtype . '_icd' => null,
                'address_id' => null,
                'orders_completed' => 'No',
                'encounter_provider' => Session::get('encounter_provider'),
                'pid' => Session::get('pid'),
                'eid' => Session::get('eid'),
                'orders_insurance' => [],
                't_messages_id' => null,
                'id' => Session::get('user_id'),
                'orders_pending_date' => date('Y-m-d'),
                'orders_notes' => null,
            ];
            ${$subtype . '_icd'} = [];
            if ($subtype == 'orders_labs') {
                $orders[$subtype . '_obtained'] = null;
            }
        } else {
            $orders_insurance = [];
            if ($result->orders_insurance !== '' && $result->orders_insurance !== null)  {
                $orders_insurance = explode("\n", $result->orders_insurance);
            }
            ${$subtype . '_icd'} = [];
            ${$subtype . '_icd1'} = [];
            if ($result->{$subtype . '_icd'} !== '' && $result->{$subtype . '_icd'} !== null)  {
                ${$subtype . '_icd1'} = explode("\n", $result->{$subtype . '_icd'});
                foreach (${$subtype . '_icd1'} as $icd) {
                    ${$subtype . '_icd'}[$icd] = $icd;
                }
            }
            $orders = [
                $subtype => $result->{$subtype},
                $subtype . '_icd' => ${$subtype . '_icd1'},
                'address_id' => $result->address_id,
                'orders_completed' => $result->orders_completed,
                'encounter_provider' => $result->encounter_provider,
                'pid' => $result->pid,
                'eid' => $result->eid,
                'orders_insurance' => $orders_insurance,
                't_messages_id' => $result->t_messages_id,
                'id' => $result->id,
                'orders_pending_date' => date('Y-m-d', $this->human_to_unix($result->orders_pending_date)),
                'orders_notes' => $result->orders_notes
            ];
            if ($subtype == 'orders_labs') {
                $orders[$subtype . '_obtained'] = $result->{$subtype . '_obtained'};
            }
        }
        $items[] = [
            'name' => $subtype,
            'label' => $type_arr[$subtype][3],
            'type' => 'textarea',
            'required' => true,
            'default_value' => $orders[$subtype]
        ];
        $items[] = [
            'name' => $subtype . '_icd[]',
            'label' => 'Diagnosis Codes',
            'type' => 'select',
            'select_items' => ${$subtype . '_icd'},
            'multiple' => true,
            'tagsinput' => route('tagsinput_icd'),
            'default_value' => $orders[$subtype . '_icd']
        ];
        if ($subtype == 'orders_referrals') {
            $referral_specialty = null;
            $specialty_arr = [];
            $specialty_arr[''] = 'Choose Specialty';
            $specialty_query = DB::table('addressbook')->select('specialty')->distinct()->orderBy('specialty', 'asc')->get();
            if ($specialty_query->count()) {
                foreach ($specialty_query as $specialty_row) {
                    if ($specialty_row->specialty !== 'Pharmacy' && $specialty_row->specialty !== 'Laboratory' && $specialty_row->specialty !== 'Radiology' && $specialty_row->specialty !== 'Cardiopulmonary' && $specialty_row->specialty !== 'Insurance')
                        $specialty_arr[$specialty_row->specialty] = $specialty_row->specialty;
                }
            }
            if ($id !== '0') {
                $address = $address = DB::table('addressbook')->where('address_id', '=', $result->address_id)->first();
                $referral_specialty = $address->specialty;
            }
            $items[] = [
                'name' => 'referral_specialty',
                'label' => 'Specialty',
                'type' => 'select',
                'select_items' => $specialty_arr,
                'default_value' => $referral_specialty
            ];
            $items[] = [
                'name' => 'address_id',
                'label' => $type_arr[$subtype][4] . ' Provider',
                'type' => 'select',
                'required' => true,
                'select_items' => $this->array_orders_provider($type_arr[$subtype][4], 'all'),
                'default_value' => $orders['address_id']
            ];
        } else {
            $items[] = [
                'name' => 'address_id',
                'label' => $type_arr[$subtype][4] . ' Provider',
                'type' => 'select',
                'required' => true,
                'select_items' => $this->array_orders_provider($type_arr[$subtype][4]),
                'default_value' => $orders['address_id']
            ];
        }
        $items[] = [
            'name' => 'orders_pending_date',
            'label' => 'Order Pending Date',
            'type' => 'date',
            'required' => true,
            'default_value' => $orders['orders_pending_date']
        ];
        $items[] = [
            'name' => 'orders_insurance[]',
            'label' => 'Insurance',
            'type' => 'select',
            'select_items' => $this->array_insurance_active(),
            'required' => true,
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $orders['orders_insurance']
        ];
        $items[] = [
            'name' => 'orders_notes',
            'label' => 'Notes about Order',
            'type' => 'textarea',
            'default_value' => $orders['orders_notes']
        ];
        $items[] = [
            'name' => 'eid',
            'type' => 'hidden',
            'default_value' => $orders['eid']
        ];
        if (Session::get('group_id') == '2') {
            $items[] = [
                'name' => 'id',
                'type' => 'hidden',
                'default_value' => $orders['id']
            ];
        }
        $items[] = [
            'name' => 'nosh_action',
            'label' => 'Action after Saving',
            'type' => 'select',
            'select_items' => $nosh_action_arr,
        ];
        return $items;
    }

    protected function form_other_history($result, $table, $id, $subtype)
    {
        $patient = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        if ($subtype == 'lifestyle') {
            $lifestyle = [
                'oh_sh' => $result->oh_sh,
                'oh_diet' => $result->oh_diet,
                'oh_physical_activity' => $result->oh_physical_activity,
                'oh_employment' => $result->oh_employment,
                'sexuallyactive' => $patient->sexuallyactive
            ];
            $items[] = [
                'name' => 'oh_sh',
                'label' => 'Social History',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $lifestyle['oh_sh']
            ];
            $items[] = [
                'name' => 'sexuallyactive',
                'label' => 'Sexually Active',
                'type' => 'select',
                'select_items' => ['no' => 'No', 'yes' => 'Yes'],
                'default_value' => $lifestyle['sexuallyactive']
            ];
            $items[] = [
                'name' => 'oh_diet',
                'label' => 'Diet',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $lifestyle['oh_diet']
            ];
            $items[] = [
                'name' => 'oh_physical_activity',
                'label' => 'Physical Activity',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $lifestyle['oh_physical_activity']
            ];
            $items[] = [
                'name' => 'oh_employment',
                'label' => 'Employment/School',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $lifestyle['oh_employment']
            ];
        }
        if ($subtype == 'habits') {
            $habits = [
                'oh_etoh' => $result->oh_etoh,
                'oh_tobacco' => $result->oh_tobacco,
                'oh_drugs' => $result->oh_drugs,
                'tobacco' => $patient->tobacco
            ];
            $items[] = [
                'name' => 'oh_etoh',
                'label' => 'Alcohol Use',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $habits['oh_etoh']
            ];
            $items[] = [
                'name' => 'tobacco',
                'label' => 'Tobacco Use',
                'type' => 'select',
                'select_items' => ['no' => 'No', 'yes' => 'Yes'],
                'default_value' => $habits['tobacco']
            ];
            $items[] = [
                'name' => 'oh_tobacco',
                'label' => 'Tobacco Use Details',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $habits['oh_tobacco']
            ];
            $items[] = [
                'name' => 'oh_drugs',
                'label' => 'Illicit Drug Use',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $habits['oh_drugs']
            ];
        }
        if ($subtype == 'mental_health') {
            $mental_health = [
                'oh_psychosocial' => $result->oh_psychosocial,
                'oh_developmental' => $result->oh_developmental,
                'oh_medtrials' => $result->oh_medtrials
            ];
            $items[] = [
                'name' => 'oh_psychosocial',
                'label' => 'Psychosocial History',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $mental_health['oh_psychosocial']
            ];
            $items[] = [
                'name' => 'oh_developmental',
                'label' => 'Developmental History',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $mental_health['oh_developmental']
            ];
            $items[] = [
                'name' => 'oh_medtrials',
                'label' => 'Past Medication Trials',
                'type' => 'textarea',
                'textarea_short' => true,
                'default_value' => $mental_health['oh_developmental']
            ];
        }
        return $items;
    }

    protected function form_practiceinfo($result, $table, $id, $subtype)
    {
        if ($subtype == 'information') {
            $info_arr = [
                'practice_name' => $result->practice_name,
                'street_address1' => $result->street_address1,
                'street_address2' => $result->street_address2,
                'country' => $result->country,
                'city' => $result->city,
                'state' => $result->state,
                'zip' => $result->zip,
                'phone' => $result->phone,
                'fax' => $result->fax,
                'email' => $result->email,
                'website' => $result->website,
                'smtp_user' => $result->smtp_user,
                'patient_portal' => $result->patient_portal
            ];
            if (Session::get('patient_centric') == 'n') {
                $items[] = [
                    'name' => 'practice_name',
                    'label' => 'Practice Name',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $info_arr['practice_name']
                ];
            } else {
                $items[] = [
                    'name' => 'practice_name',
                    'label' => 'Practice Name',
                    'type' => 'text',
                    'readonly' => true,
                    'default_value' => $info_arr['practice_name']
                ];
            }
            $items[] = [
                'name' => 'street_address1',
                'label' => 'Street Address',
                'type' => 'text',
                'required' => true,
                'default_value' => $info_arr['street_address1']
            ];
            $items[] = [
                'name' => 'street_address2',
                'label' => 'Street Address Line 2',
                'type' => 'text',
                'default_value' => $info_arr['street_address2']
            ];
            $items[] = [
                'name' => 'country',
                'label' => 'Country',
                'type' => 'select',
                'select_items' => $this->array_country(),
                'default_value' => $info_arr['country'],
                'class' => 'country'
            ];
            $items[] = [
                'name' => 'city',
                'label' => 'City',
                'type' => 'text',
                'required' => true,
                'default_value' => $info_arr['city']
            ];
            $items[] = [
                'name' => 'state',
                'label' => 'State',
                'type' => 'select',
                'select_items' => $this->array_states($info_arr['country']),
                'required' => true,
                'default_value' => $info_arr['state'],
                'class' => 'state'
            ];
            $items[] = [
                'name' => 'zip',
                'label' => 'Zip',
                'type' => 'text',
                'required' => true,
                'default_value' => $info_arr['zip']
            ];
            if (Session::get('patient_centric') == 'n') {
                $items[] = [
                    'name' => 'phone',
                    'label' => 'Phone',
                    'type' => 'text',
                    'required' => true,
                    'phone' => true,
                    'default_value' => $info_arr['phone']
                ];
                $items[] = [
                    'name' => 'fax',
                    'label' => 'Fax',
                    'type' => 'text',
                    'required' => true,
                    'phone' => true,
                    'default_value' => $info_arr['fax']
                ];
            } else {
                $items[] = [
                    'name' => 'phone',
                    'label' => 'Phone',
                    'type' => 'text',
                    'phone' => true,
                    'default_value' => $info_arr['phone']
                ];
                $items[] = [
                    'name' => 'fax',
                    'label' => 'Fax',
                    'type' => 'text',
                    'phone' => true,
                    'default_value' => $info_arr['fax']
                ];
            }
            $items[] = [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'default_value' => $info_arr['email']
            ];
            $items[] = [
                'name' => 'website',
                'label' => 'Website',
                'type' => 'text',
                'default_value' => $info_arr['website']
            ];
            if (Session::get('practice_id') == '1') {
                $items[] = [
                    'name' => 'smtp_user',
                    'label' => 'Gmail username for sending e-mail',
                    'type' => 'text',
                    'default_value' => $info_arr['smtp_user']
                ];
                $items[] = [
                    'name' => 'patient_portal',
                    'label' => 'Patient Portal Web Address',
                    'type' => 'text',
                    'default_value' => $info_arr['patient_portal']
                ];
            }
        }
        if ($subtype == 'settings') {
            $encounter_type_arr = $this->array_encounter_type();
            // Remove depreciated encounter types for new encounters
            unset($encounter_type_arr['standardmedical']);
            unset($encounter_type_arr['standardmedical1']);
            $settings_arr = [
                'primary_contact' => $result->primary_contact,
                'npi' => $result->npi,
                'medicare' => $result->medicare,
                'tax_id' => $result->tax_id,
                'default_pos_id' => $result->default_pos_id,
                'documents_dir' => $result->documents_dir,
                'weight_unit' => $result->weight_unit,
                'height_unit' => $result->height_unit,
                'temp_unit' => $result->temp_unit,
                'hc_unit' => $result->hc_unit,
                'encounter_template' => $result->encounter_template,
                'additional_message' => $result->additional_message,
                'reminder_interval' => $result->reminder_interval
            ];
            $items[] = [
                'name' => 'primary_contact',
                'label' => 'Primary Contact',
                'type' => 'text',
                'default_value' => $settings_arr['primary_contact']
            ];
            $items[] = [
                'name' => 'npi',
                'label' => 'Practice NPI',
                'type' => 'text',
                'default_value' => $settings_arr['npi']
            ];
            $items[] = [
                'name' => 'medicare',
                'label' => 'Practice Medicare Number',
                'type' => 'text',
                'default_value' => $settings_arr['medicare']
            ];
            $items[] = [
                'name' => 'tax_id',
                'label' => 'Practice Tax ID Number',
                'type' => 'text',
                'default_value' => $settings_arr['tax_id']
            ];
            $items[] = [
                'name' => 'default_pos_id',
                'label' => 'Default Practice Location',
                'type' => 'select',
                'select_items' => $this->array_pos(),
                'default_value' => $settings_arr['default_pos_id']
            ];
            if (Session::get('practice_id') == '1') {
                $items[] = [
                    'name' => 'documents_dir',
                    'label' => 'Documents Directory',
                    'type' => 'text',
                    'required' => true,
                    'default_value' => $settings_arr['documents_dir']
                ];
            } else {
                $items[] = [
                    'name' => 'documents_dir',
                    'label' => 'Documents Directory',
                    'type' => 'text',
                    'required' => true,
                    'readonly' => true,
                    'default_value' => $settings_arr['documents_dir']
                ];
            }
            $items[] = [
                'name' => 'weight_unit',
                'label' => 'Weight Unit',
                'type' => 'select',
                'select_items' => ['lbs' => 'Pounds', 'kg' => 'Kilograms'],
                'required' => true,
                'default_value' => $settings_arr['weight_unit']
            ];
            $items[] = [
                'name' => 'height_unit',
                'label' => 'Height Unit',
                'type' => 'select',
                'select_items' => ['in' => 'Inches', 'cm' => 'Centimeters'],
                'required' => true,
                'default_value' => $settings_arr['height_unit']
            ];
            $items[] = [
                'name' => 'temp_unit',
                'label' => 'Temperature Unit',
                'type' => 'select',
                'select_items' => ['F' => 'Fahrenheit', 'C' => 'Celcius'],
                'required' => true,
                'default_value' => $settings_arr['temp_unit']
            ];
            $items[] = [
                'name' => 'hc_unit',
                'label' => 'Head Circumference Unit',
                'type' => 'select',
                'select_items' => ['in' => 'Inches', 'cm' => 'Centimeters'],
                'required' => true,
                'default_value' => $settings_arr['hc_unit']
            ];
            $items[] = [
                'name' => 'encounter_template',
                'label' => 'Default Encounter Template',
                'type' => 'select',
                'select_items' => $encounter_type_arr,
                'required' => true,
                'default_value' => $settings_arr['encounter_template']
            ];
            $items[] = [
                'name' => 'additional_message',
                'label' => 'Additional Message for Appointment Reminders',
                'type' => 'textarea',
                'default_value' => $settings_arr['additional_message']
            ];
            $items[] = [
                'name' => 'reminder_interval',
                'label' => 'Appointment Reminder Interval',
                'type' => 'select',
                'select_items' => $this->array_reminder_interval(),
                'default_value' => $settings_arr['reminder_interval']
            ];
        }
        if ($subtype == 'billing') {
            $billing_arr = [
                'billing_street_address1' => $result->billing_street_address1,
                'billing_street_address2' => $result->billing_street_address2,
                'billing_country' => $result->billing_country,
                'billing_city' => $result->billing_city,
                'billing_state' => $result->billing_state,
                'billing_zip' => $result->billing_zip,
            ];
            $items[] = [
                'name' => 'billing_street_address1',
                'label' => 'Street Address',
                'type' => 'text',
                'required' => true,
                'default_value' => $billing_arr['billing_street_address1']
            ];
            $items[] = [
                'name' => 'billing_street_address2',
                'label' => 'Street Address Line 2',
                'type' => 'text',
                'default_value' => $billing_arr['billing_street_address2']
            ];
            $items[] = [
                'name' => 'billing_country',
                'label' => 'Country',
                'type' => 'select',
                'select_items' => $this->array_country(),
                'default_value' => $billing_arr['country'],
                'class' => 'country'
            ];
            $items[] = [
                'name' => 'billing_city',
                'label' => 'City',
                'type' => 'text',
                'required' => true,
                'default_value' => $billing_arr['billing_city']
            ];
            $items[] = [
                'name' => 'billing_state',
                'label' => 'State',
                'type' => 'select',
                'select_items' => $this->array_states($billing_arr['country']),
                'required' => true,
                'default_value' => $billing_arr['billing_state'],
                'class' => 'state'
            ];
            $items[] = [
                'name' => 'billing_zip',
                'label' => 'Zip',
                'type' => 'text',
                'required' => true,
                'default_value' => $billing_arr['billing_zip']
            ];
        }
        if ($subtype == 'extensions') {
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
                'fax_type' => $result->fax_type,
                'phaxio_api_key' => $result->phaxio_api_key,
                'phaxio_api_secret' => $result->phaxio_api_secret,
                'birthday_extension' => $result->birthday_extension,
                'birthday_message' => $result->birthday_message,
                'appointment_extension' => $result->appointment_extension,
                'appointment_interval' => $result->appointment_interval,
                'appointment_message' => $result->appointment_message,
                'sms_url' => $result->sms_url
            ];
            $items[] = [
                'name' => 'fax_type',
                'label' => 'Fax Integration Enabled',
                'type' => 'select',
                'select_items' => ['' => 'None', 'phaxio' => 'Phaxio'],
                'default_value' => $extensions_arr['fax_type']
            ];
            $items[] = [
                'name' => 'phaxio_api_key',
                'label' => 'Phaxio API Key',
                'type' => 'text',
                'default_value' => $extensions_arr['phaxio_api_key']
            ];
            $items[] = [
                'name' => 'phaxio_api_secret',
                'label' => 'Phaxio API Secret',
                'type' => 'text',
                'default_value' => $extensions_arr['phaxio_api_secret']
            ];
            $items[] = [
                'name' => 'birthday_extension',
                'label' => 'Birthday Message Enabled',
                'type' => 'select',
                'select_items' => ['n' => 'No','y' => 'Yes'],
                'default_value' => $extensions_arr['birthday_extension']
            ];
            $items[] = [
                'name' => 'birthday_message',
                'label' => 'Birthday Message',
                'type' => 'textarea',
                'default_value' => $extensions_arr['birthday_message']
            ];
            $items[] = [
                'name' => 'appointment_extension',
                'label' => 'Appointment Reminder Enabled',
                'type' => 'select',
                'select_items' => ['n' => 'No','y' => 'Yes'],
                'default_value' => $extensions_arr['appointment_extension']
            ];
            $items[] = [
                'name' => 'appointment_interval',
                'label' => 'Appointment Interval (minimum time lapsed from last appointment)',
                'type' => 'select',
                'select_items' => $appt_arr,
                'default_value' => $extensions_arr['appointment_interval']
            ];
            $items[] = [
                'name' => 'appointment_message',
                'label' => 'Continuing Care Reminder Message',
                'type' => 'textarea',
                'default_value' => $extensions_arr['appointment_message']
            ];
            $items[] = [
                'name' => 'sms_url',
                'label' => 'SMS URL',
                'type' => 'text',
                'default_value' => $extensions_arr['sms_url']
            ];
        }
        if ($subtype == 'schedule') {
            $schedule = [
                'weekends' => $result->weekends,
                'minTime' => $result->minTime,
                'maxTime' => $result->maxTime,
                'sun_o' => $result->sun_o,
                'sun_c' => $result->sun_c,
                'mon_o' => $result->mon_o,
                'mon_c' => $result->mon_c,
                'tue_o' => $result->tue_o,
                'tue_c' => $result->tue_c,
                'wed_o' => $result->wed_o,
                'wed_c' => $result->wed_c,
                'thu_o' => $result->thu_o,
                'thu_c' => $result->thu_c,
                'fri_o' => $result->fri_o,
                'fri_c' => $result->fri_c,
                'sat_o' => $result->sat_o,
                'sat_c' => $result->sat_c,
                'timezone' => $result->timezone
            ];
            $items[] = [
                'name' => 'weekends',
                'label' => 'Include Weekends in the Schedule',
                'type' => 'select',
                'select_items' => ['0' => 'No', '1' => 'Yes'],
                'default_value' => $schedule['weekends']
            ];
            $items[] = [
                'name' => 'minTime',
                'label' => 'First hour/time that will be displayed on the schedule',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['minTime']
            ];
            $items[] = [
                'name' => 'maxTime',
                'label' => 'Last hour/time that will be displayed on the schedule',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['maxTime']
            ];
            $items[] = [
                'name' => 'timezone',
                'label' => 'Timezone',
                'type' => 'text',
                'default_value' => $schedule['timezone']
            ];
            $items[] = [
                'name' => 'mon_o',
                'label' => 'Monday open at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['mon_o']
            ];
            $items[] = [
                'name' => 'mon_c',
                'label' => 'Monday close at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['mon_c']
            ];
            $items[] = [
                'name' => 'tue_o',
                'label' => 'Tuesday open at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['tue_o']
            ];
            $items[] = [
                'name' => 'tue_c',
                'label' => 'Tuesday close at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['tue_c']
            ];
            $items[] = [
                'name' => 'wed_o',
                'label' => 'Wednesday open at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['wed_o']
            ];
            $items[] = [
                'name' => 'wed_c',
                'label' => 'Wednesday close at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['wed_c']
            ];
            $items[] = [
                'name' => 'thu_o',
                'label' => 'Thursday open at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['thu_o']
            ];
            $items[] = [
                'name' => 'thu_c',
                'label' => 'Thursday close at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['thu_c']
            ];
            $items[] = [
                'name' => 'fri_o',
                'label' => 'Friday open at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['fri_o']
            ];
            $items[] = [
                'name' => 'fri_c',
                'label' => 'Friday close at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['fri_c']
            ];
            $items[] = [
                'name' => 'sat_o',
                'label' => 'Saturday open at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['sat_o']
            ];
            $items[] = [
                'name' => 'sat_c',
                'label' => 'Saturday close at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['sat_c']
            ];
            $items[] = [
                'name' => 'sun_o',
                'label' => 'Sunday open at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['sun_o']
            ];
            $items[] = [
                'name' => 'sun_c',
                'label' => 'Sunday close at',
                'type' => 'text',
                'time' => true,
                'default_value' => $schedule['sun_c']
            ];
        }
        return $items;
    }

    protected function form_providers($result, $table, $id, $subtype)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $provider = [
            'specialty' => $result->specialty,
            'license' => $result->license,
            'license_country' => $result->license_country,
            'license_state' => $result->license_state,
            'npi' => $result->npi,
            'npi_taxonomy' => $result->npi_taxonomy,
            'upin' => $result->upin,
            'dea' => $result->dea,
            'medicare' => $result->medicare,
            'tax_id' => $result->tax_id,
            'rcopia_username' => $result->rcopia_username,
            'schedule_increment' => $result->schedule_increment,
            'peacehealth_id' => $result->peacehealth_id
        ];
        $items[] = [
            'name' => 'specialty',
            'label' => 'Specialty',
            'type' => 'text',
            'default_value' => $provider['specialty']
        ];
        $items[] = [
            'name' => 'license',
            'label' => 'License Number',
            'type' => 'text',
            'default_value' => $provider['license']
        ];
        $items[] = [
            'name' => 'license_country',
            'label' => 'Country',
            'type' => 'select',
            'select_items' => $this->array_country(),
            'default_value' => $provider['license_country']
        ];
        $items[] = [
            'name' => 'license_state',
            'label' => 'State Licensed',
            'type' => 'select',
            'select_items' => $this->array_states($provider['license_country']),
            'default_value' => $provider['license_state']
        ];
        $items[] = [
            'name' => 'npi',
            'label' => 'NPI',
            'type' => 'text',
            'default_value' => $provider['npi']
        ];
        $items[] = [
            'name' => 'npi_taxonomy',
            'label' => 'NPI Taxonomy',
            'type' => 'text',
            'readonly' => true,
            'default_value' => $provider['npi_taxonomy']
        ];
        $items[] = [
            'name' => 'upin',
            'label' => 'UPIN',
            'type' => 'text',
            'default_value' => $provider['upin']
        ];
        $items[] = [
            'name' => 'dea',
            'label' => 'DEA Number',
            'type' => 'text',
            'default_value' => $provider['dea']
        ];
        $items[] = [
            'name' => 'medicare',
            'label' => 'Medicare Number',
            'type' => 'text',
            'default_value' => $provider['medicare']
        ];
        $items[] = [
            'name' => 'tax_id',
            'label' => 'Tax ID Number',
            'type' => 'text',
            'default_value' => $provider['tax_id']
        ];
        $items[] = [
            'name' => 'peacehealth_id',
            'label' => 'PeaceHealth ID Number',
            'type' => 'text',
            'default_value' => $provider['peacehealth_id']
        ];
        if ($practice->rcopia_extension == 'y') {
            $items[] = [
                'name' => 'rcopia_username',
                'label' => 'rCopia Username',
                'type' => 'text',
                'default_value' => $provider['rcopia_username']
            ];
        }
        $items[] = [
            'name' => 'schedule_increment',
            'label' => 'Time increment for schedule (minutes)',
            'type' => 'text',
            'default_value' => $provider['schedule_increment']
        ];
        return $items;
    }

    protected function form_recipients($result, $table, $id, $subtype)
    {
        if ($id == '0') {
            $messaging = [
                'faxrecipient' => null,
                'faxnumber' => null,
                'job_id' => $subtype
            ];
        } else {
            $messaging = [
                'faxrecipient' => $result->faxrecipient,
                'faxnumber' => $result->faxnumber,
                'job_id' => $result->job_id
            ];
        }
        $items[] = [
            'name' => 'faxrecipient',
            'label' => 'Recipient',
            'type' => 'text',
            'required' => true,
            'default_value' => $messaging['faxrecipient']
        ];
        $items[] = [
            'name' => 'faxnumber',
            'label' => 'Fax Number',
            'type' => 'text',
            'phone' => true,
            'required' => true,
            'default_value' => $messaging['faxnumber']
        ];
        $items[] = [
            'name' => 'job_id',
            'type' => 'hidden',
            'default_value' => $messaging['job_id']
        ];
        return $items;
    }

    protected function form_repeat_schedule($result, $table, $id, $subtype)
    {
        $providers_arr['0'] = 'All Providers';
        $providers_arr = $providers_arr + $this->array_providers();
        $day_arr = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday'
        ];
        if ($id == '0') {
            $data = [
                'repeat_day' => null,
                'repeat_start_time' => null,
                'repeat_end_time' => null,
                'title' => null,
                'reason' => null,
                'provider_id' => null
            ];
            if ($subtype !== '0') {
                $data['provider_id'] = $subtype;
            }
        } else {
            $data = [
                'repeat_day' => $result->repeat_day,
                'repeat_start_time' => $result->repeat_start_time,
                'repeat_end_time' => $result->repeat_end_time,
                'title' => $result->title,
                'reason' => $result->reason,
                'provider_id' => $result->provider_id
            ];
        }
        $items[] = [
            'name' => 'repeat_day',
            'label' => 'Day',
            'type' => 'select',
            'select_items' => $day_arr,
            'required' => true,
            'default_value' => $data['repeat_day']
        ];
        $items[] = [
            'name' => 'repeat_start_time',
            'label' => 'Start Time',
            'type' => 'text',
            'time' => true,
            'required' => true,
            'default_value' => $data['repeat_start_time']
        ];
        $items[] = [
            'name' => 'repeat_end_time',
            'label' => 'End Time',
            'type' => 'text',
            'time' => true,
            'required' => true,
            'default_value' => $data['repeat_end_time']
        ];
        $items[] = [
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'required' => true,
            'default_value' => $data['title']
        ];
        $items[] = [
            'name' => 'reason',
            'label' => 'Reason',
            'type' => 'text',
            'required' => true,
            'default_value' => $data['reason']
        ];
        $items[] = [
            'name' => 'provider_id',
            'label' => 'Provider',
            'type' => 'select',
            'select_items' => $providers_arr,
            'required' => true,
            'default_value' => $data['provider_id']
        ];
        return $items;
    }

    protected function form_rx_list($result, $table, $id, $subtype)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $nosh_action_arr = [
            'electronic_sign' => 'Electronically Sign',
            'print_action' => 'Print',
            'print_queue,single' => 'Add to Print Queue',
            'print_queue,combined' => 'Add to Print Queue, Combined In One Page'
        ];
        if ($practice->fax_type !== '') {
            $nosh_action_arr['fax_action'] = 'Fax';
            $nosh_action_arr['fax_queue,single'] = 'Add to Fax Queue';
            $nosh_action_arr['fax_queue,combined'] = 'Add to Fax Queue, Combined In One Page';
        }
        $edit = $this->access_level('3');
        $rxl_provider = null;
        $user_id = null;
        if ($edit) {
            $rxl_provider = Session::get('displayname');
            $user_id = Session::get('user_id');
        } else {
            if ($id !== '0') {
                $rxl_provider = $result->rxl_provider;
                $user_id = $result->id;
            }
        }
        if ($id == '0') {
            if ($subtype !== '') {
                if ($edit) {
                    $rxl_provider = Session::get('encounter_provider');
                    $user_id = Session::get('user_id');
                }
            }
            $rx = [
                'rxl_medication' => null,
                'rxl_dosage' => null,
                'rxl_dosage_unit' => null,
                'rxl_sig' => null,
                'rxl_route' => 'Oral route',
                'rxl_frequency' => null,
                'rxl_instructions' => null,
                'rxl_reason' => null,
                'rxl_date_active' => date('Y-m-d'),
                'rxl_date_prescribed' => '',
                'rxl_date_inactive' => '',
                'rxl_date_old' => '',
                'rxl_provider' => $rxl_provider,
                'id' => $user_id,
                'rxl_ndcid' => null,
                'label' => null
            ];
            if ($subtype !== '') {
                $rx['rxl_quantity'] = null;
                $rx['rxl_refill'] = null;
                $rx['rxl_days'] = null;
                $rx['daw'] = '';
                $rx['dea'] = '';
                $rx['rxl_date_prescribed'] = date('Y-m-d');
                $rx['address_id'] = null;
                $patient = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
                if ($patient->pharmacy_address_id !== '' || $patient->pharmacy_address_id !== null) {
                    $rx['address_id'] = $patient->pharmacy_address_id;
                }
            }
            if (Session::has('ccda')) {
                $ccda = Session::get('ccda');
                Session::forget('ccda');
                $rx['rxl_medication'] = $ccda['name'];
                $rx['rxl_dosage'] = $ccda['dosage'];
                $rx['rxl_dosage_unit'] = $ccda['dosage-unit'];
                $rx['rxl_route'] = $ccda['route'];
                $rx['rxl_reason'] = $ccda['reason'];
                $rx['rxl_ndcid'] = $ccda['code'];
                $rx['rxl_date_active'] = date('Y-m-d', $this->human_to_unix($ccda['date']));
                $rx['rxl_instructions'] = $ccda['administration'];
                if (isset($ccda['from'])) {
                    $rx['rxl_instructions'] .= '; Obtained via FHIR from ' . $ccda['from'];
                }
            }
        } else {
            if ($subtype !== '') {
                if ($edit) {
                    $rxl_provider = Session::get('encounter_provider');
                    $user_id = Session::get('user_id');
                }
            }
            $old_route = [
                'by mouth' => 'Oral route',
                'per rectum' => 'Per rectum',
                'transdermal' => 'Transdermal route',
                'subcutaneously' => 'Subcutaneous route',
                'intramuscularly' => 'Intramuscular route',
                'intravenously' => 'Intravenous peripheral route'
            ];
            $rxl_route = $result->rxl_route;
            if (isset($old_route[$result->rxl_route]) || array_key_exists($result->rxl_route, $old_route)) {
                $rxl_route = $old_route[$result->rxl_route];
            }
            $label = [];
            if ($result->label !== '' || $result->label !== null)  {
                $label = explode(";", $result->label);
            }
            $rx = [
                'rxl_medication' => $result->rxl_medication,
                'rxl_dosage' => $result->rxl_dosage,
                'rxl_dosage_unit' => $result->rxl_dosage_unit,
                'rxl_sig' => $result->rxl_sig,
                'rxl_route' => $rxl_route,
                'rxl_frequency' => $result->rxl_frequency,
                'rxl_instructions' => $result->rxl_instructions,
                'rxl_reason' => $result->rxl_reason,
                'rxl_date_active' => date('Y-m-d', strtotime($result->rxl_date_active)),
                'rxl_provider' => $rxl_provider,
                'id' => $user_id,
                'rxl_ndcid' => $result->rxl_ndcid,
                'label' => $label
            ];
            if ($result->rxl_date_inactive == '0000-00-00 00:00:00') {
                $rx['rxl_date_inactive'] = '';
            } else {
                $rx['rxl_date_inactive'] = date('Y-m-d', $this->human_to_unix($result->rxl_date_inactive));
            }
            if ($subtype !== '') {
                $rx['rxl_date_prescribed'] = date('Y-m-d');
            } else {
                if ($result->rxl_date_prescribed == '0000-00-00 00:00:00') {
                    $rx['rxl_date_prescribed'] = '';
                } else {
                    $rx['rxl_date_prescribed'] = date('Y-m-d', $this->human_to_unix($result->rxl_date_prescribed));
                }
            }
            if ($result->rxl_date_old == '0000-00-00 00:00:00') {
                $rx['rxl_date_old'] = '';
            } else {
                $rx['rxl_date_old'] = date('Y-m-d', $this->human_to_unix($result->rxl_date_old));
            }
            $data['panel_header'] = 'Edit Medication';
            if ($subtype !== '') {
                $rx['rxl_quantity'] = $result->rxl_quantity;
                $rx['rxl_refill'] = $result->rxl_refill;
                $rx['rxl_days'] = $result->rxl_days;
                $rx['daw'] = '';
                $rx['dea'] = '';
                if ($result->rxl_daw !== '' && $result->rxl_daw !== null) {
                    $rx['daw'] = 'Yes';
                }
                if ($result->rxl_dea !== ''&& $result->rxl_dea !== null) {
                    $rx['dea'] = 'Yes';
                }
                $rx['address_id'] = $result->address_id;
            }
        }
        $items[] = [
            'name' => 'rxl_medication',
            'label' => 'Medication',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'rxl_medication']),
            'default_value' => $rx['rxl_medication']
        ];
        $items[] = [
            'name' => 'rxl_dosage',
            'label' => 'Dosage',
            'type' => 'text',
            'required' => true,
            'default_value' => $rx['rxl_dosage']
        ];
        $items[] = [
            'name' => 'rxl_dosage_unit',
            'label' => 'Dosage Unit',
            'type' => 'text',
            'required' => true,
            'default_value' => $rx['rxl_dosage_unit']
        ];
        $items[] = [
            'name' => 'rxl_sig',
            'label' => 'Sig',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'rxl_sig']),
            'default_value' => $rx['rxl_sig']
        ];
        $items[] = [
            'name' => 'rxl_route',
            'label' => 'Route',
            'type' => 'select',
            'select_items' => $this->array_route(),
            'default_value' => $rx['rxl_route']
        ];
        $items[] = [
            'name' => 'rxl_frequency',
            'label' => 'Frequency',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'rxl_frequency']),
            'default_value' => $rx['rxl_frequency']
        ];
        $items[] = [
            'name' => 'rxl_instructions',
            'label' => 'Special Instructions',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'rxl_instructions']),
            'default_value' => $rx['rxl_instructions']
        ];
        $items[] = [
            'name' => 'rxl_reason',
            'label' => 'Reason',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'rxl_reason']),
            'default_value' => $rx['rxl_reason']
        ];
        $items[] = [
            'name' => 'rxl_date_active',
            'label' => 'Date Active',
            'type' => 'date',
            'required' => true,
            'default_value' => $rx['rxl_date_active']
        ];
        $items[] = [
            'name' => 'rxl_ndcid',
            'label' => 'NDC ID',
            'type' => 'text',
            'readonly' => true,
            'default_value' => $rx['rxl_ndcid']
        ];
        $items[] = [
            'name' => 'label[]',
            'label' => 'Sensitive Label',
            'type' => 'select',
            'select_items' => $this->fhir_scopes_sensitivities(),
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $rx['label']
        ];
        $items[] = [
            'name' => 'rxl_date_inactive',
            'type' => 'hidden',
            'default_value' => $rx['rxl_date_inactive']
        ];
        $items[] = [
            'name' => 'rxl_provider',
            'type' => 'hidden',
            'required' => true,
            'default_value' => $rx['rxl_provider']
        ];
        $items[] = [
            'name' => 'rxl_date_prescribed',
            'type' => 'hidden',
            'default_value' => $rx['rxl_date_prescribed']
        ];
        $items[] = [
            'name' => 'rxl_date_old',
            'type' => 'hidden',
            'default_value' => $rx['rxl_date_old']
        ];
        if ($subtype !== '') {
            $items[] = [
                'name' => 'rxl_days',
                'label' => 'Duration (days)',
                'type' => 'text',
                'default_value' => $rx['rxl_days']
            ];
            $items[] = [
                'name' => 'rxl_quantity',
                'label' => 'Quantity',
                'type' => 'text',
                'required' => true,
                'default_value' => $rx['rxl_quantity']
            ];
            $items[] = [
                'name' => 'rxl_refill',
                'label' => 'Refills',
                'type' => 'text',
                'default_value' => $rx['rxl_refill']
            ];
            $items[] = [
                'name' => 'daw',
                'label' => 'Dispense As Written',
                'type' => 'checkbox',
                'value' => 'Yes',
                'default_value' => $rx['daw']
            ];
            $items[] = [
                'name' => 'dea',
                'label' => 'DEA Number on Prescription',
                'type' => 'checkbox',
                'value' => 'Yes',
                'default_value' => $rx['dea']
            ];
            $items[] = [
                'name' => 'id',
                'type' => 'hidden',
                'default_value' => $rx['id']
            ];
            $items[] = [
                'name' => 'address_id',
                'label' => 'Pharmacy to Send',
                'type' => 'select',
                'select_items' => $this->array_orders_provider('Pharmacy'),
                'default_value' => $rx['address_id']
            ];
            $patient = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
            if (route('dashboard') == 'https://shihjay.xyz/nosh') {
                $notification = null;
                if (Session::get('user_id') == '3') {
                    $notification = $patient->reminder_to;
                }
                $items[] = [
                    'name' => 'notification',
                    'label' => 'Notification To (SMS or Email)',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Prescription notice will be sent to this number and will not be saved or used for any other purpose.',
                    'default_value' => $notification
                ];
            } else {
                $items[] = [
                    'name' => 'notification',
                    'label' => 'Notification To (SMS or Email)',
                    'type' => 'text',
                    'default_value' => $patient->reminder_to
                ];
            }
            $items[] = [
                'name' => 'nosh_action',
                'label' => 'Action after Saving',
                'type' => 'select',
                'select_items' => $nosh_action_arr,
                'required' => true
            ];
        }
        return $items;
    }

    protected function form_sup_list($result, $table, $id, $subtype)
    {
        $nosh_action_arr = [
            '' => 'Do Nothing',
            'inventory' => 'Pull from Supplements Inventory'
        ];
        if ($id == '0') {
            $sup = [
                'sup_supplement' => null,
                'sup_dosage' => null,
                'sup_dosage_unit' => null,
                'sup_sig' => null,
                'sup_route' => 'Oral route',
                'sup_frequency' => null,
                'sup_instructions' => null,
                'sup_reason' => null,
                'sup_date_active' => date('Y-m-d'),
                'sup_date_inactive' => '',
                'sup_provider' => Session::get('displayname'),
                'id' => Session::get('user_id'),
                'supplement_id' => null
            ];
        } else {
            $old_route = [
                'by mouth' => 'Oral route',
                'per rectum' => 'Per rectum',
                'transdermal' => 'Transdermal route',
                'subcutaneously' => 'Subcutaneous route',
                'intramuscularly' => 'Intramuscular route',
                'intravenously' => 'Intravenous peripheral route'
            ];
            $sup_route = $result->sup_route;
            if (isset($old_route[$result->sup_route]) || array_key_exists($result->sup_route, $old_route)) {
                $sup_route = $old_route[$result->sup_route];
            }
            $sup = [
                'sup_supplement' => $result->sup_supplement,
                'sup_dosage' => $result->sup_dosage,
                'sup_dosage_unit' => $result->sup_dosage_unit,
                'sup_sig' => $result->sup_sig,
                'sup_route' => $sup_route,
                'sup_frequency' => $result->sup_frequency,
                'sup_instructions' => $result->sup_instructions,
                'sup_reason' => $result->sup_reason,
                'sup_date_active' => date('Y-m-d', strtotime($result->sup_date_active)),
                'sup_provider' => Session::get('displayname'),
                'id' => Session::get('user_id'),
                'supplement_id' => $result->supplement_id
            ];
            if ($result->sup_date_inactive == '0000-00-00 00:00:00') {
                $sup['sup_date_inactive'] = '';
            } else {
                $sup['sup_date_inactive'] = date('Y-m-d', $this->human_to_unix($result->sup_date_inactive));
            }
        }
        $items[] = [
            'name' => 'sup_supplement',
            'label' => 'Supplement',
            'type' => 'text',
            'required' => true,
            'default_value' => $sup['sup_supplement']
        ];
        $items[] = [
            'name' => 'sup_dosage',
            'label' => 'Dosage',
            'type' => 'text',
            'required' => true,
            'default_value' => $sup['sup_dosage']
        ];
        $items[] = [
            'name' => 'sup_dosage_unit',
            'label' => 'Dosage Unit',
            'type' => 'text',
            'required' => true,
            'default_value' => $sup['sup_dosage_unit']
        ];
        $items[] = [
            'name' => 'sup_sig',
            'label' => 'Sig',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'sup_sig']),
            'default_value' => $sup['sup_sig']
        ];
        $items[] = [
            'name' => 'sup_route',
            'label' => 'Route',
            'type' => 'select',
            'select_items' => $this->array_route(),
            'default_value' => $sup['sup_route']
        ];
        $items[] = [
            'name' => 'sup_frequency',
            'label' => 'Frequency',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'sup_frequency']),
            'default_value' => $sup['sup_frequency']
        ];
        $items[] = [
            'name' => 'sup_instructions',
            'label' => 'Special Instructions',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'sup_instructions']),
            'default_value' => $sup['sup_instructions']
        ];
        $items[] = [
            'name' => 'sup_reason',
            'label' => 'Reason',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'sup_reason']),
            'default_value' => $sup['sup_reason']
        ];
        $items[] = [
            'name' => 'sup_date_active',
            'label' => 'Date Active',
            'type' => 'date',
            'required' => true,
            'default_value' => $sup['sup_date_active']
        ];
        $items[] = [
            'name' => 'nosh_action',
            'label' => 'Action after Saving',
            'type' => 'select',
            'select_items' => $nosh_action_arr
        ];
        $items[] = [
            'name' => 'sup_date_inactive',
            'type' => 'hidden',
            'default_value' => $sup['sup_date_inactive']
        ];
        $items[] = [
            'name' => 'sup_provider',
            'type' => 'hidden',
            'required' => true,
            'default_value' => $sup['sup_provider']
        ];
        $items[] = [
            'name' => 'supplement_id',
            'type' => 'hidden',
            'default_value' => $sup['supplement_id']
        ];
        return $items;
    }

    protected function form_supplement_inventory($result, $table, $id, $subtype)
    {
        if ($id == '0') {
            $data = [
                'sup_description' => null,
                'sup_strength' => null,
                'sup_manufacturer' => null,
                'quantity1' => null,
                'charge' => null,
                'sup_expiration' => null,
                'date_purchase'=> date("Y-m-d"),
                'sup_lot' => null,
                'cpt' => null,
                'practice_id' => Session::get('practice_id')
            ];
        } else {
            $data = [
                'sup_description' => $result->sup_description,
                'sup_strength' => $result->sup_strength,
                'sup_manufacturer' => $result->sup_manufacturer,
                'quantity1' => $result->quantity1,
                'charge' => $result->charge,
                'sup_expiration' => date("Y-m-d", $this->human_to_unix($result->sup_expiration)),
                'date_purchase'=> date("Y-m-d", $this->human_to_unix($result->date_purchase)),
                'sup_lot' => $result->sup_lot,
                'cpt' => $result->cpt,
                'practice_id' => $result->practice_id
            ];
        }
        $items[] = [
            'name' => 'sup_description',
            'label' => 'Supplement Description',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'sup_description']),
            'default_value' => $data['sup_description']
        ];
        $items[] = [
            'name' => 'sup_strength',
            'label' => 'Strength',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'sup_manufacturer']),
            'default_value' => $data['sup_strength']
        ];
        $items[] = [
            'name' => 'sup_manufacturer',
            'label' => 'Manufacturer',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'sup_manufacturer']),
            'default_value' => $data['sup_manufacturer']
        ];
        $items[] = [
            'name' => 'quantity1',
            'label' => 'Quantity',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'quantity1']),
            'default_value' => $data['quantity1']
        ];
        $items[] = [
            'name' => 'charge',
            'label' => 'Manufacturer',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'charge']),
            'default_value' => $data['charge']
        ];
        $items[] = [
            'name' => 'cpt',
            'label' => 'Procedure Code',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'cpt']),
            'default_value' => $data['cpt']
        ];
        $items[] = [
            'name' => 'sup_expiration',
            'label' => 'Expiration Date',
            'type' => 'date',
            'required' => true,
            'default_value' => $data['sup_expiration']
        ];
        $items[] = [
            'name' => 'date_purchase',
            'label' => 'Date of Purchase',
            'type' => 'date',
            'required' => true,
            'default_value' => $data['date_purchase']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'default_value' => $data['practice_id']
        ];
        return $items;
    }

    protected function form_tests($result, $table, $id, $subtype)
    {
        $test_type_arr = [
            '' => 'Select Type',
            'Laboratory' => 'Laboratory',
            'Imaging' => 'Imaging'
        ];
        if ($id == '0') {
            $tests = [
                'test_type' => null,
                'test_name' => null,
                'test_result' => null,
                'test_units' => null,
                'test_reference' => null,
                'test_flags' => null,
                'test_datetime' => date('Y-m-d'),
                'test_from' => null,
                'test_provider_id' => null,
                'test_code' => null,
            ];
        } else {
            $tests = [
                'test_type' => $result->test_type,
                'test_name' => $result->test_name,
                'test_result' => $result->test_result,
                'test_units' => $result->test_units,
                'test_reference' => $result->test_reference,
                'test_flags' => $result->test_flags,
                'test_datetime' => date('Y-m-d', $this->human_to_unix($result->test_datetime)),
                'test_from' => $result->test_from,
                'test_provider_id' => $result->test_provider_id,
                'test_code' => $result->test_code
            ];
        }
        $items[] = [
            'name' => 'test_type',
            'label' => 'Type',
            'type' => 'select',
            'required' => true,
            'select_items' => $test_type_arr,
            'default_value' => $tests['test_type']
        ];
        $items[] = [
            'name' => 'test_name',
            'label' => 'Test Name',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'test_name']),
            'default_value' => $tests['test_name']
        ];
        $items[] = [
            'name' => 'test_result',
            'label' => 'Result',
            'type' => 'text',
            'required' => true,
            'default_value' => $tests['test_result']
        ];
        $items[] = [
            'name' => 'test_units',
            'label' => 'Result Units',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'test_units']),
            'default_value' => $tests['test_units']
        ];
        $items[] = [
            'name' => 'test_reference',
            'label' => 'Normal Reference Range',
            'type' => 'text',
            'default_value' => $tests['test_reference']
        ];
        $items[] = [
            'name' => 'test_flags',
            'label' => 'Flag',
            'type' => 'select',
            'select_items' => $this->array_test_flag(),
            'default_value' => $tests['test_flags']
        ];
        $items[] = [
            'name' => 'test_datetime',
            'label' => 'Date of Test',
            'type' => 'date',
            'required' => true,
            'default_value' => $tests['test_datetime']
        ];
        $items[] = [
            'name' => 'test_from',
            'label' => 'Location',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'test_from']),
            'default_value' => $tests['test_from']
        ];
        $items[] = [
            'name' => 'test_code',
            'label' => 'LOINC Code',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'test_code']),
            'default_value' => $tests['test_code']
        ];
        $items[] = [
            'name' => 'test_provider_id',
            'label' => 'Provider',
            'type' => 'select',
            'select_items' => $this->array_providers(),
            'default_value' => $tests['test_provider_id']
        ];
        return $items;
    }

    protected function form_t_messages($result, $table, $id, $subtype)
    {
        if ($id == '0') {
            $message = [
                't_messages_subject' => null,
                't_messages_message' => null,
                't_messages_dos' => date('Y-m-d'),
                't_messages_provider' => Session::get('displayname'),
                't_messages_signed' => 'No',
                't_messages_to' => null,
                't_messages_from' => Session::get('displayname') . ' (' . Session::get('user_id') . ')',
                'pid' => Session::get('pid'),
                'practice_id' => Session::get('practice_id'),
                'label' => null
            ];
            if (Session::has('session_t_message')) {
                $new_arr = Session::get('session_t_message');
                Session::forget('session_t_message');
                unset($new_arr['t_messages_id']);
                $message = $new_arr;
            }
        } else {
            $message = [];
            if (Session::has('session_t_message')) {
                $new_arr = Session::get('session_t_message');
                Session::forget('session_t_message');
                if ($new_arr['t_messages_id'] == $id) {
                    unset($new_arr['t_messages_id']);
                    $message = $new_arr;
                }
            }
            $label = [];
            if ($result->label !== '' || $result->label !== null)  {
                $label = explode(";", $result->label);
            }
            if (count($message) == 0) {
                $message = [
                    't_messages_subject' => $result->t_messages_subject,
                    't_messages_message' => $result->t_messages_message,
                    't_messages_dos' => date('Y-m-d', $this->human_to_unix($result->t_messages_dos)),
                    't_messages_provider' => $result->t_messages_provider,
                    't_messages_signed' => $result->t_messages_signed,
                    't_messages_to' => $result->t_messages_to,
                    't_messages_from' => $result->t_messages_from,
                    'pid' => $result->pid,
                    'practice_id' => $result->practice_id,
                    'label' => $label
                ];
            }
        }
        $items[] = [
            'name' => 't_messages_subject',
            'label' => 'Subject',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 't_messages_subject']),
            'default_value' => $message['t_messages_subject']
        ];
        $items[] = [
            'name' => 't_messages_message',
            'label' => 'Message',
            'type' => 'textarea',
            'required' => true,
            'default_value' => $message['t_messages_message']
        ];
        $items[] = [
            'name' => 't_messages_dos',
            'label' => 'Date of Message',
            'type' => 'date',
            'required' => true,
            'default_value' => $message['t_messages_dos']
        ];
        $items[] = [
            'name' => 't_messages_provider',
            'type' => 'hidden',
            'default_value' => $message['t_messages_provider']
        ];
        $items[] = [
            'name' => 't_messages_signed',
            'type' => 'hidden',
            'default_value' => $message['t_messages_signed']
        ];
        $items[] = [
            'name' => 't_messages_to',
            'label' => 'Assign To',
            'type' => 'select',
            'select_items' => $this->array_users(),
            'default_value' => $message['t_messages_to']
        ];
        $items[] = [
            'name' => 't_messages_from',
            'label' => 'From',
            'type' => 'select',
            'select_items' => $this->array_users('2'),
            'default_value' => $message['t_messages_from']
        ];
        $items[] = [
            'name' => 'label[]',
            'label' => 'Sensitive Label',
            'type' => 'select',
            'select_items' => $this->fhir_scopes_sensitivities(),
            'multiple' => true,
            'selectpicker' => true,
            'default_value' => $message['label']
        ];
        $items[] = [
            'name' => 'pid',
            'type' => 'hidden',
            'default_value' => $message['pid']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'default_value' => $message['practice_id']
        ];
        return $items;
    }

    protected function form_users($result, $table, $id, $subtype)
    {
        $users_arr = $this->array_groups();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        if ($id == '0') {
            $data = [
                'username' => null,
                'firstname' => null,
                'middle' => null,
                'lastname' => null,
                'title' => null,
                'displayname' => null,
                'email' => null,
                'group_id' => $subtype,
                'active' => '1',
                'practice_id' => Session::get('practice_id'),
                'locale' => null
            ];
            if ($subtype == '2') {
                $data2 = [
                    'specialty' => null,
                    'license' => null,
                    'license_state' => null,
                    'npi' => null,
                    'npi_taxonomy' => null,
                    'upin' => null,
                    'dea' => null,
                    'medicare' => null,
                    'tax_id' => null,
                    'rcopia_username' => null,
                    'schedule_increment' => null,
                    'peacehealth_id' => null,
                    'practice_id' => Session::get('practice_id')
                ];
            }
        } else {
            $data = [
                'username' => $result->username,
                'firstname' => $result->firstname,
                'middle' => $result->middle,
                'lastname' => $result->lastname,
                'title' => $result->title,
                'displayname' => $result->displayname,
                'email' => $result->email,
                'group_id' => $result->group_id,
                'active' => $result->active,
                'practice_id' => $result->practice_id,
                'locale' => $result->locale
            ];
            if ($subtype == '2') {
                $provider = DB::table('providers')->where('id', '=', $id)->first();
                $data2 = [
                    'specialty' => $provider->specialty,
                    'license' => $provider->license,
                    'license_country' => $provider->license_country,
                    'license_state' => $provider->license_state,
                    'npi' => $provider->npi,
                    'npi_taxonomy' => $provider->npi_taxonomy,
                    'upin' => $provider->upin,
                    'dea' => $provider->dea,
                    'medicare' => $provider->medicare,
                    'tax_id' => $provider->tax_id,
                    'rcopia_username' => $provider->rcopia_username,
                    'schedule_increment' => $provider->schedule_increment,
                    'peacehealth_id' => $provider->peacehealth_id,
                    'practice_id' => $provider->practice_id
                ];
            }
        }
        if ($id !== '0') {
            $items[] = [
                'name' => 'username',
                'label' => 'Username',
                'type' => 'text',
                'required' => true,
                'readonly' => true,
                'default_value' => $data['username']
            ];
        }
        $items[] = [
            'name' => 'firstname',
            'label' => 'First Name',
            'type' => 'text',
            'required' => true,
            'default_value' => $data['firstname']
        ];
        $items[] = [
            'name' => 'middle',
            'label' => 'Middle Name',
            'type' => 'text',
            'default_value' => $data['middle']
        ];
        $items[] = [
            'name' => 'lastname',
            'label' => 'Last Name',
            'type' => 'text',
            'required' => true,
            'default_value' => $data['lastname']
        ];
        $items[] = [
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'default_value' => $data['title']
        ];
        $items[] = [
            'name' => 'displayname',
            'type' => 'hidden',
            'default_value' => $data['displayname']
        ];
        $items[] = [
            'name' => 'email',
            'label' => 'E-mail',
            'type' => 'email',
            'required' => true,
            'default_value' => $data['email']
        ];
        $items[] = [
            'name' => 'locale',
            'label' => 'Locale',
            'type' => 'select',
            'select_items' => $this->array_locale(),
            'default_value' => $data['locale']
        ];
        $items[] = [
            'name' => 'group_id',
            'type' => 'hidden',
            'default_value' => $data['group_id']
        ];
        $items[] = [
            'name' => 'active',
            'type' => 'hidden',
            'default_value' => $data['active']
        ];
        $items[] = [
            'name' => 'practice_id',
            'label' => 'Practice ID',
            'type' => 'text',
            'readonly' => true,
            'default_value' => $data['practice_id']
        ];
        if ($subtype == '2') {
            $items[] = [
                'name' => 'specialty',
                'label' => 'Specialty',
                'type' => 'text',
                'default_value' => $data2['specialty']
            ];
            $items[] = [
                'name' => 'license',
                'label' => 'License Number',
                'type' => 'text',
                'default_value' => $data2['license']
            ];
            $items[] = [
                'name' => 'license_country',
                'label' => 'Country',
                'type' => 'select',
                'select_items' => $this->array_country(),
                'default_value' => $data2['license_country'],
                'class' => 'country'
            ];
            $items[] = [
                'name' => 'license_state',
                'label' => 'State Licensed',
                'type' => 'select',
                'select_items' => $this->array_states($data2['license_country']),
                'default_value' => $data2['license_state'],
                'class' => 'state'
            ];
            $items[] = [
                'name' => 'npi',
                'label' => 'NPI',
                'type' => 'text',
                'default_value' => $data2['npi']
            ];
            $items[] = [
                'name' => 'npi_taxonomy',
                'label' => 'NPI Taxonomy',
                'type' => 'text',
                'readonly' => true,
                'default_value' => $data2['npi_taxonomy']
            ];
            $items[] = [
                'name' => 'upin',
                'label' => 'UPIN',
                'type' => 'text',
                'default_value' => $data2['upin']
            ];
            $items[] = [
                'name' => 'dea',
                'label' => 'DEA Number',
                'type' => 'text',
                'default_value' => $data2['dea']
            ];
            $items[] = [
                'name' => 'medicare',
                'label' => 'Medicare Number',
                'type' => 'text',
                'default_value' => $data2['medicare']
            ];
            $items[] = [
                'name' => 'tax_id',
                'label' => 'Tax ID Number',
                'type' => 'text',
                'default_value' => $data2['tax_id']
            ];
            $items[] = [
                'name' => 'peacehealth_id',
                'label' => 'PeaceHealth ID Number',
                'type' => 'text',
                'default_value' => $data2['peacehealth_id']
            ];
            if ($practice->rcopia_extension == 'y') {
                $items[] = [
                    'name' => 'rcopia_username',
                    'label' => 'rCopia Username',
                    'type' => 'text',
                    'default_value' => $data2['rcopia_username']
                ];
            }
            $items[] = [
                'name' => 'schedule_increment',
                'label' => 'Time Increment for schedule (minuntes)',
                'type' => 'text',
                'default_value' => $data2['schedule_increment']
            ];
        }
        return $items;
    }

    protected function form_vaccine_inventory($result, $table, $id, $subtype)
    {
        if ($id == '0') {
            $data = [
                'imm_immunization' => null,
                'imm_cvxcode' => null,
                'imm_manufacturer' => null,
                'imm_brand' => null,
                'imm_lot' => null,
                'quantity' => null,
                'cpt' => null,
                'imm_expiration' => null,
                'date_purchase'=> date("Y-m-d"),
                'practice_id' => Session::get('practice_id')
            ];
        } else {
            $data = [
                'imm_immunization' => $result->imm_immunization,
                'imm_cvxcode' => $result->imm_cvxcode,
                'imm_manufacturer' => $result->imm_manufacturer,
                'imm_brand' => $result->imm_brand,
                'imm_lot' => $result->imm_lot,
                'quantity' => $result->quantity,
                'cpt' => $result->cpt,
                'imm_expiration' => date('Y-m-d', $this->human_to_unix($result->imm_expiration)),
                'date_purchase'=> date('Y-m-d', $this->human_to_unix($result->date_purchase)),
                'practice_id' => $result->practice_id
            ];
        }
        $items[] = [
            'name' => 'imm_immunization',
            'label' => 'Vaccine',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'imm_immunization']),
            'default_value' => $data['imm_immunization']
        ];
        $items[] = [
            'name' => 'imm_cvxcode',
            'label' => 'CVX Code',
            'type' => 'text',
            'readonly' => true,
            'required' => true,
            'default_value' => $data['imm_cvxcode']
        ];
        $items[] = [
            'name' => 'imm_manufacturer',
            'label' => 'Manufacturer',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'imm_manufacturer']),
            'default_value' => $data['imm_manufacturer']
        ];
        $items[] = [
            'name' => 'imm_brand',
            'label' => 'Brand',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'imm_brand']),
            'default_value' => $data['imm_brand']
        ];
        $items[] = [
            'name' => 'imm_lot',
            'label' => 'Lot Number',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'imm_lot']),
            'default_value' => $data['imm_lot']
        ];
        $items[] = [
            'name' => 'quantity',
            'label' => 'Quantity',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'quantity']),
            'default_value' => $data['quantity']
        ];
        $items[] = [
            'name' => 'cpt',
            'label' => 'Procedure Code',
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'cpt']),
            'default_value' => $data['cpt']
        ];
        $items[] = [
            'name' => 'imm_expiration',
            'label' => 'Expiration Date',
            'type' => 'date',
            'required' => true,
            'default_value' => $data['imm_expiration']
        ];
        $items[] = [
            'name' => 'date_purchase',
            'label' => 'Date of Purchase',
            'type' => 'date',
            'required' => true,
            'default_value' => $data['date_purchase']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'default_value' => $data['practice_id']
        ];
        return $items;
    }

    protected function form_vaccine_temp($result, $table, $id, $subtype)
    {
        if ($id == '0') {
            $data = [
                'temp' => null,
                'date' => date("Y-m-d h:i A"),
                'action' => null,
                'practice_id' => Session::get('practice_id')
            ];
        } else {
            $data = [
                'temp' => $result->temp,
                'date' => date("Y-m-d h:i A", $this->human_to_unix($result->date)),
                'action' => $result->action,
                'practice_id' => $result->practice_id
            ];
        }
        $items[] = [
            'name' => 'temp',
            'label' => 'Temperature',
            'type' => 'text',
            'required' => true,
            'default_value' => $data['temp']
        ];
        $items[] = [
            'name' => 'date',
            'label' => 'Date and Time',
            'type' => 'text',
            'datetime' => true,
            'required' => true,
            'default_value' => $data['date']
        ];
        $items[] = [
            'name' => 'action',
            'label' => 'Action if Out of Range',
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => $table, 'column' => 'action']),
            'default_value' => $data['action']
        ];
        $items[] = [
            'name' => 'practice_id',
            'type' => 'hidden',
            'default_value' => $data['practice_id']
        ];
        return $items;
    }

    protected function form_vitals($result, $table, $id, $subtype)
    {
        $vitals_arr = $this->array_vitals();
        $temp_method_arr = [
            '' => 'Pick method',
            'Oral' => 'Oral',
            'Axillary' => 'Axillary',
            'Temporal' => 'Temporal',
            'Rectal' => 'Rectal'
        ];
        $bp_position_arr = [
            '' => 'Pick position',
            'Sitting' => 'Sitting',
            'Standing' => 'Standing',
            'Supine' => 'Supinee'
        ];
        if ($id == '0') {
            $vitals = [
                'weight' => null,
                'height' => null,
                'headcircumference' => null,
                'BMI' => null,
                'temp' => null,
                'temp_method' => 'Oral',
                'bp_systolic' => null,
                'bp_diastolic' => null,
                'bp_position' => 'Sitting',
                'pulse' => null,
                'respirations' => null,
                'o2_sat' => null,
                'vitals_other' => null,
                'vitals_date' => date('Y-m-d')
            ];
        } else {
            $vitals = [
                'weight' => $result->weight,
                'height' => $result->height,
                'headcircumference' => $result->headcircumference,
                'BMI' => $result->BMI,
                'temp' => $result->temp,
                'temp_method' => $result->temp_method,
                'bp_systolic' => $result->bp_systolic,
                'bp_diastolic' => $result->bp_diastolic,
                'bp_position' => $result->bp_position,
                'pulse' => $result->pulse,
                'respirations' => $result->respirations,
                'o2_sat' => $result->o2_sat,
                'vitals_other' => $result->vitals_other,
                'vitals_date' => date('Y-m-d', $this->human_to_unix($result->vitals_date))
            ];
        }
        $items[] = [
            'name' => 'weight',
            'label' => 'Weight (' . $vitals_arr['weight']['unit'] . ')',
            'type' => 'text',
            'default_value' => $vitals['weight']
        ];
        $items[] = [
            'name' => 'height',
            'label' => 'Height (' . $vitals_arr['height']['unit'] . ')',
            'type' => 'text',
            'default_value' => $vitals['height']
        ];
        if (Session::get('agealldays') < 6574.5) {
            $items[] = [
                'name' => 'headcircumference',
                'label' => 'Head Circumference (' . $vitals_arr['headcircumference']['unit'] . ')',
                'type' => 'text',
                'default_value' => $vitals['headcircumference']
            ];
        }
        $items[] = [
            'name' => 'BMI',
            'label' => 'BMI (' . $vitals_arr['BMI']['unit'] . ')',
            'type' => 'text',
            'readonly' => true,
            'default_value' => $vitals['BMI']
        ];
        $items[] = [
            'name' => 'temp',
            'label' => 'Temperature (' . $vitals_arr['temp']['unit'] . ')',
            'type' => 'text',
            'default_value' => $vitals['temp']
        ];
        $items[] = [
            'name' => 'temp_method',
            'label' => 'Temperature Method',
            'type' => 'select',
            'select_items' => $temp_method_arr,
            'default_value' => $vitals['temp_method']
        ];
        $items[] = [
            'name' => 'bp_systolic',
            'label' => 'Systolic BP (' . $vitals_arr['bp_systolic']['unit'] . ')',
            'type' => 'text',
            'default_value' => $vitals['bp_systolic']
        ];
        $items[] = [
            'name' => 'bp_diastolic',
            'label' => 'Diastolic BP (' . $vitals_arr['bp_diastolic']['unit'] . ')',
            'type' => 'text',
            'default_value' => $vitals['bp_diastolic']
        ];
        $items[] = [
            'name' => 'bp_position',
            'label' => 'BP Position',
            'type' => 'select',
            'select_items' => $bp_position_arr,
            'default_value' => $vitals['bp_position']
        ];
        $items[] = [
            'name' => 'pulse',
            'label' => 'Pulse (' . $vitals_arr['pulse']['unit'] . ')',
            'type' => 'text',
            'default_value' => $vitals['pulse']
        ];
        $items[] = [
            'name' => 'respirations',
            'label' => 'Respirations (' . $vitals_arr['respirations']['unit'] . ')',
            'type' => 'text',
            'default_value' => $vitals['respirations']
        ];
        $items[] = [
            'name' => 'o2_sat',
            'label' => 'O2 Saturation (' . $vitals_arr['o2_sat']['unit'] . ')',
            'type' => 'text',
            'default_value' => $vitals['o2_sat']
        ];
        $items[] = [
            'name' => 'vitals_other',
            'label' => 'Notes',
            'type' => 'textarea',
            'default_value' => $vitals['vitals_other']
        ];
        return $items;
    }

    protected function form_($result, $table, $id, $subtype)
    {
        return $items;
    }

    protected function gc_bmi_age($sex, $pid)
    {
        $type = 'bmi-age';
        $data['patient'] = $this->gc_bmi_chart($pid);
        $data['graph_y_title'] = 'kg/m2';
        $array = $this->gc_spline($type, $sex);
        $myComparator = function($a, $b) use ($array) {
            return $a["age"] - $b["age"];
        };
        usort($array, $myComparator);
        foreach ($array as $row) {
            $data['categories'][] = (float) $row['age'];
            $data['P5'][] = (float) $row['p5'];
            $data['P10'][] = (float) $row['p10'];
            $data['P25'][] = (float) $row['p25'];
            $data['P50'][] = (float) $row['p50'];
            $data['P75'][] = (float) $row['p75'];
            $data['P90'][] = (float) $row['p90'];
            $data['P95'][] = (float) $row['p95'];
        }
        $data['graph_x_title'] = 'Age (days)';
        $val = end($data['patient']);
        $age = round($val[0]);
        $x = $val[1];
        $lms = $this->gc_lms($type, $sex, $age);
        $l = $lms['l'];
        $m = $lms['m'];
        $s = $lms['s'];
        $val1 = $x / $m;
        if ($lms['l'] != '0') {
            $val2 = pow($val1, $l);
            $val2 = $val2 - 1;
            $val3 = $l * $s;
            $zscore = $val2 / $val3;
        } else {
            $val4 = log($val1);
            $zscore = $val4 / $s;
        }
        $percentile = $this->gc_cdf($zscore) * 100;
        $percentile = round($percentile);
        $data['percentile'] = strval($percentile);
        $data['categories'] = json_encode($data['categories']);
        $data['P5'] = json_encode($data['P5']);
        $data['P10'] = json_encode($data['P10']);
        $data['P25'] = json_encode($data['P25']);
        $data['P50'] = json_encode($data['P50']);
        $data['P75'] = json_encode($data['P75']);
        $data['P90'] = json_encode($data['P90']);
        $data['P95'] = json_encode($data['P95']);
        $data['patient'] = json_encode($data['patient']);
        return $data;
    }

    protected function gc_bmi_chart($pid)
    {
        $query = DB::table('vitals')
            ->select('BMI', 'pedsage')
            ->where('pid', '=', $pid)
            ->where('BMI', '!=', '')
            ->orderBy('pedsage', 'asc')
            ->get();
        if ($query) {
            $vals = [];
            $i = 0;
            foreach ($query as $row) {
                $x = $row->pedsage * 2629743 / 86400;
                if ($x <= 1856) {
                    $vals[$i][] = $x;
                    $vals[$i][] = (float) $row->BMI;
                    $i++;
                }
            }
            return $vals;
        } else {
            return FALSE;
        }
    }

    protected function gc_cdf($n)
    {
        if($n < 0) {
            return (1 - $this->gc_erf($n / sqrt(2)))/2;
        } else {
            return (1 + $this->gc_erf($n / sqrt(2)))/2;
        }
    }

    protected function gc_erf($x)
    {
        $pi = 3.1415927;
        $a = (8*($pi - 3))/(3*$pi*(4 - $pi));
        $x2 = $x * $x;
        $ax2 = $a * $x2;
        $num = (4/$pi) + $ax2;
        $denom = 1 + $ax2;
        $inner = (-$x2)*$num/$denom;
        $erf2 = 1 - exp($inner);
        return sqrt($erf2);
    }

    protected function gc_head_age($sex, $pid)
    {
        $type = 'head-age';
        $data['patient'] = $this->gc_hc_chart($pid);
        $data['graph_y_title'] = 'cm';
        $array = $this->gc_spline($type, $sex);
        $myComparator = function($a, $b) use ($array) {
            return $a["age"] - $b["age"];
        };
        usort($array, $myComparator);
        foreach ($array as $row) {
            $data['categories'][] = (float) $row['age'];
            $data['P5'][] = (float) $row['p5'];
            $data['P10'][] = (float) $row['p10'];
            $data['P25'][] = (float) $row['p25'];
            $data['P50'][] = (float) $row['p50'];
            $data['P75'][] = (float) $row['p75'];
            $data['P90'][] = (float) $row['p90'];
            $data['P95'][] = (float) $row['p95'];
        }
        $data['graph_x_title'] = 'Age (days)';
        $val = end($data['patient']);
        $age = round($val[0]);
        $x = $val[1];
        $lms = $this->gc_lms($type, $sex, $age);
        $l = $lms['l'];
        $m = $lms['m'];
        $s = $lms['s'];
        $val1 = $x / $m;
        if ($lms['l'] != '0') {
            $val2 = pow($val1, $l);
            $val2 = $val2 - 1;
            $val3 = $l * $s;
            $zscore = $val2 / $val3;
        } else {
            $val4 = log($val1);
            $zscore = $val4 / $s;
        }
        $percentile = $this->gc_cdf($zscore) * 100;
        $percentile = round($percentile);
        $data['percentile'] = strval($percentile);
        $data['categories'] = json_encode($data['categories']);
        $data['P5'] = json_encode($data['P5']);
        $data['P10'] = json_encode($data['P10']);
        $data['P25'] = json_encode($data['P25']);
        $data['P50'] = json_encode($data['P50']);
        $data['P75'] = json_encode($data['P75']);
        $data['P90'] = json_encode($data['P90']);
        $data['P95'] = json_encode($data['P95']);
        $data['patient'] = json_encode($data['patient']);
        return $data;
    }

    protected function gc_hc_chart($pid)
    {
        $query = DB::table('vitals')
            ->select('headcircumference', 'pedsage')
            ->where('pid', '=', $pid)
            ->where('headcircumference', '!=', '')
            ->orderBy('pedsage', 'asc')
            ->get();
        if ($query) {
            $vals = [];
            $i = 0;
            foreach ($query as $row) {
                $row1 = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
                if ($row1->hc_unit == 'in') {
                    $y = $row->headcircumference * 2.54;
                } else {
                    $y = $row->headcircumference * 1;
                }
                $x = $row->pedsage * 2629743 / 86400;
                if ($x <= 1856) {
                    $vals[$i][] = $x;
                    $vals[$i][] = $y;
                    $i++;
                }
            }
            return $vals;
        } else {
            return FALSE;
        }
    }

    protected function gc_height_age($sex, $pid)
    {
        $type = 'height-age';
        $data['patient'] = $this->gc_height_chart($pid);
        $data['graph_y_title'] = 'cm';
        $array = $this->gc_spline($type, $sex);
        $myComparator = function($a, $b) use ($array) {
            return $a["day"] - $b["day"];
        };
        usort($array, $myComparator);
        foreach ($array as $row) {
            $data['categories'][] = (float) $row['day'];
            $data['P5'][] = (float) $row['p5'];
            $data['P10'][] = (float) $row['p10'];
            $data['P25'][] = (float) $row['p25'];
            $data['P50'][] = (float) $row['p50'];
            $data['P75'][] = (float) $row['p75'];
            $data['P90'][] = (float) $row['p90'];
            $data['P95'][] = (float) $row['p95'];
        }
        $data['graph_x_title'] = 'Age (days)';
        $val = end($data['patient']);
        $age = round($val[0]);
        $x = $val[1];
        $lms = $this->gc_lms($type, $sex, $age);
        $l = $lms['l'];
        $m = $lms['m'];
        $s = $lms['s'];
        $val1 = $x / $m;
        if ($lms['l'] != '0') {
            $val2 = pow($val1, $l);
            $val2 = $val2 - 1;
            $val3 = $l * $s;
            $zscore = $val2 / $val3;
        } else {
            $val4 = log($val1);
            $zscore = $val4 / $s;
        }
        $percentile = $this->gc_cdf($zscore) * 100;
        $percentile = round($percentile);
        $data['percentile'] = strval($percentile);
        $data['categories'] = json_encode($data['categories']);
        $data['P5'] = json_encode($data['P5']);
        $data['P10'] = json_encode($data['P10']);
        $data['P25'] = json_encode($data['P25']);
        $data['P50'] = json_encode($data['P50']);
        $data['P75'] = json_encode($data['P75']);
        $data['P90'] = json_encode($data['P90']);
        $data['P95'] = json_encode($data['P95']);
        $data['patient'] = json_encode($data['patient']);
        return $data;
    }

    protected function gc_height_chart($pid)
    {
        $query = DB::table('vitals')
            ->select('height', 'pedsage')
            ->where('pid', '=', $pid)
            ->where('height', '!=', '')
            ->orderBy('pedsage', 'asc')
            ->get();
        if ($query) {
            $vals = [];
            $i = 0;
            foreach ($query as $row) {
                $row1 = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
                if ($row1->height_unit == 'in') {
                    $y = $row->height * 2.54;
                } else {
                    $y = $row->height * 1;
                }
                $x = $row->pedsage * 2629743 / 86400;
                if ($x <= 1856) {
                    $vals[$i][] = $x;
                    $vals[$i][] = $y;
                    $i++;
                }
            }
            return $vals;
        } else {
            return FALSE;
        }
    }

    protected function gc_lms($style, $sex, $age)
    {
        $gc = $this->array_gc();
        Config::set('excel.csv.delimiter', "\t");
        $reader = Excel::load(resource_path() . '/' . $gc[$style][$sex]);
        $result = $reader->get()->toArray();
        $result1a = array_where($result, function($value, $key) use ($age, $style) {
            if ($style == 'height-age') {
                if ($value['day'] == $age) {
                    return true;
                }
            } else {
                if ($value['age'] == $age) {
                    return true;
                }
            }
        });
        $result = head($result1a);
        return $result;
    }

    protected function gc_lms1($style, $sex, $length)
    {
        $gc = $this->array_gc();
        Config::set('excel.csv.delimiter', "\t");
        $reader = Excel::load(resource_path() . '/' . $gc[$style][$sex]);
        $result = $reader->get()->toArray();
        $result1a = array_where($result, function($value, $key) use ($length) {
            if ($value['length'] == $length) {
                return true;
            }
        });
        $result = head($result1a);
        return $result;
    }

    protected function gc_lms2($style, $sex, $height)
    {
        $gc = $this->array_gc();
        Config::set('excel.csv.delimiter', "\t");
        $reader = Excel::load(resource_path() . '/' . $gc[$style][$sex]);
        $result = $reader->get()->toArray();
        $result1a = array_where($result, function($value, $key) use ($height) {
            if ($value['height'] == $height) {
                return true;
            }
        });
        $result = head($result1a);
        return $result;
    }

    protected function gc_spline($style, $sex)
    {
        $gc = $this->array_gc();
        Config::set('excel.csv.delimiter', "\t");
        $reader = Excel::load(resource_path() . '/' . $gc[$style][$sex]);
        $result = $reader->get()->toArray();
        return $result;
    }

    protected function gc_weight_age($sex, $pid)
    {
        $type = 'weight-age';
        $data['patient'] = $this->gc_weight_chart($pid);
        $data['graph_y_title'] = 'kg';
        $array = $this->gc_spline($type, $sex);
        $myComparator = function($a, $b) use ($array) {
            return $a["age"] - $b["age"];
        };
        usort($array, $myComparator);
        foreach ($array as $row) {
            $data['categories'][] = (float) $row['age'];
            $data['P5'][] = (float) $row['p5'];
            $data['P10'][] = (float) $row['p10'];
            $data['P25'][] = (float) $row['p25'];
            $data['P50'][] = (float) $row['p50'];
            $data['P75'][] = (float) $row['p75'];
            $data['P90'][] = (float) $row['p90'];
            $data['P95'][] = (float) $row['p95'];
        }
        $data['graph_x_title'] = 'Age (days)';
        $val = end($data['patient']);
        $age = round($val[0]);
        $x = $val[1];
        $lms = $this->gc_lms($type, $sex, $age);
        $l = $lms['l'];
        $m = $lms['m'];
        $s = $lms['s'];
        $val1 = $x / $m;
        $data['val1'] = $val1;
        if ($lms['l'] != '0') {
            $val2 = pow($val1, $l);
            $val2 = $val2 - 1;
            $val3 = $l * $s;
            $zscore = $val2 / $val3;
        } else {
            $val4 = log($val1);
            $zscore = $val4 / $s;
        }
        $percentile = $this->gc_cdf($zscore) * 100;
        $percentile = round($percentile);
        $data['percentile'] = strval($percentile);
        $data['categories'] = json_encode($data['categories']);
        $data['P5'] = json_encode($data['P5']);
        $data['P10'] = json_encode($data['P10']);
        $data['P25'] = json_encode($data['P25']);
        $data['P50'] = json_encode($data['P50']);
        $data['P75'] = json_encode($data['P75']);
        $data['P90'] = json_encode($data['P90']);
        $data['P95'] = json_encode($data['P95']);
        $data['patient'] = json_encode($data['patient']);
        return $data;
    }

    protected function gc_weight_chart($pid)
    {
        $query = DB::table('vitals')
            ->select('weight', 'pedsage')
            ->where('pid', '=', $pid)
            ->where('weight', '!=', '')
            ->orderBy('pedsage', 'asc')
            ->get();
        if ($query) {
            $vals = [];
            $i = 0;
            foreach ($query as $row) {
                $row1 = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
                if ($row1->weight_unit == 'lbs') {
                    $y = $row->weight / 2.20462262185;
                } else {
                    $y = $row->weight * 1;
                }
                $x = $row->pedsage * 2629743 / 86400;
                if ($x <= 1856) {
                    $vals[$i][] = $x;
                    $vals[$i][] = $y;
                    $i++;
                }
            }
            return $vals;
        } else {
            return FALSE;
        }
    }

    protected function gc_weight_height($sex, $pid)
    {
        $data['patient'] = $this->gc_weight_height_chart($pid);
        $data['graph_y_title'] = 'kg';
        $data['graph_x_title'] = 'cm';
        $query = DB::table('vitals')
            ->select('weight', 'height', 'pedsage')
            ->where('pid', '=', $pid)
            ->where('weight', '!=', '')
            ->where('height', '!=', '')
            ->orderBy('pedsage', 'desc')
            ->first();
        $pedsage = $query->pedsage * 2629743 / 86400;
        if ($pedsage <= 730) {
            $type = 'weight-length';
            $array1 = $this->gc_spline($type, $sex);
            $myComparator = function($a, $b) use ($array1) {
                if ($a["length"] == $b["length"]) {
                    return 0;
                }
                return ($a["length"] < $b["length"]) ? -1 : 1;
            };
            usort($array1, $myComparator);
            $i = 0;
            foreach ($array1 as $row1) {
                $data['P5'][$i][] = (float) $row1['length'];
                $data['P5'][$i][] = (float) $row1['p5'];
                $data['P10'][$i][] = (float) $row1['length'];
                $data['P10'][$i][] = (float) $row1['p10'];
                $data['P25'][$i][] = (float) $row1['length'];
                $data['P25'][$i][] = (float) $row1['p25'];
                $data['P50'][$i][] = (float) $row1['length'];
                $data['P50'][$i][] = (float) $row1['p50'];
                $data['P75'][$i][] = (float) $row1['length'];
                $data['P75'][$i][] = (float) $row1['p75'];
                $data['P90'][$i][] = (float) $row1['length'];
                $data['P90'][$i][] = (float) $row1['p90'];
                $data['P95'][$i][] = (float) $row1['length'];
                $data['P95'][$i][] = (float) $row1['p95'];
                $i++;
            }
        } else {
            $type = 'weight-height';
            $array2 = $this->gc_spline($type, $sex);
            $myComparator = function($a, $b) use ($array2) {
                if ($a["height"] == $b["height"]) {
                    return 0;
                }
                return ($a["height"] < $b["height"]) ? -1 : 1;
            };
            usort($array2, $myComparator);
            $j = 0;
            foreach ($array2 as $row1) {
                $data['P5'][$j][] = (float) $row1['height'];
                $data['P5'][$j][] = (float) $row1['p5'];
                $data['P10'][$j][] = (float) $row1['height'];
                $data['P10'][$j][] = (float) $row1['p10'];
                $data['P25'][$j][] = (float) $row1['height'];
                $data['P25'][$j][] = (float) $row1['p25'];
                $data['P50'][$j][] = (float) $row1['height'];
                $data['P50'][$j][] = (float) $row1['p50'];
                $data['P75'][$j][] = (float) $row1['height'];
                $data['P75'][$j][] = (float) $row1['p75'];
                $data['P90'][$j][] = (float) $row1['height'];
                $data['P90'][$j][] = (float) $row1['p90'];
                $data['P95'][$j][] = (float) $row1['height'];
                $data['P95'][$j][] = (float) $row1['p95'];
                $j++;
            }
        }
        $val = end($data['patient']);
        $length = round($val[0]);
        $data['length'] = $length;
        $x = $val[1];
        if ($pedsage <= 730) {
            $lms = $this->gc_lms1($type, $sex, $length);
        } else {
            $lms = $this->gc_lms2($type, $sex, $length);
        }
        $percentile = 0;
        if (! empty($lms)) {
            $l = $lms['l'];
            $m = $lms['m'];
            $s = $lms['s'];
            $val1 = $x / $m;
            if ($lms['l'] != '0') {
                $val2 = pow($val1, $l);
                $val2 = $val2 - 1;
                $val3 = $l * $s;
                $zscore = $val2 / $val3;
            } else {
                $val4 = log($val1);
                $zscore = $val4 / $s;
            }
            $percentile = $this->gc_cdf($zscore) * 100;
            $percentile = round($percentile);
        }
        $data['percentile'] = strval($percentile);
        // $data['categories'] = json_encode($data['categories']);
        $data['P5'] = json_encode($data['P5']);
        $data['P10'] = json_encode($data['P10']);
        $data['P25'] = json_encode($data['P25']);
        $data['P50'] = json_encode($data['P50']);
        $data['P75'] = json_encode($data['P75']);
        $data['P90'] = json_encode($data['P90']);
        $data['P95'] = json_encode($data['P95']);
        $data['patient'] = json_encode($data['patient']);
        return $data;
    }

    protected function gc_weight_height_chart($pid)
    {
        $query = DB::table('vitals')
            ->select('weight', 'height', 'pedsage')
            ->where('pid', '=', $pid)
            ->where('weight', '!=', '')
            ->where('height', '!=', '')
            ->orderBy('pedsage', 'asc')
            ->get();
        if ($query) {
            $vals = [];
            $i = 0;
            foreach ($query as $row) {
                $row1 = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
                if ($row1->weight_unit == 'lbs') {
                    $y = $row->weight / 2.20462262185;
                } else {
                    $y = $row->weight * 1;
                }
                if ($row1->height_unit == 'in') {
                    $x = $row->height * 2.54;
                } else {
                    $x = $row->height * 1;
                }
                $pedsage = $row->pedsage * 2629743 / 86400;
                if ($pedsage <= 730) {
                    if ($x >= 45 && $x <= 110) {
                        $vals[$i][] = $x;
                        $vals[$i][] = $y;
                        $i++;
                    }
                } else {
                    if ($x >= 65 && $x <= 120) {
                        $vals[$i][] = $x;
                        $vals[$i][] = $y;
                        $i++;
                    }
                }
            }
            return $vals;
        } else {
            return FALSE;
        }
    }

    protected function gen_secret()
    {
        $length = 512;
        $val = '';
        for ($i = 0; $i < $length; $i++) {
            $val .= rand(0, 9);
        }
        $fp = fopen('/dev/urandom', 'rb');
        $val = fread($fp, 32);
        fclose($fp);
        $val .= uniqid(mt_rand(), true);
        $hash = hash('sha512', $val, true);
        $result = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
        return $result;
    }

    protected function gen_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    protected function generate_ccda($hippa_id='',$pid='')
    {
        $gender_arr = $this->array_gender();
        $route_arr = $this->array_route1();
        $marital_arr = $this->array_marital1();
        $ccda = File::get(resource_path() . '/ccda.xml');
        $practice_info = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $ccda = str_replace('?practice_name?', $practice_info->practice_name, $ccda);
        $date_format = "YmdHisO";
        $ccda = str_replace('?effectiveTime?', date($date_format), $ccda);
        $ccda_name = time() . '_ccda.xml';
        if ($pid == '') {
            $pid = Session::get('pid');
        }
        $ccda = str_replace('?pid?', $pid, $ccda);
        $demographics = DB::table('demographics')->where('pid', '=', $pid)->first();
        $ccda = str_replace('?ss?', $demographics->ss, $ccda);
        $ccda = str_replace('?street_address1?', $demographics->address, $ccda);
        $ccda = str_replace('?city?', $demographics->city, $ccda);
        $ccda = str_replace('?state?', $demographics->state, $ccda);
        $ccda = str_replace('?zip?', $demographics->zip, $ccda);
        $ccda = str_replace('?phone_home?', $demographics->phone_home, $ccda);
        $ccda = str_replace('?firstname?', $demographics->firstname, $ccda);
        $ccda = str_replace('?lastname?', $demographics->lastname, $ccda);
        $gender = strtoupper($demographics->sex);
        $gender_full = $gender_arr[$demographics->sex];
        $ccda = str_replace('?gender?', $gender, $ccda);
        $ccda = str_replace('?gender_full?', $gender_full, $ccda);
        $ccda = str_replace('?dob?', date('Ymd', $this->human_to_unix($demographics->DOB)), $ccda);
        $marital_code = "U";
        if ($demographics->marital_status !== '' && $demographics->marital_status !== null) {
            $marital_code = $marital_arr[$demographics->marital_status];
        }
        $ccda = str_replace('?marital_status?', $demographics->marital_status, $ccda);
        $ccda = str_replace('?marital_code?', $marital_code, $ccda);
        $ccda = str_replace('?race?', $demographics->race, $ccda);
        $ccda = str_replace('?race_code?', $demographics->race_code, $ccda);
        $ccda = str_replace('?ethnicity?', $demographics->ethnicity, $ccda);
        $ccda = str_replace('?ethnicity_code?', $demographics->ethnicity_code, $ccda);
        $ccda = str_replace('?guardian_code?', $demographics->guardian_code, $ccda);
        $ccda = str_replace('?guardian_relationship?', $demographics->guardian_relationship, $ccda);
        $ccda = str_replace('?guardian_lastname?', $demographics->guardian_lastname, $ccda);
        $ccda = str_replace('?guardian_firstname?', $demographics->guardian_firstname, $ccda);
        $ccda = str_replace('?guardian_address?', $demographics->guardian_address, $ccda);
        $ccda = str_replace('?guardian_city?', $demographics->guardian_city, $ccda);
        $ccda = str_replace('?guardian_state?', $demographics->guardian_state, $ccda);
        $ccda = str_replace('?guardian_zip?', $demographics->guardian_zip, $ccda);
        $ccda = str_replace('?guardian_phone_home?', $demographics->guardian_phone_home, $ccda);
        if ($practice_info->street_address2 != '') {
            $practice_info->street_address1 .= ', ' . $practice_info->street_address2;
        }
        $ccda = str_replace('?practiceinfo_street_address?', $practice_info->street_address1, $ccda);
        $ccda = str_replace('?practiceinfo_city?', $practice_info->city, $ccda);
        $ccda = str_replace('?practiceinfo_state?', $practice_info->state, $ccda);
        $ccda = str_replace('?practiceinfo_zip?', $practice_info->zip, $ccda);
        $ccda = str_replace('?practiceinfo_phone?', $practice_info->phone, $ccda);
        $user_id = Session::get('user_id');
        $user = DB::table('users')->where('id', '=', $user_id)->first();
        $ccda = str_replace('?user_id?', $user->id, $ccda);
        $ccda = str_replace('?user_lastname?', $user->lastname, $ccda);
        $ccda = str_replace('?user_firstname?', $user->firstname, $ccda);
        $ccda = str_replace('?user_title?', $user->title, $ccda);
        $date_format1 = "Ymd";
        $ccda = str_replace('?effectiveTimeShort?', date($date_format1), $ccda);
        $ccda = str_replace('?lang_code?', $demographics->lang_code, $ccda);
        if ($hippa_id != '') {
            $hippa_info = DB::table('hippa')->where('hippa_id', '=', $hippa_id)->first();
            $ccda = str_replace('?hippa_provider?', $hippa_info->hippa_provider, $ccda);
            $ccda = str_replace('?encounter_role?', $hippa_info->hippa_role, $ccda);
            if ($hippa_info->hippa_role == "Primary Care Provider") {
                $hippa_role_code = "PP";
            }
            if ($hippa_info->hippa_role == "Consulting Provider") {
                $hippa_role_code = "CP";
            }
            if ($hippa_info->hippa_role == "Referring Provider") {
                $hippa_role_code = "RP";
            }
        } else {
            $ccda = str_replace('?hippa_provider?', '', $ccda);
            $ccda = str_replace('?encounter_role?', '', $ccda);
            $hippa_role_code = "";
        }
        $ccda = str_replace('?encounter_role_code?', $hippa_role_code, $ccda);
        $recent_encounter_query = DB::table('encounters')->where('pid', '=', $pid)
            ->where('addendum', '=', 'n')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->where('encounter_signed', '=', 'Yes')
            ->orderBy('encounter_DOS', 'desc')
            ->take(1)
            ->first();
        if ($recent_encounter_query) {
            $ccda = str_replace('?eid?', $recent_encounter_query->eid, $ccda);
            $encounter_info = DB::table('encounters')->where('eid', '=', $recent_encounter_query->eid)->first();
            $provider_info = DB::table('users')->where('id', '=', $encounter_info->user_id)->first();
            $provider_info1 = DB::table('providers')->where('id', '=', $encounter_info->user_id)->first();
            if ($provider_info1) {
                $npi = $provider_info1->npi;
            } else {
                $npi = '';
            }
            $ccda = str_replace('?npi?', $npi, $ccda);
            $ccda = str_replace('?provider_title?', $provider_info->title, $ccda);
            $ccda = str_replace('?provider_firstname?', $provider_info->firstname, $ccda);
            $ccda = str_replace('?provider_lastname?', $provider_info->lastname, $ccda);
            $ccda = str_replace('?encounter_dos?', date('Ymd', $this->human_to_unix($encounter_info->encounter_DOS)), $ccda);
            $assessment_info = DB::table('assessment')->where('eid', '=', $recent_encounter_query->eid)->first();
            if ($assessment_info) {
                $recent_icd = $assessment_info->assessment_icd1;
                $recent_icd_description = $this->icd_search($recent_icd);
            } else {
                $recent_icd = '';
                $recent_icd_description = '';
            }
            $ccda = str_replace('?icd9?', $recent_icd, $ccda);
            $ccda = str_replace('?icd9_description?', $recent_icd_description, $ccda);
        } else {
            $ccda = str_replace('?eid?', '', $ccda);
            $ccda = str_replace('?npi?', '', $ccda);
            $ccda = str_replace('?provider_title?', '', $ccda);
            $ccda = str_replace('?provider_firstname?', '', $ccda);
            $ccda = str_replace('?provider_lastname?', '', $ccda);
            $ccda = str_replace('?encounter_dos?', '', $ccda);
            $ccda = str_replace('?icd9?', '', $ccda);
            $ccda = str_replace('?icd9_description?', '', $ccda);
        }
        $allergies_query = DB::table('allergies')->where('pid', '=', $pid)->get();
        $allergies_table = "";
        $allergies_file_final = "";
        if ($allergies_query->count()) {
            $i = 1;
            foreach ($allergies_query as $allergies_row) {
                $allergies_table .= "<tr>";
                $allergies_table .= "<td>" . $allergies_row->allergies_med . "</td>";
                $allergies_table .= "<td><content ID='reaction" . $i . "'>" . $allergies_row->allergies_reaction . "</content></td>";
                $allergies_table .= "<td><content ID='severity" . $i . "'>" . $allergies_row->allergies_severity . "</content></td>";
                if ($allergies_row->allergies_date_inactive == '0000-00-00 00:00:00') {
                    $allergies_table .= "<td>Active</td>";
                    $allergies_status = "Active";
                    $allergies_file = File::get(resource_path() . '/allergies_active.xml');
                    $allergies_file = str_replace('?allergies_date_active?', date('Ymd', $this->human_to_unix($allergies_row->allergies_date_active)), $allergies_file);
                } else {
                    $allergies_table .= "<td>Inactive</td>";
                    $allergies_status = "Inactive";
                    $allergies_file = File::get(resource_path() . '/allergies_inactive.xml');
                    $allergies_file = str_replace('?allergies_date_active?', date('Ymd', $this->human_to_unix($allergies_row->allergies_date_active)), $allergies_file);
                    $allergies_file = str_replace('?allergies_date_inactive?', date('Ymd', $this->human_to_unix($allergies_row->allergies_date_inactive)), $allergies_file);
                }
                $allergies_table .= "</tr>";
                $reaction_number = "#reaction" . $i;
                $severity_number = "#severity" . $i;
                $allergies_file = str_replace('?reaction_number?', $reaction_number, $allergies_file);
                $allergies_file = str_replace('?severity_number?', $severity_number, $allergies_file);
                $allergies_file = str_replace('?allergies_med?', $allergies_row->allergies_med, $allergies_file);
                $allergies_file = str_replace('?allergies_status?', $allergies_status, $allergies_file);
                $allergies_file = str_replace('?allergies_reaction?', $allergies_row->allergies_reaction, $allergies_file);
                $allergies_file = str_replace('?allergies_code?', '', $allergies_file);
                // Need allergies severity field
                $allergies_file = str_replace('?allergies_severity?', '', $allergies_file);
                $allergy_random_id1 = $this->gen_uuid();
                $allergy_random_id2 = $this->gen_uuid();
                $allergy_random_id3 = $this->gen_uuid();
                $allergies_file = str_replace('?allergy_random_id1?', $allergy_random_id1, $allergies_file);
                $allergies_file = str_replace('?allergy_random_id2?', $allergy_random_id2, $allergies_file);
                $allergies_file = str_replace('?allergy_random_id3?', $allergy_random_id3, $allergies_file);
                $allergies_file_final .= $allergies_file;
                $i++;
            }
        }
        $ccda = str_replace('?allergies_table?', $allergies_table, $ccda);
        $ccda = str_replace('?allergies_file?', $allergies_file_final, $ccda);
        $encounters_query = DB::table('encounters')->where('pid', '=', $pid)
            ->where('addendum', '=', 'n')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->where('encounter_signed', '=', 'Yes')
            ->orderBy('encounter_DOS', 'desc')
            ->get();
        $e = 1;
        $encounters_table = "";
        $encounters_file_final = "";
        if ($encounters_query->count()) {
            foreach($encounters_query as $encounters_row) {
                $encounters_table .= "<tr>";
                $encounters_table .= "<td><content ID='Encounter" . $e . "'>" . $encounters_row->encounter_cc . "</content></td>";
                $encounters_table .= "<td>" . $encounters_row->encounter_provider . "</td>";
                $encounters_table .= "<td>" . $practice_info->practice_name . "</td>";
                $encounters_table .= "<td>" . date('m-d-Y', $this->human_to_unix($encounters_row->encounter_DOS)) . "</td>";
                $encounters_table .= "</tr>";
                $encounters_file = File::get(resource_path() . '/encounters.xml');
                $encounters_number = "#Encounter" . $e;
                $billing = DB::table('billing_core')
                    ->where('eid', '=', $encounters_row->eid)
                    ->where('billing_group', '=', '1')
                    ->where('cpt', 'NOT LIKE', "sp%")
                    ->orderBy('cpt_charge', 'desc')
                    ->take(1)
                    ->first();
                $encounter_code = '';
                $cpt_description = '';
                if ($billing) {
                    $encounter_code = $billing->cpt;
                    $cpt_query = DB::table('cpt_relate')->where('cpt', '=', $billing->cpt)->first();
                    if ($cpt_query) {
                        $cpt_description = $cpt_query->cpt_description;
                    } else {
                        $cpt_description = $this->cpt_search($billing->cpt);
                    }
                }
                $provider_firstname = '';
                $provider_lastname = '';
                $provider_title = '';
                $provider_info2 = DB::table('users')->where('id', '=', $encounters_row->user_id)->first();
                if ($provider_info2) {
                    $provider_firstname = $provider_info2->firstname;
                    $provider_lastname = $provider_info2->lastname;
                    $provider_title = $provider_info2->title;
                }
                $encounters_file = str_replace('?encounter_cc?', $encounters_row->encounter_cc, $encounters_file);
                $encounters_file = str_replace('?encounter_number?', $encounters_row->eid, $encounters_file);
                $encounters_file = str_replace('?encounter_code?', $encounter_code, $encounters_file);
                $encounters_file = str_replace('?encounter_code_desc?', $cpt_description, $encounters_file);
                $encounters_file = str_replace('?encounter_provider?', $encounters_row->encounter_provider, $encounters_file);
                $encounters_file = str_replace('?encounter_dos1?', date('m-d-Y', $this->human_to_unix($encounters_row->encounter_DOS)), $encounters_file);
                $encounters_file = str_replace('?provider_firstname?', $provider_firstname, $encounters_file);
                $encounters_file = str_replace('?provider_lastname?', $provider_lastname, $encounters_file);
                $encounters_file = str_replace('?provider_title?', $provider_title, $encounters_file);
                $encounters_file = str_replace('?encounter_dos?', date('Ymd', $this->human_to_unix($encounters_row->encounter_DOS)), $encounters_file);
                $encounters_file = str_replace('?practiceinfo_street_address?', $practice_info->street_address1, $encounters_file);
                $encounters_file = str_replace('?practiceinfo_city?', $practice_info->city, $encounters_file);
                $encounters_file = str_replace('?practiceinfo_state?', $practice_info->state, $encounters_file);
                $encounters_file = str_replace('?practiceinfo_zip?', $practice_info->zip, $encounters_file);
                $encounters_file = str_replace('?practiceinfo_phone?', $practice_info->phone, $encounters_file);
                $encounters_file = str_replace('?practice_name?', $practice_info->practice_name, $encounters_file);
                $encounter_random_id1 = $this->gen_uuid();
                $encounter_random_id2 = $this->gen_uuid();
                $encounter_random_id3 = $this->gen_uuid();
                $encounters_file = str_replace('?encounter_random_id1?', $encounter_random_id1, $encounters_file);
                $encounters_file = str_replace('?encounter_random_id2?', $encounter_random_id2, $encounters_file);
                $assessment_info1 = DB::table('assessment')->where('eid', '=', $encounters_row->eid)->first();
                $encounter_diagnosis = '';
                if ($assessment_info1) {
                    for ($i=1; $i<=12; $i++) {
                        $col = 'assessment_' . $i;
                        if ($assessment_info1->{$col} !== '') {
                            $dx_array[] = $assessment_info1->{$col};
                        }
                    }
                    foreach ($dx_array as $dx_item) {
                        $dx_file = File::get(resource_path() . '/encounter_diagnosis.xml');
                        $dx_random_id1 = $this->gen_uuid();
                        $dx_random_id2 = $this->gen_uuid();
                        $dx_random_id3 = $this->gen_uuid();
                        $dx_file = str_replace('?dx_random_id1?', $dx_random_id1, $dx_file);
                        $dx_file = str_replace('?dx_random_id2?', $dx_random_id2, $dx_file);
                        $dx_file = str_replace('?dx_random_id3?', $dx_random_id3, $dx_file);
                        $dx_file = str_replace('?icd9?', $dx_item, $dx_file);
                        $dx_file = str_replace('?encounter_dos?', date('Ymd', $this->human_to_unix($encounter_info->encounter_DOS)), $dx_file);
                        $icd_description = $this->icd_search($dx_item);
                        $dx_file = str_replace('?icd9_description?', $icd_description, $dx_file);
                        $encounter_diagnosis .= $dx_file;
                    }
                }
                $encounters_file = str_replace('?encounter_diagnosis?', $encounter_diagnosis, $encounters_file);
                $encounters_file_final .= $encounters_file;
                $e++;
            }
        }
        $ccda = str_replace('?encounters_table?', $encounters_table, $ccda);
        $ccda = str_replace('?encounters_file?', $encounters_file_final, $ccda);
        $imm_query = DB::table('immunizations')->where('pid', '=', $pid)->orderBy('imm_immunization', 'asc')->orderBy('imm_sequence', 'asc')->get();
        $imm_table = "";
        $imm_file_final = "";
        if ($imm_query->count()) {
            $j = 1;
            foreach ($imm_query as $imm_row) {
                $imm_table .= "<tr>";
                $imm_table .= "<td><content ID='immun" . $j . "'>" . $imm_row->imm_immunization . "</content></td>";
                $imm_table .= "<td>" . date('m-d-Y', $this->human_to_unix($imm_row->imm_date)) . "</td>";
                $imm_table .= "<td>Completed</td>";
                $imm_table .= "</tr>";
                $imm_file = File::get(resource_path() . '/immunizations.xml');
                $immun_number = "#immun" . $j;
                $imm_file = str_replace('?immun_number?', $immun_number, $imm_file);
                $imm_file = str_replace('?imm_date?', date('Ymd', $this->human_to_unix($imm_row->imm_date)), $imm_file);
                $imm_code = '';
                $imm_code_description = '';
                $imm_code = $route_arr[$imm_row->imm_route][0];
                $imm_file = str_replace('?imm_code?', $imm_code, $imm_file);
                $imm_file = str_replace('?imm_code_description?', $imm_code_description, $imm_file);
                $imm_file = str_replace('?imm_dosage?', $imm_row->imm_dosage, $imm_file);
                $imm_file = str_replace('?imm_dosage_unit?', $imm_row->imm_dosage_unit, $imm_file);
                $imm_file = str_replace('?imm_cvxcode?', $imm_row->imm_cvxcode, $imm_file);
                $imm_random_id1 = $this->gen_uuid();
                $imm_file = str_replace('?imm_random_id1?', $imm_random_id1, $imm_file);
                $vaccine_name = $this->cvx_search($imm_row->imm_cvxcode);
                $imm_file = str_replace('?vaccine_name?', $vaccine_name, $imm_file);
                $imm_file = str_replace('?imm_manufacturer?', $imm_row->imm_manufacturer, $imm_file);
                $imm_file_final .= $imm_file;
                $j++;
            }
        }
        $ccda = str_replace('?imm_table?', $imm_table, $ccda);
        $ccda = str_replace('?imm_file?', $imm_file_final, $ccda);
        $med_query = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->get();
        $sup_query = DB::table('sup_list')->where('pid', '=', $pid)->where('sup_date_inactive', '=', '0000-00-00 00:00:00')->get();
        $med_table = "";
        $med_file_final = "";
        $k = 1;
        if ($med_query->count()) {
            foreach ($med_query as $med_row) {
                $med_table .= "<tr>";
                $med_table .= "<td><content ID='med" . $k . "'>" . $med_row->rxl_medication . ' ' . $med_row->rxl_dosage . ' ' . $med_row->rxl_dosage_unit . "</content></td>";
                if ($med_row->rxl_sig == '') {
                    $instructions = $med_row->rxl_instructions;
                    $med_dosage = '';
                    $med_dosage_unit = '';
                    $med_code = '';
                    $med_code_description = '';
                    $med_period = '';
                } else {
                    $instructions = $med_row->rxl_sig . ', ' . $med_row->rxl_route . ', ' . $med_row->rxl_frequency;
                    $med_dosage_parts = explode(" ", $med_row->rxl_sig);
                    $med_dosage = $med_dosage_parts[0];
                    if (count($med_dosage_parts) > 1) {
                        $med_dosage_unit = $med_dosage_parts[1];
                    } else {
                        $med_dosage_unit = '';
                    }
                    $med_code = '';
                    $med_code_description = '';
                    $med_code = $route_arr[$med_row->rxl_route][0];
                    $med_code_description = $route_arr[$med_row->rxl_route][1];
                    $med_period = '';
                    $med_freq_array_1 = ["once daily", "every 24 hours", "once a day", "1 time a day", "QD"];
                    $med_freq_array_2 = ["twice daily", "every 12 hours", "two times a day", "2 times a day", "BID", "q12h", "Q12h"];
                    $med_freq_array_3 = ["three times daily", "every 8 hours", "three times a day", "3 times daily", "3 times a day", "TID", "q8h", "Q8h"];
                    $med_freq_array_4 = ["every six hours", "every 6 hours", "four times daily", "4 times a day", "four times a day", "4 times daily", "QID", "q6h", "Q6h"];
                    $med_freq_array_5 = ["every four hours", "every 4 hours", "six times a day", "6 times a day", "six times daily", "6 times daily", "q4h", "Q4h"];
                    $med_freq_array_6 = ["every three hours", "every 3 hours", "eight times a day", "8 times a day", "eight times daily", "8 times daily", "q3h", "Q3h"];
                    $med_freq_array_7 = ["every two hours", "every 2 hours", "twelve times a day", "12 times a day", "twelve times daily", "12 times daily", "q2h", "Q2h"];
                    $med_freq_array_8 = ["every hour", "every 1 hour", "every one hour", "q1h", "Q1h"];
                    if (in_array($med_row->rxl_frequency, $med_freq_array_1)) {
                        $med_period = "24";
                    }
                    if (in_array($med_row->rxl_frequency, $med_freq_array_2)) {
                        $med_period = "12";
                    }
                    if (in_array($med_row->rxl_frequency, $med_freq_array_3)) {
                        $med_period = "8";
                    }
                    if (in_array($med_row->rxl_frequency, $med_freq_array_4)) {
                        $med_period = "6";
                    }
                    if (in_array($med_row->rxl_frequency, $med_freq_array_5)) {
                        $med_period = "4";
                    }
                    if (in_array($med_row->rxl_frequency, $med_freq_array_6)) {
                        $med_period = "3";
                    }
                    if (in_array($med_row->rxl_frequency, $med_freq_array_7)) {
                        $med_period = "2";
                    }
                    if (in_array($med_row->rxl_frequency, $med_freq_array_8)) {
                        $med_period = "1";
                    }
                }
                $med_table .= "<td>" . $instructions . "</td>";
                $med_table .= "<td>" . date('m-d-Y', $this->human_to_unix($med_row->rxl_date_active)) . "</td>";
                $med_table .= "<td>Active</td>";
                $med_table .= "<td>" . $med_row->rxl_reason . "</td>";
                $med_table .= "</tr>";
                $med_file = File::get(resource_path() . '/medications.xml');
                $med_number = "#med" . $k;
                $med_random_id1 = $this->gen_uuid();
                $med_random_id2 = $this->gen_uuid();
                $med_file = str_replace('?med_random_id1?', $med_random_id1, $med_file);
                $med_file = str_replace('?med_random_id2?', $med_random_id2, $med_file);
                $med_file = str_replace('?med_number?', $med_number, $med_file);
                $med_file = str_replace('?med_date_active?', date('Ymd', $this->human_to_unix($med_row->rxl_date_active)), $med_file);
                $med_file = str_replace('?med_code?', $med_code, $med_file);
                $med_file = str_replace('?med_code_description?', $med_code_description, $med_file);
                $med_file = str_replace('?med_period?', $med_period, $med_file);
                $med_file = str_replace('?med_dosage?', $med_dosage, $med_file);
                $med_file = str_replace('?med_dosage_unit?', $med_dosage_unit, $med_file);
                $url = 'https://rxnav.nlm.nih.gov/REST/rxcui.json?idtype=NDC&id=' . $med_row->rxl_ndcid;
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $url);
                curl_setopt($ch,CURLOPT_FAILONERROR,1);
                curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                curl_setopt($ch,CURLOPT_TIMEOUT, 15);
                $json = curl_exec($ch);
                curl_close($ch);
                $rxnorm = json_decode($json, true);
                if (isset($rxnorm['idGroup']['rxnormId'][0])) {
                    $url1 = 'https://rxnav.nlm.nih.gov/REST/rxcui/' . $rxnorm['idGroup']['rxnormId'][0] . '/properties.json';
                    $ch1 = curl_init();
                    curl_setopt($ch1,CURLOPT_URL, $url1);
                    curl_setopt($ch1,CURLOPT_FAILONERROR,1);
                    curl_setopt($ch1,CURLOPT_FOLLOWLOCATION,1);
                    curl_setopt($ch1,CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch1,CURLOPT_TIMEOUT, 15);
                    $json1 = curl_exec($ch1);
                    curl_close($ch1);
                    $rxnorm1 = json_decode($json1, true);
                    $med_rxnorm_code = $rxnorm['idGroup']['rxnormId'][0];
                    $med_name = $rxnorm1['properties']['name'];
                } else {
                    $med_rxnorm_code = '';
                    $med_name = $med_row->rxl_medication . ' ' . $med_row->rxl_dosage . ' ' . $med_row->rxl_dosage_unit ;
                }
                $med_file = str_replace('?med_rxnorm_code?', $med_rxnorm_code, $med_file);
                $med_file = str_replace('?med_name?', $med_name, $med_file);
                $med_file_final .= $med_file;
                $k++;
            }
        }
        if ($sup_query->count()) {
            foreach ($sup_query as $sup_row) {
                $med_table .= "<tr>";
                $med_table .= "<td><content ID='med" . $k . "'>" . $sup_row->sup_supplement . ' ' . $sup_row->sup_dosage . ' ' . $sup_row->sup_dosage_unit . "</content></td>";
                if ($sup_row->sup_sig == '') {
                    $instructions = $sup_row->sup_instructions;
                    $med_dosage = '';
                    $med_dosage_unit = '';
                    $med_code = '';
                    $med_code_description = '';
                    $med_period = '';
                } else {
                    $instructions = $sup_row->sup_sig . ', ' . $sup_row->sup_route . ', ' . $sup_row->sup_frequency;
                    $med_dosage_parts = explode(" ", $sup_row->sup_sig);
                    $med_dosage = $med_dosage_parts[0];
                    $med_dosage_unit = '';
                    if (isset($med_dosage_parts[1])) {
                        $med_dosage_unit = $med_dosage_parts[1];
                    }
                    $med_code = '';
                    $med_code_description = '';
                    $med_code = $route_arr[$sup_row->sup_route][0];
                    $med_code_description = $route_arr[$sup_row->sup_route][1];
                    $med_period = '';
                    $med_freq_array_1 = ["once daily", "every 24 hours", "once a day", "1 time a day", "QD"];
                    $med_freq_array_2 = ["twice daily", "every 12 hours", "two times a day", "2 times a day", "BID", "q12h", "Q12h"];
                    $med_freq_array_3 = ["three times daily", "every 8 hours", "three times a day", "3 times daily", "3 times a day", "TID", "q8h", "Q8h"];
                    $med_freq_array_4 = ["every six hours", "every 6 hours", "four times daily", "4 times a day", "four times a day", "4 times daily", "QID", "q6h", "Q6h"];
                    $med_freq_array_5 = ["every four hours", "every 4 hours", "six times a day", "6 times a day", "six times daily", "6 times daily", "q4h", "Q4h"];
                    $med_freq_array_6 = ["every three hours", "every 3 hours", "eight times a day", "8 times a day", "eight times daily", "8 times daily", "q3h", "Q3h"];
                    $med_freq_array_7 = ["every two hours", "every 2 hours", "twelve times a day", "12 times a day", "twelve times daily", "12 times daily", "q2h", "Q2h"];
                    $med_freq_array_8 = ["every hour", "every 1 hour", "every one hour", "q1h", "Q1h"];
                    if (in_array($sup_row->sup_frequency, $med_freq_array_1)) {
                        $med_period = "24";
                    }
                    if (in_array($sup_row->sup_frequency, $med_freq_array_2)) {
                        $med_period = "12";
                    }
                    if (in_array($sup_row->sup_frequency, $med_freq_array_3)) {
                        $med_period = "8";
                    }
                    if (in_array($sup_row->sup_frequency, $med_freq_array_4)) {
                        $med_period = "6";
                    }
                    if (in_array($sup_row->sup_frequency, $med_freq_array_5)) {
                        $med_period = "4";
                    }
                    if (in_array($sup_row->sup_frequency, $med_freq_array_6)) {
                        $med_period = "3";
                    }
                    if (in_array($sup_row->sup_frequency, $med_freq_array_7)) {
                        $med_period = "2";
                    }
                    if (in_array($sup_row->sup_frequency, $med_freq_array_8)) {
                        $med_period = "1";
                    }
                }
                $med_table .= "<td>" . $instructions . "</td>";
                $med_table .= "<td>" . date('m-d-Y', $this->human_to_unix($sup_row->sup_date_active)) . "</td>";
                $med_table .= "<td>Active</td>";
                $med_table .= "<td>" . $sup_row->sup_reason . "</td>";
                $med_table .= "</tr>";
                $med_file = File::get(resource_path() . '/medications.xml');
                $med_number = "#med" . $k;
                $med_random_id1 = $this->gen_uuid();
                $med_random_id2 = $this->gen_uuid();
                $med_file = str_replace('?med_random_id1?', $med_random_id1, $med_file);
                $med_file = str_replace('?med_random_id2?', $med_random_id2, $med_file);
                $med_file = str_replace('?med_number?', $med_number, $med_file);
                $med_file = str_replace('?med_date_active?', date('Ymd', $this->human_to_unix($sup_row->sup_date_active)), $med_file);
                $med_file = str_replace('?med_code?', $med_code, $med_file);
                $med_file = str_replace('?med_code_description?', $med_code_description, $med_file);
                $med_file = str_replace('?med_period?', $med_period, $med_file);
                $med_file = str_replace('?med_dosage?', $med_dosage, $med_file);
                $med_file = str_replace('?med_dosage_unit?', $med_dosage_unit, $med_file);
                $med_rxnorm_code = '';
                $med_name = $sup_row->sup_supplement . ' ' . $sup_row->sup_dosage . ' ' . $sup_row->sup_dosage_unit ;
                $med_file = str_replace('?med_rxnorm_code?', $med_rxnorm_code, $med_file);
                $med_file = str_replace('?med_name?', $med_name, $med_file);
                $med_file_final .= $med_file;
                $k++;
            }
        }
        $ccda = str_replace('?med_table?', $med_table, $ccda);
        $ccda = str_replace('?med_file?', $med_file_final, $ccda);
        $orders_table = "";
        $orders_file_final = "";
        if ($recent_encounter_query) {
            $orders_query = DB::table('orders')->where('eid', '=', $recent_encounter_query->eid)->get();
            if ($orders_query->count()) {
                foreach ($orders_query as $orders_row) {
                    if ($orders_row->orders_labs != '') {
                        $orders_labs_array = explode("\n",$orders_row->orders_labs);
                        $n1 = 1;
                        foreach ($orders_labs_array as $orders_labs_row) {
                            $orders_table .= "<tr>";
                            $orders_table .= "<td><content ID='orders_labs_" . $n1 . "'>" . $orders_labs_row . "</td>";
                            $orders_table .= "<td>" . date('m-d-Y', $this->human_to_unix($orders_row->orders_date)) . "</td>";
                            $orders_table .= "</tr>";
                            $orders_file_final .= $this->get_snomed_code($orders_labs_row, $orders_row->orders_date, '#orders_lab_' . $n1);
                            $n1++;
                        }
                    }
                    if ($orders_row->orders_radiology != '') {
                        $orders_rad_array = explode("\n",$orders_row->orders_radiology);
                        $n2 = 1;
                        foreach ($orders_rad_array as $orders_rad_row) {
                            $orders_table .= "<tr>";
                            $orders_table .= "<td><content ID='orders_rad_" . $n2 . "'>" . $orders_rad_row . "</td>";
                            $orders_table .= "<td>" . date('m-d-Y', $this->human_to_unix($orders_row->orders_date)) . "</td>";
                            $orders_table .= "</tr>";
                            $orders_file_final .= $this->get_snomed_code($orders_rad_row, $orders_row->orders_date, '#orders_rad_' . $n2);
                            $n2++;
                        }
                    }
                    if ($orders_row->orders_cp != '') {
                        $orders_cp_array = explode("\n",$orders_row->orders_cp);
                        $n3 = 1;
                        foreach ($orders_cp_array as $orders_cp_row) {
                            $orders_table .= "<tr>";
                            $orders_table .= "<td><content ID='orders_cp_" . $n3 . "'>" . $orders_cp_row . "</td>";
                            $orders_table .= "<td>" . date('m-d-Y', $this->human_to_unix($orders_row->orders_date)) . "</td>";
                            $orders_table .= "</tr>";
                            $orders_file_final .= $this->get_snomed_code($orders_cp_row, $orders_row->orders_date, '#orders_cp_' . $n3);
                            $n3++;
                        }
                    }
                    if ($orders_row->orders_referrals != '') {
                        $referral_orders = explode("\nRequested action:\n",$orders_row->orders_referrals);
                        if (count($referral_orders) > 1) {
                            $orders_ref_array = explode("\n",$referral_orders[0]);
                            $n4 = 1;
                            foreach ($orders_ref_array as $orders_ref_row) {
                                $orders_table .= "<tr>";
                                $orders_table .= "<td><content ID='orders_ref_" . $n4 . "'>" . $orders_ref_row . "</td>";
                                $orders_table .= "<td>" . date('m-d-Y', $this->human_to_unix($orders_row->orders_date)) . "</td>";
                                $orders_table .= "</tr>";
                                $orders_file_final .= $this->get_snomed_code($orders_ref_row, $orders_row->orders_date, '#orders_ref_' . $n4);
                                $n4++;
                            }
                        }
                    }
                }
            }
        }
        $ccda = str_replace('?orders_table?', $orders_table, $ccda);
        $ccda = str_replace('?orders_file?', $orders_file_final, $ccda);
        $issues_query = DB::table('issues')->where('pid', '=', $pid)->get();
        $issues_table = "";
        $issues_file_final = "";
        if ($issues_query->count()) {
            $l = 1;
            foreach ($issues_query as $issues_row) {
                $issues_table .= "<list listType='ordered'>";
                $issue_code = '';
                $issues_array = explode(' [', $issues_row->issue);
                if (count($issues_array) > 1) {
                    $issue_code = str_replace("]", "", $issues_array[1]);
                }
                $issue_code_description = $issues_array[0];
                if ($issues_row->issue_date_inactive != '0000-00-00 00:00:00') {
                    $issues_table .= "<item><content ID='problem" . $l . "'>" . $issues_row->issue . ": Status - Resolved</content></item>";
                    $issues_status = "Resolved";
                    $issues_code = "413322009";
                    $issues_file = File::get(resource_path() . '/issues_inactive.xml');
                    $issues_file = str_replace('?issue_date_inactive?', date('Ymd', $this->human_to_unix($issues_row->issue_date_inactive)), $issues_file);
                } else {
                    $issues_table .= "<item><content ID='problem" . $l . "'>" . $issues_row->issue . ": Status - Active</content></item>";
                    $issues_status = "Active";
                    $issues_code = "55561003";
                    $issues_file = File::get(resource_path() . '/issues_active.xml');
                }
                $issues_table .= "</list>";
                $issues_file = str_replace('?issue_date_active?', date('Ymd', $this->human_to_unix($issues_row->issue_date_active)), $issues_file);
                $issues_file = str_replace('?issue_code?', $issue_code, $issues_file);
                $issues_file = str_replace('?issue_code_description?', $issue_code_description, $issues_file);
                $issues_number = "#problem" . $l;
                $issues_random_id1 = $this->gen_uuid();
                $issues_file = str_replace('?issues_random_id1?', $issues_random_id1, $issues_file);
                $issues_file = str_replace('?issues_number?', $issues_number, $issues_file);
                $issues_file = str_replace('?issues_code?', $issues_code, $issues_file);
                $issues_file = str_replace('?issues_status?', $issues_status, $issues_file);
                $issues_file_final .= $issues_file;
                $l++;
            }
        }
        $ccda = str_replace('?issues_table?', $issues_table, $ccda);
        $ccda = str_replace('?issues_file?', $issues_file_final, $ccda);
        $proc_table = "";
        $proc_file_final = "";
        if ($recent_encounter_query) {
            $pre_proc = $this->procedure_build($recent_encounter_query->eid);
            if (! empty($pre_proc)) {
                $n = 1;
                foreach ($pre_proc as $pre_proc_item) {
                    $proc_table .= "<tr>";
                    $proc_table .= "<td><content ID='proc" . $n . "'>" . $pre_proc_item['type'] . "</content></td>";
                    $proc_table .= "<td>" . date('m-d-Y', $this->human_to_unix($pre_proc_item['date'])) . "</td>";
                    $proc_table .= "</tr>";
                    $proc_file = File::get(resource_path() . '/proc.xml');
                    $proc_file = str_replace('?proc_date?', date('Ymd', $this->human_to_unix($pre_proc_item['date'])), $proc_file);
                    $proc_file = str_replace('?proc_type?', $pre_proc_item['type'], $proc_file);
                    $proc_file = str_replace('?proc_cpt?', $pre_proc_item['code'] , $proc_file);
                    $proc_file = str_replace('?practiceinfo_street_address?', $practice_info->street_address1, $proc_file);
                    $proc_file = str_replace('?practiceinfo_city?', $practice_info->city, $proc_file);
                    $proc_file = str_replace('?practiceinfo_state?', $practice_info->state, $proc_file);
                    $proc_file = str_replace('?practiceinfo_zip?', $practice_info->zip, $proc_file);
                    $proc_file = str_replace('?practiceinfo_phone?', $practice_info->phone, $proc_file);
                    $proc_file = str_replace('?practice_name?', $practice_info->practice_name, $proc_file);
                    $proc_number = "#proc" . $n;
                    $proc_random_id1 = $this->gen_uuid();
                    $proc_file = str_replace('?proc_random_id1?', $proc_random_id1, $proc_file);
                    $proc_file_final .= $proc_file;
                    $n++;
                }
            }
        }
        $ccda = str_replace('?proc_table?', $proc_table, $ccda);
        $ccda = str_replace('?proc_file?', $proc_file_final, $ccda);
        $other_history_table = "";
        $other_history_file = "";
        if ($recent_encounter_query) {
            $other_history_row = DB::table('other_history')->where('eid', '=', $recent_encounter_query->eid)->first();
            if ($other_history_row) {
                if ($other_history_row->oh_tobacco != '') {
                    $other_history_table .= "<td>Smoking Status</td>";
                    $other_history_table .= "<td><content ID='other_history1'>" . $other_history_row->oh_tobacco . "</td>";
                    $other_history_table .= "<td>" . date('m-d-Y', $this->human_to_unix($other_history_row->oh_date)) . "</td>";
                    $other_history_table .= "</tr>";
                    $other_history_table .= "<tr>";
                    $other_history_code = "8392000";
                    $other_history_description = "Non-Smoker";
                    if ($demographics->tobacco == 'yes') {
                        $other_history_code = "77176002";
                        $other_history_description = "Smoker";
                    }
                    $other_history_file = File::get(resource_path() . '/social_history.xml');
                    $other_history_file = str_replace('?other_history_code?', $other_history_code, $other_history_file);
                    $other_history_file = str_replace('?other_history_description?', $other_history_description, $other_history_file);
                    $other_history_file = str_replace('?other_history_date?', date('Ymd', $this->human_to_unix($other_history_row->oh_date)), $other_history_file);
                }
            }
        }
        $ccda = str_replace('?other_history_table?', $other_history_table, $ccda);
        $ccda = str_replace('?other_history_file?', $other_history_file, $ccda);
        $vitals_table = "";
        $vitals_file_final = "";
        if ($recent_encounter_query) {
            $vitals_row = DB::table('vitals')->where('eid', '=', $recent_encounter_query->eid)->first();
            if ($vitals_row) {
                $vitals_table .= '<thead><tr><th align="right">Date / Time: </th><th>' . date('m-d-Y h:i A', $this->human_to_unix($vitals_row->vitals_date)) . '</th></tr></thead><tbody>';
                $vitals_file_final .= '               <entry typeCode="DRIV"><organizer classCode="CLUSTER" moodCode="EVN"><templateId root="2.16.840.1.113883.10.20.22.4.26"/><id root="';
                $vitals_file_final .= $this->gen_uuid() . '"/><code code="46680005" codeSystem="2.16.840.1.113883.6.96" codeSystemName="SNOMED-CT" displayName="Vital signs"/><statusCode code="completed"/><effectiveTime value="';
                $vitals_file_final .= date('Ymd', $this->human_to_unix($vitals_row->vitals_date)) . '"/>';
                if ($vitals_row->height != '') {
                    $vitals_table .= '<tr><th align="left">Height</th><td><content ID="vit_height">';
                    $vitals_table .= $vitals_row->height . ' ' . $practice_info->height_unit;
                    $vitals_table .= '</content></td></tr>';
                    $vitals_code1 = "8302-2";
                    $vitals_description1 = "Body height";
                    $vitals_file = File::get(resource_path() . '/vitals.xml');
                    $vitals_file = str_replace('?vitals_code?', $vitals_code1, $vitals_file);
                    $vitals_file = str_replace('?vitals_description?', $vitals_description1, $vitals_file);
                    $vitals_file = str_replace('?vitals_date?', date('Ymd', $this->human_to_unix($vitals_row->vitals_date)), $vitals_file);
                    $vitals_file = str_replace('?vitals_id?', '#vit_height', $vitals_file);
                    $vitals_file = str_replace('?vitals_value?', $vitals_row->height, $vitals_file);
                    $vitals_file = str_replace('?vitals_unit?', $practice_info->height_unit, $vitals_file);
                    $vitals_random_id1 = $this->gen_uuid();
                    $vitals_file = str_replace('?vitals_random_id1?', $vitals_random_id1, $vitals_file);
                }
                if ($vitals_row->weight != '') {
                    $vitals_table .= '<tr><th align="left">Weight</th><td><content ID="vit_weight">';
                    $vitals_table .= $vitals_row->weight . ' ' . $practice_info->weight_unit;
                    $vitals_table .= '</content></td></tr>';
                    $vitals_code2 = "3141-9";
                    $vitals_description2 = "Body weight Measured";
                    $vitals_file = File::get(resource_path() . '/vitals.xml');
                    $vitals_file = str_replace('?vitals_code?', $vitals_code2, $vitals_file);
                    $vitals_file = str_replace('?vitals_description?', $vitals_description2, $vitals_file);
                    $vitals_file = str_replace('?vitals_date?', date('Ymd', $this->human_to_unix($vitals_row->vitals_date)), $vitals_file);
                    $vitals_file = str_replace('?vitals_id?', '#vit_weight', $vitals_file);
                    $vitals_file = str_replace('?vitals_value?', $vitals_row->weight, $vitals_file);
                    $vitals_file = str_replace('?vitals_unit?', $practice_info->weight_unit, $vitals_file);
                    $vitals_random_id2 = $this->gen_uuid();
                    $vitals_file = str_replace('?vitals_random_id1?', $vitals_random_id2, $vitals_file);
                }
                if ($vitals_row->bp_systolic != '' && $vitals_row->bp_diastolic) {
                    $vitals_table .= '<tr><th align="left">Blood Pressure</th><td><content ID="vit_bp">';
                    $vitals_table .= $vitals_row->bp_systolic . '/' . $vitals_row->bp_diastolic . ' mmHg';
                    $vitals_table .= '</content></td></tr>';
                    $vitals_code3 = "8480-6";
                    $vitals_description3 = "Intravascular Systolic";
                    $vitals_file = File::get(resource_path() . '/vitals.xml');
                    $vitals_file = str_replace('?vitals_code?', $vitals_code3, $vitals_file);
                    $vitals_file = str_replace('?vitals_description?', $vitals_description3, $vitals_file);
                    $vitals_file = str_replace('?vitals_date?', date('Ymd', $this->human_to_unix($vitals_row->vitals_date)), $vitals_file);
                    $vitals_file = str_replace('?vitals_id?', '#vit_bp', $vitals_file);
                    $vitals_file = str_replace('?vitals_value?', $vitals_row->bp_systolic, $vitals_file);
                    $vitals_file = str_replace('?vitals_unit?', "mmHg", $vitals_file);
                    $vitals_random_id3 = $this->gen_uuid();
                    $vitals_file = str_replace('?vitals_random_id1?', $vitals_random_id3, $vitals_file);
                }
                $vitals_table .= '</tbody>';
                $vitals_file_final .= '                  </organizer>';
                $vitals_file_final .= '               </entry>';
            }
        }
        $ccda = str_replace('?vitals_table?', $vitals_table, $ccda);
        $ccda = str_replace('?vitals_file?', $vitals_file_final, $ccda);
        return $ccda;
    }

    protected function generate_pdf($html, $filepath, $footer='footerpdf', $header='', $type='1', $headerparam='', $watermark='')
    {
        // if ($header != '') {
        //     if ($headerparam == '') {
        //         $pdf_options['header-center'] = $header;
        //         $pdf_options['header-font-size'] = 8;
        //     } else {
        //         $header = route($header, array($headerparam));
        //         $header = str_replace("https", "http", $header);
        //         $pdf_options['header-html'] = $header;
        //         $pdf_options['header-spacing'] = 5;
        //     }
        // }
        if ($header !== '') {
            PDF::setHeaderCallback(function($pdf) use ($header, $headerparam) {
                // Set font
                $pdf->SetFont('helvetica', 'B', 10);
                // Title
                if ($header == 'mtmheaderpdf') {
                    $row = DB::table('demographics')->where('pid', '=', $headerparam)->first();
                    $date = explode(" ", $row->DOB);
                    $date1 = explode("-", $date[0]);
                    $patientDOB = $date1[1] . "/" . $date1[2] . "/" . $date1[0];
                    $patientInfo1 = $row->firstname . ' ' . $row->lastname;
                    $header_html = '<body style="font-size:0.8em;margin:0; padding:0;">';
                    $page = $pdf->PageNo();
                    if ($page > 1) {
                        $header_html .= '<div style="width:6.62in;text-align:center;border: 1px solid black;"><b style="font-variant: small-caps;">Personal Medication List For';
                        $header_html .= $row->firstname . ' ' . $row->lastname . ', ' . $date1[1] . "/" . $date1[2] . "/" . $date1[0] . '</b></div><br>(Continued)';
                    }
                    $header_html .= '</body>';
                } else {
                    $header_html = '<body style="font-size:0.8em;margin:0; padding:0;">';
                    $header_html .= $header;
                    $header_html .= '</body>';
                }
                $pdf->writeHTML($header_html, true, false, false, false, '');
            });
        }
        PDF::setFooterCallback(function($pdf) use ($footer) {
            // Position at 35 mm from bottom
            $pdf->SetY(-40);
            // Set font
            $pdf->SetFont('helvetica', 'I', 8);
            if ($footer == 'footerpdf') {
                $footer_html = '<div style="border-top: 1px solid #000000; text-align: center; padding-top: 3mm; font-size: 8px;">Page ' . $pdf->getAliasNumPage();
                $footer_html .= ' of ' . $pdf->getAliasNbPages() . '</div><p style="text-align:center; font-size: 8px;">';
                $footer_html .= "CONFIDENTIALITY NOTICE: The information contained in this document or facsimile transmission is intended for the recipient named above. If you are not the intended recipient or the intended recipient's agent, you are hereby notified that dissemination, copying, or distribution of the information contained in the transmission is prohibited. If you are not the intended recipient, please notify us immediately by telephone and return the documents to us by mail. Thank you.</p>";
                $footer_html .= '<p style="text-align:center; font-size: 8px;">This document was generated by NOSH ChartingSystem</p>';
            }
            if ($footer == 'mtmfooterpdf') {
                $footer_html = '<div style="border-top: 1px solid #000000; font-family: Arial, Helvetica, sans-serif; font-size: 7;">';
                $footer_html .= '<table><tr><td>Form CMS-10396 (01/12)</td><td style="text-align:right;">Form Approved OMB No. 0938-1154</td></tr></table>';
                $footer_html .- '<div style="text-align:center; font-family: ' . "'Times New Roman'" . ', Times, serif; font-size: 12;">" Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages() . '</div>';
            }
            // Page number
            $pdf->writeHTML($footer_html, true, false, false, false, '');
            // $pdf->Cell(0, 10, 'Page '.$pdf->getAliasNumPage().'/'.$pdf->getAliasNbPages(), 1, false, 'C', 0, '', 0, false, 'T', 'M');

        });
        PDF::SetAuthor('NOSH ChartingSystem');
        PDF::SetTitle('NOSH PDF Document');
        if ($type == '1') {
            PDF::SetMargins('26', '26' ,'26', true);
        }
        if ($type == '2') {
            PDF::SetMargins('16', '26' ,'16', true);
        }
        if ($type == '3') {
            PDF::SetMargins('16', '16' ,'16', true);
        }
        PDF::SetFooterMargin('40');
        PDF::SetFont('freeserif', '', 10);
        PDF::AddPage();
        PDF::SetAutoPageBreak(TRUE, '40');
        PDF::writeHTML($html, true, false, false, false, '');
        if ($watermark !== '') {
            if ($watermark == 'void') {
                $watermark_file = 'voidstamp.png';
            }
            PDF::SetAlpha(0.5);
            PDF::Image(resource_path() . '/' . $watermark_file, '0', '0', '', '', 'PNG', false, 'C', false, 300, 'C', false, false, 0 ,false, false, false);
        }
        PDF::Output($filepath, 'F');
        PDF::reset();
        return true;
    }

    protected function get_headers_from_curl_response($headerContent)
    {
        $headers = [];
        // Split the string on every "double" new line.
        $arrRequests = explode("\r\n\r\n", $headerContent);
        // Loop of response headers. The "count() -1" is to avoid an empty row for the extra line break before the body of the response.
        for ($index = 0; $index < count($arrRequests) -1; $index++) {
            foreach (explode("\r\n", $arrRequests[$index]) as $i => $line) {
                if ($i === 0) {
                    $headers[$index]['http_code'] = $line;
                } else {
                    list ($key, $value) = explode(': ', $line);
                    $headers[$index][$key] = $value;
                }
            }
        }
        return $headers;
    }

    protected function get_id($text)
    {
        preg_match('#\((.*?)\)#', $text, $match);
        return $match[1];
    }

    protected function getDimensions($width, $height, $frameWidth, $frameHeight)
    {
        if($width > $height) {
            $newWidth = $frameWidth;
            $newHeight = $frameWidth/$width*$height;
        } else {
            $newHeight = $frameHeight;
            $newWidth = $frameHeight/$height*$width;
        }
        return ['scaledWidth' => $newWidth , 'scaledHeight' => $newHeight];
    }

    protected function getImageFile($file)
    {
        $type =  exif_imagetype($file);
        switch($type){
            case 2:
                $img = imagecreatefromjpeg($file);
                break;
            case 1:
                $img = imagecreatefromgif($file);
                break;
            case 3:
                $img = imagecreatefrompng($file);
                break;
            default:
                $img = false;
                break;
        }
        return $img;
    }

    protected function getNumberAppts($id)
    {
        $start_time = strtotime("today 00:00:00");
        $end_time = $start_time + 86400;
        return DB::table('schedule')->where('provider_id', '=', $id)->whereBetween('start', array($start_time, $end_time))->count();
    }

    protected function get_results()
    {
        $dir = '/srv/ftp/shared/import/';
        $files = scandir($dir);
        $count = count($files);
        $full_count=0;
        for ($i = 2; $i < $count; $i++) {
            $line = $files[$i];
            $file = $dir . $line;
            $hl7 = file_get_contents($file);
            $hl7_lines = explode("\r", $hl7);
            $results = array();
            $j = 0;
            $result_last = '';
            $from = '';
            foreach ($hl7_lines as $line) {
                $line_section = explode("|", $line);
                if ($line_section[0] == "MSH") {
                    if (strpos($line_section[3], "LAB") !== FALSE) {
                        $test_type = "Laboratory";
                    } else {
                        $test_type = "Imaging";
                    }
                }
                if ($line_section[0] == "PID") {
                    $name_section = explode("^", $line_section[5]);
                    $lastname = $name_section[0];
                    $firstname = $name_section[1];
                    $year = substr($line_section[7], 0, 4);
                    $month = substr($line_section[7], 4, 2);
                    $day = substr($line_section[7], 6, 2);
                    $dob = $year . "-" . $month . "-" . $day . " 00:00:00";
                    $sex = strtolower($line_section[8]);
                }
                if ($line_section[0] == "ORC") {
                    $provider_section = explode("^", $line_section[12]);
                    $provider_lastname = $provider_section[1];
                    $provider_firstname = $provider_section[2];
                    $provider_id = $provider_section[0];
                    $practice_section = explode("^", $line_section[17]);
                    $practice_lab_id = $practice_section[0];
                }
                if ($line_section[0] == "OBX") {
                    $test_name_section = explode("^", $line_section[3]);
                    $results[$j]['test_name'] = $test_name_section[1];
                    $results[$j]['test_result'] = $line_section[5];
                    $results[$j]['test_units'] = $line_section[6];
                    $results[$j]['test_reference'] = $line_section[7];
                    $results[$j]['test_flags'] = $line_section[8];
                    $year1 = substr($line_section[14], 0, 4);
                    $month1 = substr($line_section[14], 4, 2);
                    $day1 = substr($line_section[14], 6, 2);
                    $hour1 = substr($line_section[14], 8, 2);
                    $minute1 = substr($line_section[14], 10, 2);
                    $results[$j]['test_datetime'] = $year1 . "-" . $month1 . "-" . $day1 . " " . $hour1 . ":" . $minute1 .":00";
                    $j++;
                }
                if ($line_section[0] == "NTE") {
                    if ($line_section[1] == '1') {
                        $result_last = $j - 1;
                    }
                    if ($line_section[2] == "TX") {
                        $from = $line_section[3] . ", Ordering Provider: " . $provider_firstname . ' ' . $provider_lastname;
                        $keys = array_keys($results);
                        foreach ($keys as $key) {
                            $results[$key]['test_from'] = $from;
                        }
                    } else {
                        $results[$result_last]['test_result'] .= "\n" . $line_section[3];
                    }
                }
            }
            $practice_id = '';
            $practice_row_test = DB::table('practiceinfo')->where('peacehealth_id', '=', $practice_lab_id)->first();
            if (!$practice_row_test) {
                $patient_row = DB::table('demographics')->where('lastname', '=', $lastname)->where('firstname', '=', $firstname)->where('DOB', '=', $dob)->where('sex', '=', $sex)->first();
                if ($patient_row) {
                    $pid = $patient_row->pid;
                    $demo_relate = DB::table('demographics_relate')->where('pid', '=', $pid)->first();
                    if ($demo_relate) {
                        $practice_id = $demo_relate->practice_id;
                    }
                }
            } else {
                $practice_id = $practice_row_test->practice_id;
            }
            if ($practice_id != '') {
                $practice_row = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
                Config::set('app.timezone' , $practice_row->timezone);
                $provider_row = DB::table('users')
                    ->join('providers', 'providers.id', '=', 'users.id')
                    ->select('users.lastname', 'users.firstname', 'users.title', 'users.id')
                    ->where('providers.peacehealth_id', '=', $provider_id)
                    ->first();
                if ($provider_row) {
                    $provider_id = $provider_row->id;
                } else {
                    $provider_id = '';
                }
                $patient_row = DB::table('demographics')->where('lastname', '=', $lastname)->where('firstname', '=', $firstname)->where('DOB', '=', $dob)->where('sex', '=', $sex)->first();
                if ($patient_row) {
                    $pid = $patient_row->pid;
                    $dob_message = date("m/d/Y", strtotime($patient_row->DOB));
                    $patient_name =  $patient_row->lastname . ', ' . $patient_row->firstname . ' (DOB: ' . $dob_message . ') (ID: ' . $pid . ')';
                    $tests = 'y';
                    $test_desc = "";
                    $k = 0;
                    foreach ($results as $results_row) {
                        $test_data = [
                            'pid' => $pid,
                            'test_name' => $results_row['test_name'],
                            'test_result' => $results_row['test_result'],
                            'test_units' => $results_row['test_units'],
                            'test_reference' => $results_row['test_reference'],
                            'test_flags' => $results_row['test_flags'],
                            'test_from' => $from,
                            'test_datetime' => $results_row['test_datetime'],
                            'test_type' => $test_type,
                            'test_provider_id' => $provider_id,
                            'practice_id' => $practice_id
                        ];
                        DB::table('tests')->insert($test_data);
                        $this->audit('Add');
                        if ($k == 0) {
                            $test_desc .= $results_row['test_name'];
                        } else {
                            $test_desc .= ", " . $results_row['test_name'];
                        }
                        $k++;
                    }
                    $directory = $practice_row->documents_dir . $pid;
                    $file_path = $directory . '/tests_' . time() . '.pdf';
                    $html = $this->page_intro('Test Results', $practice_id);
                    $html .= $this->page_results($pid, $results, $patient_name);
                    $this->generate_pdf($html, $file_path);
                    $documents_date = date("Y-m-d H:i:s", time());
                    $test_desc = 'Test results for ' . $patient_name;
                    $pages_data = [
                        'documents_url' => $file_path,
                        'pid' => $pid,
                        'documents_type' => $test_type,
                        'documents_desc' => $test_desc,
                        'documents_from' => $from,
                        'documents_date' => $documents_date
                    ];
                    $documents_id = DB::table('documents')->insertGetId($pages_data);
                    $this->audit('Add');
                } else {
                    $messages_pid = '';
                    $patient_name = "Unknown patient: " . $lastname . ", " . $firstname . ", DOB: " . $month . "/" . $day . "/" . $year;
                    $tests = 'unk';
                    foreach ($results as $results_row) {
                        $test_data = [
                            'test_name' => $results_row['test_name'],
                            'test_result' => $results_row['test_result'],
                            'test_units' => $results_row['test_units'],
                            'test_reference' => $results_row['test_reference'],
                            'test_flags' => $results_row['test_flags'],
                            'test_unassigned' => $patient_name,
                            'test_from' => $from,
                            'test_datetime' => $results_row['test_datetime'],
                            'test_type' => $test_type,
                            'test_provider_id' => $provider_id,
                            'practice_id' => $practice_id
                        ];
                        DB::table('tests')->insert($test_data);
                        $this->audit('Add');
                    }
                    $documents_id = '';
                }
                $subject = "Test results for " . $patient_name;
                $body = "Test results for " . $patient_name . "\n\n";
                foreach ($results as $results_row1) {
                    $body .= $results_row1['test_name'] . ": " . $results_row1['test_result'] . ", Units: " . $results_row1['test_units'] . ", Normal reference range: " . $results_row1['test_reference'] . ", Date: " . $results_row1['test_datetime'] . "\n";
                }
                $body .= "\n" . $from;
                if ($tests="unk") {
                    $body .= "\n" . "Patient is unknown to the system.  Please reconcile this test result in your dashboard.";
                }
                if ($provider_id != '') {
                    $provider_name = $provider_row->firstname . " " . $provider_row->lastname . ", " . $provider_row->title . " (" . $provider_id . ")";
                    $data_message = [
                        'pid' => $pid,
                        'message_to' => $provider_name,
                        'message_from' => $provider_row['id'],
                        'subject' => $subject,
                        'body' => $body,
                        'patient_name' => $patient_name,
                        'status' => 'Sent',
                        'mailbox' => $provider_id,
                        'practice_id' => $practice_id,
                        'documents_id' => $documents_id
                    ];
                    DB::table('messaging')->insert($data_message);
                    $this->audit('Add');
                }
                $file1 = str_replace('/srv/ftp/shared/import/', '', $file);
                rename($file, $practice_row->documents_dir . $file1);
                $full_count++;
            } else {
                $file1 = str_replace('/srv/ftp/shared/import/', '', $file);
                rename($file, $practice_row->documents_dir . $file1 . '.error');
            }
        }
        return $full_count;
    }

    protected function get_scans($practice_id)
    {
        $result = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        Config::set('app.timezone' , $result->timezone);
        $dir = $result->documents_dir . 'scans/' . $practice_id;
        if (!file_exists($dir)) {
            mkdir($dir, 0777);
        }
        $files = scandir($dir);
        $count = count($files);
        $j=0;
        for ($i = 2; $i < $count; $i++) {
            $line = $files[$i];
            $filePath = $dir . "/" . $line;
            $check = DB::table('scans')->where('fileName', '=', $line)->first();
            if (!$check) {
                $date = fileatime($filePath);
                $fileDateTime = date('Y-m-d H:i:s', $date);
                $pdftext = file_get_contents($filePath);
                $filePages = preg_match_all("/\/Page\W/", $pdftext, $dummy);
                $data = [
                    'fileName' => $line,
                    'filePath' => $filePath,
                    'fileDateTime' => $fileDateTime,
                    'filePages' => $filePages,
                    'practice_id' => $practice_id
                ];
                DB::table('scans')->insert($data);
                $this->audit('Add');
                $j++;
            }
        }
        return $j;
    }

    protected function get_snomed_code($item, $date, $id)
    {
        $pos = strpos($item, ", SNOMED : ");
        $pos1 = strpos($item, ", CPT: ");
        if ($pos !== false) {
            $items = explode(", SNOMED: ", $item);
            $term_row = $this->snomed($items[1], true);
            $orders_file1 = File::get(resource_path() . '/orders.xml');
            $orders_file1 = str_replace('?orders_date?', date('Ymd', $this->human_to_unix($date)), $orders_file1);
            $orders_file1 = str_replace('?orders_code?', $items[1], $orders_file1);
            $orders_file1 = str_replace('?orders_code_description?', $term_row['description'], $orders_file1);
            $orders_random_id1 = $this->gen_uuid();
            $orders_file = str_replace('?orders_random_id1?', $orders_random_id1, $orders_file1);
        } elseif ($pos1 !== false) {
            $items = explode(", CPT: ", $item);
            $orders_code_description = $this->cpt_search($items[1]);
            $orders_file2 = File::get(resource_path() . '/orders_cpt.xml');
            $orders_file2 = str_replace('?orders_date?', date('Ymd', $this->human_to_unix($date)), $orders_file2);
            $orders_file2 = str_replace('?orders_code?', $items[1], $orders_file2);
            $orders_file2 = str_replace('?orders_code_description?', $orders_code_description, $orders_file2);
            $orders_random_id2 = $this->gen_uuid();
            $orders_file = str_replace('?orders_random_id1?', $orders_random_id2, $orders_file2);
        } else {
            $orders_file3 = File::get(resource_path() . '/orders_generic.xml');
            $orders_file3 = str_replace('?orders_date?', date('Ymd', $this->human_to_unix($date)), $orders_file3);
            $orders_file3 = str_replace('?orders_description?', $item, $orders_file3);
            $orders_file3 = str_replace('?orders_reference_id?', $id, $orders_file3);
            $orders_random_id3 = $this->gen_uuid();
            $orders_file = str_replace('?orders_random_id1?', $orders_random_id3, $orders_file3);
        }
        return $orders_file;
    }

    protected function github_all()
    {
        $client = new \Github\Client(
            new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
        );
        $client = new \Github\HttpClient\CachedHttpClient();
        $client->setCache(
            new \Github\HttpClient\Cache\FilesystemCache('/tmp/github-api-cache')
        );
        $client = new \Github\Client($client);
        $result = $client->api('repo')->commits()->all('shihjay2', 'nosh2', ['sha' => 'master']);
        return $result;
    }

    protected function github_release()
    {
        $client = new \Github\Client(
            new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
        );
        $client = new \Github\HttpClient\CachedHttpClient();
        $client->setCache(
            new \Github\HttpClient\Cache\FilesystemCache('/tmp/github-api-cache')
        );
        $client = new \Github\Client($client);
        $result = $client->api('repo')->releases()->latest('shihjay2', 'nosh2');
        return $result;
    }

    protected function github_single($sha)
    {
        $client = new \Github\Client(
            new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
        );
        $client = new \Github\HttpClient\CachedHttpClient();
        $client->setCache(
            new \Github\HttpClient\Cache\FilesystemCache('/tmp/github-api-cache')
        );
        $client = new \Github\Client($client);
        $result = $client->api('repo')->commits()->show('shihjay2', 'nosh2', $sha);
        return $result;
    }

    protected function goodrx($rx, $command, $api_key='46e983ffba', $secret_key='3QmFl8W7Y2Mb655bn++NNA==')
    {
        $url = 'https://api.goodrx.com/' . $command;
        $query_string = 'name=' . $rx . '&api_key=' . $api_key;
        if ($command == 'drug-search') {
            $query_string = 'query=' . $rx . '&api_key=' . $api_key;
        }
        $hash = hash_hmac('sha256', $query_string, $secret_key, true);
        $encoded = base64_encode($hash);
        $search = array('+','/');
        $sig = str_replace($search, '_', $encoded);
        $url .= '?' . $query_string . '&sig=' . $sig;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
        $result = curl_exec($ch);
        $result_array = json_decode($result, true);
        curl_close($ch);
        return $result_array;
    }

    protected function goodrx_drug_search($rx)
    {
        $rx = rtrim($rx, ',');
        $result = $this->goodrx($rx, 'drug-search');
        $drug = $rx;
        if ($result['success'] == true) {
            $drug = current($result['data']['candidates']);
        }
        return $drug;
    }

    protected function goodrx_information($rx, $dose)
    {
        $rx1 = explode(',', $rx);
        $rx_array = explode(' ', $rx1[0]);
        $dose_array = explode('/', $dose);
        $link = '';
        $result = $this->goodrx($rx_array[0], 'drug-info');
        $type_arr = [
            'chewable tablet' => 'chewable',
            'tablet' => 'tablet',
            'capsule' => 'capsule',
            'bottle of oral suspension' => 'suspension'
        ];
        if ($result['success'] == true) {
            $key = false;
            foreach ($rx_array as $item) {
                $key = array_search(strtolower($item), $type_arr);
                if ($key !== false) {
                    break;
                }
            }
            if ($key !== false) {
                if (isset($result['data']['drugs'][$key][$dose_array[0]])) {
                    $link = $result['data']['drugs'][$key][$dose_array[0]];
                }
            }
        }
        return $link;
    }

    protected function goodrx_notification($rx, $dose)
    {
        $row = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $row2 = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $to = $row->reminder_to;
        $rx1 = explode(',', $rx);
        $rx_array = explode(' ', $rx1[0]);
        $dose_array = explode('/', $dose);
        if ($to != '') {
            $result = $this->goodrx($rx_array[0], 'drug-info');
            if ($result['success'] == true) {
                if (isset($result['data']['drugs']['tablet'][$dose_array[0]])) {
                    $link = $result['data']['drugs']['tablet'][$dose_array[0]];
                } else {
                    $link = reset($result['data']['drugs']['tablet']);
                }
                if ($row->reminder_method == 'Cellular Phone') {
                    $data_message['item'] = 'New Medication: ' . $rx . '; ' . $link;
                    $message = view('emails.blank', $data_message)->render();
                    $this->textbelt($to, $message, $row2->practice_id);
                } else {
                    $data_message['item'] = 'You have a new medication prescribed to you: ' . $rx . '; For more details, click here: ' . $link;
                    $this->send_mail('emails.blank', $data_message, 'New Medication', $to, Session::get('practice_id'));
                }
            }
        }
    }

    protected function hcfa($eid)
    {
        $query = DB::table('billing')->where('eid', '=', $eid)->get();
        $input1 = '';
        if ($query->count()) {
            $i = 0;
            $file_root = public_path() . '/temp/';
            $file_name = time() . '_' . Session::get('user_id') . '_printhcfa_';
            $file_path = $file_root . $file_name . 'final.pdf';
            $pdf = new Cezpdf('LETTER');
            $pdf->ezSetMargins(19, 0, 24, 0);
            $pdf->selectFont('Courier');
            foreach ($query as $pdfinfo) {
                $input = resource_path() . '/cms1500new.pdf';
                $print_image = $this->printimage($eid);
                $img_file = resource_path() . '/cms1500.png';
                if ($i > 0) {
                    $pdf->ezNewPage();
                }
                $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);
                $pdf->addPngFromFile($img_file, 0, 0, 612, 792);
                $pdf->ezText($print_image, 12, [
                   'justification' => 'left',
                   'leading' => 12
                ]);
                $i++;
            }
            File::put($file_path, $pdf->ezOutput());
            $data1['bill_submitted'] = 'Done';
            DB::table('encounters')->where('eid', '=', $eid)->update($data1);
            $this->audit('Update');
            return $file_path;
        } else {
            return FALSE;
        }
    }

    protected function header_build($arr, $type='')
    {
        $return = '<div class="panel panel-success"><div class="panel-heading"><div class="container-fluid panel-container"><div class="col-xs-8 text-left"><h5 class="panel-title" style="height:30px;display:table-cell !important;vertical-align:middle;">';
        if (is_array($arr)) {
            $return .= $type . '</h5></div><div class="col-xs-4 text-right"><a href="' . $arr[$type] . '" class="btn btn-default btn-sm">Edit</a></div></div></div><div class="panel-body"><div class="row">';
        } else {
            $return .= $arr . '</h5></div></div></div><div class="panel-body">';
        }
        return $return;
    }

    protected function hedis_aab($aab_result)
    {
        $data = [];
        $data['count'] = count($aab_result);
        $data['abx'] = 0;
        $data['percent_abx'] = 0;
        foreach ($query as $row) {
            $query1 = DB::table('rx')->where('eid', '=', $row->eid)->first();
            if ($query1) {
                if ($query1->rx_rx != '') {
                    $abx_count = 0;
                    $search = ['cillin','amox','zith','cef','kef','mycin','eryth','pen','bac','sulf','cycl','lox'];
                    foreach ($search as $needle) {
                        $pos = stripos($query1->rx_rx, $needle);
                        if ($pos !== false) {
                            $abx_count++;
                        }
                    }
                    if ($abx_count > 0) {
                        $data['abx']++;
                    }
                }
            }
        }
        $data['percent_abx'] = round($data['abx']/$data['count']*100);
        return $data;
    }

    protected function hedis_aba($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Adult BMI Assessment not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('vitals')->where('pid', '=', $pid)->where('BMI', '!=', '')->orderBy('eid', 'desc')->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('assessment')
                ->where('pid', '=', $pid)
                ->where(function($query_array1) {
                    $assessment_item_array = ['V85.0','V85.1','V85.21','V85.22','V85.23','V85.24','V85.25','V85.30','V85.31','V85.32','V85.33','V85.34','V85.35','V85.36','V85.37','V85.38','V85.39','V85.41','V85.42','V85.43','V85.44','V85.45','V85.51','V85.52','V85.53','V85.54','Z68.1','Z68.20','Z68.21','Z68.22','Z68.23','Z68.24','Z68.25','Z68.26','Z68.27','Z68.28','Z68.29','Z68.30','Z68.31','Z68.32','Z68.33','Z68.34','Z68.35','Z68.36','Z68.37','Z68.38','Z68.39','Z68.41','Z68.42','Z68.43','Z68.44','Z68.45'];
                    $i = 0;
                    foreach ($assessment_item_array as $assessment_item) {
                        if ($i == 0) {
                            $query_array1->where('assessment_icd1', '=', $assessment_item)->orWhere('assessment_icd2', '=', $assessment_item)->orWhere('assessment_icd3', '=', $assessment_item)->orWhere('assessment_icd4', '=', $assessment_item)->orWhere('assessment_icd5', '=', $assessment_item)->orWhere('assessment_icd6', '=', $assessment_item)->orWhere('assessment_icd7', '=', $assessment_item)->orWhere('assessment_icd8', '=', $assessment_item)->orWhere('assessment_icd9', '=', $assessment_item)->orWhere('assessment_icd10', '=', $assessment_item)->orWhere('assessment_icd11', '=', $assessment_item)->orWhere('assessment_icd12', '=', $assessment_item);
                        } else {
                            $query_array1->orWhere('assessment_icd1', '=', $assessment_item)->orWhere('assessment_icd2', '=', $assessment_item)->orWhere('assessment_icd3', '=', $assessment_item)->orWhere('assessment_icd4', '=', $assessment_item)->orWhere('assessment_icd5', '=', $assessment_item)->orWhere('assessment_icd6', '=', $assessment_item)->orWhere('assessment_icd7', '=', $assessment_item)->orWhere('assessment_icd8', '=', $assessment_item)->orWhere('assessment_icd9', '=', $assessment_item)->orWhere('assessment_icd10', '=', $assessment_item)->orWhere('assessment_icd11', '=', $assessment_item)->orWhere('assessment_icd12', '=', $assessment_item);
                        }
                        $i++;
                    }
                })
                ->orderBy('eid', 'desc')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $data['fix'][] = 'BMI needs to measured';
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Adult BMI Assessment performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_add($pid, $date)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Follow-Up Care for Children Prescribed ADHD Medication not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('encounters')
            ->where('pid', '=', $pid)
            ->where('addendum', '=', 'n')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->where('encounter_signed', '=', 'Yes')
            ->where('encounter_DOS', '>=', $date)
            ->get();
        if ($query) {
            foreach ($query as $row) {
                $query1 = DB::table('billing_core')
                    ->where('eid', '=', $row->eid)
                    ->where(function($query_array1) {
                        $add_item_array = ['90791','90792','90804','90805','90806','90807','90808','90809','90810','90811','90812','90813','90814','90815','90832','90833','90834','90836','90837','90838','90839','90840','96150','96151','96152','96153','96154','98960','98961','98962','98966','98967','98968','99078','99201','99202','99203','99204','99205','99211','99212','99213','99214','99215','99217','99218','99219','99220','99241','99242','99243','99244','99245','99341','99342','99343','99344','99345','99347','99348','99349','99350','99383','99384','99393','99394','99401','99402','99403','99404','99411','99412','99441','99442','99443','99510'];
                        $i = 0;
                        foreach ($add_item_array as $add_item) {
                            if ($i == 0) {
                                $query_array1->where('cpt', '=', $add_item);
                            } else {
                                $query_array1->orWhere('cpt', '=', $add_item);
                            }
                            $i++;
                        }
                    })
                    ->first();
                if ($query1) {
                    $score++;
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Follow-Up Care for Children Prescribed ADHD Medication performed';
            $data['goal'] = 'y';
        } else {
            $data['fix'][] = 'Encounters monitoring ADD needs to be performed';
        }
        return $data;
    }

    protected function hedis_amm($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Antidepressant Medication Management not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query1 = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->first();
        if ($query1) {
            $search = ['celexa','opram','prozac','fluoxetine','lexapro','luvox','paroxetine','paxil','pexeva','sarafem','sertraline','symbyax','viibryd','zoloft','cymbalta','effexor','pristiq','venlafaxine','khedezla','ptyline','amoxapine','anafranil','pramine','doxepin','elavil','limbitrol','norpramin','pamelor','surmontil','tofranil','vivactil','emsam','marplan','nardil','parnate','tranylcypromine','bupropion','aplenzin','budeprion','maprotiline','mirtazapine','nefazodone','oleptro','remeron','serzone','trazodone','wellbutrin','forfivo'];
            foreach ($search as $needle) {
                $pos = stripos($query1->rxl_medication, $needle);
                if ($pos !== false) {
                    $score++;
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Antidepressant Medication Management performed';
            $data['goal'] = 'y';
        } else {
            $data['fix'][] = 'Antidepressant medication is recommended.';
        }
        return $data;
    }

    protected function hedis_amr($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Medication Management for People with Asthma not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query1 = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->first();
        if ($query1) {
            $controller_count = 0;
            $rescue_count = 0;
            $search = ['budesonide','flovent','pulmicort','qvar','advair','aerobid','alvesco','asmanex','dulera','pulmicort','symbicort','breo','fluticasone','beclomethasone','flunisolide','ciclesonide','mometasone','cromolyn','phylline','lukast','singulair','accolate','theo'];
            foreach ($search as $needle) {
                $pos = stripos($query1->rxl_medication, $needle);
                if ($pos !== false) {
                    $controller_count++;
                }
            }
            $search1 = ['albuterol','ventolin','alupent','metproterenol'];
            foreach ($search1 as $needle1) {
                $pos1 = stripos($query1->rxl_medication, $needle1);
                if ($pos1 !== false) {
                    $rescue_count++;
                }
            }
            $total = $controller_count + $rescue_count;
            $ratio = 0;
            if ($total > 0) {
                $ratio = round($controller_count/$total*100);
            }
            if ($ratio > 50) {
                $score++;
            } else {
                $data['fix'][] = 'If the patient does not have mild, intermittent asthma, a ratio of controller medications to the total number of asthma medications of greater than 0.5 is recommended.';
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Medication Management for People with Asthma performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_art($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Disease Modifying Anti-Rheumatic Drug Therapy for Rheumatoid Arthritis not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query1 = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->first();
        if ($query1) {
            $search = ['methotrexate','azathioprine','cyclophosphamide','cyclosporine','azasan','cytoxan','gengraf','imuran','neoral','rheumatrex','trexall','embrel','remicade','cimzia','humira','simponi','cuprimine','hydroxychloroquine','sulfasalazine','actemra','arava','azulfidine','kineret','leflunomide','myochrysine','orencia','plaquenil','ridaura'];
            foreach ($search as $needle) {
                $pos = stripos($query1->rxl_medication, $needle);
                if ($pos !== false) {
                    $score++;
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Disease Modifying Anti-Rheumatic Drug Therapy for Rheumatoid Arthritis performed';
            $data['goal'] = 'y';
        } else {
            $data['fix'][] = 'DMARD is recommended.';
        }
        return $data;
    }

    protected function hedis_asm($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Use of Appropriate Medications for People with Asthma not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query1 = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->first();
        if ($query1) {
            $med_count = 0;
            $search = ['budesonide','flovent','pulmicort','qvar','advair','aerobid','alvesco','asmanex','dulera','pulmicort','symbicort','breo','fluticasone','beclomethasone','flunisolide','ciclesonide','mometasone','cromolyn','phylline','lukast','singulair','accolate','theo'];
            foreach ($search as $needle) {
                $pos = stripos($query1->rxl_medication, $needle);
                if ($pos !== false) {
                    $med_count++;
                }
            }
            if ($med_count > 0) {
                $score++;
            } else {
                $data['fix'][] = 'If the patient does not have mild, intermittent asthma, a controller medication is recommended.';
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Use of Appropriate Medications for People with Asthma performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_assessment_query($pid, $type, $assessment_item_array)
    {
        $query = DB::table('assessment')
            ->join('encounters', 'encounters.eid', '=', 'assessment.eid')
            ->where('encounters.addendum', '=', 'n')
            ->where('encounters.pid', '=', $pid)
            ->where('encounters.encounter_signed', '=', 'Yes');
        if ($type != 'all') {
            if ($type == 'year') {
                $date_param = date('Y-m-d H:i:s', time() - 31556926);
                $query->where('encounters.encounter_DOS', '>=', $date_param);
            } else {
                $date_param = date('Y-m-d H:i:s', strtotime($type));
                $query->where('encounters.encounter_DOS', '>=', $date_param);
            }
        }
        $query->where(function($query_array) use ($assessment_item_array) {
                $count = 0;
                foreach ($assessment_item_array as $assessment_item) {
                    if ($count == 0) {
                        $query_array->where('assessment.assessment_icd1', '=', $assessment_item)->orWhere('assessment.assessment_icd2', '=', $assessment_item)->orWhere('assessment.assessment_icd3', '=', $assessment_item)->orWhere('assessment.assessment_icd4', '=', $assessment_item)->orWhere('assessment.assessment_icd5', '=', $assessment_item)->orWhere('assessment.assessment_icd6', '=', $assessment_item)->orWhere('assessment.assessment_icd7', '=', $assessment_item)->orWhere('assessment.assessment_icd8', '=', $assessment_item)->orWhere('assessment.assessment_icd9', '=', $assessment_item)->orWhere('assessment.assessment_icd10', '=', $assessment_item)->orWhere('assessment.assessment_icd11', '=', $assessment_item)->orWhere('assessment.assessment_icd12', '=', $assessment_item);
                    } else {
                        $query_array->orWhere('assessment.assessment_icd1', '=', $assessment_item)->orWhere('assessment.assessment_icd2', '=', $assessment_item)->orWhere('assessment.assessment_icd3', '=', $assessment_item)->orWhere('assessment.assessment_icd4', '=', $assessment_item)->orWhere('assessment.assessment_icd5', '=', $assessment_item)->orWhere('assessment.assessment_icd6', '=', $assessment_item)->orWhere('assessment.assessment_icd7', '=', $assessment_item)->orWhere('assessment.assessment_icd8', '=', $assessment_item)->orWhere('assessment.assessment_icd9', '=', $assessment_item)->orWhere('assessment.assessment_icd10', '=', $assessment_item)->orWhere('assessment.assessment_icd11', '=', $assessment_item)->orWhere('assessment.assessment_icd12', '=', $assessment_item);
                    }
                    $count++;
                }
            })
            ->select('encounters.eid','encounters.pid');
        $result = $query->get();
        return $result;
    }

    protected function hedis_audit($type, $function, $pid)
    {
        $html = '';
        $return = [];
        $demographics = DB::table('demographics')->where('pid', '=', $pid)->first();
        $dob = $this->human_to_unix($demographics->DOB);
        // ABA
        if ($dob <= $this->age_calc(18,'year') && $dob >= $this->age_calc(74,'year')) {
            $return['aba'] = $this->hedis_aba($pid);
        }
        // WCC
        if ($dob <= $this->age_calc(3,'year') && $dob >= $this->age_calc(18,'year')) {
            $return['wcc'] = $this->hedis_wcc($pid);
        }
        // CIS
        if ($dob >= $this->age_calc(3,'year')) {
            $return['cis'] = $this->hedis_cis($pid);
        }
        // IMA
        if ($dob <= $this->age_calc(13,'year') && $dob >= $this->age_calc(18,'year')) {
            $return['ima'] = $this->hedis_ima($pid);
        }
        // HPV
        if ($dob <= $this->age_calc(9,'year') && $dob >= $this->age_calc(13,'year') && $demographics->sex == 'f') {
            $return['hpv'] = $this->hedis_hpv($pid);
        }
        // LSC
        if ($dob >= $this->age_calc(2,'year')) {
            $return['lsc'] = $this->hedis_lsc($pid);
        }
        // BCS
        if ($dob <= $this->age_calc(40,'year') && $dob >= $this->age_calc(69,'year') && $demographics->sex == 'f') {
            $return['bcs'] = $this->hedis_bcs($pid);
        }
        // CCS
        if ($dob <= $this->age_calc(21,'year') && $dob >= $this->age_calc(64,'year') && $demographics->sex == 'f') {
            $return['ccs'] = $this->hedis_ccs($pid);
        }
        // COL
        if ($dob <= $this->age_calc(50,'year') && $dob >= $this->age_calc(75,'year')) {
            $return['col'] = $this->hedis_col($pid);
        }
        // CHL
        if ($dob <= $this->age_calc(16,'year') && $dob >= $this->age_calc(24,'year') && $demographics->sex == 'f') {
            $return['chl'] = $this->hedis_chl($pid);
        }
        // GSO
        if ($dob <= $this->age_calc(65,'year')) {
            $return['gso'] = $this->hedis_gso($pid);
        }
        // CWP
        $cwp_assessment_item_array = ['462','J02.9','034.0','J02.0','J03.00','074.0','B08.5','474.00','J35.01','099.51','A56.4','032.0','A36.0','472.1','J31.2','098.6','A54.5'];
        $cwp_result = $this->hedis_assessment_query($pid, $type, $cwp_assessment_item_array);
        if ($cwp_result && $dob <= $this->age_calc(2,'year') && $dob >= $this->age_calc(18,'year')) {
            $return['cwp'] = $this->hedis_cwp($cwp_result);
        }
        // URI
        $uri_assessment_item_array = ['465.9','J06.9','487.1','J10.1','J11.1'];
        $uri_result = $this->hedis_assessment_query($pid, $type, $uri_assessment_item_array);
        if ($uri_result && $dob <= $this->age_calc(3,'month') && $dob >= $this->age_calc(18,'year')) {
            $return['uri'] = $this->hedis_uri($uri_result);
        }
        // AAB
        $aab_assessment_item_array = ['466.0','J20.9'];
        $aab_result = $this->hedis_assessment_query($pid, $type, $aab_assessment_item_array);
        if ($aab_result && $dob <= $this->age_calc(3,'month') && $dob >= $this->age_calc(18,'year')) {
            $return['aab'] = $this->hedis_uri($aab_result);
        }
        // SPR
        $spr_issues_item_array = ['496','J44.9'];
        $spr_query = $this->hedis_issue_query($pid, $spr_issues_item_array);
        if ($spr_query && $dob <= $this->age_calc(40,'year')) {
            $return['spr'] = $this->hedis_spr($pid);
        }
        // PCE
        $pce_assessment_item_array = ['491.21','J44.1'];
        $pce_result = $this->hedis_assessment_query($pid, $type, $pce_assessment_item_array);
        if ($pce_result && $dob <= $this->age_calc(40,'year')) {
            $return['pce'] = $this->hedis_pce($pce_result);
        }
        // ASM and AMR
        $asm_issues_item_array = ['493.90','J45.909','J45.998','493.00','J45.20','493.01','J45.22','493.02','J45.21','493.10','493.11','493.12','493.20','J44.9','493.21','J44.0','493.22','J44.1','493.81','J45.990','493.82','J45.991','493.91','J45.902','493.92','J45.901'];
        $asm_query = $this->hedis_issue_query($pid, $asm_issues_item_array);
        if ($asm_query && $dob <= $this->age_calc(5,'year') && $dob >= $this->age_calc(56,'year')) {
            $return['asm'] = $this->hedis_asm($pid);
            $return['amr'] = $this->hedis_amr($pid);
        }
        // CMC and PBH
        $cmc_issues_item_array = ['410','I20','I21','I22','I23','I24','I25','414.8'];
        $cmc_query = $this->hedis_issue_query($pid, $cmc_issues_item_array);
        if ($cmc_query && $dob <= $this->age_calc(18,'year') && $dob >= $this->age_calc(75,'year')) {
            $return['cmc'] = $this->hedis_cmc($pid);
        }
        if ($cmc_query && $dob <= $this->age_calc(18,'year')) {
            $return['pbh'] = $this->hedis_pbh($pid);
        }
        // CBP
        $cbp_issues_item_array = ['401','402','403','404','405','I10','I11','I12','I13','I15'];
        $cbp_query = $this->hedis_issue_query($pid, $cbp_issues_item_array);
        if ($cbp_query && $dob <= $this->age_calc(18,'year') && $dob >= $this->age_calc(85,'year')) {
            $return['cbp'] = $this->hedis_cbp($pid);
        }
        // CDC
        $cdc_issues_item_array = ['250','E08','E09','E10','E11','E13'];
        $cdc_query = $this->hedis_issue_query($pid, $cdc_issues_item_array);
        if ($cdc_query && $dob <= $this->age_calc(18,'year') && $dob >= $this->age_calc(75,'year')) {
            $return['cdc'] = $this->hedis_cdc($pid);
        }
        // ART
        $art_issues_item_array = ['714.0','M05','M06'];
        $art_query = $this->hedis_issue_query($pid, $art_issues_item_array);
        if ($art_query) {
            $return['art'] = $this->hedis_art($pid);
        }
        // OMW
        $omw_assessment_item_array = ['800','801','802','803','804','805','806','807','808','809','810','811','812','813','814','815','816','817','818','819','820','821','822','823','824','825','826','827','828','829','S02','S12','S22','S32','S42','S52','S62','S72','S82','S92'];
        $omw_result = $this->hedis_assessment_query($pid, $type, $omw_assessment_item_array);
        if ($omw_result && $dob <= $this->age_calc(67,'year') && $demographics->sex == 'f') {
            $return['omw'] = $this->hedis_omw($pid);
        }
        // LBP
        $lbp_assessment_item_array = ['724.2','M54.5'];
        $lbp_result = $this->hedis_assessment_query($pid, $type, $lbp_assessment_item_array);
        if ($lbp_result) {
            $return['lbp'] = $this->hedis_lbp($lbp_result);
        }
        // AMM
        $amm_issues_item_array = ['311','296.2','296.3','F32','F33'];
        $amm_query = $this->hedis_issue_query($pid, $amm_issues_item_array);
        if ($amm_query && $dob <= $this->age_calc(18,'year')) {
            $return['amm'] = $this->hedis_amm($pid);
        }
        // ADD
        $add_issues_item_array = ['314.0','F90'];
        $add_query = $this->hedis_issue_query($pid, $add_issues_item_array);
        if ($add_query && $dob <= $this->age_calc(6,'year') && $dob >= $this->age_calc(12,'year')) {
            $return['add'] = $this->hedis_add($pid, $add_query->issue_date_active);
        }
        if (!empty($return)) {
            foreach ($return as $item => $row) {
                if ($item != 'cwp' && $item != 'uri' && $item != 'aab' && $item != 'pce' && $item != 'lbp') {
                    $html .= $row['html'] . '<br><br>';
                    if (!empty($row['fix'])) {
                        $html .= '<div class="alert alert-danger"><strong>Fixes:</strong><ul>';
                        foreach ($row['fix'] as $row1) {
                            $html .= '<li>' . $row1 . '</li>';
                        }
                        $html .= '</ul></div>';
                    }
                } else {
                    if ($item == 'cwp') {
                        $html .= '<strong>Appropriate Testing for Children With Pharyngitis:</strong>';
                        $html .= '<ul><li>Percentage tested: ' . $row['percent_test'] . '</li>';
                        $html .= '<li>Percentage treated with antibiotics: ' . $row['percent_abx'] . '</li>';
                        $html .= '<li>Percentage treated with antibiotics without testing: ' . $row['percent_abx_no_test'] . '</li></ul>';
                    }
                    if ($item == 'uri') {
                        $html .= '<strong>Appropriate Treatment for Children With Upper Respiratory Infection:</strong>';
                        $html .= '<ul><li>Percentage treated with antibiotics: ' . $row['percent_abx'] . '</li></ul>';
                    }
                    if ($item == 'aab') {
                        $html .= '<strong>Avoidance of Antibiotic Treatment for Adults with Acute Bronchitis:</strong>';
                        $html .= '<ul><li>Percentage treated with antibiotics: ' . $row['percent_abx'] . '</li></ul>';
                    }
                    if ($item == 'pce') {
                        $html .= '<strong>Pharmacotherapy Management of COPD Exacerbation:</strong>';
                        $html .= '<ul><li>Percentage treated for COPD exacerbations: ' . $row['percent_tx'] . '</li></ul>';
                    }
                    if ($item == 'lbp') {
                        $html .= '<strong>Use of Imaging Studies for Low Back Pain:</strong>';
                        $html .= '<ul><li>Percentage of instances where no imaging study was performed for a diagnosis of low back pain: ' . $row['percent_no_rad'] . '</li></ul>';
                    }
                }
                $html .= '<hr class="ui-state-default"/>';
            }
        }
        if ($function == 'chart') {
            return $html;
        } else {
            return $return;
        }
    }

    protected function hedis_bcs($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Breast Cancer Screening not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('tests')->where('pid', '=', $pid)->where('test_name', 'LIKE', "%mammogram%")->orderBy('test_datetime', 'desc')->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where('documents_desc', 'LIKE', "%mammogram%")
                ->where('documents_type', '=', 'Imaging')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where('tags.tag', 'LIKE', "%mammogram%")
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $data['fix'][] = 'Mammogram needs to be performed';
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Breast Cancer Screening performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_cbp($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Controlling High Blood Pressure not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $systolic = 0;
        $diastolic = 0;
        $query = DB::table('vitals')->where('pid', '=', $pid)->where('bp_systolic', '!=', '')->where('bp_diastolic', '!=', '')->orderBy('eid', 'desc')->first();
        if ($query) {
            if ($query->bp_systolic < 140) {
                $systolic++;
            }
            if ($query->bp_diastolic < 90) {
                $diastolic++;
            }
            $score = $systolic + $diastolic;
            if ($score == 2) {
                $data['html'] = '<i class="fa fa-lg fa-check"></i> Controlling High Blood Pressure performed';
                $data['goal'] = 'y';
            } else {
                $data['fix'][] = 'Blood pressure needs to be under better control.';
            }
        } else {
            $data['fix'][] = 'Blood pressures need to be measured.';
        }
        return $data;
    }

    protected function hedis_ccs($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Cervical Cancer Screening not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('tests')->where('pid', '=', $pid)->where('test_name', 'LIKE', "%pap%")->orderBy('test_datetime', 'desc')->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where('documents_desc', 'LIKE', "%pap%")
                ->where('documents_type', '=', 'Laboratory')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where('tags.tag', 'LIKE', "%pap%")
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $data['fix'][] = 'Pap test needs to be performed';
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Cervical Cancer Screening performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_cdc($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Comprehensive Diabetes Care not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        // HgbA1c
        $query = DB::table('tests')->where('pid', '=', $pid)
            ->where(function($query_array) {
                    $query_array->where('test_name', 'LIKE', "%hgba1c%")
                        ->orWhere('test_name', 'LIKE', "%a1c%");
                })
            ->orderBy('test_datetime', 'desc')
            ->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where(function($query_array1) {
                    $query_array1->where('documents_desc', 'LIKE', "%hgba1c%")
                        ->orWhere('documents_desc', 'LIKE', "%a1c%");
                })
                ->where('documents_type', '=', 'Laboratory')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where(function($query_array2) {
                        $query_array2->where('tags.tag', 'LIKE', "%hgba1c%")
                            ->orWhere('tags.tag', 'LIKE', "%a1c%");
                    })
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $data['fix'][] = 'HgbA1c needs to be measured';
                }
            }
        }
        // LDL
        $query3 = DB::table('tests')->where('pid', '=', $pid)
            ->where(function($query_array3) {
                    $query_array3->where('test_name', 'LIKE', "%ldl%")
                        ->orWhere('test_name', 'LIKE', "%cholesterol%")
                        ->orWhere('test_name', 'LIKE', "%lipid%");
                })
            ->orderBy('test_datetime', 'desc')
            ->first();
        if ($query3) {
            $score++;
        } else {
            $query4 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where(function($query_array4) {
                    $query_array4->where('documents_desc', 'LIKE', "%ldl%")
                        ->orWhere('documents_desc', 'LIKE', "%cholesterol%")
                        ->orWhere('documents_desc', 'LIKE', "%lipid%");
                })
                ->where('documents_type', '=', 'Laboratory')
                ->first();
            if ($query4) {
                $score++;
            } else {
                $query5 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where(function($query_array5) {
                        $query_array5->where('tags.tag', 'LIKE', "%ldl%")
                            ->orWhere('tags.tag', 'LIKE', "%cholesterol%")
                            ->orWhere('tags.tag', 'LIKE', "%lipid%");
                    })
                    ->first();
                if ($query5) {
                    $score++;
                } else {
                    $data['fix'][] = 'LDL needs to be measured';
                }
            }
        }
        // Nephropathy screening
        $query6 = DB::table('tests')->where('pid', '=', $pid)
            ->where('test_name', 'LIKE', "%microalbumin%")
            ->orderBy('test_datetime', 'desc')
            ->first();
        if ($query6) {
            $score++;
        } else {
            $query7 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where('documents_desc', 'LIKE', "%microalbumin%")
                ->where('documents_type', '=', 'Laboratory')
                ->first();
            if ($query7) {
                $score++;
            } else {
                $query8 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where('tags.tag', 'LIKE', "%microalbumin%")
                    ->first();
                if ($query8) {
                    $score++;
                } else {
                    $data['fix'][] = 'Urine microalbumin needs to be measured';
                }
            }
        }
        // Eye exam
        $query9 = DB::table('documents')
            ->where('pid', '=', $pid)
            ->where(function($query_array9) {
                $query_array9->where('documents_desc', 'LIKE', "%ophthal%")
                    ->orWhere('documents_desc', 'LIKE', "%dilated eye%")
                    ->orWhere('documents_desc', 'LIKE', "%diabetic eye%");
            })
            ->where('documents_type', '=', 'Referrals')
            ->first();
        if ($query9) {
            $score++;
        } else {
            $query10 = DB::table('tags_relate')
                ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                ->where('tags_relate.pid', '=', $pid)
                ->where(function($query_array10) {
                    $query_array10->where('tags.tag', 'LIKE', "%ophthal%")
                        ->orWhere('tags.tag', 'LIKE', "%dilated eye%")
                        ->orWhere('tags.tag', 'LIKE', "%diabetic eye%");
                })
                ->first();
            if ($query10) {
                $score++;
            } else {
                $data['fix'][] = 'Diabetic eye exam needs to be performed';
            }
        }
        // BP
        $systolic = 0;
        $diastolic = 0;
        $query11 = DB::table('vitals')->where('pid', '=', $pid)->where('bp_systolic', '!=', '')->where('bp_diastolic', '!=', '')->orderBy('eid', 'desc')->first();
        if ($query11) {
            if ($query11->bp_systolic < 140) {
                $systolic++;
            }
            if ($query11->bp_diastolic < 90) {
                $diastolic++;
            }
            $bp_score = $systolic + $diastolic;
            if ($bp_score == 2) {
                $score++;
            } else {
                $data['fix'][] = 'Blood pressure needs to be under better control.';
            }
        } else {
            $data['fix'][] = 'Blood pressures need to be measured.';
        }
        if ($score == 5) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Comprehensive Diabetes Care performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_chl($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Chlamydia Screening not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('tests')->where('pid', '=', $pid)->where('test_name', 'LIKE', "%chlamydia%")->orderBy('test_datetime', 'desc')->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where('documents_desc', 'LIKE', "%chlamydia%")
                ->where('documents_type', '=', 'Laboratory')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where('tags.tag', 'LIKE', "%chlamydia%")
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $data['fix'][] = 'Chlamydia test needs to be performed';
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Chlamydia Screening performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_cis($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Childhood Immunization Status not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('vitals')->where('pid', '=', $pid)->where('BMI', '!=', '')->orderBy('eid', 'desc')->first();
        if ($query) {
            $score++;
        }
        // DTaP
        $query_1 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_1) {
                $imm_array_1 = array('20', '106', '107', '146', '110', '50', '120', '130', '132', '1', '22', '102');
                $count_1 = 0;
                foreach ($imm_array_1 as $imm_1) {
                    if ($count_1 == 0) {
                        $query_array_1->where('imm_cvxcode', '=', $imm_1);
                    } else {
                        $query_array_1->orWhere('imm_cvxcode', '=', $imm_1);
                    }
                    $count_1++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_1) {
            $score++;
        } else {
            $data['fix'][] = 'Needs DTaP immunization';
        }
        // IPV
        $query_2 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_2) {
                $imm_array_2 = array('146', '110', '120', '130', '132', '10');
                $count_2 = 0;
                foreach ($imm_array_2 as $imm_2) {
                    if ($count_2 == 0) {
                        $query_array_2->where('imm_cvxcode', '=', $imm_2);
                    } else {
                        $query_array_2->orWhere('imm_cvxcode', '=', $imm_2);
                    }
                    $count_2++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_2) {
            $score++;
        } else {
            $data['fix'][] = 'Needs IPV immunization';
        }
        // MMR
        $query_3 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_3) {
                $imm_array_3 = array('3', '94', '5', '6', '7', '38');
                $count_3 = 0;
                foreach ($imm_array_3 as $imm_3) {
                    if ($count_3 == 0) {
                        $query_array_3->where('imm_cvxcode', '=', $imm_3);
                    } else {
                        $query_array_3->orWhere('imm_cvxcode', '=', $imm_3);
                    }
                    $count_3++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_3) {
            $score++;
        } else {
            $data['fix'][] = 'Needs MMR immunization';
        }
        // Hib
        $query_4 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_4) {
                $imm_array_4 = array('146','50','120','132', '22', '102', '46', '47', '48', '49', '17', '51', '148');
                $count_4 = 0;
                foreach ($imm_array_4 as $imm_4) {
                    if ($count_4 == 0) {
                        $query_array_4->where('imm_cvxcode', '=', $imm_4);
                    } else {
                        $query_array_4->orWhere('imm_cvxcode', '=', $imm_4);
                    }
                    $count_4++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_4) {
            $score++;
        } else {
            $data['fix'][] = 'Needs Hib immunization';
        }
        // HepB
        $query_5 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_5) {
                $imm_array_5 = array('146','110','132','102','104','8','42','43','44','45','51');
                $count_5 = 0;
                foreach ($imm_array_5 as $imm_5) {
                    if ($count_5 == 0) {
                        $query_array_5->where('imm_cvxcode', '=', $imm_5);
                    } else {
                        $query_array_5->orWhere('imm_cvxcode', '=', $imm_5);
                    }
                    $count_5++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_5) {
            $score++;
        } else {
            $data['fix'][] = 'Needs Hepatitis B immunization';
        }
        // Varicella
        $query_6 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_6) {
                $imm_array_6 = array('21');
                $count_6 = 0;
                foreach ($imm_array_6 as $imm_6) {
                    if ($count_6 == 0) {
                        $query_array_6->where('imm_cvxcode', '=', $imm_6);
                    } else {
                        $query_array_6->orWhere('imm_cvxcode', '=', $imm_6);
                    }
                    $count_6++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_6) {
            $score++;
        } else {
            $data['fix'][] = 'Needs Varicella immunization';
        }
        // Pneumococcal
        $query_7 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_7) {
                $imm_array_7 = array('133','100','109');
                $count_7 = 0;
                foreach ($imm_array_7 as $imm_7) {
                    if ($count_7 == 0) {
                        $query_array_7->where('imm_cvxcode', '=', $imm_7);
                    } else {
                        $query_array_7->orWhere('imm_cvxcode', '=', $imm_7);
                    }
                    $count_7++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_7) {
            $score++;
        } else {
            $data['fix'][] = 'Needs Pneumoccocal immunization';
        }
        // HepA
        $query_8 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_8) {
                $imm_array_8 = array('52','83','84','31','85','104');
                $count_8 = 0;
                foreach ($imm_array_8 as $imm_8) {
                    if ($count_8 == 0) {
                        $query_array_8->where('imm_cvxcode', '=', $imm_8);
                    } else {
                        $query_array_8->orWhere('imm_cvxcode', '=', $imm_8);
                    }
                    $count_8++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_8) {
            $score++;
        } else {
            $data['fix'][] = 'Needs Hepatitis A immunization';
        }
        // Rotavirus
        $query_9 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_9) {
                $imm_array_9 = array('119','116','74','122');
                $count_9 = 0;
                foreach ($imm_array_9 as $imm_9) {
                    if ($count_9 == 0) {
                        $query_array_9->where('imm_cvxcode', '=', $imm_9);
                    } else {
                        $query_array_9->orWhere('imm_cvxcode', '=', $imm_9);
                    }
                    $count_9++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_9) {
            $score++;
        } else {
            $data['fix'][] = 'Needs Rotavirus immunization';
        }
        // Influenza
        $query_10 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_10) {
                $imm_array_10 = array('123','135','111','149','141','140','144','15','88','16','127','128','125','126');
                $count_10 = 0;
                foreach ($imm_array_10 as $imm_10) {
                    if ($count_10 == 0) {
                        $query_array_10->where('imm_cvxcode', '=', $imm_10);
                    } else {
                        $query_array_10->orWhere('imm_cvxcode', '=', $imm_10);
                    }
                    $count_10++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_10) {
            $score++;
        } else {
            $data['fix'][] = 'Needs influenza immunization';
        }
        if ($score >= 11) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Childhood Immunization Status performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_cmc($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Cholesterol Management for Patients With Cardiovascular Conditions not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('tests')->where('pid', '=', $pid)
            ->where(function($query_array) {
                    $query_array->where('test_name', 'LIKE', "%ldl%")
                        ->orWhere('test_name', 'LIKE', "%cholesterol%")
                        ->orWhere('test_name', 'LIKE', "%lipid%");
                })
            ->orderBy('test_datetime', 'desc')
            ->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where(function($query_array1) {
                    $query_array1->where('documents_desc', 'LIKE', "%ldl%")
                        ->orWhere('documents_desc', 'LIKE', "%cholesterol%")
                        ->orWhere('documents_desc', 'LIKE', "%lipid%");
                })
                ->where('documents_type', '=', 'Laboratory')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where(function($query_array2) {
                        $query_array2->where('tags.tag', 'LIKE', "%ldl%")
                            ->orWhere('tags.tag', 'LIKE', "%cholesterol%")
                            ->orWhere('tags.tag', 'LIKE', "%lipid%");
                    })
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $data['fix'][] = 'LDL needs to be measured';
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Cholesterol Management for Patients With Cardiovascular Conditions performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_col($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Colorectal Cancer Screening not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('tests')->where('pid', '=', $pid)
            ->where(function($query_array) {
                $query_array->where('test_name', 'LIKE', "%colonoscopy%")
                    ->orWhere('test_name', 'LIKE', "%sigmoidoscopy%");
            })
            ->orderBy('test_datetime', 'desc')
            ->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where(function($query_array1) {
                    $query_array1->where('documents_desc', 'LIKE', "%colonoscopy%")
                        ->orWhere('documents_desc', 'LIKE', "%sigmoidoscopy%");
                })
                ->where('documents_type', '=', 'Endoscopy')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where(function($query_array2) {
                        $query_array2->where('tags.tag', 'LIKE', "%colonoscopy%")
                            ->orWhere('tags.tag', 'LIKE', "%sigmoidoscopy%");
                    })
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $query3 = DB::table('documents')
                        ->where('pid', '=', $pid)
                        ->where(function($query_array3) {
                            $query_array3->where('documents_desc', 'LIKE', "%guaiac%")
                                ->orWhere('documents_desc', 'LIKE', "%fobt%");
                        })
                        ->where('documents_type', '=', 'Laboratory')
                        ->first();
                    if ($query3) {
                        $score++;
                    } else {
                        $query4 = DB::table('billing_core')
                            ->where('pid', '=', $pid)
                            ->where(function($query_array4) {
                                $fobt_item_array = array('82270','82274');
                                $fobt_count = 0;
                                foreach ($fobt_item_array as $fobt_item) {
                                    if ($fobt_count == 0) {
                                        $query_array4->where('cpt', '=', $fobt_item);
                                    } else {
                                        $query_array4->orWhere('cpt', '=', $fobt_item);
                                    }
                                    $fobt_count++;
                                }
                            })
                            ->orderBy('eid', 'desc')
                            ->first();
                        if ($query4) {
                            $score++;
                        } else {
                            $data['fix'][] = 'Colon cancer screening needs to be performed';
                        }
                    }
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Colorectal Cancer Screening performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_cwp($cwp_result)
    {
        $data = [];
        $data['count'] = count($cwp_result);
        $data['test'] = 0;
        $data['abx'] = 0;
        $data['abx_no_test'] = 0;
        $data['percent_test'] = 0;
        $data['percent_abx'] = 0;
        $data['percent_abx_no_test'] = 0;
        foreach ($cwp_result as $row) {
            $test = 0;
            $query1 = DB::table('billing_core')
                ->where('eid', '=', $row->eid)
                ->where(function($query_array2) {
                    $item2_array = ['87880','87070','87071','87081','87430','87650','87651','87652'];
                    $j = 0;
                    foreach ($item2_array as $item2) {
                        if ($j == 0) {
                            $query_array2->where('cpt', '=', $item2);
                        } else {
                            $query_array2->orWhere('cpt', '=', $item2);
                        }
                        $j++;
                    }
                })
                ->first();
            if ($query1) {
                $data['test']++;
                $test++;
            }
            $query2 = DB::table('rx')->where('eid', '=', $row->eid)->first();
            if ($query2) {
                if ($query2->rx_rx != '') {
                    $abx_count = 0;
                    $search = ['cillin','amox','zith','cef','kef','mycin','eryth','pen','bac','sulf'];
                    foreach ($search as $needle) {
                        $pos = stripos($query2->rx_rx, $needle);
                        if ($pos !== false) {
                            $abx_count++;
                        }
                    }
                    if ($abx_count > 0) {
                        $data['abx']++;
                        if ($test == 0) {
                            $data['abx_no_test']++;
                        }
                    }
                }
            }
        }
        if ($data['count'] !== 0) {
            $data['percent_test'] = round($data['test']/$data['count']*100);
            $data['percent_abx'] = round($data['abx']/$data['count']*100);
            $data['percent_abx_no_test'] = round($data['abx_no_test']/$data['count']*100);
        }
        return $data;
    }

    protected function hedis_gso($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Glaucoma Screening Older Adults not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('tests')->where('pid', '=', $pid)->where('test_name', 'LIKE', "%glaucoma%")->orderBy('test_datetime', 'desc')->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where('documents_desc', 'LIKE', "%glaucoma%")
                ->where('documents_type', '=', 'Referrals')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where('tags.tag', 'LIKE', "%glaucoma%")
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $data['fix'][] = 'Glaucoma screening needs to be performed';
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Glaucoma Screening Older Adults performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_ima($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Immunizations for Adolescents not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('vitals')->where('pid', '=', $pid)->where('BMI', '!=', '')->orderBy('eid', 'desc')->first();
        if ($query) {
            $score++;
        }
        // Meningococcal
        $query_1 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_1) {
                $imm_array_1 = array('103', '148', '147', '136', '114', '32', '108');
                $count_1 = 0;
                foreach ($imm_array_1 as $imm_1) {
                    if ($count_1 == 0) {
                        $query_array_1->where('imm_cvxcode', '=', $imm_1);
                    } else {
                        $query_array_1->orWhere('imm_cvxcode', '=', $imm_1);
                    }
                    $count_1++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_1) {
            $score++;
        } else {
            $data['fix'][] = 'Needs meningococcal immunization';
        }
        // Tdap
        $query_2 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_2) {
                $imm_array_2 = array('138', '113', '9', '139', '115');
                $count_2 = 0;
                foreach ($imm_array_2 as $imm_2) {
                    if ($count_2 == 0) {
                        $query_array_2->where('imm_cvxcode', '=', $imm_2);
                    } else {
                        $query_array_2->orWhere('imm_cvxcode', '=', $imm_2);
                    }
                    $count_2++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query_2) {
            $score++;
        } else {
            $data['fix'][] = 'Needs Tdap immunization';
        }
        if ($score >= 2) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Immunizations for Adolescents performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_issue_query($pid, $issues_item_array)
    {
        $query = DB::table('issues')
            ->where('pid','=', $pid)
            ->where('issue_date_inactive', '=', '0000-00-00 00:00:00')
            ->where(function($query_array) use ($issues_item_array) {
                $count = 0;
                foreach ($issues_item_array as $issues_item) {
                    if ($count == 0) {
                        $query_array->where('issue', 'LIKE', "%$issues_item%");
                    } else {
                        $query_array->orWhere('issue', 'LIKE', "%$issues_item%");
                    }
                    $count++;
                }
            })
            ->first();
        return $query;
    }

    protected function hedis_hpv($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Human Papillomavirus Vaccine for Female Adolescents not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('vitals')->where('pid', '=', $pid)->where('BMI', '!=', '')->orderBy('eid', 'desc')->first();
        if ($query) {
            $score++;
        }
        // HPV
        $query_1 = DB::table('immunizations')
            ->where('pid', '=', $pid)
            ->where(function($query_array_1) {
                $imm_array_1 = array('118', '62', '137');
                $count_1 = 0;
                foreach ($imm_array_1 as $imm_1) {
                    if ($count_1 == 0) {
                        $query_array_1->where('imm_cvxcode', '=', $imm_1);
                    } else {
                        $query_array_1->orWhere('imm_cvxcode', '=', $imm_1);
                    }
                    $count_1++;
                }
            })
            ->orderBy('eid', 'desc')
            ->get();
        if ($query_1) {
            $count = count($query_1);
            $row = DB::table('demographics')->where('pid', '=', $pid)->first();
            $date = Date::parse($row->DOB);
            $dob = $date->diffInYears(Date::now());
            if ($dob >= 13) {
                if ($count == 3) {
                    $score++;
                }
            }
            if ($dob >= 9 && $dob < 13) {
                if ($count > 0) {
                    $score++;
                }
            }
        } else {
            $data['fix'][] = 'Needs HPV immunization';
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Human Papillomavirus Vaccine for Female Adolescents performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_lbp($lbp_result)
    {
        $data = [];
        $data['count'] = count($lbp_result);
        $data['no_rad'] = 0;
        $data['percent_no_rad'] = 0;
        $rad = 0;
        foreach ($lbp_result as $row) {
            $encounter = DB::table('encounters')->where('eid', '=', $row->eid)->first();
            $date_a = date('Y-m-d', $this->human_to_unix($encounter->encounter_DOS));
            $date_b = date('Y-m-d', $this->human_to_unix($encounter->encounter_DOS) + 2419200); //28 days from DOS
            $date_c = date('Y-m-d H:i:s', $this->human_to_unix($encounter->encounter_DOS));
            $date_d = date('Y-m-d H:i:s', $this->human_to_unix($encounter->encounter_DOS) + 2419200);
            $pid = $encounter->pid;
            $query2 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where('documents_desc', 'LIKE', "%ray%")
                ->where('documents_date', '>=', $date_a)
                ->where('documents_date', '<=', $date_b)
                ->where(function($query_array2) {
                    $query_array2->where('documents_desc', 'LIKE', "%lumbar%")
                        ->orWhere('documents_desc', 'LIKE', "%low back%");
                })
                ->where('documents_type', '=', 'Imaging')
                ->first();
            if ($query2) {
                $rad++;
            } else {
                $query3 = DB::table('tests')->where('pid', '=', $pid)
                    ->where('test_name', 'LIKE', "%ray%")
                    ->where('test_datetime', '>=', $date_c)
                    ->where('test_datetime', '<=', $date_d)
                    ->where(function($query_array) {
                            $query_array->where('test_name', 'LIKE', "%lumbar%")
                                ->orWhere('test_name', 'LIKE', "%low back%");
                        })
                    ->first();
                if ($query3) {
                    $rad++;
                }
            }
        }
        if ($data['count'] !== 0) {
            $data['no_rad'] = $data['count'] - $rad;
            $data['percent_no_rad'] = round($data['no_rad']/$data['count']*100);
        }
        return $data;
    }

    protected function hedis_lsc($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Lead Screening in Children not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('tests')->where('pid', '=', $pid)->where('test_name', 'LIKE', "%lead%")->orderBy('test_datetime', 'desc')->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where('documents_desc', 'LIKE', "%lead%")
                ->where('documents_type', '=', 'Laboratory')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where('tags.tag', 'LIKE', "%lead%")
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $data['fix'][] = 'Lead level needs to be measured';
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Lead Screening in Children performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_omw($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Disease Modifying Anti-Rheumatic Drug Therapy for Rheumatoid Arthritis not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query1 = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->first();
        if ($query1) {
            $search = array('actonel','dronate','atelvia','boniva','fosamax','reclast','binosto','zoledronic','estraderm','estradiol','estropipate','femhrt','jinteli','menest','premarin','premphase','vivelle','activella','alora','cenestin','climara','estrace','gynodiol','menostar','mimvey','ogen','prefest','minivelle','evista','forteo','prolia');
            foreach ($search as $needle) {
                $pos = stripos($query1->rxl_medication, $needle);
                if ($pos !== false) {
                    $score++;
                }
            }
        }
        $query2 = DB::table('documents')
            ->where('pid', '=', $pid)
            ->where(function($query_array2) {
                $query_array2->where('documents_desc', 'LIKE', "%dexa%")
                    ->orWhere('documents_desc', 'LIKE', "%osteoporosis%")
                    ->orWhere('documents_desc', 'LIKE', "%bone density%");
            })
            ->where('documents_type', '=', 'Imaging')
            ->first();
        if ($query2) {
            $score++;
        } else {
            $query3 = DB::table('tags_relate')
                ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                ->where('tags_relate.pid', '=', $pid)
                ->where(function($query_array3) {
                    $query_array3->where('tags.tag', 'LIKE', "%dexa%")
                        ->orWhere('tags.tag', 'LIKE', "%osteoporosis%")
                        ->orWhere('tags.tag', 'LIKE', "%bone density%");
                })
                ->first();
            if ($query3) {
                $score++;
            } else {
                $query4 = DB::table('tests')->where('pid', '=', $pid)
                    ->where(function($query_array4) {
                            $query_array4->where('test_name', 'LIKE', "%dexa%")
                                ->orWhere('test_name', 'LIKE', "%osteoporosis%")
                                ->orWhere('test_name', 'LIKE', "%bone density%");
                        })
                    ->first();
                if ($query4) {
                    $score++;
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Disease Modifying Anti-Rheumatic Drug Therapy for Rheumatoid Arthritis performed';
            $data['goal'] = 'y';
        } else {
            $data['fix'][] = 'Bone density screening needs to be performed or osteoporosis prevention medication is recommended';
        }
        return $data;
    }

    protected function hedis_pbh($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Persistence of Beta-Blocker Treatment After a Heart Attack not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query1 = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->first();
        if ($query1) {
            $search = array('olol','ilol','alol','betapace','brevibloc','bystolic','coreg','corgard','inderal','innopran','kerlone','levatol','lopressor','sectral','tenormin','oprol','trandate','zebeta','sorine','corzide','tenoretic','ziac');
            foreach ($search as $needle) {
                $pos = stripos($query1->rxl_medication, $needle);
                if ($pos !== false) {
                    $score++;
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Persistence of Beta-Blocker Treatment After a Heart Attack performed';
            $data['goal'] = 'y';
        } else {
            $data['fix'][] = 'Beta blocker is recommended.';
        }
        return $data;
    }

    protected function hedis_pce($pce_result)
    {
        $data = [];
        $data['count'] = count($pce_result);
        $data['tx'] = 0;
        $data['percent_tx'] = 0;
        foreach ($pce_result as $row) {
            $query1 = DB::table('rx')->where('eid', '=', $row->eid)->first();
            if ($query1) {
                if ($query1->rx_rx != '') {
                    $steroid_count = 0;
                    $inhaler_count = 0;
                    $search = array('sone','medrol','pred','celestone','cortef','decadron','rayos');
                    foreach ($search as $needle) {
                        $pos = stripos($query1->rx_rx, $needle);
                        if ($pos !== false) {
                            $steroid_count++;
                        }
                    }
                    $search1 = array('terol','hfa','xopenex','maxair','combivent','ipratro','duoneb');
                    foreach ($search1 as $needle1) {
                        $pos1 = stripos($query1->rx_rx, $needle1);
                        if ($pos1 !== false) {
                            $inhaler_count++;
                        }
                    }
                    if ($steroid_count > 0 && $inhaler_count > 0) {
                        $data['tx']++;
                    }
                }
            }
        }
        if ($data['count'] !== 0) {
            $data['percent_tx'] = round($data['tx']/$data['count']*100);
        }
        return $data;
    }

    protected function hedis_spr($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Use of Spirometry Testing in the Assessment and Diagnosis of COPD not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('tests')->where('pid', '=', $pid)->where('test_name', 'LIKE', "%spirometry%")->orderBy('test_datetime', 'desc')->first();
        if ($query) {
            $score++;
        } else {
            $query1 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->where('documents_desc', 'LIKE', "%spirometry%")
                ->where('documents_type', '=', 'Cardiopulmonary')
                ->first();
            if ($query1) {
                $score++;
            } else {
                $query2 = DB::table('tags_relate')
                    ->join('tags', 'tags.tags_id', '=', 'tags_relate.tags_id')
                    ->where('tags_relate.pid', '=', $pid)
                    ->where('tags.tag', 'LIKE', "%spirometry%")
                    ->first();
                if ($query2) {
                    $score++;
                } else {
                    $data['fix'][] = 'Glaucoma screening needs to be performed';
                }
            }
        }
        if ($score >= 1) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Use of Spirometry Testing in the Assessment and Diagnosis of COPD performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function hedis_uri($uri_result)
    {
        $data = [];
        $data['count'] = count($uri_result);
        $data['abx'] = 0;
        $data['percent_abx'] = 0;
        foreach ($uri_result as $row) {
            $query1 = DB::table('rx')->where('eid', '=', $row->eid)->first();
            if ($query1) {
                if ($query1->rx_rx != '') {
                    $abx_count = 0;
                    $search = array('cillin','amox','zith','cef','kef','mycin','eryth','pen','bac','sulf');
                    foreach ($search as $needle) {
                        $pos = stripos($query2->rx_rx, $needle);
                        if ($pos !== false) {
                            $abx_count++;
                        }
                    }
                    if ($abx_count > 0) {
                        $data['abx']++;
                    }
                }
            }
        }
        if ($data['count'] !== 0) {
            $data['percent_abx'] = round($data['abx']/$data['count']*100);
        }
        return $data;
    }

    protected function hedis_wcc($pid)
    {
        $data = [];
        $data['html'] = '<i class="fa fa-lg fa-times"></i> Weight Assessment and Counseling for Nutrition and Physical Activity for Children and Adolescents not performed';
        $data['goal'] = 'n';
        $data['fix'] = [];
        $score = 0;
        $query = DB::table('vitals')->where('pid', '=', $pid)->where('BMI', '!=', '')->orderBy('eid', 'desc')->first();
        if ($query) {
            $score++;
        } else {
            $data['fix'][] = 'BMI, height, and weight needs to be measured';
        }
        $query1 = DB::table('billing_core')
            ->where('pid', '=', $pid)
            ->where(function($query_array1) {
                $wcc_item_array = array('97802','97803','97804');
                $i = 0;
                foreach ($wcc_item_array as $wcc_item) {
                    if ($i == 0) {
                        $query_array1->where('cpt', '=', $wcc_item);
                    } else {
                        $query_array1->orWhere('cpt', '=', $wcc_item);
                    }
                    $i++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query1) {
            $score++;
        } else {
            $data['fix'][] = 'Nutritional counseling needs to be performed';
        }
        $query2 = DB::table('assessment')
            ->where('pid', '=', $pid)
            ->where(function($query_array2) {
                $assessment_item_array2 = array('V85.51','V85.52','V85.53','V85.54','Z68.51','Z68.52','Z68.53','Z68.54');
                $count2 = 0;
                foreach ($assessment_item_array2 as $assessment_item2) {
                    if ($count2 == 0) {
                        $query_array2->where('assessment_icd1', '=', $assessment_item2)->orWhere('assessment_icd2', '=', $assessment_item2)->orWhere('assessment_icd3', '=', $assessment_item2)->orWhere('assessment_icd4', '=', $assessment_item2)->orWhere('assessment_icd5', '=', $assessment_item2)->orWhere('assessment_icd6', '=', $assessment_item2)->orWhere('assessment_icd7', '=', $assessment_item2)->orWhere('assessment_icd8', '=', $assessment_item2)->orWhere('assessment_icd9', '=', $assessment_item2)->orWhere('assessment_icd10', '=', $assessment_item2)->orWhere('assessment_icd11', '=', $assessment_item2)->orWhere('assessment_icd12', '=', $assessment_item2);
                    } else {
                        $query_array2->orWhere('assessment_icd1', '=', $assessment_item2)->orWhere('assessment_icd2', '=', $assessment_item2)->orWhere('assessment_icd3', '=', $assessment_item2)->orWhere('assessment_icd4', '=', $assessment_item2)->orWhere('assessment_icd5', '=', $assessment_item2)->orWhere('assessment_icd6', '=', $assessment_item2)->orWhere('assessment_icd7', '=', $assessment_item2)->orWhere('assessment_icd8', '=', $assessment_item2)->orWhere('assessment_icd9', '=', $assessment_item2)->orWhere('assessment_icd10', '=', $assessment_item2)->orWhere('assessment_icd11', '=', $assessment_item2)->orWhere('assessment_icd12', '=', $assessment_item2);
                    }
                    $count2++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query2) {
            $score++;
        } else {
            $data['fix'][] = 'BMI, height, and weight needs to be measured';
        }
        $query3 = DB::table('assessment')
            ->where('pid', '=', $pid)
            ->where(function($query_array3) {
                $assessment_item_array3 = array('V65.3','Z71.3');
                $count3 = 0;
                foreach ($assessment_item_array3 as $assessment_item3) {
                    if ($count3 == 0) {
                        $query_array3->where('assessment_icd1', '=', $assessment_item3)->orWhere('assessment_icd2', '=', $assessment_item3)->orWhere('assessment_icd3', '=', $assessment_item3)->orWhere('assessment_icd4', '=', $assessment_item3)->orWhere('assessment_icd5', '=', $assessment_item3)->orWhere('assessment_icd6', '=', $assessment_item3)->orWhere('assessment_icd7', '=', $assessment_item3)->orWhere('assessment_icd8', '=', $assessment_item3)->orWhere('assessment_icd9', '=', $assessment_item3)->orWhere('assessment_icd10', '=', $assessment_item3)->orWhere('assessment_icd11', '=', $assessment_item3)->orWhere('assessment_icd12', '=', $assessment_item3);
                    } else {
                        $query_array3->orWhere('assessment_icd1', '=', $assessment_item3)->orWhere('assessment_icd2', '=', $assessment_item3)->orWhere('assessment_icd3', '=', $assessment_item3)->orWhere('assessment_icd4', '=', $assessment_item3)->orWhere('assessment_icd5', '=', $assessment_item3)->orWhere('assessment_icd6', '=', $assessment_item3)->orWhere('assessment_icd7', '=', $assessment_item3)->orWhere('assessment_icd8', '=', $assessment_item3)->orWhere('assessment_icd9', '=', $assessment_item3)->orWhere('assessment_icd10', '=', $assessment_item3)->orWhere('assessment_icd11', '=', $assessment_item3)->orWhere('assessment_icd12', '=', $assessment_item3);
                    }
                    $count3++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query3) {
            $score++;
        } else {
            $data['fix'][] = 'Nutritional counseling needs to be performed';
        }
        $query4 = DB::table('assessment')
            ->where('pid', '=', $pid)
            ->where(function($query_array4) {
                $assessment_item_array4 = array('V65.41','Z71.89');
                $count4 = 0;
                foreach ($assessment_item_array4 as $assessment_item4) {
                    if ($count4 == 0) {
                        $query_array4->where('assessment_icd1', '=', $assessment_item4)->orWhere('assessment_icd2', '=', $assessment_item4)->orWhere('assessment_icd3', '=', $assessment_item4)->orWhere('assessment_icd4', '=', $assessment_item4)->orWhere('assessment_icd5', '=', $assessment_item4)->orWhere('assessment_icd6', '=', $assessment_item4)->orWhere('assessment_icd7', '=', $assessment_item4)->orWhere('assessment_icd8', '=', $assessment_item4)->orWhere('assessment_icd9', '=', $assessment_item4)->orWhere('assessment_icd10', '=', $assessment_item4)->orWhere('assessment_icd11', '=', $assessment_item4)->orWhere('assessment_icd12', '=', $assessment_item4);
                    } else {
                        $query_array4->orWhere('assessment_icd1', '=', $assessment_item4)->orWhere('assessment_icd2', '=', $assessment_item4)->orWhere('assessment_icd3', '=', $assessment_item4)->orWhere('assessment_icd4', '=', $assessment_item4)->orWhere('assessment_icd5', '=', $assessment_item4)->orWhere('assessment_icd6', '=', $assessment_item4)->orWhere('assessment_icd7', '=', $assessment_item4)->orWhere('assessment_icd8', '=', $assessment_item4)->orWhere('assessment_icd9', '=', $assessment_item4)->orWhere('assessment_icd10', '=', $assessment_item4)->orWhere('assessment_icd11', '=', $assessment_item4)->orWhere('assessment_icd12', '=', $assessment_item4);
                    }
                    $count4++;
                }
            })
            ->orderBy('eid', 'desc')
            ->first();
        if ($query4) {
            $score++;
        } else {
            $data['fix'][] = 'Physical activity counseling needs to be performed.';
        }
        if ($score >= 2) {
            $data['html'] = '<i class="fa fa-lg fa-check"></i> Weight Assessment and Counseling for Nutrition and Physical Activity for Children and Adolescents performed';
            $data['goal'] = 'y';
        }
        return $data;
    }

    protected function healthwise_compile()
    {
        $core_url = 'https://myhealth.alberta.ca';
        $url = $core_url . '/health/aftercareinformation/Pages/default.aspx';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $html = new Htmldom($data);
        $data1 = [];
        if (isset($html)) {
            $main = $html->find('div.HWAccordion', 0);
            foreach ($main->find('li') as $item) {
                $link = $item->find('a', 0);
                $data1[] = [
                    'url' => $link->href,
                    'desc' => $link->innertext
                ];
            }
        }
        $formatter1 = Formatter::make($data1, Formatter::ARR);
        $text = $formatter1->toYaml();
        $file_path = resource_path() . '/healthwise.yaml';
        File::put($file_path, $text);
        return 'OK';
    }

    protected function healthwise_view($link)
    {
        $return = 'Having trouble getting materials.  Try again';
        $core_url = 'https://myhealth.alberta.ca';
        $url = $core_url . $link;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $html = new Htmldom($data);
        $data1 = [];
        if (isset($html)) {
            $main = $html->find('div.HwContent', 0);
            $return = $main->outertext;
            $imgs = $main->find('img');
            $url_arr = explode('/', $url);
            array_pop($url_arr);
            $img_url = implode('/', $url_arr);
            $i = 0;
            foreach ($imgs as $img) {
                $img_link = $img->src;
                $img_url1 = $img_url . '/' . $img_link;
                $ch1 = curl_init();
                curl_setopt($ch1, CURLOPT_URL, $img_url1);
                curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch1, CURLOPT_BINARYTRANSFER, true);
                $raw = curl_exec($ch1);
                curl_close($ch1);
                $file_path_name = time() . '_img_' . $i . '.jpg';
                $file_path = public_path() .'/temp/' . $file_path_name;
                $new_url = asset('temp/' . $file_path_name);
                File::put($file_path, $raw);
                $return = str_replace($img_link, $new_url, $return);
                $i++;
            }
            $as = $main->find('a.HwSectionNameTag');
            foreach ($as as $a) {
                $a1 = $a->outertext;
                $return = str_replace($a1, '', $return);
            }
        }
        return $return;
    }

    /**
    * Human text to unix timestamp function
    * @param string  $datestr - Human readable date string
    * @return Response
    */
    protected function human_to_unix($datestr)
    {
        $datestr_arr = explode(' (', $datestr);
        $date = Date::parse($datestr_arr[0]);
        return $date->timestamp;
    }

    protected function icd_search($code)
    {
        $file = File::get(resource_path() . '/icd10cm_order_2017.txt');
        $arr = preg_split("/\\r\\n|\\r|\\n/", $file);
        foreach ($arr as $row) {
            $icd10 = rtrim(substr($row,6,7));
            if (strlen($icd10) !== 3) {
                $icd10 = substr_replace($icd10, '.', 3, 0);
            }
            $preicd[$icd10] = [
                'icd10' => $icd10,
                'desc' => substr($row,77),
                'type' => substr($row,14,1)
            ];
        }
        $result = [];
        $return = '';
        $result = array_where($preicd, function($value, $key) use ($code) {
            if (stripos($value['icd10'] , $code) !== false) {
                return true;
            }
        });
        if ($result) {
            $row = head($result);
            $return = $row['desc'];
        }
        return $return;
    }

    protected function icd10data($icd10q)
    {
        $url = 'http://www.icd10data.com/Search.aspx?search=' . $icd10q . '&codeBook=ICD10CM';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
        $result = curl_exec($ch);
        $html = new Htmldom($result);
        $data = [];
        if (isset($html)) {
            // Get pages_data
            $pagination = $html->find('ul.pagination', 0);
            if ($pagination) {
                $i = 1;
                foreach ($pagination->find('li') as $page_icd) {
                    // Limit searh to 3 pages or less
                    if ($i < 3) {
                        $data = $this->icd10data_get($i, $data, $icd10q);
                    }
                    $i++;
                }
            } else {
                $data = $this->icd10data_get('1', $data, $icd10q);
            }
        }
        return $data;
    }

    protected function icd10data_get($page, $data, $icd10q)
    {
        $url = 'http://www.icd10data.com/Search.aspx?search=' . $icd10q . '&codeBook=ICD10CM' . '&page=' . $page;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
        $result = curl_exec($ch);
        $html = new Htmldom($result);
        if (isset($html)) {
            foreach ($html->find('div.SearchResultItem') as $link) {
                $status1 = $link->find('img.img2', 0);
                if (isset($status1->src)) {
                    $status = $status1->src;
                    if ($status == '/images/bullet_triangle_green.png') {
                        $code1 = $link->find('span.identifier', 0);
                        $code = $code1->innertext;
                        $desc1 = $link->find('div.SearchResultDescription', 0);
                        $desc = $desc1->plaintext;
                        $common_records = $desc . ' [' . $code . ']';
                        $data[] = [
                            'code' => $code,
                            'desc' => $common_records
                        ];
                    }
                }
            }
        }
        return $data;
    }

    protected function ndc_convert($ndc)
    {
        $pos1 = strpos($ndc, '-');
        $parts = explode("-", $ndc);
        if ($pos1 === 4) {
            $parts[0] = '0' . $parts[0];
        } else {
            $pos2 = strrpos($ndc, '-');
            if ($pos2 === 10) {
                $parts[2] = '0' . $parts[2];
            } else {
                $parts[1] = '0' . $parts[1];
            }
        }
        return $parts[0] . $parts[1] . $parts[2];
    }

    /**
    * NPI lookup
    * @param string  $npi - NPI number
    * @return Response array
    */
    protected function npi_lookup($npi)
    {
        $url = 'https://npiregistry.cms.hhs.gov/api/?number=' . $npi . '&enumeration_type=&taxonomy_description=&first_name=&last_name=&organization_name=&address_purpose=&city=&state=&postal_code=&country_code=&limit=&skip=';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 15);
        $json = curl_exec($ch);
        curl_close($ch);
        $arr = json_decode($json, true);
        $return = [];
        if (isset($arr['results'][0])) {
            if (isset($arr['results'][0]['basic']['name'])) {
                $return['type'] = 'Practice';
                $return['practice_name'] = $arr['results'][0]['basic']['name'];
            } else {
                $return['type'] = 'Individual';
                $return['firstname'] = $arr['results'][0]['basic']['first_name'];
                $return['lastname'] = $arr['results'][0]['basic']['last_name'];
                $return['middle'] = $arr['results'][0]['basic']['middle_name'];
                $return['title'] = $arr['results'][0]['basic']['credential'];
                $return['taxonomy'] = $arr['results'][0]['taxonomies'][0]['code'];
            }
            $return['address'] = $arr['results'][0]['addresses'][0]['address_1'];
            $return['city'] = $arr['results'][0]['addresses'][0]['city'];
            $return['state'] = $arr['results'][0]['addresses'][0]['state'];
            $return['zip'] = $arr['results'][0]['addresses'][0]['postal_code'];
            $return['phone'] = $arr['results'][0]['addresses'][0]['telephone_number'];
        }
        return $return;
    }

    protected function oidc_relay($param, $status=false)
    {
        $pnosh_url = url('/');
        $pnosh_url = str_replace(array('http://','https://'), '', $pnosh_url);
        $root_url = explode('/', $pnosh_url);
        $root_url1 = explode('.', $root_url[0]);
        $final_root_url = $root_url1[1] . '.' . $root_url1[2];
        if ($pnosh_url == 'shihjay.xyz/nosh') {
            $final_root_url = 'hieofone.org';
        }
        if ($final_root_url == 'hieofone.org') {
            $relay_url = 'https://dir.' . $final_root_url . '/oidc_relay';
            if ($status == false) {
                $state = md5(uniqid(rand(), TRUE));
                $relay_url = 'https://dir.' . $final_root_url . '/oidc_relay';
            } else {
                $state = '';
                $relay_url = 'https://dir.' . $final_root_url . '/oidc_relay/' . $param['state'];
            }
            $root_param = [
                'root_uri' => $root_url[0],
                'state' => $state,
                'origin_uri' => '',
                'response_uri' => '',
                'fhir_url' => '',
                'fhir_auth_url' => '',
                'fhir_token_url' => '',
                'type' => '',
                'cms_pid' => '',
                'refresh_token' => ''
            ];
            $params = array_merge($root_param, $param);
            $post_body = json_encode($params);
            $content_type = 'application/json';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $relay_url);
            if ($status == false) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: {$content_type}",
                    'Content-Length: ' . strlen($post_body)
                ]);
            }
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch,CURLOPT_FAILONERROR,1);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_TIMEOUT, 60);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close ($ch);
            if ($httpCode !== 404 && $httpCode !== 0) {
                if ($status == false) {
                    $return['message'] = $response;
                    $return['url'] = $relay_url . '_start/' . $state;
                    $return['state'] = $state;
                } else {
                    $response1 = json_decode($response, true);
                    if (isset($response1['error'])) {
                        $return['message'] = $response1['error'];
                    } else {
                        $return['message'] = 'Tokens received';
                        $return['tokens'] = $response1;
                    }
                }
            } else {
                $return['message'] = 'Error: unable to connect to the relay.';
            }
        } else {
            $return['message'] = 'Not supported.';
        }
        return $return;
    }

    protected function orders_info($text)
    {
        preg_match("/\[(.*?)\]/", $text, $match);
        $codes = explode(',', $match[1]);
        $data = [
            'facility' => $codes[0],
            'order_code' => $codes[1],
            'cpt' => explode(';', str_replace(['(',')'], '', $codes[2])),
            'loinc' => explode(';', str_replace(['(',')'], '', $codes[3])),
            'result_code' => explode(';', str_replace(['(',')'], '', $codes[2]))
        ];
        return $data;
    }

    protected function pagecount($filename)
    {
        $pdftext = file_get_contents($filename);
          $pagecount = preg_match_all("/\/Page\W/", $pdftext, $dummy);
        return $pagecount;
    }

    protected function page_ccr($pid)
    {
        $data['patientInfo'] = DB::table('demographics')->where('pid', '=', $pid)->first();
        $data['dob'] = date('m/d/Y', $this->human_to_unix($data['patientInfo']->DOB));
        $data['insuranceInfo'] = '';
        $query_in = DB::table('insurance')->where('pid', '=', $pid)->where('insurance_plan_active', '=', 'Yes')->get();
        if ($query_in) {
            foreach ($query_in as $row_in) {
                $data['insuranceInfo'] .= $row_in->insurance_plan_name . '; ID: ' . $row_in->insurance_id_num . '; Group: ' . $row_in->insurance_group . '; ' . $row_in->insurance_insu_lastname . ', ' . $row_in->insurance_insu_firstname . '<br><br>';
            }
        }
        $body = 'Active Issues:<br />';
        $query = DB::table('issues')->where('pid', '=', $pid)->where('issue_date_inactive', '=', '0000-00-00 00:00:00')->get();
        if ($query) {
            $body .= '<ul>';
            foreach ($query as $row) {
                $body .= '<li>' . $row->issue . '</li>';
            }
            $body .= '</ul>';
        } else {
            $body .= 'None.';
        }
        $body .= '<hr />Active Medications:<br />';
        $query1 = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->get();
        if ($query1) {
            $body .= '<ul>';
            foreach ($query1 as $row1) {
                if ($row1->rxl_sig == '') {
                    $body .= '<li>' . $row1->rxl_medication . ' ' . $row1->rxl_dosage . ' ' . $row1->rxl_dosage_unit . ', ' . $row1->rxl_instructions . ' for ' . $row1->rxl_reason . '</li>';
                } else {
                    $body .= '<li>' . $row1->rxl_medication . ' ' . $row1->rxl_dosage . ' ' . $row1->rxl_dosage_unit . ', ' . $row1->rxl_sig . ', ' . $row1->rxl_route . ', ' . $row1->rxl_frequency . ' for ' . $row1->rxl_reason . '</li>';
                }
            }
            $body .= '</ul>';
        } else {
            $body .= 'None.';
        }
        $body .= '<hr />Immunizations:<br />';
        $query2 = DB::table('immunizations')->where('pid', '=', $pid)->orderBy('imm_immunization', 'asc')->orderBy('imm_sequence', 'asc')->get();
        if ($query2) {
            $body .= '<ul>';
            foreach ($query2 as $row2) {
                $sequence = '';
                if ($row2->imm_sequence == '1') {
                    $sequence = ', first,';
                }
                if ($row2->imm_sequence == '2') {
                    $sequence = ', second,';
                }
                if ($row2->imm_sequence == '3') {
                    $sequence = ', third,';
                }
                if ($row2->imm_sequence == '4') {
                    $sequence = ', fourth,';
                }
                if ($row2->imm_sequence == '5') {
                    $sequence = ', fifth,';
                }
                $body .= '<li>' . $row2->imm_immunization . $sequence . ' given on ' . date('F jS, Y', $this->human_to_unix($row2->imm_date)) . '</li>';
            }
            $body .= '</ul>';
        } else {
            $body .= 'None.';
        }
        $body .= '<hr />Allergies:<br />';
        $query3 = DB::table('allergies')->where('pid', '=', $pid)->where('allergies_date_inactive', '=', '0000-00-00 00:00:00')->get();
        if ($query3) {
            $body .= '<ul>';
            foreach ($query3 as $row3) {
                $body .= '<li>' . $row3->allergies_med . ' - ' . $row3->allergies_reaction . '</li>';
            }
            $body .= '</ul>';
        } else {
            $body .= 'No known allergies.';
        }
        $body .= '<br />Printed by ' . Session::get('displayname') . '.';
        $data['letter'] = $body;
        return view('pdf.ccr_page',$data);
    }

    protected function page_coverpage($job_id, $totalpages, $faxrecipients, $date)
    {
        $row = DB::table('sendfax')->where('job_id', '=', $job_id)->first();
        $data = [
            'user' => Session::get('displayname'),
            'faxrecipients' => $faxrecipients,
            'faxsubject' => $row->faxsubject,
            'faxmessage' => $row->faxmessage,
            'faxpages' => $totalpages,
            'faxdate' => $date
        ];
        return view('pdf.coverpage',$data);
    }

    protected function page_default()
    {
        $pid = Session::get('pid');
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $data['practiceName'] = $practice->practice_name;
        $data['website'] = $practice->website;
        $data['practiceInfo1'] = $practice->street_address1;
        if ($practice->street_address2 != '') {
            $data['practiceInfo1'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo2'] = $practice->city . ', ' . $practice->state . ' ' . $practice->zip;
        $data['practiceInfo3'] = 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax;
        $patient = DB::table('demographics')->where('pid', '=', $pid)->first();
        $data['patientInfo1'] = $patient->firstname . ' ' . $patient->lastname;
        $data['patientInfo2'] = $patient->address;
        $data['patientInfo3'] = $patient->city . ', ' . $patient->state . ' ' . $patient->zip;
        $data['firstname'] = $patient->firstname;
        $data['lastname'] = $patient->lastname;
        $data['date'] = date('F jS, Y');
        $data['signature'] = $this->signature(Session::get('user_id'));
        return $data;
    }

    protected function page_financial_results($results)
    {
        $body = '<br><br><table style="width:100%"><tr><th>Date</th><th>Last Name</th><th>First Name</th><th>Amount</th><th>Type</th></tr>';
        setlocale(LC_MONETARY, 'en_US.UTF-8');
        foreach ($results as $results_row1) {
            $body .= "<tr><td>" . $results_row1['dos_f'] . "</td><td>" . $results_row1['lastname'] . "</td><td>" . $results_row1['firstname'] . "</td><td>" . money_format('%n', $results_row1['amount']) . "</td><td>" . $results_row1['type'] . "</td></tr>";
        }
        $body .= '</table></body></html>';
        return $body;
    }

    protected function page_hippa_request($id, $pid)
    {
        $result = DB::table('hippa_request')->where('hippa_request_id', '=', $id)->first();
        $row = DB::table('demographics')->where('pid', '=', $pid)->first();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $data['practiceName'] = $practice->practice_name;
        $data['website'] = $practice->website;
        $data['practiceLogo'] = $this->practice_logo(Session::get('practice_id'));
        $data['practiceInfo'] = $practice->street_address1;
        if ($practice->street_address2 !== '') {
            $data['practiceInfo'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo'] .= '<br />';
        $data['practiceInfo'] .= $practice->city . ', ' . $practice->state . ' ' . $practice->zip . '<br />';
        $data['practiceInfo'] .= 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax . '<br />';
        $data['patientInfo1'] = $row->firstname . ' ' . $row->lastname;
        $data['patientInfo2'] = $row->address;
        $data['patientInfo3'] = $row->city . ', ' . $row->state . ' ' . $row->zip;
        $data['patientInfo'] = $row;
        $dob = $this->human_to_unix($row->DOB);
        $data['dob'] = date('m/d/Y', $dob);
        $data['signature_title'] = 'PATIENT SIGNATURE';
        $data['signature_text'] = '';
        if ($dob >= $this->age_calc(18, 'year')) {
            $data['signature_title'] = 'SIGNATURE OF PATIENT REPRESENTATIVE';
            $data['signature_text'] = '<br>Relationship of Representative:<br><br><br>';
        }
        $data['ss'] = '';
        if ($row->ss != '') {
            $data['ss'] = 'Social Security Number: ' . $row->ss . '<br>';
        }
        $data['phone'] = '';
        if ($row->phone_home != '') {
            $data['phone'] = 'Phone Number: ' . $row->phone_home;
        } elseif ($row->phone_cell != '') {
            $data['phone'] = 'Phone Number: ' . $row->phone_cell;
        }
        $data['title'] = "AUTHORIZATION TO RELEASE MEDICAL RECORDS";
        $data['date'] = date('F jS, Y', time());
        $data['reason'] = $result->request_reason;
        $data['type'] = $result->request_type;
        if ($result->request_reason == 'General Medical Records') {
            $data['type'] .= ' - excluding protected records: (Copies of medical records will be limited to two years of information including lab, x-ray unless otherwise requested.)<br>';
        }
        if ($result->history_physical != '') {
            $data['type'] .= ' dated ' . $result->history_physical . '.<br>';
        }
        if ($result->lab_type != '') {
            $data['type'] .= '<br>' . $result->lab_type . ', Dated ' . $result->lab_date . '.<br>';
        }
        if ($result->op != '') {
            $data['type'] .= ' for ' . $result->op . '.<br>';
        }
        if ($result->accident_f != '') {
            $data['type'] .= ' dated from ' . $result->accident_f . ' to ' . $result->accdient_t . '.<br>';
        }
        if ($result->other != '') {
            $data['type'] .= '<br>' . $result->other . '<br>';
        }
        $data['from'] = $result->request_to;
        if ($result->address_id != '') {
            $address = DB::table('addressbook')->where('address_id', '=', $result->address_id)->first();
            if ($address) {
                $data['from'] = $address->displayname . '<br>' . $address->street_address1 . '<br>';
                if ($address->street_address2 != '') {
                    $data['from'] .= $address->street_address2 . '<br>';
                }
                $data['from'] .= $address->city . ', ' . $address->state . ' ' . $address->zip . '<br>';
                $data['from'] .= $address->phone .'<br>';
            }
        }
        return view('pdf.hippa_request', $data);
    }

    protected function page_immunization_list()
    {
        $data = $this->page_default();
        $data['body'] = 'Immunizations for ' . $data['firstname'] . ' ' . $data['lastname'] . ':<br />';
        $seq_arr = [
            '1' => 'first',
            '2' => 'second',
            '3' => 'third',
            '4' => 'fourth',
            '5' => 'fifth'
        ];
        $query = DB::table('immunizations')->where('pid', '=', Session::get('pid'))->orderBy('imm_immunization', 'asc')->orderBy('imm_sequence', 'asc')->get();
        if ($query->count()) {
            $data['body'] .= '<ul>';
            foreach ($query as $row) {
                $sequence = '';
                if (in_array($row->imm_sequence, $seq_arr)) {
                    $sequence = ', ' . $seq_arr[$row->imm_sequence];
                }
                $data['body'] .= '<li>' . $row->imm_immunization . $sequence . ', given on ' . date('F jS, Y', $this->human_to_unix($row->imm_date)) . '</li>';
            }
            $data['body'] .= '</ul>';
        } else {
            $data['body'] .= 'None.';
        }
        $data['body'] .= '<br />Printed by ' . Session::get('displayname') . '.';
        return view('pdf.letter_page', $data);
    }

    protected function page_intro($title, $practice_id)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        $data['practiceName'] = $practice->practice_name;
        $data['website'] = $practice->website;
        $data['practiceInfo'] = $practice->street_address1;
        if ($practice->street_address2 !== '') {
            $data['practiceInfo'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo'] .= '<br />';
        $data['practiceInfo'] .= $practice->city . ', ' . $practice->state . ' ' . $practice->zip . '<br />';
        $data['practiceInfo'] .= 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax . '<br />';
        $data['practiceLogo'] = $this->practice_logo($practice_id);
        $data['title'] = $title;
        return view('pdf.intro', $data);
    }

    protected function page_invoice1($eid)
    {
        $encounterInfo = DB::table('encounters')->where('eid', '=', $eid)->first();
        $pid = $encounterInfo->pid;
        $assessmentInfo = DB::table('assessment')->where('eid', '=', $eid)->first();
        $data['assessment'] = '';
        if ($assessmentInfo) {
            for ($i=1;$i<=12;$i++) {
                $col = 'assessment_' . $i;
                if ($assessmentInfo->{$col} != '') {
                    if ($i !== 1) {
                        $data['assessment'] .= '<br />';
                    }
                    $data['assessment'] .= $assessmentInfo->{$col} . '<br />';
                }
            }
        }
        $data['text'] = 'No procedures.';
        $result1 = DB::table('billing_core')->where('eid', '=', $eid)->orderBy('cpt_charge', 'desc')->get();
        if ($result1->count()) {
            $charge = 0;
            $payment = 0;
            $data['text'] = '<table style="width:100%"><tr><th style="width:14%">PROCEDURE</th><th style="width:14%">UNITS</th><th style="width:50%">DESCRIPTION</th><th style="width:22%">CHARGE PER UNIT</th></tr>';
            foreach ($result1 as $key1 => $value1) {
                $cpt_charge1[$key1] = $value1->cpt_charge;
                $result1_arr[$key1] = $value1;
            }
            array_multisort($cpt_charge1, SORT_DESC, $result1_arr);
            foreach ($result1 as $result1a) {
                if ($result1a->cpt) {
                    $query2 = DB::table('cpt_relate')->where('cpt', '=', $result1a->cpt)->first();
                    if ($query2) {
                        $result2 = DB::table('cpt_relate')->where('cpt', '=', $result1a->cpt)->first();
                    } else {
                        $result2 = DB::table('cpt')->where('cpt', '=', $result1a->cpt)->first();
                    }
                    $data['text'] .= '<tr><td>' . $result1a->cpt . '</td><td>' . $result1a->unit . '</td><td>' . $result2->cpt_description . '</td><td>$' . $result1a->cpt_charge . '</td></tr>';
                    $charge += $result1a->cpt_charge * $result1a->unit;
                } else {
                    $data['text'] .= '<tr><td>Date of Payment:</td><td>' . $result1a->dos_f . '</td><td>' . $result1a->payment_type . '</td><td>$(' . $result1a->payment . ')</td></tr>';
                    $payment = $payment + $result1a->payment;
                }
            }
            $balance = $charge - $payment;
            $charge = number_format($charge, 2, '.', ',');
            $payment = number_format($payment, 2, '.', ',');
            $balance = number_format($balance, 2, '.', ',');
            $data['text'] .= '<tr><td></td><td></td><td><strong>Total Charges:</strong></td><td><strong>$' . $charge . '</strong></td></tr><tr><td></td><td></td><td><strong>Total Payments:</strong></td><td><strong>$' . $payment . '</strong></td></tr><tr><td></td><td></td><td></td><td><hr/></td></tr><tr><td></td><td></td><td><strong>Remaining Balance:</strong></td><td><strong>$' . $balance . '</strong></td></tr></table>';
        }
        $row = DB::table('demographics')->where('pid', '=', $pid)->first();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $data['practiceName'] = $practice->practice_name;
        $data['practiceInfo1'] = $practice->street_address1;
        if ($practice->street_address2 != '') {
            $data['practiceInfo1'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo2'] = $practice->city . ', ' . $practice->state . ' ' . $practice->zip;
        $data['practiceInfo3'] = 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax;
        $data['disclaimer'] = '<br>Please send a check payable to ' . $practice->practice_name . ' and mail it to:';
        $data['disclaimer'] .= '<br>' . $practice->billing_street_address1;
        if ($practice->billing_street_address2 != '') {
            $data['text'] .= ', ' . $practice->billing_street_address2;
        }
        $data['disclaimer'] .= '<br>' . $practice->billing_city . ', ' . $practice->billing_state . ' ' . $practice->billing_zip;
        $data['patientInfo1'] = $row->firstname . ' ' . $row->lastname;
        $data['patientInfo2'] = $row->address;
        $data['patientInfo3'] = $row->city . ', ' . $row->state . ' ' . $row->zip;
        $data['patientInfo'] = $row;
        $data['dob'] = date('m/d/Y', $this->human_to_unix($row->DOB));

        $data['encounter_DOS'] = date('F jS, Y', $this->human_to_unix($encounterInfo->encounter_DOS));
        $data['encounter_provider'] = $encounterInfo->encounter_provider;
        $query1 = DB::table('insurance')->where('pid', '=', $pid)->where('insurance_plan_active', '=', 'Yes')->get();
        $data['insuranceInfo'] = '';
        if ($query1->count()) {
            foreach ($query1 as $row1) {
                $data['insuranceInfo'] .= $row1->insurance_plan_name . '; ID: ' . $row1->insurance_id_num . '; Group: ' . $row1->insurance_group . '; ' . $row1->insurance_insu_lastname . ', ' . $row1->insurance_insu_firstname . '<br><br>';
            }
        }
        $data['title'] = "INVOICE";
        $data['date'] = date('F jS, Y', time());
        $result = DB::table('demographics_notes')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->first();
        if (is_null($result->billing_notes) || $result->billing_notes == '') {
            $billing_notes = 'Invoice for encounter (Date of Service: ' . $data['encounter_DOS'] . ') printed on ' . $data['date'] . '.';
        } else {
            $billing_notes = $result->billing_notes . "\n" . 'Invoice for encounter (Date of Service: ' . $data['encounter_DOS'] . ') printed on ' . $data['date'] . '.';
        }
        $billing_notes_data['billing_notes'] = $billing_notes;
        DB::table('demographics_notes')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->update($billing_notes_data);
        $this->audit('Update');
        return view('pdf.invoice_page', $data);
    }

    protected function page_invoice2($id, $pid)
    {
        $data['text'] = 'No procedures.';
        $result1 = DB::table('billing_core')->where('other_billing_id', '=', $id)->where('payment', '=', '0')->first();
        if ($result1) {
            $data['text'] = '<table style="width:100%"><tr><th style="width:14%">DATE</th><th style="width:14%">UNITS</th><th style="width:50%">DESCRIPTION</th><th style="width:22%">CHARGE PER UNIT</th></tr>';
            $charge = 0;
            $payment = 0;
            $data['text'] .= '<tr><td>' . $result1->dos_f . '</td><td>' . $result1->unit . '</td><td>' . $result1->reason . '</td><td>$' . $result1->cpt_charge . '</td></tr>';
            $charge += $result1->cpt_charge * $result1->unit;
            $query2 = DB::table('billing_core')->where('other_billing_id', '=', $result1->billing_core_id)->where('payment', '!=', '0')->get();
            if ($query2->count()) {
                foreach ($query2 as $row2) {
                    $data['text'] .= '<tr><td>Date of Payment:</td><td>' . $row2->dos_f . '</td><td>' . $row2->payment_type . '</td><td>$(' . $row2->payment . ')</td></tr>';
                    $payment += $row2->payment;
                }
            }
            $balance = $charge - $payment;
            $charge = number_format($charge, 2, '.', ',');
            $payment = number_format($payment, 2, '.', ',');
            $balance = number_format($balance, 2, '.', ',');
            $data['text'] .= '<tr><td></td><td></td><td><strong>Total Charges:</strong></td><td><strong>$' . $charge . '</strong></td></tr><tr><td></td><td></td><td><strong>Total Payments:</strong></td><td><strong>$' . $payment . '</strong></td></tr><tr><td></td><td></td><td></td><td><hr/></td></tr><tr><td></td><td></td><td><strong>Remaining Balance:</strong></td><td><strong>$' . $balance . '</strong></td></tr></table>';
        }
        $row = DB::table('demographics')->where('pid', '=', $pid)->first();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $data['practiceName'] = $practice->practice_name;
        $data['practiceInfo1'] = $practice->street_address1;
        if ($practice->street_address2 != '') {
            $data['practiceInfo1'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo2'] = $practice->city . ', ' . $practice->state . ' ' . $practice->zip;
        $data['practiceInfo3'] = 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax;
        $data['disclaimer'] = '<br>Please send a check payable to ' . $practice->practice_name . ' and mail it to:';
        $data['disclaimer'] .= '<br>' . $practice->billing_street_address1;
        if ($practice->billing_street_address2 != '') {
            $data['disclaimer'] .= ', ' . $practice->billing_street_address2;
        }
        $data['disclaimer'] .= '<br>' . $practice->billing_city . ', ' . $practice->billing_state . ' ' . $practice->billing_zip;
        $data['patientInfo1'] = $row->firstname . ' ' . $row->lastname;
        $data['patientInfo2'] = $row->address;
        $data['patientInfo3'] = $row->city . ', ' . $row->state . ' ' . $row->zip;
        $data['patientInfo'] = $row;
        $data['dob'] = date('m/d/Y', $this->human_to_unix($row->DOB));
        $data['title'] = "INVOICE";
        $data['date'] = date('F jS, Y', time());
        $result = DB::table('demographics_notes')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->first();
        if (is_null($result->billing_notes) || $result->billing_notes == '') {
            $billing_notes = 'Invoice for ' . $result1->reason . ' (Date of Bill: ' . $result1->dos_f . ') printed on ' . $data['date'] . '.';
        } else {
            $billing_notes = $result->billing_notes . "\n" . 'Invoice for ' . $result1->reason . ' (Date of Bill: ' . $result1->dos_f . ') printed on ' . $data['date'] . '.';
        }
        $billing_notes_data['billing_notes'] = $billing_notes;
        DB::table('demographics_notes')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->update($billing_notes_data);
        $this->audit('Update');
        return view('pdf.invoice_page2', $data);
    }

    protected function page_medication($rxl_id, $pid)
    {
        $rx = DB::table('rx_list')->where('rxl_id', '=', $rxl_id)->first();
        $data['rx'] = $rx;
        $quantity = $rx->rxl_quantity;
        $refill = $rx->rxl_refill;
        $quantity_words = strtoupper($this->convert_number($quantity));
        $refill_words = strtoupper($this->convert_number($refill));
        $data['rx_item'] = '<table style="width:100%"><thead><tr><th style="width:78%">MEDICATION</th><th style="width:22%">DATE</th></tr></thead><tbody>';
		$data['rx_item'] .= '<tr><td style="width:78%">' . $rx->rxl_medication. ' ' . $rx->rxl_dosage . ' ' . $rx->rxl_dosage_unit . '</td><td style="width:22%">' . date('m/d/Y', $this->human_to_unix($rx->rxl_date_prescribed)) . '</td></tr></tbody></table><br>';
		$data['rx_item'] .= '<table style="width:100%"><thead><tr><th style="width:56%">INSTRUCTIONS</th><th style="width:22%">QUANTITY</th><th style="width:22%">REFILLS</th></tr></thead><tbody>';
		$data['rx_item'] .= '<tr><td style="width:56%">';
        if ($rx->rxl_instructions != '') {
            $data['rx_item'] .= $rx->rxl_instructions . ' for ' . $rx->rxl_reason;
        } else {
            $data['rx_item'] .= $rx->rxl_sig . ' ' . $rx->rxl_route . ' ' . $rx->rxl_frequency . ' for ' . $rx->rxl_reason;
        }
        $data['rx_item'] .= '</td><td style="width:22%">***' . $rx->rxl_quantity . '*** ' . $quantity_words . '</td><td style="width:22%">***' . $rx->rxl_refill . '*** ' . $refill_words . '</td></tr></tbody></table>';
		$data['rx_item'] .= '<table style="width:100%"><thead><tr><th style="width:100%">SPECIAL INSTRUCTIONS</th></tr></thead><tbody><tr><td>';
        if ($rx->rxl_daw != '') {
            $data['rx_item'] .= $rx->rxl_daw . '<br>';
        }
        $data['rx_item'] .= '</td></tr></tbody></table>';
        $provider = DB::table('providers')->where('id', '=', $rx->id)->first();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $provider->practice_id)->first();
        $data['practiceName'] = $practice->practice_name;
        $data['website'] = $practice->website;
        $data['practiceInfo'] = $practice->street_address1;
        if ($practice->street_address2 != '') {
            $data['practiceInfo'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo'] .= '<br />';
        $data['practiceInfo'] .= $practice->city . ', ' . $practice->state . ' ' . $practice->zip . '<br />';
        $data['practiceInfo'] .= 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax . '<br />';
        $data['patientInfo'] = DB::table('demographics')->where('pid', '=', $pid)->first();
        $data['practiceLogo'] = $this->practice_logo($provider->practice_id, '40px');
        $rxicon = HTML::image(asset('assets/images/rxicon.png'), 'Practice Logo', array('border' => '0', 'height' => '30', 'width' => '30'));
        $data['rxicon'] = str_replace('https', 'http', $rxicon);
        $data['dob'] = date('m/d/Y', $this->human_to_unix($data['patientInfo']->DOB));
        $query1 = DB::table('insurance')->where('pid', '=', $pid)->where('insurance_plan_active', '=', 'Yes')->get();
        $data['insuranceInfo'] = '';
        if ($query1->count()) {
            foreach ($query1 as $row) {
                $data['insuranceInfo'] .= $row->insurance_plan_name . '; ID: ' . $row->insurance_id_num . '; Group: ' . $row->insurance_group . '; ' . $row->insurance_insu_lastname . ', ' . $row->insurance_insu_firstname . '<br><br>';
            }
        }
        $query2 = DB::table('allergies')->where('pid', '=', $pid)->where('allergies_date_inactive', '=', '0000-00-00 00:00:00')->get();
        $data['allergyInfo'] = '';
        if ($query2->count()) {
            $data['allergyInfo'] .= '<ul>';
            foreach ($query2 as $row1) {
                $data['allergyInfo'] .= '<li>' . $row1->allergies_med . '</li>';
            }
            $data['allergyInfo'] .= '</ul>';
        } else {
            $data['allergyInfo'] .= 'No known allergies.';
        }
        $data['signature'] = $this->signature($rx->id);
        return view('pdf.prescription_page', $data);
    }

    protected function page_medication_combined($pid, $arr, $provider_id)
    {
        $data['rx_item'] = '';
        foreach ($arr as $rxl_id) {
            $rx = DB::table('rx_list')->where('rxl_id', '=', $rxl_id)->first();
            $data['rx'] = $rx;
            $quantity = $rx->rxl_quantity;
            $refill = $rx->rxl_refill;
            $quantity_words = strtoupper($this->convert_number($quantity));
            $refill_words = strtoupper($this->convert_number($refill));
            $data['rx_item'] .= '<table style="width:100%"><thead><tr><th style="width:78%">MEDICATION</th><th style="width:22%">DATE</th></tr></thead><tbody>';
    		$data['rx_item'] .= '<tr><td style="width:78%">' . $rx->rxl_medication. ' ' . $rx->rxl_dosage . ' ' . $rx->rxl_dosage_unit . '</td><td style="width:22%">' . date('m/d/Y', $this->human_to_unix($rx->rxl_date_prescribed)) . '</td></tr></tbody></table><br>';
    		$data['rx_item'] .= '<table style="width:100%"><thead><tr><th style="width:56%">INSTRUCTIONS</th><th style="width:22%">QUANTITY</th><th style="width:22%">REFILLS</th></tr></thead><tbody>';
    		$data['rx_item'] .= '<tr><td style="width:56%">';
            if ($rx->rxl_instructions != '') {
                $data['rx_item'] .= $rx->rxl_instructions . ' for ' . $rx->rxl_reason;
            } else {
                $data['rx_item'] .= $rx->rxl_sig . ' ' . $rx->rxl_route . ' ' . $rx->rxl_frequency . ' for ' . $rx->rxl_reason;
            }
            $data['rx_item'] .= '</td><td style="width:22%">***' . $rx->rxl_quantity . '*** ' . $quantity_words . '</td><td style="width:22%">***' . $rx->rxl_refill . '*** ' . $refill_words . '</td></tr></tbody></table>';
    		$data['rx_item'] .= '<table style="width:100%"><thead><tr><th style="width:100%">SPECIAL INSTRUCTIONS</th></tr></thead><tbody><tr><td>';
            if ($rx->rxl_daw != '') {
                $data['rx_item'] .= $rx->rxl_daw . '<br>';
            }
            $data['rx_item'] .= '</td></tr></tbody></table><br><br><br>';

        }
        $provider = DB::table('providers')->where('id', '=', $provider_id)->first();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $provider->practice_id)->first();
        $data['practiceName'] = $practice->practice_name;
        $data['website'] = $practice->website;
        $data['practiceInfo'] = $practice->street_address1;
        if ($practice->street_address2 != '') {
            $data['practiceInfo'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo'] .= '<br />';
        $data['practiceInfo'] .= $practice->city . ', ' . $practice->state . ' ' . $practice->zip . '<br />';
        $data['practiceInfo'] .= 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax . '<br />';
        $data['patientInfo'] = DB::table('demographics')->where('pid', '=', $pid)->first();
        $data['practiceLogo'] = $this->practice_logo($provider->practice_id, '40px');
        $rxicon = HTML::image(asset('assets/images/rxicon.png'), 'Practice Logo', array('border' => '0', 'height' => '30', 'width' => '30'));
        $data['rxicon'] = str_replace('https', 'http', $rxicon);
        $data['dob'] = date('m/d/Y', $this->human_to_unix($data['patientInfo']->DOB));
        $query1 = DB::table('insurance')->where('pid', '=', $pid)->where('insurance_plan_active', '=', 'Yes')->get();
        $data['insuranceInfo'] = '';
        if ($query1->count()) {
            foreach ($query1 as $row) {
                $data['insuranceInfo'] .= $row->insurance_plan_name . '; ID: ' . $row->insurance_id_num . '; Group: ' . $row->insurance_group . '; ' . $row->insurance_insu_lastname . ', ' . $row->insurance_insu_firstname . '<br><br>';
            }
        }
        $query2 = DB::table('allergies')->where('pid', '=', $pid)->where('allergies_date_inactive', '=', '0000-00-00 00:00:00')->get();
        $data['allergyInfo'] = '';
        if ($query2->count()) {
            $data['allergyInfo'] .= '<ul>';
            foreach ($query2 as $row1) {
                $data['allergyInfo'] .= '<li>' . $row1->allergies_med . '</li>';
            }
            $data['allergyInfo'] .= '</ul>';
        } else {
            $data['allergyInfo'] .= 'No known allergies.';
        }
        $data['signature'] = $this->signature($provider_id);
        return view('pdf.prescription_page', $data);
    }

    protected function page_letter($letter_to, $letter_body, $address_id)
    {
        $body = '';
        if ($address_id != '') {
            $row = DB::table('addressbook')->where('address_id', '=', $address_id)->first();
            $body .= $row->displayname . '<br>' . $row->street_address1;
            if (isset($row->street_address2)) {
                $body .= '<br>' . $row->street_address2;
            }
            $body .= '<br>' . $row->city . ', ' . $row->state . ' ' . $row->zip;
            $body .= '<br><br>';
        }
        $body .= $letter_to . ':';
        $body .= '<br><br>';
        $body .= nl2br($letter_body);
        $sig = $this->signature(Session::get('user_id'));
        $body .= '<br><br>Sincerely,<br>' . $sig;
        $body .= '</body></html>';
        return $body;
    }

    protected function page_results($pid, $results, $patient_name, $from)
    {
        $body = '';
        $body .= "<br>Test results for " . $patient_name . "<br><br>";
        $body .= "<table style='table-layout:fixed;width:800px'><tr><th style='width:100px'>Date</th><th style='width:200px'>Test</th><th style='width:300px'>Result</th><th style='width:50px'>Units</th><th style='width:100px'>Range</th><th style='width:50px'>Flags</th></tr>";
        foreach ($results as $results_row1) {
            $body .= "<tr><td>" . $results_row1['test_datetime'] . "</td><td>" . $results_row1['test_name'] . "</td><td>" . $results_row1['test_result'] . "</td><td>" . $results_row1['test_units'] . "</td><td>" . $results_row1['test_reference'] . "</td><td>" . $results_row1['test_flags'] . "</td></tr>";
        }
        $body .= "</table><br>" . $from;
        $body .= '</body></html>';
        return $body;
    }

    protected function page_letter_reply($body)
    {
        $row = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $data['practiceName'] = $practice->practice_name;
        $data['practiceInfo1'] = $practice->street_address1;
        if ($practice->street_address2 != '') {
            $data['practiceInfo1'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo2'] = $practice->city . ', ' . $practice->state . ' ' . $practice->zip;
        $data['practiceInfo3'] = 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax;
        $data['patientInfo1'] = $row->firstname . ' ' . $row->lastname;
        $data['patientInfo2'] = $row->address;
        $data['patientInfo3'] = $row->city . ', ' . $row->state . ' ' . $row->zip;
        $data['firstname'] = $row->firstname;
        $data['body'] = nl2br($body) . "<br><br>Please contact me if you have any questions.";
        $data['signature'] = $this->signature(Session::get('user_id'));
        $data['date'] = date('F jS, Y');
        return view('pdf.letter_page', $data);
    }

    protected function page_results_list($id)
    {
        $data = $this->page_default();
        $test_arr = $this->test_flag_arr();
        $row0 = DB::table('tests')->where('tests_id', '=', $id)->first();
        $query1 = DB::table('tests')
            ->where('test_name', '=', $row0->test_name)
            ->where('pid', '=', Session::get('pid'))
            ->orderBy('test_datetime', 'asc')
            ->get();
        $data['body'] = $row0->test_name . ' Results for ' . $data['firstname'] . ' ' . $data['lastname'] . ':<br />';
        if ($query1->count()) {
            $data['body'] .= '<table border="1" cellpadding="5"><thead><tr><th>Date</th><th>Result</th><th>Unit</th><th>Range</th><th>Flag</th></thead><tbody>';
            foreach ($query1 as $row1) {
                $data['body'] .= '<tr><td>' . date('Y-m-d', $this->human_to_unix($row1->test_datetime)) . '</td>';
                $data['body'] .= '<td>' . $row1->test_result . '</td>';
                $data['body'] .= '<td>' . $row1->test_units . '</td>';
                $data['body'] .= '<td>' . $row1->test_reference . '</td>';
                $data['body'] .= '<td>' . $test_arr[$row1->test_flags] . '</td></tr>';
            }
            $data['body'] .= '</tbody></table>';
        }
        $data['body'] .= '<br />Printed by ' . Session::get('displayname') . '.';
        $data['date'] = date('F jS, Y');
        $data['signature'] = $this->signature(Session::get('user_id'));
        return view('pdf.letter_page', $data);
    }

    protected function page_orders($orders_id, $pid)
    {
        $data['orders'] = DB::table('orders')->where('orders_id', '=', $orders_id)->first();
        if ($data['orders']->orders_labs != '') {
            $data['title'] = "LABORATORY ORDER";
            $data['title1'] = "LABORATORY PROVIDER";
            $data['title2'] = "ORDER";
            $data['dx'] = nl2br($data['orders']->orders_labs_icd);
            $data['text'] = nl2br($data['orders']->orders_labs) . "<br><br>" . nl2br($data['orders']->orders_labs_obtained);
        }
        if ($data['orders']->orders_radiology != '') {
            $data['title'] = "IMAGING ORDER";
            $data['title1'] = "IMAGING PROVIDER";
            $data['title2'] = "ORDER";
            $data['dx'] = nl2br($data['orders']->orders_radiology_icd);
            $data['text'] = nl2br($data['orders']->orders_radiology);
        }
        if ($data['orders']->orders_cp != '') {
            $data['title'] = "CARDIOPULMONARY ORDER";
            $data['title1'] = "CARDIOPULMONARY PROVIDER";
            $data['title2'] = "ORDER";
            $data['dx'] = nl2br($data['orders']->orders_cp_icd);
            $data['text'] = nl2br($data['orders']->orders_cp);
        }
        if ($data['orders']->orders_referrals != '') {
            $data['title'] = "REFERRAL/GENERAL ORDERS";
            $data['title1'] = "REFERRAL PROVIDER";
            $data['title2'] = "DETAILS";
            $data['dx'] = nl2br($data['orders']->orders_referrals_icd);
            $data['text'] = nl2br($data['orders']->orders_referrals);
        }
        $data['address'] = DB::table('addressbook')->where('address_id', '=', $data['orders']->address_id)->first();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $data['practiceName'] = $practice->practice_name;
        $data['website'] = $practice->website;
        $data['practiceInfo'] = $practice->street_address1;
        if ($practice->street_address2 != '') {
            $data['practiceInfo'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo'] .= '<br />';
        $data['practiceInfo'] .= $practice->city . ', ' . $practice->state . ' ' . $practice->zip . '<br />';
        $data['practiceInfo'] .= 'Phone: ' . $practice->phone . ', Fax: ' . $practice->fax . '<br />';
        $data['practiceLogo'] = $this->practice_logo(Session::get('practice_id'));
        $data['patientInfo'] = DB::table('demographics')->where('pid', '=', $pid)->first();
        $data['dob'] = date('m/d/Y', $this->human_to_unix($data['patientInfo']->DOB));
        $gender_arr = $this->array_gender();
        $data['sex'] = $gender_arr[$data['patientInfo']->sex];
        $data['orders_date'] = date('m/d/Y', $this->human_to_unix($data['orders']->orders_date));
        $data['insuranceInfo'] = nl2br($data['orders']->orders_insurance);
        $data['signature'] = $this->signature($data['orders']->id);
        $data['top'] = 'Physician Order';
        if ($data['orders']->orders_referrals != '') {
            $data['top'] = 'Physician Referral';
        }
        return view('pdf.order_page', $data);
    }

    protected function page_plan($eid)
    {
        $pid = Session::get('pid');
        $ordersInfo = DB::table('orders')->where('eid', '=', $eid)->first();
        if ($ordersInfo) {
            $data['orders'] = '<br><h4>Orders:</h4><p class="view">';
            $ordersInfo_labs_query = DB::table('orders')->where('eid', '=', $eid)->where('orders_labs', '!=', '')->get();
            if ($ordersInfo_labs_query->count()) {
                $data['orders'] .= '<strong>Labs: </strong>';
                foreach ($ordersInfo_labs_query as $ordersInfo_labs_result) {
                    $text1 = nl2br($ordersInfo_labs_result->orders_labs);
                    $address_row1 = DB::table('addressbook')->where('address_id', '=', $ordersInfo_labs_result->address_id)->first();
                    $data['orders'] .= 'Orders sent to ' . $address_row1->displayname . ': '. $text1 . '<br />';
                    $data['orders'] .= $address_row1->street_address1 . '<br />';
                    if ($address_row1->street_address2 != '') {
                        $data['orders'] .= $address_row1->street_address2 . '<br />';
                    }
                    $data['orders'] .= $address_row1->city . ', ' . $address_row1->state . ' ' . $address_row1->zip . '<br />';
                    $data['orders'] .= $address_row1->phone . '<br />';
                }
            }
            $ordersInfo_rad_query = DB::table('orders')->where('eid', '=', $eid)->where('orders_radiology', '!=', '')->get();
            if ($ordersInfo_rad_query->count()) {
                $data['orders'] .= '<strong>Imaging: </strong>';
                foreach ($ordersInfo_rad_query as $ordersInfo_rad_result) {
                    $text2 = nl2br($ordersInfo_rad_result->orders_radiology);
                    $address_row2 = DB::table('addressbook')->where('address_id', '=', $ordersInfo_rad_result->address_id)->first();
                    $data['orders'] .= 'Orders sent to ' . $address_row2->displayname . ': '. $text2 . '<br />';
                    $data['orders'] .= $address_row2->street_address1 . '<br />';
                    if ($address_row2->street_address2 != '') {
                        $data['orders'] .= $address_row2->street_address2 . '<br />';
                    }
                    $data['orders'] .= $address_row2->city . ', ' . $address_row2->state . ' ' . $address_row2->zip . '<br />';
                    $data['orders'] .= $address_row2->phone . '<br />';
                }
            }
            $ordersInfo_cp_query = DB::table('orders')->where('eid', '=', $eid)->where('orders_cp', '!=', '')->get();
            if ($ordersInfo_cp_query->count()) {
                $data['orders'] .= '<strong>Cardiopulmonary: </strong>';
                foreach ($ordersInfo_cp_query as $ordersInfo_cp_result) {
                    $text3 = nl2br($ordersInfo_cp_result->orders_cp);
                    $address_row3 = DB::table('addressbook')->where('address_id', '=', $ordersInfo_cp_result->address_id)->first();
                    $data['orders'] .= 'Orders sent to ' . $address_row3->displayname . ': '. $text3 . '<br />';
                    $data['orders'] .= $address_row3->street_address1 . '<br />';
                    if ($address_row3->street_address2 != '') {
                        $data['orders'] .= $address_row3->street_address2 . '<br />';
                    }
                    $data['orders'] .= $address_row3->city . ', ' . $address_row3->state . ' ' . $address_row3->zip . '<br />';
                    $data['orders'] .= $address_row3->phone . '<br />';
                }
            }
            $ordersInfo_ref_query = DB::table('orders')->where('eid', '=', $eid)->where('orders_referrals', '!=', '')->get();
            if ($ordersInfo_ref_query->count()) {
                $data['orders'] .= '<strong>Referrals: </strong>';
                foreach ($ordersInfo_ref_query as $ordersInfo_ref_result) {
                    $address_row4 = DB::table('addressbook')->where('address_id', '=', $ordersInfo_ref_result->address_id)->first();
                    $data['orders'] .= 'Orders sent to ' . $address_row4->displayname . '<br />';
                    $data['orders'] .= $address_row4->street_address1 . '<br />';
                    if ($address_row4->street_address2 != '') {
                        $data['orders'] .= $address_row4->street_address2 . '<br />';
                    }
                    $data['orders'] .= $address_row4->city . ', ' . $address_row4->state . ' ' . $address_row4->zip . '<br />';
                    $data['orders'] .= $address_row4->phone . '<br />';
                }
            }
            $data['orders'] .= '</p>';
        } else {
            $data['orders'] = '';
        }
        $rxInfo = DB::table('rx')->where('eid', '=', $eid)->first();
        if ($rxInfo) {
            $data['rx'] = '<br><h4>Prescriptions and Immunizations:</h4><p class="view">';
            if ($rxInfo->rx_rx!= '') {
                $data['rx'] .= '<strong>Medications: </strong>';
                $data['rx'] .= nl2br($rxInfo->rx_orders_summary);
                $data['rx'] .= '<br /><br />';
            }
            if ($rxInfo->rx_supplements!= '') {
                $data['rx'] .= '<strong>Supplements to take: </strong>';
                $data['rx'] .= nl2br($rxInfo->rx_supplements_orders_summary);
                $data['rx'] .= '<br /><br />';
            }
            if ($rxInfo->rx_immunizations != '') {
                $data['rx'] .= '<strong>Immunizations: </strong>';
                $data['rx'] .= 'CDC Vaccine Information Sheets given for each immunization and consent obtained.<br />';
                $data['rx'] .= nl2br($rxInfo->rx_immunizations);
                $data['rx'] .= '<br /><br />';
            }
            $data['rx'] .= '</p>';
        } else {
            $data['rx'] = '';
        }
        $planInfo = DB::table('plan')->where('eid', '=', $eid)->first();
        if ($planInfo) {
            $data['plan'] = '<br><h4>Plan:</h4><p class="view">';
            if ($planInfo->plan!= '') {
                $data['plan'] .= '<strong>Recommendations: </strong>';
                $data['plan'] .= nl2br($planInfo->plan);
                $data['plan'] .= '<br /><br />';
            }
            if ($planInfo->followup != '') {
                $data['plan'] .= '<strong>Followup: </strong>';
                $data['plan'] .= nl2br($planInfo->followup);
                $data['plan'] .= '<br /><br />';
            }
            $data['plan'] .= '</p>';
        } else {
            $data['plan'] = '';
        }
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $data['practiceName'] = $practice->practice_name;
        $data['website'] = $practice->website;
        $data['practiceInfo'] = $practice->street_address1;
        if ($practice->street_address2 != '') {
            $data['practiceInfo'] .= ', ' . $practice->street_address2;
        }
        $data['practiceInfo'] .= '<br />';
        $data['practiceInfo'] .= $practice->city . ', ' . $practice->state . ' ' . $practice->zip . '<br />';
        $data['practiceLogo'] = $this->practice_logo(Session::get('practice_id'));
        $data['patientInfo'] = DB::table('demographics')->where('pid', '=', $pid)->first();
        $data['dob'] = date('m/d/Y', $this->human_to_unix($data['patientInfo']->DOB));
        $encounterInfo = DB::table('encounters')->where('eid', '=', $eid)->first();
        $data['encounter_DOS'] = date('F jS, Y', $this->human_to_unix($encounterInfo->encounter_DOS));
        $data['encounter_provider'] = $encounterInfo->encounter_provider;
        $query1 = DB::table('insurance')->where('pid', '=', $pid)->where('insurance_plan_active', '=', 'Yes')->get();
        $data['insuranceInfo'] = '';
        if ($query1->count()) {
            foreach ($query1 as $row) {
                $data['insuranceInfo'] .= $row->insurance_plan_name . '; ID: ' . $row->insurance_id_num . '; Group: ' . $row->insurance_group . '; ' . $row->insurance_insu_lastname . ', ' . $row->insurance_insu_firstname . '<br><br>';
            }
        }
        return view('pdf.instruction_page', $data);
    }

    function parse_era($era_string)
    {
        $return = [];
        $lines = explode('~', $era_string);
        $pos1 = strpos($era_string, '~');
        if ($pos1 !== false) {
            if (substr($lines[0], 0, 3) === 'ISA') {
                $element_delimiter = substr($lines[0], 3, 1);
                $sub_element_delimiter = substr($lines[0], -1);
                $return['loop_id'] = '';
                $return['st_segment_count'] = 0;
                $return['clp_segment_count'] = 0;
                foreach ($lines as $line) {
                    $pos2 = strpos($line, $element_delimiter);
                    if ($pos2 !== false) {
                        $elements = explode($element_delimiter, $line);
                        if ($elements[0] == 'ISA') {
                            if ($return['loop_id'] != '') {
                                $return['error'][] = 'Unexpected ISA segment for ' . $return['loop_id'];
                            } else {
                                $return['isa_sender_id'] = trim($elements[6]);
                                $return['isa_receiver_id'] = trim($elements[8]);
                                $return['isa_control_number'] = trim($elements[13]);
                            }
                        } elseif ($elements[0] == 'GS') {
                            if ($return['loop_id'] != '') {
                                $return['error'][] = 'Unexpected GS segment for ' . $return['loop_id'];
                            } else {
                                $return['gs_date'] = trim($elements[4]);
                                $return['gs_time'] = trim($elements[5]);
                                $return['gs_control_number'] = trim($elements[6]);
                            }
                        } elseif ($elements[0] == 'ST') {
                            if ($return['loop_id'] != '') {
                                $return['error'][] = 'Unexpected ST segment for ' . $return['loop_id'];
                            } else {
                                //$this->parse_era_2100($return, $cb);
                                $return['st_control_number'] = trim($elements[2]);
                                $return['st_segment_count'] = 0;
                            }
                        } elseif ($elements[0] == 'BPR') {
                            if ($return['loop_id'] != '') {
                                $return['error'][] = 'Unexpected BPR segment for ' . $return['loop_id'];
                            } else {
                                $return['check_amount'] = trim($elements[2]);
                                $return['check_date'] = strtotime(trim($elements[16])); // converted to unix time
                            }
                        } elseif ($elements[0] == 'TRN') {
                            if ($return['loop_id'] != '') {
                                $return['error'][] = 'Unexpected TRN segment for ' . $return['loop_id'];
                            } else {
                                $return['check_number'] = trim($elements[2]);
                                $return['payer_tax_id'] = substr($elements[3], 1); // converted to 9 digits
                                if (isset($elements[4])) {
                                    $return['payer_id'] = trim($elements[4]);
                                }
                            }
                        } elseif ($elements[0] == 'DTM' && $elements[1] == '405') {
                            if ($return['loop_id'] != '') {
                                $return['error'][] = 'Unexpected DTM/405 segment for ' . $return['loop_id'];
                            } else {
                                $return['production_date'] = strtotime(trim($elements[2])); // converted to unix time
                            }
                        } elseif ($elements[0] == 'N1' && $elements[1] == 'PR') {
                            if ($return['loop_id'] != '') {
                                $return['error'][] = 'Unexpected N1|PR segment for ' . $return['loop_id'];
                            } else {
                                $return['loop_id'] = '1000A';
                                $return['payer_name'] = trim($elements[2]);
                            }
                        } elseif ($elements[0] == 'N3' && $return['loop_id'] == '1000A') {
                            $return['payer_street'] = trim($elements[1]);
                        } elseif ($elements[0] == 'N4' && $return['loop_id'] == '1000A') {
                            $return['payer_city'] = trim($elements[1]);
                            $return['payer_state'] = trim($elements[2]);
                            $return['payer_zip'] = trim($elements[3]);
                        } elseif ($elements[0] == 'N1' && $elements[1] == 'PE') {
                            if ($return['loop_id'] != '1000A') {
                                $return['error'][] = 'Unexpected N1|PE segment for ' . $return['loop_id'];
                            } else {
                                $return['loop_id'] = '1000B';
                                $return['payee_name'] = trim($elements[2]);
                                $return['payee_tax_id'] = trim($elements[4]);
                            }
                        } elseif ($elements[0] == 'N3' && $return['loop_id'] == '1000B') {
                            $return['payee_street'] = trim($elements[1]);
                        } elseif ($elements[0] == 'N4' && $return['loop_id'] == '1000B') {
                            $return['payee_city']  = trim($elements[1]);
                            $return['payee_state'] = trim($elements[2]);
                            $return['payee_zip']   = trim($elements[3]);
                        } elseif ($elements[0] == 'LX') {
                            if (!$return['loop_id']) {
                                $return['error'][] = 'Unexpected LX segment for ' . $return['loop_id'];
                            } else {
                                //$this->parse_era_2100($return, $cb);
                                $return['loop_id'] = '2000';
                            }
                        } elseif ($elements[0] == 'CLP') {
                            if (!$return['loop_id']) {
                                $return['error'][] = 'Unexpected CLP segment for ' . $return['loop_id'];
                            } else {
                                //$this->parse_era_2100($return, $cb);
                                $return['loop_id'] = '2100';
                                // Clear some stuff to start the new claim:
                                $claim_num = $return['clp_segment_count'];
                                $return['clp_segment_count']++;
                                $return['claim'][$claim_num]['subscriber_lastname']     = '';
                                $return['claim'][$claim_num]['subscriber_firstname']     = '';
                                $return['claim'][$claim_num]['subscriber_middle']     = '';
                                $return['claim'][$claim_num]['subscriber_member_id'] = '';
                                $return['claim'][$claim_num]['claim_forward'] = 0;
                                $return['claim'][$claim_num]['item'] = array();
                                $return['claim'][$claim_num]['bill_Box26'] = trim($elements[1]); // HCFA Box 26 pid_eid
                                $return['claim'][$claim_num]['claim_status_code'] = trim($elements[2]);
                                $return['claim'][$claim_num]['amount_charged'] = trim($elements[3]);
                                $return['claim'][$claim_num]['amount_approved'] = trim($elements[4]);
                                $return['claim'][$claim_num]['amount_patient'] = trim($elements[5]); // pt responsibility, copay + deductible
                                $return['claim'][$claim_num]['payer_claim_id'] = trim($elements[7]); // payer's claim number
                            }
                        } elseif ($elements[0] == 'CAS' && $return['loop_id'] == '2100') {
                            $return['adjustment'] = array();
                            $i = 0;
                            for ($k = 2; $k < 20; $k += 3) {
                                if (!$elements[$k]) break;
                                $return['claim'][$claim_num]['adjustment'][$i]['group_code'] = $elements[1];
                                $return['claim'][$claim_num]['adjustment'][$i]['reason_code'] = $elements[$k];
                                $return['claim'][$claim_num]['adjustment'][$i]['amount'] = $elements[$k+1];
                                $i++;
                            }
                        } elseif ($elements[0] == 'NM1' && $elements[1] == 'QC' && $return['loop_id'] == '2100') {
                            $return['claim'][$claim_num]['patient_lastname'] = trim($elements[3]);
                            $return['claim'][$claim_num]['patient_firstname'] = trim($elements[4]);
                            $return['claim'][$claim_num]['patient_middle'] = trim($elements[5]);
                            $return['claim'][$claim_num]['patient_member_id'] = trim($elements[9]);
                        } elseif ($elements[0] == 'NM1' && $elements[1] == 'IL' && $return['loop_id'] == '2100') {
                            $return['claim'][$claim_num]['subscriber_lastname'] = trim($elements[3]);
                            $return['claim'][$claim_num]['subscriber_firstname'] = trim($elements[4]);
                            $return['claim'][$claim_num]['subscriber_middle'] = trim($elements[5]);
                            $return['claim'][$claim_num]['subscriber_member_id'] = trim($elements[9]);
                        } elseif ($elements[0] == 'NM1' && $elements[1] == '82' && $return['loop_id'] == '2100') {
                            $return['claim'][$claim_num]['provider_lastname'] = trim($elements[3]);
                            $return['claim'][$claim_num]['provider_firstname'] = trim($elements[4]);
                            $return['claim'][$claim_num]['provider_middle'] = trim($elements[5]);
                            $return['claim'][$claim_num]['provider_member_id'] = trim($elements[9]);
                        } elseif ($elements[0] == 'NM1' && $elements[1] == 'TT' && $return['loop_id'] == '2100') {
                            $return['claim'][$claim_num]['claim_forward'] = 1; // claim automatic forward case to another payer.
                        } elseif ($elements[0] == 'REF' && $elements[1] == '1W' && $return['loop_id'] == '2100') {
                            $return['claim'][$claim_num]['claim_comment'] = trim($elements[2]);
                        } elseif ($elements[0] == 'DTM' && $elements[1] == '050' && $return['loop_id'] == '2100') {
                            $return['claim'][$claim_num]['claim_date'] = strtotime(trim($elements[2])); // converted to unix time
                        } else if ($elements[0] == 'PER' && $return['loop_id'] == '2100') {
                            $return['claim'][$claim_num]['payer_insurance'] = trim($elements[2]);
                        } else if ($elements[0] == 'SVC') {
                            if (!$return['loop_id']) {
                                $return['error'][] = 'Unexpected SVC segment for ' . $return['loop_id'];
                            } else {
                                $return['loop_id'] = '2110';
                                if (isset($elements[6])) {
                                    $item = explode($sub_element_delimiter, $elements[6]);
                                } else {
                                    $item = explode($sub_element_delimiter, $elements[1]);
                                }
                                if ($item[0] != 'HC') {
                                    $return['error'][] = 'item segment has unexpected qualifier';
                                }
                                if (isset($return['claim'][$claim_num]['item'])) {
                                    $l = count($return['claim'][$claim_num]['item']);
                                } else {
                                    $l = 0;
                                }
                                $return['claim'][$claim_num]['item'][$l] = array();
                                if (strlen($item[1]) == 7 && empty($item[2])) {
                                    $return['claim'][$claim_num]['item'][$l]['cpt'] = substr($item[1], 0, 5);
                                    $return['claim'][$claim_num]['item'][$l]['modifier'] = substr($item[1], 5);
                                } else {
                                    $return['claim'][$claim_num]['item'][$l]['cpt'] = $item[1];
                                    $return['claim'][$claim_num]['item'][$l]['modifier'] = isset($item[2]) ? $item[2] . ':' : '';
                                    $return['claim'][$claim_num]['item'][$l]['modifier'] .= isset($item[3]) ? $item[3] . ':' : '';
                                    $return['claim'][$claim_num]['item'][$l]['modifier'] .= isset($item[4]) ? $item[4] . ':' : '';
                                    $return['claim'][$claim_num]['item'][$l]['modifier'] .= isset($item[5]) ? $item[5] . ':' : '';
                                    $return['claim'][$claim_num]['item'][$l]['modifier'] = preg_replace('/:$/','',$return['claim'][$claim_num]['item'][$l]['modifier']);
                                }
                                $return['claim'][$claim_num]['item'][$l]['charge'] = $elements[2];
                                $return['claim'][$claim_num]['item'][$l]['paid'] = $elements[3];
                                $return['claim'][$claim_num]['item'][$l]['adjustment'] = array();
                            }
                        } elseif ($elements[0] == 'DTM' && $return['loop_id'] == '2110') {
                            $return['claim'][$claim_num]['dos'] = strtotime(trim($elements[2])); // converted to unix time
                        } elseif ($elements[0] == 'CAS' && $return['loop_id'] == '2110') {
                            $m = count($return['claim'][$claim_num]['item']) - 1;
                            for ($n = 2; $n < 20; $n += 3) {
                                if (!isset($elements[$n])) break;
                                if ($elements[1] == 'CO' && $elements[$n+1] < 0) {
                                    $elements[$n+1] = 0 - $elements[$n+1];
                                }
                                $o = count($return['claim'][$claim_num]['item'][$m]['adjustment']);
                                $return['claim'][$claim_num]['item'][$m]['adjustment'][$o] = array();
                                $return['claim'][$claim_num]['item'][$m]['adjustment'][$o]['group_cpt']  = $elements[1];
                                $return['claim'][$claim_num]['item'][$m]['adjustment'][$o]['reason_cpt'] = $elements[$n];
                                $return['claim'][$claim_num]['item'][$m]['adjustment'][$o]['amount'] = $elements[$n+1];
                            }
                        } elseif ($elements[0] == 'AMT' && $elements[1] == 'B6' && $return['loop_id'] == '2110') {
                            $p = count($return['claim'][$claim_num]['item']) - 1;
                            $return['claim'][$claim_num]['item'][$p]['allowed'] = $elements[2];
                        } elseif ($elements[0] == 'LQ' && $elements[1] == 'HE' && $return['loop_id'] == '2110') {
                            $q = count($return['claim'][$claim_num]['item']) - 1;
                            $return['claim'][$claim_num]['item'][$q]['remark'] = $elements[2];
                        } elseif ($elements[0] == 'PLB') {
                            for ($r = 3; $r < 15; $r += 2) {
                                if (!$elements[$r]) break;
                                $return['plb'] .= 'PROVIDER LEVEL ADJUSTMENT (not claim-specific): $' .
                                    sprintf('%.2f', $elements[$r+1]) . " with reason cpt " . $elements[$r] . "\n";
                            }
                        } elseif ($elements[0] == 'SE') {
                            //$this->parse_era_2100($return, $cb);
                            $return['loop_id'] = '';
                            if ($return['st_control_number'] != trim($elements[2])) {
                                return 'Ending transaction set control number mismatch';
                            }
                            if (($return['st_segment_count'] + 1) != trim($elements[1])) {
                                return 'Ending transaction set segment count mismatch';
                            }
                        } elseif ($elements[0] == 'GE') {
                            if ($return['loop_id']) {
                                $return['error'][] = 'Unexpected GE segment';
                            }
                            if ($return['gs_control_number'] != trim($elements[2])) {
                                $return['error'][] = 'Ending functional group control number mismatch';
                            }
                        } elseif ($elements[0] == 'IEA') {
                            if ($return['loop_id']) {
                                $return['error'][] = 'Unexpected IEA segment';
                            }
                            if ($return['isa_control_number'] != trim($elements[2])) {
                                $return['error'][] = 'Ending interchange control number mismatch';
                            }
                        } else {
                            $return['error'][] = 'Unknown or unexpected segment ID ' . $elements[0];
                        }
                    } else {
                        $error_line = $return['st_segment_count'] + 1;
                        $return['error'][] = 'Error reading line ' . $error_line;
                    }
                    $return['st_segment_count']++;
                }
                if ($elements[0] != 'IEA') {
                    $return['error'][] = 'Premature end of ERA file';
                }
            } else {
                $return['invalid'] = 'First line is not an ISA segment, unable to read the file.';
            }
        } else {
            $return['invalid'] = 'This is not a valid EDI 835 file!';
        }
        return $return;
    }

    protected function patient_is_user($pid)
    {
        $row = DB::table('demographics_relate')->where('pid', '=', $pid)->where('practice_id', '=', Session::get('practice_id'))->first();
        $row2 = DB::table('demographics')->where('pid', '=', $pid)->first();
        $data = [];
        $data['status'] = 'no';
        if ($row->id !== '' && $row->id !== null) {
            $data['status'] = "yes";
            $row1 = DB::table('users')->where('id', '=', $row->id)->first();
            $data['message_to'] = $row1->displayname . ' (' . $row1->id . ')';
            $data['patient_name'] = $row2->lastname . ', ' . $row2->firstname . ' (DOB: ' . date('m/d/Y', strtotime($row2->DOB)) . ') (ID: ' . $pid . ')';
            $data['pid'] = $pid;
        }
        return $data;
    }

    protected function plan_build($type, $action, $text)
    {
        $eid = Session::get('eid');
        $pid = Session::get('pid');
        $encounter_provider = Session::get('displayname');
        $rx_row = DB::table('rx')->where('eid', '=', $eid)->first();
        if ($type == 'rx') {
            $rx_rx_arr = [];
            $rx_rx = "";
            $rx_orders_summary_text = "";
            $rx_prescribe_text_arr = [];
            $rx_eie_text_arr = [];
            $rx_inactivate_text_arr = [];
            $rx_reactivate_text_arr = [];
            $rx_col = 'rx_' . $action . '_text_arr';
            ${$rx_col}[] = $text;
            if ($rx_row) {
                $rx_row_parts = explode("\n\n", $rx_row->rx_rx);
                foreach($rx_row_parts as $rx_row_part) {
                    if (strpos($rx_row_part, "PRESCRIBED MEDICATIONS:") !== false) {
                        $arr1 = explode("\n", str_replace("PRESCRIBED MEDICATIONS:  ", "", $rx_row_part));
                        $arr1 = array_where($arr1, function($value, $key) {
                            if ($value !== '') {
                                return true;
                            }
                        });
                        $rx_prescribe_text_arr = array_merge($rx_prescribe_text_arr, $arr1);
                    }
                    if (strpos($rx_row_part, "ENTERED MEDICATIONS IN ERROR:") !== false) {
                        $arr2 = explode("\n", str_replace("ENTERED MEDICATIONS IN ERROR:  ", "", $rx_row_part));
                        $arr2 = array_where($arr2, function($value, $key) {
                            if ($value !== '') {
                                return true;
                            }
                        });
                        $rx_eie_text_arr = array_merge($rx_eie_text_arr, $arr2);
                    }
                    if (strpos($rx_row_part, "DISCONTINUED MEDICATIONS:") !== false) {
                        $arr3 = explode("\n", str_replace("DISCONTINUED MEDICATIONS:  ", "", $rx_row_part));
                        $arr3 = array_where($arr3, function($value, $key) {
                            if ($value !== '') {
                                return true;
                            }
                        });
                        $rx_inactivate_text_arr = array_merge($rx_inactivate_text_arr, $arr3);
                    }
                    if (strpos($rx_row_part, "REINSTATED MEDICATIONS:") !== false) {
                        $arr4 = explode("\n", str_replace("REINSTATED MEDICATIONS:  ", "", $rx_row_part));
                        $arr4 = array_where($arr4, function($value, $key) {
                            if ($value !== '') {
                                return true;
                            }
                        });
                        $rx_reactivate_text_arr = array_merge($rx_reactivate_text_arr, $arr4);
                    }
                }
            }
            if(! empty($rx_prescribe_text_arr)) {
                array_unshift($rx_prescribe_text_arr, "PRESCRIBED MEDICATIONS:  ");
                $rx_rx_arr[] = implode("\n", $rx_prescribe_text_arr);
            }
            if(! empty($rx_eie_text_arr)) {
                array_unshift($rx_eie_text_arr, "ENTERED MEDICATIONS IN ERROR:  ");
                $rx_rx_arr[]= implode("\n", $rx_eie_text_arr);
            }
            if(! empty($rx_inactivate_text_arr)) {
                array_unshift($rx_inactivate_text_arr, "DISCONTINUED MEDICATIONS:  ");
                $rx_rx_arr[] = implode("\n", $rx_inactivate_text_arr);
            }
            if(! empty($rx_reactivate_text_arr)) {
                array_unshift($rx_reactivate_text_arr, "REINSTATED MEDICATIONS:  ");
                $rx_rx_arr[] = implode("\n", $rx_reactivate_text_arr);
            }
            if(! empty($rx_rx_arr)) {
                $rx_rx = implode("\n\n", $rx_rx_arr);
                $rx_orders_summary_text = implode("\n\n", $rx_rx_arr);
            }
            $rx_data = [
                'eid' => $eid,
                'pid' => $pid,
                'encounter_provider' => $encounter_provider,
                'rx_rx' => $rx_rx,
                'rx_orders_summary' => $rx_orders_summary_text
            ];
            if ($rx_row) {
                DB::table('rx')->where('eid', '=', $eid)->update($rx_data);
                $this->audit('Update');
                // $this->api_data('update', 'rx', 'eid', $eid);
                $return = 'Medication Orders Updated';
            } else {
                DB::table('rx')->insert($rx_data);
                $this->audit('Add');
                // $this->api_data('update', 'rx', 'eid', $eid);
                $return = 'Medication Orders Added';
            }
        }
        if ($type == 'imm') {
            $rx_immunizations_arr = [];
            if ($action == 'save') {
                $rx_immunizations_arr[] = $text;
            }
            if ($rx_row) {
                $imm_row_parts = explode("\n", $rx_row->rx_immunizations);
                $rx_immunizations_arr = array_merge($rx_immunizations_arr, $imm_row_parts);
            }
            if ($action == 'delete') {
                $rx_immunizations_arr = $this->remove_array_item($rx_immunizations_arr, $text);
            }
            $imm_data = [
                'eid' => $eid,
                'pid' => $pid,
                'encounter_provider' => $encounter_provider,
                'rx_immunizations' => implode("\n", $rx_immunizations_arr),
            ];
            if ($rx_row) {
                DB::table('rx')->where('eid', '=', $eid)->update($imm_data);
                $this->audit('Update');
                // $this->api_data('update', 'rx', 'eid', $eid);
                $return = 'Immunization Orders Updated';
            } else {
                DB::table('rx')->insert($imm_data);
                $this->audit('Add');
                // $this->api_data('update', 'rx', 'eid', $eid);
                $return = 'Immunization Orders Added';
            }
        }
        if ($type == 'sup') {
            $sup_orders_summary_text = "";
            $query = DB::table('sup_list')->where('pid', '=', $pid)->where('sup_date_inactive', '=', '0000-00-00 00:00:00')->get();
            if ($query->count()) {
                foreach ($query as $query_row) {
                    $sup_orders_summary_text .= $query_row->sup_supplement . ' ' . $query_row->sup_dosage . ' ' . $query_row->sup_dosage_unit;
                    if ($query_row->sup_sig != "") {
                        $sup_orders_summary_text .= ", " . $query_row->sup_sig . ', ' . $query_row->sup_route . ', ' . $query_row->sup_frequency;
                    }
                    if ($query_row->sup_instructions != "") {
                        $sup_orders_summary_text .= ", " . $query_row->sup_instructions;
                    }
                    $sup_orders_summary_text .= ' for ' . $query_row->sup_reason . "\n";
                }
            }
            $rx_supplements = "";
            $rx_supplements_arr = [];
            $sup_order_text_arr = [];
            $sup_purchase_text_arr = [];
            $sup_inactivate_text_arr = [];
            $sup_reactivate_text_arr = [];
            $sup_col = 'sup_' . $action . '_text_arr';
            ${$sup_col}[] = $text;
            if ($rx_row) {
                $sup_row_parts = explode("\n\n", $rx_row->rx_supplements);
                foreach($sup_row_parts as $sup_row_part) {
                    if (strpos($sup_row_part, "SUPPLEMENTS ADVISED:") !== false) {
                        $arr5 = explode("\n", str_replace("SUPPLEMENTS ADVISED:  ", "", $sup_row_part));
                        $arr5 = array_where($arr5, function($value, $key) {
                            if ($value !== '') {
                                return true;
                            }
                        });
                        $sup_order_text_arr = array_merge($sup_order_text_arr, $arr5);
                    }
                    if (strpos($sup_row_part, "SUPPLEMENTS PURCHASED BY PATIENT:") !== false) {
                        $arr6 = explode("\n", str_replace("ENTERED MEDICATIONS IN ERROR:  ", "", $sup_row_part));
                        $arr6 = array_where($arr6, function($value, $key) {
                            if ($value !== '') {
                                return true;
                            }
                        });
                        $sup_purchase_text_arr = array_merge($sup_purchase_text_arr, $arr6);
                    }
                    if (strpos($sup_row_part, "DISCONTINUED SUPPLEMENTS:") !== false) {
                        $arr7 = explode("\n", str_replace("DISCONTINUED SUPPLEMENTS:  ", "", $sup_row_part));
                        $arr7 = array_where($arr7, function($value, $key) {
                            if ($value !== '') {
                                return true;
                            }
                        });
                        $sup_inactivate_text_arr = array_merge($sup_inactivate_text_arr, $arr7);
                    }
                    if (strpos($sup_row_part, "REINSTATED SUPPLEMENTS:") !== false) {
                        $arr8 = explode("\n", str_replace("REINSTATED SUPPLEMENTS:  ", "", $sup_row_part));
                        $arr8 = array_where($arr8, function($value, $key) {
                            if ($value !== '') {
                                return true;
                            }
                        });
                        $sup_reactivate_text_arr = array_merge($sup_reactivate_text_arr, $arr8);
                    }
                }
            }
            if(! empty($sup_order_text_arr)) {
                array_unshift($sup_order_text_arr, "SUPPLEMENTS ADVISED:  ");
                $rx_supplements_arr[] = implode("\n", $sup_order_text_arr);
            }
            if(! empty($sup_purchase_text_arr)) {
                array_unshift($sup_purchase_text_arr, "SUPPLEMENTS PURCHASED BY PATIENT:  ");
                $rx_supplements_arr[]= implode("\n", $sup_purchase_text_arr);
            }
            if(! empty($sup_inactivate_text_arr)) {
                array_unshift($sup_inactivate_text_arr, "DISCONTINUED SUPPLEMENTS:  ");
                $rx_supplements_arr[] = implode("\n", $sup_inactivate_text_arr);
            }
            if(! empty($sup_reactivate_text_arr)) {
                array_unshift($sup_reactivate_text_arr, "REINSTATED SUPPLEMENTS:  ");
                $rx_supplements_arr[] = implode("\n", $sup_reactivate_text_arr);
            }
            if(! empty($rx_supplements_arr)) {
                $rx_supplements = implode("\n\n", $rx_supplements_arr);
                $sup_orders_summary_text .= implode("\n\n", $rx_supplements_arr);
            }
            $sup_data = [
                'eid' => $eid,
                'pid' => $pid,
                'encounter_provider' => $encounter_provider,
                'rx_supplements' => $rx_supplements,
                'rx_supplements_orders_summary' => $sup_orders_summary_text
            ];
            if ($rx_row) {
                DB::table('rx')->where('eid', '=', $eid)->update($sup_data);
                $this->audit('Update');
                // $this->api_data('update', 'rx', 'eid', $eid);
                $return = 'Supplement Orders Updated';
            } else {
                DB::table('rx')->insert($sup_data);
                $this->audit('Add');
                // $this->api_data('update', 'rx', 'eid', $eid);
                $return = 'Supplement Orders Added';
            }
        }
        return $return;
    }

    protected function pnosh_notification()
    {
        $core_practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        if ($core_practice->patient_centric == 'y') {
            $providers = DB::table('users')->where('group_id', '=', '2')->get();
            $patient = DB::table('demographics')->where('pid', '=', '1')->first();
            $dob = date('m/d/Y', strtotime($patient->DOB));
            $name = $patient->lastname . ', ' . $patient->firstname . ' (DOB: ' . $dob . ')';
            if ($providers->count()) {
                $data_message['item'] = 'There is a new health update for ' . $name . '.  For more details, click here: ' . route('dashboard');
                foreach ($providers as $provider) {
                    $this->send_mail('emails.blank', $data_message, 'Health Update', $provider->email, '1');
                }
            }
        }
        return true;
    }

    protected function pnosh_sync($sync_data)
    {
        $result = '';
        if (Session::get('patient_centric') == 'yp' || Session::get('patient_centric') == 'y') {
            $url = str_replace('/nosh', '/pnosh_sync', URL::to('/'));
            $ch = curl_init();
            $message = http_build_query($sync_data);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0);
            $result = curl_exec($ch);
        }
        return $result;
    }

    protected function practice_logo($practice_id, $size='80px')
    {
        $logo = '<br><br><br><br><br>';
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        if ($practice->practice_logo !== '' && $practice->practice_logo !== null) {
            if (file_exists(public_path() . '/' . $practice->practice_logo)) {
                $link = HTML::image($practice->practice_logo, 'Practice Logo', array('border' => '0', 'height' => $size));
                $logo = str_replace('https', 'http', $link);
            }
        }
        return $logo;
    }

    protected function practice_stats()
    {
        $practice_id = Session::get('practice_id');
        $current_date = time();
        $query1 = DB::table('demographics')
            ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
            ->where('demographics.active', '=', '1')
            ->where('demographics_relate.practice_id', '=', $practice_id)
            ->get();
        $total = count($query1);
        $a = $current_date - 568024668;
        $a1 = date('Y-m-d H:i:s', $a);
        $query2 = DB::table('demographics')
            ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
            ->where('demographics.active', '=', '1')
            ->where('demographics_relate.practice_id', '=', $practice_id)
            ->where('demographics.DOB', '>=', $a1)
            ->get();
        $num1 = count($query2);
        $b = $current_date - 2051200190;
        $b1 = date('Y-m-d H:i:s', $b);
        $query3 = DB::table('demographics')
            ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
            ->where('demographics.active', '=', '1')
            ->where('demographics_relate.practice_id', '=', $practice_id)
            ->where('demographics.DOB', '<', $a1)
            ->where('demographics.DOB', '>=', $b1)
            ->get();
        $num2 = count($query3);
        $query4 = DB::table('demographics')
            ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
            ->where('demographics.active', '=', '1')
            ->where('demographics_relate.practice_id', '=', $practice_id)
            ->where('demographics.DOB', '<', $b1)
            ->get();
        $num3 = count($query4);
        $html = '<h4>Age Distribution of Patients in the Practice:</h4><ul>';
        $html .= '<li><b>0-18 years of age:</b> ' . round($num1/$total*100) . "% of patients</li>";
        $html .= '<li><b>19-64 years of age:</b> ' . round($num2/$total*100) . "% of patients</li>";
        $html .= '<li><b>65+ years of age:</b> ' . round($num3/$total*100) . "% of patients</li></ul>";
        return $html;
    }

    protected function prescription_notification($id, $to='')
    {
        $row = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $row2 = DB::table('practiceinfo')->where('practice_id', '=',Session::get('practice_id'))->first();
        if ($to == '') {
            $to = $row->reminder_to;
            $reminder_method = $row->reminder_method;
        } else {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
                $reminder_method = 'Email';
            } else {
                $reminder_method = 'Cellular Phone';
            }
        }
        if ($to !== '' && $to !== null) {
            $link = route('prescription_view', [$id]);
            if ($reminder_method == 'Cellular Phone') {
                $data_message['item'] = 'New Medication: ' . $link;
                $message = view('emails.blank', $data_message)->render();
                $this->textbelt($to, $message, $row2->practice_id);
            } else {
                $data_message['item'] = 'You have a new medication prescribed to you.  For more details, click here: ' . $link;
                $this->send_mail('emails.blank', $data_message, 'New Medication', $to, Session::get('practice_id'));
            }
        }
    }

    /**
     *    Print chart
     *
     *    @param    $hippa_id = Hippa ID
     * @param    $pid = Patient ID
     *  @param    $type = Options: all, queue, 1year
     */
    protected function print_chart($hippa_id, $pid, $type)
    {
        ini_set('memory_limit','196M');
        $result = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $patient = DB::table('demographics')->where('pid', '=', $pid)->first();
        $lastname = str_replace(' ', '_', $patient->lastname);
        $firstname = str_replace(' ', '_', $patient->firstname);
        $dob = date('Ymd', $this->human_to_unix($patient->DOB));
        $filename_string = str_random(30);
        $pdf_arr = [];
        // Generate encounters and messages
        $header = strtoupper($patient->lastname . ', ' . $patient->firstname . '(DOB: ' . date('m/d/Y', $this->human_to_unix($patient->DOB)) . ', Gender: ' . ucfirst(Session::get('gender')) . ', ID: ' . $pid . ')');
        $file_path_enc = public_path() . '/temp/' . time() . '_' . $filename_string . '_printchart.pdf';
        $html = $this->page_intro('Medical Records', Session::get('practice_id'));
        if ($type == 'all') {
            $query1 = DB::table('encounters')
                ->where('pid', '=', $pid)
                ->where('encounter_signed', '=', 'Yes')
                ->where('addendum', '=', 'n')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('encounter_DOS', 'desc')
                ->get();
            $query2 = DB::table('t_messages')
                ->where('pid', '=', $pid)
                ->where('t_messages_signed', '=', 'Yes')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('t_messages_dos', 'desc')
                ->get();
            $query3 = DB::table('documents')
                ->where('pid', '=', $pid)
                ->orderBy('documents_date', 'desc')->get();
        } elseif ($type == 'queue') {
            $query1 = DB::table('hippa')
                ->join('encounters', 'hippa.eid', '=', 'encounters.eid')
                ->where('hippa.other_hippa_id', '=', $hippa_id)
                ->whereNotNull('hippa.eid')
                ->orderBy('encounters.encounter_DOS', 'desc')
                ->get();
            $query2 = DB::table('hippa')
                ->join('t_messages', 'hippa.t_messages_id', '=', 't_messages.t_messages_id')
                ->where('hippa.other_hippa_id', '=', $hippa_id)
                ->whereNotNull('hippa.t_messages_id')
                ->orderBy('t_messages.t_messages_dos', 'desc')
                ->get();
            $query3 = DB::table('hippa')
                ->join('documents', 'hippa.documents_id', '=', 'documents.documents_id')
                ->where('hippa.other_hippa_id', '=', $hippa_id)
                ->whereNotNull('hippa.documents_id')
                ->orderBy('documents.documents_date', 'desc')
                ->get();
        } else {
            $end = time();
            $start = $end - 31556926;
            $query1 = DB::table('encounters')->where('pid', '=', $pid)
                ->whereRaw("UNIX_TIMESTAMP(encounter_DOS) >= ?", [$start])
                ->whereRaw("UNIX_TIMESTAMP(encounter_DOS) <= ?", [$end])
                ->where('encounter_signed', '=', 'Yes')
                ->where('addendum', '=', 'n')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('encounter_DOS', 'desc')->get();
            $query2 = DB::table('t_messages')->where('pid', '=', $pid)
                ->whereRaw("UNIX_TIMESTAMP(t_messages_dos) >= ?", [$start])
                ->whereRaw("UNIX_TIMESTAMP(t_messages_dos) <= ?", [$end])
                ->where('t_messages_signed', '=', 'Yes')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('t_messages_dos', 'desc')
                ->get();
            $query3 = DB::table('documents')->where('pid', '=', $pid)
                ->whereRaw("UNIX_TIMESTAMP(documents_date) >= ?", [$start])
                ->whereRaw("UNIX_TIMESTAMP(documents_date) <= ?", [$end])
                ->orderBy('documents_date', 'desc')
                ->get();
        }
        if ($query1->count()) {
            $html .= '<table width="100%" style="font-size:1em"><tr><th style="background-color: gray;color: #FFFFFF;">ENCOUNTERS</th></tr></table>';
            foreach ($query1 as $row1) {
                $html .= $this->encounters_view($row1->eid, $pid, Session::get('practice_id'));
            }
        }
        if ($query2->count()) {
            $html .= '<pagebreak /><table width="100%" style="font-size:1em"><tr><th style="background-color: gray;color: #FFFFFF;">MESSAGES</th></tr></table>';
            foreach ($query2 as $row2) {
                $html .= $this->t_messages_view($row2->t_messages_id, true);
            }
        }
        $html .= '</body></html>';
        $this->generate_pdf($html, $file_path_enc, 'footerpdf', $header, '2');
        $pdf_arr[] = $file_path_enc;
        // Generate CCR
        $file_path_ccr = public_path() . '/temp/' . time() . '_' . $filename_string . '_ccr.pdf';
        $html_ccr = $this->page_intro('Continuity of Care Record', Session::get('practice_id'));
        $html_ccr .= $this->page_ccr($pid);
        $this->generate_pdf($html_ccr, $file_path_ccr, 'footerpdf', $header, '2');
        $pdf_arr[] = $file_path_ccr;
        // Gather documents
        if ($query3->count()) {
            foreach ($query3 as $row3) {
                $pdf_arr[] = $row3->documents_url;
            }
        }
        // Compile and save file
        $pdf = new Merger();
        foreach ($pdf_arr as $pdf_item) {
            if (file_exists($pdf_item)) {
                $file_parts = pathinfo($pdf_item);
                if ($file_parts['extension'] == 'pdf') {
                    $pdf->addFromFile($pdf_item );
                }
            }
        }
        $file_path = public_path() . '/temp/' . time() . '_' . $filename_string . '_' . $pid . '_printchart_final.pdf';
        $pdf->merge();
        $pdf->save($file_path);
        return $file_path;
    }

    protected function printimage($eid)
    {
        $query = DB::table('billing')->where('eid', '=', $eid)->get();
        $new_template = '';
        $b = $this->array_billing();
        foreach ($query as $result) {
            $template = File::get(resource_path() . '/billing.txt');
            foreach ($b as $k => $v) {
                if (isset($v['len'])) {
                    $search[] = $v['hcfa'];
                    $replace[] = $result->{$k};
                }
            }
            $new_template .= str_replace($search, $replace, $template);
        }
        $data['bill_submitted'] = 'Done';
        DB::table('encounters')->where('eid', '=', $eid)->update($data);
        $this->audit('Update');
        return $new_template;
    }

    protected function procedure_build($eid)
    {
        $pre_proc = [];
        $proc_row = DB::table('procedure')->where('eid', '=', $eid)->first();
        if ($proc_row) {
            $m = 0;
            $procedure_arr = $this->array_procedure();
            foreach ($procedure_arr as $procedure_k => $procedure_v) {
                if ($proc_row->{$procedure_k} !== '' && $proc_row->{$procedure_k} !== null) {
                    if ($procedure_k == 'proc_description') {
                        if ($this->yaml_check($proc_row->{$procedure_k})) {
                            $formatter = Formatter::make($proc_row->{$procedure_k}, Formatter::YAML);
                            $arr = $formatter->toArray();
                            foreach ($arr as $arr_item) {
                                $pre_proc[$m]['code'] = $arr_item['code'];
                                $pre_proc[$m]['type'] = $arr_item['type'];
                                $pre_proc[$m]['date'] = $proc_row->proc_date;
                            }
                        } else {
                            $pre_proc[$m]['code'] = $proc_row->proc_cpt;
                            $pre_proc[$m]['type'] = $proc_row->proc_type;
                            $pre_proc[$m]['date'] = $proc_row->proc_date;
                        }
                    }
                }
                $m++;
            }
        }
        return $pre_proc;
    }

    protected function progress_track($percent)
    {
        $track_data['template'] = $percent;
        DB::table('users')->where('id', '=', Session::get('user_id'))->update($track_data);
        return true;
    }

    protected function query_build($values)
    {
        $search_field_arr = [
            "" => trans('nosh.superquery_select'),
            "age" => trans('nosh.superquery_age'),
            "insurance" => trans('nosh.superquery_insurance'),
            "issue" => trans('nosh.superquery_issue'),
            "billing" => trans('nosh.superquery_billing'),
            "rxl_medication" => trans('nosh.superquery_rxl_medication'),
            "imm_immunization" => trans('nosh.superquery_imm_immunization'),
            "sup_supplement" => trans('nosh.superquery_sup_supplement'),
            "zip" => trans('nosh.superquery_zip'),
            "city" => trans('nosh.superquery_city'),
            "month" => trans('nosh.superquery_month'),
            "bp_systolic" => trans('nosh.superquery_bp_systolic'),
            "bp_diastolic" => trans('nosh.superquery_bp_diastolic'),
            "test_name" => trans('nosh.superquery_test_name'),
            "test_code" => trans('nosh.superquery_test_code'),
            "test_result" => trans('nosh.superquery_test_result')
        ];
        $search_join_arr = [
            'AND' => trans('nosh.and'),
            'OR' => trans('nosh.or')
        ];
        $age_op_arr = [
            '' => trans('nosh.select_operator'),
            'less than' => trans('nosh.less_than'),
            'equal' => trans('nosh.equal'),
            'greater than' => trans('nosh.greater_than'),
            'contains' => trans('nosh.contains'),
            'not equal' => trans('nosh.not_equal')
        ];
        $multi_op_arr = [
            '' => trans('nosh.select_operator'),
            'equal' => trans('nosh.equal'),
            'contains' => trans('nosh.contains'),
            'not equal' => trans('nosh.not_equal')
        ];
        $billing_op_arr = [
            '' => trans('nosh.select_operator'),
            'equal' => trans('nosh.equal'),
            'not equal' => trans('nosh.not_equal')
        ];
        $op_arr = [
            '' => trans('nosh.select_operator')
        ];
        if ($values[0]['search_field'] !== null) {
            if ($values[0]['search_field'] == 'age' || $values[0]['search_field'] == 'bp_systolic' || $values[0]['search_field'] == 'bp_diastolic' || $values[0]['search_field'] == 'test_result') {
                $op_arr = $age_op_arr;
            } elseif ($values[0]['search_field'] == 'billing' || $values[0]['search_field'] == 'month') {
                $op_arr = $billing_op_arr;
            } else {
                $op_arr = $multi_op_arr;
            }
        }
        $return = '<div id="super_query_div" class="col-md-8 col-md-offset-3" style="margin-bottom:10px;">';
        $return .= '<h5>' . trans('nosh.superquery_header') . '</h5><br><div class="input-group" id="search_div_1"><span id="search_add" class="input-group-addon"><i class="fa fa-plus fa-lg"></i></span><input type="hidden" name="search_join[]" id="search_join_first" value="start"></input>';
        $return .= Form::select('search_field[]', $search_field_arr, $values[0]['search_field'], ['class' => 'form-control search_field_class', 'id' => 'search_field_1']);
        $return .= Form::select('search_op[]', $op_arr, $values[0]['search_op'], ['class' => 'form-control search_op_class', 'id' => 'search_op_1']);
        $return .= Form::text('search_desc[]', $values[0]['search_desc'], ['class' => 'form-control search_desc_class', 'id' => 'search_desc_1']);
        $return .= '</div>';
        if (count($values) > 1) {
            for ($i = 1; $i < count($values); $i++) {
                $j = $i + 1;
                $op_arr = [
                    '' => trans('nosh.select_operator')
                ];
                if ($values[$i]['search_field'] !== null) {
                    if ($values[$i]['search_field'] == 'age') {
                        $op_arr = $age_op_arr;
                    } elseif ($values[$i]['search_field'] == 'billing' || $values[$i]['search_field'] == 'month') {
                        $op_arr = $billing_op_arr;
                    } else {
                        $op_arr = $multi_op_arr;
                    }
                }
                $return .= '<br><div class="input-group" id="search_div_' . $j . '"><span class="input-group-addon search_remove"><i class="fa fa-trash fa-lg"></i></span><input type="hidden" name="search_join[]" id="search_join_first" value="start"></input>';
                $return .= Form::select('search_join[]', $search_join_arr, $values[$i]['search_join'], ['class' => 'form-control search_join_class', 'id' => 'search_join_' . $j]);
                $return .= Form::select('search_field[]', $search_field_arr, $values[$i]['search_field'], ['class' => 'form-control search_field_class', 'id' => 'search_field_' . $j]);
                $return .= Form::select('search_op[]', $op_arr, $values[$i]['search_op'], ['class' => 'form-control search_op_class', 'id' => 'search_op_' . $j]);
                $return .= Form::text('search_desc[]', $values[$i]['search_desc'], ['class' => 'form-control search_desc_class', 'id' => 'search_desc_' . $j]);
                $return .= '</div>';
            }
        }
        $return .= '</div>';
        return $return;
    }

    protected function recursive_array_search($needle, $haystack)
    {
        foreach ($haystack as $key=>$value) {
            $current_key = $key;
            if ($needle === $value OR (is_array($value) && $this->recursive_array_search($needle, $value) !== FALSE)) {
                return $current_key;
            }
        }
        return false;
    }

    protected function remove_array_item($array, $item )
    {
        $index = array_search($item, $array);
        if ($index !== false) {
            unset( $array[$index] );
        }
        return $array;
    }

    protected function resource_composite_array($value)
    {
        $return_value = [
            'value' => $value,
            'parameter' => ''
        ];
        $value_composite = explode("\$", $value);
        if (count($value_composite) == 2) {
            if (substr($value_composite[0], -1) != "\\") {
                $return_value['value'] = $value_composite[1];
                $return_value['parameter'] = $value_composite[0];
            }
        }
        return $return_value;
    }

    protected function resource_detail($row, $resource_type)
    {
        $gender_arr = $this->array_gender();
        $gender_arr1 = $this->array_gender2();
        $route_arr = $this->array_route1();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $response['resourceType'] = $resource_type;
        $response['text']['status'] = 'generated';
        // Patient
        if ($resource_type == 'Patient') {
            $response['text']['div'] = '<div><table><tbody>';
            $response['identifier'][] = [
                'use' => 'usual',
                'label' => 'MRN',
                'system' => 'urn:oid:1.2.36.146.595.217.0.1',
                'value' => $row->pid,
                'period' => [
                    'start' => date('Y-m-d')
                ],
                'assigner' => [
                    'display' => $practice->practice_name
                ]
            ];
            $response['name'][] = [
                'use' => 'official',
                'family' => [$row->lastname],
                'given' => [$row->firstname],
            ];
            $response['text']['div'] .= '<tr><td><b>Name</b></td><td>' . $row->firstname . ' ' . $row->lastname .'</td></tr>';
            if ($row->nickname != '') {
                $response['name'][] = [
                    'use' => 'usual',
                    'given' => [$row->nickname]
                ];
            }
            if ($row->phone_home != '') {
                $response['telecom'][] = [
                    'use' => 'home',
                    'value' => $row->phone_home,
                    'system' => 'phone'
                ];
                $response['text']['div'] .= '<tr><td><b>Telcom, Home</b></td><td>' . $row->phone_home .'</td></tr>';
            }
            if ($row->phone_work != '') {
                $response['telecom'][] = [
                    'use' => 'work',
                    'value' => $row->phone_work,
                    'system' => 'phone'
                ];
                $response['text']['div'] .= '<tr><td><b>Telcom, Work</b></td><td>' . $row->phone_work .'</td></tr>';
            }
            $gender = $gender_arr[$row->sex];
            $gender_full = $gender_arr1[$row->sex];
            $response['gender']['coding'][] = [
                'system' => "http://hl7.org/fhir/v3/AdministrativeGender",
                'code' => $gender,
                'display' => $gender_full
            ];
            $response['text']['div'] .= '<tr><td><b>Gender</b></td><td>' . $gender_full .'</td></tr>';
            $birthdate = date('Y-m-d', $this->human_to_unix($row->DOB));
            $response['birthDate'] = $birthdate;
            $response['text']['div'] .= '<tr><td><b>Birthdate</b></td><td>' . $birthdate .'</td></tr>';
            $response['deceasedBoolean'] = false;
            $response['address'][] = [
                'use' => 'home',
                'line' => [$row->address],
                'city' => $row->city,
                'state' => $row->state,
                'zip' => $row->zip
            ];
            $response['text']['div'] .= '<tr><td><b>Address</b></td><td>' . $row->address . ', ' . $row->city . ', ' . $row->state . ', ' . $row->zip . '</td></tr>';
            $response['contact'][0]['relationship'][0]['coding'][0] = [
                'system' => "http://hl7.org/fhir/patient-contact-relationship",
                'code' => $row->guardian_relationship
            ];
            $response['contact'][0]['name'] = [
                'family' => [$row->guardian_lastname],
                'given' => [$row->guardian_firstname]
            ];
            $response['contact'][0]['telecom'][] = [
                'system' => 'phone',
                'value' => $row->guardian_phone_home
            ];
            $response['text']['div'] .= '<tr><td><b>Contacts</b></td><td>' . $row->guardian_firstname . ' ' . $row->guardian_lastname . ', Phone: ' . $row->guardian_phone_home . ', Relationship: ' . $row->guardian_relationship . '</td></tr>';
            $response['managingOrganization'] = [
                'reference' => 'Organization/1'
            ];
            if ($row->active == '0') {
                $response['active'] = false;
            } else {
                $response['active'] = true;
            }
            $response['text']['div'] .= '</tbody></table></div>';
        }

        // Condition
        if ($resource_type == 'Condition') {
            $patient = DB::table('demographics')->where('pid', '=', $row->pid)->first();
            $response['patient'] = [
                'reference' => 'Patient/' . $row->pid,
                'display' => $patient->firstname . ' ' . $patient->lastname
            ];
            if ($practice->icd == '9') {
                $condition_system = 'http://hl7.org/fhir/sid/icd-9';
            } else {
                $condition_system = 'http://hl7.org/fhir/sid/icd-10';
            }
            if (isset($row->eid)) {
                $response['encounter'] = [
                    'reference' => 'Encounter/' . $row->eid
                ];
                $response['id'] = 'eid_' . $row->eid;
                $provider = DB::table('users')->where('displayname', '=', $row->encounter_provider)->where('group_id', '=', '2')->first();
                $response['dateRecorded'] = date('Y-m-d', $this->human_to_unix($row->assessment_date));
                $i = 1;
                while ($i <= 12) {
                    $condition_row_array = (array) $row;
                    if ($condition_row_array['assessment_' . $i] != '') {
                        $code_array = explode(' [', $condition_row_array['assessment_' . $i]);
                        $response['code']['coding'][] = [
                            'system' => $condition_system,
                            'code' => str_replace(']', '', $code_array[1]),
                            'display' => $code_array[0]
                        ];
                        $response['text']['div'] = '<div>' . $condition_row_array['assessment_' . $i] . ', <a href="' . route('home') . '/fhir/Encounter/' . $row->eid .'">Encounter Assessment</a>, Date Active: ' . date('Y-m-d', $this->human_to_unix($row->assessment_date)) . '</div>';
                    }
                    $i++;
                }
                $response['category']['coding'][] = [
                    'system' => 'http://hl7.org/fhir/condition-category',
                    'code' => 'diagnosis',
                    'display' => 'Diagnosis'
                ];
            } else {
                $response['id'] = 'issue_id_' . $row->issue_id;
                $provider = DB::table('users')->where('displayname', '=', $row->issue_provider)->first();
                $response['dateRecorded'] = date('Y-m-d', $this->human_to_unix($row->issue_date_active));
                $response['onsetDateTime'] = date('Y-m-d', $this->human_to_unix($row->issue_date_active));
                $code_array = explode(' [', $row->issue);
                $response['code']['coding'][] = [
                    'system' => $condition_system,
                    'code' => str_replace(']', '', $code_array[1]),
                    'display' => $code_array[0]
                ];
                $response['text']['div'] = '<div>' . $row->issue . ', Problem, Date Active: ' . date('Y-m-d', $this->human_to_unix($row->issue_date_active)) . '</div>';
                $response['category']['coding'][] = [
                    'system' => 'http://snomed.info/sct',
                    'code' => '55607006',
                    'display' => 'Problem'
                ];
            }
            $response['asserter'] = [
                'reference' => 'Practitioner/' . $provider->id,
                'display' => $provider->displayname
            ];
            $response['status'] = 'confirmed';
            // missing severity
            // missing evidence
            // missing location
            // missing relatedItem
        }

        $med_freq_array_1 = ["once daily", "every 24 hours", "once a day", "1 time a day", "QD"];
        $med_freq_array_2 = ["twice daily", "every 12 hours", "two times a day", "2 times a day", "BID", "q12h", "Q12h"];
        $med_freq_array_3 = ["three times daily", "every 8 hours", "three times a day", "3 times daily", "3 times a day", "TID", "q8h", "Q8h"];
        $med_freq_array_4 = ["every six hours", "every 6 hours", "four times daily", "4 times a day", "four times a day", "4 times daily", "QID", "q6h", "Q6h"];
        $med_freq_array_5 = ["every four hours", "every 4 hours", "six times a day", "6 times a day", "six times daily", "6 times daily", "q4h", "Q4h"];
        $med_freq_array_6 = ["every three hours", "every 3 hours", "eight times a day", "8 times a day", "eight times daily", "8 times daily", "q3h", "Q3h"];
        $med_freq_array_7 = ["every two hours", "every 2 hours", "twelve times a day", "12 times a day", "twelve times daily", "12 times daily", "q2h", "Q2h"];
        $med_freq_array_8 = ["every hour", "every 1 hour", "every one hour", "q1h", "Q1h"];
        // MedicationStatement
        if ($resource_type == 'MedicationStatement') {
            $response['id'] = $row->rxl_id;
            $patient = DB::table('demographics')->where('pid', '=', $row->pid)->first();
            $response['patient'] = [
                'reference' => 'Patient/' . $row->pid,
                'display' => $patient->firstname . ' ' . $patient->lastname
            ];
            $provider = DB::table('providers')->where('id', '=', $row->id)->first();
            if ($provider) {
                $response['recorder'] = [
                    'reference' => 'Practitioner/' . $provider->id,
                    'display' => $row->rxl_provider
                ];
            }
            $response['dateAsserted'] = date('Y-m-d');
            $response['effectiveDateTime'] = date('Y-m-d', $this->human_to_unix($row->rxl_date_active));
            $medication_reference = $this->resource_medication_reference($row->rxl_ndcid);
            if (! empty($medication_reference)) {
                $response['medicationReference'] = $medication_reference;
            }
            $reason_snomed = $this->snomed($row->rxl_reason, true);
            if (! empty($reason_snomed)) {
                $response['reasonCodeableConcept'] = [
                    'coding' => [
                        '0' => [
                            'system' => 'http://snomed.info/sct',
                            'code' => $reason_snomed['code'],
                            'display' => $reason_snomed['description']
                        ]
                    ]
                ];
            }
            $med_prn_array = ["as needed", "PRN"];
            if ($row->rxl_sig == '') {
                $rx_text = $row->rxl_medication . ' ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $row->rxl_instructions . ' for ' . $row->rxl_reason;
                $response['text']['div'] = '<div>' . $rx_text;
                $response['text']['div'] .= ', Date Active: ' . date("Y-m-d", $this->human_to_unix($row->rxl_date_active)) . '</div>';
                $dosage_text = $row->rxl_instructions . ' for ' . $row->rxl_reason;
                $asNeededBoolean = false;
                if (in_array($med_row->rxl_instructions, $med_prn_array)) {
                    $asNeededBoolean = true;
                }
                $dosage_array = [
                    'text' => $dosage_text,
                    'asNeededBoolean' => $asNeededBoolean,
                    'quantityQuantity' => [
                        'value' => $row->rxl_quantity
                    ]
                ];
            } else {
                $rx_text = $row->rxl_medication . ' ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $row->rxl_sig . ', ' . $row->rxl_route . ', ' . $row->rxl_frequency . ' for ' . $row->rxl_reason;
                $response['text']['div'] = '<div>' . $rx_text;
                $response['text']['div'] .= ', Date Active: ' . date("Y-m-d", $this->human_to_unix($row->rxl_date_active)) . '</div>';
                $dosage_text = $row->rxl_sig . ', ' . $row->rxl_route . ', ' . $row->rxl_frequency . ' for ' . $row->rxl_reason;
                $med_dosage_parts = explode(" ", $row->rxl_sig);
                $med_dosage = $med_dosage_parts[0];
                if (! empty($med_dosage_parts)) {
                    $med_dosage_unit = $med_dosage_parts[1];
                } else {
                    $med_dosage_unit = '';
                }
                $med_code = $route_arr[$row->rxl_route][0];
                $med_code_description = $route_arr[$row->rxl_route][1];
                $med_period = '';
                if (in_array($row->rxl_frequency, $med_freq_array_1)) {
                    $med_period = "24";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_2)) {
                    $med_period = "12";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_3)) {
                    $med_period = "8";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_4)) {
                    $med_period = "6";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_5)) {
                    $med_period = "4";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_6)) {
                    $med_period = "3";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_7)) {
                    $med_period = "2";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_8)) {
                    $med_period = "1";
                }
                $asNeededBoolean = false;
                if (in_array($row->rxl_frequency, $med_prn_array)) {
                    $asNeededBoolean = true;
                }
                $dosage_array = [
                    'text' => $dosage_text,
                    'asNeededBoolean' => $asNeededBoolean,
                    'quantityQuantity' => [
                        'value' => $row->rxl_quantity
                    ]
                ];
                if ($med_period != '') {
                    $dosage_array['timing'] = [
                        'repeat' => [
                            'frequency' => $med_period,
                            'period' => '1',
                            'periodUnits' => 'd'
                        ]
                    ];
                }
                if ($med_code != '' && $med_code_description != '') {
                    $dosage_array['route'] = [
                        'coding' => [
                            '0' => [
                                'system' => 'http://snomed.info/sct',
                                'code' => $med_code,
                                'display' => $med_code_description
                            ]
                        ]
                    ];
                }
            }
            $response['medicationCodeableConcept']['text'] = $rx_text;
            if ($row->rxl_date_inactive == '0000-00-00 00:00:00' && $row->rxl_date_old == '0000-00-00 00:00:00') {
                $response['status'] = 'active';
                $response['wasNotTaken'] = false;
            }
            if ($row->rxl_date_inactive != '0000-00-00 00:00:00') {
                $response['status'] = 'completed';
                $response['wasNotTaken'] = true;
            }
            $response['dosage'][] = $dosage_array;
        }

        // MedicationRequest
        if ($resource_type == 'MedicationRequest') {
            $response['id'] = $row->rxl_id;
            $patient = DB::table('demographics')->where('pid', '=', $row->pid)->first();
            $response['intent'] = 'order';
            $response['priority'] = 'routine';
            $response['subject'] = [
                'reference' => 'Patient/' . $row->pid,
                'display' => $patient->firstname . ' ' . $patient->lastname
            ];
            $provider = DB::table('users')->where('id', '=', $row->id)->first();
            if ($provider) {
                $response['requester']['agent'] = [
                    'reference' => 'Practitioner/' . $provider->id,
                    'display' => $row->rxl_provider
                ];
                $provider_info = DB::table('providers')->where('id', '=', $provider->id)->first();
                $response['requester']['onBehalfOf'] = [
                    'reference' => 'Organization/' . $provider_info->practice_id
                ];
            }
            $response['dateWritten'] = date('Y-m-d', $this->human_to_unix($row->rxl_date_active));
            $medication_reference = $this->resource_medication_reference($row->rxl_ndcid);
            if (! empty($medication_reference)) {
                $response['medicationReference'] = $medication_reference;
            }
            $reason_snomed = $this->snomed($row->rxl_reason, true);
            if (! empty($reason_snomed)) {
                $response['reasonCodeableConcept'] = [
                    'coding' => [
                        '0' => [
                            'system' => 'http://snomed.info/sct',
                            'code' => $reason_snomed['code'],
                            'display' => $reason_snomed['description']
                        ]
                    ]
                ];
            }
            $med_prn_array = ["as needed", "PRN"];
            if ($row->rxl_sig == '') {
                $response['text']['div'] = '<div>' . $row->rxl_medication . ' ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $row->rxl_instructions . ' for ' . $row->rxl_reason . '</div>';
                $dosage_text = $row->rxl_instructions . ' for ' . $row->rxl_reason;
                $asNeededBoolean = false;
                if (in_array($row->rxl_instructions, $med_prn_array)) {
                    $asNeededBoolean = true;
                }
                $dosage_array = [
                    'text' => $dosage_text,
                    'asNeededBoolean' => $asNeededBoolean,
                    'quantityQuantity' => [
                        'value' => $row->rxl_quantity
                    ]
                ];
            } else {
                $response['text']['div'] = '<div>' . $row->rxl_medication . ' ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $row->rxl_sig . ', ' . $row->rxl_route . ', ' . $row->rxl_frequency . ' for ' . $row->rxl_reason . '</div>';
                $dosage_text = $row->rxl_sig . ', ' . $row->rxl_route . ', ' . $row->rxl_frequency . ' for ' . $row->rxl_reason;
                $med_dosage_parts = explode(" ", $row->rxl_sig);
                $med_dosage = $med_dosage_parts[0];
                if (count($med_dosage_parts) > 1) {
                    $med_dosage_unit = $med_dosage_parts[1];
                } else {
                    $med_dosage_unit = '';
                }
                $med_code = $route_arr[$row->rxl_route][0];
                $med_code_description = $route_arr[$row->rxl_route][1];
                $med_period = '';
                if (in_array($row->rxl_frequency, $med_freq_array_1)) {
                    $med_period = "24";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_2)) {
                    $med_period = "12";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_3)) {
                    $med_period = "8";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_4)) {
                    $med_period = "6";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_5)) {
                    $med_period = "4";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_6)) {
                    $med_period = "3";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_7)) {
                    $med_period = "2";
                }
                if (in_array($row->rxl_frequency, $med_freq_array_8)) {
                    $med_period = "1";
                }
                $asNeededBoolean = false;
                if (in_array($row->rxl_frequency, $med_prn_array)) {
                    $asNeededBoolean = true;
                }
                $dosage_array = [
                    'text' => $dosage_text,
                    'asNeededBoolean' => $asNeededBoolean
                ];
                if ($med_period != '') {
                    $dosage_array['timing'] = [
                        'repeat' => [
                            'frequency' => $med_period,
                            'period' => '1',
                            'periodUnit' => 'd'
                        ]
                    ];
                }
                if ($med_code != '' && $med_code_description != '') {
                    $dosage_array['route'] = [
                        'coding' => [
                            '0' => [
                                'system' => 'http://ncimeta.nci.nih.gov',
                                'code' => $med_code,
                                'display' => $med_code_description
                            ]
                        ]
                    ];
                }
            }
            $dosage_array['doseQuantity'] = [
                'value' => $row->rxl_dosage,
                'unit' => $row->rxl_dosage_unit
            ];
            if ($row->rxl_date_inactive == '0000-00-00 00:00:00' && $row->rxl_date_old == '0000-00-00 00:00:00') {
                $response['status'] = 'active';
            }
            if ($row->rxl_date_inactive != '0000-00-00 00:00:00') {
                $response['status'] = 'completed';
            }
            $response['dosageInstruction'][] = $dosage_array;
            $temp_date = new Date($this->human_to_unix($row->rxl_date_active));
            $temp_quantity = explode(' ', $row->rxl_quantity);
            $quantity_arr['value'] = $temp_quantity[0];
            if (count($temp_quantity) > 1) {
                $quantity_arr['unit'] = $temp_quantity[1];
            }
            $response['dispenseRequest'] = [
                'medicationReference' => $medication_reference,
                'validityPeriod' => [
                    'start' => date('Y-m-d', $this->human_to_unix($row->rxl_date_active)),
                    'end' => $temp_date->addYear()->format('Y-m-d')
                ],
                'numberOfRepeatsAllowed' => $row->rxl_refill,
                'quantity' => $quantity_arr,
                'expectedSupplyDuration' => [
                    'value' => $row->rxl_days,
                    'unit' => 'days',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'd'
                ]
            ];
            $sub_bool = true;
            if ($row->rxl_daw !== '' && $row->rxl_daw !== null) {
                $sub_bool = false;
            }
            $response['substitution']['allowed'] = $sub_bool;
        }

        // AllergyIntolerance
        if ($resource_type == 'AllergyIntolerance') {
            $response['id'] = $row->allergies_id;
            $patient = DB::table('demographics')->where('pid', '=', $row->pid)->first();
            $response['patient'] = [
                'reference' => 'Patient/' . $row->pid,
                'display' => $patient->firstname . ' ' . $patient->lastname
            ];
            $provider = DB::table('users')->where('provider_id', '=', $row->provider_id)->first();
            if ($provider) {
                $response['recorder'] = [
                    'reference' => 'Practitioner/' . $provider->id,
                    'display' => $row->allergies_provider
                ];
            }
            $response['recordedDate'] = date('Y-m-d', $this->human_to_unix($row->allergies_date_active));
            $response['substance']['text'] = $row->allergies_med;
            if ($row->meds_ndcid !== '' && $row->meds_ndcid !== null) {
                $response['substance']['coding'][] = [
                    'system' => 'http://www.nlm.nih.gov/research/umls/rxnorm',
                    'code' => $row->meds_ndcid,
                    'display' => $row->allergies_med
                ];
            } else {
                $rxnorm = $this->rxnorm_search($row->allergies_med);
                if ($rxnorm !== '') {
                    $response['substance']['coding'][] = [
                        'system' => 'http://www.nlm.nih.gov/research/umls/rxnorm',
                        'code' => $rxnorm,
                        'display' => $row->allergies_med
                    ];
                }
            }
            $response['text']['div'] = '<div>' . $row->allergies_med . ', Reaction: ' . $row->allergies_reaction . ', Severeity ' . $row->allergies_severity . ', Date Active: ' . date('Y-m-d', $this->human_to_unix($row->allergies_date_active)) . '</div>';
            $response['reaction'][] = [
                'manifestation' => [
                    '0' => [
                        'coding' => [
                            '0' => [
                                'system' => 'http://snomed.info/sct',
                                'code' => '', //need code
                                'display' => '' //need definition
                            ]
                        ],
                        'text' => $row->allergies_reaction
                    ]
                ]
            ];
        }
        // Immunization
        if ($resource_type == 'Immunization') {
            $response['id'] = $row->imm_id;
            $patient = DB::table('demographics')->where('pid', '=', $row->pid)->first();
            $response['patient'] = [
                'reference' => 'Patient/' . $row->pid,
                'display' => $patient->firstname . ' ' . $patient->lastname
            ];
            $provider = DB::table('users')->where('displayname', '=', $row->imm_provider)->first();
            if ($provider) {
                $response['requester'] = [
                    'reference' => 'Practitioner/' . $provider->id,
                    'display' => $row->imm_provider
                ];
            }
            $response['vaccineCode']['text'] = $row->imm_immunization;
            if ($row->imm_cvxcode != '') {
                $response['vaccineCode']['coding'][] = [
                    'system' => 'http://hl7.org/fhir/sid/cvx',
                    'code' => $row->imm_cvxcode,
                    'display' => $row->imm_immunization
                ];
            }
            $response['date'] = date('Y-m-d', $this->human_to_unix($row->imm_date));
            $response['status'] = 'completed';
            $response['wasNotGiven'] = false;
            if ($row->imm_lot != '') {
                $response['lotNumber'] = $row->imm_lot;
            }
            if ($row->imm_expiration != '') {
                $response['expirationDate'] = date('Y-m-d', $this->human_to_unix($row->imm_expiration));
            }
            if ($row->imm_sequence != '') {
                $response['vaccinationProtocol'][] = [
                    'doseSequence' => $row->imm_sequence
                ];
            }
            if ($row->imm_sequence == '1') {
                $sequence = ', first';
            }
            if ($row->imm_sequence == '2') {
                $sequence = ', second';
            }
            if ($row->imm_sequence == '3') {
                $sequence = ', third';
            }
            if ($row->imm_sequence == '4') {
                $sequence = ', fourth';
            }
            if ($row->imm_sequence == '5') {
                $sequence = ', fifth';
            }
            $response['text']['div'] = '<div>' . $row->imm_immunization . $sequence . ', Given: ' . date('Y-m-d', $this->human_to_unix($row->imm_date)) . '</div>';
        }
        return $response;
    }

    protected function resource_medication_reference($ndcid)
    {
        $return = [];
        $url = 'https://rxnav.nlm.nih.gov/REST/rxcui.json?idtype=NDC&id=' . $ndcid;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 15);
        $json = curl_exec($ch);
        curl_close($ch);
        $rxnorm = json_decode($json, true);
        if (isset($rxnorm['idGroup']['rxnormId'][0])) {
            $url1 = 'https://rxnav.nlm.nih.gov/REST/rxcui/' . $rxnorm['idGroup']['rxnormId'][0] . '/properties.json';
            $ch1 = curl_init();
            curl_setopt($ch1,CURLOPT_URL, $url1);
            curl_setopt($ch1,CURLOPT_FAILONERROR,1);
            curl_setopt($ch1,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch1,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch1,CURLOPT_TIMEOUT, 15);
            $json1 = curl_exec($ch1);
            curl_close($ch1);
            $rxnorm1 = json_decode($json1, true);
            $med_name = $rxnorm1['properties']['name'];
            $return = [
                'reference' => 'Medication/' . $ndcid,
                'display' => $rxnorm1['properties']['name']
            ];
        }
        return $return;
    }

    protected function resource_query_build($query, $table_key, $key1, $comparison, $value1, $or, $resource, $table)
    {
        $proceed = false;
        // check if resource is a condition and clean up identifier values if present
        if ($resource == 'Condition') {
            if ($key1 == 'identifier') {
                if (strpos($value1, 'issue_id_') >= 0 && $table == 'issues') {
                    $value1 = str_replace('issue_id_', '', $value1);
                    $proceed = true;
                }
                if (strpos($value1, 'eid_') >= 0 && $table == 'assessment') {
                    $value1 = str_replace('eid_', '', $value1);
                    $proceed = true;
                }
            }
        } else {
            $proceed = true;
        }
        // check if resource is medication statement and clean up references
        if ($resource == 'MedicationStatement') {
            if ($key1 == 'status') {
                if ($value1 == 'active') {
                    $query->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00');
                }
                if ($value1 == 'completed') {
                    $query->where('rxl_date_inactive', '!=', '0000-00-00 00:00:00');
                }
                if ($value1 == 'entered-in-error') {
                    //not functional
                }
                if ($value1 == 'intended') {
                    //not functional
                }
            }
        }
        if ($resource == 'MedicationRequest') {
            if ($key1 == 'status') {
                if ($value1 == 'active') {
                    $query->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->where('prescription', '=', 'pending');
                }
                if ($value1 == 'on-hold') {
                    //not functional
                }
                if ($value1 == 'completed') {
                    //not functional
                }
                if ($value1 == 'entered-in-error') {
                    //not functional
                }
                if ($value1 == 'stopped') {
                    //not functional
                }
                if ($value1 == 'draft') {
                    //not functional
                }
            }
        }
        if ($proceed == true) {
            // check if value is a date
            $unixdate = strtotime($value1);
            if ($unixdate) {
                if ($key1 == 'birthDate') {
                    $value1 = date('Y-m-d', $unixdate);
                }
            }
            // check if value is boolean
            if ($value1 == 'true') {
                $value1 = '1';
            }
            if ($value1 == 'false') {
                $value1 = '0';
            }
            if (isset($table_key[$key1]) && is_array($table_key[$key1])) {
                if ($or == false) {
                    $query->where(function($query_array1) use ($table_key, $key1, $value1) {
                        $a = 0;
                        foreach ($table_key[$key1] as $key_name) {
                            if ($a == 0) {
                                $query_array1->where($key_name, 'LIKE', "%$value1%");
                            } else {
                                $query_array1->orWhere($key_name, 'LIKE', "%$value1%");
                            }
                            $a++;
                        }
                    });
                } else {
                    $query->orWhere(function($query_array1) use ($table_key, $key1, $value1) {
                        $a = 0;
                        foreach ($table_key[$key1] as $key_name) {
                            if ($a == 0) {
                                $query_array1->where($key_name, 'LIKE', "%$value1%");
                            } else {
                                $query_array1->orWhere($key_name, 'LIKE', "%$value1%");
                            }
                            $a++;
                        }
                    });
                }
            } else {
                if ($key1 == 'subject') {
                    if ($resource == 'Patient') {
                        $key_name = 'pid';
                    } else {
                        $key_name = $table_key[$key1];
                    }
                } else {
                    $key_name = $table_key[$key1];
                }
                if ($or == false) {
                    if ($comparison == '=') {
                        $query->where($key_name, 'LIKE', "%$value1%");
                    } else {
                        $query->where($key_name, $comparison, $value1);
                    }
                } else {
                    if ($comparison == '=') {
                        $query->orWhere($key_name, 'LIKE', "%$value1%");
                    } else {
                        $query->orWhere($key_name, $comparison, $value1);
                    }
                }
            }
        }
        return $query;
    }

    // $data is Input array, $table_key is key translation for associated table, $is_date is boolean
    protected function resource_translation($data, $table, $table_primary_key, $table_key)
    {
        $i = 0;
        $parameters = [];
        foreach ($data as $key => $value) {
            if ($key != 'page' && $key != 'sort') {
                $exact = false;
                $missing = false;
                $text = false;
                $resource = '';
                $comparison = '=';
                $namespace = '';
                $unit = '';
                $either = false;
                $key_modifier = explode(':', $key);
                if (count($key_modifier) == 2) {
                    $key1 = $key_modifier[0];
                    if ($key_modifier[1] == 'exact' || $key_modifier[1] == 'missing' || $key_modifier[1] == 'text') {
                        if ($key_modifier[1] == 'exact') {
                            $exact = true;
                        }
                        if ($key_modifier[1] == 'missing') {
                            $missing = true;
                        }
                        if ($key_modifier[1] == 'text') {
                            $text = true;
                        }
                    } else {
                        $resource = $key_modifier[1];
                    }
                } else {
                    $key1 = $key;
                }
                $parameters[$i]['parameter'] = $key1;
                $parameters[$i]['value'] = $value;
                $char1 = substr($value, 0, 1);
                $char2 = substr($value, 0, 2);
                if ($char1 == '<' || $char1 == '>' || $char1 == '~') {
                    $comparison = $char1;
                    $value = substr($value, 1);
                }
                if ($char1 == '~') {
                    $comparison = 'LIKE';
                    $like_value = substr($value, 1);
                    $value = "%$like_value%";
                }
                if ($char2 == '<=' || $char2 == '>=') {
                    $comparison = $char2;
                    $value = substr($value, 2);
                }
                $value_token = explode('|', $value);
                if (count($value_token) == 2) {
                    if (substr($value_token[0], -1) == "\\") {
                        $value1 = $value;
                    } else {
                        $value1 = $value_token[1];
                        $namespace = $value_token[0];
                    }
                } elseif (count($value_token) == 3) {
                    if (substr($value_token[0], -1) == "\\" || substr($value_token[1], -1) == "\\") {
                        if (substr($value_token[0], -1) == "\\" && substr($value_token[1], -1) == "\\") {
                            $value1 = str_replace("\\|", "|", $value);
                        } else {
                            if (substr($value_token[0], -1) == "\\") {
                                $value1 = $value_token[2];
                                $namespace = $value_token[0] . "|" . $value_token[1];
                            } else {
                                $value1 = $value_token[1] . "|" . $value_token[2];
                                $namespace = $value_token[0];
                            }
                        }
                    } else {
                        $value1 = $value_token[0];
                        $namespace = $value_token[1];
                        $unit = $value_token[2];
                    }
                } else {
                    $value1 = $value;
                }
                if ($i == 0) {
                    $query = DB::table($table);
                } else {
                    $this->resource_query_build($query, $table_key, $key1, $comparison, $value1, false, $resource, $table);
                }
                $value_composite1 = explode(",", $value1);
                if (count($value_composite1) > 1) {
                    $code = array();
                    $temp_value = '';
                    $j = 0;
                    foreach ($value_composite1 as $value2) {
                        if (substr($value2, -1) == "\\") {
                            if ($temp_value != '') {
                                $value3 = $this->resource_composite_array($value2);
                                if ($value3['parameter'] == '') {
                                    $temp_value .= ',' . $value2;
                                } else {
                                    $temp_value .= ',' . $value3['value'];
                                }
                            } else {
                                $value3 = $this->resource_composite_array($value2);
                                if ($value3['parameter'] == '') {
                                    $temp_value = $value2;
                                } else {
                                    $temp_value = $value3['value'];
                                }
                            }
                        } else {
                            if ($temp_value == '') {
                                $value3 = $this->resource_composite_array($value2);
                                if ($value3['parameter'] == '') {
                                    $code[$j] = [
                                        'key' => $key1,
                                        'comparison' => $comparison,
                                        'value' => $value2
                                    ];

                                } else {
                                    $code[$j] = [
                                        'key' => $key1,
                                        'comparison' => $comparison,
                                        'value' => $value3['value']
                                    ];
                                }
                            } else {
                                $value3 = $this->resource_composite_array($value2);
                                if ($value3['parameter'] == '') {
                                    $temp_value .= ',' . $value2;
                                } else {
                                    $temp_value .= ',' . $value3['value'];
                                }
                                $code[$j] = [
                                    'key' => $key1,
                                    'comparison' => $comparison,
                                    'value' => $temp_value
                                ];
                                $temp_value = '';
                            }
                            $j++;
                        }
                    }
                    if (isset($code[0])) {
                        $query->where(function($query_array1) use ($code, $table_key, $resource, $table) {
                            $k = 0;
                            foreach ($code as $line) {
                                if ($k == 0) {
                                    $this->resource_query_build($query_array1, $table_key, $line['key'], $line['comparison'], $line['value'], false, $resource, $table);
                                } else {
                                    $this->resource_query_build($query_array1, $table_key, $line['key'], $line['comparison'], $line['value'], true, $resource, $table);
                                }
                                $k++;
                            }
                        });
                    } else {
                        $this->resource_query_build($query, $table_key, $key1, $comparison, $temp_value, false, $resource, $table);
                    }
                } else {
                    $this->resource_query_build($query, $table_key, $key1, $comparison, $value1, false, $resource, $table);
                }
                $i++;
            }
        }
        $query->select($table_primary_key);
        $result = $query->get();
        if ($result) {
            $return['response'] = true;
            $url_array = explode('/', Request::url());
            $return['parameters'][] = [
                'url' => array_splice($url_array, -1, 1, 'query#_type'),
                'valueString' => strtolower(end($url_array))
            ];
            $return['total'] = count($result);
            foreach ($parameters as $parameter) {
                $new_url = array_splice($url_array, -1, 1, 'query#' . $parameter['parameter']);
                $return['parameters'][] = [
                    'url' => $new_url,
                    'valueString' => $parameter['value']
                ];
            }
            $return['data'] = array();
            foreach ($result as $result_row) {
                $result_row_array = (array) $result_row;
                $return['data'][] = reset($result_row_array);
            }
        } else {
            $return['response'] = false;
        }
        return $return;
    }

    /**
    * Result build
    * @param array  $list_array - ['label' => '', 'pid' => 'Patient ID', 'edit' => 'URL', 'delete' => 'URL', 'reactivate' => 'URL, 'inactivate' => 'URL', 'origin' => 'previous URL', 'active' => boolean, 'danger' => boolean, 'label_class' => 'class', 'label_data' => 'label information']
    * @param int $id - Item key in database
    * @return Response
    */
    protected function result_build($list_array, $id, $nosearch=false, $viewfirst=false)
    {
        $return = '';
        if ($nosearch == false) {
            $return .= '<form role="form"><div class="form-group"><input class="form-control" id="searchinput" type="search" placeholder="Filter Results..." /></div>';
        }
        $return .= '<ul class="list-group searchlist" id="' . $id . '">';
        if (is_array($list_array)) {
            foreach ($list_array as $item) {
                $return .= '<li class="list-group-item container-fluid';
                if (isset($item['active'])) {
                    $return .= ' list-group-item-success';
                }
                if (isset($item['danger'])) {
                    $return .= ' list-group-item-danger';
                }
                if (isset($item['label_class'])) {
                    $return .= ' ' . $item['label_class'];
                } else {
                    $return .= ' nosh-result-list';
                }
                $return .= '"';
                if (isset($item['label_data'])) {
                    $return .= ' nosh-data="' . $item['label_data'] . '"';
                }
                if (isset($item['label_data_arr'])) {
                    foreach ($item['label_data_arr'] as $data_item_k => $data_item_v) {
                        $return .= ' ' . $data_item_k . '="' . $data_item_v . '"';
                    }
                }
                $return .= '><span>' . $item['label'] . '</span><span class="pull-right">';
                if ($viewfirst == true) {
                    if (isset($item['view'])) {
                        $return .= '<a href="' . $item['view'] . '" class="btn fa-btn" data-toggle="tooltip" title="View"><i class="fa fa-eye fa-lg"></i></a>';
                    }
                    if (isset($item['edit'])) {
                        $return .= '<a href="' . $item['edit'] . '" class="btn fa-btn" data-toggle="tooltip" title="Edit"><i class="fa fa-pencil fa-lg"></i></a>';
                    }
                } else {
                    if (isset($item['edit'])) {
                        $return .= '<a href="' . $item['edit'] . '" class="btn fa-btn" data-toggle="tooltip" title="Edit"><i class="fa fa-pencil fa-lg"></i></a>';
                    }
                    if (isset($item['view'])) {
                        $return .= '<a href="' . $item['view'] . '" class="btn fa-btn" data-toggle="tooltip" title="View"><i class="fa fa-eye fa-lg"></i></a>';
                    }
                }
                if (isset($item['refill'])) {
                    $return .= '<a href="' . $item['refill'] . '" class="btn fa-btn" data-toggle="tooltip" title="Refill"><i class="fa fa-repeat fa-lg"></i></a>';
                }
                if (isset($item['eie'])) {
                    $return .= '<a href="' . $item['eie'] . '" class="btn fa-btn" data-toggle="tooltip" title="Entered in Error/Undo"><i class="fa fa-undo fa-lg"></i></a>';
                }
                if (isset($item['reply'])) {
                    $return .= '<a href="' . $item['reply'] . '" class="btn fa-btn" data-toggle="tooltip" title="Reply to Patient"><i class="fa fa-reply fa-lg"></i></a>';
                }
                if (isset($item['complete'])) {
                    $return .= '<a href="' . $item['complete'] . '" class="btn fa-btn" data-toggle="tooltip" title="Complete"><i class="fa fa-check fa-lg"></i></a>';
                }
                if (isset($item['reactivate'])) {
                    $return .= '<a href="' . $item['reactivate'] . '" class="btn fa-btn" data-toggle="tooltip" title="Reactivate"><i class="fa fa-plus-circle fa-lg"></i></a>';
                }
                if (isset($item['inactivate'])) {
                    $return .= '<a href="' . $item['inactivate'] . '" class="btn fa-btn" data-toggle="tooltip" title="Inactivate"><i class="fa fa-minus-circle fa-lg"></i></a>';
                }
                if (isset($item['delete'])) {
                    $return .= '<a href="' . $item['delete'] . '" class="btn fa-btn nosh-delete" data-toggle="tooltip" title="Delete"><i class="fa fa-trash fa-lg"></i></a>';
                }
                if (isset($item['move_mh'])) {
                    $return .= '<a href="' . $item['move_mh'] . '" class="btn fa-btn" data-toggle="tooltip" title="Move to Medical History"><i class="fa fa-share-square fa-lg"></i></a>';
                }
                if (isset($item['move_sh'])) {
                    $return .= '<a href="' . $item['move_sh'] . '" class="btn fa-btn" data-toggle="tooltip" title="Move to Surgical History"><i class="fa fa-share-square fa-lg" style="color:red"></i></a>';
                }
                if (isset($item['move_pl'])) {
                    $return .= '<a href="' . $item['move_pl'] . '" class="btn fa-btn" data-toggle="tooltip" title="Move to Problem List"><i class="fa fa-share-square fa-lg" style="color:green"></i></a>';
                }
                if (isset($item['move_down'])) {
                    $return .= '<a href="' . $item['move_down'] . '" class="btn fa-btn" data-toggle="tooltip" title="Move Down"><i class="fa fa-chevron-down fa-lg"></i></a>';
                }
                if (isset($item['move_up'])) {
                    $return .= '<a href="' . $item['move_up'] . '" class="btn fa-btn" data-toggle="tooltip" title="Move Up"><i class="fa fa-chevron-up fa-lg"></i></a>';
                }
                if (isset($item['encounter'])) {
                    $return .= '<a href="' . $item['encounter'] . '" class="btn fa-btn" data-toggle="tooltip" title="Copy To Encounter"><i class="fa fa-clone fa-lg"></i></a>';
                }
                if (isset($item['reset'])) {
                    $return .= '<a href="' . $item['reset'] . '" class="btn fa-btn" data-toggle="tooltip" title="Reset Password"><i class="fa fa-repeat fa-lg"></i></a>';
                }
                if (isset($item['jump'])) {
                    $return .= '<a href="' . $item['jump'] . '" target="_blank" class="btn fa-btn" data-toggle="tooltip" title="Open Chart"><i class="fa fa-hand-o-right fa-lg"></i></a>';
                }
				// GYN 20181007: Add Assessment Copy to Problem List
				if (isset($item['problem_list'])) {
                    $return .= '<a href="' . $item['problem_list'] . '" class="btn fa-btn" data-toggle="tooltip" title="Copy to Problem List"><i class="fa fa-share-square fa-lg" style="color:green"></i></a>';
				}
                $return .= '</span></li>';
            }
        }
        $return .= '</ul>';
        if ($nosearch == false) {
            $return .= '</form>';
        }
        return $return;
    }

    protected function rpHash($value)
	{
		$hash = 5381;
		$value = strtoupper($value);
		if (PHP_INT_SIZE == 4) {
			for($i = 0; $i < strlen($value); $i++) {
				$hash = (($hash << 5) + $hash) + ord(substr($value, $i));
			}
		} else {
			for($i = 0; $i < strlen($value); $i++) {
				$hash = ($this->rp_leftShift32($hash, 5) + $hash) + ord(substr($value, $i));
			}
		}
		return $hash;
	}

	protected function rp_leftShift32($number, $steps)
	{
		$binary = decbin($number);
		$binary = str_pad($binary, 32, "0", STR_PAD_LEFT);
		$binary = $binary.str_repeat("0", $steps);
		$binary = substr($binary, strlen($binary) - 32);
		return ($binary{0} == "0" ? bindec($binary) : -(pow(2, 31) - bindec(substr($binary, 1))));
	}

    protected function rxnorm_search($item)
    {
        $url = 'http://rxnav.nlm.nih.gov/REST/rxcui.json?name=' . strtolower($item);
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 15);
        $json = curl_exec($ch);
        curl_close($ch);
        $rxnorm = json_decode($json, true);
        $return = '';
        if (isset($rxnorm['idGroup']['rxnormId'])) {
            $return = $rxnorm['idGroup']['rxnormId'][0];
        }
        return $return;
    }

    protected function rxnorm_search1($item)
    {
        $url = 'http://rxnav.nlm.nih.gov/REST/Prescribe/rxcui/' . $item . '/properties.json';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 15);
        $json = curl_exec($ch);
        curl_close($ch);
        $rxnorm = json_decode($json, true);
        $return = [
            'name' => '',
            'dosage' => '',
            'dosage_unit' => '',
            'ndcid' => ''
        ];
        if (isset($rxnorm['properties']['name'])) {
            $return['name'] = $rxnorm['properties']['name'];
        }
        $url1 = 'https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui/' . $item . '/allProperties.json?prop=attributes';
        $ch1 = curl_init();
        curl_setopt($ch1,CURLOPT_URL, $url1);
        curl_setopt($ch1,CURLOPT_FAILONERROR,1);
        curl_setopt($ch1,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch1,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch1,CURLOPT_TIMEOUT, 15);
        $json1 = curl_exec($ch1);
        curl_close($ch1);
        $rxnorm1 = json_decode($json1, true);
        if (isset($rxnorm1['propConceptGroup']['propConcept'])) {
            foreach ($rxnorm1['propConceptGroup']['propConcept'] as $row1) {
                if ($row1['propName'] == 'AVAILABLE_STRENGTH') {
                    $units = ['MG', 'MG/ML', 'MCG'];
                    $dosage_arr = [];
                    $unit_arr = [];
                    $arr = explode(' ', $row1['propValue']);
                    foreach ($units as $unit) {
                        $key = array_search($unit, $arr);
                        if ($key) {
                            $key1 = $key-1;
                            $dosage_arr[] = $arr[$key1];
                            $unit_arr[] = $arr[$key];
                        }
                    }
                    $return['dosage'] = implode(';', $dosage_arr);
                    $return['dosage_unit'] =implode(';', $unit_arr);
                }
            }
        }
        $url2 = 'https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui/' . $item . '/ndcs.json';
        $ch2 = curl_init();
        curl_setopt($ch2,CURLOPT_URL, $url2);
        curl_setopt($ch2,CURLOPT_FAILONERROR,1);
        curl_setopt($ch2,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch2,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch2,CURLOPT_TIMEOUT, 15);
        $json2 = curl_exec($ch2);
        curl_close($ch2);
        $rxnorm2 = json_decode($json2, true);
        if (isset($rxnorm2['ndcGroup']['ndcList']['ndc'][0])) {
            $return['ndcid'] = $rxnorm2['ndcGroup']['ndcList']['ndc'][0];
        }
        return $return;
    }

    protected function saveImage($img, $finalDestination)
    {
        $type = strtolower(strrchr($finalDestination, '.'));
        switch($type) {
            case '.jpg':
            case '.jpeg':
                if (imagetypes() & IMG_JPG) {
                    imagejpeg($img, $finalDestination, 100);
                }
                break;
            case '.gif':
                if (imagetypes() & IMG_GIF) {
                    imagegif($img, $finalDestination, 100);
                }
                break;
            case '.png':
                if (imagetypes() & IMG_PNG) {
                    imagepng($img, $finalDestination, 0);
                }
                break;
            default:
                break;
        }
        imagedestroy($img);
    }

    protected function schedule_notification($appt_id)
    {
        $appt = DB::table('schedule')->where('appt_id', '=', $appt_id)->first();
        if ($appt->pid !== '0') {
            $patient = DB::table('demographics')->where('pid', '=', $appt->pid)->first();
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $user = DB::table('users')->where('id', '=', $appt->provider_id)->first();
            if ($appt->start > time()) {
                if ($patient->reminder_to !== '') {
                    $data_message['startdate'] = date("F j, Y, g:i a", $appt->start);
                    $data_message['startdate1'] = date("Y-m-d, g:i a", $appt->start);
                    $data_message['displayname'] = $user->displayname;
                    $data_message['phone'] = $practice->phone;
                    $data_message['email'] = $practice->email;
                    $data_message['additional_message'] = $practice->additional_message;
                    if ($patient->reminder_method == 'Cellular Phone') {
                        $message = view('emails.remindertext', $data_message)->render();
                        $this->textbelt($patient->reminder_to, $message, Session::get('practice_id'));
                    } else {
                        $this->send_mail('emails.reminder', $data_message, 'Appointment Reminder', $patient->reminder_to, Session::get('practice_id'));
                    }
                }
            }
        }
    }

    protected function sidebar_build($type)
    {
        $return = [];
        $return['sidebar_content'] = $type;
        if ($type == 'chart') {
            $return['name'] = Session::get('ptname');
            $return['title'] = Session::get('ptname');
            // Demographics
            $demographics = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
            if ($demographics->address == '') {
                $return['demographics_alert'] = 'Address';
            }
            if ($demographics->nickname !== '' && $demographics->nickname !== null) {
                $return['name'] .= ' (' . $demographics->nickname . ')';
            }
            $return['demographics_quick'] = '<p style="margin:2px"><strong>DOB: </strong>' . date('F jS, Y', strtotime($demographics->DOB)) . '</p>';
            $return['demographics_quick'] .= '<p style="margin:2px"><strong>Age: </strong>' . Session::get('age') . '</p>';
            $return['demographics_quick'] .= '<p style="margin-top:2px; margin-bottom:8px;"><strong>Gender: </strong>' . ucfirst(Session::get('gender')) . '</p>';
            // Conditions
            $conditions = DB::table('issues')->where('pid', '=', Session::get('pid'))->where('issue_date_inactive', '=', '0000-00-00 00:00:00')->orderBy('issue', 'asc')->get();
            $return['conditions_badge'] = '0';
            $return['conditions_preview'] = 'None';
            if ($conditions->count()) {
                $return['conditions_badge'] = count($conditions);
                $return['conditions_preview'] = '<ul>';
                foreach ($conditions as $condition) {
                    $return['conditions_preview'] .= '<li>' . $condition->issue . '</li>';
                }
                $return['conditions_preview'] .= '</ul>';
            }
            // Medications
            $medications = DB::table('rx_list')->where('pid', '=', Session::get('pid'))->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->orderBy('rxl_medication', 'asc')->get();
            $return['medications_badge'] = '0';
            $return['medications_preview'] = 'None';
            if ($medications->count()) {
                $return['medications_badge'] = count($medications);
                $return['medications_preview'] = '<ul>';
                foreach ($medications as $medication) {
                    $return['medications_preview'] .= '<li>' . $medication->rxl_medication. '</li>';
                }
                $return['medications_preview'] .= '</ul>';
            }
            // Supplements
            $supplements = DB::table('sup_list')->where('pid', '=', Session::get('pid'))->where('sup_date_inactive', '=', '0000-00-00 00:00:00')->orderBy('sup_supplement', 'asc')->get();
            $return['supplements_badge'] = '0';
            $return['supplements_preview'] = 'None';
            if ($supplements->count()) {
                $return['supplements_badge'] = count($supplements);
                $return['supplements_preview'] = '<ul>';
                foreach ($supplements as $supplement) {
                    $return['supplements_preview'] .= '<li>' . $supplement->sup_supplement. '</li>';
                }
                $return['supplements_preview'] .= '</ul>';
            }
            // Allergies
            $allergies = DB::table('allergies')->where('pid', '=', Session::get('pid'))->where('allergies_date_inactive', '=', '0000-00-00 00:00:00')->orderBy('allergies_med', 'asc')->get();
            $return['allergies_badge'] = '0';
            $return['allergies_preview'] = 'None';
            if ($allergies->count()) {
                $return['allergies_badge'] = count($allergies);
                $return['allergies_preview'] = '<ul>';
                foreach ($allergies as $allergy) {
                    $return['allergies_preview'] .= '<li>' . $allergy->allergies_med. '</li>';
                }
                $return['allergies_preview'] .= '</ul>';
            }
            // Immunizations
            $immunizations = DB::table('immunizations')->where('pid', '=', Session::get('pid'))->orderBy('imm_immunization', 'asc')->orderBy('imm_sequence', 'asc')->get();
            $return['immunizations_badge'] = '0';
            $return['immunizations_preview'] = 'None';
            if ($immunizations->count()) {
                $return['immunizations_badge'] = count($immunizations);
                $return['immunizations_preview'] = '<ul>';
                foreach ($immunizations as $immunization) {
                    $return['immunizations_preview'] .= '<li>' . $immunization->imm_immunization. '</li>';
                }
                $return['immunizations_preview'] .= '</ul>';
            }
            // Alerts
            $return['alerts_badge'] = DB::table('alerts')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->count();
            // Orders
            $return['orders_badge'] = DB::table('orders')->where('pid', '=', Session::get('pid'))->where('orders_completed', '=', '0')->count();
            // Encounters
            $encounters_query = DB::table('encounters')->where('pid', '=', Session::get('pid'))
                ->where('addendum', '=', 'n');
            if (Session::get('patient_centric') == 'n') {
                $encounters_query->where('practice_id', '=', Session::get('practice_id'));
            }
            if (Session::get('group_id') == '100') {
                $encounters_query->where('encounter_signed', '=', 'Yes');
            }
            $return['encounters_badge'] = $encounters_query->count();
            $return['encounters_preview'] = '';
            if (Session::has('eid')) {
                $return['active_encounter'] = Session::get('eid');
                $return['active_encounter_url'] = Session::get('last_page_encounter');
                $eid = Session::get('eid');
                $return['encounters_preview'] .= '<strong>Encounter Action Items</strong>';
                $procedureInfo = DB::table('procedure')->where('eid', '=', $eid)->first();
                if ($procedureInfo) {
                    $procedure_arr = $this->array_procedure();
                    $return['encounters_preview'] .= '<br><h5>Procedures:</h5><p class="view">';
                    foreach ($procedure_arr as $procedure_k => $procedure_v) {
                        if ($procedureInfo->{$procedure_k} !== '' && $procedureInfo->{$procedure_k} !== null) {
                            if ($procedure_k == 'proc_description') {
                                if ($this->yaml_check($procedureInfo->{$procedure_k})) {
                                    $proc_search_arr = ['code:', 'timestamp:', 'procedure:', 'type:', '---' . "\n", '|'];
                                    $proc_replace_arr = ['<b>Procedure Code:</b>', '<b>When:</b>', '<b>Procedure Description:</b>', '<b>Type:</b>', '', ''];
                                    $return['encounters_preview'] .= nl2br(str_replace($proc_search_arr, $proc_replace_arr, $procedureInfo->{$procedure_k}));
                                } else {
                                    $return['encounters_preview'] .= '<strong>' . $procedure_v . ': </strong>';
                                    $return['encounters_preview'] .= nl2br($procedureInfo->{$procedure_k});
                                    $return['encounters_preview'] .= '<br /><br />';
                                }
                            } else {
                                $return['encounters_preview'] .= '<strong>' . $procedure_v . ': </strong>';
                                $return['encounters_preview'] .= nl2br($procedureInfo->{$procedure_k});
                                $return['encounters_preview'] .= '<br /><br />';
                            }
                        }
                    }
                    $return['encounters_preview'] .= '</p>';
                }
                $ordersInfo1 = DB::table('orders')->where('eid', '=', $eid)->get();
                if ($ordersInfo1->count()) {
                    $return['encounters_preview'] .= '<br><h5>Orders:</h5><p class="view">';
                    $orders_lab_array = [];
                    $orders_radiology_array = [];
                    $orders_cp_array = [];
                    $orders_referrals_array = [];
                    foreach ($ordersInfo1 as $ordersInfo) {
                        $address_row1 = DB::table('addressbook')->where('address_id', '=', $ordersInfo->address_id)->first();
                        if ($address_row1) {
                            $orders_displayname = $address_row1->displayname;
                            if ($ordersInfo->orders_referrals != '') {
                                $orders_displayname = $address_row1->specialty . ': ' . $address_row1->displayname;
                            }
                        } else {
                            $orders_displayname = 'Unknown';
                        }
                        if ($ordersInfo->orders_labs != '') {
                            $orders_lab_array[] = 'Orders sent to ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_labs) . '<br />';
                        }
                        if ($ordersInfo->orders_radiology != '') {
                            $orders_radiology_array[] = 'Orders sent to ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_radiology) . '<br />';
                        }
                        if ($ordersInfo->orders_cp != '') {
                            $orders_cp_array[] = 'Orders sent to ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_cp) . '<br />';
                        }
                        if ($ordersInfo->orders_referrals != '') {
                            $orders_referrals_array[] = 'Referral sent to ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_referrals) . '<br />';
                        }
                    }
                    if (! empty($orders_lab_array)) {
                        $return['encounters_preview'] .= '<strong>Labs: </strong><br>';
                        foreach ($orders_lab_array as $lab_item) {
                            $return['encounters_preview'] .= $lab_item;
                        }
                    }
                    if (! empty($orders_radiology_array)) {
                        $return['encounters_preview'] .= '<strong>Imaging: </strong><br>';
                        foreach ($orders_radiology_array as $radiology_item) {
                            $return['encounters_preview'] .= $radiology_item;
                        }
                    }
                    if (! empty($orders_cp_array)) {
                        $return['encounters_preview'] .= '<strong>Cardiopulmonary: </strong><br>';
                        foreach ($orders_cp_array as $cp_item) {
                            $return['encounters_preview'] .= $cp_item;
                        }
                    }
                    if (! empty($orders_referrals_array)) {
                        $return['encounters_preview'] .= '<strong>Referrals: </strong><br>';
                        foreach ($orders_referrals_array as $referrals_item) {
                            $return['encounters_preview'] .= $referrals_item;
                        }
                    }
                    $return['encounters_preview'] .= '</p>';
                }
                $rxInfo = DB::table('rx')->where('eid', '=', $eid)->first();
                if ($rxInfo) {
                    $rx_arr = $this->array_rx();
                    $return['encounters_preview'] .= '<br><h5>Prescriptions and Immunizations:</h5><p class="view">';
                    foreach ($rx_arr as $rx_k => $rx_v) {
                        if ($rxInfo->{$rx_k} !== '' && $rxInfo->{$rx_k} !== null) {
                            $return['encounters_preview'] .= '<strong>' . $rx_v . ': </strong><br>';
                            $return['encounters_preview'] .= nl2br($rxInfo->{$rx_k});
                            if ($rx_k == 'rx_immunizations') {
                                $return['encounters_preview'] .= 'CDC Vaccine Information Sheets given for each immunization and consent obtained.<br />';
                            }
                            $return['encounters_preview'] .= '<br /><br />';
                        }
                    }
                    $return['encounters_preview'] .= '</p>';
                }
                $planInfo = DB::table('plan')->where('eid', '=', $eid)->first();
                if ($planInfo) {
                    $plan_arr = $this->array_plan();
                    $return['encounters_preview'] .= '<br><h5>Plan:</h5><p class="view">';
                    foreach ($plan_arr as $plan_k => $plan_v) {
                        if ($planInfo->{$plan_k} !== '' && $planInfo->{$plan_k} !== null) {
                            $return['encounters_preview'] .= '<strong>' . $plan_v . ': </strong>';
                            $return['encounters_preview'] .= nl2br($planInfo->{$plan_k});
                            if ($plan_k == 'duration') {
                                $return['encounters_preview'] .= '  minutes';
                            }
                            $return['encounters_preview'] .= '<br /><br />';
                        }
                    }
                    $return['encounters_preview'] .= '</p>';
                }
            }
            // Vitals
            $return['growth_chart_show'] = 'no';
            if (Session::get('agealldays') < 6574.5) {
                $vitals = DB::table('vitals')->where('pid', '=', Session::get('pid'))->first();
                if ($vitals) {
                    $return['growth_chart_show'] = 'yes';
                }
            }
        }
        return $return;
    }

    protected function signature($id)
    {
        $user = DB::table('users')->where('id', '=', $id)->first();
        $sig = '<br><br><br><br><br><br><br>' . $user->displayname;
        $signature = DB::table('providers')->where('id', '=', $id)->first();
        if ($signature) {
            if ($signature->signature !== '') {
                if (file_exists($signature->signature)) {
                    $name = time() . '_signature.png';
                    $temp_path = public_path() .'/temp/' . $name;
                    $url = asset('temp/' . $name);
                    copy($signature->signature, $temp_path);
                    $link = HTML::image($url, 'Signature', ['border' => '0']);
                    $link = str_replace('https', 'http', $link);
                    $sig = $link . '<br>' . $user->displayname;
                }
            }
        }
        return $sig;
    }

    protected function send_fax($job_id, $faxnumber, $faxrecipient)
    {
        $fax_data = DB::table('sendfax')->where('job_id', '=', $job_id)->first();
        $meta = ["(", ")", "-", " "];
        if ($faxnumber != '' && $faxrecipient != '') {
            $fax = str_replace($meta, "", $faxnumber);
            $send_list_data = [
                'faxrecipient' => $faxrecipient,
                'faxnumber' => str_replace($meta, "", $faxnumber),
                'job_id' => $job_id
            ];
            DB::table('recipients')->insert($send_list_data);
            $this->audit('Add');
        }
        $faxrecipients = '';
        $faxnumber_array = [];
        $recipientlist = DB::table('recipients')->where('job_id', '=', $job_id)->select('faxrecipient', 'faxnumber')->get();
        foreach ($recipientlist as $row) {
            $faxrecipients .= $row->faxrecipient . ', Fax: ' . $row->faxnumber . "\n";
            $faxnumber_array[] = str_replace($meta, "", $row->faxnumber);
        }
        $practice_row = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $pagesInfo = DB::table('pages')->where('job_id', '=', $job_id)->get();
        $faxpages = '';
        $totalpages = 0;
        $senddate = date('Y-m-d H:i:s');
        foreach ($pagesInfo as $row4) {
            $faxpages .= ' ' . $row4->file;
            $totalpages = $totalpages + $row4->pagecount;
        }
        if ($fax_data->faxcoverpage == 'yes') {
            $cover_filename = Session::get('documents_dir') . 'sentfax/' . $job_id . '/coverpage.pdf';
            if(file_exists($cover_filename)) {
                unlink($cover_filename);
            }
            $cover_html = $this->page_intro('Cover Page', Session::get('practice_id'));
            $cover_html .= $this->page_coverpage($job_id, $totalpages, $faxrecipients, date("M d, Y, h:i", time()));
            $this->generate_pdf($cover_html, $cover_filename, 'footerpdf');
            while(!file_exists($cover_filename)) {
                sleep(2);
            }
        }
        $phaxio_files_array = [];
        if ($fax_data->faxcoverpage == 'yes') {
            $phaxio_files_array[] = $cover_filename;
        }
        foreach ($pagesInfo as $phaxio_file) {
            $phaxio_files_array[] = $phaxio_file->file;
        }
        $phaxio = new Phaxio($practice_row->phaxio_api_key, $practice_row->phaxio_api_secret);
        $phaxio_result = $phaxio->sendFax($faxnumber_array, $phaxio_files_array);
        $phaxio_result_array = json_decode($phaxio_result, true);
        $fax_update_data = [
            'sentdate' => date('Y-m-d'),
            'ready_to_send' => '1',
            'senddate' => $senddate,
            'faxdraft' => '0',
            'attempts' => '0',
            'success' => '0'
        ];
        if ($phaxio_result_array['success'] == true) {
            $fax_update_data['success'] = '2';
            $fax_update_data['command'] = $phaxio_result_array['faxId'];
        }
        DB::table('sendfax')->where('job_id', '=', $job_id)->update($fax_update_data);
        $this->audit('Update');
        Session::forget('job_id');
        return 'Fax Job ' . $job_id . ' Sent';
    }

    protected function send_mail($template, $data_message, $subject, $to, $practice_id)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        if (env('MAIL_HOST') == 'smtp.gmail.com') {
            // $file = File::get(base_path() . "/.google");
            // if ($file !== '') {
                // $file_arr = json_decode($file, true);
            $google = new Google_Client();
            $google->setClientID(env('GOOGLE_KEY'));
            $google->setClientSecret(env('GOOGLE_SECRET'));
            // $google->setClientID($file_arr['web']['client_id']);
            // $google->setClientSecret($file_arr['web']['client_secret']);
            $google->refreshToken($practice->google_refresh_token);
            $credentials = $google->getAccessToken();
            $data1['smtp_pass'] = $credentials['access_token'];
            DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data1);
            $config['mail.password'] =  $credentials['access_token'];
            // $config = [
                // 'mail.driver' => 'smtp',
                // 'mail.host' => 'smtp.gmail.com',
                // 'mail.port' => 465,
                // 'mail.from' => ['address' => null, 'name' => null],
                // 'mail.encryption' => 'ssl',
                // 'mail.username' => $practice->smtp_user,
                // 'mail.password' =>  $credentials['access_token'],
                // 'mail.sendmail' => '/usr/sbin/sendmail -bs'
            // ];
            config($config);
            extract(Config::get('mail'));
            // $transport = Swift_SmtpTransport::newInstance($host, $port, 'ssl');
            // $transport->setAuthMode('XOAUTH2');
            // if (isset($encryption)) {
            //     $transport->setEncryption($encryption);
            // }
            // if (isset($username)) {
            //     $transport->setUsername($username);
            //     $transport->setPassword($password);
            // }
            // Mail::setSwiftMailer(new Swift_Mailer($transport));
            // Mail::send($template, $data_message, function ($message) use ($to, $subject, $practice) {
            //     $message->to($to)
            //         ->from($practice->email, $practice->practice_name)
            //         ->subject($subject);
            // });
            // }
        }
        if (env('MAIL_DRIVER') !== 'none') {
            Mail::send($template, $data_message, function ($message) use ($to, $subject, $practice) {
    			$message->to($to)
    				->from($practice->email, $practice->practice_name)
    				->subject($subject);
    		});
		    return "E-mail sent.";
        }
        return true;
    }

    /**
    * Set patient into session
    * @param string  $pid - patient id
    * @return Response
    */
    protected function setpatient($pid)
    {
        $row = DB::table('demographics')->where('pid', '=', $pid)->first();
        $date = Date::parse($row->DOB);
        $dob1 = $date->timestamp;
        $age_arr = explode(',', $date->timespan());
        $gender_arr = [
            'm' => 'male',
            'f' => 'female',
            'u' => 'individual'
        ];
        if (Session::has('eid')) {
            Session::forget('eid');
        }
        Session::put('pid', $pid);
        Session::put('gender', $gender_arr[$row->sex]);
        Session::put('age', ucwords($age_arr[0] . ' Old'));
        Session::put('agealldays', $date->diffInDays(Date::now()));
        Session::put('ptname', $row->firstname . ' ' . $row->lastname);
        $history = [];
        if (Session::has('history_pid')) {
            $history = Session::get('history_pid');
        }
        if (!in_array($pid, $history)) {
            $history[] = $pid;
        }
        Session::put('history_pid', $history);
        return true;
    }

    /**
     *    Signature to Image: A supplemental script for Signature Pad that
     *    generates an image of the signatureâs JSON output server-side using PHP.
     *
     *    @project    ca.thomasjbradley.applications.signaturetoimage
     *    @author        Thomas J Bradley <hey@thomasjbradley.ca>
     *    @link        http://thomasjbradley.ca/lab/signature-to-image
     *    @link        http://github.com/thomasjbradley/signature-to-image
     *    @copyright    Copyright MMXIâ, Thomas J Bradley
     *    @license    New BSD License
     *    @version    1.0.1
     */

    /**
     *    Accepts a signature created by signature pad in Json format
     *    Converts it to an image resource
     *    The image resource can then be changed into png, jpg whatever PHP GD supports
     *
     *    To create a nicely anti-aliased graphic the signature is drawn 12 times it's original size then shrunken
     *
     *    @param    string|array    $json
     *    @param    array    $options    OPTIONAL; the options for image creation
     *        imageSize => array(width, height)
     *        bgColour => array(red, green, blue)
     *        penWidth => int
     *        penColour => array(red, green, blue)
     *
     *    @return    object
     */

    protected function sigJsonToImage($json, $options=[])
    {
        $defaultOptions = [
            'imageSize' => [198, 55],
            'bgColour' => [0xff, 0xff, 0xff],
            'penWidth' => 2,
            'penColour' => [0x14, 0x53, 0x94],
            'drawMultiplier'=> 12
        ];
        $options = array_merge($defaultOptions, $options);
        $img = imagecreatetruecolor($options['imageSize'][0] * $options['drawMultiplier'], $options['imageSize'][1] * $options['drawMultiplier']);
        imagesavealpha($img, true);
        $color = imagecolorallocatealpha($img, $options['bgColour'][0], $options['bgColour'][1], $options['bgColour'][2], 127);
        // $bg = imagecolorallocate($img, $options['bgColour'][0], $options['bgColour'][1], $options['bgColour'][2]);
        $pen = imagecolorallocate($img, $options['penColour'][0], $options['penColour'][1], $options['penColour'][2]);
        // imagefill($img, 0, 0, $bg);
        imagefill($img, 0, 0, $color);
        if(is_string($json)) {
            $json = json_decode(stripslashes($json));
        }
        foreach($json as $v) {
            $this->sigdrawThickLine($img, $v->lx * $options['drawMultiplier'], $v->ly * $options['drawMultiplier'], $v->mx * $options['drawMultiplier'], $v->my * $options['drawMultiplier'], $pen, $options['penWidth'] * ($options['drawMultiplier'] / 2));
        }
        $imgDest = imagecreatetruecolor($options['imageSize'][0], $options['imageSize'][1]);
        imagealphablending($imgDest, false );
        imagesavealpha($imgDest, true );
        imagecopyresampled($imgDest, $img, 0, 0, 0, 0, $options['imageSize'][0], $options['imageSize'][0], $options['imageSize'][0] * $options['drawMultiplier'], $options['imageSize'][0] * $options['drawMultiplier']);
        imagedestroy($img);
        return $imgDest;
    }

    /**
     *    Draws a thick line
     *    Changing the thickness of a line using imagesetthickness doesn't produce as nice of result
     *
     *    @param    object    $img
     *    @param    int        $startX
     *    @param    int        $startY
     *    @param    int        $endX
     *    @param    int        $endY
     *    @param    object    $colour
     *    @param    int        $thickness
     *
     *    @return    void
     */
    protected function sigdrawThickLine($img, $startX, $startY, $endX, $endY, $colour, $thickness)
    {
        $angle = (atan2(($startY - $endY), ($endX - $startX)));
        $dist_x = $thickness * (sin($angle));
        $dist_y = $thickness * (cos($angle));
        $p1x = ceil(($startX + $dist_x));
        $p1y = ceil(($startY + $dist_y));
        $p2x = ceil(($endX + $dist_x));
        $p2y = ceil(($endY + $dist_y));
        $p3x = ceil(($endX - $dist_x));
        $p3y = ceil(($endY - $dist_y));
        $p4x = ceil(($startX - $dist_x));
        $p4y = ceil(($startY - $dist_y));
        $array = array(0=>$p1x, $p1y, $p2x, $p2y, $p3x, $p3y, $p4x, $p4y);
        imagefilledpolygon($img, $array, (count($array)/2), $colour);
    }

    protected function snomed($query, $single=false)
    {
        if (!Session::has('tgt')) {
            $url = 'https://utslogin.nlm.nih.gov/cas/v1/api-key';
            $message = http_build_query([
                'apikey' => 'c7c9d25a-4317-42f5-9094-899963fb9700'
            ]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            curl_close($ch);
            $html = new Htmldom($data);
            if (isset($html)) {
                $form = $html->find('form',0);
                $action = $form->action;
                $action_arr = explode('/', $action);
                $tgt = end($action_arr);
            }
            Session::put('tgt', $tgt);
        }
        $url1 = 'https://utslogin.nlm.nih.gov/cas/v1/tickets/' .  Session::get('tgt');
        $message1 = http_build_query([
            'service' => 'http://umlsks.nlm.nih.gov'
        ]);
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, $url1);
        curl_setopt($ch1, CURLOPT_POST, 1);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, $message1);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        $service_ticket = curl_exec($ch1);
        curl_close($ch1);
        $params = [
            'ticket' => $service_ticket,
            'string' => $query,
            'sabs' => 'SNOMEDCT_US',
            'returnIdType' => 'code'
        ];
        $url2 = 'https://uts-ws.nlm.nih.gov/rest/search/current';
        $url2 .= '?' . http_build_query($params, null, '&');
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $url2);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        $data2 = curl_exec($ch2);
        curl_close($ch2);
        $data2_arr = json_decode($data2, true);
        $return = [];
        if (isset($data2_arr['result']['results'])) {
            foreach ($data2_arr['result']['results'] as $row) {
                if ($row['ui'] !== 'NONE') {
                    $return[] = [
                        'code' => $row['ui'],
                        'description' => $row['name']
                    ];
                }
            }
        }
        if ($single == true) {
            if (! empty($return)) {
                return $return[0];
            } else {
                return $return;
            }
        } else {
            return $return;
        }
    }

    protected function string_format($str, $len, $phone='')
    {
        if ($phone == 'phone') {
            $phone_str = preg_replace('/\D+/', '', $str);
            $str = substr($phone_str, 0, 3) . ' ' . substr($phone_str, 3);
        }
        if (strlen((string)$str) < $len) {
            $str1 = str_pad((string)$str, $len);
        } else {
            $str1 = substr((string)$str, 0, $len);
        }
        $str1 = strtoupper((string)$str1);
        return $str1;
    }

    protected function striposa($haystack, $needle, $offset=0)
    {
        if (!is_array($needle)) $needle = array($needle);
        $count = count($needle);
        $i = 0;
        foreach ($needle as $query) {
            if (stripos($haystack, $query, $offset) !== false) {
                $i++;
            }
        }
        if ($i == $count) {
            return true;
        }
        return false;
    }

    protected function super_unique($array)
    {
        $result = array_map('unserialize', array_unique(array_map('serialize', $array)));
        foreach ($result as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->super_unique($value);
            }
        }
        return $result;
    }

    protected function supplements_compile()
    {
        $url = 'https://medlineplus.gov/druginfo/herb_All.html';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $html = new Htmldom($data);
        $data1 = [];
        if (isset($html)) {
            foreach ($html->find('[class=section-body]') as $div) {
                foreach ($div->find('li') as $li) {
                    $a = $li->find('a',0);
                    if (!in_array($a->innertext, $data1)) {
                        $data1[] = $a->innertext;
                    }
                }
            }
        }
        $formatter1 = Formatter::make($data1, Formatter::ARR);
        $text = $formatter1->toYaml();
        $file_path = resource_path() . '/supplements.yaml';
        File::put($file_path, $text);
        return 'OK';
    }

    protected function tables_oboslete()
    {
        $data = [
            'ci_sessions',
            'curr_associationrefset_d',
            'curr_attributevaluerefset_f',
            'curr_complexmaprefset_f',
            'curr_concept_f',
            'curr_description_f',
            'curr_langrefset_f',
            'curr_relationship_f',
            'curr_simplemaprefset_f',
            'curr_simplerefset_f',
            'curr_stated_relationship_f',
            'curr_textdefinition_f',
            'cvx',
            'gc',
            'guardian_roles',
            'icd9',
            'icd10',
            'lang',
            'meds_full',
            'meds_full_package',
            'npi',
            'pos',
            'sessions',
            'snomed_procedure_imaging',
            'snomed_procedure_path'
        ];
        return $data;
    }

    /**
    * SMS notifcation with TextBelt
    *
    * @return Response
    */
    protected function textbelt($number, $message, $practice_id)
    {
        $url = "http://cloud.noshchartingsystem.com:9090/text";
        $practice = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
        if ($practice->sms_url !== '' && $practice->sms_url !== null) {
            $url = $practice->sms_url;
        }
        $message = http_build_query([
            'number' => $number,
            'message' => $message
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    protected function timeline()
    {
        $pid = Session::get('pid');
        $json = [];
        $date_arr = [];
        $query0 = DB::table('encounters')->where('pid', '=', $pid)->where('addendum', '=', 'n')->get();
        if ($query0->count()) {
            foreach ($query0 as $row0) {
                $description = '';
                $procedureInfo = DB::table('procedure')->where('eid', '=', $row0->eid)->first();
                if ($procedureInfo) {
                    $description .= '<span class="nosh_bold">Procedures:</span>';
                    if ($procedureInfo->proc_type != '') {
                        $description .= '<strong>Procedure: </strong>';
                        $description .= nl2br($procedureInfo->proc_type);
                    }
                }
                $assessmentInfo = DB::table('assessment')->where('eid', '=', $row0->eid)->first();
                if ($assessmentInfo) {
                    $assessment_arr = $this->array_assessment();
                    $description .= '<span class="nosh_bold">Assessment:</span>';
                    for ($l = 1; $l <= 12; $l++) {
                        $col0 = 'assessment_' . $l;
                        if ($assessmentInfo->{$col0} !== '' && $assessmentInfo->{$col0} !== null) {
                            $description .= '<br><strong>' . $assessmentInfo->{$col0} . '</strong><br />';
                        }
                    }
                    foreach ($assessment_arr as $assessment_k => $assessment_v) {
                        if ($assessmentInfo->{$assessment_k} !== '' && $assessmentInfo->{$assessment_k} !== null) {
                            if ($row0->encounter_template == 'standardmtm') {
                                $description .= '<br /><strong>' . $assessment_v['standardmtm'] . ': </strong>';
                            } else {
                                $description .= '<br /><strong>' . $assessment_v['standard'] . ': </strong>';
                            }
                            $description .= nl2br($assessmentInfo->{$assessment_k});
                            $description .= '<br /><br />';
                        }
                    }
                }
                $encounter_status = 'y';
                if (Session::get('group_id') == '100' && $row0->encounter_signed == 'No') {
                    $encounter_status = 'n';
                    $description = 'Unsigned encounter';
                }
                $div0 = $this->timeline_item($row0->eid, 'eid', 'Encounter', $this->human_to_unix($row0->encounter_DOS), 'Encounter: ' . $row0->encounter_cc, $description, $encounter_status);
                $json[] = [
                    'div' => $div0,
                    'startDate' => $this->human_to_unix($row0->encounter_DOS)
                ];
                $date_arr[] = $this->human_to_unix($row0->encounter_DOS);
            }
        }
        $query1 = DB::table('t_messages')->where('pid', '=', $pid)->get();
        if ($query1->count()) {
            foreach ($query1 as $row1) {
                $div1 = $this->timeline_item($row1->t_messages_id, 't_messages_id', 'Telephone Message', $this->human_to_unix($row1->t_messages_dos), 'Telephone Message', substr($row1->t_messages_message, 0, 500) . '...', $row1->t_messages_signed);
                $json[] = [
                    'div' => $div1,
                    'startDate' => $this->human_to_unix($row1->t_messages_dos)
                ];
                $date_arr[] = $this->human_to_unix($row1->t_messages_dos);
            }
        }
        $query2 = DB::table('rx_list')->where('pid', '=', $pid)->orderBy('rxl_date_active','asc')->groupBy('rxl_medication')->get();
        if ($query2->count()) {
            foreach ($query2 as $row2) {
                $row2a = DB::table('rx_list')->where('rxl_id', '=', $row2->rxl_id)->first();
                if ($row2->rxl_sig == '') {
                    $instructions = $row2->rxl_instructions;
                } else {
                    $instructions = $row2->rxl_sig . ', ' . $row2->rxl_route . ', ' . $row2->rxl_frequency;
                    if ($row2->rxl_instructions !== null && $row2->rxl_instructions !== '') {
                        $instructions .= ', ' . $row2->rxl_instructions;
                    }
                }
                $description2 = $row2->rxl_medication . ' ' . $row2->rxl_dosage . ' ' . $row2->rxl_dosage_unit . ', ' . $instructions . ' for ' . $row2->rxl_reason;
                if ($row2->rxl_date_prescribed == null || $row2->rxl_date_prescribed == '0000-00-00 00:00:00') {
                    $div2 = $this->timeline_item($row2->rxl_id, 'rxl_id', 'New Medication', $this->human_to_unix($row2->rxl_date_active), 'New Medication', $description2);
                } else {
                    $description2 .= '<br>Status: ' . ucfirst($row2->prescription);
                    $div2 = $this->timeline_item($row2->rxl_id, 'rxl_id', 'Prescribed Medication', $this->human_to_unix($row2->rxl_date_active), 'Prescribed Medication', $description2);
                }
                $json[] = [
                    'div' => $div2,
                    'startDate' => $this->human_to_unix($row2->rxl_date_active)
                ];
                $date_arr[] = $this->human_to_unix($row2->rxl_date_active);
            }
        }
        $query3 = DB::table('issues')->where('pid', '=', $pid)->get();
        if ($query3->count()) {
            foreach ($query3 as $row3) {
                if ($row3->type == 'Problem List') {
                    $title = 'New Problem';
                }
                if ($row3->type == 'Medical History') {
                    $title = 'New Medical Event';
                }
                if ($row3->type == 'Surgical History') {
                    $title = 'New Surgical Event';
                }
                $description3 = $row3->issue;
                if ($row3->notes !== null && $row3->notes !== '') {
                    $description3 .= ', ' . $row3->notes;
                }
                $div3 = $this->timeline_item($row3->issue_id, 'issue_id', $title, $this->human_to_unix($row3->issue_date_active), $title, $description3);
                $json[] = [
                    'div' => $div3,
                    'startDate' => $this->human_to_unix($row3->issue_date_active)
                ];
                $date_arr[] = $this->human_to_unix($row3->issue_date_active);
            }
        }
        $query4 = DB::table('immunizations')->where('pid', '=', $pid)->get();
        if ($query4->count()) {
            foreach ($query4 as $row4) {
                $div4 = $this->timeline_item($row4->imm_id, 'imm_id', 'Immunization Given', $this->human_to_unix($row4->imm_date), 'Immunization Given', $row4->imm_immunization);
                $json[] = [
                    'div' => $div4,
                    'startDate' => $this->human_to_unix($row4->imm_date)
                ];
                $date_arr[] = $this->human_to_unix($row4->imm_date);
            }
        }
        $query5 = DB::table('rx_list')->where('pid', '=', $pid)->where('rxl_date_inactive', '!=', '0000-00-00 00:00:00')->get();
        if ($query5->count()) {
            foreach ($query5 as $row5) {
                $row5a = DB::table('rx_list')->where('rxl_id', '=', $row5->rxl_id)->first();
                if ($row5->rxl_sig == '') {
                    $instructions5 = $row5->rxl_instructions;
                } else {
                    $instructions5 = $row5->rxl_sig . ', ' . $row5->rxl_route . ', ' . $row5->rxl_frequency;
                    if ($row5->rxl_instructions !== null && $row5->rxl_instructions !== '') {
                        $instructions5 .= ', ' . $row5->rxl_instructions;
                    }
                }
                $description5 = $row5->rxl_medication . ' ' . $row5->rxl_dosage . ' ' . $row5->rxl_dosage_unit . ', ' . $instructions5 . ' for ' . $row5->rxl_reason;
                $div5 = $this->timeline_item($row5->rxl_id, 'rxl_id', 'Medication Stopped', $this->human_to_unix($row5->rxl_date_inactive), 'Medication Stopped', $description5);
                $json[] = [
                    'div' => $div5,
                    'startDate' => $this->human_to_unix($row5->rxl_date_inactive)
                ];
                $date_arr[] = $this->human_to_unix($row5->rxl_date_inactive);
            }
        }
        $query6 = DB::table('allergies')->where('pid', '=', $pid)->where('allergies_date_inactive', '=', '0000-00-00 00:00:00')->get();
        if ($query6->count()) {
            foreach ($query6 as $row6) {
                $description6 = $row6->allergies_med;
                if ($row6->notes !== null && $row6->notes !== '') {
                    $description6 .= ', ' . $row6->notes;
                }
                $div6 = $this->timeline_item($row6->allergies_id, 'allergies_id', 'New Allergy', $this->human_to_unix($row6->allergies_date_active), 'New Allergy', $description6);
                $json[] = [
                    'div' => $div6,
                    'startDate' => $this->human_to_unix($row6->allergies_date_active)
                ];
                $date_arr[] = $this->human_to_unix($row6->allergies_date_active);
            }
        }
        $query7 = DB::table('data_sync')->where('pid', '=', $pid)->get();
        if ($query7->count()) {
            foreach ($query7 as $row7) {
                $description7 = $row7->action . ', ' . $row7->from;
                $div7 = $this->timeline_item($row7->source_id, $row7->source_index, 'Data Sync via FHIR', $this->human_to_unix($row7->created_at), 'Data Sync via FHIR', $description7);
                $json[] = [
                    'div' => $div7,
                    'startDate' => $this->human_to_unix($row7->created_at)
                ];
                $date_arr[] = $this->human_to_unix($row7->created_at);
            }
        }
        if (! empty($json)) {
            foreach ($json as $key => $value) {
                $item[$key]  = $value['startDate'];
            }
            array_multisort($item, SORT_DESC, $json);
        }
        asort($date_arr);
        $arr['start'] = reset($date_arr);
        $arr['end'] = end($date_arr);
        if ($arr['end'] - $arr['start'] >= 315569260) {
            $arr['granular'] = 'decade';
        }
        if ($arr['end'] - $arr['start'] > 31556926 && $arr['end'] - $arr['start'] < 315569260) {
            $arr['granular'] = 'year';
        }
        if ($arr['end'] - $arr['start'] <= 31556926) {
            $arr['granular'] = 'month';
        }
        $arr['json'] = $json;
        return $arr;
    }

    protected function timeline_item($value, $type, $category, $date, $title, $p, $status='')
    {
        $div = '<div class="cd-timeline-block" data-nosh-category="' . $category . '">';
        if ($category == 'Encounter') {
            $div .= '<div class="cd-timeline-img cd-encounter"><i class="fa fa-stethoscope fa-fw fa-lg"></i>';
        }
        if ($category == 'Telephone Message') {
            $div .= '<div class="cd-timeline-img cd-encounter"><i class="fa fa-phone fa-fw fa-lg"></i>';
        }
        if ($category == 'New Medication' || $category == 'Prescribed Medication') {
            $div .= '<div class="cd-timeline-img cd-medication"><i class="fa fa-eyedropper fa-fw fa-lg"></i>';
        }
        if ($category == 'New Problem' || $category == 'New Medical Event' || $category == 'New Surgical Event') {
            $div .= '<div class="cd-timeline-img cd-issue"><i class="fa fa-bars fa-fw fa-lg"></i>';
        }
        if ($category == 'Immunization Given') {
            $div .= '<div class="cd-timeline-img cd-imm"><i class="fa fa-magic fa-fw fa-lg"></i>';
        }
        if ($category == 'Medication Stopped') {
            $div .= '<div class="cd-timeline-img cd-medication"><i class="fa fa-ban fa-fw fa-lg"></i>';
        }
        if ($category == 'New Allergy') {
            $div .= '<div class="cd-timeline-img cd-allergy"><i class="fa fa-exclamation-triangle fa-fw fa-lg"></i>';
        }
        if ($category == 'Data Sync via FHIR') {
            $div .= '<div class="cd-timeline-img cd-datasync"><i class="fa fa-fire fa-fw fa-lg"></i>';
        }
        $div .= '</div><div class="cd-timeline-content">';
        $div .= '<h3>' . $title . '</h3>';
        $div .= '<p>' . $p . '</p>';
        // Set up URL to each type
        $type_arr = [
            'eid' => route('encounter_view', [$value]),
            't_messages_id' => route('t_message_view', [$value]),
            'rxl_id' => route('medications_list', ['active']),
            'issue_id' => route('conditions_list', ['active']),
            'imm_id' => route('immunizations_list'),
            'allergies_id' => route('allergies_list', ['active'])
        ];
        $url = $type_arr[$type];
        if ($status !== 'n') {
            $div .= '<a href="' . $url . '" class="btn btn-primary cd-read-more" data-nosh-value="' . $value . '" data-nosh-type="' . $type . '" data-nosh-status="' . $status . '">Read more</a>';
        }
        $div .= '<span class="cd-date">' . date('Y-m-d', $date) . '</span>';
        $div .= '</div></div>';
        return $div;
    }

    protected function timezone($unixtime)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        date_default_timezone_set('UTC');
        $str = date('d-m-Y H:i:s', $unixtime);
        date_default_timezone_set($practice->timezone);
        return strtotime($str);
    }

    protected function t_messages_view($t_messages_id, $print=false)
    {
        $row = DB::table('t_messages')->where('t_messages_id', '=', $t_messages_id)->first();
        $text = '<table cellspacing="2" style="font-size:0.9em; width:100%;">';
        if ($print == true) {
            $text .= '<tr><th style="background-color: gray;color: #FFFFFF; text-align: left;">MESSAGE DETAILS</th></tr>';
        }
        $text .= '<tr><td><h4>Date of Service: </h4>' . date('m/d/Y', $this->human_to_unix($row->t_messages_dos));
        $text .= '<br><h4>Subject: </h4>' . $row->t_messages_subject;
        $text .= '<br><h4>Message: </h4>' . $row->t_messages_message;
        if ($row->actions !== '' && $row->actions !== null) {
            $search_arr = ['action:', 'timestamp:', '---' . "\n"];
            $replace_arr = ['<b>Action:</b>', '<b>When:</b>', ''];
            $text .= '<br><h4>Actions: </h4>' . nl2br(str_replace($search_arr, $replace_arr, $row->actions));
        }
        $text .= '<br><hr />Electronically signed by ' . $row->t_messages_provider . '.';
        $text .= '</td></tr></table>';
        return $text;
    }

    protected function total_balance($pid)
    {
        $practice_id = Session::get('practice_id');
        $query1 = DB::table('encounters')->where('pid', '=', $pid)->where('addendum', '=', 'n')->where('practice_id', '=', $practice_id)->get();
        $i = 0;
        $balance1 = 0;
        $balance2 = 0;
        if ($query1) {
            foreach ($query1 as $row1) {
                $query1a = DB::table('billing_core')->where('eid', '=', $row1->eid)->get();
                if ($query1a) {
                    $charge1 = 0;
                    $payment1 = 0;
                    foreach ($query1a as $row1a) {
                        $charge1 += $row1a->cpt_charge * $row1a->unit;
                        $payment1 += $row1a->payment;
                    }
                    $balance1 += $charge1 - $payment1;
                }
                $i++;
            }
        }
        $query2 = DB::table('billing_core')->where('pid', '=', $pid)->where('eid', '=', '0')->where('payment', '=', '0')->where('practice_id', '=', $practice_id)->get();
        $j = 0;
        if ($query2) {
            foreach ($query2 as $row2) {
                $charge2 = $row2->cpt_charge * $row2->unit;
                $payment2 = 0;
                $query2a = DB::table('billing_core')->where('other_billing_id', '=', $row2->billing_core_id)->where('payment', '!=', '0')->get();
                if ($query2a) {
                    foreach ($query2a as $row2a) {
                        $payment2 += $row2a->payment;
                    }
                }
                $balance2 += $charge2 - $payment2;
                $j++;
            }
        }
        $total_balance = $balance1 + $balance2;
        $billing_notes = "None.";
        $result = DB::table('demographics_notes')->where('pid', '=', $pid)->where('practice_id', '=', $practice_id)->first();
        if ($result->billing_notes !== '' && $result->billing_notes !== null) {
            $billing_notes = nl2br($result->billing_notes);
        }
        $note = "<strong>Total Balance: $" .  number_format($total_balance, 2, '.', ',') . "</strong><br><br><strong>Billing Notes: </strong>" . $billing_notes . "<br>";
        return $note;
    }

    protected function treedata_x_build($nodes_arr)
    {
        $return = [];
        $nodes_partners = [];
        $nodes_sibling = [];
        $nodes_paternal = [];
        $nodes_maternal = [];
        $nodes_children = [];
        $nodes_placeholder = [];
        foreach ($nodes_arr as $node)
        {
            if ($node['id'] !== 'patient') {
                if (isset($node['sibling_group'])) {
                    if ($node['sibling_group'] == 'Partners') {
                        $nodes_partners[] = $node;
                    }
                    if ($node['sibling_group'] == 'Sibling') {
                        $nodes_sibling[] = $node;
                    }
                    if ($node['sibling_group'] == 'Paternal') {
                        $nodes_paternal[] = $node;
                    }
                    if ($node['sibling_group'] == 'Maternal') {
                        $nodes_maternal[] = $node;
                    }
                    if ($node['sibling_group'] == 'Children') {
                        $nodes_children[] = $node;
                    }
                }
                if (isset($node['orig_x'])) {
                    $nodes_placeholder[] = $node;
                }
            } else {
                $return[] = $node;
            }
        }
        if (! empty($nodes_partners)) {
            $a = 1;
            foreach ($nodes_partners as $node1) {
                $node1['x'] = 8 * $a;
                $return[] = $node1;
                $a++;
            }
        }
        if (! empty($nodes_sibling)) {
            $a1 = -1;
            foreach ($nodes_sibling as $node1a) {
                $node1a['x'] = 8 * $a1;
                $return[] = $node1a;
                $a1--;
            }
        }
        if (! empty($nodes_children)) {
            $bf = 1;
            $bm = -1;
            foreach ($nodes_children as $node2) {
                if ($node2['color'] == 'rgb(125,125,255)')  {
                    $node2['x'] = 8 * $bm;
                    $return[] = $node2;
                    $bm--;
                } else {
                    $node2['x'] = 8 * $bf;
                    $return[] = $node2;
                    $bf++;
                }
            }
        }
        if (! empty($nodes_paternal)) {
            $c = -1;
            foreach ($nodes_paternal as $node3) {
                $node3['x'] = 8 * $c;
                $return[] = $node3;
                $c--;
            }
        }
        if (! empty($nodes_maternal)) {
            $d = 1;
            foreach ($nodes_maternal as $node4) {
                $node4['x'] = 8 * $d;
                $return[] = $node4;
                $d++;
            }
        }
        if (! empty($nodes_placeholder)) {
            foreach ($nodes_placeholder as $node5) {
                $orig_x = 0;
                foreach ($return as $k => $v) {
                    if ($v['id'] == $node5['orig_x']) {
                        $orig_x = $return[$k]['x'];
                        break;
                    }
                }
                if ($node5['color'] == 'rgb(125,125,255)')  {
                    if ($node5['y'] == -10) {
                        $node5['x'] = $orig_x - 8;
                    }
                    if ($node5['y'] == -20) {
                        $node5['x'] = $orig_x - 4;
                    }

                } else {
                    if ($node5['y'] == -10) {
                        $node5['x'] = $orig_x + 8;
                    }
                    if ($node5['y'] == -20) {
                        $node5['x'] = $orig_x + 4;
                    }
                }
                $return[] = $node5;
            }
        }
        return $return;
    }

    protected function treedata_build($arr, $key, $nodes_arr, $edges_arr, $placeholder_count)
    {
        $rel_arr = [
            'Father' => ['Paternal Grandfather', 'Paternal Grandmother', -20, -10, 'Paternal'],
            'Mother' => ['Maternal Grandfather', 'Maternal Grandmother', -20, -10, 'Maternal'],
            'Sister' => ['Father', 'Mother', -10, 0, 'Sibling'],
            'Brother' => ['Father', 'Mother', -10, 0, 'Sibling'],
            'Paternal Uncle' => ['Paternal Grandfather', 'Paternal Grandmother', -20, -10, 'Paternal'],
            'Paternal Aunt' => ['Paternal Grandfather', 'Paternal Grandmother', -20, -10, 'Paternal'],
            'Maternal Uncle' => ['Maternal Grandfather', 'Maternal Grandmother', -20, -10, 'Maternal'],
            'Maternal Aunt' => ['Maternal Grandfather', 'Maternal Grandmother', -20, -10, 'Maternal'],
        ];
        $rel_arr1 = [
            'Spouse',
            'Partner',
        ];
        $patient = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $parents_arr = [];
        $parents1_arr = [];
        if (! empty($arr) && $key !== 'patient') {
            $color = 'rgb(125,125,255)';
            if ($arr[$key]['Gender'] == 'Female') {
                $color = 'rgb(255,125,125)';
            }
            $nosh_data = '<strong>Name:</strong> ' . $arr[$key]['Name'];
            $nosh_data = '<br><strong>Relationship to Patient:</strong> ' . $arr[$key]['Relationship'];
            $nosh_data .= '<br><strong>Status:</strong> ' . $arr[$key]['Status'];
            $nosh_data .= '<br><strong>Date of Birth:</strong> ' . $arr[$key]['Date of Birth'];
            $nosh_data .= '<br><strong>Gender:</strong> ' . $arr[$key]['Gender'];
            $nosh_data .= '<br><strong>Medical History:</strong><ul>';
            $medical_arr = explode("\n", $arr[$key]['Medical']);
            foreach ($medical_arr as $medical_item) {
                $nosh_data .= '<li>' . $medical_item . '</li>';
            }
            $nosh_data .= '</ul>';
            $nosh_data .= '<div class="form-group"><div class="col-md-6 col-md-offset-3">';
            $nosh_data .= '<a href="' . route('family_history_update', [$key]) . '" class="btn btn-success btn-block"><i class="fa fa-btn fa-pencil"></i> Edit</a>';
            $nosh_data .= '<a href="' . route('family_history_update', ['add']) . '" class="btn btn-info btn-block"><i class="fa fa-btn fa-plus"></i> Add new Entry</a></div></div>';
            $node = [
                'id' => $key,
                'label' => $arr[$key]['Name'],
                'size' => 5,
                'color' => $color,
                'nosh_url' => route('family_history_update', [$key]),
                'nosh_data' => $nosh_data
            ];
            if (array_key_exists($arr[$key]['Relationship'], $rel_arr)) {
                $parents_arr = $rel_arr[$arr[$key]['Relationship']];
                if (isset($rel_arr[$arr[$key]['Relationship']][4])) {
                    $node['sibling_group'] = $rel_arr[$arr[$key]['Relationship']][4];
                }
                $node['y'] = $rel_arr[$arr[$key]['Relationship']][3];
            } else {
                if ($arr[$key]['Relationship'] == 'Son' || $arr[$key]['Relationship'] == 'Daughter') {
                    $parents1_arr = ['Patient', $arr[$key]['Mother']];
                    if ($patient->sex == 'f') {
                        $parents1_arr = [$arr[$key]['Father'],'Patient'];
                    }
                    $node['y'] = 10;
                    $node['sibling_group'] = 'Children';
                }
                if (in_array($arr[$key]['Relationship'], $rel_arr1)) {
                    $node['y'] = 0;
                    $node['sibling_group'] = 'Partners';
                }
            }
            $nodes_arr[] = $node;
            $orig_x = $key;
        } else {
            // Add root patient since $arr is empty
            $color = 'rgb(125,125,255)';
            if ($patient->sex == 'f') {
                $color = 'rgb(255,125,125)';
            }
            $oh = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
            if ($this->yaml_check($oh->oh_fh)) {
                $nosh_data = '<div class="form-group"><div class="col-md-6 col-md-offset-3">';
                $nosh_data .= '<a href="' . route('family_history_update', ['add']) . '" class="btn btn-info btn-block"><i class="fa fa-btn fa-plus"></i> Add new Entry</a></div></div>';
            } else {
                $nosh_data = $oh->oh_fh;
                $nosh_data .= '<div class="form-group"><div class="col-md-6 col-md-offset-3">';
                $nosh_data .= '<a href="' . route('family_history_update', ['add']) . '" class="btn btn-info btn-block"><i class="fa fa-btn fa-plus"></i> Add new Entry</a></div></div>';
            }
            $nodes_arr[] = [
                'id' => 'patient',
                'label' => $patient->firstname . ' ' . $patient->lastname,
                'size' => 10,
                'color' => $color,
                'nosh_url' => '',
                'nosh_data' => $nosh_data,
                'x' => 0,
                'y' => 0
            ];
            $parents_arr = ['Father', 'Mother', -10];
            $orig_x = 'patient';
        }
        // Build mother and father (all people do) - find them if they exist in YAML
        if (! empty($parents_arr)) {
            $father = array_search($parents_arr[0], array_column($arr, 'Relationship'));
            $mother = array_search($parents_arr[1], array_column($arr, 'Relationship'));
            if ($father) {
                $edges_arr[] = [
                    'id' => $this->gen_uuid(),
                    'source' => $father,
                    'target' => $key,
                    'label' => 'Biological father of',
                    'type' => 'arrow',
                    'size' => 2,
                    'color' => '#bbb'
                ];
            } else {
                // Check if placeholder node already exists
                $placeholder_node = array_search($parents_arr[0], array_column($nodes_arr, 'label'));
                if ($placeholder_node) {
                    $placeholder_id = $nodes_arr[$placeholder_node]['id'];
                } else {
                    // Make node
                    $placeholder_id = 'placeholder_' . $placeholder_count;
                    $nosh_data = '<div class="form-group"><div class="col-md-6 col-md-offset-3">';
                    $nosh_data .= '<a href="' . route('family_history_update', ['add']) . '" class="btn btn-info btn-block"><i class="fa fa-btn fa-plus"></i> Add new Entry</a></div></div>';
                    $nodes_arr[] = [
                        'id' => $placeholder_id,
                        'label' => $parents_arr[0],
                        'size' => 5,
                        'color' => 'rgb(125,125,255)',
                        'nosh_url' => route('family_history_update', ['add']),
                        'nosh_data' => $nosh_data,
                        'orig_x' => $key,
                        'y' => $parents_arr[2]
                    ];
                    $placeholder_count++;
                }
                $edges_arr[] = [
                    'id' => $this->gen_uuid(),
                    'source' => $placeholder_id,
                    'target' => $key,
                    'label' => 'Biological father of',
                    'type' => 'arrow',
                    'size' => 2,
                    'color' => '#bbb'
                ];
            }
            if ($mother) {
                $edges_arr[] = [
                    'id' => $this->gen_uuid(),
                    'source' => $mother,
                    'target' => $key,
                    'label' => 'Biological mother of',
                    'type' => 'arrow',
                    'size' => 2,
                    'color' => '#bbb'
                ];
            } else {
                $placeholder_node = array_search($parents_arr[1], array_column($nodes_arr, 'label'));
                if ($placeholder_node) {
                    $placeholder_id = $nodes_arr[$placeholder_node]['id'];
                } else {
                    // Make node
                    $placeholder_id = 'placeholder_' . $placeholder_count;
                    $nosh_data = '<div class="form-group"><div class="col-md-6 col-md-offset-3">';
                    $nosh_data .= '<a href="' . route('family_history_update', ['add']) . '" class="btn btn-info btn-block"><i class="fa fa-btn fa-plus"></i> Add new Entry</a></div></div>';
                    $nodes_arr[] = [
                        'id' => $placeholder_id,
                        'label' => $parents_arr[1],
                        'size' => 5,
                        'color' => 'rgb(255,125,255)',
                        'nosh_url' => route('family_history_update', ['add']),
                        'nosh_data' => $nosh_data,
                        'orig_x' => $key,
                        'y' => $parents_arr[2]
                    ];
                    $placeholder_count++;
                }
                $edges_arr[] = [
                    'id' => $this->gen_uuid(),
                    'source' => $placeholder_id,
                    'target' => $key,
                    'label' => 'Biological mother of',
                    'type' => 'arrow',
                    'size' => 2,
                    'color' => '#bbb'
                ];
            }
        }
        if (! empty($parents1_arr)) {
            if ($parents1_arr[0] == 'Patient') {
                $edges_arr[] = [
                    'id' => $this->gen_uuid(),
                    'source' => 'patient',
                    'target' => $key,
                    'label' => 'Biological father of',
                    'type' => 'arrow',
                    'size' => 2,
                    'color' => '#bbb'
                ];
                $mother = array_search($parents1_arr[1], array_column($arr, 'Name'));
                if ($mother) {
                    $edges_arr[] = [
                        'id' => $this->gen_uuid(),
                        'source' => $mother,
                        'target' => $key,
                        'label' => 'Biological mother of',
                        'type' => 'arrow',
                        'size' => 2,
                        'color' => '#bbb'
                    ];
                }
            }
            if ($parents1_arr[1] == 'Patient') {
                $edges_arr[] = [
                    'id' => $this->gen_uuid(),
                    'source' => 'patient',
                    'target' => $key,
                    'label' => 'Biological mother of',
                    'type' => 'arrow',
                    'size' => 2,
                    'color' => '#bbb'
                ];
                $father = array_search($parents1_arr[0], array_column($arr, 'Name'));
                if ($father) {
                    $edges_arr[] = [
                        'id' => $this->gen_uuid(),
                        'source' => $father,
                        'target' => $key,
                        'label' => 'Biological father of',
                        'type' => 'arrow',
                        'size' => 2,
                        'color' => '#bbb'
                    ];
                }
            }
        }
        return [$nodes_arr, $edges_arr, $placeholder_count];
    }

    protected function uma_policy($resource_set_id, $email, $name, $scopes, $policy_id='')
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $client_id = $practice->uma_client_id;
        $client_secret = $practice->uma_client_secret;
        $open_id_url = $practice->uma_uri;
        $refresh_token = $practice->uma_refresh_token;
        $oidc1 = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
        $oidc->startSession();
        $oidc->setSessionName('pnosh');
        $oidc1->setUMA(true);
        $oidc1->refreshToken($refresh_token);
        if ($oidc1->getRefreshToken() != '') {
            $refresh_data['uma_refresh_token'] = $oidc1->getRefreshToken();
            DB::table('practiceinfo')->where('practice_id', '=', '1')->update($refresh_data);
            $this->audit('Update');
        }
        $permissions = [
            'claim' => $email,
            'name' => $name,
            'scopes' => $scopes
        ];
        if ($policy_id == '') {
            $oidc1->policy($resource_set_id, $permissions);
        } else {
            $oidc1->update_policy($policy_id, $resource_set_id, $permissions);
        }
        return true;
    }

    protected function uma_resource($scopes, $name='', $icon='', $delete=false)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        if ($practice->uma_refresh_token !== null && $practice->uma_refresh_token !== '') {
            $client_id = $practice->uma_client_id;
            $client_secret = $practice->uma_client_secret;
            $open_id_url = $query->uma_uri;
            $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
            $oidc->startSession();
            $oidc->setSessionName('pnosh');
            $oidc->setUMA(true);
            $oidc->refreshToken($practice->uma_refresh_token);
            $oidc->setUMAType('resource_server');
            if ($oidc->getRefreshToken() != '') {
                $refresh_data['uma_refresh_token'] = $oidc->getRefreshToken();
                DB::table('practiceinfo')->where('practice_id', '=', '1')->update($refresh_data);
                $this->audit('Update');
            }
            $resource_set_id = '';
            foreach ($scope as $scope) {
                if (filter_var($scope, FILTER_VALIDATE_URL)) {
                    $resource = DB::table('uma')->where('scope', '=', $scope)->first();
                    if ($resource) {
                        $resource_set_id = $resource->resource_set_id;
                    }
                }
            }
            if ($delete == true && $resource_set_id !== '') {
                $response = $oidc->delete_resource_set($resource_set_id);
                DB::table('uma')->where('resource_set_id', '=', $resource_set_id)->delete();
                $this->audit('Delete');
            } else {
                if ($resource_set_id !== '') {
                    $response = $oidc->update_resource_set($resource_set_id, $name, $icon, $scopes);
                    DB::table('uma')->where('resource_set_id', '=', $resource_set_id)->delete();
                    $this->audit('Delete');
                } else {
                    $response = $oidc->resource_set($name, $icon, $scopes);
                }
                if (isset($response['resource_set_id'])) {
                    foreach ($scopes as $scope_item) {
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

    protected function uma_resource_process($pre_label, $row_id1, $table, $delete=false)
    {
        $label_arr = explode(';', $pre_label);
        if ($table == 'allergies') {
            $name = 'Allergy from Trustee';
            $icon = 'https://cloud.noshchartingsystem.com/i-allergy.png';
            $scopes = [
                URL::to('/') . '/fhir/AllergyIntolerance/' . $row_id1,
                'view',
                'edit'
            ];
        }
        if ($table == 'documents') {
            $name = 'Document from Trustee';
            $icon = 'https://cloud.noshchartingsystem.com/i-file.png';
            $scopes = [
                URL::to('/') . '/fhir/Binary/' . $row_id1,
                'view',
                'edit'
            ];
        }
        if ($table == 'issues') {
            $name = 'Condition from Trustee';
            $icon = 'https://cloud.noshchartingsystem.com/i-condition.png';
            $scopes = [
                URL::to('/') . '/fhir/Condition/issue_id_' . $row_id1,
                'view',
                'edit'
            ];
        }
        if ($table == 'rx_list') {
            $name = 'Medication from Trustee';
            $icon = 'https://cloud.noshchartingsystem.com/i-pharmacy.png';
            $scopes = [
                URL::to('/') . '/fhir/MedicationStatement/' . $row_id1,
                'view',
                'edit'
            ];
        }
        if ($table == 't_messages') {
            $name = 'Encounter from Trustee';
            $icon = 'https://cloud.noshchartingsystem.com/i-medical-records.png';
            $scopes = [
                URL::to('/') . '/fhir/Encounter/t_messages_id_' . $row_id1,
                'view',
                'edit'
            ];
        }
        foreach ($label_arr as $label) {
            $scopes[] = $label;
        }
        $this->uma_resource($scopes, $name, $icon, $delete);
    }

    protected function update200()
    {
        $data['version'] = '2.0.0';
        // Update version
        DB::table('practiceinfo')->update($data);
        return true;
    }

    protected function vaccine_supplement_alert($practice_id)
    {
        $time_exp = date('Y-m-d H:i:s', time() + (28 * 24 * 60 * 60));
        $return = '';
        $vaccine_alert_query = DB::table('vaccine_inventory')->where('quantity', '<=', '2')->where('practice_id', '=', $practice_id)->get();
        if ($vaccine_alert_query) {
            if ($return != '') {
                $return .= '<br>';
            }
            $return .= '<strong>Vaccines in your inventory that need to be reordered soon:</strong><ul>';
            foreach ($vaccine_alert_query as $vaccine_alert_row) {
                $return .= '<li>' . $vaccine_alert_row->imm_brand . ', Quantity left: ' . $vaccine_alert_row->quantity . '</li>';
            }
            $return .= '</ul>';
        }
        $vaccine_alert_query1 = DB::table('vaccine_inventory')->where('quantity', '!=', '0')->where('imm_expiration', '<', $time_exp)->where('practice_id', '=', $practice_id)->get();
        if ($vaccine_alert_query1) {
            if ($return != '') {
                $return .= '<br>';
            }
            $return .= '<strong>Vaccines in your inventory that will expire soon:</strong><ul>';
            foreach ($vaccine_alert_query1 as $vaccine_alert_row1) {
                $return .= '<li>' . $vaccine_alert_row1->imm_brand . ', Expiration date: ' . date('m/d/Y', $this->human_to_unix($vaccine_alert_row1->imm_expiration)) . '</li>';
            }
            $return .= '</ul>';
        }
        $supplement_alert_query = DB::table('supplement_inventory')->where('quantity1', '<=', '2')->where('practice_id', '=', $practice_id)->get();
        if ($supplement_alert_query) {
            if ($return != '') {
                $return .= '<br>';
            }
            $return .= '<strong>Supplements/Herbs in your inventory that need to be reordered soon:</strong><ul>';
            foreach ($supplement_alert_query as $supplement_alert_row) {
                $return .= '<li>' . $supplement_alert_row->sup_description . ', Quantity left: ' . $supplement_alert_row->quantity1 . '</li>';
            }
            $return .= '</ul>';
        }
        $data['supplement_expire_alert'] = '';
        $supplement_alert_query1 = DB::table('supplement_inventory')->where('quantity1', '!=', '0')->where('sup_expiration', '<', $time_exp)->where('practice_id', '=', $practice_id)->get();
        if ($supplement_alert_query1) {
            if ($return != '') {
                $return .= '<br>';
            }
            $return .= '<strong>Supplements/Herbs in your inventory that will expire soon:</strong><ul>';
            foreach ($supplement_alert_query1 as $supplement_alert_row1) {
                $return .= '<li>' . $supplement_alert_row1->sup_description . ', Expiration date: ' . date('m/d/Y', $this->human_to_unix($supplement_alert_row1->sup_expiration)) . '</li>';
            }
            $return .= '</ul>';
        }
        return $return;
    }

    protected function yaml_check($yaml)
    {
        $check = substr($yaml, 0, 3);
        if ($check == '---') {
            return true;
        } else {
            return false;
        }
    }

    // Future functions
    public function send_api_data($url, $data, $username, $password)
    {
        if (is_array($data)) {
            $data_string = json_encode($data);
        } else {
            $data_string = $data;
        }
        $ch = curl_init($url);
        if ($username != '') {
            curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );
        $result = curl_exec($ch);
        $result_arr = json_decode($result, true);
        if(curl_errno($ch)){
            $result_arr['url_error'] = 'Error:' . curl_error($ch);
        } else {
            $result_arr['url_error'] = '';
        }
        curl_close($ch);
        return $result_arr;
    }

    public function api_sync_data()
    {
        $check = DB::table('demographics_relate')->where('pid', '=', Session::get('pid'))->whereNotNull('api_key')->first();
        if ($check) {

        }
    }
}
