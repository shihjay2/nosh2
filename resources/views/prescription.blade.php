@extends('layouts.app')

@section('view.stylesheet')
<style>

</style>
@endsection

@section('content')
@if (isset($sidebar_content))
    <div class="container-fluid">
@else
    <div class="container">
@endif
    <div class="row">
        <div>
        <!-- <div class="col-md-10 col-md-offset-1"> -->
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left" style="padding-top: 7.5px;">{{ trans('nosh.present_pharmacy') }}</h3>
                    @if (isset($panel_dropdown))
                        <div class="pull-right">
                            {!! $panel_dropdown !!}
                        </div>
                    @endif
                </div>
                <div class="panel-body">
                    {!! $content !!}
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left" style="padding-top: 7.5px;">{{ trans('nosh.prescription') }}</h3>
                    @if (isset($panel_dropdown))
                        <div class="pull-right">
                            {!! $panel_dropdown !!}
                        </div>
                    @endif
                </div>
                <div class="panel-body">
                    <div class="container">
                        <div class="row">
                            <img src="{{ $rx_jpg }}" class="img-responsive">
                        </div>
                        <div class="row">
                            <a href="{{ $document_url }}" target="_blank" class="nosh-no-load btn btn-primary">{{ trans('nosh.save_pdf') }}</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left" style="padding-top: 7.5px;">{{ trans('nosh.goodrx_header')}}</h3>
                </div>
                <div class="panel-body" id="goodrx_container">
                    <div class="container">
                        <div class="row">
                            <div class="col-md-6">
                                <div id="goodrx_compare-price_widget"></div>
                            </div>
                            <div class="col-md-6">
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
        </div>

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
    });
</script>
<script>
    var _grxdn = '{!! $rx !!}';
    (function(d,t){var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
    g.src="//s3.amazonaws.com/assets.goodrx.com/static/widgets/compare.min.js";
    s.parentNode.insertBefore(g,s);}(document,"script"));
    (function(e,v){var h=e.createElement(v),u=e.getElementsByTagName(v)[0];
    h.src="//s3.amazonaws.com/assets.goodrx.com/static/widgets/low.min.js";
    u.parentNode.insertBefore(h,u);}(document,"script"));
</script>
@endsection
