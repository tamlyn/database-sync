<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Sync with Remote Server</h2>

	<h3>Push</h3>

	<form method="post" action="<?php dbs_url(); ?>">
		<input type="hidden" name="dbs_action" value="push">
		<input type="hidden" name="url" value="<?php echo $url; ?>">
		<p><?php echo dbs_stripHttp(get_bloginfo('wpurl')) . " &#x21d2 " . dbs_stripHttp($url); ?></p>
		<p>
			<b>Delete all data</b> in the remote WordPress database and replace with the data from this database.
			<input type="submit" value="Push" class="button-primary">
		</p>
	</form>

	<h3>Pull</h3>

	<form method="post" action="<?php dbs_url(); ?>">
		<input type="hidden" name="dbs_action" value="pull">
		<input type="hidden" name="url" value="<?php echo $url; ?>">
		<p><?php echo dbs_stripHttp(get_bloginfo('wpurl')) . " &#x21d0 " . dbs_stripHttp($url); ?></p>
		<p>
			<b>Delete all data</b> in this database and replace with the data from the remote WordPress database.
			<input type="submit" value="Pull" class="button-primary">
		</p>
	</form>