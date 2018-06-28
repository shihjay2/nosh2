<html>
	<head>
		<style>
			body {
				font-family: "Times New Roman", Times, serif;
				font-size: 14;
				height: 120mm;
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
			.nobreak {
				page-break-inside: avoid;
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
				<b class="smallcaps">Personal Medication List For <?php echo $patientInfo1;?>, <?php echo $patientDOB;?></b>
			</div>
		</div>
		<p>This medication list was made for you after we talked. We also used information from the electronic medical record.</p>
			<div style="width:3in;float:left;padding:0mm, 5mm">
			<ul>
				<li>Use blank rows to add new medications. Then fill in the dates you started using them.</li>
				<li>Cross out medications when you no longer use them. Then write the date and why you stopped using them.</li>
				<li>Ask your doctors, pharmacists, and other healthcare providers to update this list at every visit.</li>
			</ul>
			</div>
		<div style="width:2.5in;float:left;padding:2mm;" class="surround_div">
			Keep this list up-to-date with:<br>
			<input type="checkbox"/> prescription medications<br>
			<input type="checkbox"/> over the counter drugs<br>
			<input type="checkbox"/> herbals<br>
			<input type="checkbox"/> vitamins<br>
			<input type="checkbox"/> minerals<br>
		</div>
		<p style="width:6.62in;float:left">If you go to the hospital or emergency room, take this list with you. Share this with your family or caregivers too.</p>
		<div style="width:6.62in;float:right;text-align:right;font-size:16">
			<b class="smallcaps">Date Prepared <?php echo $date;?></b>
		</div>
		<div style="width:6.62in;height:0.2in;float:left"></div>
		<div style="width:6.62in;min-height:0.45in;float:left" class="surround_div1">
			<div class="content"><b>Allergies or side effects: <?php echo $allergies;?></div>
		</div>
		<?php echo $pmlItems;?>
		<div style="width:6.62in;height:0.2in;float:left"></div>
		<div style="width:6.62in;height:1.15in;float:left" class="surround_div1 nobreak">
			<div class="content"><b>Other Information:</div>
		</div>
		<p style="width:6.62in;float:left">If you have any questions about your medication list, call <?php echo $practicePhone;?>.</p>
		<div style="border-top: 1px solid #000000; border-bottom: 1px solid #000000; font-family: Arial, Helvetica, sans-serif; font-size: 7;margin-left:auto;margin-right:auto;width:5in;">
			According to the Paperwork Reduction Act of 1995, no persons are required to respond to a collection of information unless it displays a valid OMB<br>
			control number. The valid OMB number for this information collection is 0938-XXXX. The time required to complete this information collection is<br>
			estimated to average 37.76 minutes per response, including the time to review instructions, searching existing data resources, gather the data needed,<br>
			and complete and review the information collection. If you have any comments concerning the accuracy of the time estimate(s) or suggestions for<br>
			improving this form, please write to: CMS, Attn: PRA Reports Clearance Officer, 7500 Security Boulevard, Baltimore, Maryland 21244-1850.
		</div>
	</body>
</html>
