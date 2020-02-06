<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <title>
        @if (isset($title))
            {{ $title }}
        @else
            NOSH ChartingSystem
        @endif
    </title>
    {!! $assets_css !!}
    @yield('view.stylesheet')
    <style>
		@import url(https://fonts.googleapis.com/css?family=Nunito);
		body {
			font-family: 'Nunito';
		}
        h3 {
			font-family: 'Nunito';
		}
        h4 {
			font-family: 'Nunito';
		}
        h5 {
			font-family: 'Nunito';
		}
        .cd-timeline-content h3 {
            font-family: 'Nunito';
        }
    </style>

</head>
<body id="app-layout">
    <nav class="navbar navbar-default navbar-static-top" role="navigation">
        <div class="container">
            <div class="navbar-header">

                <!-- Collapsed Hamburger -->
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#app-navbar-collapse">
                    <span class="sr-only">Toggle Navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>

                <!-- Branding Image -->
                <span class="navbar-brand" id="logo" data-toggle="offcanvas">
                    Nosh
                </span>
                @if (Session::has('pid'))
                    <div class="navbar-brand">
                        <span class="fa-btn" data-toggle="modal" data-target="#overviewModal" role="button" id="overviewModal_trigger"><i class="fa fa-user fa-lg"></i></span>
                    </div>
                @endif
            </div>

            <div class="collapse navbar-collapse" id="app-navbar-collapse">
                <!-- Left Side Of Navbar -->
                <ul class="nav navbar-nav">
                    @if (!Auth::guest())
                        <li><a href="{{ route('dashboard') }}">{{ trans('nosh.tasks') }}</a></li>
                        @if (Session::get('group_id') == '1')
                            <li><a href="{{ route('setup') }}">{{ trans('nosh.setup') }}</a></li>
                            @if (env('DOCKER') !== '1')
                                <li><a href="{{ route('setup_mail') }}">{{ trans('nosh.setup_mail') }}</a></li>
                            @endif
                            <li><a href="{{ route('practice_manage', ['active']) }}">{{ trans('noshform.practice_manage') }}</a></li>
                            <li><a href="{{ route('users', ['2', '1']) }}">{{ trans('nosh.users') }}</a></li>
                            <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ trans('nosh.schedule') }} <span class="caret"></span></a>
                                <ul class="dropdown-menu" role="menu" style="width:250px;">
                                    <li><a href="{{ route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'schedule']) }}">{{ trans('nosh.schedule_setup') }}</a></li>
                                    <li><a href="{{ route('schedule_visit_types', ['y']) }}">{{ trans('nosh.schedule_visit_types') }}</a></li>
                                    <li><a href="{{ route('schedule_provider_exceptions', ['0']) }}">{{ trans('nosh.schedule_provider_exceptions') }}</a></li>
                                </ul>
                            </li>
                            <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ trans('nosh.export') }} <span class="caret"></span></a>
                                <ul class="dropdown-menu" role="menu" style="width:250px;">
                                    <li><a href="{{ route('download_ccda_entire') }}">{{ trans('nosh.download_ccda_entire') }}</a></li>
                                    <li><a href="{{ route('download_charts_entire') }}">{{ trans('nosh.download_charts_entire') }}</a></li>
                                    <li><a href="{{ route('download_csv_demographics') }}">{{ trans('nosh.download_csv_demographics') }}</a></li>
                                    <li><a href="{{ route('database_export') }}">{{ trans('nosh.database_export') }}</a></li>
                                </ul>
                            </li>
                            @if (isset($saas_admin))
                                <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ trans('nosh.database') }} <span class="caret"></span></a>
                                    <ul class="dropdown-menu" role="menu" style="width:250px;">
                                        <li><a href="{{ route('audit_logs') }}">{{ trans('nosh.audit_logs') }}</a></li>
                                        <li><a href="{{ route('database_import') }}">{{ trans('nosh.database_import') }}</a></li>
                                        <li><a href="{{ route('database_import_file') }}">{{ trans('nosh.database_import_file') }}</a></li>
                                        <li><a href="{{ route('database_import_cloud') }}">{{ trans('nosh.database_import_cloud') }}</a></li>
                                    </ul>
                                </li>
                            @endif
                        @endif
                        @if (Session::get('group_id') == '2'|| Session::get('group_id') == '3' || Session::get('group_id') == '4' || Session::get('group_id') == '100')
                            <li><a href="{{ route('messaging', ['inbox']) }}">{{ trans('nosh.messaging') }} <span class="badge">{{ Session::get('messages_count') }}</span></a></li>
                            <li><a href="{{ route('schedule') }}">{{ trans('nosh.schedule') }}</a></li>
                        @endif
                        @if (Session::get('group_id') == '2' || Session::get('group_id') == '3' || Session::get('group_id') == '4')
                            <li><a href="{{ route('financial', ['queue']) }}">{{ trans('nosh.financial') }}</a></li>
                            <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ trans('nosh.office') }} <span class="caret"></span></a>
                                <ul class="dropdown-menu" role="menu" style="width:250px;">
                                    <li><a href="{{ route('vaccines', ['inventory']) }}">{{ trans('nosh.vaccines') }}</a></li>
                                    <li><a href="{{ route('supplements', ['inventory']) }}">{{ trans('nosh.supplements') }}</a></li>
                                    <li><a href="{{ route('superquery_list') }}">{{ trans('nosh.reports') }}</a></li>
                                </ul>
                            </li>
                            @if (Session::has('print_queue') || Session::has('fax_queue'))
                                <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ trans('nosh.queues') }} <span class="caret"></span></a>
                                    <ul class="dropdown-menu" role="menu" style="width:250px;">
                                        @if (Session::has('print_queue'))
                                            <li><a href="{{ route('print_queue', ['run', '0', '0']) }}">{{ trans('nosh.print_queue') }}  <span class="badge pull-right">{{ Session::get('print_queue_count') }}</span></a></li>
                                        @endif
                                        @if (Session::has('fax_queue'))
                                            <li><a href="{{ route('fax_queue', ['run', '0', '0']) }}">{{ trans('nosh.fax_queue') }}  <span class="badge pull-right">{{ Session::get('fax_queue_count') }}</span></a></li>
                                        @endif
                                    </ul>
                                </li>
                            @endif
                            @if (Session::has('tags_array') || Session::has('hedis_query'))
                                <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ trans('nosh.queries') }} <span class="caret"></span></a>
                                    <ul class="dropdown-menu" role="menu" style="width:250px;">
                                        @if (Session::has('tags_array'))
                                            <li><a href="{{ route('superquery_tag') }}">{{ trans('nosh.superquery_tag') }}</a></li>
                                        @endif
                                        @if (Session::has('hedis_query'))
                                            <li><a href="{{ route('superquery_hedis', [Session::get('hedis_query')]) }}">{{ trans('nosh.superquery_hedis') }}</a></li>
                                        @endif
                                    </ul>
                                </li>
                            @endif
                        @endif
                        @if (Session::get('group_id') == '2' || Session::get('group_id') == '3')
                            <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ trans('nosh.configure') }} <span class="caret"></span></a>
                                <ul class="dropdown-menu" role="menu" style="width:250px;">
                                    @if (Session::get('patient_centric') == 'yp')
                                        <li><a href="{{ route('api_patient') }}">{{ trans('nosh.api_patient') }}</a></li>
                                    @endif
                                    <li><a href="{{ route('addressbook', ['all']) }}">{{ trans('nosh.addressbook') }}</a></li>
                                    <li><a href="{{ route('configure_form_list') }}">{{ trans('nosh.configure_form_list') }}</a></li>
                                    <li><a href="{{ route('setup') }}">{{ trans('nosh.practice_setup') }}</a></li>
                                    <li><a href="{{ route('schedule_provider_exceptions', [Session::get('user_id')]) }}">{{ trans('nosh.schedule_provider_exceptions') }}</a></li>
                                    <li><a href="{{ route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'schedule']) }}">{{ trans('nosh.schedule_setup') }}</a></li>
                                    <li><a href="{{ route('schedule_visit_types', ['y']) }}">{{ trans('nosh.schedule_visit_types') }}</a></li>
                                </ul>
                            </li>
                        @endif
                    @endif
                </ul>

                <!-- Right Side Of Navbar -->
                <ul class="nav navbar-nav navbar-right">
                    <!-- Authentication Links -->
                    @if (Auth::guest())
                        @if (!isset($noheader))
                            <li><a href="{{ url('/') }}">{{ trans('nosh.login_heading') }}</a></li>
                        @endif
                    @else
                        @if (Session::has('uma_uri'))
                            <li><a href="{{ Session::get('uma_uri') }}/make_invitation"><i class="fa fa-share-square-o fa-fw fa-lg send_uma_invite" data-toggle="tooltip" data-placement="bottom" title="{{ trans('nosh.uma_invite') }}"></i></a></li>
                        @endif
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                                {{ Auth::user()->displayname }} <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu" role="menu">
                                @if (Session::get('group_id') !== 1)
                                    <li><a href="{{ route('core_form', ['users', 'id', Session::get('user_id'), Session::get('group_id')]) }}"><i class="fa fa-fw fa-btn fa-cogs"></i>{{ trans('nosh.my_information') }}</a></li>
                                @endif
                                @if (Session::get('group_id') == '2')
                                    <li><a href="{{ route('user_signature') }}"><i class="fa fa-fw fa-btn fa-pencil"></i>{{ trans('nosh.user_signature') }}</a></li>
                                @endif
                                @if (Session::get('patient_centric') == 'y')
                                    <li><a href="{{ route('fhir_connect') }}"><i class="fa fa-fw fa-btn fa-plug"></i>{{ trans('nosh.fhir_connect') }}</a></li>
                                    <li><a href="{{ route('cms_bluebutton') }}"><i class="fa fa-fw fa-btn fa-plug"></i>{{ trans('nosh.medicare_connect') }}</a></li>
                                    @if (Session::has('uma_uri'))
                                        <li><a href="{{ Session::get('uma_uri') }}"><i class="fa fa-fw fa-btn fa-openid"></i>{{ trans('nosh.hieofone') }}</a></li>
                                    @endif
                                    <li><a href="{{ route('demographics_add_photo') }}"><i class="fa fa-fw fa-btn fa-camera"></i>{{ trans('nosh.add_photo') }}</a></li>
                                @endif
                                @if (Session::get('patient_centric') == 'y' || Session::get('patient_centric') == 'yp')
                                    <li><a href="{{ route('patient_data_export') }}"><i class="fa fa-fw fa-btn fa-medkit"></i>{{ trans('noshform.patient_data_export') }}</a></li>
                                @endif
                                @if (Session::get('group_id') == '2' || Session::get('group_id') == '3')
                                    <li><a href="{{ route('template_restore', ['backup']) }}" class="nosh-no-load"><i class="fa fa-fw fa-btn fa-cloud-download"></i>{{ trans('nosh.template_restore_backup') }}</a></li>
                                    <li><a href="{{ route('template_restore', ['upload']) }}"><i class="fa fa-fw fa-btn fa-cloud-upload"></i>{{ trans('nosh.template_restore_upload') }}</a></li>
                                    <li><a href="{{ route('template_restore') }}" class="nosh-confirm" data-nosh-confirm-message="{{ trans('nosh.template_restore_confirm') }}"><i class="fa fa-fw fa-btn fa-refresh"></i>{{ trans('nosh.template_restore') }}</a></li>
                                @endif
                                <li><a href="{{ route('password_change') }}"><i class="fa fa-fw fa-btn fa-cog"></i>{{ trans('nosh.password_change') }}</a></li>
                                @if (env('DOCKER') !== '1')
                                    <li><a href="{{ route('update_system') }}"><i class="fa fa-fw fa-btn fa-download"></i>{{ trans('nosh.update_system') }}</a></li>
                                @endif
                                <li><a href="https://github.com/shihjay2/nosh2/issues/new" target="_blank" class="nosh-no-load"><i class="fa fa-fw fa-btn fa-github-alt"></i>{{ trans('nosh.report_bug') }}</a></li>
                                <li><a href="https://github.com/shihjay2/nosh2/issues/new" target="_blank" class="nosh-no-load"><i class="fa fa-fw fa-btn fa-heart"></i>{{ trans('nosh.make_suggestion') }}</a></li>
                                <li><a href="{{ route('logout') }}"><i class="fa fa-fw fa-btn fa-sign-out"></i>{{ trans('nosh.logout') }}</a></li>
                            </ul>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    @if (isset($sidebar_content))
    <div class="row-offcanvas row-offcanvas-left">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar-offcanvas">
            <div class="col-md-12">
                @if (isset($name))
                    <a href="{{ url('patient') }}">
                        <h4>{{ $name }}</h4>
                        {!! $demographics_quick !!}
                    </a>
                @endif
                <ul class="nav nav-pills nav-stacked">
                    @if ($sidebar_content == 'chart')
                        @if (isset($active_encounter))
                            <li>
                                <a class="btn btn-success" href="{{ $active_encounter_url }}">
                                    <i class="fa fa-hand-o-right fa-fw fa-lg"></i>
                                    <span class="sidebar-item">{{ trans('nosh.active_encounter') }}: {{ $active_encounter }}</span>
                                </a>
                            </li>
                        @endif
                        <li class="sidebar-search">
                            <form class="nosh-form" role="form" method="POST" action="{{ route('search_chart') }}">
                                {{ csrf_field() }}
                                <div class="input-group custom-search-form">
                                    <input type="text" name="search_chart" class="form-control" placeholder="{{ trans('nosh.search_chart') }}">
                                    <span class="input-group-btn">
                                    <button class="btn btn-default" type="submit">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </span>
                                </div>
                            </form>
                        </li>
                        <li @if(isset($demographics_active)) class="active" @endif>
                            <a href="{{ route('demographics') }}">
                                <i class="fa fa-user fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.demographics') }}</span>
                                @if (isset($demographics_alert))
                                    <span class="label label-danger">{{ $demographics_alert }}</span>
                                @endif
                            </a>
                        </li>
                        <li @if(isset($issues_active)) class="active" @endif>
                            <a href="{{ route('conditions_list', ['type' => 'active']) }}">
                                <i class="fa fa-bars fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.conditions_list') }}</span>
                                <span class="badge">{{ $conditions_badge }}</span>
                            </a>
                        </li>
                        <li @if(isset($medications_active)) class="active" @endif>
                            <a href="{{ route('medications_list', ['type' => 'active']) }}">
                                <i class="fa fa-eyedropper fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.medications_list') }}</span>
                                <span class="badge">{{ $medications_badge }}</span>
                            </a>
                        </li>
                        <li @if(isset($supplements_active)) class="active" @endif>
                            <a href="{{ route('supplements_list', ['type' => 'active']) }}">
                                <i class="fa fa-tree fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.supplements_list') }}</span>
                                <span class="badge">{{ $supplements_badge }}</span>
                            </a>
                        </li>
                        <li @if(isset($immunizations_active)) class="active" @endif>
                            <a href="{{ route('immunizations_list') }}">
                                <i class="fa fa-magic fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.immunizations_list') }}</span>
                                <span class="badge">{{ $immunizations_badge }}</span>
                            </a>
                        </li>
                        <li @if(isset($allergies_active)) class="active" @endif>
                            <a href="{{ route('allergies_list', ['type' => 'active']) }}">
                                <i class="fa fa-exclamation-triangle fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.allergies_list') }}</span>
                                <span class="badge">{{ $allergies_badge }}</span>
                            </a>
                        </li>
                        @if (Session::get('group_id') != '100')
                            <li @if(isset($alerts_active)) class="active" @endif>
                                <a href="{{ route('alerts_list', ['type' => 'active']) }}">
                                    <i class="fa fa-bell fa-fw fa-lg"></i>
                                    <span class="sidebar-item">{{ trans('nosh.alerts_list') }}</span>
                                    <span class="badge">{{ $alerts_badge }}</span>
                                </a>
                            </li>
                        @endif
                        <li @if(isset($orders_active)) class="active" @endif>
                            <a href="{{ route('orders_list', ['type' => 'orders_labs']) }}">
                                <i class="fa fa-thumbs-o-up fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.orders_list') }}</span>
                                <span class="badge">{{ $orders_badge }}</span>
                            </a>
                        </li>
                        <li @if(isset($encounters_active)) class="active" @endif>
                            <a href="{{ route('encounters_list') }}">
                                <i class="fa fa-stethoscope fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.encounters_list') }}</span>
                                <span class="badge">{{ $encounters_badge }}</span>
                            </a>
                        </li>
                        <li @if(isset($documents_active)) class="active" @endif>
                            <a href="{{ route('documents_list', ['type' => 'All']) }}">
                                <i class="fa fa-file-text-o fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.documents_list') }}</span>
                            </a>
                        </li>
                        <li @if(isset($results_active)) class="active" @endif>
                            <a href="{{ route('results_list', ['type' => 'Laboratory']) }}">
                                <i class="fa fa-flask fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.results_list') }}</span>
                            </a>
                        </li>
                        <li @if(isset($t_messages_active)) class="active" @endif>
                            <a href="{{ route('t_messages_list') }}">
                                <i class="fa fa-phone fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.t_messages_list')}}</span>
                            </a>
                        </li>
                        <li @if(isset($forms_active)) class="active" @endif>
                            <a href="{{ route('form_list', ['fill']) }}">
                                <i class="fa fa-question-circle-o fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.form_list') }}</span>
                            </a>
                        </li>
                        @if($growth_chart_show == 'yes')
                            <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-line-chart fa-fw fa-lg"></i><span class="sidebar-item">{{ trans('nosh.growth_charts') }}</span><span class="caret"></span></a>
                                <ul class="dropdown-menu" role="menu">
                                    <li><a href="{{ route('growth_chart', ['weight-age']) }}">{{ trans('nosh.weight') }}</a></li>
                                    <li><a href="{{ route('growth_chart', ['height-age']) }}">{{ trans('nosh.height') }}</a></li>
                                    @if(Session::get('agealldays') < 1856)
                                        <li><a href="{{ route('growth_chart', ['head-age']) }}">{{ trans('nosh.hc') }}</a></li>
                                    @endif
                                    <li><a href="{{ route('growth_chart', ['weight-height']) }}">{{ trans('nosh.weight_height') }}</a></li>
                                    @if(Session::get('agealldays') > 730.5)
                                        <li><a href="{{ route('growth_chart', ['bmi-age']) }}">{{ trans('nosh.BMI') }}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        <li @if(isset($sh_active)) class="active" @endif>
                            <a href="{{ route('social_history') }}">
                                <i class="fa fa-users fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.social_history') }}</span>
                            </a>
                        </li>
                        <li @if(isset($fh_active)) class="active" @endif>
                            <a href="{{ route('family_history') }}">
                                <i class="fa fa-sitemap fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.family_history') }}</span>
                            </a>
                        </li>
                        @if (Session::has('mtm_extension'))
                            @if (Session::get('mtm_extension') == 'y')
                                <li @if(isset($mtm_active)) class="active" @endif>
                                    <a href="{{ route('mtm') }}">
                                        <i class="fa fa-medkit fa-fw fa-lg"></i>
                                        <span class="sidebar-item">{{ trans('noshform.mtm2') }}</span>
                                    </a>
                                </li>
                            @endif
                        @endif
                        <li @if(isset($care_active)) class="active" @endif>
                            <a href="{{ route('care_opportunities', ['prevention']) }}">
                                <i class="fa fa-info fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.care_opportunities') }}</span>
                            </a>
                        </li>
                        <li @if(isset($records_active)) class="active" @endif>
                            <a href="{{ route('records_list', ['release']) }}">
                                <i class="fa fa-handshake-o fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.records_list') }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="print_summary">
                                <i class="fa fa-print fa-fw fa-lg"></i><span class="sidebar-item">{{ trans('noshform.print_summary') }}</span>
                            </a>
                        </li>
                        <li @if(isset($billing_active)) class="active" @endif>
                            <a href="{{ route('billing_list', ['type' => 'encounters', 'pid' => Session::get('pid')]) }}">
                                <i class="fa fa-bank fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.billing_list') }}</span>
                            </a>
                        </li>
                        <li @if(isset($payors_active)) class="active" @endif>
                            <a href="{{ route('payors_list', ['type' => 'active']) }}">
                                <i class="fa fa-money fa-fw fa-lg"></i>
                                <span class="sidebar-item">{{ trans('nosh.payors_list') }}</span>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
        <div id="main">
            <div class="col-md-12">
                @if (Session::get('patient_centric') !== 'y' && Session::get('patient_centric') !== 'yp' && Session::get('group_id') != '100')
                    <div style="margin:15px">
                        <form class="input-group form" border="0" id="search_patient_form" role="search" action="{{ url('search_patient') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_patient_results">
                            <input type="text" class="form-control search" id="search_patient" name="search_patient" placeholder="{{ trans('nosh.search_patient') }}" style="margin-bottom:0px;" required autocomplete="off">
                            <input type="hidden" name="type" value="div">
                            <span class="input-group-btn">
                                <button type="submit" class="btn btn-md" id="search_patient_submit" name="submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                            </span>
                            @if (Session::get('group_id') != '1')
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md btn-default" id="search_patient_recent" data-toggle="tooltip" data-placement="bottom" title="{{ trans('nosh.recent_patients') }}"><i class="fa fa-history fa-lg"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <a href="{{ route('uma_list') }}" type="button" class="btn btn-md btn-default" data-toggle="tooltip" data-placement="bottom" title="{{ trans('nosh.uma_list') }}"><i class="fa fa-fire fa-lg"></i></a>
                                </span>
                                <span class="input-group-btn">
                                    <a href="{{ route('add_patient') }}" class="btn btn-md btn-default" data-toggle="tooltip" data-placement="bottom" title="{{ trans('nosh.add_patient') }}"><i class="fa fa-plus fa-lg"></i></a>
                                </span>
                            @endif
                        </form>
                        <div class="list-group" id="search_patient_results"></div>
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>
    @else
    <div id="main">
        <div class="col-md-12">
            @if (!Auth::guest())
                @if (Session::get('patient_centric') !== 'y' && Session::get('patient_centric') !== 'yp' && Session::get('group_id') != '100')
                    <div style="margin:15px">
                        <form class="input-group form" border="0" id="search_patient_form" role="search" action="{{ url('search_patient') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_patient_results">
                            <input type="hidden" name="type" value="div">
                            <input type="text" class="form-control search" id="search_patient" name="search_patient" placeholder="{{ trans('nosh.search_patient') }}" style="margin-bottom:0px;" required autocomplete="off">
                            <span class="input-group-btn">
                                <button type="submit" class="btn btn-md" id="search_patient_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                            </span>
                            @if (Session::get('group_id') != '1')
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-md btn-default" id="search_patient_recent" data-toggle="tooltip" data-placement="bottom" title="{{ trans('nosh.recent_patients') }}"><i class="fa fa-history fa-lg"></i></button>
                                </span>
                                <span class="input-group-btn">
                                    <a href="{{ route('uma_list') }}" type="button" class="btn btn-md btn-default" data-toggle="tooltip" data-placement="bottom" title="{{ trans('nosh.uma_list') }}"><i class="fa fa-fire fa-lg"></i></a>
                                </span>
                                <span class="input-group-btn">
                                    <a href="{{ route('add_patient') }}" type="button" class="btn btn-md btn-default" data-toggle="tooltip" data-placement="bottom" title="{{ trans('nosh.add_patient') }}"><i class="fa fa-plus fa-lg"></i></a>
                                </span>
                            @endif
                        </form>
                        <div class="list-group" id="search_patient_results"></div>
                    </div>
                @endif
            @endif
            @yield('content')
        </div>
    </div>
    @endif
    @if (isset($noshversion))
        <footer class="footer">
            <div class="container">
                <p class="text-muted pull-right">Version git-{{ $noshversion }}</p>
            </div>
        </footer>
    @endif
    <!-- Modals -->
    <div class="modal" id="loadingModal" role="dialog">
        <div class="modal-dialog">
          <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-body">
                    <i class="fa fa-spinner fa-spin fa-pulse fa-2x fa-fw"></i><span id="modaltext" style="margin:10px"></span>
                </div>
            </div>
        </div>
    </div>
    <div class="modal" id="progressModal" role="dialog">
        <div class="modal-dialog">
          <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-body">
                    <i class="fa fa-spinner fa-spin fa-pulse fa-2x fa-fw"></i><span id="progressmodaltext" style="margin:10px"></span>
                    <br><br>
                    <div class="progress">
                        <div class="progress-bar" id="progressdata" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%">0%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal" id="warningModal" role="dialog">
        <div class="modal-dialog">
          <!-- Modal content-->
            <div class="modal-content">
                <div id="warningModal_header" class="modal-header"></div>
                <div id="warningModal_body" class="modal-body" style="height:80vh;overflow-y:auto;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-btn fa-times"></i> {{ trans('nosh.button_close') }}</button>
                  </div>
            </div>
        </div>
    </div>
    <div class="modal" id="templateModal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ trans('nosh.template_toolbox') }}</h5>
                </div>
                <div class="modal-body" style="height:60vh;overflow-y:auto;">
                    <form id="template_toolbox_form">
                        <div class="form-group template_item_divs">
                            <div class="col-md-6 col-md-offset-4">
                                <button type="button" id="template_modal_copy" class="btn btn-success" style="margin:10px"><i class="fa fa-btn fa-copy"></i> {{ trans('nosh.copy_text_input') }}</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-sm-5" for="template_text">{{ trans('nosh.template_text') }}</label>
                            <div class="col-sm-7">
                                <textarea class="form-control" id="template_text" name="template_text" placeholder="{{ trans('nosh.template_text_placeholder') }}" required autocomplete="off"></textarea>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-sm-5" for="template_gender">{{ trans('nosh.gender_association') }}</label>
                            <div class="col-sm-7">
                                <select id="template_gender" name="template_gender" class="form-control">
                                    <option value="">{{ trans('nosh.gender_association_all') }}</option>
                                    <option value="m">{{ trans('nosh.gender_association_m') }}</option>
                                    <option value="f">{{ trans('nosh.gender_association_f') }}</option>
                                    <option value="u">{{ trans('nosh.gender_association_u') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-sm-5" for="template_age">{{ trans('nosh.age_association') }}</label>
                            <div class="col-sm-7">
                                <select id="template_age" name="template_age" class="form-control">
                                    <option value="">{{ trans('nosh.age_association_all') }}</option>
                                    <option value="adult">{{ trans('nosh.age_association_adult') }}</option>
                                    <option value="child">{{ trans('nosh.age_association_child') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group template_item_divs" style="display:none;">
                            <label class="control-label col-sm-5" for="template_input_type">{{ trans('nosh.template_input_type') }}</label>
                            <div class="col-sm-7">
                                <select id="template_input_type" name="template_input_type" class="form-control">
                                    <option value="">{{ trans('nosh.template_input_type_none')}}</option>
                                    <option value="text">{{ trans('nosh.template_input_type_text') }}</option>
                                    <option value="select">{{ trans('nosh.template_input_type_select') }}</option>
                                    <option value="checkbox">{{ trans('nosh.template_input_type_checkbox') }}</option>
                                    <option value="radio">{{ trans('nosh.template_input_type_radio') }}</option>
                                    <option value="orders">{{ trans('nosh.template_input_type_orders') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" id="template_options_div" style="display:none;">
                            <label class="control-label col-sm-5" for="template_options" id="template_options_label">{{ trans('nosh.template_options') }}</label>
                            <div class="col-sm-7">
                                <input type="text" id="template_options" name="template_options" value="" placeholder="{{ trans('nosh.template_options_placeholder') }}"/>
                            </div>
                        </div>
                        <div class="form-group" id="template_options_orders_div" style="display:none;">
                            <label class="control-label col-sm-5" for="template_options" id="template_options_label">{{ trans('nosh.template_options') }}</label>
                            <div class="col-sm-7">
                                <input type="text" id="template_options_orders_facility" name="template_options_orders_facility" value="" placeholder="{{ trans('nosh.template_options_orders_facility') }}" class="form-control"/>
                                <input type="text" id="template_options_orders_orders_code" name="template_options_orders_orders_code" value="" placeholder="{{ trans('nosh.template_options_orders_orders_code') }}" class="form-control"/>
                                <input type="text" id="template_options_orders_cpt" name="template_options_orders_cpt" value="" placeholder="{{ trans('nosh.template_options_orders_cpt') }}"/>
                                <input type="text" id="template_options_orders_loinc" name="template_options_orders_loinc" value="" placeholder="{{ trans('nosh.template_options_orders_loinc') }}"/>
                                <input type="text" id="template_options_orders_results_code" name="template_options_orders_results_code" value="" placeholder="{{ trans('nosh.template_options_orders_results_code') }}"/>
                            </div>
                        </div>
                        <input type="hidden" name="template_edit_type" id="template_edit_type" value="">
                        <input type="hidden" name="id" id="template_id" value="">
                        <input type="hidden" name="group_name" id="template_group_name" value="">
                        <input type="hidden" name="category" id="template_category" value="">
                        <div class="form-group">
                            <div class="col-md-6 col-md-offset-4">
                                <button type="button" id="template_modal_save" class="btn btn-success" style="margin:10px"><i class="fa fa-btn fa-save"></i> {{ trans('nosh.button_save') }}</button>
                                <button type="button" id="template_modal_cancel" class="btn btn-danger" style="margin:10px"><i class="fa fa-btn fa-ban"></i> {{ trans('nosh.button_cancel') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @if (isset($conditions_preview))
    <div class="modal" id="overviewModal" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ trans('nosh.patient_summary') }} {{ trans('noshform.for') }} {{ $name }}</h5>
                </div>
                <div class="modal-body" id="chart_overview" style="height:80vh;overflow-y:auto;">
                    @if (isset($encounters_preview))
                        <div class="row">
                            <div class="col-xs-12">
                                {!! $encounters_preview !!}
                            </div>
                        </div>
                    @endif
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>{{ trans('nosh.conditions_list') }}</strong>
                            @if (isset($conditions_preview))
                                {!! $conditions_preview !!}
                            @endif
                            <strong>{{ trans('nosh.medications_list') }}</strong>
                            @if (isset($medications_preview))
                                {!! $medications_preview !!}
                            @endif
                        </div>
                        <div class="col-xs-6">
                            <strong>{{ trans('nosh.supplements_list') }}</strong>
                            @if (isset($supplements_preview))
                                {!! $supplements_preview !!}
                            @endif
                            <strong>{{ trans('nosh.allergies_list') }}</strong>
                            @if (isset($allergies_preview))
                                {!! $allergies_preview !!}
                            @endif
                            <strong>{{ trans('nosh.immunizations_list') }}</strong>
                            @if (isset($immunizations_preview))
                                {!! $immunizations_preview !!}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default print_summary"><i class="fa fa-btn fa-print"></i> {{ trans('noshform.print') }}</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-btn fa-times"></i> {{ trans('nosh.button_close') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- JavaScripts -->
    <script type="text/javascript">
        // Global variables
        var noshdata = {
            'add_cpt': '<?php echo url("add_cpt"); ?>',
            'chart_queue': '<?php echo url("set_chart_queue"); ?>',
            'check_cpt': '<?php echo url("check_cpt"); ?>',
            'copy_address': '<?php echo url("copy_address"); ?>',
            'delete_event': '<?php echo url("delete_event"); ?>',
            'document_delete': '<?php echo url("document_delete"); ?>',
            'drag_event': '<?php echo url("drag_event"); ?>',
            'education': '<?php echo url("education"); ?>',
            'event_encounter': '<?php echo url("event_encounter"); ?>',
            'get_appointments': '<?php echo url("get_appointments"); ?>',
            'get_state_data': '<?php echo url("get_state_data"); ?>',
            'home_url': '<?php echo url("/") . '/'; ?>',
            'image_dimensions': '<?php echo url("image_dimensions"); ?>',
            'last_page': '<?php echo url("last_page"); ?>',
            'login_uport': '<?php echo route("login_uport"); ?>',
            'logout_url': '<?php echo url("logout"); ?>',
            'messaging_session': '<?php echo url("messaging_session"); ?>',
            'notification': '<?php echo url("notification"); ?>',
            'patient_url': '<?php echo url("patient"); ?>',
            'practice_logo': '<?php echo url("practice_logo_login"); ?>',
            'progress': '<?php echo url("progress"); ?>',
            'provider_schedule': '<?php echo url("provider_schedule"); ?>',
            'read_message': '<?php echo url("read_message"); ?>',
            'remove_smart_on_fhir': '<?php echo url("remove_smart_on_fhir"); ?>',
            'rx_json': '<?php echo url("rx_json"); ?>',
            'rxnorm': '<?php echo url("rxnorm"); ?>',
            'schedule_url': '<?php echo url("schedule"); ?>',
            'search_cpt_billing': '<?php echo route("typeahead", ["billing_core", "cpt"]); ?>',
            'search_encounters': '<?php echo url("search_encounters"); ?>',
            'search_interactions': '<?php echo url("search_interactions"); ?>',
            'search_ndc': '<?php echo url("search_ndc"); ?>',
            'search_icd_specific': '<?php echo url("search_icd_specific"); ?>',
            'search_patient_history': '<?php echo url("search_patient_history"); ?>',
            'search_referral_provider': '<?php echo url("search_referral_provider"); ?>',
            'set_ccda_data': '<?php echo url("set_ccda_data"); ?>',
            'tags_url': '<?php echo url("tags"); ?>',
            'template_get': '<?php echo url("template_get"); ?>',
            'template_edit': '<?php echo url("template_edit"); ?>',
            'template_normal': '<?php echo url("template_normal"); ?>',
            'template_normal_change': '<?php echo url("template_normal_change"); ?>',
            'template_remove': '<?php echo url("template_remove"); ?>',
            'test_reminder': '<?php echo url("test_reminder"); ?>',
            't_messaging_session': '<?php echo url("t_messaging_session"); ?>',
            'treedata': '<?php echo url("treedata"); ?>',
            'update_cpt': '<?php echo url("update_cpt"); ?>',
            'vitals_graph': '<?php echo url("encounter_vitals_chart"); ?>',
            'group_id': '<?php echo Session::get("group_id"); ?>',
            'notification_run': '<?php echo Session::get("notification_run"); ?>',
            'ccda': '<?php if (isset($ccda)) { echo $ccda; }?>',
            'demo_comment': '<?php if (isset($demo_comment)) { echo $demo_comment; }?>',
            'document_url': '<?php if (isset($document_url)) { echo $document_url; }?>',
            'document_type': '<?php if (isset($document_type)) { echo $document_type; }?>',
            'download_now': '<?php if (isset($download_now)) { echo $download_now; }?>',
            'download_progress': '<?php if (isset($download_progress)) { echo $download_progress; }?>',
            'graph_series_name': '<?php if (isset($graph_series_name)) { echo $graph_series_name; }?>',
            'graph_title': '<?php if (isset($graph_title)) { echo $graph_title; }?>',
            'graph_type': '<?php if (isset($graph_type)) { echo $graph_type; }?>',
            'graph_x_title': '<?php if (isset($graph_x_title)) { echo $graph_x_title; }?>',
            'graph_y_title': '<?php if (isset($graph_y_title)) { echo $graph_y_title; }?>',
            'height_unit': '<?php if (isset($height_unit)) { echo $height_unit; }?>',
            'maxTime': '<?php if (isset($maxTime)) { echo $maxTime; }?>',
            'message_action': <?php if (isset($message_action)) { echo json_encode($message_action); } else { echo "''";}?>,
            'minTime': '<?php if (isset($minTime)) { echo $minTime; }?>',
            'pid': '<?php if (isset($pid)) { echo $pid; }?>',
            'print_now': '<?php if (isset($print_now)) { echo $print_now; }?>',
            'pt_name': '<?php if (isset($pt_name)) { echo $pt_name; }?>',
            'recent_weight': '<?php if (isset($recent_weight)) { echo $recent_weight; }?>',
            'signature': '<?php if (isset($signature)) { echo $signature; }?>',
            'timezone': '<?php if (isset($timezone)) { echo $timezone; }?>',
            'weekends': '<?php if (isset($weekends)) { echo $weekends; }?>',
            'weight_unit': '<?php if (isset($weight_unit)) { echo $weight_unit; }?>',
            'billing_charge': '',
            'billing_unit': '',
            'notification_alert': '',
            'notification_appt': '',
            'progress_id': '',
            'toastr_collide': '',
            'error_text': '<?php echo trans('noshform.error') . " - "; ?>',
        };
    </script>
    {!! $assets_js !!}
    <script type="text/javascript">
        toastr.options = {
            'closeButton': true,
            'debug': false,
            'newestOnTop': true,
            'progressBar': true,
            'positionClass': 'toast-bottom-full-width',
            'preventDuplicates': false,
            'showDuration': '300',
            'hideDuration': '1000',
            'timeOut': '10000',
            'extendedTimeOut': '5000',
            'showEasing': 'swing',
            'hideEasing': 'linear',
            'showMethod': 'fadeIn',
            'hideMethod': 'fadeOut'
        };
        toastr.options.onHidden = function() { noshdata.toastr_collide = ''; };

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

        function split(val) {
            return val.split( /\n\s*/ );
        }

        function checkEmpty(o,n) {
            var text = '';
            if (o.val() === '' || o.val() === null) {
                if (n !== undefined) {
                    text = n.replace(":","");
                    toastr.error(text + ' Required');
                }
                o.closest('.form_group').addClass('has-error');
                o.parent().append('<span class="help-block">' + text + ' required</span>');
                return false;
            } else {
                if (o.closest('.form_group').hasClass('has-error')) {
                    o.closest('.form_group').removeClass('has-error');
                    o.next().remove();
                }
                return true;
            }
        }

        function checkNumeric(o,n) {
            var text = '';
            if (! $.isNumeric(o.val())) {
                text = n.replace(":","");
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
            var text = '';
            if ( !( regexp.test( o.val() ) ) ) {
                text = n.replace(":","");
                toastr.error('Incorrect format: ' + text);
                o.closest('.form_group').addClass('has-error');
                o.parent().append('<span class="help-block">"Incorrect format: ' + text + '</span>');
                return false;
            } else {
                if (o.closest('.form_group').hasClass('has-error')) {
                    o.closest('.form_group').removeClass('has-error');
                    o.next().remove();
                }
                return true;
            }
        }

        function roundit(which) {
            return Math.round(which*100)/100;
        }

        function chart_notification() {
            if (noshdata.group_id == '2' && noshdata.notification_run == 'true') {
                $.ajax({
                    type: "POST",
                    url: noshdata.notification,
                    dataType: "json",
                    success: function(data){
                        if (data.appt !== noshdata.notification_appt && data.appt !== '') {
                            toastr.warning(data.appt, data.appt_header, {"timeOut":"10000","preventDuplicates":true});
                            noshdata.notification_appt = data.appt;
                        }
                        if (data.alert !== noshdata.notification_alert && data.alert !== '') {
                            toastr.warning(data.alert, data.alert_header, {"timeOut":"10000","preventDuplicates":true});
                            noshdata.notification_alert = data.alert;
                        }
                    }
                });
            }
        }

        function nl2br (str, is_xhtml) {
            var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
            return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
        }

        function load_template_group(id) {
            $('#template_target').val(id);
            $('#template_back').hide();
            $('#template-all-items-span').hide();
            $('#template-add').attr('title', 'Add Group').tooltip('fixTitle');
            $('#template_panel_body').removeClass('hidden');
            $('#template_input').addClass('loading');
            $('#template_input').prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: noshdata.template_get,
                data: 'id=' + id,
                dataType: 'json',
                encode: true
            }).done(function(response) {
                var $target = $('#template_list');
                var html = '';
                if (response.response == 'false') {
                    html = '<li class="list-group-item">{{ trans('nosh.notemplate_group') }}</li>';
                    $target.html(html);
                }
                if (response.response == 'li') {
                    $.each(response.message, function (i, val) {
                        if (val.value !== null) {
                            html += '<li class="list-group-item container-fluid"><a href="#" class="btn fa-btn template-default"><i class="fa fa-star fa-lg"></i></a><span class="template-group" data-nosh-template-group="' + val.value + '" data-nosh-template-category="' + val.category + '">' + val.value + '</span><span class="pull-right"><a href="#" class="btn fa-btn template-group-edit"><i class="fa fa-pencil fa-lg"></i></a></a><a href="#" class="btn fa-btn template-remove-group"  data-nosh-template-group="' + val.value + '"><i class="fa fa-trash fa-lg"></i></a></span></li>';
                        }
                    });
                    $target.html(html);
                    $('.searchlist_template').btsListFilter('#searchinput_template', {initial: false});
                }
            });
        }

        function load_template_items(id, group) {
            $('#template-add').attr('title', 'Add Item').tooltip('fixTitle');
            $('#template-all-items-span').show();
            $('#template_back_text').text('Back');
            $('#template_back').show();
            $.ajax({
                type: 'POST',
                url: noshdata.template_get,
                data: 'id=' + id + '&template_group=' + group,
                dataType: 'json',
                encode: true
            }).done(function(response) {
                var $target = $('#template_list');
                var html = '';
                var options = '';
                if (response.response == 'false') {
                    html = '<li class="list-group-item">{{ trans('nosh.notemplate_item') }}</li>';
                    $target.html(html);
                }
                if (response.response == 'li') {
                    var radio_ct = 0;
                    var checkbox_ct = 0;
                    $.each(response.message, function (i, val) {
                        if (val.value !== null) {
                            html += '<li class="list-group-item container-fluid"><span class="template-item" data-nosh-value="' + val.value + '" data-nosh-template-group="' + val.group + '" data-nosh-template-id="' + val.id + '" data-nosh-template-category="' + val.category + '"';
                            if (val.input !== null) {
                                html += ' data-nosh-input="' + val.input + '"';
                                if (val.options !== null) {
                                    html += 'data-nosh-options="' + val.options + '"';
                                }
                                if (val.orders !== null) {
                                    html += 'data-nosh-orders-facility="' + val.orders.facility + '"';
                                    html += 'data-nosh-orders-orders-code="' + val.orders.orders_code + '"';
                                    html += 'data-nosh-orders-cpt="' + val.orders.cpt + '"';
                                    html += 'data-nosh-orders-loinc="' + val.orders.loinc + '"';
                                    html += 'data-nosh-orders-results-code="' + val.orders.results_code + '"';
                                }
                            }
                            if (val.normal === true) {
                                html += ' data-nosh-normal="yes"';
                            }
                            if (val.age !== null) {
                                html += ' data-nosh-age="' + val.age + '"';
                            }
                            if (val.gender !== null) {
                                html += ' data-nosh-gender="' + val.gender + '"';
                            }
                            html += '>' + nl2br(val.value, true);
                            if (val.input !== null) {
                                if (val.input == 'text') {
                                    html += '<div class="form-group"><input type="text" class="form-control input-sm nosh-template-input"></div>';
                                }
                                if (val.input == 'radio') {
                                    if (val.options !== null) {
                                        html += '<div class="form-group">';
                                        options = val.options.split(',');
                                        for (var j=0; j<options.length; j++) {
                                            html += '<label class="radio-inline"><input class="nosh-template-input" type="radio" name="optradio_' + radio_ct + '" value="' + options[j] + '">' + options[j] + '</label>';
                                        }
                                        html += '</div>';
                                    }
                                }
                                if (val.input == 'checkbox') {
                                    if (val.options !== null) {
                                        html += '<div class="form-group nosh-template-input">';
                                        options = val.options.split(',');
                                        for (var k=0; k<options.length; k++) {
                                            html += '<label class="checkbox-inline"><input class="nosh-template-input" type="checkbox" name="optcheckbox_' + checkbox_ct + '" value="' + options[k] + '">' + options[k] + '</label>';
                                        }
                                        html += '</div>';
                                    }
                                }
                                if (val.input == 'select') {
                                    if (val.options !== null) {
                                        html += '<div class="form-group nosh-template-input"><select class="form-control nosh-template-input" id="optselect">';
                                        options = val.options.split(',');
                                        html += '<option></options>';
                                        for (var l=0; l<options.length; l++) {
                                            html += '<option>' + options[l] + '</options>';
                                        }
                                        html += '</select></div>';
                                    }
                                }
                                if (val.input == 'orders') {
                                    var cpt = val.orders.cpt;
                                    cpt = cpt.toString().replace(/,/g, ";");
                                    var loinc = val.orders.loinc;
                                    loinc = loinc.toString().replace(/,/g, ";");
                                    var results = val.orders.results_code;
                                    results = results.toString().replace(/,/g, ";");
                                    var orders = '[' + val.orders.facility + ',' + val.orders.orders_code + ',(' + cpt + '),(' + loinc + '),(' + results + ')]';
                                    html += '<input class="form-control nosh-template-input" type="hidden" value="' + orders + '">';
                                }
                                html += '</span><span class="pull-right">';
                            } else {
                                html += '</span><span class="pull-right"><a href="#" class="btn fa-btn template-normal-change">';
                                if (val.normal === true) {
                                    html += '<i class="fa fa-star fa-lg"></i></a>';
                                } else {
                                    html += '<i class="fa fa-star-o fa-lg"></i></a>';
                                }
                            }
                            html += '<a href="#" class="btn fa-btn template-edit"><i class="fa fa-pencil fa-lg"></i><a href="#" class="btn fa-btn template-remove-item"><i class="fa fa-trash fa-lg"></i></a></span></li>';
                            radio_ct++;
                            checkbox_ct++;
                        }
                    });
                    $target.html(html);
                    $('.searchlist_template').btsListFilter('#searchinput_template', {initial: false});
                }
            });
        }

        function checktemplatestatus() {
            var i = 0;
            $('.template-item').each(function() {
                if ($(this).parent().hasClass('active')) {
                    i++;
                }
            });
            if (i > 0) {
                $('#template_back_text').text('{{ trans('nosh.template_copy') }}');
            } else {
                $('#template_back_text').text('{{ trans('nosh.template_back') }}');
            }
        }

        function loadimagepreview(){
            $('#image_placeholder').html('');
            $('#image_placeholder').empty();
            var image_total = '';
            $.ajax({
                url: noshdata.image_load,
                type: 'POST',
                success: function(data){
                    $('#image_placeholder').html(data);
                    image_total = $('#image_placeholder img').length;
                    var $image = $('#image_placeholder img');
                    $image.tooltip();
                    $image.first().show();
                    var i = 1;
                    $('#image_status').html('{{ trans('nosh.image') }} ' + i + ' of ' + image_total);
                    $('#next_image').css('cursor', 'pointer').click(function () {
                        var $next = $image.filter(':visible').hide().next('img');
                        i++;
                        if($next.length === 0) {
                            $next = $image.first();
                            i = 1;
                        }
                        $next.show();
                        $('#image_status').html('{{ trans('nosh.image') }} ' + i + ' of ' + image_total);
                    });
                    $('#prev_image').css('cursor', 'pointer').click(function () {
                        var $prev = $image.filter(':visible').hide().prev('img');
                        i--;
                        if($prev.length === 0) {
                            $next = $image.last();
                            i = image_total;
                        }
                        $prev.show();
                        $('#image_status').html('{{ trans('nosh.image') }} ' + i + ' of ' + image_total);
                    });
                }
            });
        }

        function progressbartrack() {
            $.ajax({
                type: "POST",
                url: noshdata.progress,
                data: "id=" + noshdata.progress_id,
                success: function(data) {
                    var w = data + "%";
                    $('#progressdata').attr('area-valuenow', data).text(w).css('width', w);
                    if (parseInt(data) < 100) {
                        setTimeout(progressbartrack, 5000);
                    }
                }
            });
        }

        function print_summary()
        {
            var divToPrint = document.getElementById("overviewModal");
            newWin = window.open("");
            newWin.document.write(divToPrint.outerHTML);
            newWin.document.getElementById("overviewModal").removeAttribute('style');
            newWin.document.getElementById("chart_overview").removeAttribute('style');
            var f = newWin.document.getElementsByClassName("modal-footer");
            var requiredfooter = f[0];
            requiredfooter.remove();
            var e = newWin.document.getElementsByTagName('h5')[0];
            var d = newWin.document.createElement('h2');
            d.innerHTML = e.innerHTML;
            e.parentNode.replaceChild(d, e);
            newWin.print();
            newWin.close();
        }

        if (noshdata.group_id !== '') {
            $(document).idleTimeout({
                inactivity: 3600000,
                noconfirm: 10000,
                alive_url: noshdata.home_url,
                redirect_url: noshdata.logout_url,
                logout_url: noshdata.logout_url,
                sessionAlive: false
            });
        }

        $(document).on('click', 'a.nosh-icd10', function(event) {
            if ($(this).hasClass('list-group-item-danger')) {
                var $this = $(this);
                event.preventDefault();
                var list_item = $(this).attr('id');
                $.ajax({
                    type: 'POST',
                    url: noshdata.search_icd_specific,
                    data: 'icd=' + $(this).attr('data-nosh-id'),
                    dataType: 'json',
                    encode: true
                }).done(function(response) {
                    var html = '';
                    $.each(response, function (i, val) {
                        var proceed1 = 0;
                        $this.siblings().each(function() {
                            if ($(this).attr('data-nosh-id') == val.id) {
                                proceed1++;
                            }
                        });
                        if (proceed1 === 0) {
                            html += '<a href="' + val.href + '" class="list-group-item nosh-icd10 list-group-item-info';
                            html += '" data-nosh-value="' + val.value +'" data-nosh-id="' + val.id + '">' + val.label + '</a>';
                        }
                    });
                    if (html === '') {
                        toastr.error('{{ trans('nosh.more_specific_code') }}');
                    }
                    $this.after(html);
                    $this.remove();
                });
            }
        });

        $(document).ready(function() {
            var tz = jstz.determine();
            $.cookie('nosh_tz', tz.name(), { path: '/' });
            $(".fancybox").fancybox({
                openEffect: "none",
                closeEffect: "none"
            });
            $('.nosh-result-list').css('cursor', 'pointer').click(function() {
                var href = $(this).find('.pull-right').find('a').first().attr('href');
                $('#modaltext').text('{{ trans('nosh.loading') }}...');
                $('#loadingModal').modal('show');
                window.location = href;
            });
            if (noshdata.group_id == '1') {
                $('#search_patient').attr('placeholder', '{{ trans('nosh.search_patient_placeholder') }}');
            }
            setInterval(chart_notification, 10000);
            $('body').tooltip({
                selector: '[data-toggle=tooltip]'
            });
            $('.nosh-confirm').css('cursor', 'pointer').click(function() {
                var r = confirm($(this).attr('data-nosh-confirm-message'));
                if (r === true) {
                    return true;
                } else {
                    $(this).addClass('nosh-no-load');
                    return false;
                }
            });
            $('.nosh-dash').css('cursor', 'pointer').click(function() {
                var href = $(this).find('a').first().attr('href');
                $('#modaltext').text('{{ trans('nosh.loading') }}...');
                $('#loadingModal').modal('show');
                window.location = href;
            });
            $('.nosh-delete').css('cursor', 'pointer').click(function() {
                var r = confirm('{{ trans('nosh.confirm_delete') }}');
                if (r === true) {
                    return true;
                } else {
                    $(this).addClass('nosh-no-load');
                    return false;
                }
            });
            $('.nosh-click').css('cursor', 'pointer').click(function(){
                var url = $(this).attr('data-nosh-click');
                $('#modaltext').text('{{ trans('nosh.loading') }}...');
                $('#loadingModal').modal('show');
                window.location = url;
            });
            $('.nosh-click-view').css('cursor', 'pointer').click(function(){
                var url = $(this).attr('data-nosh-click');
                $('#modaltext').text('{{ trans('nosh.loading') }}...');
                $('#loadingModal').modal('show');
                $.ajax({
                    type: 'POST',
                    url: url,
                    data: 'pid=' + $(this).attr('data-nosh-pid') + '&table=' + $(this).attr('data-nosh-table') + '&index=' + $(this).attr('data-nosh-index') + '&id=' + $(this).attr('data-nosh-id'),
                    encode: true
                }).done(function(response) {
                    $('#warningModal_body').html(response);
                    $('#warningModal').modal('show');
                    $('#loadingModal').modal('hide');
                });
            });
            $('a').css('cursor', 'pointer').on('click', function(event) {
                if ($(this).attr('href') !== undefined) {
                    if ($(this).attr('id') == 'nosh_messaging_add_photo' || $(this).hasClass('nosh-photo-delete') || $(this).hasClass('nosh-photo-delete-t-message') || $(this).attr('id') == 'nosh_t_message_add_action' || $(this).attr('id') == 'nosh_t_message_add_photo') {
                        event.preventDefault();
                        var formData = $('#messaging_form').serialize();
                        var formUrl = noshdata.messaging_session;
                        if ($(this).hasClass('nosh-photo-delete-t-message') || $(this).attr('id') == 'nosh_t_message_add_action' || $(this).attr('id') == 'nosh_t_message_add_photo') {
                            formData = $('#t_messages_form').serialize();
                            formUrl = noshdata.t_messaging_session;
                        }
                        var action = $(this).attr('href');
                        $('#loadingModal').modal('show');
                        $.ajax({
                            type: 'POST',
                            url: formUrl,
                            data: formData,
                            encode: true,
                            async: false
                        }).done(function(response) {
                            window.location = action;
                        });
                    }
                    if ($(this).attr('href').search('#') == -1 && $(this).hasClass('nosh-no-load') === false) {
                        $('#modaltext').text('{{ trans('nosh.loading') }}...');
                        $('#loadingModal').modal('show');
                    }
                    if ($(this).hasClass('nosh-no-load') === true && $(this).hasClass('nosh-delete')) {
                        $(this).removeClass('nosh-no-load');
                    }
                    if ($(this).hasClass('nosh-schedule')) {
                        event.preventDefault();
                        $.cookie('nosh-schedule', $(this).attr('nosh-schedule-date'), { path: '/' });
                        window.location = $(this).attr('href');
                    }
                }
            });
            $('.nosh-button-submit').css('cursor', 'pointer');
            // CSS
            $('.nosh_textarea_short').css('height', '80px');
            $('.carousel-caption').css('background', 'rgba(0,0,0,0.5)');
            // Masks
            $('.nosh_phone').mask('(999) 999-9999');
            $("#upin").mask("aa9999999");
            $("#tax_id").mask("99-9999999");
            // DateTime
            $('.nosh-datetime').datetimepicker({
                format: 'YYYY-MM-DD hh:mm A'
            });
            $('.nosh-time').datetimepicker({
                format: 'hh:mm A'
            });
            $('.nosh-time1').datetimepicker({
                format: 'hh:mmA'
            });
            // Typeahead search
            $('.nosh-typeahead').each(function() {
                var source_url = $(this).attr('data-nosh-typeahead');
                var id = $(this).attr('id');
                $('#' + id).prop('disabled', true);
                $('#' + id).addClass('loading');
                $.ajax({
                    type: 'POST',
                    url: source_url,
                    dataType: 'json',
                    encode: true
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
                                var submatch = '';
                                if (matches) {
                                    submatch = matches[1];
                                }
                                $('#address_id').val(submatch);
                                var newval = val.replace(' [' + submatch + ']', '');
                                // console.log(newval);
                                $('#' + id).val(newval);
                            }
                        }
                    });
                    $('#' + id).removeClass('loading');
                    $('#' + id).prop('disabled', false);
                    if (id == 'search_rx') {
                        $('#' + id).focus();
                    }
                });
            });
            // Tags
            $('.nosh-tags').each(function() {
                var id = $(this).attr('id');
                $('#' + id).prop('disabled', true);
                $('#' + id).addClass('loading');
                $.ajax({
                    type: 'POST',
                    url: noshdata.tags_url,
                    dataType: 'json',
                    encode: true
                }).done(function(response) {
                    $('#' + id).tagsinput({
                        typeahead: {
                            source: response,
                            afterSelect: function(val) { this.$element.val(""); },
                        }
                    });
                    $('#' + id).removeClass('loading');
                    $('#' + id).prop('disabled', false);
                });
            });
            $('.nosh-tags').on('itemAdded', function(event) {
                var source_url = $(this).attr('data-nosh-add-url');
                $.ajax({
                    type: 'POST',
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
            $('#form_options').tagsinput();
            // Template buttons
            if ($('textarea').length && $('#template_panel').length) {
                var width = $('textarea').width();
                $('textarea').wrap('<div class="textarea_wrap" style="position:relative;width:100%"></div>');
                $('.textarea_wrap').append('<a href="#template_list" class="btn hidden template_click_show" style="position:absolute;right:15px;top:10px;width:35px;"><i class="fa fa-heart fa-lg" style="width:30px;color:red;"></i></a>');
            }
            $('.template_click_show').css('cursor', 'pointer').on('click', function() {
                var id = $(this).prev().attr('id');
                $('#template_back').attr('href', '#' + id);
            });
            // Tagsinput
            $('.tagsinput_select').each(function() {
                var id = $(this).attr('id');
                $('#' + id).prop('disabled', true);
                $('#' + id).addClass('loading');
                var source_url = $(this).attr('data-nosh-tagsinput');
                $.ajax({
                    type: 'POST',
                    url: source_url,
                    dataType: 'json',
                    encode: true
                }).done(function(response) {
                    $('#' + id).tagsinput({
                        typeahead: {
                            source: response,
                            afterSelect: function(val) { this.$element.val(""); },
                        }
                    });
                    if ($('#orders_form').length) {
                        if ($('#' + id).val().length !== 0) {
                            $('.nosh-button-submit').prop('disabled', false);
                        } else {
                            $('.nosh-button-submit').prop('disabled', true);
                        }
                    }
                    $('#' + id).on('itemAdded', function(event) {
                        if ($('#orders_form').length) {
                            $('.nosh-button-submit').prop('disabled', false);
                        }
                    }).on('itemRemoved', function(event) {
                        if ($('#orders_form').length) {
                            if ($('#' + id).val().length === 0) {
                                $('.nosh-button-submit').prop('disabled', true);
                            }
                        }
                    });
                    $('#' + id).prev().find('input').attr('placeholder', '{{ trans('nosh.tag_placeholder') }}...');
                    $('#' + id).removeClass('loading');
                    $('#' + id).prop('disabled', false);
                });
            });
            // Template toolbox
            $('#template_input_type').change(function() {
                if ($(this).val() === 'select' || $(this).val() === 'checkbox' || $(this).val() === 'radio' || $(this).val() === 'orders') {
                    if ($(this).val() !== 'orders') {
                        $('#template_options').tagsinput();
                        $('#template_options_div').show();
                    } else {
                        $('#template_options_orders_facility').val($('#template_group_name').val());
                        $('#template_options_orders_cpt').tagsinput();
                        $('#template_options_orders_loinc').tagsinput();
                        $('#template_options_orders_results_code').tagsinput();
                        $('#template_options_orders_div').show();
                    }
                } else {
                    $('#template_options').val('');
                    $('#template_options').tagsinput('destroy');
                    $('#template_options_orders_facility').val('');
                    $('#template_options_orders_cpt').val('');
                    $('#template_options_orders_cpt').tagsinput('destroy');
                    $('#template_options_orders_loinc').val('');
                    $('#template_options_orders_loinc').tagsinput('destroy');
                    $('#template_options_orders_results_code').val('');
                    $('#template_options_orders_results_code').tagsinput('destroy');
                    $('#template_options_div').hide();
                    $('#template_options_orders_div').hide();
                }
            });
            // Downloads
            if (noshdata.print_now !== '') {
                window.location = noshdata.print_now;
            }
            if (noshdata.download_now !== '') {
                $('#modaltext').text('{{ trans('nosh.creating_document') }}...');
                $('#loadingModal').modal('show');
                $.fileDownload(noshdata.download_now, {
                    successCallback: function (url) {
                        $('#loadingModal').modal('hide');
                    }
                });
            }
            if (noshdata.download_progress !== '') {
                $('#progressmodaltext').text('{{ trans('nosh.creating_document') }}...');
                $('#progressModal').modal('show');
                $.fileDownload(noshdata.download_progress, {
                    prepareCallback: function (url) {
                        noshdata.progress_id = noshdata.download_progress.split('/').pop();
                        setTimeout(progressbartrack, 5000);
                    },
                    successCallback: function (url) {
                        $('#progressModal').modal('hide');
                        $('#progressdata').attr('area-valuenow', '0').text('0' + "%");
                    }
                });
            }
            $('#overviewModal_trigger').css('cursor', 'pointer').click(function() {
                if ($('#overviewModal').length) {
                    return true;
                } else {
                    window.location = noshdata.patient_url;
                }
            });
            // focus
            if ($('.nosh-form').length) {
                if ($('.nosh-form').prev().hasClass('panel-container')) {
                    $('.nosh-form').prev().find('form').find('input').focus();
                } else {
                    $('.nosh-form :input:visible:enabled:first').focus();
                }
            }
        });

        $(document).on('submit', '.nosh-form', function(event) {
            var formId = $(this).attr('id');
            var bValid = true;
            $('#' + formId).find('[required]').each(function() {
                var type = $(this).attr('type');
                if (type !== 'hidden') {
                    var input_id = $(this).attr('id');
                    var id1 = $('#' + input_id);
                    var text = $("label[for='" + input_id + "']").html();
                    bValid = bValid && checkEmpty(id1, text);
                }
            });
            if (bValid) {
                $('#modaltext').text('{{ trans('nosh.loading') }}...');
                $('#loadingModal').modal('show');
                return;
            } else {
                event.preventDefault();
            }
        });

        // Search and schedule functions
        $(document).on('submit', '.form', function(event) {
            event.preventDefault();
            var formId = $(this).attr('id');
            if (formId == 'event_form') {
                if ($('#eventModal_title').text() === '{{ trans('nosh.new_appointment') }}' || $('#eventModal_title').text() === '{{ trans('nosh.edit_appointment') }}') {
                    if ($('#pid').val() === '') {
                        toastr.error('{{ trans('nosh.patient_not_selected') }}');
                        return false;
                    }
                }
            }
            var bValid = true;
            $('#' + formId).find('[required]').each(function() {
                var input_id = $(this).attr('id');
                var id1 = $('#' + input_id);
                var text = $("label[for='" + input_id + "']").html();
                bValid = bValid && checkEmpty(id1, text);
            });
            if (bValid) {
                var formData = $(this).serialize();
                var formUrl = $(this).attr('action');
                var formTarget = $(this).attr('data-nosh-target');
                var lastInput = $(this).find('input:last').attr('id');
                var searchTo= $(this).attr('data-nosh-search-to');
                $('#modaltext').text('{{ trans('nosh.searching') }}...');
                if (formId == 'event_form') {
                    $('#modaltext').text('{{ trans('nosh.calendar_event') }}...');
                }
                $('#loadingModal').modal('show');
                $.ajax({
                    type: 'POST',
                    url: formUrl,
                    data: formData,
                    dataType: 'json',
                    encode: true
                }).done(function(response) {
                    var $target = $('#' + formTarget);
                    var html = '';
                    if (response.response == 'false') {
                        html = '{{ trans('nosh.no_results') }}';
                        $target.html(html);
                    }
                    if (response.response == 'div') {
                        $target.html('');
                        $.each(response.message, function (i, val) {
                            if (val.value !== null) {
                                if (typeof val.category_id !== 'undefined') {
                                    $target.removeClass('list-group');
                                    $target.addClass('list-category text-primary');
                                    if ($('#' + val.category_id).length === 0) {
                                        $target.append('<h5 class="list-category-title">' + val.category + '</h3><ul id="' + val.category_id + '" class="list-group"></ul>');
                                    }
                                }
                                html += '<a href="' + val.href + '" class="list-group-item';
                                if (typeof val.icd10type !== 'undefined') {
                                    if (val.icd10type == '0') {
                                        html += ' nosh-icd10 list-group-item-danger';
                                    } else {
                                        html += ' nosh-icd10 list-group-item-success';
                                    }
                                }
                                if (typeof val.ptactive !== 'undefined') {
                                    if (val.ptactive != '1') {
                                        html += ' list-group-item-danger';
                                    }
                                }
                                html += '" data-nosh-value="' + val.value +'" data-nosh-id="' + val.id + '">' + val.label + '</a>';
                                if (typeof val.category_id !== 'undefined') {
                                    $('#'+ val.category_id).append(html);
                                    html = '';
                                }
                            }
                        });
                        if (html !== '') {
                            $target.addClass('list-group');
                            $target.html(html);
                            $('.list-group-item').first().focus();
                        }
                    }
                    if (response.response == 'li') {
                        $target.html('');
                        $.each(response.message, function (i, val) {
                            if (val.value !== null) {
                                if (typeof val.category_id !== 'undefined') {
                                    $target.removeClass('list-group');
                                    $target.addClass('list-category text-primary');
                                    if ($('#' + val.category_id).length === 0) {
                                        $target.append('<h5 class="list-category-title">' + val.category + '</h3><ul id="' + val.category_id + '" class="list-group"></ul>');
                                    }
                                }
                                html += '<li class="list-group-item';
                                if (searchTo !== undefined) {
                                    html += ' nosh-search-to';
                                }
                                if (typeof val.icd10type !== 'undefined') {
                                    if (val.icd10type == '0') {
                                        html += ' nosh-icd10 list-group-item-danger';
                                    } else {
                                        html += ' nosh-icd10 list-group-item-success';
                                    }
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
                                if (typeof val.rxcui !== 'undefined') {
                                    html += ' data-nosh-rxcui="' + val.rxcui + '"';
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
                                if (typeof val.category_id !== 'undefined') {
                                    html += ' data-nosh-category="' + val.category_id + '"';
                                }
                                if (typeof val.cvx !== 'undefined') {
                                    html += ' data-nosh-cvx="' + val.cvx + '"';
                                }
                                if (typeof val.lot !== 'undefined') {
                                    html += ' data-nosh-lot="' + val.lot + '"';
                                }
                                if (typeof val.manufacturer !== 'undefined') {
                                    html += ' data-nosh-manufacturer="' + val.manufacturer + '"';
                                }
                                if (typeof val.expire !== 'undefined') {
                                    html += ' data-nosh-expire="' + val.expire + '"';
                                }
                                if (typeof val.code !== 'undefined') {
                                    html += ' data-nosh-code="' + val.code + '"';
                                }
                                if (typeof val.url !== 'undefined') {
                                    html += ' data-nosh-url="' + val.url + '"';
                                }
                                if (formId == 'search_loinc_form') {
                                    html += ' data-nosh-loinc="' + val.id + '"';
                                }
                                if (typeof val.icd10type !== 'undefined') {
                                    if (val.icd10type == '0') {
                                        html += ' data-toggle="tooltip" title="{{ trans('nosh.click_to_expand') }}"';
                                    }
                                }
                                html +='>' + val.label + '</li>';
                                if (typeof val.category_id !== 'undefined') {
                                    $('#'+ val.category_id).append(html);
                                    html = '';
                                }
                            }
                        });
                        if (html !== '') {
                            $target.addClass('list-group');
                            $target.html(html);
                        }
                    }
                    if (response.response == 'schedule') {
                        $('#eventModal').modal('hide');
                        $('#event_form').clearForm();
                        $('#patient_name').html('');
                        $('#event_delete').show();
                        $('#calendar').fullCalendar('removeEvents');
                        $('#calendar').fullCalendar('refetchEvents');
                    }
                    $('#loadingModal').modal('hide');
                });
            }
        });

        // $(document).on('keydown', '.search', function(event){
        //     var exclude = ['search_patient', 'search_rx', 'search_icd', 'search_cpt', 'search_loinc', 'search_patient1', 'search_specialty', 'search_healthwise', 'search_language'];
        //     var value = $(this).val();
        //     if(event.keyCode==13) {
        //         event.preventDefault();
        //         if ($('.typeahead').is(':visible') === false) {
        //             $('#'+$(this).closest('form').attr('id')).submit();
        //         }
        //     } else {
        //         if (value && value.length > 2 && $.inArray($(this).attr('id'), exclude) <= -1) {
        //             $('#'+$(this).closest('form').attr('id')).submit();
        //         }
        //     }
        // });

        $(document).on('click', '#search_patient_recent', function(event) {
            $.ajax({
                type: 'POST',
                url: noshdata.search_patient_history,
                dataType: 'json',
                encode: true
            }).done(function(response) {
                var $target = $('#search_patient_results');
                var html = '';
                if (response.response == 'false') {
                    html = 'No results.';
                    $target.html(html);
                }
                if (response.response == 'div') {
                    $.each(response.message, function (i, val) {
                        if (val.value !== null) {
                            html += '<a href="' + val.href + '" class="list-group-item" data-nosh-value="' + val.value +'" data-nosh-id="' + val.id + '">' + val.label + '</a>';
                        }
                    });
                    $target.html(html);
                    $('.list-group-item').first().focus();
                }
            });
        });

        $(document).on('click', '.nosh-search-to', function(event) {
            var target = $(this).attr('data-nosh-search-to');
            var value = $(this).attr('data-nosh-value');
            var $this = $(this);
            var text_data = '';
            var proceed = true;
            if ($('#' + target).is('textarea')) {
                if ($('#' + target).val().endsWith("\n") === false) {
                    if ($('#' + target).val() !== '') {
                        $('#' + target).val($('#' + target).val() + "\n");
                    }
                }
                var terms = split($('#' + target).val());
                terms.pop();
                terms.push(value);
                terms.push("");
                $('#' + target).val(terms.join("\n"));
            } else {
                if ($('#' + target).hasClass('tagsinput_select')) {
                    $('#' + target).tagsinput('add', value);
                } else {
                    if ($(this).hasClass('nosh-icd10')) {
                        if ($(this).hasClass('list-group-item-success') || $(this).hasClass('list-group-item-info')) {
                            // GYN 20181006: Parse out assessment and icd code
                            if (target.includes('_')) { // GYN 20181008: Only parse if target textbox contains '_'
	                            $('#' + target).val(value.split(' [', 1));
								var target_icd = target.replace("_", "_icd");
								if ($('#' + target_icd).length != 0) { // GYN 20181008: Only if target_icd exists
									$('#' + target.replace("_", "_icd")).val($(this).attr('data-nosh-id'));
								}
							}
							else {
	                            $('#' + target).val(value);
							}
                        } else {
                            proceed = false;
                            var list_item = $(this).attr('id');
                            $.ajax({
                                type: 'POST',
                                url: noshdata.search_icd_specific,
                                data: 'icd=' + $(this).attr('data-nosh-id'),
                                dataType: 'json',
                                encode: true
                            }).done(function(response) {
                                var html = '';
                                $.each(response, function (i, val) {
                                    var proceed1 = 0;
                                    $this.siblings().each(function() {
                                        if ($(this).attr('data-nosh-id') == val.id) {
                                            proceed1++;
                                        }
                                    });
                                    if (proceed1 === 0) {
                                        html += '<li class="list-group-item nosh-search-to nosh-icd10 list-group-item-info';
                                        html +='" data-nosh-value="' + val.value +'" data-nosh-id="' + val.id + '"';
                                        html += ' data-nosh-search-to="' + target + '"';
                                        html +='>' + val.label + '</li>';
                                    }
                                });
                                if (html === '') {
                                    toastr.error('{{ trans('nosh.more_specific_code') }}');
                                }
                                $this.after(html);
                                $this.remove();
                            });
                        }
                    } else {
                        $('#' + target).val(value).trigger('change');
                        if (target == 'address_id') {
                            var label = $(this).text();
                            $('.nosh-data-address').find('input').val(label);
                        }
                    }
                }
            }
            if ($(this).attr('data-nosh-dosage') !== undefined) {
                $('#rxl_dosage').val($(this).attr('data-nosh-dosage'));
                if ($(this).attr('data-nosh-dosage').indexOf(';') === -1) {
                    $('#calc_med_amount').val($(this).attr('data-nosh-dosage'));
                } else {
                    var dosage_parts = $(this).attr('data-nosh-dosage').split(";");
                    $('#calc_med_amount').val(dosage_parts[0]);
                }
            }
            if ($(this).attr('data-nosh-unit') !== undefined) {
                $('#rxl_dosage_unit').val($(this).attr('data-nosh-unit'));
                var unit_parts = $(this).attr('data-nosh-unit').split("/");
                $('#calc_med_amount_unit option').filter(function() {
                    return this.text == unit_parts[0].toLowerCase();
                }).attr('selected', true);
                if (unit_parts.length == 2) {
                    $('#calc_med_volume').val('1');
                    $('#calc_med_volume_unit option').filter(function() {
                        return this.text == unit_parts[1].toLowerCase();
                    }).attr('selected', true);
                }
                $('#unit').val($(this).attr('data-nosh-unit'));
                noshdata.billing_unit = $(this).attr('data-nosh-unit');
            }
            if ($(this).attr('data-nosh-rxcui') !== undefined) {
                $.ajax({
                    type: 'POST',
                    url: noshdata.search_ndc,
                    data: 'rxcui=' + $(this).attr('data-nosh-rxcui'),
                    success: function(data){
                        $('#rxl_ndcid').val(data);
                    }
                });
                $.ajax({
                    type: 'POST',
                    url: noshdata.search_interactions,
                    data: 'rxl_medication=' + value + '&rxcui=' + $(this).attr('data-nosh-rxcui'),
                    dataType: 'json',
                    success: function(data){
                        $('#warningModal_body').html(data.info);
                        text_data = '<div class="col-md-2 col-md-offset-5"><button id="warning" class="btn btn-default btn-block">{{ trans('nosh.button_learn_more') }}</button></div>';
                        toastr.error(text_data, '{{ trans('nosh.medication_interactions') }}', {"timeOut":"20000","preventDuplicates":true,"preventOpenDuplicates":true});
                        $('#warning').css('cursor', 'pointer').on('click', function(){
                            // toastr.clear();
                            $('#warningModal').modal('show');
                        });
                    }
                });
            }
            if ($(this).attr('data-nosh-ptname') !== undefined) {
                $('#title').val($(this).attr('data-nosh-ptname'));
                if ($('#patient_name').is('input')) {
                    $('#patient_name').val($(this).attr('data-nosh-ptname'));
                } else {
                    $('#patient_name').text($(this).attr('data-nosh-ptname'));
                }
            }
            if ($(this).attr('data-nosh-insurance_plan_name') !== undefined) {
                $('#insurance_plan_name').val($(this).attr('data-nosh-insurance_plan_name'));
            }
            if ($(this).attr('data-nosh-charge') !== undefined) {
                $('#cpt_charge').val($(this).attr('data-nosh-charge'));
                noshdata.billing_charge = $(this).attr('data-nosh-charge');
            }
            if ($(this).attr('data-nosh-category') !== undefined) {
                if ($(this).attr('data-nosh-category') == 'practice_cpt_result') {
                    $.ajax({
                        type: 'POST',
                        url: noshdata.check_cpt,
                        data: 'cpt=' + value,
                        success: function(data){
                            if (data == 'y') {
                                text_data = '<div class="col-md-2 col-md-offset-5"><button data-nosh-type="favorite" class="btn btn-default btn-block add_cpt" data-nosh-value="' + value + '">{{ trans('nosh.button_add_favorites') }}</button></div>';
                                toastr.success(text_data, '{{ trans('nosh.add_cpt1') }}' + value + '{{ trans('nosh.add_cpt2') }}', {"timeOut":"20000","preventDuplicates":true});
                                noshdata.toastr_collide = '1';
                            }
                        }
                    });
                }
                if ($(this).attr('data-nosh-category') == 'universal_cpt_result') {
                    text_data = '<div class="col-md-2 col-md-offset-5"><button data-nosh-type="favorite" class="btn btn-default btn-block add_cpt" data-nosh-value="' + value + '">{{ trans('nosh.button_add_favorites') }}</button>';
                    text_data += '<button data-nosh-type="practice" class="btn btn-default btn-block add_cpt" data-nosh-value="' + value + '">{{ trans('nosh.button_add_practice') }}</button></div>';
                    toastr.success(text_data, '{{ trans('nosh.add_cpt1') }}' + value + '{{ trans('nosh.add_cpt3') }}', {"timeOut":"20000","preventDuplicates":true});
                }
            }
            if ($(this).attr('data-nosh-cvx') !== undefined) {
                $('#imm_cvxcode').val($(this).attr('data-nosh-cvx'));
            }
            if ($(this).attr('data-nosh-lot') !== undefined) {
                $('#imm_lot').val($(this).attr('data-nosh-lot'));
            }
            if ($(this).attr('data-nosh-manufacturer') !== undefined) {
                $('#imm_manufacturer').val($(this).attr('data-nosh-manufacturer'));
            }
            if ($(this).attr('data-nosh-expire') !== undefined) {
                $('#imm_expiration').val($(this).attr('data-nosh-expire'));
                $('#nosh_action').val('inventory');
            }
            if ($(this).attr('data-nosh-code') !== undefined) {
                $('#npi_taxonomy').val($(this).attr('data-nosh-code'));
                $('#lang_code').val($(this).attr('data-nosh-code'));
                $('#guardian_code').val($(this).attr('data-nosh-code'));
            }
            if ($(this).attr('data-nosh-url') !== undefined) {
                $('#url').val($(this).attr('data-nosh-url'));
                $('#modaltext').text('Loading...');
                $('#loadingModal').modal('show');
                $.ajax({
                    type: 'POST',
                    url: noshdata.education,
                    data: 'url=' + $(this).attr('data-nosh-url'),
                    encode: true
                }).done(function(response) {
                    $('#warningModal_body').html(response);
                    $('#warningModal').modal('show');
                    $('#loadingModal').modal('hide');
                });
            }
            if ($(this).attr('data-nosh-loinc') !== undefined) {
                $('#test_code').val($(this).attr('data-nosh-loinc'));
            }
            if (proceed === true) {
                $(this).parent().prev().children().first().val('');
                if ($(this).parent().is('div')) {
                    $(this).parent().html('');
                } else {
                    $(this).parent().parent().removeClass('list-category text-primary').addClass('list-group').html('');
                }
                $('#' + target).focus();
            }
        });

        $(document).on('click', '.nosh-search-clear', function(event) {
            var target = $(this).closest('form').attr('data-nosh-target');
            var input = $(this).closest('form').children().first().attr('id');
            if ($('#' + target).hasClass('list-category')) {
                $('#' + target).removeClass('list-category text-primary').addClass('list-group').html('');
            } else {
                $('#' + target).html('');
            }
            $('#' + input).val('');
        });

        $(document).on('click', '.nosh-search-favorite', function(event) {
            $(this).parent().prev().val('***');
            $(this).closest('form').trigger('submit');
            $(this).parent().prev().val('');
        });

        // Template functions
        $(document).on('click', 'textarea', function(event) {
            var id = $(this).attr('id');
            if (id !== 'template_text') {
                load_template_group(id);
                $('.template_click_show').removeClass('visible-xs-block visible-sm-block');
                $('.template_click_show').addClass('hidden');
                $(this).next().removeClass('hidden');
                $(this).next().addClass('visible-xs-block visible-sm-block');
            }
        });

        $(document).on('click', '.nosh-template-input', function(event) {
            if ($(this).attr('type') == 'text' || $(this).is('select')) {
                $(this).parent().parent().parent().addClass('active');
            } else {
                $(this).parent().parent().parent().parent().addClass('active');
            }
            checktemplatestatus();
        });

        $(document).on('blur', '.nosh-template-input', function(event) {
            if ($(this).val() !== '') {
                if ($(this).attr('type') == 'text' || $(this).is('select')) {
                    $(this).parent().parent().parent().addClass('active');
                } else {
                    $(this).parent().parent().parent().parent().addClass('active');
                }
            }
            checktemplatestatus();
        });

        $(document).on('click', '#template-all-items', function(event) {
            $('.template-item').each(function() {
                $(this).parent().addClass('active');
            });
            $('#template_back_text').text('Copy');
        });

        $(document).on('click', '.template-group', function(event) {
            var id = $('#template_target').val();
            var group = $(this).text();
            $('#template_group').val(group);
            $('#template_input').addClass('loading');
            $('#template_input').prop('disabled', true);
            load_template_items(id, group);
        });

        $(document).on('click', '.template-item', function(event){
            if (event.target !== this) {
                return true;
            } else {
                if ($(this).parent().hasClass('active')) {
                    $(this).parent().removeClass('active');
                    checktemplatestatus();
                    return true;
                }
                $(this).parent().addClass('active');
                checktemplatestatus();
            }
        });

        $(document).on('click', '#template-add', function(event){
            if ($(this).attr('data-original-title') == 'Add Group') {
                $('#template_edit_type').val('group');
                $('#template_category').val($('#template_target').val());
                $('#template_text').attr('placeholder', 'Group Name');
            } else {
                $('#template_edit_type').val('item');
                $('.template_item_divs').show();
                $('#template_id').val('new');
                $('#template_group_name').val($('#template_group').val());
                $('#template_category').val($('#template_target').val());
                $('#template_text').attr('placeholder', '{{ trans('nosh.template_text_placeholder') }}');
            }
            $('#templateModal').modal('show');
            $('#template_text').focus();
        });

        $(document).on('click', '.template-group-edit', function(event){
            $('#template_edit_type').val('group');
            $('#template_text').val($(this).parent().prev().clone().children().remove().end().text());
            $('#template_group_name').val($(this).parent().prev().attr('data-nosh-template-group'));
            $('#template_category').val($(this).parent().prev().attr('data-nosh-template-category'));
            $('#templateModal').modal('show');
            $('#template_text').attr('placeholder', '{{ trans('nosh.template_group_name') }}').focus();
        });

        $(document).on('click', '.template-edit', function(event){
            $('#template_edit_type').val('item');
            $('.template_item_divs').show();
            $('#template_text').val($(this).parent().prev().clone().children().remove().end().text());
            $('#template_id').val($(this).parent().prev().attr('data-nosh-template-id'));
            $('#template_group_name').val($(this).parent().prev().attr('data-nosh-template-group'));
            $('#template_category').val($(this).parent().prev().attr('data-nosh-template-category'));
            if ($(this).parent().prev().attr('data-nosh-input') !== undefined) {
                $('#template_input_type').val($(this).parent().prev().attr('data-nosh-input'));
            }
            if ($(this).parent().prev().attr('data-nosh-options') !== undefined) {
                $('#template_options').val($(this).parent().prev().attr('data-nosh-options'));
                $('#template_options').tagsinput();
                $('#template_options_div').show();
            }
            if ($(this).parent().prev().attr('data-nosh-orders-facility') !== undefined) {
                $('#template_options_orders_facility').val($(this).parent().prev().attr('data-nosh-orders-facility'));
                $('#template_options_orders_orders_code').val($(this).parent().prev().attr('data-nosh-orders-orders-code'));
                $('#template_options_orders_cpt').val($(this).parent().prev().attr('data-nosh-orders-cpt'));
                $('#template_options_orders_loinc').val($(this).parent().prev().attr('data-nosh-orders-loinc'));
                $('#template_options_orders_results_code').val($(this).parent().prev().attr('data-nosh-orders-results-code'));
                $('#template_options_orders_cpt').tagsinput();
                $('#template_options_orders_loinc').tagsinput();
                $('#template_options_orders_results_code').tagsinput();
                $('#template_options_orders_div').show();
            }
            if ($(this).parent().prev().attr('data-nosh-age') !== undefined) {
                $('#template_age').val($(this).parent().prev().attr('data-nosh-age'));
            }
            if ($(this).parent().prev().attr('data-nosh-gender') !== undefined) {
                $('#template_gender').val($(this).parent().prev().attr('data-nosh-gender'));
            }
            $('#templateModal').modal('show');
            $('#template_text').attr('placeholder', '{{ trans('nosh.template_text_placeholder') }}').focus();
        });

        $(document).on('click', '.template-default', function(event) {
            var group = $(this).next().attr('data-nosh-template-group');
            var category = $('#template_target').val();
            if (category !== '') {
                var old = $('#'+category).val();
                var delimiter = $('#template_delimiter').val();
                var input = '';
                var text = [];
                $.ajax({
                    type: 'POST',
                    url: noshdata.template_normal,
                    data: 'group_name=' + group + '&category=' + category,
                    dataType: 'json',
                    encode: true,
                }).done(function(data) {
                    $.each(data, function(index, value) {
                        text.push(value);
                    });
                    if (text.length > 0) {
                        var group_text = group + ': ';
                        var old_delimiter = '\n\n';
                        if (category == 'letter_body') {
                            group_text = '';
                            old_delimiter = delimiter;
                        }
                        if (category == 'orders_labs') {
                            group_text = '';
                        }
                        if (category !== 'body') {
                            if (old !== '') {
                                input += old + old_delimiter + group_text;
                            } else {
                                input += group_text;
                            }
                            input += text.join(delimiter);
                        } else {
                            input += text.join(delimiter);
                            if (old !== '') {
                                input += "\n" + old;
                            }
                        }
                        $("#"+category).val(input);
                    }
                    load_template_group(category);
                });
            } else {
                return true;
            }
        });

        $(document).on('click', '.template-normal-change', function(event){
            var id = $(this).parent().prev().attr('data-nosh-template-id');
            var group = $(this).parent().prev().attr('data-nosh-template-group');
            var category = $(this).parent().prev().attr('data-nosh-template-category');
            var normal = 'y';
            if ($(this).html() == '<i class="fa fa-star-o fa-lg"></i>') {
                $(this).html('<i class="fa fa-star fa-lg"></i>');
                normal = 'n';
            } else {
                $(this).html('<i class="fa fa-star-o fa-lg"></i>');
            }
            $.ajax({
                type: 'POST',
                url: noshdata.template_normal_change,
                data: 'id=' + id + '&group_name=' + group + '&category=' + category + '&template_normal_item=' + normal,
                encode: true,
                success: function(data){
                    toastr.success(data);
                }
            });
        });

        $(document).on('click', '.template-remove-group', function(event){
            if(confirm('Are you sure you want to delete this group?')) {
                var group = $(this).attr('data-nosh-template-group');
                var category = $('#template_target').val();
                $.ajax({
                    type: 'POST',
                    url: noshdata.template_remove,
                    data: 'group_name=' + group + '&category=' + category + '&template_edit_type=group',
                    encode: true,
                    success: function(data){
                        toastr.success(data);
                        load_template_group(category);
                    }
                });
            }
        });

        $(document).on('click', '.template-remove-item', function(event){
            if(confirm('Are you sure you want to delete this item?')) {
                var id = $(this).parent().prev().attr('data-nosh-template-id');
                var group = $(this).parent().prev().attr('data-nosh-template-group');
                var category = $(this).parent().prev().attr('data-nosh-template-category');
                $.ajax({
                    type: 'POST',
                    url: noshdata.template_remove,
                    data: 'id=' + id + '&group_name=' + group + '&category=' + category + '&template_edit_type=item',
                    encode: true,
                    success: function(data){
                        toastr.success(data);
                        load_template_items(category, group);
                    }
                });
            }
        });

        $(document).on('click', '#template_modal_save', function(event){
            var str = $('#template_toolbox_form').serialize();
            var id = $('#template_target').val();
            var group = $('#template_group_name').val();
            var type = $('#template_edit_type').val();
            $.ajax({
                type: 'POST',
                url: noshdata.template_edit,
                data: str,
                dataType: 'json',
                encode: true,
                success: function(data){
                    if (data.response == 'yes') {
                        $('#template_toolbox_form').clearForm();
                        $('#template_options').tagsinput('destroy');
                        $('.template_item_divs').hide();
                        $('#template_options_div').hide();
                        $('#template_options_orders_facility').val('');
                        $('#template_options_orders_cpt').val('');
                        $('#template_options_orders_cpt').tagsinput('destroy');
                        $('#template_options_orders_loinc').val('');
                        $('#template_options_orders_loinc').tagsinput('destroy');
                        $('#template_options_orders_results_code').val('');
                        $('#template_options_orders_results_code').tagsinput('destroy');
                        $('#template_options_div').hide();
                        $('#template_options_orders_div').hide();
                        $('#templateModal').modal('hide');
                        toastr.success(data.message);
                        if (type == 'item') {
                            load_template_items(id, group);
                        } else {
                            load_template_group(id);
                        }
                    } else {
                        toastr.error(data.message);
                    }
                }
            });
        });

        $(document).on('click', '#template_modal_cancel', function(event){
            $('#template_toolbox_form').clearForm();
            $('#template_options').tagsinput('destroy');
            $('.template_item_divs').hide();
            $('#template_options_div').hide();
            $('#template_options_orders_facility').val('');
            $('#template_options_orders_cpt').val('');
            $('#template_options_orders_cpt').tagsinput('destroy');
            $('#template_options_orders_loinc').val('');
            $('#template_options_orders_loinc').tagsinput('destroy');
            $('#template_options_orders_results_code').val('');
            $('#template_options_orders_results_code').tagsinput('destroy');
            $('#template_options_div').hide();
            $('#template_options_orders_div').hide();
            $('#templateModal').modal('hide');
        });

        $(document).on('click', '#template_back', function(event){
            var id = $('#template_target').val();
            if (id !== '') {
                var old = $('#'+id).val();
                var delimiter = $('#template_delimiter').val();
                var input = '';
                var text = [];
                $('.template-item').each(function() {
                    if ($(this).parent().hasClass('active')) {
                        var add_text = '';
                        $(this).find('.nosh-template-input').each(function() {
                            if ($(this).is(':checkbox') || $(this).is(':radio')) {
                                if ($(this).is(':radio')) {
                                    if ($(this).is(':checked')) {
                                        add_text = ': ' + $(this).val();
                                    }
                                } else {
                                    if ($(this).is(':checked')) {
                                        if (add_text === '') {
                                            add_text = ': ' + $(this).val();
                                        } else {
                                            add_text += ', ' + $(this).val();
                                        }
                                    }
                                }
                            } else if ($(this).is('select')) {
                                add_text = ': ' + $(this).val();
                            } else {
                                add_text = $(this).val();
                            }
                        });
                        text.push($(this).clone().children().remove().end().attr('data-nosh-value') + ' ' + add_text);
                    }
                });
                if (text.length > 0) {
                    var group = $('#template_group').val() + ': ';
                    var old_delimiter = '\n\n';
                    if (id == 'letter_body') {
                        group = '';
                        old_delimiter = delimiter;
                    }
                    if (id == 'orders_labs') {
                        group = '';
                    }
                    if (id !== 'body') {
                        if (old !== '') {
                            input += old + old_delimiter + group;
                        } else {
                            input += group;
                        }
                        input += text.join(delimiter);
                    } else {
                        input += text.join(delimiter);
                        if (old !== '') {
                            input += "\n" + old;
                        }
                    }
                    $("#"+id).val(input);
                }
                load_template_group(id);
            } else {
                return true;
            }
        });
        $(document).on('click', '#template_modal_copy', function(event) {
            var id = $('#template_target').val();
            $('#template_text').val($('#' + id).val());
        });

        // CPT functions
        $(document).on('click', '.add_cpt', function(event){
            $.ajax({
                type: 'POST',
                url: noshdata.add_cpt,
                data: 'cpt=' + $(this).attr('data-nosh-value') + '&type=' + $(this).attr('data-nosh-type'),
                success: function(data){
                    toastr.success('{{ trans('nosh.procedure_code_add') }}');
                }
            });
        });

        $(document).on('click', '#update_cpt', function(event){
            $.ajax({
                type: 'POST',
                url: noshdata.update_cpt,
                data: 'cpt=' + $('#cpt').val() + '&cpt_charge=' + $('#cpt_charge').val() + '&unit=' + $('#unit').val(),
                success: function(data){
                    toastr.success('{{ trans('nosh.procedure_code_update') }}');
                }
            });
        });

        $(document).on('click', '#add_new_cpt', function(event){
            $.ajax({
                type: 'POST',
                url: noshdata.add_cpt,
                data: 'cpt=' + $('#cpt').val() + '&cpt_charge=' + $('#cpt_charge').val() + '&unit=' + $('#unit').val(),
                success: function(data){
                    toastr.success('{{ trans('nosh.procedure_code_add') }}');
                }
            });
        });

        $(document).on('click', '.nosh-ccda-list', function(event){
            $('#modaltext').text('{{ trans('nosh.loading') }}...');
            $('#loadingModal').modal('show');
            var query = 'name=' + $(this).attr('data-nosh-name');
            query += '&type=' + $(this).attr('data-nosh-type');
            query += '&date=' + $(this).attr('data-nosh-date');
            if ($(this).attr('data-nosh-code') !== undefined) {
                query += '&code=' + $(this).attr('data-nosh-code');
            }
            if ($(this).attr('data-nosh-dosage') !== undefined) {
                query += '&dosage=' + $(this).attr('data-nosh-dosage');
            }
            if ($(this).attr('data-nosh-dosage-unit') !== undefined) {
                query += '&dosage-unit=' + $(this).attr('data-nosh-dosage-unit');
            }
            if ($(this).attr('data-nosh-route') !== undefined) {
                query += '&route=' + $(this).attr('data-nosh-route');
            }
            if ($(this).attr('data-nosh-reason') !== undefined) {
                query += '&reason=' + $(this).attr('data-nosh-reason');
            }
            if ($(this).attr('data-nosh-administration') !== undefined) {
                query += '&administration=' + $(this).attr('data-nosh-administration');
            }
            if ($(this).attr('data-nosh-reaction') !== undefined) {
                query += '&reaction=' + $(this).attr('data-nosh-reaction');
            }
            if ($(this).attr('data-nosh-sequence') !== undefined) {
                query += '&sequence=' + $(this).attr('data-nosh-sequence');
            }
            if ($(this).attr('data-nosh-from') !== undefined) {
                query += '&from=' + $(this).attr('data-nosh-from');
            }
            $.ajax({
                type: 'POST',
                url: noshdata.set_ccda_data,
                data: query,
                success: function(data){
                    window.location = data;
                }
            });
        });

        $('.country select').change(function(event){
            var country = $(this).val();
            $('.state select').removeOption(/./);
            $.ajax({
                type: 'POST',
                url: noshdata.get_state_data,
                data: 'country=' + country,
                dataType: 'json',
                success: function(data){
                    $('.state select').addOption(data, false);
                }
            });

        });
        $(document).on('click', '.print_summary', function(event){
            print_summary();
        });
    </script>
    {{-- <script src="{{ elixir('js/app.js') }}"></script> --}}
    @yield('view.scripts')
</body>
</html>
