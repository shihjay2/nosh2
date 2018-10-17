<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

// Authentication routes
Route::any('accept_invitation/{id}', ['as' => 'accept_invitation', 'uses' => 'LoginController@accept_invitation']);
Route::post('as_sync', ['as' => 'as_sync', 'uses' => 'LoginController@as_sync']);
Route::get('fhir/oidc', ['as' => 'oidc_api', 'uses' => 'LoginController@oidc_api']);
Route::any('google_auth', ['as' => 'google_auth', 'uses' => 'LoginController@google_auth']);
Route::any('googleoauth', ['as' => 'googleoauth', 'uses' => 'LoginController@googleoauth']);
Route::any('login/{type?}', ['as' => 'login', 'uses' => 'LoginController@login']);
Route::post('login_uport', ['as' => 'login_uport', 'middleware' => 'csrf', 'uses' => 'LoginController@login_uport']);
Route::any('logout', ['as' => 'logout', 'uses' => 'LoginController@logout']);
Route::any('oidc', ['as' => 'oidc', 'uses' => 'LoginController@oidc']); // Login with mdNOSH
Route::get('oidc_check_patient_centric', ['as' => 'oidc_check_patient_centric', 'uses' => 'LoginController@oidc_check_patient_centric']);
Route::any('oidc_logout', ['as' => 'oidc_logout', 'uses' => 'LoginController@oidc_logout']);
Route::get('oidc_register_client', ['as' => 'oidc_register_client', 'uses' => 'LoginController@oidc_register_client']);
Route::any('password_email', ['as' => 'password_email', 'uses' => 'LoginController@password_email']);
Route::any('password_reset_response/{id}', ['as' => 'password_reset_response', 'uses' => 'LoginController@password_reset_response']);
Route::any('practice_choose', ['as' => 'practice_choose', 'uses' => 'LoginController@practice_choose']);
Route::post('practice_logo_login', ['as' => 'practice_logo_login', 'uses' => 'LoginController@practice_logo_login']);
Route::any('register_user', ['as' => 'register_user', 'uses' => 'LoginController@register_user']);
Route::get('remote_logout', ['as' => 'remote_logout', 'uses' => 'LoginController@remote_logout']);
Route::get('reset_demo', ['as' => 'reset_demo', 'uses' => 'LoginController@reset_demo']);
Route::get('smart_on_fhir_list', ['as' => 'smart_on_fhir_list', 'uses' => 'LoginController@smart_on_fhir_list']);
Route::get('start/{practicehandle?}', ['as' => 'start', 'uses' => 'LoginController@start']);
Route::get('transactions', ['as' => 'transactions', 'uses' => 'LoginController@transactions']);
Route::any('uma_auth', ['as' => 'uma_auth', 'uses' => 'LoginController@uma_auth']); // Login with HIE of One AS
Route::get('uma_invitation_request', ['as' => 'uma_invitation_request', 'uses' => 'LoginController@uma_invitation_request']);
Route::any('uma_logout', ['as' => 'uma_logout', 'uses' => 'LoginController@uma_logout']);

// Install routes
Route::any('backup', ['as' => 'backup', 'uses' => 'InstallController@backup']);
Route::any('google_start', ['as' => 'google_start', 'uses' => 'InstallController@google_start']);
Route::any('install/{type}', ['as' => 'install', 'uses' => 'InstallController@install']);
Route::any('install_fix', ['as' => 'install_fix', 'uses' => 'InstallController@install_fix']);
Route::post('pnosh_install', ['as' => 'pnosh_install', 'uses' => 'InstallController@pnosh_install']);
Route::any('prescription_pharmacy_view/{id}/{ret?}', ['as' => 'prescription_pharmacy_view', 'uses' => 'InstallController@prescription_pharmacy_view']);
Route::get('set_version', ['as' => 'set_version', 'uses' => 'InstallController@set_version']);
Route::any('setup_mail', ['as' => 'setup_mail', 'uses' => 'InstallController@setup_mail']);
Route::get('setup_mail_test', ['as' => 'setup_mail_test', 'uses' => 'InstallController@setup_mail_test']);
Route::get('uma_patient_centric', ['as' => 'uma_patient_centric', 'uses' => 'InstallController@uma_patient_centric']);
Route::any('uma_patient_centric_designate', ['as' => 'uma_patient_centric_designate', 'uses' => 'InstallController@uma_patient_centric_designate']);
Route::get('update', ['as' => 'update_install', 'uses' => 'InstallController@update']);
Route::get('update_env', ['as' => 'update_env', 'uses' => 'InstallController@update_env']);
Route::get('update_system/{type?}', ['as' => 'update_system', 'uses' => 'InstallController@update_system']);
Route::post('get_state_data', ['as' => 'get_state_data', 'uses' => 'AjaxInstallController@get_state_data']);

