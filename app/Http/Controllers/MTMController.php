<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use Date;
use DB;
use Form;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use QrCode;
use Session;
use URL;

class MTMController extends Controller
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
        $this->middleware('patient');
    }

    public function postMtm()
    {
        $practice_id = Session::get('practice_id');
        $pid = Session::get('pid');
        $page = Input::get('page');
        $limit = Input::get('rows');
        $sidx = Input::get('sidx');
        $sord = Input::get('sord');
        $query = DB::table('mtm')
            ->where('pid', '=', $pid)
            ->where('practice_id', '=', $practice_id)
            ->get();
        if ($query) {
            $count = count($query);
            $total_pages = ceil($count/$limit);
        } else {
            $count = 0;
            $total_pages = 0;
        }
        if ($page > $total_pages) {
            $page=$total_pages;
        }
        $start = $limit*$page - $limit;
        if ($start < 0) {
            $start = 0;
        }
        $query1 = DB::table('mtm')
            ->where('pid', '=', $pid)
            ->where('practice_id', '=', $practice_id)
            ->orderBy($sidx, $sord)
            ->skip($start)
            ->take($limit)
            ->get();
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

    public function postEditMtm()
    {
        $data = array(
            'mtm_description' => Input::get('mtm_description'),
            'mtm_recommendations' => Input::get('mtm_recommendations'),
            'mtm_beneficiary_notes' => Input::get('mtm_beneficiary_notes'),
            'pid' => Session::get('pid'),
            'mtm_action' => Input::get('mtm_action'),
            'mtm_outcome' => Input::get('mtm_outcome'),
            'mtm_related_conditions' => Input::get('mtm_related_conditions'),
            'mtm_duration' => Input::get('mtm_duration'),
            'practice_id' => Session::get('practice_id')
        );
        if (Input::get('mtm_date_completed') != '') {
            $data['mtm_date_completed'] = date('Y-m-d H:i:s', strtotime(Input::get('mtm_date_completed')));
            $data['complete'] = 'yes';
        } else {
            $data['mtm_date_completed'] = '';
            $data['complete'] = 'no';
        }
        if (Input::get('oper') == 'edit') {
            DB::table('mtm')->where('mtm_id', '=', Input::get('id'))->update($data);
            $this->audit('Update');
        }
        if (Input::get('oper') == 'add') {
            DB::table('mtm')->insert($data);
            $this->audit('Add');
        }
        if (Input::get('oper') == 'del') {
            $id_del = explode(",", Input::get('id'));
            foreach ($id_del as $id_del1) {
                DB::table('mtm')->where('mtm_id', '=', $id_del1)->delete();
                $this->audit('Delete');
            }
        }
    }

    public function postPrintMtm()
    {
        ini_set('memory_limit', '196M');
        $pid = Session::get('pid');
        $result = Practiceinfo::find(Session::get('practice_id'));
        $directory = $result->documents_dir . $pid . "/mtm";
        if (file_exists($directory)) {
            foreach (scandir($directory) as $item) {
                if ($item == '.' || $item == '..') {
                    continue;
                }
                unlink($directory.DIRECTORY_SEPARATOR.$item);
            }
        } else {
            mkdir($directory, 0775);
        }
        $input = "";
        $file_path_cp = $directory . '/cp.pdf';
        $html_cp = $this->page_mtm_cp($pid)->render();
        if (file_exists($file_path_cp)) {
            unlink($file_path_cp);
        }
        $this->generate_pdf($html_cp, $file_path_cp, 'mtmfooterpdf', '', '1');
        while (!file_exists($file_path_cp)) {
            sleep(2);
        }
        $input = $file_path_cp;
        $file_path_map = $directory . '/map.pdf';
        $html_map = $this->page_mtm_map($pid)->render();
        if (file_exists($file_path_map)) {
            unlink($file_path_map);
        }
        $this->generate_pdf($html_map, $file_path_map, 'mtmfooterpdf', '', '1');
        while (!file_exists($file_path_map)) {
            sleep(2);
        }
        $input .= " " . $file_path_map;
        $file_path_pml = $directory . '/pml.pdf';
        $html_pml = $this->page_mtm_pml($pid)->render();
        if (file_exists($file_path_pml)) {
            unlink($file_path_pml);
        }
        $this->generate_pdf($html_pml, $file_path_pml, 'mtmfooterpdf', 'mtmheaderpdf', '1', Session::get('pid'));
        while (!file_exists($file_path_pml)) {
            sleep(2);
        }
        $input .= " " . $file_path_pml;
        $file_path = $result->documents_dir . $pid . "/mtm_" . time() . ".pdf";
        $commandpdf1 = "pdftk " . $input . " cat output " . $file_path;
        $commandpdf2 = escapeshellcmd($commandpdf1);
        exec($commandpdf2);
        while (!file_exists($file_path)) {
            sleep(2);
        }
        $pages_data = array(
            'documents_url' => $file_path,
            'pid' => $pid,
            'documents_type' => 'Letters',
            'documents_desc' => 'Medication Therapy Management Letter for ' . Session::get('ptname'),
            'documents_from' => Session::get('displayname'),
            'documents_viewed' => Session::get('displayname'),
            'documents_date' => date('Y-m-d H:i:s', time())
        );
        $arr['id'] = DB::table('documents')->insertGetId($pages_data);
        $this->audit('Add');
        $arr['message'] = 'OK';
        echo json_encode($arr);
    }

    public function postPrintMtmProvider($type)
    {
        ini_set('memory_limit', '196M');
        $pid = Session::get('pid');
        $result = Practiceinfo::find(Session::get('practice_id'));
        $directory = $result->documents_dir . $pid;
        $file_path_provider = $directory . '/mtm_' . time() . '_provider.pdf';
        $html_provider = $this->page_mtm_provider($pid)->render();
        if (file_exists($file_path_provider)) {
            unlink($file_path_provider);
        }
        $this->generate_pdf($html_provider, $file_path_provider, 'footerpdf', '', '1');
        while (!file_exists($file_path_provider)) {
            sleep(2);
        }
        $pages_data = array(
            'documents_url' => $file_path_provider,
            'pid' => $pid,
            'documents_type' => 'Letters',
            'documents_desc' => 'Medication Therapy Management Provider Letter for ' . Session::get('ptname'),
            'documents_from' => Session::get('displayname'),
            'documents_viewed' => Session::get('displayname'),
            'documents_date' => date('Y-m-d H:i:s', time())
        );
        $arr['id'] = DB::table('documents')->insertGetId($pages_data);
        $this->audit('Add');
        if ($type == "print") {
            $arr['message'] = 'OK';
        }
        if ($type == "fax") {
            $arr['message'] = $this->fax_document($pid, 'MTM Provider Letter', 'yes', $file_path_provider, '', Input::get('faxnumber'), Input::get('faxrecipient'), '', 'yes');
        }
        echo json_encode($arr);
    }

    public function postEncounterMtm()
    {
        $practice_id = Session::get('practice_id');
        $pid = Session::get('pid');
        $query = DB::table('mtm')->where('pid', '=', $pid)->where('complete', '=', 'no')->where('practice_id', '=', $practice_id)->get();
        $data['value'] = "";
        $data['duration'] = "";
        if ($query) {
            $data['value'] = "Medication Therapy Management Topics and Recommendations:\n";
            foreach ($query as $row) {
                $data['value'] .= "Topic: " . $row->mtm_description . "\n";
                $data['value'] .= "Recommendations: " . $row->mtm_recommendations . "\n";
                if ($row->mtm_beneficiary_notes != '') {
                    $data['value'] .= "Patient Notes: " . $row->mtm_beneficiary_notes . "\n";
                }
                if ($row->mtm_action != '') {
                    $data['value'] .= "Actions Taken: " . $row->mtm_action . "\n";
                }
                if ($row->mtm_outcome != '') {
                    $data['value'] .= "Outcome: " . $row->mtm_outcome . "\n";
                }
                if ($row->mtm_duration != '') {
                    $data['duration'] += str_replace(" minutes", "", $row->mtm_duration);
                }
                $data['value'] .= "\n";
            }
        }
        echo json_encode($data);
    }



	public function postMtmMedicationList() {
		$query1 = DB::table('rx_list')
			->where('pid', '=', $pid)
			->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')
			->where('rxl_date_old', '=', '0000-00-00 00:00:00')
			->get();
		$result1 = '';
		if ($query1) {
			foreach ($query1 as $row1) {
				if ($row1->rxl_sig != '') {
					$result1 .= $row1->rxl_medication . ' ' . $row1->rxl_dosage . ' ' . $row1->rxl_dosage_unit . ', ' . $row1->rxl_sig . ' ' . $row1->rxl_route . ' ' . $row1->rxl_frequency . ' for ' . $row1->rxl_reason . "\n";
				} else {
					$result1 .= $row1->rxl_medication . ' ' . $row1->rxl_dosage . ' ' . $row1->rxl_dosage_unit . ', ' . $row1->rxl_instructions . ' for ' . $row1->rxl_reason . "\n";
				}
			}
		} else {
			$result1 .= 'None.';
		}
		$result1 = trim($result1);
		echo $result1;
	}

	public function postMtmGetMedicationList() {
		$query = DB::table('other_history')->where('eid', '=', Session::get('eid'))->first();
		if ($query) {
			$data['oh_meds'] = $query->oh_meds;
		} else {
			$data['oh_meds'] = '';
		}
		echo json_encode($data);
	}

	public function postMtmSaveMedicationList() {
		$eid = Session::get('eid');
		$pid = Session::get('pid');
		$encounter_provider = Session::get('displayname');
		$data = array(
			'eid' => $eid,
			'pid' => $pid,
			'encounter_provider' => $encounter_provider,
			'oh_meds' => Input::get('oh_meds')
		);
		$count = DB::table('other_history')->where('eid', '=', $eid)->first();
		if ($count) {
			DB::table('other_history')->where('eid', '=', $eid)->update($data);
			$this->audit('Update');
			$this->api_data('update', 'other_history', 'eid', $eid);
			$result = 'Medication List Updated.';
		} else {
			DB::table('other_history')->insert($data);
			$this->audit('Add');
			$this->api_data('add', 'other_history', 'eid', $eid);
			$result = 'Medication List Added.';
		}
		echo $result;
	}

	public function postMtmEncounters()
	{
		$practice_id = Session::get('practice_id');
		$pid = Session::get('pid');
		$page = Input::get('page');
		$limit = Input::get('rows');
		$sidx = Input::get('sidx');
		$sord = Input::get('sord');
		$query = DB::table('encounters')->where('pid', '=', $pid)
			->where('addendum', '=', 'n')
			->where('encounter_template', '=', 'standardmtm')
			->where('practice_id', '=', $practice_id);
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

	public function postMtmMedicationHistory($eid)
	{
		$practice_id = Session::get('practice_id');
		$pid = Session::get('pid');
		$page = Input::get('page');
		$limit = Input::get('rows');
		$sidx = Input::get('sidx');
		$sord = Input::get('sord');
		$query = DB::table('other_history')->where('eid', '=', $eid)->first();
		if($query) {
			$meds = $query->oh_meds;
			if ($meds != '') {
				$meds_array = explode("\n", trim($meds));
				$count = count($meds_array);
				$total_pages = ceil($count/$limit);
				if ($page > $total_pages) $page=$total_pages;
				$start = $limit*$page - $limit;
				if($start < 0) $start = 0;
				if ($sord == 'asc') {
					sort($meds_array);
				} else {
					rsort($meds_array);
				}
				$meds_array1 = array_slice($meds_array, $start, $limit);
				foreach($meds_array1 as $row) {
					$response['rows'][]['mtm_medication'] = $row;
				}
			} else {
				$count = 0;
				$total_pages = 0;
				$response['rows'] = '';
			}
		} else {
			$count = 0;
			$total_pages = 0;
			$response['rows'] = '';
		}
		$response['page'] = $page;
		$response['total'] = $total_pages;
		$response['records'] = $count;
		echo json_encode($response);
	}

    public function postMtmAlerts()
	{
		if (Session::get('group_id') != '2') {
			Auth::logout();
			Session::flush();
			header("HTTP/1.1 404 Page Not Found", true, 404);
			exit("You cannot do this.");
		} else {
			$practice_id = Session::get('practice_id');
			$page = Input::get('page');
			$limit = Input::get('rows');
			$sidx = Input::get('sidx');
			$sord = Input::get('sord');
			$query = DB::table('alerts')
				->join('demographics', 'alerts.pid', '=', 'demographics.pid')
				->where('alerts.alert_date_complete', '=', '0000-00-00 00:00:00')
				->where('alerts.alert_reason_not_complete', '=', '')
				->where('alerts.alert', '=', 'Medication Therapy Management')
				->where('alerts.practice_id', '=', $practice_id)
				->get();
			if($query) {
				$count = count($query);
				$total_pages = ceil($count/$limit);
			} else {
				$count = 0;
				$total_pages = 0;
			}
			if ($page > $total_pages) $page=$total_pages;
			$start = $limit*$page - $limit;
			if($start < 0) $start = 0;
			$query1 = DB::table('alerts')
				->join('demographics', 'alerts.pid', '=', 'demographics.pid')
				->where('alerts.alert_date_complete', '=', '0000-00-00 00:00:00')
				->where('alerts.alert_reason_not_complete', '=', '')
				->where('alerts.alert', '=', 'Medication Therapy Management')
				->where('alerts.practice_id', '=', $practice_id)
				->orderBy($sidx, $sord)
				->skip($start)
				->take($limit)
				->get();
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
	}
}
