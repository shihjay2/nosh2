@extends('layouts.app')

@section('view.stylesheet')
	<link rel="stylesheet" href="{{ asset('assets/css/fileinput.min.css') }}">
@endsection

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
<script src="{{ asset('assets/js/canvas-to-blob.min.js') }}"></script>
<script src="{{ asset('assets/js/sortable.min.js') }}"></script>
<script src="{{ asset('assets/js/purify.min.js') }}"></script>
<script src="{{ asset('assets/js/fileinput.min.js') }}"></script>
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
            maxFileCount: 1,
			dropZoneEnabled: false
        });

		@if (isset($content))
		function handleError(error) {
			console.error('navigator.getUserMedia error: ', error);
		}
		const constraints = {video: true};

		(function() {
			const video = document.querySelector('video');
			const captureVideoButton = document.querySelector('#start_video');
			const screenshotButton = document.querySelector('#stop_video');
			const img = document.querySelector('#screenshot img');
			const input = document.querySelector('#img');
			const canvas = document.createElement('canvas');
			const saveButton = document.querySelector('#save_picture');
			const restartButton = document.querySelector('#restart_picture');
			const cancelButton = document.querySelector('#cancel_picture');

			function handleSuccess(stream) {
				screenshotButton.disabled = false;
				video.srcObject = stream;
			}

			captureVideoButton.onclick = function() {
				video.style.display = "block";
				navigator.mediaDevices.getUserMedia(constraints).
				then(handleSuccess).catch(handleError);
				img.style.display = "none";
			};

			restartButton.onclick = function() {
				video.style.display = "block";
				navigator.mediaDevices.getUserMedia(constraints).
				then(handleSuccess).catch(handleError);
				img.style.display = "none";
			};

			screenshotButton.onclick = function() {
				canvas.width = video.videoWidth;
				canvas.height = video.videoHeight;
				canvas.getContext('2d').drawImage(video, 0, 0);
				img.src = canvas.toDataURL('image/png');
				input.value = img.src;
				img.style.display = "block";
				saveButton.style.display = "inline";
				restartButton.style.display = "inline";
				cancelButton.style.display = "inline";
				video.style.display = "none";
			};

			cancelButton.onclick = function() {
				video.style.display = "none";
				img.style.display = "none";
				saveButton.style.display = "none";
				restartButton.style.display = "none";
			}
		})();
		@endif
    });
</script>
@endsection
