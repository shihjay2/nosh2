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
<script src="{{ asset('assets/js/abi-decoder.js') }}"></script>
<script type="text/javascript">
    const metaIdentityManager = [{"constant":true,"inputs":[{"name":"identity","type":"address"},{"name":"recoveryKey","type":"address"}],"name":"isRecovery","outputs":[{"name":"","type":"bool"}],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"identity","type":"address"},{"name":"owner","type":"address"}],"name":"removeOwner","outputs":[],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"identity","type":"address"},{"name":"newIdManager","type":"address"}],"name":"initiateMigration","outputs":[],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"identity","type":"address"},{"name":"recoveryKey","type":"address"}],"name":"changeRecovery","outputs":[],"payable":false,"type":"function"},{"constant":true,"inputs":[{"name":"identity","type":"address"},{"name":"owner","type":"address"}],"name":"isOlderOwner","outputs":[{"name":"","type":"bool"}],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"owner","type":"address"},{"name":"recoveryKey","type":"address"},{"name":"destination","type":"address"},{"name":"data","type":"bytes"}],"name":"createIdentityWithCall","outputs":[],"payable":false,"type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"}],"name":"migrationNewAddress","outputs":[{"name":"","type":"address"}],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"identity","type":"address"},{"name":"destination","type":"address"},{"name":"value","type":"uint256"},{"name":"data","type":"bytes"}],"name":"forwardTo","outputs":[],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"identity","type":"address"},{"name":"newOwner","type":"address"}],"name":"addOwnerFromRecovery","outputs":[],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"owner","type":"address"},{"name":"recoveryKey","type":"address"}],"name":"registerIdentity","outputs":[],"payable":false,"type":"function"},{"constant":true,"inputs":[{"name":"identity","type":"address"},{"name":"owner","type":"address"}],"name":"isOwner","outputs":[{"name":"","type":"bool"}],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"identity","type":"address"}],"name":"cancelMigration","outputs":[],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"identity","type":"address"},{"name":"newOwner","type":"address"}],"name":"addOwner","outputs":[],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sender","type":"address"},{"name":"identity","type":"address"}],"name":"finalizeMigration","outputs":[],"payable":false,"type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"}],"name":"migrationInitiated","outputs":[{"name":"","type":"uint256"}],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"owner","type":"address"},{"name":"recoveryKey","type":"address"}],"name":"createIdentity","outputs":[],"payable":false,"type":"function"},{"inputs":[{"name":"_userTimeLock","type":"uint256"},{"name":"_adminTimeLock","type":"uint256"},{"name":"_adminRate","type":"uint256"},{"name":"_relayAddress","type":"address"}],"payable":false,"type":"constructor"},{"anonymous":false,"inputs":[{"indexed":true,"name":"identity","type":"address"},{"indexed":true,"name":"creator","type":"address"},{"indexed":false,"name":"owner","type":"address"},{"indexed":true,"name":"recoveryKey","type":"address"}],"name":"LogIdentityCreated","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"identity","type":"address"},{"indexed":true,"name":"owner","type":"address"},{"indexed":false,"name":"instigator","type":"address"}],"name":"LogOwnerAdded","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"identity","type":"address"},{"indexed":true,"name":"owner","type":"address"},{"indexed":false,"name":"instigator","type":"address"}],"name":"LogOwnerRemoved","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"identity","type":"address"},{"indexed":true,"name":"recoveryKey","type":"address"},{"indexed":false,"name":"instigator","type":"address"}],"name":"LogRecoveryChanged","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"identity","type":"address"},{"indexed":true,"name":"newIdManager","type":"address"},{"indexed":false,"name":"instigator","type":"address"}],"name":"LogMigrationInitiated","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"identity","type":"address"},{"indexed":true,"name":"newIdManager","type":"address"},{"indexed":false,"name":"instigator","type":"address"}],"name":"LogMigrationCanceled","type":"event"},{"anonymous":false,"inputs":[{"indexed":true,"name":"identity","type":"address"},{"indexed":true,"name":"newIdManager","type":"address"},{"indexed":false,"name":"instigator","type":"address"}],"name":"LogMigrationFinalized","type":"event"}];
    abiDecoder.addABI(metaIdentityManager);
    const txRelay = [{"constant":true,"inputs":[{"name":"add","type":"address"}],"name":"getNonce","outputs":[{"name":"","type":"uint256"}],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sendersToUpdate","type":"address[]"}],"name":"removeFromWhitelist","outputs":[],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sendersToUpdate","type":"address[]"}],"name":"addToWhitelist","outputs":[],"payable":false,"type":"function"},{"constant":true,"inputs":[{"name":"","type":"address"},{"name":"","type":"address"}],"name":"whitelist","outputs":[{"name":"","type":"bool"}],"payable":false,"type":"function"},{"constant":false,"inputs":[{"name":"sigV","type":"uint8"},{"name":"sigR","type":"bytes32"},{"name":"sigS","type":"bytes32"},{"name":"destination","type":"address"},{"name":"data","type":"bytes"},{"name":"listOwner","type":"address"}],"name":"relayMetaTx","outputs":[],"payable":false,"type":"function"},{"constant":true,"inputs":[{"name":"b","type":"bytes"}],"name":"getAddress","outputs":[{"name":"a","type":"address"}],"payable":false,"type":"function"}];
    abiDecoder.addABI(txRelay);
    const validatetx = () => {
        var tx = $('#tx_hash').val();
        var txdata = $('#tx_data').val();
        var transaction_data = null;
        var transaction_did = '';
        const txRelaydata = abiDecoder.decodeMethod(txdata);

        for (var i=0; i<txRelaydata.params.length; i++) {
            if (txRelaydata.params[i].name == 'data') {
                const relayMetaTx = abiDecoder.decodeMethod(txRelaydata.params[i].value);
                for (var j=0; j<relayMetaTx.params.length; j++) {
                    if (relayMetaTx.params[j].name == 'data') {
                        transaction_data = relayMetaTx.params[j].value;
                        transaction_data = transaction_data.slice(2);
                    }
                    if (relayMetaTx.params[j].name == 'identity') {
                        transaction_did = relayMetaTx.params[j].value;
                    }
                }
            }
        }
        if (transaction_data !== null) {
            window.location = '{!! $url !!}' + '/' + transaction_data + '/' + transaction_did;
        } else {
            toastr.error('{{ trans('nosh.no_transaction') }}');
        }
    };
    const uport_need = '{!! $uport_need !!}';
    $(document).ready(function() {
        // Core
        if (noshdata.message_action !== '') {
            if (noshdata.message_action.search('{{ trans('nosh.error_search') }}') == -1) {
                toastr.success(noshdata.message_action);
            } else {
                toastr.error(noshdata.message_action);
            }
        }
        if (uport_need == 'validate') {
            validatetx();
        }
        if ($('#rx_hash').length) {
            location.href = '#rx_hash';
        }
    });
</script>
@endsection
