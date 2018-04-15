@extends('layouts.app')

@section('content')
@if (isset($sidebar_content))
    <div class="container-fluid">
@else
    <div class="container">
@endif
    <div class="row">
        <div class="col-md-4">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4 class="panel-title">Schedule</h4>
                </div>
                <div class="panel-body">
                    <div id="datetimepicker"></div>
                    <form class="form-horizontal" role="form">
                         <div class="form-group">
                             <label for="provider_list" class="col-md-4 control-label">Provider</label>
                             <div class="col-md-8">
                                 <select id="provider_list" class="form-control" name="provider_list" value="@if (isset($provider_id)){{ $provider_id }}@endif">
                                     {!! $provider_list !!}
                                 </select>
                             </div>
                         </div>
                    </form>
                    @if (isset($colorlegend))
                        <div style="margin:5px;"><i style="color:green;" class="fa fa-square-o fa-lg"></i> Attended</div>
                        <div style="margin:5px;"><i style="color:black;" class="fa fa-square-o fa-lg"></i> DNKA</div>
                        <div style="margin:5px;"><i style="color:red;" class="fa fa-square-o fa-lg"></i> LMC</div>
                    @endif
                    <div style="margin:15px;">
                        <button type="button" id="schedule_view_button" class="btn btn-default btn-block"></button>
                    </div>
                </div>
            </div>
        </div>
        @if (isset($provider_id))
            <div class="col-md-8">
            <!-- FullCalendar -->
                <div id="calendar"></div>
            </div>
        @endif
    </div>
