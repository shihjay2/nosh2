@extends('layouts.app')

@section('content')
@if (isset($sidebar_content))
    <div class="container-fluid">
@else
    <div class="container">
@endif
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Health Information Exchange Consent</div>
                <div class="panel-body">
                    <div style="text-align: center;">
                        <i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
                    </div>
                    <form class="form-horizontal" role="form" method="POST" action="{{ url('/uma_register') }}">
                        {{ csrf_field() }}
                        <div class="form-group has-error" style="text-align: center;">
                            <span class="help-block has-error">
                                <strong>{{ $errors->first('tryagain') }}</strong>
                            </span>
                        </div>

                        <div class="form-group" style="text-align: center;">
                            <h4>By entering your <abbr data-toggle="tooltip" title="Find this email address by logging into your HIE of One authorization server, click on the username on the right upper corner, click on My Information">email address linked to your HIE of One authorization service below:</abbr></h4>
                        </div>
                        <div class="form-group">
                            <ul>
                                <li>You will be allowing physicians using mdNOSH the potential to access your health information</li>
                                <!-- <li>You will be able to make your authorization server identifiable in a patient directory for future physicians using mdNOSH to access your health information</li> -->
                                <li>For more information about how your email address identifies you, <abbr data-toggle="tooltip" id="more_info" title="Click here">click here</abbr>
                            </ul>
                        </div>
                        <div class="form_group" id="more_info_div">
                            <h4 style="color:yellow;">How mdNOSH will contact your authorization server</h4>
                            <ol>
                                <li>mdNOSH will be contacting the server to validate if a user tied to this e-mail address exists.</li>
                                <li>mdNOSH will then determine if an authorization service (like HIE of One) exists on the domain.</li>
                                <li>mdNOSH will then make a call to register itself as a client to the authorization service so that physicians who have an account with mdNOSH that you invite can access your health-related resources.</li>
                                <li>You will be prompted to accept or deny the registration of mdNOSH to your HIE of One authorization service.</li>
                            </ol>
                        </div>

                        <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                            <label for="email" class="col-md-4 control-label">E-Mail Address:</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control" name="email" value="shihjay2@shihjay.xyz" data-toggle="tooltip" title="Email: shihjay2@shihjay.xyz">

                                @if ($errors->has('email'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('email') }}</strong>
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
        $("#email").focus();
        $('[data-toggle="tooltip"]').tooltip();
        $('#more_info_div').hide();
        $('#more_info').on('click', function(){
            $('#more_info_div').toggle();
        });
    });
</script>
@endsection
