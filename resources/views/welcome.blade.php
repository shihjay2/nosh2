@extends('layouts.app')

@section('content')
@if (isset($sidebar_content))
	<div class="container-fluid">
@else
	<div class="container">
@endif
	<div class="row">
		@if (isset($number_messages))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-primary nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-envelope fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_messages }}</div>
							<div>Messages</div>
						</div>
					</div>
				</div>
				<a href="{{ route('messaging', ['inbox']) }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($number_encounters))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-green nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-stethoscope fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_encounters }}</div>
							<div>Encounters to Complete</div>
						</div>
					</div>
				</div>
				<a href="{{ route('dashboard_encounters') }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($number_t_messages))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-green nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-phone fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_t_messages }}</div>
							<div>Telephone Messages</div>
						</div>
					</div>
				</div>
				<a href="{{ route('dashboard_t_messages') }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($number_appts))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-red nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-calendar fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_appts }}</div>
							<div>Appointments Today</div>
						</div>
					</div>
				</div>
				<a href="{{ route('schedule') }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($number_reminders))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-red nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-bell fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_reminders }}</div>
							<div>Reminders</div>
						</div>
					</div>
				</div>
				<a href="{{ route('dashboard_reminders') }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($number_scans))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-yellow nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-file-o fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_scans }}</div>
							<div>Scanned Documents</div>
						</div>
					</div>
				</div>
				<a href="{{ route('messaging', ['scans']) }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($number_faxes))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-yellow nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-fax fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_faxes }}</div>
							<div>Fax Messages</div>
						</div>
					</div>
				</div>
				<a href="{{ route('messaging', ['faxes']) }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($number_bills))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-purple nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-money fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_bills }}</div>
							<div>Bills to Process</div>
						</div>
					</div>
				</div>
				<a href="{{ route('financial', ['queue']) }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($number_tests))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-purple nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-flask fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge">{{ $number_tests }}</div>
							<div>Test Results to Review</div>
						</div>
					</div>
				</div>
				<a href="{{ route('dashboard_tests') }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($users_needed))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-red nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-user fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><i class="fa fa-exclamation-triangle"></i></div>
							<div>Users Needed</div>
						</div>
					</div>
				</div>
				<a href="{{ route('users', ['2']) }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($schedule_needed))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-red nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-calendar fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><i class="fa fa-exclamation-triangle"></i></div>
							<div>Schedule Configuration Needed</div>
						</div>
					</div>
				</div>
				<a href="{{ route('core_form', ['practiceinfo', 'practice_id', Session::get('practice_id'), 'schedule']) }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (isset($admin_ok))
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-green nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-smile-o fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><i class="fa fa-check"></i></div>
							<div>Good To Go!</div>
						</div>
					</div>
				</div>
				<a href="{{ route('dashboard') }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
		@if (route('dashboard') == 'https://cloud.noshchartingsystem.com/nosh')
		<div class="col-lg-3 col-md-3">
			<div class="panel panel-red nosh-dash">
				<div class="panel-heading">
					<div class="row">
						<div class="col-xs-3">
							<i class="fa fa-ban fa-5x"></i>
						</div>
						<div class="col-xs-9 text-right">
							<div class="huge"><i class="fa fa-cloud"></i></div>
							<div>Cancel Practice</div>
						</div>
					</div>
				</div>
				<a href="{{ route('practice_cancel') }}">
					<div class="panel-footer">
						<span class="pull-left">View Details</span>
						<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
						<div class="clearfix"></div>
					</div>
				</a>
			</div>
		</div>
		@endif
	</div>
	<div class="row">
		<div>
		<!-- <div class="col-md-10 col-md-offset-1"> -->
			@if (isset($content))
			<div class="panel panel-default">
				<div class="panel-heading clearfix">
					<h3 class="panel-title pull-left" style="padding-top: 7.5px;">{!! $title !!}</h3>
					@if (isset($back))
						<div class="pull-right">
							{!! $back !!}
						</div>
					@endif
				</div>
				<div class="panel-body">
					{!! $content !!}
				</div>
			</div>
			@endif
		</div>
	</div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
	$(document).ready(function() {
		if (noshdata.message_action !== '') {
			if (noshdata.message_action.search('Error - ') == -1) {
				toastr.success(noshdata.message_action);
			} else {
				toastr.error(noshdata.message_action);
			}
		}
		$('[data-toggle=offcanvas]').click(function() {
			$('.row-offcanvas').toggleClass('active');
		});
		if (noshdata.home_url == window.location.href) {
			$('#search_patient').focus();
		}
	});
</script>
@endsection
