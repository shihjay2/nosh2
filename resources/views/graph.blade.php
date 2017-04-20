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
                    <div id="graph"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('view.scripts')
<script src="{{ asset('assets/js/highcharts.js') }}"></script>
<script src="{{ asset('assets/js/exporting.js') }}"></script>
<script src="{{ asset('assets/js/offline-exporting.js') }}"></script>
<script type="text/javascript">
    var nosh_chart_options = {};
    if (noshdata.graph_type === 'data-to-time') {
        nosh_chart_options = {
            chart: {
                renderTo: 'graph',
                type: 'line',
                marginRight: 130,
                marginBottom: 50
            },
            title: {
                text: noshdata.graph_title,
                x: -20
            },
            xAxis: {
                title: {
                    text: noshdata.graph_x_title
                },
                type: 'datetime'
            },
            yAxis: {
                title: {
                    text: noshdata.graph_y_title
                },
                plotLines: [{
                    value: 0,
                    width: 1,
                    color: '#808080'
                }]
            },
            legend: {
                layout: 'vertical',
                align: 'right',
                verticalAlign: 'top',
                x: -10,
                y: 100,
                borderWidth: 0
            },
            series: [
                {type: 'line', data: [], name: noshdata.graph_series_name}
            ],
            credits: {
                href: 'http://noshemr.wordpress.com',
                text: 'NOSH ChartingSystem'
            }
        };
    }
    if (noshdata.graph_type === 'growth-chart' || noshdata.graph_type === 'growth-chart1') {
        nosh_chart_options = {
            chart: {
                renderTo: 'graph',
                defaultSeriesType: 'line',
                marginRight: 130,
                marginBottom: 50,
            },
            title: {
                text: noshdata.graph_title,
                x: -20
            },
            xAxis: {
                title: {
                    text: noshdata.graph_x_title
                },
                labels: {
                    step: 180
                },
                categories: []
            },
            yAxis: {
                title: {
                    text: noshdata.graph_y_title
                },
                plotLines: [{
                    value: 0,
                    width: 1,
                    color: '#808080'
                }]
            },
            tooltip: {
                enabled: true
            },
            legend: {
                layout: 'vertical',
                align: 'right',
                verticalAlign: 'top',
                x: -10,
                y: 100,
                borderWidth: 0
            },
            series: [
                {name: '95%', type: 'spline', data: []},
                {name: '90%', type: 'spline', data: []},
                {name: '75%', type: 'spline', data: []},
                {name: '50%', type: 'spline', data: []},
                {name: '25%', type: 'spline', data: []},
                {name: '10%', type: 'spline', data: []},
                {name: '5%', type: 'spline', data: []},
                {type: 'line', data: []}
            ],
            credits: {
                href: 'http://noshemr.wordpress.com',
                text: 'NOSH ChartingSystem'
            },
            plotOptions: {
                spline: {
                    marker: {
                        enabled: false
                    }
                },
                line: {
                    marker: {
                        enabled: true
                    }
                }
            },
        };
    }
    if (noshdata.graph_type === 'data-to-time') {
        var graph_data = JSON.parse('<?php if (isset($graph_data)) { echo $graph_data; }?>');
        var newData = [];
        for (var i=0; i < graph_data.length; i++) {
            newData.push( [ new Date(graph_data[i][0]).getTime(), parseFloat(graph_data[i][1]) ] );
        }
        nosh_chart_options.series[0].data = newData;
    }
    if (noshdata.graph_type === 'growth-chart' || noshdata.graph_type === 'growth-chart1') {
        if (noshdata.graph_type === 'growth-chart') {
            nosh_chart_options.xAxis.categories = JSON.parse('<?php if (isset($categories)) { echo $categories;} ?>');
        }
        nosh_chart_options.series[0].data = JSON.parse('<?php if (isset($P95)) { echo $P95; } ?>');
        nosh_chart_options.series[1].data = JSON.parse('<?php if (isset($P90)) { echo $P90; }?>');
        nosh_chart_options.series[2].data = JSON.parse('<?php if (isset($P75)) { echo $P75; }?>');
        nosh_chart_options.series[3].data = JSON.parse('<?php if (isset($P50)) { echo $P50; }?>');
        nosh_chart_options.series[4].data = JSON.parse('<?php if (isset($P25)) { echo $P25; }?>');
        nosh_chart_options.series[5].data = JSON.parse('<?php if (isset($P10)) { echo $P10; }?>');
        nosh_chart_options.series[6].data = JSON.parse('<?php if (isset($P5)) { echo $P5; }?>');
        nosh_chart_options.series[7].data = JSON.parse('<?php if (isset($patient)) { echo $patient; }?>');
        nosh_chart_options.series[7].name = '<?php if (isset($patientname)) { echo $patientname; }?>';
    }
    var chart = new Highcharts.Chart(nosh_chart_options);
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
