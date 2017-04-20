@extends('layouts.app')
@section('view.stylesheet')
<style>
    #graph {
        max-width: 98%;
        height: 800px;
        margin: auto;
    }
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
                    <div id="graph"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
    sigma.renderers.def = sigma.renderers.canvas;
    s = sigma.parsers.json(noshdata.treedata, {
        container: 'graph',
        settings: {
            drawEdges: true,
            drawLabels: true,
            minNodeSize: 2,
            maxNodeSize: 20,
            minEdgeSize: 1,
            maxEdgeSize: 5,
            batchEdgesDrawing: true,
            labelThreshold: 18,
            sideMargin: 0,
            edgeColor: "default",
            defaultEdgeColor: "#bbb",
        }
    }, function(s) {
        s.bind('clickNode', function(e) {
            // show details
            $('#warningModal_body').html(e.data.node.nosh_data);
            $('#warningModal').modal('show');
        });

        // Start the ForceAtlas2 algorithm:
        // s.startForceAtlas2({
        //     //slowDown: 3,
        //     linLogMode: false,
        //     adjustSizes: true,
        //     strongGravityMode: true
        // });
        //
        // setTimeout(function() {
        //     s.killForceAtlas2();
        //     //not compatible with WebGL
        //     sigma.plugins.dragNodes(s, s.renderers[0]);
        // }, 10000);
    });
    $(window).resize(function() {
       window.dispatchEvent(new Event('resize'));
    });

    $(document).ready(function() {
        $('[data-toggle=offcanvas]').css('cursor', 'pointer').click(function() {
            $('.row-offcanvas').toggleClass('active');
        });
        if (noshdata.message_action !== '') {
            toastr.success(noshdata.message_action);
        }
    });
</script>
@endsection
