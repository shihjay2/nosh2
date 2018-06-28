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
		<?php echo $practiceName;?><br><?php echo $practiceInfo1;?><br><?php echo $practiceInfo2;?><br><?php echo $practiceInfo3;?>
		<br><br><br><br><br><br>
		<?php echo $patientInfo1;?><br><?php echo $patientInfo2;?><br><?php echo $patientInfo3;?>
		<br><br><br>
		<p><b><?php echo $title;?></b></p>
		<table style="width:100%">
			<thead>
				<tr>
					<th style="width:50%">PATIENT DEMOGRAPHICS</th>
					<th style="width:50%">DATE OF INVOICE</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<?php echo $patientInfo->lastname. ', ' . $patientInfo->firstname;?><br>
						Date of Birth: <?php echo $dob;?><br>
					</td>
					<td>
						<?php echo $date;?>
					</td>
				</tr>
			</tbody>
		</table><br>
		<?php echo $disclaimer;?><br><br>
		<?php echo $text;?>
	</body>
</html>
