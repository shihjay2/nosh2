<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use DB;
use Hash;
use HTML;
use Illuminate\Http\Request;
use Response;
use Schema;
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
		if ($practice) {
			$data1 = $data['data'];
			$data1['pid'] = $patient->pid;
			$id = DB::table($data['table'])->insertGetId($data1);
			$this->audit('Add');
			return Response::json(array(
				'error' => false,
				'message' => 'Adding data successful',
				'remote_id' => $id
			),200);
		} else {
			return Response::json(array(
				'error' => true,
				'message' => 'Adding data unsuccessful; no practice identified.'
			),200);
		}
	}

	public function update(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('practice_api_key', '=', $data['api_key'])->first();
		$patient = DB::table('demographics')->first();
		if ($practice) {
			$data1 = $data['data'];
			$data1['pid'] = $patient->pid;
			DB::table($data['table'])->where($data['primary'], '=', $data['remote_id'])->update($data1);
			$this->audit('Update');
			return Response::json(array(
				'error' => false,
				'message' => 'Updating data successful',
				'remote_id' => $data['remote_id']
			),200);
		} else {
			return Response::json(array(
				'error' => true,
				'message' => 'Updating data unsuccessful; no practice identified.'
			),200);
		}
	}

	public function delete(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('practice_api_key', '=', $data['api_key'])->first();
		$patient = DB::table('demographics')->first();
		if ($practice) {
			DB::table($data['table'])->where($data['primary'], '=', $data['remote_id'])->delete();
			$this->audit('Delete');
			return Response::json(array(
				'error' => false,
				'message' => 'Deleting data successful',
				'remote_id' => $data['remote_id']
			),200);
		} else {
			return Response::json(array(
				'error' => true,
				'message' => 'Deleting data unsuccessful; no practice identified.'
			),200);
		}
	}

	public function practiceregister($api)
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

	public function checkapi($practicehandle)
	{
		if ($practicehandle == '0') {
			$query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		} else {
			$query = DB::table('practiceinfo')->where('practicehandle', '=', $practicehandle)->first();
		}
		$result = 'No';
		if ($query) {
			if (Schema::hasColumn('practiceinfo', 'practice_api_key')) {
				$result = 'Yes';
			}
		}
		echo $result;
	}

	public function registerapi()
	{
		if ($request->input('practicehandle') == '0') {
			$query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		} else {
			$query = DB::table('practiceinfo')->where('practicehandle', '=', $request->input('practicehandle'))->first();
		}
		$practice_id = $query->practice_id;
		$patient_query = DB::table('demographics')->where('lastname', '=', $request->input('lastname'))->where('firstname', '=', $request->input('firstname'))->where('DOB', '=', $request->input('DOB'))->where('sex', '=', $request->input('sex'))->first();
		if ($patient_query) {
			// Patient exists
			$pid = $patient_query->pid;
			$return['status'] = 'Patient already exists, updated API Key and URL';
		} else {
			// If patient doesn't exist, create a new one
			$data = array(
				'lastname' => $request->input('lastname'),
				'firstname' => $request->input('firstname'),
				'middle' => $request->input('middle'),
				'nickname' => $request->input('nickname'),
				'title' => $request->input('title'),
				'sex' => $request->input('sex'),
				'DOB'=> $request->input('DOB'),
				'ss' => $request->input('ss'),
				'race' => $request->input('race'),
				'ethnicity' => $request->input('ethnicity'),
				'language' => $request->input('language'),
				'address' => $request->input('address'),
				'city' => $request->input('city'),
				'state' => $request->input('state'),
				'zip' => $request->input('zip'),
				'phone_home' => $request->input('phone_home'),
				'phone_work' => $request->input('phone_work'),
				'phone_cell' => $request->input('phone_cell'),
				'email' => $request->input('email'),
				'marital_status' => $request->input('marital_status'),
				'partner_name' => $request->input('partner_name'),
				'employer' => $request->input('employer'),
				'emergency_contact' => $request->input('emergency_contact'),
				'emergency_phone' => $request->input('emergency_phone'),
				'reminder_method' => $request->input('reminder_method'),
				'reminder_to' => $request->input('reminder_to'),
				'cell_carrier' => $request->input('cell_carrier'),
				'preferred_provider' => $request->input('preferred_provider'),
				'preferred_pharmacy' => $request->input('preferred_pharmacy'),
				'active' => $request->input('active'),
				'other1' => $request->input('other1'),
				'other2' => $request->input('other2'),
				'caregiver' => $request->input('caregiver'),
				'referred_by' => $request->input('referred_by'),
				'comments' => $request->input('comments'),
				'rcopia_sync' => 'n',
				'race_code' => $request->input('race_code'),
				'ethnicity_code' => $request->input('ethnicity_code'),
				'guardian_lastname' => $request->input('guardian_lastname'),
				'guardian_firstname' => $request->input('guardian_firstname'),
				'guardian_relationship' => $request->input('guardian_relationship'),
				'guardian_code' => $request->input('guardian_code'),
				'guardian_address' => $request->input('guardian_address'),
				'guardian_city' => $request->input('guardian_city'),
				'guardian_state' => $request->input('guardian_state'),
				'guardian_zip' => $request->input('guardian_zip'),
				'guardian_phone_home' => $request->input('guardian_phone_home'),
				'guardian_phone_work' => $request->input('guardian_phone_work'),
				'guardian_phone_cell' => $request->input('guardian_phone_cell'),
				'guardian_email' => $request->input('guardian_email'),
				'lang_code' => $request->input('lang_code')
			);
			$pid = DB::table('demographics')->insertGetId($data);
			$this->audit('Add');
			$data1 = array(
				'billing_notes' => '',
				'imm_notes' => '',
				'pid' => $pid,
				'practice_id' => $practice_id
			);
			DB::table('demographics_notes')->insert($data1);
			$this->audit('Add');
			$data2 = array(
				'pid' => $pid,
				'practice_id' => $practice_id
			);
			DB::table('demographics_relate')->insert($data2);
			$this->audit('Add');
			$directory = $query->documents_dir . $pid;
			mkdir($directory, 0775);
			$return['status'] = 'Patient newly created in the chart.';
		}
		$patient_data = array(
			'api_key' => $request->input('api_key'),
			'url' => $request->input('url')
		);
		DB::table('demographics_relate')->where('pid', '=', $pid)->where('practice_id', '=', $practice_id)->update($patient_data);
		$this->audit('Update');
		return $return;
	}

	public function practice_api(Request $request)
	{
		$url_check = false;
		$url_reason = '';
		$api_key = uniqid('nosh',true);
		$register_code = uniqid();
		$patient_portal = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		$patient = DB::table('demographics')->first();
		$register_data = (array) $patient;
		$register_data['api_key'] = $api_key;
		$register_data['url'] = URL::to('/');
		$pos = stripos($request->input('practice_url'), 'noshchartingsystem.com');
		if ($pos !== false) {
			$url_explode = explode('/', $request->input('practice_url'));
			$url = 'https://noshchartingsystem.com/nosh/checkapi/' . $url_explode[5];
			$url1 = 'https://noshchartingsystem.com/nosh/registerapi';
			$register_data['practicehandle'] = $url_explode[5];
		} else {
			$url = $request->input('practice_url') . '/checkapi/0';
			$url1 = $request->input('practice_url') . '/registerapi';
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
			$return['status'] = 'n';
			//$data_message['temp_url'] = rtrim($patient_portal->patient_portal, '/') . '/practiceregister/' . $register_code;
			$return['message'] = 'Problem with contacting the URL problem for NOSH integration.  Please check if you have NOSH and that the URL provided is correct.';
			if ($url_reason != '') {
				$return['message'] .= '  ' . $url_reason;
			}
		} else {
			$data = array(
				'practice_api_key' => $api_key,
				'active' => 'Y',
				'practice_registration_key' => $register_code,
				'practice_registration_timeout' => time() + 86400,
				'practice_api_url' => $request->input('practice_url')
			);
			$practice_id = Session::get('practice_id');
			DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data);
			$data2 = array(
				'pid' => $patient->pid,
				'practice_id' => $practice_id,
				'api_key' => $api_key
			);
			DB::table('demographics_relate')->insert($data2);
			$this->audit('Add');
			//$data_message['temp_url'] = rtrim($patient_portal->patient_portal, '/') . '/practiceregisternosh/' . $register_code;
			// Send API key to mdNOSH;
			$result = $this->send_api_data($url1, $register_data, '', '');
			$return['status'] = 'y';
			$return['message'] = 'Practice added with NOSH integration.';
			$return['message'] .= '  Response from server: ' . $result['status'];
			$return['url'] = $request->input('practice_url');
		}
		//$this->send_mail('emails.apiregister', $data_message, 'NOSH ChartingSystem API Registration', $request->input('email'), '1');
		echo json_encode($return);
	}

	public function apilogin(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('npi', '=', $data['npi'])->where('practice_api_key', '=', $data['api_key'])->first();
		if ($practice) {
			$password = Hash::make(time());
			$data1 = array(
				'username' => $practice->practice_api_key,
				'password' => $password,
				'group_id' => '99',
				'practice_id' => $practice->practice_id
			);
			DB::table('users')->insert($data1);
			$this->audit('Add');
			return Response::json(array(
				'error' => false,
				'message' => 'Login successful',
				'username' => $practice->practice_api_key,
				'password' => $password
			),200);
		} else {
			return Response::json(array(
				'error' => true,
				'message' => 'Login incorrect!'
			),200);
		}
	}

	public function apilogout(Request $request)
	{
		$data = $request->all();
		$practice = DB::table('practiceinfo')->where('npi', '=', $data['npi'])->where('practice_api_key', '=', $data['api_key'])->first();
		if ($practice) {
			DB::table('users')->where('group_id', '=', '99')->where('practice_id', '=', $practice->practice_id)->delete();
			$this->audit('Delete');
			return Response::json(array(
				'error' => false,
				'message' => 'Logout successful'
			),200);
		} else {
			return Response::json(array(
				'error' => true,
				'message' => 'Login incorrect!'
			),200);
		}
	}

	// HIE of One Functions
	public function postConnectedPractices()
	{
		$pid = Session::get('pid');
		$page = Input::get('page');
		$limit = Input::get('rows');
		$sidx = Input::get('sidx');
		$sord = Input::get('sord');
		$query = DB::table('practiceinfo')->where('patient_centric', '=', 'yp');
		$result = $query->get();
		if($result) {
			$count = count($result);
			$total_pages = ceil($count/$limit);
		} else {
			$count = 0;
			$total_pages = 0;
		}
		if ($page > $total_pages) $page=$total_pages;
		$start = $limit*$page - $limit;
		if($start < 0) $start = 0;
		$query->orderBy($sidx, $sord)
			->skip($start)
			->take($limit);
		$query1 = $query->get();
		$response['page'] = $page;
		$response['total'] = $total_pages;
		$response['records'] = $count;
		if ($query1) {
			$response['rows'] = $query1;
		} else {
			$response['rows'] = '';
		}
		echo json_encode($response);
	}

	public function postInvitedUsers()
	{
		$pid = Session::get('pid');
		$page = Input::get('page');
		$limit = Input::get('rows');
		$sidx = Input::get('sidx');
		$sord = Input::get('sord');
		$query = DB::table('uma_invitation')->where('invitation_timeout', '>', time());
		$result = $query->get();
		if($result) {
			$count = count($result);
			$total_pages = ceil($count/$limit);
		} else {
			$count = 0;
			$total_pages = 0;
		}
		if ($page > $total_pages) $page=$total_pages;
		$start = $limit*$page - $limit;
		if($start < 0) $start = 0;
		$query->orderBy($sidx, $sord)
			->skip($start)
			->take($limit);
		$query1 = $query->get();
		$response['page'] = $page;
		$response['total'] = $total_pages;
		$response['records'] = $count;
		if ($query1) {
			$response['rows'] = $query1;
		} else {
			$response['rows'] = '';
		}
		echo json_encode($response);
	}

	public function postGetProviderNosh()
	{
		$query = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
		echo $query->practice_api_url;
	}

	public function postGetPatientResources()
	{
		$query = DB::table('uma')->groupBy('resource_set_id')->get();
		$html = 'No resources registered.';
		if ($query) {
			$html = '<table class="pure-table pure-table-horizontal"><thead><tr><th>Resource</th><th>Edit</th></tr></thead>';
			$row_id = '';
			foreach ($query as $row) {
				if ($row->resource_set_id != $row_id) {
					$name_arr = explode('/', $row->scope);
					$name = $name_arr[5];
					if ($name == 'Patient') {
						$title = 'This resource is your demographic information.';
						$resource = 'Demographics';
					}
					if ($name == 'Medication') {
						$title = 'This resource is the RXNorm medication database.  This is NOT your active medication list.';  //UMA do we need this?
						$resource = 'Medication Database';
					}
					if ($name == 'Practitioner') {
						$title = 'This resource is a list of your participating medical providers.';
						$resource = 'Providers';
					}
					if ($name == 'Condition') {
						$title = 'This resource is a list of your medical history, active problem list, surgical history, and encounter diagnoses.';
						$resource = 'Conditions';
					}
					if ($name == 'MedicationStatement') {
						$title = 'This resource is a list of your active medications.';
						$resource = 'Medications';
					}
					if ($name == 'AllergyIntolerance') {
						$title = 'This resource is a list of your allergies.';
						$resource = 'Allergies';
					}
					if ($name == 'Immunization') {
						$title = 'This resource is a list of your immunizations given.';
						$resource = 'Immunizations';
					}
					if ($name == 'Encounter') {
						$title = 'This resource is a list of your medical encounters.';
						$resource = 'Encounters';
					}
					if ($name == 'FamilyHistory') {
						$title = 'This resource is your family medical history.';
						$resource = 'Family History';
					}
					if ($name == 'Binary') {
						$title = 'This resource is your list of associated medical documents in PDF format';
						$resource = 'Documents';
					}
					if ($name == 'Observation') {
						$title = 'This resource is your list of vital signs and test results';
						$resource = 'Observation';
					}
					$html .= '<tr class="uma_table1"><td><span class="nosh_tooltip" title="' . $title . '">' . $resource . '</span></td><td><i class="fa fa-pencil-square-o fa-fw fa-2x view_uma_users nosh_tooltip" style="vertical-align:middle;padding:2px" title="Add/Edit Permitted Users" nosh-id="' . $row->resource_set_id . '"></i></td></tr>';
					$row_id = $row->resource_set_id;
				}
			}
			$html .= '</table>';
		}
		echo $html;
	}

	public function postGetPatientResources1()
	{
		$query = DB::table('uma')->groupBy('resource_set_id')->get();
		$html = 'No resources registered.';
		if ($query) {
			$i = 0;
			$row_id = '';
			$html = '';
			foreach ($query as $row) {
				if ($row->resource_set_id != $row_id) {
					$name_arr = explode('/', $row->scope);
					$name = $name_arr[5];
					if ($name == 'Patient') {
						$resource = 'Demographics';
					}
					if ($name == 'Medication') {
						$resource = 'Medication Database';
					}
					if ($name == 'Practitioner') {
						$resource = 'Providers';
					}
					if ($name == 'Condition') {
						$resource = 'Conditions';
					}
					if ($name == 'MedicationStatement') {
						$resource = 'Medications';
					}
					if ($name == 'AllergyIntolerance') {
						$resource = 'Allergies';
					}
					if ($name == 'Immunization') {
						$resource = 'Immunizations';
					}
					if ($name == 'Encounter') {
						$resource = 'Encounters';
					}
					if ($name == 'FamilyHistory') {
						$resource = 'Family History';
					}
					if ($name == 'Binary') {
						$resource = 'Documents';
					}
					if ($name == 'Observation') {
						$resource = 'Observation';
					}
					$html .= '<label for="mdnosh_provider_' . $i . '" class="pure-checkbox" style="display:block;margin-left:20px;">';
					$html .= Form::checkbox('resources[]', $row->resource_set_id, true, ['id' => 'mdnosh_resource_' . $i, 'style' => 'float:left; margin-left:-20px; margin-right:7px;', 'class' => 'mdnosh_resource_select']);
					$html .= ' ' . $resource;
					$html .= '</label>';
					$i++;
					$row_id = $row->resource_set_id;
				}
			}
		}
		echo $html;
	}

	public function postGetPatientResourceUsers($resource_set_id)
	{
		$result = $this->get_uma_policy($resource_set_id);
		echo $result;
	}

	public function postSendUmaInvite()
	{
		$resource_set_ids = implode(',', Input::get('resources'));
		$data = array(
			'email' => Input::get('email'),
			'name' => Input::get('name'),
			'invitation_timeout' => time() + 259200,
			'resource_set_ids' => $resource_set_ids
		);
		DB::table('uma_invitation')->insert($data);
		$this->audit('Add');
		// $invite_url = 'http://textbelt.com/text';
		// $message2 = http_build_query([
		// 	'number' => Input::get('email'),
		// ]);
		// $ch1 = curl_init();
		// curl_setopt($ch1, CURLOPT_URL, $invite_url);
		// curl_setopt($ch1, CURLOPT_POST, 1);
		// curl_setopt($ch1, CURLOPT_POSTFIELDS, $message);
		// curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
		// $output1 = curl_exec ($ch1);
		// curl_close ($ch1);
		// $data_message['temp_url'] = URL::to('oidc');
		$data_message['temp_url'] = URL::to('/');
		$data_message['patient'] = Session::get('displayname');
		if (filter_var(Input::get('email'), FILTER_VALIDATE_EMAIL)) {
			$mesg  = $this->send_mail('emails.apiregister', $data_message, 'URGENT: Invitation to access the personal electronic medical record of ' . Session::get('displayname'), Input::get('email'), '1');
		} else {

			$message = "You've been invited to use" . $data_message['patient'] . "'s personal health record.  Go to " . $data_message['temp_url'] . " to register";
			$url = 'http://textbelt.com/text';
			$message1 = http_build_query([
				'number' => Input::get('email'),
				'message' => $message
			]);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $message1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$output = curl_exec ($ch);
			curl_close ($ch);
			//return $output;
		}
		echo 'Invitation sent to ' . Input::get('email') . '!';
	}

	public function postRemovePatientResourceUser()
	{
		$open_id_url = str_replace('/nosh', '', URL::to('/'));
		$practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		$client_id = $practice->uma_client_id;
		$client_secret = $practice->uma_client_secret;
		$refresh_token = $practice->uma_refresh_token;
		$oidc1 = new OpenIDConnectClient($open_id_url, $client_id, $client_secret);
		$oidc1->refresh($refresh_token,true);
		if ($oidc1->getRefreshToken() != '') {
			$refresh_data['uma_refresh_token'] = $oidc1->getRefreshToken();
			DB::table('practiceinfo')->where('practice_id', '=', '1')->update($refresh_data);
			$this->audit('Update');
		}
		$response = $oidc1->delete_policy(Input::get('policy_id'));
		$result = $this->get_uma_policy(Input::get('resource_set_id'));
		echo $result;
	}

	public function postEditPolicy()
	{
		$scopes = explode(' ', Input::get('scopes'));
		if (Input::get('action') == 'edit') {
			$scopes[] = 'edit';
		}
		if (Input::get('action') == 'show') {
			$key = array_search('edit', $scopes);
			if($key!==false){
				unset($scopes[$key]);
			}
		}
		$result['message'] = $this->uma_policy(Input::get('resource_set_id'),Input::get('email'),$scopes,Input::get('policy_id'));
		$result['html'] = $this->get_uma_policy(Input::get('resource_set_id'));
		echo json_encode($result);
	}
}
