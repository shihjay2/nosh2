@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">Setup the Mail Service</div>
				<div class="panel-body">
					<div style="text-align: center;">
					  <i class="fa fa-envelope fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
					</div>
					<div class="alert alert-success">
						<ul>
							<li>If you are using Google Gmail, instructions are on <a href='https://github.com/shihjay2/nosh-in-a-box/wiki/How-to-get-Gmail-to-work-with-NOSH' target='_blank'>this Wiki page</a>.  Please refer to it carefully.</li>
							<li><a href='https://www.mailgun.com/' target='_blank'>Mailgun</a> is highly recommended.</li>
						</ul>
					</div>
					<form class="form-horizontal" role="form" method="POST" action="{{ route('setup_mail') }}">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('mail_type') ? ' has-error' : '' }}">
							<label for="mail_type" class="col-md-4 control-label">Mail Service</label>

							<div class="col-md-6">
								<select id="mail_type" class="form-control" name="mail_type" value="{{ old('mail_type', $mail_type) }}">
									<option value="none">None</option>
									<option value="gmail" {{ $mail_type == 'gmail' ? 'selected="selected"' : '' }}>Google Gmail</option>
									<option value="mailgun" {{ $mail_type == 'mailgun' ? 'selected="selected"' : '' }}>Mailgun</option>
									<option value="sparkpost" {{ $mail_type == 'sparkpost' ? 'selected="selected"' : '' }}>SparkPost</option>
									<option value="ses" {{ $mail_type == 'ses' ? 'selected="selected"' : '' }}>Amazon SES</option>
									<option value="unique" {{ $mail_type == 'uniqu' ? 'selected="selected"' : '' }}>Custom</option>
								</select>
								@if ($errors->has('mail_type'))
									<span class="help-block">
										<strong>{{ $errors->first('mail_type') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form gmail unique form-group{{ $errors->has('mail_username') ? ' has-error' : '' }}" style="display:none;">
							<label for="mail_username" class="col-md-4 control-label">Username</label>

							<div class="col-md-6">
								<input id="mail_username" class="form-control" name="mail_username" value="{{ old('mail_username', $mail_username) }}">

								@if ($errors->has('mail_username'))
									<span class="help-block">
										<strong>{{ $errors->first('mail_username') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form unique form-group{{ $errors->has('mail_password') ? ' has-error' : '' }}" style="display:none;">
							<label for="mail_password" class="col-md-4 control-label">Password</label>

							<div class="col-md-6">
								<input id="mail_password" class="form-control" name="mail_password" value="{{ old('mail_password', $mail_password) }}">

								@if ($errors->has('mail_password'))
									<span class="help-block">
										<strong>{{ $errors->first('mail_password') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form gmail form-group{{ $errors->has('google_client_id') ? ' has-error' : '' }}" style="display:none;">
							<label for="google_client_id" class="col-md-4 control-label">Google Client ID</label>

							<div class="col-md-6">
								<input id="google_client_id" class="form-control" name="google_client_id" value="{{ old('google_client_id', $google_client_id) }}">

								@if ($errors->has('google_client_id'))
									<span class="help-block">
										<strong>{{ $errors->first('google_client_id') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form gmail form-group{{ $errors->has('google_client_secret') ? ' has-error' : '' }}" style="display:none;">
							<label for="google_client_secret" class="col-md-4 control-label">Google Client Secret</label>

							<div class="col-md-6">
								<input id="google_client_secret" class="form-control" name="google_client_secret" value="{{ old('google_client_secret', $google_client_secret) }}">

								@if ($errors->has('google_client_secret'))
									<span class="help-block">
										<strong>{{ $errors->first('google_client_secret') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form mailgun form-group{{ $errors->has('mailgun_domain') ? ' has-error' : '' }}" style="display:none;">
							<label for="mailgun_domain" class="col-md-4 control-label">Mailgun Domain</label>

							<div class="col-md-6">
								<input id="mailgun_domain" class="form-control" name="mailgun_domain" value="{{ old('mailgun_domain', $mailgun_domain) }}">

								@if ($errors->has('mailgun_domain'))
									<span class="help-block">
										<strong>{{ $errors->first('mailgun_domain') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form mailgun form-group{{ $errors->has('mailgun_secret') ? ' has-error' : '' }}" style="display:none;">
							<label for="mailgun_secret" class="col-md-4 control-label">Mailgun Secret</label>

							<div class="col-md-6">
								<input id="mailgun_secret" class="form-control" name="mailgun_secret" value="{{ old('mailgun_secret', $mailgun_secret) }}">

								@if ($errors->has('mailgun_secret'))
									<span class="help-block">
										<strong>{{ $errors->first('mailgun_secret') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form sparkpost form-group{{ $errors->has('sparkpost_secret') ? ' has-error' : '' }}" style="display:none;">
							<label for="sparkpost_secret" class="col-md-4 control-label">SparkPost Secret</label>

							<div class="col-md-6">
								<input id="sparkpost_secret" class="form-control" name="sparkpost_secret" value="{{ old('sparkpost_secret', $sparkpost_secret) }}">

								@if ($errors->has('sparkpost_secret'))
									<span class="help-block">
										<strong>{{ $errors->first('sparkpost_secret') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form ses form-group{{ $errors->has('ses_key') ? ' has-error' : '' }}" style="display:none;">
							<label for="ses_key" class="col-md-4 control-label">Amazon SES Key</label>

							<div class="col-md-6">
								<input id="ses_key" class="form-control" name="ses_key" value="{{ old('ses_key', $ses_key) }}">

								@if ($errors->has('ses_key'))
									<span class="help-block">
										<strong>{{ $errors->first('ses_key') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form ses form-group{{ $errors->has('ses_secret') ? ' has-error' : '' }}" style="display:none;">
							<label for="ses_secret" class="col-md-4 control-label">Amazon SES Secret</label>

							<div class="col-md-6">
								<input id="ses_secret" class="form-control" name="ses_secret" value="{{ old('ses_secret', $ses_secret) }}">

								@if ($errors->has('ses_secret'))
									<span class="help-block">
										<strong>{{ $errors->first('ses_secret') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form unique form-group{{ $errors->has('mail_host') ? ' has-error' : '' }}" style="display:none;">
							<label for="mail_host" class="col-md-4 control-label">Mail Host URL</label>

							<div class="col-md-6">
								<input id="mail_host" class="form-control" name="mail_host" value="{{ old('mail_host', $mail_host) }}">

								@if ($errors->has('mail_host'))
									<span class="help-block">
										<strong>{{ $errors->first('mail_host') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form unique form-group{{ $errors->has('mail_port') ? ' has-error' : '' }}" style="display:none;">
							<label for="mail_port" class="col-md-4 control-label">Port</label>

							<div class="col-md-6">
								<input id="mail_port" class="form-control" name="mail_port" value="{{ old('mail_port', $mail_port) }}">

								@if ($errors->has('mail_port'))
									<span class="help-block">
										<strong>{{ $errors->first('mail_port') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="mail_form unique form-group{{ $errors->has('mail_encryption') ? ' has-error' : '' }}" style="display:none;">
							<label for="mail_encryption" class="col-md-4 control-label">Encryption</label>

							<div class="col-md-6">
								<select id="mail_encryption" class="form-control" name="mail_encryption" value="{{ old('mail_encryption', $mail_encryption) }}">
									<option value="">None</option>
									<option value="SSL" {{ $mail_encryption == 'SSL' ? 'selected="selected"' : '' }}>SSL</option>
									<option value="TLS" {{ $mail_encryption == 'TLS' ? 'selected="selected"' : '' }}>TLS</option>
								</select>

								@if ($errors->has('mail_encryption'))
									<span class="help-block">
										<strong>{{ $errors->first('mail_encryption') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-sign-in"></i> Save
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
		$("#mail_type").focus();
		$("#mail_type").change(function(){
			var mail_type = $("#mail_type").val();
			$(".mail_form").hide();
			$("." + mail_type).show();
		});
		var set_mail_type = $("#mail_type").val();
		if (set_mail_type !== '') {
			$(".mail_form").hide();
			$("." + set_mail_type).show();
		}
	});
</script>
@endsection
