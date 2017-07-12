<div id="uma_invitation_request">
	<div align="center" >
		<div align="left" class="ui-corner-all ui-tabs ui-widget ui-widget-content">
			<?php if (Session::has('uma_error')) {?>
				<div>
					<strong>{{ trans('nosh.uma_error') }}:  </strong><?php echo Session::get('uma_error');?>
				</div>
			<?php } else {?>
				<div>{{ trans('nosh.uma_error_text1') }}<br>
					{{ trans('nosh.uma_error_text2') }}<br>
					<ul>
						<li>{{ trans('nosh.uma_error_text3') }}</li>
						<li>{{ trans('nosh.uma_error_text4')}} <?php echo HTML::mailto($email, "{{ trans('nosh.uma_error_text5')}}", array('target'=>'_blank'));?></li>
						<li>{{ trans('nosh.uma_error_text6')}}</li>
					</ul>
				</div>
			<?php }?>
		</div>
	</div>
</div>