// Core routes
Route::get('/', ['as' => 'dashboard', 'uses' => 'CoreController@dashboard']);
Route::any('add_patient', ['as' => 'add_patient', 'uses' => 'CoreController@add_patient']);
Route::get('addressbook/{type}', ['as' => 'addressbook', 'uses' => 'CoreController@addressbook']);
Route::get('audit_logs', ['as' => 'audit_logs', 'uses' => 'CoreController@audit_logs']);
Route::get('billing_list/{type}/{pid}', ['as' => 'billing_list', 'uses' => 'CoreController@billing_list']);
Route::any('core_action/{table}/{action}/{id}/{index}/{subtype?}', ['as' => 'core_action', 'uses' => 'CoreController@core_action']);
Route::get('core_form/{table}/{index}/{id}/{subtype?}', ['as' => 'core_form', 'uses' => 'CoreController@core_form']);
Route::any('configure_form_edit/{type}/{item}', ['as' => 'configure_form_edit', 'uses' => 'CoreController@configure_form_edit']);
Route::any('configure_form_delete/{type}', ['as' => 'configure_form_delete', 'uses' => 'CoreController@configure_form_delete']);
Route::any('configure_form_details/{type}', ['as' => 'configure_form_details', 'uses' => 'CoreController@configure_form_details']);
Route::any('configure_form_list', ['as' => 'configure_form_list', 'uses' => 'CoreController@configure_form_list']);
Route::any('configure_form_remove/{type}/{item}', ['as' => 'configure_form_remove', 'uses' => 'CoreController@configure_form_remove']);
Route::any('configure_form_scoring/{type}/{item}', ['as' => 'configure_form_scoring', 'uses' => 'CoreController@configure_form_scoring']);
Route::get('configure_form_scoring_delete/{type}/{item}', ['as' => 'configure_form_scoring_delete', 'uses' => 'CoreController@configure_form_scoring_delete']);
Route::get('configure_form_scoring_list/{type}', ['as' => 'configure_form_scoring_list', 'uses' => 'CoreController@configure_form_scoring_list']);
Route::get('configure_form_show/{type}', ['as' => 'configure_form_show', 'uses' => 'CoreController@configure_form_show']);
Route::get('dashboard_encounters', ['as' => 'dashboard_encounters', 'uses' => 'CoreController@dashboard_encounters']);
Route::get('dashboard_reminders', ['as' => 'dashboard_reminders', 'uses' => 'CoreController@dashboard_reminders']);
Route::get('dashboard_tests', ['as' => 'dashboard_tests', 'uses' => 'CoreController@dashboard_tests']);
Route::any('dashboard_tests_reconcile/{id}', ['as' => 'dashboard_tests_reconcile', 'uses' => 'CoreController@dashboard_tests_reconcile']);
Route::get('dashboard_t_messages', ['as' => 'dashboard_t_messages', 'uses' => 'CoreController@dashboard_t_messages']);
Route::get('database_export/{tracK_id?}', ['as' => 'database_export', 'uses' => 'CoreController@database_export']);
Route::any('database_import', ['as' => 'database_import', 'uses' => 'CoreController@database_import']);
Route::any('database_import_cloud', ['as' => 'database_import_cloud', 'uses' => 'CoreController@database_import_cloud']);
Route::any('database_import_file', ['as' => 'database_import_file', 'uses' => 'CoreController@database_import_file']);
Route::get('download_ccda_entire/{track_id?}', ['as' => 'download_ccda_entire', 'uses' => 'CoreController@download_ccda_entire']);
Route::get('download_charts_entire/{track_id?}', ['as' => 'download_charts_entire', 'uses' => 'CoreController@download_charts_entire']);
Route::get('download_csv_demographics/{track_id?}', ['as' => 'download_csv_demographics', 'uses' => 'CoreController@download_csv_demographics']);
Route::get('download_now', ['as' => 'download_now', 'uses' => 'CoreController@download_now']);
Route::get('fax_action/{action}/{id}/{pid}/{subtype?}', ['as' => 'fax_action', 'uses' => 'CoreController@fax_action']);
Route::any('fax_queue/{action}/{id}/{pid}/{subtype?}', ['as' => 'fax_queue', 'uses' => 'CoreController@fax_queue']);
Route::get('financial/{type}', ['as' => 'financial', 'uses' => 'CoreController@financial']);
Route::get('financial_era/{era_id}', ['as' => 'financial_era', 'uses' => 'CoreController@financial_era']);
Route::any('financial_era_form', ['as' => 'financial_era_form', 'uses' => 'CoreController@financial_era_form']);
Route::get('financial_insurance/{id}', ['as' => 'financial_insurance', 'uses' => 'CoreController@financial_insurance']);
Route::get('financial_patient/{action}/{pid}/{eid}', ['as' => 'financial_patient', 'uses' => 'CoreController@financial_patient']);
Route::get('financial_queue/{type}/{eid}', ['as' => 'financial_queue', 'uses' => 'CoreController@financial_queue']);
Route::post('financial_query', ['as' => 'financial_query', 'uses' => 'CoreController@financial_query']);
Route::get('financial_resubmit/{eid}', ['as' => 'financial_resubmit', 'uses' => 'CoreController@financial_resubmit']);
Route::any('financial_upload_era', ['as' => 'financial_upload_era', 'uses' => 'CoreController@financial_upload_era']);
Route::get('generate_hcfa/{eid}', ['as' => 'generate_hcfa', 'uses' => 'CoreController@generate_hcfa']);
Route::get('messaging/{type}', ['as' => 'messaging', 'uses' => 'CoreController@messaging']);
Route::any('messaging_add_photo/{message_id}', ['as' => 'messaging_add_photo', 'uses' => 'CoreController@messaging_add_photo']);
Route::get('messaging_delete_photo/{id}/{type?}', ['as' => 'messaging_delete_photo', 'uses' => 'CoreController@messaging_delete_photo']);
Route::any('messaging_editdoc/{id}/{type}', ['as' => 'messaging_editdoc', 'uses' => 'CoreController@messaging_editdoc']);
Route::get('messaging_editdoc_cancel/{id}/{type}', ['as' => 'messaging_editdoc_cancel', 'uses' => 'CoreController@messaging_editdoc_cancel']);
Route::any('messaging_editdoc_process/{id}/{type}', ['as' => 'messaging_editdoc_process', 'uses' => 'CoreController@messaging_editdoc_process']);
Route::get('messaging_export/{id}', ['as' => 'messaging_export', 'uses' => 'CoreController@messaging_export']);
Route::any('messaging_sendfax/{id}', ['as' => 'messaging_sendfax', 'uses' => 'CoreController@messaging_sendfax']);
Route::any('messaging_sendfax_upload/{job_id}', ['as' => 'messaging_sendfax_upload', 'uses' => 'CoreController@messaging_sendfax_upload']);
Route::get('messaging_view/{id}', ['as' => 'messaging_view', 'uses' => 'CoreController@messaging_view']);
Route::get('messaging_viewdoc/{id}/{type}', ['as' => 'messaging_viewdoc', 'uses' => 'CoreController@messaging_viewdoc']);
Route::any('password_change', ['as' => 'password_change', 'uses' => 'CoreController@password_change']);
Route::any('password_reset/{id}', ['as' => 'password_reset', 'uses' => 'CoreController@password_reset']);
Route::get('pnosh_provider_redirect', ['as' => 'pnosh_provider_redirect', 'uses' => 'CoreController@pnosh_provider_redirect']);
Route::any('practice_cancel', ['as' => 'practice_cancel', 'uses' => 'CoreController@practice_cancel']);
Route::any('practice_logo_upload', ['as' => 'practice_logo_upload', 'uses' => 'CoreController@practice_logo_upload']);
Route::get('prescription_view/{id?}', ['as' => 'prescription_view', 'uses' => 'CoreController@prescription_view']);
Route::get('print_batch/{type}', ['as' => 'print_batch', 'uses' => 'CoreController@print_batch']);
Route::get('print_chart_admin/{id}', ['as' => 'print_chart_admin', 'uses' => 'CoreController@print_chart_admin']);
Route::get('print_chart_request/{id}/{type}/{download?}', ['as' => 'print_chart_request', 'uses' => 'CoreController@print_chart_request']);
Route::get('printimage_single/{eid}', ['as' => 'printimage_single', 'uses' => 'CoreController@printimage_single']);
Route::get('print_invoice1/{eid}/{insurance_id_1}/{insurance_id_2}', ['as' => 'print_invoice1', 'uses' => 'CoreController@print_invoice1']);
Route::get('print_invoice2/{id}/{pid}', ['as' => 'print_invoice2', 'uses' => 'CoreController@print_invoice2']);
Route::get('print_medication/{id}/{pid}/{download?}', ['as' => 'print_medication', 'uses' => 'CoreController@print_medication']);
Route::get('print_medication_combined/{download?}', ['as' => 'print_medication_combined', 'uses' => 'CoreController@print_medication_combined']);
Route::get('print_orders/{id}/{pid}/{download?}', ['as' => 'print_orders', 'uses' => 'CoreController@print_orders']);
Route::get('print_queue/{action}/{id}/{pid}/{subtype?}', ['as' => 'print_queue', 'uses' => 'CoreController@print_queue']);
Route::any('restore_backup', ['as' => 'restore_backup', 'uses' => 'CoreController@restore_backup']);
Route::get('set_patient/{pid}', ['as' => 'set_patient', 'uses' => 'CoreController@set_patient']);
Route::get('schedule_provider_exceptions/{type}', ['as' => 'schedule_provider_exceptions', 'uses' => 'CoreController@schedule_provider_exceptions']);
Route::get('schedule_visit_types/{type}', ['as' => 'schedule_visit_types', 'uses' => 'CoreController@schedule_visit_types']);
Route::get('setup', ['as' => 'setup', 'uses' => 'CoreController@setup']);
Route::any('superquery/{type}', ['as' => 'superquery', 'uses' => 'CoreController@superquery']);
Route::get('superquery_delete/{type}', ['as' => 'superquery_delete', 'uses' => 'CoreController@superquery_delete']);
Route::any('superquery_hedis/{type}', ['as' => 'superquery_hedis', 'uses' => 'CoreController@superquery_hedis']);
Route::get('superquery_list', ['as' => 'superquery_list', 'uses' => 'CoreController@superquery_list']);
Route::get('superquery_patient/{action}/{pid}/{id?}/{id1?}/{id2?}/{id3?}', ['as' => 'superquery_patient', 'uses' => 'CoreController@superquery_patient']);
Route::any('superquery_tag', ['as' => 'superquery_tag', 'uses' => 'CoreController@superquery_tag']);
Route::get('supplements/{type}', ['as' => 'supplements', 'uses' => 'CoreController@supplements']);
Route::post('supplements_sales_tax', ['as' => 'supplements_sales_tax', 'uses' => 'CoreController@supplements_sales_tax']);
Route::any('uma_aat', ['as' => 'uma_aat', 'uses' => 'CoreController@uma_aat']);
Route::get('uma_add_patient/{type?}', ['as' => 'uma_add_patient', 'uses' => 'CoreController@uma_add_patient']);
Route::any('uma_api', ['as' => 'uma_api', 'uses' => 'CoreController@uma_api']);
Route::any('uma_list', ['as' => 'uma_list', 'uses' => 'CoreController@uma_list']);
Route::get('uma_resources/{id}', ['as' => 'uma_resources', 'uses' => 'CoreController@uma_resources']);
Route::get('uma_resource_view/{type}', ['as' => 'uma_resource_view', 'uses' => 'CoreController@uma_resource_view']);
Route::get('users/{type}/{active}', ['as' => 'users', 'uses' => 'CoreController@users']);
Route::any('user_signature', ['as' => 'user_signature', 'uses' => 'CoreController@user_signature']);
Route::get('vaccines/{type}', ['as' => 'vaccines', 'uses' => 'CoreController@vaccines']);
Route::get('last_page/{hash}', ['as' => 'last_page', 'uses' => 'AjaxCoreController@last_page']);
Route::post('add_cpt', ['as' => 'add_cpt', 'uses' => 'AjaxCoreController@add_cpt']);
Route::post('check_cpt', ['as' => 'check_cpt', 'uses' => 'AjaxCoreController@check_cpt']);
Route::post('document_delete', ['as' => 'document_delete', 'uses' => 'AjaxCoreController@document_delete']);
Route::post('education', ['as' => 'education', 'uses' => 'AjaxCoreController@education']);
Route::post('notification', ['as' => 'notification', 'uses' => 'AjaxCoreController@notification']);
Route::post('messaging_session', ['as' => 'messaging_session', 'uses' => 'AjaxCoreController@messaging_session']);
Route::post('progress', ['as' => 'progress', 'uses' => 'AjaxCoreController@progress']);
Route::post('image_dimensions', ['as' => 'image_dimensions', 'uses' => 'AjaxCoreController@image_dimensions']);
Route::post('update_cpt', ['as' => 'update_cpt', 'uses' => 'AjaxCoreController@update_cpt']);
Route::post('read_message', ['as' => 'read_message', 'uses' => 'AjaxCoreController@read_message']);
Route::post('superquery_tag_view', ['as' => 'superquery_tag_view', 'uses' => 'AjaxCoreController@superquery_tag_view']);

