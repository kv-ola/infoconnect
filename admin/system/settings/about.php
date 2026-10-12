<header class="settings-panel-heading">
	<h3>About System</h3>
	<p>System information, organization details, and version information.</p>
</header>
<section class="settings-section" aria-labelledby="about-infoconnect-heading">
	<header class="settings-section-heading"><h4 id="about-infoconnect-heading">System Information</h4></header>
	<dl class="about-system-table">
		<div><dt>System Name</dt><dd>InfoConnect</dd></div>
		<div><dt>System Version</dt><dd>1.0.0</dd></div>
		<div><dt>Organization</dt><dd>Office for Senior Citizens Affairs (OSCA), Mandaluyong City</dd></div>
		<div><dt>Database</dt><dd>MySQL (<?php echo escapeSettingsValue($dbName); ?><?php echo $databaseVersion !== '' ? ', ' . escapeSettingsValue($databaseVersion) : ''; ?>)</dd></div>
		<div><dt>Address</dt><dd>Acacia Lane Extension, Barangay Addition Hills, Mandaluyong City</dd></div>
		<div><dt>Developed By</dt><dd>BMirk, Capstone Project<br>Bachelor of Science in Information Systems<br>Mandaluyong College of Science and Technology</dd></div>
		<div><dt>Contact Number</dt><dd>8721-99-25</dd></div>
		<div><dt>Last Updated</dt><dd><?php echo escapeSettingsValue(date('F j, Y h:i A')); ?></dd></div>
		<div><dt>Email Address</dt><dd>oscamandaluyong@gmail.com</dd></div>
	</dl>
</section>
