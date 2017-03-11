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

class RcopiaController extends Controller
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

    public function postRcopiaUpdateAllergy()
    {
        $pid = Session::get('pid');
        $encounter_provider = Session::get('displayname');
        $old = Demographics::find($pid);
        $rcopia_data = array(
            'rcopia_update_allergy' => 'y'
        );
        DB::table('demographics')->where('pid', '=', $pid)->update($rcopia_data);
        $this->audit('Update');
        $xml1 = "<Request><Command>update_allergy</Command>";
        $xml1 .= "<LastUpdateDate>" . $old->rcopia_update_allergy_date . "</LastUpdateDate>";
        $xml1 .= "<Patient><ExternalID>" . $pid . "</ExternalID></Patient>";
        $xml1 .= "</Request></RCExtRequest>";
        $result1 = $this->rcopia($xml1, Session::get('practice_id'));
        $response1 = new SimpleXMLElement($result1);
        $status1 = $response1->Response->Status . "";
        if ($status1 == "error") {
            $description1 = $response1->Response->Error->Text . "";
            $data1a = array(
                'action' => 'update_allergy',
                'pid' => $pid,
                'extensions_name' => 'rcopia',
                'description' => $description1,
                'practice_id' => Session::get('practice_id')
            );
            DB::table('extensions_log')->insert($data1a);
            $response = "Error connecting to DrFirst RCopia.  Try again later.";
        } else {
            $response = $this->rcopia_update_allergy_xml($pid, $result1);
        }
        echo $response;
    }

    public function postRcopiaUpdateMedication($origin)
    {
        if (Session::get('group_id') != '2' && Session::get('group_id') != '3') {
            Auth::logout();
            Session::flush();
            header("HTTP/1.1 404 Page Not Found", true, 404);
            exit("You cannot do this.");
        } else {
            $eid = Session::get('eid');
            $pid = Session::get('pid');
            $encounter_provider = Session::get('displayname');
            $old = Demographics::find($pid);
            $rcopia_data = array(
                'rcopia_update_prescription' => 'y'
            );
            DB::table('demographics')->where('pid', '=', $pid)->update($rcopia_data);
            $this->audit('Update');
            $xml1 = "<Request><Command>update_prescription</Command>";
            $xml1 .= "<LastUpdateDate>" . $old->rcopia_update_prescription_date . "</LastUpdateDate>";
            $xml1 .= "<Patient><ExternalID>" . $pid . "</ExternalID></Patient>";
            $xml1 .= "</Request></RCExtRequest>";
            $result1 = $this->rcopia($xml1, Session::get('practice_id'));
            $response1 = new SimpleXMLElement($result1);
            $status1 = $response1->Response->Status . "";
            if ($status1 == "error") {
                $description1 = $response1->Response->Error->Text . "";
                $data1a = array(
                    'action' => 'update_prescription',
                    'pid' => $pid,
                    'extensions_name' => 'rcopia',
                    'description' => $description1,
                    'practice_id' => Session::get('practice_id')
                );
                DB::table('extensions_log')->insert($data1a);
                $arr['response'] = "Error connecting to DrFirst RCopia.  Try again later.";
            } else {
                $response = $this->rcopia_update_medication_xml($pid, $result1, $origin);
                if ($response == "No updated prescriptions.") {
                    $arr['response'] = $response;
                } else {
                    $arr['medtext'] = $response;
                    $arr['response'] = "Updated medications from DrFirst Rcopia.";
                }
            }
            echo json_encode($arr);
        }
    }

    public function rcopia_sync($practice_id)
    {
        // Update Notification
        $row0 = Practiceinfo::find($practice_id);
        Config::set('app.timezone' , $row0->timezone);
        if ($row0->rcopia_update_notification_lastupdate == "") {
            $date0 = date('m/d/Y H:i:s', time());
        } else {
            $date0 = $row0->rcopia_update_notification_lastupdate;
        }
        $xml0 = "<Request><Command>update_notification</Command>";
        $xml0 .= "<LastUpdateDate>" . $date0 . "</LastUpdateDate>";
        $xml0 .= "</Request></RCExtRequest>";
        $result0 = $this->rcopia($xml0, $practice_id);
        $response0 = new SimpleXMLElement($result0);
        if ($response0->Response->Status == "error") {
            $description0 = $response0->Response->Error->Text . "";
            $data0a = array(
                'action' => 'update_notification',
                'pid' => '0',
                'extensions_name' => 'rcopia',
                'description' => $description0,
                'practice_id' => $practice_id
            );
            DB::table('extensions_log')->insert($data0a);
        } else {
            $last_update_date = $response0->Response->LastUpdateDate . "";
            $number = $response0->Response->NotificationList->Number . "";
            if ($number != "0") {
                foreach ($response0->Response->NotificationList->Notification as $notification) {
                    $type = $notification->Type . "";
                    $status = $notification->Status . "";
                    $rcopia_username = $notification->Provider->Username . "";
                    $medication_message = $notification->Sig->Drug->BrandName . "";
                    $form_message = $notification->Sig->Drug->Form . "";
                    $dose_message = $notification->Sig->Drug->Strength . "";
                    $sig_message = $notification->Sig->Dose . "";
                    $sig1_message = $notification->Sig->DoseUnit . "";
                    $route_message = $notification->Sig->Route . "";
                    $frequency_message = $notification->Sig->DoseTiming . "";
                    $instructions_message = $notification->Sig->DoseOther . "";
                    $quantity_message = $notification->Sig->Quantity . "";
                    $quantity_message1 = $notification->Sig->QuantityUnit . "";
                    $refill_message = $notification->Sig->Refills . "";
                    $pharmacy_message = $notification->Pharmacy->Name . "";
                    $medication_message = "Medication: " . $medication_message . ", " . $form_message . ", " . $dose_message;
                    $medication_message .= "\nInstructions: " . $sig_message . " " . $sig1_message . " " . $route_message . ", " . $frequency_message;
                    $medication_message .= "\nOther Instructions: " . $instructions_message;
                    $medication_message .= "\nQuantity: " . $quantity_message . " " . $quantity_message1;
                    $medication_message .= "\nRefills: " . $refill_message;
                    $medication_message .= "\nPharmacy: " . $pharmacy_message;
                    $messages_pid = $notification->Patient->ExternalID . "";
                    $sender = $notification->Sender . "";
                    $title = $notification->Title . "";
                    $text = $notification->Text . "";
                    $full_text = "From: " . $sender . "\nMessage: " . $text;
                    $patient_row = Demographics::where('pid', '=', $messages_pid)->first();
                    $dob_message = date("m/d/Y", strtotime($patient_row->DOB));
                    $patient_name =  $patient_row->lastname . ', ' . $patient_row->firstname . ' (DOB: ' . $dob_message . ') (ID: ' . $messages_pid . ')';
                    $provider_row = DB::table('users')
                        ->join('providers', 'providers.id', '=', 'users.id')
                        ->select('users.lastname', 'users.firstname', 'users.title', 'users.id')
                        ->where('providers.rcopia_username', '=', $rcopia_username)
                        ->first();
                    if ($provider_row) {
                        $provider_name = $provider_row->firstname . " " . $provider_row->lastname . ", " . $provider_row->title . " (" . $provider_row->id . ")";
                        if ($type == "refill") {
                            $subject = "Refill Request for " . $patient_name;
                            $body = $medication_message;
                        }
                        if ($type == "message") {
                            $subject = $title;
                            $body = $full_text;
                        }
                        $data_message = array(
                            'pid' => $messages_pid,
                            'message_to' => $provider_name,
                            'message_from' => $provider_row->id,
                            'subject' => $subject,
                            'body' => $body,
                            'patient_name' => $patient_name,
                            'status' => 'Sent',
                            'mailbox' => $provider_row->id,
                            'practice_id' => $practice_id
                        );
                        DB::table('messaging')->insert($data_message);
                        $this->audit('Add');
                    }
                }
            }
            $data_update = array(
                'rcopia_update_notification_lastupdate' => $last_update_date
            );
            DB::table('practiceinfo')->where('practice_id', '=', $practice_id)->update($data_update);
        }

        // Send Patient
        $query1 = Demographics::where('rcopia_sync', '=', 'n')->get();
        if ($query1) {
            foreach ($query1 as $row1) {
                if ($this->check_practice_id($row1->pid, $practice_id)) {
                    $dob = explode(" ", $row1->DOB);
                    $dob1 = explode("-", $dob[0]);
                    $dob_final = $dob1[1] . "/" . $dob1[2] . "/" . $dob1[0];
                    $xml1 = "<Request><Command>send_patient</Command><Synchronous>y</Synchronous><CheckEligibility>y</CheckEligibility>";
                    $xml1 .= "<PatientList><Patient>";
                    $xml1 .= "<FirstName>" . $row1->firstname . "</FirstName>";
                    $xml1 .= "<LastName>" . $row1->lastname . "</LastName>";
                    $xml1 .= "<MiddleName>" . $row1->middle . "</MiddleName>";
                    $xml1 .= "<DOB>" . $dob_final . "</DOB>";
                    $xml1 .= "<Sex>". $row1->sex . "</Sex>";
                    $xml1 .= "<ExternalID>" . $row1->pid . "</ExternalID>";
                    $xml1 .= "<HomePhone>" . $row1->phone_home . "</HomePhone>";
                    $xml1 .= "<WorkPhone>" . $row1->phone_work . "</WorkPhone>";
                    $xml1 .= "<Address1>" . $row1->address . "</Address1>";
                    $xml1 .= "<Address2></Address2>";
                    $xml1 .= "<City>" . $row1->city . "</City>";
                    $xml1 .= "<State>" . $row1->state . "</State>";
                    $xml1 .= "<Zip>" . $row1->zip . "</Zip>";
                    $xml1 .= "</Patient></PatientList></Request></RCExtRequest>";
                    $result1 = $this->rcopia($xml1, $practice_id);
                    $response1 = new SimpleXMLElement($result1);
                    $status1 = $response1->Response->PatientList->Patient->Status . "";
                    if ($status1 == "error") {
                        $description1 = $response1->Response->PatientList->Patient->Error->Text . "";
                        $data1a = array(
                            'action' => 'send_patient',
                            'pid' => $row1->pid,
                            'extensions_name' => 'rcopia',
                            'description' => $description1,
                            'practice_id' => $practice_id
                        );
                        DB::table('extensions_log')->insert($data1a);
                    } else {
                        $data1b = array('rcopia_sync' => 'y');
                        DB::table('demographics')->where('pid', '=', $row1->pid)->update($data1b);
                        $this->audit('Update');
                    }
                }
            }
        }

        // Send Allergy
        $query2 = Allergies::where('rcopia_sync', '=', 'n')->where('allergies_date_inactive', '=', '0000-00-00 00:00:00')->get();
        if ($query2) {
            foreach ($query2 as $row2) {
                if ($this->check_practice_id($row2->pid, $practice_id)) {
                    $da = explode(" ", $row2->allergies_date_active);
                    $da1 = explode("-", $da[0]);
                    $da_final = $da1[1] . "/" . $da1[2] . "/" . $da1[0];
                    $xml2 = "<Request><Command>send_allergy</Command><Synchronous>y</Synchronous>";
                    $xml2 .= "<AllergyList><Allergy>";
                    $xml2 .= "<ExternalID>" . $row2->allergies_id . "</ExternalID>";
                    $xml2 .= "<Patient><ExternalID>" . $row2->pid . "</ExternalID></Patient>";
                    $xml2 .= "<Allergen><Name>" . $row2->allergies_med . "</Name>";
                    $xml2 .= "<Drug><NDCID>" . $row2->meds_ndcid . "</NDCID></Drug></Allergen>";
                    $xml2 .= "<Reaction>" . $row2->allergies_reaction . "</Reaction>";
                    $xml2 .= "<OnsetDate>" . $da_final . "</OnsetDate>";
                    $xml2 .= "</Allergy></AllergyList></Request></RCExtRequest>";
                    $result2 = $this->rcopia($xml2, $practice_id);
                    $response2 = new SimpleXMLElement($result2);
                    $status2 = $response2->Response->AllergyList->Allergy->Status . "";
                    if ($status2 == "error") {
                        $description2 = $response2->Response->AllergyList->Allergy->Error->Text . "";
                        $data2a = array(
                            'action' => 'send_allergy',
                            'pid' => $row2->pid,
                            'extensions_name' => 'rcopia',
                            'description' => $description2,
                            'practice_id' => $practice_id
                        );
                        DB::table('extensions_log')->insert($data2a);
                        if ($description2 == "Can find neither name, Rcopia ID, or NDC ID for drug.") {
                            $data2c = array('rcopia_sync' => 'ye');
                            DB::table('allergies')->where('allergies_id', '=', $row2->allergies_id)->update($data2c);
                            $this->audit('Update');
                        }
                    } else {
                        $data2b = array('rcopia_sync' => 'y');
                        DB::table('allergies')->where('allergies_id', '=', $row2->allergies_id)->update($data2b);
                        $this->audit('Update');
                    }
                }
            }
        }

        //Send Medication
        $query3 = Rx_list::where('rcopia_sync', '=', 'n')->where('rxl_date_inactive', '=', '0000-00-00 00:00:00')->where('rxl_date_old', '=', '0000-00-00 00:00:00')->get();
        if ($query3) {
            foreach ($query3 as $row3) {
                if ($this->check_practice_id($row3->pid, $practice_id)) {
                    $dm = explode(" ", $row3->rxl_date_active);
                    $dm1 = explode("-", $dm[0]);
                    $dm_final = $dm1[1] . "/" . $dm1[2] . "/" . $dm1[0];
                    if ($row3->rxl_due_date != '') {
                        $dn = explode(" ", $row3->rxl_due_date);
                        $dn1 = explode("-", $dn[0]);
                        $dn_final = $dn1[1] . "/" . $dn1[2] . "/" . $dn1[0];
                    } else {
                        $dn_final = "";
                    }
                    if ($row3->rxl_ndcid != '') {
                        $ndcid = $row3->rxl_ndcid;
                    } else {
                        $ndcid = '';
                    }
                    $medication_parts1 = explode(", ", $row3->rxl_medication);
                    $generic_name = $medication_parts1[0];
                    if (isset($medication_parts[1])) {
                        $form = $medication_parts1[1];
                    } else {
                        $form = '';
                    }
                    $strength = $row3->rxl_dosage . " " . $row3->rxl_dosage_unit;
                    if ($row3->rxl_sig != '') {
                        if(strpos($row3->rxl_sig, ' ') !== false) {
                            $sig_parts1 = explode(" ", $row3->rxl_sig);
                            $dose = $sig_parts1[0];
                            $dose_unit = $sig_parts1[1];
                        } else {
                            $dose = $row3->rxl_sig;
                            $dose_unit = '';
                        }
                    } else {
                        $dose = '';
                        $dose_unit = '';
                    }
                    if ($row3->rxl_quantity != '') {
                        if(strpos($row3->rxl_quantity, ' ') !== false) {
                            $quantity_parts1 = explode(" ", $row3->rxl_quantity);
                            $quantity = $quantity_parts1[0];
                            $quantity_unit = $quantity_parts1[1];
                        } else {
                            $quantity = $row3->rxl_quantity;
                            $quantity_unit = '';
                        }
                    } else {
                        $quantity = '';
                        $quantity_unit = '';
                    }
                    if ($row3->rxl_daw != '') {
                        $daw = 'n';
                    } else {
                        $daw = 'y';
                    }
                    $xml3 = "<Request><Command>send_medication</Command><Synchronous>y</Synchronous>";
                    $xml3 .= "<MedicationList><Medication>";
                    $xml3 .= "<ExternalID>" . $row3->rxl_id . "</ExternalID>";
                    $xml3 .= "<Patient><ExternalID>" . $row3->pid . "</ExternalID></Patient>";
                    $xml3 .= "<Sig>";
                    $xml3 .= "<Drug><NDCID>" . $ndcid . "</NDCID>";
                    $xml3 .= "<GenericName>" . $generic_name . "</GenericName>";
                    $xml3 .= "<Form>" . $form . "</Form>";
                    $xml3 .= "<Strength>" . $strength . "</Strength></Drug>";
                    $xml3 .= "<Dose>" . $dose . "</Dose>";
                    $xml3 .= "<DoseUnit>" . $dose_unit . "</DoseUnit>";
                    $xml3 .= "<Route>" . $row3->rxl_route . "</Route>";
                    $xml3 .= "<DoseTiming>" . $row3->rxl_frequency . "</DoseTiming>";
                    $xml3 .= "<DoseOther>" . $row3->rxl_instructions . "</DoseOther>";
                    $xml3 .= "<Quantity>" . $quantity . "</Quantity>";
                    $xml3 .= "<QuantityUnit>" . $quantity_unit . "</QuantityUnit>";
                    $xml3 .= "<Refills>" . $row3->rxl_refill . "</Refills>";
                    $xml3 .= "<SubstitutionPermitted>" . $daw . "</SubstitutionPermitted>";
                    $xml3 .= "</Sig>";
                    $xml3 .= "<StartDate>" . $dm_final . "</StartDate>";
                    $xml3 .= "<StopDate>" . $dn_final . "</StopDate>";
                    $xml3 .= "</Medication></MedicationList></Request></RCExtRequest>";
                    $result3 = $this->rcopia($xml3, $practice_id);
                    $response3 = new SimpleXMLElement($result3);
                    $status3 = $response3->Response->MedicationList->Medication->Status . "";
                    if ($status3 == "error") {
                        $description3 = $response3->Response->MedicationList->Medication->Error->Text . "";
                        $data3a = array(
                            'action' => 'send_medication',
                            'pid' => $row3->pid,
                            'extensions_name' => 'rcopia',
                            'description' => $description3,
                            'practice_id' => $practice_id
                        );
                        DB::table('extensions_log')->insert($data3a);
                    } else {
                        $data3b = array('rcopia_sync' => 'y');
                        DB::table('rx_list')->where('rxl_id', '=',$row3->rxl_id)->update($data3b);
                        $this->audit('Update');
                    }
                }
            }
        }

        //Send Problem List
        $query4 = Issues::where('rcopia_sync', '=', 'n')->where('issue_date_inactive', '=', '0000-00-00 00:00:00')->get();
        if ($query4) {
            foreach ($query4 as $row4) {
                if ($this->check_practice_id($row4->pid, $practice_id)) {
                    $di = explode(" [", $row4->issue);
                    $code = str_replace("]", "", $di[1]);
                    $xml4 = "<Request><Command>send_problem</Command><Synchronous>y</Synchronous>";
                    $xml4 .= "<ProblemList><Problem>";
                    $xml4 .= "<ExternalID>" . $row4->issue_id . "</ExternalID>";
                    $xml4 .= "<Patient><ExternalID>" . $row4->pid . "</ExternalID></Patient>";
                    $xml4 .= "<Code>" . $code . "</Code>";
                    $xml4 .= "<Description>" . $di[0] . "</Description>";
                    $xml4 .= "</Problem></ProblemList></Request></RCExtRequest>";
                    $result4 = $this->rcopia($xml4, $practice_id);
                    $response4 = new SimpleXMLElement($result4);
                    $status4 = $response4->Response->ProblemList->Problem->Status . "";
                    if ($status4 == "error") {
                        $description4 = $response4->Response->ProblemList->Problem->Error->Text . "";
                        $data4a = array(
                            'action' => 'send_problem',
                            'pid' => $row4->pid,
                            'extensions_name' => 'rcopia',
                            'description' => $description4,
                            'practice_id' => $practice_id
                        );
                        DB::table('extensions_log')->insert($data4a);
                    } else {
                        $data4b = array('rcopia_sync' => 'y');
                        DB::table('issues')->where('issue_id', '=',$row4->issue_id)->update($data4b);
                        $this->audit('Update');
                    }
                }
            }
        }

        //Delete Allergy
        $query5 = Allergies::where('rcopia_sync', '=', 'nd')->orWhere('rcopia_sync', '=', 'nd1')->get();
        if ($query5) {
            foreach ($query5 as $row5) {
                if ($this->check_practice_id($row5->pid, $practice_id)) {
                    $dda = explode(" ", $row5->allergies_date_active);
                    $daa1 = explode("-", $dda[0]);
                    $dda_final = $dda1[1] . "/" . $dda1[2] . "/" . $dda1[0];
                    $xml5 = "<Request><Command>send_allergy</Command><Synchronous>y</Synchronous>";
                    $xml5 .= "<AllergyList><Allergy><Deleted>y</Deleted>";
                    $xml5 .= "<ExternalID>" . $row5->allergies_id . "</ExternalID>";
                    $xml5 .= "<Patient><ExternalID>" . $row5->pid . "</ExternalID></Patient>";
                    $xml5 .= "<Allergen><Name>" . $row5->allergies_med . "</Name>";
                    $xml5 .= "<Drug><NDCID>" . $row5->meds_ndcid . "</NDCID></Drug></Allergen>";
                    $xml5 .= "<Reaction>" . $row5->allergies_reaction . "</Reaction>";
                    $xml5 .= "<OnsetDate>" . $dda_final . "</OnsetDate>";
                    $xml5 .= "</Allergy></AllergyList></Request></RCExtRequest>";
                    $result5 = $this->rcopia($xml5, $practice_id);
                    $response5 = new SimpleXMLElement($result5);
                    $status5 = $response5->Response->AllergyList->Allergy->Status . "";
                    if ($status5 == "error") {
                        $description5 = $response5->Response->AllergyList->Allergy->Error->Text . "";
                        $data5a = array(
                            'action' => 'delete_allergy',
                            'pid' => $row5->pid,
                            'extensions_name' => 'rcopia',
                            'description' => $description5,
                            'practice_id' => $practice_id
                        );
                        DB::table('extensions_log')->insert($data5a);
                        $data5b = array('rcopia_sync' => 'y');
                        DB::table('allergies')->where('pid',$row5->pid)->update($data5b);
                        $this->audit('Update');
                    } else {
                        $data5b = array('rcopia_sync' => 'y');
                        DB::table('allergies')->where('allergies_id',$row5->allergies_id)->update($data5b);
                        $this->audit('Update');
                    }
                }
            }
        }

        //Delete Medication
        $query6 = Rx_list::where('rcopia_sync', '=', 'nd')->orWhere('rcopia_sync', '=', 'nd1')->get();
        if ($query6) {
            foreach ($query6 as $row6) {
                if ($this->check_practice_id($row6->pid, $practice_id)) {
                    $ddm = explode(" ", $row6->rxl_date_active);
                    $ddm1 = explode("-", $ddm[0]);
                    $ddm_final = $ddm1[1] . "/" . $ddm1[2] . "/" . $ddm1[0];
                    if ($row3->rxl_due_date != '') {
                        $ddn = explode(" ", $row6->rxl_due_date);
                        $ddn1 = explode("-", $ddn[0]);
                        $ddn_final = $ddn1[1] . "/" . $ddn1[2] . "/" . $ddn1[0];
                    } else {
                        $ddn_final = "";
                    }
                    if ($row6->rxl_ndcid != '') {
                        $ndcid1 = $row6->rxl_ndcid;
                        $generic_name1 = '';
                        $form1 = '';
                        $strength1 = '';
                    } else {
                        $ndcid1 = '';
                        $medication_parts2 = explode(", ", $row6->rxl_medication);
                        if (count($medication_parts2) > 1) {
                            $generic_name1 = $medication_parts2[0];
                            $form1 = $medication_parts2[1];
                        } else {
                            $generic_name1 = $medication_parts2[0];
                            $form1 = '';
                        }
                        $strength1 = $row6->rxl_dosage . " " . $row6->rxl_dosage_unit;
                    }
                    $sig_parts2 = explode(" ", $row6->rxl_sig);
                    if (count($sig_parts2) > 1) {
                        $dose = $sig_parts2[0];
                        $doseunit = $sig_parts2[1];
                    } else {
                        $dose = $sig_parts2[0];
                        $doseunit = '';
                    }
                    if ($row6->rxl_quantity != '') {
                        if(strpos($row6->rxl_quantity, ' ') !== false) {
                            $quantity_parts2 = explode(" ", $row6->rxl_quantity);
                            $quantity1 = $quantity_parts2[0];
                            $quantity_unit1 = $quantity_parts2[1];
                        } else {
                            $quantity1 = $row6->rxl_quantity;
                            $quantity_unit1 = '';
                        }
                    } else {
                        $quantity1 = '';
                        $quantity_unit1 = '';
                    }
                    if ($row6->rxl_daw != '') {
                        $daw1 = 'n';
                    } else {
                        $daw1 = 'y';
                    }
                    $xml6 = "<Request><Command>send_medication</Command><Synchronous>y</Synchronous>";
                    $xml6 .= "<MedicationList><Medication><Deleted>y</Deleted>";
                    $xml6 .= "<ExternalID>" . $row6->rxl_id . "</ExternalID>";
                    $xml6 .= "<Patient><ExternalID>" . $row6->pid . "</ExternalID></Patient>";
                    $xml6 .= "<Sig>";
                    $xml6 .= "<Drug><NDCID>" . $ndcid1 . "</NDCID>";
                    $xml6 .= "<GenericName>" . $generic_name1 . "</GenericName>";
                    $xml6 .= "<Form>" . $form1 . "</Form>";
                    $xml6 .= "<Strength>" . $strength1 . "</Strength></Drug>";
                    $xml6 .= "<Dose>" . $dose . "</Dose>";
                    $xml6 .= "<DoseUnit>" . $doseunit . "</DoseUnit>";
                    $xml6 .= "<Route>" . $row6->rxl_route . "</Route>";
                    $xml6 .= "<DoseTiming>" . $row6->rxl_frequency . "</DoseTiming>";
                    $xml6 .= "<DoseOther>" . $row6->rxl_instructions . "</DoseOther>";
                    $xml6 .= "<Quantity>" . $quantity1 . "</Quantity>";
                    $xml6 .= "<QuantityUnit>" . $quantity_unit1 . "</QuantityUnit>";
                    $xml6 .= "<Refills>" . $row6->rxl_refill . "</Refills>";
                    $xml6 .= "<SubstitutionPermitted>" . $daw1 . "</SubstitutionPermitted>";
                    $xml6 .= "</Sig>";
                    $xml6 .= "<StartDate>" . $ddm_final . "</StartDate>";
                    $xml6 .= "<StopDate>" . $ddn_final . "</StopDate>";
                    $xml6 .= "</Medication></MedicationList></Request></RCExtRequest>";
                    $result6 = $this->rcopia($xml6, $practice_id);
                    $response6 = new SimpleXMLElement($result6);
                    $status6 = $response6->Response->MedicationList->Medication->Status . "";
                    if ($status6 == "error") {
                        $description6 = $response3->Response->MedicationList->Medication->Error->Text . "";
                        $data6a = array(
                            'action' => 'delete_medication',
                            'pid' => $row6->pid,
                            'extensions_name' => 'rcopia',
                            'description' => $description6,
                            'practice_id' => $practice_id
                        );
                        DB::table('extensions_log')->insert($data6a);
                        $data6b = array('rcopia_sync' => 'y');
                        DB::table('rx_list')->where('pid', '=', $row6->pid)->update($data6b);
                        $this->audit('Update');
                    } else {
                        $data6b = array('rcopia_sync' => 'y');
                        DB::table('rx_list')->where('rxl_id', '=', $row6->rxl_id)->update($data6b);
                        $this->audit('Update');
                    }
                }
            }
        }

        //Delete Problem List
        $query7 = Issues::where('rcopia_sync', '=', 'nd')->orWhere('rcopia_sync', '=', 'nd1')->get();
        if ($query7) {
            foreach ($query7 as $row7) {
                if ($this->check_practice_id($row7->pid, $practice_id)) {
                    $ddi = explode(" [", $row7->issue);
                    $code1 = str_replace("]", "", $ddi[1]);
                    $xml7 = "<Request><Command>send_problem</Command><Synchronous>y</Synchronous>";
                    $xml7 .= "<ProblemList><Problem><Deleted>y</Deleted>";
                    $xml7 .= "<ExternalID>" . $row7->issue_id . "</ExternalID>";
                    $xml7 .= "<Patient><ExternalID>" . $row7->pid . "</ExternalID></Patient>";
                    $xml7 .= "<Code>" . $code1 . "</Code>";
                    $xml7 .= "<Description>" . $ddi[0] . "</Description>";
                    $xml7 .= "</Problem></ProblemList></Request></RCExtRequest>";
                    $result7 = $this->rcopia($xml7, $practice_id);
                    $response7 = new SimpleXMLElement($result7);
                    $status7 = $response7->Response->ProblemList->Problem->Status . "";
                    if ($status7 == "error") {
                        $description7 = $response7->Response->ProblemList->Problem->Error->Text . "";
                        $data7a = array(
                            'action' => 'delete_problem',
                            'pid' => $row7->pid,
                            'extensions_name' => 'rcopia',
                            'description' => $description7,
                            'practice_id' => $practice_id
                        );
                        DB::table('extensions_log')->insert($data7a);
                        $data7b = array('rcopia_sync' => 'y');
                        DB::table('issues')->where('pid', '=', $row7->pid)->update($data7b);
                        $this->audit('Update');
                    } else {
                        $data7b = array('rcopia_sync' => 'y');
                        DB::table('issues')->where('issue_id', '=', $row7->issue_id)->update($data7b);
                        $this->audit('Update');
                    }
                }
            }
        }
    }

    public function postElectronicOrders()
    {
        $pid = Session::get('pid');
        $orders_id = Input::get('orders_id');
        $row = Orders::find($orders_id);
        $row1 = Addressbook::find($row->address_id);
        if ($row1->electronic_order == '') {
            echo "Laboratory provider is not configured for electronic order entry.  Please use an alternate method for delivery.";
            exit(0);
        } else {
            $row2 = Demographics::find($pid);
            $row3 = User::find($row->id);
            $row4 = Providers::find($row->id);
            if ($row1->electronic_order == 'PeaceHealth') {
                $date = date('YmdHi');
                $dob = date('Ymd', $this->human_to_unix($row2->DOB));
                $order_date = date('YmdHi', $this->human_to_unix($row->orders_pending_date));
                $middle = substr($row3->middle, 0, 1);
                $pname = substr($row3->lastname, 0, 5) . substr($row3->firstname, 0, 1) . substr($row3->middle, 0, 1);
                $hl7 = "MSH|^~\&|QDX|" . strtoupper($pname) . "|||" . $date . "00||ORM^O01|R10063131003.1|P|2.3||^" . strtoupper($pname);
                $hl7 .= "\r";
                $hl7 .= "PID|1|" . $pid . "|||" . strtoupper($row2->lastname) . "^" . strtoupper($row2->firstname) . "||" . $dob ."|" . strtoupper($row2->sex) . "|||||||||||";
                $hl7 .= "\r";
                $hl7 .= "ORC|NW|" . $orders_id . "|||A||^^^" . $order_date . "00||" . $date . "00|||" . strtoupper($row4->peacehealth_id) . "^" . strtoupper($row3->lastname) ."^" . strtoupper($row3->firstname) . "^" . strtoupper($middle) . "^^^" . strtoupper($row3->title) . "||||^|" . strtoupper($row4->peacehealth_id) . "^" . strtoupper($row3->lastname) ."^" . strtoupper($row3->firstname) . "^" . strtoupper($middle) . "^^^" . strtoupper($row3->title) . "||||100^QA-Central Laboratory|QA-Central Laboratory|QA-Central Laboratory^123 International Way^Springfield^OR^97477|1-800-826-3616";
                $orders_array = explode("\n", $row->orders_labs);
                $j = 1;
                foreach ($orders_array as $orders_row) {
                    if ($orders_row != "") {
                        $orders_row_array = explode(";", $orders_row);
                        $testname = $orders_row_array[0];
                        $i = 0;
                        foreach ($orders_row_array as $orders_row1) {
                            if (strpos($orders_row1, " Code: ") !== false) {
                                $testcode = str_replace(" Code: ", "", $orders_row1);
                                $i++;
                            }
                            if (strpos($orders_row1, " AOEAnswer: ") !== false) {
                                $aoe_answer = str_replace(" AOEAnswer: ", "", $orders_row1);
                                if (strpos($aoe_answer, "|") !== false) {
                                    $aoe_answer_array = explode("|", $aoe_answer);
                                } else {
                                    $aoe_answer_array[] = $aoe_answer;
                                }
                            }
                            if (strpos($orders_row1, " AOECode: ") !== false) {
                                $aoe_code = str_replace(" AOECode: ", "", $orders_row1);
                                $aoe_code = str_replace("\r", "", $aoe_code);
                                if (strpos($aoe_code, "|") !== false) {
                                    $aoe_code_array = explode("|", $aoe_code);
                                } else {
                                    $aoe_code_array[] = $aoe_code;
                                }
                            }
                        }
                        if ($i == 0) {
                            echo "Laboratory order code is missing for the electronic order entry.  Be sure you are choosing an order from an Electronic Order Entry list";
                            exit(0);
                        }
                        $hl7 .= "\r";
                        $orders_cc = '';
                        $hl7 .= "OBR|" . $j . "|" . $orders_id . "||" . strtoupper($testcode) . "^" . strtoupper($testname) . "^^|R|" . $order_date . "00|" .$date . "|||||||" . $date . "00|SST^BLD|96666|||||PHL^PeaceHealth Laboratories^123 International Way^Springfield^OR^97477|||||||" . $orders_cc . "|";
                        $j++;
                    }
                }
                if ($row->orders_insurance != 'Bill Client') {
                    $in1_array = explode("\n", $row->orders_insurance);
                    $k = 1;
                    foreach ($in1_array as $in1_row) {
                        $in1_array1 = explode(";", $in1_row);
                        $payor_id = str_replace(" Payor ID: ", "", $in1_array1[1]);
                        if ($payor_id == "Unknown") {
                            $payor_id = 'UNK.';
                        }
                        $plan_id = str_replace(" ID: ", "", $in1_array1[2]);
                        if (strpos($in1_array1[3], " Group: ") !== false) {
                            $group_id = str_replace(" Group: ", "", $in1_array1[3]);
                            $name_array = explode(", ", $in1_array1[4]);
                        } else {
                            $group_id = "";
                            $name_array = explode(", ", $in1_array1[3]);
                        }
                        $hl7 .= "\r";
                        $hl7 .= "IN1|" . $k . "|UNK.|" . strtoupper($payor_id) . "|" . strtoupper($in1_array1[0]) . "||||" . strtoupper($group_id) . "||||||||" . strtoupper($name_array[0]) . "^" . strtoupper($name_array[1]) . "^^^||||||||||||||||||||" . strtoupper($plan_id) . "|||||||||";
                        $k++;
                    }
                }
                if (isset($aoe_answer_array)) {
                    for ($l=0; $l<count($aoe_answer_array); $l++) {
                        $hl7 .= "\r";
                        $m = $l+1;
                        $hl7 .= "OBX|" . $m ."||" . strtoupper($aoe_code_array[$l]) . "||" . strtoupper($aoe_answer_array[$l]) . "||||||P|||" . $date ."00|";
                    }
                }
                $file = "/srv/ftp/shared/export/PHLE_" . time();
                file_put_contents($file, $hl7);
                echo "Electronic order entry sent!";
                exit(0);
            }
        }
    }

}
