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
                    <div id="pdf"></div>
                    <a href="{{ $document_url }}" target="_blank" class="nosh-no-load">{{ trans('nosh.no_pdf') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
    var options = {
        pdfOpenParams: {
            navpanes: 0,
            toolbar: 0,
            statusbar: 0,
            view: "FitV",
            pagemode: "thumbs",
            page: 1
        },
        forcePDFJS: true,
        PDFJS_URL: "{{ asset('assets/js/web/viewer.html') }}"
    };
    var myPDF = PDFObject.embed(noshdata.document_url, "#pdf", options);
    if (! myPDF) {
        toastr.error('{{ trans('nosh.error_pdf') }}');
    }
    $(document).ready(function() {
        $('[data-toggle=offcanvas]').css('cursor', 'pointer').click(function() {
            $('.row-offcanvas').toggleClass('active');
        });
        if (noshdata.message_action !== '') {
            toastr.success(noshdata.message_action);
        }
    });
    $(window).bind('beforeunload', function(){
        $.ajax({
            type: 'POST',
            url: noshdata.document_delete,
            async: false,
            success: function(data){
            }
        });
        return void(0);
    });
</script>
@endsection
