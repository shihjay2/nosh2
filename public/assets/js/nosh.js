// Options
toastr.options = {
	"closeButton": true,
	"debug": false,
	"newestOnTop": true,
	"progressBar": true,
	"positionClass": "toast-bottom-full-width",
	"preventDuplicates": false,
	"showDuration": "300",
	"hideDuration": "1000",
	"timeOut": "5000",
	"extendedTimeOut": "1000",
	"showEasing": "swing",
	"hideEasing": "linear",
	"showMethod": "fadeIn",
	"hideMethod": "fadeOut"
};

// Functions


function split( val ) {
	return val.split( /\n\s*/ );
}

function extractLast( term ) {
	return split( term ).pop();
}

function search_array(a, query_value) {
	var query_value1 = query_value.replace('?','\\?');
	var query_value2 = query_value1.replace('(','\\(');
	var query_value3 = query_value2.replace(')','\\)');
	var query_value4 = query_value3.replace('+','\\+');
	var query_value5 = query_value4.replace('/','\\/');
	var found = $.map(a, function (value) {
		var re = RegExp(query_value5, "g");
		if(value.match(re)) {
			return value;
		} else {
			return null;
		}
	});
	return found;
}

function progressbartrack() {
	if (parseInt(noshdata.progress) < 100) {
		if (noshdata.progress === 0) {
			$("#dialog_progressbar").progressbar({value:0});
		}
		$.ajax({
			type: "POST",
			url: "ajaxdashboard/progressbar-track",
			success: function(data){
				$("#dialog_progressbar").progressbar("value", parseInt(data));
				if (parseInt(data) < 100) {
					setTimeout(progressbartrack(),1000);
					noshdata.progress = data;
				} else {
					$.ajax({
						type: "POST",
						url: "ajaxdashboard/delete-progress",
						success: function(data){
							$("#dialog_progressbar").progressbar('destroy');
							$("#dialog_load").dialog('close');
							noshdata.progress = 0;
						}
					});
				}
			}
		});
	}
}



function open_demographics() {
	$.ajax({
		type: "POST",
		url: "ajaxdashboard/demographics",
		dataType: "json",
		success: function(data){
			$.each(data, function(key, value){
				if (key == 'DOB') {
					value = editDate1(data.DOB);
				}
				$("#edit_demographics_form :input[name='" + key + "']").val(value);
			});
			if (noshdata.group_id != '100') {
				$.ajax({
					type: "POST",
					url: "ajaxdashboard/check-registration-code",
					success: function(data){
						if (data == 'n') {
							$("#register_menu_demographics").show();
						} else {
							$("#register_menu_demographics").hide();
							$("#menu_registration_code").html(data);
						}
					}
				});
			}
			$("#menu_lastname").focus();
			$("#demographics_list_dialog").dialog('open');
		}
	});
}

function addMinutes(date, minutes) {
	var d = new Date(date);
	return d.getTime() + minutes*60000;
}

function open_messaging(type) {
	$.mobile.loading("show");
	$ul = $("#"+type);
	var command = type.replace('_', '-');
	$.ajax({
		type: "POST",
		url: "ajaxmessaging/" + command,
		data: "sidx=date&sord=desc&rows=1000000&page=1",
		dataType: 'json'
	}).then(function(response) {
		if (type == 'internal_inbox') {
			var col = ['message-id','message-to','read','date','message-from','message-from-label','subject','body','cc','pid','patient_name','bodytext','t-messages-id','documents-id'];
		}
		if (type == 'internal_draft') {
			var col = ['message-id','date','message-to','cc','subject','body','pid','patient_name'];
		}
		if (type == 'internal_outbox') {
			var col = ['message-id','date','message-to','cc','subject','pid','body'];
		}
		var html = '';
		if (response.rows != '') {
			$.each(response.rows, function ( i, item ) {
				var obj = {};
				$.each(item.cell, function ( j, val ) {
					obj[col[j]] = val;
				});
				if (type == 'internal_inbox') {
					var label = '<h3>' + obj['message-from-label'] + '</h3><p>' + obj['subject'] + '</p>';
				} else {
					var label = '<h3>' + obj['message-to'] + '</h3><p>' + obj['subject'] + '</p>';
				}
				var datastring = '';
				$.each(obj, function ( key, value ) {
					datastring += 'data-nosh-' + key + '="' + value + '" ';
				});
				html += '<li><a href="#" class="nosh_messaging_item" ' + datastring + ' data-origin="' + type + '">' + label + '</a></li>';
			});
		}
		$ul.html(html);
		$ul.listview("refresh");
		$ul.trigger("updatelayout");
		$.mobile.loading("hide");
	});
}

function chart_notification() {
	if (noshdata.group_id == '2') {
		$.ajax({
			type: "POST",
			url: "ajaxchart/notification",
			dataType: "json",
			success: function(data){
				if (data.appt != noshdata.notification_appt && data.appt != '') {
					$.jGrowl(data.appt, {sticky:true, header:data.appt_header});
					noshdata.notification_appt = data.appt;
				}
				if (data.alert != noshdata.notification_alert && data.alert != '') {
					$.jGrowl(data.alert, {sticky:true, header:data.alert_header});
					noshdata.notification_alert = data.alert;
				}
			}
		});
	}
}

function openencounter() {
	$("#encounter_body").html('');
	$("#encounter_body").empty();
	if ($(".ros_dialog").hasClass('ui-dialog-content')) {
		$(".ros_dialog").dialog('destroy');
	}
	if ($(".pe_dialog").hasClass('ui-dialog-content')) {
		$(".pe_dialog").dialog('destroy');
	}
	$("#encounter_body").load('ajaxencounter/loadtemplate');
	$('#dialog_load').dialog('option', 'title', "Loading encounter...").dialog('open');
	$("#encounter_link_span").html('<a href="#" id="encounter_panel">[Active Encounter #: ' + noshdata.eid + ']</a>');
	$.ajax({
		type: "POST",
		url: "ajaxsearch/get-tags/eid/" + noshdata.eid,
		dataType: "json",
		success: function(data){
			$("#encounter_tags").tagit("fill",data);
		}
	});
}

function closeencounter() {
	var $hpi = $('#hpi_form');
	console.log($hpi.length);
	if($hpi.length) {
		hpi_autosave('hpi');
	}
	var $situation = $('#situation_form');
	if($situation.length) {
		hpi_autosave('situation');
	}
	var $oh = $('#oh_form');
	if($oh.length) {
		oh_autosave();
	}
	var $vitals = $('#vitals_form');
	if($vitals.length) {
		vitals_autosave();
	}
	var $proc = $('#procedure_form');
	if($proc.length) {
		proc_autosave();
	}
	var $assessment = $('#assessment_form');
	if($assessment.length) {
		assessment_autosave();
	}
	var $orders = $('#orders_form');
	if($orders.length) {
		orders_autosave();
	}
	var $medications = $('#mtm_medications_form');
	if($medications.length) {
		medications_autosave();
	}
	$.ajax({
		type: "POST",
		url: "ajaxchart/closeencounter",
		success: function(data){
			noshdata.encounter_active = 'n';
			$("#nosh_encounter_div").hide();
			$("#nosh_chart_div").show();
			$("#encounter_link_span").html('');
		}
	});
}

function signedlabel (cellvalue, options, rowObject){
	if (cellvalue == 'No') {
		return 'Draft';
	}
	if (cellvalue == 'Yes') {
		return 'Signed';
	}
}




function menu_update(type) {
	$.ajax({
		type: "POST",
		url: "ajaxchart/" + type + "-list",
		success: function(data){
			$("#menu_accordion_" + type + "-list_content").html(data);
			$("#menu_accordion_" + type + "-list_load").hide();
		}
	});
}

function remove_text(parent_id_entry, a, label_text, ret) {
	var old = $("#" + parent_id_entry).val();
	var old_arr = old.split('  ');
	if (label_text != '') {
		var new_arr = search_array(old_arr, label_text);
	} else {
		var new_arr = [];
	}
	if (new_arr.length > 0) {
		var arr_index = old_arr.indexOf(new_arr[0]);
		a = a.replace(label_text, '');
		old_arr[arr_index] = old_arr[arr_index].replace(label_text, '');
		var old_arr1 = old_arr[arr_index].split('; ')
		var new_arr1 = search_array(old_arr1, a);
		if (new_arr1.length > 0) {
			var arr_index1 = old_arr1.indexOf(new_arr1[0]);
			old_arr1.splice(arr_index1,1);
			if (old_arr1.length > 0) {
				old_arr[arr_index] = label_text + old_arr1.join('; ');
			} else {
				old_arr.splice(arr_index,1);
			}
		}
	} else {
		var new_arr2 = search_array(old_arr, a);
		if (new_arr2.length > 0) {
			var arr_index2 = old_arr.indexOf(new_arr2[0]);
			old_arr.splice(arr_index2,1);
		}
	}
	var b = old_arr.join("  ");
	if (ret == true) {
		return b;
	} else {
		$("#" + parent_id_entry).val(b);
	}
}

function repeat_text(parent_id_entry, a, label_text) {
	var ret = false;
	var old = $("#" + parent_id_entry).val();
	var old_arr = old.split('  ');
	if (label_text != '') {
		var new_arr = search_array(old_arr, label_text);
	} else {
		var new_arr = [];
	}
	if (new_arr.length > 0) {
		var arr_index = old_arr.indexOf(new_arr[0]);
		a = a.replace(label_text, '');
		old_arr[arr_index] = old_arr[arr_index].replace(label_text, '');
		var old_arr1 = old_arr[arr_index].split('; ')
		var new_arr1 = search_array(old_arr1, a);
		if (new_arr1.length > 0) {
			ret = true;
		}
	} else {
		var new_arr2 = search_array(old_arr, a);
		if (new_arr2.length > 0) {
			ret = true;
		}
	}
	return ret;
}

function checkorders() {
	$.ajax({
		type: "POST",
		url: "ajaxencounter/check-orders",
		dataType: "json",
		success: function(data){
			$('#button_orders_labs_status').html(data.labs_status);
			$('#button_orders_rad_status').html(data.rad_status);
			$('#button_orders_cp_status').html(data.cp_status);
			$('#button_orders_ref_status').html(data.ref_status);
			$('#button_orders_rx_status').html(data.rx_status);
			$('#button_orders_imm_status').html(data.imm_status);
			$('#button_orders_sup_status').html(data.sup_status);
		}
	});
}

function check_oh_status() {
	$.ajax({
		type: "POST",
		url: "ajaxencounter/check-oh",
		dataType: "json",
		success: function(data){
			$('#button_oh_sh_status').html(data.sh_status);
			$('#button_oh_etoh_status').html(data.etoh_status);
			$('#button_oh_tobacco_status').html(data.tobacco_status);
			$('#button_oh_drugs_status').html(data.drugs_status);
			$('#button_oh_employment_status').html(data.employment_status);
			$('#button_oh_meds_status').html(data.meds_status);
			$('#button_oh_supplements_status').html(data.supplements_status);
			$('#button_oh_allergies_status').html(data.allergies_status);
			$('#button_oh_psychosocial_status').html(data.psychosocial_status);
			$('#button_oh_developmental_status').html(data.developmental_status);
			$('#button_oh_medtrials_status').html(data.medtrials_status);
		}
	});
}

function check_ros_status() {
	$.ajax({
		type: "POST",
		url: "ajaxencounter/check-ros",
		dataType: "json",
		success: function(data){
			$('#button_ros_gen_status').html(data.gen);
			$('#button_ros_eye_status').html(data.eye);
			$('#button_ros_ent_status').html(data.ent);
			$('#button_ros_resp_status').html(data.resp);
			$('#button_ros_cv_status').html(data.cv);
			$('#button_ros_gi_status').html(data.gi);
			$('#button_ros_gu_status').html(data.gu);
			$('#button_ros_mus_status').html(data.mus);
			$('#button_ros_neuro_status').html(data.neuro);
			$('#button_ros_psych_status').html(data.psych);
			$('#button_ros_heme_status').html(data.heme);
			$('#button_ros_endocrine_status').html(data.endocrine);
			$('#button_ros_skin_status').html(data.skin);
			$('#button_ros_wcc_status').html(data.wcc);
			$('#button_ros_psych1_status').html(data.psych1);
			$('#button_ros_psych2_status').html(data.psych2);
			$('#button_ros_psych3_status').html(data.psych3);
			$('#button_ros_psych4_status').html(data.psych4);
			$('#button_ros_psych5_status').html(data.psych5);
			$('#button_ros_psych6_status').html(data.psych6);
			$('#button_ros_psych7_status').html(data.psych7);
			$('#button_ros_psych8_status').html(data.psych8);
			$('#button_ros_psych9_status').html(data.psych9);
			$('#button_ros_psych10_status').html(data.psych10);
			$('#button_ros_psych11_status').html(data.psych11);
		}
	});
}

function check_pe_status() {
	$.ajax({
		type: "POST",
		url: "ajaxencounter/check-pe",
		dataType: "json",
		success: function(data){
			$('#button_pe_gen_status').html(data.gen);
			$('#button_pe_eye_status').html(data.eye);
			$('#button_pe_ent_status').html(data.ent);
			$('#button_pe_neck_status').html(data.neck);
			$('#button_pe_resp_status').html(data.resp);
			$('#button_pe_cv_status').html(data.cv);
			$('#button_pe_ch_status').html(data.ch);
			$('#button_pe_gi_status').html(data.gi);
			$('#button_pe_gu_status').html(data.gu);
			$('#button_pe_lymph_status').html(data.lymph);
			$('#button_pe_ms_status').html(data.ms);
			$('#button_pe_neuro_status').html(data.neuro);
			$('#button_pe_psych_status').html(data.psych);
			$('#button_pe_skin_status').html(data.skin);
			$('#button_pe_constitutional_status').html(data.constitutional);
			$('#button_pe_mental_status').html(data.mental);
		}
	});
}

function check_labs1() {
	$.ajax({
		type: "POST",
		url: "ajaxencounter/check-labs",
		dataType: "json",
		success: function(data){
			$('#button_labs_ua_status').html(data.ua);
			$('#button_labs_rapid_status').html(data.rapid);
			$('#button_labs_micro_status').html(data.micro);
			$('#button_labs_other_status').html(data.other);
		}
	});
}

function total_balance() {
	if (noshdata.pid != '') {
		$.ajax({
			type: "POST",
			url: "ajaxchart/total-balance",
			success: function(data){
				$('#total_balance').html(data);
			}
		});
	}
}

