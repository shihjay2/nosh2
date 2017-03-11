<html>
	<head>
		<style>
			body {
				font-family: Arial, sans-serif;
				font-size: 0.9em;
				margin:     0;
				padding:    0;
				width:      8.5in;
				height:     11in;
			}
			div.surround_div {
				border: 1px solid black;
			}
			b.smallcaps {
				font-variant: small-caps;
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
			}
			table td {
				overflow: hidden;
			}
		</style>
	</head>
	<body>
		<table style="width:100%;">
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
				<b class="smallcaps"><?php echo $title;?></b>
			</div>
		</div>
