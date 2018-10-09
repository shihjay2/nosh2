@extends('layouts.app')

@section('view.stylesheet')
	<style>
	html {
		position: relative;
		min-height: 100%;
	}
	body {
	/* Margin bottom by footer height */
		margin-bottom: 60px;
	}
	.footer {
		position: absolute;
		bottom: 0;
		width: 100%;
		/* Set the fixed height of the footer here */
		height: 60px;
		background-color: #f5f5f5;
	}
	.container .text-muted {
		margin: 20px 0;
	}
	.footer > .container {
		padding-right: 15px;
		padding-left: 15px;
	}
	</style>
@endsection

@section('content')
<div class="container" style="margin-bottom:100px">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">{{ trans('nosh.login_heading') }}</div>
                <div class="panel-body">
                    <div style="text-align: center;">
                        <div style="text-align: center;">
                            <div id="login_practice_logo">
                                <i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
                            </div>
                            @if ($errors->has('tryagain'))
                                <div class="form-group has-error">
                                    <span class="help-block has-error">
                                        <strong>{{ $errors->first('tryagain') }}</strong>
                                    </span>
                                </div>
                            @endif
                            @if (isset($attempts))
                                <div class="form-group has-error">
                                    <span class="help-block has-error">
                                        <strong>{{ $attempts }}</strong>
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if (isset($pnosh_provider))
                        @if ($pnosh_provider == 'n')
                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-3">
                                    <a class="btn btn-primary btn-block" href="{{ url('/') }}">
                                        <i class="fa fa-btn fa-openid"></i> {{ trans('nosh.button_pnosh_login') }}
                                    </a>
                                    <br><br><a href="#" id="show_login_form">{{ trans('nosh.button_pnosh_admin') }}</a><br><br>
                                </div>
                            </div>
                        @else
                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-3">
									<a class="btn btn-primary btn-block" href="{{ url('/') }}">
                                        <i class="fa fa-btn fa-openid"></i> {{ trans('nosh.button_pnosh_login_with') }} HIE of One
                                    </a>
                                    <a class="btn btn-primary btn-block" href="{{ url('/oidc') }}">
                                        <i class="fa fa-btn fa-openid"></i> {{ trans('nosh.button_pnosh_login_with') }} mdNOSH
                                    </a>
                                    <a class="btn btn-primary btn-block" href="{{ url('/google_auth') }}">
                                        <i class="fa fa-btn fa-google"></i> {{ trans('nosh.button_pnosh_login_with') }} Google
                                    </a>
                                </div>
                            </div>
                        @endif
                    @endif
                    @if (isset($login_form))
                        @if ($login_form == 'y')
                            <form id="login_form" class="form-horizontal" role="form" method="POST" action="{{ url('/login') }}">
                        @else
                            <form id="login_form" class="form-horizontal" role="form" method="POST" action="{{ url('/login') }}" style="display:none;">
                        @endif
                            {{ csrf_field() }}

                            <div class="form-group{{ $errors->has('username') ? ' has-error' : '' }}">
                                <label for="username" class="col-md-4 control-label">{{ trans('nosh.username') }}</label>

                                <div class="col-md-6">
                                    <input id="username" type="text" class="form-control" name="username" value="{{ old('username') }}">

                                    @if ($errors->has('username'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('username') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                                <label for="password" class="col-md-4 control-label">{{ trans('nosh.password') }}</label>

                                <div class="col-md-6">
                                    <input id="password" type="password" class="form-control" name="password">

                                    @if ($errors->has('password'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('password') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if (isset($practice_list))
                                <div class="form-group{{ $errors->has('practice_id') ? ' has-error' : '' }}">
                                    <label for="password" class="col-md-4 control-label"> {{ trans('nosh.organization_practice') }}</label>

                                    <div class="col-md-6">
                                        <select id="practice_id" class="form-control" name="practice_id" value="{{ old('practice_id') }}">{!! $practice_list !!}</select>

                                        @if ($errors->has('practice_id'))
                                            <span class="help-block">
                                                <strong>{{ $errors->first('practice_id') }}</strong>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="remember"> {{ trans('nosh.remember_me') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fa fa-btn fa-sign-in"></i> {{ trans('nosh.login_heading') }}
                                    </button>
                                    <a class="btn btn-primary btn-block" href="{{ url('/password_email') }}">{{ trans('nosh.forgot_password') }}</a>
                                    @if ($patient_centric == 'n' && $demo == 'n')
                                        <a class="btn btn-primary btn-block" href="#" id="register">{{ trans('nosh.new_patient_portal') }}</a>
                                    @endif
                                </div>
                            </div>

							<div class="form-group">
								<div class="col-md-6 col-md-offset-4">
									<button type="button" class="btn btn-primary btn-block" id="connectUportBtn" onclick="loginBtnClick()">
										<img src="{{ asset('assets/uport-logo-white.svg') }}" height="25" width="25" style="margin-right:5px"></img> {{ trans('nosh.login_uport') }}
									</button>
								</div>
							</div>
                        </form>
                        @if ($errors->has('registration_code') || $errors->has('lastname') || $errors->has('firstname') || $errors->has('dob') || $errors->has('email') || $errors->has('username1') || $errors->has('numberReal'))
                            <form id="register_form" class="form-horizontal" role="form" method="POST" action="{{ url('register_user') }}">
                        @else
                            <form id="register_form" class="form-horizontal" role="form" method="POST" action="{{ url('register_user') }}" style="display:none;">
                        @endif
                            {{ csrf_field() }}
                            <input type="hidden" name="count" id="new_password_count" value="" />
                            <input type="hidden" name="practice_id" id="register_practice_id" value="" />

                            <div class="well">
                                <p>{{ trans('nosh.instruct_patient_portal1') }}</p>
                                <p>{{ trans('nosh.instruct_patient_portal2') }}</p>
                                <p>{{ trans('nosh.instruct_patient_portal3') }}</p>
                                <p>{{ trans('nosh.instruct_patient_portal4') }}</p>
                            </div>

                            <div class="form-group{{ $errors->has('lastname') ? ' has-error' : '' }}">
                                <label for="lastname" class="col-md-4 control-label">{{ trans('nosh.lastname') }}</label>

                                <div class="col-md-6">
                                    <input id="lastname" type="text" class="form-control" name="lastname" value="{{ old('lastname') }}" required>

                                    @if ($errors->has('lastname'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('lastname') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('firstname') ? ' has-error' : '' }}">
                                <label for="firstname" class="col-md-4 control-label">{{ trans('nosh.firstname') }}</label>

                                <div class="col-md-6">
                                    <input id="firstname" type="text" class="form-control" name="firstname" value="{{ old('firstname') }}" required>

                                    @if ($errors->has('firstname'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('firstname') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('dob') ? ' has-error' : '' }}">
                                <label for="dob" class="col-md-4 control-label">{{ trans('nosh.dob') }}</label>

                                <div class="col-md-6">
                                    <input id="dob" class="form-control" type="date" name="dob" value="{{ old('dob') }}" required>

                                    @if ($errors->has('dob'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('dob') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                                <label for="email" class="col-md-4 control-label">{{ trans('nosh.email') }}</label>

                                <div class="col-md-6">
                                    <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required>

                                    @if ($errors->has('email'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('email') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('username1') ? ' has-error' : '' }}">
                                <label for="username1" class="col-md-4 control-label">{{ trans('nosh.desired_username') }}</label>

                                <div class="col-md-6">
                                    <input id="username1" type="text" class="form-control" name="username1" value="{{ old('username1') }}" required>

                                    @if ($errors->has('username1'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('username1') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('password1') ? ' has-error' : '' }}">
                                <label for="password1" class="col-md-4 control-label">{{ trans('nosh.password') }}</label>

                                <div class="col-md-6">
                                    <input id="password1" type="password" class="form-control" name="password1">

                                    @if ($errors->has('password1'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('password1') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('confirm_password1') ? ' has-error' : '' }}">
                                <label for="confirm_password1" class="col-md-4 control-label">{{ trans('nosh.confirm_password') }}</label>

                                <div class="col-md-6">
                                    <input id="confirm_password1" type="password" class="form-control" name="confirm_password1">

                                    @if ($errors->has('confirm_password1'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('confirm_password1') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('secret_question') ? ' has-error' : '' }}">
                                <label for="secret_question" class="col-md-4 control-label">{{ trans('nosh.secret_question') }}</label>

                                <div class="col-md-6">
                                    <input id="secret_question" type="text" class="form-control" name="secret_question">

                                    @if ($errors->has('secret_question'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('secret_question') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('secret_answer') ? ' has-error' : '' }}">
                                <label for="secret_answer" class="col-md-4 control-label">{{ trans('nosh.secret_answer') }}</label>

                                <div class="col-md-6">
                                    <input id="secret_answer" type="text" class="form-control" name="secret_answer">

                                    @if ($errors->has('secret_answer'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('secret_answer') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('registration_code') ? ' has-error' : '' }}">
                                <label for="registration_code" class="col-md-4 control-label">{{ trans('nosh.registration_code') }}</label>

                                <div class="col-md-6">
                                    <input id="registration_code" type="password" class="form-control" name="registration_code" placeholder="{{ trans('nosh.optional') }}">

                                    @if ($errors->has('registration_code'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('registration_code') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group{{ $errors->has('numberReal') ? ' has-error' : '' }}">
                                <label for="numberReal" class="col-md-4 control-label">{{ trans('nosh.captcha_code') }}</label>

                                <div class="col-md-6">
                                    <input id="numberReal" type="text" class="form-control" name="numberReal" placeholder="{{ trans('nosh.captcha_code1') }}">

                                    @if ($errors->has('numberReal'))
                                        <span class="help-block">
                                            <strong>{{ $errors->first('numberReal') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-btn fa-sign-in"></i> {{ trans('nosh.button_register') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('view.scripts')
<script src="{{ asset('assets/js/web3.js') }}"></script>
<script src="{{ asset('assets/js/uport-connect.js') }}"></script>
<script src="{{ asset('assets/js/toastr.min.js') }}"></script>
<script type="text/javascript">
    $(document).ready(function() {
        if (noshdata.message_action !== '') {
            if (noshdata.message_action.search('Error - ') == -1) {
                toastr.success(noshdata.message_action);
            } else {
                toastr.error(noshdata.message_action);
            }
        }
        if ($("#register_form").is(':visible')) {
            $("#lastname").focus();
            $("#login_form").hide();
        } else {
            $("#username").focus();
        }
        $('#show_login_form').click(function() {
            $('#login_form').show();
        });
        $("#register").click(function(){
            $("#register_form").show();
            $("#login_form").hide();
			$(".footer").css('bottom', '-60px');
        });
        $('#numberReal').realperson({includeNumbers: true});
        function loadlogo() {
            var a = $('#practice_id').val();
			if (a !== undefined) {
	            $.ajax({
	                type: "POST",
	                url: noshdata.practice_logo,
	                data: "practice_id=" + a,
	                success: function(data){
	                    $("#login_practice_logo").html(data);
	                }
	            });
			}
        }
        $('#practice_id').change(function(){
            loadlogo();
        });
        if ($('#practice_id').val() !== '') {
            loadlogo();
        }
    });

	// Uport
	const Connect = window.uportconnect.Connect;
	const appName = 'nosh';
	const connect = new Connect(appName, {
		'clientId': '2oyVF8cuGih6VQy7LseeXjaXHHFNzzoqBTk',
		'signer': window.uportconnect.SimpleSigner('82de57b7883af687b673f7c6521e143d28e02d00ba39aed237beac97f2a96f2e'),
		'network': 'rinkeby'
	});
	const web3 = connect.getWeb3();
	const loginBtnClick = () => {
		connect.requestCredentials({
	      requested: ['name', 'phone', 'country', 'email', 'description'],
	      notifications: true // We want this if we want to recieve credentials
	    }).then((credentials) => {
			console.log(credentials);
			var uport_data = 'name=' + credentials.name + '&uport=' + credentials.address;
			if (typeof credentials.description !== 'undefined') {
				uport_data += '&npi=' + credentials.description;
			}
			if (typeof credentials.email !== 'undefined') {
				uport_data += '&email=' + credentials.email;
			}
			$.ajax({
				type: "POST",
				url: noshdata.login_uport,
				data: uport_data,
				dataType: 'json',
				success: function(data){
					if (data.message !== 'OK') {
						toastr.error(data.message);
					} else {
						window.location = data.url;
					}
				}
			});
		}, console.err);
	};
</script>
@endsection
