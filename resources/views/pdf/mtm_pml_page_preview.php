<div style="float:left;width:100%;">
	<div style="width:6.62in;text-align:center;" class="surround_div">
		<b class="smallcaps">Personal Medication List For <?php echo $patientInfo1;?>, <?php echo $patientDOB;?></b>
	</div>
</div>
<p>This medication list was made for you after we talked. We also used information from the electronic medical record.</p>
	<div style="width:100%float:left;padding:0mm, 5mm">
	<ul>
		<li>Use blank rows to add new medications. Then fill in the dates you started using them.</li>
		<li>Cross out medications when you no longer use them. Then write the date and why you stopped using them.</li>
		<li>Ask your doctors, pharmacists, and other healthcare providers to update this list at every visit.</li>
	</ul>
	</div>
<div style="width:100%;float:left;padding:2mm;" class="surround_div">
	Keep this list up-to-date with:<br>
	<input type="checkbox"/> prescription medications<br>
	<input type="checkbox"/> over the counter drugs<br>
	<input type="checkbox"/> herbals<br>
	<input type="checkbox"/> vitamins<br>
	<input type="checkbox"/> minerals<br>
</div>
<p style="width:6.62in;float:left">If you go to the hospital or emergency room, take this list with you. Share this with your family or caregivers too.</p>
<div style="width:6.62in;height:0.2in;float:left"></div>
<div style="width:100%;min-height:0.45in;float:left" class="surround_div1">
	<div class="content"><b>Allergies or side effects: <?php echo $allergies;?></b></div>
</div>
<?php echo $pmlItems;?>
