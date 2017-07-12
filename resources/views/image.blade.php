@extends('layouts.app')

@section('content')
@if (isset($sidebar_content))
    <div class="container-fluid">
@else
    <div class="container">
@endif
    <div class="row">
        @if (isset($image_list))
            <div class="col-md-4">
                <div class="panel panel-default">
                    <div class="panel-heading clearfix">
                        <h3 class="panel-title pull-left" style="padding-top: 7.5px;">{!! $image_list_title !!}</h3>
                        @if (isset($panel_dropdown))
                            <div class="pull-right">
                                {!! $panel_dropdown !!}
                            </div>
                        @endif
                    </div>
                    <div class="panel-body">
                        <form class="form-horizontal" role="form">
                             <div class="form-group">
                                 <label for="image_select" class="col-md-4 control-label">{!! $image_list_label !!}</label>
                                 <div class="col-md-8">
                                     <select id="image_select" class="form-control" name="image_select" value="">
                                         {!! $image_list !!}
                                     </select>
                                 </div>
                             </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
        @else
            <div>
        @endif
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <div class="container-fluid panel-container">
                            @if (isset($panel_dropdown))
                                <div class="col-xs-4 text-left">
                                    <h5 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">{!! $panel_header !!}</h5>
                                </div>
                                <div class="col-xs-8 text-right">
                                    {!! $panel_dropdown !!}
                                </div>
                            @else
                                <div class="col-xs-12 text-left">
                                    <h5 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">{!! $panel_header !!}</h5>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="btn-group">
                            <button type="button" class="btn btn-default" id="sketchpad_undo" ><i class="fa fa-undo fa-fw fa-lg"></i></button>
                            <button type="button" class="btn btn-default" id="sketchpad_redo" ><i class="fa fa-repeat fa-fw fa-lg"></i></button>
                            <button type="button" class="btn btn-default" id="clear_tool"><i class="fa fa-mouse-pointer fa-fw fa-lg"></i></button>
                            <button type="button" class="btn btn-default sketchpad_buttons" id="sketchpad_brush" title="{{ trans('nosh.pen_tool') }}"><i class="fa fa-pencil fa-fw fa-lg"></i></button>
                            <button type="button" class="btn btn-default sketchpad_buttons" id="sketchpad_text" title="{{ trans('nosh.text_tool') }}"><i class="fa fa-text-width fa-fw fa-lg"></i></button>
                            <button type="button" class="btn btn-default sketchpad_buttons" id="sketchpad_rect" title="{{ trans('nosh.rectangle_tool') }}"><i class="fa fa-square-o fa-fw fa-lg"></i></button>
                            <button type="button" class="btn btn-default sketchpad_buttons" id="sketchpad_ellipse" title="{{ trans('nosh.ellipse_tool') }}"><i class="fa fa-circle-o fa-fw fa-lg"></i></button>
                            <button type="button" class="btn btn-default sketchpad_buttons" id="sketchpad_signature" title="{{ trans('nosh.signature_tool') }}"><i class="fa fa-thumbs-o-up fa-fw fa-lg"></i></button>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"><span id="sketchpad_pen_width" style="margin-right:10px;" nosh-data-value="4"><i class="fa fa-circle" style="font-size:4px;"></i></span><span class="caret"></span></button>
                                <ul class="dropdown-menu pull-right" role="menu">
                                    <li><a href="#" class="sketchpad_pen_width_num" nosh-data-value="2"><i class="fa fa-circle" style="font-size:2px;"></i></a></li>
                                    <li><a href="#" class="sketchpad_pen_width_num" nosh-data-value="4"><i class="fa fa-circle" style="font-size:4px;"></i></a></li>
                                    <li><a href="#" class="sketchpad_pen_width_num" nosh-data-value="6"><i class="fa fa-circle" style="font-size:6px;"></i></a></li>
                                    <li><a href="#" class="sketchpad_pen_width_num" nosh-data-value="8"><i class="fa fa-circle" style="font-size:8px;"></i></a></li>
                                </ul>
                            </div>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"><span id="sketchpad_color" style="margin-right:10px;" nosh-data-value=""><i id="sketchpad_color_icon" class="fa fa-square fa-lg"></i></span><span class="caret"></span></button>
                                <ul class="dropdown-menu pull-right" role="menu" id="sketchpad_color_list"></ul>
                            </div>
                        </div>
                        <div>
                            <div class="btn-group" id="sketchpad_text_options">
                                <button type="button" data-toggle="button" class="btn btn-default" id="sketchpad_text_bold"><i class="fa fa-bold fa-fw fa-lg"></i></button>
                                <button type="button" data-toggle="button" class="btn btn-default" id="sketchpad_text_italic"><i class="fa fa-italic fa-fw fa-lg"></i></button>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"><span id="sketchpad_text_size" style="margin-right:10px;" nosh-data-value="12pt">12</span><span class="caret"></span></button>
                                    <ul class="dropdown-menu" role="menu">
                                        <li><a href="#" class="sketchpad_text_size_num" nosh-data-value="10pt">10</a></li>
                                        <li><a href="#" class="sketchpad_text_size_num" nosh-data-value="12pt">12</a></li>
                                        <li><a href="#" class="sketchpad_text_size_num" nosh-data-value="14pt">14</a></li>
                                        <li><a href="#" class="sketchpad_text_size_num" nosh-data-value="16pt">16</a></li>
                                    </ul>
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"><span id="sketchpad_text_font" style="margin-right:10px;" nosh-data-value="Arial">Arial</span><span class="caret"></span></button>
                                    <ul class="dropdown-menu" role="menu">
                                        <li><a href="#" class="sketchpad_font_name" nosh-data-value="Arial" style="font-family:Arial;">Arial</a></li>
                                        <li><a href="#" class="sketchpad_font_name" nosh-data-value="Courier" style="font-family:Courier;">Courier</a></li>
                                        <li><a href="#" class="sketchpad_font_name" nosh-data-value="Times" style="font-family:Times;">Times</a></li>
                                        <li><a href="#" class="sketchpad_font_name" nosh-data-value="Veranda" style="font-family:Veranda;">Veranda</a></li>
                                    </ul>
                                </div>
                                <input type="text" class="form-control" id="sketchpad_textarea" spellcheck="false" data-toggle="popover" title="Hint" data-content="{{ trans('nosh.text_instruct') }}" placeholder="{{ trans('nosh.text_placeholder') }}"/>
                            </div>
                        </div>
                        <div id="sketchpad_div" style="margin-top:20px;">
                            <canvas id="sketchpad" class="sketchpad_class" height="100" width="100"></canvas>
                            <canvas id="sketchpad_temp" class="sketchpad_class" height="100" width="100"></canvas>
                        </div>
                        {!! $content !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
    $.fn.brushTool = function(sketchpad) {
        // SET ESSENTIALS
        var $canvas = this;
        $canvas.unbind();
        sketchpad.clicks = 0;
        var startX, startY, endX, endY;
        var drawLine = function() {
            $canvas.drawLine({
                strokeWidth: sketchpad.stroke.attr('nosh-data-value'),
                strokeStyle: sketchpad.color,
                strokeCap: 'round',
                strokeJoin: 'round',
                x1: startX, y1: startY,
                x2: endX, y2: endY
            });
        };
        $canvas.on(sketchpad.getTouchEventName('mousedown'), function(event) {
            sketchpad.hist.push(sketchpad.last.src=$canvas[0].toDataURL('image/png'));
            sketchpad.undoHist.length = 0;
            if (sketchpad.press === true) {sketchpad.clicks = 0;}
            if (sketchpad.clicks === 0) {
                sketchpad.drag = true;
                var pageCoords = sketchpad.getPageCoords(event);
                startX = pageCoords.pageX;
                startY = pageCoords.pageY;
                endX = startX;
                endY = startY;
                $canvas.drawArc({
                    fillStyle: sketchpad.color,
                    x: startX, y: startY,
                    radius: (sketchpad.stroke.attr('nosh-data-value') / 2),
                    start: 0,
                    end: 360
                });
                sketchpad.clicks += 1;
            }
            event.preventDefault();
        });
        $canvas.on(sketchpad.getTouchEventName('mouseup'), function(event) {
            sketchpad.drag = false;
            sketchpad.last.src = $canvas[0].toDataURL('image/png');
            sketchpad.clicks = 0;
            event.preventDefault();
        });
        $canvas.on(sketchpad.getTouchEventName('mousemove'), function(event) {
            if (sketchpad.drag === true && sketchpad.clicks >= 1) {
                startX = endX;
                startY = endY;
                var pageCoords = sketchpad.getPageCoords(event);
                endX = pageCoords.pageX;
                endY = pageCoords.pageY;
                drawLine();
            }
            event.preventDefault();
        });
    };

    $.fn.textTool = function(sketchpad) {
        var fontString = '';
        $('#sketchpad_textarea').val('');
        // SET ESSENTIALS
        var $canvas = this;
        $canvas.unbind();
        sketchpad.clicks = 0;
        var startX, startY, currentX, currentY, width, height;
        function makeText() {
            if ($('#sketchpad_text_bold').hasClass('active')) {
                fontString = 'bold';
            }
            if ($('#sketchpad_text_italic').hasClass('active')) {
                if ($('#sketchpad_text_bold').hasClass('active')) {
                    fontString = 'bold italic';
                } else {
                    fontString = 'italic';
                }
            }
            $canvas.drawText({
                fillStyle: sketchpad.color,
                // strokeWidth: sketchpad.stroke.attr('nosh-data-value'),
                fontStyle: fontString,
                fontSize: $('#sketchpad_text_size').text(),
                fontFamily: $('#sketchpad_text_font').text(),
                maxWidth: $('#sketchpad_textarea').width() - 2,
                x: startX, y: startY,
                text: $('#sketchpad_textarea').val()
            });
            $('#sketchpad_textarea').val('');
        }
        // MOUSE DOWN STARTS DRAWING
        $canvas.on(sketchpad.getTouchEventName('mousedown'), function(event) {
            sketchpad.hist.push(sketchpad.last.src=$canvas[0].toDataURL('image/png'));
            var pageCoords = sketchpad.getPageCoords(event);
            startX = pageCoords.pageX;
            startY = pageCoords.pageY;
            width = 0;
            height = 0;
            if ($('#sketchpad_textarea').val() !== '') {
                makeText();
            }
            sketchpad.drag = true;
            event.preventDefault();
        });
    };

    $.fn.ellipseTool = function(sketchpad) {
        // SET ESSENTIALS
        var $canvas = this;
        $canvas.unbind();
        var startX, startY, currentX, currentY, width, height;
        // DRAW ELLIPSE
        function makeEllipse() {
            $canvas.drawEllipse({
                fillStyle: sketchpad.color,
                x: currentX, y: currentY,
                width: Math.abs(width), height: Math.abs(height),
                fromCenter: false
            });
        }
        // MOUSE DOWN STARTS DRAWING
        $canvas.on(sketchpad.getTouchEventName('mousedown'), function(event) {
            sketchpad.hist.push(sketchpad.last.src=$canvas[0].toDataURL('image/png'));
            sketchpad.undoHist.length = 0;
            sketchpad.drag = true;
            width = 0;
             height = 0;
            var pageCoords = sketchpad.getPageCoords(event);
             startX = pageCoords.pageX;
            startY = pageCoords.pageY;
            makeEllipse();
            event.preventDefault();
        });
        // MOUSE UP STOPS DRAWING
        $canvas.on(sketchpad.getTouchEventName('mouseup'), function(event) {
            sketchpad.drag = false;
            sketchpad.last.src = $canvas[0].toDataURL('image/png');
            event.preventDefault();
        });
        $canvas.on(sketchpad.getTouchEventName('mousemove'), function(event) {
            if (sketchpad.drag === true) {
                sketchpad.clearCanvas();
                $canvas.drawImage({
                    source:sketchpad.last.src,
                    x: 0, y: 0,
                    width: sketchpad.canvasW, height: sketchpad.canvasH,
                    fromCenter: false,
                    load: function() {
                        var pageCoords = sketchpad.getPageCoords(event);
                        var eventX = pageCoords.pageX;
                        var eventY = pageCoords.pageY;
                        var dx = eventX - startX;
                        var dy = eventY - startY;
                        if (dx < 0) {
                            currentX = eventX;
                        } else {
                            currentX = startX;
                        }
                        if (dy < 0 && sketchpad.press === false) {
                            currentY = eventY;
                        } else {
                            currentY = startY;
                        }
                        width = Math.abs(dx);
                        height = Math.abs(dy);
                        makeEllipse();
                    }
                });
                event.preventDefault();
            }
        });
    };
    $.fn.rectTool = function(sketchpad) {
        // DECLARE VARIABLES
        var $canvas = this;
        $canvas.unbind();
        var startX, startY, currentX, currentY, width, height;
        // MAKE RECTANGLES
        function makeRect() {
            $canvas.drawRect({
                fillStyle: 'red',
                x: currentX, y: currentY,
                width: Math.abs(width),
                height: Math.abs(height),
                fromCenter: false
            });
        }
        // MOUSE DOWN STARTS DRAWING
        $canvas.on(sketchpad.getTouchEventName('mousedown'), function(event) {
            sketchpad.hist.push(sketchpad.last.src=$canvas[0].toDataURL('image/png'));
            var pageCoords = sketchpad.getPageCoords(event);
            startX = pageCoords.pageX;
            startY = pageCoords.pageY;
            width = 0;
            height = 0;
            makeRect();
            sketchpad.drag = true;
            event.preventDefault();
        });
        // MOUSE UP STOPS DRAWING
        $canvas.on(sketchpad.getTouchEventName('mouseup'), function(event) {
            sketchpad.drag = false;
            width = 0;
            height = 0;
            sketchpad.last.src=$canvas[0].toDataURL('image/png');
            event.preventDefault();
        });
        // DRAG MOUSE TO DRAW
        $canvas.on(sketchpad.getTouchEventName('mousemove'), function(event) {
            if (sketchpad.drag === true) {
                sketchpad.clearCanvas();
                $canvas.drawImage({
                    source:sketchpad.last.src,
                    x: 0, y: 0,
                    width: sketchpad.canvasW, height: sketchpad.canvasH,
                    fromCenter: false,
                    load: function() {
                        var pageCoords = sketchpad.getPageCoords(event);
                        var eventX = pageCoords.pageX;
                        var eventY = pageCoords.pageY;
                        var dx = eventX - startX;
                        var dy = eventY - startY;
                        if (dx < 0) {
                            currentX = eventX;
                        } else {
                            currentX = startX;
                        }
                        if (dy < 0) {
                            currentY = eventY;
                        } else {
                            currentY = startY;
                        }
                        width = Math.abs(dx);
                        height = Math.abs(dy);
                        makeRect();
                    }
                });
                event.preventDefault();
            }
        });
    };
    $.fn.signatureTool = function(sketchpad) {
        // SET ESSENTIALS
        var $canvas = this;
        $canvas.unbind();
        sketchpad.clicks = 0;
        var startX, startY, currentX, currentY, width, height;
        function makeSig() {
            $canvas.drawImage({
                source: noshdata.signature,
                x: startX, y: startY,
                fillStyle: 'transparent',
            });
        }
        // MOUSE DOWN STARTS DRAWING
        $canvas.on(sketchpad.getTouchEventName('mousedown'), function(event) {
            sketchpad.hist.push(sketchpad.last.src=$canvas[0].toDataURL('image/png'));
            var pageCoords = sketchpad.getPageCoords(event);
            startX = pageCoords.pageX;
            startY = pageCoords.pageY;
            width = 0;
            height = 0;
            makeSig();
            sketchpad.drag = true;
            event.preventDefault();
        });
    };

    $(document).ready(function() {
        var sketchpad = {
            $canvas: $('#sketchpad'),
            stroke: $('#sketchpad_pen_width'),
            press: false,
            last: new Image(),
            hist: [],
            undoHist: [],
            clicks: 0,
            start: false
        };
        function updateCanvasSize(w,h) {
            var image = sketchpad.$canvas.getCanvasImage('image/png');
            sketchpad.canvasW = w;
            sketchpad.canvasH = h;
            sketchpad.$canvas.prop({
                width: sketchpad.canvasW,
                height: sketchpad.canvasH
            });
            sketchpad.$canvas.detectPixelRatio();
            if (image.length > 10) {
                sketchpad.$canvas.drawImage({
                    source: image,
                    x: 0, y: 0,
                    width: sketchpad.canvasW, height: sketchpad.canvasH,
                    fromCenter: false
                });
            }
        }
        var $$ = {
            stroke: $('#sketchpad_pen_width'),
            strokeContainer: $('#sketchpad_stroke-container'),
            box: $('#sketchpad_box'),
            tools: $('#sketchpad_tools'),
            clear: $('#clear'),
            slider: $('#sketchpad_slider'),
            colors: $('#sketchpad_color'),
            brush: $('#sketchpad_brush'),
            text: $('#sketchpad_text'),
            rect: $('#sketchpad_rect'),
            ellipse: $('#sketchpad_ellipse'),
            signature: $('#sketchpad_signature'),
            undo: $('#sketchpad_undo'),
            redo: $('#sketchpad_redo'),
            save: $('#save')
        };
        var duration;
        // Map standard mouse events to touch events
        var mouseEventMap = {
            'mousedown': 'touchstart',
            'mouseup': 'touchend',
            'mousemove': 'touchmove'
        };
        // Convert mouse event name to a corresponding touch event name (if possible)
        function getTouchEventName(eventName) {
            // Detect touch event support
            if (window.ontouchstart !== undefined) {
                if (mouseEventMap[eventName]) {
                    eventName = mouseEventMap[eventName];
                }
            }
            return eventName;
        }
        sketchpad.getTouchEventName = getTouchEventName;

        function getPageCoords(event) {
            canoffset = $('#sketchpad').offset();
            var x,y;
            if (event.originalEvent.changedTouches) {
                x = event.originalEvent.changedTouches[0].clientX + document.body.scrollLeft + document.documentElement.scrollLeft - Math.floor(canoffset.left);
                y = event.originalEvent.changedTouches[0].clientY + document.body.scrollTop + document.documentElement.scrollTop - Math.floor(canoffset.top) + 1;
            } else {
                x = event.clientX + document.body.scrollLeft + document.documentElement.scrollLeft - Math.floor(canoffset.left);
                y = event.clientY + document.body.scrollTop + document.documentElement.scrollTop - Math.floor(canoffset.top) + 1;
            }
            return {
                // pageX: event.pageX,
                // pageY: event.pageY
                pageX: x,
                pageY: y
            };
        }

        sketchpad.getPageCoords = getPageCoords;

        // Clear canvas and set background
        function clearCanvas() {
            sketchpad.$canvas.drawRect({
                fillStyle: '#fff',
                x: 0, y: 0,
                width: sketchpad.canvasW, height: sketchpad.canvasH,
                fromCenter: false
            });
        }
        sketchpad.clearCanvas = clearCanvas;

        function drawCanvasState(image) {
            sketchpad.$canvas.clearCanvas();
            sketchpad.$canvas.drawImage({
                source: image,
                x: 0, y: 0,
                width: sketchpad.canvasW, height: sketchpad.canvasH,
                fromCenter: false
            });
        }

        // UPDATE STROKE
        function updateStroke() {
            $$.stroke.width(sketchpad.stroke.attr('nosh-data-value'));
            $$.stroke.height(sketchpad.stroke.attr('nosh-data-value'));
            $$.stroke.css({
                marginLeft: ($$.box.width() - $$.stroke.width()) / 2,
                marginTop: ($$.box.height() - $$.stroke.height()) / 2
            });
        if (sketchpad.start === false) {
            $$.stroke.css({backgroundColor: sketchpad.color});
            sketchpad.start += 1;
        } else if (sketchpad.start === true) {
            $$.stroke.stop().animate({backgroundColor: sketchpad.color}, duration);
        }
            sketchpad.start = true;
        }

        var colorMap = {
            red: {
                dark: '#a11',
                medium: '#c33',
                light: '#e55'
            },
            green: {
                dark: '#4b1',
                medium: '#6d2',
                light: '#8f4'
            },
            blue: {
                dark: '#14b',
                medium: '#36d',
                light: '#58f'
            },
            orange: {
                dark: '#d51',
                medium: '#f73',
                light: '#f95'
            },
            yellow: {
                dark: '#ed2',
                medium: '#fe3',
                light: '#ff5'
            },
            purple: {
                dark: '#75d',
                medium: '#96f',
                light: '#b8f'
            },
            black: {
                dark: '#000',
                medium: '#999',
                light: '#fff'
            }
        };
        var colors = ['red', 'green', 'blue', 'orange', 'yellow', 'purple', 'brown', 'white', 'black'];
        var shades = ['light', 'medium', 'dark'];

        // ADD COLORS
        function addColors() {
            var color, c, s;
            function addColor(color, shade) {
                if (colorMap[color] && colorMap[color][shade]) {
                    var colorname = shade + ' ' + color;
                    if (colorname == 'light black') {
                        colorname = 'white';
                    }
                    if (colorname == 'dark black') {
                        colorname = 'black';
                    }
                    if (colorname == 'medium black') {
                        colorname = 'gray';
                    }
                    $('<li><a href="#" class="sketchpad_color_item" nosh-data-value="' + colorMap[color][shade] + '"><i class="fa fa-square fa-lg" style="color:' + colorMap[color][shade] + '"></i><span style="margin:20px;">' + colorname + '</span></a></li>')
                    .appendTo('#sketchpad_color_list');
                }
            }
            for (s = 0; s < shades.length; s += 1) {
                for (c = 0; c < colors.length; c += 1) {
                    color = colors[c];
                    addColor(color, shades[s]);
                }
            }
        }
        // CHOSEN TOOL
        $$.tools.on('click', '.tool', function() {
            $$.tools.find('.chosen').removeClass('chosen');
            $(this).addClass('chosen');
        });
        // CLEAR CANVAS BUTTON
        $$.clear.on('click', function() {
            sketchpad.$canvas.trigger('mouseup');
            sketchpad.last.src = sketchpad.$canvas[0].toDataURL('image/png');
            sketchpad.hist.push(sketchpad.last.src);
            clearCanvas();
            sketchpad.clicks = 0;
        });
        // UNDO BUTTON
        $$.undo.on('click', function() {
            sketchpad.$canvas.mouseup();
            if (sketchpad.hist.length > 0) {
                sketchpad.clicks = 0;
                sketchpad.undoHist.push(sketchpad.$canvas[0].toDataURL('image/png'));
                var last = sketchpad.hist.pop();
                drawCanvasState(last);
            }
        });
        $$.redo.on('click', function () {
            sketchpad.$canvas.mouseup();
            if (sketchpad.undoHist.length > 0) {
                sketchpad.clicks = 0;
                var last = sketchpad.undoHist.pop();
                sketchpad.hist.push(sketchpad.$canvas[0].toDataURL('image/png'));
                drawCanvasState(last);
            }
        });
        // PAINT TOOL BUTTON
        $$.brush.on('click', function() {
            $('.sketchpad_buttons').removeClass('active');
            $(this).addClass('active');
            $('#sketchpad_text_options').hide();
            sketchpad.last.src = sketchpad.$canvas[0].toDataURL('image/png');
            sketchpad.$canvas.brushTool(sketchpad);
        });
        // TEXT TOOL BUTTON
        $$.text.on('click', function() {
            $('.sketchpad_buttons').removeClass('active');
            $(this).addClass('active');
            $('#sketchpad_text_options').show();
            sketchpad.last.src = sketchpad.$canvas[0].toDataURL('image/png');
            sketchpad.$canvas.textTool(sketchpad);
        });
        // RECT TOOL BUTTON
        $$.rect.on('click', function() {
            $('.sketchpad_buttons').removeClass('active');
            $(this).addClass('active');
            $('#sketchpad_text_options').hide();
            sketchpad.last.src = sketchpad.$canvas[0].toDataURL('image/png');
            sketchpad.$canvas.rectTool(sketchpad);
        });
        // ELLIPSE TOOL BUTTON
        $$.ellipse.on('click', function() {
            $('.sketchpad_buttons').removeClass('active');
            $(this).addClass('active');
            $('#sketchpad_text_options').hide();
            sketchpad.last.src = sketchpad.$canvas[0].toDataURL('image/png');
            sketchpad.$canvas.ellipseTool(sketchpad);
        });
        // SIGNATURE TOOL BUTTON
        $$.signature.on('click', function() {
            $('.sketchpad_buttons').removeClass('active');
            $(this).addClass('active');
            $('#sketchpad_text_options').hide();
            sketchpad.last.src = sketchpad.$canvas[0].toDataURL('image/png');
            sketchpad.$canvas.signatureTool(sketchpad);
        });
        // CHOOSE COLOR
        addColors();
        // DEFAULT STUFF
        // $$.brush.click();
        $('#clear_tool').on('click', function() {
            $('.sketchpad_buttons').removeClass('active');
            $('#sketchpad_text_options').hide();
            sketchpad.last.src = sketchpad.$canvas[0].toDataURL('image/png');
            $('#sketchpad').unbind();
        });
        $('#sketchpad_color').attr('nosh-data-value', colorMap.black.dark);
        $('#sketchpad_color_icon').css({
            color: colorMap.black.dark
        });
        $('#sketchpad_text_options').hide();
        $("#image_form").submit(function() {
            $("#image").val($('#sketchpad').getCanvasImage());
            return true;
        });
        if (noshdata.signature === '') {
            $('#sketchpad_signature').hide();
        }
        function imageload() {
            var a = $("#image_select").val();
            $("#image_path").val(a);
            if (a !== '') {
                $.ajax({
                    url: noshdata.image_dimensions,
                    data: "file=" + a,
                    dataType: "json",
                    type: "POST",
                    success: function(data){
                        $('.sketchpad_class').attr('width', data.width);
                        $('.sketchpad_class').attr('height', data.height);
                        updateCanvasSize(data.width, data.height);
                        $('#sketchpad').drawImage({
                            source: a,
                            x: 0, y: 0,
                            width: data.width,
                            height: data.height,
                            fromCenter: false
                        });
                    }
                });
            }
        }
        if ($("#image_select").length) {
            if ($("#image_select").val() !== '') {
                imageload();
            }
        }
        $("#image_select").change(function() {
            imageload();
        });
        if ($("#image_src").val()) {
            var w = $("#image_src_width").val();
            var h = $("#image_src_height").val();
            var image = $("#image_src").val();
            updateCanvasSize(w, h);
            $('#sketchpad').drawImage({
                source: image,
                x: 0, y: 0,
                width: w,
                height: h,
                fromCenter: false
            });
        }
        $('.sketchpad_text_size_num').css('cursor', 'pointer').click(function(){
            $('#sketchpad_text_size').attr('nosh-data-value', $(this).attr('nosh-data-value')).text($(this).text());
        });
        $('.sketchpad_text_font_name').css('cursor', 'pointer').click(function(){
            $('#sketchpad_text_font').attr('nosh-data-value', $(this).attr('nosh-data-value')).text($(this).text());
        });
        $('.sketchpad_pen_width_num').css('cursor', 'pointer').click(function(){
            $('#sketchpad_pen_width').attr('nosh-data-value', $(this).attr('nosh-data-value')).html($(this).html());
        });
        $('.sketchpad_color_item').css('cursor', 'pointer').click(function(){
            $('#sketchpad_color').attr('nosh-data-value', $(this).attr('nosh-data-value'));
            $('#sketchpad_color_icon').css({
                color: $(this).attr('nosh-data-value')
            });
            sketchpad.color = $(this).attr('nosh-data-value');
        });
        $('[data-toggle=offcanvas]').css('cursor', 'pointer').click(function() {
            $('.row-offcanvas').toggleClass('active');
        });
        $('[data-toggle="popover"]').popover({
            placement: 'auto'
        });
        $('#sketchpad_textarea').css('cursor', 'pointer').click(function(){
            setTimeout(function(){$('#sketchpad_textarea').popover('hide');},3000);
        });
        if (noshdata.message_action !== '') {
            toastr.success(noshdata.message_action);
        }
    });
</script>
@endsection