function proc_autosave() {
	var bValid = false;
	$("#procedure_form").find(".text").each(function() {
		if (bValid == false) {
			var input_id = $(this).attr('id');
			var a = $("#" + input_id).val();
			var b = $("#" + input_id + "_old").val();
			if (a != b) {
				bValid = true;
			}
		}
	});
	if (bValid) {
		var proc_str = $("#procedure_form").serialize();
		if(proc_str){
			$.ajax({
				type: "POST",
				url: "ajaxencounter/proc-save",
				data: proc_str,
				success: function(data){
					$.jGrowl(data);
					$("#procedure_form").find(".text").each(function() {
						var input_id = $(this).attr('id');
						var a = $("#" + input_id).val();
						$("#" + input_id + "_old").val(a);
					});
				}
			});
		} else {
			$.jGrowl("Please complete the form");
		}
	}
}

function assessment_autosave() {
	var bValid = false;
	$("#assessment_form").find(".text").each(function() {
		if (bValid == false) {
			var input_id = $(this).attr('id');
			var a = $("#" + input_id).val();
			var b = $("#" + input_id + "_old").val();
			if (a != b) {
				bValid = true;
			}
		}
	});
	if (bValid) {
		var assessment_str = $("#assessment_form").serialize();
		if(assessment_str){
			$.ajax({
				type: "POST",
				url: "ajaxencounter/assessment-save",
				data: assessment_str,
				success: function(data){
					$.jGrowl(data);
					$("#assessment_form").find(".text").each(function() {
						var input_id = $(this).attr('id');
						var a = $("#" + input_id).val();
						$("#" + input_id + "_old").val(a);
					});
					$.ajax({
						type: "POST",
						url: "ajaxencounter/get-billing",
						dataType: "json",
						success: function(data){
							$("#billing_icd").removeOption(/./);
							$("#billing_icd").addOption(data, false);
						}
					});
				}
			});
		} else {
			$.jGrowl("Please complete the form");
		}
	}
}

function orders_autosave() {
	var bValid = false;
	$("#orders_form").find(".text").each(function() {
		if (bValid == false) {
			var input_id = $(this).attr('id');
			var a = $("#" + input_id).val();
			var b = $("#" + input_id + "_old").val();
			if (a != b) {
				bValid = true;
			}
		}
	});
	if (bValid) {
		var orders_str = $("#orders_form").serialize();
		if(orders_str){
			$.ajax({
				type: "POST",
				url: "ajaxencounter/orders-save",
				data: orders_str,
				success: function(data){
					$.jGrowl(data);
					$("#orders_form").find(".text").each(function() {
						var input_id = $(this).attr('id');
						var a = $("#" + input_id).val();
						$("#" + input_id + "_old").val(a);
					});
				}
			});
		} else {
			$.jGrowl("Please complete the form");
		}
	}
}

function medications_autosave() {
	$.ajax({
		type: "POST",
		url: "ajaxencounter/oh-save1/meds",
		success: function(data){
			$.jGrowl(data);
		}
	});
}

function results_autosave() {
	var bValid = false;
	$("#oh_results_form").find(".text").each(function() {
		if (bValid == false) {
			var input_id = $(this).attr('id');
			var a = $("#" + input_id).val();
			var b = $("#" + input_id + "_old").val();
			if (a != b) {
				bValid = true;
			}
		}
	});
	if (bValid) {
		var oh_str = $("#oh_results_form").serialize();
		if(oh_str){
			$.ajax({
				type: "POST",
				url: "ajaxencounter/oh-save1/results",
				data: oh_str,
				success: function(data){
					$.jGrowl(data);
					$("#oh_results_form").find(".text").each(function() {
						var input_id = $(this).attr('id');
						var a = $("#" + input_id).val();
						$("#" + input_id + "_old").val(a);
					});
				}
			});
		} else {
			$.jGrowl("Please complete the form");
		}
	}
}

function billing_autosave() {
	var bValid = false;
	$("#encounter_billing_form").find(".text").each(function() {
		if (bValid == false) {
			var input_id = $(this).attr('id');
			var a = $("#" + input_id).val();
			var b = $("#" + input_id + "_old").val();
			if (a != b) {
				bValid = true;
			}
		}
	});
	if (bValid) {
		var billing_str = $("#encounter_billing_form").serialize();
		if(billing_str){
			$.ajax({
				type: "POST",
				url: "ajaxencounter/billing-save1",
				data: billing_str,
				success: function(data){
					$.jGrowl(data);
					$("#encounter_billing_form").find(".text").each(function() {
						var input_id = $(this).attr('id');
						var a = $("#" + input_id).val();
						$("#" + input_id + "_old").val(a);
					});
				}
			});
		} else {
			$.jGrowl("Please complete the form");
		}
	}
}

function pending_order_load(item) {
	$.ajax({
		url: "ajaxchart/order-type/" + item,
		dataType: "json",
		type: "POST",
		success: function(data){
			var label = data.label;
			var status = "";
			var type = "";
			if (label == 'messages_lab') {
				status = 'Details for Lab Order #' + item;
				type = 'lab';
			}
			if (label == 'messages_rad') {
				status = 'Details for Radiology Order #' + item;
				type = 'rad';
			}
			if (label == 'messages_cp') {
				status = 'Details for Cardiopulmonary Order #' + item;
				type = 'cp';
			}
			load_outside_providers(type,'edit');
			$.each(data, function(key, value){
				if (key != 'label') {
					if (key == 'orders_pending_date') {
						var value = getCurrentDate();
					}
					$("#edit_"+label+"_form :input[name='" + key + "']").val(value);
				}
			});
			$("#"+label+"_status").html(status);
			if ($("#"+label+"_provider_list").val() == '' && noshdata.group_id == '2') {
				$("#"+label+"_provider_list").val(noshdata.user_id);
			}
			$("#"+label+"_edit_fields").dialog("option", "title", "Edit Lab Order");
			$("#"+label+"_edit_fields").dialog('open');
		}
	});
}

function load_outside_providers(type,action) {
	$("#messages_"+type+"_location").removeOption(/./);
	var type1 = '';
	var type2 = '';
	if (type == 'lab') {
		type1 = 'Laboratory';
		type2 = 'lab';
	}
	if (type == 'rad') {
		type1 = 'Radiology';
		type2 = 'imaging';
	}
	if (type == 'cp') {
		type1 = 'Cardiopulmonary';
		type2 = 'cardiopulmonary';
	}
	$.ajax({
		url: "ajaxsearch/orders-provider/" + type1,
		dataType: "json",
		type: "POST",
		async: false,
		success: function(data){
			if(data.response == 'true'){
				$("#messages_"+type+"_location").addOption({"":"Add "+type2+" provider."}, false);
				$("#messages_"+type+"_location").addOption(data.message, false);
			} else {
				$("#messages_"+type+"_location").addOption({"":"No "+type2+" provider.  Click Add."}, false);
			}
		}
	});
	$("#messages_"+type+"_provider_list").removeOption(/./);
	$.ajax({
		url: "ajaxsearch/provider-select",
		dataType: "json",
		type: "POST",
		async: false,
		success: function(data){
			$("#messages_"+type+"_provider_list").addOption({"":"Select a provider for the order."}, false);
			$("#messages_"+type+"_provider_list").addOption(data, false);
			if(action == 'add') {
				if (noshdata.group_id == '2') {
					$("#messages_"+type+"_provider_list").val(noshdata.user_id);
				} else {
					$("#messages_"+type+"_provider_list").val('');
				}
			}
		}
	});
}

function parse_date(string) {
	var date = new Date();
	var parts = String(string).split(/[- :]/);
	date.setFullYear(parts[0]);
	date.setMonth(parts[1] - 1);
	date.setDate(parts[2]);
	date.setHours(parts[3]);
	date.setMinutes(parts[4]);
	date.setSeconds(parts[5]);
	date.setMilliseconds(0);
	return date;
}

function parse_date1(string) {
	var date = new Date();
	var parts = String(string).split("/");
	date.setFullYear(parts[2]);
	date.setMonth(parts[0] - 1);
	date.setDate(parts[1]);
	date.setHours(0);
	date.setMinutes(0);
	date.setSeconds(0);
	date.setMilliseconds(0);
	return date;
}

function editDate(string) {
	var result = string.split("-");
	var edit_date = result[1] + '/' + result[2] + '/' + result[0];
	return edit_date;
}

function editDate1(string) {
	var result1 = string.split(" ");
	var result = result1[0].split("-");
	var edit_date = result[1] + '/' + result[2] + '/' + result[0];
	if (edit_date == '00/00/0000') {
		var edit_date1 = '';
	} else {
		var edit_date1 = edit_date;
	}
	return edit_date1;
}

function editDate2(string) {
	var result1 = string.split(" ");
	var result = result1[1].split(":");
	var hour1 = result[0];
	var hour2 = parseInt(hour1);
	if (hour2 > 12) {
		var hour3 = hour2 - 12;
		var hour4 = hour3 + '';
		var pm = 'PM';
		if (hour4.length == 1) {
			var hour = "0" + hour4;
		} else {
			var hour = hour4;
		}
	} else {
		if (hour2 == 0) {
			var hour = '12';
			var pm = 'AM';
		}
		if (hour2 == 12) {
			var hour = hour2;
			var pm = 'PM';
		}
		if (hour2 < 12) {
			var pm = 'AM';
			if (hour2.length == 1) {
				var hour = "0" + hour2;
			} else {
				var hour = hour2;
			}
		}
	}
	var minute1 = result[1];
	var minute2 = minute1 + '';
	if (minute2.length == 1) {
		var minute = "0" + minute2;
	} else {
		var minute = minute2;
	}
	var time = hour + ":" + minute + ' ' + pm;
	return time;
}

function getCurrentDate() {
	var d = new Date();
	var day1 = d.getDate();
	var day2 = day1 + '';
	if (day2.length == 1) {
		var day = "0" + day2;
	} else {
		var day = day2;
	}
	var month1 = d.getMonth();
	var month2 = parseInt(month1);
	var month3 = month2 + 1;
	var month4 = month3 + '';
	if (month4.length == 1) {
		var month = "0" + month4;
	} else {
		var month = month4;
	}
	var date = month + "/" + day + "/" + d.getFullYear();
	return date;
}

function getCurrentTime() {
	var d = new Date();
	var hour1 = d.getHours();
	var hour2 = parseInt(hour1);
	if (hour2 > 12) {
		var hour3 = hour2 - 12;
		var hour4 = hour3 + '';
		var pm = 'PM';
		if (hour4.length == 1) {
			var hour = "0" + hour4;
		} else {
			var hour = hour4;
		}
	} else {
		if (hour2 == 0) {
			var hour = '12';
			var pm = 'AM';
		}
		if (hour2 == 12) {
			var hour = hour2;
			var pm = 'PM';
		}
		if (hour2 < 12) {
			var pm = 'AM';
			if (hour2.length == 1) {
				var hour = "0" + hour2;
			} else {
				var hour = hour2;
			}
		}
	}
	var minute1 = d.getMinutes();
	var minute2 = minute1 + '';
	if (minute2.length == 1) {
		var minute = "0" + minute2;
	} else {
		var minute = minute2;
	}
	var time = hour + ":" + minute + ' ' + pm;
	return time;
}

function typelabel (cellvalue, options, rowObject){
	if (cellvalue == 'standardmedical') {
		return 'Standard Medical Visit V1';
	}
	if (cellvalue == 'standardmedical1') {
		return 'Standard Medical Visit V2';
	}
	if (cellvalue == 'clinicalsupport') {
		return 'Clinical Support Visit';
	}
	if (cellvalue == 'standardpsych') {
		return 'Annual Psychiatric Evaluation';
	}
	if (cellvalue == 'standardpsych1') {
		return 'Psychiatric Encounter';
	}
	if (cellvalue == 'standardmtm') {
		return 'MTM Encounter';
	}
}

function t_messages_tags() {
	var id = $("#t_messages_id").val();
	$.ajax({
		type: "POST",
		url: "ajaxsearch/get-tags/t_messages_id/" + id,
		dataType: "json",
		success: function(data){
			$(".t_messages_tags").tagit("fill",data);
		}
	});
}

function refresh_timeline() {
	var $timeline_block = $('.cd-timeline-block');
	//hide timeline blocks which are outside the viewport
	$timeline_block.each(function(){
		if($(this).offset().top > $(window).scrollTop()+$(window).height()*0.75) {
			$(this).find('.cd-timeline-img, .cd-timeline-content').hide();
		}
	});
	//on scolling, show/animate timeline blocks when enter the viewport
	$(window).on('scroll', function(){
		$timeline_block.each(function(){
			if( $(this).offset().top <= $(window).scrollTop()+$(window).height()*0.75 && $(this).find('.cd-timeline-img').is(":hidden")) {
				$(this).find('.cd-timeline-img, .cd-timeline-content').show("slide");
			}
		});
	});
}



$.fn.clearDiv = function() {
	return this.each(function() {
		var type = this.type, tag = this.tagName.toLowerCase();
		if (tag == 'div') {
			return $(':input',this).clearForm();
		}
		if (type == 'text' || type == 'password' || type == 'hidden' || tag == 'textarea') {
			this.value = '';
			$(this).removeClass("ui-state-error");
		} else if (type == 'checkbox' || type == 'radio') {
			this.checked = false;
			$(this).removeClass("ui-state-error");
			$(this).checkboxradio('refresh');
		} else if (tag == 'select') {
			this.selectedIndex = 0;
			$(this).removeClass("ui-state-error");
			$(this).selectmenu('refresh');
		}
	});
};

$.fn.serializeJSON = function() {
	var o = {};
	var a = this.serializeArray();
	$.each(a, function() {
		if (o[this.name] !== undefined) {
			if (!o[this.name].push) {
				o[this.name] = [o[this.name]];
			}
			o[this.name].push(this.value || '');
		} else {
			o[this.name] = this.value || '';
		}
	});
	return o;
};

// $.widget( "custom.catcomplete", $.ui.autocomplete, {
// 	_renderMenu: function( ul, items ) {
// 		var that = this,
// 		currentCategory = "";
// 		$.each( items, function( index, item ) {
// 			if ( item.category != currentCategory ) {
// 				ul.append( "<li class='ui-autocomplete-category'>" + item.category + "</li>" );
// 				currentCategory = item.category;
// 			}
// 			that._renderItemData( ul, item );
// 		});
// 	}
// });



// NOSH events
$.ajaxSetup({
	headers: {"cache-control":"no-cache"},
	beforeSend: function(request) {
		return request.setRequestHeader("X-CSRF-Token", $("meta[name='csrf-token']").attr('content'));
	}
});

