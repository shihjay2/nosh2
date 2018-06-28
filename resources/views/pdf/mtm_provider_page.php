<html>
	<head>
		<style>
			body {
				font-family: "Times New Roman", Times, serif;
				font-size: 14;
			}
			p {
				text-align: left;
				font-size: 14;
			}
			div.surround_div {
				border: 1px solid black;
			}
			div.surround_div1 {
				border: 2px solid black;
			}
			div.content {
				padding: 0.5mm;
			}
			table {
				border-collapse:collapse;
				border: 2px solid black;
				width: 6.62in;
				page-break-inside:avoid;
			}
			td {
				border: 1px solid black;
				vertical-align: top;
			}
			b.smallcaps {
				font-variant: small-caps;
			}
		</style>
	</head>
	<body>
		<table>
			<tr>
				<td>
					<?php echo $practiceLogo;?>
				</td>
				<td>
					<b><?php echo $practiceName;?></b><br><?php echo $practiceInfo;?><br><?php echo $website;?>
				</td>
			</tr>
		</table>
		<div style="float:left;width:100%;">
			<div style="width:6.62in;text-align:center;" class="surround_div">
				<b class="smallcaps">Medication Therapy Management Notes and Recommendations</b>
			</div>
		</div>
		<div style="float:right;text-align:right;">
			<b class="smallcaps">Date: <?php echo $date;?></b>
		</div>
		<p>Dear Dr. <?php echo $patient_doctor;?>,</p>
		<p>During the course of a MTM (Medication Therapy Management) encounter with your patient, <?php echo $patientInfo1;?>, Date of Birth <?php echo $patientDOB;?>, as mandated by Medicare/Health Plan:</p>
		<p><b>I discussed and reviewed with your patient the following topics:</b></p>
		<?php echo $topics;?>
		<p><b>Please consider the following recommendations:</b></p>
		<?php echo $recommendations;?>
		<p><b>Physician's response:</b></p>
		<div style="width:6.62in;text-align:left;" class="surround_div">
			<input type="checkbox"/> I accept recommendations<br>
			<input type="checkbox"/> I decline the recommendations<br>
			Notes:
			<br><br><br><br><br><br><br><br>
		</div>
		<p>Thank you so much and I will be happy to carry out your orders.</p>
		<p>Sincerely,</p>
		<span style="float:left;">
			<?php echo $providerSignature;?>
		</span>
	</body>
</html>
