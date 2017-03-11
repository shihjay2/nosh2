<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use URL;
use Response;
use App\Http\Requests;

class AjaxInstallController extends Controller {

	/**
	* NOSH ChartingSystem Installation Ajax Functions
	*/

	public function postInstallProcess($type)
	{
		set_time_limit(0);
		ini_set('memory_limit','196M');
		$smtp_user = Input::get('smtp_user');
		$username = Input::get('username');
		$password = substr_replace(Hash::make(Input::get('password')),"$2a",0,3);
		$email = Input::get('email');
		if ($type == 'practice') {
			$practice_name = Input::get('practice_name');
			$street_address1 = Input::get('street_address1');
			$street_address2 = Input::get('street_address2');
			$phone = Input::get('phone');
			$fax = Input::get('fax');
			$patient_centric = 'n';
		} else {
			$practice_name = "NOSH for Patient: " . Input::get('firstname') . ' ' . Input::get('lastname');
			$street_address1 = Input::get('address');
			$street_address2 = '';
			$phone = '';
			$fax = '';
			$patient_centric = 'y';
		}
		$city = Input::get('city');
		$state = Input::get('state');
		$zip = Input::get('zip');
		$documents_dir = Input::get('documents_dir');
		// Clean up documents directory string
		$check_string = substr($documents_dir, -1);
		if ($check_string != '/') {
			$documents_dir .= '/';
		}
		// Insert Administrator
		$data1 = array(
			'username' => $username,
			'password' => $password,
			'email' => $email,
			'group_id' => '1',
			'displayname' => 'Administrator',
			'active' => '1',
			'practice_id' => '1'
		);
		$user_id = DB::table('users')->insertGetId($data1);
		// Insert practice
		$data2 = array(
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
			'smtp_user' => $smtp_user,
			'vivacare' => '',
			'version' => '1.8.4',
			'active' => 'Y',
			'patient_centric' => $patient_centric
		);
		DB::table('practiceinfo')->insert($data2);
		// Insert patient
		if ($type == 'patient') {
			$dob = date('Y-m-d', strtotime(Input::get('DOB')));
			$displayname = Input::get('firstname') . " " . Input::get('lastname');
			$patient_data = array(
				'lastname' => Input::get('lastname'),
				'firstname' => Input::get('firstname'),
				'DOB' => $dob,
				'sex' => Input::get('gender'),
				'active' => '1',
				'sexuallyactive' => 'no',
				'tobacco' => 'no',
				'pregnant' => 'no',
				'address' => $street_address1,
				'city' => $city,
				'state' => $state,
				'zip' => $zip
			);
			$pid = DB::table('demographics')->insertGetId($patient_data);
			$this->audit('Add');
			$patient_data1 = array(
				'billing_notes' => '',
				'imm_notes' => '',
				'pid' => $pid,
				'practice_id' => '1'
			);
			DB::table('demographics_notes')->insert($patient_data1);
			$this->audit('Add');
			$patient_data2 = array(
				'username' => Input::get('pt_username'),
				'firstname' => Input::get('firstname'),
				'middle' => '',
				'lastname' => Input::get('lastname'),
				'title' => '',
				'displayname' => $displayname,
				'email' => Input::get('email'),
				'group_id' => '100',
				'active'=> '1',
				'practice_id' => '1',
				'password' => $this->gen_uuid()
			);
			$patient_user_id = DB::table('users')->insertGetId($patient_data2);
			$patient_data3 = array(
				'pid' => $pid,
				'practice_id' => '1',
				'id' => $patient_user_id
			);
			DB::table('demographics_relate')->insert($patient_data3);
			$this->audit('Add');
			$directory = $documents_dir . $pid;
			mkdir($directory, 0775);
		} else {
			$displayname = 'Administrator';
		}
		// Insert groups
		$data3 = array(
			'id' => '1',
			'title' => 'admin',
			'description' => 'Administrator'
		);
		$data4 = array(
			'id' => '2',
			'title' => 'provider',
			'description' => 'Provider'
		);
		$data5 = array(
			'id' => '3',
			'title' => 'assistant',
			'description' => 'Assistant'
		);
		$data6 = array(
			'id' => '4',
			'title' => 'billing',
			'description' => 'Billing'
		);
		$data7 = array(
			'id' => '100',
			'title' => 'patient',
			'description' => 'Patient'
		);
		DB::table('groups')->insert(array($data3,$data4,$data5,$data6,$data7));
		// Insert default calendar class
		$data8 = array(
			'visit_type' => 'Closed',
			'classname' => 'colorblack',
			'active' => 'y',
			'practice_id' => '1'
		);
		DB::table('calendar')->insert($data8);
		// Insert default values for procedure template
		$procedurelist_data_array = array();
		$procedurelist_data_array[] = array(
			'procedure_type' => 'Laceration repair',
			'procedure_description' => '',
			'procedure_complications' => 'None.',
			'procedure_ebl' => 'Less than 5 mL.'
		);
		$procedurelist_data_array[] = array(
			'procedure_type' => 'Excision - lesion completely removed',
			'procedure_description' => '',
			'procedure_complications' => 'None.',
			'procedure_ebl' => 'Less than 5 mL.'
		);
		$procedurelist_data_array[] = array(
			'procedure_type' => 'Shave - no penetration of fat, no sutures',
			'procedure_description' => '',
			'procedure_complications' => 'None.',
			'procedure_ebl' => 'Less than 5 mL.'
		);
		$procedurelist_data_array[] = array(
			'procedure_type' => 'Biopsy - lesion partially removed',
			'procedure_description' => '',
			'procedure_complications' => 'None.',
			'procedure_ebl' => 'Less than 5 mL.'
		);
		$procedurelist_data_array[] = array(
			'procedure_type' => 'Skin tag removal',
			'procedure_description' => '' ,
			'procedure_complications' => 'None.',
			'procedure_ebl' => 'Less than 5 mL.'
		);
		$procedurelist_data_array[] = array(
			'procedure_type' => 'Cryotherapy',
			'procedure_description' => '',
			'procedure_complications' => 'None.',
			'procedure_ebl' => 'Less than 5 mL.'
		);
		foreach($procedurelist_data_array as $row1) {
			DB::table('procedurelist')->insert($row1);
		}
		// Insert default values for orders template
		$orderslist_data_array = array();
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Comprehensive metabolic panel (CMP)',
			'snomed' => '167209002'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Complete blood count with platelets and differential (CBC)',
			'snomed' => '117356000'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Antinuclear antibody panel (ANA)',
			'snomed' => '394977005'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Fasting lipid panel',
			'snomed' => '394977005'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Erythrocyte sedimentation rate (ESR)',
			'snomed' => '104155006'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Hemoglobin A1c (HgbA1c)',
			'snomed' => '166902009'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'INR',
			'snomed' => '440685005'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Liver function panel (LFT)',
			'snomed' => '143927001'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Pap smear with HPV testing',
			'snomed' => '119252009'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Pap smear',
			'snomed' => '119252009'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Prostate specific antigen (PSA)',
			'snomed' => '143526001'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Hepatitis C antibody',
			'snomed' => '166123004'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'RPR',
			'snomed' => '19869000'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Peripheral smear',
			'snomed' => '104130000'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Follicle stimulating hormone (FSH)',
			'snomed' => '273971007'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Luteinizing hormone (LH)',
			'snomed' => '69527006'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Follicle stimulating hormone and Leutinizing hormone (FSH and LH)',
			'snomed' => '250660006'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Gonorrhea and Chlamydia GenProbe (GC/Chl PCR)',
			'snomed' => '399143002'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Thyroid stimulating hormone (TSH)',
			'snomed' => '313440008'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Thyroid panel (TSH, T3, Free T4)',
			'snomed' => '35650009'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Urinalysis',
			'snomed' => '53853004'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Urine culture',
			'snomed' => '144792004'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Wound culture',
			'snomed' => '77601007'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Respiratory Allergen Testing',
			'snomed' => '388464003'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Laboratory',
			'orders_description' => 'Herpes Type 2 antibody',
			'snomed' => '117739006'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the abdomen with contrast',
			'snomed' => '32962002'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the abdomen without contrast',
			'snomed' => '169070004'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the chest with contrast',
			'snomed' => '75385009'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the chest without contrast',
			'snomed' => '169069000'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the head with contrast',
			'snomed' => '396207002'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the head without contrast',
			'snomed' => '396205005'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the sinuses',
			'snomed' => '431247005'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the neck with contrast',
			'snomed' => '431326009'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'CT of the neck without contrast',
			'snomed' => '169068008'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'DEXA scan',
			'snomed' => '300004007'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Radiology',
			'orders_description' => 'Bilateral screening mammogram',
			'snomed' => '275980005'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Frequency',
			'orders_description' => 'Once a week'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Frequency',
			'orders_description' => 'Two times a week'
		);
		$orderslist_data_array[] = array(
			'orders_category' => 'Frequency',
			'orders_description' => 'Three times a week'
		);
		foreach($orderslist_data_array as $row2) {
			DB::table('orderslist')->insert($row2);
		}
		$orderslist_data = array(
			'user_id' => '0'
		);
		DB::table('orderslist')->update($orderslist_data);
		// Insert templates
		$template_array = array();
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html":[{"type":"div","class":"letter_buttonset","id":"letter_school_absence_1_div","html":[{"type":"span","html":"Start Date:"},{"type":"br"},{"type":"text","id":"letter_school_absence_1a","class":"letter_date letter_start_date","css":{"width":"200px"},"name":"letter_school_absence_1","caption":""}]},{"type":"br"},{"type":"div","class":"letter_buttonset","id":"letter_school_absence_2_div","html":[{"type":"span","html":"Return Date:"},{"type":"br"},{"type":"text","id":"letter_school_absence_2a","class":"letter_date letter_return_date","css":{"width":"200px"},"name":"letter_school_absence_2","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"Please excuse _firstname from school starting on _start_date.  _firstname can return to school on _return_date.","id":"letter_school_absence_hidden"}]}',
			'group' => 'school_absence',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html":[{"type":"div","class":"letter_buttonset","id":"letter_school_absence_1_div","html":[{"type":"span","html":"Start Date:"},{"type":"br"},{"type":"text","id":"letter_school_absence_1a","class":"letter_date letter_start_date","css":{"width":"200px"},"name":"letter_school_absence_1","caption":""}]},{"type":"br"},{"type":"div","class":"letter_buttonset","id":"letter_school_absence_2_div","html":[{"type":"span","html":"Return Date:"},{"type":"br"},{"type":"text","id":"letter_school_absence_2a","class":"letter_date letter_return_date","css":{"width":"200px"},"name":"letter_school_absence_2","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"Please excuse _firstname from school starting on _start_date.  _firstname can return to school on _return_date.","id":"letter_school_absence_hidden"}]}',
			'group' => 'school_absence',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html": [{"type":"div","class":"letter_buttonset","id":"letter_school_return_1_div","html": [{"type":"span","html":"Return Date:"},{"type":"br"},{"type":"text","id":"letter_school_return_1a","class":"letter_date letter_return_date","css": {"width":"200px"},"name":"letter_school_return_1","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"_firstname can return to school on _return_date.","id":"letter_school_return_hidden"}]}',
			'group' => 'school_return',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html": [{"type":"div","class":"letter_buttonset","id":"letter_school_return_1_div","html": [{"type":"span","html":"Return Date:"},{"type":"br"},{"type":"text","id":"letter_school_return_1a","class":"letter_date letter_return_date","css": {"width":"200px"},"name":"letter_school_return_1","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"_firstname can return to school on _return_date.","id":"letter_school_return_hidden"}]}',
			'group' => 'school_return',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html":[{"type":"div","class":"letter_buttonset","id":"letter_work_absence_1_div","html":[{"type":"span","html":"Start Date:"},{"type":"br"},{"type":"text","id":"letter_work_absence_1a","class":"letter_date letter_start_date","css":{"width":"200px"},"name":"letter_work_absence_1","caption":""}]},{"type":"br"},{"type":"div","class":"letter_buttonset","id":"letter_work_absence_2_div","html":[{"type":"span","html":"Return Date:"},{"type":"br"},{"type":"text","id":"letter_work_absence_2a","class":"letter_date letter_return_date","css":{"width":"200px"},"name":"letter_work_absence_2","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"Please excuse _firstname from work starting on _start_date.  _firstname can return to work on _return_date.","id":"letter_work_absence_hidden"}]}',
			'group' => 'work_absence',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html":[{"type":"div","class":"letter_buttonset","id":"letter_work_absence_1_div","html":[{"type":"span","html":"Start Date:"},{"type":"br"},{"type":"text","id":"letter_work_absence_1a","class":"letter_date letter_start_date","css":{"width":"200px"},"name":"letter_work_absence_1","caption":""}]},{"type":"br"},{"type":"div","class":"letter_buttonset","id":"letter_work_absence_2_div","html":[{"type":"span","html":"Return Date:"},{"type":"br"},{"type":"text","id":"letter_work_absence_2a","class":"letter_date letter_return_date","css":{"width":"200px"},"name":"letter_work_absence_2","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"Please excuse _firstname from work starting on _start_date.  _firstname can return to work on _return_date.","id":"letter_work_absence_hidden"}]}',
			'group' => 'work_absence',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html": [{"type":"div","class":"letter_buttonset","id":"letter_work_return_1_div","html": [{"type":"span","html":"Return Date:"},{"type":"br"},{"type":"text","id":"letter_work_return_1a","class":"letter_date letter_return_date","css": {"width":"200px"},"name":"letter_work_return_1","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"_firstname can return to work on _return_date.","id":"letter_work_return_hidden"}]}',
			'group' => 'work_return',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html": [{"type":"div","class":"letter_buttonset","id":"letter_work_return_1_div","html": [{"type":"span","html":"Return Date:"},{"type":"br"},{"type":"text","id":"letter_work_return_1a","class":"letter_date letter_return_date","css": {"width":"200px"},"name":"letter_work_return_1","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"_firstname can return to work on _return_date.","id":"letter_work_return_hidden"}]}',
			'group' => 'work_return',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html": [{"type":"div","class":"letter_buttonset","id":"letter_work_modified_1_div","html": [{"type":"span","html":"Start Date:"},{"type":"br"},{"type":"text","id":"letter_work_modified_1a","class":"letter_date letter_start_date","css": {"width":"200px"},"name":"letter_work_modified_1","caption":""}]},{"type":"br"},{"type":"div","class":"letter_buttonset","id":"letter_work_modified_2_div","html": [{"type":"span","html":"End Date:"},{"type":"br"},{"type":"text","id":"letter_work_modified_2a","class":"letter_date letter_end_date","css": {"width":"200px"},"name":"letter_work_modified_2","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"_firstname should begin the following modified work restrictions starting on _start_date and ending on _end_date.","id":"letter_work_modified_hidden"},{"type":"div","class":"letter_buttonset","id":"letter_work_modified_3_div","html": [{"type":"span","html":"Select from list:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"letter_work_modified_3a","class":"letter_select","css": {"width":"200px"},"name":"letter_work_modified_3","caption":"","options": {"shoulder": {"type":"optgroup","label":"Shoulder","options": {"Limited use of the right shoulder.  ":"Limited use of the right shoulder.","No use of the right shoulder.  ":"No use of the right shoulder.","Limited use of the left shoulder.  ":"Limited use of the left shoulder.","No use of the left shoulder.  ":"No use of the left shoulder.","Limited use of both shoulders.  ":"Limited use of both shoulders.","No use of both shoulders.  ":"No use of both shoulders."}},"arm": {"type":"optgroup","label":"Arm","options": {"Limited use of the right arm.  ":"Limited use of the right arm.","No use of the right arm.  ":"No use of the right arm.","Limited use of the left arm.  ":"Limited use of the left arm.","No use of the left arm.  ":"No use of the left arm.","Limited use of both arms.  ":"Limited use of both arms.","No use of both arms.  ":"No use of both arms."}},"hand": {"type":"optgroup","label":"Hand","options": {"Limited use of the right hand.  ":"Limited use of the right hand.","No use of the right hand.  ":"No use of the right hand.","Limited use of the left hand.  ":"Limited use of the left hand.","No use of the left hand.  ":"No use of the left hand.","Limited use of both hands.  ":"Limited use of both hands.","No use of both hands.  ":"No use of both hands."}},"leg": {"type":"optgroup","label":"Leg","options": {"Limited use of the right leg.  ":"Limited use of the right leg.","No use of the right leg.  ":"No use of the right leg.","Limited use of the left leg.  ":"Limited use of the left leg.","No use of the left leg.  ":"No use of the left leg.","Limited use of both legs.  ":"Limited use of both legs.","No use of both legs.  ":"No use of both legs."}},"device": {"type":"optgroup","label":"Devices","options": {"Need to use splint provided while at work.  ":"Need to use splint provided while at work.","Need to use crutches provided while at work.  ":"Need to use crutches provided while at work.","Need to use back brace provided while at work.":"Need to use back brace provided while at work."}},"actions": {"type":"optgroup","label":"Actions","options": {"Limited bending.  ":"Limited bending.","No bending.  ":"No bending.","Limited climbing.  ":"Limited climbing.","No climbing.  ":"No climbing.","Limited heavy lifting.  ":"Limited heavy lifting.","No heavy lifting.  ":"No heavy lifting.","Limited overhead reaching.  ":"Limited overhead reaching.","No overhead reaching.  ":"No overhead reaching.","Limited pulling.  ":"Limited pulling.","No pulling.  ":"No pulling.","Limited pushing.  ":"Limited pushing.","No pushing.  ":"No pushing.","Limited squatting.  ":"Limited squatting.","No squatting.  ":"No squatting.","Limited standing.  ":"Limited standing.","No standing.  ":"No standing","Limited stooping.  ":"Limited stooping.","No stooping.  ":"No stooping.","Limited twisting.  ":"Limited twisting.","No twisting.  ":"No twisting.","Limited weight bearing.  ":"Limited weight bearing.","No weight bearing.  ":"No weight bearing.","Limited work near moving machinery.  ":"Limited work near moving machinery.","No work near moving machinery.  ":"No work near moving machinery.","Limited work requiring depth perception.  ":"Limited work requiring depth perception.","No work requiring depth perception.  ":"No work requiring depth perception."}}}}]}]}',
			'group' => 'work_modified',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'letter',
			'json' => '{"html": [{"type":"div","class":"letter_buttonset","id":"letter_work_modified_1_div","html": [{"type":"span","html":"Start Date:"},{"type":"br"},{"type":"text","id":"letter_work_modified_1a","class":"letter_date letter_start_date","css": {"width":"200px"},"name":"letter_work_modified_1","caption":""}]},{"type":"br"},{"type":"div","class":"letter_buttonset","id":"letter_work_modified_2_div","html": [{"type":"span","html":"End Date:"},{"type":"br"},{"type":"text","id":"letter_work_modified_2a","class":"letter_date letter_end_date","css": {"width":"200px"},"name":"letter_work_modified_2","caption":""}]},{"type":"hidden","class":"letter_hidden","value":"_firstname should begin the following modified work restrictions starting on _start_date and ending on _end_date.","id":"letter_work_modified_hidden"},{"type":"div","class":"letter_buttonset","id":"letter_work_modified_3_div","html": [{"type":"span","html":"Select from list:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"letter_work_modified_3a","class":"letter_select","css": {"width":"200px"},"name":"letter_work_modified_3","caption":"","options": {"shoulder": {"type":"optgroup","label":"Shoulder","options": {"Limited use of the right shoulder.  ":"Limited use of the right shoulder.","No use of the right shoulder.  ":"No use of the right shoulder.","Limited use of the left shoulder.  ":"Limited use of the left shoulder.","No use of the left shoulder.  ":"No use of the left shoulder.","Limited use of both shoulders.  ":"Limited use of both shoulders.","No use of both shoulders.  ":"No use of both shoulders."}},"arm": {"type":"optgroup","label":"Arm","options": {"Limited use of the right arm.  ":"Limited use of the right arm.","No use of the right arm.  ":"No use of the right arm.","Limited use of the left arm.  ":"Limited use of the left arm.","No use of the left arm.  ":"No use of the left arm.","Limited use of both arms.  ":"Limited use of both arms.","No use of both arms.  ":"No use of both arms."}},"hand": {"type":"optgroup","label":"Hand","options": {"Limited use of the right hand.  ":"Limited use of the right hand.","No use of the right hand.  ":"No use of the right hand.","Limited use of the left hand.  ":"Limited use of the left hand.","No use of the left hand.  ":"No use of the left hand.","Limited use of both hands.  ":"Limited use of both hands.","No use of both hands.  ":"No use of both hands."}},"leg": {"type":"optgroup","label":"Leg","options": {"Limited use of the right leg.  ":"Limited use of the right leg.","No use of the right leg.  ":"No use of the right leg.","Limited use of the left leg.  ":"Limited use of the left leg.","No use of the left leg.  ":"No use of the left leg.","Limited use of both legs.  ":"Limited use of both legs.","No use of both legs.  ":"No use of both legs."}},"device": {"type":"optgroup","label":"Devices","options": {"Need to use splint provided while at work.  ":"Need to use splint provided while at work.","Need to use crutches provided while at work.  ":"Need to use crutches provided while at work.","Need to use back brace provided while at work.":"Need to use back brace provided while at work."}},"actions": {"type":"optgroup","label":"Actions","options": {"Limited bending.  ":"Limited bending.","No bending.  ":"No bending.","Limited climbing.  ":"Limited climbing.","No climbing.  ":"No climbing.","Limited heavy lifting.  ":"Limited heavy lifting.","No heavy lifting.  ":"No heavy lifting.","Limited overhead reaching.  ":"Limited overhead reaching.","No overhead reaching.  ":"No overhead reaching.","Limited pulling.  ":"Limited pulling.","No pulling.  ":"No pulling.","Limited pushing.  ":"Limited pushing.","No pushing.  ":"No pushing.","Limited squatting.  ":"Limited squatting.","No squatting.  ":"No squatting.","Limited standing.  ":"Limited standing.","No standing.  ":"No standing","Limited stooping.  ":"Limited stooping.","No stooping.  ":"No stooping.","Limited twisting.  ":"Limited twisting.","No twisting.  ":"No twisting.","Limited weight bearing.  ":"Limited weight bearing.","No weight bearing.  ":"No weight bearing.","Limited work near moving machinery.  ":"Limited work near moving machinery.","No work near moving machinery.  ":"No work near moving machinery.","Limited work requiring depth perception.  ":"Limited work requiring depth perception.","No work requiring depth perception.  ":"No work requiring depth perception."}}}}]}]}',
			'group' => 'work_modified',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Referral - Please provide primary physician with summaries of subsequent visits.","id":"ref_referral_hidden"},{"type":"checkbox","id":"ref_referral_1","class":"ref_other ref_intro","value":"Assume management for this particular problem and return patient after conclusion of care.","name":"ref_referral_1","caption":"Return patient after managing particular problem"},{"type":"br"},{"type":"checkbox","id":"ref_referral_2","class":"ref_other ref_intro","value":"Assume future management of patient within your area of expertise.","name":"ref_referral_2","caption":"Future ongoing management"},{"type":"br"},{"type":"checkbox","id":"ref_referral_3","class":"ref_other ref_after","value":"Please call me when you have seen the patient.","name":"ref_referral_3","caption":"Call back"},{"type":"br"},{"type":"checkbox","id":"ref_referral_4","class":"ref_other ref_after","value":"I would like to receive periodic status reports on this patient.","name":"ref_referral_4","caption":"Receive periodic status reports"},{"type":"br"},{"type":"checkbox","id":"ref_referral_5","class":"ref_other ref_after","value":"Please send a thorough written report when the consultation is complete.","name":"ref_referral_5","caption":"Receive thorough written report"}]}',
			'group' => 'referral',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Referral - Please provide primary physician with summaries of subsequent visits.","id":"ref_referral_hidden"},{"type":"checkbox","id":"ref_referral_1","class":"ref_other ref_intro","value":"Assume management for this particular problem and return patient after conclusion of care.","name":"ref_referral_1","caption":"Return patient after managing particular problem"},{"type":"br"},{"type":"checkbox","id":"ref_referral_2","class":"ref_other ref_intro","value":"Assume future management of patient within your area of expertise.","name":"ref_referral_2","caption":"Future ongoing management"},{"type":"br"},{"type":"checkbox","id":"ref_referral_3","class":"ref_other ref_after","value":"Please call me when you have seen the patient.","name":"ref_referral_3","caption":"Call back"},{"type":"br"},{"type":"checkbox","id":"ref_referral_4","class":"ref_other ref_after","value":"I would like to receive periodic status reports on this patient.","name":"ref_referral_4","caption":"Receive periodic status reports"},{"type":"br"},{"type":"checkbox","id":"ref_referral_5","class":"ref_other ref_after","value":"Please send a thorough written report when the consultation is complete.","name":"ref_referral_5","caption":"Receive thorough written report"}]}',
			'group' => 'referral',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Consultation - Please send the patient back for follow-up and treatment.","id":"ref_consultation_hidden"},{"type":"checkbox","id":"ref_consultation_1","class":"ref_other ref_intro","value":"Confirm the diagnosis.","name":"ref_consultation_1","caption":"Confirm the diagnosis"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_2","class":"ref_other ref_intro","value":"Advise as to the diagnosis.","name":"ref_consultation_2","caption":"Advise as to the diagnosis"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_3","class":"ref_other ref_intro","value":"Suggest medication or treatment for the diagnosis.","name":"ref_consultation_3","caption":"Suggest medication or treatment"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_4","class":"ref_other ref_after","value":"Please call me when you have seen the patient.","name":"ref_consultation_4","caption":"Call back"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_5","class":"ref_other ref_after","value":"I would like to receive periodic status reports on this patient.","name":"ref_consultation_5","caption":"Receive periodic status reports"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_6","class":"ref_other ref_after","value":"Please send a thorough written report when the consultation is complete.","name":"ref_consultation_6","caption":"Receive thorough written report"}]}',
			'group' => 'consultation',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Consultation - Please send the patient back for follow-up and treatment.","id":"ref_consultation_hidden"},{"type":"checkbox","id":"ref_consultation_1","class":"ref_other ref_intro","value":"Confirm the diagnosis.","name":"ref_consultation_1","caption":"Confirm the diagnosis"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_2","class":"ref_other ref_intro","value":"Advise as to the diagnosis.","name":"ref_consultation_2","caption":"Advise as to the diagnosis"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_3","class":"ref_other ref_intro","value":"Suggest medication or treatment for the diagnosis.","name":"ref_consultation_3","caption":"Suggest medication or treatment"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_4","class":"ref_other ref_after","value":"Please call me when you have seen the patient.","name":"ref_consultation_4","caption":"Call back"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_5","class":"ref_other ref_after","value":"I would like to receive periodic status reports on this patient.","name":"ref_consultation_5","caption":"Receive periodic status reports"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_6","class":"ref_other ref_after","value":"Please send a thorough written report when the consultation is complete.","name":"ref_consultation_6","caption":"Receive thorough written report"}]}',
			'group' => 'consultation',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Physical therapy referral details:","id":"ref_pt_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_pt_1_div","html":[{"type":"span","html":"Objectives:"},{"type":"br"},{"type":"checkbox","id":"ref_pt_1a","class":"ref_other ref_intro","value":"Decrease pain.","name":"ref_pt_1","caption":"Decrease pain"},{"type":"checkbox","id":"ref_pt_1b","class":"ref_other ref_intro","value":"Increase strength.","name":"ref_pt_1","caption":"Increase strength"},{"type":"checkbox","id":"ref_pt_1c","class":"ref_other ref_intro","value":"Increase mobility.","name":"ref_pt_1","caption":"Increase mobility"}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_pt_2_div","html":[{"type":"span","html":"Modalities:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"ref_pt_2","class":"ref_select ref_intro","css":{"width":"200px"},"name":"ref_pt_2","caption":"","options":{"Hot or cold packs. ":"Hot or cold packs.","TENS unit. ":"TENS unit.","Back program. ":"Back program.","Joint mobilization. ":"Joint mobilization.","Home program. ":"Home program.","Pool therapy. ":"Pool therapy.","Feldenkrais method. ":"Feldenkrais method.","Therapeutic exercise. ":"Therapeutic exercise.","Myofascial release. ":"Myofascial release.","Patient education. ":"Patient education.","Work hardening. ":"Work hardening."}}]},{"type":"br"},{"type":"text","id":"ref_pt_3","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_3","placeholder":"Precautions"},{"type":"br"},{"type":"text","id":"ref_pt_4","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_4","placeholder":"Frequency"},{"type":"br"},{"type":"text","id":"ref_pt_5","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_5","placeholder":"Duration"}]}',
			'group' => 'pt',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Physical therapy referral details:","id":"ref_pt_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_pt_1_div","html":[{"type":"span","html":"Objectives:"},{"type":"br"},{"type":"checkbox","id":"ref_pt_1a","class":"ref_other ref_intro","value":"Decrease pain.","name":"ref_pt_1","caption":"Decrease pain"},{"type":"checkbox","id":"ref_pt_1b","class":"ref_other ref_intro","value":"Increase strength.","name":"ref_pt_1","caption":"Increase strength"},{"type":"checkbox","id":"ref_pt_1c","class":"ref_other ref_intro","value":"Increase mobility.","name":"ref_pt_1","caption":"Increase mobility"}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_pt_2_div","html":[{"type":"span","html":"Modalities:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"ref_pt_2","class":"ref_select ref_intro","css":{"width":"200px"},"name":"ref_pt_2","caption":"","options":{"Hot or cold packs. ":"Hot or cold packs.","TENS unit. ":"TENS unit.","Back program. ":"Back program.","Joint mobilization. ":"Joint mobilization.","Home program. ":"Home program.","Pool therapy. ":"Pool therapy.","Feldenkrais method. ":"Feldenkrais method.","Therapeutic exercise. ":"Therapeutic exercise.","Myofascial release. ":"Myofascial release.","Patient education. ":"Patient education.","Work hardening. ":"Work hardening."}}]},{"type":"br"},{"type":"text","id":"ref_pt_3","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_3","placeholder":"Precautions"},{"type":"br"},{"type":"text","id":"ref_pt_4","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_4","placeholder":"Frequency"},{"type":"br"},{"type":"text","id":"ref_pt_5","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_5","placeholder":"Duration"}]}',
			'group' => 'pt',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Massage therapy referral details:","id":"ref_massage_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_massage_1_div","html":[{"type":"span","html":"Objectives:"},{"type":"br"},{"type":"checkbox","id":"ref_massage_1a","class":"ref_other ref_intro","value":"Decrease pain.","name":"ref_massage_1","caption":"Decrease pain"},{"type":"checkbox","id":"ref_massage_1b","class":"ref_other ref_intro","value":"Increase mobility.","name":"ref_massage_1","caption":"Increase mobility"}]},{"type":"br"},{"type":"text","id":"ref_massage_2","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_2","placeholder":"Precautions"},{"type":"br"},{"type":"text","id":"ref_massage_3","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_3","placeholder":"Frequency"},{"type":"br"},{"type":"text","id":"ref_massage_4","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_4","placeholder":"Duration"}]}',
			'group' => 'massage',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Massage therapy referral details:","id":"ref_massage_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_massage_1_div","html":[{"type":"span","html":"Objectives:"},{"type":"br"},{"type":"checkbox","id":"ref_massage_1a","class":"ref_other ref_intro","value":"Decrease pain.","name":"ref_massage_1","caption":"Decrease pain"},{"type":"checkbox","id":"ref_massage_1b","class":"ref_other ref_intro","value":"Increase mobility.","name":"ref_massage_1","caption":"Increase mobility"}]},{"type":"br"},{"type":"text","id":"ref_massage_2","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_2","placeholder":"Precautions"},{"type":"br"},{"type":"text","id":"ref_massage_3","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_3","placeholder":"Frequency"},{"type":"br"},{"type":"text","id":"ref_massage_4","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_4","placeholder":"Duration"}]}',
			'group' => 'massage',
			'sex' => 'f'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Sleep study referral details:","id":"ref_sleep_study_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_1_div","html":[{"type":"span","html":"Type:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"ref_sleep_study_1","class":"ref_select ref_other ref_intro","css":{"width":"200px"},"name":"ref_sleep_study_1","caption":"","options":{"Diagnostic Sleep Study Only.\n":"Diagnostic Sleep Study Only.","Diagnostic testing with Continuous Positive Airway Pressure.\n":"Diagnostic testing with Continuous Positive Airway Pressure.","Diagnostic testing with BiLevel Positive Airway Pressure.\n":"Diagnostic testing with BiLevel Positive Airway Pressure.","Diagnostic testing with BiLevel Positive Airway Pressure.\n":"Diagnostic testing with BiLevel Positive Airway Pressure.","Diagnostic testing with Oxygen.\n":"Diagnostic testing with Oxygen.","Diagnostic testing with Oral Device.\n":"Diagnostic testing with Oral Device.","MSLT (Multiple Sleep Latency Test).\n":"MSLT (Multiple Sleep Latency Test).","MWT (Maintenance of Wakefulness Test).\n":"MWT (Maintenance of Wakefulness Test).","Titrate BiPAP settings.\n":"Titrate BiPAP settings.","Patient education. ":"Patient education.","Work hardening. ":"Work hardening."}}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_2_div","html":[{"type":"span","html":"BiPAP pressures:"},{"type":"br"},{"type":"text","id":"ref_sleep_study_2a","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_2a","placeholder":"Inspiratory Pressure (IPAP), cm H20"},{"type":"br"},{"type":"text","id":"ref_sleep_study_2b","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_2b","placeholder":"Expiratory Pressure (EPAP), cm H20"}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_3_div","html":[{"type":"span","html":"BiPAP Mode:"},{"type":"br"},{"type":"checkbox","id":"ref_sleep_study_3a","class":"ref_other ref_intro","value":"Spontaneous mode.","name":"ref_sleep_study_3","caption":"Spontaneous"},{"type":"checkbox","id":"ref_sleep_study_3b","class":"ref_other ref_intro","value":"Spontaneous/Timed mode","name":"ref_sleep_study_3","caption":"Spontaneous/Timed"},{"type":"br"},{"type":"text","id":"ref_sleep_study_3c","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_3","placeholder":"Breaths per minute"}]}]}',
			'group' => 'sleep_study',
			'sex' => 'm'
		);
		$template_array[] = array(
			'category' => 'referral',
			'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Sleep study referral details:","id":"ref_sleep_study_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_1_div","html":[{"type":"span","html":"Type:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"ref_sleep_study_1","class":"ref_select ref_other ref_intro","css":{"width":"200px"},"name":"ref_sleep_study_1","caption":"","options":{"Diagnostic Sleep Study Only.\n":"Diagnostic Sleep Study Only.","Diagnostic testing with Continuous Positive Airway Pressure.\n":"Diagnostic testing with Continuous Positive Airway Pressure.","Diagnostic testing with BiLevel Positive Airway Pressure.\n":"Diagnostic testing with BiLevel Positive Airway Pressure.","Diagnostic testing with BiLevel Positive Airway Pressure.\n":"Diagnostic testing with BiLevel Positive Airway Pressure.","Diagnostic testing with Oxygen.\n":"Diagnostic testing with Oxygen.","Diagnostic testing with Oral Device.\n":"Diagnostic testing with Oral Device.","MSLT (Multiple Sleep Latency Test).\n":"MSLT (Multiple Sleep Latency Test).","MWT (Maintenance of Wakefulness Test).\n":"MWT (Maintenance of Wakefulness Test).","Titrate BiPAP settings.\n":"Titrate BiPAP settings.","Patient education. ":"Patient education.","Work hardening. ":"Work hardening."}}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_2_div","html":[{"type":"span","html":"BiPAP pressures:"},{"type":"br"},{"type":"text","id":"ref_sleep_study_2a","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_2a","placeholder":"Inspiratory Pressure (IPAP), cm H20"},{"type":"br"},{"type":"text","id":"ref_sleep_study_2b","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_2b","placeholder":"Expiratory Pressure (EPAP), cm H20"}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_3_div","html":[{"type":"span","html":"BiPAP Mode:"},{"type":"br"},{"type":"checkbox","id":"ref_sleep_study_3a","class":"ref_other ref_intro","value":"Spontaneous mode.","name":"ref_sleep_study_3","caption":"Spontaneous"},{"type":"checkbox","id":"ref_sleep_study_3b","class":"ref_other ref_intro","value":"Spontaneous/Timed mode","name":"ref_sleep_study_3","caption":"Spontaneous/Timed"},{"type":"br"},{"type":"text","id":"ref_sleep_study_3c","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_3","placeholder":"Breaths per minute"}]}]}',
			'group' => 'sleep_study',
			'sex' => 'f'
		);
		foreach ($template_array as $template_ind) {
			$template_array = serialize(json_decode($template_ind['json']));
			$template_data = array(
				'user_id' => '0',
				'template_name' => 'Global Default',
				'default' => 'default',
				'category' => $template_ind['category'],
				'sex' => $template_ind['sex'],
				'group' => $template_ind['group'],
				'array' => $template_array
			);
			DB::table('templates')->insert($template_data);
		}
		$orderslist1_array = array();
		$orderslist1_array[] = array(
			'orders_code' => '11550',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '12500',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '24080',
			'aoe_code' => 'MIC1^SOURCE:',
			'aoe_field' => 'aoe_source_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '30000',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '30740',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '30820',
			'aoe_code' => 'GLUFAST^HOURS FASTING:',
			'aoe_field' => 'aoe_fasting_hours_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '31300',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '33320',
			'aoe_code' => 'TDM1^LAST DOSE DATE:;TDM2^LAST DOSE TIME:',
			'aoe_field' => 'aoe_dose_date_code;aoe_dose_time_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '43540',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '43542',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '43546',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '60109',
			'aoe_code' => 'BFL1^SOURCE:',
			'aoe_field' => 'aoe_source1_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '61500',
			'aoe_code' => 'MIC1^SOURCE:;MIC2^ADD. INFORMATION:',
			'aoe_field' => 'aoe_source_code;aoe_additional_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '68329',
			'aoe_code' => 'MIC1^SOURCE:',
			'aoe_field' => 'aoe_source_code'
		);
		foreach ($orderslist1_array as $row1) {
			$orders_query = DB::table('orderslist1')->where('orders_code', '=', $row1['orders_code'])->get();
			foreach ($orders_query as $row2) {
				$orders_data = array(
					'aoe_code' => $row1['aoe_code'],
					'aoe_field' => $row1['aoe_field']
				);
				DB::table('orderslist1')->where('orderslist1_id', '=', $row2->orderslist1_id)->update($orders_data);
			}
		}
		$this->default_template('1');
		Auth::attempt(array('username' => $username, 'password' => Input::get('password'), 'active' => '1', 'practice_id' => '1'));
		Session::put('user_id', $user_id);
		Session::put('group_id', '1');
		Session::put('practice_id', '1');
		Session::put('version', $data2['version']);
		Session::put('practice_active', $data2['active']);
		Session::put('displayname', $displayname);
		Session::put('documents_dir', $data2['documents_dir']);
		Session::put('patient_centric', $data2['patient_centric']);
		echo "OK";
	}

	public function postPracticeRegister()
	{
		$base_practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
		$username = Input::get('username');
		$password = substr_replace(Hash::make(Input::get('password')),"$2a",0,3);
		$email = Input::get('email');
		$practice_id = Input::get('practice_id');
		// Insert practice
		$data2 = array(
			'practice_name' => Input::get('practice_name'),
			'street_address1' => Input::get('street_address1'),
			'street_address2' => Input::get('street_address2'),
			'city' => Input::get('city'),
			'state' => Input::get('state'),
			'zip' => Input::get('zip'),
			'phone' => Input::get('phone'),
			'fax' => Input::get('fax'),
			'fax_type' => '',
			'vivacare' => '',
			'practicehandle' => Input::get('practicehandle'),
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
		Auth::attempt(array('username' => $username, 'password' => Input::get('password'), 'active' => '1', 'practice_id' => '1'));
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
			$data = Input::all();
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

	public function postDirectoryCheck()
	{
		$documents_dir = Input::get('documents_dir');
		if (!is_writable($documents_dir)) {
			echo "'" . $documents_dir . "' is not writable.";
		} else {
			echo "OK";
		}
	}

	public function postDatabaseFix()
	{
		$db_username = Input::get('db_username');
		$db_password = Input::get('db_password');
		$connect = mysqli_connect('localhost', $db_username, $db_password);
		$db = mysqli_select_db($connect,'nosh');
		if ($db) {
			$filename = __DIR__."/../../.env.php";
			$config = include $filename;
			$config['mysql_username'] = $db_username;
			$config['mysql_password'] = $db_password;
			file_put_contents($filename, '<?php return ' . var_export($config, true) . ";\n");
			echo "OK";
		} else {
			echo "Incorrect username/password for your MySQL database.  Try again.";
		}
	}

	public function postCheckPracticehandle()
	{
		$query = DB::table('practiceinfo')->where('practicehandle', '=', Input::get('practicehandle'))->first();
		if ($query) {
			echo "Practice Handle already used!";
		} else {
			echo "OK";
		}
	}

	public function set_version()
	{
		$result = $this->github_all();
		File::put(__DIR__."/../../.version", $result[0]['sha']);
		return Redirect::to('/');
	}

	public function default_template($practice_id)
	{
		$directory = __DIR__.'/../../import/templates';
		$i = 0;
		$error = '';
		$file_path = $directory . '/Default.txt';
		$csv = File::get($file_path);
		$return = $this->install_template($csv, $practice_id);
		if (isset($return['count'])) {
			$i += $return['count'];
		}
		if (isset($return['error'])) {
			$error .= $return['error'];
		}
		return "Imported " . $i . " templates." . $error;
	}

	public function google_upload()
	{
		$pid = Session::get('pid');
		$directory = __DIR__."/../../";
		$new_name = ".google";
		$config_file = __DIR__."/../../.google";
		foreach (Input::file('file') as $file) {
			if ($file) {
				$json = file_get_contents($file->getRealPath());
				if (json_decode($json) == NULL) {
					echo "This is not a json file.  Try again.";
					exit (0);
				}
				if (file_exists($config_file)) {
					unlink($config_file);
				}
				$file->move($directory, $new_name);
			}
		}
		echo 'Google client JSON file saved!';
	}

	// Update scripts

	public function update()
	{
		$practice = Practiceinfo::find(1);
		if ($practice->version < "1.8.0") {
			$this->update180();
		}
		if ($practice->version < "1.8.1") {
			$this->update181();
		}
		if ($practice->version < "1.8.2") {
			$this->update182();
		}
		if ($practice->version < "1.8.3") {
			$this->update183();
		}
		if ($practice->version < "1.8.4") {
			$this->update184();
		}
		return Redirect::to('/');
	}

	public function update180()
	{
		$orderslist1_array = array();
		$orderslist1_array[] = array(
			'orders_code' => '11550',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '12500',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '24080',
			'aoe_code' => 'MIC1^SOURCE:',
			'aoe_field' => 'aoe_source_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '30000',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '30740',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '30820',
			'aoe_code' => 'GLUFAST^HOURS FASTING:',
			'aoe_field' => 'aoe_fasting_hours_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '31300',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '33320',
			'aoe_code' => 'TDM1^LAST DOSE DATE:;TDM2^LAST DOSE TIME:',
			'aoe_field' => 'aoe_dose_date_code;aoe_dose_time_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '43540',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '43542',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '43546',
			'aoe_code' => 'CHM1^FASTING STATE:',
			'aoe_field' => 'aoe_fasting_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '60109',
			'aoe_code' => 'BFL1^SOURCE:',
			'aoe_field' => 'aoe_source1_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '61500',
			'aoe_code' => 'MIC1^SOURCE:;MIC2^ADD. INFORMATION:',
			'aoe_field' => 'aoe_source_code;aoe_additional_code'
		);
		$orderslist1_array[] = array(
			'orders_code' => '68329',
			'aoe_code' => 'MIC1^SOURCE:',
			'aoe_field' => 'aoe_source_code'
		);
		foreach ($orderslist1_array as $row1) {
			$orders_query = DB::table('orderslist1')->where('orders_code', '=', $row1['orders_code'])->get();
			foreach ($orders_query as $row2) {
				$orders_data = array(
					'aoe_code' => $row1['aoe_code'],
					'aoe_field' => $row1['aoe_field']
				);
				DB::table('orderslist1')->where('orderslist1_id', '=', $row2->orderslist1_id)->update($orders_data);
			}
		}
		// Update referral templates
		$template_query = DB::table('templates')->where('category', '=', 'referral')->first();
		if (!$template_query) {
			$template_array = array();
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Referral - Please provide primary physician with summaries of subsequent visits.","id":"ref_referral_hidden"},{"type":"checkbox","id":"ref_referral_1","class":"ref_other ref_intro","value":"Assume management for this particular problem and return patient after conclusion of care.","name":"ref_referral_1","caption":"Return patient after managing particular problem"},{"type":"br"},{"type":"checkbox","id":"ref_referral_2","class":"ref_other ref_intro","value":"Assume future management of patient within your area of expertise.","name":"ref_referral_2","caption":"Future ongoing management"},{"type":"br"},{"type":"checkbox","id":"ref_referral_3","class":"ref_other ref_after","value":"Please call me when you have seen the patient.","name":"ref_referral_3","caption":"Call back"},{"type":"br"},{"type":"checkbox","id":"ref_referral_4","class":"ref_other ref_after","value":"I would like to receive periodic status reports on this patient.","name":"ref_referral_4","caption":"Receive periodic status reports"},{"type":"br"},{"type":"checkbox","id":"ref_referral_5","class":"ref_other ref_after","value":"Please send a thorough written report when the consultation is complete.","name":"ref_referral_5","caption":"Receive thorough written report"}]}',
				'group' => 'referral',
				'sex' => 'm'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Referral - Please provide primary physician with summaries of subsequent visits.","id":"ref_referral_hidden"},{"type":"checkbox","id":"ref_referral_1","class":"ref_other ref_intro","value":"Assume management for this particular problem and return patient after conclusion of care.","name":"ref_referral_1","caption":"Return patient after managing particular problem"},{"type":"br"},{"type":"checkbox","id":"ref_referral_2","class":"ref_other ref_intro","value":"Assume future management of patient within your area of expertise.","name":"ref_referral_2","caption":"Future ongoing management"},{"type":"br"},{"type":"checkbox","id":"ref_referral_3","class":"ref_other ref_after","value":"Please call me when you have seen the patient.","name":"ref_referral_3","caption":"Call back"},{"type":"br"},{"type":"checkbox","id":"ref_referral_4","class":"ref_other ref_after","value":"I would like to receive periodic status reports on this patient.","name":"ref_referral_4","caption":"Receive periodic status reports"},{"type":"br"},{"type":"checkbox","id":"ref_referral_5","class":"ref_other ref_after","value":"Please send a thorough written report when the consultation is complete.","name":"ref_referral_5","caption":"Receive thorough written report"}]}',
				'group' => 'referral',
				'sex' => 'f'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Consultation - Please send the patient back for follow-up and treatment.","id":"ref_consultation_hidden"},{"type":"checkbox","id":"ref_consultation_1","class":"ref_other ref_intro","value":"Confirm the diagnosis.","name":"ref_consultation_1","caption":"Confirm the diagnosis"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_2","class":"ref_other ref_intro","value":"Advise as to the diagnosis.","name":"ref_consultation_2","caption":"Advise as to the diagnosis"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_3","class":"ref_other ref_intro","value":"Suggest medication or treatment for the diagnosis.","name":"ref_consultation_3","caption":"Suggest medication or treatment"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_4","class":"ref_other ref_after","value":"Please call me when you have seen the patient.","name":"ref_consultation_4","caption":"Call back"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_5","class":"ref_other ref_after","value":"I would like to receive periodic status reports on this patient.","name":"ref_consultation_5","caption":"Receive periodic status reports"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_6","class":"ref_other ref_after","value":"Please send a thorough written report when the consultation is complete.","name":"ref_consultation_6","caption":"Receive thorough written report"}]}',
				'group' => 'consultation',
				'sex' => 'm'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Consultation - Please send the patient back for follow-up and treatment.","id":"ref_consultation_hidden"},{"type":"checkbox","id":"ref_consultation_1","class":"ref_other ref_intro","value":"Confirm the diagnosis.","name":"ref_consultation_1","caption":"Confirm the diagnosis"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_2","class":"ref_other ref_intro","value":"Advise as to the diagnosis.","name":"ref_consultation_2","caption":"Advise as to the diagnosis"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_3","class":"ref_other ref_intro","value":"Suggest medication or treatment for the diagnosis.","name":"ref_consultation_3","caption":"Suggest medication or treatment"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_4","class":"ref_other ref_after","value":"Please call me when you have seen the patient.","name":"ref_consultation_4","caption":"Call back"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_5","class":"ref_other ref_after","value":"I would like to receive periodic status reports on this patient.","name":"ref_consultation_5","caption":"Receive periodic status reports"},{"type":"br"},{"type":"checkbox","id":"ref_consultation_6","class":"ref_other ref_after","value":"Please send a thorough written report when the consultation is complete.","name":"ref_consultation_6","caption":"Receive thorough written report"}]}',
				'group' => 'consultation',
				'sex' => 'f'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Physical therapy referral details:","id":"ref_pt_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_pt_1_div","html":[{"type":"span","html":"Objectives:"},{"type":"br"},{"type":"checkbox","id":"ref_pt_1a","class":"ref_other ref_intro","value":"Decrease pain.","name":"ref_pt_1","caption":"Decrease pain"},{"type":"checkbox","id":"ref_pt_1b","class":"ref_other ref_intro","value":"Increase strength.","name":"ref_pt_1","caption":"Increase strength"},{"type":"checkbox","id":"ref_pt_1c","class":"ref_other ref_intro","value":"Increase mobility.","name":"ref_pt_1","caption":"Increase mobility"}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_pt_2_div","html":[{"type":"span","html":"Modalities:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"ref_pt_2","class":"ref_select ref_intro","css":{"width":"200px"},"name":"ref_pt_2","caption":"","options":{"Hot or cold packs. ":"Hot or cold packs.","TENS unit. ":"TENS unit.","Back program. ":"Back program.","Joint mobilization. ":"Joint mobilization.","Home program. ":"Home program.","Pool therapy. ":"Pool therapy.","Feldenkrais method. ":"Feldenkrais method.","Therapeutic exercise. ":"Therapeutic exercise.","Myofascial release. ":"Myofascial release.","Patient education. ":"Patient education.","Work hardening. ":"Work hardening."}}]},{"type":"br"},{"type":"text","id":"ref_pt_3","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_3","placeholder":"Precautions"},{"type":"br"},{"type":"text","id":"ref_pt_4","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_4","placeholder":"Frequency"},{"type":"br"},{"type":"text","id":"ref_pt_5","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_5","placeholder":"Duration"}]}',
				'group' => 'pt',
				'sex' => 'm'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Physical therapy referral details:","id":"ref_pt_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_pt_1_div","html":[{"type":"span","html":"Objectives:"},{"type":"br"},{"type":"checkbox","id":"ref_pt_1a","class":"ref_other ref_intro","value":"Decrease pain.","name":"ref_pt_1","caption":"Decrease pain"},{"type":"checkbox","id":"ref_pt_1b","class":"ref_other ref_intro","value":"Increase strength.","name":"ref_pt_1","caption":"Increase strength"},{"type":"checkbox","id":"ref_pt_1c","class":"ref_other ref_intro","value":"Increase mobility.","name":"ref_pt_1","caption":"Increase mobility"}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_pt_2_div","html":[{"type":"span","html":"Modalities:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"ref_pt_2","class":"ref_select ref_intro","css":{"width":"200px"},"name":"ref_pt_2","caption":"","options":{"Hot or cold packs. ":"Hot or cold packs.","TENS unit. ":"TENS unit.","Back program. ":"Back program.","Joint mobilization. ":"Joint mobilization.","Home program. ":"Home program.","Pool therapy. ":"Pool therapy.","Feldenkrais method. ":"Feldenkrais method.","Therapeutic exercise. ":"Therapeutic exercise.","Myofascial release. ":"Myofascial release.","Patient education. ":"Patient education.","Work hardening. ":"Work hardening."}}]},{"type":"br"},{"type":"text","id":"ref_pt_3","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_3","placeholder":"Precautions"},{"type":"br"},{"type":"text","id":"ref_pt_4","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_4","placeholder":"Frequency"},{"type":"br"},{"type":"text","id":"ref_pt_5","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_pt_5","placeholder":"Duration"}]}',
				'group' => 'pt',
				'sex' => 'f'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Massage therapy referral details:","id":"ref_massage_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_massage_1_div","html":[{"type":"span","html":"Objectives:"},{"type":"br"},{"type":"checkbox","id":"ref_massage_1a","class":"ref_other ref_intro","value":"Decrease pain.","name":"ref_massage_1","caption":"Decrease pain"},{"type":"checkbox","id":"ref_massage_1b","class":"ref_other ref_intro","value":"Increase mobility.","name":"ref_massage_1","caption":"Increase mobility"}]},{"type":"br"},{"type":"text","id":"ref_massage_2","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_2","placeholder":"Precautions"},{"type":"br"},{"type":"text","id":"ref_massage_3","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_3","placeholder":"Frequency"},{"type":"br"},{"type":"text","id":"ref_massage_4","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_4","placeholder":"Duration"}]}',
				'group' => 'massage',
				'sex' => 'm'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Massage therapy referral details:","id":"ref_massage_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_massage_1_div","html":[{"type":"span","html":"Objectives:"},{"type":"br"},{"type":"checkbox","id":"ref_massage_1a","class":"ref_other ref_intro","value":"Decrease pain.","name":"ref_massage_1","caption":"Decrease pain"},{"type":"checkbox","id":"ref_massage_1b","class":"ref_other ref_intro","value":"Increase mobility.","name":"ref_massage_1","caption":"Increase mobility"}]},{"type":"br"},{"type":"text","id":"ref_massage_2","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_2","placeholder":"Precautions"},{"type":"br"},{"type":"text","id":"ref_massage_3","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_3","placeholder":"Frequency"},{"type":"br"},{"type":"text","id":"ref_massage_4","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_massage_4","placeholder":"Duration"}]}',
				'group' => 'massage',
				'sex' => 'f'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Sleep study referral details:","id":"ref_sleep_study_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_1_div","html":[{"type":"span","html":"Type:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"ref_sleep_study_1","class":"ref_select ref_other ref_intro","css":{"width":"200px"},"name":"ref_sleep_study_1","caption":"","options":{"Diagnostic Sleep Study Only.\n":"Diagnostic Sleep Study Only.","Diagnostic testing with Continuous Positive Airway Pressure.\n":"Diagnostic testing with Continuous Positive Airway Pressure.","Diagnostic testing with BiLevel Positive Airway Pressure.\n":"Diagnostic testing with BiLevel Positive Airway Pressure.","Diagnostic testing with BiLevel Positive Airway Pressure.\n":"Diagnostic testing with BiLevel Positive Airway Pressure.","Diagnostic testing with Oxygen.\n":"Diagnostic testing with Oxygen.","Diagnostic testing with Oral Device.\n":"Diagnostic testing with Oral Device.","MSLT (Multiple Sleep Latency Test).\n":"MSLT (Multiple Sleep Latency Test).","MWT (Maintenance of Wakefulness Test).\n":"MWT (Maintenance of Wakefulness Test).","Titrate BiPAP settings.\n":"Titrate BiPAP settings.","Patient education. ":"Patient education.","Work hardening. ":"Work hardening."}}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_2_div","html":[{"type":"span","html":"BiPAP pressures:"},{"type":"br"},{"type":"text","id":"ref_sleep_study_2a","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_2a","placeholder":"Inspiratory Pressure (IPAP), cm H20"},{"type":"br"},{"type":"text","id":"ref_sleep_study_2b","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_2b","placeholder":"Expiratory Pressure (EPAP), cm H20"}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_3_div","html":[{"type":"span","html":"BiPAP Mode:"},{"type":"br"},{"type":"checkbox","id":"ref_sleep_study_3a","class":"ref_other ref_intro","value":"Spontaneous mode.","name":"ref_sleep_study_3","caption":"Spontaneous"},{"type":"checkbox","id":"ref_sleep_study_3b","class":"ref_other ref_intro","value":"Spontaneous/Timed mode","name":"ref_sleep_study_3","caption":"Spontaneous/Timed"},{"type":"br"},{"type":"text","id":"ref_sleep_study_3c","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_3","placeholder":"Breaths per minute"}]}]}',
				'group' => 'sleep_study',
				'sex' => 'm'
			);
			$template_array[] = array(
				'category' => 'referral',
				'json' => '{"html":[{"type":"hidden","class":"ref_hidden","value":"Sleep study referral details:","id":"ref_sleep_study_hidden"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_1_div","html":[{"type":"span","html":"Type:"},{"type":"br"},{"type":"select","multiple":"multiple","id":"ref_sleep_study_1","class":"ref_select ref_other ref_intro","css":{"width":"200px"},"name":"ref_sleep_study_1","caption":"","options":{"Diagnostic Sleep Study Only.\n":"Diagnostic Sleep Study Only.","Diagnostic testing with Continuous Positive Airway Pressure.\n":"Diagnostic testing with Continuous Positive Airway Pressure.","Diagnostic testing with BiLevel Positive Airway Pressure.\n":"Diagnostic testing with BiLevel Positive Airway Pressure.","Diagnostic testing with BiLevel Positive Airway Pressure.\n":"Diagnostic testing with BiLevel Positive Airway Pressure.","Diagnostic testing with Oxygen.\n":"Diagnostic testing with Oxygen.","Diagnostic testing with Oral Device.\n":"Diagnostic testing with Oral Device.","MSLT (Multiple Sleep Latency Test).\n":"MSLT (Multiple Sleep Latency Test).","MWT (Maintenance of Wakefulness Test).\n":"MWT (Maintenance of Wakefulness Test).","Titrate BiPAP settings.\n":"Titrate BiPAP settings.","Patient education. ":"Patient education.","Work hardening. ":"Work hardening."}}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_2_div","html":[{"type":"span","html":"BiPAP pressures:"},{"type":"br"},{"type":"text","id":"ref_sleep_study_2a","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_2a","placeholder":"Inspiratory Pressure (IPAP), cm H20"},{"type":"br"},{"type":"text","id":"ref_sleep_study_2b","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_2b","placeholder":"Expiratory Pressure (EPAP), cm H20"}]},{"type":"br"},{"type":"div","class":"ref_buttonset","id":"ref_sleep_study_3_div","html":[{"type":"span","html":"BiPAP Mode:"},{"type":"br"},{"type":"checkbox","id":"ref_sleep_study_3a","class":"ref_other ref_intro","value":"Spontaneous mode.","name":"ref_sleep_study_3","caption":"Spontaneous"},{"type":"checkbox","id":"ref_sleep_study_3b","class":"ref_other ref_intro","value":"Spontaneous/Timed mode","name":"ref_sleep_study_3","caption":"Spontaneous/Timed"},{"type":"br"},{"type":"text","id":"ref_sleep_study_3c","css":{"width":"200px"},"class":"ref_other ref_detail_text ref_intro","name":"ref_sleep_study_3","placeholder":"Breaths per minute"}]}]}',
				'group' => 'sleep_study',
				'sex' => 'f'
			);
			foreach ($template_array as $template_ind) {
				$template_array = serialize(json_decode($template_ind['json']));
				$template_data = array(
					'user_id' => '0',
					'template_name' => 'Global Default',
					'default' => 'default',
					'category' => $template_ind['category'],
					'sex' => $template_ind['sex'],
					'group' => $template_ind['group'],
					'array' => $template_array
				);
				DB::table('templates')->insert($template_data);
			}
		}
		// Update image links and create scans and received faxes directories if needed
		$practices = Practiceinfo::all();
		foreach ($practices as $practice) {
			$practice->practice_logo = str_replace("/var/www/nosh/","", $practice->practice_logo);
			$practice->save();
			$scans_dir = $practice->documents_dir . 'scans/' . $practice->practice_id;
			if (! file_exists($scans_dir)) {
				mkdir($scans_dir, 0777);
			}
			$received_dir = $practice->documents_dir . 'received/' . $practice->practice_id;
			if (! file_exists($received_dir)) {
				mkdir($received_dir, 0777);
			}
		}
		$providers = Providers::all();
		foreach ($providers as $provider) {
			$provider->signature = str_replace("/var/www/nosh/","", $provider->signature);
			$provider->save();
		}
		// Assign standard encounter templates
		DB::table('encounters')->update(array('encounter_template' => 'standardmedical'));
		// Move scans and received faxes
		$scans = DB::table('scans')->get();
		if ($scans) {
			foreach ($scans as $scan) {
				$practice1 = Practiceinfo::find($scan->practice_id);
				$new_scans_dir = $practice1->documents_dir . 'scans/' . $scan->practice_id;
				$scans_data['filePath'] = str_replace('/var/www/nosh/scans', $new_scans_dir, $scan->filePath);
				rename($scan->filePath, $scans_data['filePath']);
				DB::table('scans')->where('scans_id', '=', $scan->scans_id)->update($scans_data);
			}
		}
		$received = DB::table('received')->get();
		if ($received) {
			foreach ($received as $fax) {
				$fax_practice_id = '1';
				if ($fax->practice_id != '' && $fax->practice_id != '0') {
					$fax_practice_id = $fax->practice_id;
				}
				$practice2 = Practiceinfo::find($fax_practice_id);
				$new_received_dir = $practice2->documents_dir . 'received/' . $fax_practice_id;
				$received_data['filePath'] = str_replace('/var/www/nosh/received', $new_received_dir, $fax->filePath);
				if (file_exists($fax->filePath)) {
					rename($fax->filePath, $received_data['filePath']);
				}
				DB::table('received')->where('received_id', '=', $fax->received_id)->update($received_data);
			}
		}
		// Migrate bill_complex field to encounters
		$encounters = DB::table('encounters')->get();
		if ($encounters) {
			foreach ($encounters as $encounter) {
				$billing = DB::table('billing')
					->where('eid', '=', $encounter->eid)
					->where(function($query_array1){
						$query_array1->where('bill_complex', '!=', "")
						->orWhereNotNull('bill_complex');
					})
					->first();
				$data['bill_complex'] = '';
				if ($billing) {
					$data['bill_complex'] = $billing->bill_complex;
				}
				DB::table('encounters')->where('eid', '=', $encounter->eid)->update($data);
			}
		}
		$db_name = $_ENV['mysql_database'];
		$db_username = $_ENV['mysql_username'];
		$db_password = $_ENV['mysql_password'];
		DB::table('meds_full')->truncate();
		$meds_sql_file = __DIR__."/../../import/meds_full.sql";
		$meds_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $meds_sql_file;
		system($meds_command);
		DB::table('meds_full_package')->truncate();
		$meds1_sql_file = __DIR__."/../../import/meds_full_package.sql";
		$meds1_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $meds1_sql_file;
		system($meds1_command);
		DB::table('supplements_list')->truncate();
		$supplements_file = __DIR__."/../../import/supplements_list.sql";
		$supplements_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $supplements_file;
		system($supplements_command);
		DB::table('icd9')->truncate();
		$icd_file = __DIR__."/../../import/icd9.sql";
		$icd_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $icd_file;
		system($icd_command);
		$alpha_array = array(
			'1' => 'A',
			'2' => 'B',
			'3' => 'C',
			'4' => 'D',
			'5' => 'E',
			'6' => 'F',
			'7' => 'G',
			'8' => 'H'
		);
		// Update ICD pointers to reflect new HCFA-1500
		$billing_core = DB::table('billing_core')->whereNotNull('icd_pointer')->get();
		if ($billing_core) {
			foreach ($billing_core as $billing_core_row) {
				if ($billing_core_row->icd_pointer != '') {
					$icd_pointer = $billing_core_row->icd_pointer;
					foreach($alpha_array as $key => $value) {
						$icd_pointer = str_replace($key, $value, $icd_pointer);
					}
					$billing_core_data['icd_pointer'] = $icd_pointer;
					DB::table('billing_core')->where('billing_core_id', '=', $billing_core_row->billing_core_id)->update($billing_core_data);
				}
			}
		}
		// Update calendar
		$calendar['provider_id'] = '0';
		DB::table('calendar')->update($calendar);
		// Update version
		DB::table('practiceinfo')->update(array('version' => '1.8.0'));
	}

	public function update181()
	{
		$db_name = $_ENV['mysql_database'];
		$db_username = $_ENV['mysql_username'];
		$db_password = $_ENV['mysql_password'];
		$icd10_sql_file = __DIR__."/../../import/icd10.sql";
		$icd10_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $icd10_sql_file;
		system($icd10_command);
		$practiceinfo_data = array(
			'icd' => '9',
			'version' => '1.8.1'
		);
		// Update version
		DB::table('practiceinfo')->update($practiceinfo_data);
	}

	public function update182()
	{
		// Fix template numbering bug
		$query = DB::table('templates')
			->where('template_name', '!=', 'Global Default')
			->where(function($query_array1) {
				$query_array1->where('category', '=', 'pe')
				->orWhere('category', '=', 'ros')
				->orWhere('category', '=', 'hpi');
			})
			->where('category', '=', 'pe')
			->get();
		if ($query) {
			foreach ($query as $row) {
				$arr = json_decode(unserialize($row->array), true);
				$category = $row->category . "_form_div";
				$i = 0;
				$j = $i + 1;
				foreach ($arr['html'] as $row1) {
					$cat = $category . $j;
					if($row1['id'] != $cat) {
						$arr['html'][$i]['id'] = $cat;
						$k = 0;
						foreach ($arr['html'][$i]['html'] as $row2) {
							if ($k != 1) {
								if ($k == 0) {
									$arr['html'][$i]['html'][$k]['id'] = $cat . '_label';
								} else {
									$arr['html'][$i]['html'][$k]['id'] = str_replace($row1['id'], $cat, $arr['html'][$i]['html'][$k]['id']);
									$arr['html'][$i]['html'][$k]['name'] = $cat;
								}
							}
							$k++;
						}
					}
					$i++;
					$j++;
				}
				$data['array'] = serialize(json_encode($arr));
				DB::table('templates')->where('template_id', '=', $row->template_id)->update($data);
			}
		}
		$practiceinfo_data = array(
			'version' => '1.8.2'
		);
		// Update version
		DB::table('practiceinfo')->update($practiceinfo_data);
	}

	public function update183()
	{
		// Update ICD9 database
		DB::table('icd9')->truncate();
		$db_name = $_ENV['mysql_database'];
		$db_username = $_ENV['mysql_username'];
		$db_password = $_ENV['mysql_password'];
		$icd9_sql_file = __DIR__."/../../import/icd9.sql";
		$icd9_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $icd9_sql_file;
		system($icd9_command);
		$practiceinfo_data = array(
			'version' => '1.8.3'
		);
		// Update version
		DB::table('practiceinfo')->update($practiceinfo_data);
	}

	public function update184()
	{
		set_time_limit(0);
		ini_set('memory_limit','384M');
		// Install Default Templates
		$practices = DB::table('practiceinfo')->get();
		if ($practices) {
			foreach ($practices as $practice) {
				$this->default_template($practice->practice_id);
			}
		}
		$practiceinfo_data = array(
			'version' => '1.8.4'
		);
		// Update version
		DB::table('practiceinfo')->update($practiceinfo_data);
	}

	public function update2()
	{
		$practices = DB::table('practiceinfo')->get();
		if ($practices->count()) {
			foreach ($practices as $practice) {
				// Depreciate unsupported fax programs
				if ($practice->fax_type == 'efaxsend.com' || $practice->fax_type == 'rcfax.com' || $practice->fax_type == 'metrofax.com') {
					$data['fax_type'] = '';
				}
				// Depreciated encounter templates
				if ($practice->encounter_template == 'standardmedical' || $practice->encounter_template == 'standardmedical1') {
					$data['encounter_template'] = 'medical';
				}
				DB::table('practiceinfo')->where('practice_id', '=', $practice->practice_id)->update($data);
			}
		}
		$practiceinfo_data = array(
			'version' => '2.0.0'
		);
		// Update version
		DB::table('practiceinfo')->update($practiceinfo_data);
	}

	public function postResetDatabase()
	{
		$db_name = $_ENV['mysql_database'];
		$db_username = $_ENV['mysql_username'];
		$db_password = $_ENV['mysql_password'];
		DB::table('meds_full')->truncate();
		$meds_sql_file = __DIR__."/../../import/meds_full.sql";
		$meds_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $meds_sql_file;
		system($meds_command);
		DB::table('meds_full_package')->truncate();
		$meds1_sql_file = __DIR__."/../../import/meds_full_package.sql";
		$meds1_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $meds1_sql_file;
		system($meds1_command);
		DB::table('supplements_list')->truncate();
		$supplements_file = __DIR__."/../../import/supplements_list.sql";
		$supplements_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $supplements_file;
		system($supplements_command);
		DB::table('icd9')->truncate();
		$icd_file = __DIR__."/../../import/icd9.sql";
		$icd_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $icd_file;
		system($icd_command);
		DB::table('cpt')->truncate();
		$cpt_file = __DIR__."/../../import/cpt.sql";
		$cpt_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $cpt_file;
		system($cpt_command);
		DB::table('templates')->truncate();
		$templates_file = __DIR__."/../../import/templates.sql";
		$templates_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $templates_file;
		system($templates_command);
		DB::table('orderslist1')->truncate();
		$orderslist1_file = __DIR__."/../../import/orderslist1.sql";
		$orderslist1_command = "mysql -u " . $db_username . " -p". $db_password . " " . $db_name. " < " . $orderslist1_file;
		system($orderslist1_command);
		DB::table('addressbook')->truncate();
		DB::table('alerts')->truncate();
		DB::table('allergies')->truncate();
		DB::table('api_queue')->truncate();
		DB::table('assessment')->truncate();
		DB::table('audit')->truncate();
		DB::table('billing')->truncate();
		DB::table('billing_core')->truncate();
		DB::table('calendar')->truncate();
		DB::table('ci_sessions')->truncate();
		DB::table('cpt_relate')->truncate();
		$practice = Practiceinfo::find('1');
		$patients = DB::table('demographics')->get();
		foreach ($patients as $patient) {
			$directory = $practice->documents_dir . $patient->pid;
			$this->deltree($directory, false);
		}
		DB::table('demographics')->truncate();
		DB::table('demographics_notes')->truncate();
		DB::table('demographics_relate')->truncate();
		DB::table('documents')->truncate();
		DB::table('encounters')->truncate();
		DB::table('era')->truncate();
		DB::table('extensions_log')->truncate();
		DB::table('forms')->truncate();
		DB::table('groups')->truncate();
		DB::table('hippa')->truncate();
		DB::table('hippa_request')->truncate();
		DB::table('hpi')->truncate();
		DB::table('image')->truncate();
		DB::table('immunizations')->truncate();
		DB::table('insurance')->truncate();
		DB::table('issues')->truncate();
		DB::table('labs')->truncate();
		DB::table('messaging')->truncate();
		DB::table('mtm')->truncate();
		DB::table('orders')->truncate();
		DB::table('orderslist')->truncate();
		DB::table('other_history')->truncate();
		DB::table('pages')->truncate();
		DB::table('pe')->truncate();
		DB::table('plan')->truncate();
		DB::table('procedure')->truncate();
		DB::table('procedurelist')->truncate();
		DB::table('providers')->truncate();
		DB::table('received')->truncate();
		$received = $practice->documents_dir . 'received';
		$this->deltree($received, true);
		DB::table('recipients')->truncate();
		DB::table('ros')->truncate();
		DB::table('rx')->truncate();
		DB::table('scans')->truncate();
		$scans = $practice->documents_dir . 'scans';
		$this->deltree($scans, true);
		DB::table('schedule')->truncate();
		DB::table('sendfax')->truncate();
		$sentfax = $practice->documents_dir . 'sentfax';
		$sentfax->deltree($sentfax, true);
		DB::table('sessions')->truncate();
		DB::table('supplement_inventory')->truncate();
		DB::table('sup_list')->truncate();
		DB::table('tags')->truncate();
		DB::table('tags_relate')->truncate();
		DB::table('tests')->truncate();
		DB::table('t_messages')->truncate();
		DB::table('uma')->truncate();
		DB::table('uma_invitation')->truncate();
		DB::table('users')->truncate();
		DB::table('vaccine_inventory')->truncate();
		DB::table('vaccine_temp')->truncate();
		DB::table('vitals')->truncate();
		DB::table('practiceinfo')->truncate();
		echo "OK";
	}
}
