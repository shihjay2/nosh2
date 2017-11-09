<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Libraries\Phaxio;
use DB;
use File;
use Response;
use URL;

class FaxController extends Controller {

	/**
	* NOSH ChartingSystem Fax System, to be run as a cron job
	*/

	public function fax(Request $request)
	{
		$ret = 'Fax module inactive<br>';
		$query1 = DB::table('practiceinfo')->get();
		foreach ($query1 as $row1) {
			$fax_type = $row1->fax_type;
			$smtp_user = $row1->fax_email;
			$smtp_pass = $row1->fax_email_password;
			$smtp_host = $row1->fax_email_hostname;
			if ($fax_type == 'phaxio') {
				$phaxio_pending = DB::table('sendfax')->where('practice_id', '=', $row1->practice_id)->where('success', '=', '2')->get();
				if ($phaxio_pending) {
					foreach ($phaxio_pending as $phaxio_row) {
						$phaxio = new Phaxio($row1->phaxio_api_key, $row1->phaxio_api_secret);
						$phaxio_result = $phaxio->faxStatus($phaxio_row->command);
						$phaxio_result_array = json_decode($phaxio_result, true);
						if ($phaxio_result_array['data'][0]['status'] == 'success') {
							$fax_update_data['success'] = '1';
							DB::table('sendfax')->where('job_id', '=', $phaxio_row->job_id)->update($fax_update_data);
							$this->audit('Update');
						}
						if ($phaxio_result_array['data'][0]['status'] == 'failure') {
							$fax_update_data['success'] = '0';
							DB::table('sendfax')->where('job_id', '=', $phaxio_row->job_id)->update($fax_update_data);
							$this->audit('Update');
						}
					}
				}
			}
		}
		return $ret;
	}

	public function phaxio(Request $request, $practice_id)
	{
		$row = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
		if ($row->fax_type == 'phaxio') {
			if ($request->input('success') == 'true' && $request->input('direction') == 'received') {
				$received_dir = $row->documents_dir . 'received/' . $practice_id;
				if (! file_exists($received_dir)) {
					mkdir($received_dir, 0777);
				}
				$file = $request->file('filename');
				$result = json_decode($request->input('fax'), true);
				$data['fileDateTime'] = date('Y-m-d H:i:s', $result['completed_at']);
				$data['practice_id'] = $practice_id;
				$data['fileFrom'] = $result['from_number'];
				$data['filePages'] = $result['num_pages'];
				$new_name = $result['id'] . '_' . $result['completed_at'] . '.pdf';
				$path = $received_dir . '/' . $new_name;
				$file->move($received_dir, $new_name);
				$data['fileName'] = $new_name;
				$data['filePath'] = $path;
				DB::table('received')->insert($data);
				$this->audit('Add');
				return "Recieved file";
			} else {
				return "No success";
			}
		} else {
			return "Phaxio module not activated";
		}
	}
}