// Chart routes
Route::any('action_edit/{table}/{index}/{id}/{yaml_id}/{column?}', ['as' => 'action_edit', 'uses' => 'ChartController@action_edit']);
Route::get('alerts_list/{type}', ['as' => 'alerts_list', 'uses' => 'ChartController@alerts_list']);
Route::get('allergies_list/{type}', ['as' => 'allergies_list', 'uses' => 'ChartController@allergies_list']);
Route::get('billing_delete_invoice/{id}', ['as' => 'billing_delete_invoice', 'uses' => 'ChartController@billing_delete_invoice']);
Route::any('billing_details/{id}', ['as' => 'billing_details', 'uses' => 'ChartController@billing_details']);
Route::any('billing_make_payment/{id}/{index}/{billing_id?}', ['as' => 'billing_make_payment', 'uses' => 'ChartController@billing_make_payment']);
Route::any('billing_notes', ['as' => 'billing_notes', 'uses' => 'ChartController@billing_notes']);
Route::get('billing_payment_delete/{id}/{index}/{billing_id}', ['as' => 'billing_payment_delete', 'uses' => 'ChartController@billing_payment_delete']);
Route::get('billing_payment_history/{id}/{index}', ['as' => 'billing_payment_history', 'uses' => 'ChartController@billing_payment_history']);
Route::get('care_opportunities/{type}', ['as' => 'care_opportunities', 'uses' => 'ChartController@care_opportunities']);
Route::any('cms_bluebutton/{as?}', ['as' => 'cms_bluebutton', 'uses' => 'ChartController@cms_bluebutton']);
Route::get('cms_bluebutton_display/{type?}', ['as' => 'cms_bluebutton_display', 'uses' => 'ChartController@cms_bluebutton_display']);
Route::get('cms_bluebutton_eob/{sequence}', ['as' => 'cms_bluebutton_eob', 'uses' => 'ChartController@cms_bluebutton_eob']);
Route::any('chart_action/{table}/{action}/{id}/{index}', ['as' => 'chart_action', 'uses' => 'ChartController@chart_action']);
Route::get('chart_form/{table}/{index}/{id}/{subtype?}', ['as' => 'chart_form', 'uses' => 'ChartController@chart_form']);
Route::get('chart_queue/{action}/{hippa_id}/{pid}/{type}', ['as' => 'chart_queue', 'uses' => 'ChartController@chart_queue']);
Route::get('conditions_list/{type}', ['as' => 'conditions_list', 'uses' => 'ChartController@conditions_list']);
Route::get('demographics', ['as' => 'demographics', 'uses' => 'ChartController@demographics']);
Route::any('demographics_add_photo', ['as' => 'demographics_add_photo', 'uses' => 'ChartController@demographics_add_photo']);
Route::any('document_letter', ['as' => 'document_letter', 'uses' => 'ChartController@document_letter']);
Route::any('document_upload', ['as' => 'document_upload', 'uses' => 'ChartController@document_upload']);
Route::get('document_view/{id}', ['as' => 'document_view', 'uses' => 'ChartController@document_view']);
Route::get('documents_list/{type}', ['as' => 'documents_list', 'uses' => 'ChartController@documents_list']);
Route::get('download_ccda/{action}/{hippa_id}', ['as' => 'download_ccda', 'uses' => 'ChartController@download_ccda']);
Route::get('electronic_sign/{action}/{id}/{pid}/{subtype?}', ['as' => 'electronic_sign', 'uses' => 'ChartController@electronic_sign']);
Route::get('electronic_sign_demo/{table}/{index}/{id}', ['as' => 'electronic_sign_demo', 'uses' => 'ChartController@electronic_sign_demo']);
Route::post('electronic_sign_gas', ['as' => 'electronic_sign_gas', 'uses' => 'AjaxChartController@electronic_sign_gas']);
Route::post('electronic_sign_login', ['as' => 'electronic_sign_login', 'uses' => 'AjaxChartController@electronic_sign_login']);
Route::post('electronic_sign_process/{table}/{index}/{id}', ['as' => 'electronic_sign_process', 'uses' => 'AjaxChartController@electronic_sign_process']);
Route::any('encounter/{eid}/{section?}', ['as' => 'encounter', 'uses' => 'ChartController@encounter']);
Route::any('encounter_addendum/{eid}', ['as' => 'encounter_addendum', 'uses' => 'ChartController@encounter_addendum']);
Route::any('encounter_add_photo/{eid}/{type?}', ['as' => 'encounter_add_photo', 'uses' => 'ChartController@encounter_add_photo']);
Route::get('encounter_assessment_add/{type}/{id}', ['as' => 'encounter_assessment_add', 'uses' => 'ChartController@encounter_assessment_add']);
Route::get('encounter_assessment_delete/{id}', ['as' => 'encounter_assessment_delete', 'uses' => 'ChartController@encounter_assessment_delete']);
Route::any('encounter_assessment_edit/{id}', ['as' => 'encounter_assessment_edit', 'uses' => 'ChartController@encounter_assessment_edit']);
Route::get('encounter_assessment_move/{id}/{direction}', ['as' => 'encounter_assessment_move', 'uses' => 'ChartController@encounter_assessment_move']);
// GYN 20181007: Add Copy to Problem List
Route::get('encounter_assessment_copy/{id}', ['as' => 'encounter_assessment_copy', 'uses' => 'ChartController@encounter_assessment_copy']);
Route::any('encounter_billing/{eid}/{section?}', ['as' => 'encounter_billing', 'uses' => 'ChartController@encounter_billing']);
Route::get('encounter_close', ['as' => 'encounter_close', 'uses' => 'ChartController@encounter_close']);
Route::get('encounter_delete_photo/{id}', ['as' => 'encounter_delete_photo', 'uses' => 'ChartController@encounter_delete_photo']);
Route::any('encounter_details/{eid}', ['as' => 'encounter_details', 'uses' => 'ChartController@encounter_details']);
Route::any('encounter_edit_image/{id?}/{t_messages_id?}', ['as' => 'encounter_edit_image', 'uses' => 'ChartController@encounter_edit_image']);
Route::any('encounter_education', ['as' => 'encounter_education', 'uses' => 'ChartController@encounter_education']);
Route::get('encounter_form_add/{id}', ['as' => 'encounter_form_add', 'uses' => 'ChartController@encounter_form_add']);
Route::get('encounter_print_plan/{eid}', ['as' => 'encounter_print_plan', 'uses' => 'ChartController@encounter_print_plan']);
Route::any('encounter_save/{eid}/{section}', ['as' => 'encounter_save', 'uses' => 'ChartController@encounter_save']);
Route::get('encounter_sign/{eid}', ['as' => 'encounter_sign', 'uses' => 'ChartController@encounter_sign']);
Route::any('encounter_view/{eid}/{previous?}', ['as' => 'encounter_view', 'uses' => 'ChartController@encounter_view']);
Route::get('encounter_vitals_view/{eid?}', ['as' => 'encounter_vitals_view', 'uses' => 'ChartController@encounter_vitals_view']);
Route::get('encounter_vitals_chart/{type}', ['as' => 'encounter_vitals_chart', 'uses' => 'ChartController@encounter_vitals_chart']);
Route::get('encounters_list', ['as' => 'encounters_list', 'uses' => 'ChartController@encounters_list']);
Route::get('family_history', ['as' => 'family_history', 'uses' => 'ChartController@family_history']);
Route::any('family_history_sensitive/{id}', ['as' => 'family_history_sensitive', 'uses' => 'ChartController@family_history_sensitive']);
Route::any('family_history_update/{id}', ['as' => 'family_history_update', 'uses' => 'ChartController@family_history_update']);
Route::any('fhir_connect/{id?}/{as?}', ['as' => 'fhir_connect', 'uses' => 'ChartController@fhir_connect']);
Route::any('fhir_connect_display/{type?}', ['as' => 'fhir_connect_display', 'uses' => 'ChartController@fhir_connect_display']);
Route::any('fhir_connect_response', ['as' => 'fhir_connect_response', 'uses' => 'ChartController@fhir_connect_response']);
Route::get('form_list/{type}', ['as' => 'form_list', 'uses' => 'ChartController@form_list']);
Route::any('form_show/{id}/{type}/{origin?}', ['as' => 'form_show', 'uses' => 'ChartController@form_show']);
Route::get('form_view/{id}', ['as' => 'form_view', 'uses' => 'ChartController@form_view']);
Route::get('generate_hcfa1/{eid}/{insurance_id_1}/{insurance_id_2}', ['as' => 'generate_hcfa1', 'uses' => 'ChartController@generate_hcfa1']);
Route::post('get_appointments', ['as' => 'get_appointments', 'uses' => 'AjaxChartController@get_appointments']);
Route::get('growth_chart/{type}', ['as' => 'growth_chart', 'uses' => 'ChartController@growth_chart']);
Route::get('medications_list/{type}', ['as' => 'medications_list', 'uses' => 'ChartController@medications_list']);
Route::get('immunizations_csv', ['as' => 'immunizations_csv', 'uses' => 'ChartController@immunizations_csv']);
Route::get('immunizations_list', ['as' => 'immunizations_list', 'uses' => 'ChartController@immunizations_list']);
Route::any('immunizations_notes', ['as' => 'immunizations_notes', 'uses' => 'ChartController@immunizations_notes']);
Route::get('immunizations_print', ['as' => 'immunizations_print', 'uses' => 'ChartController@immunizations_print']);
Route::any('inventory/{action}/{id}/{pid}/{subtype?}', ['as' => 'inventory', 'uses' => 'ChartController@inventory']);
Route::get('patient', ['as' => 'patient', 'uses' => 'ChartController@patient']);
Route::get('payors_list/{type}', ['as' => 'payors_list', 'uses' => 'ChartController@payors_list']);
Route::get('print_action/{action}/{id}/{pid}/{subtype?}', ['as' => 'print_action', 'uses' => 'ChartController@print_action']);
Route::get('print_chart_action/{hippa_id}/{type}', ['as' => 'print_chart_action', 'uses' => 'ChartController@print_chart_action']);
Route::get('orders_list/{type}', ['as' => 'orders_list', 'uses' => 'ChartController@orders_list']);
Route::get('records_list/{type}', ['as' => 'records_list', 'uses' => 'ChartController@records_list']);
Route::get('register_patient', ['as' => 'register_patient', 'uses' => 'ChartController@register_patient']);
Route::post('remove_smart_on_fhir', ['as' => 'remove_smart_on_fhir', 'uses' => 'AjaxChartController@remove_smart_on_fhir']);
Route::get('results_chart/{id}', ['as' => 'results_chart', 'uses' => 'ChartController@results_chart']);
Route::get('results_list/{type}', ['as' => 'results_list', 'uses' => 'ChartController@results_list']);
Route::get('results_print/{id}', ['as' => 'results_print', 'uses' => 'ChartController@results_print']);
Route::any('results_reply', ['as' => 'results_reply', 'uses' => 'ChartController@results_reply']);
Route::get('results_view/{id}', ['as' => 'results_view', 'uses' => 'ChartController@results_view']);
Route::post('set_ccda_data', ['as' => 'set_ccda_data', 'uses' => 'AjaxChartController@set_ccda_data']);
Route::any('search_chart', ['as' => 'search_chart', 'uses' => 'ChartController@search_chart']);
Route::post('set_chart_queue', ['as' => 'set_chart_queue', 'uses' => 'AjaxChartController@set_chart_queue']);
Route::get('social_history', ['as' => 'social_history', 'uses' => 'ChartController@social_history']);
Route::get('supplements_list/{type}', ['as' => 'supplements_list', 'uses' => 'ChartController@supplements_list']);
Route::get('t_messages_list', ['as' => 't_messages_list', 'uses' => 'ChartController@t_messages_list']);
Route::any('t_message_view/{t_messages_id}', ['as' => 't_message_view', 'uses' => 'ChartController@t_message_view']);
Route::post('t_messaging_session', ['as' => 't_messaging_session', 'uses' => 'AjaxChartController@t_messaging_session']);
Route::post('test_reminder', ['as' => 'test_reminder', 'uses' => 'AjaxChartController@test_reminder']);
Route::get('treedata', ['as' => 'treedata', 'uses' => 'ChartController@treedata']);
Route::any('uma_invite', ['as' => 'uma_invite', 'uses' => 'ChartController@uma_invite']);
Route::any('uma_register', ['as' => 'uma_register', 'uses' => 'CoreController@uma_register']);
Route::any('uma_register_auth', ['as' => 'uma_register_auth', 'uses' => 'CoreController@uma_register_auth']);
Route::any('upload_ccda', ['as' => 'upload_ccda', 'uses' => 'ChartController@upload_ccda']);
Route::get('upload_ccda_view/{id}/{type}', ['as' => 'upload_ccda_view', 'uses' => 'ChartController@upload_ccda_view']);
Route::any('upload_ccr', ['as' => 'upload_ccr', 'uses' => 'ChartController@upload_ccr']);