$.fn.clearForm = function() {
	return this.each(function() {
		var type = this.type, tag = this.tagName.toLowerCase();
		if (tag == 'form') {
			return $(':input',this).clearForm();
		}
		if (this.hasAttribute('nosh-no-clear') === false) {
			if (type == 'text' || type == 'password' || type == 'hidden' || tag == 'textarea') {
				this.value = '';
			} else if (type == 'checkbox' || type == 'radio') {
				this.checked = false;
				// $(this).checkboxradio('refresh');
			} else if (tag == 'select') {
				this.selectedIndex = 0;
				// $(this).selectmenu('refresh');
			}
		}
	});
};

$(document).ajaxError(function(event,xhr,options,exc) {
	if (xhr.status == "404" ) {
		alert("Route not found!");
		//window.location.replace(noshdata.error);
	} else {
		if(xhr.responseText){
			var response1 = $.parseJSON(xhr.responseText);
			var error = "Error:\nType: " + response1.error.type + "\nMessage: " + response1.error.message + "\nFile: " + response1.error.file;
			alert(error);
		}
	}
});

function checkEmpty(o,n) {
	if (o.val() === '' || o.val() === null) {
		if (n !== undefined) {
			var text = n.replace(":","");
			toastr.error(text + " Required");
		}
		o.closest(".form_group").addClass('has-error');
		o.parent().append('<span class="help-block">' + text + ' required</span>');
		return false;
	} else {
		if (o.closest(".form_group").hasClass("has-error")) {
			o.closest(".form_group").removeClass("has-error");
			o.next().remove();
		}
		return true;
	}
}

function checkNumeric(o,n) {
	if (! $.isNumeric(o.val())) {
		var text = n.replace(":","");
		toastr.error(text + " is not a number!");
		o.closest(".form_group").addClass('has-error');
		o.parent().append('<span class="help-block">' + text + ' is not a number</span>');
		return false;
	} else {
		if (o.closest(".form_group").hasClass("has-error")) {
			o.closest(".form_group").removeClass("has-error");
			o.next().remove();
		}
		return true;
	}
}

function checkRegexp( o, regexp, n ) {
	if ( !( regexp.test( o.val() ) ) ) {
		var text = n.replace(":","");
		toastr.error("Incorrect format: " + text);
		o.closest(".form_group").addClass('has-error');
		o.parent().append('<span class="help-block">"Incorrect format: ' + text + '</span>');
		return false;
	} else {
		if (o.closest(".form_group").hasClass("has-error")) {
			o.closest(".form_group").removeClass("has-error");
			o.next().remove();
		}
		return true;
	}
}

function roundit(which) {
	return Math.round(which*100)/100
}

function load_template_group(id) {
	$("#template_target").val(id);
	$("#template_panel_body").removeClass('hidden');
	$("#template_input").addClass('loading');
	$("#template_input").prop("disabled", true);
	$.ajax({
		type: 'POST',
		url: noshdata.template_get,
		data: 'id=' + id,
		dataType: 'json',
		encode : true
	}).done(function(response) {
		var $target = $("#template_list");
		var html = "";
		if (response.response == 'false') {
			html = '<li class="list-group-item>No templates groups.</li>';
			$target.html(html);
		}
		if (response.response == 'li') {
			$.each(response.message, function (i, val) {
				if (val.value != null) {
					html += '<li class="list-group-item container-fluid"><a href="#" class="btn fa-btn template-default"><i class="fa fa-star fa-lg"></i></a><span class="template-group" data-nosh-id="' + val.id + '">' + val.value + '</span><span class="pull-right"><a href="#" class="btn fa-btn template-edit"><i class="fa fa-pencil fa-lg"></i></a><a href="#" class="btn fa-btn template-edit-stop"><i class="fa fa-check fa-lg"></i></a><a href="#" class="btn fa-btn template-delete"><i class="fa fa-trash fa-lg"></i></a></span></li>';
				}
			});
			$target.html(html);
		}
		$("#template_input").removeClass('loading');
		$("#template_input").prop("disabled", false);
		$("#template_input").attr("placeholder", "Add Group");
	});
}

function loadimagepreview(){
	$('#image_placeholder').html('');
	$('#image_placeholder').empty();
	var image_total = '';
	$.ajax({
		url: noshdata.image_load,
		type: "POST",
		success: function(data){
			$('#image_placeholder').html(data);
			image_total = $("#image_placeholder img").length;
			var $image = $("#image_placeholder img");
			$image.tooltip();
			$image.first().show();
			var i = 1;
			$("#image_status").html('Image ' + i + ' of ' + image_total);
			$('#next_image').click(function () {
				var $next = $image.filter(':visible').hide().next('img');
				i++;
				if($next.length === 0) {
					$next = $image.first();
					i = 1;
				}
				$next.show();
				$("#image_status").html('Image ' + i + ' of ' + image_total);
			});
			$('#prev_image').click(function () {
				var $prev = $image.filter(':visible').hide().prev('img');
				i--;
				if($prev.length === 0) {
					$next = $image.last();
					i = image_total;
				}
				$prev.show();
				$("#image_status").html('Image ' + i + ' of ' + image_total);
			});
		}
	});
}

// $(document).idleTimeout({
// 	inactivity: 3600000,
// 	noconfirm: 10000,
// 	alive_url: noshdata.error,
// 	redirect_url: noshdata.logout_url,
// 	logout_url: noshdata.logout_url,
// 	sessionAlive: false
// });
$(document).ready(function() {
	if ($('#internal_inbox').length) {
		open_messaging('internal_inbox');
	}
	// Typeahead search
	$('.nosh-typeahead').each(function() {
		var source_url = $(this).attr('data-nosh-typeahead');
		var id = $(this).attr('id');
		$('#' + id).prop('disabled', true)
		$('#' + id).addClass('loading');
		$.ajax({
			type: 'POST',
			url: source_url,
			dataType: 'json',
			encode : true
		}).done(function(response) {
			$('#' + id).typeahead({
				source: response,
				afterSelect: function(val) {
					$('#' + id).val(val);
					if (id == 'search_rx') {
						$('#'+$('#search_rx').parent().attr('id')).submit();
					}
					if (id == 'letter_to') {
						var matches = val.match(/\[(.*?)\]/);
						if (matches) {
						    var submatch = matches[1];
						}
						$('#address_id').val(submatch);
						var newval = val.replace(' [' + submatch + ']', '');
						// console.log(newval);
						$('#' + id).val(newval);
					}
				}
			});
			$('#' + id).removeClass('loading');
			$('#' + id).prop('disabled', false)
		});
	});
	// Tags
	$('.nosh-tags').each(function() {
		var id = $(this).attr('id');
		$('#' + id).prop('disabled', true)
		$('#' + id).addClass('loading');
		$.ajax({
			type: 'POST',
			url: noshdata.tags_url,
			dataType: 'json',
			encode : true
		}).done(function(response) {
			$('#' + id).tagsinput({
				typeahead: {
					source: response,
					afterSelect: function(val) { this.$element.val(""); },
				}
			});
			$('#' + id).removeClass('loading');
			$('#' + id).prop('disabled', false)
		});
	});
	$('.nosh-tags').on('itemAdded', function(event) {
		var source_url = $(this).attr('data-nosh-add-url');
		$.ajax({
			type: "POST",
			url: source_url,
			data: 'tag=' + event.item,
			success: function(data) {
				toastr.success(data);
			}
		});
	});
	$('.nosh-tags').on('itemRemoved', function(event) {
		var source_url = $(this).attr('data-nosh-remove-url');
		$.ajax({
			type: "POST",
			url: source_url,
			data: 'tag=' + event.item,
			success: function(data) {
				toastr.success(data);
			}
		});
	});
	// Template buttons
	if ($('textarea').length && $('#template_panel').length) {
		var width = $('textarea').width();
		$('textarea').wrap('<div class="textarea_wrap" style="position:relative;width:100%"></div>');
		$('.textarea_wrap').append('<a href="#template_list" class="btn hidden template_click_show" style="position:absolute;right:15px;top:10px;width:35px;"><i class="fa fa-heart fa-lg" style="width:30px;color:red;"></i></a>');
	}
	$('.template_click_show').on('click', function() {
		var id = $(this).prev().attr('id');
		$('#template_back').attr('href', '#' + id);
	});
});

$(document).on('click', '.form_action', function(e) {
	var form_id = $(this).attr('data-nosh-form');
	var table = $(this).attr('data-nosh-table');
	var row_id = $(this).attr('data-nosh-id');
	var action = $(this).attr('data-nosh-action');
	var refresh_url = $(this).attr('data-nosh-origin');
	var row_index = $(this).attr('data-nosh-index');
	var bValid = true;
	$('#'+form_id).find('[required]').each(function() {
		var input_id = $(this).attr('id');
		var id1 = $('#' + input_id);
		var text = $("label[for='" + input_id + "']").html();
		bValid = bValid && checkEmpty(id1, text);
	});
	if (bValid) {
		var str = $("#"+form_id).serialize();
		$.ajax({
			type: "POST",
			url: "form_action/" + table + '/' + action + '/' + row_id + '/' + row_index,
			data: str,
			dataType: 'json',
			success: function(data){
				if (data.response == 'OK') {
					$('#'+form_id).clearForm();
					$.mobile.loading("show");
					toastr.success(data.message);
					$.ajax({
						type: "POST",
						url: refresh_url,
						success: function(data1){
							$('#content_inner').html(data1).trigger('create');
							$('#edit_content').hide();
							$('#navigation_header').hide();
							$('#content').show();
							$('#chart_header').show();
							$.mobile.loading('hide');
						}
					});
				} else {
					// error handling
				}
			}
		});
	}
});

$(document).on('submit', '.form', function(event) {
	event.preventDefault();
	var formId = $(this).attr('id');
	var bValid = true;
	$('#' + formId).find("[required]").each(function() {
		var input_id = $(this).attr('id');
		var id1 = $("#" + input_id);
		var text = $("label[for='" + input_id + "']").html();
		bValid = bValid && checkEmpty(id1, text);
	});
	if (bValid) {
		var formData = $(this).serialize();
		var formUrl = $(this).attr('action');
		var formTarget = $(this).attr('data-nosh-target');
		var lastInput = $(this).find('input:last').attr('id');
		var searchTo= $(this).attr('data-nosh-search-to');
		$('#modaltext').text('Searching...');
		$('#loadingModal').modal('show');
		$.ajax({
	        type: 'POST',
	        url: formUrl,
	        data: formData,
	        dataType: 'json',
	        encode : true
	    }).done(function(response) {
			var $target = $("#" + formTarget);
			var html = "";
			if (response.response == 'false') {
				html = 'No results.'
				$target.html(html);
			}
	    	if (response.response == 'div') {
				$.each(response.message, function (i, val) {
					if (val.value != null) {
						html += '<a href="' + val.href + '" class="list-group-item" data-nosh-value="' + val.value +'" data-nosh-id="' + val.id + '">' + val.label + '</a>';
					}
				});
				$target.html(html);
			}
			if (response.response == 'li') {
				var category = '';
				$.each(response.message, function (i, val) {
					if (val.value != null) {
						if (val.category_id !== '') {
							if (val.category_id !== category) {
								if (category !== '') {
									$('#'+category).append(html);
								}
							}
							category = val.category_id;
							$target.append('<div class="panel-heading"><h4>' + val.category + '</h4></div><div class="panel"><ul id="' + val.category_id + '"></ul></div>');
							//html = '';
						}
						html += '<li class="list-group-item';
						if (searchTo !== undefined) {
							html += ' nosh-search-to';
						}
						html +='" data-nosh-value="' + val.value +'" data-nosh-id="' + val.id + '"';
						if (searchTo !== undefined) {
							html += ' data-nosh-search-to="' + searchTo + '"';
						}
						if (typeof val.dosage !== 'undefined') {
							html += ' data-nosh-dosage="' + val.dosage + '"';
						}
						if (typeof val.unit !== 'undefined') {
							html += ' data-nosh-unit="' + val.unit + '"';
						}
						if (typeof val.ndcid !== 'undefined') {
							html += ' data-nosh-ndcid="' + val.ndcid + '"';
							html += ' data-nosh-rxcui="' + val.id + '"';
						}
						if (typeof val.ptname !== 'undefined') {
							html += ' data-nosh-ptname="' + val.ptname + '"';
						}
						if (typeof val.insurance_plan_name !== 'undefined') {
							html += ' data-nosh-insurance_plan_name="' + val.insurance_plan_name + '"';
						}
						if (typeof val.charge !== 'undefined') {
							html += ' data-nosh-charge="' + val.charge + '"';
						}
						html +='>' + val.label + '</li>';
					}
				});
				if (category !== '') {
					$('#'+category).append(html);
				} else {
					//$target.html(html);
				}
			}
			if (response.response == 'schedule') {
				$('#eventModal').modal('hide');
				$('#event_form').clearForm;
				$("#calendar").fullCalendar('removeEvents');
				$("#calendar").fullCalendar('refetchEvents');
			}
			$('#loadingModal').modal('hide');
	    });
	}
});

$(document).on('keydown', '.search', function(event){
	var exclude = ['search_rx'];
	var value = $(this).val();
	if(event.keyCode==13) {
		event.preventDefault();
		if ($('.typeahead').is(':visible') === false) {
			$('#'+$(this).closest('form').attr('id')).submit();
		}
	} else {
		if (value && value.length > 2 && $.inArray($(this).attr('id'), exclude) <= -1) {
			$('#'+$(this).closest('form').attr('id')).submit();
		}
	}
});

$(document).on('click', '.nosh-search-to', function(event) {
	var target = $(this).attr('data-nosh-search-to');
	var value = $(this).attr('data-nosh-value');
	var prescribe = '';
	$('#' + target).val(value);
	if ($(this).attr('data-nosh-dosage') !== undefined) {
		$('#rxl_dosage').val($(this).attr('data-nosh-dosage'));
	}
	if ($(this).attr('data-nosh-unit') !== undefined) {
		$('#rxl_dosage_unit').val($(this).attr('data-nosh-unit'));
	}
	if ($(this).attr('data-nosh-ndcid') !== undefined) {
		$('#rxl_ndcid').val($(this).attr('data-nosh-ndcid'));
		prescribe = $(this).attr('data-nosh-rxcui');
	}
	if ($(this).attr('data-nosh-ptname') !== undefined) {
		$('#title').val($(this).attr('data-nosh-ptname'));
		$('#patient_name').text($(this).attr('data-nosh-ptname'));
	}
	if ($(this).attr('data-nosh-insurance_plan_name') !== undefined) {
		$('#insurance_plan_name').val($(this).attr('data-nosh-insurance_plan_name'));
	}
	$(this).parent().prev().children().first().val('');
	$(this).parent().html('');
	$('#' + target).focus();
	if (prescribe !== '') {
		$.ajax({
			type: "POST",
			url: noshdata.search_interactions,
			data: "rxl_medication=" + value + "&rxcui=" + prescribe,
			dataType: 'json',
			success: function(data){
				$('#warningModal_body').html(data.info);
				var text_data = '<button id="warning" class="btn btn-default">Click Here to Learn More</button>';
				toastr.error(text_data, 'Medication Interaction Information Available', {"timeOut":"20000","preventDuplicates":true});
				$('#warning').on('click', function(){
					// toastr.clear();
					$('#warningModal').modal('show');
				});
			}
		});
	}
});

