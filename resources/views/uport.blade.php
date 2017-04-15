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
<script type="text/javascript">
    $(function () {
        var activeTab = $('[href="' + location.hash + '"]');
        if (activeTab) {
            activeTab.tab('show');
        }
    });
    const Connect = window.uportconnect.Connect;
	const appName = 'nosh';
	const connect = new Connect(appName);
	const web3 = connect.getWeb3();
    var eth_address = {'toWhom':'0xb65e3a3027fa941eec63411471d90e6c24b11ed1'}; // uPort ethereum address
	let globalState = {
		uportId: "0x962517e3d2cbc1d0410876d975316edc3385dfe6",
		txHash: "",
		sendToAddr: "0x687422eea2cb73b5d3e242ba5456b782919afc85", //back to Ropsten faucet
		sendToVal: "5"
	};
	const uportConnect = function () {
		web3.eth.getCoinbase((error, address) => {
			if (error) { throw error; }
			console.log(address);
			globalState.uportId = address;
            sendEther();
		});
	};
	const sendEther = () => {
		const value = parseFloat(globalState.sendToVal) * 1.0e18;
		const gasPrice = 100000000000;
		const gas = 500000;
        const hash = '{!! $hash !!}';
		web3.eth.sendTransaction(
			{
				from: globalState.uportId,
				to: globalState.sendToAddr,
				value: value,
				gasPrice: gasPrice,
				gas: gas,
                // data: hash
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
    $(document).ready(function() {
        // Core
        if (noshdata.message_action !== '') {
            if (noshdata.message_action.search('Error - ') == -1) {
                toastr.success(noshdata.message_action);
            } else {
                toastr.error(noshdata.message_action);
            }
        }
        $.ajax({
            type: "POST",
            url: 'https://ropsten.faucet.b9lab.com/tap',
            data: JSON.stringify(eth_address),
            dataType: 'jsonp',
            success: function(data){
                // uportConnect();
                sendEther();
            },
            error: function(xhr, ajaxOptions, thrownError) {
                toastr.error(xhr.status + ": Try again");
            }
        });
    });
</script>
@endsection
