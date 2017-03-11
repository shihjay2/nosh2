<html>
	<head>
		<style>
			@page {
				size: 8.5in 11in;
				sheet-size: Letter;
				margin: 92px 95px 92px 95px;
				margin-header: 0mm;
				margin-footer: 5mm;
			}
			body {
				font-family: Arial, sans-serif;
				font-size: 10;
			}
			p {
				text-align: center;
				font-size: 11;
			}
			div.outline {
				border: 1;
				border-style: solid;
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
		<table>
			<tr>
				<td style="width:30%">
					<?php echo $practiceLogo;?>
				</td>
				<td style="width:70%">
					<b><?php echo $practiceName;?></b><br><?php echo $practiceInfo;?><br><?php echo $website;?>
				</td>
			</tr>
		</table>
		<div style="float:left;width:100%;">
			<div style="border-bottom: 1px solid #000000; text-align: center; padding-bottom: 3mm; ">
				<b class="smallcaps"><?php echo $title;?></b>
			</div>
		</div>
		<br>
		<table style="width:100%">
			<thead>
				<tr>
					<th style="width:50%">PATIENT DEMOGRAPHICS</th>
					<th style="width:50%">DATE OF REQUEST</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<?php echo $patientInfo->lastname. ', ' . $patientInfo->firstname;?><br>
						Date of Birth: <?php echo $dob;?><br>
						<?php echo $ss;?>
						<?php echo $phone;?>
					</td>
					<td>
						<?php echo $date;?>
					</td>
				</tr>
			</tbody>
		</table><br>
		<table style="width:100%">
			<thead>
				<tr>
					<th style="width:50%">I AUTHORIZE INFORMATION TO BE RELEASED FROM</th>
					<th style="width:50%">TYPE OF INFORMATION TO BE RELEASED</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo $from;?></td>
					<td><?php echo $type;?></td>
				</tr>
			</tbody>
		</table><br>
		<table style="width:100%">
			<thead>
				<tr>
					<th style="width:100%">PROTECTED OR SENSITIVE INFORMATION</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>I understand that certain information cannot be released without specific authorization as required by State/Federal Law. By INITIALING I authorize the release of the following protected or sensitive information:</td>
				</tr>
				<tr>
					<td>
						<ul>
							<li>Drug Abuse Diagnosis/Treatment: </li>
							<li>Sexually Transmitted Diseases: </li>
							<li>Alcoholism Diagnosis/Treatment: </li>
							<li>Mental Health Treatment: </li>
							<li>AIDS/HIV Test results including related High Risk Behaviors: </li>
							<li>Genetic Testing: </li>
						</ul>
					</td>
				</tr>
			</tbody>
		</table><br>
		<table style="width:100%">
			<thead>
				<tr>
					<th style="width:50%">PURPOSE OF RELEASE</th>
					<th style="width:50%"><?php echo $signature_title;?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo $reason;?></td>
					<td>
						<br><br><br><br><br><br><br>
						<hr/>
						<?php echo $signature_text;?>
					</td>
				</tr>
			</tbody>
		</table><br>
		Please send the records to:<br>
		<b><?php echo $practiceName;?></b><br><?php echo $practiceInfo;?><br><br>
		This authorization is valid for 90 days and may be revoked by the patient (orally and in writing) at any time prior to the 90 days.
	</body>
</html>