$(document).on('click', '.nosh-search-clear', function(event) {
	var target = $(this).closest("form").attr('data-nosh-target');
	var input = $(this).closest("form").children().first().attr('id');
	$("#" + target).html('');
	$("#" + input).val('');
});

$(document).on('click', 'textarea', function(event) {
	var id = $(this).attr('id');
	load_template_group(id);
	$('.template_click_show').removeClass('visible-xs-block visible-sm-block');
	$('.template_click_show').addClass('hidden');
	$(this).next().removeClass('hidden');
	$(this).next().addClass('visible-xs-block visible-sm-block');
});

$(document).on('click', '.template-group', function(event) {
	var id = $(this).attr('data-nosh-id');
	var group = $(this).text();
	$("#template_group").val(group);
	$("#template_input").addClass('loading');
	$("#template_input").prop("disabled", true);
	$.ajax({
		type: 'POST',
		url: noshdata.template_get,
		data: 'id=' + id + "&template_group=" + group,
		dataType: 'json',
		encode : true
	}).done(function(response) {
		var $target = $("#template_list");
		var html = "";
		if (response.response == 'false') {
			html = '<li class="list-group-item>No templates groups.</li>';
			$target.html(html);
		}
		if (response.response == 'li') {
			$.each(response.message, function (i, val) {
				if (val.value !== null) {
					// html += '<li class="list-group-item container-fluid"><span class="template-item col-xs-7" data-nosh-old-value="' + val.value + '">' + val.value;
					html += '<li class="list-group-item container-fluid"><span class="template-item" data-nosh-old-value="' + val.value + '">' + val.value;
					if (val.input !== null) {
						if (val.input == 'text') {
							html += '<div class="form-group"><input type="text" class="form-control input-sm"></div>';
						}
						if (val.input == 'radio') {
							if (val.options !== null) {
								html += '<div class="form-group">';
								var options = val.options.split(',');
								for (var j=0; j<options.length; j++) {
									html += '<label class="radio-inline"><input type="radio" name="optradio" value="' + options[j] + '">' + options[j] + '</label>';
								}
								html += '</div>';
							}
						}
						if (val.input == 'checkbox') {
							if (val.options !== null) {
								html += '<div class="form-group">';
								var options = val.options.split(',');
								for (var j=0; j<options.length; j++) {
									html += '<label class="checkbox-inline"><input type="checkbox" name="optcheckbox" value="' + options[j] + '">' + options[j] + '</label>';
								}
								html += '</div>';
							}
						}
						if (val.input == 'select') {
							if (val.options !== null) {
								html += '<div class="form-group"><select class="form-control" id="optselect">';
								var options = val.options.split(',');
								for (var j=0; j<options.length; j++) {
									html += '<option>' + options[j] + '</options>';
								}
								html += '</select></div>';
							}
						}
					}
					html += '</span><span class="pull-right"><a href="#" class="btn fa-btn template-normal">'
					if (val.normal === true) {
						html += '<i class="fa fa-star fa-lg"></i>';
					} else {
						html += '<i class="fa fa-star-o fa-lg"></i>';
					}
					html += '</a><a href="#" class="btn fa-btn template-edit"><i class="fa fa-pencil fa-lg"></i><a href="#" class="btn fa-btn template-edit-stop"><i class="fa fa-check fa-lg"></i></a><a href="#" class="btn fa-btn tempalte-delete"><i class="fa fa-trash fa-lg"></i></a></span></li>';
				}
			});
			$target.html(html);
		}
		$("#template_input").removeClass('loading');
		$("#template_input").prop("disabled", false);
		$("#template_input").attr("placeholder", "Add Item");
	});
});

$(document).on('click', '.template-item', function(event){
	if ($(this).attr('contenteditable') == 'true') {
		return true;
	} else {
		if ($(this).parent().hasClass('active')) {
			$(this).parent().removeClass('active');
			return true;
		}
		$(this).parent().addClass('active');
		// var text = $(this).text();
		// var delimiter = ', ';
		// if (noshdata.template_text == '') {
		// 	noshdata.template_text += text;
		// } else {
		// 	noshdata.template_text += delimiter + text;
		// }
		// console.log(noshdata.template_text);
	}
});

$(document).on('click', '.template-edit', function(event){
	$(this).parent().prev().attr('contenteditable', 'true');
	$(this).siblings().hide();
	$(this).hide();
	$(this).next().show();
	$(this).parent().parent().addClass('list-group-item-success');
	$(this).parent().prev().focus();

});

$(document).on('click', '.template-edit-stop', function(event){
	$(this).parent().prev().removeAttr('contenteditable');
	$(this).siblings().show();
	$(this).hide();
	$(this).parent().parent().removeClass('list-group-item-success');
});

$(document).on('click', '#template_back', function(event){
	var id = $("#template_target").val();
	if (id !== '') {
		var old = $("#"+id).val();
		var delimiter = $("#template_delimiter").val();
		var input = '';
		var text = [];
		$('.template-item').each(function() {
			if ($(this).parent().hasClass('active')) {
				text.push($(this).text());
			}
		});
		// $("#template_list").children().find('.active').children().find('.template-item').each(function() {
		// 		var a = $(this).text();
		//  	text.push(a);
		// });
		if (text.length > 0) {
			var group = $("#template_group").val() + ": ";
			var old_delimiter = '\n';
			if (id == 'letter_body') {
				group = '';
				old_delimiter = delimiter
			}
			if (old !== '') {
				input += old + old_delimiter + group;
			} else {
				input += group;
			}
			input += text.join(delimiter);
			$("#"+id).val(input);
		}
		load_template_group(id);
	} else {
		return true;
	}
});


























$(document).on("click", "#encounter_panel", function() {
	noshdata.encounter_active = 'y';
	openencounter();
	$("#nosh_chart_div").hide();
	$("#nosh_encounter_div").show();
});
$(document).on("click", ".ui-jqgrid-titlebar", function() {
	$(".ui-jqgrid-titlebar-close", this).click();
});
$(document).on('click', '#save_oh_sh_form', function(){
	var old = $("#oh_sh").val();
	var old1 = old.trim();
	var a = $("#sh1").val();
	var b = $("#sh2").val();
	var c = $("#sh3").val();
	var d = $("#oh_sh_marital_status").val();
	var d0 = $("#oh_sh_marital_status_old").val();
	var e = $("#oh_sh_partner_name").val();
	var e0 = $("#oh_sh_partner_name").val();
	var f = $("#sh4").val();
	var g = $("#sh5").val();
	var h = $("#sh6").val();
	var i = $("#sh7").val();
	var j = $("#sh8").val();
	var k = $("input[name='sh9']:checked").val();
	var l = $("input[name='sh10']:checked").val();
	var m = $("input[name='sh11']:checked").val();
	if(a){
		var a1 = 'Family members in the household: ' + a + '\n';
	} else {
		var a1 = '';
	}
	if(b){
		var b1 = 'Children: ' + b + '\n';
	} else {
		var b1 = '';
	}
	if(c){
		var c1 = 'Pets: ' + c + '\n';
	} else {
		var c1 = '';
	}
	if(d){
		var d1 = 'Marital status: ' + d + '\n';
	} else {
		var d1 = '';
	}
	if(e){
		var e1 = 'Partner name: ' + e + '\n';
	} else {
		var e1 = '';
	}
	if(f){
		var f1 = 'Diet: ' + f + '\n';
	} else {
		var f1 = '';
	}
	if(g){
		var g1 = 'Exercise: ' + g + '\n';
	} else {
		var g1 = '';
	}
	if(h){
		var h1 = 'Sleep: ' + h + '\n';
	} else {
		var h1 = '';
	}
	if(i){
		var i1 = 'Hobbies: ' + i + '\n';
	} else {
		var i1 = '';
	}
	if(j){
		var j1 = 'Child care arrangements: ' + j + '\n';
	} else {
		var j1 = '';
	}
	if(k){
		var k1 = k + '\n';
	} else {
		var k1 = '';
	}
	if(l){
		var l1 = l + '\n';
	} else {
		var l1 = '';
	}
	if(m){
		var m1 = m + '\n';
	} else {
		var m1 = '';
	}
	var full = d1+e1+a1+b1+c1+f1+g1+h1+i1+j1+k1+l1+m1;
	var full1 = full.trim();
	if (old1 != '') {
		var n = old1+'\n'+full1+'\n';
	} else {
		var n = full1+'\n';
	}
	var o = n.length;
	$("#oh_sh").val(n).caret(o);
	if(d != d0 || e != e0) {
		$.ajax({
			type: "POST",
			url: "ajaxencounter/edit-demographics/sh",
			data: "marital_status=" + d + "&partner_name=" + e,
			success: function(data){
				$.jGrowl(data);
			}
		});
	}
	var sh9_y = $('#sh9_y').attr('checked');
	var sh9_n = $('#sh9_n').attr('checked');
	if(sh9_y){
		$.ajax({
			type: "POST",
			url: "ajaxencounter/edit-demographics/sex",
			data: "status=yes",
			success: function(data){
				$.jGrowl(data);
			}
		});
	}
	if(sh9_n){
		$.ajax({
			type: "POST",
			url: "ajaxencounter/edit-demographics/sex",
			data: "status=no",
			success: function(data){
				$.jGrowl(data);
			}
		});
	}
});
$(document).on("click", '#save_oh_etoh_form', function(){
	var old = $("#oh_etoh").val();
	var old1 = old.trim();
	var a = $("input[name='oh_etoh_select']:checked").val();
	var a0 = $("#oh_etoh_text").val();
	if(a){
		var a1 = a + a0;
	} else {
		var a1 = '';
	}
	if (old1 != '') {
		var b = old1+'\n'+a1+'\n';
	} else {
		var b = a1+'\n';
	}
	var c = b.length;
	$("#oh_etoh").val(b).caret(c);
});
$(document).on('click', '#save_oh_tobacco_form', function(){
	var old = $("#oh_tobacco").val();
	var old1 = old.trim();
	var a = $("input[name='oh_tobacco_select']:checked").val();
	var a0 = $("#oh_tobacco_text").val();
	if(a){
		var a1 = a + a0;
	} else {
		var a1 = '';
	}
	if (old1 != '') {
		var b = old1+'\n'+a1+'\n';
	} else {
		var b = a1+'\n';
	}
	var c = b.length;
	$("#oh_tobacco").val(b).caret(c);
	var tobacco_y = $('#oh_tobacco_y').prop('checked');
	var tobacco_n = $('#oh_tobacco_n').prop('checked');
	if(tobacco_y){
		$.ajax({
			type: "POST",
			url: "ajaxencounter/edit-demographics/tobacco",
			data: "status=yes",
			success: function(data){
				$.jGrowl(data);
			}
		});
	}
	if(tobacco_n){
		$.ajax({
			type: "POST",
			url: "ajaxencounter/edit-demographics/tobacco",
			data: "status=no",
			success: function(data){
				$.jGrowl(data);
			}
		});
	}
});
$(document).on('click', '#save_oh_drugs_form', function(){
	var old = $("#oh_drugs").val();
	var old1 = old.trim();
	var a = $("input[name='oh_drugs_select']:checked").val();
	if(a){
		if (a == 'No illicit drug use.') {
			var a1 = a;
		} else {
			var a0 = $("#oh_drugs_text").val();
			var a2 = $("#oh_drugs_text1").val();
			var a1 = a + a0 + '\nFrequency of drug use: ' + a2;
			$('#oh_drugs_input').hide();
			$('#oh_drugs_text').val('');
			$("#oh_drugs_text1").val('');
			$("input[name='oh_drugs_select']").each(function(){
				$(this).prop('checked', false);
			});
			$('#oh_drugs_form input[type="radio"]').button('refresh');
		}
	} else {
		var a1 = '';
		$('#oh_drugs_input').hide();
	}
	if (old1 != '') {
		var b = old1+'\n'+a1+'\n';
	} else {
		var b = a1+'\n';
	}
	var c = b.length;
	$("#oh_drugs").val(b).caret(c);
});
$(document).on('click', '#save_oh_employment_form', function(){
	var old = $("#oh_employment").val();
	var old1 = old.trim();
	var a = $("input[name='oh_employment_select']:checked").val();
	var b = $("#oh_employment_text").val();
	var c = $("#oh_employment_employer").val();
	var c0 = $("#oh_employment_employer_old").val();
	if(a){
		var a1 = a + '\n';
	} else {
		var a1 = '';
	}
	if(b){
		var b1 = 'Employment field: ' + b + '\n';
	} else {
		var b1 = '';
	}
	if(c){
		var c1 = 'Employer: ' + c + '\n';
	} else {
		var c1 = '';
	}
	var full = a1+b1+c1;
	var full1 = full.trim();
	if (old1 != '') {
		var d = old1+'\n'+full1+'\n';
	} else {
		var d = full1+'\n';
	}
	var e = d.length;
	$("#oh_employment").val(d).caret(e);
	if(c != c0){
		$.ajax({
			type: "POST",
			url: "ajaxencounter/edit-demographics/employer",
			data: "employer=" + c,
			success: function(data){
				$.jGrowl(data);
			}
		});
	}
});

