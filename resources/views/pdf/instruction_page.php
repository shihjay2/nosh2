<html>
	<head>
		<style>
			body {
				font-family: Arial, sans-serif;
				font-size: 0.9em;
			}
			h2 {
				text-align: center;
			}
			b.smallcaps {
				font-variant: small-caps;
			}
			div.outline {
				border: 1;
				border-style: solid;
			}
			p.borders {
				border: 1;
				border-style: solid;
			}
			table.top {
				width: 700;
			}
			table.order {
				width: 700;
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
			<div style="border-bottom: 1px solid #000000; text-align: center; padding-bottom: 3mm; ">
				<b class="smallcaps">Patient Instructions</b>
			</div>
		</div>
		<div style="float:left;width:100%;">
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
			<table style="width:100%">
				<thead>
					<tr>
						<th style="width:100%">INSTRUCTIONS</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>Patient instructions for encounter date of service <?php echo $encounter_DOS;?> prepared by <?php echo $encounter_provider;?></strong></td>
					</tr>
					<tr>
						<td><?php echo $orders;?></td>
					</tr>
					<tr>
						<td><?php echo $rx;?></td>
					</tr>
					<tr>
						<td><?php echo $plan;?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</body>
</html>
