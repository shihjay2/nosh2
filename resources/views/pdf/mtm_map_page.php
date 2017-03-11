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
				<b class="smallcaps">Medication Action Plan For <?php echo $patientInfo1;?>, <?php echo $patientDOB;?></b>
			</div>
		</div>
		<p>This action plan will help you get the best results from your medications if you:</p>
		<ol>
			<li>Read "What we talked about."</li>
			<li>Take the steps listed in the "What I need to do" boxes.</li>
			<li>Fill in "What I did and when I did it."</li>
			<li>Fill in "My follow-up plan" and "Questions I want to ask."</li>
		</ol>
		<p>Have this action plan with you when you talk with your doctors, pharmacists, and other healthcare providers. Share this with your family or caregivers too.</p>
		<div style="width:6.62in;float:right;text-align:right;font-size:16">
			<b class="smallcaps">Date Prepared <?php echo $date;?></b>
		</div>
		<?php echo $mapItems;?>
		<div style="width:6.62in;height:0.2in;"></div>
		<div style="width:6.62in;height:1.15in;" class="surround_div1">
			<div class="content"><b>My follow-up plan</b> (add notes about next steps):</div>
		</div>
		<div style="width:6.62in;height:0.2in"></div>
		<div style="width:6.62in;height:1.15in;" class="surround_div1">
			<div class="content"><b>Questions I want to ask</b>  (include topics about medications or therapy):</div>
		</div>
		<p>If you have any questions about your action plan, call <?php echo $practicePhone;?>.</p>
	</body>
</html>