$(document).on('click', "#del_image", function() {
	var image_id1 = $("#image_placeholder img").filter(':visible').attr('id');
	var image_id = image_id1.replace('_image', '');
	if(confirm('Are you sure you want to delete this image?')){
		$.ajax({
			type: "POST",
			url: "ajaxchart/delete-image",
			data: "image_id=" + image_id,
			success: function(data){
				$.jGrowl(data);
				loadimagepreview();
			}
		});
	}
});
$(document).on('keydown', ':text', function(e){
	if(e.keyCode==13) {
		e.preventDefault();
	}
});
$(document).on('keydown', ':password', function(e){
	var a = $(this).attr('id');
	if(a != 'password') {
		if(e.keyCode==13) {
			e.preventDefault();
		}
	}
});
$(document).on('keydown', '.textdump', function(e){
	if(e.keyCode==39) {
		if(e.shiftKey==true) {
			e.preventDefault();
			var id = $(this).attr('id');
			$.ajax({
				type: "POST",
				url: "ajaxsearch/textdump-group/" + id,
				success: function(data){
					$("#textdump_group_html").html('');
					$("#textdump_group_html").append(data);
					$(".edittextgroup").button({text: false, icons: {primary: "ui-icon-pencil"}});
					$(".deletetextgroup").button({text: false, icons: {primary: "ui-icon-trash"}});
					$(".normaltextgroup").button({text: false, icons: {primary: "ui-icon-check"}});
					$(".restricttextgroup").button({text: false, icons: {primary: "ui-icon-close"}});
					$('.textdump_group_item_text').editable('destroy');
					$('.textdump_group_item_text').editable({
						toggle:'manual',
						ajaxOptions: {
							headers: {"cache-control":"no-cache"},
							beforeSend: function(request) {
								return request.setRequestHeader("X-CSRF-Token", $("meta[name='token']").attr('content'));
							},
							error: function(xhr) {
								if (xhr.status == "404" ) {
									alert("Route not found!");
									//window.location.replace(noshdata.error);
								} else {
									if(xhr.responseText){
										var response1 = $.parseJSON(xhr.responseText);
										var error = "Error:\nType: " + response1.error.type + "\nMessage: " + response1.error.message + "\nFile: " + response1.error.file;
										alert(error);
									}
								}
							}
						}
					});
					$("#textdump_group_target").val(id);
					$("#textdump_group").dialog("option", "position", { my: 'left top', at: 'right top', of: '#'+id });
					$("#textdump_group").dialog('open');
				}
			});
		}
	}
});

$(document).on('click', '.textdump_item', function() {
	if ($(this).find(':first-child').hasClass("ui-state-error") == false) {
		$(this).find(':first-child').addClass("ui-state-error ui-corner-all");
	} else {
		$(this).find(':first-child').removeClass("ui-state-error ui-corner-all");
	}
});
$(document).on('click', '.textdump_item_specific', function() {
	if ($(this).find(':first-child').hasClass("ui-state-error") == false) {
		$(this).find(':first-child').addClass("ui-state-error ui-corner-all");
	} else {
		$(this).find(':first-child').removeClass("ui-state-error ui-corner-all");
	}
});
$(document).on('click', '.edittextgroup', function(e) {
	var id = $(this).attr('id');
	var isEditable= $("#"+id+"_b").is('.editable');
	$("#"+id+"_b").prop('contenteditable',!isEditable).toggleClass('editable');
	if (isEditable) {
		var url = $("#"+id+"_b").attr('data-url');
		var pk = $("#"+id+"_b").attr('data-pk');
		var name = $("#"+id+"_b").attr('data-name');
		var title = $("#"+id+"_b").attr('data-title');
		var type = $("#"+id+"_b").attr('data-type');
		var value = encodeURIComponent($("#"+id+"_b").html());
		$.ajax({
			type: "POST",
			url: url,
			data: 'value=' + value + "&pk=" + pk + "&name=" + name,
			success: function(data){
				toastr.success(data);
			}
		});
		$(this).html('<i class="zmdi zmdi-edit"></i>');
		$(this).siblings('.deletetextgroup').show();
		$(this).siblings('.restricttextgroup').show();
	} else {
		$(this).html('<i class="zmdi zmdi-check"></i>');
		$(this).siblings('.deletetextgroup').hide();
		$(this).siblings('.restricttextgroup').hide();
	}
});
$(document).on('click', '.edittexttemplate', function(e) {
	var id = $(this).attr('id');
	e.stopPropagation();
	$("#"+id+"_span").editable('show', true);
});
$(document).on('click', '.edittexttemplatespecific', function(e) {
	var id = $(this).attr('id');
	e.stopPropagation();
	$("#"+id+"_span").editable('show', true);
});
$(document).on('click', '.deletetextgroup', function() {
	var id = $(this).attr('id');
	var template_id = id.replace('deletetextgroup_','');
	$.ajax({
		type: "POST",
		url: "ajaxsearch/deletetextdumpgroup/" + template_id,
		success: function(data){
			$("#textgroupdiv_"+template_id).remove();
		}
	});
});
$(document).on('click', '.restricttextgroup', function() {
	var id = $(this).attr('id');
	var template_id = id.replace('restricttextgroup_','');
	$("#restricttextgroup_template_id").val(template_id);
	$.ajax({
		type: "POST",
		url: "ajaxsearch/restricttextgroup-get/" + template_id,
		dataType: 'json',
		success: function(data){
			$.each(data, function(key, value){
				$("#restricttextgroup_form :input[name='" + key + "']").val(value);
			});
		}
	});
	$("#restricttextgroup_dialog").dialog('open');
});
$(document).on('click', '.deletetexttemplate', function() {
	var id = $(this).attr('id');
	var template_id = id.replace('deletetexttemplate_','');
	$.ajax({
		type: "POST",
		url: "ajaxsearch/deletetextdump/" + template_id,
		success: function(data){
			$("#texttemplatediv_"+template_id).remove();
		}
	});
});
$(document).on('click', '.deletetexttemplatespecific', function() {
	var id = $(this).attr('id');
	var template_id = id.replace('deletetexttemplatespecific_','');
	$.ajax({
		type: "POST",
		url: "ajaxsearch/deletetextdump/" + template_id,
		success: function(data){
			$("#texttemplatespecificdiv_"+template_id).remove();
		}
	});
});
$(document).on('click', '.normaltextgroup', function() {
	var id = $("#textdump_group_target").val();
	var a = $(this).val();
	var old = $("#"+id).val();
	var delimiter = $("#textdump_delimiter2").val();
	if (a != 'No normal values set.') {
		var a_arr = a.split("\n");
		var d = a_arr.join(delimiter);
		if ($(this).prop('checked')) {
			if (old != '') {
				var b = old + '\n' + d;
			} else {
				var b = d;
			}
			$("#"+id).val(b);
		} else {
			var a1 = d + '  ';
			var c = old.replace(a1,'');
			c = c.replace(d, '');
			$("#" +id).val(c);
		}
	} else {
		$.jGrowl(a);
	}
});
$(document).on('click', '.normaltexttemplate', function() {
	var id = $(this).attr('id');
	var template_id = id.replace('normaltexttemplate_','');
	if ($(this).prop('checked')) {
		$.ajax({
			type: "POST",
			url: "ajaxsearch/defaulttextdump/" + template_id,
			success: function(data){
				$.jGrowl('Template marked as normal default!');
				$("#textdump_group_html").html('');
				$("#textdump_group_html").append(data);
				$(".edittextgroup").button({text: false, icons: {primary: "ui-icon-pencil"}});
				$(".deletetextgroup").button({text: false, icons: {primary: "ui-icon-trash"}});
				$(".normaltextgroup").button({text: false, icons: {primary: "ui-icon-check"}});
				$(".restricttextgroup").button({text: false, icons: {primary: "ui-icon-close"}});
				$('.textdump_group_item_text').editable('destroy');
				$('.textdump_group_item_text').editable({
					toggle:'manual',
					ajaxOptions: {
						headers: {"cache-control":"no-cache"},
						beforeSend: function(request) {
							return request.setRequestHeader("X-CSRF-Token", $("meta[name='token']").attr('content'));
						},
						error: function(xhr) {
							if (xhr.status == "404" ) {
								alert("Route not found!");
								//window.location.replace(noshdata.error);
							} else {
								if(xhr.responseText){
									var response1 = $.parseJSON(xhr.responseText);
									var error = "Error:\nType: " + response1.error.type + "\nMessage: " + response1.error.message + "\nFile: " + response1.error.file;
									alert(error);
								}
							}
						}
					}
				});
			}
		});
	} else {
		$.ajax({
			type: "POST",
			url: "ajaxsearch/undefaulttextdump/" + template_id,
			success: function(data){
				$.jGrowl('Template unmarked as normal default!');
				$("#textdump_group_html").html('');
				$("#textdump_group_html").append(data);
				$(".edittextgroup").button({text: false, icons: {primary: "ui-icon-pencil"}});
				$(".deletetextgroup").button({text: false, icons: {primary: "ui-icon-trash"}});
				$(".normaltextgroup").button({text: false, icons: {primary: "ui-icon-check"}});
				$(".restricttextgroup").button({text: false, icons: {primary: "ui-icon-close"}});
				$('.textdump_group_item_text').editable('destroy');
				$('.textdump_group_item_text').editable({
					toggle:'manual',
					ajaxOptions: {
						headers: {"cache-control":"no-cache"},
						beforeSend: function(request) {
							return request.setRequestHeader("X-CSRF-Token", $("meta[name='token']").attr('content'));
						},
						error: function(xhr) {
							if (xhr.status == "404" ) {
								alert("Route not found!");
								//window.location.replace(noshdata.error);
							} else {
								if(xhr.responseText){
									var response1 = $.parseJSON(xhr.responseText);
									var error = "Error:\nType: " + response1.error.type + "\nMessage: " + response1.error.message + "\nFile: " + response1.error.file;
									alert(error);
								}
							}
						}
					}
				});
			}
		});
	}
});
$(document).on('keydown', '#textdump_group_add', function(e){
	if(e.keyCode==13) {
		e.preventDefault();
		var a = $("#textdump_group_add").val();
		if (a != '') {
			var str = $("#textdump_group_form").serialize();
			if(str){
				$.ajax({
					type: "POST",
					url: "ajaxsearch/add-text-template-group",
					data: str,
					dataType: 'json',
					success: function(data){
						$.jGrowl(data.message);
						var app = '<div id="textgroupdiv_' + data.id + '" style="width:99%" class="pure-g"><div class="pure-u-2-3"><input type="checkbox" id="normaltextgroup_' + data.id + '" class="normaltextgroup" value="No normal values set."><label for="normaltextgroup_' + data.id + '">Normal</label> <b id="edittextgroup_' + data.id + '_b" class="textdump_group_item textdump_group_item_text" data-type="text" data-pk="' + data.id + '" data-name="group" data-url="ajaxsearch/edit-text-template-group" data-title="Group">' + a + '</b></div><div class="pure-u-1-3" style="overflow:hidden"><div style="width:200px;"><button type="button" id="edittextgroup_' + data.id + '" class="edittextgroup">Edit</button><button type="button" id="deletetextgroup_' + data.id + '" class="deletetextgroup">Remove</button><button type="button" id="restricttextgroup_' + data.id + '" class="restricttextgroup">Restrictions</button></div></div><hr class="ui-state-default"/></div>';
						$("#textdump_group_html").append(app);
						$(".edittextgroup").button({text: false, icons: {primary: "ui-icon-pencil"}});
						$(".deletetextgroup").button({text: false, icons: {primary: "ui-icon-trash"}});
						$(".normaltextgroup").button({text: false, icons: {primary: "ui-icon-check"}});
						$(".restricttextgroup").button({text: false, icons: {primary: "ui-icon-close"}});
						$('.textdump_group_item_text').editable('destroy');
						$('.textdump_group_item_text').editable({
							toggle:'manual',
							ajaxOptions: {
								headers: {"cache-control":"no-cache"},
								beforeSend: function(request) {
									return request.setRequestHeader("X-CSRF-Token", $("meta[name='token']").attr('content'));
								},
								error: function(xhr) {
									if (xhr.status == "404" ) {
										alert("Route not found!");
										//window.location.replace(noshdata.error);
									} else {
										if(xhr.responseText){
											var response1 = $.parseJSON(xhr.responseText);
											var error = "Error:\nType: " + response1.error.type + "\nMessage: " + response1.error.message + "\nFile: " + response1.error.file;
											alert(error);
										}
									}
								}
							}
						});
						$("#textdump_group_add").val('');
					}
				});
			} else {
				$.jGrowl("Please complete the form");
			}
		} else {
			$.jGrowl("No text to add!");
		}
	}
});
$(document).on('keydown', '#textdump_add', function(e){
	if(e.keyCode==13) {
		e.preventDefault();
		var a = $("#textdump_add").val();
		if (a != '') {
			var str = $("#textdump_form").serialize();
			if(str){
				$.ajax({
					type: "POST",
					url: "ajaxsearch/add-text-template",
					data: str,
					dataType: 'json',
					success: function(data){
						$.jGrowl(data.message);
						var app = '<div id="texttemplatediv_' + data.id + '" style="width:99%" class="pure-g"><div class="textdump_item pure-u-2-3"><span id="edittexttemplate_' + data.id + '_span" class="textdump_item_text ui-state-error ui-corner-all" data-type="text" data-pk="' + data.id + '" data-name="array" data-url="ajaxsearch/edit-text-template" data-title="Item">' + a + '</span></div><div class="pure-u-1-3" style="overflow:hidden"><div style="width:400px;"><input type="checkbox" id="normaltexttemplate_' + data.id + '" class="normaltexttemplate" value="normal"><label for="normaltexttemplate_' + data.id + '">Mark as Default Normal</label><button type="button" id="edittexttemplate_' + data.id + '" class="edittexttemplate">Edit</button><button type="button" id="deletetexttemplate_' + data.id + '" class="deletetexttemplate">Remove</button></div></div><hr class="ui-state-default"/></div>';
						$("#textdump_html").append(app);
						$(".edittexttemplate").button({text: false, icons: {primary: "ui-icon-pencil"}});
						$(".deletetexttemplate").button({text: false, icons: {primary: "ui-icon-trash"}});
						$(".normaltexttemplate").button({text: false, icons: {primary: "ui-icon-check"}});
						$('.textdump_item_text').editable('destroy');
						$('.textdump_item_text').editable({
							toggle:'manual',
							ajaxOptions: {
								headers: {"cache-control":"no-cache"},
								beforeSend: function(request) {
									return request.setRequestHeader("X-CSRF-Token", $("meta[name='token']").attr('content'));
								},
								error: function(xhr) {
									if (xhr.status == "404" ) {
										alert("Route not found!");
										//window.location.replace(noshdata.error);
									} else {
										if(xhr.responseText){
											var response1 = $.parseJSON(xhr.responseText);
											var error = "Error:\nType: " + response1.error.type + "\nMessage: " + response1.error.message + "\nFile: " + response1.error.file;
											alert(error);
										}
									}
								}
							}
						});
						$("#textdump_add").val('');
					}
				});
			} else {
				$.jGrowl("Please complete the form");
			}
		} else {
			$.jGrowl("No text to add!");
		}
	}
});
$(document).on('keydown', '#textdump_specific_add', function(e){
	if(e.keyCode==13) {
		e.preventDefault();
		var a = $("#textdump_specific_add").val();
		if (a != '') {
			var specific_name = $("#textdump_specific_name").val();
			if (specific_name == '') {
				var id = $("#textdump_specific_target").val();
				var start = $("#textdump_specific_start").val();
				var length = $("#textdump_specific_length").val();
				$("#"+id).textrange('set', start, length);
				$("#"+id).textrange('replace', a);
				$("#textdump_specific").dialog('close');
			} else {
				var str = $("#textdump_specific_form").serialize();
				if(str){
					$.ajax({
						type: "POST",
						url: "ajaxsearch/add-specific-template",
						data: str,
						dataType: 'json',
						success: function(data){
							$.jGrowl(data.message);
							var app = '<div id="texttemplatespecificdiv_' + data.id + '" style="width:99%" class="pure-g"><div class="textdump_item_specific pure-u-2-3"><span id="edittexttemplatespecific_' + data.id + '_span" class="textdump_item_specific_text ui-state-error ui-corner-all" data-type="text" data-pk="' + data.id + '" data-name="array" data-url="ajaxsearch/edit-text-template-specific" data-title="Item">' + a + '</span></div><div class="pure-u-1-3" style="overflow:hidden"><div style="width:400px;"><button type="button" id="edittexttemplatespecific_' + data.id + '" class="edittexttemplatespecific">Edit</button><button type="button" id="deletetexttemplatespecific_' + data.id + '" class="deletetexttemplatespecific">Remove</button></div></div><hr class="ui-state-default"/></div>';
							$("#textdump_specific_html").append(app);
							$(".edittexttemplatespecific").button({text: false, icons: {primary: "ui-icon-pencil"}});
							$(".deletetexttemplatespecific").button({text: false, icons: {primary: "ui-icon-trash"}});
							$(".defaulttexttemplatespecific").button();
							$('.textdump_item_specific_text').editable('destroy');
							$('.textdump_item_specific_text').editable({
								toggle:'manual',
								ajaxOptions: {
									headers: {"cache-control":"no-cache"},
									beforeSend: function(request) {
										return request.setRequestHeader("X-CSRF-Token", $("meta[name='token']").attr('content'));
									},
									error: function(xhr) {
										if (xhr.status == "404" ) {
											alert("Route not found!");
											//window.location.replace(noshdata.error);
										} else {
											if(xhr.responseText){
												var response1 = $.parseJSON(xhr.responseText);
												var error = "Error:\nType: " + response1.error.type + "\nMessage: " + response1.error.message + "\nFile: " + response1.error.file;
												alert(error);
											}
										}
									}
								}
							});
							$("#textdump_specific_add").val('');
						}
					});
				} else {
					$.jGrowl("Please complete the form");
				}
			}
		} else {
			$.jGrowl("No text to add!");
		}
	}
});
$(document).on("change", "#hippa_address_id", function () {
	var a = $(this).find("option:selected").first().text();
	if (a != 'Select Provider') {
		$("#hippa_provider1").val(a);
	} else {
		$("#hippa_provider1").val('');
	}
});
$(document).on('click', "#hippa_address_id2", function (){
	var id = $("#hippa_address_id").val();
	if(id){
		$("#print_to_dialog").dialog("option", "title", "Edit Provider");
		$.ajax({
			type: "POST",
			url: "ajaxsearch/orders-provider1",
			data: "address_id=" + id,
			dataType: "json",
			success: function(data){
				$.each(data, function(key, value){
					$("#print_to_form :input[name='" + key + "']").val(value);
				});
			}
		});
	} else {
		$("#print_to_dialog").dialog("option", "title", "Add Provider");
	}
	$("#print_to_origin").val('hippa');
	$("#print_to_dialog").dialog('open');
});
$(document).on("change", "#hippa_request_address_id", function () {
	var a = $(this).find("option:selected").first().text();
	if (a != 'Select Provider') {
		$("#hippa_request_to").val(a);
	} else {
		$("#hippa_request_to").val('');
	}
});
$(document).on('click', "#hippa_request_address_id2", function (){
	var id = $("#hippa_request_address_id").val();
	if(id){
		$("#print_to_dialog").dialog("option", "title", "Edit Provider");
		$.ajax({
			type: "POST",
			url: "ajaxsearch/orders-provider1",
			data: "address_id=" + id,
			dataType: "json",
			success: function(data){
				$.each(data, function(key, value){
					$("#print_to_form :input[name='" + key + "']").val(value);
				});
			}
		});
	} else {
		$("#print_to_dialog").dialog("option", "title", "Add Provider");
	}
	$("#print_to_origin").val('request');
	$("#print_to_dialog").dialog('open');
});
$(document).on('click', '.assessment_clear', function(){
	var id = $(this).attr('id');
	var parts = id.split('_');
	console.log(parts[2]);
	$("#assessment_" + parts[2]).val('');
	$("#assessment_icd" + parts[2]).val('');
	$("#assessment_icd" + parts[2] + "_div").html('');
	$("#assessment_icd" + parts[2] + "_div_button").hide();
});
$(document).on('click', '.hedis_patient', function() {
	var id = $(this).attr('id');
	var pid = id.replace('hedis_', '');
	$.ajax({
		type: "POST",
		url: "ajaxsearch/openchart",
		data: "pid=" + pid,
		success: function(data){
			$.ajax({
				type: "POST",
				url: "ajaxsearch/hedis-set",
				dataType: "json",
				success: function(data){
					window.location = data.url;
				}
			});
		}
	});
});
$(document).on('click', '.claim_associate', function() {
	var id = $(this).attr('id');
	var form_id = id.replace('era_button_', 'era_form_');
	var div_id = id.replace('era_button_', 'era_div_');
	var bValid = true;
	$("#" + form_id).find("[required]").each(function() {
		var input_id = $(this).attr('id');
		var id1 = $("#" + input_id);
		var text = $("label[for='" + input_id + "']").html();
		bValid = bValid && checkEmpty(id1, text);
	});
	if (bValid) {
		var str = $("#" + form_id).serialize();
		if(str){
			$.ajax({
				type: "POST",
				url: "ajaxfinancial/associate-claim",
				data: str,
				success: function(data){
					$.jGrowl(data);
					$("#" + form_id).clearForm();
					$('#' + div_id).remove();
				}
			});
		} else {
			$.jGrowl("Please complete the form");
		}
	}
});