</div>
@if (isset($provider_id))
    <div class="modal" id="scheduleModal" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModal_title">Choose a type of event</h5>
                </div>
                <div class="modal-body">
                    <button type="button" id="appointment_button" class="btn btn-default">Patient Appointment</button>
                    <button type="button" id="event_button" class="btn btn-default">Other Event</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal" id="eventModal" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModal_title"></h5>
                </div>
                <div class="modal-body" style="height:85vh;overflow-y:auto;">
                    @if (Session::get('group_id') != '100')
                        <div style="margin:15px">
                            <form class="input-group form nosh-appt" border="0" id="search_patient_appointment_form" role="search" action="{{ url('search_patient') }}" method="POST" style="margin-bottom:0px;" data-nosh-target="search_patient_appointment_results" data-nosh-search-to="pid">
                                <input type="text" class="form-control search" id="search_patient_appointment" name="search_patient" placeholder="Search Patient" style="margin-bottom:0px;" required autocomplete="off">
                                <input type="hidden" name="type" value="li">
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn-md" id="search_patient_appointment_submit" value="Go"><i class="glyphicon glyphicon-search"></i></button>
                                </span>
                            </form>
                            <div class="list-group" id="search_patient_appointment_results"></div>
                        </div>
                    @endif
                    <form id="event_form" class="form-horizontal form" role="form" method="POST" action="{{ url('edit_event') }}">
                        <input type="hidden" name="pid" id="pid">
                        <input type="hidden" name="event_id" id="event_id">
                        <input type="hidden" name="title" id="title">
                        <input type="hidden" name="provider_id" value="{{ $provider_id }}" nosh-no-clear>
                        <!-- <div class="form-group nosh-appt nosh-pt-appt nosh-event" id="event_id_div">
                            <div class="col-md-3">Event ID</div>
                            <div class="col_md-8" id="event_id_span"></div>
                        </div> -->
                        <!-- <div class="form-group nosh-appt nosh-pt-appt nosh-event" id="pid_div">
                            <div class="col-md-3">Patient ID</div>
                            <div class="col_md-8" id="pid_span"></div>
                        </div> -->
                        @if (Session::get('group_id') != '100')
                            <div class="form-group nosh-appt" id="patient_name_div">
                                <label class="col-md-3 control-label">Patient</label>
                                <div class="col-md-8">
                                    <p class="form-control-static" id="patient_name"></p>
                                </div>
                            </div>
                        @endif
                        <div class="form-group nosh-appt nosh-appt-old">
                            <label for="status" class="col-md-3 control-label">Status</label>
                            <div class="col-md-8">
                                <select id="status" class="form-control" name="status" value="">
                                    <option value="">None</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Reminder Sent">Reminder Sent</option>
                                    <option value="Attended">Attended</option>
                                    <option value="LMC">Last Minute Cancellation</option>
                                    <option value="DNKA">Did Not Keep Appointment</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group nosh-appt nosh-event nosh-pt-appt">
                            <label for="start_date" class="col-md-3 control-label">Start Date</label>
                            <div class="col-md-8">
                                @if (Session::get('group_id') != '100')
                                    <input type="date" id="start_date" class="form-control" name="start_date" value="" required>
                                @else
                                    <input type="date" id="start_date" class="form-control" name="start_date" value="" required readonly>
                                @endif
                            </div>
                        </div>
                        <div class="form-group nosh-appt nosh-event nosh-pt-appt">
                            <label for="start_time" class="col-md-3 control-label">Start Time</label>
                            <div class="col-md-8">
                                @if (Session::get('group_id') != '100')
                                    <input type="text" id="start_time" class="form-control nosh-time1" name="start_time" value="" required>
                                @else
                                    <input type="text" id="start_time" class="form-control" name="start_time" value="" required readonly>
                                @endif
                            </div>
                        </div>
                        <div class="form-group nosh-appt nosh-event" id="end_div">
                            <label for="end" class="col-md-3 control-label">End Time</label>
                            <div class="col-md-8">
                                <input type="text" id="end" class="form-control nosh-time1" name="end" value="">
                            </div>
                        </div>
                        <div class="form-group nosh-appt nosh-pt-appt">
                            <label for="visit_type" class="col-md-3 control-label">Visit Type</label>
                            <div class="col-md-8">
                                <select id="visit_type" class="form-control" name="visit_type" value="" required>
                                    {!! $visit_type !!}
                                </select>
                            </div>
                        </div>
                        <div class="form-group nosh-appt nosh-event nosh-pt-appt">
                            <label for="reason" class="col-md-3 control-label">Reason</label>
                            <div class="col-md-8">
                                <textarea id="reason" class="form-control" name="reason" value="" rows="5"></textarea>
                            </div>
                        </div>
                        <div class="form-group nosh-appt">
                            <label for="notes" class="col-md-3 control-label">Notes/Tasks</label>
                            <div class="col-md-8">
                                <textarea id="notes" class="form-control" name="notes" value="" rows="5"></textarea>
                            </div>
                        </div>
                        <div class="form-group nosh-event">
                            <label for="repeat" class="col-md-3 control-label">Repeat</label>
                            <div class="col-md-8">
                                <select id="repeat" class="form-control" name="repeat" value="">
                                    <option value="">None</option>
                                    <option value="86400">Every Day</option>
                                    <option value="604800">Every Week</option>
                                    <option value="1209600">Every Other Week</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group nosh-event" id="until_div">
                            <label for="until" class="col-md-3 control-label">Until</label>
                            <div class="col-md-8">
                                <input type="date" id="until" class="form-control" name="until" value="" placeholder="Leave blank if repeat goes on forever">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-md-11 col-md-offset-1">
                                <button type="submit" class="btn btn-success" style="margin:10px">
                                    <i class="fa fa-btn fa-save"></i> Save
                                </button>
                                <button type="button" class="btn btn-success" style="margin:10px" id="event_encounter">
                                    <i class="fa fa-btn fa-forward"></i> Encounter
                                </button>
                                <button type="button" class="btn btn-danger" style="margin:10px" id="event_cancel">
                                    <i class="fa fa-btn fa-ban"></i> Cancel
                                </button>
                                <button type="button" class="btn btn-danger" style="margin:10px" id="event_delete">
                                    <i class="fa fa-btn fa-ban"></i> Delete
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('view.scripts')
<script type="text/javascript">
    function addMinutes(date, minutes) {
        var d = new Date(date);
        return d.getTime() + minutes*60000;
    }
    function isOverlapping(start){
        var array = $('#calendar').fullCalendar('clientEvents');
        var end = addMinutes(start, 15);
        for(var i in array){
            if(!(array[i].start >= end || array[i].end <= start)){
                return true;
            }
        }
        return false;
    }
    $(function () {
        $('#datetimepicker').datetimepicker({
            inline: true,
            keepOpen: true,
            showTodayButton: true,
            format: 'MM/dd/YYYY'
        });
    });
    $(document).ready(function() {
        $('[data-toggle=offcanvas]').css('cursor', 'pointer').click(function() {
            $('.row-offcanvas').toggleClass('active');
        });
        $('#calendar').fullCalendar({
            weekends: noshdata.weekends,
            minTime: noshdata.minTime,
            maxTime: noshdata.maxTime,
            allDayDefault: false,
            slotDuration: '00:15:00',
            defaultView: 'agendaDay',
            aspectRatio: 0.3,
            header: false,
            editable: true,
            timezone: noshdata.timezone,
            events: noshdata.provider_schedule,
            dayClick: function(date, jsEvent, view) {
                if (noshdata.group_id == 'schedule') {
                    if(confirm('You will need to login to schedule an appointment.  Proceed?')){
                        window.location = noshdata.login_url;
                    }
                } else {
                    if (noshdata.group_id != '1') {
                        if (noshdata.group_id != '100') {
                            $('#event_form').clearForm();
                            $('#start_date').val(date.format('YYYY-MM-DD'));
                            $('#start_time').val(date.format('hh:mmA'));
                            $('#event_id_div').hide();
                            $('#pid_div').hide();
                            $('#scheduleModal').modal('show');
                        } else {
                            if (isOverlapping(date)) {
                                toastr.error('You cannot schedule an appointment in this time slot!');
                            } else {
                                $('#event_form').clearForm();
                                $('#start_date').val(date.format('YYYY-MM-DD'));
                                $('#start_time').val(date.format('hh:mmA'));
                                if (noshdata.pid !== '') {
                                    $('#pid').val(noshdata.pid);
                                    $('#title').val(noshdata.pt_name);
                                    $('#patient_name').text(noshdata.pt_name);
                                }
                                $('.nosh-pt-appt').show();
                                $('.nosh-appt-old').hide();
                                $('#eventModal_title').text('{{ trans('nosh.new_appointment') }}');
                                $('#event_id_div').hide();
                                $('#pid_div').hide();
                                $('#eventModal').modal('show');
                                $('#visit_type').focus();
                            }
                        }
                    }
                }
            },
            eventClick: function(calEvent, jsEvent, view) {
                if (noshdata.group_id != '1') {
                    $('#event_id').val(calEvent.id);
                    $('#event_id_span').text(calEvent.id);
                    $('#pid').val(calEvent.pid);
                    $('#pid_span').text(calEvent.pid);
                    $('#timestamp_span').text(calEvent.timestamp);
                    $('#start_date').val(calEvent.start.format('YYYY-MM-DD'));
                    $('#start_time').val(calEvent.start.format('hh:mmA'));
                    $('#end').val(calEvent.end.format('hh:mmA'));
                    $('#title').val(calEvent.title);
                    $('#visit_type').val(calEvent.visit_type);
                    if (calEvent.visit_type){
                        $('.nosh-event').hide();
                        $('.nosh-appt').show();
                        $('#event_encounter').show();
                        $('#eventModal_title').text('{{ trans('nosh.edit_appointment') }}');
                        $('#patient_name').text(calEvent.title);
                        $('#end').val('');
                        $('#end').prop('required', false);
                        $('#visit_type').prop('required', true);
                        $('#visit_type').focus();
                    } else {
                        $('.nosh-appt').hide();
                        $('.nosh-event').show();
                        $('#event_encounter').hide();
                        $('#eventModal_title').text('Edit Event');
                        $('#end').prop('required', true);
                        $('#visit_type').prop('required', false);
                        $('#reason').focus();
                    }
                    $('#reason').val(calEvent.reason);
                    $('#repeat').val(calEvent.repeat);
                    $('#until').val(calEvent.until);
                    var repeat_select = $('#repeat').val();
                    if (repeat_select !== ''){
                        $('#until_div').show();
                    } else {
                        $('#until_div').hide();
                        $('#until').val('');
                    }
                    $('#status').val(calEvent.status);
                    $('#notes').val(calEvent.notes);
                    if (calEvent.editable !== false) {
                        $('#event_id_div').show();
                        $('#pid_div').show();
                        $('#eventModal').modal('show');
                    }
                }
            },
            eventDragStart: function(event, jsEvent, ui, view) {
                $('.fc-event').each(function(){
                    $(this).tooltip('destroy');
                });
            },
            eventDrop: function(event, delta, revertFunc, jsEvent, ui, view) {
                if (noshdata.group_id != '1') {
                    var start = event.start.unix();
                    var end = event.end.unix();
                    if(start){
                        $.ajax({
                            type: "POST",
                            url: noshdata.drag_event,
                            data: "start=" + start + "&end=" + end + "&id=" + event.id,
                            success: function(data){
                                toastr.success(data);
                            }
                        });
                    } else {
                        revertFunc();
                    }
                    // $('.fc-event').each(function(){
                    //     $(this).tooltip('destroy');
                    // });
                } else {
                    revertFunc();
                    toastr.error("You don't have permission to do this.");
                }
            },
            eventResize: function(event, delta, revertFunc, jsEvent, ui, view) {
                if (noshdata.group_id != '1') {
                    var start = event.start.unix();
                    var end = event.end.unix();
                    if(start){
                        $.ajax({
                            type: "POST",
                            url: noshdata.drag_event,
                            data: "start=" + start + "&end=" + end + "&id=" + event.id,
                            success: function(data){
                                toastr.success(data);
                            }
                        });
                    } else {
                        revertFunc();
                    }
                    // $('.fc-event').each(function(){
                    //     $(this).tooltip('destroy');
                    // });
                } else {
                    revertFunc();
                    toastr.error("You don't have permission to do this.");
                }
            },
            eventRender: function(event, element) {
                var display = 'Reason: ' + event.reason + '<br>Status: ' + event.status + '<br>' + event.notes;
                element.tooltip({
                    html: true,
                    title: display,
                    trigger: 'hover'
                });
            }
        });
        if ($('#provider_list').attr('value') !== '') {
            $('#provider_list option[value=' + $('#provider_list').attr('value') + ']').prop('selected', true);
        }
        $('#provider_list').change(function() {
            var id = $('#provider_list').val();
            if (id !== '') {
                window.location = noshdata.schedule_url + '/' + id;
            }
        });
        $('#datetimepicker').on('dp.change', function (e) {
            $('#calendar').fullCalendar('gotoDate', e.date);
            $.cookie('nosh-schedule', e.date.format('YYYY-MM-DD'), { path: '/' });
        });
        if( $.cookie('nosh-schedule') !== null){
            $('#calendar').fullCalendar('gotoDate', moment($.cookie('nosh-schedule')));
            $('#datetimepicker').data("DateTimePicker").date(moment($.cookie('nosh-schedule')));
        }
        $('#appointment_button').css('cursor', 'pointer').click(function() {
            $('#scheduleModal').modal('hide');
            $('.nosh-event').hide();
            $('.nosh-appt').show();
            $('#event_encounter').hide();
            $('.nosh-appt-old').hide();
            if (noshdata.pid !== '') {
                $('#pid').val(noshdata.pid);
                $('#title').val(noshdata.pt_name);
                $('#patient_name').text(noshdata.pt_name);
            }
            $('#eventModal_title').text('{{ trans('nosh.new_appointment') }}');
            $('#event_delete').hide();
            $('#eventModal').modal('show');
            $('#end').prop('required', false);
            $('#visit_type').prop('required', true);
            $('#visit_type').focus();
        });
        $('#event_button').css('cursor', 'pointer').click(function() {
            $('#scheduleModal').modal('hide');
            $('.nosh-appt').hide();
            $('.nosh-event').show();
            $('#event_encounter').hide();
            $('#eventModal_title').text('New Event');
            $('#eventModal').modal('show');
            $('#end').prop('required', true);
            $('#visit_type').prop('required', false);
            $('#reason').focus();
        });
        $('#repeat').change(function() {
            var a = $("#repeat").val();
            if (a !== ''){
                $('#until_div').show();
            } else {
                $('#until_div').hide();
                $("#until").val('');
            }
        });
        $('#event_encounter').css('cursor', 'pointer').click(function() {
            var appt_id = $("#event_id").val();
            window.location = noshdata.event_encounter + '/' + appt_id;
        });
        $('#event_cancel').css('cursor', 'pointer').click(function() {
            $('#event_form').clearForm();
            $('#patient_name').html('');
            $('#event_delete').show();
            $('#eventModal').modal('hide');
        });
        $('#event_delete').css('cursor', 'pointer').click(function() {
            if(confirm('Are you sure you want to delete this appointment?')){
                var appt_id = $("#event_id").val();
                $.ajax({
                    type: "POST",
                    url: noshdata.delete_event,
                    data: "appt_id=" + appt_id,
                    success: function(data){
                        toastr.success(data);
                        $('#eventModal').modal('hide');
                        $('#event_form').clearForm();
                        $('#patient_name').html('');
                        $('#event_delete').show();
                        $('#calendar').fullCalendar('removeEvents');
                        $('#calendar').fullCalendar('refetchEvents');
                    }
                });
            }
        });
        if (noshdata.message_action !== '') {
            if (noshdata.message_action.search('Error - ') == -1) {
                toastr.success(noshdata.message_action);
            } else {
                toastr.error(noshdata.message_action);
            }
        }
        if ($('#calendar').fullCalendar('getView').type == 'agendaDay') {
            $('#schedule_view_button').text('Week View');
        } else {
            $('#schedule_view_button').text('Day View');
        };
        $('#schedule_view_button').click(function() {
            if ($('#calendar').fullCalendar('getView').type == 'agendaDay') {
                $('#calendar').fullCalendar('changeView', 'agendaWeek');
                $('#schedule_view_button').text('Day View');
            } else {
                $('#calendar').fullCalendar('changeView', 'agendaDay');
                $('#schedule_view_button').text('Week View');
            }
        });
    });
</script>
@endsection