// Schedule routes
Route::get('schedule/{provider_id?}', ['as' => 'schedule', 'uses' => 'CoreController@schedule']);
Route::post('delete_event', ['as' => 'delete_event', 'uses' => 'AjaxScheduleController@delete_event']);
Route::post('drag_event', ['as' => 'drag_event', 'uses' => 'AjaxScheduleController@drag_event']);
Route::post('edit_event', ['as' => 'edit_event', 'uses' => 'AjaxScheduleController@edit_event']);
Route::get('event_encounter/{appt_id}', ['as' => 'event_encounter', 'uses' => 'CoreController@event_encounter']);
Route::get('provider_schedule', ['as' => 'provider_schedule', 'uses' => 'AjaxScheduleController@provider_schedule']);

// Search routes
Route::post('copy_address', ['as' => 'copy_address', 'uses' => 'AjaxSearchController@copy_address']);
Route::any('rx_json', ['as' => 'rx_json', 'uses' => 'AjaxSearchController@rx_json']);
Route::any('rxnorm', ['as' => 'rxnorm', 'uses' => 'AjaxSearchController@rxnorm']);
Route::post('search_address', ['as' => 'search_address', 'uses' => 'AjaxSearchController@search_address']);
Route::post('search_cpt', ['as' => 'search_cpt', 'uses' => 'AjaxSearchController@search_cpt']);
Route::post('search_encounters', ['as' => 'search_encounters', 'uses' => 'AjaxSearchController@search_encounters']);
Route::post('search_guardian', ['as' => 'search_guardian', 'uses' => 'AjaxSearchController@search_guardian']);
Route::post('search_healthwise', ['as' => 'search_healthwise', 'uses' => 'AjaxSearchController@search_healthwise']);
Route::post('search_icd/{assessment?}', ['as' => 'search_icd', 'uses' => 'AjaxSearchController@search_icd']);
Route::post('search_icd_specific', ['as' => 'search_icd_specific', 'uses' => 'AjaxSearchController@search_icd_specific']);
Route::post('search_imaging', ['as' => 'search_imaging', 'uses' => 'AjaxSearchController@search_imaging']);
Route::post('search_immunization', ['as' => 'search_immunization', 'uses' => 'AjaxSearchController@search_immunization']);
Route::post('search_immunization_inventory', ['as' => 'search_immunization_inventory', 'uses' => 'AjaxSearchController@search_immunization_inventory']);
Route::post('search_insurance', ['as' => 'search_insurance', 'uses' => 'AjaxSearchController@search_insurance']);
Route::post('search_interactions', ['as' => 'search_interactions', 'uses' => 'AjaxSearchController@search_interactions']);
Route::post('search_language', ['as' => 'search_language', 'uses' => 'AjaxSearchController@search_language']);
Route::post('search_loinc', ['as' => 'search_loinc', 'uses' => 'AjaxSearchController@search_loinc']);
Route::post('search_ndc', ['as' => 'search_ndc', 'uses' => 'AjaxSearchController@search_ndc']);
Route::post('search_patient', ['as' => 'search_patient', 'uses' => 'AjaxSearchController@search_patient']);
Route::post('search_patient_history', ['as' => 'search_patient_history', 'uses' => 'AjaxSearchController@search_patient_history']);
Route::post('search_referral_provider', ['as' => 'search_referral_provider', 'uses' => 'AjaxSearchController@search_referral_provider']);
Route::post('search_rx', ['as' => 'search_rx', 'uses' => 'AjaxSearchController@search_rx']);
Route::post('search_specialty', ['as' => 'search_specialty', 'uses' => 'AjaxSearchController@search_specialty']);
Route::post('search_supplement/{order}', ['as' => 'search_supplement', 'uses' => 'AjaxSearchController@search_supplement']);
Route::post('template_get', ['as' => 'template_get', 'uses' => 'AjaxSearchController@template_get']);
Route::post('template_edit', ['as' => 'template_edit', 'uses' => 'AjaxSearchController@template_edit']);
Route::post('template_normal', ['as' => 'template_normal', 'uses' => 'AjaxSearchController@template_normal']);
Route::post('template_normal_change', ['as' => 'template_normal_change', 'uses' => 'AjaxSearchController@template_normal_change']);
Route::post('template_remove', ['as' => 'template_remove', 'uses' => 'AjaxSearchController@template_remove']);
Route::any('template_restore/{action?}', ['as' => 'template_restore', 'uses' => 'AjaxSearchController@template_restore']);
Route::post('tags', ['as' => 'tags', 'uses' => 'AjaxSearchController@tags']);
Route::post('tag_save/{type}/{id}', ['as' => 'tag_save', 'uses' => 'AjaxSearchController@tag_save']);
Route::post('tag_remove/{type}/{id}', ['as' => 'tag_remove', 'uses' => 'AjaxSearchController@tag_remove']);
Route::post('typeahead/{table}/{column}/{subtype?}', ['as' => 'typeahead', 'uses' => 'AjaxSearchController@typeahead']);
Route::post('tagsinput_icd', ['as' => 'tagsinput_icd', 'uses' => 'AjaxSearchController@tagsinput_icd']);
Route::post('tagsinput_icd_all', ['as' => 'tagsinput_icd_all', 'uses' => 'AjaxSearchController@tagsinput_icd_all']);