$(document).on('click', '#autogenerate_encounter_template', function() {
	$('#dialog_load').dialog('option', 'title', "Autogenerating template...").dialog('open');
	var str = $("#template_encounter_edit_form").serialize();
	if(str){
		$.ajax({
			type: "POST",
			url: "ajaxsearch/autogenerate-encounter-template",
			data: str,
			dataType: "json",
			success: function(data){
				$.jGrowl(data.message);
				if (data.name != '') {
					$("#template_encounter_edit_dialog").dialog('close');
					$('#dialog_load').dialog('close');
					$('#dialog_load').dialog('option', 'title', "Loading template...").dialog('open');
					$.ajax({
						type: "POST",
						url: "ajaxsearch/get-encounter-templates-details",
						data: 'template_name='+data.name,
						dataType: "json",
						success: function(data){
							$('#dialog_load').dialog('close');
							$("#template_encounter_edit_div").html(data.html);
							$("#template_encounter_edit_dialog").dialog("option", "title", "Edit Encounter Template");
							$("#template_encounter_edit_dialog").dialog('open');
						}
					});
				}
			}
		});
	}
});
$(document).on('click', '.remove_encounter_template_field', function() {
	var id = $(this).attr('id');
	var a1 = id.split("_");
	var count = a1[4];
	$("#group_encounter_template_div_"+count).remove();
	$("#array_encounter_template_div_"+count).remove();
	$("#remove_encounter_template_div_"+count).remove();
});

$(document).on('click', '.timeline_event', function() {
	var type = $(this).attr('type');
	var value = $(this).attr('value');
	var status = $(this).attr('status');
	var acl = false;
	if (noshdata.group_id == '2' || noshdata.group_id == '3') {
		acl = true;
	}
	if (type == 'eid') {
		if (status == 'Yes') {
			if (acl) {
				$("#encounter_view").load('ajaxchart/modal-view/' + value);
			} else {
				$.ajax({
					type: "POST",
					url: "ajaxcommon/opennotes",
					success: function(data){
						if (data == 'y') {
							$("#encounter_view").load('ajaxcommon/modal-view2/' + value);
						} else {
							$.jGrowl('You cannot view the encounter as your provider has not activated OpenNotes.');
						}
					}
				});
			}
			$("#encounter_view_dialog").dialog('open');
		} else {
			$.jGrowl('Encounter is not signed.  You cannot view it at this time.');
		}
	} else if (type == 't_messages_id') {
		if (status == 'Yes') {
			if (acl) {
				$("#message_view").load('ajaxcommon/tmessages-view/' + value);
				$("#t_messages_id").val(value);
				t_messages_tags();
				$("#messages_view_dialog").dialog('open');
			} else {
				$.ajax({
					type: "POST",
					url: "ajaxcommon/opennotes",
					success: function(data){
						if (data == 'y') {
							$("#message_view").load('ajaxcommon/tmessages-view/' + value);
							$("#t_messages_id").val(value);
							$("#messages_view_dialog").dialog('open');
						} else {
							$.jGrowl('You cannot view the message as your provider has not activated OpenNotes.');
						}
					}
				});
			}
		} else {
			$.jGrowl('Message is not signed.  You cannot view it at this time.');
		}
	}
	console.log(value + "," + type);
});

