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
			p {
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
			<div style="border-bottom: 1px solid #000000; text-align: center; padding-bottom: 3mm; ">
				<b class="smallcaps"><?php echo $top;?></b>
			</div>
		</div>
		<br>
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
						Date of Birth: <?php echo $dob;?>, Gender: <?php echo $sex;?><br>
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
					<th style="width:78%"><?php echo $title1;?></th>
					<th style="width:22%">DATE</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td style="width:78%">
						<?php echo $address->displayname;?><br>
						<?php echo $address->street_address1;?><br>
						<?php if ($address->street_address2 != '') {echo $address->street_address2 . '<br>';}?>
						<?php echo $address->city . ', ' . $address->state . ' ' . $address->zip;?><br>
						<?php echo $address->phone;?><br>
					</td>
					<td style="width:22%"><?php echo $orders_date;?></td>
				</tr>
			</tbody>
		</table><br>
		<table style="width:100%">
			<thead>
				<tr>
					<th style="width:50%">DIAGNOSES</th>
					<th style="width:50%">SIGNATURE</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo $dx;?></td>
					<td>
						<?php echo $signature;?>
						<hr/>
					</td>
				</tr>
			</tbody>
		</table><br>
		<table style="width:100%">
			<thead>
				<tr>
					<th style="width:100%"><?php echo $title2;?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo $text;?></td>
				</tr>
			</tbody>
		</table>
	</body>
</html>
