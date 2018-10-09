@extends('layouts.app')

@section('view.stylesheet')
<style>
    .pediatric-immunizations-schedule .received {
        background-color: lightgreen;
    }
    .pediatric-immunizations-schedule .due {
        background-color: lightyellow;
    }
    .pediatric-immunizations-schedule .main-cell {
        font-weight: bold;
        width: 10%;
    }
    .pediatric-immunizations-schedule td {
        text-align: center;
    }
    .pediatric-immunizations-schedule td .recommended-age {
        display: block;
        font-size: .8em;
    }
</style>
@endsection

@section('content')
@if (isset($sidebar_content))
    <div class="container-fluid">
@else
    <div class="container">
@endif
    <div class="row">
        @if (isset($template_content) || isset($dosage_calculator))
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
                    @if (isset($search_rx))
                        <div class="container-fluid panel-container">
                            <form class="input-group form" border="0" id="search_rx_form" role="search" action="{{ url('search_rx') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_rx_results" data-nosh-search-to="{{ $search_rx }}">
                                <input type="text" class="form-control search nosh-typeahead" id="search_rx" name="search_rx" placeholder="{{ trans('nosh.search_rx') }}" style="margin-bottom:0px;" required data-provide="typeahead" autocomplete="off" data-nosh-typeahead="{{ url('rx_json')}}">
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
                                <input type="text" class="form-control search" id="search_icd" name="search_icd" placeholder="{{ trans('nosh.search_icd') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_cpt" name="search_cpt" placeholder="{{ trans('nosh.search_cpt') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_supplement" name="search_supplement" placeholder="{{ trans('nosh.search_supplement') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_immunization" name="search_immunization" placeholder="{{ trans('nosh.search_immunization') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_immunization_inventory" name="search_immunization_inventory" placeholder="{{ trans('nosh.search_immunization_inventory') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_insurance" name="search_insurance" placeholder="{{ trans('nosh.search_insurance') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_loinc" name="search_loinc" placeholder="{{ trans('nosh.search_loinc') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_address" name="search_address" placeholder="{{ trans('nosh.search_address') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_specialty" name="search_specialty" placeholder="{{ trans('nosh.search_specialty') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_healthwise" name="search_healthwise" placeholder="{{ trans('nosh.search_healthwise') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_language" name="search_language" placeholder="{{ trans('nosh.search_language') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_guardian" name="search_guardian" placeholder="{{ trans('nosh.search_guardian') }}" style="margin-bottom:0px;" autocomplete="off">
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
                                <input type="text" class="form-control search" id="search_imaging" name="search_imaging" placeholder="{{ trans('nosh.search_imaging') }}" style="margin-bottom:0px;" autocomplete="off">
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
                    @if ($errors->has('address_id'))
                        <div class="form-group has-error">
                            <span class="help-block has-error">
                                <strong>Select a valid recipient from the address book in the search box above or create one first.</strong>
                            </span>
                        </div>
                    @endif
                    @if ($errors->has('payment'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('payment') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('cpt_charge'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('cpt_charge') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('amount'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('amount') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('quantity'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('quantity') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('rxl_days'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('rxl_days') }}</strong>
                        </span>
                    </div>
                    @endif
                    @if ($errors->has('rxl_refill'))
                    <div class="form-group has-error">
                        <span class="help-block has-error">
                            <strong>{{ $errors->first('rxl_refill') }}</strong>
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
                    {!! $content !!}
                </div>
            </div>
            @if (isset($goodrx))
                <div class="panel panel-default">
                    <div class="panel-heading clearfix">
                        <h3 class="panel-title pull-left" style="padding-top: 7.5px;">{{ trans('nosh.goodrx_header') }}</h3>
                    </div>
                    <div class="panel-body" id="goodrx_container">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-4">
                                    <div id="goodrx_compare-price_widget"></div>
                                </div>
                                <div class="col-md-4">
                                    <div id="goodrx_low-price_widget"></div>
                                </div>
                            </div>
                            @if ($link !== '')
                                <div class="row" style="margin-top: 10px;">
                                    <div class="col-md-6 col-md-offset-3">
                                        <a href="{!! $link !!}" class="btn btn-info btn-block nosh-no-load" target="_blank">
                                            <i class="fa fa-btn fa-forward"></i> {{ trans('nosh.goodrx_more') }}
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @if (isset($template_content))
            <div class="col-md-4">
                <div class="panel">
                    <div id="template_panel" class="panel-heading clearfix">
                        <div class="pull-left">
                            <a href="#" class="btn btn-primary btn-sm" role="button" id="template_back"><i class="fa fa-btn fa-chevron-left"></i> <span id="template_back_text">OK</span></a>
                        </div>
                        <h3 id="template_header_text" class="panel-title pull-right" style="padding-top: 7.5px;">
                            {{ trans('nosh.templates') }}
                            @if (isset($template_header))
                                - {!! $template_header !!}
                            @endif
                        </h3>
                    </div>
                    <div class="panel-body hidden" id="template_panel_body">
                        <ul class="list-group">
                            <li class="list-group-item">
                                <div class="input-group">
                                    <span class="input-group-addon">{{ trans('nosh.delimiter') }}</span>
                                    <select class="form-control" id="template_delimiter">
                                        <option value="&#13;&#10;">{{ trans('nosh.delimiter') }}</option>
                                        <option value=", ">{{ trans('nosh.delimiter_comma') }}</option>
                                        <option value=" ">{{ trans('nosh.delimiter_space') }}</option>
                                        <option value="  ">{{ trans('nosh.delimiter_double_space') }}</option>
                                        <option value="; ">{{ trans('nosh.delimiter_semi_colon') }}</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-md" id="template-add" value="Add" title="" data-toggle="tooltip"><i class="fa fa-plus fa-lg"></i></button>
                                    </span>
                                    <span class="input-group-btn" id="template-all-items-span" style="display:none;">
                                        <button type="button" class="btn btn-md" id="template-all-items" value="All" title="{{ trans('nosh.select_all_items') }}" data-toggle="tooltip"><i class="fa fa-list fa-lg"></i></button>
                                    </span>
                                    <input type="hidden" id="template_target" value="">
                                    <input type="hidden" id="template_group" value="">
                                </div>
                            </li>
                        </ul>
                        <form role="form"><div class="form-group"><input class="form-control" id="searchinput_template" type="search" placeholder="{{ trans('nosh.filter_results') }}" /></div>
                            <ul id="template_list" class="list-group searchlist_template"></ul>
                        </form>
                    </div>
                </div>
            </div>
        @endif
        @if (isset($dosage_calculator))
            <div class="col-md-4">
                <div class="panel">
                    <div id="dosage_panel" class="panel-heading clearfix">
                        <div class="pull-left">
                            <a href="#" class="btn btn-primary btn-sm" role="button" id="dosage_ok"><i class="fa fa-btn fa-chevron-left"></i> <span id="template_back_text">OK</span></a>
                        </div>
                        <h3 id="dosage_header_text" class="panel-title pull-right" style="padding-top: 7.5px;">{{ trans('nosh.dosage_calculator_header') }}</h3>
                    </div>
                    <div class="panel-body" id="dosage_panel_body">
                        <div class="row">
                            <div class="form-horizontal">
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_dosage">{{ trans('nosh.dosage') }}</label>
                                    <div class="col-sm-7">
                                        <input type="text" id="calc_dosage" class="form-control input-sm docalcblur">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_dosage_unit">{{ trans('nosh.dosage_unit') }}</label>
                                    <div class="col-sm-7">
                                        <select id="calc_dosage_unit" class="form-control input-sm docalc">
                                            <option value="1000|0|gm/kg">gm/kg/d</option>
                                            <option value="0.001|0|mcg/kg">mcg/kg/d</option>
                                            <option value="1|0|mg/kg" selected>mg/kg/d</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="email">{{ trans('nosh.weight') }}</label>
                                    <div class="col-sm-7">
                                        <p class="form-control-static"><span id="calc_weight_span">{{ $recent_weight }} {{ $weight_unit }}</span></p>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_frequency">{{ trans('nosh.frequency') }}</label>
                                    <div class="col-sm-7">
                                        <select id="calc_frequency" class="form-control input-sm docalc">
                                            <option value="1" selected>{{ trans('nosh.daily') }}</option>
                                            <option value="2">{{ trans('nosh.bid') }}</option>
                                            <option value="3">{{ trans('nosh.tid') }}</option>
                                            <option value="4">{{ trans('nosh.qid') }}</option>
                                            <option value="5">{{ trans('nosh.5xd') }}</option>
                                            <option value="6">{{ trans('nosh.6xd') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_med_amount">{{ trans('nosh.amount') }}</label>
                                    <div class="col-sm-7">
                                        <input type="text" id="calc_med_amount" class="form-control input-sm docalcblur">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_med_amount_unit">{{ trans('nosh.amount_unit') }}</label>
                                    <div class="col-sm-7">
                                        <select id="calc_med_amount_unit" class="form-control input-sm docalc">
                                            <option value="1000|0|gm">gm</option>
                                            <option value="0.001|0|mcg">mcg</option>
                                            <option value="1|0|mg" selected="">mg</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_med_volume">{{ trans('nosh.volume') }}</label>
                                    <div class="col-sm-7">
                                        <input type="text" id="calc_med_volume" class="form-control input-sm docalcblur">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_med_volume_unit">{{ trans('nosh.volume_unit') }}</label>
                                    <div class="col-sm-7">
                                        <select id="calc_med_volume_unit" class="form-control input-sm docalc">
                                            <option value="1000|0|L">L</option>
                                            <option value="1|0|mL" selected="">mL</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_dose">{{ trans('nosh.dose') }}</label>
                                    <div class="col-sm-7">
                                        <input type="text" id="calc_dose" readonly class="form-control input-sm">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_dose_unit">{{ trans('nosh.dosage_unit') }}</label>
                                    <div class="col-sm-7">
                                        <select id="calc_dose_unit" class="form-control input-sm docalc">
                                            <option value="1000|0|gm">gm</option>
                                            <option value="0.001|0|mcg">mcg</option>
                                            <option value="1|0|mg" selected="">mg</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_liquid_dose">{{ trans('nosh.liquid_dose') }}</label>
                                    <div class="col-sm-7">
                                        <input type="text" id="calc_liquid_dose" readonly class="form-control input-sm">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label col-sm-5" for="calc_liquid_dose_unit">{{ trans('nosh.liquid_dose_unit') }}</label>
                                    <div class="col-sm-7">
                                        <select id="calc_liquid_dose_unit" class="form-control input-sm docalc">
                                            <option value="1000|0|L">L</option>
                                            <option value="1|0|mL" selected="">mL</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
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
        $('.nav-tabs .nosh-encounter_tab').on('hide.bs.tab', function(event){
            var prev = $(event.target).attr('href').replace('#', '');
            var next = $(event.relatedTarget).attr('href').replace('#', '');
            var action = $('#' + prev + '_form').attr('action').slice(0, -1) + next;
            $('#' + prev + '_form').attr('action', action);
            $('#' + prev + '_form').submit();
        });

        // Encounter Details
        if ($('#encounter_provider').val() !== '') {
            var a = $('#encounter_provider').val();
            $('#encounter_type').removeOption(/./);
            $('#encounter_type').addOption({'':'{{ trans('nosh.choose_appt') }}'}, false);
            $.ajax({
                type: 'POST',
                url: noshdata.get_appointments,
                data: 'id=' + a,
                dataType: 'json',
                success: function(data){
                    $('#encounter_type').addOption(data,false);
                }
            });
        }
        $('#encounter_provider').change(function() {
            var a = $(this).val();
            if (a !== '') {
                $('#encounter_type').removeOption(/./);
                $('#encounter_type').addOption({'':'{{ trans('nosh.choose_appt') }}'}, false);
                $.ajax({
                    type: 'POST',
                    url: noshdata.get_appointments,
                    data: 'id=' + a,
                    dataType: 'json',
                    success: function(data){
                        $('#encounter_type').addOption(data,false);
                    }
                });
            }
        });
        $('#encounter_role').change(function(){
            if ($(this).val() == 'Consulting Provider' || $(this).val() == 'Referring Provider') {
                $('.referring_group').show();
            } else {
                $('.referring_group').hide().val('');
            }
        });

        // Medications
        $('#rxl_medication').blur(function() {
            var medication = $('#rxl_medication').val();
            var rxcui = '';
            $.ajax({
                type: 'POST',
                url: noshdata.search_interactions,
                data: 'rxl_medication=' + medication + '&rxcui' + rxcui,
                dataType: 'json',
                success: function(data){
                    $('#warningModal_body').html(data.info);
                    var text_data = '<div class="col-md-2 col-md-offset-5"><button id="warning" class="btn btn-default btn-block">Click Here to Learn More</button></div>';
                    toastr.error(text_data, '{{ trans('nosh.medication_interactions') }}', {'timeOut':'20000','tapToDismiss':false,'preventDuplicates':true,"preventOpenDuplicates":true});
                    $('#warning').css('cursor', 'pointer').on('click', function(){
                        $('#warningModal').modal('show');
                    });
                }
            });
        });
        $('#rxl_days').blur(function() {
            if ($(this).val() !== '' && $('#rxl_sig').val() !== '' && $('#rxl_frequency').val() !== '') {
                var days = $(this).val();
                var amount = 0;
                var quantity = '';
                var unit = '';
                var one = [{!! trans('nosh.one') !!}];
                var two = [{!! trans('nosh.two') !!}];
                var three = [{!! trans('nosh.three') !!}];
                var four = [{!! trans('nosh.four') !!}];
                var five = [{!! trans('nosh.five') !!}];
                var six = [{!! trans('nosh.six') !!}];
                var eight = [{!! trans('nosh.eight') !!}];
                var twelve = [{!! trans('nosh.twelve') !!}];
                var exclude = [{!! trans('nosh.exclude') !!}];
                if ($.inArray($('#rxl_frequency').val().toLowerCase(), one) !== -1) {
                    amount = 1;
                }
                if ($.inArray($('#rxl_frequency').val().toLowerCase(), two) !== -1) {
                    amount = 2;
                }
                if ($.inArray($('#rxl_frequency').val().toLowerCase(), three) !== -1) {
                    amount = 3;
                }
                if ($.inArray($('#rxl_frequency').val().toLowerCase(), four) !== -1) {
                    amount = 4;
                }
                if ($.inArray($('#rxl_frequency').val().toLowerCase(), five) !== -1) {
                    amount = 5;
                }
                if ($.inArray($('#rxl_frequency').val().toLowerCase(), six) !== -1) {
                    amount = 6;
                }
                if ($.inArray($('#rxl_frequency').val().toLowerCase(), eight) !== -1) {
                    amount = 8;
                }
                if ($.inArray($('#rxl_frequency').val().toLowerCase(), twelve) !== -1) {
                    amount = 12;
                }
                if (amount !== 0) {
                    var sig_arr = $('#rxl_sig').val().split(' ');
                    if ($.isNumeric(sig_arr[0])) {
                        var sig = parseInt(sig_arr[0]);
                        if ($.inArray(sig_arr[1].toLowerCase(), exclude) === -1) {
                            unit = sig_arr[1];
                        }
                        quantity = sig * amount * days;
                        quantity = quantity + ' ' + unit;
                    }
                }
                $('#rxl_quantity').val(quantity);
            }
        });

        // Allergies
        $('#allergies_med').blur(function(){
            if ($(this).val() !== '') {
                $.ajax({
                    type: 'POST',
                    url: noshdata.rxnorm,
                    data: 'term=' + $(this).val(),
                    success: function(data){
                        $('#meds_ndcid').val(data);
                    }
                });
            }
        });

        // Demographics
        $('#creditcard_expiration').mask('99/9999');
        $('#test_reminder').css('cursor', 'pointer').click(function(){
            $.ajax({
                type: 'POST',
                url: noshdata.test_reminder,
                success: function(data){
                    if (data == '{{ trans('nosh.no_reminder') }}') {
                        toastr.error(data);
                    } else {
                        toastr.success(data);
                    }
                }
            });
        });
        $('#guardian_import').css('cursor', 'pointer').click(function(){
            $.ajax({
                type: 'POST',
                url: noshdata.copy_address,
                dataType: 'json',
                success: function(data){
                    $('#guardian_address').val(data.address);
                    $('#guardian_city').val(data.city);
                    $('#guardian_zip').val(data.zip);
                    $('#guardian_phone_home').val(data.phone_home);
                    $('#guardian_phone_cell').val(data.phone_cell);
                    $('#guardian_phone_work').val(data.phone_work);
                }
            });
        });
        $('#insurance_relationship').change(function(){
            if($('#insurance_relationship').val() == 'Self') {
                $.ajax({
                    type: 'POST',
                    url: noshdata.copy_address,
                    dataType: 'json',
                    success: function(data){
                        $('#insurance_insu_lastname').val(data.lastname);
                        $('#insurance_insu_firstname').val(data.firstname);
                        $('#insurance_insu_dob').val(data.DOB);
                        $('#insurance_insu_gender').val(data.sex);
                        $('#insurance_insu_address').val(data.address);
                        $('#insurance_insu_city').val(data.city);
                        $('#insurance_insu_state').val(data.state);
                        $('#insurance_insu_zip').val(data.zip);
                        if (data.phone_home !== '') {
                            $('#insurance_insu_phone').val(data.phone_home);
                        } else {
                            $('#insurance_insu_phone').val(data.phone_cell);
                        }
                    }
                });
            }
        });

        // Vital Signs
        $('.nosh-graph').css('cursor', 'pointer').click(function(){
            var type = $(this).attr('data-nosh-vitals-type');
            window.location = noshdata.vitals_graph + '/' + type;
        });
        $('#height').blur(function(){
            var w = $('#weight').val();
            var h = $('#height').val();
            if (noshdata.weight_unit == 'kg') {
                w = roundit(w/0.4536);
            }
            if (noshdata.height_unit == 'cm') {
                h = roundit(h/2.54);
            }
            if (w !== '' && h !== '' && w !== 0 && h !== 0) {
                var text = '';
                if ((w >= 500) || (h >= 120)) {
                    toastr.error('{{ trans('nosh.invalid_data') }}');
                } else {
                    var bmi = (Math.round((w * 703) / (h * h)));
                    $('#BMI').val(bmi);
                    if (parseInt(noshdata.agealldays) <= 6574.5) {
                        text = bmi + ' kg/m2';
                    } else {
                        if (bmi < 19) {
                            text += ' - {{ trans('nosh.underweight') }}';
                        }
                        if (bmi >=19 && bmi <=25) {
                            text += ' - {{ trans('nosh.desirable') }}';
                        }
                        if (bmi >=26 && bmi <=29) {
                            text += ' - {{ trans('nosh.prone_health_risks') }}';
                        }
                        if (bmi >=30 && bmi <=40) {
                            text += ' - {{ trans('nosh.obese') }}';
                        }
                        if (bmi >40){
                            text += ' - {{ trans('nosh.morbid_obese') }}';
                        }
                    }
                    var old = $('#vitals_other').val();
                    if (old === '') {
                        $('#vitals_other').val(text);
                    } else {
                        $('#vitals_other').val(old + '\n' + text);
                    }
                }
            }
        });
        $('#temp').blur(function(){
            var a = $('#temp').val();
            if (a !== '') {
                if (a > 100.4) {
                    $('#temp').css('color','red');
                } else {
                    $('#temp').css('color','black');
                }
                if (a > 106 || a < 93) {
                    toastr.error('{{ trans('nosh.invalid_temp') }}');
                    $('#temp').val('');
                    $('#temp').css('color','black');
                }
            }
        });
        $('#bp_systolic').blur(function(){
            var a = $('#bp_systolic').val();
            if (a !== '') {
                var n = a.search('/')
                if (n !== -1) {
                    var b = a.split('/');
                    $('#bp_systolic').val(b[0]);
                    a = b[0];
                    $('#bp_diastolic').val(b[1]);
                    if (b[1] > 90 || b[1] < 50) {
                        $('#bp_diastolic').css('color','red');
                    } else {
                        $('#bp_diastolic').css('color','black');
                    }
                    if (b[1] > 200 || b[1] < 30) {
                        toastr.error('{{ trans('nosh.invalid_value') }}');
                        $('#bp_diastolic').val('');
                        $('#bp_diastolic').css('color','black');
                    }
                }
                if (a > 140 || a < 80) {
                    $('#bp_systolic').css('color','red');
                } else {
                    $('#bp_systolic').css('color','black');
                }
                if (a > 250 || a < 50) {
                    toastr.error('{{ trans('nosh.invalid_value') }}');
                    $('#bp_systolic').val('');
                    $('#bp_systolic').css('color','black');
                }
            }
        });
        $('#bp_diastolic').blur(function(){
            var a = $('#bp_diastolic').val();
            if (a !== '') {
                var n = a.search('/')
                if (n !== -1) {
                    var b = a.split('/');
                    $('#bp_systolic').val(b[0]);
                    a = b[1];
                    $('#bp_diastolic').val(b[1]);
                    if (b[0] > 140 || b[0] < 80) {
                        $('#bp_systolic').css('color','red');
                    } else {
                        $('#bp_systolic').css('color','black');
                    }
                    if (b[0] > 250 || b[0] < 50) {
                        toastr.error('{{ trans('nosh.invalid_value') }}');
                        $('#bp_systolic').val('');
                        $('#bp_systolic').css('color','black');
                    }
                }
                if (a > 90 || a < 50) {
                    $('#bp_diastolic').css('color','red');
                } else {
                    $('#bp_diastolic').css('color','black');
                }
                if (a > 200 || a < 30) {
                    toastr.error('{{ trans('nosh.invalid_value') }}');
                    $('#bp_diastolic').val('');
                    $('#bp_diastolic').css('color','black');
                }
            }
        });
        $('#pulse').blur(function(){
            var a = $('#pulse').val();
            if (a !== '') {
                if (a > 140 || a < 50) {
                    $('#pulse').css('color','red');
                } else {
                    $('#pulse').css('color','black');
                }
                if (a > 250 || a < 30) {
                    toastr.error('{{ trans('nosh.invalid_value') }}');
                    $('#pulse').val('');
                    $('#pulse').css('color','black');
                }
            }
        });
        $('#respirations').blur(function(){
            var a = $('#respirations').val();
            if (a !== '') {
                if (a > 35 || a < 10) {
                    $('#respirations').css('color','red');
                } else {
                    $('#respirations').css('color','black');
                }
                if (a > 50 || a < 5) {
                    toastr.error('{{ trans('nosh.invalid_value') }}');
                    $('#respirations').val('');
                    $('#respirations').css('color','black');
                }
            }
        });
        $('#o2_sat').blur(function(){
            var a = $('#o2_sat').val();
            if (a !== '') {
                if (a < 90) {
                    $('#o2_sat').css('color','red');
                } else {
                    $('#o2_sat').css('color','black');
                }
                if (a > 100 || a < 50) {
                    toastr.error('{{ trans('nosh.invalid_value') }}');
                    $('#o2_sat').val('');
                    $('#o2_sat').css('color','black');
                }
            }
        });

        // Billing
        $('#cpt').blur(function(){
            if ($(this).val() !== '') {
                $.ajax({
                    type: 'POST',
                    url: noshdata.check_cpt,
                    data: 'cpt=' + $(this).val(),
                    success: function(data){
                        if (data == 'y') {
                            var text_data = '<div class="col-md-2 col-md-offset-5"><button id="add_new_cpt" class="btn btn-default btn-block">{{ trans('nosh.button_yes') }}</button></div>';
                            toastr.success(text_data, '{{ trans('nosh.new_procedure_code') }}', {"timeOut":"20000","preventDuplicates":true});
                            noshdata.toastr_collide = '1';
                        }
                    }
                });
            }
        });
        if ($('#cpt_charge').val() !== '') {
            noshdata.billing_charge = $('#cpt_charge').val();
        }
        if ($('#unit').val() !== '') {
            noshdata.billing_unit = $('#unit').val();
        }
        $('#cpt_charge').blur(function(){
            if ($(this).val() !== noshdata.billing_charge) {
                if ($('#cpt').val() !== '' && noshdata.toastr_collide !== '1') {
                    var text_data = '<div class="col-md-2 col-md-offset-5"><button id="update_cpt" class="btn btn-default btn-block">{{ trans('nosh.button_yes') }}</button></div>';
                    toastr.success(text_data, '{{ trans('nosh.new_charge') }}', {"timeOut":"20000","preventDuplicates":true});
                    noshdata.toastr_collide = '1';
                }
            }
        });
        $('#unit').blur(function(){
            if ($(this).val() !== noshdata.billing_unit) {
                if ($('#cpt').val() !== '' && noshdata.toastr_collide !== '1') {
                    var text_data = '<div class="col-md-2 col-md-offset-5"><button id="update_cpt" class="btn btn-default btn-block">{{ trans('nosh.button_yes') }}</button></div>';
                    toastr.success(text_data, '{{ trans('nosh.new_unit') }}', {"timeOut":"20000","preventDuplicates":true});
                    noshdata.toastr_collide = '1';
                }
            }
        });

        // Referral
        $('#referral_specialty').change(function() {
            var a = $(this).val();
            if (a !== '') {
                $('#address_id').removeOption(/./);
                $('#address_id').addOption({'':'{{ trans('nosh.select_provider') }}'}, false);
                $.ajax({
                    type: 'POST',
                    url: noshdata.search_referral_provider,
                    data: 'specialty=' + a,
                    dataType: 'json',
                    success: function(data){
                        if (data.response == 'y') {
                            $('#address_id').addOption(data.options,false);
                        }
                    }
                });
            }
        });

        // Chart queue
        $('.chart_queue_item').css('cursor', 'pointer').click(function(){
            var id = '';
            var target = $(this);
            if ($(this).hasClass('list-group-item-success')) {
                // Remove from queue
                id = $(this).attr('nosh-data');
                $.ajax({
                    type: 'POST',
                    url: noshdata.chart_queue,
                    data: 'type=remove&id=' + id,
                    success: function(data){
                        toastr.success(data);
                        target.removeClass('list-group-item-success');
                    }
                });
            } else {
                // Add to queue
                id = $(this).attr('nosh-data');
                $.ajax({
                    type: 'POST',
                    url: noshdata.chart_queue,
                    data: 'type=add&id=' + id,
                    success: function(data){
                        toastr.success(data);
                        target.addClass('list-group-item-success');
                    }
                });
            }
        });

        // Bluebutton
        function bluebutton_add(arr, target, type) {
            var html = '';
            var label = '';
            $.each(arr, function(key,value) {
                html = '<li class="list-group-item container-fluid list-group-item-danger nosh-ccda-list"';
                html += ' data-nosh-type="' + type + '"';
                if (type == 'issues') {
                    html += ' data-nosh-name="' + value.name + '"';
                    html += ' data-nosh-code="' + value.code + '"';
                    html += ' data-nosh-date="' + value.date_range.start + '"';
                    label = value.name;
                }
                if (type == 'rx_list') {
                    html += ' data-nosh-name="' + value.product.name + '"';
                    html += ' data-nosh-code="' + value.product.code + '"';
                    html += ' data-nosh-date="' + value.date_range.start + '"';
                    html += ' data-nosh-dosage="' + value.dose_quantity.value + '"';
                    html += ' data-nosh-dosage-unit="' + value.dose_quantity.unit + '"';
                    html += ' data-nosh-route="' + value.route.name + '"';
                    html += ' data-nosh-reason="' + value.reason.name + '"';
                    html += ' data-nosh-administration="' + value.administration.name + '"';
                    label = value.product.name + ' ' + value.dose_quantity.value + ' ' + value.dose_quantity.unit + ', ' + value.administration.name + ' ' + value.route.name + ' for ' + value.reason.name;
                 }
                if (type == 'immunizations') {
                    html += ' data-nosh-name="' + value.product.name + '"';
                    html += ' data-nosh-route="' + value.route.name + '"';
                    html += ' data-nosh-date="' + value.date + '"';
                    html += ' data-nosh-code="' + value.product.code + '"';
                    label = value.product.name;
                }
                if (type == 'allergies') {
                    html += ' data-nosh-name="' + value.allergen.name + '"';
                    html += ' data-nosh-reaction="' + value.reaction_type.name + '"';
                    html += ' data-nosh-date="' + value.date_range.start + '"';
                    label = value.allergen.name + ' - ' + value.reaction_type.name;
                }
                html += '><span>' + label + '</span></li>';
                $('#' + target).append(html);
            });
        }

        if (noshdata.ccda !== '') {
            var bb = BlueButton(noshdata.ccda);
            if ($('#issues_reconcile_list').length) {
                bluebutton_add(bb.problems(), 'issues_reconcile_list', 'issues');
            }
            if ($('#rx_list_reconcile_list').length) {
                bluebutton_add(bb.medications(), 'rx_list_reconcile_list', 'rx_list');
            }
            if ($('#immunizations_reconcile_list').length) {
                bluebutton_add(bb.immunizations(), 'immunizations_reconcile_list', 'immunizations');
            }
            if ($('#allergies_reconcile_list').length) {
                bluebutton_add(bb.allergies(), 'allergies_reconcile_list', 'allergies');
            }
        }

        // Immunization recomendations
        if ($('#immunization_recs').length) {
            var noshimm = JSON.parse('<?php if (isset($imm_arr)) { echo $imm_arr; }?>');
            var imm_container = document.getElementById('immunization_recs');
            immunizationTable = new ImmunizationTable(noshimm);
            immunizationTable.append(imm_container);
            $('.pediatric-immunizations-schedule').addClass('table table-striped table-bordered');
        }

        // Dosage calculator
        $(".docalcblur").blur(function(){
            if ($(this).val() !== '' && $('#calc_dosage').val() !== '') {
                dosage_calc();
            }
        });
        $(".docalc").change(function(){
            if ($(this).val() !== '' && $('#calc_dosage').val() !== '') {
                dosage_calc();
            }
        });
        $('#dosage_ok').css('cursor', 'pointer').click(function(){
            if ($('#calc_liquid_dose').val() !== '') {
                $('#rxl_sig').val($('#calc_liquid_dose').val() + ' ' + $('#calc_liquid_dose_unit option:selected').text());
            } else {
                $('#rxl_sig').val($('#calc_dose').val() + ' ' + $('#calc_dose_unit option:selected').text());
            }
            $('#rxl_frequency').val($('#calc_frequency option:selected').text());
        });

        // Smart-on-FHIR
        $(document).on('click', '.nosh_icon_ban', function(event){
            var url = $(this).attr('nosh-val');
            event.preventDefault();
            $.ajax({
                type: 'POST',
                url: noshdata.remove_smart_on_fhir,
                data: 'url=' + url
            }).done(function(response) {
				toastr.success(response);
				location.reload(true);
            });
        });

        // Demo
        if (noshdata.demo_comment !== '') {
            var response = '<p>Thank you for testing our demo.  <a href="mailto:agropper@gmaill.com?Subject=HIEofONe%20Demo" target="_blank">Please send us your comments</a>';
            $('#warningModal_body').css('height','30vh').html(response);
            $('#warningModal').modal('show');
        }
    });

    function fixDP(r, dps) {
        if (isNaN(r)) return 'NaN';
        var msign = '';
        if (r < 0) msign = '-';
        x = Math.abs(r);
        if (x > Math.pow(10, 21)) return msign + x.toString();
        var m = Math.round(x * Math.pow(10, dps)).toString();
        if (dps === 0) return msign + m;
        while (m.length <= dps) m = '0' + m;
        return msign + m.substring(0, m.length - dps) + "." + m.substring(m.length - dps);
    }

    function dosage_calc() {
        doCalc = true;
        if ($('#calc_dosage').val().indexOf(',') >= 0) {
            toastr.error('{{ trans('nosh.no_comma') }}');
            $('#calc_dosage').val('');
            doCalc = false;
        }
        param_value = parseFloat($('#calc_dosage').val());
        if (isNaN(param_value)){param_value = ''; doCalc = false;}
        unit_parts = $('#calc_dosage_unit').val().split('|');
        Dosage = param_value * parseFloat(unit_parts[0]) + parseFloat(unit_parts[1]);
        if (noshdata.recent_weight === '') {
            toastr.error('{{ trans('nosh.disable_calc') }}');
            doCalc = false;
        }
        param_value = parseFloat(noshdata.recent_weight);
        unit = 1;
        if (noshdata.weight_unit !== 'kg') {
            unit = 0.45359237;
        }
        Weight = param_value * parseFloat(unit);
        if (noshdata.weight_unit !== 'kg') {
            if ($('#calc_weight_span').text().indexOf(',') === -1) {
                $('#calc_weight_span').append(', ' + Weight + ' kg');
            }
        }
        if ($('#calc_med_amount').val().indexOf(',') >= 0) {
            toastr.error('{{ trans('nosh.no_comma') }}');
            $('#calc_med_amount').val('');
            doCalc = false;
        }
        param_value = parseFloat($('#calc_med_amount').val());
        unit_parts = $('#calc_med_amount_unit').val().split('|');
        Med_Amount = param_value * parseFloat(unit_parts[0]) + parseFloat(unit_parts[1]);
        if ($('#calc_med_volume').val().indexOf(',') >= 0) {
            toastr.error('{{ trans('nosh.no_comma') }}');
            $('#calc_med_volume').val('');
            doCalc = false;
        }
        param_value = parseFloat($('#calc_med_volume').val());
        unit_parts = $('#calc_med_volume_unit').val().split('|');
        Per_Volume = param_value * parseFloat(unit_parts[0]) + parseFloat(unit_parts[1]);
        Frequency = $('#calc_frequency').val();
        Dose =  Weight * Dosage / Frequency;
        unit_parts = $('#calc_dose_unit').val().split('|');
        if (doCalc) {
            $('#calc_dose').val(fixDP((Dose - parseFloat(unit_parts[1])) / parseFloat(unit_parts[0]), 0));
        }
        Liquid_Dose =  Dose * Per_Volume / Med_Amount;
        unit_parts = $('#calc_liquid_dose_unit').val().split('|');
        if (doCalc) {
            $('#calc_liquid_dose').val(fixDP((Liquid_Dose - parseFloat(unit_parts[1])) / parseFloat(unit_parts[0]), 0));
        }
        if (isNaN($('#calc_liquid_dose').val())) $('#calc_liquid_dose').val('');
    }
</script>
@if (isset($goodrx))
    <script>
        var _grxdn = '{!! $goodrx !!}';
        (function(d,t){var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
        g.src="//s3.amazonaws.com/assets.goodrx.com/static/widgets/compare.min.js";
        s.parentNode.insertBefore(g,s);}(document,"script"));
        (function(e,v){var h=e.createElement(v),u=e.getElementsByTagName(v)[0];
        h.src="//s3.amazonaws.com/assets.goodrx.com/static/widgets/low.min.js";
        u.parentNode.insertBefore(h,u);}(document,"script"));
    </script>
@endif
@endsection