// Mobile
$(document).on('click', '.ui-title', function(e) {
	$("#form_item").val('');
	$("#search_results").html('');
	var url = $(location).attr('href');
	var parts = url.split("/");
	if (parts[4] == 'chart_mobile') {
		$.mobile.loading("show");
		$.ajax({
			type: "POST",
			url: "../ajaxchart/refresh-timeline",
			success: function(data){
				$("#content_inner_timeline").html(data);
				$("#content_inner_main").show();
				$("#content_inner").hide();
				//refresh_timeline();
				$.mobile.loading("hide");
			}
		});
	}
});
$(document).on('click', '.mobile_click_home', function(e) {
	var classes = $(this).attr('class').split(' ');
	for (var i=0; i<classes.length; i++) {
		if (classes[i].indexOf("ui-") == -1) {
			if (classes[i] != 'mobile_click_home') {
				//console.log(classes[i]);
				//var link = classes[i].replace("mobile_","");
				//$.mobile.loading("show");
				//$.ajax({
					//type: "POST",
					//url: "ajaxdashboard/" + link,
					//success: function(data){
						//$("#content_inner").html(data).trigger('create').show();
						//$("#content_inner_main").hide();
						//$.mobile.loading("hide");
					//}
				//});
				window.location = classes[i];
				break;
			}
		}
	}
});
$(document).on('click', '.mobile_click_chart', function(e) {
	var classes = $(this).attr('class').split(' ');
	for (var i=0; i<classes.length; i++) {
		if (classes[i].indexOf("ui-") == -1) {
			if (classes[i] != 'mobile_click_chart') {
				console.log(classes[i]);
				var link = classes[i].replace("mobile_","");
				$.ajax({
					type: "POST",
					url: "../ajaxchart/" + link + "/true",
					success: function(data){
						$("#content_inner").html(data).trigger('create').show();
						$.mobile.loading("hide");
						$("#content_inner_main").hide();
						$("#left_panel").panel('close');
					}
				});
				break;
			}
		}
	}
});
$(document).on('click', '.mobile_link', function(e) {
	$.mobile.loading("show");
	$("#content").hide();
	$("#chart_header").hide();
	var url = $(this).attr('data-nosh-url');
	var origin = $(this).attr('data-nosh-origin');
	$.ajax({
		type: "POST",
		url: url,
		data: 'origin=' + origin,
		dataType: 'json',
		success: function(data){
			$("#navigation_header_back").attr('data-nosh-origin', origin);
			$("#navigation_header_save").attr('data-nosh-form', data.form);
			$("#navigation_header_save").attr('data-nosh-origin', origin);
			if (data.search != '') {
				$(".search_class").hide();
				$("#"+data.search+"_div").show();
				$("#"+data.search+"_div").find('ul').attr('data-nosh-paste-to',data.search_to);
			}
			$("#edit_content_inner").html(data.content).trigger('create');
			$("#navigation_header").show();
			$("#edit_content").show();
			$.mobile.loading("hide");
		}
	});
});
$(document).on('click', '#navigation_header_back', function(e) {
	$.mobile.loading("show");
	var origin = $(this).attr('data-nosh-origin');
	if (origin == 'Chart') {
		$("#navigation_header").hide();
		$("#content_inner").hide();
		$("#chart_header").show();
		$("#content_inner_main").show();
		$.mobile.loading("hide");
		var scroll = parseInt($(this).attr('data-nosh-scroll'));
		$.mobile.silentScroll(scroll-70);
	} else {
		$.ajax({
			type: "POST",
			url: origin,
			success: function(data){
				$("#content_inner").html(data).trigger('create');
				$("#edit_content").hide();
				$("#navigation_header").hide();
				$("#content").show();
				$("#chart_header").show();
				$.mobile.loading("hide");
			}
		});
	}
});
$(document).on('click', '.cancel_edit', function(e) {
	$.mobile.loading("show");
	var origin = $(this).attr('data-nosh-origin');
	$.ajax({
		type: "POST",
		url: origin,
		success: function(data){
			$("#content_inner").html(data).trigger('create');
			$("#edit_content").hide();
			$("#navigation_header").hide();
			$("#content").show();
			$("#chart_header").show();
			$.mobile.loading("hide");
		}
	});
});
$(document).on('click', '.cancel_edit2', function(e) {
	var form = $(this).attr('data-nosh-form');
	$("#"+form).clearForm();
	$("#edit_content").hide();
	$("#content").show();
});
$(document).on('click', '.nosh_schedule_event', function(e) {
	var editable = $(this).attr('data-nosh-editable');
	if (editable != "false") {
		var id = $(this).attr('id');
		if (id == 'patient_appt_button') {
			loadappt();
			var startday = $(this).attr('data-nosh-start');
			$('#start_date').val(startday);
			$("#edit_content").show();
			$("#content").hide();
			$("#title").focus();
			$.mobile.silentScroll(0);
			return false;
		}
		if (id == 'event_appt_button') {
			loadevent();
			var startday = $(this).attr('data-nosh-start');
			$('#start_date').val(startday);
			$("#edit_content").show();
			$("#content").hide();
			$("#title").focus();
			$.mobile.silentScroll(0);
			return false;
		}
		var form = {};
		$.each($(this).get(0).attributes, function(i, attr) {
			if (attr.name.indexOf("data-nosh-") == '0') {
				var field = attr.name.replace('data-nosh-','');
				field = field.replace('-', '_');
				if (field == 'visit_type') {
					form.visit_type = attr.value;
				}
				if (field == 'title') {
					form.title = attr.value;
				}
				if (attr.value != 'undefined') {
					if (field != 'timestamp') {
						var value = attr.value;
						if (field.indexOf('_date') > 0) {
							value = moment(new Date(value)).format('YYYY-MM-DD');
						}
						if (field == 'pid') {
							field = 'schedule_pid';
						}
						if (field == 'title') {
							field = 'schedule_title';
						}
						$('#' + field).val(value);
					}
				}
			}
		});
		var timestamp = $(this).attr('data-nosh-timestamp');
		$("#event_id_span").text(form.event_id);
		$("#pid_span").text(form.pid);
		$("#timestamp_span").text(timestamp);
		if (form.visit_type){
			loadappt();
			$("#patient_search").val(form.title);
			$("#end").val('');
		} else {
			loadevent();
		}
		var repeat_select = $("#repeat").val();
		if (repeat_select != ''){
			$("#until_row").show();
		} else {
			$("#until_row").hide();
			$("#until").val('');
		}
		$("#delete_form").show();
		$("#schedule_form select").selectmenu('refresh');
		$("#edit_content").show();
		$("#content").hide();
		$("#title").focus();
		$.mobile.silentScroll(0);
		return false;
	} else {
		toastr.error('You cannot edit this entry!');
		return false;
	}
});
$(document).on('click', '.nosh_messaging_item', function(e) {
	var form = {};
	var datastring = '';
	var label = $(this).html();
	label = label.replace('<h3>','<h3 class="card-primary-title">');
	label = label.replace('<p>','<h5 class="card-subtitle">');
	label = label.replace('</p>','</h5>');
	var origin = $(this).attr('data-origin');
	var id = $(this).attr('data-nosh-message-id');
	$.each($(this).get(0).attributes, function(i, attr) {
		if (attr.name.indexOf("data-nosh-") == '0') {
			datastring += attr.name + '="' + attr.value + '" ';
			var field = attr.name.replace('data-nosh-','');
			if (field == 'message-from-label') {
				form.displayname = attr.value;
			}
			if (field == 'date') {
				form.date = attr.value;
			}
			if (field == 'subject') {
				form.subject = attr.value;
			}
			if (field == 'body') {
				form.body = attr.value;
			}
			if (field == 'bodytext') {
				form.bodytext = attr.value;
			}
		}
	});
	var text = '<br><strong>From:</strong> ' + form.displayname + '<br><br><strong>Date:</strong> ' + form.date + '<br><br><strong>Subject:</strong> ' + form.subject + '<br><br><strong>Message:</strong> ' + form.bodytext;
	var action = '<div class="card-action">';
		action += '<div class="row between-xs">';
			action += '<div class="col-xs-4">';
				action += '<div class="box">';
					action += '<a href="#" class="ui-btn ui-btn-inline ui-btn-fab back_message" data-origin="' + origin + '" data-origin-id="' + id + '"><i class="zmdi zmdi-arrow-left"></i></a>';
				action += '</div>'
			action += '</div>'
			if (origin == 'internal_inbox') {
				action += '<div class="col-xs-8 align-right">';
					action += '<div class="box">';
						action += '<a href="#" class="ui-btn ui-btn-inline ui-btn-fab reply_message"' + datastring + '><i class="zmdi zmdi-mail-reply"></i></a>';
						action += '<a href="#" class="ui-btn ui-btn-inline ui-btn-fab reply_all_message"' + datastring + '><i class="zmdi zmdi-mail-reply-all"></i></a>';
						action += '<a href="#" class="ui-btn ui-btn-inline ui-btn-fab forward_message"' + datastring + '><i class="zmdi zmdi-forward"></i></a>';
						action += '<a href="#" class="ui-btn ui-btn-inline ui-btn-fab export_message"' + datastring + '><i class="zmdi zmdi-sign-in"></i></a>';
					action += '</div>';
				action += '</div>';
			}
		action += '</div>';
	action += '</div>';
	var html = '<div class="nd2-card">';
		html += '<div class="card-title">' + label + '</div>' + action;
		html += '<div class="card-supporting-text">' + text + '</div>' + action;
	html += '</div>';
	$("#message_view1").html(html);
	//$("#message_view_rawtext").val(rawtext);
	//$("#message_view_message_id").val(id);
	//$("#message_view_from").val(row['message_from']);
	//$("#message_view_to").val(row['message_to']);
	//$("#message_view_cc").val(row['cc']);
	//$("#message_view_subject").val(row['subject']);
	//$("#message_view_body").val(row['body']);
	//$("#message_view_date").val(row['date']);
	//$("#message_view_pid").val(row['pid']);
	//$("#message_view_patient_name").val(row['patient_name']);
	//$("#message_view_t_messages_id").val(row['t_messages_id']);
	//$("#message_view_documents_id").val(row['documents_id']);
	//messages_tags();
	//if (row['pid'] == '' || row['pid'] == "0") {
		//$("#export_message").hide();
	//} else {
		//$("#export_message").show();
	//}
	//$("#internal_messages_view_dialog").dialog('open');
	//setTimeout(function() {
		//var a = $("#internal_messages_view_dialog" ).dialog("isOpen");
		//if (a) {
			//var id = $("#message_view_message_id").val();
			//var documents_id = $("#message_view_documents_id").val();
			//if (documents_id == '') {
				//documents_id = '0';
			//}
			//$.ajax({
				//type: "POST",
				//url: "ajaxmessaging/read-message/" + id + "/" + documents_id,
				//success: function(data){
					//$.jGrowl(data);
					//reload_grid("internal_inbox");
				//}
			//});
		//}
	//}, 3000);
	//form.event_id = $(this).attr('data-nosh-event-id');
	//form.pid = $(this).attr('data-nosh-pid');
	//form.start_date = moment(new Date($(this).attr('data-nosh-start-date'))).format('YYYY-MM-DD');
	//form.start_time = $(this).attr('data-nosh-start-time');
	//form.end = $(this).attr('data-nosh-end-time');
	//form.visit_type = $(this).attr('data-nosh-visit-type');
	//form.title = $(this).attr('data-nosh-title');
	//form.repeat = $(this).attr('data-nosh-repeat');
	//form.reason = $(this).attr('data-nosh-reason');
	//form.until = $(this).attr('data-nosh-until');
	//form.notes = $(this).attr('data-nosh-notes');
	//form.status = $(this).attr('data-nosh-status');
	//$.each(form, function(key, value){
		//if (value != 'undefined') {
			//$('#'+key).val(value);
		//}
	//});
	//var timestamp = $(this).attr('data-nosh-timestamp');
	//$("#event_id_span").text(form.event_id);
	//$("#pid_span").text(form.pid);
	//$("#timestamp_span").text(timestamp);
	//if (form.visit_type){
		//loadappt();
		//$("#patient_search").val(form.title);
		//$("#end").val('');
	//} else {
		//loadevent();
	//}
	//var repeat_select = $("#repeat").val();
	//if (repeat_select != ''){
		//$("#until_row").show();
	//} else {
		//$("#until_row").hide();
		//$("#until").val('');
	//}
	//$("#delete_form").show();
	//$("#schedule_form select").selectmenu('refresh');
	$("#view_content").show();
	$("#content").hide();
	$("#edit_content").hide();
	$('html, body').animate({
		scrollTop: $("#view_content").offset().top
	});
	return false;

});

$(document).on('click', '.mobile_form_action2', function(e) {
	var form_id = $(this).attr('data-nosh-form');
	var action = $(this).attr('data-nosh-action');
	var refresh_url = $(this).attr('data-nosh-origin');
	if (refresh_url == 'mobile_schedule') {
		var start_date = $("#start_date").val();
		var end = $("#end").val();
		var visit_type = $("#visit_type").val();
		var pid = $("#pid").val();
		if (pid == '') {
			var reason = $("#reason").val();
			$("#title").val(reason);
		}
		if ($("#repeat").val() != '' && $("#event_id").val() != '' && $("#event_id").val().indexOf("R") === -1) {
			var event_id = $("#event_id").val();
			$("#event_id").val("N" + event_id);
		}
		if ($("#repeat").val() == '' && $("#event_id").val() != '' && $("#event_id").val().indexOf("R") !== -1) {
			var event_id1 = $("#event_id").val();
			$("#event_id").val("N" + event_id1);
		}
		var str = $("#"+form_id).serialize();
		if (visit_type == '' || visit_type == null && end == '') {
			toastr.error("No visit type or end time selected!");
		} else {
			$.mobile.loading("show");
			$.ajax({
				type: "POST",
				url: "ajaxschedule/edit-event",
				data: str,
				success: function(data){
					open_schedule(start_date);
					$("#"+form_id).clearForm();
					$("#edit_content").hide();
					$("#content").show();
					$.mobile.loading("hide");
				}
			});
		}
	}
	if (refresh_url == 'mobile_inbox') {
		if (action == 'save') {
			var bValid = true;
			$("#"+form_id).find("[required]").each(function() {
				var input_id = $(this).attr('id');
				var id1 = $("#" + input_id);
				var text = $("label[for='" + input_id + "']").html();
				bValid = bValid && checkEmpty(id1, text);
			});
			if (bValid) {
				$.mobile.loading("show");
				var str = $("#"+form_id).serialize();
				$.ajax({
					type: "POST",
					url: "ajaxmessaging/send-message",
					data: str,
					success: function(data){
						toastr.success(data);
						$("#"+form_id).clearForm();
						$("#edit_content").hide();
						$("#content").show();
						$.mobile.loading("hide");
					}
				});
			}
		}
		if (action == 'draft') {
			var str = $("#"+form_id).serialize();
			$.ajax({
				type: "POST",
				url: "ajaxmessaging/draft-message",
				data: str,
				success: function(data){
					toastr.success(data);
					$("#"+form_id).clearForm();
					$("#edit_content").hide();
					$("#content").show();
					$.mobile.loading("hide");
				}
			});
		}
	}
	// more stuff
	$("#edit_content").hide();
	$("#content").show();
});
$(document).on("click", ".mobile_paste", function(e) {
	var value = $(this).attr('data-nosh-value');
	var to = $(this).attr('data-nosh-paste-to');
	$('#'+to).val(value);
	$('input[data-type="search"]').val("");
	$('input[data-type="search"]').trigger("keyup");
});
$(document).on("click", ".mobile_paste1", function(e) {
	var form = {};
	form.rxl_medication = $(this).attr('data-nosh-med');
	form.rxl_dosage = $(this).attr('data-nosh-value');
	form.rxl_dosage_unit = $(this).attr('data-nosh-unit');
	form.rxl_ndcid = $(this).attr('data-nosh-ndc');
	$.each(form, function(key, value){
		if (value != 'undefined') {
			$('#'+key).val(value);
		}
	});
	$('input[data-type="search"]').val("");
	$('input[data-type="search"]').trigger("keyup");
});
$(document).on("click", ".mobile_paste2", function(e) {
	var value = $(this).attr('data-nosh-value');
	var to = $("#form_item").val();
	$('#'+to).val(value);
	if (to == 'patient_search') {
		var id = $(this).attr('data-nosh-id');
		$("#schedule_pid").val(id);
		$("#schedule_title").val(value);
	}
	$("#right_panel").panel('close');
	$("#"+to).focus();
});
$(document).on("click", ".mobile_paste3", function(e) {
	var form = {};
	form.sup_supplement = $(this).attr('data-nosh-value');
	form.sup_dosage = $(this).attr('data-nosh-dosage');
	form.sup_dosage_unit = $(this).attr('data-nosh-dosage-unit');
	form.supplement_id = $(this).attr('data-nosh-supplement-id');
	$.each(form, function(key, value){
		if (value != 'undefined') {
			$('#'+key).val(value);
		}
	});
	$('input[data-type="search"]').val("");
	$('input[data-type="search"]').trigger("keyup");
});
$(document).on("click", ".mobile_paste4", function(e) {
	var value = $(this).attr('data-nosh-value');
	var to = $(this).attr('data-nosh-paste-to');
	var cvx = $(this).attr('data-nosh-cvx');
	$('#'+to).val(value);
	$('#imm_cvxcode').val(cvx);
	$('input[data-type="search"]').val("");
	$('input[data-type="search"]').trigger("keyup");
});
$(document).on("click", ".return_button", function(e) {
	$("#right_panel").panel('close');
});
$(document).on("click", "input", function(e) {
	if ($(this).hasClass('texthelper')) {
		var id = $(this).attr('id');
		$("#form_item").val(id);
		$("#navigation_header_fav").show();
	} else {
		$("#navigation_header_fav").hide();
	}
});
$(document).on('keydown', '.texthelper', function(e){
	var value = $(this).val();
	var input = $(this).attr('id');
	if (value && value.length > 1) {
		$("#form_item").val(input);
		var $ul = $("#search_results");
		var html = "";
		var parts = input.split('_');
		if (parts[0] == 'rxl') {
			var url = "../ajaxsearch/rx-search/" + input + "/true";
		}
		if (parts[0] == 'sup') {
			var url = "../ajaxsearch/sup-"+ parts[1];
		}
		if (parts[0] == 'allergies') {
			var url = "../ajaxsearch/reaction/true";
		}
		$.mobile.loading("show");
		$.ajax({
			url: url,
			dataType: "json",
			type: "POST",
			data: "term=" + value
		})
		.then(function(response) {
			if (response.response == 'true') {
				$.each(response.message, function ( i, val ) {
					if (val.value != null) {
						html += '<li><a href=# class="ui-btn ui-btn-icon-left ui-icon-carat-l mobile_paste2" data-nosh-value="' + val.value +'">' + val.label + '</a></li>';
					}
				});
				$ul.html(html);
				$ul.listview("refresh");
				$ul.trigger("updatelayout");
				$.mobile.loading("hide");
				$("#right_panel").panel('open');
			} else {
				$.mobile.loading("hide");
			}
		});
	}
});

