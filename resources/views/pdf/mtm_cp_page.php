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
		<div style="width:100%;">
		<hr />
		<?php echo $date;?><br><br>
		<?php echo $patientInfo1;?><br><?php echo $patientInfo2;?><br><?php echo $patientInfo3;?>
		<br><br><br>
		<?php echo $salutation;?><br>
		</div>
		<p>Thank you for talking with me on <?php echo $date;?> about your health and medications. Medicare's MTM (Medication Therapy Management) program helps you make sure that your medications are working.</p>
		<p>Along with this letter are an action plan (Medication Action Plan) and a medication list (Personal Medication List). <b>The action plan has steps you should take to help you get the best results from your medications. The medication list will help you keep track of your medications and how to use them the right way.</b></p>
		<ul>
			<li>Have your action plan and medication list with you when you talk with your doctors, pharmacists, and other health care providers.</li>
			<li>Ask your doctors, pharmacists, and other healthcare providers to update them at every visit.</li>
			<li>Take your medication list with you if you go to the hospital or emergency room.</li>
			<li>Give a copy of the action plan and medication list to your family or caregivers.</li>
		</ul>
		<p>If you want to talk about this letter or any of the papers with it, please call <?php echo $practicePhone;?>.</p>
		<p>We look forward to working with you and your doctors to help you stay healthy through the <?php echo $practiceName;?> MTM program.</p>
		<p>Sincerely,</p>
		<span style="float:left;">
			<?php echo $providerSignature;?>
		</span>
	</body>
</html>
