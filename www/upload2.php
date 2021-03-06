<?php

	#
	# $Id$
	#

	include("include/init.php");

	loadlib("import");
	loadlib("formats");

	#################################################################

	login_ensure_loggedin("{$GLOBALS['cfg']['abs_root_url']}upload2");

	if (! $GLOBALS['cfg']['enable_feature_import']){

		$GLOBALS['error']['uploads_disabled'] = 1;
		$smarty->display("page_upload.txt");
		exit();
	}

	#################################################################

	$crumb_key = 'upload';
	$crumb_ok = crumb_check($crumb_key);

	$GLOBALS['smarty']->assign("crumb_key", $crumb_key);

	#

	$label = filter_strict(post_str('label'));
	$private = (post_str('private')) ? 1 : 0;
	$dots_index_on = filter_strict(post_str('dots_index_on'));
	$mime_type = filter_strict(post_str('mime_type'));
		
	$GLOBALS['smarty']->assign("label", $label);
	$GLOBALS['smarty']->assign("private", $private);
	$GLOBALS['smarty']->assign("dots_index_on", $dots_index_on);
	$GLOBALS['smarty']->assign("mime_type", $mime_type);

	#
	# First grab the file and do some basic validation
	#

	if (($crumb_ok) && ($_FILES['upload'])){

		$GLOBALS['smarty']->assign('step', 'process');

		$ok = 1;

		if ($_FILES['upload']['error']){

			$GLOBALS['error']['upload_error'] = 1;
			$GLOBALS['error']['upload_error_msg'] = $_FILES['upload']['error'];
			$ok = 0;
		}

		if ($ok){

			$more = array();

			if ($mime_type){
				$more['assume_mime_type'] = $mime_type;
			}

			if (! import_is_valid_mimetype($_FILES['upload'], $more)){
				$GLOBALS['error']['invalid_mimetype'] = 1;
			}

			# okay. try to pre-process the data

			else {

				$_FILES['upload']['path'] = $_FILES['upload']['tmp_name'];

				$fingerprint = md5_file($_FILES['upload']['path']);
				$GLOBALS['smarty']->assign("fingerprint", $fingerprint);

				$sheets = sheets_lookup_by_fingerprint($fingerprint, $GLOBALS['cfg']['user']['id']);
				$GLOBALS['smarty']->assign_by_ref("sheets", $sheets);

				$more = array(
					'dots_index_on' => $dots_index_on,
				);

				$pre_process = import_process_file($_FILES['upload'], $more);

				# convert any errors from a bag of arrays in to a hash
				# where the key maps to record number (assuming the count
				# starts at 1.

				if (count($pre_process['errors'])){

					$_errors = array();

					foreach ($pre_process['errors'] as $e){
						$_errors[$e['record']] = $e;
					}

					$pre_process['errors'] = $_errors;
				}

				$GLOBALS['smarty']->assign_by_ref("pre_process", $pre_process);

				# store the file somewhere in a pending bin?
			}
		}
	}

	#
	# Okay, finally try to import the data. Note that we re-validate $data
	# here and we don't reassign the (Smarty) $step variable until everything
	# looks like it's okay.
	#

	else if (($crumb_ok) && (post_str("data"))){

		$GLOBALS['smarty']->assign('step', 'process');

		$fingerprint = post_str('fingerprint');
		$mime_type = post_str('mime_type');
		$simplified = post_str('simplified');

		$raw_data = post_str("data");
		$data = json_decode($raw_data, "as hash");

		$ok = 1;

		if (! $data){

			$GLOBALS['error']['missing_data'] = 1;
			$ok = 0;
		}

		if ($ok){

			$more = array(
				'dots_index_on' => $dots_index_on,
			);

			$pre_process = import_ensure_valid_data($data);

			if (! $pre_process['ok']){

				$GLOBALS['error']['invalid_data'] = 1;
				$ok = 0;

				$pre_process['data'] = $data;

				if (count($pre_process['errors'])){

					$_errors = array();

					foreach ($pre_process['errors'] as $e){
						$_errors[$e['record']] = $e;
					}

					$pre_process['errors'] = $_errors;
				}

				$GLOBALS['smarty']->assign_by_ref("pre_process", $pre_process);
			}
		}

		#
		# Everything looks good, so let's try to talk to the database.
		# Note the part where we're also re-assign $step (below).
		#

		if ($ok){

			$GLOBALS['smarty']->assign('step', 'import');

			$more = array(
				'return_dots' => 0,
				'dots_index_on' => $dots_index_on,
				'label' => $label,
				'mark_all_private' => $private,
				'mime_type' => $mime_type,
				'fingerprint' => $fingerprint,
				'simplified' => $simplified,
			);

			$import = import_process_data($GLOBALS['cfg']['user'], $data, $more);
			$GLOBALS['smarty']->assign_by_ref("import", $import);
		}
	}

	else {

		# nuthin' 
	}

	$GLOBALS['smarty']->assign("upload_endpoint", "upload2");

	$import_formats = formats_valid_import_map('key by extension');
	
	$GLOBALS['smarty']->assign_by_ref("import_formats", $import_formats);
	
	$import_formats_pretty = formats_pretty_import_names_map();
	$GLOBALS['smarty']->assign_by_ref("import_formats_pretty", $import_formats_pretty);

	$smarty->display("page_upload2.txt");
	exit();
?>