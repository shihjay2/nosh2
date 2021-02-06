<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use Config;
use Crypt;
use Date;
use DB;
use File;
use Form;
use HTML;
use Imagick;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;
use PdfMerger;
use QrCode;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Response;
use Schema;
use Session;
use Illuminate\Support\Facades\Storage;
use Shihjay2\OpenIDConnectUMAClient;
use URL;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class ChartController extends Controller {

    public function __construct()
    {
        $this->middleware('checkinstall');
        $this->middleware('auth');
        $this->middleware('csrf');
        $this->middleware('postauth');
        $this->middleware('patient');
    }

    public function action_edit(Request $request, $table, $index, $id, $yaml_id='new', $column='actions')
    {
        $arr = [];
        $query = DB::table($table)->where($index, '=', $id)->first();
        if ($query) {
            if ($query->{$column} !== '' && $query->{$column} !== null) {
                $arr = Yaml::parse($query->{$column});
            }
        }
        $name = 'action';
        $label = trans('noshform.action');
        if ($column == 'proc_description') {
            $name = 'procedure';
            $label = trans('noshform.procedure_description');
        }
        if ($request->isMethod('post')) {
            if ($column == 'proc_description') {
                $item['type'] = $request->input('type');
                $item['code'] = $request->input('code');
            }
            $item[$name] = $request->input($name);
            $item['timestamp'] = date('Y-m-d H:i:s');
            if ($yaml_id == 'new') {
                $arr[] = $item;
                $message = ucfirst(trans('noshform.added')) . ' ' . $name;
            } else {
                $arr[$yaml_id] = $item;
                $message = ucfirst(trans('noshform.updated')) . ' ' . $name;
            }
            $data1[$column] = Yaml::dump($arr);
            $exist = DB::table($table)->where($index, '=', $id)->first();
            if ($exist) {
                if ($table == 'procedure') {
                    $data1['proc_date'] = date("Y-m-d H:i:s", time());
                }
                DB::table($table)->where($index, '=', $id)->update($data1);
                $this->audit('Update');
            } else {
                $data1[$index] = $id;
                if ($column == 'proc_description') {
                    $data1['pid'] = Session::get('pid');
                    $data1['encounter_provider'] = Session::get('displayname');
                }
                DB::table($table)->insert($data1);
                $this->audit('Add');
            }
            Session::put('message_action', $message);
            $redirect = Session::get('action_redirect');
            Session::forget('action_redirect');
            return redirect($redirect);
        } else {
            $code = null;
            $proc_type = null;
            if ($yaml_id == 'new') {
                $action = null;
                $data['panel_header'] = trans('noshform.add') . ' ' . ucfirst($name);
            } else {
                $action = $arr[$yaml_id][$name];
                if (isset($arr[$yaml_id]['code'])) {
                    $code = $arr[$yaml_id]['code'];
                }
                if (isset($arr[$yaml_id]['type'])) {
                    $proc_type = $arr[$yaml_id]['type'];
                }
                $data['panel_header'] = trans('noshform.edit') . ' ' . ucfirst($name);
            }
            if ($column == 'proc_description') {
                $items[] = [
                    'name' => 'type',
                    'label' => trans('noshform.procedure_type'),
                    'type' => 'text',
                    'default_value' => $proc_type
                ];
                $items[] = [
                    'name' => 'code',
                    'label' => trans('noshform.procedure_code'),
                    'type' => 'text',
                    'default_value' => $code
                ];
            }
            $items[] = [
                'name' => $name,
                'label' => $label,
                'type' => 'textarea',
                'default_value' => $action
            ];
            $form_array = [
                'form_id' => 'action_form',
                'action' => route('action_edit', [$table, $index, $id, $yaml_id, $column]),
                'items' => $items,
                'origin' => Session::get('action_redirect')
            ];
            $data['content'] = $this->form_build($form_array);
            if ($table == 't_messages') {
                $data['t_messages_active'] = true;
            }
            if ($table == 'hpi' || $table == 'procedure') {
                $data['encounters_active'] = true;
            }
            $data['template_content'] = 'test';
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function alerts_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('alerts')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'));
        $type_arr = [
            'active' => [trans('noshform.alert_active'), 'fa-bell'],
            'pending' => [trans('noshform.alert_pending'), 'fa-clock-o'],
            'results' => [trans('noshform.alert_pending_results'), 'fa-flask'],
            'completed' => [trans('noshform.alert_completed'), 'fa-check'],
            'canceled' => [trans('noshform.alert_canceled'), 'fa-times']
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('alerts_list', [$key])
                ];
            }
        }
        if ($type == 'active') {
            $query->where('alert_date_active', '<=', date('Y-m-d H:i:s', time() + 1209600))
                ->whereNull('alert_date_complete')
                ->where('alert_reason_not_complete', '=', '');
        }
        if ($type == 'completed') {
            $query->whereNotNull('alert_date_complete')
                ->where('alert_reason_not_complete', '=', '');
        }
        if ($type == 'canceled') {
            $query->whereNull('alert_date_complete')
                ->where('alert_reason_not_complete', '!=', '');
        }
        if ($type == 'pending') {
            $query->where('alert_date_active', '>', date('Y-m-d H:i:s', time() + 1209600))
                ->whereNull('alert_date_complete')
                ->where('alert_reason_not_complete', '=', '');
        }
        if ($type == 'results') {
            $query->whereNull('alert_date_complete')
                ->where('alert_reason_not_complete', '=', '')
                ->where(function($query_array1) {
                    $query_array1->where('alert', '=', 'Laboratory results pending')
                    ->orWhere('alert', '=', 'Radiology results pending')
                    ->orWhere('alert', '=', 'Cardiopulmonary results pending')
                    ->orWhere('results', '=', 1);
                });
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('7');
        $columns = Schema::getColumnListing('alerts');
        $row_index = $columns[0];
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = $row->alert . ' (' . trans('noshform.due') . ' ' . date('m/d/Y', $this->human_to_unix($row->alert_date_active)) . ') - ' . $row->alert_description;
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['alerts', $row_index, $row->$row_index]);
                    if ($type == 'active' || $type == 'pending' || $type == 'results') {
                        if ($type == 'results' || $row->alert == 'Laboratory results pending' || $row->alert == 'Radiology results pending' || $row->alert == 'Cardiopulmonary results pending') {
                            $arr['reply'] = route('results_reply');
                        }
                        $arr['complete'] = route('chart_action', ['table' => 'alerts', 'action' => 'complete', 'index' => $row_index, 'id' => $row->$row_index]);
                        $arr['inactivate'] = route('chart_form', ['alerts', $row_index, $row->$row_index, 'incomplete']);
                    } else {
                        $arr['reactivate'] = route('chart_action', ['table' => 'alerts', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                    $arr['delete'] = route('chart_action', ['table' => 'alerts', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'alerts_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['alerts_active'] = true;
        $data['panel_header'] = trans('noshform.alerts');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => '',
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['alerts', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if (Session::has('eid')) {
            // Mark conditions list as reviewed
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function allergies_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('allergies')->where('pid', '=', Session::get('pid'))->orderBy('allergies_med', 'asc');
        if ($type == 'active') {
            $query->whereNull('allergies_date_inactive');
            $dropdown_array = [
                'items_button_text' => trans('noshform.allergies_active')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.allergies_inactive'),
                'icon' => 'fa-times',
                'url' => route('allergies_list', ['inactive'])
            ];
        } else {
            $query->whereNotNull('allergies_date_inactive');
            $dropdown_array = [
                'items_button_text' => trans('noshform.allergies_inactive')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.allergies_active'),
                'icon' => 'fa-check',
                'url' => route('allergies_list', ['active'])
            ];
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('7');
        $columns = Schema::getColumnListing('allergies');
        $row_index = $columns[0];
        $list_array = [];
        if ($result->count()) {
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = $row->allergies_med . ' - ' . $row->allergies_reaction;
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['allergies', $row_index, $row->$row_index]);
                    if ($type == 'active') {
                        $arr['inactivate'] = route('chart_action', ['table' => 'allergies', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    } else {
                        $arr['reactivate'] = route('chart_action', ['table' => 'allergies', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                    if (Session::get('group_id') == '2') {
                        $arr['delete'] = route('chart_action', ['table' => 'allergies', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                }
                if ($row->reconcile !== null && $row->reconcile !== 'y') {
                    $arr['danger'] = true;
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'allergies_list');
        } else {
            $return .= ' ' . trans('noshform.nkda') . '.';
        }
        $data['content'] = $return;
        $data['allergies_active'] = true;
        $data['panel_header'] = trans('noshform.allergies');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => '',
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['allergies', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if (Session::has('eid') && $type == 'active') {
            if (Session::get('group_id') == '2') {
                // Mark conditions list as reviewed by physician
                $allergies_encounter = '';
                if (! empty($list_array)) {
                    $allergies_encounter .= implode("\n", array_column($list_array, 'label'));
                }
                App::setLocale(Session::get('practice_locale'));
                $allergies_encounter .= "\n" . trans('noshform.reviewed_by') . ' ' . Session::get('displayname') . ' ' . trans('noshform.on') . ' ' . date('Y-m-d');
                $encounter_query = DB::table('other_history')->where('eid', '=', Session::get('eid'))->first();
                $encounter_data['oh_allergies'] = $allergies_encounter;
                if ($encounter_query) {
                    $encounter_data['oh_date'] = date("Y-m-d H:i:s", time());
                    DB::table('other_history')->where('eid', '=', Session::get('eid'))->update($encounter_data);
                } else {
                    $encounter_data['eid'] = Session::get('eid');
                    $encounter_data['pid'] = Session::get('pid');
                    DB::table('other_history')->insert($encounter_data);
                }
                if ($data['message_action'] !== '') {
                    $data['message_action'] .= '<br>';
                }
                App::setLocale(Session::get('user_locale'));
                $data['message_action'] .= trans('noshform.allergies_message_action_encounter');
            }
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function api_patient(Request $request)
    {
        $query = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
        $url = rtrim($query->uma_uri, '/') . '/get_mdnosh';
        $result = $this->fhir_request($url, false, Session::get('uma_auth_access_token'));
        return $result;
    }

    public function billing_delete_invoice(Request $request, $id)
    {
        DB::table('billing_core')->where('billing_core_id', '=', $id)->delete();
        $this->audit('Delete');
        DB::table('billing_core')->where('other_billing_id', '=', $id)->delete();
        $this->audit('Delete');
        Session::get('message_action', trans('noshform.billing_message_action_delete'));
        return redirect(Session::get('last_page'));
    }

    public function billing_details(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'cpt_charge' => 'numeric'
            ]);
            $id = $request->input('other_billing_id');
            $data = [
                'eid' => '0',
                'pid' => Session::get('pid'),
                'dos_f' => $request->input('dos_f'),
                'cpt_charge' => $request->input('cpt_charge'),
                'reason' => $request->input('reason'),
                'unit' => '1',
                'payment' => '0',
                'practice_id' => Session::get('practice_id')
            ];
            if ($id !== 'new') {
                DB::table('billing_core')->where('billing_core_id', '=', $id)->update($data);
                $this->audit('Update');
                $message = trans('billing_message_action_update');
            } else {
                $id1 = DB::table('billing_core')->insertGetId($data, 'billing_core_id');
                $this->audit('Add');
                $data1['other_billing_id'] = $id1;
                DB::table('billing_core')->where('billing_core_id', '=', $id1)->update($data1);
                $this->audit('Update');
                $message = trans('noshform.billing_message_action_added');
            }
            Session::put('message_action', $message);
            return redirect(Session::get('last_page'));
        } else {
            if ($id == 'new') {
                $result = [
                    'eid' => '0',
                    'pid' => Session::get('pid'),
                    'payment' => '0',
                    'unit' => '1',
                    'practice_id' => Session::get('practice_id'),
                    'reason' => null,
                    'dos_f' => date('Y-m-d'),
                    'cpt_charge' => null
                ];
                $data['panel_header'] = trans('noshform.billing_add_misc');
            } else {
                $query = DB::table('billing_core')->where('billing_core_id', '=', $id)->first();
                $result = [
                    'eid' => $query->eid,
                    'pid' => $query->pid,
                    'payment' => $query->payment,
                    'unit' => $query->unit,
                    'practice_id' => $query->practice_id,
                    'reason' => $query->reason,
                    'dos_f' => date('Y-m-d', $this->human_to_unix($query->dos_f)),
                    'cpt_charge' => $query->cpt_charge
                ];
                $data['panel_header'] = trans('noshform.billing_update_misc');
            }
            $items[] = [
                'name' => 'eid',
                'type' => 'hidden',
                'default_value' => $result['eid']
            ];
            $items[] = [
                'name' => 'pid',
                'type' => 'hidden',
                'required' => true,
                'default_value' => $result['pid']
            ];
            $items[] = [
                'name' => 'payment',
                'type' => 'hidden',
                'default_value' => $result['payment']
            ];
            $items[] = [
                'name' => 'unit',
                'type' => 'hidden',
                'required' => true,
                'default_value' => $result['unit']
            ];
            $items[] = [
                'name' => 'practice_id',
                'type' => 'hidden',
                'required' => true,
                'default_value' => $result['practice_id']
            ];
            $items[] = [
                'name' => 'dos_f',
                'label' => trans('noshform.date_of_charge'),
                'type' => 'date',
                'required' => true,
                'default_value' => $result['dos_f']
            ];
            $items[] = [
                'name' => 'cpt_charge',
                'label' => trans('noshform.charge_amount'),
                'type' => 'text',
                'required' => true,
                'default_value' => $result['cpt_charge']
            ];
            $items[] = [
                'name' => 'reason',
                'label' => trans('noshform.misc_reason'),
                'type' => 'text',
                'required' => true,
                'typeahead' => route('typeahead', ['table' => 'billing_core', 'column' => 'reason']),
                'default_value' => $result['reason']
            ];
            $form_array = [
                'form_id' => 'payment_form',
                'action' => route('billing_details', [$id]),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            $data['content'] = $this->form_build($form_array);
            $data['billing_active'] = true;
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function billing_make_payment(Request $request, $id, $index, $billing_id='')
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'payment' => 'numeric'
            ]);
            $data = [
                'eid' => $request->input('eid'),
                'other_billing_id' => $request->input('other_billing_id'),
                'pid' => $request->input('pid'),
                'dos_f' => date('m/d/Y', strtotime($request->input('dos_f'))),
                'payment' => $request->input('payment'),
                'payment_type' => $request->input('payment_type'),
                'practice_id' => Session::get('practice_id')
            ];
            if ($billing_id !== '') {
                DB::table('billing_core')->where('billing_core_id', '=', $billing_id)->update($data);
                $this->audit('Update');
                $message = trans('noshform.payment') . ' ' . trans('noshform.updated');
            } else {
                DB::table('billing_core')->insert($data);
                $this->audit('Add');
                $message = trans('noshform.payment') . ' ' . trans('noshform.added');
            }
            Session::put('message_action', $message);
            return redirect(Session::get('billing_last_page'));
        } else {
            if ($billing_id == '') {
                $result = [
                    'eid' => 0,
                    'other_billing_id' => 0,
                    'pid' => Session::get('pid'),
                    'dos_f' => date('m/d/Y'),
                    'payment' => null,
                    'payment_type' => null,
                    'practice_id' => Session::get('practice_id')
                ];
                if ($index == 'eid') {
                    $result['eid'] = $id;
                } else {
                    $result['other_billing_id'] = $id;
                }
            } else {
                $query = DB::table('billing_core')->where('billing_core_id', '=', $billing_id)->first();
                $result = [
                    'eid' => $query->eid,
                    'other_billing_id' => $query->other_billing_id,
                    'pid' => $query->other_billing_id,
                    'dos_f' => date('m/d/Y', $this->human_to_unix($query->dos_f)),
                    'payment' => $query->payment,
                    'payment_type' => $query->payment_type,
                    'practice_id' => $query->practice_id
                ];
            }
            $items[] = [
                'name' => 'eid',
                'type' => 'hidden',
                'default_value' => $result['eid']
            ];
            $items[] = [
                'name' => 'other_billing_id',
                'type' => 'hidden',
                'default_value' => $result['other_billing_id']
            ];
            $items[] = [
                'name' => 'pid',
                'type' => 'hidden',
                'required' => true,
                'default_value' => $result['pid']
            ];
            $items[] = [
                'name' => 'dos_f',
                'label' => trans('noshform.dos_f1'),
                'type' => 'date',
                'required' => true,
                'default_value' => $result['dos_f']
            ];
            $items[] = [
                'name' => 'payment',
                'label' => trans('noshform.payment1'),
                'type' => 'text',
                'required' => true,
                'default_value' => $result['payment']
            ];
            $items[] = [
                'name' => 'payment_type',
                'label' => trans('noshform.payment_type'),
                'type' => 'text',
                'required' => true,
                'typeahead' => route('typeahead', ['table' => 'billing_core', 'column' => 'payment_type']),
                'default_value' => $result['payment_type']
            ];
            $items[] = [
                'name' => 'practice_id',
                'type' => 'hidden',
                'required' => true,
                'default_value' => $result['practice_id']
            ];
            $form_array = [
                'form_id' => 'payment_form',
                'action' => route('billing_make_payment', [$id, $index, $billing_id]),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            $data['panel_header'] = trans('noshform.add_payment');
            $data['content'] = $this->form_build($form_array);
            $data['billing_active'] = true;
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function billing_notes(Request $request)
    {
        if ($request->isMethod('post')) {
            $data['billing_notes'] = $request->input('billing_notes');
            DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->update($data);
            $this->audit('Update');
            Session::put('message_action', trans('noshform.billing_notes_updated'));
            return redirect(Session::get('last_page'));
        } else {
            $result = DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->first();
            $notes = null;
            $data['panel_header'] = trans('noshform.add_billing_note');
            if ($result->billing_notes !== '' && $result->billing_notes !== null) {
                $notes = $result->billing_notes;
                $data['panel_header'] = trans('noshform.update_billing_note');
            }
            $items[] = [
                'name' => 'billing_notes',
                'label' => trans('noshform.billing_notes'),
                'type' => 'textarea',
                'required' => true,
                'default_value' => $notes
            ];
            $form_array = [
                'form_id' => 'billing_notes_form',
                'action' => route('billing_notes'),
                'items' => $items,
                'save_button_label' => 'Save'
            ];
            $data['content'] = $this->form_build($form_array);
            $data['billing_active'] = true;
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function billing_payment_delete(Request $request, $id, $index, $billing_id)
    {
        DB::table('billing_core')->where('billing_core_id', '=', $billing_id)->delete();
        $this->audit('Delete');
        Session::put('message_action', trans('noshform.billing_payment_delete'));
        return redirect()->route('billing_payment_history', [$id, $index]);
    }

    public function billing_payment_history(Request $request, $id, $index)
    {
        $return = '';
        $result = DB::table('billing_core')->where($index, '=', $id)->where('payment', '!=', '0')->get();
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->dos_f)) . '</b> - ' . $row->payment;
                $arr['edit'] = route('billing_make_payment', [$id, $index, $row->billing_core_id]);
                $arr['delete'] = route('billing_payment_delete', [$id, $index, $row->billing_core_id]);
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'scoring_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back'),
            'default_button_text_url' => Session::get('last_page')
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $dropdown_array1 = [
           'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
           'type' => 'item',
           'label' => '',
           'icon' => 'fa-plus',
           'url' => route('billing_make_payment', [$id, $index])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['panel_header'] = trans('noshform.billing_payment_history');
        $data['content'] = $return;
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['billing_active'] = true;
        // Session::put('last_page', $request->fullUrl());
        Session::put('billing_last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function care_opportunities(Request $request, $type)
    {
        $row = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $gender = 'Male';
        if ($row->sex == 'f' || $row->sex == 'u') {
            $gender = 'Female';
        }
        $age = (time() - $this->human_to_unix($row->DOB))/31556926;
        $age_date = Date::parse($row->DOB);
        $age1 = $age_date->diffinYears();
        $return = '';
        $type_arr = [
            'prevention' => [trans('noshform.prevention'), 'fa-calendar'],
            'immunizations' => [trans('noshform.immunizations'), 'fa-magic'],
            'hedis' => [trans('noshform.hedis'), 'fa-clock-o']
        ];
        if ($age1 >= 40 && $age1 <= 79) {
            $type_arr['ascvd'] = [trans('noshform.ascvd'), 'fa-heartbeat'];
        }
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('care_opportunities', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        if ($type == 'prevention') {
            $key = '48126e4e8ea3b83bc808850c713a5743';
            $url = 'https://epssdata.ahrq.gov/';
            $post = http_build_query([
                'key' => $key,
                'age' => round($age, 0, PHP_ROUND_HALF_DOWN),
                'sex' => $gender,
                'pregnant' => ucfirst($row->pregnant),
                'sexuallyActive' => $row->sexuallyactive,
                'tobacco' => $row->tobacco
            ]);
            $cr = curl_init($url);
            curl_setopt($cr, CURLOPT_POST, 1);
            curl_setopt($cr, CURLOPT_POSTFIELDS, $post);
            curl_setopt($cr, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($cr, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
            $data1 = curl_exec($cr);
            curl_close($cr);
            $epss = json_decode($data1, true);
            if (isset($epss['specificRecommendations'])) {
                $return .= '<h4>' . trans('noshform.uspstf') . '</h4>';
                $grade_ab = '<div class="alert alert-success"><h5>' . trans('noshform.uspstf_ab') . '</h5><p>' . trans('noshform.uspstf_ab_desc') . '</p><ul>';
                $grade_c = '<div class="alert alert-warning"><h5>' . trans('noshform.uspstf_c') . '</h5><p>' . trans('noshform.uspstf_c_desc') . '</p><ul>';
                $grade_d = '<div class="alert alert-danger"><h5>' . trans('noshform.uspstf_d') . '</h5><p>' . trans('noshform.uspstf_d_desc') . '</p><ul>';
                $grade_i = '<div class="alert alert-info"><h5>' . trans('noshform.uspstf_i') . '</h5><p>' . trans('noshform.uspstf_i_desc') . '</p><ul>';
                 foreach ($epss['specificRecommendations'] as $rec) {
                    $rec_text = '<li>' . $rec['title'] . rtrim($rec['text']) . ', Grade: ' . $rec['grade'] .'</li>';
                    if ($rec['grade'] == 'A' || $rec['grade'] == 'B') {
                        $grade_ab .= $rec_text;
                    }
                    if ($rec['grade'] == 'C') {
                        $grade_c .= $rec_text;
                    }
                    if ($rec['grade'] == 'D') {
                        $grade_d .= $rec_text;
                    }
                    if ($rec['grade'] == 'I') {
                        $grade_i .= $rec_text;
                    }
                }
                $grade_ab .= '</ul></div>';
                $grade_c .= '</ul></div>';
                $grade_d .= '</ul></div>';
                $grade_i .= '</ul></div>';
                $return .= $grade_ab . $grade_c . $grade_d . $grade_i;
            } else {
                $return .= '<h4>' . trans('noshform.uspstf_none') . '</h4>';
            }
        }
        if ($type == 'immunizations') {
            if (Session::get('agealldays') < 6574.5) {
                $imm_arr['patientImmunizationHistory'] = [];
                $imm_arr['patientAgeMonths'] = round(Session::get('agealldays')/30.436875);
                $imms = DB::table('immunizations')->where('pid', '=', Session::get('pid'))->get();
                if ($imms->count()) {
                    foreach ($imms as $imm) {
                        $imm_arr['patientImmunizationHistory'][] = [
                            'date' => date('m/d/Y', strtotime($imm->imm_date)),
                            'product' => [
                                'name' => $imm->imm_immunization
                            ]
                        ];
                    }
                }
                $data['imm_arr'] = json_encode($imm_arr);
                $return .= '<div id="immunization_recs" class="table-responsive"></div>';
            }
        }
        if ($type == 'hedis') {
            $return .= $this->hedis_audit('all', 'chart', Session::get('pid'));
        }
        if ($type == 'ascvd') {
            $ascvd_rec = [
                'startAspirin' => trans('noshform.startAspirin'),
                'startAspirin_StartBPlowering' => trans('noshform.startAspirin_StartBPlowering'),
                'startAspirin_StartBPlowering_StopSmoking' => trans('noshform.startAspirin_StartBPlowering_StopSmoking'),
                'startAspirin_StartStatin' => trans('noshform.startAspirin_StartStatin'),
                'startAspirin_StartStatin_StopSmoking' => trans('noshform.startAspirin_StartStatin_StopSmoking'),
                'startAspirin_StopSmoking' => trans('noshform.startAspirin_StopSmoking'),
                'startBPlowering' => trans('noshform.startBPlowering'),
                'startBPlowering_StopSmoking' => trans('noshform.startBPlowering_StopSmoking'),
                'startStatin' => trans('noshform.startStatin'),
                'startStatin_StartAspirin_StartBPlowering' => trans('noshform.startStatin_StartAspirin_StartBPlowering'),
                'startStatin_StartBPlowering' => trans('noshform.startStatin_StartBPlowering'),
                'startStatin_StartBPlowering_StopSmoking' => trans('noshform.startStatin_StartBPlowering_StopSmoking'),
                'startStatin_StopSmoking' => trans('noshform.startStatin_StopSmoking'),
                'stopSmoking' => trans('noshform.stopSmoking'),
                'startAll' => trans('noshform.startAll')
            ];
            $return .= '<h4>' . trans('noshform.ascvd_calc') . '</h4>';
            $ascvd_arr = $this->ascvd_calc();
            if ($ascvd_arr['status'] !== 'missing') {
                $return .= '<div class="alert alert-info"><strong>' . trans('noshform.ascvd_baseline') . ': </strong>' . $ascvd_arr['baselineRisk'] .'%</div>';
                $ascvd_arr1 = [];
                foreach ($ascvd_arr['therapyChoice'] as $ascvd_item_k => $ascvd_item_v) {
                    if ($ascvd_item_v !== 'NA') {
                        $ascvd_arr1[$ascvd_item_v] = $ascvd_item_k;
                    }
                }
                if (! empty($ascvd_arr1)) {
                    krsort($ascvd_arr1);
                    foreach ($ascvd_arr1 as $ascvd_item1_k => $ascvd_item1_v) {
                        $return .= '<div class="alert alert-danger"><strong>' . $ascvd_rec[$ascvd_item1_v] . ':</strong> - ' . $ascvd_item1_k . '%</div>';
                    }
                }
            } else {
                $return .= $ascvd_arr['message'];
            }
        }
        $data['panel_header'] = trans('noshform.care_opportunities');
        $data['content'] = $return;
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['care_active'] = true;
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function cms_bluebutton(Request $request, $as='')
    {
        $data['panel_header'] = trans('noshform.cms_bluebutton');
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $base_url = 'https://api.bluebutton.cms.gov';
        $connected1 = DB::table('refresh_tokens')->where('practice_id', '=', '1')->where('endpoint_uri', '=', $base_url)->first();
        if (! Session::has('oidc_relay')) {
            $param = [
                'origin_uri' => route('cms_bluebutton', [$as]),
                'response_uri' => route('cms_bluebutton', [$as]),
                'fhir_url' => '',
                'fhir_auth_url' => '',
                'fhir_token_url' => '',
                'type' => 'cms_bluebutton',
                'cms_pid' => '',
                'refresh_token' => ''
            ];
            // $param = [
            //     'origin_uri' => route('cms_bluebutton'),
            //     'response_uri' => route('cms_bluebutton'),
            //     'fhir_url' => '',
            //     'fhir_auth_url' => '',
            //     'fhir_token_url' => '',
            //     'type' => 'cms_bluebutton',
            //     'cms_pid' => '',
            //     'refresh_token' => ''
            // ];
            if ($connected1) {
                $param['refresh_token'] = $connected1->refresh_token;
            }
            $oidc_response = $this->oidc_relay($param);
            if ($oidc_response['message'] == 'OK') {
                Session::put('oidc_relay', $oidc_response['state']);
                return redirect($oidc_response['url']);
            } else {
                if ($as !== '') {
                    $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                    if (!empty($practice->uma_uri)) {
                        $html = 'Error: ' . $oidc_response['message'] . '<br><a href="' . $practice->uma_uri . '">Click here to return to your authorization server</a>';
                        return $html;
                    }
                }
                Session::put('message_action', $oidc_response['message']);
                return redirect(Session::get('last_page'));
            }
        } else {
            $param1['state'] = Session::get('oidc_relay');
            Session::forget('oidc_relay');
            $oidc_response1 = $this->oidc_relay($param1, true);
            if ($oidc_response1['message'] == 'Tokens received') {
                if ($oidc_response1['tokens']['access_token'] == '') {
                    return redirect()->route('cms_bluebutton');
                }
                $access_token = $oidc_response1['tokens']['access_token'];
                $refresh_token = $oidc_response1['tokens']['refresh_token'];
                $cms_pid = $oidc_response1['tokens']['patient'];
                // $base_url = 'https://sandbox.bluebutton.cms.gov';
                if (!$connected1) {
                    $refresh = [
                        'refresh_token' => $refresh_token,
                        'pid' => Session::get('pid'),
                        'practice_id' => Session::get('practice_id'),
                        'user_id' => Session::get('user_id'),
                        'endpoint_uri' => $base_url,
                        'pnosh' => 'Medicare Benefits'
                    ];
                    DB::table('refresh_tokens')->insert($refresh);
                    $this->audit('Add');
                } else {
                    $refresh['refresh_token'] = $refresh_token;
                    DB::table('refresh_tokens')->where('id', '=', $connected1->id)->update($refresh);
                    $this->audit('Update');
                }
                Session::put('cms_access_token', $access_token);
                Session::put('cms_pid', $cms_pid);
                return redirect()->route('cms_bluebutton_display');
            } else {
                if ($as !== '') {
                    $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                    if (!empty($practice->uma_uri)) {
                        $html = trans('noshform.error') . ': ' . $oidc_response1['message'] . '<br><a href="' . $practice->uma_uri . '">' . trans('noshform.cms_bluebutton_as_return') . '</a>';
                        return $html;
                    }
                }
                Session::put('message_action', $oidc_response1['message']);
                return redirect(Session::get('last_page'));
            }
        }
    }

    public function cms_bluebutton_coverage(Request $request, $id)
    {
        $token = Session::get('cms_access_token');
        // $base_url = 'https://sandbox.bluebutton.cms.gov';
        $base_url = 'https://api.bluebutton.cms.gov';
        $data['panel_header'] = trans('noshform.cms_bluebutton_display') . ' - ' . trans('noshform.coverage');
        $url = $base_url . '/v1/fhir/Coverage/' . $id;
        $result = $this->fhir_request($url,false,$token,true);
        $dropdown_array = [];
        $items = [];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.back'),
            'icon' => 'fa-chevron-left',
            'url' => route('cms_bluebutton_display', ['Coverage'])
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = '<strong>' . trans('noshform.type') . ':</strong> ' . $result['grouping']['subGroup'] . ' ' . $result['grouping']['subPlan'] . '<br>';
        $data['content'] .= '<strong>' . trans('noshform.insurance_id_num') . ':</strong> ' . $result['id'] . '<br>';
        $data['content'] .= '<strong>' . trans('noshform.status') . ':</strong> ' . ucfirst($result['status']) . '<br>';
        if (isset($result['extension'])) {
            $extension_arr = [];
            foreach ($result['extension'] as $row) {
                if (isset($row['valueCoding']['display'])) {
                    if (!in_array($row['valueCoding']['display'], $extension_arr)) {
                        $extension_arr[] = $row['valueCoding']['display'];
                    }
                }
            }
            $data['content'] .= '<strong>' . trans('noshform.details') . ':</strong><br><ul>';
            foreach ($extension_arr as $row1) {
                $data['content'] .= '<li>' . $row1 . '</li>';
            }
            $data['content'] .= '</ul>';
        }
        $data['content'] .= '<br><button data-toggle="collapse" data-target="#json_coverage">JSON</button>';
        $data['content'] .= '<div id="json_coverage" class="collapse">' . json_encode($result, JSON_PRETTY_PRINT) . '</div>';
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        $data['payors_active'] = true;
        Session::put('last_page', $request->fullUrl());
        $data = array_merge($data, $this->sidebar_build('chart'));
        return view('chart', $data);
    }

    public function cms_bluebutton_disconnect(Request $request)
    {
        $base_url = 'https://api.bluebutton.cms.gov';
        $connected1 = DB::table('refresh_tokens')->where('practice_id', '=', '1')->where('endpoint_uri', '=', $base_url)->first();
        if ($connected1) {
            DB::table('refresh_tokens')->where('id', '=', $connected1->id)->delete();
            $this->audit('Delete');
            Session::put('message_action', trans('noshform.cms_bluebutton_disconnect'));
        }
        return redirect()->route('payors_list', ['active']);
    }

    public function cms_bluebutton_display(Request $request, $type='Summary')
    {
        $token = Session::get('cms_access_token');
        // $base_url = 'https://sandbox.bluebutton.cms.gov';
        $base_url = 'https://api.bluebutton.cms.gov';
        $title_array = [
            'Summary' => [trans('noshform.patient_summary'), 'fa-address-card', '/v1/connect/userinfo'],
            'EOB' => [trans('noshform.explanation_of_benefit'), 'fa-money', '/v1/fhir/ExplanationOfBenefit/?patient=' . Session::get('cms_pid')],
            'Coverage' => [trans('noshform.coverage'), 'fa-address-book', '/v1/fhir/Coverage/?patient=' . Session::get('cms_pid')]
        ];
        $data['panel_header'] = trans('noshform.cms_bluebutton_display') . ' - ' . $title_array[$type][0];
        $url = $base_url . $title_array[$type][2];
        $result = $this->fhir_request($url,false,$token,true);
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $gender_arr = $this->array_gender();
        if ($type == 'Summary') {
            $data['content'] = '<div class="alert alert-success">';
            if (isset($result)) {
                foreach ($result as $result_k=>$result_v) {
                    if ($result_k !== 'iat' && $result_k !== 'ial') {
                        if ($result_k == 'sub') {
                            $result_k = 'Subject';
                        }
                        if ($result_k == 'patient') {
                            $result_k = 'Patient ID';
                        }
                        $result_k = str_replace('_', ' ', $result_k);
                        $data['content'] .= '<strong>' . ucfirst($result_k) . ': </strong>' . $result_v . '<br>';
                    }
                }
            }
            $data['content'] .= '</div>';
            $data['content'] .= '<div class="list-group">';
            foreach ($title_array as $title_k=>$title_v) {
                if ($title_k !== 'Summary') {
                    $data['content'] .= '<a href="' . route('cms_bluebutton_display', [$title_k]) . '" class="list-group-item"><i class="fa ' . $title_v[1] . ' fa-fw"></i><span style="margin:10px;">' . $title_v[0] . '</span></a>';
                    $items[] = [
                        'type' => 'item',
                        'label' => $title_v[0],
                        'icon' => $title_v[1],
                        'url' => route('cms_bluebutton_display', [$title_k])
                    ];
                }
            }
            $data['content'] .= '</div>';
            $data['panel_header'] = trans('noshform.patient_summary');
            $dropdown_array = [
                'items_button_text' => trans('noshform.cms_bluebutton_data')
            ];
            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        } else {
            $dropdown_array = [];
            $items = [];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.back'),
                'icon' => 'fa-chevron-left',
                'url' => route('cms_bluebutton_display', ['Summary'])
            ];
            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['content'] = '';
            $list_array = [];
            if (isset($result)) {
                // $data['content'] .= json_encode($result);
                if ($type == 'Coverage') {
                    if (!isset($result['detail'])) {
                        if ($result['total'] > 0) {
                            foreach ($result['entry'] as $row1) {
                                if (isset($row1['resource']['status'])) {
                                    $arr = [];
                                    $arr['label'] = (string) $row1['resource']['type']['coding'][0]['system'] . ' ' . $row1['resource']['type']['coding'][0]['code'] . ', ID: ' . $row1['resource']['id'];
                                    $arr['label'] .= ', Status: ' . ucfirst($row1['resource']['status']);
                                    $arr['view'] = route('cms_bluebutton_coverage', [$row1['resource']['id']]);
                                    if ($row1['resource']['status'] !== 'active') {
                                        $arr['danger'] = true;
                                    }
                                    $list_array[] = $arr;
                                }
                            }
                        }
                    }
                }
                if ($type == 'EOB') {
                    if (!isset($result['detail'])) {
                        if ($result['total'] > 0) {
                            $sub_session = [];
                            $i=0;
                            foreach ($result['entry'] as $row2) {
                                if (isset($row2['resource']['status'])) {
                                    if ($row2['resource']['status'] == 'active') {
                                        if (isset($row2['resource']['billablePeriod']['start'])) {
                                            $arr = [];
                                            $arr['label'] = (string) $row2['resource']['billablePeriod']['start'] . ' - ' . trans('noshform.payment_amount') . ': ' . $row2['resource']['payment']['amount']['value'] . ' ' . $row2['resource']['payment']['amount']['code'];
                                            $dx_session = [];
                                            foreach ($row2['resource']['diagnosis'] as $dx_row) {
                                                $dx_session[$dx_row['sequence']] = $dx_row['diagnosisCodeableConcept']['coding'][0]['code'];
                                            }
                                            $sub_session_arr = [];
                                            foreach ($row2['resource']['item'] as $sub_row) {
                                                $adj_session = [];
                                                foreach ($sub_row['adjudication'] as $adj_row) {
                                                    $adj_session_text = $adj_row['category']['coding'][0]['code'];
                                                    if (isset($adj_row['category']['coding'][0]['display'])) {
                                                        $adj_session_text = $adj_row['category']['coding'][0]['display'];
                                                    }
                                                    if (isset($adj_row['amount'])) {
                                                        $adj_session_text .= ', ' . $adj_row['amount']['value'] . ' ' . $adj_row['amount']['code'];
                                                    }
                                                    $adj_session[] = $adj_session_text;
                                                }
                                                $diagnosis = '';
                                                if (isset($sub_row['diagnosisLinkId'][0])) {
                                                    $diagnosis = $dx_session[$sub_row['diagnosisLinkId'][0]];
                                                }
                                                $sub_session_arr[] = [
                                                    'date' => $row2['resource']['billablePeriod']['start'],
                                                    'quantity' => $sub_row['quantity']['value'],
                                                    'diagnosis' => $diagnosis,
                                                    'adjudications' => $adj_session
                                                ];
                                            }
                                            $sub_session[$i] = $sub_session_arr;
                                            $arr['view'] = route('cms_bluebutton_eob', [$i]);
                                            $list_array[] = $arr;
                                            $i++;
                                        } else {
                                            if (isset($row2['resource']['item'][0]['servicedDate'])) {
                                                $arr = [];
                                                $arr['label'] = (string) $row2['resource']['item'][0]['servicedDate'];
                                                $list_array[] = $arr;
                                            }
                                        }
                                    }
                                }
                            }
                            Session::put('sub_session', $sub_session);
                        }
                    }
                }
                if (! empty($list_array)) {
                    $data['content'] .= $this->result_build($list_array, $type . '_cms_list');
                }
            }
        }
        $data['content'] .= '<br><button data-toggle="collapse" data-target="#json_coverage">JSON</button>';
        $data['content'] .= '<div id="json_coverage" class="collapse">' . json_encode($result, JSON_PRETTY_PRINT) . '</div>';
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        $data['payors_active'] = true;
        Session::put('last_page', $request->fullUrl());
        $data = array_merge($data, $this->sidebar_build('chart'));
        return view('chart', $data);
    }

    public function cms_bluebutton_eob(Request $request, $sequence)
    {
        $data['panel_header'] = trans('noshform.cms_bluebutton_eob');
        $sub = Session::get('sub_session');
        $dropdown_array = [];
        $items = [];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.back'),
            'icon' => 'fa-chevron-left',
            'url' => route('cms_bluebutton_display', ['EOB'])
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = '';
        foreach ($sub[$sequence] as $sub_row) {
            $data['content'] .= '<strong>' . trans('noshform.cms_bluebutton_eob_date') . ': </strong>' . $sub_row['date'] . '<br>';
            $data['content'] .= '<strong>' . trans('noshform.cms_bluebutton_eob_quantity') . ': </strong>' . $sub_row['quantity'] . '<br>';
            $data['content'] .= '<strong>' . trans('noshform.cms_bluebutton_eob_diagnosis') . ': </strong>' . $sub_row['diagnosis'] . '<br>';
            $data['content'] .= '<strong>' . trans('noshform.cms_bluebutton_eob_adjudications') . ': </strong><ul>';
            foreach ($sub_row['adjudications'] as $row) {
                $data['content'] .= '<li>' . $row . '</li>';
            }
            $data['content'] .= '</ul>';
        }
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        $data['payors_active'] = true;
        Session::put('last_page', $request->fullUrl());
        $data = array_merge($data, $this->sidebar_build('chart'));
        return view('chart', $data);
    }

    /**
    * Chart form action
    * @param string  $table
    * @param string  $action (save, inactivate, reactivate, delete, eie, prescribe, order)
    * @return Response
    */
    public function chart_action(Request $request, $table, $action, $id, $index)
    {
        $date_convert_array = [
            'issue_date_active',
            'issue_date_inactive',
            'allergies_date_active',
            'allergies_date_inactive',
            'rxl_date_active',
            'rxl_date_inactive',
            'rxl_date_old',
            'imm_date',
            'imm_expiration',
            'alert_date_active',
            'sup_date_active',
            'sup_date_inactive',
            'DOB',
            'insurance_insu_dob',
            'test_datetime',
            'orders_date',
            'orders_pending_date',
            'hippa_date_release',
            'history_physical',
            'lab_date',
            'accident_f',
            'accident_t',
            'hippa_date_request',
            'mtm_date_completed'
        ];
        $date_convert_array1 = [
            'dos_f',
            'dos_t'
        ];
        $rcopia_tables = [
            'issues',
            'rx_list',
            'allergies'
        ];
        $api_tables = [
            'issues',
            'allergies'
        ];
        $mtm_tables = [
            'issues',
        ];
        $ndc_tables = [
            'allergies'
        ];
        $good_rx_tables = [
            'rx_list'
        ];
        $table_message_arr = [
            'issues' => trans('noshform.issue') . ' ',
            'allergies' => trans('noshform.allergy') . ' ',
            'rx_list' => trans('noshform.medication') . ' ',
            'sup_list' => trans('noshform.supplement') . ' ',
            'immunizations' => trans('noshform.immunization') . ' ',
            'alerts' => trans('noshform.alert') . ' ',
            'documents' => trans('noshform.document') . ' ',
            'tests' => trans('noshform.test_result') . ' ',
            'orders' => trans('noshform.order') . ' ',
            'vitals' => trans('noshform.vital_signs') . ' ',
            't_messages' => trans('noshform.t_messages_message') . ' ',
            'hippa' => trans('noshform.hippa') . ' ',
            'hippa_request' => trans('noshform.hippa_request') . ' ',
            'billing_core' => trans('noshform.billing') . ' ',
            'demographics' => trans('noshform.demographics') .' ',
            'mtm' => trans('noshform.mtm') . ' '
        ];
        $multiple_select_arr = [
            'icd_pointer'
        ];
        $multiple_select_arr1 = [
            'orders_insurance',
            'orders_labs_icd',
            'orders_radiology_icd',
            'orders_cp_icd',
            'orders_referrals_icd'
        ];
        $multiple_select_arr2 = [
            'label'
        ];
        $multiple_select_arr3 = [
            'alert_providers'
        ];
        $nosh_action_tables = [
            'rx_list',
            'orders',
            'hippa',
            'hippa_request',
            'sup_list',
            'immunizations'
        ];
        $reconcile_tables = [
            'rx_list',
            'sup_list',
            'allergies',
            'immunizations',
            'issues',
            'documents'
        ];
        $message = '';
        if (isset($table_message_arr[$table])) {
            $message = $table_message_arr[$table];
        }
        $arr = [];
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $pid = Session::get('pid');
        $data = $request->post();
        unset($data['_token']);
        $next_action = '';
        // Handle multiple submit buttons if exisiting
        if ($request->has('submit')) {
            if ($request->input('submit') == 'sign') {
                if ($table == 't_messages') {
                    $data['t_messages_signed'] = 'Yes';
                }
            }
            if ($request->input('submit') == 'phone_encounter') {
                $details['encounter_template'] = 'phone';
                Session::put('encounter_details', $details);
                $next_action = route('encounter_details', ['0']);
            }
            unset($data['submit']);
        }
        // Handle forms that direct a specific action after saving
        foreach ($nosh_action_tables as $nosh_action_table) {
            if ($nosh_action_table == $table) {
                if (isset($data['nosh_action'])) {
                    if ($data['nosh_action'] !== '') {
                        $next_action = $data['nosh_action'];
                    }
                    unset($data['nosh_action']);
                }
            }
        }
        // Convert dates to MySQL format
        foreach ($date_convert_array as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] !== '') {
                    $data[$key] = date('Y-m-d H:i:s', strtotime($data[$key]));
                }
            }
        }
        // Convert dates to conventional format
        foreach ($date_convert_array1 as $key1) {
            if (array_key_exists($key1, $data)) {
                if ($data[$key1] !== '') {
                    $data[$key1] = date('m/d/Y', strtotime($data[$key1]));
                }
            }
        }
        // Handle multiple selects
        foreach ($multiple_select_arr as $key2) {
            if (array_key_exists($key2, $data)) {
                $data[$key2] = implode('', $data[$key2]);
            }
        }
        foreach ($multiple_select_arr1 as $key3) {
            if (array_key_exists($key3, $data)) {
                $data[$key3] = implode("\n", $data[$key3]);
            }
        }
        foreach ($multiple_select_arr2 as $key4) {
            if (array_key_exists($key4, $data)) {
                $data[$key4] = implode(";", $data[$key4]);
            }
        }
        foreach ($multiple_select_arr3 as $key5) {
            if (array_key_exists($key5, $data)) {
                $data[$key5] = implode(",", $data[$key5]);
            }
        }
        // Handle rCopia calls after action
        foreach ($rcopia_tables as $rcopia_table) {
            if ($rcopia_table == $table) {
                $data['rcopia_sync'] = 'n';
            }
        }
        // Handle reconciliation
        foreach ($reconcile_tables as $reconcile_table) {
            if ($reconcile_table == $table) {
                $reconcile_group = $this->access_level('2');
                $data['reconcile'] = 'n';
                if ($reconcile_group) {
                    $data['reconcile'] = 'y';
                }
            }
        }
        // Convert NDC numbers
        foreach ($ndc_tables as $ndc_table) {
            if ($ndc_table == $table && $action == 'save') {
                if (strpos($data['allergies_med'], ', ') === false) {
                    $ndcid = '';
                } else {
                    $med_name = explode(", ", $data['allergies_med'], -1);
                    $ndcid = "";
                    if ($med_name[0]) {
                        $med_result = DB::table('meds_full_package')
                            ->join('meds_full', 'meds_full.PRODUCTNDC', '=', 'meds_full_package.PRODUCTNDC')
                            ->select('meds_full_package.NDCPACKAGECODE')
                            ->where('meds_full.PROPRIETARYNAME', '=', $med_name[0])
                            ->first();
                        if ($med_result) {
                            $ndcid = $this->ndc_convert($med_result->NDCPACKAGECODE);
                        }
                    }
                }
                if ($table == 'allergies') {
                    $data['meds_ndcid'] = $ndcid;
                }
            }
        }
        // Demographic-specific data handling
        if ($table == 'demographics' && isset($data['creditcard_number'])) {
            $data['creditcard_number'] = encrypt($data['creditcard_number']);
        }
        if ($table == 'other_history') {
            $oh_data = [];
            if (isset($data['tobacco'])) {
                $oh_data['tobacco'] = $data['tobacco'];
                unset($data['tobacco']);
            }
            if (isset($data['sexuallyactive'])) {
                $oh_data['sexuallyactive'] = $data['sexuallyactive'];
                unset($data['sexuallyactive']);
            }
            if (! empty($oh_data)) {
                DB::table('demographics')->where('pid', '=', Session::get('pid'))->update($oh_data);
            }
        }
        if ($table == 'demographics') {
            if (isset($data['ethnicity'])) {
                $ethnicity_arr = $this->array_ethnicity();
                $data['ethnicity_code'] = $ethnicity_arr[$data['ethnicity']];
            }
            if (isset($data['race'])) {
                $race_arr = $this->array_race();
                $data['race_code'] = $race_arr[$data['race']];
            }
            if (isset($data['reminder_method'])) {
                if ($data['reminder_method'] == 'Email') {
                    $data['reminder_to'] = $data['email'];
                }
                if ($data['reminder_method'] == 'Cellular Phone') {
                    $data['reminder_to'] = $data['phone_cell'];
                }
            }
        }
        // Vital sign-specific data handling
        if ($table == 'vitals') {
            if ($id == '0') {
                $data['eid'] = Session::get('eid');
                $encounterInfo = DB::table('encounters')->where('eid', '=', Session::get('eid'))->first();
                $demographicsInfo = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
                $a = $this->human_to_unix($encounterInfo->encounter_DOS);
                $b = $this->human_to_unix($demographicsInfo->DOB);
                $data['pedsage'] = ($a - $b)/2629743;
                $data['vitals_age'] = ($a - $b)/31556926;
                $data['vitals_date'] = $encounterInfo->encounter_DOS;
            }
        }
        // Alerts-specific data handling
        if ($table == 'alerts') {
            if ($id == '0') {
                $data['alert_reason_not_complete'] = '';
            }
        }
        // Records release specific data handling
        if ($table == 'hippa') {
            if ($next_action == 'fax_action,all' || $next_action == 'fax_action,1year' || $next_action == 'chart_queue,encounters' || $next_action == 'fax_queue,all' || $next_action == 'fax_queue,1year') {
                $this->validate($request, [
                    'address_id' => 'required'
                ]);
            }
            if (isset($data['address_id'])) {
                if ($data['address_id'] == '') {
                    unset($data['address_id']);
                }
            }
        }
        // Mediations specific data handling
        if ($table == 'rx_list') {
            // $this->validate($request, [
            //     'rxl_days' => 'numeric',
            //     'rxl_refill' => 'numeric'
            // ]);
        }
        // Orders specific data handling
        if ($table == 'orders') {
            if (isset($data['referral_specialty'])) {
                unset($data['referral_specialty']);
            }
        }
        // Billing specific handling
        if ($table == 'billing_core') {
            if ($action == 'save') {
                $this->validate($request, [
                    'cpt_charge' => 'numeric',
                    'unit' => 'numeric'
                ]);
            }
        }
        // MTM specific handling
        if ($table == 'mtm') {
            if ($data['mtm_date_completed'] != '') {
                $data['complete'] = 'yes';
            } else {
                $data['complete'] = 'no';
            }
        }
        if ($action == 'save') {
            if ($id == '0') {
                if ($table !== 'vitals') {
                    unset($data[$index]);
                }
                $data['pid'] = $pid;
                $row_id1 = DB::table($table)->insertGetId($data, $index);
                $this->audit('Add');
                // foreach ($api_tables as $api_table) {
                //     if ($api_table == $table) {
                //         $this->api_data('add', $table, $row_index, $row_id1);
                //     }
                // }
                if ($practice->mtm_extension == 'y') {
                    foreach ($mtm_tables as $mtm_table) {
                        if ($mtm_table == $table) {
                            $this->add_mtm_alert($pid, $table);
                        }
                    }
                }
                $arr['message'] = $message . trans('noshform.added') . '!';
                if ($next_action !== '') {
                    if (filter_var($next_action, FILTER_VALIDATE_URL) == false) {
                        $type = '';
                        $next_action_arr = explode(',', $next_action);
                        if (count($next_action_arr) > 1) {
                            $type = $next_action_arr[1];
                            $next_action = $next_action_arr[0];
                        }
                        $next_action = route($next_action, [$table, $row_id1, Session::get('pid'), $type]);
                    }
                }
            } else {
                $sync_message = '';
                if ($table == 'demographics') {
                    if (isset($data['email']) || isset($data['lastname'])) {
                        if (Session::get('patient_centric') == 'yp' || Session::get('patient_centric') == 'y') {
                            // Synchronize with HIE of One AS
                            $old_demo = DB::table('demographics')->where('pid', '=', '1')->first();
                            $pnosh_practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                            $sync_data = [
                                'old_email' => $old_demo->email,
                                'email' => $old_demo->email,
                                'sms' => $old_demo->phone_cell,
                                'client_id' => $pnosh_practice->uma_client_id,
                                'client_secret' => $pnosh_practice->uma_client_secret,
                                'lastname' => $old_demo->lastname,
                                'firstname' => $old_demo->firstname,
                                'DOB' => date('Y-m-d', strtotime($old_demo->DOB))
                            ];
                            if (isset($data['email'])) {
                                $sync_data['email'] = $data['email'];
                                $sync_data['sms'] = $data['phone_cell'];
                            }
                            if (isset($data['lastname'])) {
                                $sync_data['lastname'] = $data['lastname'];
                                $sync_data['firstname'] = $data['firstname'];
                                $sync_data['DOB'] = date('Y-m-d', strtotime($data['DOB']));
                            }
                            $sync_message = $this->pnosh_sync($sync_data);
                        }
                    }
                }
                if ($table == 'orders') {
                    $data['orders_date'] = date("Y-m-d H:i:s", time());
                }
                if ($table == 't_messages') {
                    $data['t_messages_date'] = date("Y-m-d H:i:s", time());
                }
                DB::table($table)->where($index, '=', $id)->update($data);
                $this->audit('Update');
                // foreach ($api_tables as $api_table) {
                //     if ($api_table == $table) {
                //         $this->api_data('update', $table, $index, $id);
                //     }
                // }
                $row_id1 = $id;
                $arr['message'] = $message . trans('noshform.updated') . '!';
                if ($sync_message !== '') {
                    $arr['message'] .= '<br>' . $sync_message;
                }
                if ($next_action !== '') {
                    if (filter_var($next_action, FILTER_VALIDATE_URL) == false) {
                        $type = '';
                        $next_action_arr = explode(',', $next_action);
                        if (count($next_action_arr) > 1) {
                            $type = $next_action_arr[1];
                            $next_action = $next_action_arr[0];
                        }
                        $next_action = route($next_action, [$table, $row_id1, Session::get('pid'), $type]);
                    }
                }
            }
            if (Session::has('eid')) {
                App::setLocale(Session::get('practice_locale'));
                if ($table == 'immunizations') {
                    if ($request->input('imm_elsewhere') == 'No') {
                        $encounter_text = $request->input('imm_immunization') . '; ' . trans('noshform.imm_sequence') . ': ' . $request->input('imm_sequence') . '; ' . trans('noshform.imm_dosage') . ': ' . $request->input('imm_dosage') . ' ' . $request->input('imm_dosage_unit');
                        $encounter_text .= ' ' . $request->input('imm_route') . ' ' . trans('noshform.administered') . ' ' . $request->input('imm_body_site');
                        $encounter_text .= '; ' . trans('noshform.imm_manufacturer') . ': ' . $request->input('imm_manufacturer') . '; ' . trans('noshform.imm_lot') . ': ' . $request->input('imm_lot') . '; ' . trans('noshform.imm_expiration') . ': ' . $request->input('imm_expiration');
                        $this->plan_build('imm','save', $encounter_text);
                    }
                }
                if ($table == 'sup_list') {
                    $encounter_text = $request->input('sup_supplement') . ' ' . $request->input('sup_dosage');
                    if ($request->input('sup_dosage_unit') != "") {
                        $encounter_text .= ' ' . $request->input('sup_dosage_unit');
                    }
                    if ($request->input('sup_sig') != "") {
                        if ($request->input('sup_instructions') != "") {
                            $encounter_text .= ', ' . $request->input('sup_sig') . ', ' . $request->input('sup_route') . ', ' . $request->input('sup_frequency') . ', ' . $request->input('sup_instructions') . ' ' . trans('noshform.for') . ' ' . $request->input('sup_reason');
                        } else {
                            $encounter_text .= ', ' . $request->input('sup_sig') . ', ' . $request->input('sup_route') . ', ' . $request->input('sup_frequency') . ' ' . trans('noshform.for') . ' ' . $request->input('sup_reason');
                        }
                    } else {
                        $encounter_text .= ', ' . $request->input('sup_instructions') . ' ' . trans('noshform.for') . ' ' . $request->input('sup_reason');
                    }
                    $this->plan_build('sup','order', $encounter_text);
                }

                if ($table == 'vitals') {
                    if (Session::get('agealldays') < 6574.5) {
                        $gender = Session::get('gender');
                        $sex = 'f';
                        if ($gender == 'male') {
                            $sex = 'm';
                        }
                        if (!empty($data['weight'])) {
                            $wt_data = $this->gc_weight_age($sex, $pid);
                            $data['wt_percentile'] = $wt_data['percentile'];
                        }
                        if (!empty($data['height'])) {
                            $ht_data = $this->gc_height_age($sex, $pid);
                            $data['ht_percentile'] = $ht_data['percentile'];
                        }
                        if (!empty($data['weight']) && !empty($data['height'])) {
                            $wt_ht_data = $this->gc_weight_height($sex, $pid);
                            $data['wt_ht_percentile'] = $wt_ht_data['percentile'];
                        }
                        if (!empty($data['headcircumference'])) {
                            $hc_data = $this->gc_head_age($sex, $pid);
                            $data['hc_percentile'] = $hc_data['percentile'];
                        }
                        if (Session::get('agealldays') > 730.5) {
                            if ($data['BMI'] !== '') {
                                $bmi_data = $this->gc_bmi_age($sex, $pid);
                                $data['bmi_percentile'] = $bmi_data['percentile'];
                            }
                        }
                        $update_id = $id;
                        if ($id == '0') {
                            $update_id = $row_id1;
                        }
                        DB::table($table)->where($index, '=', $update_id)->update($data);
                        $this->audit('Update');
                    }
                }
                if ($table == 'other_history') {
                    DB::table('other_history')->where('eid', '=', Session::get('eid'))->update($data);
                    $this->audit('Update');
                }
                App::setLocale(Session::get('user_locale'));
            }
            // Telephone message - post save handling
            if ($table == 't_messages') {
                if ($data['t_messages_to'] != '') {
                    $demo_result = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
                    $to_result = DB::table('users')->where('id', '=', $data['t_messages_to'])->first();
                    if (Session::get('user_id') !== $data['t_messages_to']) {
                        App::setLocale(Session::get('practice_locale'));
                        $internal_message_data = [
                            'pid' => Session::get('pid'),
                            'patient_name' => $demo_result->lastname . ', ' . $demo_result->firstname . ' (DOB: ' . date("m/d/Y", strtotime($demo_result->DOB)) . ') (ID: ' . Session::get('pid') . ')',
                            'message_to' => $to_result->displayname,
                            'message_from' => Session::get('user_id'),
                            'subject' => trans('noshform.t_message') . ' - ' . $data['t_messages_subject'],
                            'body' => $data['t_messages_message'],
                            'status' => 'Sent',
                            't_messages_id' => $row_id1,
                            'mailbox' => $data['t_messages_to'],
                            'practice_id' => Session::get('practice_id')
                        ];
                        DB::table('messaging')->insert($internal_message_data);
                        $this->audit('Add');
                        App::setLocale(Session::get('user_locale'));
                        $arr['message'] .= '<br>' . trans('noshform.internal_message_sent');
                    }
                }
            }
            // Immunizations and Orders - post save handling
            if ($table == 'immunizations' || $table == 'orders') {
                if ($next_action === 'electronic_sign') {
                    $fhir_resource_arr = [
                        'immunizations' => 'Immunization',
                        'orders' => 'ServiceRequest'
                    ];
                    // Create FHIR JSON resource
            		$json_row = DB::table($table)->where($index, '=', $row_id1)->first();
            		$json = $this->resource_detail($json_row, $fhir_resource_arr[$table]);
                    $json_data['json'] = json_encode($json);
                    $json_data['table'] = $table;
                    $json_data['index'] = $row_id1;
                    $json_query = DB::table('fhir_json')->where('table', '=', $table)->where('index', '=', $row_id1)->first();
                    if ($json_query) {
                        DB::table('fhir_json')->where('id', '=', $json_query->id)->update($json_data);
                    } else {
                        DB::table('fhir_json')->insert($json_data);
                    }
                    $this->audit('Update');
                }
            }
            // Orders - post save handling
            if ($table == 'orders') {
                $orders_type_arr = [
                    'orders_labs' => [trans('noshform.laboratory_orders'), trans('noshform.laboratory_results_pending')],
                    'orders_radiology' => [trans('noshform.imaging_orders'), trans('noshform.radiology_results_pending')],
                    'orders_cp' => [trans('noshform.cardiopulmonary_orders'), trans('cardiopulmonary_results_pending')],
                    'orders_referrals' => [trans('noshform.referrals'), trans('noshform.referral_pending')]
                ];
                foreach ($orders_type_arr as $type_k => $type_v) {
                    App::setLocale(Session::get('practice_locale'));
                    if (isset($data[$type_k])) {
                        $alert_subject = $type_v[1];
                        if (strtotime($data['orders_pending_date']) > time() && $type_k !== 'orders_referrals') {
                            $alert_subject .= " - " . trans('noshform.need_obtain');
                        }
                        $orders_arr = explode("\n", $data[$type_k]);
                        $orders_new_arr = [];
                        foreach ($orders_arr as $orders_item) {
                            $orders_new_arr[] = preg_replace('/\[[^\]]*\]/', '', $orders_item);
                        }
                        $order_address = DB::table('addressbook')->where('address_id', '=', $data['address_id'])->first();
                        $description = $type_v[0] . trans('noshform.sent_to') . $order_address->displayname;
                        if ($type_k !== 'orders_referrals') {
                            $description .= ': '. implode(', ', $orders_new_arr);
                        }
                        if ($id == '0') {
                            $orders_alert_data = [
                                'alert' => $alert_subject,
                                'alert_description' => $description,
                                'alert_date_active' => date('Y-m-d H:i:s', time()),
                                'alert_date_complete' => null,
                                'alert_reason_not_complete' => '',
                                'alert_providers' => Session::get('user_id'),
                                'orders_id' => $row_id1,
                                'pid' => Session::get('pid'),
                                'practice_id' => Session::get('practice_id'),
                                'alert_send_message' => 'n',
                                'results' => 1
                            ];
                            if ($type_k == 'orders_referrals') {
                                $orders_alert_data['results'] = 0;
                            }
                            DB::table('alerts')->insert($orders_alert_data);
                            $this->audit('Add');
                        } else {
                            $old_alert = DB::table('alerts')->where('orders_id', '=', $row_id1)->first();
                            if ($old_alert) {
                                $orders_alert_data = [
                                    'alert' => $alert_subject,
                                    'alert_description' => $description,
                                    'alert_date_active' => date('Y-m-d H:i:s', time()),
                                    'alert_date_complete' => null,
                                    'alert_reason_not_complete' => '',
                                    'alert_providers' => Session::get('user_id'),
                                    'results' => 1
                                ];
                                if ($type_k == 'orders_referrals') {
                                    $orders_alert_data['results'] = 0;
                                }
                                DB::table('alerts')->where('alert_id', '=', $old_alert->alert_id)->update($orders_alert_data);
                                $this->audit('Update');
                            }
                        }
                    }
                    App::setLocale(Session::get('user_locale'));
                }
            }
            // Demographics post-save handling
            if ($table == 'demographics') {
                if (isset($data['firstname'])) {
                    $this->setpatient(Session::get('pid'));
                    $appts = DB::table('schedule')->where('pid', '=', Session::get('pid'))->get();
                    if ($appts->count()) {
                        foreach ($appts as $appt_row) {
                            $appt['title'] = $data['lastname'] . ', ' . $data['firstname'] . ' (DOB: ' . date('m/d/Y', strtotime($data['DOB'])) . ') (ID: ' . Session::get('pid') . ')';
                            DB::table('schedule')->where('appt_id', '=', $appt_row->appt_id)->update($appt);
                            $this->audit('Update');
                        }
                    }
                }
                $this->demographics_update($row_id1, true);
            }
            // FHIR resource post-save handling
            if (isset($data['label'])) {
                if ($data['label'] !== '') {
                    $this->uma_resource_process($data['label'], $row_id1, $table);
                }
            }
        }
        if ($action == 'inactivate') {
            if ($table == 'issues') {
                $data1['issue_date_inactive'] = date('Y-m-d H:i:s', time());
            }
            if ($table == 'rx_list') {
                $data1['rxl_date_inactive'] = date('Y-m-d H:i:s', time());
                if (Session::has('eid')) {
                    $rx_query = DB::table($table)->where($index, '=', $id)->first();
                    $encounter_text = $rx_query->rxl_medication . ' ' . $rx_query->rxl_dosage . ' ' . $rx_query->rxl_dosage_unit;
                    $this->plan_build('rx', $action, $encounter_text);
                }
            }
            if ($table == 'sup_list') {
                $data1['sup_date_inactive'] = date('Y-m-d H:i:s', time());
                if (Session::has('eid')) {
                    $sup_query = DB::table($table)->where($index, '=', $id)->first();
                    $encounter_text = $sup_query->sup_supplement . ' ' . $sup_query->sup_dosage . ' ' . $sup_query->sup_dosage_unit;
                    $this->plan_build('sup', $action, $encounter_text);
                }
            }
            if ($table == 'allergies') {
                $data1['allergies_date_inactive'] = date('Y-m-d H:i:s', time());
            }
            if ($table == 'insurance') {
                $data1['insurance_plan_active'] = 'No';
            }
            foreach ($rcopia_tables as $rcopia_table) {
                if ($rcopia_table == $table) {
                    $data1['rcopia_sync'] = 'nd1';
                }
            }
            DB::table($table)->where($index, '=', $id)->update($data1);
            $this->audit('Update');
            // foreach ($api_tables as $api_table) {
            //     if ($api_table == $table) {
            //         $this->api_data('update', $table, $index, $id);
            //     }
            // }
            // FHIR resource post-save handling
            $update_query1 = DB::table($table)->where($index, '=', $id)->first();
            if ($update_query1->label !== '') {
                $this->uma_resource_process($update_query1->label, $id, $table, true);
            }
            $arr['message'] = $message . trans('noshform.inactivated') . '!';
        }
        if ($action == 'reactivate') {
            if ($table == 'issues') {
                $data2['issue_date_inactive'] = null;
            }
            if ($table == 'rx_list') {
                $data2['rxl_date_inactive'] = null;
                if (Session::has('eid')) {
                    $rx_query = DB::table($table)->where($index, '=', $id)->first();
                    $encounter_text = $rx_query->rxl_medication . ' ' . $rx_query->rxl_dosage . ' ' . $rx_query->rxl_dosage_unit;
                    $this->plan_build('rx', $action, $encounter_text);
                }
            }
            if ($table == 'sup_list') {
                $data2['sup_date_inactive'] = null;
                if (Session::has('eid')) {
                    $sup_query = DB::table($table)->where($index, '=', $id)->first();
                    $encounter_text = $sup_query->sup_supplement . ' ' . $sup_query->sup_dosage . ' ' . $sup_query->sup_dosage_unit;
                    $this->plan_build('sup', $action, $encounter_text);
                }
            }
            if ($table == 'allergies') {
                $data2['allergies_date_inactive'] = null;
            }
            if ($table == 'alerts') {
                $data2['alert_reason_not_complete'] = '';
            }
            if ($table == 'insurance') {
                $data2['insurance_plan_active'] = 'Yes';
            }
            foreach ($rcopia_tables as $rcopia_table) {
                if ($rcopia_table == $table) {
                    $data2['rcopia_sync'] = 'n';
                }
            }
            DB::table($table)->where($index, '=', $id)->update($data2);
            $this->audit('Update');
            // foreach ($api_tables as $api_table) {
            //     if ($api_table == $table) {
            //         $this->api_data('update', $table, $index, $id);
            //     }
            // }
            // FHIR resource post-save handling
            $update_query2 = DB::table($table)->where($index, '=', $id)->first();
            if ($update_query2->label !== '') {
                $this->uma_resource_process($update_query2->label, $id, $table);
            }
            $arr['message'] = $message . trans('noshform.reactivated') . '!';
        }
        if ($action == 'move_mh') {
            if ($table == 'issues') {
                $data_mh['type'] = 'Medical History';
            }
            DB::table($table)->where($index, '=', $id)->update($data_mh);
            $this->audit('Update');
            $arr['message'] = $message . trans('noshform.moved_to') . ' ' . trans('noshform.mh');
        }
        if ($action == 'move_pl') {
            if ($table == 'issues') {
                $data_pl['type'] = 'Problem List';
            }
            DB::table($table)->where($index, '=', $id)->update($data_pl);
            $this->audit('Update');
            $arr['message'] = $message . trans('noshform.moved_to') . ' ' . trans('noshform.pl');
        }
        if ($action == 'move_sh') {
            if ($table == 'issues') {
                $data_sh['type'] = 'Surgical History';
            }
            DB::table($table)->where($index, '=', $id)->update($data_sh);
            $this->audit('Update');
            $arr['message'] = $message . trans('noshform.moved_to') . ' ' . trans('noshform.sh');
        }
        if ($action == 'delete') {
            if($practice->rcopia_extension == 'y') {
                foreach ($rcopia_tables as $rcopia_table) {
                    if ($rcopia_table == $table) {
                        $data4 = array(
                            'rcopia_sync' => 'nd'
                        );
                        DB::table($table)->where($index, '=', $id)->update($data4);
                        $this->audit('Update');
                        while(!$this->check_rcopia_delete($table, $id)) {
                            sleep(2);
                        }
                    }
                }
            }
            if ($table == 'documents') {
                $document = DB::table('documents')->where('documents_id', '=', $id)->first();
                if (file_exists($document->documents_url)) {
                    unlink($document->documents_url);
                }
            }
            if ($table == 'image') {
                $image = DB::table('image')->where('image_id', '=', $id)->first();
                if (!file_exists($image->image_location)) {
                    unlink($image->image_location);
                }
            }
            if ($table == 'orders') {
                DB::table('alerts')->where('orders_id', '=', $id)->delete();
            }
            if (Session::has('eid')) {
                App::setLocale(Session::get('practice_locale'));
                if ($table == 'immunizations') {
                    $immunization = DB::table('immunizations')->where('imm_id', '=', $id)->first();
                    if ($immunization->imm_elsewhere == 'No') {
                        $encounter_text = $immunization->imm_immunization . '; ' . trans('noshform.imm_sequence') . ': ' . $immunization->imm_sequence . '; ' . trans('noshform.imm_dosage') . ': ' . $immunization->imm_dosage . ' ' . $immunization->imm_dosage_unit . ' ' . $immunization->imm_route;
                        $encounter_text .= ' ' . trans('noshform.administered') . ' ' . $immunization->imm_body_site;
                        $encounter_text .= '; ' . trans('noshform.imm_manufacturer') . ': ' . $immunization->imm_manufacturer . '; ' . trans('noshform.imm_lot') . ': ' . $immunization->imm_lot . '; ' . trans('noshform.imm_expiration') . ': ' . $immunization->imm_expiration;
                        $this->plan_build('imm','delete', $encounter_text);
                    }
                }
                App::setLocale(Session::get('user_locale'));
            }
            // FHIR resource post-save handling
            $delete_query = DB::table($table)->where($index, '=', $id)->first();
            if (!empty($delete_query->label)) {
                $this->uma_resource_process($delete_query->label, $id, $table);
            }
            DB::table($table)->where($index, '=', $id)->delete();
            $this->audit('Delete');
            // foreach ($api_tables as $api_table) {
            //     if ($api_table == $table) {
            //         $this->api_data('delete', $table, $index, $id);
            //     }
            // }
            $arr['message'] = $message . trans('noshform.deleted') . '!';
        }
        if ($action == 'prescribe') {
            $provider = DB::table('providers')->where('id', '=', Session::get('user_id'))->first();
            $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
            $data['rxl_license'] = $provider->license . ' - ' . $provider->license_state;
            $data['rxl_dea'] = '';
            $data['rxl_daw'] = '';
            if($request->input('dea') == 'Yes') {
                $data['rxl_dea'] = $provider->dea;
            }
            if($request->input('daw') == 'Yes') {
                $data['rxl_daw'] = trans('noshform.daw');
            }
            unset($data['dea']);
            unset($data['daw']);
            $data['rxl_provider'] = $user->displayname;
            if ($request->input('rxl_days') !== '') {
                $data['rxl_due_date'] = date('Y-m-d H:i:s', strtotime($request->input('rxl_date_prescribed')) + ($request->input('rxl_days') * 86400));
            }
            if (!isset($data['rxl_refill'])) {
                $data['rxl_refill'] = '0';
            } else {
                if ($data['rxl_refill'] == '' || $data['rxl_refill'] == null) {
                    $data['rxl_refill'] = '0';
                }
            }
            $data['prescription'] = 'pending';
            $to = '';
            if (isset($data['notification'])) {
                $to = $data['notification'];
                unset($data['notification']);
            }
            if ($id == '0') {
                $data['pid'] = $pid;
                $row_id1 = DB::table($table)->insertGetId($data, $index);
                $this->audit('Add');
                // foreach ($good_rx_tables as $good_rx_table) {
                //     if ($good_rx_table == $table) {
                //         $this->goodrx_notification($request->input('rxl_medication'), $request->input('rxl_dosage') . $request->input('rxl_dosage_unit'));
                //     }
                // }
                // foreach ($api_tables as $api_table) {
                //     if ($api_table == $table) {
                //         $this->api_data('add', $table, $row_index, $row_id1);
                //     }
                // }
                if ($practice->mtm_extension == 'y') {
                    foreach ($mtm_tables as $mtm_table) {
                        if ($mtm_table == $table) {
                            $this->add_mtm_alert($pid, $table);
                        }
                    }
                }
                // FHIR resource post-save handling
                if (isset($data['label'])) {
                    if ($data['label'] !== '') {
                        $this->uma_resource_process($data['label'], $row_id1, $table);
                    }
                }
                $arr['message'] = $message . trans('noshform.prescribed') . '!';
            } else {
                $data5['rxl_date_old'] = date('Y-m-d H:i:s', time());
                DB::table($table)->where($index, '=', $id)->update($data5);
                $this->audit('Update');
                // FHIR resource post-save handling
                $update_query5 = DB::table($table)->where($index, '=', $id)->first();
                if ($update_query5->label !== '') {
                    $this->uma_resource_process($update_query5->label, $id, $table, true);
                }
                // $this->api_data('update', 'rx_list', 'rxl_id', $id);
                $old_rx = DB::table($table)->where($index, '=', $id)->first();
                $data['rxl_date_active'] = $old_rx->rxl_date_active;
                unset($data['rxl_id']);
                $data['pid'] = $pid;
                $row_id1 = DB::table($table)->insertGetId($data, $index);
                $this->audit('Add');
                // FHIR resource post-save handling
                if (isset($data['label'])) {
                    if ($data['label'] !== '') {
                        $this->uma_resource_process($data['label'], $row_id1, $table);
                    }
                }
                // foreach ($good_rx_tables as $good_rx_table) {
                //     if ($good_rx_table == $table) {
                //         $this->goodrx_notification($request->input('rxl_medication'), $request->input('rxl_dosage') . $request->input('rxl_dosage_unit'));
                //     }
                // }
                // foreach ($api_tables as $api_table) {
                //     if ($api_table == $table) {
                //         $this->api_data('update', $table, $index, $id);
                //     }
                // }
                $arr['message'] = $message . trans('noshform.refilled'). '!';
            }
            if ($next_action !== 'electronic_sign') {
                $this->prescription_notification($row_id1, $to);
            } else {
                Session::put('prescription_notification_to', $to);
                // Create FHIR JSON prescription
        		$json_row = DB::table('rx_list')->where('rxl_id', '=', $row_id1)->first();
        		$prescription_json = $this->resource_detail($json_row, 'MedicationRequest');
                $json_data['json'] = json_encode($prescription_json);
                DB::table('rx_list')->where('rxl_id', '=', $json_row->rxl_id)->update($json_data);
                $this->audit('Update');
            }
            if (Session::has('eid')) {
                App::setLocale(Session::get('practice_locale'));
                if ($request->input('rxl_sig') == '') {
                    $instructions = $request->input('rxl_instructions');
                } else {
                    $instructions = $request->input('rxl_sig') . ', ' . $request->input('rxl_route') . ', ' . $request->input('rxl_frequency');
                }
                $encounter_text = $request->input('rxl_medication') . ' ' . $request->input('rxl_dosage') . ' ' . $request->input('rxl_dosage_unit') . ', ' . $instructions . ' ' . trans('noshform.for') . ' ' . $request->input('rxl_reason');
                $encounter_text .= ', ' . trans('noshform.rxl_quantity') . ': ' . $request->input('rxl_quantity') . ', ' . trans('noshform.rxl_refill') . ': ' . $request->input('rxl_refill');
                $this->plan_build('rx', 'prescribe', $encounter_text);
                App::setLocale(Session::get('user_locale'));
            }
            if ($next_action !== '') {
                // $next_action = route($next_action, [$table, $row_id1, Session::get('pid')]);
                if (filter_var($next_action, FILTER_VALIDATE_URL) == false) {
                    $type = '';
                    $next_action_arr = explode(',', $next_action);
                    if (count($next_action_arr) > 1) {
                        $type = $next_action_arr[1];
                        $next_action = $next_action_arr[0];
                    }
                    $next_action = route($next_action, [$table, $row_id1, Session::get('pid'), $type]);
                }
            }
        }
        if ($action == 'complete') {
            if ($table == 'alerts') {
                $data6['alert_date_complete'] = date('Y-m-d H:i:s');
                $orders_query = DB::table($table)->where($index, '=', $id)->first();
                if ($orders_query->orders_id != '') {
                    $data7['orders_completed'] = 'Yes';
                    DB::table('orders')->where('orders_id', '=', $orders_query->orders_id)->update($data7);
                    $this->audit('Update');
                }
            }
            if ($table == 'orders') {
                $data6['orders_completed'] = '1';
                $alerts_query = DB::table('alerts')->where('orders_id', '=', $id)->first();
                if ($alerts_query) {
                    $data8['alert_date_complete'] = date('Y-m-d H:i:s');
                    DB::table('alerts')->where('alert_id', '=', $alerts_query->alert_id)->update($data8);
                    $this->audit('Update');
                }
            }
            if ($table == 'hippa_request') {
                $data6['received'] = 'Yes';
            }
            DB::table($table)->where($index, '=', $id)->update($data6);
            $this->audit('Update');
            $arr['message'] = $message . trans('noshform.marked_completed') . '!';
        }
        if ($action == 'eie') {
            $row = DB::table('rx_list')->where('rxl_id', '=', $id)->first();
            if ($row->rxl_sig == '') {
                $instructions = $row->rxl_instructions;
            } else {
                $instructions = $row->rxl_sig . ', ' . $row->rxl_route . ', ' . $row->rxl_frequency;
            }
            if (Session::has('eid')) {
                $encounter_text =  $row->rxl_medication . ' ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $instructions . ' ' . trans('noshform.for') . ' ' . $row->rxl_reason;
                $this->plan_build('rx', 'eie', $encounter_text);
            }
            $row1 = DB::table('rx_list')
                ->where('rxl_medication', '=', $row->rxl_medication)
                ->whereNull('rxl_date_inactive')
                ->whereNotNull('rxl_date_old')
                ->orderBy('rxl_date_old', 'desc')
                ->first();
            if ($row1) {
                $rxl_id = $row1->rxl_id;
                $eie_data = [
                    'rxl_date_old' => null,
                    'rcopia_sync' => 'nd1'
                ];
                DB::table('rx_list')->where('rxl_id', '=', $row1->rxl_id)->update($eie_data);
                $this->audit('Update');
                $update_query6 = DB::table('rx_list')->where('rxl_id', '=', $row1->rxl_id)->first();
                if ($update_query6->label !== '') {
                    $this->uma_resource_process($update_query6->label, $row1->rxl_id, 'rx_list');
                }
                // $this->api_data('update', 'rx_list', 'rxl_id', $row1->rxl_id);
            }
            if($practice->rcopia_extension == 'y') {
                $eie_data1['rcopia_sync'] = 'nd';
                DB::table('rx_list')->where('rxl_id', '=', $old_rxl_id)->update($eie_data1);
                $this->audit('Update');
                while(!$this->check_rcopia_delete('rx_list', $old_rxl_id)) {
                    sleep(2);
                }
            }
            $delete_query1 = DB::table('rx_list')->where('rxl_id', '=', $id)->first();
            if ($delete_query1->label !== '') {
                $this->uma_resource_process($delete_query1->label, $id, 'rx_list', true);
            }
            DB::table('rx_list')->where('rxl_id', '=', $id)->delete();
            $this->audit('Delete');

            // $this->api_data('delete', 'rx_list', 'rxl_id', $old_rxl_id);
            // UMA placeholder
            $arr['message'] = trans('noshform.eie') . "!";
        }
        $arr['response'] = 'OK';
        Session::put('message_action', $arr['message']);
        if ($next_action !== '') {
            return redirect($next_action);
        } else {
            if ($table == 'billing_core') {
                if (Session::has('billing_last_page')) {
                    return redirect(Session::get('billing_last_page'));
                } else {
                    return redirect(Session::get('last_page'));
                }
            } else {
                return redirect(Session::get('last_page'));
            }
        }
    }

    public function chart_form(Request $request, $table, $index, $id, $subtype='')
    {
        if ($id == '0') {
            $result = [];
            $items[] = [
                'name' => $index,
                'type' => 'hidden',
                'required' => true,
                'default_value' => null
            ];
        } else {
            $result = DB::table($table)->where($index, '=', $id)->first();
            if ($table == 'other_history') {
                $result = DB::table($table)->where($index, '=', $id)->where('eid', '=', '0')->first();
            }
            $items[] = [
                'name' => $index,
                'type' => 'hidden',
                'required' => true,
                'default_value' => $id
            ];
        }
        $form_function = 'form_' . $table;
        $items = array_merge($items, $this->{$form_function}($result, $table, $id, $subtype));
        // Issues
        if ($table == 'issues') {
            $data['search_icd'] = 'issue';
            $issue_type_arr = [
                'pl' => trans('noshform.problem'),
                'mh' => trans('noshform.medical_history'),
                'sh' => trans('noshform.surgical_history')
            ];
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . $issue_type_arr[$subtype];
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . $issue_type_arr[$subtype];
            }
        }
        // Allergies
        if ($table == 'allergies') {
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.allergy');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.allergy');
            }
        }
        // Medications, Prescriptions
        if ($table == 'rx_list') {
            $edit = $this->access_level('2');
            $data['search_rx'] = 'rxl_medication';
            $rx_array = [
                'prescribe' => trans('noshform.new') . ' ' . trans('noshform.prescription'),
                'refill' => trans('noshform.refill') . ' ' . trans('noshform.prescription')
            ];
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.medication');
                if ($subtype !== '') {
                    // If new prescription and no active encounter - create one and come back
                    if (!Session::has('eid')) {
                        Session::put('encounter_redirect', $request->fullUrl());
                        Session::put('message_action', trans('noshform.encounter_prescription'));
                        return redirect()->route('encounter_details', ['0']);
                    }
                    $data['panel_header'] = $rx_array[$subtype];
                }
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.medication');
                if ($subtype !== '') {
                    $data['panel_header'] = $rx_array[$subtype];
                } else {
                    if ($edit == true) {
                        $dropdown_array = [
                            'default_button_text' => trans('noshform.refill') . ' ' . trans('noshform.medication'),
                            'default_button_text_url' => route('chart_form', ['rx_list', $index, $id, 'refill'])
                        ];
                        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                    }
                }
            }
        }
        // Supplements
        if ($table == 'sup_list') {
            $data['search_supplement'] = 'sup_supplement';
            $data['search_supplement_option'] = 'N';
            if ($subtype == 'order') {
                $data['search_supplement_option'] = 'Y';
            }
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.supplement');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.supplement');
            }
        }
        // Immunizations
        if ($table == 'immunizations') {
            $data['search_immunization'] = 'imm_immunization';
            if (Session::get('group_id') != '100') {
                $data['search_immunization_inventory'] = 'imm_immunization';
            }
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.immunization');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.immunization');
            }
        }
        // Alerts
        if ($table == 'alerts') {
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.alert');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.alert');
            }
            if ($subtype == 'incomplete') {
                $data['panel_header'] = trans('noshform.mark_alert_incomplete');
            }
        }
        // Demographics
        if ($table == 'demographics') {
            if ($subtype == 'name') {
                $data['search_language'] = 'language';
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.name_identity');
            }
            if ($subtype == 'contacts') {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.contacts');
                $dropdown_array = [
                    'default_button_text' => trans('noshform.test_reminder'),
                    'default_button_text_url' => '#',
                    'default_button_id' => 'test_reminder'
                ];
                $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            }
            if ($subtype == 'guardians') {
                $data['search_guardian'] = 'guardian_relationship';
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.guardians');
                $dropdown_array = [
                    'default_button_text' => trans('noshform.guardian_import'),
                    'default_button_text_url' => '#',
                    'default_button_id' => 'guardian_import'
                ];
                $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            }
            if ($subtype == 'other') {
                $dropdown_array = [
                    'default_button_text' => trans('noshform.add_pharmacy'),
                    'default_button_text_url' => route('core_form', ['addressbook', 'address_id', '0', 'Pharmacy']),
                    'default_button_id' => 'add_pharmacy'
                ];
                $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.other');
                Session::put('addressbook_last_page', $request->fullUrl());
            }
            if ($subtype == 'cc') {
                $data['panel_header'] = trans('noshform.credit_card');
            }
        }
        // Docuuments
        if ($table == 'documents') {
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.document');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.document');
            }
        }
        // Insurance
        if ($table == 'insurance') {
            $data['search_insurance'] = 'address_id';
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.insurance');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.insurance');
            }
        }
        // Test Results
        if ($table == 'tests') {
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.test_result1');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.test_result1');
            }
            $data['search_loinc'] = 'test_code';
        }
        // Vitals
        if ($table == 'vitals') {
            $data['template_content'] = 'test';
            $vitals_arr = $this->array_vitals();
            $data['height_unit'] = $vitals_arr['height']['unit'];
            $data['weight_unit'] = $vitals_arr['weight']['unit'];
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.vital_signs');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.vital_signs');
            }
        }
        // Other history
        if ($table == 'other_history') {
            if ($subtype == 'lifestyle') {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.lifestyle');
            }
            if ($subtype == 'habits') {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.habits');
            }
            if ($subtype == 'mental_health') {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.mental_health');
            }
        }
        // Billing core
        if ($table == 'billing_core') {
            $data['search_cpt'] = 'cpt';
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.cpt');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.cpt');
            }
        }
        // Orders
        if ($table == 'orders') {
            $type_arr = [
                'orders_labs' => [trans('noshform.laboratory'), trans('noshform.laboratory_results_pending'), 'orders_labs_icd', trans('noshform.orders_labs'), trans('noshform.laboratory'), 'Laboratory'],
                'orders_radiology' => [trans('noshform.imaging'), trans('noshform.radiology_results_pending'), 'orders_radiology_icd', trans('noshform.orders_radiology'), trans('noshform.radiology'), 'Radiology'],
                'orders_cp' => [trans('noshform.cardiopulmonary'), trans('noshform.cardiopulmonary_results_pending'), 'orders_cp_icd', trans('noshform.orders_cp'), trans('noshform.cardiopulmonary'), 'Cardiopulmonary'],
                'orders_referrals' => [trans('noshform.referral'), trans('noshform.referral_pending'), 'orders_referrals_icd', trans('noshform.orders_referrals'), trans('noshform.referral'), 'Referral']
            ];
            $data['search_icd'] = $type_arr[$subtype][2];
            if ($subtype == 'orders_labs') {
                $data['search_loinc'] = $subtype;
            }
            if ($subtype == 'orders_radiology') {
                $data['search_imaging'] = $subtype;
            }
            $data['template_content'] = 'test';
            if ($id == '0') {
                // If new order and no active encounter - create one and come back
                if (!Session::has('eid')) {
                    Session::put('encounter_redirect', $request->fullUrl());
                    Session::put('message_action', trans('noshform.encounter_order'));
                    return redirect()->route('encounter_details', ['0']);
                }
                $data['panel_header'] = trans('noshform.add') . ' ' . $type_arr[$subtype][0] . ' ' . trans('noshform.order');
            } else {
                if (!Session::has('eid')) {
                    // If no active encounter, check if encounter is locked; if so, create addendum
                    $encounter_query = DB::table('encounters')->where('eid', '=', $result->eid)->first();
                    if ($encounter_query->encounter_signed == 'No') {
                        Session::put('eid', $result->eid);
                        $user_query = DB::table('users')->where('id', '=', $encounter_query->user_id)->first();
                        Session::put('encounter_DOS', $encounter_query->encounter_DOS);
                        Session::put('encounter_template', $encounter_query->encounter_template);
                        Session::put('encounter_provider', $user_query->displayname);
                    } else {
                        Session::put('encounter_redirect', $request->fullUrl());
                        Session::put('message_action', trans('noshform.addendum_order'));
                        return redirect()->route('encounter_addendum', $result->eid);
                    }
                }
                $data['panel_header'] = trans('noshform.edit') . ' ' . $type_arr[$subtype][0] . ' ' . trans('noshform.order');
            }
            $dropdown_array = [
                'default_button_text' => trans('noshform.add_new') . ' ' . $type_arr[$subtype][4] . ' ' . trans('noshform.provider'),
                'default_button_text_url' => route('core_form', ['addressbook', 'address_id', '0', $type_arr[$subtype][5]]),
                'default_button_id' => 'add_external_provider'
            ];
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            Session::put('addressbook_last_page', $request->fullUrl());
        }
        // Telephone messages
        if ($table == 't_messages') {
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.t_message');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.t_message');
                $dropdown_array = [
                    'items_button_icon' => 'fa-bars'
                ];
                $items1 = [];
                $items1[] = [
                    'type' => 'item',
                    'label' => trans('noshform.add') . trans('noshform.action'),
                    'icon' => 'fa-plus',
                    'url' => route('action_edit', [$table, $index, $id, 'new']),
                    'id' => 'nosh_t_message_add_action'
                ];
                $items1[] = [
                    'type' => 'item',
                    'label' => trans('noshform.messaging_add_photo'),
                    'icon' => 'fa-camera',
                    'url' => route('encounter_add_photo', [$id, 't_messages']),
                    'id' => 'nosh_t_message_add_photo'
                ];
                $dropdown_array['items'] = $items1;
                $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                Session::put('t_messages_photo_last_page', $request->fullUrl());
                Session::put('action_redirect', $request->fullUrl());
            }
        }
        // Records Release
        if ($table == 'hippa') {
            $data['search_address'] = 'address_id';
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.hippa');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.hippa');
            }
            $dropdown_array = [
                'default_button_text' => trans('noshform.add_address_book'),
                'default_button_text_url' => route('core_form', ['addressbook', 'address_id', '0']),
                'default_button_id' => 'add_external_provider'
            ];
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            Session::put('addressbook_last_page', $request->fullUrl());
        }
        // Records Request
        if ($table == 'hippa_request') {
            $data['search_address'] = 'address_id';
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.hippa_request');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.hippa_request');
            }
            $dropdown_array = [
                'default_button_text' => trans('noshform.add_address_book'),
                'default_button_text_url' => route('core_form', ['addressbook', 'address_id', '0']),
                'default_button_id' => 'add_external_provider'
            ];
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            Session::put('addressbook_last_page', $request->fullUrl());
        }
        // MTM
        if ($table == 'mtm') {
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.new') . ' ' . trans('noshform.mtm1');
            } else {
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.mtm1');
            }
        }
        $form_array = [
            'form_id' => $table . '_form',
            'action' => route('chart_action', ['table' => $table, 'action' => 'save', 'index' => $index, 'id' => $id]),
            'items' => $items,
            'save_button_label' => trans('noshform.save')
        ];
        if ($table == 'rx_list') {
            if ($subtype == 'prescribe' || $subtype == 'refill') {
                $form_array['action'] = route('chart_action', ['table' => $table, 'action' => 'prescribe', 'index' => $index, 'id' => $id]);
                $form_array['intro'] = '<div class="col-md-6 col-md-offset-4"><img src="https://d4fuqqd5l3dbz.cloudfront.net/static/images/powered-by-goodrx-black-xs.png" style="height:40px;vertical-align:text-top;margin10px" title="' . trans('noshform.good_rx') . '" data-toggle="tooltip"></div>';
                $data['dosage_calculator'] = true;
                $data['recent_weight'] = '';
                $weight = DB::table('vitals')->where('pid', '=', Session::get('pid'))->where('weight', '!=', '')->orderBy('vitals_date', 'desc')->first();
                if ($weight) {
                    $data['recent_weight'] = $weight->weight;
                }
                $vitals_arr = $this->array_vitals();
                $data['weight_unit'] = $vitals_arr['weight']['unit'];
            }
        }
        if ($table == 't_messages') {
            $form_array['add_save_button'] = [
                'sign' => trans('noshform.sign_message'),
                'phone_encounter' => trans('noshform.create_phone_encounter')
            ];
        }
        // Specify show template
        if ($table == 'other_history' || $table == 't_messages') {
            $data['template_content'] = 'test';
        }
        if (Session::has('billing_last_page')) {
            if ($table == 'billing_core') {
                $form_array['origin'] = Session::get('billing_last_page');
            }
        }
        if ($table == 't_messages') {
            $data['content'] = $this->actions_build($table, $index, $id);
            $data['content'] .= $this->form_build($form_array);
            $images = DB::table('image')->where('t_messages_id', '=', $id)->get();
            if ($images->count()) {
                $data['content'] .= '<br><h5>Images:</h5><div class="list-group gallery">';
                foreach ($images as $image) {
                    $file_path1 = '/temp/' . time() . '_' . basename($image->image_location);
                    $file_path = public_path() . $file_path1;
                    copy($image->image_location, $file_path);
                    $data['content'] .= '<div class="col-sm-4 col-xs-6 col-md-3 col-lg-3"><a class="thumbnail fancybox nosh-no-load" rel="ligthbox" href="' . url('/') . $file_path1 . '">';
                    $data['content'] .= '<img class="img-responsive" alt="" src="' . url('/') . $file_path1 . '" />';
                    $data['content'] .= '<div class="text-center"><small class="text-muted">' . $image->image_description . '</small></div></a>';
                    $data['content'] .= '<a href="' . route('encounter_edit_image', [$image->image_id, $id]) . '" class="nosh-photo-delete-t-message edit-icon btn btn-success"><i class="glyphicon glyphicon-pencil"></i></a>';
                    $data['content'] .= '<a href="' . route('encounter_delete_photo', [$image->image_id]) . '" class="nosh-photo-delete-t-message close-icon btn btn-danger"><i class="glyphicon glyphicon-remove"></i></a></div>';
                }
                $data['content'] .= '</div>';
            }
        } else {
            $data['content'] = $this->form_build($form_array);
        }
        if ($table == 'rx_list') {
            if (Session::get('patient_centric') == 'y' || Session::get('patient_centric') == 'yp') {
                if ($id !== '0') {
                    $med = explode(' ', $result->rxl_medication);
                    $data['goodrx'] = $this->goodrx_drug_search($med[0]);
                    $data['link'] = $this->goodrx_information($result->rxl_medication, $result->rxl_dosage . $result->rxl_dosage_unit);
                    $dropdown_array = [];
                    $items = [];
                    $items[] = [
                        'type' => 'item',
                        'label' => trans('noshform.goodrx'),
                        'icon' => 'fa-chevron-down',
                        'url' => '#goodrx_container'
                    ];
                    $dropdown_array['items'] = $items;
                    $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
                }
            }
        }
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function chart_queue(Request $request, $action, $hippa_id, $pid, $type='encounters')
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $type_arr = [
            'encounters' => [trans('noshform.encounters'), 'fa-stethoscope'],
            't_messages' => [trans('noshform.t_messages'), 'fa-phone'],
            'Laboratory' => [trans('noshform.laboratory'), 'fa-flask'],
            'Imaging' => [trans('noshform.imaging'), 'fa-film'],
            'Cardiopulmonary' => [trans('noshform.cardiopulmonary'), 'fa-heartbeat'],
            'Endoscopy' => [trans('noshform.endoscopy'), 'fa-video-camera'],
            'Referrals' => [trans('noshform.referrals'), 'fa-hand-o-right'],
            'Past_Records' => [trans('noshform.past_records'), 'fa-folder'],
            'Other_Forms' => [trans('noshform.other_forms'), 'fa-file-o'],
            'Letters' => [trans('noshform.letters'), 'fa-file-text-o'],
            'ccda' => [trans('noshform.ccda'), 'fa-file-code-o']
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('chart_queue', [$action, $hippa_id, $pid, $key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        if ($type == 'encounters' || $type == 't_messages') {
            if ($type == 'encounters') {
                $encounter_type = $this->array_encounter_type();
                $encounters_query = DB::table('encounters')->where('pid', '=', $pid)->where('addendum', '=', 'n')->orderBy('encounter_DOS', 'desc')->where('encounter_signed', '=', 'Yes');
                if (Session::get('patient_centric') == 'n') {
                    $encounters_query->where('practice_id', '=', Session::get('practice_id'));
                }
                $result = $encounters_query->get();
            }
            if ($type == 't_messages') {
                $t_messages_query = DB::table('t_messages')->where('pid', '=', $pid)->orderBy('t_messages_dos', 'desc')->where('t_messages_signed', '=', 'Yes');
                if (Session::get('patient_centric') == 'n') {
                    $t_messages_query->where('practice_id', '=', Session::get('practice_id'));
                }
                $result = $t_messages_query->get();
            }
        } else {
            $documents_query = DB::table('documents')->where('pid', '=', $pid)->where('documents_type', '=', $type)->orderBy('documents_date', 'desc');
            $result = $documents_query->get();
        }
        $return = '';
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label_class'] = 'chart_queue_item';
                if ($type == 'encounters' || $type == 't_messages') {
                    if ($type == 'encounters') {
                        $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->encounter_DOS)) . '</b> - ' . $encounter_type[$row->encounter_template] . ' - ' . $row->encounter_cc;
                        $check1 = DB::table('hippa')->where('other_hippa_id', '=', $hippa_id)->where('eid', '=', $row->eid)->first();
                        if ($check1) {
                            $arr['active'] = true;
                            $arr['label_data'] = $check1->hippa_id;
                        } else {
                            $arr['label_data'] = 'eid,' . $row->eid . ',' . $hippa_id;
                        }
                    }
                    if ($type == 't_messages') {
                        $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->t_messages_dos)) . '</b> - ' . $row->t_messages_subject;
                        $check2 = DB::table('hippa')->where('other_hippa_id', '=', $hippa_id)->where('t_messages_id', '=', $row->t_messages_id)->first();
                        if ($check2) {
                            $arr['active'] = true;
                            $arr['label_data'] = $check2->hippa_id;
                        } else {
                            $arr['label_data'] = 't_messages_id,' . $row->t_messages_id . ',' . $hippa_id;
                        }
                    }
                } else {
                    $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->documents_date)) . '</b> - ' . $row->documents_desc . ' from ' . $row->documents_from;
                    $check3 = DB::table('hippa')->where('other_hippa_id', '=', $hippa_id)->where('documents_id', '=', $row->documents_id)->first();
                    if ($check3) {
                        $arr['active'] = true;
                        $arr['label_data'] = $check3->hippa_id;
                    } else {
                        $arr['label_data'] = 'documents_id,' . $row->documents_id . ',' . $hippa_id;
                    }
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'chart_queue_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['panel_header'] = trans('noshform.chart_queue');
        $data['records_active'] = true;
        $dropdown_array1 = [
            'items_button_text' => trans('noshform.actions')
        ];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => trans('noshform.print'),
            'icon' => 'fa-print',
            'url' => route('print_action', ['hippa', $hippa_id, $pid, 'queue'])
        ];
        $items1[] = [
            'type' => 'item',
            'label' => trans('noshform.fax'),
            'icon' => 'fa-fax',
            'url' => route('fax_action', ['hippa', $hippa_id, $pid, 'queue'])
        ];
        $items1[] = [
            'type' => 'item',
            'label' => trans('noshform.cancel'),
            'icon' => 'fa-chevron-left',
            'url' => route('patient')
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function conditions_list(Request $request, $type="")
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('issues')->where('pid', '=', Session::get('pid'))->orderBy('issue', 'asc');
        if ($type == 'active') {
            $query->whereNull('issue_date_inactive');
            $dropdown_array = [
                'items_button_text' => trans('noshform.active')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.inactive'),
                'icon' => 'fa-times',
                'url' => route('conditions_list', ['inactive'])
            ];
        } else {
            $query->whereNotNull('issue_date_inactive');
            $dropdown_array = [
                'items_button_text' => trans('noshform.inactive')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.active'),
                'icon' => 'fa-check',
                'url' => route('conditions_list', ['active'])
            ];
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('7');
        $columns = Schema::getColumnListing('issues');
        $row_index = $columns[0];
        $pl_list_array = [];
        $mh_list_array = [];
        $sh_list_array = [];
        if ($result->count()) {
            $return .= '<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#pl" style="color:green;">' . trans('noshform.problems') . '</a></li><li><a data-toggle="tab" href="#mh" title="' . trans('noshform.medical_history') . '">' . trans('noshform.past') . '</a></li><li><a data-toggle="tab" href="#sh" title="' . trans('noshform.surgical_history') . '" style="color:red;">' . trans('noshform.surgeries') . '</a></li></ul><div class="tab-content" style="margin-top:15px;">';
            foreach ($result as $row) {
                if ($row->type == 'Problem List') {
                    $pl_arr = [];
                    $pl_arr['label'] = $row->issue;
                    if ($edit == true) {
                        $pl_arr['edit'] = route('chart_form', ['issues', $row_index, $row->$row_index, 'pl']);
                        if ($type == 'active') {
                            $pl_arr['inactivate'] = route('chart_action', ['table' => 'issues', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                        } else {
                            $pl_arr['reactivate'] = route('chart_action', ['table' => 'issues', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                        }
                        $pl_arr['move_mh'] = route('chart_action', ['table' => 'issues', 'action' => 'move_mh', 'index' => $row_index, 'id' => $row->$row_index]);
                        $pl_arr['move_sh'] = route('chart_action', ['table' => 'issues', 'action' => 'move_sh', 'index' => $row_index, 'id' => $row->$row_index]);
                        if (Session::get('group_id') == '2') {
                            $pl_arr['delete'] = route('chart_action', ['table' => 'issues', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                        }
                        if (Session::has('eid')) {
                            $pl_arr['encounter'] = route('encounter_assessment_add', ['issue', $row->$row_index]);
                        }
                    }
                    if ($row->reconcile !== null && $row->reconcile !== 'y') {
                        $pl_arr['danger'] = true;
                    }
                    $pl_list_array[] = $pl_arr;
                }
                if ($row->type == 'Medical History') {
                    $mh_arr = [];
                    $mh_arr['label'] = $row->issue;
                    if ($edit == true) {
                        $mh_arr['edit'] = route('chart_form', ['issues', $row_index, $row->$row_index, 'mh']);
                        if ($type == 'active') {
                            $mh_arr['inactivate'] = route('chart_action', ['table' => 'issues', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                        } else {
                            $mh_arr['reactivate'] = route('chart_action', ['table' => 'issues', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                        }
                        $mh_arr['move_pl'] = route('chart_action', ['table' => 'issues', 'action' => 'move_pl', 'index' => $row_index, 'id' => $row->$row_index]);
                        if (Session::get('group_id') == '2') {
                            $mh_arr['delete'] = route('chart_action', ['table' => 'issues', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                        }
                        if (Session::has('eid')) {
                            $mh_arr['encounter'] = route('encounter_assessment_add', ['issue', $row->$row_index]);
                        }
                    }
                    if ($row->reconcile !== null && $row->reconcile !== 'y') {
                        $mh_arr['danger'] = true;
                    }
                    $mh_list_array[] = $mh_arr;
                }
                if ($row->type == 'Surgical History') {
                    $sh_arr = [];
                    $sh_arr['label'] = $row->issue;
                    if ($edit == true) {
                        $sh_arr['edit'] = route('chart_form', ['issues', $row_index, $row->$row_index, 'sh']);
                        if ($type == 'active') {
                            $sh_arr['inactivate'] = route('chart_action', ['table' => 'issues', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                        } else {
                            $sh_arr['reactivate'] = route('chart_action', ['table' => 'issues', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                        }
                        $sh_arr['move_pl'] = route('chart_action', ['table' => 'issues', 'action' => 'move_pl', 'index' => $row_index, 'id' => $row->$row_index]);
                        if (Session::get('group_id') == '2') {
                            $sh_arr['delete'] = route('chart_action', ['table' => 'issues', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                        }
                        if (Session::has('eid')) {
                            $sh_arr['encounter'] = route('encounter_assessment_add', ['issue', $row->$row_index]);
                        }
                    }
                    if ($row->reconcile !== null && $row->reconcile !== 'y') {
                        $sh_arr['danger'] = true;
                    }
                    $sh_list_array[] = $sh_arr;
                }
            }
            $return .= '<div id="pl" class="tab-pane fade in active">' . $this->result_build($pl_list_array, 'conditions_list_pl') . '</div>';
            $return .= '<div id="mh" class="tab-pane fade">' . $this->result_build($mh_list_array, 'conditions_list_mh') . '</div>';
            $return .= '<div id="sh" class="tab-pane fade">' . $this->result_build($sh_list_array, 'conditions_list_sh') . '</div>';
            $return .= '</div>';
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['issues_active'] = true;
        $data['panel_header'] = trans('noshform.conditions');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_problem'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['issues', $row_index, '0', 'pl'])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_medical_history'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['issues', $row_index, '0', 'mh'])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_surgical_history'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['issues', $row_index, '0', 'sh'])
            ];
            if (Session::has('eid')) {
                $items[] = [
                    'type' => 'separator'
                ];
                $items1[] = [
                    'type' => 'item',
                    'label' => trans('noshform.copy_all_problems'),
                    'icon' => 'fa-clone',
                    'url' => route('encounter_assessment_add', ['issue', 'pl'])
                ];
                $items1[] = [
                    'type' => 'item',
                    'label' => trans('noshform.copy_all_medical_history'),
                    'icon' => 'fa-clone',
                    'url' => route('encounter_assessment_add', ['issue', 'mh'])
                ];
                $items1[] = [
                    'type' => 'item',
                    'label' => trans('noshform.copy_all_surgical_history'),
                    'icon' => 'fa-clone',
                    'url' => route('encounter_assessment_add', ['issue', 'sh'])
                ];
            }
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if (Session::has('eid') && $type == 'active') {
            if (Session::get('group_id') == '2') {
                // Mark conditions list as reviewed by physician
                $mh_encounter = '';
                $sh_encounter = '';
                if (! empty($mh_list_array)) {
                    $mh_encounter .= implode("\n", array_column($mh_list_array, 'label'));
                }
                if (! empty($sh_list_array)) {
                    $sh_encounter .= implode("\n", array_column($sh_list_array, 'label'));
                }
                $mh_encounter .= "\n" . trans('noshform.reviewed_by') . ' ' . Session::get('displayname') . ' ' . trans('noshform.on') . ' ' . date('Y-m-d');
                $sh_encounter .= "\n" . trans('noshform.reviewed_by') . ' ' . Session::get('displayname') . ' ' . trans('noshform.on') . ' ' . date('Y-m-d');
                $encounter_query = DB::table('other_history')->where('eid', '=', Session::get('eid'))->first();
                $encounter_data = [
                    'oh_pmh' => $mh_encounter,
                    'oh_psh' => $sh_encounter
                ];
                if ($encounter_query) {
                    DB::table('other_history')->where('eid', '=', Session::get('eid'))->update($encounter_data);
                } else {
                    $encounter_data['eid'] = Session::get('eid');
                    $encounter_data['pid'] = Session::get('pid');
                    DB::table('other_history')->insert($encounter_data);
                }
                if ($data['message_action'] !== '') {
                    $data['message_action'] .= '<br>';
                }
                $data['message_action'] .= trans('noshform.issue_message_action_encounter');
            }
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function demographics(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $result = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $return = '';
        if (Session::get('patient_centric') == 'n' && Session::get('group_id') != '100') {
            $dropdown_array = [
                'default_button_text' => trans('noshform.register_portal'),
                'default_button_text_url' => route('register_patient')
            ];
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        }
        $active_arr = [
            '0' => trans('noshform.inactive'),
            '1' => trans('noshform.active')
        ];
        if ($result) {
            $gender = $this->array_gender();
            $marital = $this->array_marital();
            $state = $this->array_states($result->country);
            $guardian_state = $this->array_states($result->guardian_country);
            $header_arr = [
                trans('noshform.name_identity') => route('chart_form', ['demographics', 'pid', Session::get('pid'), 'name']),
                trans('noshform.contacts') => route('chart_form', ['demographics', 'pid', Session::get('pid'), 'contacts']),
                trans('noshform.guardians') => route('chart_form', ['demographics', 'pid', Session::get('pid'), 'guardians']),
                trans('noshform.other') => route('chart_form', ['demographics', 'pid', Session::get('pid'), 'other']),
            ];
            $identity_arr = [
                trans('noshform.lastname') => $result->lastname,
                trans('noshform.firstname') => $result->firstname,
                trans('noshform.nickname') => $result->nickname,
                trans('noshform.middle') => $result->middle,
                trans('noshform.title') => $result->title,
                trans('noshform.DOB') => date('F jS, Y', strtotime($result->DOB)),
                trans('noshform.sex') => $gender[$result->sex],
                trans('noshform.patient_id') => $result->patient_id,
                trans('noshform.ss') => $result->ss,
                trans('noshform.race') => $result->race,
                trans('noshform.marital_status') => $result->marital_status,
                trans('noshform.partner_name') => $result->partner_name,
                trans('noshform.employer') => $result->employer,
                trans('noshform.ethnicity') => $result->ethnicity,
                trans('noshform.caregiver') => $result->caregiver,
                trans('noshform.status') => $active_arr[$result->active],
                trans('noshform.referred_by') => $result->referred_by,
                trans('noshform.language') => $result->language
            ];
            $contact_arr = [
                trans('noshform.street_address1') => $result->address,
                trans('noshform.country') => $result->country,
                trans('noshform.city') => $result->city,
                trans('noshform.state') => $state[$result->state],
                trans('noshform.zip') => $result->zip,
                trans('noshform.email') => $result->email,
                trans('noshform.phone_home') => $result->phone_home,
                trans('noshform.phone_work') => $result->phone_work,
                trans('noshform.phone_cell') => $result->phone_cell,
                trans('noshform.emergency_contact') => $result->emergency_contact,
                trans('noshform.reminder_method') => $result->reminder_method
            ];
            $guardian_arr = [
                trans('noshform.lastname') => $result->guardian_lastname,
                trans('noshform.firstname') => $result->guardian_firstname,
                trans('noshform.relationship') => $result->guardian_relationship,
                trans('noshform.street_address1') => $result->guardian_address,
                trans('noshform.country') => $result->guardian_country,
                trans('noshform.city') => $result->guardian_city,
                trans('noshform.state') => $guardian_state[$result->guardian_state],
                trans('noshform.zip') => $result->guardian_zip,
                trans('noshform.email') => $result->guardian_email,
                trans('noshform.phone_home') => $result->guardian_phone_home,
                trans('noshform.phone_work') => $result->guardian_phone_work,
                trans('noshform.phone_cell') => $result->guardian_phone_cell
            ];
            $other_arr = [
                trans('noshform.preferred_provider') => $result->preferred_provider,
                trans('noshform.preferred_pharmacy') => $result->preferred_pharmacy,
                trans('noshform.other1') => $result->other1,
                trans('noshform.other2') => $result->other2,
                trans('noshform.comments') => $result->comments
            ];
            if ($result->pharmacy_address_id !== '' && $result->pharmacy_address_id !== null) {
                $pharmacy_query = DB::table('addressbook')->where('address_id', '=', $result->pharmacy_address_id)->first();
                $other_arr['Preferred Pharmacy'] = (is_object($pharmacy_query) ? $pharmacy_query->displayname : '');
            }
            $return = $this->header_build($header_arr, trans('noshform.name_identity'));
            foreach ($identity_arr as $key1 => $value1) {
                if ($value1 !== '' && $value1 !== null) {
                    $return .= '<div class="row"><div class="col-md-3"><b>' . $key1 . '</b></div><div class="col-md-8">' . $value1 . '</div></div>';
                }
            }
            $return .= '</div></div></div>';
            $return .= $this->header_build($header_arr, trans('noshform.contacts'));
            foreach ($contact_arr as $key2 => $value2) {
                if ($value2 !== '' && $value2 !== null) {
                    $return .= '<div class="row"><div class="col-md-3"><b>' . $key2 . '</b></div><div class="col-md-8">' . $value2 . '</div></div>';
                }
            }
            $return .= '</div></div></div>';
            $return .= $this->header_build($header_arr, trans('noshform.guardians'));
            foreach ($guardian_arr as $key3 => $value3) {
                if ($value3 !== '' && $value3 !== null) {
                    $return .= '<div class="row"><div class="col-md-3"><b>' . $key3 . '</b></div><div class="col-md-8">' . $value3 . '</div></div>';
                }
            }
            $return .= '</div></div></div>';
            $return .= $this->header_build($header_arr, trans('noshform.other'));
            foreach ($other_arr as $key4 => $value4) {
                if ($value4 !== '' && $value4 !== null) {
                    $return .= '<div class="row"><div class="col-md-3"><b>' . $key4 . '</b></div><div class="col-md-8">' . $value4 . '</div></div>';
                }
            }
            $return .= '</div></div></div>';
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['demographics_active'] = true;
        $data['panel_header'] = trans('noshform.demographics');
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function demographics_add_photo(Request $request)
    {
        if ($request->isMethod('post')) {
            $pid = Session::get('pid');
            $directory = Storage::path($pid);
            $postData = $request->post();
            $img = Arr::has($postData, 'img');
            if ($img) {
                $img_data = substr($request->input('img'), strpos($request->input('img'), ',') + 1);
                $img_data = base64_decode($img_data);
                $new_name = time() . '_photo_' . Session::get('user_id') . ".png";
            } else {
                $file = $request->file('file_input');
                $new_name = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.' . $file->getClientOriginalExtension();
            }
            $file_path = $directory . "/" . $new_name;
            if ($img) {
                Storage::put($file_path, $img_data);
            } else {
                $file->move($directory, $new_name);
            }
            $data['photo'] = $file_path;
            $image_id = DB::table('demographics')->where('pid', '=', $pid)->update($data);
            $this->audit('Update');
            Session::put('message_action', trans('noshform.photo_added'));
            return redirect(Session::get('last_page'));
        } else {
            $data['panel_header'] = trans('noshform.upload_photo');
            $data['document_upload'] = route('demographics_add_photo');
            $type_arr = ['png', 'jpg'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['content'] .= '<div class="panel panel-default"><div class="panel-heading" id="start_video" data-toggle="collapse" data-target="#snapshot1"><h4 class="panel-title">' . trans('noshform.snapshot') . '</h4></div><div id="snapshot1" class="panel-collapse collapse"><div class="panel-body">';
            $data['content'] .= '<form action="' . route('demographics_add_photo') . '" method="POST">' . csrf_field() . '<div style="margin:auto;" id="screenshot"><video autoplay style="display:none;width: 100% !important;height: auto !important;"></video><img src="" style="width: 100% !important;height: auto !important;"><canvas style="display:none;"></canvas><input type="hidden" name="img" id="img"></div>';
            $data['content'] .= '<div style="margin:auto;"><button type="button" id="stop_video" class="btn btn-primary" style="margin:5px;"><i class="fa fa-pause fa-fw" style="margin-right:10px"></i>' . trans('noshform.snap') . '</button><button type="button" id="restart_picture" class="btn btn-primary" style="margin:5px;display:none;"><i class="fa fa-repeat fa-fw" style="margin-right:10px;"></i>' . trans('noshform.retake') . '</button><button type="submit" id="save_picture" class="btn btn-success" style="margin:5px;display:none;"><i class="fa fa-camera fa-fw" style="margin-right:10px"></i>' . trans('noshform.save') . '</button><button type="button" id="cancel_picture" class="btn btn-danger" style="margin:5px;display:none;"><i class="fa fa-times fa-fw" style="margin-right:10px"></i>' . trans('noshform.cancel') . '</button></form></div></div></div></div>';
            $data['content'] .= '<div class="panel panel-default"><div class="panel-heading" data-toggle="collapse" data-target="#upload1"><h4 class="panel-title">' . trans('noshform.upload_photo') . '</h4></div><div id="upload1" class="panel-collapse collapse"><div class="panel-body">';
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('document_upload', $data);
        }
    }

    public function document_letter(Request $request)
    {
        $pid = Session::get('pid');
        if ($request->isMethod('post')) {
            ini_set('memory_limit','196M');
            $result = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $html = $this->page_intro('Letter', Session::get('practice_id'))->render();
            $html .= $this->page_letter($request->input('letter_to'), $request->input('letter_body'), $request->input('address_id'));
            $user_id = Session::get('user_id');
            $file_path = Storage::path($pid . '/letter_' . time() . '.pdf');
            $this->generate_pdf($html, $file_path, 'footerpdf', '', '1');
            while(!file_exists($file_path)) {
                sleep(2);
            }
            $desc = trans('noshform.letter_for') . ' ' . Session::get('ptname');
            $pages_data = [
                'documents_url' => $file_path,
                'pid' => $pid,
                'documents_type' => 'Letters',
                'documents_desc' => $desc,
                'documents_from' => Session::get('displayname'),
                'documents_viewed' => Session::get('displayname'),
                'documents_date' => date('Y-m-d H:i:s', time())
            ];
            $id = DB::table('documents')->insertGetId($pages_data, 'documents_id');
            $this->audit('Add');
            return redirect()->route('document_view', [$id]);
        } else {
            $pt = DB::table('demographics')->where('pid', '=', $pid)->first();
            $encounter = DB::table('encounters')->where('pid', '=', Session::get('pid'))
                ->where('eid', '!=', '')
                ->where('practice_id', '=', Session::get('practice_id'))
                ->orderBy('eid', 'desc')
                ->first();
            $start = trans('noshform.letter1') . $pt->firstname . ' ' . $pt->lastname . ' (' . trans('noshform.DOB') . ': ' . date('F jS, Y', $this->human_to_unix($pt->DOB)) . '), ' . trans('noshform.letter2') . '.  ';
            if ($encounter) {
                App::setLocale(Session::get('practice_locale'));
                $start .= $pt->firstname . ' ' . trans('noshform.last_seen') . ' ' . date('F jS, Y', strtotime($encounter->encounter_DOS)) . '.  ';
                App::setLocale(Session::get('user_locale'));
            }
            $data['documents_active'] = true;
            $data['panel_header'] = trans('noshform.generate_letter');
            $items[] = [
                'name' => 'address_id',
                'type' => 'hidden',
                'default_value' => null
            ];
            $items[] = [
                'name' => 'letter_to',
                'label' => trans('noshform.letter_to'),
                'type' => 'text',
                'required' => true,
                'typeahead' => route('typeahead', ['table' => 'addressbook', 'column' => 'displayname', 'subtype' => 'address_id']),
                'default_value' => null
            ];
            $items[] = [
                'name' => 'letter_body',
                'label' => trans('noshform.letter_body'),
                'type' => 'textarea',
                'required' => true,
                'default_value' => $start
            ];
            $form_array = [
                'form_id' => 'document_letter_form',
                'action' => route('document_letter'),
                'items' => $items,
                'save_button_label' => trans('noshform.save_print')
            ];
            $data['content'] = $this->form_build($form_array);
            $data['template_content'] = 'test';
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function document_upload(Request $request)
    {
        ini_set('memory_limit','196M');
        ini_set('max_execution_time', '300');
        if ($request->isMethod('post')) {
            $pid = Session::get('pid');
            $directory = Storage::path($pid);
            $file = $request->file('file_input');
            $new_name = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.pdf';
            if ($file->getClientOriginalExtension() == 'pdf' || $file->getClientOriginalExtension() == 'PDF') {
                $file->move($directory, $new_name);
            } else {
                $temp_filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $temp_file = storage_path($temp_filename);
                $file->move(storage_path(), $temp_filename);
                $image = new Imagick($temp_file);
                $image->setImageFormat('pdf');
                $image->writeImage($directory . '/' . $new_name);
                File::delete($temp_file);
            }
            $data = [
                'documents_url' => $directory . '/' . $new_name,
                'pid' => $pid
            ];
            $documents_id = DB::table('documents')->insertGetId($data, 'documents_id');
            $this->audit('Add');
            return redirect()->route('chart_form', ['documents', 'documents_id', $documents_id]);
        } else {
            $data['documents_active'] = true;
            $data['panel_header'] = trans('noshform.document_upload');
            $data['document_upload'] = route('document_upload');
            $type_arr = ['pdf', 'png', 'jpg'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('document_upload', $data);
        }
    }

    public function document_view(Request $request, $id)
    {
        $pid = Session::get('pid');
        $result = DB::table('documents')->where('documents_id', '=', $id)->first();
        if ($result->documents_type == 'ccda' || $result->documents_type == 'ccr') {
            return redirect()->route('upload_ccda_view', [$id, 'issues']);
        }
        $file_path = $result->documents_url;
        $data1['documents_viewed'] = Session::get('displayname');
        DB::table('documents')->where('documents_id', '=', $id)->update($data1);
        $this->audit('Update');
        $name = time() . '_' . $pid . '.pdf';
        $data['filepath'] = public_path() . '/temp/' . $name;
        copy($file_path, $data['filepath']);
        Session::put('file_path_temp', $data['filepath']);
        while(!file_exists($data['filepath'])) {
            sleep(2);
        }
        $data['document_url'] = asset('temp/' . $name);
        $dropdown_array = [];
        $items = [];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.back'),
            'icon' => 'fa-chevron-left',
            'url' => Session::get('last_page')
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $dropdown_array1 = [];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => '',
            'icon' => 'fa-pencil',
            'url' => route('chart_form', ['documents', 'documents_id', $id])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['documents_active'] = true;
        $data['panel_header'] = date('Y-m-d', $this->human_to_unix($result->documents_date)) . ' - ' . $result->documents_desc . ' ' . trans('noshform.from1') . ' ' . $result->documents_from;
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('documents');
        $data['assets_css'] = $this->assets_css('documents');
        return view('documents', $data);
    }

    public function documents_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        if ($type == 'All') {
            $query = DB::table('documents')->where('pid', '=', Session::get('pid'))->orderBy('documents_date', 'desc');
        } else {
            $query = DB::table('documents')->where('pid', '=', Session::get('pid'))->where('documents_type', '=', $type)->orderBy('documents_date', 'desc');
        }
        $type_arr = [
            'All' => [trans('noshform.all'), 'fa-files-o'],
            'Laboratory' => [trans('noshform.laboratory'), 'fa-flask'],
            'Imaging' => [trans('noshform.imaging'), 'fa-film'],
            'Cardiopulmonary' => [trans('noshform.cardiopulmonary'), 'fa-heartbeat'],
            'Endoscopy' => [trans('noshform.endoscopy'), 'fa-video-camera'],
            'Referrals' => [trans('noshform.referrals'), 'fa-hand-o-right'],
            'Past_Records' => [trans('noshform.past_records'), 'fa-folder'],
            'Other_Forms' => [trans('noshform.other_forms'), 'fa-file-o'],
            'Letters' => [trans('noshform.letters'), 'fa-file-text-o'],
            'Education' => [trans('noshform.education'), 'fa-info-circle'],
            'ccda' => [trans('noshform.ccda'), 'fa-file-code-o'],
            'ccr' => [trans('noshform.ccr'), 'fa-file-code-o'],
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('documents_list', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('2');
        $edit1 = $this->access_level('4');
        $columns = Schema::getColumnListing('documents');
        $row_index = $columns[0];
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                if (!empty($row->documents_type)) {
                    $arr['label'] = '<i class="fa ' . $type_arr[$row->documents_type][1] . ' fa-fw" style="margin-right:10px;"></i><b>' . date('Y-m-d', $this->human_to_unix($row->documents_date)) . '</b> - ' . $row->documents_desc . ' from ' . $row->documents_from;
                    $arr['view'] = route('document_view', [$row->$row_index]);
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['documents', $row_index, $row->$row_index]);
                        $arr['delete'] = route('chart_action', ['table' => 'documents', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                    if ($row->reconcile !== null && $row->reconcile !== 'y') {
                        $arr['danger'] = true;
                    }
                    $list_array[] = $arr;
                } else {
                    // Clean up orphaned entries
                    if (file_exists($row->documents_url)) {
                        unlink($row->documents_url);
                    }
                    DB::table('documents')->where($row_index, '=', $row->$row_index)->delete();
                    $this->audit('Delete');
                }
            }
            $return .= $this->result_build($list_array, 'documents_list', false, true);
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['documents_active'] = true;
        $data['panel_header'] = trans('noshform.documents');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.upload_document'),
                'icon' => 'fa-plus',
                'url' => route('document_upload')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.generate_letter'),
                'icon' => 'fa-pencil-square-o',
                'url' => route('document_letter')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_patient_education'),
                'icon' => 'fa-info-circle',
                'url' => route('encounter_education')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.upload_ccda'),
                'icon' => 'fa-upload',
                'url' => route('upload_ccda')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.upload_ccr'),
                'icon' => 'fa-upload',
                'url' => route('upload_ccr')
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        } elseif ($edit1 == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.upload_document'),
                'icon' => 'fa-plus',
                'url' => route('document_upload')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.upload_ccda'),
                'icon' => 'fa-upload',
                'url' => route('upload_ccda')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.upload_ccr'),
                'icon' => 'fa-upload',
                'url' => route('upload_ccr')
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function download_ccda(Request $request, $action, $hippa_id)
    {
        if (Session::has('download_ccda')) {
            Session::forget('download_ccda');
            $pid = Session::get('pid');
            $practice_id = Session::get('practice_id');
            $file_name = time() . '_ccda_' . $pid . '_' . Session::get('user_id') . ".xml";
            $file_path = public_path() . '/temp/' . $file_name;
            $ccda = $this->generate_ccda($hippa_id);
            File::put($file_path, $ccda);
            $headers = [
                'Set-Cookie' => 'fileDownload=true; path=/',
                'Content-Type' => 'text/xml',
                'Cache-Control' => 'max-age=60, must-revalidate',
                'Content-Disposition' => 'attachment; filename="' . $file_name . '"'
            ];
            return response()->download($file_path, $file_name, $headers);
        } else {
            Session::put('download_ccda', $request->fullUrl());
            return redirect()->route('records_list', ['release']);
        }
    }

    public function electronic_sign($action, $id, $pid, $subtype='')
    {
        if ($action == 'rx_list') {
            $data['medications_active'] = true;
            $data['panel_header'] = trans('noshform.sign_prescription');
            $index = 'rxl_id';
        }
        if ($action == 'orders') {
            $data['orders_active'] = true;
            $data['panel_header'] = trans('noshform.sign_order');
            $index = 'orders_id';
        }
        if ($action == 'immunizations') {
            $data['immunizations_active'] = true;
            $data['panel_header'] = trans('noshform.sign_immunization');
            $index = 'imm_id';
        }
        $raw = DB::table($action)->where($index, '=', $id)->first();
        $data['hash'] = hash('sha256', $raw->json);
        $data['ajax'] = route('electronic_sign_process', [$action, $index, $id]);
        $data['ajax1'] = route('electronic_sign_login');
        $data['ajax2'] = route('electronic_sign_gas');
        $data['uport_need'] = 'y';
        $data['uport_id'] = '';
        $data['url'] = '';
        if (Session::has('uport_id')) {
            if (Session::get('uport_id') !== '') {
                $data['uport_need'] = 'n';
                $data['uport_id'] = Session::get('uport_id');
            }
        }
        $data['content'] = '<p>' . trans('noshform.sign_prescription_text1') . '</p>';
        $data['content'] .= '<p>' . trans('noshform.sign_prescription_text2') . '</p>';
        $provider = DB::table('providers')->where('id', '=', $raw->id)->first();
        if ($provider) {
            if ($provider->npi == '1234567890') {
                // Google demo, skip uPort
                $user = DB::table('users')->where('id', '=', $provider->id)->first();
                $data['uport_need'] = 'google';
                $data['content'] .= '<p>The Google ID / OpenID Connect login standard cannot be used as a secure signature.</p><p><a href="https://youtu.be/OH6hsu4A4gE" target="_blank" class="nosh-no-load">Here is a video demonstration of using your smartphone with the uPort app to electronically sign a prescription:</a></p><div style="text-align: center;"><iframe width="560" height="315" src="https://www.youtube.com/embed/OH6hsu4A4gE" frameborder="0" allowfullscreen></iframe></div>';
                $data['content'] .= '<a href="' . route('electronic_sign_demo', [$action, $index, $id]) . '" class="btn btn-primary btn-block">Click here to continue demo as if legally signed as ' . $user->email . '</a>';
            }
        }
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('uport', $data);
    }

    public function electronic_sign_demo(Request $request, $table, $index, $id)
    {
        $message_arr = [
            'rx_list' => 'Prescription digitally signed',
            'orders' => 'Order digitally signed'
        ];
        $to = Session::get('prescription_notification_to');
        Session::forget('prescription_notification_to');
        $this->prescription_notification($id, $to);
        Session::put('message_action', $message_arr[$table]);
        Session::put('demo_comment', 'yes');
        return redirect()->route('medications_list', ['active']);
    }

    public function encounter(Request $request, $eid, $section='s')
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        Session::put('eid', $eid);
        Session::forget('eid_billing');
        $data['template_content'] = 'test';
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $encounter = DB::table('encounters')->where('eid', '=', $eid)->first();
        $user = DB::table('users')->where('id', '=', $encounter->user_id)->first();
        Session::put('encounter_DOS', $encounter->encounter_DOS);
        Session::put('encounter_template', $encounter->encounter_template);
        Session::put('encounter_provider', $user->displayname);
        $o_array = [
            'medical',
            'virtual',
            'standardmedical',
            'standardmedical1',
            'standardpsych',
            'standardpsych1',
            'standardmtm'
        ];
        $psych_array = [
            'standardpsych',
            'standardpsych1'
        ];
        $depreciated_array = [
            'standardmedical',
            'standardmedical1'
        ];
        // Tags
        $tags_relate = DB::table('tags_relate')->where('eid', '=', $eid)->get();
        $tags_val_arr = [];
        if ($tags_relate->count()) {
            foreach ($tags_relate as $tags_relate_row) {
                $tags = DB::table('tags')->where('tags_id', '=', $tags_relate_row->tags_id)->first();
                $tags_val_arr[] = $tags->tag;
            }
        }
        $return = '<div style="margin-bottom:15px;"><input type="text" id="encounter_tags" class="nosh-tags" value="' . implode(',', $tags_val_arr) . '" data-nosh-add-url="' . route('tag_save', ['eid', $eid]) . '" data-nosh-remove-url="' . route('tag_remove', ['eid', $eid]) . '" placeholder="' . trans('noshform.add_tags') . '"/></div>';
        $return .= '<ul class="nav nav-tabs"><li ';
        if ($section == 's') {
            $return .= 'class="active"';
        }
        $return .= '><a data-toggle="tab" href="#s" title="' . trans('noshform.subjective') . '" class="nosh-encounter_tab"><span style="margin-right:15px;">S</span></a></li>';
        if (in_array($encounter->encounter_template, $o_array)) {
            $return .= '<li ';
            if ($section == 'o') {
                $return .= 'class="active"';
            }
            $return .= '><a data-toggle="tab" href="#o" title="' . trans('noshform.objective') . '" class="nosh-encounter_tab"><span style="margin-right:15px;">O</span></a></li>';
        }
        $return .= '<li ';
        if ($section == 'a') {
            $return .= 'class="active"';
        }
        $return .= '><a data-toggle="tab" href="#a" title="' . trans('noshform.assessment') . '" class="nosh-encounter_tab"><span style="margin-right:15px;">A</span></a></li><li ';
        if ($section == 'p') {
            $return .= 'class="active"';
        }
        $return .= '><a data-toggle="tab" href="#p" title="' . trans('noshform.plan') . '" class="nosh-encounter_tab"><span style="margin-right:15px;">P</span></a></li></ul><div class="tab-content" style="margin-top:15px;">';
        // S
        $return .= '<div id="s" class="tab-pane fade';
        if ($section == 's') {
            $return .= ' in active';
        }
        $return .= '">';
        $cc_val = null;
        $hpi_val = null;
        $ros_val = null;
        $situation_val = null;
        $hpi = DB::table('hpi')->where('eid', '=', $eid)->first();
        $ros = DB::table('ros')->where('eid', '=', $eid)->first();
        if ($encounter->encounter_cc !== '') {
            $cc_val = $encounter->encounter_cc;
        }
        if ($hpi) {
            $hpi_val = $hpi->hpi;
            $situation_val = $hpi->situation;
        }
        if ($ros) {
            $ros_val = $ros->ros;
            // Convert depreciated encounter type to current
            if (in_array($encounter->encounter_template, $depreciated_array)) {
                $ros_arr = $this->array_ros();
                foreach ($ros_arr as $ros_k => $ros_v) {
                    if ($ros->{$ros_k} !== '' && $ros->{$ros_k} !== null) {
                        if ($ros_k !== 'ros') {
                            $ros_val .=  $ros_v . ': ';
                        }
                        $ros_val .= $ros->{$ros_k};
                        $ros_val .= "\n\n";
                    }
                }
            }
        }
        $s_items[] = [
            'name' => 'encounter_cc',
            'label' => trans('noshform.encounter_cc'),
            'type' => 'text',
            'required' => true,
            'typeahead' => route('typeahead', ['table' => 'encounters', 'column' => 'encounter_cc']),
            'default_value' => $cc_val
        ];
        if ($encounter->encounter_template == 'clinicalsupport') {
            $s_items[] = [
                'name' => 'situation',
                'label' => trans('noshform.situation'),
                'type' => 'textarea',
                'default_value' => $situation_val
            ];
        } else {
            $s_items[] = [
                'name' => 'hpi',
                'label' => trans('noshform.hpi'),
                'type' => 'textarea',
                'default_value' => $hpi_val
            ];
            $s_items[] = [
                'name' => 'ros',
                'label' => trans('noshform.ros'),
                'type' => 'textarea',
                'default_value' => $ros_val
            ];
        }
        if (in_array($encounter->encounter_template, $o_array)) {
            $s_form_array = [
                'form_id' => 's_form',
                'action' => route('encounter_save', [$eid, 'o']),
                'items' => $s_items,
                'save_button_label' => trans('noshform.save_next')
            ];
        } else {
            $s_form_array = [
                'form_id' => 's_form',
                'action' => route('encounter_save', [$eid, 'a']),
                'items' => $s_items,
                'save_button_label' => trans('noshform.save_next')
            ];
        }
        $return .= $this->form_build($s_form_array);
        $return .= '</div>';
        // O
        if (in_array($encounter->encounter_template, $o_array)) {
            $return .= '<div id="o" class="tab-pane fade';
            if ($section == 'o') {
                $return .= ' in active';
            }
            $return .= '">';
            if ($encounter->encounter_template !== 'virtual') {
                $vitals = DB::table('vitals')->where('eid', '=', $eid)->first();
                $vitals_list_array = [];
                if ($vitals) {
                    $vitals_arr['label'] = '<b>' . date('Y-m-d, g:i a', $this->human_to_unix($vitals->vitals_date)) . '</b> - ' . trans('noshform.vital_signs');
                    $vitals_columns = Schema::getColumnListing('vitals');
                    $vitals_include = $this->array_vitals();
                    $vitals_percent_arr = $this->array_vitals1();
                    $vitals_display_arr = [];
                    foreach ($vitals_include as $vitals_key=>$vitals_value) {
                        if (!empty($vitals->{$vitals_key})) {
                            if ($vitals_key !== 'bp_systolic' && $vitals_key !== 'bp_diastolic') {
                                $vitals_display_arr[] = '<b>' .$vitals_value['name'] . ':</b> ' . $vitals->{$vitals_key} . ' ' . $vitals_value['unit'];
                            } elseif ($vitals_value['name'] == 'SBP') {
                                $vitals_display_arr[] = '<b>BP:</b>' . $vitals->bp_systolic . '/' . $vitals->bp_diastolic . ' mmHg';
                            }
                        }
                    }
                    foreach ($vitals_percent_arr as $vitals_key1=>$vitals_value1) {
                        if (!empty($vitals->{$vitals_key1})) {
                            $vitals_display_arr[] = '<b>' .$vitals_value1 . ':</b> ' . $vitals->{$vitals_key1};
                        }
                    }
                    if (!empty($vitals_display_arr)) {
                        $vitals_arr['label'] .= '<p>';
                        $vitals_arr['label'] .= implode('; ', $vitals_display_arr);
                        $vitals_arr['label'] .= '</p>';
                    }
                    if ($vitals->vitals_other !== '') {
                        $vitals_arr['label'] .= '<p><b>Notes:</b>' . nl2br($vitals->vitals_other) .'</p>';
                    }
                    $vitals_arr['view'] = route('encounter_vitals_view', [$eid]);
                    $vitals_arr['edit'] = route('chart_form', ['vitals', 'eid', $eid]);
                    $vitals_arr['delete'] = route('chart_action', ['table' => 'vitals', 'action' => 'delete', 'index' => 'eid', 'id' => $eid]);
                    $vitals_list_array[] = $vitals_arr;
                } else {
                    $vitals_arr['label'] = '<b>' . trans('noshform.add') . ' ' . trans('noshform.vital_signs') . '</b>';
                    $vitals_arr['edit'] = route('chart_form', ['vitals', 'eid', '0']);
                    $vitals_arr['view'] = route('encounter_vitals_view');
                    $vitals_list_array[] = $vitals_arr;
                }
                $return .= $this->result_build($vitals_list_array, 'vitals_list', true);
            }
            if ($encounter->encounter_template !== 'standardmtm') {
                $images = DB::table('image')->where('eid', '=', Session::get('eid'))->get();
                if ($images->count()) {
                    $return .= '<div style="overflow:auto;padding-top:20px;"><div class="list-group gallery">';
                    foreach ($images as $image) {
                        $file_path1 = '/temp/' . time() . '_' . basename($image->image_location);
                        $file_path = public_path() . $file_path1;
                        copy($image->image_location, $file_path);
                        $return .= '<div class="col-sm-4 col-xs-6 col-md-3 col-lg-3"><a class="thumbnail fancybox nosh-no-load" rel="ligthbox" href="' . url('/') . $file_path1 . '">';
                        $return .= '<img class="img-responsive" alt="" src="' . url('/') . $file_path1 . '" />';
                        $return .= '<div class="text-center"><small class="text-muted">' . $image->image_description . '</small></div></a>';
                        $return .= '<a href="' . route('encounter_edit_image', [$image->image_id]) . '" class="edit-icon btn btn-success"><i class="glyphicon glyphicon-pencil"></i></a>';
                        $return .= '<a href="' . route('encounter_delete_photo', [$image->image_id]) . '" class="close-icon btn btn-danger"><i class="glyphicon glyphicon-remove"></i></a></div>';
                    }
                    $return .= '</div></div>';
                }
                $pe_val = null;
                $pe = DB::table('pe')->where('eid', '=', $eid)->first();
                if ($pe) {
                    $pe_val = $pe->pe;
                    // Convert depreciated encounter type to current
                    if (in_array($encounter->encounter_template, $depreciated_array)) {
                        $pe_arr = $this->array_pe();
                        foreach ($pe_arr as $pe_k => $pe_v) {
                            if ($pe->{$pe_k} !== '' && $pe->{$pe_k} !== null) {
                                if ($pe_k !== 'pe') {
                                    $pe_val .=  $pe_v . ': ';
                                }
                                $pe_val .= $pe->{$pe_k};
                                $pe_val .= "\n\n";
                            }
                        }
                    }
                }
                $o_items[] = [
                    'name' => 'pe',
                    'label' => trans('noshform.pe'),
                    'type' => 'textarea',
                    'default_value' => $pe_val
                ];
                $o_form_array = [
                    'form_id' => 'o_form',
                    'action' => route('encounter_save', [$eid, 'a']),
                    'items' => $o_items,
                    'save_button_label' => trans('noshform.save_next')
                ];
                $return .= $this->form_build($o_form_array);
            }
            $return .= '</div>';
        }
        // A
        $return .= '<div id="a" class="tab-pane fade';
        if ($section == 'a') {
            $return .= ' in active';
        }
        $return .= '">';
        $return .= '<div class="container-fluid panel-container"><form class="input-group form" border="0" id="search_icd_form" role="search" action="' . route('search_icd', [true]) . '" method="POST" style="margin-bottom:0px;" data-nosh-target="search_icd_results">';
        $return .= '<input type="text" class="form-control search" id="search_icd" name="search_icd" placeholder="' . trans('noshform.search_icd10') . '" style="margin-bottom:0px;" autocomplete="off"><span class="input-group-btn"><button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button></span>';
        $return .= '<span class="input-group-btn"><button type="submit" class="btn btn-md" id="search_icd_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button></span></form><div class="list-group" id="search_icd_results"></div></div>';
        $dxs = DB::table('assessment')->where('eid', '=', $eid)->first();
        $assessment_other_label = trans('noshform.additional_dx');
        $assessment_other_val = null;
        $assessment_ddx_label = trans('noshform.diff_dx');
        $assessment_ddx_val = null;
        $assessment_notes_label = trans('noshform.discuss_dx');
        $assessment_notes_val = null;
        if ($encounter->encounter_template == 'standardmtm') {
            $assessment_other_label = trans('noshform.soap_note');
            $assessment_ddx_label = trans('noshform.map2');
            $assessment_notes_label = trans('noshform.pharma_note');
        }
        if ($dxs) {
            $dx_array = [];
            $dx_pre_array = [];
            for ($j = 1; $j <= 12; $j++) {
                $col0 = 'assessment_' . $j;
                // GYN 20181006: Add ICD code to Assessment display
                $col1 = 'assessment_icd' . $j;
                if (!empty($dxs->{$col0})) {
                    $dx_pre_array[] = $j;
                }
            }
            if (! empty($dx_pre_array)) {
                $first_dx = $dx_pre_array[0];
                $last_dx = $dx_pre_array[count($dx_pre_array) - 1];
                foreach ($dx_pre_array as $dx_num) {
                    $arr = [];
                    $arr['label'] = '<strong>' . $dx_num . ':</strong> ' . $dxs->{'assessment_'.$dx_num};
                    // GYN 20181006: Add ICD code to assessment display
                    $arr['label'] .= ' [' . $dxs->{'assessment_icd'.$dx_num} . ']';
                    $arr['edit'] = route('encounter_assessment_edit', [$dx_num]);
                    $arr['delete'] = route('encounter_assessment_delete', [$dx_num]);
                    if ($dx_num !== $first_dx && $dx_num !== $last_dx) {
                        $arr['move_up'] = route('encounter_assessment_move', [$dx_num, 'up']);
                        $arr['move_down'] = route('encounter_assessment_move', [$dx_num, 'down']);
                    } else {
                        if ($dx_num === $first_dx) {
                            $arr['move_down'] = route('encounter_assessment_move', [$dx_num, 'down']);
                        }
                        if ($dx_num === $last_dx) {
                            $arr['move_up'] = route('encounter_assessment_move', [$dx_num, 'up']);
                        }
                    }
					// GYN 20181007: Add Assessment Copy to Problem List option
                    $arr['problem_list'] = route('encounter_assessment_copy', [$dx_num]);
                    $dx_array[] = $arr;
                }
                $return .= $this->result_build($dx_array, 'assessment_list');
            }
            $assessment_other_val = $dxs->assessment_other;
            $assessment_ddx_val = $dxs->assessment_ddx;
            $assessment_notes_val = $dxs->assessment_notes;
        }
        $a_items[] = [
            'name' => 'assessment_other',
            'label' => $assessment_other_label,
            'type' => 'textarea',
            'textarea_short' => true,
            'default_value' => $assessment_other_val
        ];
        $a_items[] = [
            'name' => 'assessment_ddx',
            'label' => $assessment_ddx_label,
            'type' => 'textarea',
            'textarea_short' => true,
            'default_value' => $assessment_ddx_val
        ];
        $a_items[] = [
            'name' => 'assessment_notes',
            'label' => $assessment_notes_label,
            'type' => 'textarea',
            'textarea_short' => true,
            'default_value' => $assessment_notes_val
        ];
        $a_form_array = [
            'form_id' => 'a_form',
            'action' => route('encounter_save', [$eid, 'p']),
            'items' => $a_items,
            'save_button_label' => trans('noshform.save_next')
        ];
        $return .= $this->form_build($a_form_array);
        $return .= '</div>';
        // P
        $return .= '<div id="p" class="tab-pane fade';
        if ($section == 'p') {
            $return .= ' in active';
        }
        $return .= '">';
        $return .= $this->actions_build('procedure', 'eid', $eid, 'proc_description');
        $ordersInfo1 = DB::table('orders')->where('eid', '=', $eid)->get();
        $orders_section = '';
        if ($ordersInfo1->count()) {
            $orders_section = '<div class="panel panel-default"><div class="panel-heading">' . trans('noshform.orders') . '</div><div class="panel-body"><p>';
            $orders_lab_array = [];
            $orders_radiology_array = [];
            $orders_cp_array = [];
            $orders_referrals_array = [];
            foreach ($ordersInfo1 as $ordersInfo) {
                $address_row1 = DB::table('addressbook')->where('address_id', '=', $ordersInfo->address_id)->first();
                if ($address_row1) {
                    $orders_displayname = $address_row1->displayname;
                } else {
                    $orders_displayname = trans('noshform.unknown');
                }
                if ($ordersInfo->orders_labs != '') {
                    $orders_lab_array[] = trans('noshform.orders_sent_to') . ' ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_labs) . '<br />';
                }
                if ($ordersInfo->orders_radiology != '') {
                    $orders_radiology_array[] = trans('noshform.orders_sent_to') . ' ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_radiology) . '<br />';
                }
                if ($ordersInfo->orders_cp != '') {
                    $orders_cp_array[] = trans('noshform.orders_sent_to') . ' ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_cp) . '<br />';
                }
                if ($ordersInfo->orders_referrals != '') {
                    $orders_referrals_array[] = trans('noshform.referral_sent_to') . ' ' . $orders_displayname . ': '. nl2br($ordersInfo->orders_referrals) . '<br />';
                }
            }
            if (! empty($orders_lab_array)) {
                $orders_section .= '<strong>' . trans('noshform.laboratory') . ': </strong>';
                foreach ($orders_lab_array as $lab_item) {
                    $orders_section .= $lab_item;
                }
            }
            if (! empty($orders_radiology_array)) {
                $orders_section .= '<strong>' . trans('noshform.imaging') . ': </strong>';
                foreach ($orders_radiology_array as $radiology_item) {
                    $orders_section .= $radiology_item;
                }
            }
            if (! empty($orders_cp_array)) {
                $orders_section .= '<strong>' . trans('noshform.cardiopulmonary') . ': </strong>';
                foreach ($orders_cp_array as $cp_item) {
                    $orders_section .= $cp_item;
                }
            }
            if (! empty($orders_referrals_array)) {
                $orders_section .= '<strong>' . trans('noshform.referrals') . ': </strong>';
                foreach ($orders_referrals_array as $referrals_item) {
                    $orders_section .= $referrals_item;
                }
            }
            $orders_section .= '</p></div></div>';
        }
        $return .= $orders_section;
        $rx_section = '';
        $rxInfo = DB::table('rx')->where('eid', '=', $eid)->first();
        if ($rxInfo) {
            $rx_arr = $this->array_rx();
            $rx_section = '<div class="panel panel-default"><div class="panel-heading">' . trans('noshform.prescriptions_imm') . ':</div><div class="panel-body"><p>';
            foreach ($rx_arr as $rx_k => $rx_v) {
                if ($rxInfo->{$rx_k} !== '' && $rxInfo->{$rx_k} !== null) {
                    $rx_section .= '<strong>' . $rx_v . ': </strong><br>';
                    $rx_section .= nl2br($rxInfo->{$rx_k});
                    if ($rx_k == 'rx_immunizations') {
                        $rx_section .= trans('noshform.imm_disclaimer') . '.<br />';
                    }
                    $rx_section .= '<br /><br />';
                }
            }
            $rx_section .= '</p></div></div>';
        }
        $return .= $rx_section;
        $plan_array = $this->array_plan();
        $plan_val = [];
        $plan = DB::table('plan')->where('eid', '=', $eid)->first();
        foreach ($plan_array as $plan_k => $plan_v) {
            if ($plan) {
                $plan_val[$plan_k] = $plan->{$plan_k};
            } else {
                $plan_val[$plan_k] = null;
            }
        }
        if (in_array($encounter->encounter_template, $psych_array)) {
            $p_items[] = [
                'name' => 'goals',
                'label' => $plan_array['goals'],
                'type' => 'textarea',
                'default_value' => $plan_val['goals']
            ];
            $p_items[] = [
                'name' => 'tp',
                'label' => $plan_array['tp'],
                'type' => 'textarea',
                'default_value' => $plan_val['tp']
            ];
        } else {
            $p_items[] = [
                'name' => 'plan',
                'label' => $plan_array['plan'],
                'type' => 'textarea',
                'default_value' => $plan_val['plan']
            ];
        }
        $p_items[] = [
            'name' => 'followup',
            'label' => $plan_array['followup'],
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => 'plan', 'column' => 'followup']),
            'default_value' => $plan_val['followup']
        ];
        $p_items[] = [
            'name' => 'duration',
            'label' => trans('noshform.duration2'),
            'type' => 'text',
            'typeahead' => route('typeahead', ['table' => 'plan', 'column' => 'duration']),
            'default_value' => $plan_val['duration']
        ];
        $p_form_array = [
            'form_id' => 'p_form',
            'action' => route('encounter_save', [$eid, 'd']),
            'items' => $p_items,
            'save_button_label' => trans('noshform.save')
        ];
        $return .= $this->form_build($p_form_array);
        $return .= '</div></div>';
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-check fa-lg fa-btn"></i> ' . trans('noshform.sign'),
            'default_button_text_url' => route('encounter_sign', [$eid])
        ];
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $dropdown_array1 = [
            'items_button_icon' => 'fa-bars'
        ];
        $items = [];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.details'),
            'icon' => 'fa-pencil',
            'url' => route('encounter_details', [$eid])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.preview'),
            'icon' => 'fa-search',
            'url' => route('encounter_view', [$eid])
        ];
        $items[] = [
            'type' => 'separator'
        ];
        if ($encounter->encounter_template !== 'virtual') {
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.add_anatomical_image'),
                'icon' => 'fa-smile-o',
                'url' => route('encounter_edit_image', ['0'])
            ];
        }
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_photo'),
            'icon' => 'fa-camera',
            'url' => route('encounter_add_photo', [$eid])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_procedure'),
            'icon' => 'fa-plus',
            'url' => route('action_edit', ['procedure', 'eid', $eid, 'new', 'proc_description']),
        ];
        $items[] = [
            'type' => 'separator'
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.prescribe_medication'),
            'icon' => 'fa-eyedropper',
            'url' => route('chart_form', ['rx_list', 'rxl_id', '0', 'prescribe'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.order_supplement'),
            'icon' => 'fa-tree',
            'url' => route('chart_form', ['sup_list', 'sup_id', '0', 'order'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_lab_order'),
            'icon' => 'fa-thumbs-o-up',
            'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_labs'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_imaging_order'),
            'icon' => 'fa-thumbs-o-up',
            'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_radiology'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_cardiopulmonary_order'),
            'icon' => 'fa-thumbs-o-up',
            'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_cp'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_referral'),
            'icon' => 'fa-thumbs-o-up',
            'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_referrals'])
        ];
        $items[] = [
            'type' => 'separator'
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_patient_education'),
            'icon' => 'fa-info-circle',
            'url' => route('encounter_education')
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.billing'),
            'icon' => 'fa-money',
            'url' => route('encounter_billing', [$eid])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.print_plan'),
            'icon' => 'fa-print',
            'url' => route('encounter_print_plan', [$eid])
        ];
        if ($encounter->encounter_template == 'standardmtm') {
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.print_mtm'),
                'icon' => 'fa-print',
                'url' => route('encounter_print_mtm', [$eid])
            ];
        }
        $items[] = [
            'type' => 'separator'
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.close'),
            'icon' => 'fa-times',
            'url' => route('encounter_close')
        ];
        $dropdown_array1['items'] = $items;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        $data['encounters_active'] = true;
        $data['panel_header'] = trans('noshform.encounter') . ' - ' .  date('Y-m-d', $this->human_to_unix($encounter->encounter_DOS));
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        Session::put('last_page_encounter', $request->fullUrl());
        Session::put('action_redirect',  $request->fullUrl());
        if (Session::has('download_now')) {
            $data['download_now'] = route('download_now');
        }
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function encounter_addendum(Request $request, $eid)
    {
        $encounter = DB::table('encounters')->where('eid', '=', $eid)->first();
        $data = (array) $encounter;
        unset($data['eid']);
        unset($data['encounter_signed']);
        $data['encounter_signed'] = 'No';
        $new_eid = DB::table('encounters')->insertGetId($data, 'eid');
        $this->audit('Add');
        $data1['addendum'] = 'y';
        DB::table('encounters')->where('eid', '=', $eid)->update($data1);
        $this->audit('Update');
        if ($encounter->encounter_template == 'standardmedical' || $encounter->encounter_template == 'standardmedical1' || $encounter->encounter_template == 'medical') {
            $table_array1 = ["hpi", "ros", "vitals", "pe", "labs", "procedure", "rx", "assessment", "plan"];
            $table_array2 = ["other_history", "orders", "billing", "billing_core", "image"];
        }
        if ($encounter->encounter_template == 'clinicalsupport' || $encounter->encounter_template == 'phone' || $encounter->encounter_template == 'virtual') {
            $table_array1 = ["hpi", "labs", "procedure", "rx", "assessment", "plan"];
            $table_array2 = ["other_history", "orders", "billing", "billing_core", "image"];
        }
        if ($encounter->encounter_template == 'standardpsych' || $encounter->encounter_template == 'standardpsych1') {
            $table_array1 = ["hpi", "ros", "vitals", "pe", "rx", "assessment", "plan"];
            $table_array2 = ["other_history", "orders", "billing", "billing_core", "image"];
        }
        if ($encounter->encounter_template == 'standardmtm') {
            $table_array1 = ["hpi", "vitals", "assessment", "plan"];
            $table_array2 = ["other_history", "orders", "billing", "billing_core", "image"];
        }
        foreach($table_array1 as $table1) {
            $table_query1 = DB::table($table1)->where('eid', '=', $eid)->first();
            if ($table_query1) {
                $data2 = (array) $table_query1;
                unset($data2['eid']);
                $data2['eid'] = $new_eid;
                DB::table($table1)->insert($data2);
                $this->audit('Add');
                // $this->api_data('add', $table1, 'eid', $new_eid);
            }
        }
        foreach($table_array2 as $table2) {
            $table_query2 = DB::table($table2)->where('eid', '=', $eid)->get();
            if ($table_query2->count()) {
                if ($table2 == 'other_history') {
                    $primary = 'oh_id';
                }
                if ($table2 == 'orders') {
                    $primary = 'orders_id';
                }
                if ($table2 == 'billing') {
                    $primary = 'bill_id';
                }
                if ($table2 == 'billing_core') {
                    $primary = 'billing_core_id';
                }
                if ($table2 == 'image') {
                    $primary = 'image_id';
                }
                foreach ($table_query2 as $table_row) {
                    $data3 = (array) $table_row;
                    unset($data3['eid']);
                    unset($data3[$primary]);
                    $data3['eid'] = $new_eid;
                    DB::table($table2)->insert($data3);
                    $this->audit('Add');
                    // $this->api_data('add', $table2, 'eid', $new_eid);
                }
            }
        }
        // Session::put('encounter_template', $encounter->encounter_template);
        // Session::put('encounter_DOS', $encounter->encounter_DOS);
        if (Session::has('encounter_redirect')) {
            $redirect_url = Session::get('encounter_redirect');
            Session::forget('encounter_redirect');
            return redirect($redirect_url);
        } else {
            return redirect()->route('encounter', [$new_eid]);
        }
    }

    public function encounter_add_photo(Request $request, $eid, $type='')
    {
        if ($request->isMethod('post')) {
            $pid = Session::get('pid');
            $directory = Storage::path($pid);
            $file = $request->file('file_input');
            $new_name = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $new_name);
            $file_path = $directory . "/" . $new_name;
            App::setLocale(Session::get('practice_locale'));
            $data = [
                'image_location' => $file_path,
                'pid' => $pid,
                'image_description' => trans('noshform.photo_uploaded') . ' ' . date('F jS, Y'),
                'id' => Session::get('user_id'),
                'encounter_provider' => Session::get('displayname')
            ];
            App::setLocale(Session::get('user_locale'));
            if ($type == '') {
                $data['eid'] = $eid;
            } else {
                $data['t_messages_id'] = $eid;
            }
            $image_id = DB::table('image')->insertGetId($data, 'image_id');
            $this->audit('Add');
            if ($type == '') {
                return redirect()->route('encounter_edit_image', [$image_id]);
            } else {
                return redirect()->route('encounter_edit_image', [$image_id, $eid]);
            }
        } else {
            $data['encounters_active'] = true;
            $data['panel_header'] = trans('noshform.add_photo');
            if ($type == '') {
                $data['document_upload'] = route('encounter_add_photo', [$eid]);
            } else {
                $data['document_upload'] = route('encounter_add_photo', [$eid, $type]);
            }
            $type_arr = ['png', 'jpg'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>Back';
            if ($type == '') {
                $dropdown_array['default_button_text_url'] = Session::get('last_page');
            } else {
                $dropdown_array['default_button_text_url'] = Session::get('t_messages_photo_last_page');
            }
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('document_upload', $data);
        }
    }

    public function encounter_assessment_add(Request $request, $type, $id)
    {
        $query = DB::table('assessment')->where('eid', '=', Session::get('eid'))->first();
        $copy_arr = [];
        $message_some = '';
        if ($type == 'issue') {
            if ($id == 'pl' || $id == 'mh' || $id == 'sh') {
                $arr = [
                    'pl' => 'Problem List',
                    'mh' => 'Medical History',
                    'sh' => 'Surgical History'
                ];
                $issues = DB::table('issues')->where('type', '=', $arr[$id])->whereNull('issue_date_inactive')->get();
                if ($issues->count()) {
                    foreach ($issues as $issue) {
                        $issue_arr = explode('[', $issue->issue);
                        if (count($issue_arr) == 1) {
                            $message_some = '  ' . trans('noshform.incorrect_format');
                        } else {
                            $copy_arr[] = [
                                'desc' => rtrim($issue_arr[0]),
                                'icd' => str_replace(']', '', $issue_arr[1])
                            ];
                        }
                    }
                } else {
                    Session::put('message_action', trans('noshform.error') . ' - ' . $arr[$id] . ' ' . trans('noshform.is_empty') . '.');
                    return redirect(Session::get('last_page'));
                }
            } else {
                $issue = DB::table('issues')->where('issue_id', '=', $id)->first();
                $issue_arr = explode('[', $issue->issue);
                if (count($issue_arr) == 1) {
                    Session::put('message_action', trans('noshform.error') . ' - ' . trans('noshform.incorrect_format1') . '.');
                    return redirect(Session::get('last_page'));
                } else {
                    $copy_arr[] = [
                        'desc' => rtrim($issue_arr[0]),
                        'icd' => str_replace(']', '', $issue_arr[1])
                    ];
                }
            }
        } else {
            // Get from ICD10
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
            $copy_arr[] = [
                'desc' => $preicd[$id]['desc'],
                'icd' => $id
            ];
        }
        // Get first empty assessment item
        $id = '1';
        if ($query) {
            for ($i = 1; $i <= 12; $i++) {
                $item = 'assessment_' . $i;
                if ($query->{$item} == '') {
                    break;
                }
            }
            $id = $i;
        }
        foreach ($copy_arr as $copy_item) {
            //for ($j = $id; $j <= 12; $j++) {
            $query1 = DB::table('assessment')->where('eid', '=', Session::get('eid'))->first();
            if ($id <= 12) {
                $data1 = [
                    'assessment_' . $id => $copy_item['desc'],
                    'assessment_icd' . $id => $copy_item['icd']
                ];
            } else {
                if ($query1) {
                    $other = $query1->assessment_other . "\n" . $copy_item['desc'];
                } else {
                    $other = $copy_item['desc'];
                }
                $data1['assessment_other'] = $other;
            }
            if ($query1) {
                $data1['assessment_date'] = date("Y-m-d H:i:s", time());
                DB::table('assessment')->where('eid', '=', Session::get('eid'))->update($data1);
                $this->audit('Update');
            } else {
                $data1['eid'] = Session::get('eid');
                $data1['pid'] = Session::get('pid');
                $data1['encounter_provider'] = Session::get('displayname');
                DB::table('assessment')->where('eid', '=', Session::get('eid'))->insert($data1);
                $this->audit('Add');
            }
            $id++;
        }
        Session::put('message_action', trans('noshform.assessment_updated') . '.' . $message_some);
        return redirect(Session::get('last_page'));
    }

    public function encounter_assessment_copy(Request $request, $id)
 		/**
		 * Copy Encounter Assessment item to Problem List.
		 *
		 * Selected assessment will be added to the problem list via the "issues" table.
		 * If the issue (assessment) already exists in the table it will be "activated in place", if
		 * it is not active. "Activated in place" means that its type will not be changed from
		 * "Medical History" or "Surgical History" to "Problem List", just activated.
		 *
		 * @since x.x.x
		 *
		 * @link https://www.dropbox.com/s/3g4pc9ggoj1016d/Encounter%20View-%20Edit%20Assessment%20Doesn%27t%20update%20assessment_icd.pdf?dl=0
		 * @global Lavavel helper Session.
		 * @global Laravel helper DB.
		 *
		 * @param Request $request Ignored.
		 * @param String $id Assessment ID# (builds column names in "assessment" table).
		 * @return redirect page
		 */
   {
        $query = DB::table('assessment')->where('eid', '=', Session::get('eid'))->first();
        $assess_desc_key = 'assessment_' . $id;
        $assess_icd_key = 'assessment_icd' . $id;

		// Check for existing issue by getting existing issues in table
		$issue_query = DB::table('issues')->where('pid', '=', $query->{'pid'})->get() ;
		// If query succeeds with results
		if (isset($issue_query) && $issue_query->count()) {
			foreach ($issue_query as $issues_row) {
				// Check for match
				// Because 'issue' column is a mashup of text description and ICD code
				// we need to parse it
				$issue = explode( '[', $issues_row->issue); // $issue[0] is Text Description
				$issue_icd =  (isset($issue[1])) ? explode( ']', $issue[1])[0] : '' ;
				if ($issue_icd == $query->{$assess_icd_key}) {
					// Found a matching ICD code
					$message = trans('noshform.already_active');
					// Is it active
					if ($issues_row->issue_date_inactive != null) {
						// Yes, then activate it
						$issue_data = [
							'issue_date_active' => date("Y-m-d"),
							'issue_date_inactive' => null,
							'issue_provider' => Session::get('displayname')
						];
						$message = trans('noshform.activated');
						DB::table('issues')->where('issue_id', '=', $issues_row->issue_id)->update($issue_data);
						$this->audit('Update');
					}
			        Session::put('message_action', trans('noshform.assessment') . ' ' . $message . ' ' . trans('noshform.in1') . ' ' . $issues_row->type);
			        return redirect(Session::get('last_page'));
				}
			}
		}
		// Assessment not found, so need to add it to 'issues' table
		$issue_data = [
			'pid' => $query->{'pid'},
			'issue' => $query->{$assess_desc_key} . ' [' . $query->{$assess_icd_key} . ']',
			'issue_date_active' => date("Y-m-d"),
			'issue_date_inactive' => null,
			'issue_provider' => Session::get('displayname'),
			'rcopia_sync' => 'n',
			'type' => "Problem List",
			'reconcile' => 'y',
			'notes' => ''
		];
		DB::table('issues')->insert($issue_data);
		$this->audit('Add');
        Session::put('message_action', trans('noshform.encounter_assessment_copy'));
        return redirect(Session::get('last_page'));
	}

    public function encounter_assessment_delete(Request $request, $id)
    {
        $data = [
            'assessment_' . $id => '',
            'assessment_icd' . $id => '',
            'assessment_date' => date("Y-m-d H:i:s", time())
        ];
        DB::table('assessment')->where('eid', '=', Session::get('eid'))->update($data);
        $this->audit('Update');
        // Move up next assessments if any
        $query = DB::table('assessment')->where('eid', '=', Session::get('eid'))->first();
        $start = $id + 1;
        $cur_id = $id;
        for ($i = $start; $i <= 12; $i++) {
            $item = 'assessment_' . $i;
            $item1 = 'assessment_icd' . $i;
            if ($query->{$item} !== '') {
                $data1 = [
                    'assessment_' . $cur_id => $query->{$item},
                    'assessment_icd' . $cur_id => $query->{$item1},
                    'assessment_date' => date("Y-m-d H:i:s", time())
                ];
                DB::table('assessment')->where('eid', '=', Session::get('eid'))->update($data1);
                $this->audit('Update');
            } else {
                // Must be last item; delete it
                $data2 = [
                    'assessment_' . $cur_id => '',
                    'assessment_icd' . $cur_id => '',
                    'assessment_date' => date("Y-m-d H:i:s", time())
                ];
                DB::table('assessment')->where('eid', '=', Session::get('eid'))->update($data2);
                $this->audit('Update');
            }
            $cur_id++;
        }
        return redirect(Session::get('last_page'));
    }

    public function encounter_assessment_edit(Request $request, $id)
    {
        $query = DB::table('assessment')->where('eid', '=', Session::get('eid'))->first();
        if ($request->isMethod('post')) {
            $data1 = [
                'assessment_' . $id => $request->input('assessment_' . $id),
                'assessment_icd' . $id => $request->input('assessment_icd' . $id)
            ];
            if ($query) {
                $data1['assessment_date'] = date("Y-m-d H:i:s", time());
                DB::table('assessment')->where('eid', '=', Session::get('eid'))->update($data1);
                $this->audit('Update');
                Session::put('message_action', trans('noshform.assessment_updated'));
            } else {
                $data1['eid'] = Session::get('eid');
                $data1['pid'] = Session::get('pid');
                $data1['encounter_provider'] = Session::get('displayname');
                DB::table('assessment')->where('eid', '=', Session::get('eid'))-insert($data1);
                $this->audit('Add');
                Session::put('message_action', trans('noshform.assessment_added'));
            }
            return redirect(Session::get('last_page'));
        } else {
            if ($id == '0') {
                $data['panel_header'] = trans('noshform.add_assessment');
                // Get first empty assessment item
                $id = '1';
                if ($query) {
                    for ($i = 1; $i <= 12; $i++) {
                        $item = 'assessment_' . $i;
                        if ($query->{$item} == '') {
                            break;
                        }
                    }
                    $id = $i;
                }
                $default_value = null;
                $default_value1 = null;
            } else {
                $data['panel_header'] = trans('noshform.edit_assessment');
                $item1 = 'assessment_' . $id;
                $item2 = 'assessment_icd' . $id;
                $default_value = $query->{$item1};
                $default_value1 = $query->{$item2};
            }
            $items[] = [
                'name' => 'assessment_' . $id,
                'label' => trans('noshform.assessment') . ' #' . $id,
                'type' => 'text',
                'required' => true,
                'default_value' => $default_value
            ];
            $items[] = [
                'name' => 'assessment_icd' . $id,
                'label' => trans('noshform.assessment_icd'),
                'type' => 'text',
                'required' => true,
                'readonly' => true,
                'default_value' => $default_value1
            ];
            $form_array = [
                'form_id' => 'assessment_form',
                'action' => route('encounter_assessment_edit', [$id]),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            $data['content'] = $this->form_build($form_array);
            $data['encounters_active'] = true;
            $data['search_icd'] = 'assessment_' . $id;
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function encounter_assessment_move(Request $request, $id, $direction)
    {
        $query = DB::table('assessment')->where('eid', '=', Session::get('eid'))->first();
        $orig_key = 'assessment_' . $id;
        $orig_key1 = 'assessment_icd' . $id;
        if ($direction == 'down') {
            $new_id = $id + 1;
            $new_key = 'assessment_' . $new_id;
            $new_key1 = 'assessment_icd' . $new_id;

        } else {
            $new_id = $id - 1;
            $new_key = 'assessment_' . $new_id;
            $new_key1 = 'assessment_icd' . $new_id;
        }
        $data = [
            'assessment_' . $new_id => $query->{$orig_key},
            'assessment_icd' . $new_id => $query->{$orig_key1},
            'assessment_' . $id => $query->{$new_key},
            'assessment_icd' . $id => $query->{$new_key1}
        ];
        $data['assessment_date'] = date("Y-m-d H:i:s", time());
        DB::table('assessment')->where('eid', '=', Session::get('eid'))->update($data);
        $this->audit('Update');
        Session::put('message_action', trans('noshform.assessment_updated'));
        return redirect(Session::get('last_page'));
    }

    public function encounter_billing(Request $request, $eid, $section='what')
    {
        if ($request->isMethod('post')) {
            // Make sure insurance 2 is not the same as insurance 1, otherwise, remove it
            $insurance_id_2 = $request->input('insurance_id_2');
            if ($request->input('insurance_id_2') == $request->input('insurance_id_1')) {
                $insurance_id_2 = '';
            }
            // If CPT code is already entered for encounter
            $billing_core = DB::table('billing_core')->where('eid', '=', $eid)->first();
            if ($billing_core) {
                $result = $this->billing_save_common($request->input('insurance_id_1'), $insurance_id_2, $eid);
                Session::put('message_action', $result);
                return redirect(route('encounter_billing', [$eid, 'action']) . '#action');
            } else {
                Session::get('message_action', 'Error - You need to enter at least one CPT code first');
                return redirect(route('encounter_billing', [$eid, 'what']) . '#what');
            }
        } else {
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $encounter = DB::table('encounters')->where('eid', '=', $eid)->first();
            $billing = DB::table('billing')->where('eid', '=', $eid)->first();
            $insurance_result = DB::table('insurance')->where('pid', '=', Session::get('pid'))->where('insurance_plan_active', '=', 'Yes')->get();
            $insurance_arr['0'] = 'No Insurance';
            if ($insurance_result->count()) {
                foreach ($insurance_result as $insurance_item) {
                    $insurance_arr[$insurance_item->insurance_id] = $insurance_item->insurance_plan_name;
                }
            }
            $insurance_id_1 = null;
            $insurance_id_2 = null;
            if ($billing) {
                $insurance_id_1 = $billing->insurance_id_1;
                $insurance_id_2 = $billing->insurance_id_2;
            }
            $return = '<ul class="nav nav-tabs"><li class="active"><a data-toggle="tab" href="#what" title="' . trans('noshform.what_to_bill') . '"><span>' . trans('noshform.what') . '</span></a></li><li><a data-toggle="tab" href="#who" title="' . trans('noshform.who_to_bill') . '"><span>' . trans('noshform.who') . '</span></a></li><li><a data-toggle="tab" href="#action" title="' . trans('noshform.action') . '"><span>' .trans('noshform.active') . '</span></a></li></ul><div class="tab-content" style="margin-top:15px;">';
            // What to Bill
            $return .= '<div id="what" class="tab-pane fade';
            if ($section == 'what') {
                $return .= ' in active';
            }
            $return .= '">';
            $cpt_array = [];
            $cpt_codes = DB::table('billing_core')
                ->join('cpt_relate', 'billing_core.cpt', '=', 'cpt_relate.cpt')
                ->where('billing_core.eid', '=', $eid)
                ->where('cpt_relate.practice_id', '=', Session::get('practice_id'))
                ->select('billing_core.*', 'cpt_relate.cpt_description')
                ->distinct()
                ->get();
            $columns = Schema::getColumnListing('billing_core');
            $row_index = $columns[0];
            $dx_pointer_arr = $this->array_assessment_billing($eid);
            if (empty($dx_pointer_arr)) {
                Session::put('message_action', trans('noshform.error') . ' - ' . trans('noshform.assessment_encounter_first'));
                return redirect()->route('encounter', [$eid, 'a']);
            }
            $cpt_array[] = [
                'label' => '<b>' . trans('noshform.add_procedure_code') . '</b>',
                'edit' => route('chart_form', ['billing_core', $row_index, '0'])
            ];
            if ($cpt_codes->count()) {
                foreach ($cpt_codes as $cpt_code) {
                    $arr = [];
                    $label = '<b>' . $cpt_code->cpt . ':</b>' . $cpt_code->cpt_description;
                    if ($cpt_code->icd_pointer !== '' || $cpt_code->icd_pointer !== null) {
                        $label .= '<br><br><b>' . trans('noshform.associated_diagnoses') . ':</b><ul>';
                        $dx_arr = str_split($cpt_code->icd_pointer);
                        foreach ($dx_arr as $dx) {
                            if (isset($dx_pointer_arr[$dx])) {
                                $label .= '<li>' . $dx_pointer_arr[$dx] . '</li>';
                            }
                        }
                        $label .= '</ul>';
                    }
                    $arr['label'] = $label;
                    $arr['edit'] = route('chart_form', ['billing_core', $row_index, $cpt_code->$row_index]);
                    $arr['delete'] = route('chart_action', ['table' => 'billing_core', 'action' => 'delete', 'index' => $row_index, 'id' => $cpt_code->$row_index]);
                    $cpt_array[] = $arr;
                }
            }
            $return .= $this->result_build($cpt_array, 'cpt_list');
            $return .= '</div>';
            // Who to bill
            $return .= '<div id="who" class="tab-pane fade';
            $return .= '">';
            $items1[] = [
                'name' => 'insurance_id_1',
                'label' => trans('noshform.primary_insurance'),
                'type' => 'select',
                'required' => true,
                'select_items' => $insurance_arr,
                'default_value' => $insurance_id_1
            ];
            $items1[] = [
                'name' => 'insurance_id_2',
                'label' => trans('noshform.secondary_insurance'),
                'type' => 'select',
                'required' => true,
                'select_items' => $insurance_arr,
                'default_value' => $insurance_id_2
            ];
            $form_array1 = [
                'form_id' => 'billing_accordion_1_form',
                'action' => route('encounter_billing', [$eid]),
                'items' => $items1,
                'save_button_label' => trans('noshform.save')
            ];
            $return .= $this->form_build($form_array1);
            $return .= '</div>';
            // Action
            $return .= '<div id="action" class="tab-pane fade';
            if ($section == 'action') {
                $return .= ' in active';
            }
            $return .= '">';
            if ($insurance_id_1 !== null) {
                $return .= '<a href="' . route('print_invoice1', [$eid, $insurance_id_1, $insurance_id_2]) . '" class="btn btn-default btn-block nosh-no-load" role="button"><i class="fa fa-btn fa-print"></i>' . trans('noshform.print_invoice') . '</a>';
                if ($insurance_id_1 !== '0') {
                    $return .= '<a href="' . route('generate_hcfa1', [$eid, $insurance_id_1, $insurance_id_2]) . '" class="btn btn-default btn-block nosh-no-load" role="button"><i class="fa fa-btn fa-print"></i>' . trans('noshform.print_hcfa') . '</a>';
                }
            }
            $return .= '</div>';
            $return .= '</div>';
            $dropdown_array = [
                'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back'),
                'default_button_text_url' => Session::get('last_page')
            ];
            if (Session::has('last_page_encounter')) {
                $url = parse_url(Session::get('last_page_encounter'));
                $path_components = explode('/', $url['path']);
                $last_eid = end($path_components);
                if ($last_eid == $eid) {
                    $dropdown_array['default_button_text_url'] = Session::get('last_page_encounter');
                    $data['encounters_active'] = true;
                } else {
                    $data['billing_active'] = true;
                }
            } else {
                $data['billing_active'] = true;
            }
            $items = [];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.add_procedure_code1'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['billing_core', $row_index, '0'])
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.sign'),
                'icon' => 'fa-check',
                'url' => route('encounter_sign', [$eid])
            ];
            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data['content'] = $return;
            $data['panel_header'] = trans('noshform.encounter') . ' - ' .  date('Y-m-d', $this->human_to_unix($encounter->encounter_DOS));
            $data = array_merge($data, $this->sidebar_build('chart'));
            Session::put('billing_last_page', $request->fullUrl());
            Session::put('eid_billing', $eid);
            // if (!Session::has('eid')) {
            //     Session::put('eid_billing', $eid);
            // } else {
            //     if (Session::get('eid') !== $eid) {
            //         Session::put('eid_billing', $eid);
            //     }
            // }
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function encounter_close(Request $request)
    {
        Session::forget('eid');
        return redirect()->route('patient');
    }

    public function encounter_delete_photo(Request $request, $id)
    {
        DB::table('image')->where('image_id', '=', $id)->delete();
        $this->audit('Delete');
        return redirect(Session::get('last_page'));
    }

    public function encounter_details(Request $request, $eid)
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        if ($request->isMethod('post')) {
            $appt_id = "";
            $encounter_type = "";
            if ($request->has('encounter_type')) {
                if ($request->input('encounter_type') != '') {
                    $encounter_type_arr = explode(",", $request->input('encounter_type'));
                    $encounter_type_arr1 = array_slice($encounter_type_arr, 0, -1);
                    $encounter_type = implode(",", $encounter_type_arr1);
                    $appt_id = end($encounter_type_arr);
                }
            }
            $user_id = $request->input('encounter_provider');
            $user_query = DB::table('users')->where('id', '=', $user_id)->first();
            $data_add = [
                'pid' => Session::get('pid'),
                'appt_id' => $appt_id,
                'encounter_age' => Session::get('age'),
                'encounter_type' => $encounter_type,
                'encounter_signed' => 'No',
                'addendum' => 'n',
                'user_id' => $user_id,
                'practice_id' => Session::get('practice_id'),
            ];
            $data = $request->post();
            if ($request->has('encounter_type')) {
                unset($data['encounter_type']);
            }
            unset($data['_token']);
            $data['encounter_provider'] = $user_query->displayname;
            $data['encounter_DOS'] = date('Y-m-d H:i:s', strtotime($data['encounter_DOS']));
            if (isset($data['label'])) {
                $data['label'] = implode(";", $data['label']);
            } else {
                $data['label'] = '';
            }
            $data = array_merge($data, $data_add);
            if ($eid == '0') {
                $eid = DB::table('encounters')->insertGetId($data, 'eid');
                $this->audit('Add');
                // $this->api_data('add', 'encounters', 'eid', $eid);
                $data2['status'] = 'Attended';
                if ($appt_id != '') {
                    DB::table('schedule')->where('appt_id', '=', $appt_id)->update($data2);
                    $this->audit('Update');
                }
                $data3['addendum_eid'] = $eid;
                DB::table('encounters')->where('eid', '=', $eid)->update($data3);
                $this->audit('Update');
                // $this->api_data('update', 'encounters', 'eid', $eid);
                Session::put('message_action', 'Encounter created.');
                if (Session::has('encounter_redirect')) {
                    Session::put('eid', $eid);
                    Session::forget('eid_billing');
                    $redirect_url = Session::get('encounter_redirect');
                    Session::forget('encounter_redirect');
                    return redirect($redirect_url);
                } else {
                    return redirect()->route('encounter', [$eid]);
                }
            } else {
                DB::table('encounters')->where('eid', '=', $eid)->update($data);
                $this->audit('Update');
                // $this->api_data('update', 'encounters', 'eid', $eid);
                Session::put('message_action', trans('noshform.encounter_details_updated'));
                return redirect(Session::get('last_page'));
            }
            if (isset($data['label'])) {
                if ($data['label'] !== '') {
                    $label_arr = explode(';', $data['label']);
                    $name = 'Encounter from Trustee';
                    $icon = 'https://cloud.noshchartingsystem.com/i-medical-records.png';
                    $scopes = [
                        URL::to('/') . '/fhir/Encounter/eid_' . $eid,
                        'view',
                        'edit'
                    ];
                    foreach ($label_arr as $label) {
                        $scopes[] = $label;
                    }
                    $this->uma_resource($scopes, $name, $icon);
                }
            }
        } else {
            if ($eid == '0') {
                $data['panel_header'] = trans('noshform.new_encounter');
                $encounter = [
                    'encounter_provider' => null,
                    'encounter_template' => $practice->encounter_template,
                    'encounter_DOS' => date('Y-m-d h:i A'),
                    'encounter_location' => $practice->default_pos_id,
                    'encounter_type' => null,
                    'encounter_role' => 'Primary Care Provider',
                    'bill_complex' => null,
                    'referring_provider' => null,
                    'referring_provider_npi' => null,
                    'encounter_condition_work' => 'No',
                    'encounter_condition_auto' => 'No',
                    'encounter_condition_auto_country' => $practice->country,
                    'encounter_condition_auto_state' => null,
                    'encounter_condition_other' => 'No',
                    'encounter_condition' => null,
                    'label' => null
                ];
                if (Session::get('group_id') == '2') {
                    $encounter['encounter_provider'] = Session::get('user_id');
                    if (Session::has('encounter_details')) {
                        $details = Session::get('encounter_details');
                        Session::forget('encounter_details');
                        foreach ($details as $detail_k=>$detail_v) {
                            $encounter[$detail_k] = $detail_v;
                        }
                    }
                }
            } else {
                $result = DB::table('encounters')->where('eid', '=', $eid)->first();
                $label = [];
                if ($result->label !== '' || $result->label !== null)  {
                    $label = explode(";", $result->label);
                }
                $encounter = [
                    'encounter_provider' => $result->encounter_provider,
                    'encounter_template' => $result->encounter_template,
                    'encounter_DOS' => date('Y-m-d h:i A', $this->human_to_unix($result->encounter_DOS)),
                    'encounter_location' => $result->encounter_location,
                    'encounter_type' => $result->encounter_type,
                    'encounter_role' => $result->encounter_role,
                    'bill_complex' => $result->bill_complex,
                    'referring_provider' => $result->referring_provider,
                    'referring_provider_npi' => $result->referring_provider_npi,
                    'encounter_condition_work' => $result->encounter_condition_work,
                    'encounter_condition_auto' => $result->encounter_condition_auto,
                    'encounter_condition_auto_country' => $result->encounter_condition_auto_country,
                    'encounter_condition_auto_state' => $result->encounter_condition_auto_state,
                    'encounter_condition_other' => $result->encounter_condition_other,
                    'encounter_condition' => $result->encounter_condition,
                    'label' => $label
                ];
                $data['panel_header'] = trans('noshform.encounter_details');
            }
            $provider_arr = $this->array_providers();
            $encounter_type_arr = $this->array_encounter_type();
            if ($eid == '0') {
                // Remove depreciated encounter types for new encounters
                unset($encounter_type_arr['standardmedical']);
                unset($encounter_type_arr['standardmedical1']);
            }
            $encounter_location_arr = $this->array_pos();
            $encounter_complex_arr = [
                '' => '',
                'Low Complexity' => trans('noshform.low_complex'),
                'Medium Complexity' => trans('noshform.med_complex'),
                'High Complexity' => trans('noshform.high_complex')
            ];
            $encounter_role_arr = [
                '' => trans('noshform.choose_provider_role'),
                'Primary Care Provider' => trans('noshform.primary_care_provider'),
                'Consulting Provider' => trans('noshform.consulting_provider'),
                'Referring Provider' => trans('noshform.referring_provider')
            ];
            $encounter_select_arr = [
                '' => '',
                'No' => trans('noshform.no'),
                'Yes' => trans('noshform.yes')
            ];
            $encounter_auto_state_arr = $this->array_states($encounter['encounter_condition_auto_country']);
            $items[] = [
                'name' => 'encounter_provider',
                'label' => trans('noshform.provider'),
                'type' => 'select',
                'select_items' => $provider_arr,
                'required' => true,
                'default_value' => $encounter['encounter_provider']
            ];
            if ($eid == '0') {
                $items[] = [
                    'name' => 'encounter_cc',
                    'label' => trans('noshform.encounter_cc'),
                    'type' => 'text',
                    'typeahead' => route('typeahead', ['table' => 'encounters', 'column' => 'encounter_cc']),
                    'default_value' => null
                ];
            }
            $items[] = [
                'name' => 'encounter_template',
                'label' => trans('noshform.encounter_template'),
                'type' => 'select',
                'select_items' => $encounter_type_arr,
                'required' => true,
                'default_value' => $encounter['encounter_template']
            ];
            $items[] = [
                'name' => 'encounter_DOS',
                'label' => trans('noshform.encounter_DOS'),
                'type' => 'text',
                'required' => true,
                'datetime' => true,
                'default_value' => $encounter['encounter_DOS']
            ];
            $items[] = [
                'name' => 'encounter_location',
                'label' => trans('noshform.encounter_location'),
                'type' => 'select',
                'select_items' => $encounter_location_arr,
                'required' => true,
                'default_value' => $encounter['encounter_location']
            ];
            if ($eid == '0') {
                $items[] = [
                    'name' => 'encounter_type',
                    'label' => trans('noshform.encounter_type'),
                    'type' => 'select',
                    'select_items' => ['' => 'No Appointments'],
                    'default_value' => $encounter['encounter_type']
                ];
            }
            $items[] = [
                'name' => 'encounter_role',
                'label' => trans('noshform.encounter_role'),
                'type' => 'select',
                'select_items' => $encounter_role_arr,
                'required' => true,
                'default_value' => $encounter['encounter_role']
            ];
            $items[] = [
                'name' => 'label[]',
                'label' => trans('noshform.label'),
                'type' => 'select',
                'select_items' => $this->fhir_scopes_sensitivities(),
                'multiple' => true,
                'selectpicker' => true,
                'default_value' => $encounter['label']
            ];
            if ($eid !== '0') {
                $items[] = [
                    'name' => 'bill_complex',
                    'label' => trans('noshform.bill_complex'),
                    'type' => 'select',
                    'select_items' => $encounter_complex_arr,
                    'default_value' => $encounter['bill_complex']
                ];
                $items[] = [
                    'name' => 'referring_provider',
                    'label' => trans('noshform.referring_provider'),
                    'type' => 'text',
                    'class' => 'referring_group',
                    'default_value' => $encounter['referring_provider']
                ];
                $items[] = [
                    'name' => 'referring_provider_npi',
                    'label' => trans('noshform.referring_provider_npi'),
                    'type' => 'text',
                    'class' => 'referring_group',
                    'default_value' => $encounter['referring_provider_npi']
                ];
                $items[] = [
                    'name' => 'encounter_condition_work',
                    'label' => trans('noshform.encounter_condition_work'),
                    'type' => 'select',
                    'select_items' => $encounter_select_arr,
                    'default_value' => $encounter['encounter_condition_work']
                ];
                $items[] = [
                    'name' => 'encounter_condition_auto',
                    'label' => trans('noshform.encounter_condition_auto'),
                    'type' => 'select',
                    'select_items' => $encounter_select_arr,
                    'default_value' => $encounter['encounter_condition_auto']
                ];
                $items[] = [
                    'name' => 'encounter_condition_auto_country',
                    'label' => trans('noshform.country'),
                    'type' => 'select',
                    'select_items' => $this->array_country(),
                    'default_value' => $encounter['encounter_condition_auto_country'],
                    'class' => 'country'
                ];
                $items[] = [
                    'name' => 'encounter_condition_auto_state',
                    'label' => trans('noshform.encounter_condition_auto_state'),
                    'type' => 'select',
                    'select_items' => $encounter_auto_state_arr,
                    'default_value' => $encounter['encounter_condition_auto_state'],
                    'class' => 'state'
                ];
                $items[] = [
                    'name' => 'encounter_condition_other',
                    'label' => trans('noshform.encounter_condition_other'),
                    'type' => 'select',
                    'select_items' => $encounter_select_arr,
                    'default_value' => $encounter['encounter_condition_other']
                ];
                $items[] = [
                    'name' => 'encounter_condition',
                    'label' => trans('noshform.encounter_condition'),
                    'type' => 'text',
                    'default_value' => $encounter['encounter_condition']
                ];
            }
            $form_array = [
                'form_id' => 'encounter_details_form',
                'action' => route('encounter_details', [$eid]),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            $data['content'] = $this->form_build($form_array);
            $data['encounters_active'] = true;
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function encounter_education(Request $request)
    {
        if ($request->isMethod('post')) {
            $view = $this->healthwise_view($request->input('url'));
            if ($view == 'Having trouble getting materials.  Try again') {
                Session::put('message_action', trans('noshform.error') . ' - ' . trans('noshform.healthwise_error'));
                return redirect(Session::get('last_page_encounter'));
            }
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $directory = Storage::path(Session::get('pid') . "/");
            $file_path = $directory . time() . '_patienteducation.pdf';
            App::setLocale(Session::get('practice_locale'));
            $html = $this->page_intro(trans('noshform.patient_education') . ': ' . $request->input('desc'), Session::get('practice_id'));
            $html .= $view;
            // return $html;
            $this->generate_pdf($html, $file_path);
            while(!file_exists($file_path)) {
                sleep(2);
            }
            $pages_data = [
                'documents_url' => $file_path,
                'pid' => Session::get('pid'),
                'documents_type' => 'Education',
                'documents_desc' => trans('noshform.instructions_for') . ' ' . Session::get('ptname') . ' - ' . $request->input('desc'),
                'documents_from' => Session::get('displayname'),
                'documents_viewed' => Session::get('displayname'),
                'documents_date' => date("Y-m-d H:i:s", time())
            ];
            DB::table('documents')->insert($pages_data);
            $this->audit('Add');
            if (Session::has('eid')) {
                $plan = DB::table('plan')->where('eid', '=', Session::get('eid'))->first();
                $plan_arr['plan'] = '';
                if ($plan) {
                    $plan_arr['plan'] = $plan->plan . "\n\n";
                }
                $plan_arr['plan'] .= trans('noshform.patient_education1') . ': ' . $request->input('desc');
                if ($plan) {
                    DB::table('plan')->where('eid', '=', Session::get('eid'))->update($plan_arr);
                    $this->audit('Update');
                } else {
                    $plan_arr['eid'] = Session::get('eid');
                    $plan_arr['pid'] = Session::get('pid');
                    $plan_arr['encounter_provider'] = Session::get('encounter_provider');
                    DB::table('plan')->insert($plan_arr);
                    $this->audit('Add');
                }
            }
            App::setLocale(Session::get('user_locale'));
            if ($request->has('submit')) {
                $file_path1_name = time() . '_' . Session::get('user_id') . '_patienteducation.pdf';
                $file_path1 = public_path() . '/temp/' . $file_path1_name;
                copy($file_path, $file_path1);
                Session::put('download_now', $file_path1);
            }
            Session::put('message_action', trans('noshform.patient_education2'));
            return redirect(Session::get('last_page_encounter'));
        } else {
            $items[] = [
                'name' => 'desc',
                'label' => trans('noshform.selected_topic'),
                'type' => 'text',
                'readonly' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'url',
                'type' => 'hidden',
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'education_form',
                'action' => route('encounter_education'),
                'items' => $items,
                'save_button_label' => trans('noshform.save_only'),
                'add_save_button' => ['download' => trans('noshform.save_download')]
            ];
            $data['search_healthwise'] = 'desc';
            $data['content'] = $this->form_build($form_array);
            $data['encounters_active'] = true;
            $data['panel_header'] = trans('noshform.patient_education_header');
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function encounter_edit_image(Request $request, $id, $t_messages_id='')
    {
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $directory = Storage::path(Session::get('pid') . "/");
        if ($request->isMethod('post')) {
            $image_id = $request->input('image_id');
            $file_path = $directory . 'image_' . time() . '.png';
            if ($request->has('image_src_override')) {
                if ($request->input('image_src_override') == 'Yes') {
                    $original = DB::table('image')->where('image_id', '=', $id)->first();
                    $file_path = $original->image_location;
                }
            }
            $image = imagecreatefrompng($request->input('image'));
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, $file_path);
            $data = [
                'image_location' => $file_path,
                'pid' => Session::get('pid'),
                'image_description' => $request->input('image_description'),
                'id' => Session::get('user_id'),
                'encounter_provider' => Session::get('displayname')
            ];
            if ($t_messages_id !== '') {
                $data['t_messages_id'] = $t_messages_id;
            } else {
                $data['eid'] = Session::get('eid');
            }
            if ($image_id == '') {
                DB::table('image')->insert($data);
                $this->audit('Add');
                Session::put('message_action', trans('noshform.image_added'));
            } else {
                $data['image_date'] = date("Y-m-d H:i:s", time());
                DB::table('image')->where("image_id", '=', $request->input('image_id'))->update($data);
                $this->audit('Update');
                Session::put('message_action', trans('noshform.image_updated'));
            }
            if ($t_messages_id !== '') {
                return redirect(Session::get('t_messages_photo_last_page'));
            } else {
                return redirect(Session::get('last_page'));
            }
        } else {
            if ($id == '0') {
                $patient = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
                $gender_arr = $this->array_gender();
                if ($patient->sex == 'm' || $patient->sex == 'f') {
                    $genders[] = $patient->sex;
                } else {
                    $genders = ['m', 'f'];
                }
                $arr = [];
                foreach ($genders as $gender) {
                    $dir = "assets/images/illustrations/" . $gender;
                    $full_dir = public_path() . '/' . $dir;
                    $files = scandir($full_dir);
                    $count = count($files);
                    for ($i = 2; $i < $count; $i++) {
                        $line = $files[$i];
                        $file = url($dir . "/" . $line);
                        $line1 = str_replace("_", " ", $line);
                        $line1 = ucwords(strtolower($line1));
                        $name = str_replace(".jpg", " - " . $gender_arr[$gender], $line1);
                        $arr[$file] = $name;
                    }
                }
                $data['image_list'] = '<option value="">' . trans('noshform.select') . '...</option>';
                foreach ($arr as $k => $v) {
                    $data['image_list'] .= '<option value="' . $k . '">' . $v . '</option>';
                }
                $data['image_list_title'] = trans('noshform.image_list_title');
                $data['image_list_label'] = trans('noshform.image_list_label') . ':';
                $items[] = [
                    'name' => 'image_id',
                    'type' => 'hidden',
                    'required' => true,
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'image_description',
                    'type' => 'text',
                    'label' => trans('noshform.image_description'),
                    'default_value' => null
                ];
            } else {
                $image = DB::table('image')->where('image_id', '=', $id)->first();
                $items[] = [
                    'name' => 'image_id',
                    'type' => 'hidden',
                    'required' => true,
                    'default_value' => $id
                ];
                $new_directory = public_path() . '/temp/' . time() . '_';
                $new_directory1 = '/temp/' . time() . '_';
                $file_path = str_replace($directory, $new_directory, $image->image_location);
                copy($image->image_location, $file_path);
                $items[] = [
                    'name' => 'image_src',
                    'type' => 'hidden',
                    'default_value' => url(str_replace($directory, $new_directory1, $image->image_location))
                ];
                $size = getimagesize($file_path);
                $items[] = [
                    'name' => 'image_src_width',
                    'type' => 'hidden',
                    'default_value' => $size[0]
                ];
                $items[] = [
                    'name' => 'image_src_height',
                    'type' => 'hidden',
                    'default_value' => $size[1]
                ];
                $items[] = [
                    'name' => 'image_src_override',
                    'type' => 'checkbox',
                    'label' => trans('noshform.image_src_override'),
                    'value' => 'Yes',
                    'default_value' => null
                ];
                $items[] = [
                    'name' => 'image_description',
                    'type' => 'text',
                    'label' => trans('noshform.image_description'),
                    'default_value' => $image->image_description
                ];
            }
            $items[] = [
                'name' => 'image',
                'type' => 'hidden',
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'image_form',
                'action' => route('encounter_edit_image', [$id]),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            if ($t_messages_id !== '') {
                $form_array['action'] = route('encounter_edit_image', [$id, $t_messages_id]);
            }
            $data['content'] = $this->form_build($form_array);
            $data['encounters_active'] = true;
            $data['panel_header'] = trans('noshform.annotate_image');
            $dropdown_array = [];
            $items = [];
            if ($t_messages_id !== '') {
                $items[] = [
                    'type' => 'item',
                    'label' => trans('noshform.back'),
                    'icon' => 'fa-chevron-left',
                    'url' => Session::get('t_messages_photo_last_page')
                ];

            } else {
                $items[] = [
                    'type' => 'item',
                    'label' => trans('noshform.back'),
                    'icon' => 'fa-chevron-left',
                    'url' => Session::get('last_page')
                ];
            }
            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $dropdown_array1 = [];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => '',
                'icon' => 'fa-trash',
                'url' => route('chart_action', ['table' => 'image', 'action' => 'delete', 'index' => 'image_id', 'id' => $id])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('image');
            $data['assets_css'] = $this->assets_css('image');
            return view('image', $data);
        }
    }

    public function encounter_form_add(Request $request, $id)
    {
        $message = $this->copy_form($id);
        if ($message !== '') {
            Session::put('message_action', $message);
        }
        return redirect(Session::get('last_page'));
    }

    public function encounter_print_mtm(Request $request, $eid)
    {
        $file_path = $this->print_mtm($eid);
        return response()->download($file_path);
    }

    public function encounter_print_plan(Request $request, $eid)
    {
        $html = $this->page_plan($eid);
        $name = "plan_" . time() . "_" . Session::get('user_id') . ".pdf";
        $filepath = public_path() . '/temp/' . time() . '_' . Session::get('user_id');
        $this->generate_pdf($html, $filepath);
        while(!file_exists($filepath)) {
            sleep(2);
        }
        Session::put('download_now', $filepath);
        return redirect(Session::get('last_page'));
    }

    public function encounter_save(Request $request, $eid, $section)
    {
        $table_arr = [
            'encounters' => ['encounter_cc'],
            'hpi' => ['hpi', 'situation'],
            'ros' => ['ros'],
            'pe' => ['pe'],
            'assessment' => ['assessment_other', 'assessment_ddx', 'assessment_notes'],
            'plan' => ['plan', 'goals', 'tp', 'followup', 'duration']
        ];
        foreach ($table_arr as $table => $columns) {
            foreach ($columns as $column) {
                if ($request->has($column)) {;
                    $data = [];
                    $data[$column] = $request->input($column);
                    $query = DB::table($table)->where('eid', '=', $eid)->first();
                    if ($query) {
                        if ($table !== 'encounters') {
                            $prefix = $table;
                        } else {
                            $prefix = 'encounter';
                        }
                        $data[$prefix . '_date'] = date("Y-m-d H:i:s", time());
                        DB::table($table)->where('eid', '=', $eid)->update($data);
                        $this->audit('Update');
                    } else {
                        $data['eid'] = $eid;
                        $data['pid'] = Session::get('pid');
                        $data['encounter_provider'] = Session::get('encounter_provider');
                        DB::table($table)->insert($data);
                        $this->audit('Add');
                    }
                }
            }
        }
        // if ($section == 's') {
        //     $url = route('encounter', [$eid, 'o']) . '#o';
        // }
        // if ($section == 'o') {
        //     $url = route('encounter', [$eid, 'a']) . '#a';
        // }
        // if ($section == 'a') {
        //     $url = route('encounter', [$eid, 'p']) . '#p';
        // }
        if ($section == 'd') {
            $url = route('encounter', [$eid]);
        } else {
            $url = route('encounter', [$eid, $section]) . '#' . $section;
        }
        Session::put('message_action', trans('noshform.encounter_save'));
        return redirect($url);
    }

    public function encounter_sign(Request $request, $eid)
    {
        $eid = Session::get('eid');
        $encounter = DB::table('encounters')->where('eid', '=',$eid)->first();
        // Validation
        $error_arr = [];
        $hpi = DB::table('hpi')->where('eid', '=',$eid)->first();
        $pe = DB::table('pe')->where('eid', '=', $eid)->first();
        $assessment = DB::table('assessment')->where('eid', '=', $eid)->first();
        if (!$hpi) {
            $error_arr[] = trans('noshform.subjective');
        }
        if ($encounter->encounter_template == 'medical') {
            if (!$pe) {
                $error_arr[] = trans('noshform.objective');
            }
        }
        if (!$assessment) {
            $error_arr[] = trans('noshform.assessment');
        }
        if (! empty($error_arr)) {
            $error = trans('noshform.error') . ' - ' . trans('noshform.missing_items') . ': ' . implode(', ', $error_arr);
            Session::put('message_action', $error);
            return redirect(Session::get('last_page_encounter'));
        }
        if (($encounter->encounter_template == 'standardmedical' || $encounter->encounter_template == 'standardmedical1') && Session::get('group_id') == '3') {
            Session::put('message_action', trans('noshform.error') . ' - ' . trans('noshform.encounter_no_sign'));
            return redirect(Session::get('last_page_encounter'));
        } else {
            $data = [
                'encounter_signed' => "Yes",
                'date_signed' => date('Y-m-d H:i:s', time())
            ];
            DB::table('encounters')->where('eid', '=', Session::get('eid'))->update($data);
            $this->audit('Update');
            // $this->api_data('update', 'encounters', 'eid', Session::get('eid'));
            if ($encounter->encounter_template == 'standardpsych') {
                $patient = DB::table('demographics_relate')
                    ->where('pid', '=', Session::get('pid'))
                    ->where('practice_id', '=', Session::get('practice_id'))
                    ->whereNotNull('id')
                    ->first();
                $alert_send_message = 'n';
                if ($patient) {
                    $alert_send_message = 'y';
                }
                $psych_date = strtotime($encounter->encounter_DOS) + 31556926;
                App::setLocale(Session::get('practice_locale'));
                $description = trans('noshform.annual_psych_reminder1') . ' ' . date('F jS, Y', $psych_date);
                $data1 = [
                    'alert' => trans('noshform.annual_psych_reminder'),
                    'alert_description' => $description,
                    'alert_date_active' => date('Y-m-d H:i:s', time()),
                    'alert_date_complete' => null,
                    'alert_reason_not_complete' => '',
                    'alert_providers' => Session::get('user_id'),
                    'orders_id' => '',
                    'pid' => Session::get('pid'),
                    'practice_id' => Session::get('practice_id'),
                    'alert_send_message' => $alert_send_message
                ];
                $id = DB::table('alerts')->insertGetId($data1, 'alert_id');
                $this->audit('Add');
                App::setLocale(Session::get('user_locale'));
                // $this->api_data('add', 'alerts', 'alert_id', $id);
            }
            Session::forget('eid');
            Session::forget('last_page_encounter');
            Session::forget('encounter_DOS');
            Session::forget('encounter_template');
            Session::put('message_action', trans('noshform.encounter_sign'));
            return redirect()->route('patient');
        }
    }

    public function encounter_view(Request $request, $eid, $previous=false)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $encounter = DB::table('encounters')->where('eid', '=', $eid)->first();
        // Tags
        $tags_relate = DB::table('tags_relate')->where('eid', '=', $eid)->get();
        $tags_val_arr = [];
        if ($tags_relate->count()) {
            foreach ($tags_relate as $tags_relate_row) {
                $tags = DB::table('tags')->where('tags_id', '=', $tags_relate_row->tags_id)->first();
                $tags_val_arr[] = $tags->tag;
            }
        }
        $return = '';
        $edit = $this->access_level('3');
        if ($encounter->encounter_signed == 'Yes') {
            $return .= '<div style="margin-bottom:15px;"><input type="text" id="encounter_tags" class="nosh-tags" value="' . implode(',', $tags_val_arr) . '" data-nosh-add-url="' . route('tag_save', ['eid', $eid]) . '" data-nosh-remove-url="' . route('tag_remove', ['eid', $eid]) . '" placeholder="' . trans('noshform.add_tags') . '"/></div>';
            $dropdown_array = [
                'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back'),
                'default_button_text_url' => Session::get('last_page')
            ];
            if ($edit) {
                $items = [];
                $items[] = [
                    'type' => 'item',
                    'label' => trans('noshform.make_addendum'),
                    'icon' => 'fa-plus',
                    'url' => route('encounter_addendum', [$eid])
                ];
                $query = DB::table('encounters')->where('addendum_eid', '=', $encounter->addendum_eid)->orderBy('date_signed', 'asc')->get();
                if (count($query) > 1) {
                    foreach ($query as $row) {
                        if ($row->addendum != "n" && $row->encounter_signed === 'Yes') {
                            $items[] = [
                                'type' => 'item',
                                'label' => trans('noshform.date_signed') . ': ' . $row->date_signed,
                                'icon' => 'fa-undo',
                                'url' => route('encounter_view', [$row->eid, true])
                            ];
                        }
                    }
                }
                $items[] = [
                    'type' => 'item',
                    'label' => trans('noshform.current_version'),
                    'icon' => 'fa-exclamation',
                    'url' => route('encounter_view', [$eid])
                ];
                $dropdown_array['items'] = $items;
            }
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        } else {
            $dropdown_array = [
                'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back'),
                'default_button_text_url' => Session::get('last_page')
            ];
            $items = [];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.edit'),
                'icon' => 'fa-pencil',
                'url' => route('encounter', [$eid])
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.sign'),
                'icon' => 'fa-check',
                'url' => route('encounter_sign', [$eid])
            ];

            $dropdown_array['items'] = $items;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        }
        if ($previous == true) {
            $return .= $this->encounters_view($eid, Session::get('pid'), Session::get('practice_id'), true, false)->render();
        } else {
            $return .= $this->encounters_view($eid, Session::get('pid'), Session::get('practice_id'), true, true)->render();
        }
        $data['content'] = $return;
        $data['encounters_active'] = true;
        $data['panel_header'] = trans('noshform.encounter') . ' - ' .  date('Y-m-d', $this->human_to_unix($encounter->encounter_DOS));
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function encounter_vitals_view(Request $request, $eid='')
    {
        $vitals_arr = $this->array_vitals();
        $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
        $return = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>' . trans('noshform.date') . '</th>';
        foreach ($vitals_arr as $k => $v) {
            $return .= '<th class="nosh-graph" data-nosh-vitals-type="' . $k . '">' . $v['name'] . '</th>';
        }
        $return .= '</tr></thead><tbody>';
        $query = DB::table('vitals')->where('pid', '=', Session::get('pid'))->orderBy('vitals_date', 'desc')->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $return .= '<tr';
                $class_arr = [];
                if ($eid == $row->eid) {
                    $class_arr[] = 'nosh-table-active';
                }
                if (!empty($class_arr)) {
                    $return .= ' class="' . implode(' ', $class_arr) . '"';
                }
                $return .= '><td>' . date('Y-m-d', $this->human_to_unix($row->vitals_date)) . '</td>';
                foreach ($vitals_arr as $vitals_key => $vitals_val) {
                    if (isset($vitals_val['min'])) {
                        if ($vitals_key == 'temp') {
                            if (($vitals_val['min'][$practice->temp_unit] <= $row->{$vitals_key}) && ($row->{$vitals_key} <= $vitals_val['max'][$practice->temp_unit])) {
                                $return .= '<td class="nosh-graph" data-nosh-vitals-type="' . $vitals_key . '">' . $row->{$vitals_key} . '</td>';
                            } elseif ($row->{$vitals_key} !== null && $row->{$vitals_key} !== '') {
                                $return .= '<td class="nosh-graph danger" data-nosh-vitals-type="' . $vitals_key . '">' . $row->{$vitals_key} . '</td>';
                            } else {
                                $return .= '<td class="nosh-graph" data-nosh-vitals-type="' . $vitals_key . '"></td>';
                            }
                        } else {
                            if (($vitals_val['min'] <= $row->{$vitals_key}) && ($row->{$vitals_key} <= $vitals_val['max'])) {
                                $return .= '<td class="nosh-graph" data-nosh-vitals-type="' . $vitals_key . '">' . $row->{$vitals_key} . '</td>';
                            } elseif ($row->{$vitals_key} !== null && $row->{$vitals_key} !== '') {
                                $return .= '<td class="nosh-graph danger" data-nosh-vitals-type="' . $vitals_key . '">' . $row->{$vitals_key} . '</td>';
                            } else {
                                $return .= '<td class="nosh-graph" data-nosh-vitals-type="' . $vitals_key . '"></td>';
                            }
                        }
                    } else {
                        $return .= '<td class="nosh-graph" data-nosh-vitals-type="' . $vitals_key . '">' . $row->{$vitals_key} . '</td>';
                    }
                }
            }
        }
        $return .= '</tbody></table></div>';
        $dropdown_array = [];
        $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
        if ($eid !== '') {
            $dropdown_array['default_button_text_url'] = route('encounter', [$eid, 'o']);
        } else {
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
        }
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = $return;
        $data['encounters_active'] = true;
        $data['panel_header'] = trans('noshform.vital_signs');
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function encounter_vitals_chart(Request $request, $type)
    {
        $pid = Session::get('pid');
        $demographics = DB::table('demographics')->where('pid', '=', $pid)->first();
        $vitals_arr = $this->array_vitals();
        $data['graph_y_title'] = $vitals_arr[$type]['name'] . ',' . $vitals_arr[$type]['unit'];
        $data['graph_x_title'] = trans('noshform.date');
        $data['graph_series_name'] = $vitals_arr[$type]['name'];
        $data['graph_title'] = trans('noshform.chart_of') . ' ' . $vitals_arr[$type]['name'] . ' ' . trans('noshform.over_time_for') . ' ' . $demographics->firstname . ' ' . $demographics->lastname . ' ' . trans('noshform.as_of') . ' ' . date("Y-m-d, g:i a", time());
        $query1 = DB::table('vitals')
            ->select($type, 'vitals_date')
            ->where('pid', '=', $pid)
            ->orderBy('vitals_date', 'asc')
            ->distinct()
            ->get();
        $json = [];
        if ($query1->count()) {
            foreach ($query1 as $row1) {
                if ($row1->{$type} !== null && $row1->{$type} !== '') {
                    $json[] = [
                        $row1->vitals_date,
                        $row1->{$type}
                    ];
                }
            }
        }
        $edit = $this->access_level('2');
        $dropdown_array = [];
        $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
        if (Session::has('eid')) {
            $dropdown_array['default_button_text_url'] = route('encounter_vitals_view');
        } else {
            $dropdown_array['default_button_text_url'] = route('encounter_vitals_view', [Session::get('eid')]);
        }
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['graph_data'] = json_encode($json);
        $data['graph_type'] = 'data-to-time';
        $data['results_active'] = true;
        $data['panel_header'] = $vitals_arr[$type]['name'];
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('graph');
        $data['assets_css'] = $this->assets_css();
        return view('graph', $data);
    }

    public function encounters_list(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('encounters')->where('pid', '=', Session::get('pid'))
            ->where('addendum', '=', 'n')->orderBy('encounter_DOS', 'desc');
        if (Session::get('patient_centric') == 'n') {
            $query->where('practice_id', '=', Session::get('practice_id'));
        }
        if (Session::get('group_id') == '100') {
            $query->where('encounter_signed', '=', 'Yes');
        }
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('2');
        $encounter_type = $this->array_encounter_type();
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->encounter_DOS)) . '</b> - ' . $encounter_type[$row->encounter_template] . ' - ' . $row->encounter_cc . '<br>' . trans('noshform.provider') . ': ' . $row->encounter_provider;
                if ($edit == true && $row->encounter_signed == 'No') {
                    $arr['edit'] = route('encounter', [$row->eid]);
                }
                $arr['view'] = route('encounter_view', [$row->eid]);
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'encounters_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add'),
                'icon' => 'fa-plus',
                'url' => route('encounter_details', ['0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array1);
        }
        $data['content'] = $return;
        $data['encounters_active'] = true;
        $data['panel_header'] = trans('noshform.encounters');
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function family_history(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $recent_query = DB::table('other_history')
            ->where('pid', '=', Session::get('pid'))
            ->where('eid', '!=', '0')
            ->orderBy('eid', 'desc')
            ->whereNotNull('oh_fh')->first();
        // Set up persistent values
        $persistent_check = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
        if (!$persistent_check) {
            // No persistent values exist
            $create_data = [];
            if ($recent_query) {
                $create_data['oh_fh'] = $recent_query->oh_fh;
            }
            $create_data['eid'] = '0';
            $create_data['pid'] = Session::get('pid');
            DB::table('other_history')->insert($create_data);
            $this->audit('Add');
        }
        $result = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
        if (Session::has('eid')) {
            if (Session::get('group_id') == '2' || Session::get('group_id') == '3') {
                // Mark as reviewed by physician or assistant
                $current_query = DB::table('other_history')->where('eid', '=', Session::get('eid'))->first();
                if ($current_query) {
                    $data1['oh_fh'] = $result->oh_fh;
                    DB::table('other_history')->where('eid', '=', Session::get('eid'))->update($data1);
                    $this->audit('Update');
                } else {
                    $data2['oh_fh'] = $result->oh_fh;
                    $data2['eid'] = Session::get('eid');
                    $data2['pid'] = Session::get('pid');
                    DB::table('other_history')->insert($data2);
                    $this->audit('Add');
                }
                if ($data['message_action'] !== '') {
                    $data['message_action'] .= '<br>';
                }
                $data['message_action'] .= trans('noshform.family_history_encounter');
            }
        }
        $data['content'] = '';
        $data['fh_active'] = true;
        $data['panel_header'] = trans('noshform.family_history');
        $data = array_merge($data, $this->sidebar_build('chart'));
        $dropdown_array1 = [
            'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => trans('noshform.add_family_member'),
            'icon' => 'fa-plus',
            'url' => route('family_history_update', ['add'])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array1);
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('sigma');
        $data['assets_css'] = $this->assets_css('');
        return view('sigma', $data);
    }

    public function family_history_sensitive(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $oh = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
            $data = $request->post();
            if (isset($data['label'])) {
                $name = 'Family History from Trustee';
                $icon = 'https://cloud.noshchartingsystem.com/i-family-practice.png';
                $scopes = [
                    URL::to('/') . '/fhir/FamilyHistory/',
                    'view',
                    'edit'
                ];
                foreach ($data['label'] as $label) {
                    $scopes[] = $label;
                }
                $this->uma_resource($scopes, $name, $icon);
            }
        } else {

        }

    }

    public function family_history_update(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $data = [
                'Name' => $request->input('name'),
                'Relationship' => $request->input('relationship'),
                'Status' => $request->input('status'),
                'Gender' => $request->input('gender'),
                'Date of Birth' => $request->input('date_of_birth'),
                'Marital Status' => $request->input('marital_status'),
                'Mother' => $request->input('mother'),
                'Father' => $request->input('father'),
                'Medical' => implode("\n", $request->input('medical'))
            ];
            $oh = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
            $fh_arr = [];
            if ($oh->oh_fh !== null) {
                if ($this->yaml_check($oh->oh_fh)) {
                    $fh_arr = Yaml::parse($oh->oh_fh);
                }
            }
            if ($id == 'add') {
                $fh_arr[] = $data;
                $message = trans('noshform.added_family_member');
            } else {
                $fh_arr[$id] = $data;
                $message = trans('noshform.updated_family_member');
            }
            $data1['oh_fh'] = Yaml::dump($fh_arr);
            DB::table('other_history')->where('oh_id', '=', $oh->oh_id)->update($data1);
            $this->audit('Update');
            Session::put('message_action', $message);
            return redirect(Session::get('last_page'));
        } else {
            if ($id == 'add') {
                $items[] = [
                    'name' => 'id',
                    'type' => 'hidden',
                    'required' => true,
                    'default_value' => null
                ];
                $result = [
                    'name' => null,
                    'relationship' => null,
                    'status' => null,
                    'gender' => null,
                    'date_of_birth' => null,
                    'marital_status' => null,
                    'mother' => null,
                    'father' => null,
                    'medical' => null
                ];
                $fh_arr = [];
                $data['panel_header'] = trans('noshform.add_family_member');
            } else {
                $oh = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
                $fh_arr = Yaml::parse($oh->oh_fh);
                $fh = $fh_arr[$id];
                $result = [
                    'name' => $fh['Name'],
                    'relationship' => $fh['Relationship'],
                    'status' => $fh['Status'],
                    'gender' => $fh['Gender'],
                    'date_of_birth' => $fh['Date of Birth'],
                    'marital_status' => $fh['Marital Status'],
                    'mother' => $fh['Mother'],
                    'father' => $fh['Father'],
                    'medical' => $fh['Medical']
                ];
                $items[] = [
                    'name' => 'id',
                    'type' => 'hidden',
                    'required' => true,
                    'default_value' => $id
                ];
                $data['panel_header'] = trans('noshform.edit_family_member');
            }
            $items = array_merge($items, $this->form_family_history($result, $fh_arr, $id));
            $form_array = [
                'form_id' => 'family_history_form',
                'action' => route('family_history_update', [$id]),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            $data['search_icd'] = 'medical';
            $data['fh_active'] = true;
            $data['content'] = $this->form_build($form_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function fhir_aat(Request $request)
    {
        // Check if call comes from rqp_claims redirect
        if (Session::has('uma_permission_ticket')) {
            if (isset($_REQUEST["authorization_state"])) {
                if ($_REQUEST["authorization_state"] != 'claims_submitted') {
                    if ($_REQUEST["authorization_state"] == 'not_authorized') {
                        $text = 'You are not authorized to have the desired authorization data added.';
                    }
                    if ($_REQUEST["authorization_state"] == 'request_submitted') {
                        $text = 'The authorization server needs additional information in order to determine whether you are authorized to have this authorization data.';
                    }
                    if ($_REQUEST["authorization_state"] == 'need_info') {
                        $text = 'The authorization server requires intervention by the patient to determine whether authorization data can be added. Try again later after receiving any information from the patient regarding updates on your access status.';
                    }
                    return $text;
                } else {
                    // Great - move on!
                    return redirect()->route('fhir_api');
                }
            } else {
                Session::forget('uma_permission_ticket');
            }
        }
        $urlinit = $as_uri . '/nosh/fhir/' . Session::get('type') . '?subject:Patient=1';
        $result = $this->fhir_request($urlinit,true);
        if (isset($result['error'])) {
            // error - return something
            return $result;
        }
        $permission_ticket = $result['ticket'];
        Session::put('uma_permission_ticket', $permission_ticket);
        Session::save();
        $as_uri = $result['as_uri'];
        $url = route('fhir_aat');
        // Requesting party claims
        $oidc->setRedirectURL($url);
        $oidc->rqp_claims($permission_ticket);
    }

    public function fhir_api(Request $request)
    {
        $as_uri = Session::get('uma_uri');
        if (!Session::has('rpt')) {
            // Send permission ticket + AAT to Authorization Server to get RPT
            $permission_ticket = Session::get('uma_permission_ticket');
            $client_id = Session::get('uma_client_id');
            $client_secret = Session::get('uma_client_secret');
            $url = route('fhir_api');
            $oidc = new OpenIDConnectUMAClient($as_uri, $client_id, $client_secret);
            $oidc->setSessionName('nosh');
            if (file_exists(base_path() . '/fakelerootx1.pem')) {
                $oidc->setCertPath(base_path() . '/fakelerootx1.pem');
            }
            $oidc->setRedirectURL($url);
            $result1 = $oidc->rpt_request($permission_ticket);
            if (isset($result1['error'])) {
                // error - return something
                if ($result1['error'] == 'expired_ticket') {
                    Session::forget('uma_permission_ticket');
                    return redirect()->route('uma_aat');
                } else {
                    $data['title'] = trans('noshform.fhir_error');
                    $data['back'] = '<a href="' . URL::to('resources') . '/' . $request->session()->get('current_client_id') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> Patient Summary</a>';
                    $data['content'] = 'Description:<br>' . $result1['error'];
                    return view('home', $data);
                }
            }
            $rpt = $result1['rpt'];
            // Save RPT in session in case for future calls in same session
            Session::put('rpt', $rpt);
            Session::save();
        } else {
            $rpt = Session::get('rpt');
        }
        // Contact pNOSH again, now with RPT
        $urlinit = $as_uri . '/nosh/fhir/' . Session::get('type') . '?subject:Patient=1';
        $result3 = $this->fhir_request($urlinit,false,$rpt);
        if (isset($result3['ticket'])) {
            // New permission ticket issued, expire rpt session
            Session::forget('rpt');
            Session::put('uma_permission_ticket', $result3['ticket']);
            Session::save();
            // Get new RPT
            return redirect()->route('fhir_api');
        }
        // Format the result into a nice display
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $id = Session::get('current_client_id');
        $client = DB::table('oauth_rp')->where('id', '=', $request->session()->get('current_client_id'))->first();
        $title_array = [
            'Condition' => trans('noshform.conditions'),
            'MedicationStatement' => trans('noshform.medications'),
            'AllergyIntolerance' => trans('noshform.allergies'),
            'Immunization' => trans('noshform.immunizations'),
            'Patient' => trans('noshform.patient_summary')
        ];
        $query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
        $data['title'] = $title_array[$request->session()->get('type')] . ' ' . trans('noshform.for') . ' ' . $client->as_name;
        $data['back'] = '<a href="' . URL::to('resources') . '/' . $request->session()->get('current_client_id') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> Patient Summary</a>';
        $data['content'] = ' ' . trans('noshform.none') . '.';
        $pt_name = '';
        if (isset($result3['total'])) {
            if ($result3['total'] != '0') {
                $data['content'] = '<ul class="list-group">';
                foreach ($result3['entry'] as $entry) {
                    if ($request->session()->get('type') == 'Patient' && $request->session()->get('hnosh') == 'true') {
                        $data['title'] = $title_array[$request->session()->get('type')];
                        $data['content'] .= '<li class="list-group-item">' . $entry['resource']['text']['div'];
                        $urlinit1 = $as_uri . '/nosh/fhir/MedicationStatement?subject:Patient=1';
                        $result4 = $this->fhir_request($urlinit1,false,$rpt);
                        if (isset($result4['total'])) {
                            if ($result4['total'] != '0') {
                                $data['content'] .= '<strong>' . trans('noshform.medications') . '</strong><ul>';
                                foreach ($result4['entry'] as $entry1) {
                                    $data['content'] .= '<li>' . $entry1['resource']['text']['div'] . '</li>';
                                }
                                $data['content'] .= '</ul>';
                            }
                        }
                        $data['content'] .= '</li>';
                    } else  {
                        $data['content'] .= '<li class="list-group-item">' . $entry['resource']['text']['div'] . '</li>';
                    }
                    if ($request->session()->get('type') == 'Patient') {
                        $pt_name = $entry['resource']['name'][0]['given'][0] . ' ' . $entry['resource']['name'][0]['family'][0] . ' (DOB: ' . $entry['resource']['birthDate'] . ')';
                    }
                }
                $data['content'] .= '</ul>';
            }
        }
        if ($request->session()->get('hnosh') == 'true') {
            $data['demo_title'] = "Dr. Second's Practice EHR";
            $data['hnosh'] = true;
            if ($pt_name !== '') {
                $data['back'] = '<a href="' . URL::to('add_patient_hnosh') . '/' . $request->session()->get('current_client_id') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-plus"></i> Add Patient</a>';
                $request->session()->put('hnosh_pt_name', $pt_name);
            }
        }
        return view('home', $data);
    }

    public function fhir_connect(Request $request, $id='list', $as='')
    {
        $data['panel_header'] = trans('noshform.fhir_connect');
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        // Get Open Epic servers
        $url = 'https://open.epic.com/MyApps/EndpointsJson';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_FAILONERROR,1);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
        $result = curl_exec($ch);
        $result_array = json_decode($result, true);
        if ($id == 'list') {
            if ($request->isMethod('post')) {
                // $data1['openepic_client_id'] = $request->input('openepic_client_id');
                // $data1['openepic_sandbox_client_id'] = $request->input('openepic_sandbox_client_id');
                // DB::table('practiceinfo')->where('practice_id', '=', '1')->update($data1);
                // $this->audit('Update');
                // Session::put('message_action', 'open.epic Client ID updated');
                // return redirect()->route('fhir_connect', ['list']);
            } else {
                $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                $data['content'] = '';
                // if ($practice->openepic_client_id == null || $practice->openepic_client_id == '') {
                //     $data['content'] .= '<div class="alert alert-danger"><p>' . trans('nosh.openepic1') . '   Once you have the ';
                //     $data['content'] .= '<p><a href="https://github.com/shihjay2/hieofone-as/wiki/Client-registration-for-open.epic" target="_blank" class="nosh-no-load">' . trans('nosh.openepic2') . '</a></p>';
                //     $data['content'] .= '<p>' . trans('nosh.openepic3') . '</p></div>';
                //     $epic_items[] = [
                //         'name' => 'openepic_client_id',
                //         'label' => trans('nosh.openepic_client_id'),
                //         'type' => 'text',
                //         'default_value' => null,
                //         'required' => true
                //     ];
                //     $epic_items[] = [
                //         'name' => 'openepic_sandbox_client_id',
                //         'label' => trans('nosh.openepic_sandbox_client_id'),
                //         'type' => 'text',
                //         'default_value' => null,
                //         'required' => true
                //     ];
                //     $epic_form_array = [
                //         'form_id' => 'epic_form',
                //         'action' => route('fhir_connect', ['list']),
                //         'items' => $epic_items
                //     ];
                //     $data['content'] .= $this->form_build($epic_form_array);
                // } else {
                    $data['content'] .= '<form role="form"><div class="form-group"><input class="form-control" id="searchinput" type="search" placeholder="' . trans('noshform.filter_results') . '..." /></div>';
                    $data['content'] .= '<div class="list-group searchlist">';
                    $sandbox_link = '<a href="' . route('fhir_connect', ['sandbox']) . '" class="list-group-item">' . trans('noshform.open_epic_list') . '</a>';
                    $conn_link = '';
                    $oth_link = '';
                    $i = 0;
                    $connected = DB::table('refresh_tokens')->where('practice_id', '=', '1')->get();
                    $connected_arr = [];
                    if ($connected->count()) {
                        foreach ($connected as $connect_row) {
                            if ($connect_row->pnosh !== null && $connect_row->pnosh !== '') {
                                $connected_arr[] = $connect_row->endpoint_uri;
                            }
                        }
                    }
                    foreach ($result_array['Entries'] as $row) {
                        if (in_array($row['FHIRPatientFacingURI'], $connected_arr)) {
                            $conn_link .= '<a href="' . route('fhir_connect', [$i]) . '" class="list-group-item list-group-item-success">' . trans('noshform.fhir_connected') . ' - ' . $row['OrganizationName'] . '<span class="pull-right"><i class="fa fa-ban fa-lg nosh_icon_ban" nosh-val="' . $row['FHIRPatientFacingURI'] . '" title="' . trans('noshform.remove') . '" style="cursor:pointer;"></i></span></a>';
                        } else {
                            $oth_link .= '<a href="' . route('fhir_connect', [$i]) . '" class="list-group-item">' . $row['OrganizationName'] . '</a>';
                        }
                        $i++;
                    }
                    $data['content'] .= $conn_link . $sandbox_link . $oth_link;
                    $data['content'] .= '</div></form>';
                // }
            }
        } else {
            if ($id == 'sandbox') {
                $fhir_url = 'https://open-ic.epic.com/argonaut/api/FHIR/Argonaut/';
                $fhir_name = 'Open Epic Argonaut Profile';
            } else {
                $fhir_url = $result_array['Entries'][$id]['FHIRPatientFacingURI'];
                $fhir_name = $result_array['Entries'][$id]['OrganizationName'];
            }
            $metadata = $this->fhir_metadata($fhir_url);
            if (isset($metadata['error'])) {
                $data['content'] = trans('noshform.open_epic_error');
            } else {
                Session::put('fhir_url', $fhir_url);
                Session::put('fhir_auth_url', $metadata['auth_url']);
                Session::put('fhir_token_url', $metadata['token_url']);
                Session::put('fhir_name', $fhir_name);
                if ($as !== '') {
                    Session::put('fhir_as', 'true');
                }
                $connected1 = DB::table('refresh_tokens')->where('practice_id', '=', '1')->where('endpoint_uri', '=', $fhir_url)->first();
                if (!$connected1) {
                    $refresh = [
                        'refresh_token' => $metadata['token_url'],
                        'pid' => Session::get('pid'),
                        'practice_id' => Session::get('practice_id'),
                        'user_id' => Session::get('user_id'),
                        'endpoint_uri' => $fhir_url,
                        'pnosh' => $fhir_name
                    ];
                    DB::table('refresh_tokens')->insert($refresh);
                    $this->audit('Add');
                }
                return redirect()->route('fhir_connect_response');
            }
        }
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        $data = array_merge($data, $this->sidebar_build('chart'));
        return view('chart', $data);
    }

    public function fhir_connect_display(Request $request, $type='Patient')
    {
        $token = Session::get('fhir_access_token');
        $title_array = $this->fhir_resources();
        $data['panel_header'] = trans('noshform.portal_data') . ' - ' . $title_array[$type]['name'];
        if ($type == 'Patient') {
            $url = Session::get('fhir_url') . $type . '/' . Session::get('fhir_patient_token');
        } else {
            $url = Session::get('fhir_url') . $type . '?Patient=' . Session::get('fhir_patient_token');
        }
        $result = $this->fhir_request($url,false,$token,true);
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data = $this->fhir_display($result, $type, $data);
        $dropdown_array = [];
        $items = [];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.back'),
            'icon' => 'fa-chevron-left',
            'url' => route('fhir_connect_display', ['Patient'])
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        Session::put('last_page', $request->fullUrl());
        $data = array_merge($data, $this->sidebar_build('chart'));
        return view('chart', $data);
    }

    public function fhir_connect_response(Request $request)
    {
        if (! Session::has('oidc_relay')) {
            $param = [
                'origin_uri' => route('fhir_connect_response'),
                'response_uri' => route('fhir_connect_response'),
                'fhir_url' => Session::get('fhir_url'),
                'fhir_auth_url' => Session::get('fhir_auth_url'),
                'fhir_token_url' => Session::get('fhir_token_url'),
                'type' => 'epic',
                'refresh_token' => ''
            ];
            $oidc_response = $this->oidc_relay($param);
            if ($oidc_response['message'] == 'OK') {
                Session::put('oidc_relay', $oidc_response['state']);
                return redirect($oidc_response['url']);
            } else {
                if (Session::has('fhir_as')) {
                    Session::forget('fhir_as');
                    $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                    if (!empty($practice->uma_uri)) {
                        $html = 'Error: ' . $oidc_response['message'] . '<br><a href="' . $practice->uma_uri . '">Click here to return to your authorization server</a>';
                        return $html;
                    }
                }
                Session::put('message_action', $oidc_response['message']);
                return redirect(Session::get('last_page'));
            }
        } else {
            $param1['state'] = Session::get('oidc_relay');
            Session::forget('oidc_relay');
            $oidc_response1 = $this->oidc_relay($param1, true);
            if ($oidc_response1['message'] == 'Tokens received') {
                if ($oidc_response1['tokens']['access_token'] == '') {
                    return redirect()->route('fhir_connect_response');
                }
                Session::put('fhir_access_token', $oidc_response1['tokens']['access_token']);
                Session::put('fhir_patient_token', $oidc_response1['tokens']['patient_token']);
                Session::forget('fhir_as');
                // Session::put('fhir_refresh_token', $oidc_response1['tokens']['refresh_token']);
                return redirect()->route('fhir_connect_display');
            } else {
                if (Session::has('fhir_as')) {
                    Session::forget('fhir_as');
                    $practice = DB::table('practiceinfo')->where('practice_id', '=', '1')->first();
                    if (!empty($practice->uma_uri)) {
                        $html = 'Error: ' . $oidc_response1['message'] . '<br><a href="' . $practice->uma_uri . '">Click here to return to your authorization server</a>';
                        return $html;
                    }
                }
                Session::put('message_action', $oidc_response1['message']);
                return redirect(Session::get('last_page'));
            }
        }
    }

    public function form_list(Request $request, $type)
    {
        $data['panel_header'] = trans('noshform.forms');
        $form_arr = [];
        $list_array = [];
        $return = '';
        $edit = $this->access_level('1');
        if ($type == 'fill') {
            $dropdown_array = [
                'items_button_text' => trans('noshform.forms_fill')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.forms_completed'),
                'icon' => 'fa-check',
                'url' => route('form_list', ['completed'])
            ];
            if ($edit) {
                $user = DB::table('users')->where('id', '=', Session::get('user_id'))->first();
                if (!empty($user->forms)) {
                    $yaml = $user->forms;
                } else {
                    $data1['forms'] = File::get(resource_path() . '/forms.yaml');
                    $data1['forms_updated_at'] = null;
                    DB::table('users')->where('id', '=', Session::get('user_id'))->update($data1);
                    $yaml = $data1['forms'];
                }
                $form_item = [
                    'id' => Session::get('user_id'),
                    'form' => $yaml
                ];
                $form_arr[] = $form_item;
            } else {
                $users = DB::table('users')->where('group_id', '!=', '100')->where('active', '=', '1')->get();
                if ($users->count()) {
                    foreach ($users as $user) {
                        if ($user->forms !== null && $user->forms !== '') {
                            $form_item = [
                                'id' => $user->id,
                                'owner' => $user->displayname,
                                'form' => $user->forms
                            ];
                            $form_arr[] = $form_item;
                        }
                    }
                }
            }
            foreach ($form_arr as $form) {
                $array = Yaml::parse($form['form']);
                foreach ($array as $row_k => $row_v) {
                    $arr = [];
                    $proceed = true;
                    $gender = null;
                    $age = null;
                    if ($edit == true) {
                        $arr['label'] = $row_k;
                        $arr['view'] = route('form_show', [$form['id'], $row_k]);
                    } else {
                        $arr['label'] = '<b>' . $form['owner'] . '</b> - '. $row_k;
                        $arr['view'] = route('form_show', [$form['id'], $row_k]);
                    }
                    if (isset($row_v['gender'])) {
                        $gender = $row_v['gender'];
                    }
                    if (isset($row_v['age'])) {
                        $age = $row_v['age'];
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
                        $list_array[] = $arr;
                    }
                }
            }
        } else {
            $dropdown_array = [
                'items_button_text' => trans('noshform.forms_completed')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.forms_fill'),
                'icon' => 'fa-file-text-o',
                'url' => route('form_list', ['fill'])
            ];
            $query1 = DB::table('forms')->where('pid', '=', Session::get('pid'))->orderBy('forms_date', 'desc')->get();
            if ($query1->count()) {
                foreach ($query1 as $row1) {
                    $arr = [];
                    $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row1->forms_date)) . '</b> - ' . $row1->forms_title;
                    $arr['view'] = route('form_view', [$row1->forms_id]);
                    if ($edit == true) {
                        if (Session::has('eid')) {
                            $arr['encounter'] = route('encounter_form_add', [$row1->forms_id]);
                        }
                    }
                    $list_array[] = $arr;
                }
            }
        }
        if (! empty($list_array)) {
            $return .= $this->result_build($list_array, 'forms_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        if ($edit == true) {
           $dropdown_array1 = [
               'items_button_icon' => 'fa-plus'
           ];
           $items1 = [];
           $items1[] = [
               'type' => 'item',
               'label' => trans('noshform.forms_add'),
               'icon' => 'fa-plus',
               'url' => route('configure_form_details', ['0'])
           ];
           $dropdown_array1['items'] = $items1;
           $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        $data['content'] = $return;
        $data['forms_active'] = true;
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function form_show(Request $request, $id, $type, $origin='users')
    {
        if ($origin == 'users') {
            $user = DB::table('users')->where('id', '=', $id)->first();
            $array = Yaml::parse($user->forms);
        } else {
            $forms = DB::table('forms')->where('forms_id', '=', $id)->first();
            $array = Yaml::parse($forms->forms_content);
        }
        $edit = $this->access_level('1');
        if ($request->isMethod('post')) {
            $score_arr = [];
            $score = 0;
            App::setLocale(Session::get('practice_locale'));
            $text_arr[] = trans('noshform.forms_title') . ': ' . $array[$type]['forms_title'];
            $text_arr[] = trans('noshform.form_completed_by') . ' ' . Session::get('displayname') . ' ' . trans('noshform.on') . ' ' . date('Y-m-d h:i A');
            $text_arr[] = '********************************************';
            $response_array[$type] = $array[$type];
            foreach ($array[$type] as $row_k => $row_v) {
                if ($row_k !== 'forms_title' && $row_k !== 'forms_destination' && $row_k !== 'scoring') {
                    $response_array[$type][$row_k]['value'] = $request->input($row_v['name']);
                    $text_arr[] = $row_v['text'] . ': ' . $request->input($row_v['name']);
                    if ($row_v['input'] == 'checkbox' || $row_v['input'] == 'radio') {
                        $options_arr = explode(',', $row_v['options']);
                        $i = 0;
                        foreach ($options_arr as $option) {
                            if ($request->input($row_v['name']) == $option) {
                                $score = $score + $i;
                            }
                            $i++;
                        }
                    }
                } else {
                    if ($row_k == 'scoring') {
                        foreach ($row_v as $score_k => $score_v) {
                            $score_arr[$score_k] = $score_v;
                        }
                    }
                }
            }
            if (! empty($score_arr)) {
                foreach ($score_arr as $range => $description) {
                    $range_arr = explode('-', $range);
                    if ($score >= $range_arr[0] && $score <= $range_arr[1]) {
                        $text_arr[] = '********************************************';
                        $text_arr[] = trans('noshform.total_score') . ': ' . $score . ' - ' . $description;
                    }
                }
            }
            App::setLocale(Session::get('user_locale'));
            $data['forms_content'] = Yaml::dump($response_array);
            $data['pid'] = Session::get('pid');
            $data['forms_title'] = $array[$type]['forms_title'];
            $data['forms_content_text'] = implode("\n", $text_arr);
            $data['array'] = $type;
            $query = DB::table('forms')->where('pid', '=', Session::get('pid'))->where('forms_title', '=', $array[$type]['forms_title'])->first();
            if ($query) {
                DB::table('forms')->where('forms_id', '=', $query->forms_id)->update($data);
                $message = trans('noshform.form_updated');
            } else {
                DB::table('forms')->insert($data);
                $message = trans('noshform.form_saved');
            }
            return redirect()->route('form_list', [$type]);
        } else {
            $items = [];
            foreach ($array[$type] as $row_k => $row_v) {
                if ($row_k !== 'forms_title' && $row_k !== 'forms_destination' && $row_k !== 'scoring' && $row_k !== 'gender') {
                    $form_item = [
                        'name' => $row_v['name'],
                        'label' => $row_v['text'],
                        'type' => $row_v['input'],
                        'required' => true,
                        'default_value' => null
                    ];
                    if ($row_v['input'] == 'checkbox' || $row_v['input'] == 'radio' || $row_v['input'] == 'select') {
                        $options = [];
                        if (isset($row_v['options'])) {
                            $options_arr = explode(',', $row_v['options']);
                            foreach ($options_arr as $options_item)
                            $options[$options_item] = $options_item;
                        }
                        if ($row_v['input'] == 'select') {
                            $form_item['select_items'] = $options;
                        } else {
                            $form_item['section_items'] = $options;
                        }
                    }
                    if ($origin !== 'users') {
                        $form_item['default_value'] = $row_v['value'];
                        $form_item['value'] = $row_v['value'];
                    }
                    $items[] = $form_item;
                }
            }
            $form_array = [
                'form_id' => 'patient_form',
                'action' => route('form_show', [$id, $type, $origin]),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            $data['panel_header'] = trans('noshform.forms_complete');
            $data['content'] = $this->form_build($form_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['forms_active'] = true;
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function form_view(Request $request, $id)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        if (Session::has('eid')) {
            $message = $this->copy_form($id);
            if ($message !== '') {
                Session::put('message_action', $message);
            }
        }
        $result = DB::table('forms')->where('forms_id', '=', $id)->first();
        $dropdown_array = [
            'default_button_text' => '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back'),
            'default_button_text_url' => Session::get('last_page')
        ];
        if ($result->template_id == null) {
            $items = [];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.update_form_response'),
                'icon' => 'fa-repeat',
                'url' => route('form_show', [$result->forms_id, $result->array, 'forms'])
            ];
            $dropdown_array['items'] = $items;
        }
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['panel_header'] = trans('noshform.form_response');
        $data['content'] = nl2br($result->forms_content_text);
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['forms_active'] = true;
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function generate_hcfa1($eid, $insurance_id_1, $insurance_id_2='')
    {
        $result = $this->billing_save_common($insurance_id_1, $insurance_id_2, $eid);
        $file_path = $this->hcfa($eid);
        if ($file_path) {
            return response()->download($file_path);
        } else {
            Session::put('message_action', trans('noshform.error ') . ' - ' . trans('noshform.no_hcfa_print'));
            return redirect(Session::get('last_page'));
        }
    }

    public function growth_chart(Request $request, $type)
    {
        $pid = Session::get('pid');
        $displayname = Session::get('displayname');
        $demographics = DB::table('demographics')->where('pid', '=', $pid)->first();
        $gender = Session::get('gender');
        $sex = 'f';
        if ($gender == 'male') {
            $sex = 'm';
        }
        $time = time();
        $dob = $this->human_to_unix($demographics->DOB);
        $pedsage = ($time - $dob);
        $datenow = date(DATE_RFC822, $time);
        if ($type == 'bmi-age') {
            $data = $this->gc_bmi_age($sex, $pid);
            $data['title'] = trans('noshform.bmi_percentiles') . ' ' . $demographics->firstname . ' ' . $demographics->lastname . ' ' . trans('noshform.as_of') . ' ' . $datenow;
            $data['graph_type'] = 'growth-chart';
        }
        if ($type == 'weight-age') {
            $data = $this->gc_weight_age($sex, $pid);
            $data['title'] = trans('noshform.wt_percentiles') . ' ' . $demographics->firstname . ' ' . $demographics->lastname . ' ' . trans('noshform.as_of') . ' ' . $datenow;
            $data['graph_type'] = 'growth-chart';
        }
        if ($type == 'height-age') {
            $data = $this->gc_height_age($sex, $pid);
            $data['title'] = trans('noshform.ht_percentiles') . ' ' . $demographics->firstname . ' ' . $demographics->lastname . ' ' . trans('noshform.as_of') . ' ' . $datenow;
            $data['graph_type'] = 'growth-chart';
        }
        if ($type == 'head-age') {
            $data = $this->gc_head_age($sex, $pid);
            $data['title'] = trans('noshform.hc_percentiles') . ' ' . $demographics->firstname . ' ' . $demographics->lastname . ' ' . trans('noshform.as_of') . ' ' . $datenow;
            $data['graph_type'] = 'growth-chart';
        }
        if ($type == 'weight-height') {
            $data = $this->gc_weight_height($sex, $pid);
            $data['title'] = trans('noshform.wt_ht_percentiles') . ' ' . $demographics->firstname . ' ' . $demographics->lastname . ' ' . trans('noshform.as_of') . ' ' . $datenow;
            $data['graph_type'] = 'growth-chart1';
        }
        $data['patientname'] = $demographics->firstname . ' ' . $demographics->lastname;
        // $data['results_active'] = true;
        $data['panel_header'] = $data['title'];
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('graph');
        $data['assets_css'] = $this->assets_css();
        return view('graph', $data);
    }

    public function immunizations_csv(Request $request)
    {
        $pid = Session::get('pid');
        $result = DB::table('immunizations')
            ->join('demographics', 'demographics.pid', '=', 'immunizations.pid')
            ->join('insurance', 'insurance.pid' , '=', 'immunizations.pid')
            ->where('immunizations.pid', '=', $pid)
            ->where('insurance.insurance_plan_active', '=', 'Yes')
            ->where('insurance.insurance_order', '=', 'Primary')
            ->select('immunizations.pid', 'demographics.lastname', 'demographics.firstname', 'demographics.DOB', 'demographics.sex', 'demographics.address', 'demographics.city', 'demographics.state', 'demographics.zip', 'demographics.phone_home', 'immunizations.imm_cvxcode', 'immunizations.imm_elsewhere', 'immunizations.imm_date', 'immunizations.imm_lot', 'immunizations.imm_manufacturer', 'insurance.insurance_plan_name')
            ->get();
        $csv = '';
        if ($result->count()) {
            $csv .= "PatientID,Last,First,BirthDate,Gender,PatientAddress,City,State,Zip,Phone,ImmunizationCVX,OtherClinic,DateGiven,LotNumber,Manufacturer,InsuredPlanName";
            foreach ($result as $row1) {
                $row = (array) $row1;
                $row['DOB'] = date('m/d/Y', $this->human_to_unix($row['DOB']));
                $row['imm_date'] = date('m/d/Y', $this->human_to_unix($row['imm_date']));
                $row['sex'] = strtoupper($row['sex']);
                if ($row['imm_elsewhere'] == 'Yes') {
                    $row['imm_elsewhere'] = $row['imm_date'];
                } else {
                    $row['imm_elsewhere'] = '';
                }
                $csv .= "\n";
                $csv .= implode(',', $row);
            }
        }
        $file_path = public_path() . '/temp/' . time() . '_'. Session::get('user_id') . '_immunization_csv.txt';
        File::put($file_path, $csv);
        while(!file_exists($file_path)) {
            sleep(2);
        }
        Session::put('download_now', $file_path);
        return redirect(Session::get('last_page'));
    }

    public function immunizations_list(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('immunizations')
            ->where('pid', '=', Session::get('pid'))
            ->orderBy('imm_immunization', 'asc')
            ->orderBy('imm_sequence', 'asc');
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('7');
        $notes = DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->first();
        if (!empty($notes->imm_notes)) {
            $return .= '<div class="alert alert-success"><h5>' . trans('noshform.imm_notes') . '</h5>';
            $return .= nl2br($notes->imm_notes);
            $return .= '</div>';
        }
        $columns = Schema::getColumnListing('immunizations');
        $row_index = $columns[0];
        if ($result->count()) {
            $list_array = [];
            $seq_array = [
                '1' => ', ' . lcfirst(trans('noshform.first')),
                '2' => ', ' . lcfirst(trans('noshform.second')),
                '3' => ', ' . lcfirst(trans('noshform.third')),
                '4' => ', ' . lcfirst(trans('noshform.fourth')),
                '5' => ', ' . lcfirst(trans('noshform.fifth'))
            ];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . $row->imm_immunization . '</b> - ' . date('Y-m-d', $this->human_to_unix($row->imm_date));
                if (isset($row->imm_sequence)) {
                    if (isset($seq_array[$row->imm_sequence])) {
                        $arr['label'] = '<b>' . $row->imm_immunization . $seq_array[$row->imm_sequence]  . '</b> - ' . date('Y-m-d', $this->human_to_unix($row->imm_date));
                    }
                }
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['immunizations', $row_index, $row->$row_index]);
                    if (Session::get('group_id') == '2') {
                        $arr['delete'] = route('chart_action', ['table' => 'immunizations', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                }
                if ($row->reconcile !== null && $row->reconcile !== 'y') {
                    $arr['danger'] = true;
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'immunizations_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['immunizations_active'] = true;
        $data['panel_header'] = trans('noshform.immunizations');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        $dropdown_array = [];
        $items = [];
        $items[] = [
            'type' => 'item',
            'label' => '',
            'icon' => 'fa-plus',
            'url' => route('chart_form', ['immunizations', $row_index, '0'])
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-bars'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.imm_notes'),
                'icon' => 'fa-pencil-square-o',
                'url' => route('immunizations_notes')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.immunizations_print'),
                'icon' => 'fa-print',
                'url' => route('immunizations_print')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.immunizations_csv'),
                'icon' => 'fa-table',
                'url' => route('immunizations_csv')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.recommendations'),
                'icon' => 'fa-thumbs-o-up',
                'url' => route('care_opportunities', ['immunizations'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        if (Session::has('eid')) {
            // Mark conditions list as reviewed
        }
        if (Session::has('download_now')) {
            $data['download_now'] = route('download_now');
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function immunizations_notes(Request $request)
    {
        if ($request->isMethod('post')) {
            $data['imm_notes'] = $request->input('imm_notes');
            DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->update($data);
            $this->audit('Update');
            Session::put('message_action', trans('noshform.imm_notes_updated'));
            return redirect(Session::get('last_page'));
        } else {
            $result = DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->first();
            $notes = null;
            $data['panel_header'] = trans('noshform.add') . ' ' . trans('noshform.imm_notes');
            if ($result->imm_notes !== '' && $result->imm_notes !== null) {
                $notes = $result->imm_notes;
                $data['panel_header'] = trans('noshform.edit') . ' ' . trans('noshform.imm_notes');
            }
            $items[] = [
                'name' => 'imm_notes',
                'label' => trans('noshform.imm_notes'),
                'type' => 'textarea',
                'required' => true,
                'default_value' => $notes
            ];
            $form_array = [
                'form_id' => 'imm_notes_form',
                'action' => route('immunizations_notes'),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            $data['content'] = $this->form_build($form_array);
            $data['immunizations_active'] = true;
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function immunizations_print()
    {
        ini_set('memory_limit','196M');
        $html = $this->page_immunization_list()->render();
        $user_id = Session::get('user_id');
        $file_path = public_path() . "/temp/" . time() . "_imm_list_" . $user_id . ".pdf";
        $this->generate_pdf($html, $file_path);
        while(!file_exists($file_path)) {
            sleep(2);
        }
        Session::put('download_now', $file_path);
        return redirect(Session::get('last_page'));
    }

    public function inventory(Request $request, $action, $id, $pid, $subtype='')
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'amount' => 'numeric'
            ]);
            if ($action == 'sup_list') {
                $data['supplement_id'] = $request->input('supplement_id');
                $inventory_result = DB::table('supplement_inventory')->where('supplement_id', '=', $request->input('supplement_id'))->first();
                $inventory_data['quantity1'] =  $inventory_result->quantity1 - $request->input('amount');
                DB::table('supplement_inventory')->where('supplement_id', '=', $request->input('supplement_id'))->update($inventory_data);
                $this->audit('Update');
                $message = trans('noshform.supplement_inventory_updated');
                $sales_tax_check = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
                if (Session::has('eid')) {
                    $eid = Session::get('eid');
                    $encounterInfo = DB::table('encounters')->where('eid', '=', $eid)->first();
                    $dos1 = $this->human_to_unix($encounterInfo->encounter_DOS);
                    $dos = date('mdY', $dos1);
                    $dos2 = date('m/d/Y', $dos1);
                    $pos = $encounterInfo->encounter_location;
                    $icd_pointer = '';
                    $assessment_data = DB::table('assessment')->where('eid', '=', $eid)->first();
                    if ($assessment_data) {
                        if ($assessment_data->assessment_1 !== '') {
                            $icd_pointer .= "A";
                        }
                        if ($assessment_data->assessment_2 !== '') {
                            $icd_pointer .= "B";
                        }
                        if ($assessment_data->assessment_3 !== '') {
                            $icd_pointer .= "C";
                        }
                        if ($assessment_data->assessment_4 !== '') {
                            $icd_pointer .= "D";
                        }
                    }
                    $cpt = [
                        'cpt' => $inventory_result->cpt,
                        'cpt_charge' => $inventory_result->charge,
                        'eid' => $eid,
                        'pid' => $pid,
                        'dos_f' => $dos2,
                        'dos_t' => $dos2,
                        'payment' => '0',
                        'icd_pointer' => $icd_pointer,
                        'unit' => $request->input('amount'),
                        'billing_group' => '1',
                        'modifier' => '',
                        'practice_id' => Session::get('practice_id')
                    ];
                    DB::table('billing_core')->insert($cpt);
                    $this->audit('Add');
                    if ($sales_tax_check->sales_tax !== '') {
                        $sales_tax_add_query1 = DB::table('billing_core')
                            ->where('eid', '=', $eid)
                            ->where('cpt', 'LIKE', "sp%")
                            ->get();
                        if ($sales_tax_add_query1->count()) {
                            $sales_tax_total1 = $inventory_result->charge * $request->input('amount');
                            foreach ($sales_tax_add_query1 as $sales_tax_add_row1) {
                                $sales_tax_total1 += $sales_tax_add_row1->cpt_charge * $sales_tax_add_row1->unit;
                            }
                        } else {
                            $sales_tax_total1 = $inventory_result->charge * $request->input('amount');
                        }
                        $sales_tax1 = [
                            'cpt' => 'sptax',
                            'cpt_charge' => number_format($sales_tax_total1 * $sales_tax_check->sales_tax / 100, 2),
                            'eid' => $eid,
                            'pid' => $pid,
                            'dos_f' => $dos2,
                            'dos_t' => $dos2,
                            'payment' => '0',
                            'icd_pointer' => $icd_pointer,
                            'unit' => '1',
                            'billing_group' => '1',
                            'modifier' => '',
                            'practice_id' => Session::get('practice_id')
                        ];
                        $sales_tax_row1 = DB::table('billing_core')
                            ->where('cpt', '=', 'sptax')
                            ->where('eid', '=', $eid)
                            ->first();
                        if ($sales_tax_row1) {
                            DB::table('billing_core')->where('billing_core_id', '=', $sales_tax_row1->billing_core_id)->update($sales_tax1);
                            $this->audit('Update');
                        } else {
                            DB::table('billing_core')->insert($sales_tax1);
                            $this->audit('Add');
                        }
                    }
                } else {
                    if ($sales_tax_check->sales_tax !== '') {
                        $sales_tax_total2 = $inventory_result->charge * $amount;
                        $tax = number_format($sales_tax_total2 * $sales_tax_check->sales_tax / 100, 2);
                        $cpt_charge = $sales_tax_total2 + $tax;
                        $reason = $inventory_result->sup_description . ', ' . $inventory_result->sup_strength . ", Quantity: " . $request->input('amount') . ", Tax: $" . $tax;
                        $unit = '1';
                    } else {
                        $cpt_charge = $inventory_result->charge;
                        $reason = $inventory_result->sup_description . ', ' . $inventory_result->sup_strength . ", Quantity: " . $request->input('amount');
                        $unit = $request->input('amount');
                    }
                    $other_data = [
                        'eid' => '0',
                        'pid' => $pid,
                        'dos_f' => date('m/d/Y'),
                        'cpt_charge' => $cpt_charge,
                        'reason' => $reason,
                        'payment' => '0',
                        'unit' => $unit,
                        'practice_id' => Session::get('practice_id')
                    ];
                    $id1 = DB::table('billing_core')->insertGetId($other_data, 'billing_core_id');
                    $this->audit('Add');
                    $data1['other_billing_id'] = $id1;
                    DB::table('billing_core')->where('billing_core_id', '=', $id1)->update($data1);
                    $this->audit('Update');
                }
            }
            if ($action == 'immunizations') {
                $vaccine_id = $request->input('vaccine_id');
                $inventory_result = DB::table('vaccine_inventory')->where('vaccine_id', '=', $request->input('vaccine_id'))->first();
                $inventory_data['quantity'] = $inventory_result->quantity - $request->input('amount');
                DB::table('vaccine_inventory')->where('vaccine_id', '=', $vaccine_id)->update($inventory_data);
                $this->audit('Update');
                $message = trans('noshform.vaccine_inventory_updated');
            }
            Session::put('message_action', $message);
            return redirect(Session::get('last_page'));
        } else {
            $columns = Schema::getColumnListing($action);
            $row_index = $columns[0];
            $query = DB::table($action)->where($row_index, '=', $id)->first();
            if ($action == 'sup_list') {
                $supplement = null;
                $supplement_arr = $this->array_supplement_inventory();
                $supplement = $this->closest_match($query->sup_supplement . ', ' . $query->sup_dosage . ' ' . $query->sup_dosage_unit, $supplement_arr);
                $items[] = [
                    'name' => 'supplement_id',
                    'label' => trans('noshform.supplement'),
                    'type' => 'select',
                    'select_items' => $supplement_arr,
                    'required' => true,
                    'default_value' => $supplement
                ];
                $items[] = [
                    'name' => 'amount',
                    'label' => trans('noshform.quantity1'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => '1'
                ];
                $data['panel_header'] = trans('noshform.supplement_inventory_use');
                $data['supplements_active'] = true;


            }
            if ($action == 'immunizations') {
                $vaccine = null;
                $vaccine_arr = $this->array_vaccine_inventory();
                $vaccine = $this->closest_match($query->imm_immunization, $vaccine_arr);
                $items[] = [
                    'name' => 'vaccine_id',
                    'label' => trans('noshform.vaccine'),
                    'type' => 'select',
                    'select_items' => $vaccine_arr,
                    'required' => true,
                    'default_value' => $vaccine
                ];
                $items[] = [
                    'name' => 'amount',
                    'label' => trans('noshform.quantity1'),
                    'type' => 'text',
                    'required' => true,
                    'default_value' => '1'
                ];
                $data['immunizations_active'] = true;
                $data['panel_header'] = trans('noshform.vaccine_inventory_use');
            }
            $form_array = [
                'form_id' => 'inventory_form',
                'action' => route('inventory', [$action, $id, $pid]),
                'items' => $items,
                'save_button_label' => trans('noshform.save')
            ];
            $data['content'] = $this->form_build($form_array);
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function medications_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('rx_list')->where('pid', '=', Session::get('pid'))->orderBy('rxl_medication', 'asc');
        if ($type == 'active') {
            $query->whereNull('rxl_date_inactive')->whereNull('rxl_date_old');
            $dropdown_array = [
                'items_button_text' => trans('noshform.active')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.inactive'),
                'icon' => 'fa-times',
                'url' => route('medications_list', ['inactive'])
            ];
        } else {
            $query->whereNotNull('rxl_date_inactive');
            $dropdown_array = [
                'items_button_text' => trans('noshform.inactive')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.active'),
                'icon' => 'fa-check',
                'url' => route('medications_list', ['active'])
            ];
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('7');
        $columns = Schema::getColumnListing('rx_list');
        $row_index = $columns[0];
        $list_array = [];
        if ($result->count()) {
            foreach ($result as $row) {
                $arr = [];
                if ($row->rxl_sig == '') {
                    $arr['label'] = '<strong>' . $row->rxl_medication . '</strong> ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $row->rxl_instructions . ' ' . trans('noshform.for') . ' ' . $row->rxl_reason;
                } else {
                    $arr['label'] = '<strong>' . $row->rxl_medication . '</strong> ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $row->rxl_sig . ', ' . $row->rxl_route . ', ' . $row->rxl_frequency;
                    $arr['label'] .= ' ' . trans('noshform.for') . ' ' . $row->rxl_reason;
                }
                $previous = DB::table('rx_list')
        			->where('pid', '=', Session::get('pid'))
        			->where('rxl_medication', '=', $row->rxl_medication)
        			->select('rxl_date_prescribed', 'prescription')
                    ->orderBy('rxl_date_prescribed', 'desc')
        			->first();
                if ($previous) {
                    if ($previous->rxl_date_prescribed !== null && $previous->rxl_date_prescribed !== null) {
                        $previous_date = new Date($this->human_to_unix($previous->rxl_date_prescribed));
                        $ago = $previous_date->diffInDays();
                        $arr['label'] .= '<br><strong>' . trans('noshform.last_prescribed') . ':</strong> ' . date('Y-m-d', $this->human_to_unix($previous->rxl_date_prescribed)) . ', ' . $ago . ' ' . trans('noshform.days_ago');
                        // $arr['label'] .= '<br><strong>Prescription Status:</strong> ' . ucfirst($previous->prescription);
                    }
                }
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['rx_list', $row_index, $row->$row_index]);
                    if (Session::get('group_id') == '2') {
                        $arr['refill'] = route('chart_form', ['rx_list', $row_index, $row->$row_index, 'refill']);
                        $arr['eie'] = route('chart_action', ['table' => 'rx_list', 'action' => 'eie', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                    if ($type == 'active') {
                        $arr['inactivate'] = route('chart_action', ['table' => 'rx_list', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    } else {
                        $arr['reactivate'] = route('chart_action', ['table' => 'rx_list', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                    if (Session::get('group_id') == '2') {
                        $arr['delete'] = route('chart_action', ['table' => 'rx_list', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                }
                if ($row->reconcile !== null && $row->reconcile !== 'y') {
                    $arr['danger'] = true;
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'medications_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['medications_active'] = true;
        $data['panel_header'] = trans('noshform.medications');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['rx_list', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
            if (Session::get('group_id') == '2') {
                $dropdown_array2 = [
                    'items_button_icon' => 'fa-plus'
                ];
                $items2[] = [
                    'type' => 'item',
                    'label' => trans('noshform.prescribe'),
                    'icon' => 'fa-plus',
                    'url' => route('chart_form', ['rx_list', $row_index, '0', 'prescribe'])
                ];
                $dropdown_array2['items'] = $items2;
                $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array2);
            }
        }
        if (Session::has('eid') && $type == 'active') {
            if (Session::get('group_id') == '2') {
                // Mark medication list as reviewed by physician
                $medications_encounter = '';
                if (! empty($list_array)) {
                    $medications_encounter .= implode("\n", array_column($list_array, 'label'));
                }
                $medications_encounter .= "\n" . trans('noshform.reviewed_by') . ' ' . Session::get('displayname') . ' ' . trans('noshform.on') . ' ' . date('Y-m-d');
                $encounter_query = DB::table('other_history')->where('eid', '=', Session::get('eid'))->first();
                $encounter_data['oh_meds'] = $medications_encounter;
                if ($encounter_query) {
                    DB::table('other_history')->where('eid', '=', Session::get('eid'))->update($encounter_data);
                } else {
                    $encounter_data['eid'] = Session::get('eid');
                    $encounter_data['pid'] = Session::get('pid');
                    DB::table('other_history')->insert($encounter_data);
                }
                if ($data['message_action'] !== '') {
                    $data['message_action'] .= '<br>';
                }
                $data['message_action'] .= trans('noshform.medication_encounter');
            }
        }
        if (Session::has('demo_comment')) {
            $data['demo_comment'] = 'true';
            Session::forget('demo_comment');
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function mtm(Request $request, $type='mtm')
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $result = [];
        $return = '';
        $query = DB::table('mtm')
            ->where('pid', '=', Session::get('pid'))
            ->where('practice_id', '=', Session::get('practice_id'))
            ->get();
        $columns = Schema::getColumnListing('mtm');
        $row_index = $columns[0];
        if ($query->count()) {
            $list_array = [];
            foreach ($query as $row) {
                $arr['label'] = '<b>' . $row->mtm_description . '</b><br><br><b>' . trans('noshform.mtm_recommendations') . ':</b> ' . $row->mtm_recommendations;
                if ($row->mtm_date_completed !== '0000-00-00') {
                    $arr['label'] .= '<br><b>' . trans('noshform.mtm_date_completed') . ':</b> ' . date('Y-m-d', $this->human_to_unix($row->mtm_date_completed));
                }
                $arr['edit'] = route('chart_form', ['mtm', $row_index, $row->$row_index]);
                $arr['inactivate'] = route('chart_action', ['table' => 'mtm', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                $arr['delete'] = route('chart_action', ['table' => 'mtm', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'mtm_list');
        } else {
            $return .= trans('noshform.none') . '.';
        }
        $dropdown_array1 = [
            'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => '',
            'icon' => 'fa-plus',
            'url' => route('chart_form', ['mtm', $row_index, '0'])
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array1);
        $data['content'] = $return;
        $data['panel_header'] = trans('noshform.mtm');
        $data['mtm_active'] = true;
        Session::put('last_page', $request->fullUrl());
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function patient(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data['content'] = '';
        $demographics = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        $demographics_plus = DB::table('demographics_plus')->where('pid', '=', Session::get('pid'))->first();
        $practiceInfo = DB::table('practiceinfo')->first();
        if ($demographics->photo !== null) {
            if (file_exists($demographics->photo)) {
                $directory = Storage::path(Session::get('pid') . "/");
                $new_directory = public_path() . '/temp/';
                $new_directory1 = '/temp/';
                $file_path = str_replace($directory, $new_directory, $demographics->photo);
                $file_path1 = str_replace($directory, $new_directory1, $demographics->photo);
                copy($demographics->photo, $file_path);
                $data['content'] .= HTML::image($file_path1, 'Image', array('border' => '0', 'style' => 'display:block;margin:auto;max-height: 200px;width: auto;'));
                $data['content'] .= '<br>';
            }
        }
        if ($demographics->hieofone_as_url !== '' && $demographics->hieofone_as_url !== null) {
            $data['content'] .= '<div class="alert alert-danger"><span style="margin-right:15px;"><i class="fa fa-download fa-lg"></i></span><strong><a href="' . route('uma_resources', [Session::get('pid')]) . '">' . trans('noshform.reconcile_chart') . '</a></strong></div>';
        }
        if (!empty($demographics_plus->date_added)) {
            $data['content'] .= '<div class="alert alert-success"><span style="margin-right:15px;"><i class="fa fa-user fa-lg" aria-hidden="true"></i></span><strong>' . trans('noshform.date_added') . '</strong>: ' . date('F jS, Y', strtotime($demographics_plus->date_added)) . '</div>';
        } else {
            $demographics_plus_data['date_added'] = $demographics->date;
            if ($demographics_plus) {
                DB::table('demographics_plus')->where('pid', '=', Session::get('pid'))->update($demographics_plus_data);
                $this->audit('Update');
            } else {
                $demographics_plus_data['pid'] = Session::get('pid');
                DB::table('demographics_plus')->insert($demographics_plus_data);
                $this->audit('Add');
            }
            $data['content'] .= '<div class="alert alert-success"><span style="margin-right:15px;"><i class="fa fa-user fa-lg" aria-hidden="true"></i></span><strong>' . trans('noshform.date_added') . '</strong>: ' . date('F jS, Y', strtotime($demographics->date)) . '</div>';
        }
        if (Session::get('patient_centric') !== 'y') {
            $next_appt = DB::table('schedule')->where('pid', '=', Session::get('pid'))->where('start', '>', time())->first();
            if ($next_appt && isset($next_appt->start)) {
                $data['content'] .= '<div class="alert alert-success"><a href="' . route('schedule') . '" class="nosh-schedule" nosh-schedule-date="' . date('Y-m-d', $next_appt->start) . '"><span style="margin-right:15px;"><i class="fa fa-hand-o-right fa-lg"></i></span><strong>' . trans('noshform.next_appt') . '</strong>: ' . date('F jS, Y, g:i A', $next_appt->start) . '</a></div>';
            }
            $last_visit = DB::table('encounters')->where('pid', '=', Session::get('pid'))
    			->where('eid', '!=', '')
    			->where('practice_id', '=', Session::get('practice_id'))
    			->orderBy('eid', 'desc')
    			->first();
    		if ($last_visit) {
                $data['content'] .= '<div class="alert alert-success"><span style="margin-right:15px;"><i class="fa fa-calendar-check-o fa-lg" aria-hidden="true"></i></span><strong>' . trans('noshform.last_visit') . '</strong>: ' . date('F jS, Y', strtotime($last_visit->encounter_DOS)) . '</div>';
    		}
            $lmc = DB::table('schedule')->where('pid', '=', Session::get('pid'))->where('status', '=', 'LMC')->orderBy('start', 'desc')->get();
            $dnka = DB::table('schedule')->where('pid', '=', Session::get('pid'))->where('status', '=', 'DNKA')->get();
            if ($lmc->count()) {
                $lmc_last = DB::table('schedule')->where('pid', '=', Session::get('pid'))->where('status', '=', 'LMC')->orderBy('start', 'desc')->first();
                $data['content'] .= '<div class="alert alert-warning"><span style="margin-right:15px;"><i class="fa fa-clock-o fa-lg"></i></span><strong>' . trans('noshform.lmc') . ': ' . $lmc->count() . '</strong><br><strong>' . trans('noshform.lmc_last') . ':</strong> ' . date('F jS, Y', $lmc_last->start) . '</div>';
            }
            if ($dnka->count()) {
                $data['content'] .= '<div class="alert alert-danger"><span style="margin-right:15px;"><i class="fa fa-ban fa-lg"></i></span><strong>' . trans('noshform.dnka') . ': ' . $dnka->count() . '</strong></div>';
            }
        }
        $arr = $this->timeline();
        $data['content'] .= '<h4 style="text-align:center;">' . trans('noshform.timeline') . '</h4>';
        if (count($arr['json']) <1) {
            $data['content'] .= '<div class="alert alert-success"><p><span style="margin-right:15px;"><i class="fa fa-star-o fa-lg"></i></span><strong>' . trans('noshform.account_created') . '!</strong></p>';
            if (Session::get('patient_centric') == 'y') {
                if ($demographics->photo == null || $demographics->photo == '') {
                    $data['content'] .= '<p>' . trans('noshform.photo_rec1') . ' <a href="' . route("demographics_add_photo") . '">' . trans('noshform.photo_rec2') . '</a> ' . trans('noshform.photo_rec3') . '.</p>';
                }
            }
            $data['content'] .='</div>';
        } else {
            $data['content'] .= '<section id="cd-timeline" class="cd-container">';
            foreach ($arr['json'] as $item) {
                $data['content'] .= $item['div'];
            }
            $data['content'] .= '</section>';
        }
        // $data['template_content'] = 'test';
        $data['title'] = Session::get('ptname');
        $data['panel_header'] = Session::get('ptname');
        if ($demographics->nickname !== '' && $demographics->nickname !== null) {
            $data['panel_header'] .= ' (' . $demographics->nickname . ')';
        }
        $data['panel_header'] .= ', ' . Session::get('age') . ', ' . ucfirst(Session::get('gender'));
        $edit = $this->access_level('2');
        $edit1 = $this->access_level('1');
        if ($edit == true) {
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.new_encounter'),
                'icon' => 'fa-stethoscope',
                'url' => route('encounter_details', ['0'])
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.new_t_message'),
                'icon' => 'fa-phone',
                'url' => route('chart_form', ['t_messages', 't_messages_id', '0'])
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.new_letter'),
                'icon' => 'fa-pencil-square-o',
                'url' => route('document_letter')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.new_tests'),
                'icon' => 'fa-flask',
                'url' => route('chart_form', ['tests', 'tests_id', '0'])
            ];
        }
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.new_alert'),
            'icon' => 'fa-exclamation-triangle',
            'url' => route('chart_form', ['alerts', 'alert_id', '0'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.new_document'),
            'icon' => 'fa-file-o',
            'url' => route('document_upload')
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_patient_photo'),
            'icon' => 'fa-camera',
            'url' => route('demographics_add_photo')
        ];
        $items[] = [
            'type' => 'separator'
        ];
        if ($edit1 == true) {
            $messaging = $this->patient_is_user(Session::get('pid'));
            if ($messaging['status'] == 'yes') {
                Session::put('messaging_patient', $messaging);
                $items[] = [
                    'type' => 'item',
                    'label' => trans('noshform.new_patient_messaging'),
                    'icon' => 'fa-envelope',
                    'url' => route('core_form', ['messaging', 'message_id', '0'])
                ];
            }
        }
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.new_coordination_care'),
            'icon' => 'fa-print',
            'url' => route('chart_form', ['hippa', 'hippa_id', '0'])
        ];
        $dropdown_array = [
            'items_button_icon' => 'fa-tasks',
            'items' => $items
        ];
        if (Session::has('uma_uri') && Session::get('group_id') == '100') {
            $dropdown_array1 = [];
            $dropdown_array1['default_button_text'] = '<i class="fa fa-table fa-fw fa-btn"></i>' . trans('noshform.consent_table');
            $dropdown_array1['default_button_text_url'] = Session::get('uma_uri');
            $dropdown_array1['class'] = 'btn-success';
            $dropdown_array1['new_window'] = true;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array1) . '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array);
        } else {
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        }
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function patient_data_export(Request $request)
    {
        if (Session::get('patient_centric') == 'y' || Session::get('patient_centric') == 'yp') {
            ini_set('memory_limit','196M');
            ini_set('max_execution_time', 300);
            $zip_file_name = time() . '_nosh_patient_data_export.zip';
            $zip_file = public_path() . '/temp/' . $zip_file_name;
            $zip = new ZipArchive;
            $zip->open($zip_file, ZipArchive::CREATE);
            $documents_dir = Storage::path('');
            $sqlfilename = time() . '_noshexport.sql';
            $sqlfile = $documents_dir . $sqlfilename;
            $command = "mysqldump -h " . env('DB_HOST') . " -u " . env('DB_USERNAME') . " -p". env('DB_PASSWORD') . " " . env('DB_DATABASE') . " > " . $sqlfile;
            system($command);
            if (!file_exists($sqlfile)) {
                sleep(2);
            }
            $zip->addFile($sqlfile, $sqlfilename);
            $rootPath = realpath($documents_dir . Session::get('pid'));
            if (file_exists($rootPath)) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($files as $name => $file) {
                    if(in_array(substr($file, strrpos($file, '/')+1), ['.', '..'])) {
                        continue;
                    } else {
                        if (is_dir($file) === true) {
                            continue;
                        } else {
                            $filePath = $file->getRealPath();
                            $localPath = str_replace($documents_dir,'/',$filePath);
                            if ($filePath != '' && file_exists($filePath)) {
                                $zip->addFile($filePath,$localPath);
                            }
                        }
                    }
                }
            }
            $zip->close();
            Session::forget('database_export');
            $headers = [
                'Set-Cookie' => 'fileDownload=true; path=/',
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'max-age=60, must-revalidate',
                'Content-Disposition' => 'attachment; filename="' . $zip_file_name . '"'
            ];
            return response()->download($zip_file, $zip_file_name, $headers);
        } else {
            Session::put('message_action', trans('noshform.not_allowed'));
            return response()->route('patient');
        }
    }

    public function payors_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('insurance')->where('pid', '=', Session::get('pid'))->orderBy('insurance_order', 'asc');
        $cc_active = false;
        $query1 = DB::table('demographics')->select('creditcard_number', 'creditcard_expiration', 'creditcard_type', 'creditcard_key')->where('pid', '=', Session::get('pid'))->first();
        if ($query1->creditcard_key !== '' && $query1->creditcard_key !== null) {
            $cc_active = true;
        }
        if ($type == 'active') {
            $query->where('insurance_plan_active', '=', 'Yes');
            $dropdown_array = [
                'items_button_text' => trans('noshform.active')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.inactive'),
                'icon' => 'fa-times',
                'url' => route('payors_list', ['inactive'])
            ];
        } else {
            $query->where('insurance_plan_active', '!=', 'Yes');
            $dropdown_array = [
                'items_button_text' => trans('noshform.inactive')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.active'),
                'icon' => 'fa-check',
                'url' => route('payors_list', ['active'])
            ];
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $columns = Schema::getColumnListing('insurance');
        $row_index = $columns[0];
        // Medicare Bluebutton
        $base_url = 'https://api.bluebutton.cms.gov';
        $connected1 = DB::table('refresh_tokens')->where('practice_id', '=', '1')->where('endpoint_uri', '=', $base_url)->first();
        $arr_medicare['label'] = '<b>' . trans('noshform.connected_medicare') . '</b>';
        $arr_medicare['edit'] = route('cms_bluebutton');
        $arr_medicare['delete'] = route('cms_bluebutton_disconnect');
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . $row->insurance_order . '</b> - ' . $row->insurance_plan_name . ' - ' . $row->insurance_id_num;
                $arr['edit'] = route('chart_form', ['insurance', $row_index, $row->$row_index]);
                if ($type == 'active') {
                    $arr['inactivate'] = route('chart_action', ['table' => 'insurance', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                } else {
                    $arr['reactivate'] = route('chart_action', ['table' => 'insurance', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                }
                $arr['delete'] = route('chart_action', ['table' => 'insurance', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                $list_array[] = $arr;
            }
            if ($cc_active == true && $type == 'active') {
                // $key = 'base64:kF2yXMGR9U2tnqJwatRigQLOjZhNDXMCTYXIDwdoXiw=';
                $arr1['label'] = '<b>' . trans('noshform.credit_card') . '</b> - ' . $query1->creditcard_type . ' - ' . decrypt($query1->creditcard_number);
                $arr1['edit'] = route('chart_form', ['demographics', 'pid', Session::get('pid'), 'cc']);
                $list_array[] = $arr1;
            }
            if ($connected1) {
                $list_array[] = $arr_medicare;
            }
            $return .= $this->result_build($list_array, 'payors_list');
        } else {
            $list_array1 = [];
            if ($type == 'active' && $cc_active == true) {
                $arr2['label'] = '<b>' . trans('noshform.credit_card') . '</b> - ' . $query1->creditcard_type . ' - ' . decrypt($query1->creditcard_number);
                $arr2['edit'] = route('chart_form', ['demographics', 'pid', Session::get('pid'), 'cc']);
                $list_array1[] = $arr2;
            }
            if ($connected1) {
                $list_array1[] = $arr_medicare;
            }
            $return .= $this->result_build($list_array1, 'payors_list');
        }
        $data['content'] = $return;
        $data['payors_active'] = true;
        $data['panel_header'] = trans('nosh.payors_list');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        $dropdown_array1 = [
            'items_button_icon' => 'fa-plus'
        ];
        $items1 = [];
        $items1[] = [
            'type' => 'item',
            'label' => trans('noshform.add_insurance'),
            'icon' => 'fa-plus',
            'url' => route('core_form', ['addressbook', 'address_id', '0', 'Insurance'])
        ];
        if ($cc_active == true) {
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_credit_card'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['demographics', 'pid', Session::get('pid'), 'cc'])
            ];
        }
        $items1[] = [
            'type' => 'item',
            'label' => trans('nosh.medicare_connect'),
            'icon' => 'fa-plug',
            'url' => route('cms_bluebutton')
        ];
        $dropdown_array1['items'] = $items1;
        $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function print_action($action, $id, $pid, $subtype='')
    {
        if ($action == 'rx_list') {
            $data['medications_active'] = true;
            $data['panel_header'] = trans('noshform.print_prescription');
            $data['print_now'] = route('print_medication', [$id, $pid]);
        }
        if ($action == 'orders') {
            $data['orders_active'] = true;
            $data['panel_header'] = trans('noshform.print_order');
            $data['print_now'] = route('print_orders', [$id, $pid]);
        }
        if ($action == 'hippa') {
            $data['records_active'] = true;
            $data['panel_header'] = trans('noshform.print_chart');
            $data['print_now'] = route('print_chart_action', [$id, $subtype]);
        }
        if ($action == 'hippa_request') {
            $data['records_active'] = true;
            $data['panel_header'] = trans('noshform.print_records_release');
            $data['print_now'] = route('print_chart_request', [$id, $pid]);
        }
        $data['content'] = '<div class="form-group"><div class="col-md-6 col-md-offset-3">';
        $data['content'] .= '<a href="' . Session::get('last_page') . '" class="btn btn-success btn-block nosh-no-load"><i class="fa fa-btn fa-check"></i> ' . trans('noshform.printing_finished') . '</a>';
        $data['content'] .= '<a href="' . $data['print_now'] . '" class="btn btn-info btn-block nosh-no-load"><i class="fa fa-btn fa-print"></i> ' . trans('noshform.reprint') . '</a></div></div>';
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function print_chart_action($hippa_id, $type)
    {
        $file_path = $this->print_chart($hippa_id, Session::get('pid'), $type);
        return response()->download($file_path);
    }

    public function orders_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('orders')->where('pid', '=', Session::get('pid'))->where($type, '!=', '')->orderBy('orders_date', 'desc');
        $type_arr = [
            'orders_labs' => [trans('noshform.laboratory'), 'fa-flask'],
            'orders_radiology' => [trans('noshform.imaging'), 'fa-film'],
            'orders_cp' => [trans('noshform.cardiopulmonary'), 'fa-heartbeat'],
            'orders_referrals' => [trans('noshform.referrals'), 'fa-hand-o-right']
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('orders_list', [$key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('2');
        $columns = Schema::getColumnListing('orders');
        $row_index = $columns[0];
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->orders_date)) . '</b> - ' . $row->{$type};
                if ($type == 'orders_referrals') {
                    $address = DB::table('addressbook')->where('address_id', '=', $row->address_id)->first();
                    $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->orders_date)) . '</b> - ' . $address->specialty . ': ' . $address->displayname;
                }
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['orders', $row_index, $row->$row_index, $type]);
                    $arr['complete'] = route('chart_action', ['table' => 'orders', 'action' => 'complete', 'index' => $row_index, 'id' => $row->$row_index]);
                    $arr['delete'] = route('chart_action', ['table' => 'orders', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'orders_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['orders_active'] = true;
        $data['panel_header'] = trans('noshform.pending_orders');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_lab_order'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_labs'])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_imaging_order'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_radiology'])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_cardiopulmonary_order'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_cp'])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_referral'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_referrals'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function records_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        if ($type == 'release') {
            $table = 'hippa';
            $query = DB::table($table)->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->where('other_hippa_id', '=', '0')->orderBy('hippa_date_release', 'desc');
            $dropdown_array = [
                'items_button_text' => trans('noshform.hippas')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.hippa_requests'),
                'icon' => 'fa-arrow-down',
                'url' => route('records_list', ['request'])
            ];
        } else {
            $table ='hippa_request';
            $query = DB::table($table)->where('pid', '=', Session::get('pid'))->where('practice_id', '=', Session::get('practice_id'))->orderBy('hippa_date_request', 'desc');
            $dropdown_array = [
                'items_button_text' => trans('noshform.hippa_requests')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.hippas'),
                'icon' => 'fa-arrow-up',
                'url' => route('records_list', ['release'])
            ];
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('7');
        $columns = Schema::getColumnListing($table);
        $row_index = $columns[0];
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                if ($type == 'release') {
                    $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->hippa_date_release)) . '</b> - ' . $row->hippa_provider . ' - ' . $row->hippa_reason;
                } else {
                    $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->hippa_date_request)) . '</b> - ' . $row->request_to . ' - ' . $row->request_reason;
                    if ($row->received == 'Yes') {
                        $arr['label_class'] = 'list-group-item-success nosh-result-list';
                    }
                }
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', [$table, $row_index, $row->$row_index]);
                    if ($type == 'request') {
                        if ($row->received == 'No') {
                            $arr['complete'] = route('chart_action', ['table' => $table, 'action' => 'complete', 'index' => $row_index, 'id' => $row->$row_index]);
                        }
                    }
                    $arr['delete'] = route('chart_action', ['table' => $table, 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'records_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['panel_header'] = trans('noshform.coordination_of_care');
        $data['content'] = $return;
        $data['records_active'] = true;
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add') . ' ' . trans('noshform.hippa'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['hippa', $row_index, '0'])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add') . ' ' . trans('noshform.hippa_request'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['hippa_request', $row_index, '0'])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.upload_ccda'),
                'icon' => 'fa-upload',
                'url' => route('upload_ccda')
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.upload_ccr'),
                'icon' => 'fa-upload',
                'url' => route('upload_ccr')
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        Session::put('last_page', $request->fullUrl());
        if (Session::has('download_ccda')) {
            $data['download_now'] = Session::get('download_ccda');
        }
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function register_patient(Request $request)
    {
        $pid = Session::get('pid');
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';
        for ($i = 0; $i < 6; $i++) {
            $token .= $characters[mt_rand(0, strlen($characters)-1)];
        }
        $data['registration_code'] = $token;
        DB::table('demographics')->where('pid', '=', $pid)->update($data);
        $this->audit('Update');
        $result = DB::table('demographics')->where('pid', '=', $pid)->first();
        if ($result->email != '') {
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $data1 = [
                'practicename' => $practice->practice_name,
                'url' => route('dashboard'),
                'token' => $token
            ];
            $this->send_mail('emails.loginregistrationcode', $data1, 'Patient Portal Registration Code', $result->email, Session::get('practice_id'));
        }
        $message =  trans('noshform.registration_code') . ": " . $token;
        if (Session::has('message_action')) {
            $message = Session::get('message_action') . '<br>' . $message;
        }
        Session::put('message_action', $message);
        return redirect(Session::get('last_page'));
    }

    public function results_chart(Request $request, $id)
    {
        $pid = Session::get('pid');
        $demographics = DB::table('demographics')->where('pid', '=', $pid)->first();
        $row0 = DB::table('tests')->where('tests_id', '=', $id)->first();
        // $data['patient'] = [];
        $data['graph_y_title'] = $row0->test_units;
        $data['graph_x_title'] = 'Date';
        $data['graph_series_name'] = $row0->test_name;
        $data['graph_title'] = trans('noshform.results_chart1') . ' ' . $row0->test_name . ' ' . trans('noshform.results_chart2') . ' ' . $demographics->firstname . ' ' . $demographics->lastname . ' ' . trans('noshform.as_of') . ' ' . date("Y-m-d, g:i a", time());
        $query1 = DB::table('tests')
            ->where('test_name', '=', $row0->test_name)
            ->where('pid', '=', $pid)
            ->orderBy('test_datetime', 'asc')
            ->get();
        $json = [];
        if ($query1->count()) {
            foreach ($query1 as $row1) {
                $json[] = [
                    $row1->test_datetime,
                    $row1->test_result
                ];
            }
        }
        $edit = $this->access_level('2');
        $dropdown_array = [];
        $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
        $dropdown_array['default_button_text_url'] = Session::get('last_page');
        $items = [];
        if ($edit == true) {
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.edit'),
                'icon' => 'fa-pencil',
                'url' => route('chart_form', ['tests', 'tests_id', $id])
            ];
        }
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.print'),
            'icon' => 'fa-print',
            'url' => route('results_print', [$id])
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['graph_data'] = json_encode($json);
        $data['graph_type'] = 'data-to-time';
        $data['results_active'] = true;
        $data['panel_header'] = $row0->test_name;
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('graph');
        $data['assets_css'] = $this->assets_css();
        return view('graph', $data);
    }

    public function results_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('tests')->where('pid', '=', Session::get('pid'))->where('test_type', '=', $type)->orderBy('test_datetime', 'desc');
        $type_arr = [
            'Laboratory' => [trans('noshform.laboratory'), 'fa-flask'],
            'Imaging' => [trans('noshform.imaging'), 'fa-film'],
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('results_list', [$key])
                ];
            }
        }
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.vital_signs'),
            'icon' => 'fa-eye',
            'url' => route('encounter_vitals_view')
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('2');
        $columns = Schema::getColumnListing('tests');
        $row_index = $columns[0];
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->test_datetime)) . '</b> - ' . $row->test_name;
                $arr['view'] = route('results_view', [$row->$row_index]);
                $arr['chart'] = route('results_chart', [$row->$row_index]);
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['tests', $row_index, $row->$row_index]);
                    $arr['delete'] = route('chart_action', ['table' => 'tests', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'results_list', false, true);
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['results_active'] = true;
        $data['panel_header'] = trans('noshform.results');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_result'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['tests', 'tests_id', '0'])
            ];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.results_reply'),
                'icon' => 'fa-reply',
                'url' => route('results_reply')
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function results_print(Request $request, $id)
    {
        ini_set('memory_limit','196M');
        $html = $this->page_results_list($id)->render();
        $user_id = Session::get('user_id');
        $file_path = public_path() . "/temp/" . time() . "_results_list_" . $user_id . ".pdf";
        $this->generate_pdf($html, $file_path);
        while(!file_exists($file_path)) {
            sleep(2);
        }
        return response()->download($file_path);
    }

    public function results_reply(Request $request)
    {
        if ($request->isMethod('post')) {
            $pid = Session::get('pid');
            $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
            $file_path = Storage::path($pid . '/letter_' . time() . '.pdf');
            $tests_performed = $request->input('tests_performed');
            $body = '';
            if (! empty($tests_performed)) {
                if (Session::has('patient_locale')) {
                    App::setLocale(Session::get('patient_locale'));
                }
                $body .= trans('noshform.results_reply1') . ': ';
                foreach ($tests_performed as $test) {
                    $data1['alert_date_complete'] = date('Y-m-d H:i:s');
                    $orders_query = DB::table('alerts')->where('alert_id', '=', $test)->first();
                    if ($orders_query->orders_id != '') {
                        $data2['orders_completed'] = 'Yes';
                        DB::table('orders')->where('orders_id', '=', $orders_query->orders_id)->update($data2);
                        $this->audit('Update');
                        $orders_query1 = DB::table('orders')->where('orders_id', '=', $orders_query->orders_id)->first();
                        if ($orders_query1->orders_labs !== '') {
                            $body .= "\n" . $orders_query1->orders_labs;
                        }
                        if ($orders_query1->orders_radiology !== '') {
                            $body .= "\n" . $orders_query1->orders_radiology;
                        }
                        if ($orders_query1->orders_cp !== '') {
                            $body .= "\n" . $orders_query1->orders_cp;
                        }
                    }
                    DB::table('alerts')->where('alert_id', '=', $test)->update($data1);
                    $this->audit('Update');
                }
            }
            $body .= "\n" . $request->input('message');
            if ($request->input('followup') !== '') {
                $body .= "\n" . trans('noshform.results_reply2') . ': ' . $request->input('followup');
            }
            if ($request->input('action') == 'letter') {
                $html = $this->page_letter_reply($body)->render();
                $this->generate_pdf($html, $file_path, 'footerpdf', '', '2');
                while (!file_exists($file_path)) {
                    sleep(2);
                }
                App::setLocale(Session::get('practice_locale'));
                $pages_data = [
                    'documents_url' => $file_path,
                    'pid' => $pid,
                    'documents_type' => 'Letters',
                    'documents_desc' => trans('noshform.test_results_letter') . ' ' . Session::get('ptname'),
                    'documents_from' => Session::get('displayname'),
                    'documents_viewed' => Session::get('displayname'),
                    'documents_date' => date('Y-m-d H:i:s', time())
                ];
                $arr['id'] = DB::table('documents')->insertGetId($pages_data, 'documents_id');
                $this->audit('Add');
                App::setLocale(Session::get('user_locale'));
                $message = trans('noshform.letter_generated');
            }
            if ($request->input('action') == 'portal') {
                App::setLocale(Session::get('user_locale'));
                $patient = DB::table('demographics')->where('pid', '=', $pid)->first();
                $row_relate = DB::table('demographics_relate')
                    ->where('pid', '=', $pid)
                    ->where('practice_id', '=', Session::get('practice_id'))
                    ->first();
                $data_message = [
                    'displayname' => Session::get('displayname'),
                    'email' => $practice->email,
                    'patient_portal' => $practice->patient_portal
                ];
                if ($row_relate->id == '') {
                    if ($row->email == '') {
                        $message = trans('noshform.error') . ' - ' . trans('noshform.no_email');
                    } else {
                        $data_message['portal'] = false;
                        if (Session::has('patient_locale')) {
                            App::setLocale(Session::get('patient_locale'));
                        } else {
                            App::setLocale(Session::get('practice_locale'));
                        }
                        $mail = $this->send_mail('emails.newresult', $data_message, trans('noshform.test_results_available'), $patient->email, Session::get('practice_id'));
                        App::setLocale(Session::get('user_locale'));
                        if (!$mail) {
                            $message = Session::get('message_action');
                            Session::forget('message_action');
                        } else {
                            $message = trans('noshform.email_sent');
                        }
                    }
                } else {
                    $from = Session::get('user_id');
                    $patient_name = $patient->lastname . ', ' . $patient->firstname . ' (DOB: ' . date('m/d/Y', strtotime($patient->DOB)) . ') (ID: ' . $pid . ')';
                    $patient_name1 = $patient->lastname . ', ' . $patient->firstname . ' (ID: ' . $pid . ')';
                    $body .= "\n" . trans('noshform.results_reply3') . "\n\n" . trans('noshform.results_reply4') . "\n" . Session::get('displayname');
                    if (Session::has('patient_locale')) {
                        App::setLocale(Session::get('patient_locale'));
                    } else {
                        App::setLocale(Session::get('practice_locale'));
                    }
                    $data = [
                        'pid' => $pid,
                        'patient_name' => $patient_name,
                        'message_to' => $patient_name1,
                        'cc' => '',
                        'message_from' => $from,
                        'subject' => trans('noshform.your_test_results'),
                        'body' => $body,
                        'status' => 'Sent',
                        'mailbox' => $row_relate->id,
                        'practice_id' => Session::get('practice_id')
                    ];
                    DB::table('messaging')->insert($data);
                    $this->audit('Add');
                    $data1a = [
                        'pid' => $pid,
                        'patient_name' => $patient_name,
                        'message_to' => $patient_name1,
                        'cc' => '',
                        'message_from' => $from,
                        'subject' => trans('noshform.your_test_results'),
                        'body' => $body,
                        'status' => 'Sent',
                        'mailbox' => '0',
                        'practice_id' => Session::get('practice_id')
                    ];
                    DB::table('messaging')->insert($data1a);
                    $this->audit('Add');
                    if ($patient->email == '') {
                        App::setLocale(Session::get('user_locale'));
                        $message = trans('noshform.internal_message_sent');
                    } else {
                        $data_message['portal'] = true;
                        $mail1 = $this->send_mail('emails.newresult', $data_message, trans('noshform.test_results_available'), $patient->email, Session::get('practice_id'));
                        App::setLocale(Session::get('user_locale'));
                        if (!$mail1) {
                            $message = Session::get('message_action');
                            Session::forget('message_action');
                        } else {
                            $message = trans('noshform.internal_message_sent_notify');
                        }
                    }
                }
            }
            Session::put('message_action', $message);
            return redirect(Session::get('last_page'));
        } else {
            $tests_arr = [];
            $query = DB::table('alerts')
                ->where('pid', '=', Session::get('pid'))
                ->where('practice_id', '=', Session::get('practice_id'))
                ->whereNull('alert_date_complete')
                ->where('alert_reason_not_complete', '=', '')
                ->where(function($query_array1) {
                    $query_array1->where('alert', '=', 'Laboratory results pending')
                    ->orWhere('alert', '=', 'Radiology results pending')
                    ->orWhere('alert', '=', 'Cardiopulmonary results pending')
                    ->orWhere('results', '=', 1);
                })
                ->get();
            if ($query->count()) {
                foreach ($query as $item) {
                    $orders_query2 = DB::table('orders')->where('orders_id', '=', $item->orders_id)->first();
                    if ($orders_query2) {
                        if ($orders_query2->orders_labs !== '') {
                            $test_desc = $orders_query2->orders_labs;
                        }
                        if ($orders_query2->orders_radiology !== '') {
                            $test_desc = $orders_query2->orders_radiology;
                        }
                        if ($orders_query2->orders_cp !== '') {
                            $test_desc = $orders_query2->orders_cp;
                        }
                        App::setLocale(Session::get('practice_locale'));
                        $tests_arr[$item->alert_id] = str_replace(' ' . trans('noshform.results_pending'), ': ', $item->alert) . $test_desc;
                        App::setLocale(Session::get('user_locale'));
                    }
                }
            }
            $items[] = [
                'name' => 'tests_performed[]',
                'label' => trans('noshform.tests_performed'),
                'type' => 'select',
                'select_items' => $tests_arr,
                'multiple' => true,
                'required' => true,
                'selectpicker' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'message',
                'label' => trans('noshform.tests_message'),
                'type' => 'textarea',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'followup',
                'label' => trans('noshform.followup'),
                'type' => 'text',
                'default_value' => null
            ];
            $items[] = [
                'name' => 'action',
                'label' => trans('noshform.nosh_action'),
                'type' => 'select',
                'select_items' => ['portal' => trans('noshform.send_portal'), 'letter' => trans('noshform.send_letter')],
                'required' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'results_reply_form',
                'action' => route('results_reply'),
                'items' => $items,
                'origin' => Session::get('last_page')
            ];
            $data['content'] = $this->form_build($form_array);
            $data['panel_header'] = trans('noshform.results_reply_header');
            $data['alerts_active'] = true;
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function results_view(Request $request, $id)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $test_arr = $this->array_test_flag();
        $test = DB::table('tests')->where('tests_id', '=', $id)->first();
        $return = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>' . trans('noshform.date') . '</th><th>' . trans('noshform.test_result') . '</th><th>' . trans('noshform.unit') . '</th><th>' . trans('noshform.range') . '</th><th>' . trans('noshform.test_flags') . '</th></thead><tbody>';
        // Get old results for comparison table
        $query = DB::table('tests')
            ->where('test_name', '=', $test->test_name)
            ->where('pid', '=', Session::get('pid'))
            ->orderBy('test_datetime', 'desc')
            ->get();
        if ($query->count()) {
            foreach ($query as $row) {
                $return .= '<tr';
                $class_arr = [];
                if ($id == $row->tests_id) {
                    $class_arr[] = 'nosh-table-active';
                }
                if ($row->test_flags == "HH" || $row->test_flags == "LL" || $row->test_flags == "H" || $row->test_flags == "L") {
                    $class_arr[] = 'danger';
                }
                if (!empty($class_arr)) {
                    $return .= ' class="' . implode(' ', $class_arr) . '"';
                }
                $return .= '><td>' . date('Y-m-d', $this->human_to_unix($row->test_datetime)) . '</td>';
                $return .= '<td>' . $row->test_result . '</td>';
                $return .= '<td>' . $row->test_units . '</td>';
                $return .= '<td>' . $row->test_reference . '</td>';
                $return .= '<td>' . $test_arr[$row->test_flags] . '</td></tr>';
            }
        }
        $return .= '</tbody></table></div>';
        $edit = $this->access_level('2');
        $dropdown_array = [];
        $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
        $dropdown_array['default_button_text_url'] = Session::get('last_page');
        $items = [];
        if ($edit == true) {
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.edit'),
                'icon' => 'fa-pencil',
                'url' => route('chart_form', ['tests', 'tests_id', $id])
            ];
        }
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.chart'),
            'icon' => 'fa-line-chart',
            'url' => route('results_chart', [$id])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.print'),
            'icon' => 'fa-print',
            'url' => route('results_print', [$id])
        ];
        $items[] = [
            'type' => 'separator'
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_lab_order'),
            'icon' => 'fa-thumbs-o-up',
            'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_labs'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_imaging_order'),
            'icon' => 'fa-thumbs-o-up',
            'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_radiology'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_cardiopulmonary_order'),
            'icon' => 'fa-thumbs-o-up',
            'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_cp'])
        ];
        $items[] = [
            'type' => 'item',
            'label' => trans('noshform.add_referral'),
            'icon' => 'fa-thumbs-o-up',
            'url' => route('chart_form', ['orders', 'orders_id', '0', 'orders_referrals'])
        ];
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = $return;
        $data['results_active'] = true;
        $data['panel_header'] = $test->test_name;
        $data = array_merge($data, $this->sidebar_build('chart'));
        if (Session::has('eid')) {
            if (Session::get('group_id') == '2') {
                // Mark conditions list as reviewed by physician
                $results_encounter = $test->test_name . ': ' .  $test->test_result . ' ' .  $test->test_units . '; ' . trans('noshform.reference') . ': ' . $test->test_reference . '; ' . trans('noshform.flags') . ': ' . $test_arr[$test->test_flags];
                $results_encounter .= '; ' . trans('noshform.performed_on') . ': ' . date('Y-m-d', $this->human_to_unix($test->test_datetime));
                $encounter_query = DB::table('other_history')->where('eid', '=', Session::get('eid'))->first();
                $encounter_data['oh_results'] = $results_encounter;
                if ($encounter_query) {
                    if ($encounter_query->oh_results !== null) {
                        $results_encounter_arr = explode("\n", $encounter_query->oh_results);
                        $results_encounter_arr[] = $results_encounter;
                        $encounter_data['oh_results'] = implode("\n", $results_encounter_arr);
                    } else {
                        $encounter_data['oh_results'] = $results_encounter;
                    }
                    $encounter_data['oh_date'] = date("Y-m-d H:i:s", time());
                    DB::table('other_history')->where('eid', '=', Session::get('eid'))->update($encounter_data);
                } else {
                    $encounter_data['oh_results'] = $results_encounter;
                    $encounter_data['eid'] = Session::get('eid');
                    $encounter_data['pid'] = Session::get('pid');
                    DB::table('other_history')->insert($encounter_data);
                }
                if ($data['message_action'] !== '') {
                    $data['message_action'] .= '<br>';
                }
                $data['message_action'] .= trans('noshform.results_encounter');
            }
        }
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function search_chart(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $edit = $this->access_level('2');
        $edit1 = $this->access_level('7');
        $return = '';
        if ($request->isMethod('post')) {
            $q = $request->input('search_chart');
            Session::put('search_chart', $q);
        } else {
            $q = Session::get('search_chart');
            Session::forget('search_chart');
        }
        $allergies = DB::table('allergies')
            ->where('pid', '=', Session::get('pid'))
            ->whereNull('allergies_date_inactive')
            ->where(function($allergies1) use ($q) {
                $allergies1->where('allergies_med', 'LIKE', "%$q%")
                ->orWhere('allergies_reaction', 'LIKE', "%$q%");
            })
            ->get();
        $issues = DB::table('issues')
            ->where('pid', '=', Session::get('pid'))
            ->whereNull('issue_date_inactive')
            ->where(function($issues1) use ($q) {
                $issues1->where('issue', 'LIKE', "%$q%")
                ->orWhere('notes', 'LIKE', "%$q%");
            })
            ->get();
        $rx = DB::table('rx_list')
            ->where('pid', '=', Session::get('pid'))
            ->whereNull('rxl_date_inactive')
            ->whereNull('rxl_date_old')
            ->where(function($rx1) use ($q) {
                $rx1->where('rxl_medication', 'LIKE', "%$q%")
                ->orWhere('rxl_sig', 'LIKE', "%$q%")
                ->orWhere('rxl_reason', 'LIKE', "%$q%")
                ->orWhere('rxl_instructions', 'LIKE', "%$q%");
            })
            ->get();
        $sup = DB::table('sup_list')
            ->where('pid', '=', Session::get('pid'))
            ->whereNull('sup_date_inactive')
            ->where(function($sup1) use ($q) {
                $sup1->where('sup_supplement', 'LIKE', "%$q%")
                ->orWhere('sup_sig', 'LIKE', "%$q%")
                ->orWhere('sup_reason', 'LIKE', "%$q%")
                ->orWhere('sup_instructions', 'LIKE', "%$q%");
            })
            ->get();
        $imm = DB::table('immunizations')
            ->where('pid', '=', Session::get('pid'))
            ->where(function($imm1) use ($q) {
                $imm1->where('imm_immunization', 'LIKE', "%$q%")
                ->orWhere('imm_sequence', 'LIKE', "%$q%")
                ->orWhere('imm_manufacturer', 'LIKE', "%$q%");
            })
            ->get();
        $orders = DB::table('orders')
            ->where('pid', '=', Session::get('pid'))
            ->where(function($orders1) use ($q) {
                $orders1->where('orders_labs', 'LIKE', "%$q%")
                ->orWhere('orders_radiology', 'LIKE', "%$q%")
                ->orWhere('orders_cp', 'LIKE', "%$q%")
                ->orWhere('orders_referrals', 'LIKE', "%$q%")
                ->orWhere('orders_notes', 'LIKE', "%$q%");
            })
            ->get();
        $encounters = DB::table('encounters')
            ->join('assessment', 'assessment.eid', '=', 'encounters.eid')
            ->join('image', 'image.eid', '=', 'encounters.eid')
            ->join('pe', 'pe.eid', '=', 'encounters.eid')
            ->join('plan', 'plan.eid', '=', 'encounters.eid')
            ->join('procedure', 'procedure.eid', '=', 'encounters.eid')
            ->join('ros', 'ros.eid', '=', 'encounters.eid')
            ->join('rx', 'rx.eid', '=', 'encounters.eid')
            ->join('vitals', 'vitals.eid', '=', 'encounters.eid')
            ->select('encounters.eid', 'encounters.encounter_DOS', 'encounters.encounter_type', 'encounters.encounter_cc', 'encounters.encounter_provider', 'encounters.encounter_template', 'encounters.encounter_signed')
            ->where('encounters.pid', '=', Session::get('pid'))
            ->where('encounters.addendum', '=', 'n')
            ->where(function($encounters1) use ($q) {
                $encounters1->where('encounters.encounter_type', 'LIKE', "%$q%")
                ->orWhere('encounters.encounter_cc', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_1', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_2', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_3', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_4', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_5', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_6', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_7', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_8', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_9', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_10', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_11', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_12', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_other', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_ddx', 'LIKE', "%$q%")
                ->orWhere('assessment.assessment_notes', 'LIKE', "%$q%")
                ->orWhere('image.image_description', 'LIKE', "%$q%")
                ->orWhere('pe.pe', 'LIKE', "%$q%")
                ->orWhere('plan.plan', 'LIKE', "%$q%")
                ->orWhere('plan.goals', 'LIKE', "%$q%")
                ->orWhere('plan.tp', 'LIKE', "%$q%")
                ->orWhere('procedure.proc_description', 'LIKE', "%$q%")
                ->orWhere('ros.ros', 'LIKE', "%$q%")
                ->orWhere('rx.rx_rx', 'LIKE', "%$q%")
                ->orWhere('rx.rx_supplements', 'LIKE', "%$q%")
                ->orWhere('rx.rx_immunizations', 'LIKE', "%$q%")
                ->orWhere('rx.rx_orders_summary', 'LIKE', "%$q%")
                ->orWhere('rx.rx_supplements_orders_summary', 'LIKE', "%$q%")
                ->orWhere('vitals.vitals_other', 'LIKE', "%$q%");
            })
            ->get();
        $notes = DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('imm_notes', 'LIKE', "%$q%")->get();
        $notes1 = DB::table('demographics_notes')->where('pid', '=', Session::get('pid'))->where('billing_notes', 'LIKE', "%$q%")->get();
        $documents = DB::table('documents')
            ->where('pid', '=', Session::get('pid'))
            ->where(function($documents1) use ($q) {
                $documents1->where('documents_desc', 'LIKE', "%$q%")
                ->orWhere('documents_from', 'LIKE', "%$q%");
            })
            ->get();
        $tests = DB::table('tests')
            ->where('pid', '=', Session::get('pid'))
            ->where(function($tests1) use ($q) {
                $tests1->where('test_name', 'LIKE', "%$q%")
                ->orWhere('test_from', 'LIKE', "%$q%");
            })
            ->get();
        $alerts = DB::table('alerts')
            ->where('pid', '=', Session::get('pid'))
            ->where('practice_id', '=', Session::get('practice_id'))
            ->whereNull('alert_date_complete')
            ->where('alert_reason_not_complete', '=', '')
            ->where(function($alerts1) use ($q) {
                $alerts1->where('alert', 'LIKE', "%$q%")
                ->orWhere('alert_description', 'LIKE', "%$q%");
            })
            ->get();
        $t_messages_query = DB::table('t_messages')->where('pid', '=', Session::get('pid'));
        if (Session::get('patient_centric') == 'n') {
            $t_messages_query->where('practice_id', '=', Session::get('practice_id'));
        }
        if (Session::get('group_id') == '100') {
            $t_messages_query->where('t_messages_signed', '=', 'Yes');
        }
        $t_messages = $t_messages_query->where(function($t_messages_query1) use ($q) {
            $t_messages_query1->where('t_messages_subject', 'LIKE', "%$q%")
            ->orWhere('t_messages_message', 'LIKE', "%$q%");
            })->get();
        $demographics = DB::table('demographics')
            ->where('pid', '=', Session::get('pid'))
            ->where(function($demographics1) use ($q) {
                $demographics1->where('firstname', 'LIKE', "%$q%")
                ->orWhere('lastname', 'LIKE', "%$q%")
                ->orWhere('nickname', 'LIKE', "%$q%")
                ->orWhere('race', 'LIKE', "%$q%")
                ->orWhere('ethnicity', 'LIKE', "%$q%")
                ->orWhere('language', 'LIKE', "%$q%")
                ->orWhere('employer', 'LIKE', "%$q%");
            })
            ->get();
        $demographics_a = DB::table('demographics')
            ->where('pid', '=', Session::get('pid'))
            ->where(function($demographics_a1) use ($q) {
                $demographics_a1->where('address', 'LIKE', "%$q%")
                ->orWhere('city', 'LIKE', "%$q%")
                ->orWhere('email', 'LIKE', "%$q%")
                ->orWhere('emergency_contact', 'LIKE', "%$q%");
            })
            ->get();
        $demographics_b = DB::table('demographics')
            ->where('pid', '=', Session::get('pid'))
            ->where(function($demographics_b1) use ($q) {
                $demographics_b1->where('guardian_firstname', 'LIKE', "%$q%")
                ->orWhere('guardian_lastname', 'LIKE', "%$q%")
                ->orWhere('guardian_address', 'LIKE', "%$q%")
                ->orWhere('guardian_city', 'LIKE', "%$q%");
            })
            ->get();
        $demographics_c = DB::table('demographics')
            ->where('pid', '=', Session::get('pid'))
            ->where(function($demographics_c1) use ($q) {
                $demographics_c1->where('preferred_pharmacy', 'LIKE', "%$q%")
                ->orWhere('comments', 'LIKE', "%$q%")
                ->orWhere('other1', 'LIKE', "%$q%")
                ->orWhere('other2', 'LIKE', "%$q%");
            })
            ->get();
        $tags = DB::table('tags')->where('tag', 'LIKE', "%$q%")->get();
        $encounters_arr = [];
        $t_messages_arr = [];
        $documents_arr = [];
        $tests_arr = [];
        if ($tags->count()) {
            foreach ($tags as $tag) {
                $tags_query = DB::table('tags_relate')->where('tags_id', '=', $tag->tags_id)->where('pid', '=', Session::get('pid'))->get();
                if ($tags_query->count()) {
                    foreach ($tags_query as $tags_row) {
                        if ($tags_row->eid !== null && $tags_row->eid !== '') {
                            $encounters_arr[] = $tags_row->eid;
                        }
                        if ($tags_row->t_messages_id !== null && $tags_row->t_messages_id !== '') {
                            $t_messages_arr[] = $tags_row->t_messages_id;
                        }
                        if ($tags_row->documents_id !== null && $tags_row->documents_id !== '') {
                            $documents_arr[] = $tags_row->documents_id;
                        }
                        if ($tags_row->tests_id !== null && $tags_row->tests_id !== '') {
                            $tests_arr[] = $tags_row->tests_id;
                        }
                    }
                }
            }
        }
        if ($allergies->count() || $issues->count() || $rx->count() || $sup->count() || $imm->count() || $orders->count() || $encounters->count() || $notes->count() || $notes1->count() || $documents->count() || $tests->count() || $alerts->count() || $t_messages->count() || $demographics->count() || $demographics_a->count() || $demographics_b->count() || $demographics_c->count() || ! empty($encounters_arr) || ! empty($t_messages_arr) || ! empty($documents_arr) || ! empty($tests_arr)) {
            $list_array = [];
            $encounter_type = $this->array_encounter_type();
            if ($encounters->count()) {
                foreach ($encounters as $encounters_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.encounter') . ' - ' . date('Y-m-d', $this->human_to_unix($encounters_row->encounter_DOS)) . '</b> - ' . $encounter_type[$encounters_row->encounter_template] . ' - ' . $encounters_row->encounter_cc . '<br>' . trans('noshform.provider') . ': ' . $encounters_row->encounter_provider;
                    if ($edit == true && $encounters_row->encounter_signed == 'No') {
                        $arr['edit'] = route('encounter', [$encounters_row->eid]);
                    }
                    $arr['view'] = route('encounter_view', [$encounters_row->eid]);
                    $list_array[] = $arr;
                }
            }
            if (! empty($encounters_arr)) {
                foreach ($encounters_arr as $encounters_item) {
                    $encounters_row1 = DB::table('encounters')->where('eid', '=', $encounters_item)->first();
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.tagged_encounter') . ' - ' . date('Y-m-d', $this->human_to_unix($encounters_row1->encounter_DOS)) . '</b> - ' . $encounter_type[$encounters_row1->encounter_template] . ' - ' . $encounters_row1->encounter_cc . '<br>' . trans('noshform.provider') . ': ' . $encounters_row1->encounter_provider;
                    if ($edit == true && $encounters_row1->encounter_signed == 'No') {
                        $arr['edit'] = route('encounter', [$encounters_row1->eid]);
                    }
                    $arr['view'] = route('encounter_view', [$encounters_row1->eid]);
                    $list_array[] = $arr;
                }
            }
            if ($issues->count()) {
                $issue_arr = [
                    'Problem List' => 'pl',
                    'Medical History' => 'mh',
                    'Surgical History' => 'sh'
                ];
                foreach ($issues as $issues_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . $issues_row->type . ' - </b>' . $issues_row->issue;
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['issues', 'issue_id', $issues_row->issue_id, $issue_arr[$issues_row->type]]);
                    }
                    if ($issues_row->reconcile !== null && $issues_row->reconcile !== 'y') {
                        $arr['danger'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
            if ($allergies->count()) {
                foreach ($allergies as $allergies_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.allergy') . ' - ' . $allergies_row->allergies_med . ' - ' . $allergies_row->allergies_reaction;
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['allergies', 'allergies_id', $allergies_row->allergies_id]);
                    }
                    if ($allergies_row->reconcile !== null && $allergies_row->reconcile !== 'y') {
                        $arr['danger'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
            if ($rx->count()) {
                foreach ($rx as $rx_row) {
                    $arr = [];
                    if ($rx_row->rxl_sig == '') {
                        $arr['label'] = '<b>' . trans('noshform.medication') . ' - </b><strong>' . $rx_row->rxl_medication . '</strong> ' . $rx_row->rxl_dosage . ' ' . $rx_row->rxl_dosage_unit . ', ' . $rx_row->rxl_instructions . ' ' . trans('noshform.for') . ' ' . $rx_row->rxl_reason;
                    } else {
                        $arr['label'] = '<b>' . trans('noshform.medication') . ' - </b><strong>' . $rx_row->rxl_medication . '</strong> ' . $rx_row->rxl_dosage . ' ' . $rx_row->rxl_dosage_unit . ', ' . $rx_row->rxl_sig . ', ' . $rx_row->rxl_route . ', ' . $rx_row->rxl_frequency;
                        $arr['label'] .= ' ' . trans('noshform.for')  . ' ' . $rx_row->rxl_reason;
                    }
                    $previous = DB::table('rx_list')
            			->where('pid', '=', Session::get('pid'))
            			->where('rxl_medication', '=', $rx_row->rxl_medication)
            			->select('rxl_date_prescribed', 'prescription')
                        ->orderBy('rxl_date_prescribed', 'desc')
            			->first();
                    if ($previous) {
                        if ($previous->rxl_date_prescribed !== null && $previous->rxl_date_prescribed !== null) {
                            $previous_date = new Date($this->human_to_unix($previous->rxl_date_prescribed));
                            $ago = $previous_date->diffInDays();
                            $arr['label'] .= '<br><strong>' . trans('noshform.last_prescribed') . ':</strong> ' . date('Y-m-d', $this->human_to_unix($previous->rxl_date_prescribed)) . ', ' . $ago . ' ' . trans('noshform.days_ago');
                            $arr['label'] .= '<br><strong>' . trans('noshform.prescription_status') . ':</strong> ' . ucfirst($previous->prescription);
                        }
                    }
                    if ($edit1 == true) {
                        $arr['edit'] = route('chart_form', ['rx_list', 'rxl_id', $rx_row->rxl_id]);
                        if (Session::get('group_id') == '2') {
                            $arr['refill'] = route('chart_form', ['rx_list', 'rxl_id', $rx_row->rxl_id, 'refill']);
                            $arr['eie'] = route('chart_action', ['table' => 'rx_list', 'action' => 'eie', 'index' => 'rxl_id', 'id' => $rx_row->rxl_id]);
                        }
                    }
                    if ($rx_row->reconcile !== null && $rx_row->reconcile !== 'y') {
                        $arr['danger'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
            if ($sup->count()) {
                foreach ($sup as $sup_row) {
                    $arr = [];
                    if ($sup_row->sup_sig == '') {
                        $arr['label'] = '<b>' . trans('noshform.supplement') . ' - </b>' . $sup_row->sup_supplement . ' ' . $sup_row->sup_dosage . ' ' . $sup_row->sup_dosage_unit . ', ' . $sup_row->sup_instructions . ' ' . trans('noshform.for') . ' ' . $sup_row->sup_reason;
                    } else {
                        $arr['label'] = '<b>' . trans('noshform.supplement') . ' - </b>' . $sup_row->sup_supplement . ' ' . $sup_row->sup_dosage . ' ' . $sup_row->sup_dosage_unit . ', ' . $sup_row->sup_sig . ', ' . $sup_row->sup_route . ', ' . $sup_row->sup_frequency;
                        $arr['label'] .= ' ' . trans('noshform.for') . ' ' . $sup_row->sup_reason;
                    }
                    if ($edit1 == true) {
                        $arr['edit'] = route('chart_form', ['sup_list', 'sup_id', $sup_row->sup_id]);
                    }
                    if ($sup_row->reconcile !== null && $sup_row->reconcile !== 'y') {
                        $arr['danger'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
            if ($imm->count()) {
                $seq_array = [
                    '1' => ', first',
                    '2' => ', second',
                    '3' => ', third',
                    '4' => ', fourth',
                    '5' => ', fifth'
                ];
                foreach ($imm as $imm_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.immunization') . ' - ' . $imm_row->imm_immunization . '</b> - ' . date('Y-m-d', $this->human_to_unix($imm_row->imm_date));
                    if (isset($imm_row->imm_sequence)) {
                        if (isset($seq_array[$imm_row->imm_sequence])) {
                            $arr['label'] = '<b>' . trans('noshform.immunization') . ' - ' . $imm_row->imm_immunization . $seq_array[$imm_row->imm_sequence]  . '</b> - ' . date('Y-m-d', $this->human_to_unix($imm_row->imm_date));
                        }
                    }
                    if ($edit1 == true) {
                        $arr['edit'] = route('chart_form', ['immunizations', 'imm_id', $imm_row->imm_id]);
                    }
                    if ($imm_row->reconcile !== null && $imm_row->reconcile !== 'y') {
                        $arr['danger'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
            if ($tests->count()) {
                foreach ($tests as $tests_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.test_result1') . ' - ' . date('Y-m-d', $this->human_to_unix($tests_row->test_datetime)) . '</b> - ' . $tests_row->test_name;
                    $arr['view'] = route('results_view', [$tests_row->tests_id]);
                    $arr['chart'] = route('results_chart', [$tests_row->tests_id]);
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['tests', 'tests_id', $tests_row->tests_id]);
                    }
                    $list_array[] = $arr;
                }
            }
            if (! empty($tests_arr)) {
                foreach ($tests_arr as $tests_item) {
                    $tests_row1 = DB::table('tests')->where('tests_id', '=', $tests_item)->first();
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.test_result2') . ' - ' . date('Y-m-d', $this->human_to_unix($tests_row1->test_datetime)) . '</b> - ' . $tests_row1->test_name;
                    $arr['view'] = route('results_view', [$tests_row1->tests_id]);
                    $arr['chart'] = route('results_chart', [$tests_row1->tests_id]);
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['tests', 'tests_id', $tests_row1->tests_id]);
                    }
                    $list_array[] = $arr;
                }
            }
            if ($documents->count()) {
                foreach ($documents as $documents_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.document') . ' - ' . date('Y-m-d', $this->human_to_unix($documents_row->documents_date)) . '</b> - ' . $documents_row->documents_desc . ' ' . trans('noshform.from') . ' ' . $documents_row->documents_from;
                    $arr['view'] = route('document_view', [$documents_row->documents_id]);
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['documents', 'documents_id', $documents_row->documents_id]);
                    }
                    if ($documents_row->reconcile !== null && $documents_row->reconcile !== 'y') {
                        $arr['danger'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
            if (! empty($documents_arr)) {
                foreach ($documents_arr as $documents_item) {
                    $documents_row1 = DB::table('documents')->where('documents_id', '=', $documents_item)->first();
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.tagged_document') . ' - ' . date('Y-m-d', $this->human_to_unix($documents_row1->documents_date)) . '</b> - ' . $documents_row1->documents_desc . ' ' . trans('noshform.from') . ' ' . $documents_row1->documents_from;
                    $arr['view'] = route('document_view', [$documents_row1->documents_id]);
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['documents', 'documents_id', $documents_row1->documents_id]);
                    }
                    if ($documents_row1->reconcile !== null && $documents_row1->reconcile !== 'y') {
                        $arr['danger'] = true;
                    }
                    $list_array[] = $arr;
                }
            }
            if ($t_messages->count()) {
                foreach ($t_messages as $t_messages_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.t_message') . ' - ' . date('Y-m-d', $this->human_to_unix($t_messages_row->t_messages_dos)) . '</b> - ' . $t_messages_row->t_messages_subject;
                    if ($edit == true && $t_messages_row->t_messages_signed == 'No') {
                        $arr['edit'] = route('chart_form', ['t_messages', 't_messages_id', $t_messages_row->t_messages_id]);
                    }
                    $arr['view'] = route('t_message_view', [$t_messages_row->t_messages_id]);
                    $list_array[] = $arr;
                }
            }
            if (! empty($t_messages_arr)) {
                foreach ($t_messages_arr as $t_messages_item) {
                    $t_messages_row1 = DB::table('t_messages')->where('t_messages_id', '=', $t_messages_item)->first();
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.tagged_t_message') . ' - ' . date('Y-m-d', $this->human_to_unix($t_messages_row1->t_messages_dos)) . '</b> - ' . $t_messages_row1->t_messages_subject;
                    if ($edit == true && $t_messages_row1->t_messages_signed == 'No') {
                        $arr['edit'] = route('chart_form', ['t_messages', 't_messages_id', $t_messages_row1->t_messages_id]);
                    }
                    $arr['view'] = route('t_message_view', [$t_messages_row1->t_messages_id]);
                    $list_array[] = $arr;
                }
            }
            if ($orders->count()) {
                foreach ($orders as $orders_row) {
                    $arr = [];
                    if ($orders_row->orders_labs !== '') {
                        $arr['label'] = '<b>' . trans('noshform.laboratory_orders') . ' - ' . date('Y-m-d', $this->human_to_unix($orders_row->orders_date)) . '</b> - ' . $orders_row->orders_labs;
                        $order_type = 'orders_labs';
                    }
                    if ($orders_row->orders_radiology !== '') {
                        $arr['label'] = '<b>' . trans('noshform.imaging_orders') . ' - ' . date('Y-m-d', $this->human_to_unix($orders_row->orders_date)) . '</b> - ' . $orders_row->orders_radiology;
                        $order_type = 'orders_radiology';
                    }
                    if ($orders_row->orders_cp !== '') {
                        $arr['label'] = '<b>' . trans('noshform.cardiopulmonary_orders') . ' - ' . date('Y-m-d', $this->human_to_unix($orders_row->orders_date)) . '</b> - ' . $orders_row->orders_cp;
                        $order_type = 'orders_cp';
                    }
                    if ($orders_row->orders_referrals !== '') {
                        $address = DB::table('addressbook')->where('address_id', '=', $orders_row->address_id)->first();
                        $arr['label'] = '<b>' . trans('noshform.referral') . ' - ' . date('Y-m-d', $this->human_to_unix($orders_row->orders_date)) . '</b> - ' . $address->specialty . ': ' . $address->displayname;
                        $order_type = 'orders_referrals';
                    }
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['orders', 'orders_id', $orders_row->orders_id, $order_type]);
                    }
                    $list_array[] = $arr;
                }
            }
            if ($alerts->count()) {
                foreach ($alerts as $alerts_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.alert') . ' - </b>' . $alerts_row->alert . ' (' . trans('noshform.due') . ' ' . date('m/d/Y', $this->human_to_unix($alerts_row->alert_date_active)) . ') - ' . $alerts_row->alert_description;
                    if ($edit == true) {
                        $arr['edit'] = route('chart_form', ['alerts', 'alert_id', $alerts_row->alert_id]);
                    }
                    $list_array[] = $arr;
                }
            }
            if ($notes->count()) {
                foreach ($notes as $notes_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.imm_notes') . ' - </b>' . $notes_row->imm_notes;
                    if ($edit == true) {
                        $arr['edit'] = route('immunizations_notes');
                    }
                    $list_array[] = $arr;
                }
            }
            if ($notes1->count()) {
                foreach ($notes1 as $notes1_row) {
                    $arr = [];
                    $arr['label'] = '<b>' . trans('noshform.billing_notes') . ' - </b>' . $notes1_row->billing_notes;
                    if ($edit == true) {
                        $arr['edit'] = route('billing_notes');
                    }
                    $list_array[] = $arr;
                }
            }
            if ($demographics->count()) {
                $arr = [];
                $arr['label'] = '<b>'. trans('noshform.demographics') . ' - ' . trans('noshform.name_identity') . '</b>';
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['demographics', 'pid', Session::get('pid'), 'name']);
                }
                $list_array[] = $arr;
            }
            if ($demographics_a->count()) {
                $arr = [];
                $arr['label'] = '<b>' . trans('noshform.demographics') . ' - ' . trans('noshform.contacts') . '</b>';
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['demographics', 'pid', Session::get('pid'), 'contacts']);
                }
                $list_array[] = $arr;
            }
            if ($demographics_b->count()) {
                $arr = [];
                $arr['label'] = '<b>' . trans('noshform.demographics') . ' - ' . trans('noshform.guardians') . '</b>';
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['demographics', 'pid', Session::get('pid'), 'guardians']);
                }
                $list_array[] = $arr;
            }
            if ($demographics_c->count()) {
                $arr = [];
                $arr['label'] = '<b>' . trans('noshform.demographics') . ' - ' . trans('noshform.other') . '</b>';
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['demographics', 'pid', Session::get('pid'), 'other']);
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'search_chart', false, true);
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['panel_header'] = trans('noshform.search_results');
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function social_history(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $recent_query = DB::table('other_history')
            ->where('pid', '=', Session::get('pid'))
            ->where('eid', '!=', '0')
            ->orderBy('eid', 'desc');
        $recent_oh_sh = $recent_query->whereNotNull('oh_sh')->first();
        $recent_oh_etoh = $recent_query->whereNotNull('oh_etoh')->first();
        $recent_oh_tobacco = $recent_query->whereNotNull('oh_tobacco')->first();
        $recent_oh_drugs = $recent_query->whereNotNull('oh_drugs')->first();
        $recent_oh_employment = $recent_query->whereNotNull('oh_employment')->first();
        $recent_oh_psychosocial = $recent_query->whereNotNull('oh_psychosocial')->first();
        $recent_oh_developmental = $recent_query->whereNotNull('oh_developmental')->first();
        $recent_oh_medtrials = $recent_query->whereNotNull('oh_medtrials')->first();
        $recent_oh_diet = $recent_query->whereNotNull('oh_diet')->first();
        $recent_oh_physical_activity = $recent_query->whereNotNull('oh_physical_activity')->first();
        $social_hx_arr = ['oh_sh', 'oh_etoh', 'oh_tobacco', 'oh_drugs', 'oh_employment', 'oh_psychosocial', 'oh_developmental', 'oh_medtrials', 'oh_diet', 'oh_physical_activity'];
        $return = '';
        // Set up persistent values
        $persistent_check = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
        if (!$persistent_check) {
            // No persistent values exist
            $create_data = [];
            foreach ($social_hx_arr as $social_hx_item) {
                $recent = 'recent_' . $social_hx_item;
                if (${$recent}) {
                    $create_data[$social_hx_item] = ${$recent}->{$social_hx_item};
                }
            }
            $create_data['eid'] = '0';
            $create_data['pid'] = Session::get('pid');
            DB::table('other_history')->insert($create_data);
            $this->audit('Add');
        }
        $result = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
        $patient = DB::table('demographics')->where('pid', '=', Session::get('pid'))->first();
        if ($result) {
            $header_arr = [
                trans('noshform.lifestyle') => route('chart_form', ['other_history', 'pid', Session::get('pid'), 'lifestyle']),
                trans('noshform.habits') => route('chart_form', ['other_history', 'pid', Session::get('pid'), 'habits']),
                trans('noshform.mental_health') => route('chart_form', ['other_history', 'pid', Session::get('pid'), 'mental_health'])
            ];
            $lifestyle_arr = [
                trans('noshform.oh_sh') => nl2br($result->oh_sh),
                trans('noshform.sexuallyactive') => ucfirst($patient->sexuallyactive),
                trans('noshform.oh_diet') => nl2br($result->oh_diet),
                trans('noshform.oh_physical_activity') => nl2br($result->oh_physical_activity),
                trans('noshform.oh_employment') => nl2br($result->oh_employment)
            ];
            $habits_arr = [
                trans('noshform.oh_etoh') => nl2br($result->oh_etoh),
                trans('noshform.tobacco') => ucfirst($patient->tobacco),
                trans('noshform.oh_tobacco') => nl2br($result->oh_tobacco),
                trans('noshform.oh_drugs') => nl2br($result->oh_drugs)
            ];
            $mental_health_arr = [
                trans('noshform.oh_psychosocial') => nl2br($result->oh_psychosocial),
                trans('noshform.oh_developmental') => nl2br($result->oh_developmental),
                trans('noshform.oh_medtrials') => nl2br($result->oh_medtrials)
            ];
            $return = $this->header_build($header_arr, trans('noshform.lifestyle'));
            foreach ($lifestyle_arr as $key1 => $value1) {
                if ($value1 !== '' && $value1 !== null) {
                    $return .= '<div class="col-md-3"><b>' . $key1 . '</b></div><div class="col-md-8">' . $value1 . '</div>';
                }
            }
            $return .= '</div></div></div>';
            $return .= $this->header_build($header_arr, trans('noshform.habits'));
            foreach ($habits_arr as $key2 => $value2) {
                if ($value2 !== '' && $value2 !== null) {
                    $return .= '<div class="col-md-3"><b>' . $key2 . '</b></div><div class="col-md-8">' . $value2 . '</div>';
                }
            }
            $return .= '</div></div></div>';
            $return .= $this->header_build($header_arr, trans('noshform.mental_health'));
            foreach ($mental_health_arr as $key3 => $value3) {
                if ($value3 !== '' && $value3 !== null) {
                    $return .= '<div class="col-md-3"><b>' . $key3 . '</b></div><div class="col-md-8">' . $value3 . '</div>';
                }
            }
            $return .= '</div></div></div>';
            if (Session::has('eid')) {
                if (Session::get('group_id') == '2' || Session::get('group_id') == '3') {
                    // Mark as reviewed by physician or assistant
                    $current_query = DB::table('other_history')->where('eid', '=', Session::get('eid'))->first();
                    if ($current_query) {
                        foreach ($social_hx_arr as $social_hx_item) {
                            $data1[$social_hx_item] = $result->{$social_hx_item};
                        }
                        DB::table('other_history')->where('eid', '=', Session::get('eid'))->update($data1);
                        $this->audit('Update');
                    } else {
                        foreach ($social_hx_arr as $social_hx_item) {
                            $data2[$social_hx_item] = $result->{$social_hx_item};
                        }
                        $data2['eid'] = Session::get('eid');
                        $data2['pid'] = Session::get('pid');
                        DB::table('other_history')->insert($date2);
                        $this->audit('Add');
                    }
                    if ($data['message_action'] !== '') {
                        $data['message_action'] .= '<br>';
                    }
                    $data['message_action'] .= trans('noshform.social_history_encounter');
                }
            }
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['sh_active'] = true;
        $data['panel_header'] = trans('noshform.social_history');
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function supplements_list(Request $request, $type)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('sup_list')->where('pid', '=', Session::get('pid'))->orderBy('sup_supplement', 'asc');
        if ($type == 'active') {
            $query->whereNull('sup_date_inactive');
            $dropdown_array = [
                'items_button_text' => trans('noshform.active')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.inactive'),
                'icon' => 'fa-times',
                'url' => route('supplements_list', ['inactive'])
            ];
        } else {
            $query->whereNotNull('sup_date_inactive');
            $dropdown_array = [
                'items_button_text' => trans('noshform.inactive')
            ];
            $items[] = [
                'type' => 'item',
                'label' => trans('noshform.active'),
                'icon' => 'fa-check',
                'url' => route('supplements_list', ['active'])
            ];
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('7');
        $columns = Schema::getColumnListing('sup_list');
        $row_index = $columns[0];
        $list_array = [];
        if ($result->count()) {
            foreach ($result as $row) {
                $arr = [];
                if ($row->sup_sig == '') {
                    $arr['label'] = $row->sup_supplement . ' ' . $row->sup_dosage . ' ' . $row->sup_dosage_unit . ', ' . $row->sup_instructions . ' ' . trans('noshform.for') . ' ' . $row->sup_reason;
                } else {
                    $arr['label'] =$row->sup_supplement . ' ' . $row->sup_dosage . ' ' . $row->sup_dosage_unit . ', ' . $row->sup_sig . ', ' . $row->sup_route . ', ' . $row->sup_frequency;
                    $arr['label'] .= ' ' . trans('noshform.for') . ' ' . $row->sup_reason;
                }
                if ($edit == true) {
                    $arr['edit'] = route('chart_form', ['sup_list', $row_index, $row->$row_index]);
                    if ($type == 'active') {
                        $arr['inactivate'] = route('chart_action', ['table' => 'sup_list', 'action' => 'inactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    } else {
                        $arr['reactivate'] = route('chart_action', ['table' => 'sup_list', 'action' => 'reactivate', 'index' => $row_index, 'id' => $row->$row_index]);
                    }
                    $arr['delete'] = route('chart_action', ['table' => 'sup_list', 'action' => 'delete', 'index' => $row_index, 'id' => $row->$row_index]);
                }
                if ($row->reconcile !== null && $row->reconcile !== 'y') {
                    $arr['danger'] = true;
                }
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 'supplements_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        $data['content'] = $return;
        $data['supplements_active'] = true;
        $data['panel_header'] = trans('noshform.supplements');
        $data = array_merge($data, $this->sidebar_build('chart'));
        //$data['template_content'] = 'test';
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['sup_list', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array1);
            $dropdown_array2 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items2 = [];
            $items2[] = [
                'type' => 'item',
                'label' => trans('noshform.order'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['sup_list', $row_index, '0', 'order'])
            ];
            $dropdown_array2['items'] = $items2;
            $data['panel_dropdown'] .= '<span class="fa-btn"></span>' . $this->dropdown_build($dropdown_array2);
        }
        if (Session::has('eid') && $type == 'active') {
            if (Session::get('group_id') == '2') {
                // Mark supplement list as reviewed by physician
                $supplements_encounter = '';
                if (! empty($list_array)) {
                    $supplements_encounter .= implode("\n", array_column($list_array, 'label'));
                }
                $supplements_encounter .= "\n" . trans('noshform.reviewed_by') . ' ' . Session::get('displayname') . ' ' . trans('noshform.on') . ' ' . date('Y-m-d');
                $encounter_query = DB::table('other_history')->where('eid', '=', Session::get('eid'))->first();
                $encounter_data['oh_supplements'] = $supplements_encounter;
                if ($encounter_query) {
                    $encounter_data['oh_date'] = date("Y-m-d H:i:s", time());
                    DB::table('other_history')->where('eid', '=', Session::get('eid'))->update($encounter_data);
                } else {
                    $encounter_data['eid'] = Session::get('eid');
                    $encounter_data['pid'] = Session::get('pid');
                    DB::table('other_history')->insert($encounter_data);
                }
                if ($data['message_action'] !== '') {
                    $data['message_action'] .= '<br>';
                }
                $data['message_action'] .= trans('noshform.supplement_encounter');
            }
        }
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function t_messages_list(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('t_messages')->where('pid', '=', Session::get('pid'))->orderBy('t_messages_dos', 'desc');
        if (Session::get('patient_centric') == 'n') {
            $query->where('practice_id', '=', Session::get('practice_id'));
        }
        if (Session::get('group_id') == '100') {
            $query->where('t_messages_signed', '=', 'Yes');
        }
        $result = $query->get();
        $return = '';
        $edit = $this->access_level('2');
        $columns = Schema::getColumnListing('t_messages');
        $row_index = $columns[0];
        if ($result->count()) {
            $list_array = [];
            foreach ($result as $row) {
                $arr = [];
                $arr['label'] = '<b>' . date('Y-m-d', $this->human_to_unix($row->t_messages_dos)) . '</b> - ' . $row->t_messages_subject;
                if ($edit == true && $row->t_messages_signed == 'No') {
                    $arr['edit'] = route('chart_form', ['t_messages', $row_index, $row->$row_index]);
                }
                $arr['view'] = route('t_message_view', [$row->t_messages_id]);
                $list_array[] = $arr;
            }
            $return .= $this->result_build($list_array, 't_messages_list');
        } else {
            $return .= ' ' . trans('noshform.none') . '.';
        }
        if ($edit == true) {
            $dropdown_array1 = [
                'items_button_icon' => 'fa-plus'
            ];
            $items1 = [];
            $items1[] = [
                'type' => 'item',
                'label' => trans('noshform.add_telephone_visit'),
                'icon' => 'fa-plus',
                'url' => route('chart_form', ['t_messages', $row_index, '0'])
            ];
            $dropdown_array1['items'] = $items1;
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array1);
        }
        $data['content'] = $return;
        $data['t_messages_active'] = true;
        $data['panel_header'] = trans('noshform.t_messages_list');
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function t_message_view(Request $request, $t_messages_id)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $message = DB::table('t_messages')->where('t_messages_id', '=', $t_messages_id)->first();
        // Tags
        $tags_relate = DB::table('tags_relate')->where('t_messages_id', '=', $t_messages_id)->get();
        $tags_val_arr = [];
        if ($tags_relate->count()) {
            foreach ($tags_relate as $tags_relate_row) {
                $tags = DB::table('tags')->where('tags_id', '=', $tags_relate_row->tags_id)->first();
                $tags_val_arr[] = $tags->tag;
            }
        }
        $return = '';
        $return .= '<div style="margin-bottom:15px;"><input type="text" id="encounter_tags" class="nosh-tags" value="' . implode(',', $tags_val_arr) . '" data-nosh-add-url="' . route('tag_save', ['t_messages_id', $t_messages_id]) . '" data-nosh-remove-url="' . route('tag_remove', ['t_messages_id', $t_messages_id]) . '" placeholder="' . trans('noshform.add_tags') . '"/></div>';
        $return .= $this->t_messages_view($t_messages_id);
        $images = DB::table('image')->where('t_messages_id', '=', $t_messages_id)->get();
        if ($images->count()) {
            $return .= '<br><h5>' . trans('noshform.images') . ':</h5><div class="list-group gallery">';
            foreach ($images as $image) {
                $file_path1 = '/temp/' . time() . '_' . basename($image->image_location);
                $file_path = public_path() . $file_path1;
                copy($image->image_location, $file_path);
                $return .= '<div class="col-sm-4 col-xs-6 col-md-3 col-lg-3"><a class="thumbnail fancybox nosh-no-load" rel="ligthbox" href="' . url('/') . $file_path1 . '">';
                $return .= '<img class="img-responsive" alt="" src="' . url('/') . $file_path1 . '" />';
                $return .= '<div class="text-center"><small class="text-muted">' . $image->image_description . '</small></div></a>';
            }
            $return .= '</div>';
        }
        $dropdown_array = [];
        $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
        $dropdown_array['default_button_text_url'] = Session::get('last_page');
        // $items = [];
        // if ($edit == true) {
        //     $items[] = [
        //         'type' => 'item',
        //         'label' => 'Edit',
        //         'icon' => 'fa-pencil',
        //         'url' => route('chart_form', ['tests', 'tests_id', $id])
        //     ];
        // }
        // $items[] = [
        //     'type' => 'item',
        //     'label' => 'Chart',
        //     'icon' => 'fa-line-chart',
        //     'url' => route('results_chart', [$id])
        // ];
        // $items[] = [
        //     'type' => 'item',
        //     'label' => 'Print',
        //     'icon' => 'fa-print',
        //     'url' => route('results_print', [$id])
        // ];
        // $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $data['content'] = $return;
        $data['t_messages_active'] = true;
        $data['panel_header'] = trans('noshform.t_messages_message') . ' - ' .  date('Y-m-d', $this->human_to_unix($message->t_messages_dos));
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function treedata(Request $request)
    {
        $oh = DB::table('other_history')->where('pid', '=', Session::get('pid'))->where('eid', '=', '0')->first();
        $ret_arr = $this->treedata_build([], 'patient', [], [], 0);
        $nodes_arr = $ret_arr[0];
        $edges_arr = $ret_arr[1];
        $placeholder_count = $ret_arr[2];
        if ($oh->oh_fh !== null) {
            if ($this->yaml_check($oh->oh_fh)) {
                $nodes_arr = [];
                $edges_arr = [];
                $placeholder_count = 0;
                $fh_arr = Yaml::parse($oh->oh_fh);
                $ret_arr = $this->treedata_build($fh_arr, 'patient', [], [], 0);
                $nodes_arr = $ret_arr[0];
                $edges_arr = $ret_arr[1];
                $placeholder_count = $ret_arr[2];
                foreach ($fh_arr as $person_key => $person_val) {
                    $ret_arr = $this->treedata_build($fh_arr, $person_key, $nodes_arr, $edges_arr, $placeholder_count);
                    $nodes_arr = $ret_arr[0];
                    $edges_arr = $ret_arr[1];
                    $placeholder_count = $ret_arr[2];
                }
            }
        }
        $nodes_arr = $this->treedata_x_build($nodes_arr);
        $arr = [
            'nodes' => $nodes_arr,
            'edges' => $edges_arr
        ];
        return $arr;
    }

    public function uma_invite(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'email' => 'required_without_all:sms',
                'sms' => 'required_without_all:email'
            ]);
            // $resource_set_ids = implode(',', $request->input('resources'));
            $data_message['temp_url'] = URL::to('uma_auth');
            $data_message['patient'] = Session::get('displayname');
            if ($request->input('email') !== '') {
                $email = $request->input('email');
                $this->send_mail('emails.apiregister', $data_message, trans('noshform.email_invite1') . ' ' . Session::get('displayname'), $request->input('email'), '1');
            } else {
                $email = $request->input('sms');
                $message = trans('noshform.sms_invite1') . $data_message['patient'] . trans('noshform.sms_invite2') . " " . $data_message['temp_url'] . " " . trans('noshform.sms_invite3');
                if (env('NEXMO_API') == null) {
					$this->textbelt($request->input('sms'), $message, Session::get('practice_id'));
				} else {
					$this->nexmo($request->input('sms'), $message);
				}
            }
            $data = [
                'email' => $request->input('email'),
                'name' => $request->input('name'),
                'invitation_timeout' => time() + 259200
                // 'resource_set_ids' => $resource_set_ids
            ];
            DB::table('uma_invitation')->insert($data);
            $this->audit('Add');
            $message_pre = '';
            if (Session::has('message_action')) {
                $message_pre = Session::get('message_action') . '<br>';
            }
            Session::put('message_action', $message_pre . trans('noshform.uma_invite_message') . ' ' . $email);
            return redirect(Session::get('last_page'));
        } else {
            $items[] = [
                'name' => 'name',
                'label' => trans('noshform.provider_name'),
                'type' => 'text',
                'required' => true,
                'default_value' => null
            ];
            $items[] = [
                'name' => 'email',
                'label' => trans('noshform.email'),
                'type' => 'email',
                'default_value' => null
            ];
            $items[] = [
                'name' => 'sms',
                'label' => trans('noshform.sms'),
                'type' => 'text',
                'phone' => true,
                'default_value' => null
            ];
            $form_array = [
                'form_id' => 'uma_send_form',
                'action' => route('uma_invite'),
                'items' => $items,
                'origin' => Session::get('last_page')
            ];
            $data['content'] = $this->form_build($form_array);
            $data['panel_header'] = trans('noshform.uma_invite');
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('chart', $data);
        }
    }

    public function uma_register(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'email' => 'required|email'
            ]);
            Session::forget('type');
            Session::forget('client_id');
            Session::forget('url');
            $domain = explode('@', $request->input('email'));
            // webfinger
            $url = 'https://' . $domain[1] . '/.well-known/webfinger';
            $query_string = 'resource=acct:' . $request->input('email') . '&rel=http://openid.net/specs/connect/1.0/issuer';
            $url .= '?' . $query_string ;
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
            if (isset($result_array['subject'])) {
                $as_uri = $result_array['links'][0]['href'];
                // $client_name = 'mdNOSH';
                $practice = DB::table('practiceinfo')->where('practice_id', '=', Session::get('practice_id'))->first();
                $client_name = 'mdNOSH - ' . $practice->practice_name;
                $url1 = route('uma_auth');
                $oidc = new OpenIDConnectUMAClient($as_uri);
                $oidc->startSession();
                $oidc->setClientName($client_name);
                $oidc->setSessionName('pnosh');
                if (file_exists(base_path() . '/fakelerootx1.pem')) {
                    $oidc->setCertPath(base_path() . '/fakelerootx1.pem');
                }
                $oidc->addRedirectURLs($url1);
                $oidc->addRedirectURLs(route('uma_api'));
                $oidc->addRedirectURLs(route('uma_logout'));
                $oidc->addRedirectURLs(route('uma_patient_centric'));
                $oidc->addRedirectURLs(route('uma_register_auth'));
                $oidc->addRedirectURLs(route('oidc'));
                $oidc->addRedirectURLs(route('oidc_api'));
                $oidc->addRedirectURLs(str_replace('uma_auth', 'fhir', $url1));
                $oidc->addScope('openid');
                $oidc->addScope('email');
                $oidc->addScope('profile');
                $oidc->addScope('address');
                $oidc->addScope('phone');
                $oidc->addScope('offline_access');
                $oidc->addScope('uma_authorization');
                $oidc->addScope('uma_protection');
                $oidc->setLogo('https://cloud.noshchartingsystem.com/SAAS-Logo.jpg');
                $oidc->setClientURI(str_replace('/uma_auth', '', $url1));
                $oidc->setUMA(true);
                $oidc->setResourceServer(true);
                $oidc->register();
                $client_id = $oidc->getClientID();
                $client_secret = $oidc->getClientSecret();
                $data1 = [
                    'hieofone_as_client_id' => $client_id,
                    'hieofone_as_client_secret' => $client_secret,
                    'hieofone_as_url' => $as_uri
                ];
                DB::table('demographics')->where('pid', '=', Session::get('pid'))->update($data1);
                $this->audit('Update');
                Session::put('pnosh_client_id', $client_id);
                Session::put('pnosh_client_secret', $client_secret);
                Session::put('pnosh_url', $as_uri);
                Session::save();
                return redirect()->route('uma_register_auth');
            } else {
                return redirect()->back()->withErrors(['tryagain' => 'Try again']);
            }
        } else {
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js('chart');
            $data['assets_css'] = $this->assets_css('chart');
            return view('uma_register', $data);
        }
    }

    public function uma_register_auth(Request $request)
    {
        $url = route('uma_register_auth');
        $open_id_url = Session::get('pnosh_url');
        $client_id = Session::get('pnosh_client_id');
        $client_secret = Session::get('pnosh_client_secret');
        $oidc = new OpenIDConnectUMAClient($open_id_url, $client_id, $client_secret);
        $oidc->startSession();
        $oidc->setRedirectURL($url);
        $oidc->setSessionName('pnosh');
        if (file_exists(base_path() . '/fakelerootx1.pem')) {
            $oidc->setCertPath(base_path() . '/fakelerootx1.pem');
        }
        $oidc->addScope('openid');
        $oidc->addScope('email');
        $oidc->addScope('profile');
        $oidc->addScope('offline_access');
        $oidc->addScope('uma_authorization');
        $oidc->addScope('uma_protection');
        $oidc->setUMA(true);
        $oidc->authenticate();
        $name = $oidc->requestUserInfo('name');
        $birthday = $oidc->requestUserInfo('birthday');
        $access_token = $oidc->getAccessToken();
        $patient['hieofone_as_name'] = $name . '(DOB: ' . $birthday . ')';
        $patient['hieofone_as_picture'] = $oidc->requestUserInfo('picture');
        DB::table('demographics')->where('pid', '=', Session::get('pid'))->update($patient);
        $this->audit('Update');
        $refresh = [
            'refresh_token' => $oidc->getRefreshToken(),
            'pid' => Session::get('pid'),
            'practice_id' => Session::get('practice_id'),
            'user_id' => Session::get('user_id'),
            'endpoint_uri' => Session::get('pnosh_url'),
            'client_id' => Session::get('pnosh_client_id'),
            'client_secret' => Session::get('pnosh_client_secret')
        ];
        DB::table('refresh_tokens')->insert($refresh);
        $this->audit('Add');
        $data['panel_header'] = trans('noshform.uma_register_auth');
        $data1['content'] = '<p>' . trans('noshform.uma_register_auth1') . ' <a href="' . Session::get('pnosh_url') .'/home">' . trans('noshform.uma_register_auth2') . '.</a></p>';
        $data = array_merge($data, $this->sidebar_build('chart'));
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function upload_ccda(Request $request)
    {
        if ($request->isMethod('post')) {
            $pid = Session::get('pid');
            $directory = Storage::path($pid);
            $file = $request->file('file_input');
            $new_name = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.xml';
            $file->move($directory, $new_name);
            $file_path = $directory . '/' . $new_name;
            $ccda = simplexml_load_file($file_path);
            if ($ccda) {
                $data = [
                    'documents_url' => $file_path,
                    'documents_type' => 'ccda',
                    'documents_desc' => $ccda->title,
                    'documents_from' => $ccda->recordTarget->patientRole->providerOrganization->name,
                    'documents_date' => date("Y-m-d", strtotime($ccda->effectiveTime['value'])),
                    'pid' => $pid
                ];
                $documents_id = DB::table('documents')->insertGetId($data, 'documents_id');
                $this->audit('Add');
                Session::put('message_action', trans('noshform.ccda_uploaded'));
                return redirect()->route('upload_ccda_view', [$documents_id, 'issues']);
            } else {
                unlink($file_path);
                Session::put('message_action', trans('noshform.error') . ' - ' . trans('noshform.format_error'));
                return redirect(Session::get('last_page'));
            }
        } else {
            $data['records_active'] = true;
            $data['panel_header'] = trans('noshform.upload_ccda');
            $data['document_upload'] = route('upload_ccda');
            $type_arr = ['xml', 'ccda'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('document_upload', $data);
        }
    }

    public function upload_ccda_view(Request $request, $id, $type='issues')
    {
        $documents = DB::table('documents')->where('documents_id', '=', $id)->first();
        if ($documents->documents_type == 'ccda') {
            $data['ccda'] = str_replace("'", '"', preg_replace( "/\r|\n/", "", File::get($documents->documents_url)));
        }
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $return = '';
        $type_arr = [
            'issues' => [trans('noshform.conditions'), 'fa-bars', 'issue'],
            'rx_list' => [trans('noshform.medications'), 'fa-eyedropper', 'rxl_medication'],
            'immunizations' => [trans('noshform.immunizations'), 'fa-magic', 'imm_immunization'],
            'allergies' => [trans('noshform.allergies'), 'fa-exclamation-triangle', 'allergies_med', 'allergies_date_inactive']
        ];
        $dropdown_array = [
            'items_button_text' => $type_arr[$type][0]
        ];
        foreach ($type_arr as $key => $value) {
            if ($key !== $type) {
                $items[] = [
                    'type' => 'item',
                    'label' => $value[0],
                    'icon' => $value[1],
                    'url' => route('upload_ccda_view', [$id, $key])
                ];
            }
        }
        $dropdown_array['items'] = $items;
        $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
        $query = DB::table($type)->where('pid', '=', Session::get('pid'))->orderBy($type_arr[$type][2], 'asc');
        if ($type == 'issues') {
            $query->whereNull('issue_date_inactive');
        }
        if ($type == 'rx_list') {
            $query->whereNull('rxl_date_inactive')->whereNull('rxl_date_old');
        }
        if ($type == 'immunizations') {
            $query->orderBy('imm_sequence', 'asc');
        }
        if ($type == 'allergies') {
            $query->whereNull('allergies_date_inactive');
        }
        $result = $query->get();
        $list_array = [];
        if ($result->count()) {
            if ($type == 'issues') {
                foreach($result as $row) {
                    $arr = [];
                    $arr['label'] = $row->issue;
                    $list_array[] = $arr;
                }
            }
            if ($type == 'rx_list') {
                foreach($result as $row) {
                    $arr = [];
                    if ($row->rxl_sig == '') {
                        $arr['label'] = $row->rxl_medication . ' ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $row->rxl_instructions . ' ' . trans('noshform.for') . ' ' . $row->rxl_reason;
                    } else {
                        $arr['label'] = $row->rxl_medication . ' ' . $row->rxl_dosage . ' ' . $row->rxl_dosage_unit . ', ' . $row->rxl_sig . ', ' . $row->rxl_route . ', ' . $row->rxl_frequency;
                        $arr['label'] .= ' ' . trans('noshform.for') . ' ' . $row->rxl_reason;
                    }
                    $list_array[] = $arr;
                }
            }
            if ($type == 'immunizations') {
                $seq_array = [
                    '1' => ', ' . lcfirst(trans('noshform.first')),
                    '2' => ', ' . lcfirst(trans('noshform.second')),
                    '3' => ', ' . lcfirst(trans('noshform.third')),
                    '4' => ', ' . lcfirst(trans('noshform.fourth')),
                    '5' => ', ' . lcfirst(trans('noshform.fifth'))
                ];
                foreach ($result as $row) {
                    $arr = [];
                    $arr['label'] = $row->imm_immunization;
                    if (isset($row->imm_sequence)) {
                        if (isset($seq_array[$row->imm_sequence])) {
                            $arr['label'] = $row->imm_immunization . $seq_array[$row->imm_sequence];
                        }
                    }
                    $list_array[] = $arr;
                }
            }
            if ($type == 'allergies') {
                foreach ($result as $row) {
                    $arr = [];
                    $arr['label'] = $row->allergies_med . ' - ' . $row->allergies_reaction;
                    $list_array[] = $arr;
                }
            }
        }
        if ($documents->documents_type == 'ccr') {
            $xml = simplexml_load_file($documents->documents_url);
            // $phone_home = '';
            // $phone_work = '';
            // $phone_cell = '';
            // foreach ($xml->Actors->Actor[0]->Telephone as $phone) {
            //     if ((string) $phone->Type->Text == 'Home') {
            //         $phone_home = (string) $phone->Value;
            //     }
            //     if ((string) $phone->Type->Text == 'Mobile') {
            //         $phone_cell = (string) $phone->Value;
            //     }
            //     if ((string) $phone->Type->Text == 'Alternate') {
            //         $phone_work = (string) $phone->Value;
            //     }
            // }
            // $address = (string) $xml->Actors->Actor[0]->Address->Line1;
            // $address = ucwords(strtolower($address));
            // $city = (string) $xml->Actors->Actor[0]->Address->City;
            // $city = ucwords(strtolower($city));
            // $data1 = [
            //     'address' => $address,
            //     'city' => $city,
            //     'state' => (string) $xml->Actors->Actor[0]->Address->State,
            //     'zip' => (string) $xml->Actors->Actor[0]->Address->PostalCode,
            //     'phone_home' => $phone_home,
            //     'phone_work' => $phone_work,
            //     'phone_cell' => $phone_cell,
            // ];
            // DB::table('demographics')->where('pid', '=', $pid)->update($data1);
            // $this->audit('Update');
            if ($type == 'issues') {
                if (isset($xml->Body->Problems)) {
                    foreach ($xml->Body->Problems->Problem as $issue) {
                        if ((string) $issue->Status->Text == 'Active') {
                            $icd = (string) $issue->Description->Code->Value;
                            $icd_desc = $this->icd_search($icd);
                            if ($icd_desc == '') {
                                $icd_desc = (string) $issue->Description->Text;
                            }
                            $arr = [];
                            $arr['label'] = $icd_desc;
                            $arr['label_class'] = 'nosh-ccda-list';
                            $arr['danger'] = true;
                            $arr['label_data_arr'] = [
                                'data-nosh-type' => 'issues',
                                'data-nosh-name' => $icd_desc,
                                'data-nosh-code' => $icd,
                                'data-nosh-date' => (string) $issue->DateTime->ExactDateTime
                            ];
                            $list_array[] = $arr;
                        }
                    }
                }
            }
            if ($type == 'rx_list') {
                if (isset($xml->Body->Medications)) {
                    foreach ($xml->Body->Medications->Medication as $rx) {
                        if ((string) $rx->Status->Text == 'Active') {
                            $arr = [];
                            $arr['label'] = (string) $rx->Product->ProductName->Text . ', ' . $rx->Directions->Direction->Dose->Value;
                            $arr['label_class'] = 'nosh-ccda-list';
                            $arr['danger'] = true;
                            $arr['label_data_arr'] = [
                                'data-nosh-type' => 'rx_list',
                                'data-nosh-name' => (string) $rx->Product->ProductName->Text,
                                'data-nosh-code' => '',
                                'data-nosh-dosage' => '',
                                'data-nosh-dosage-unit' => '',
                                'data-nosh-route' => '',
                                'data-nosh-reason' => '',
                                'data-nosh-date' => (string) $rx->DateTime->ExactDateTime,
                                'data-nosh-administration' => $rx->Directions->Direction->Dose->Value
                            ];
                            $list_array[] = $arr;
                        }
                    }
                }
            }
            if ($type == 'immunizations') {
                if (isset($xml->Body->Immunizations)) {
                    foreach ($xml->Body->Immunizations->Immunization as $imm) {
                        if (strpos((string) $imm->Product->ProductName->Text, '#')) {
                            $items = explode('#',(string) $imm->Product->ProductName->Text);
                            $imm_immunization = rtrim($items[0]);
                            $imm_sequence = $items[1];
                        } else {
                            $imm_immunization = (string) $imm->Product->ProductName->Text;
                            $imm_sequence = '';
                        }
                        $arr = [];
                        $arr['label'] = $imm_immunization;
                        $arr['label_class'] = 'nosh-ccda-list';
                        $arr['danger'] = true;
                        $arr['label_data_arr'] = [
                            'data-nosh-type' => 'immunizations',
                            'data-nosh-name' =>  $imm_immunization,
                            'data-nosh-route' => '',
                            'data-nosh-date' => (string) $imm->DateTime->ApproximateDateTime,
                            'data-nosh-sequence' => $imm_sequence
                        ];
                        $list_array[] = $arr;
                    }
                }
            }
            if ($type == 'allergies') {
                if (isset($xml->Body->Alerts)) {
                    foreach ($xml->Body->Alerts->Alert as $alert) {
                        if ((string) $alert->Type->Text == 'Allergy') {
                            if ((string) $alert->Status->Text == 'Active') {
                                $arr = [];
                                $arr['label'] = (string) $alert->Description->Text;
                                $arr['label_class'] = 'nosh-ccda-list';
                                $arr['danger'] = true;
                                $arr['label_data_arr'] = [
                                    'data-nosh-type' => 'allergies',
                                    'data-nosh-name' => (string) $alert->Description->Text,
                                    'data-nosh-reaction' => (string) $alert->Reaction->Description->Text,
                                    'data-nosh-date' => (string) $alert->DateTime->ExactDateTime,
                                ];
                                $list_array[] = $arr;
                            }
                        }
                    }
                }
            }
        }
        $return = '<div class="alert alert-success">';
        $return .= '<h5>' . trans('noshform.upload_ccda') . '</h5>';
        $return .= '</div>';
        $return .= $this->result_build($list_array, $type . '_reconcile_list');
        $data['content'] = $return;
        $data['panel_header'] = trans('noshform.upload_ccda_view');
        $data['records_active'] = true;
        $data = array_merge($data, $this->sidebar_build('chart'));
        Session::put('last_page', $request->fullUrl());
        $data['assets_js'] = $this->assets_js('chart');
        $data['assets_css'] = $this->assets_css('chart');
        return view('chart', $data);
    }

    public function upload_ccr(Request $request)
    {
        if ($request->isMethod('post')) {
            $pid = Session::get('pid');
            $directory = Storage::path($pid);
            $file = $request->file('file_input');
            $new_name = str_replace('.' . $file->getClientOriginalExtension(), '', $file->getClientOriginalName()) . '_' . time() . '.xml';
            $file->move($directory, $new_name);
            $file_path = $directory . '/' . $new_name;
            $xml = simplexml_load_file($file_path);
            if ($xml) {
                $documents_from = 'Unknown';
                foreach ($xml->From->ActorLink as $actor) {
                    if ($actor->ActorRole->Text == 'Organization') {
                        $documents_from = $actor->ActorID;
                    }
                }
                $data = [
                    'documents_url' => $file_path,
                    'documents_type' => 'ccr',
                    'documents_desc' => trans('noshform.ccr_description'),
                    'documents_from' => $documents_from,
                    'documents_date' => date("Y-m-d"),
                    'pid' => $pid
                ];
                $documents_id = DB::table('documents')->insertGetId($data, 'documents_id');
                $this->audit('Add');
                Session::put('message_action', trans('noshform.ccr_uploaded'));
                return redirect()->route('upload_ccda_view', [$documents_id, 'issues']);
            } else {
                unlink($file_path);
                Session::put('message_action', trans('noshform.error') . ' - ' . trans('noshform.format_error'));
                return redirect(Session::get('last_page'));
            }
        } else {
            $data['records_active'] = true;
            $data['panel_header'] = trans('noshform.upload_ccr');
            $data['document_upload'] = route('upload_ccr');
            $type_arr = ['xml', 'ccr'];
            $data['document_type'] = json_encode($type_arr);
            $dropdown_array['default_button_text'] = '<i class="fa fa-chevron-left fa-fw fa-btn"></i>' . trans('noshform.back');
            $dropdown_array['default_button_text_url'] = Session::get('last_page');
            $data['panel_dropdown'] = $this->dropdown_build($dropdown_array);
            $data = array_merge($data, $this->sidebar_build('chart'));
            $data['assets_js'] = $this->assets_js();
            $data['assets_css'] = $this->assets_css();
            return view('document_upload', $data);
        }
    }
















































    public function ccda($hippa_id)
    {
        $pid = Session::get('pid');
        $practice_id = Session::get('practice_id');
        $file_path = __DIR__.'/../../public/temp/ccda_' . $pid . "_" . time() . ".xml";
        $ccda = $this->generate_ccda($hippa_id);
        File::put($file_path, $ccda);
        return Response::download($file_path);
    }



    public function export_demographics($type)
    {
        $practice_id = Session::get('practice_id');
        if ($type == "all") {
            $query = DB::table('demographics')
                ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
                ->where('demographics_relate.practice_id', '=', $practice_id)
                ->get();
        } else {
            $query = DB::table('demographics')
                ->join('demographics_relate', 'demographics_relate.pid', '=', 'demographics.pid')
                ->where('demographics_relate.practice_id', '=', $practice_id)
                ->where('demographics.active', '=', '1')
                ->get();
        }
        $i = 0;
        $csv = '';
        foreach ($query as $row) {
            $array_row = (array) $row;
            $array_values = array_values($array_row);
            if ($i == 0) {
                $array_key = array_keys($array_row);
                $csv .= implode(',', $array_key);
                $csv .= "\n" . implode(',', $array_values);
            } else {
                $csv .= "\n" . implode(',', $array_values);
            }
        }
        $file_path = __DIR__."/../../public/temp/" . time() . "_demographics.txt";
        File::put($file_path, $csv);
        return Response::download($file_path);
    }
}
