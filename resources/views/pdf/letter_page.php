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
			}
			table {
				font-family: Arial, sans-serif;
				font-size: 13;
			}
		</style>
	</head>
	<body>
		<?php echo $practiceName;?><br><?php echo $practiceInfo1;?><br><?php echo $practiceInfo2;?><br><?php echo $practiceInfo3;?>
		<br><br><br><br><br><br>
		<?php echo $patientInfo1;?><br><?php echo $patientInfo2;?><br><?php echo $patientInfo3;?>
		<div align="right">
			<br><br><br><?php echo $date;?>
		</div>
		Dear <?php echo $firstname;?>,<br><br><?php echo $body;?><br><br>Sincerely,<br><?php echo $signature;?>
	</body>
</html>
