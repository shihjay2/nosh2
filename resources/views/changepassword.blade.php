@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">{{ trans('nosh.reset_password') }}</div>
                <div class="panel-body">
                    <div style="text-align: center;">
                      <i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
                      @if ($errors->has('tryagain'))
                          <div class="form-group  has-error">
                            <span class="help-block has-error">
                                <strong>{{ $errors->first('tryagain') }}</strong>
                            </span>
                          </div>
                      @endif
                    </div>
                    <form class="form-horizontal" role="form" method="POST" action="{{ route('password_reset_response', [$code]) }}">
                        {{ csrf_field() }}
                        <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                            <label for="password" class="col-md-4 control-label">{{ trans('nosh.new_password') }}</label>
                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control" name="password">
                                @if ($errors->has('password'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('confirm_password') ? ' has-error' : '' }}">
                            <label for="confirm_password" class="col-md-4 control-label">{{ trans('nosh.confirm_new_password') }}</label>
                            <div class="col-md-6">
                                <input id="confirm_password" type="password" class="form-control" name="confirm_password">
                                @if ($errors->has('confirm_password'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('confirm_password') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="secret_question" class="col-md-4 control-label">{{ trans('nosh.secret_question') }}</label>
                            <div class="col-md-6">
                                {{ $secret_question }}
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
                        <div class="form-group">
                            <div class="col-md-6 col-md-offset-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-btn fa-sign-in"></i> {{ trans('nosh.reset_password') }}
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
        $("#password").focus();
    });
</script>
@endsection
