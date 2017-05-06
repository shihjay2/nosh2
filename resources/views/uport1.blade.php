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
                        <h3 class="panel-title pull-left" style="padding-top: 7.5px;">Prescription</h3>
                    </div>
                    <div class="panel-body">
                        <div class="container">
                            <div class="row">
                                <img src="{{ $rx_jpg }}" class="img-responsive">
                            </div>
                            <div class="row">
                                <a href="{{ $document_url }}" target="_blank" class="nosh-no-load btn btn-primary">Save as PDF</a>
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
<script src="{{ asset('assets/js/web3.min.js') }}"></script>
<script type="text/javascript">
    const validatetx = () => {
        var web3 = new Web3(new Web3.providers.HttpProvider('https://ropsten.infura.io/'));
        var tx = $('#tx_hash').val();
        var transaction = web3.eth.getTransaction(tx);
        if (transaction !== null) {
            window.location = '{!! $url !!}' + '/' + transaction.input;
        } else {
            toastr.error('Transaction receipt not found');
        }
    };
    const uport_need = '{!! $uport_need !!}';
    $(document).ready(function() {
        // Core
        if (noshdata.message_action !== '') {
            if (noshdata.message_action.search('Error - ') == -1) {
                toastr.success(noshdata.message_action);
            } else {
                toastr.error(noshdata.message_action);
            }
        }
        if (uport_need == 'validate') {
            validatetx();
        }
    });
</script>
@endsection
