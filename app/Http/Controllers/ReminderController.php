<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use URL;
use Response;
use App\Http\Requests;

class ReminderController extends Controller {

	/**
	* NOSH ChartingSystem Reminder System, to be run as a cron job
	*/

	public function reminder()
	{
		$start = time();
		$end = $start + (2 * 24 * 60 * 60);
		$query1 = DB::table('schedule')
			->join('demographics', 'schedule.pid', '=', 'demographics.pid')
			->select('demographics.reminder_to', 'demographics.reminder_method', 'demographics.reminder_interval', 'schedule.appt_id', 'schedule.provider_id', 'schedule.start')
			->where('schedule.status', '=', 'Pending')
			->whereBetween('schedule.start', [$start, $end])
			->get();
		$j=0;
		$i=0;
		if ($query1->count()) {
			foreach ($query1 as $row) {
				$to = $row->reminder_to;
				$row0 = DB::table('users')->where('id', '=', $row->provider_id)->first();
				$row2 = DB::table('practiceinfo')->where('practice_id', '=', $row0->practice_id)->first();
				$proceed = true;
				if ($row2->reminder_interval !== 'Default' && $row2->reminder_interval !== null && $row2->reminder_interval !== '') {
					$practice_end = $start + ((int)$row2->reminder_interval * 60 * 60);
					if ($row->start > $practice_end) {
						$proceed = false;
					}
				}
				if ($row->reminder_interval !== 'Default' && $row->reminder_interval !== null && $row->reminder_interval !== '') {
					$patient_end = $start + ((int)$row->reminder_interval * 60 * 60);
					if ($row->start > $patient_end) {
						$proceed = false;
					}
				}
				if ($proceed == true) {
					if ($to != '') {
						if ($row2->timezone != null) {
							date_default_timezone_set($row2->timezone);
						}
						$data_message['startdate'] = date("F j, Y, g:i a", $row->start);
						$data_message['startdate1'] = date("Y-m-d, g:i a", $row->start);
						$data_message['displayname'] = $row0->displayname;
						$data_message['phone'] = $row2->phone;
						$data_message['email'] = $row2->email;
						$data_message['additional_message'] = $row2->additional_message;
						if ($row->reminder_method == 'Cellular Phone') {
							$message = view('emails.remindertext', $data_message)->render();
	                        $this->textbelt($to, $message, $row0->practice_id);
						} else {
							$this->send_mail('emails.reminder', $data_message, 'Appointment Reminder', $to, $row0->practice_id);
						}
						$data['status'] = 'Reminder Sent';
						DB::table('schedule')->where('appt_id', '=', $row->appt_id)->update($data);
						$this->audit('Add');
						$i++;
					}
				}
				$j++;
			}
		}
		$arr = "Number of appointments: " . $j . "<br>";
		$arr .= "Number of appointment reminders sent: " . $i . "<br>";
		$query3 = DB::table('practiceinfo')->get();
		if ($query3->count()) {
			foreach ($query3 as $practice_row) {
				$results_scan=0;
				$birthday_count=0;
				$appointment_count=0;
				$appointment_count1=0;
				$arr .= "<strong>Practice " . $practice_row->practice_id ."</strong><br>";
				//$updox = $this->check_extension('updox_extension', $practice_row->practice_id);
				//if ($updox) {
					//$this->updox_sync($practice_row->practice_id);
				//}
				// $rcopia = $this->check_extension('rcopia_extension', $practice_row->practice_id);
				// if ($rcopia) {
				// 	$this->rcopia_sync($practice_row->practice_id);
				// }
				$results_scan = $this->get_scans($practice_row->practice_id);
				$birthday = $this->check_extension('birthday_extension', $practice_row->practice_id);
				if ($birthday) {
					$birthday1 = DB::table('practiceinfo')->where('practice_id', '=', $practice_row->practice_id)->first();
					if ($birthday1->timezone != null) {
						date_default_timezone_set($birthday1->timezone);
					}
					$date = date('Y-m-d');
					if ($birthday1->birthday_sent_date != $date) {
						$birthday_count = $this->birthday_reminder($practice_row->practice_id);
					}
				}
				$appointment = $this->check_extension('appointment_extension', $practice_row->practice_id);
				if ($appointment) {
					$appointment1 = DB::table('practiceinfo')->where('practice_id', '=', $practice_row->practice_id)->first();
					if ($appointment1->timezone != null) {
						date_default_timezone_set($appointment1->timezone);
					}
					$date1 = date('Y-m-d');
					$appointment_count = $this->appointment_screen($practice_row->practice_id);
					if ($appointment1->appointment_sent_date != $date1) {
						$appointment_count1 = $this->appointment_reminder($practice_row->practice_id);
					}
				}
				$arr .= "Number of documents scanned: " . $results_scan . "<br>";
				$arr .= "Number of birthday announcements: " . $birthday_count . "<br>";
				$arr .= "Number of patients screened needing apointments: " . $appointment_count1 . "<br>";
				$arr .= "Number of patients reminders sent to make appointment: " . $appointment_count1 . "<br>";
			}
		}
		// $results_count = $this->get_results();
		$results_alert = $this->alert_message_send();
		$results_practice_clean = $this->clean_practice();
		$results_api = $this->api_process();
		$this->clean_temp_dir();
		// $arr .= "Number of results obtained: " . $results_count . "<br>";
		$arr .= "Number of alerts sent: " . $results_alert . "<br>";
		$arr .= "Number of unused practices cleaned: " . $results_practice_clean . "<br>";
		$arr .= "Number of commands sent via API: " . $results_api . "<br><br>";
		return $arr;
	}
}
