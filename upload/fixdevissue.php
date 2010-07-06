<?php

require_once('./includes/config.php');

mysql_connect($config['MasterServer']['servername'], $config['MasterServer']['username'], $config['MasterServer']['password']);

mysql_select_db($config['Database']['dbname']);

define('TABLE_PREFIX', $config['Database']['tableprefix']);

mysql_query("
	CREATE TABLE " . TABLE_PREFIX . "pt_user (
		userid INT UNSIGNED NOT NULL,
		totalissues INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (userid)
	) ENGINE=MyISAM
");

// Add every user into pt_user table
$usercounts = mysql_query("
	SELECT userid
	FROM " . TABLE_PREFIX . "user
	ORDER BY userid ASC
");

while ($usercount = mysql_fetch_array($usercounts))
{
	mysql_query("
		INSERT INTO " . TABLE_PREFIX . "pt_user (userid) VALUES (" . intval($usercount['userid']) . ")");
}

?>