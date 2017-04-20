@extends('layouts.app')

@section('content')
@if (isset($sidebar_content))
    <div class="container-fluid">
@else
    <div class="container">
@endif
    <div class="row">
        <div>
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
                    @if (isset($content))
                        {!! $content !!}
                    @endif
                    <form id="document_upload_form" class="form-horizontal" role="form" method="POST" enctype="multipart/form-data" action="{{ $document_upload }}">
                        {{ csrf_field() }}
                        <label class="control-label"></label>
                        <input id="file_input" name="file_input" type="file" multiple class="file-loading">
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
        $('[data-toggle=offcanvas]').css('cursor', 'pointer').click(function() {
            $('.row-offcanvas').toggleClass('active');
        });
        if (noshdata.message_action !== '') {
            if (noshdata.message_action.search('Error - ') == -1) {
                toastr.success(noshdata.message_action);
            } else {
                toastr.error(noshdata.message_action);
            }
        }
        $("#file_input").fileinput({
            allowedFileExtensions: JSON.parse(noshdata.document_type),
            maxFileCount: 1
        });
    });
</script>
@endsection