$(document).on("click", "#nosh_fab", function(e) {
	$(".nosh_fab_child").toggle('fade');
	return false;
});
$(document).on("click", "#nosh_fab1", function(e) {
	$("#view_content").hide();
	$("#content").hide();
	$("#edit_content").show();
	return false;
});
$(document).on("change", "#provider_list2", function(e) {
	var id = $('#provider_list2').val();
	if(id){
		$.ajax({
			type: "POST",
			url: "ajaxschedule/set-provider",
			data: "id=" + id,
			success: function(data){
				$("#visit_type").removeOption(/./);
				$.ajax({
					url: "ajaxsearch/visit-types/" + id,
					dataType: "json",
					type: "POST",
					async: false,
					success: function(data){
						if (data.response == 'true') {
							$("#visit_type").addOption(data.message, false);
						} else {
							$("#visit_type").addOption({"":"No visit types available."},false);
						}
					}
				});
			}
		});
	}
});
$(document).on("click", ".cd-read-more", function(e) {
	$.mobile.loading("show");
	var type = $(this).attr('data-nosh-type');
	var value = $(this).attr('data-nosh-value');
	var status = $(this).attr('data-nosh-status');
	var scroll = $(this).closest('.cd-timeline-block').offset().top;
	var acl = false;
	if (noshdata.group_id == '2' || noshdata.group_id == '3') {
		acl = true;
	}
	if (type == 'eid') {
		if (status == 'Yes') {
			if (acl) {
				$("#content_inner_main").hide();
				$.ajax({
					type: "GET",
					url: "../ajaxchart/modal-view-mobile/" + value,
					success: function(data){
						$("#content_inner").html(data).trigger('create').show();
						$('#content_inner').find('h4').css('color','blue');
						$("#navigation_header_back").attr('data-nosh-origin', 'Chart');
						$("#navigation_header_back").attr('data-nosh-scroll', scroll);
						$("#chart_header").hide();
						$("#navigation_header").show();
						$("#left_panel").panel('close');
						$.mobile.loading("hide");
					}
				});
			} else {
				$("#content_inner_main").hide();
				$.ajax({
					type: "POST",
					url: "../ajaxcommon/opennotes",
					success: function(data){
						if (data == 'y') {
							$.ajax({
								type: "GET",
								url: "../ajaxcommon/modal-view2-mobile/" + value,
								success: function(data){
									$("#content_inner").html(data).trigger('create').show();
									$('#content_inner').find('h4').css('color','blue');
									$("#navigation_header_back").attr('data-nosh-origin', 'Chart');
									$("#navigation_header_back").attr('data-nosh-scroll', scroll);
									$("#chart_header").hide();
									$("#navigation_header").show();
									$("#left_panel").panel('close');
									$.mobile.loading("hide");
								}
							});
						} else {
							$toastr.error('You cannot view the encounter as your provider has not activated OpenNotes.');
							$.mobile.loading("hide");
							return false;
						}
					}
				});
			}
		} else {
			toastr.error('Encounter is not signed.  You cannot view it at this time.');
			$.mobile.loading("hide");
			return false;
		}
	} else if (type == 't_messages_id') {
		if (status == 'Yes') {
			if (acl) {
				$("#content_inner_main").hide();
				$.ajax({
					type: "GET",
					url: "../ajaxcommon/tmessages-view/" + value,
					success: function(data){
						$("#content_inner").html(data).trigger('create').show();
						$('#content_inner').find('strong').css('color','blue');
						$("#navigation_header_back").attr('data-nosh-origin', 'Chart');
						$("#navigation_header_back").attr('data-nosh-scroll', scroll);
						$("#chart_header").hide();
						$("#navigation_header").show();
						$("#left_panel").panel('close');
						$.mobile.loading("hide");
					}
				});
				//$("#message_view").load('ajaxcommon/tmessages-view/' + value);
				//$("#t_messages_id").val(value);
				//t_messages_tags();
				//$("#messages_view_dialog").dialog('open');
			} else {
				$("#content_inner_main").hide();
				$.ajax({
					type: "POST",
					url: "../ajaxcommon/opennotes",
					success: function(data){
						if (data == 'y') {
							$.ajax({
								type: "GET",
								url: "../ajaxcommon/tmessages-view/" + value,
								success: function(data){
									$("#content_inner").html(data).trigger('create').show();
									$('#content_inner').find('strong').css('color','blue');
									$("#navigation_header_back").attr('data-nosh-origin', 'Chart');
									$("#navigation_header_back").attr('data-nosh-scroll', scroll);
									$("#chart_header").hide();
									$("#navigation_header").show();
									$("#left_panel").panel('close');
									$.mobile.loading("hide");
								}
							});
							//$("#t_messages_id").val(value);
							//$("#messages_view_dialog").dialog('open');
						} else {
							toastr.error('You cannot view the message as your provider has not activated OpenNotes.');
							$.mobile.loading("hide");
							return false;
						}
					}
				});
			}
		} else {
			toastr.error('Message is not signed.  You cannot view it at this time.');
			$.mobile.loading("hide");
			return false;
		}
	}
});
$(document).on("click", ".messaging_tab", function(e) {
	var tab = $(this).attr('data-tab');
	open_messaging(tab);
	$("#edit_content").hide();
	$("#content").show();
});
$(document).on("click", ".back_message", function(e) {
	var tab = $(this).attr('data-origin');
	var id = $(this).attr('data-origin-id');
	open_messaging(tab);
	$("#view_content").hide();
	$("#edit_content").hide();
	$("#content").show();
	var scroll = parseInt($('.nosh_messaging_item[data-nosh-message-id="' + id + '"]').offset().top);
	$.mobile.silentScroll(scroll-70);
});
$(document).on("click", ".reply_message", function(e) {
	var form = {};
	$.each($(this).get(0).attributes, function(i, attr) {
		if (attr.name.indexOf("data-nosh-") == '0') {
			var field = attr.name.replace('data-nosh-','');
			field = field.replace(/-/g, '_');
			form[field] = attr.value;
			if (attr.value != 'undefined') {
				if (attr.value != 'null') {
					if (field != 'timestamp') {
						var value = attr.value;
						if (field == 'date') {
							value = moment(new Date(value)).format('YYYY-MM-DD');
						}
						$('input[name="' + field + '"]').val(value);
					}
				}
			}
		}
	});
	$.ajax({
		type: "POST",
		url: "ajaxmessaging/get-displayname",
		data: "id=" + form['message_from'],
		success: function(data){
			$('select[name="messages_to[]"]').val(data);
			$('select[name="messages_to[]"]').selectmenu('refresh');
			var subject = 'Re: ' + form['subject'];
			$('input[name="subject"]').val(subject);
			var newbody = '\n\n' + 'On ' + form['date'] + ', ' + data + ' wrote:\n---------------------------------\n' + form['body'];
			$('textarea[name="body"]').val(newbody).caret(0);
			$('textarea[name="body"]').focus();
			$("#view_content").hide();
			$("#content").hide();
			$("#edit_content").show();
		}
	});
});
$(document).on("click", ".reply_all_message", function(e) {
	var form = {};
	$.each($(this).get(0).attributes, function(i, attr) {
		if (attr.name.indexOf("data-nosh-") == '0') {
			var field = attr.name.replace('data-nosh-','');
			field = field.replace(/-/g, '_');
			form[field] = attr.value;
			if (attr.value != 'undefined') {
				if (attr.value != 'null') {
					if (field != 'timestamp') {
						var value = attr.value;
						if (field == 'date') {
							value = moment(new Date(value)).format('YYYY-MM-DD');
						}
						$('input[name="' + field + '"]').val(value);
					}
				}
			}
		}
	});
	if (form['cc'] == ''){
		$.ajax({
			type: "POST",
			url: "ajaxmessaging/get-displayname",
			data: "id=" + form['message_from'],
			success: function(data){
				$('select[name="messages_to[]"]').val(data);
				$('select[name="messages_to[]"]').selectmenu('refresh');
				var subject = 'Re: ' + form['subject'];
				$('input[name="subject"]').val(subject);
				var newbody = '\n\n' + 'On ' + form['date'] + ', ' + data + ' wrote:\n---------------------------------\n' + form['body'];
				$('textarea[name="body"]').val(newbody).caret(0);
				$('textarea[name="body"]').focus();
				$("#view_content").hide();
				$("#content").hide();
				$("#edit_content").show();
			}
		});
	} else {
		var to1 = to + ';' + cc;
		$.ajax({
			type: "POST",
			url: ".ajaxmessaging/get-displayname1",
			data: "id=" + form['message_from'] + ';' + form['cc'],
			success: function(data){
				var a_array = String(data).split(";");
				$('select[name="messages_to[]"]').val(a_array);
				$('select[name="messages_to[]"]').selectmenu('refresh');
				//var a_length = a_array.length;
				//for (var i = 0; i < a_length; i++) {
					//$('select[name="messages_to[]"]').selectOptions(a_array[i]);
				//}
				var subject = 'Re: ' + form['subject'];
				$('input[name="subject"]').val(subject);
				var newbody = '\n\n' + 'On ' + form['date'] + ', ' + data + ' wrote:\n---------------------------------\n' + form['body'];
				$('textarea[name="body"]').val(newbody).caret(0);
				$('textarea[name="body"]').focus();
				$("#view_content").hide();
				$("#content").hide();
				$("#edit_content").show();
			}
		});
	}
});
$(document).on("click", ".forward_message", function(e) {
	var form = {};
	$.each($(this).get(0).attributes, function(i, attr) {
		if (attr.name.indexOf("data-nosh-") == '0') {
			var field = attr.name.replace('data-nosh-','');
			field = field.replace(/-/g, '_');
			form[field] = attr.value;
			if (attr.value != 'undefined') {
				if (attr.value != 'null') {
					if (field != 'timestamp') {
						var value = attr.value;
						if (field == 'date') {
							value = moment(new Date(value)).format('YYYY-MM-DD');
						}
						$('input[name="' + field + '"]').val(value);
					}
				}
			}
		}
	});
	var rawtext = 'From:  ' + form['message_from_label'] + '\nDate: ' + form['date'] + '\nSubject: ' + form['subject'] + '\n\nMessage: ' + form['body'];
	var subject = 'Fwd: ' + form['subject'];
	$('input[name="subject"]').val(subject);
	var newbody = '\n\n' + 'On ' + form['date'] + ', ' + data + ' wrote:\n---------------------------------\n' + form['body'];
	$('input[name="body"]').val(newbody).caret(0);
	$('input[name="messages_to"]').focus();
	$("#view_content").hide();
	$("#content").hide();
	$("#edit_content").show();
});

$(document).on("click", ".template_click", function(e) {
	$.mobile.loading("show");
	//var id = $(this).prev().attr('id');
	//console.log(id);
	var id = 'hpi';
	$.mobile.loading("show");
	$.ajax({
		url: "ajaxsearch/textdump-group/" + id,
		type: "POST"
	})
	.then(function(response) {
		$("#textdump_group_html").html('');
		$("#textdump_group_html").append(response);
		$("#textdump_group_html").children().css({"padding":"6px"});
		$("#textdump_group_html").children().not(':last-child').css({"border-width":"2px","border-bottom":"2px black solid"});
		$(".edittextgroup").html('<i class="zmdi zmdi-edit"></i>').addClass('ui-btn ui-btn-inline');
		$(".deletetextgroup").html('<i class="zmdi zmdi-delete"></i>').addClass('ui-btn ui-btn-inline');
		$(".normaltextgroup").each(function(){
			$item = $(this);
			$nextdiv = $(this).parent().next();
			$($item).next('label').html('ALL NORMAL').css('color','blue').andSelf().wrapAll('<fieldset data-role="controlgroup" data-type="horizontal" data-mini="true"></fieldset>').parent().prependTo($nextdiv);
		});
		$(".restricttextgroup").html('<i class="zmdi zmdi-close"></i>').addClass('ui-btn ui-btn-inline');
		$("#textdump_group_target").val(id);
		$('#textdump_group_html_div').css('overflow-y', 'scroll');
		$.mobile.loading("hide");
		$("#textdump_group_html").trigger('create');
		$('#textdump_group_html_div').popup('open');
	});
});
$('#textdump_group_html_div').on({
  popupbeforeposition: function() {
    var maxHeight = $(window).height() - 30;
    $('#textdump_group_html_div').css('max-height', maxHeight + 'px');
  }
});
$(document).on('click', '.textdump_group_item', function(){
	$.mobile.loading("show");
	var id = $("#textdump_group_target").val();
	var group = $(this).text();
	$("#textdump_group_item").val(group);
	var id1 = $(this).attr('id');
	$("#textdump_group_id").val(id1);
	$.ajax({
		type: "POST",
		url: "ajaxsearch/textdump/" + id,
		data: 'group='+group
	})
	.then(function(response) {
		$("#textdump_html").html('');
		$("#textdump_html").append(response);
		$("#textdump_html").children().css({"padding":"6px"});
		$("#textdump_html").children().not(':last-child').css({"border-width":"2px","border-bottom":"2px black solid"});
		$(".edittexttemplate").html('<i class="zmdi zmdi-edit"></i>').addClass('ui-btn ui-btn-inline');
		$(".deletetexttemplate").html('<i class="zmdi zmdi-delete"></i>').addClass('ui-btn ui-btn-inline');
		$(".normaltexttemplate").each(function(){
			$item = $(this);
			$nextdiv = $(this).parent();
			$($item).next('label').html('DEFAULT').css('color','blue').andSelf().wrapAll('<fieldset data-role="controlgroup"  data-type="horizontal"  data-mini="true"></fieldset>').parent().prependTo($nextdiv);
		});
		// $(".normaltexttemplate").button({text: false, icons: {primary: "ui-icon-check"}});
		// $('.textdump_item_text').editable('destroy');
		// $('.textdump_item_text').editable({
		// 	toggle:'manual',
		// 	ajaxOptions: {
		// 		headers: {"cache-control":"no-cache"},
		// 		beforeSend: function(request) {
		// 			return request.setRequestHeader("X-CSRF-Token", $("meta[name='token']").attr('content'));
		// 		},
		// 		error: function(xhr) {
		// 			if (xhr.status == "404" ) {
		// 				alert("Route not found!");
		// 				//window.location.replace(noshdata.error);
		// 			} else {
		// 				if(xhr.responseText){
		// 					var response1 = $.parseJSON(xhr.responseText);
		// 					var error = "Error:\nType: " + response1.error.type + "\nMessage: " + response1.error.message + "\nFile: " + response1.error.file;
		// 					alert(error);
		// 				}
		// 			}
		// 		}
		// 	}
		// });
		$("#textdump_target").val(id);
		$('#textdump_html_div').css('overflow-y', 'scroll');
		$.mobile.loading("hide");
		$("#textdump_html").trigger('create');
		$('#textdump_group_html_div').popup('close');
		$('#textdump_html_div').popup('open');
	});
});
$('#textdump_html_div').on({
  popupbeforeposition: function() {
    var maxHeight = $(window).height() - 30;
    $('#textdump_html_div').css('max-height', maxHeight + 'px');
  }
});
