@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        @if (isset($template_content))
            <div class="col-md-8">
        @else
            <div>
        @endif
        <!-- <div class="col-md-10 col-md-offset-1"> -->
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left" style="padding-top: 7.5px;">{!! $panel_header !!}</h3>
                    @if (isset($panel_dropdown))
                        <div class="pull-right">
                            {!! $panel_dropdown !!}
                        </div>
                    @endif
                </div>
                <div class="panel-body">
                    @if (isset($search_patient1))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_patient1_form" role="search" action="{{ url('search_patient') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_patient1_results" data-nosh-search-to="{{ $search_patient1 }}">
                                <input type="text" class="form-control search" id="search_patient1" name="search_patient" placeholder="Search Patient" style="margin-bottom:0px;" autocomplete="off">
                                <input type="hidden" name="type" value="li">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_patient1_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_patient1_results"></div>
                        </div>
                    @endif
                    @if (isset($search_rx))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_rx_form" role="search" action="{{ url('search_rx') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_rx_results" data-nosh-search-to="{{ $search_rx }}">
                                <input type="text" class="form-control search nosh-typeahead" id="search_rx" name="search_rx" placeholder="Search RX" style="margin-bottom:0px;" required data-provide="typeahead" autocomplete="off" data-nosh-typeahead="{{ url('rx_json')}}">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_rx_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_rx_results"></div>
                        </div>
                    @endif
                    @if (isset($search_icd))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_icd_form" role="search" action="{{ url('search_icd') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_icd_results" data-nosh-search-to="{{ $search_icd }}">
                                <input type="text" class="form-control search" id="search_icd" name="search_icd" placeholder="Search ICD10 for Dx" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_icd_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_icd_results"></div>
                        </div>
                    @endif
                    @if (isset($search_cpt))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_cpt_form" role="search" action="{{ url('search_cpt') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_cpt_results" data-nosh-search-to="{{ $search_cpt }}">
                                <input type="text" class="form-control search" id="search_cpt" name="search_cpt" placeholder="Search CPT" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-favorite" value="Go"><i class="glyphicon glyphicon-star"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_icd_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_cpt_results"></div>
                        </div>
                    @endif
                    @if (isset($search_supplement))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_supplement_form" role="search" action="{{ url('search_supplement') . '/' . $search_supplement_option }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_supplement_results" data-nosh-search-to="{{ $search_supplement }}">
                                <input type="text" class="form-control search" id="search_supplement" name="search_supplement" placeholder="Search supplement" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_supplement_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_supplement_results"></div>
                        </div>
                    @endif
                    @if (isset($search_immunization))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_immunization_form" role="search" action="{{ url('search_immunization') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_immunization_results" data-nosh-search-to="{{ $search_immunization }}">
                                <input type="text" class="form-control search" id="search_immunization" name="search_immunization" placeholder="Search Immunization" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_immunization_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_immunization_results"></div>
                        </div>
                    @endif
                    @if (isset($search_immunization_inventory))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_immunization_inventory_form" role="search" action="{{ url('search_immunization_inventory') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_immunization_inventory_results" data-nosh-search-to="{{ $search_immunization_inventory }}">
                                <input type="text" class="form-control search" id="search_immunization_inventory" name="search_immunization_inventory" placeholder="Search Vaccine Inventory" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_immunization_inventory_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_immunization_inventory_results"></div>
                        </div>
                    @endif
                    @if (isset($search_insurance))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_insurance_form" role="search" action="{{ url('search_insurance') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_insurance_results" data-nosh-search-to="{{ $search_insurance }}">
                                <input type="text" class="form-control search" id="search_insurance" name="search_insurance" placeholder="Search insurance" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_insurance_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_insurance_results"></div>
                        </div>
                    @endif
                    @if (isset($search_loinc))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_loinc_form" role="search" action="{{ url('search_loinc') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_loinc_results" data-nosh-search-to="{{ $search_loinc }}">
                                <input type="text" class="form-control search" id="search_loinc" name="search_loinc" placeholder="Search LOINC for Tests" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_loinc_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_loinc_results"></div>
                        </div>
                    @endif
                    @if (isset($search_address))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_address_form" role="search" action="{{ url('search_address') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_address_results" data-nosh-search-to="{{ $search_address }}">
                                <input type="text" class="form-control search" id="search_address" name="search_address" placeholder="Search Address Book" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_address_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_address_results"></div>
                        </div>
                    @endif
                    @if (isset($search_specialty))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_specialty_form" role="search" action="{{ url('search_specialty') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_specialty_results" data-nosh-search-to="{{ $search_specialty }}">
                                <input type="text" class="form-control search" id="search_specialty" name="search_specialty" placeholder="Search specialty" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_specialty_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_specialty_results"></div>
                        </div>
                    @endif
                    @if (isset($search_healthwise))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_healthwise_form" role="search" action="{{ url('search_healthwise') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_healthwise_results" data-nosh-search-to="{{ $search_healthwise }}">
                                <input type="text" class="form-control search" id="search_healthwise" name="search_healthwise" placeholder="Search Patient Education Materials" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_healthwise_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_healthwise_results"></div>
                        </div>
                    @endif
                    @if (isset($search_language))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_language_form" role="search" action="{{ url('search_language') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_language_results" data-nosh-search-to="{{ $search_language }}">
                                <input type="text" class="form-control search" id="search_language" name="search_language" placeholder="Search Language" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_language_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_language_results"></div>
                        </div>
                    @endif
                    @if (isset($search_guardian))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_guardian_form" role="search" action="{{ url('search_guardian') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_guardian_results" data-nosh-search-to="{{ $search_guardian }}">
                                <input type="text" class="form-control search" id="search_guardian" name="search_guardian" placeholder="Search Guardian Role" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_guardian_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_guardian_results"></div>
                        </div>
                    @endif
                    @if (isset($search_imaging))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_imaging_form" role="search" action="{{ url('search_imaging') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_imaging_results" data-nosh-search-to="{{ $search_imaging }}">
                                <input type="text" class="form-control search" id="search_imaging" name="search_imaging" placeholder="Search Imaging Studies" style="margin-bottom:0px;" autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md nosh-search-clear" value="Go"><i class="glyphicon glyphicon-remove"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_imaging_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_imaging_results"></div>
                        </div>
                    @endif
                    @if ($errors->has('quantity'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('quantity') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('quantity1'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('quantity1') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('temp'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('temp') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('sales_tax'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('sales_tax') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('schedule_increment'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('schedule_increment') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('email'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('email') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('sms'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('sms') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('username'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('username') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('tryagain'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('tryagain') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('password'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('password') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('confirm_password'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('confirm_password') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('url'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('url') }}</strong>
                        </span>
                    </div>
                    @endif
                    {!! $content !!}
                </div>
            </div>
        </div>
        @if (isset($template_content))
            <div class="col-md-4">
                <div class="panel">
                    <div id="template_panel" class="panel-heading clearfix">
                        <h3 id="template_header_text" class="panel-title pull-left" style="padding-top: 7.5px;">
                            Templates
                            @if (isset($template_header))
                                - {!! $template_header !!}
                            @endif
                        </h3>
                        <div class="pull-right">
                            <a href="#" class="btn btn-primary btn-sm" role="button" id="template_back"><i class="fa fa-btn fa-chevron-left"></i> <span id="template_back_text">OK</span></a>
                        </div>
                    </div>
                    <div class="panel-body hidden" id="template_panel_body">
                        <ul class="list-group">
                            <li class="list-group-item">
                                <div class="input-group">
                                    <span class="input-group-addon">Delimiter:</span>
                                    <select class="form-control" id="template_delimiter">
                                        <option value="&#13;&#10;">new line</option>
                                        <option value=", ">comma (,)</option>
                                        <option value=" ">space</option>
                                        <option value="  ">double space</option>
                                        <option value="; ">semi-colon (;)</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-md" id="template-add" value="Add" title="" data-toggle="tooltip"><i class="fa fa-plus fa-lg"></i></button>
                                    </span>
                                    <span class="input-group-btn" id="template-all-items-span" style="display:none;">
                                        <button type="button" class="btn btn-md" id="template-all-items" value="All" title="Select all items" data-toggle="tooltip"><i class="fa fa-list fa-lg"></i></button>
                                    </span>
                                    <input type="hidden" id="template_target" value="">
                                    <input type="hidden" id="template_group" value="">
                                </div>
                            </li>
                        </ul>
                        <form role="form"><div class="form-group"><input class="form-control" id="searchinput_template" type="search" placeholder="Filter Results..." /></div>
                            <ul id="template_list" class="list-group searchlist_template"></ul>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
    var availableMonths = ["January","February","March","April","May","June","July","August","September","October","November","December"];
    $(function () {
        var activeTab = $('[href="' + location.hash + '"]');
        if (activeTab) {
            activeTab.tab('show');
        }
    });
    $(document).ready(function() {
        // Core
        if (noshdata.message_action !== '') {
            if (noshdata.message_action.search('Error - ') == -1) {
                toastr.success(noshdata.message_action);
            } else {
                toastr.error(noshdata.message_action);
            }
        }
        $('[data-toggle="tab"]').tooltip({
            trigger: 'hover',
            placement: 'top',
            animate: true,
            delay: 500,
            container: 'body'
        });
        if (noshdata.patient_url == window.location.href) {
            $('#search_patient').focus();
        }
        $('.searchlist').btsListFilter('#searchinput', {initial: false});
        $('[data-toggle=offcanvas]').css('cursor', 'pointer').click(function() {
            $('.row-offcanvas').toggleClass('active');
        });
        $('.nav-tabs a').on('shown.bs.tab', function(event){
            var hash = $(event.target).attr('href');
            hash = hash.replace('#', '/');
            $.ajax({
                type: 'GET',
                url: noshdata.last_page + hash,
            });
        });
        if (noshdata.print_now !== '') {
            window.location = noshdata.print_now;
        }
        $('#url').attr('title', 'Copy the link (URL) from your email or SMS that you received from your patient').attr('data-toggle', 'tooltip');
        // ERA reconciliation
        $('#pid').change(function() {
            var a = $(this).val();
            if (a !== '') {
                $('#eid').removeOption(/./);
                $('#eid').addOption({'':'Select Encounter'}, false);
                $.ajax({
                    type: 'POST',
                    url: noshdata.search_encounters,
                    data: 'pid=' + a,
                    dataType: 'json',
                    success: function(data){
                        if (data.response == 'y') {
                            $('#eid').addOption(data.options,false);
                        }
                    }
                });
            }
        });
        // Super query
        function superquery() {
            $('.search_desc_class').css('cursor', 'pointer').click(function(){
                var a = $(this).val();
                var a1 = $(this).attr('id').split("_");
                var b = $('#search_field_' + a1[2]).val();
                if (b == "billing") {
                    $.ajax({
                        type: 'POST',
                        url: noshdata.search_cpt_billing,
                        dataType: 'json',
                        encode: true
                    }).done(function(response) {
                        $('#search_desc_' + a1[2]).typeahead({
                            source: response,
                            afterSelect: function(val) {
                                $('#search_desc_' + a1[2]).val(val);
                            }
                        });
                        $('#search_desc_' + a1[2]).removeClass('loading');
                        $('#search_desc_' + a1[2]).prop('disabled', false);
                    });
                }
                if (b == "month") {
                    $('#search_desc_' + a1[2]).typeahead({
                        source: availableMonths,
                        afterSelect: function(val) {
                            $('#search_desc_' + a1[2]).val(val);
                        }
                    });
                }
            });
            $('.search_field_class').change(function(){
                var a = $(this).val();
                // console.log(a);
                var a1 = $(this).attr('id').split("_");
                if (a == "age") {
                    $('#search_op_' + a1[2]).removeOption(/./);
                    $('#search_op_' + a1[2]).addOption({"":"Select Operator","less than":"is less than","equal":"is equal to","greater than":"is greater than","contains":"contains","not equal":"is not equal to"},false);
                    $('#search_desc_' + a1[2]).val("");
                }
                if (a == "issue" || a == "rxl_medication" || a == "imm_immunization" || a == "insurance" || a == "sup_supplement" || a == "zip" || a == "city") {
                    $('#search_op_' + a1[2]).removeOption(/./);
                    $('#search_op_' + a1[2]).addOption({"":"Select Operator","equal":"is equal to","contains":"contains","not equal":"is not equal to"},false);
                    $('#search_desc_' + a1[2]).val("");
                }
                if (a == "billing") {
                    $('#search_op_' + a1[2]).removeOption(/./);
                    $('#search_op_' + a1[2]).addOption({"":"Select Operator","equal":"is equal to","not equal":"is not equal to"},false);
                    $('#search_desc_' + a1[2]).val("");
                    $('#search_desc_' + a1[2]).addClass('loading');
                    $('#search_desc_' + a1[2]).prop('disabled', true);
                    $('#search_desc_' + a1[2]).typeahead('destroy');
                    $.ajax({
                        type: 'POST',
                        url: noshdata.search_cpt_billing,
                        dataType: 'json',
                        encode: true
                    }).done(function(response) {
                        $('#search_desc_' + a1[2]).typeahead({
                            source: response,
                            afterSelect: function(val) {
                                $('#search_desc_' + a1[2]).val(val);
                            }
                        });
                        $('#search_desc_' + a1[2]).removeClass('loading');
                        $('#search_desc_' + a1[2]).prop('disabled', false);
                    });
                }
                if (a == "month") {
                    $('#search_op_' + a1[2]).removeOption(/./);
                    $('#search_op_' + a1[2]).addOption({"":"Select Operator","equal":"is equal to","not equal":"is not equal to"},false);
                    $('#search_desc_' + a1[2]).val("");
                    $('#search_desc_' + a1[2]).typeahead('destroy');
                    $('#search_desc_' + a1[2]).typeahead({
                        source: availableMonths,
                        afterSelect: function(val) {
                            $('#search_desc_' + a1[2]).val(val);
                        }
                    });
                }
            });
            $('.search_op_class').change(function(){
                var a = $(this).val();
                var a1 = $(this).attr('id').split("_");
                if (a == "between") {
                    $('#search_desc_' + a1[2]).val(" AND ");
                }
            });
            $('.search_remove').css('cursor', 'pointer').on('click', function(event){
                $(this).parent().remove();
            });
        }
        superquery();
        $('#search_add').css('cursor', 'pointer').click(function() {
            var a = $('#super_query_div > :last-child').attr("id");
            var a1 = a.split("_");
            var count = parseInt(a1[2]) + 1;
            $('#super_query_div').append('<br><div class="input-group" id="search_div_' + count + '"><span class="input-group-addon search_remove"><i class="fa fa-trash fa-lg"></i></span><select name="search_join[]" id="search_join_' + count + '" class="form-control search_join_class"></select><select name="search_field[]" id="search_field_' + count + '" class="form-control search_field_class"></select><select name="search_op[]" id="search_op_' + count + '" class="form-control search_op_class"></select><input type="text" name="search_desc[]" id="search_desc_' + count + '"  class="form-control search_desc_class"></input></div>');
            $('#search_field_' + count).addOption({"":"Select Field","age":"Patient's age","insurance":"Patient's primary insurance","issue":"Patient's active medical issue list","billing":"Patient's billing code","rxl_medication":"Patient's active medication list","imm_immunization":"Patient's immunization list","sup_supplement":"Patient's active supplement list","zip":"Zip code where patient resides","city":"City where patient resides","month":"Patient's birth month"},false);
            $('#search_op_' + count).addOption({"":"Select Operator"},false);
            $('#search_join_' + count).addOption({"AND":"And (&)","OR":"Or (||)"},false);
            superquery();
        });
        // Messaging
        if ($('#message_read_id').length) {
            setTimeout(function() {
                var id = $('#message_read_id').attr('nosh-data-id');
                $.ajax({
                    type: "POST",
                    url: noshdata.read_message,
                    data: 'id=' + id,
                    success: function(data){
                        toastr.success(data);
                    }
                });
            }, 3000);
        }
        // Signature
        if ($('#signature_form').length) {
            $('#signature_form').signaturePad({drawOnly:true});
        }
        // Schedule admin
        if ($("#timezone").val() === '') {
            var tz = jstz.determine();
            $("#timezone").val(tz.name());
            toastr.success('Timezone not set. Automatically set based on your browser location');
        }
    });
</script>
@endsection
