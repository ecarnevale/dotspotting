<?php

	#
	# $Id$
	#

	include("include/init.php");

	#################################################################

	$owner = ensure_valid_user_from_url();
	$smarty->assign_by_ref('owner', $owner);

	$is_own = ($owner['id'] == $GLOBALS['cfg']['user']['id']) ? 1 : 0;
	$smarty->assign("is_own", $is_own);

	#################################################################

	$owner['counts'] = buckets_counts_for_user($owner, $GLOBALS['cfg']['user']['id']);

	$smarty->display('page_user.txt');
	exit();
?>