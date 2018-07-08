<html>
	<head>
		<style>
			body {
				font-family: Arial, sans-serif;
				font-size: 10;
			}
			p {
				text-align: center;
				font-size: 0.8em;
			}
			b.smallcaps {
				font-variant: small-caps;
			}
			p.borders {
				border: 1;
				border-style: solid;
			}
			th {
				background-color: gray;
				color: #FFFFFF;
			}
			table {
				font-size: 0.8em;
				table-layout:fixed;
				page-break-inside:avoid;
				width: 700px;
			}
			table td {
				overflow: hidden;
			}
		</style>
	</head>
	<body>
		<table style="width:100%">
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
			<div style="border-bottom: 1px solid #000000; text-align: center; padding-bottom: 3mm;">
				<b class="smallcaps">Prescription</b>
			</div>
		</div>
		<table style="width:100%">
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
		<?php echo $rx_item;?>
		<table style="width:100%">
			<thead>
				<tr>
					<th style="width:50%">ALLERGIES</th>
					<th style="width:50%">SIGNATURE</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo $allergyInfo;?></td>
					<td>
						<?php echo $signature;?>
						<p style="font-size:2pt;text-align:left;">THIS IS AN ORIGINAL PRESCRIPTION-THIS IS AN ORIGINAL PRESCRIPTION-THIS IS AN ORIGINAL PRESCRIPTION-THIS IS AN ORIGINAL PRESCRIPTION-THIS IS AN ORIGINAL PRESCRIPTION-THIS IS AN ORIGINAL PRESCRIPTION</p>
						<br>
						<?php if ($rx->rxl_dea != '') {echo 'DEA Number: ' . $rx->rxl_dea . '<br>';}?>
					</td>
				</tr>
			</tbody>
		</table>
		<p>Security features: (*) bordered and spelled quantities, microprint signature line visible at 5x or > magnification that must show "THIS IS AN ORIGINAL PRESCRIPTION", and this description of features.</p>
	</body>
</html>
