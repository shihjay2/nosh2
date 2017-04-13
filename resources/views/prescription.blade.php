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
                    <h3 class="panel-title pull-left" style="padding-top: 7.5px;">Prescription QR Code</h3>
                </div>
                <div class="panel-body">
                    {!! $content !!}
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left" style="padding-top: 7.5px;">GoodRX Information</h3>
                </div>
                <div class="panel-body">
                    <div class="container">
                        <div class="row">
                            <div class="col-md-6">
                                <div id="goodrx_compare-price_widget"></div>
                            </div>
                            <div class="col-md-6">
                                <div id="goodrx_low-price_widget"></div>
                            </div>
                        </div>
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
    var _grxdn = "Melatonin";
    (function(d,t){var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
    g.src="//s3.amazonaws.com/assets.goodrx.com/static/widgets/compare.min.js";
    s.parentNode.insertBefore(g,s);}(document,"script"));
    (function(e,v){var h=e.createElement(v),u=e.getElementsByTagName(v)[0];
    h.src="//s3.amazonaws.com/assets.goodrx.com/static/widgets/low.min.js";
    u.parentNode.insertBefore(h,u);}(document,"script"));
</script>
@endsection
