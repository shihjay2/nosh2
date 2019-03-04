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
            @if (isset($document_url))
                <div class="panel panel-default">
                    <div class="panel-heading clearfix">
                        <h3 class="panel-title pull-left" style="padding-top: 7.5px;">{{ trans('nosh.prescription') }}</h3>
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
            @endif
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
                    <div id="uport_indicator" style="text-align: center;">
                        <i class="fa fa-spinner fa-spin fa-pulse fa-2x fa-fw"></i><span id="modaltext" style="margin:10px">Loading uPort...</span><br><br>
                    </div>
                    {!! $content !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('view.scripts')
<script src="{{ asset('assets/js/web3.js') }}"></script>
<script src="{{ asset('assets/js/uport-connect.js') }}"></script>
<script src="{{ asset('assets/js/mnid.js') }}"></script>
<script type="text/javascript">
    const Connect = window.uportconnect;
    const Mnid = window.mnid;
	const appName = 'NOSH ChartingSystem';
    const uport = new Connect(appName, {
        network: 'rinkeby'
    });
	const globalState = {
		uportId: "{!! $uport_id !!}",
		txHash: "",
		sendToAddr: "0x7d86a87178d28f805716828837D1677Fb7aF6Ff7", //back to B9 testnet faucet
		sendToVal: "1"
	};
    const value = parseFloat(globalState.sendToVal) * 1.0e18;
    const gasPrice = 100000000000;
    const gas = 500000;
    const hash = '{!! $hash !!}';
    const uport_need = '{!! $uport_need !!}';
	const uportConnect = function () {
        $('#uport_indicator').show();
        uport.requestDisclosure({
            requested: ['name', 'email', 'NPI'],
			notifications: true // We want this if we want to recieve credentials
	    });
        uport.onResponse('disclosureReq').then((res) => {
            var did = res.payload.did;
			var credentials = res.payload.verified;
			console.log(res.payload);
			$.ajax({
				type: "POST",
				url: '{!! $ajax1 !!}',
				data: 'name=' + res.payload.name + '&uport=' + res.payload.did,
				dataType: 'json',
				success: function(data){
					if (data.message !== 'OK') {
						toastr.error(data.message);
						// console.log(data);
					} else {
                        globalState.uportId = res.payload.did;
                        setEther();
						sendEther();
					}
				}
			});
			// render();
		}, console.err);
	};
    const sendEther = () => {
        const txobject = {
            to: globalState.sendToAddr,
            value: '0.1',
            data: hash,
            appName: appName
        }
        uport.sendTransaction(txobject, 'setStatus');
        uport.onResponse('setStatus').then((res) => {
            const txHash = res.payload;
            $.ajax({
                type: "POST",
                url: '{!! $ajax !!}',
                data: 'txHash=' + txHash,
                dataType: 'json',
                success: function(data){
                    if (data.message !== 'OK') {
                        toastr.error(data.message);
                        // console.log(data);
                    } else {
                        window.location = data.url;
                    }
                }
            });
            console.log(txHash);
        });
    };
	const setEther = function () {
        if (Mnid.isMNID(globalState.uportId)) {
            var address = Mnid.decode(globalState.uportId);
            globalState.uportId = address.address;
        }
        // $.ajax({
        //     type: "POST",
        //     url: '{!! $ajax2 !!}',
        //     data: 'uportId=' + globalState.uportId,
        //     dataType: 'json',
        //     success: function(data){
        //         if (data.message !== 'OK') {
        //             toastr.error(data.message);
        //             // console.log(data);
        //         }
        //     }
        // });
    };
    $(document).ready(function() {
        // Core
        if (noshdata.message_action !== '') {
            if (noshdata.message_action.search(noshdata.error_text) == -1) {
                toastr.success(noshdata.message_action);
            } else {
                toastr.error(noshdata.message_action);
            }
        }
        if (uport_need == 'y') {
            uportConnect();
        }
        if (uport_need == 'n') {
            setEther();
            sendEther();
        }
    });
</script>
@endsection
