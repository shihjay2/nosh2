const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.styles([
    'public/assets/css/font-awesome.min.css',
    'public/assets/css/bootstrap.min.css',
    'public/assets/css/toastr.min.css',
    'public/assets/css/nosh-timeline.css',
    'public/assets/css/bootstrap-select.min.css',
    'public/assets/css/bootstrap-tagsinput.css',
    'public/assets/css/bootstrap-datetimepicker.css',
    'public/assets/css/jquery.fancybox.css',
    'public/assets/css/main.css'
], 'public/assets/css/builds/base.css');

mix.styles([
    'public/assets/css/font-awesome.min.css',
    'public/assets/css/bootstrap.min.css',
    'public/assets/css/toastr.min.css',
    'public/assets/css/nosh-timeline.css',
    'public/assets/css/bootstrap-select.min.css',
    'public/assets/css/bootstrap-tagsinput.css',
    'public/assets/css/bootstrap-datetimepicker.css',
    'public/assets/css/jquery.fancybox.css',
    'public/assets/css/fullcalendar.min.css',
    'public/assets/css/main.css'
], 'public/assets/css/builds/schedule.css');

mix.styles([
    'public/assets/css/font-awesome.min.css',
    'public/assets/css/bootstrap.min.css',
    'public/assets/css/toastr.min.css',
    'public/assets/css/nosh-timeline.css',
    'public/assets/css/bootstrap-select.min.css',
    'public/assets/css/bootstrap-tagsinput.css',
    'public/assets/css/bootstrap-datetimepicker.css',
    'public/assets/css/jquery.fancybox.css',
    'public/assets/css/fileinput.min.css',
    'public/assets/css/main.css'
], 'public/assets/css/builds/document_upload.css');

mix.styles([
    'public/assets/css/font-awesome.min.css',
    'public/assets/css/bootstrap.min.css',
    'public/assets/css/toastr.min.css',
    'public/assets/css/nosh-timeline.css',
    'public/assets/css/bootstrap-select.min.css',
    'public/assets/css/bootstrap-tagsinput.css',
    'public/assets/css/bootstrap-datetimepicker.css',
    'public/assets/css/jquery.fancybox.css',
    'public/assets/css/jquery.realperson.css',
    'public/assets/css/main.css'
], 'public/assets/css/builds/login.css');

mix.styles([
    'public/assets/css/font-awesome.min.css',
    'public/assets/css/bootstrap.min.css',
    'public/assets/css/toastr.min.css',
    'public/assets/css/nosh-timeline.css',
    'public/assets/css/bootstrap-select.min.css',
    'public/assets/css/bootstrap-tagsinput.css',
    'public/assets/css/bootstrap-datetimepicker.css',
    'public/assets/css/jquery.fancybox.css',
    'public/assets/css/jquery.signaturepad.css',
    'public/assets/css/main.css'
], 'public/assets/css/builds/signature.css');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js'
], 'public/assets/js/builds/base.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
    'public/assets/js/bluebutton.js',
    'public/assets/js/pediatric-immunizations.min.js'
], 'public/assets/js/builds/chart.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
], 'public/assets/js/builds/schedule.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
    'public/assets/js/canvas-to-blob.min.js',
    'public/assets/js/sortable.min.js',
    'public/assets/js/purify.min.js',
    'public/assets/js/fileinput.min.js'
], 'public/assets/js/builds/document_upload.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
    'public/assets/js/pdfobject.min.js',
], 'public/assets/js/builds/documents.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
    'public/assets/js/highcharts.js',
    'public/assets/js/exporting.js',
    'public/assets/js/offline-exporting.js'
], 'public/assets/js/builds/graph.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
    'public/assets/js/jcanvas.min.js',
], 'public/assets/js/builds/image.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
    'public/assets/js/jquery.realperson.js',
], 'public/assets/js/builds/login.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
    'public/assets/js/sigma.min.js',
    'public/assets/js/plugins/sigma.parsers.json.min.js',
    'public/assets/js/plugins/sigma.plugins.dragNodes.min.js'
], 'public/assets/js/builds/sigma.js');

mix.scripts([
    'public/assets/js/jquery-3.4.1.min.js',
    'public/assets/js/bootstrap.min.js',
    'public/assets/js/moment.min.js',
    'public/assets/js/jquery.maskedinput.min.js',
    'public/assets/js/toastr.min.js',
    'public/assets/js/bootstrap3-typeahead.min.js',
    'public/assets/js/jquery.cookie.js',
    'public/assets/js/bootstrap-list-filter.min.js',
    'public/assets/js/jquery-idleTimeout.js',
    'public/assets/js/bootstrap-tagsinput.js',
    'public/assets/js/jquery.selectboxes.js',
    'public/assets/js/bootstrap-select.min.js',
    'public/assets/js/bootstrap-datetimepicker.min.js',
    'public/assets/js/jquery.fileDownload.js',
    'public/assets/js/jstz-1.0.4.min.js',
    'public/assets/js/jquery.fancybox.js',
    'public/assets/js/jquery.signaturepad.min.js',
], 'public/assets/js/builds/signature.js');

mix.version();