// Fax routes
Route::get('fax', ['as' => 'fax', 'uses' => 'FaxController@fax']);
Route::post('phaxio/{practice_id}', ['as' => 'phaxio', 'uses' => 'FaxController@phaxio']);

// Reminders
Route::get('reminder', ['as' => 'reminder', 'uses' => 'ReminderController@reminder']);

// FHIR Endpoints
// Route::group(['prefix' => 'fhir'], function () {
Route::group(['prefix' => 'fhir', 'middleware' => 'fhir'], function () {
    Route::resource('AdverseReaction', 'AdverseReactionController');
    Route::resource('Alert', 'AlertController');
    Route::resource('AllergyIntolerance', 'AllergyIntoleranceController'); //in use - allergies
    Route::resource('Appointment', 'AppointmentController'); //in use - appointments
    Route::resource('Binary', 'BinaryController'); //in use - documents
    Route::resource('CarePlan', 'CarePlanController');
    Route::resource('CareTeam', 'CareTeamController');
    Route::resource('Composition', 'CompositionController');
    Route::resource('ConceptMap', 'ConceptMapController');
    Route::resource('Condition', 'ConditionController'); //in use - issues, assessments
    Route::resource('Conformance', 'ConformanceController');
    Route::resource('Device', 'DeviceController');
    Route::resource('DeviceObservationReport', 'DeviceObservationReportController');
    Route::resource('DiagnosticOrder', 'DiagnosticOrderController');
    Route::resource('DiagnosticReport', 'DiagnosticReportController');
    Route::resource('DocumentReference', 'DocumentReferenceController');
    Route::resource('DocumentManifest', 'DocumentManifestController');
    Route::resource('Encounter', 'EncounterController'); //in use - encounters
    Route::resource('FamilyHistory', 'FamilyHistoryController'); //in use
    Route::resource('Group', 'GroupController');
    Route::resource('ImagingStudy', 'ImagingStudyController');
    Route::resource('Immunization', 'ImmunizationController'); //in use - immunizations
    Route::resource('ImmunizationRecommendation', 'ImmunizationRecommendationController');
    Route::resource('List', 'ListController');
    Route::resource('Location', 'LocationController');
    Route::resource('Media', 'MediaController');
    Route::resource('Medication', 'MedicationController'); //in use - rxnorm
    Route::resource('MedicationAdministration', 'MedicationAdministrationController');
    Route::resource('MedicationDispense', 'MedicationDispenseController');
    Route::resource('MedicationRequest', 'MedicationOrderController');
    Route::resource('MedicationStatement', 'MedicationStatementController'); //in use - medication list
    Route::resource('MessageHeader', 'MessageHeaderController');
    Route::resource('Observation', 'ObservationController'); //in use - vitals and test results
    Route::resource('OperationOutcome', 'OperationOutcomeController');
    Route::resource('Order', 'OrderController'); //in use
    Route::resource('OrderResponse', 'OrderResponseController');
    Route::resource('Organization', 'OrganizationController');
    Route::resource('Other', 'OtherController');
    Route::resource('Patient', 'PatientController'); //in use
    Route::resource('Practitioner', 'PractitionerController'); //in use
    Route::resource('Procedure', 'ProcedureController');
    Route::resource('Profile', 'ProfileController');
    Route::resource('Provenance', 'ProvenanceController');
    Route::resource('Query', 'QueryController');
    Route::resource('Questionnaire', 'QuestionnaireController');
    Route::resource('RelatedPerson', 'RelatedPersonController');
    Route::resource('Schedule', 'ScheduleController'); //in use - available appointments
    Route::resource('SecurityEvent', 'SecurityEventController');
    Route::resource('Specimen', 'SpecimenController');
    Route::resource('Substance', 'SubstanceController');
    Route::resource('Supply', 'SupplyController');
    Route::resource('ValueSet', 'ValueSetController');
});

