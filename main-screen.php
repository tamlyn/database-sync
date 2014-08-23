<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Database Sync</h2>

	<?php if (!empty($_REQUEST['error'])) echo '<div class="error"><p>' . htmlspecialchars($_REQUEST['error']) . '</p></div>'; ?>
	<?php if (!empty($_REQUEST['message'])) echo '<div class="updated"><p>' . htmlspecialchars($_REQUEST['message']) . '</p></div>'; ?>

	<table class="form-table">
		<tr valign="top">
			<th scope="row">My token</th>
			<td>
				<?php echo dbs_getToken(); ?>
				<div>Keep this secret. Anyone with this token can gain access to your database.</div>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Linked sites</th>
			<td>
				<?php if ($tokens): ?>
				<?php foreach ($tokens as $url => $secret): ?>
					<form method="post" action="">
						<input type="hidden" name="dbs_action" value="remove">
						<input type="hidden" name="url" value="<?php echo $url; ?>">
						<p>
							<a href="<?php echo $url; ?>/wp-admin/tools.php?page=dbs_options"><?php echo dbs_stripHttp($url); ?></a>
							<a class="button" href="<?php echo htmlspecialchars(dbs_url(array('dbs_action'=>'sync', 'url'=>$url))); ?>">Sync...</a>
							<input type="submit" value="Remove" class="button-secondary">
						</p>
					</form>
					<?php endforeach; ?>
				<?php else: ?>
				No sites linked yet
				<?php endif; ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Add a remote site token</th>
			<td>
				<form method="post" action="">
					<input type="hidden" name="dbs_action" value="add">
					<input type="input" size="60" name="token" class="regular-text">
					<input type="submit" value="Add" class="button-primary">
				</form>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Dump database manually</th>
			<td>
				<a href="<?php echo admin_url('admin-ajax.php?action=dbs_pull&dump=manual'); ?>" target="_blank" class="button">Dump</a>
			</td>
		</tr>
	</table>
