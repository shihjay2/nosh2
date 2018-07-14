<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use Config;
use DB;
use Date;
use Excel;
use File;
use Form;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Schema;
use Session;
use SoapBox\Formatter\Formatter;
use URL;

class AjaxSearchController extends Controller {

    /**
    * NOSH ChartingSystem Search Ajax Functions
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
    }

    public function copy_address(Request $request)
    {
        $row = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $return = (array)$row;
        $return['DOB'] = date('Y-m-d', $this->human_to_unix($return['DOB']));
        return $return;
    }

    public function rx_json(Request $request)
    {
        if (Session::has('rx_json')) {
            return Session::get('rx_json');
        } else {
            $url = 'http://rxnav.nlm.nih.gov/REST/displaynames.json';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_FAILONERROR,1);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_TIMEOUT, 15);
            $json = curl_exec($ch);
            curl_close($ch);
            $rxnorm = json_decode($json, true);
            Session::put('rx_json', $rxnorm['displayTermsList']['term']);
            return $rxnorm['displayTermsList']['term'];
        }
    }

    public function rxnorm(Request $request)
    {
        $return = $this->rxnorm_search($request->input('term'));
        return $return;
    }

    public function search_address(Request $request)
    {
        $q = strtolower($request->input('search_address'));
        if (!$q) return;
        $data['response'] = 'false';
        $query = DB::table('addressbook')->where('displayname', 'LIKE', "%$q%")->get();
        if ($query->count()) {
            $data['message'] = [];
            $data['response'] = 'li';
            foreach ($query as $row) {
                $data['message'][] = [
                    'id' => $row->address_id,
                    'label' => $row->displayname,
                    'value' => $row->address_id,
                ];
            }
        }
        return $data;
    }

    public function search_cpt(Request $request)
    {
        $q = strtolower($request->input('search_cpt'));
        if (!$q) return;
        $data['response'] = 'false';
        $data['message'] = [];
        if ($q == "***") {
            $query0 = DB::table('cpt_relate')->where('favorite', '=', '1')->where('practice_id', '=', Session::get('practice_id'))->get();
            if ($query0) {
                $data['response'] = 'li';
                foreach ($query0 as $row0) {
                    $records0 = $row0->cpt_description . ' [' . $row0->cpt . ']';
                    $unit0 = $row0->unit;
                    if ($row0->unit == null) {
                        $unit0 = '1';
                    }
                    $data['message'][] = [
                        'label' => $records0,
                        'value' => $row0->cpt,
                        'charge' => $row0->cpt_charge,
                        'unit' => $unit0,
                        'category' => 'Favorites',
                        'category_id' => 'favorite_cpt_result'
                    ];
                }
            }
        } else {
            $pos2 = explode(',', $q);
            $query2 = DB::table('cpt_relate')->where('practice_id', '=', Session::get('practice_id'));
            if ($pos2 == FALSE) {
                $query2->where(function($query_array1) use ($q) {
                    $q = strtolower(Input::get('term'));
                    $query_array1->where('cpt_description', 'LIKE', "%$q%")
                    ->orWhere('cpt', 'LIKE', "%$q%");
                });
            } else {
                $query2->where(function($query_array1) use ($q, $pos2) {
                    foreach ($pos2 as $r) {
                        $query_array1->where('cpt_description', 'LIKE', "%$r%");
                    }
                    $query_array1->orWhere('cpt', 'LIKE', "%$q%");
                });
            }
            $result2 = $query2->get();
            if ($result2) {
                $data['response'] = 'li';
                foreach ($result2 as $row2) {
                    if (array_search($row2->cpt, array_column($data['message'], 'value')) === false) {
                        $records2 = $row2->cpt_description . ' [' . $row2->cpt . ']';
                        $unit2 = $row2->unit;
                        if ($row2->unit == null) {
                            $unit2 = '1';
                        }
                        $data['message'][] = [
                            'label' => $records2,
                            'value' => $row2->cpt,
                            'charge' => $row2->cpt_charge,
                            'unit' => $unit2,
                            'category' => 'Practice CPT Database',
                            'category_id' => 'practice_cpt_result'
                        ];
                    }
                }
            }
            $pos1 = explode(',', $q);
            Config::set('excel.csv.delimiter', "\t");
            $reader = Excel::load(resource_path() . '/CPT.txt');
            $arr = $reader->noHeading()->get()->toArray();
            $i = 0;
            foreach ($arr as $row) {
                $precpt[$i] = [
                    'cpt' => $row[0],
                    'desc' => $row[3]
                ];
                $i++;
            }
            if (count($pos1) == 1) {
                $result1a = array_where($precpt, function($value, $key) use ($q) {
                    if (stripos($value['desc'] , $q) !== false) {
                        return true;
                    }
                });
                $result1b = array_where($precpt, function($value, $key) use ($q) {
                    if (stripos($value['cpt'] , $q) !== false) {
                        return true;
                    }
                });
                $result1 = array_merge($result1a, $result1b);
            } else {
                $result1 = array_where($precpt, function($value, $key) use ($pos1) {
                    if ($this->striposa($value['desc'] , $pos1) !== false) {
                        return true;
                    }
                });
            }
            if ($result1) {
                $data['response'] = 'li';
                foreach ($result1 as $row1) {
                    if (array_search($row1['cpt'], array_column($data['message'], 'value')) === false) {
                        $records1 = $row1['desc'] . ' [' . $row1['cpt'] . ']';
                        $data['message'][] = [
                            'label' => $records1,
                            'value' => $row1['cpt'],
                            'charge' => '',
                            'unit' => '1',
                            'category' => 'Universal CPT Database',
                            'category_id' => 'universal_cpt_result'
                        ];
                    }
                }
            }
        }
        return $data;
    }

    public function search_encounters(Request $request)
    {
        $data['response'] = 'n';
        $query = DB::table('encounters')
            ->where('pid', '=', $request->input('pid'))
            ->where('bill_submitted', '=', 'Done')
            ->where('addendum', '=', 'n')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->orderBy('encounter_DOS', 'desc')
            ->get();
        if ($query->count()) {
            $data['response'] = 'y';
            foreach ($query as $row) {
                $data['options'][$row->eid] = date('Y-m-d', $this->human_to_unix($row->encounter_DOS)) . ' - ' . $row->encounter_cc;
            }
        }
        return $data;
    }

    public function search_guardian(Request $request)
    {
        $q = strtolower($request->input('search_guardian'));
        if (!$q) return;
        $data['response'] = 'false';
        $data['message'] = [];
        if (($handle = fopen(resource_path() . '/familyrole.csv', "r")) !== FALSE) {
            while (($role = fgetcsv($handle, 0, ",")) !== FALSE) {
                if ($role[0] != '') {
                    $pre[] = [
                        'code' => $role[0],
                        'desc' => ucwords($role[1])
                    ];
                }
            }
            fclose($handle);
        }
        $result1 = array_where($pre, function($value, $key) use ($q) {
            if (stripos($value['desc'] , $q) !== false) {
                return true;
            }
        });
        if ($result1) {
            $data['response'] = 'li';
            foreach ($result1 as $row1) {
                $data['message'][] = [
                    'label' => $row1['desc'],
                    'value' => $row1['desc'],
                    'code' => $row1['code']
                ];
            }
        }
        return $data;
    }

    public function search_healthwise(Request $request)
    {
        $q = strtolower($request->input('search_healthwise'));
        if (!$q) return;
        $data['response'] = 'false';
        $data['message'] = [];
        $yaml = File::get(resource_path() . '/healthwise.yaml');
        $formatter = Formatter::make($yaml, Formatter::YAML);
        $arr = $formatter->toArray();
        $pos1 = explode(',', $q);
        if (count($pos1) == 1) {
            $result1 = array_where($arr, function($value, $key) use ($q) {
                if (stripos($value['desc'] , $q) !== false) {
                    return true;
                }
            });
        } else {
            $result1 = array_where($arr, function($value, $key) use ($pos1) {
                if ($this->striposa($value['desc'] , $pos1) !== false) {
                    return true;
                }
            });
        }
        if ($result1) {
            $data['response'] = 'li';
            foreach ($result1 as $row1) {
                $data['message'][] = [
                    'label' => $row1['desc'],
                    'value' => $row1['desc'],
                    'url' => $row1['url']
                ];
            }
        }
        return $data;
    }

    public function search_icd(Request $request, $assessment=false)
    {
        ini_set('memory_limit','196M');
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $q = strtolower($request->input('search_icd'));
        if (!$q) return;
        $data['response'] = 'false';
        if ($practice->icd == '9') {
            $query = DB::table('icd9');
            $pos = explode(',', $q);
            if ($pos == FALSE) {
                $query->where('icd9_description', 'LIKE', "%$q%");
            } else {
                foreach ($pos as $p) {
                    $query->where('icd9_description', 'LIKE', "%$p%");
                }
            }
            $query->orWhere('icd9', 'LIKE', "%$q%");
            $result = $query->get();
            if ($result->count()) {
                $data['message'] = [];
                $data['response'] = 'li';
                if ($assessment == true) {
                    $data['response'] = 'div';
                }
                foreach ($result as $row) {
                    $records = $row->icd9_description . ' [' . $row->icd9 . ']';
                    if ($assessment == true) {
                        $data['message'][] = [
                            'id' => $row->icd9,
                            'label' => $records,
                            'value' => $records,
                            'href' => route('encounter_assessment_add', ['icd', $records])
                        ];
                    } else {
                        $data['message'][] = [
                            'label' => $records,
                            'value' => $records
                        ];
                    }
                }
            }
        } else {
            $pos = explode(' ', $q);
            $data['message'] = [];
            // ICD10data
            $icd10q = implode('+', $pos);
            $icd10data = $this->icd10data($icd10q);
            if (! empty($icd10data)) {
                $data['response'] = 'li';
                if ($assessment == true) {
                    $data['response'] = 'div';
                }
            }
            foreach ($icd10data as $icd10data_r) {
                if ($assessment == true) {
                    $data['message'][] = [
                        'id' => $icd10data_r['code'],
                        'label' => $icd10data_r['desc'],
                        'value' => $icd10data_r['desc'],
                        'href' => route('encounter_assessment_add', ['icd', $icd10data_r['code']]),
                        'category' => 'ICD10Data',
                        'category_id' => 'icd10data_result',
                        'icd10type' => '1',
                    ];
                } else {
                    $data['message'][] = [
                        'id' => $icd10data_r['code'],
                        'label' => $icd10data_r['desc'],
                        'value' => $icd10data_r['desc'],
                        'category' => 'ICD10Data',
                        'category_id' => 'icd10data_result',
                        'icd10type' => '1',
                    ];
                }
            }
            // Get common codes
            $common = File::get(resource_path() . '/common_icd.yaml');
            $formatter = Formatter::make($common, Formatter::YAML);
            $common_arr_pre = $formatter->toArray();
            $common_arr = [];
            // Add all primary care codes
            $default_arr = ['Family Practice', 'Internal Medicine', 'Obstetrics & Gynaecology', 'Primary Care', 'Pediatrics'];
            foreach ($default_arr as $default) {
                foreach ($common_arr_pre[$default] as $common1) {
                    if (array_search($common1['code'], array_column($common_arr, 'code')) === false) {
                        $common_arr[] = $common1;
                    }
                }
                unset($common_arr_pre[$default]);
            }
            $common_arr_keys = array_keys($common_arr_pre);
            if (Session::get('group_id') == '2') {
                $user = DB::table('providers')->where('id', '=', Session::get('user_id'))->first();
                $specialty = $user->specialty;
                $common_key_index = $this->closest_match($specialty, $common_arr_keys);
                $common_key = $common_arr_keys[$common_key_index];
                foreach ($common_arr_pre[$common_key] as $common2) {
                    if (array_search($common2['code'], array_column($common_arr, 'code')) === false) {
                        $common_arr[] = $common2;
                    }
                }
            }
            if (count($pos) == 1) {
                $common_result = array_where($common_arr, function($value, $key) use ($q) {
                    if (stripos($value['desc'] , $q) !== false) {
                        return true;
                    }
                });
                $common_result1 = array_where($common_arr, function($value, $key) use ($q) {
                    if (stripos($value['code'] , $q) !== false) {
                        return true;
                    }
                });
                $common_result = array_merge($common_result, $common_result1);
            } else {
                $common_result = array_where($common_arr, function($value, $key) use ($pos) {
                    if ($this->striposa($value['desc'] , $pos) !== false) {
                        return true;
                    }
                });
            }
            if ($common_result) {
                $data['response'] = 'li';
                if ($assessment == true) {
                    $data['response'] = 'div';
                }
                foreach ($common_result as $common_row) {
                    $common_records = $common_row['desc'] . ' [' . $common_row['code'] . ']';
                    // $records = $row->icd10_description . ' [' . $row->icd10 . ']';
                    if ($assessment == true) {
                        $data['message'][] = [
                            'id' => $common_row['code'],
                            'label' => $common_records,
                            'value' => $common_records,
                            'href' => route('encounter_assessment_add', ['icd', $common_row['code']]),
                            'category' => 'Common Library',
                            'category_id' => 'common_icd_result',
                            'icd10type' => '1',
                        ];
                    } else {
                        $data['message'][] = [
                            'id' => $common_row['code'],
                            'label' => $common_records,
                            'value' => $common_records,
                            'category' => 'Common Library',
                            'category_id' => 'common_icd_result',
                            'icd10type' => '1',
                        ];
                    }
                }
            }
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
            if (count($pos) == 1) {
                $result = array_where($preicd, function($value, $key) use ($q) {
                    if (stripos($value['desc'] , $q) !== false) {
                        return true;
                    }
                });
                $result1 = array_where($preicd, function($value, $key) use ($q) {
                    if (stripos($value['icd10'] , $q) !== false) {
                        return true;
                    }
                });
                $result = array_merge($result, $result1);
            } else {
                $result = array_where($preicd, function($value, $key) use ($pos) {
                    if ($this->striposa($value['desc'] , $pos) !== false) {
                        return true;
                    }
                });
            }
            if ($result) {
                if (!isset($data['message'])) {
                    $data['message'] = [];
                }
                $data['response'] = 'li';
                if ($assessment == true) {
                    $data['response'] = 'div';
                }
                foreach ($result as $row) {
                    $records = $row['desc'] . ' [' . $row['icd10'] . ']';
                    if ($assessment == true) {
                        $href = route('encounter_assessment_add', ['icd', $row['icd10']]);
                        if ($row['type'] == '0') {
                            $href = '#';
                        }
                        $data['message'][] = [
                            'id' => $row['icd10'],
                            'label' => $records,
                            'value' => $records,
                            'href' => $href,
                            'icd10type' => $row['type'],
                            'category' => 'Universal Library',
                            'category_id' => 'universal_icd_result'
                        ];
                    } else {
                        $data['message'][] = [
                            'id' => $row['icd10'],
                            'label' => $records,
                            'value' => $records,
                            'icd10type' => $row['type'],
                            'category' => 'Universal Library',
                            'category_id' => 'universal_icd_result'
                        ];
                    }
                }
            }
        }
        return $data;
    }

    public function search_icd_specific(Request $request)
    {
        $data = [];
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
        $result = array_where($preicd, function($value, $key) use ($request) {
            if (stripos($value['icd10'] , $request->input('icd')) !== false) {
                return true;
            }
        });
        if ($result) {
            foreach ($result as $row) {
                if ($row['type'] == '1') {
                    $records = $row['desc'] . ' [' . $row['icd10'] . ']';
                    $data[] = [
                        'id' => $row['icd10'],
                        'label' => $records,
                        'value' => $records,
                        'href' => route('encounter_assessment_add', ['icd', $row['icd10']])
                    ];
                }
            }
        }
        return $data;
    }

    public function search_imaging(Request $request)
    {
        $q = strtolower($request->input('search_imaging'));
        if (!$q) return;
        $data['response'] = 'false';
        $data['message'] = [];
        if (($handle = fopen(resource_path() . '/imaging.csv', "r")) !== FALSE) {
            while (($data1 = fgetcsv($handle, 0, "\t")) !== FALSE) {
                if ($data1[0] != '') {
                    $pre[] = [
                        'code' => $data1[0],
                        'desc' => $data1[1]
                    ];
                }
            }
            fclose($handle);
        }
        $pos = explode(' ', $q);
        if (count($pos) == 1) {
            $result = array_where($pre, function($value, $key) use ($q) {
                if (stripos($value['desc'] , $q) !== false) {
                    return true;
                }
            });
        } else {
            $result = array_where($pre, function($value, $key) use ($pos) {
                if ($this->striposa($value['desc'] , $pos) !== false) {
                    return true;
                }
            });
        }
        if ($result) {
            $data['response'] = 'li';
            foreach ($result as $row) {
                $data['message'][] = [
                    'label' => $row['desc'],
                    'value' => $row['desc'] . ' [' . $row['code'] . ']'
                ];
            }
        }
        return $data;
    }

    public function search_immunization(Request $request)
    {
        $q = strtolower($request->input('search_immunization'));
        if (!$q) return;
        $data['response'] = 'false';
        $data['message'] = [];
        $pos1 = explode(',', $q);
        Config::set('excel.csv.delimiter', "|");
        $reader = Excel::load(resource_path() . '/cvx.txt');
        $arr = $reader->noHeading()->get()->toArray();
        foreach ($arr as $row) {
            if ($row[4] == 'Active') {
                $pre[] = [
                    'cvx' => rtrim($row[0]),
                    'short_desc' => $row[1],
                    'long_desc' => ucfirst($row[2])
                ];
            }
        }
        if (count($pos1) == 1) {
            $result1a = array_where($pre, function($value, $key) use ($q) {
                if (stripos($value['cvx'] , $q) !== false) {
                    return true;
                }
            });
            $result1b = array_where($pre, function($value, $key) use ($q) {
                if (stripos($value['long_desc'] , $q) !== false) {
                    return true;
                }
            });
            $result1 = array_merge($result1a, $result1b);
        } else {
            $result1 = array_where($pre, function($value, $key) use ($pos1) {
                if ($this->striposa($value['long_desc'] , $pos1) !== false) {
                    return true;
                }
            });
        }
        if ($result1) {
            $data['response'] = 'li';
            foreach ($result1 as $row1) {
                if (array_search($row1['cvx'], array_column($data['message'], 'cvx')) === false) {
                    $data['message'][] = [
                        'label' => $row1['short_desc'],
                        'value' => $row1['long_desc'],
                        'cvx' => $row1['cvx']
                    ];
                }
            }
        }
        return $data;
    }

    public function search_immunization_inventory(Request $request)
    {
        $q = strtolower($request->input('search_immunization_inventory'));
        if (!$q) return;
        $data['response'] = 'false';
        $data['message'] = [];
        $query = DB::table('vaccine_inventory')
            ->where('quantity', '>', '0')
            ->where('imm_immunization', 'LIKE', "%$q%")
            ->where('practice_id', '=', Session::get('practice_id'))
            ->distinct()
            ->get();
        if ($query->count()) {
            $data['message'] = [];
            $data['response'] = 'li';
            foreach ($query as $row) {
                $label = $row->imm_immunization . ", Quantity left: " . $row->quantity;
                $data['message'][] = [
                    'label' => $label,
                    'value' => $row->imm_immunization,
                    'cvx' => $row->imm_cvxcode,
                    'manufacturer' => $row->imm_manufacturer,
                    'lot' => $row->imm_lot,
                    'expire' => date('Y-m-d', $this->human_to_unix($row->imm_expiration))
                ];
            }
        }
        return $data;
    }

    public function search_insurance(Request $request)
    {
        $q = strtolower($request->input('search_insurance'));
        if (!$q) return;
        $data['response'] = 'false';
        $query = DB::table('addressbook')->where('specialty', '=', 'Insurance')->where('displayname', 'LIKE', "%$q%")->get();
        if ($query->count()) {
            $data['message'] = [];
            $data['response'] = 'li';
            foreach ($query as $row) {
                $data['message'][] = [
                    'id' => $row->address_id,
                    'label' => $row->displayname,
                    'value' => $row->address_id,
                    'insurance_plan_name' => $row->displayname
                ];
            }
        }
        return $data;
    }

    public function search_interactions(Request $request)
    {
        $pid = Session::get('pid');
        $rxl_medication = $request->input('rxl_medication');
        $rxcui = $request->input('rxcui');
        if ($rxcui == '') {
            $rx_query = DB::table('rx_list')->where('rxl_medication', '=', $rxl_medication)->whereNotNull('rxl_ndcid')->first();
            if ($rx_query) {
                $url = 'http://rxnav.nlm.nih.gov/REST/rxcui.json?idtype=NDC&id=' . $rx_query->rxl_ndcid;
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $url);
                curl_setopt($ch,CURLOPT_FAILONERROR,1);
                curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                curl_setopt($ch,CURLOPT_TIMEOUT, 15);
                $json = curl_exec($ch);
                curl_close($ch);
                $rxnorm = json_decode($json, true);
                if (isset($rxnorm['idGroup']['rxnormId'])) {
                    $rxcui = $rxnorm['idGroup']['rxnormId'][0];
                }
            }
        }
        $rx = explode(" ", $rxl_medication);
        $return['info'] = '';
        $allergies = DB::table('allergies')
            ->where('pid', '=', $pid)
            ->where('allergies_date_inactive', '=', '0000-00-00 00:00:00')
            ->where('allergies_med', '=', $rxl_medication)
            ->first();
        if ($allergies) {
            // Match any allergies
            $return['info'] .= '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle fa-fw fa-lg"></i>';
            $return['info'] .= '<h5>ALERT:</h5>Medication prescribed is in the patient allergy list!';
            $return['info'] .= '</div>';
            return $return;
        }
        $meds = DB::table('rx_list')
            ->where('pid', '=', $pid)
            ->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')
            ->where('rxl_date_old', '=', '0000-00-00 00:00:00')
            ->get();
        $q_arr = [];
        if ($meds->count()) {
            // Gather all existing meds and get their rxcuis, if any
            foreach ($meds as $med) {
                if ($med->rxl_ndcid !== null) {
                    $url1 = 'http://rxnav.nlm.nih.gov/REST/rxcui.json?idtype=NDC&id=' . $med->rxl_ndcid;
                    $ch1 = curl_init();
                    curl_setopt($ch1,CURLOPT_URL, $url1);
                    curl_setopt($ch1,CURLOPT_FAILONERROR,1);
                    curl_setopt($ch1,CURLOPT_FOLLOWLOCATION,1);
                    curl_setopt($ch1,CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch1,CURLOPT_TIMEOUT, 15);
                    $json1 = curl_exec($ch1);
                    curl_close($ch1);
                    $rxnorm1 = json_decode($json1, true);
                    if (isset($rxnorm1['idGroup']['rxnormId'])) {
                        $q_arr[] = $rxnorm1['idGroup']['rxnormId'][0];
                    }
                }
            }
        }
        if (! empty($q_arr)) {
            // If more than on rxcui is gathered, proceed with interaction check
            $q_arr[] = $rxcui;
            $q = implode('+', $q_arr);
            $url2 = 'http://rxnav.nlm.nih.gov/REST/interaction/list.json?rxcuis=' . $q;
            $ch2 = curl_init();
            curl_setopt($ch2,CURLOPT_URL, $url2);
            curl_setopt($ch2,CURLOPT_FAILONERROR,1);
            curl_setopt($ch2,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch2,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch2,CURLOPT_TIMEOUT, 15);
            $json2 = curl_exec($ch2);
            curl_close($ch2);
            $rxnorm2 = json_decode($json2, true);
            if (isset($rxnorm2['fullInteractionTypeGroup'][0]['fullInteractionType'][0]['interactionPair'][0]['description'])) {
                foreach ($rxnorm2['fullInteractionTypeGroup'][0]['fullInteractionType'] as $item) {
                    $return['info'] .= '<div class="alert alert-warning"><i class="fa fa-exclamation-circle fa-fw fa-lg"></i><h5>INTERACTION:</h5>';
                    $return['info'] .= $item['interactionPair'][0]['description'];
                    $return['info'] .= '</div><br>';
                }
            }

        }
        return $return;
    }

    public function search_language(Request $request)
    {
        $q = strtolower($request->input('search_language'));
        if (!$q) return;
        $data['response'] = 'false';
        $data['message'] = [];
        if (($lang_handle = fopen(resource_path() . '/lang.csv', "r")) !== FALSE) {
            while (($lang1 = fgetcsv($lang_handle, 0, "\t")) !== FALSE) {
                if ($lang1[0] != '') {
                    $pre[] = [
                        'code' => $lang1[0],
                        'desc' => $lang1[6]
                    ];
                }
            }
            fclose($lang_handle);
        }
        $result1 = array_where($pre, function($value, $key) use ($q) {
            if (stripos($value['desc'] , $q) !== false) {
                return true;
            }
        });
        if ($result1) {
            $data['response'] = 'li';
            foreach ($result1 as $row1) {
                $data['message'][] = [
                    'label' => $row1['desc'],
                    'value' => $row1['desc'],
                    'code' => $row1['code']
                ];
            }
        }
        return $data;
    }

    public function search_loinc(Request $request)
    {
        $q = strtolower($request->input('search_loinc'));
        if (!$q) return;
        $data['response'] = 'false';
        $reader = Excel::load(resource_path() . '/LOINC.csv');
        $results = $reader->get()->toArray();
        $result = [];
        $pos = explode(' ', $q);
        if (count($pos) == 1) {
            $result = array_where($results, function($value, $key) use ($q) {
                if (stripos($value['long_common_name'] , $q) !== false) {
                    return true;
                }
            });
        } else {
            $result = array_where($results, function($value, $key) use ($pos) {
                if ($this->striposa($value['long_common_name'] , $pos) !== false) {
                    return true;
                }
            });
        }
        if (! empty($result)) {
            $data['message'] = [];
            $data['response'] = 'li';
            foreach($result as $row) {
                $arr[] = [
                    'loinc' => $row['loinc'],
                    'description' => $row['long_common_name']
                ];
                $label = $row['long_common_name'] . ' [' . $row['loinc'] . ']';
                $data['message'][] = [
                    'id' => $row['loinc'],
                    'label' => $label,
                    'value' => $label
                ];
            }
        }
        return $data;
    }

    public function search_ndc(Request $request)
    {
        $url = 'https://rxnav.nlm.nih.gov/REST/rxcui/' . $request->input('rxcui') . '/ndcs.json';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 15);
        $json = curl_exec($ch);
        curl_close($ch);
        $rxnorm = json_decode($json, true);
        $ndcid = '';
        if (isset($rxnorm['ndcGroup']['ndcList']['ndc'])) {
            $ndcid = $rxnorm['ndcGroup']['ndcList']['ndc'][0];
        }
        return $ndcid;
    }

    public function search_patient(Request $request)
    {
        $q = strtolower($request->input('search_patient'));
        if (!$q) return;
        $data['response'] = 'false';
        $query = DB::table('demographics')
            ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
            ->select('demographics.lastname', 'demographics.firstname', 'demographics.DOB', 'demographics.pid')
            ->where('demographics_relate.practice_id', '=', Session::get('practice_id'))
            ->where(function($query_array1) use ($q) {
                $query_array1->where('demographics.lastname', 'LIKE', "%$q%")
                ->orWhere('demographics.firstname', 'LIKE', "%$q%")
                ->orWhere('demographics.pid', 'LIKE', "%$q%");
            })
            ->get();
        if ($query->count()) {
            $data['message'] = [];
            $data['response'] = $request->input('type');
            foreach ($query as $row) {
                $dob = date('m/d/Y', strtotime($row->DOB));
                $name = $row->lastname . ', ' . $row->firstname . ' (DOB: ' . $dob . ') (ID: ' . $row->pid . ')';
                $href = route('set_patient', [$row->pid]);
                if (Session::get('group_id') == '1') {
                    $href = route('print_chart_admin', [$row->pid]);
                }
                $data['message'][] = [
                    'id' => 'pid',
                    'label' => $name,
                    'value' => $row->pid,
                    'ptname' => $name,
                    'href' => $href
                ];
            }
        }
        return $data;
    }

    public function search_patient_history(Request $request)
    {
        $result = [];
        if (Session::has('history_pid')) {
            $result = Session::get('history_pid');
        }
        $data['response'] = 'false';
        if (! empty($result)) {
            $data['message'] = [];
            $data['response'] = 'div';
            foreach ($result as $pid) {
                $row = DB::table('demographics')->where('pid', '=', $pid)->first();
                $dob = date('m/d/Y', strtotime($row->DOB));
                $name = $row->lastname . ', ' . $row->firstname . ' (DOB: ' . $dob . ') (ID: ' . $row->pid . ')';
                $data['message'][] = [
                    'id' => 'pid',
                    'label' => $name,
                    'value' => $row->pid,
                    'ptname' => $name,
                    'href' => route('set_patient', [$row->pid])
                ];
            }
        }
        return $data;
    }

    public function search_rx(Request $request)
    {
        $q = $request->input('search_rx');
        if (!$q) return;
        $q1 = explode(' ', $q);
        $data['response'] = 'false';
        $url = 'http://rxnav.nlm.nih.gov/REST/Prescribe/drugs.json?name=' . $q1[0];
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 15);
        $json = curl_exec($ch);
        curl_close($ch);
        $rxnorm = json_decode($json, true);
        $result = [];
        $i = 0;
        if (isset($rxnorm['drugGroup']['conceptGroup'])) {
            foreach ($rxnorm['drugGroup']['conceptGroup'] as $rxnorm_row1) {
                if ($rxnorm_row1['tty'] == 'SBD' || $rxnorm_row1['tty'] == 'SCD') {
                    if (isset($rxnorm_row1['conceptProperties'])) {
                        foreach($rxnorm_row1['conceptProperties'] as $item) {
                            $result[$i]['rxcui'] = $item['rxcui'];
                            $result[$i]['name'] = $item['name'];
                            if ($rxnorm_row1['tty'] == 'SBD') {
                                $result[$i]['category'] = 'Brand';
                            } else {
                                $result[$i]['category'] = 'Generic';
                            }
                            $i++;
                        }
                    }
                }
            }
            uasort($result, function ($a, $b){
                return $a['name'] <=> $b['name'];
            });
        }
        if (isset($result[0])) {
            $data['message'] = [];
            $data['response'] = 'li';
            foreach ($result as $row) {
                $arr = explode(' / ', $row['name']);
                $units = ['MG', 'MG/ML', 'MCG'];
                $dosage_arr = [];
                $unit_arr = [];
                foreach ($arr as $row1) {
                    $arr = explode(' ', $row1);
                    foreach ($units as $unit) {
                        $key = array_search($unit, $arr);
                        if ($key) {
                            $key1 = $key-1;
                            $dosage_arr[] = $arr[$key1];
                            $unit_arr[] = $arr[$key];
                        }
                    }
                }
                $data['message'][] = [
                    'id' => $row['rxcui'],
                    'label' => $row['name'],
                    'value' => $row['name'],
                    'badge' => $row['category'],
                    'dosage' => implode(';', $dosage_arr),
                    'unit' => implode(';', $unit_arr),
                    'rxcui' => $row['rxcui']
                ];
            }
        }
        return $data;
    }

    public function search_referral_provider(Request $request)
    {
        $data['response'] = 'n';
        $query = $this->array_orders_provider('Referral', $request->input('specialty'));
        if (! empty($query)) {
            $data['response'] = 'y';
            foreach ($query as $k => $v) {
                $data['options'][$k] = $v;
            }
        }
        return $data;
    }

    public function search_specialty(Request $request)
    {
        $q = strtolower($request->input('search_specialty'));
        if (!$q) return;
        $data['response'] = 'false';
        $data['message'] = [];
        $pos1 = explode(',', $q);
        Config::set('excel.csv.delimiter', ",");
        $reader = Excel::load(resource_path() . '/nucc_taxonomy.csv');
        $arr = $reader->noHeading()->get()->toArray();
        $i = 0;
        foreach ($arr as $row) {
            $pre[$i] = [
                'code' => $row[0],
                'classification' => $row[2],
                'specialization' => $row[3]
            ];
            $i++;
        }
        if (count($pos1) == 1) {
            $result1a = array_where($pre, function($value, $key) use ($q) {
                if (stripos($value['specialization'] , $q) !== false) {
                    return true;
                }
            });
            $result1b = array_where($pre, function($value, $key) use ($q) {
                if (stripos($value['classification'] , $q) !== false) {
                    return true;
                }
            });
            $result1 = array_merge($result1a, $result1b);
        } else {
            $result1 = array_where($pre, function($value, $key) use ($pos1) {
                if ($this->striposa($value['specialization'] , $pos1) !== false) {
                    return true;
                }
            });
        }
        if ($result1) {
            $data['response'] = 'li';
            foreach ($result1 as $row1) {
                if ($row1['specialization'] !== '' && $row1['specialization'] !== null) {
                    $records = $row1['classification'] . ', ' . $row1['specialization'];
                } else {
                    $records = $row1['classification'];
                }
                $records1 = $records . ' (' . $row1['code'] . ')';
                $data['message'][] = [
                    'label' => $records1,
                    'value' => $records,
                    'code' => $row1['code']
                ];
            }
        }
        return $data;
    }

    public function search_supplement(Request $request, $order)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $q = strtolower($request->input('search_supplement'));
        if (!$q) return;
        $data['response'] = 'false';
        $query = DB::table('supplement_inventory')
            ->where('sup_description', 'LIKE', "%$q%")
            ->where('quantity',  '>', '0')
            ->where('practice_id', '=', Session::get('practice_id'))
            ->select('sup_description', 'quantity', 'cpt', 'charge', 'sup_strength', 'supplement_id')
            ->distinct()
            ->get();
        if ($query->count()) {
            $data['message'] = [];
            $data['response'] = 'li';
            foreach ($query as $row) {
                if ($order == "Y") {
                    if(strpos($row->sup_strength, " ") === FALSE) {
                        $dosage_array[0] = $row->sup_strength;
                        $dosage_array[1] = '';
                    } else {
                        $dosage_array = explode(' ', $row->sup_strength);
                    }
                    $label = $row->sup_description . ", Quantity left: " . $row->quantity1;
                    $data['message'][] = [
                        'label' => $label,
                        'value' => $row->sup_description,
                        'category' => 'Supplements Inventory',
                        'quantity' => $row->quantity1,
                        'dosage' => $dosage_array[0],
                        'dosage_unit' => $dosage_array[1],
                        'supplement_id' => $row->supplement_id
                    ];
                } else {
                    if(strpos($row->sup_strength, " ") === FALSE) {
                        $dosage_array[0] = $row->sup_strength;
                        $dosage_array[1] = '';
                    } else {
                        $dosage_array = explode(' ', $row->sup_strength);
                    }
                    $data['message'][] = [
                        'label' => $row->sup_description,
                        'value' => $row->sup_description,
                        'category' => 'Supplements Inventory',
                        'dosage' => $dosage_array[0],
                        'dosage_unit' => $dosage_array[1],
                        'supplement_id' => $row->supplement_id
                    ];
                }
            }
        }
        $query0 = DB::table('sup_list')
            ->where('sup_supplement', 'LIKE', "%$q%")
            ->select('sup_supplement', 'sup_dosage', 'sup_dosage_unit')
            ->distinct()
            ->get();
        if ($query0->count()) {
            if (!isset($data['message'])) {
                $data['message'] = [];
                $data['response'] = 'li';
            }
            foreach ($query0 as $row0) {
                if ($order == "Y") {
                    $label0 = $row0->sup_supplement . ", Dosage: " . $row0->sup_dosage . " " . $row0->sup_dosage_unit;
                    $data['message'][] = [
                        'label' => $label0,
                        'value' => $row0->sup_supplement,
                        'category' => 'Previously Prescribed',
                        'quantity' => '',
                        'dosage' => $row0->sup_dosage,
                        'dosage_unit' => $row0->sup_dosage_unit,
                        'supplement_id' => ''
                    ];
                } else {
                    $data['message'][] = [
                        'label' => $row0->sup_supplement,
                        'value' => $row0->sup_supplement,
                        'category' => ''
                    ];
                }
            }
        }
        $yaml = File::get(resource_path() . '/supplements.yaml');
        $formatter = Formatter::make($yaml, Formatter::YAML);
        $arr = $formatter->toArray();
        $result1 = array_where($arr, function($value, $key) use ($q) {
            if (stripos($value , $q) !== false) {
                return true;
            }
        });
        if ($result1) {
            $data['response'] = 'li';
            foreach ($result1 as $row1) {
                if ($order == "Y") {
                    $data['message'][] = [
                        'label' => $row1,
                        'value' => $row1,
                        'category' => 'Supplement Database',
                        'quantity' => '',
                        'dosage' => '',
                        'dosage_unit' => '',
                        'supplement_id' => ''
                    ];
                } else {
                    $data['message'][] = [
                        'label' => $row1,
                        'value' => $row1,
                        'category' => ''
                    ];
                }
            }
        }
        return $data;
    }

    // Tag functions
    public function tags(Request $request)
    {
        $data = [];
        $query = DB::table('tags')
            ->join('tags_relate', 'tags_relate.tags_id', '=', 'tags.tags_id')
            ->select('tags.tag')
            ->where('tags_relate.practice_id', '=', Session::get('practice_id'))
            ->distinct()
            ->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $data[] =  $row->tag;
            }
        }
        return $data;
    }

    public function tag_save(Request $request, $type, $id)
    {
        $row1 = DB::table('tags')->where('tag', '=', $request->input('tag'))->first();
        if ($row1) {
            $tags_id = $row1->tags_id;
        } else {
            $data1['tag'] = $request->input('tag');
            $tags_id = DB::table('tags')->insertGetId($data1);
            $this->audit('Add');
        }
        $data2 = [
            'tags_id' => $tags_id,
            $type => $id,
            'pid' => Session::get('pid'),
            'practice_id' => Session::get('practice_id')
        ];
        DB::table('tags_relate')->insert($data2);
        $this->audit('Add');
        return 'Tag added.';
    }

    public function tag_remove(Request $request, $type, $id)
    {
        $row = DB::table('tags')->where('tag', '=', $request->input('tag'))->first();
        DB::table('tags_relate')->where('tags_id', '=', $row->tags_id)->where($type, '=', $id)->delete();
        $this->audit('Delete');
        return 'Tag removed.';
    }

    // Tagsinput functions
    public function tagsinput_icd()
    {
        $data = [];
        // Assessments of current encounter
        if (Session::has('eid')) {
            $dxs = DB::table('assessment')->where('eid', '=', Session::get('eid'))->first();
            if ($dxs) {
                $dx_array = [];
                $dx_pre_array = [];
                for ($j = 1; $j <= 12; $j++) {
                    $col0 = 'assessment_' . $j;
                    if ($dxs->{$col0} !== '') {
                        $dx_pre_array[] = $j;
                    }
                }
                foreach ($dx_pre_array as $dx_num) {
                    $col = 'assessment_' . $dx_num;
                    $data[] = $dxs->{$col};
                }
            }
        }
        // Problem list
        $issues = DB::table('issues')->where('pid', '=', Session::get('pid'))->orderBy('issue', 'asc')->where('issue_date_inactive', '=', '0000-00-00 00:00:00')->get();
        if ($issues->count()) {
            foreach ($issues as $issue) {
                $issue_arr = explode('[', $issue->issue);
                if (count($issue_arr) > 1) {
                    if (in_array($issue->issue, $data) == false) {
                        $data[] = $issue->issue;
                    }
                }
            }
        }
        return $data;
    }

    public function tagsinput_icd_all()
    {
        $data = [];
        // Assessments of current encounter
        if (Session::has('eid')) {
            $dxs = DB::table('assessment')->where('eid', '=', Session::get('eid'))->first();
            if ($dxs) {
                $dx_array = [];
                $dx_pre_array = [];
                for ($j = 1; $j <= 12; $j++) {
                    $col0 = 'assessment_' . $j;
                    if ($dxs->{$col0} !== '') {
                        $dx_pre_array[] = $j;
                    }
                }
                foreach ($dx_pre_array as $dx_num) {
                    $col = 'assessment_' . $dx_num;
                    $data[] = $dxs->{$col};
                }
            }
        }
        // Problem list
        $issues = DB::table('issues')->where('pid', '=', Session::get('pid'))->orderBy('issue', 'asc')->where('issue_date_inactive', '=', '0000-00-00 00:00:00')->get();
        if ($issues->count()) {
            foreach ($issues as $issue) {
                $issue_arr = explode('[', $issue->issue);
                if (count($issue_arr) > 1) {
                    if (in_array($issue->issue, $data) == false) {
                        $data[] = $issue->issue;
                    }
                }
            }
        }
        return $data;
    }

    // Template functions
    public function template_get(Request $request)
    {
        $data['response'] = 'false';
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        if ($user->template == null || $user->template == '') {
            $data1['template'] = File::get(resource_path() . '/template.yaml');
            DB::table('users')->where('id', '=', Session::get('user_id'))->update($data1);
            $yaml = $data1['template'];
        } else {
            $yaml = $user->template;
        }
        $formatter = Formatter::make($yaml, Formatter::YAML);
        $array = $formatter->toArray();
        // If target doesn't exist, make one in template.yaml
        if (!isset($array[$request->input('id')])) {
            $array[$request->input('id')] = [];
            $formatter1 = Formatter::make($array, Formatter::ARR);
            $user_data['template'] = $formatter1->toYaml();
            DB::table('users')->where('id', '=', Session::get('user_id'))->update($user_data);
            $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
            $yaml = $user->template;
            $formatter = Formatter::make($yaml, Formatter::YAML);
            $array = $formatter->toArray();
        }
        $replace_arr = $this->array_template();
        if (is_array($array[$request->input('id')])) {
            // $data['response'] = 'li';
            foreach ($array[$request->input('id')] as $key => $value) {
                if ($request->has('template_group')) {
                    if ($request->input('template_group') == $key) {
                        foreach ($value as $row_k => $row_v) {
                            if ($row_k !== 'gender' && $row_k !== 'age') {
                                $normal = null;
                                $input = null;
                                $options = null;
                                $orders = null;
                                $age = null;
                                $gender = null;
                                $proceed = true;
                                if (isset($row_v['normal'])) {
                                    $normal = $row_v['normal'];
                                }
                                if (isset($row_v['input'])) {
                                    $input = $row_v['input'];
                                }
                                if (isset($row_v['options'])) {
                                    $options = $row_v['options'];
                                }
                                if (isset($row_v['gender'])) {
                                    $gender = $row_v['gender'];
                                }
                                if (isset($row_v['age'])) {
                                    $age = $row_v['age'];
                                }
                                if (isset($row_v['orders'])) {
                                    $orders = $row_v['orders'];
                                }
                                if (Session::has('gender')) {
                                    if (Session::get('gender') == 'male') {
                                        if ($gender == 'f' || $gender == 'u') {
                                            $proceed = false;
                                        }
                                    } elseif (Session::get('gender') == 'female') {
                                        if ($gender == 'm' || $gender == 'u') {
                                            $proceed = false;
                                        }
                                    } else {
                                        if ($gender == 'm' || $gender == 'f') {
                                            $proceed = false;
                                        }
                                    }
                                }
                                if (Session::has('agealldays')) {
                                    $agealldays = Session::get('agealldays');
                                    if ($agealldays <= 6574.5) {
                                        if ($age == 'adult') {
                                            $proceed = false;
                                        }
                                    } else {
                                        if ($age == 'child') {
                                            $proceed = false;
                                        }
                                    }
                                }
                                if ($proceed == true) {
                                    $data['message'][] = [
                                        'value' => str_replace(array_keys($replace_arr), $replace_arr, $row_v['text']),
                                        'normal' => $normal,
                                        'input' => $input,
                                        'options' => $options,
                                        'orders' => $orders,
                                        'gender' => $gender,
                                        'age' => $age,
                                        'group' => $request->input('template_group'),
                                        'id' => $row_k,
                                        'category' => $request->input('id')
                                    ];
                                }
                            }
                        }
                    }
                } else {
                    $proceed = true;
                    $age = null;
                    $gender = null;
                    if (isset($value['gender'])) {
                        $gender = $value['gender'];
                    }
                    if (isset($value['age'])) {
                        $age = $value['age'];
                    }
                    if (Session::has('gender')) {
                        if (Session::get('gender') == 'male') {
                            if ($gender == 'f' || $gender == 'u') {
                                $proceed = false;
                            }
                        } elseif (Session::get('gender') == 'female') {
                            if ($gender == 'm' || $gender == 'u') {
                                $proceed = false;
                            }
                        } else {
                            if ($gender == 'm' || $gender == 'f') {
                                $proceed = false;
                            }
                        }
                    }
                    if (Session::has('agealldays')) {
                        $agealldays = Session::get('agealldays');
                        if ($agealldays <= 6574.5) {
                            if ($age == 'adult') {
                                $proceed = false;
                            }
                        } else {
                            if ($age == 'child') {
                                $proceed = false;
                            }
                        }
                    }
                    if ($proceed == true) {
                        $data['message'][] = [
                            'category' => $request->input('id'),
                            'value' => $key
                        ];
                    }
                }
            }
        }
        if (isset($data['message'])) {
            $data['response'] = 'li';
        }
        return $data;
    }

    public function template_edit(Request $request)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $formatter = Formatter::make($user->template, Formatter::YAML);
        $array = $formatter->toArray();
        $arr['response'] = 'yes';
        if ($request->input('template_edit_type') == 'item') {
            $new['text'] =  $request->input('template_text');
            if ($request->input('template_gender') !== '') {
                $new['gender'] = $request->input('template_gender');
            }
            if ($request->input('template_age') !== '') {
                $new['age'] = $request->input('template_age');
            }
            if ($request->input('template_input_type') !== '') {
                $new['input'] = $request->input('template_input_type');
            }
            if ($request->input('template_options') !== '') {
                $new['options'] = $request->input('template_options');
            }
            if ($request->input('template_options_orders_facility') !== '') {
                $orders['facility'] = $request->input('template_options_orders_facility');
                $orders['orders_code'] = $request->input('template_options_orders_orders_code');
                $orders['cpt'] = $request->input('template_options_orders_cpt');
                $orders['loinc'] = $request->input('template_options_orders_loinc');
                $orders['results_code'] = $request->input('template_options_orders_results_code');
                $new['orders'] = $orders;
            }
            if ($request->input('id') == 'new') {
                $array[$request->input('category')][$request->input('group_name')][] = $new;
                $arr['message'] = 'Template added';
            } else {
                $array[$request->input('category')][$request->input('group_name')][$request->input('id')] = $new;
                $arr['message'] = 'Template updated';
            }
        } else {
            if (isset($array[$request->input('category')][$request->input('template_text')])) {
                if ($request->input('group_name') == '') {
                    $arr['response'] = 'no';
                    $arr['message'] = 'Error: Group name already exists';
                } else {
                    if ($request->input('template_gender') !== '') {
                        $array[$request->input('category')][$request->input('template_text')]['gender'] = $request->input('template_gender');
                    }
                    if ($request->input('template_age') !== '') {
                        $array[$request->input('category')][$request->input('template_text')]['age'] = $request->input('template_age');
                    }
                    $arr['message'] = 'Template group updated';
                }
            } else {
                if ($request->input('group_name') == '') {
                    if( !is_array($array[$request->input('category')])) {
                        $array[$request->input('category')] = [];
                    }
                    $array[$request->input('category')][$request->input('template_text')] = [];
                    if ($request->input('template_gender') !== '') {
                        $array[$request->input('category')][$request->input('template_text')]['gender'] = $request->input('template_gender');
                    }
                    if ($request->input('template_age') !== '') {
                        $array[$request->input('category')][$request->input('template_text')]['age'] = $request->input('template_age');
                    }
                    $arr['message'] = 'Template group added';
                } else {
                    $array[$request->input('category')][$request->input('template_text')] = $array[$request->input('category')][$request->input('group_name')];
                    if ($request->input('template_gender') !== '') {
                        $array[$request->input('category')][$request->input('template_text')]['gender'] = $request->input('template_gender');
                    }
                    if ($request->input('template_age') !== '') {
                        $array[$request->input('category')][$request->input('template_text')]['age'] = $request->input('template_age');
                    }
                    unset($array[$request->input('category')][$request->input('group_name')]);
                    $arr['message'] = 'Template group updated';
                }
            }
        }
        if ($arr['response'] == 'yes') {
            $formatter1 = Formatter::make($array, Formatter::ARR);
            $data['template'] = $formatter1->toYaml();
            DB::table('users')->where('id', '=', Session::get('user_id'))->update($data);
        }
        return $arr;
    }

    public function template_normal(Request $request)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $formatter = Formatter::make($user->template, Formatter::YAML);
        $array = $formatter->toArray();
        $data = [];
        foreach ($array[$request->input('category')][$request->input('group_name')] as $row) {
            if (isset($row['normal'])) {
                if ($row['normal'] == true) {
                    $data[] = $row['text'];
                }
            }
        }
        return $data;
    }

    public function template_normal_change(Request $request)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $formatter = Formatter::make($user->template, Formatter::YAML);
        $array = $formatter->toArray();
        if ($request->input('template_normal_item') == 'y') {
            unset($array[$request->input('category')][$request->input('group_name')][$request->input('id')]['normal']);
            $message = 'Template unset as normal';
        } else {
            $array[$request->input('category')][$request->input('group_name')][$request->input('id')]['normal'] = true;
            $message = 'Template set as normal';
        }
        $formatter1 = Formatter::make($array, Formatter::ARR);
        $data['template'] = $formatter1->toYaml();
        DB::table('users')->where('id', '=', Session::get('user_id'))->update($data);
        return $message;
    }

    public function template_remove(Request $request)
    {
        $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
        $formatter = Formatter::make($user->template, Formatter::YAML);
        $array = $formatter->toArray();
        if ($request->input('template_edit_type') == 'item') {
            unset($array[$request->input('category')][$request->input('group_name')][$request->input('id')]);
            $message = 'Template deleted';
        } else {
            unset($array[$request->input('category')][$request->input('group_name')]);
            $message = 'Template group deleted';
        }
        $formatter1 = Formatter::make($array, Formatter::ARR);
        $data['template'] = $formatter1->toYaml();
        DB::table('users')->where('id', '=', Session::get('user_id'))->update($data);
        return $message;
    }

    public function template_restore(Request $request, $action='')
    {
        if ($action == '') {
            $data1['template'] = File::get(resource_path() . '/template.yaml');
            DB::table('users')->where('id', '=', Session::get('user_id'))->update($data1);
            $message = 'Template restored to default';
        }
        if ($action == 'backup') {
            $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
            if ($user->template == null || $user->template == '') {
                $yaml = File::get(resource_path() . '/template.yaml');
            } else {
                $yaml = $user->template;
            }
            $file_name = time() . '_template_' . Session::get('user_id') . ".yaml";
            $file_path = public_path() . '/temp/' . $file_name;
            File::put($file_path, $yaml);
            return response()->download($file_path)->deleteFileAfterSend(true);
        }
        if ($action == 'upload') {
            if ($request->isMethod('post')) {
                $file = $request->file('file_input');
                $directory = public_path() . '/temp/';
                $file_name = time() . '_template_' . Session::get('user_id') . ".yaml";
                $file_path = $directory . $file_name;
                $file->move($directory, $file_name);
                while(!file_exists($file_path)) {
                    sleep(2);
                }
                $data2['template'] = File::get($file_path);
                DB::table('users')->where('id', '=', Session::get('user_id'))->update($data2);
                $message = 'Template uploaded and set';
            } else {
                $data['panel_header'] = 'Upload your templates';
                $data['document_upload'] = route('template_restore', ['upload']);
                $type_arr = ['yaml'];
                $data['document_type'] = json_encode($type_arr);
                $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
                $dropdown_array['default_button_text_url'] = Session::get('last_page');
                $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                $data['assets_js'] = $this->assets_js('document_upload');
                $data['assets_css'] = $this->assets_css('document_upload');
                return view('document_upload', $data);
            }
        }
        Session::get('message_action', $message);
        return redirect(Session::get('last_page'));
    }

    // Typeahead functions
    public function typeahead($table, $column, $subtype='')
    {
        $data = [];
        $query = DB::table($table);
        if ($subtype == 'address_id') {
            $query->select($column, 'address_id');
        } else {
            $query->select($column);
        }
        $query->distinct();
        if ($subtype == 'pharmacy') {
            $query->where('specialty', '=', 'Pharmacy');
        }
        if ($subtype == 'provider') {
            $query->where('group_id', '=', '2')->where('practice_id', '=', Session::get('practice_id'));
        }
        $result = $query->get();
        if ($result->count()) {
            foreach ($result as $row) {
                if ($row->{$column} !== null) {
                    if ($subtype == 'address_id') {
                        $data[] = $row->{$column} . ' [' . $row->address_id . ']';
                    } else {
                        $data[] = $row->{$column};
                    }
                }
            }
        }
        return $data;
    }

    public function md_nosh_providers(Request $request)
    {
        $url = 'http://noshchartingsystem.com/oidc/providersearch?term=' . $request->input('term');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        $return = '';
        if(curl_errno($ch)) {
            $return = 'Error: ' . curl_error($ch);
        } else {
            if ($result['response'] != 'false') {
                $i = 0;
                foreach ($result['message'] as $row) {
                    $return .= '<label for="mdnosh_provider_' . $i . '" class="pure-checkbox" style="display:block;margin-left:20px;">';
                    $return .= Form::radio('mdnosh_email', $row['email'], false, ['id' => 'mdnosh_email_' . $i, 'style' => 'float:left; margin-left:-20px; margin-right:7px;', 'class' => 'mdnosh_email_select', 'mdnosh-name' => $row['given_name'] . ' ' . $row['family_name']]);
                    $return .= ' <span id="mdnosh_email_label_span_' . $i . '">' . $row['label'] . '</span>';
                    $return .= '</label>';
                    $i++;
                }
            } else {
                $return .= 'No providers identified with the search terms provided';
            }
        }
        return $return;
    }
}
