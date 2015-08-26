<?php
/*
Plugin Name: Database Sync
Description: Sync databases across servers with a single click.
Version: 0.5.1
Author: tamlyn
*/

//What WordPress capability is required to access this plugin?
define('DBS_REQUIRED_CAPABILITY', 'manage_options');

//API version for forward-compatibility
define('DBS_API_VERSION', 1);

require_once 'functions.php';

//add a menu item under Tools
add_action('admin_menu', 'dbs_menu');
function dbs_menu() {
	add_submenu_page('tools.php', 'Database Sync', 'Database Sync', DBS_REQUIRED_CAPABILITY, 'dbs_options', 'dbs_admin_ui');
}

//display admin menu page
function dbs_admin_ui() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}

	$action = isset($_REQUEST['dbs_action']) ? $_REQUEST['dbs_action'] : 'index';
	switch ($action) {
		case 'sync' :
			$url = esc_url($_GET['url']);
			include 'sync-screen.php';
			break;
		default :
			$tokens = get_option('outlandish_sync_tokens') ? : array();
			include 'main-screen.php';
			break;
	}
}

//do most actions on admin_init so that we can redirect before any content is output
add_action('admin_init', 'dbs_post_actions');
function dbs_post_actions() {
	if (!isset($_REQUEST['dbs_action'])) return;

	if (!current_user_can(DBS_REQUIRED_CAPABILITY)) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}

	$tokens = get_option('outlandish_sync_tokens') ? : array();

	switch ($_REQUEST['dbs_action']) {
		//add a token
		case 'add' :
			$decodedToken = base64_decode($_POST['token']);
			@list($secret, $url) = explode(' ', $decodedToken);
			if (empty($secret) || empty($url) || !preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url)) {
				$gotoUrl = dbs_url(array('error' => 'The token is not valid.'));
			} elseif ($url == get_bloginfo('wpurl')) {
				$gotoUrl = dbs_url(array('error' => 'The token cannot be added as it is for this installation.'));
			} else {
				$tokens[$url] = $secret;
				update_option('outlandish_sync_tokens', $tokens);
				$gotoUrl = dbs_url();
			}
			wp_redirect($gotoUrl);
			exit;

		//remove a token
		case 'remove' :
			unset($tokens[$_POST['url']]);
			update_option('outlandish_sync_tokens', $tokens);
			wp_redirect(dbs_url());
			exit;

		//pull from remote db
		case 'pull' :
			try {
				//send post request with secret
				$result = dbs_post($_REQUEST['url'], 'dbs_pull', array('secret' => $tokens[$_REQUEST['url']]));
				if ($result == 'You don\'t know me') {
					$gotoUrl = dbs_url(array('error' => 'Invalid site token'));
				} elseif ($result == '0') {
					$gotoUrl = dbs_url(array('error' => 'Sync failed. Is the plugin activated on the remote server?'));
				} else {

					$sql = $result;
					if ($sql && preg_match('|^/\* Dump of database |', $sql)) {

						//backup current database
						$backupfile = dbs_makeBackup();

						//store some options to restore after sync
						$optionCache = dbs_cacheOptions();

						//load the new data
						if (dbs_loadSql($sql)) {
							//clear object cache
							wp_cache_flush();

							//restore options
							dbs_restoreOptions($optionCache);

							$gotoUrl = dbs_url(array('message' => 'Database synced successfully'));
						} else {
							//import failed part way through so attempt to restore last backup
							$compressedSql = substr(file_get_contents($backupfile), 10); //strip gzip header
							dbs_loadSql(gzinflate($compressedSql));

							$gotoUrl = dbs_url(array('error' => 'Sync failed. SQL error.'));
						}
					} else {
						$gotoUrl = dbs_url(array('error' => 'Sync failed. Invalid dump.'));
					}
				}
			} catch (Exception $ex) {
				$gotoUrl = dbs_url(array('error' => 'Remote site not accessible (HTTP ' . $ex->getCode() . ')'));
			}

			wp_redirect($gotoUrl);
			exit;

		//push to remote db
		case 'push' :
			//get SQL data
			ob_start();
			dbs_mysqldump();
			$sql = ob_get_clean();

			try {
				//send post request with secret and SQL data
				$result = dbs_post($_REQUEST['url'], 'dbs_push', array(
					'secret' => $tokens[$_REQUEST['url']],
					'sql' => $sql
				));
				if ($result == 'You don\'t know me') {
					$gotoUrl = dbs_url(array('error' => 'Invalid site token'));
				} elseif ($result == '0') {
					$gotoUrl = dbs_url(array('error' => 'Sync failed. Is the plugin activated on the remote server?'));
				} elseif ($result == 'OK') {
					$gotoUrl = dbs_url(array('message' => 'Database synced successfully'));
				} else {
					$gotoUrl = dbs_url(array('error' => 'Something may be wrong'));
				}
			} catch (RuntimeException $ex) {
				$gotoUrl = dbs_url(array('error' => 'Remote site not accessible (HTTP ' . $ex->getCode() . ')'));
			}
			wp_redirect($gotoUrl);
			exit;
	}
}

