<div id="uma_invitation_request">
	<div align="center" >
		<div align="left" class="ui-corner-all ui-tabs ui-widget ui-widget-content">
			<?php if (Session::has('uma_error')) {?>
				<div>
					<strong>Error:  </strong><?php echo Session::get('uma_error');?>
				</div>
			<?php } else {?>
				<div>You have tried to login to this patient's personal electronic health record but you do not have sufficient priviledges to access it.<br>
					There are several reasons for this.<br>
					<ul>
						<li>You were not given an invitation by this patient for access.</li>
						<li>Your invitation has expired.  If so, please contact <?php echo HTML::mailto($email, 'the patient directly.', array('target'=>'_blank'));?></li>
						<li>If you previously had access, your acesss has been revoked by the patient.</li>
					</ul>
				</div>
			<?php }?>
		</div>
	</div>
</div>
