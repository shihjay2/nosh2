<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use Hash;
use HTML;
use Illuminate\Http\Request;
use Response;
use Schema;
use Session;
use URL;

class APIv1Controller extends Controller
{

	/**
	* NOSH ChartingSystem API Functions
	*/

	public function add(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('practice_api_key', '=', $data['api_key'])->first();
		$patient = DB::table('demographics')->first();
		$statusCode = 200;
		if ($practice) {
			$data1 = $data['data'];
			$data1['pid'] = $patient->pid;
			$id = DB::table($data['table'])->insertGetId($data1);
			$this->audit('Add');
			$response = [
				'error' => false,
				'message' => 'Adding data successful',
				'remote_id' => $id
			];
		} else {
			$response = [
				'error' => true,
				'message' => 'Adding data unsuccessful; no practice identified.'
			];
		}
		return response()->json($response, $statusCode);
	}

	public function update(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('practice_api_key', '=', $data['api_key'])->first();
		$patient = DB::table('demographics')->first();
		$statusCode = 200;
		if ($practice) {
			$data1 = $data['data'];
			$data1['pid'] = $patient->pid;
			DB::table($data['table'])->where($data['primary'], '=', $data['remote_id'])->update($data1);
			$this->audit('Update');
			$response = [
				'error' => false,
				'message' => 'Updating data successful',
				'remote_id' => $data['remote_id']
			];
		} else {
			$response = [
				'error' => true,
				'message' => 'Updating data unsuccessful; no practice identified.'
			];
		}
		return response()->json($response, $statusCode);
	}

	public function delete(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('practice_api_key', '=', $data['api_key'])->first();
		$patient = DB::table('demographics')->first();
		$statusCode = 200;
		if ($practice) {
			DB::table($data['table'])->where($data['primary'], '=', $data['remote_id'])->delete();
			$this->audit('Delete');
			$response = [
				'error' => false,
				'message' => 'Deleting data successful',
				'remote_id' => $data['remote_id']
			];
		} else {
			$response = [
				'error' => true,
				'message' => 'Deleting data unsuccessful; no practice identified.'
			];
		}
		return response()->json($response, $statusCode);
	}

	public function practiceregister(Request $request, $api)
	{
		if ($request->isMethod('post')) {
		} else {
			$this->layout->title = "NOSH ChartingSystem Practice Registration";
			$this->layout->style = '';
			$this->layout->script = HTML::script('/js/practiceregister.js');
			$this->layout->content = '';
			$practice = DB::table('practiceinfo')->where('practice_registration_key', '=', $api)->first();
			$base = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
			if ($practice) {
				$data['practice_id'] = $practice->practice_id;
				$data['patient_portal'] = rtrim($base->patient_portal, '/');
				$this->layout->content .= View::make('practiceregister', $data)->render();
			} else {
				$this->layout->content .= '<strong>Registration link timed out or does not exist!</strong><br>';
				$this->layout->content .= '<p>' . HTML::linkRoute('login', 'Click here to re-register to NOSH ChartingSystem') . '</p>';
			}
		}
	}

	public function postPracticeRegister()
	{
		$base_practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		$username = $request->input('username');
		$password = substr_replace(Hash::make($request->input('password')),"$2a",0,3);
		$email = $request->input('email');
		$practice_id = $request->input('practice_id');
		// Insert practice
		$data2 = array(
			'practice_name' => $request->input('practice_name'),
			'street_address1' => $request->input('street_address1'),
			'street_address2' => $request->input('street_address2'),
			'city' => $request->input('city'),
			'state' => $request->input('state'),
			'zip' => $request->input('zip'),
			'phone' => $request->input('phone'),
			'fax' => $request->input('fax'),
			'fax_type' => '',
			'vivacare' => '',
			'practicehandle' => $request->input('practicehandle'),
			'practice_registration_key' => '',
			'practice_registration_timeout' => ''
		);
		DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data2);
		$this->audit('Update');
		$data3 = DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->first();
		// Insert Administrator
		$data1 = array(
			'username' => $username,
			'password' => $password,
			'email' => $email,
			'group_id' => '1',
			'displayname' => 'Administrator',
			'active' => '1',
			'practice_id' => $practice_id
		);
		$user_id = DB::table('users')->insertGetId($data1);
		// Insert default calendar class
		$data8 = array(
			'visit_type' => 'Closed',
			'classname' => 'colorblack',
			'active' => 'y',
			'practice_id' => $practice_id
		);
		DB::table('calendar')->insert($data8);
		$this->default_template($practice_id);
		Auth::attempt(array('username' => $username, 'password' => $request->input('password'), 'active' => '1', 'practice_id' => '1'));
		Session::put('user_id', $user_id);
		Session::put('group_id', '1');
		Session::put('practice_id', $practice_id);
		Session::put('version', $data3->version);
		Session::put('practice_active', $data3->active);
		Session::put('displayname', 'Administrator');
		Session::put('documents_dir', $data3->documents_dir);
		Session::put('patient_centric', $data3->patient_centric);
		echo "OK";
	}

	public function practiceregisternosh($api)
	{
		$practice = DB::table('practiceinfo')->where('practice_registration_key', '=', $api)->first();
		if ($practice) {
			$data = $request->all();
			if ($practice->npi == $data['practice']['npi']) {
				$patient_data['url'] = $data['practice']['patient_portal'];
				DB::table('demographics_relate')->where('practice_id', '=', $practice->practice_id)->update($patient_data);
				$this->audit('Update');
				unset($data['practice']['patient_portal']);
				unset($data['practice']['patient_centric']);
				$data['practice']['practice_registration_key'] = '';
				$data['practice']['practice_registration_timeout'] = '';
				DB::table('practiceinfo')->where('practice_id', '=', $practice->practice_id)->update($data['practice']);
				$this->audit('Update');
				return Response::json(array(
					'error' => false,
					'message' => 'Practice connected',
					'api_key' => $practice->practice_api_key
				),200);
			} else {
				return Response::json(array(
					'error' => true,
					'message' => 'Incorrect NPI number registered for the practice!',
				),200);
			}
			return Response::json(array(
				'error' => true,
				'message' => 'Registration link timed out or does not exist!',
			),200);
		} else {
			return Response::json(array(
				'error' => true,
				'message' => 'Registration link timed out or does not exist!',
			),200);
		}
	}
}