// API routes
Route::get('api_check/{practicehandle}', ['as' => 'api_check', 'uses' => 'LoginController@api_check']);
Route::any('api_login', ['as' => 'api_login', 'uses' => 'LoginController@api_login']);
Route::any('api_logout', ['as' => 'api_logout', 'uses' => 'LoginController@api_logout']);
Route::any('api_patient', ['as' => 'api_patient', 'uses' => 'ChartController@api_patient']);
Route::any('api_practice', ['as' => 'api_practice', 'uses' => 'CoreController@api_practice']);
Route::any('api_register', ['as' => 'api_register', 'uses' => 'LoginController@api_register']);
Route::group(['prefix' => 'api/v1', 'middleware' => 'auth'], function () {
    Route::post('add', ['as' => 'add', 'uses' => 'APIv1Controller@add']);
    Route::post('update', ['as' => 'update', 'uses' => 'APIv1Controller@update']);
    Route::post('delete', ['as' => 'delete', 'uses' => 'APIv1Controller@delete']);
});

Route::any('practiceregister/{api}', ['as' => 'practiceregister', 'uses' => 'APIv1Controller@practiceregister']);
Route::any('practiceregisternosh/{api}', ['as' => 'practiceregisternosh', 'uses' => 'APIv1Controller@practiceregisternosh']);
Route::any('providerregister/{api}', ['as' => 'providerregister', 'uses' => 'APIv1Controller@providerregister']);

// test
Route::any('test1', array('as' => 'test1', 'uses' => 'InstallController@test1'));
