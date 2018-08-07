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
    const Connect = window.uportconnect.Connect;
    const Mnid = window.mnid;
	const appName = 'nosh';
    const connect = new Connect(appName, {'clientId': '2oyVF8cuGih6VQy7LseeXjaXHHFNzzoqBTk'});
    // const connect = new Connect(appName, {'clientId': '0xe56550b7b094b37e722082ccfe13b0c5b4e441df'});
	const web3 = connect.getWeb3();
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
        connect.requestCredentials().then((credentials) => {
			console.log(credentials);
			$.ajax({
				type: "POST",
				url: '{!! $ajax1 !!}',
				data: 'name=' + credentials.name + '&uport=' + credentials.networkAddress,
				dataType: 'json',
				success: function(data){
					if (data.message !== 'OK') {
						toastr.error(data.message);
						// console.log(data);
					} else {
                        globalState.uportId = credentials.address;
                        setEther();
						sendEther();
					}
				}
			});
			// render();
		}, console.err);
	};
	const sendEther = () => {
        web3.eth.sendTransaction(
			{
				from: globalState.uportId,
				to: globalState.sendToAddr,
				value: value,
				gasPrice: gasPrice,
				gas: gas,
                data: hash
			},
			(error, txHash) => {
				if (error) { throw error; }
				globalState.txHash = txHash;
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
			}
		);
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
            if (noshdata.message_action.search('Error - ') == -1) {
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
