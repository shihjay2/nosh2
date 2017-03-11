<br>
<table style="width:100%;">
	<thead>
		<tr>
			<th style="width:50%">PATIENT DEMOGRAPHICS</th>
			<th style="width:50%">GUARANTOR AND INSURANCE INFORMATION</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>
				<?php echo $patientInfo->lastname. ', ' . $patientInfo->firstname;?><br>
				Date of Birth: <?php echo $dob;?><br>
				<?php echo $patientInfo->address;?><br>
				<?php echo $patientInfo->city . ', ' . $patientInfo->state . ' ' . $patientInfo->zip;?><br>
				<?php echo $patientInfo->phone_home;?><br>
			</td>
			<td>
				<?php echo $insuranceInfo;?>
			</td>
		</tr>
	</tbody>
</table><br>
<hr />
<?php echo $letter;?>
</body>
</html>