//handle remote pull action when not logged in
add_action('wp_ajax_nopriv_dbs_pull', 'dbs_pull_nopriv');
function dbs_pull_nopriv() {
	//test for secret
	$secret = dbs_getSecret();
	if (stripslashes($_REQUEST['secret']) != $secret) {
		die("You don't know me");
	}
	//dump DB
	dbs_pull();
}

//handle pull action when logged in
add_action('wp_ajax_dbs_pull', 'dbs_pull');
function dbs_pull() {
	//dump DB and GZip it
	header('Content-type: application/octet-stream');
	if(isset($_GET['dump'])){
		if ($_GET['dump'] == 'manual') {
			//manual dump, so include attachment headers
			header('Content-Description: File Transfer');
		        header('Content-Disposition: attachment; filename=data.sql');
		        header('Content-Transfer-Encoding: binary');
		        header('Expires: 0');
		        header('Cache-Control: must-revalidate');
		        header('Pragma: public');
		}
	}
	dbs_mysqldump();
	exit;
}

//handle remote push action
add_action('wp_ajax_nopriv_dbs_push', 'dbs_push');
function dbs_push() {
	//test for secret
	$secret = dbs_getSecret();
	if (stripslashes($_REQUEST['secret']) != $secret) {
		die("You don't know me");
	}
	$tokens = get_option('outlandish_sync_tokens') ? : array();

//	echo $sql = gzinflate($_POST['sql']);
	$sql = stripslashes($_POST['sql']);
	if ($sql && preg_match('|^/\* Dump of database |', $sql)) {

		//backup current DB
		dbs_makeBackup();

		//store options
		$optionCache = dbs_cacheOptions();

		//load posted data
		dbs_loadSql($sql);

		//clear object cache
		wp_cache_flush();

		//reinstate options
		dbs_restoreOptions($optionCache);

		echo 'OK';
	} else {
		echo 'Error: invalid SQL dump';
	}
	exit;
}

/**
 * @return array key-value pairs of selected current WordPress options
 */
function dbs_cacheOptions() {
	//persist these options
	$defaultOptions = array('siteurl', 'home', 'outlandish_sync_tokens', 'outlandish_sync_secret');
	$persistOptions = apply_filters('dbs_persist_options', $defaultOptions);

	$optionCache = array();
	foreach ($persistOptions as $name) {
		$optionCache[$name] = get_option($name);
	}
	return $optionCache;
}

/**
 * @param array $optionCache key-value pairs of options to restore
 */
function dbs_restoreOptions($optionCache) {
	foreach ($optionCache as $name => $value) {
		update_option($name, $value);
	}
}
