@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Login</div>
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
                                    <a class="btn btn-primary btn-block" href="{{ url('/uma_auth') }}">
                                        <i class="fa fa-btn fa-openid"></i> I'm the Patient
                                    </a>
                                    <br><br><a href="#" id="show_login_form">Standard Login for Administrator</a>
                                </div>
                            </div>
                        @else
                            <div class="form-group">
                                <div class="col-md-6 col-md-offset-3">
                                    <a class="btn btn-primary btn-block" href="{{ url('/oidc') }}">
                                        <i class="fa fa-btn fa-openid"></i> Login with mdNOSH
                                    </a>
                                    <a class="btn btn-primary btn-block" href="{{ url('/google_auth') }}">
                                        <i class="fa fa-btn fa-google"></i> Login with Google
                                    </a>
                                </div>
                            </div>
                        @endif
                    @endif
                    <form id="login_form" class="form-horizontal" role="form" method="POST" action="{{ url('/login') }}">
                        {{ csrf_field() }}

                        <div class="form-group{{ $errors->has('username') ? ' has-error' : '' }}">
                            <label for="username" class="col-md-4 control-label">Username</label>

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
                            <label for="password" class="col-md-4 control-label">Password</label>

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
                                <label for="password" class="col-md-4 control-label">Organization/Practice</label>

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
                                        <input type="checkbox" name="remember"> Remember Me
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-6 col-md-offset-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-btn fa-sign-in"></i> Login
                                </button>
                                <a class="btn btn-link" href="{{ url('/password_email') }}">Forgot Your Password?</a>
                                @if ($patient_centric == 'n' && $demo == 'n')
                                    <a class="btn btn-link" href="#" id="register">Are you new to the Patient Portal?</a>
                                @endif
                            </div>
                        </div>
                    </form>
                    @if ($errors->has('registration_code') || $errors->has('lastname') || $errors->has('firstname') || $errors->has('dob') || $errors->has('email') || $errors->has('username1') || $errors->has('numberReal'))
                        <form id="register_form" class="form-horizontal" role="form" method="POST" action="{{ url('/register') }}">
                    @else
                        <form id="register_form" class="form-horizontal" role="form" method="POST" action="{{ url('/register') }}" style="display:none;">
                    @endif
                        {{ csrf_field() }}
                        <input type="hidden" name="count" id="new_password_count" value="" />
                        <input type="hidden" name="practice_id" id="register_practice_id" value="" />

                        <div class="well">
                            <p>Enter the following fields to register as a patient portal user.  It is important that your answers are exactly what is provided to your practice such as the spelling of your name and date of birth.</p>
                            <p>If you don't have a registration code, a registration request will be sent to the practice administrator.</p>
                            <p>You will then receive a registration code sent to your e-mail address before you proceed further.</p>
                            <p>Keep in mind that this may take some time depending on the response time of the practice administrator.</p>
                        </div>

                        <div class="form-group{{ $errors->has('lastname') ? ' has-error' : '' }}">
                            <label for="lastname" class="col-md-4 control-label">Last Name</label>

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
                            <label for="firstname" class="col-md-4 control-label">First Name</label>

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
                            <label for="dob" class="col-md-4 control-label">Date of Birth</label>

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
                            <label for="email" class="col-md-4 control-label">E-Mail Address</label>

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
                            <label for="username1" class="col-md-4 control-label">Desired Username</label>

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
                            <label for="password1" class="col-md-4 control-label">Password</label>

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
                            <label for="confirm_password1" class="col-md-4 control-label">Confirm Password</label>

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
                            <label for="secret_question" class="col-md-4 control-label">Security Question</label>

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
                            <label for="secret_answer" class="col-md-4 control-label">Security Answer</label>

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
                            <label for="registration_code" class="col-md-4 control-label">Registration Code</label>

                            <div class="col-md-6">
                                <input id="registration_code" type="password" class="form-control" name="registration_code" placeholder="Optional">

                                @if ($errors->has('registration_code'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('registration_code') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group{{ $errors->has('numberReal') ? ' has-error' : '' }}">
                            <label for="numberReal" class="col-md-4 control-label">CAPTCHA Code</label>

                            <div class="col-md-6">
                                <input id="numberReal" type="text" class="form-control" name="numberReal" placeholder="Enter CAPTCHA code here.">

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
                                    <i class="fa fa-btn fa-sign-in"></i> Register
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
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
        });
        $('#numberReal').realperson({includeNumbers: true});
        function loadlogo() {
            var a = $('#practice_id').val();
            $.ajax({
                type: "POST",
                url: noshdata.practice_logo,
                data: "practice_id=" + a,
                success: function(data){
                    $("#login_practice_logo").html(data);
                }
            });
        }
        $('#practice_id').change(function(){
            loadlogo();
        });
        if ($('#practice_id').val() !== '') {
            loadlogo();
        }
    });
</script>
@endsection
