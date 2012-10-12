<?php
/**
 * Integrate Linux image optimizers into WordPress.
 * @version 1.1.1
 * @package EWWW_Image_Optimizer
 */
/*
Plugin Name: EWWW Image Optimizer
Plugin URI: http://www.shanebishop.net/ewww-image-optimizer/
Description: Reduce image file sizes and improve performance for images within WordPress including NextGEN Gallery. Uses jpegtran, optipng, pngout, and gifsicle.
Author: Shane Bishop
Version: 1.1.1
Author URI: http://www.shanebishop.net/
License: GPLv3
*/

/**
 * Constants
 */
define('EWWW_IMAGE_OPTIMIZER_DOMAIN', 'ewww_image_optimizer');
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_DIR', dirname(plugin_basename(__FILE__)));
define('EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH', plugin_dir_path(__FILE__) );

/**
 * Hooks
 */
add_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 10, 2);
add_filter('manage_media_columns', 'ewww_image_optimizer_columns');
// variable for plugin settings link
$plugin = plugin_basename ( __FILE__ );
add_filter ("plugin_action_links_$plugin", 'ewww_image_optimizer_settings_link' );
add_action('manage_media_custom_column', 'ewww_image_optimizer_custom_column', 10, 2);
add_action('admin_init', 'ewww_image_optimizer_admin_init');
add_action('admin_action_ewww_image_optimizer_manual', 'ewww_image_optimizer_manual');
add_action('admin_menu', 'ewww_image_optimizer_admin_menu' );
add_action('admin_head-upload.php', 'ewww_image_optimizer_add_bulk_actions_via_javascript' ); 
add_action('admin_action_bulk_optimize', 'ewww_image_optimizer_bulk_action_handler' ); 
add_action('admin_action_-1', 'ewww_image_optimizer_bulk_action_handler' ); 
add_action('admin_print_scripts-media_page_ewww-image-optimizer-bulk', 'ewww_image_optimizer_scripts' );
add_action('admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout');

/**
 * Check if system requirements are met
 */
if('Linux' != PHP_OS && 'Darwin' != PHP_OS) {
	add_action('admin_notices', 'ewww_image_optimizer_notice_os');
	define('EWWW_IMAGE_OPTIMIZER_PNGOUT', false);
	define('EWWW_IMAGE_OPTIMIZER_GIFSICLE', false);
	define('EWWW_IMAGE_OPTIMIZER_JPEGTRAN', false);
	define('EWWW_IMAGE_OPTIMIZER_OPTIPNG', false);
}else{
	add_action('admin_notices', 'ewww_image_optimizer_notice_utils');
}   

require( dirname(__FILE__) . '/nextgen-integration.php' );

function ewww_image_optimizer_notice_os() {
	echo "<div id='ewww-image-optimizer-warning-os' class='updated fade'><p><strong>EWWW Image Optimizer isn't supported on your server.</strong> Unfortunately, the EWWW Image Optimizer plugin doesn't work with " . htmlentities(PHP_OS) . ".</p></div>";
}   

// Retrieves user specified paths or set defaults if they don't exist. We also do a basic check to make sure we weren't given a malicious path.
function ewww_image_optimizer_path_check() {
	//$doc_root = $_SERVER['DOCUMENT_ROOT'];
	$jpegtran = get_option('ewww_image_optimizer_jpegtran_path');
	if(!preg_match('/^\/[\w\.-\d\/_]+\/jpegtran$/', $jpegtran)) {
		$jpegtran = 'jpegtran';
	}
	$optipng = get_option('ewww_image_optimizer_optipng_path');
	if(!preg_match('/^\/[\w\.-\d\/_]+\/optipng$/', $optipng)) {
		$optipng = 'optipng';
	}
	$gifsicle = get_option('ewww_image_optimizer_gifsicle_path');
	if(!preg_match('/^\/[\w\.-\d\/_]+\/gifsicle$/', $gifsicle)) {
		$gifsicle = 'gifsicle';
	}
	$pngout = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-static";
	return array($jpegtran, $optipng, $gifsicle, $pngout);
}

function ewww_image_optimizer_notice_utils() {
	if( ini_get('safe_mode') ){
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated fade'><p><strong>PHP's Safe Mode is turned on. This plugin cannot operate in safe mode.</strong></p></div>";
	}
	list ($jpegtran_path, $optipng_path, $gifsicle_path, $pngout_path) = ewww_image_optimizer_path_check();
	$required = array(
		'JPEGTRAN' => $jpegtran_path,
		'OPTIPNG' => $optipng_path,
		'GIFSICLE' => $gifsicle_path,
		'PNGOUT' => $pngout_path
	);
   
	if(get_option('ewww_image_optimizer_skip_check') == TRUE){
		$skip_jpegtran = true;
		$skip_optipng = true;
		$skip_gifsicle = true;
		$skip_pngout = true;
	} else {
		$skip_jpegtran = false;
		$skip_optipng = false;
		$skip_gifsicle = false;
		$skip_pngout = false;
	}
	if (get_option('ewww_image_optimizer_disable_jpegtran')) {
		$skip_jpegtran = true;
	}
	if (get_option('ewww_image_optimizer_disable_optipng')) {
		$skip_optipng = true;
	}
	if (get_option('ewww_image_optimizer_disable_gifsicle')) {
		$skip_gifsicle = true;
	}
	if (get_option('ewww_image_optimizer_disable_pngout')) {
		$skip_pngout = true;
	}
	$missing = array();

	foreach($required as $key => $req){
		$result = trim(exec('which ' . $req));
		if(empty($result)){
			switch($key) {
				case 'JPEGTRAN':
					if (!$skip_jpegtran) {
						$missing[] = 'jpegtran';
					}
					break; 
				case 'OPTIPNG':
					if (!$skip_optipng) {
						$missing[] = 'optipng';
					}
					break;
				case 'GIFSICLE':
					if (!$skip_gifsicle) {
						$missing[] = 'gifsicle';
					}
					break;
				case 'PNGOUT':
					if (!$skip_pngout) {
						$missing[] = 'pngout';
					}
					break;
			}
			define('EWWW_IMAGE_OPTIMIZER_' . $key, false);
		} else {
			define('EWWW_IMAGE_OPTIMIZER_' . $key, true);
		}
	}

	$msg = implode(', ', $missing);

	if(!empty($msg)){
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated fade'><p><strong>EWWW Image Optimizer requires <a href='http://jpegclub.org/jpegtran/'>jpegtran</a>, <a href='http://optipng.sourceforge.net/'>optipng</a> or <a href='http://advsys.net/ken/utils.htm'>pngout</a>, and <a href='http://www.lcdf.org/gifsicle/'>gifsicle</a>.</strong> You are missing: $msg. Please install via the <a href='http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>Installation Instructions</a> and update paths (if necessary) on the <a href='options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php'>Settings Page</a>.</p></div>";
	}

	// Check if exec is disabled
	$disabled = explode(', ', ini_get('disable_functions'));
	if(in_array('exec', $disabled)){
		echo "<div id='ewww-image-optimizer-warning-opt-png' class='updated fade'><p><strong>EWWW Image Optimizer requires exec().</strong> Your system administrator has disabled this function.</p></div>";
	}
}

/**
 * Plugin admin functions
 */
function ewww_image_optimizer_admin_init() {
	load_plugin_textdomain(EWWW_IMAGE_OPTIMIZER_DOMAIN);
	wp_enqueue_script('common');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_check');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_skip_gifs');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpegtran_copy');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_optipng_level');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_pngout_level');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpegtran_path');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_optipng_path');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_gifsicle_path');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_jpegtran');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_optipng');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_gifsicle');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_disable_pngout');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_to_png');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_png_to_jpg');
	register_setting('ewww_image_optimizer_options', 'ewww_image_optimizer_gif_to_png');
	add_option('ewww_image_optimizer_disable_pngout', TRUE);
	add_option('ewww_image_optimizer_optipng_level', 2);
	add_option('ewww_image_optimizer_pngout_level', 2);
}

function ewww_image_optimizer_scripts () {
	wp_enqueue_script ('ewwwloadscript', plugins_url('/pageload.js', __FILE__));
}	

function ewww_image_optimizer_admin_menu() {
	add_media_page( 'Bulk Optimize', 'Bulk Optimize', 'edit_others_posts', 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview');
	add_options_page(
		'EWWW Image Optimizer',		//Title
		'EWWW Image Optimizer',		//Sub-menu title
		'manage_options',		//Security
		__FILE__,			//File to open
		'ewww_image_optimizer_options'	//Function to call
	);
}

function ewww_image_optimizer_settings_link($links) {
	$settings_link = '<a href="options-general.php?page=ewww-image-optimizer/ewww-image-optimizer.php">Settings</a>';
	array_unshift ( $links, $settings_link );
	return $links;
}

function ewww_image_optimizer_bulk_preview() {
	$attachments = null;
	$auto_start = false;
	$skip_attachments = false;
	$upload_dir = wp_upload_dir();
	$progress_file = $upload_dir['basedir'] . "/ewww.tmp";
	if (isset($_REQUEST['ids'])) {
		$attachments = get_posts( array(
			'numberposts' => -1,
			'include' => explode(',', $_REQUEST['ids']),
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
		));
		$auto_start = true;
	} else if (isset($_REQUEST['resume'])) {
		$progress_contents = file($progress_file);
		$last_attachment = $progress_contents[0];
		$attachments = unserialize($progress_contents[1]);
		$auto_start = true;
		$skip_attachments = true;
	} else {
		$attachments = get_posts( array(
			'numberposts' => -1,
			'post_type' => 'attachment',
			'post_mime_type' => 'image'
		));
	}
	//prep $attachments for storing in a file
	$attach_ser = serialize($attachments);
	require( dirname(__FILE__) . '/bulk.php' );
}

/**
 * Manually process an image from the Media Library
 */
function ewww_image_optimizer_manual() {
	if ( FALSE === current_user_can('upload_files') ) {
		wp_die(__('You don\'t have permission to work with uploaded files.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}

	if ( FALSE === isset($_GET['attachment_ID'])) {
		wp_die(__('No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}

	$attachment_ID = intval($_GET['attachment_ID']);

	$original_meta = wp_get_attachment_metadata( $attachment_ID );
	$new_meta = ewww_image_optimizer_resize_from_meta_data( $original_meta, $attachment_ID );
	wp_update_attachment_metadata( $attachment_ID, $new_meta );

	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	wp_redirect($sendback);
	exit(0);
}

/**
 * Process an image.
 *
 * Returns an array of the $file $results.
 *
 * @param   string $file            Full absolute path to the image file
 * @returns array
 */
function ewww_image_optimizer($file) {
	$file_path = $file;

	// check that the file exists
	if ( FALSE === file_exists($file_path) || FALSE === is_file($file_path) ) {
		$msg = sprintf(__("Could not find <span class='code'>%s</span>", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file_path);
		return array($file, $msg);
	}

	// check that the file is writable
	if ( FALSE === is_writable($file_path) ) {
		$msg = sprintf(__("<span class='code'>%s</span> is not writable", EWWW_IMAGE_OPTIMIZER_DOMAIN), $file_path);
		return array($file, $msg);
	}

	// check that the file is within the WP uploads folder 
	$upload_dir = wp_upload_dir();
	$upload_path = trailingslashit( $upload_dir['basedir'] );
	$path_in_upload = stripos(realpath($file_path), realpath($upload_path));
	$path_in_wp = stripos(realpath($file_path), realpath(ABSPATH));
	if (0 !== $path_in_upload && 0 !== $path_in_wp) {
		$msg = sprintf(__("<span class='code'>%s</span> must be within the wordpress or upload directory (<span class='code'>%s or %s</span>)", EWWW_IMAGE_OPTIMIZER_DOMAIN), htmlentities($file_path), $upload_path, ABSPATH);
		return array($file, $msg);
	}

	if(function_exists('getimagesize')){
		$type = getimagesize($file_path);
		if(false !== $type){
			$type = $type['mime'];
		}
	} elseif (function_exists('mime_content_type')) {
		$type = mime_content_type($file_path);
	} else {
		$type = 'Missing getimagesize() and mime_content_type() PHP functions';
	}

	list ($jpegtran_path, $optipng_path, $gifsicle_path, $pngout_path) = ewww_image_optimizer_path_check();
	// To skip binary checking, you can visit the EWWW Image Optimizer options page
	if(get_option('ewww_image_optimizer_skip_check') == TRUE){
		$skip_jpegtran = true;
		$skip_optipng = true;
		$skip_gifsicle = true;
		$skip_pngout = true;
	} else {
		$skip_jpegtran = false;
		$skip_optipng = false;
		$skip_gifsicle = false;
		$skip_pngout = false;
		//$skip = true;
	//} else {
	//	$skip = false;
	}

	switch($type) {
		case 'image/jpeg':
			if (get_option('ewww_image_optimizer_disable_jpegtran')) {
				$result = 'jpegtran is disabled';
				break;
			}
			$result = trim(exec('which ' . $jpegtran_path));
			if(!$skip_jpegtran && empty($result)){
				$result = '<em>jpegtran</em> is missing';
				break;
			}
			$orig_size = filesize($file);
			$tempfile = $file . ".tmp"; //non-progressive jpeg
			$progfile = $file . ".prog"; // progressive jpeg
			if(get_option('ewww_image_optimizer_jpegtran_copy') == TRUE){
				$copy_opt = 'none';
			} else {
				$copy_opt = 'all';
			}
			exec("$jpegtran_path -copy $copy_opt -optimize $file > $tempfile");
			exec("$jpegtran_path -copy $copy_opt -optimize -progressive $file > $progfile");
			$non_size = filesize($tempfile);
			$prog_size = filesize($progfile);
			// compare progressive vs. non-progressive
			if ($prog_size > $non_size) {
				$new_size = $non_size;
				unlink($progfile);
			} else {
				$new_size = $prog_size;
				exec("mv $progfile $tempfile");
			}
			// compare best-optimized vs. original
			if ($orig_size > $new_size && $new_size != 0) {
				exec("mv $tempfile $file");
				$result = "$file: $orig_size vs. $new_size";
			} else {
				unlink($tempfile);
				$result = "$file: unchanged";
			}
			break;
		case 'image/png':
			if (get_option('ewww_image_optimizer_disable_optipng') && get_option('ewww_image_optimizer_disable_pngout')) {
				$result = 'png tools are disabled';
				break;
			}
			$result = trim(exec('which ' . $optipng_path));
			if(!$skip_optipng && empty($result) && !get_option('ewww_image_optimizer_disable_optipng')) {
				$result = '<em>optipng</em> is missing';
				break;
			}
			$result = trim(exec('which ' . $pngout_path));
			if(!$skip_pngout && empty($result) && !get_option('ewww_image_optimizer_disable_pngout')) {
				$result = '<em>pngout</em> is missing';
				break;
			}
			$orig_size = filesize($file);
			if(!get_option('ewww_image_optimizer_disable_pngout')) {
				$pngout_level = get_option('ewww_image_optimizer_pngout_level');
				exec("$pngout_path -s$pngout_level -q $file");
			}
			if(!get_option('ewww_image_optimizer_disable_optipng')) {
				$optipng_level = get_option('ewww_image_optimizer_optipng_level');
				exec("$optipng_path -o$optipng_level -quiet $file");
			}
			clearstatcache();
			$new_size = filesize($file);
			if ($orig_size > $new_size) {
				$result = "$file: $orig_size vs. $new_size";    
			} else {
				$result = "$file: unchanged";
			}
			break;
		case 'image/gif':
			if (get_option('ewww_image_optimizer_disable_gifsicle')) {
				$result = 'gifsicle is disabled';
				break;
			}
			$result = trim(exec('which ' . $gifsicle_path));

			// TO DO: don't skip optimization if conversion to PNG is enabled

			if(!$skip_gifsicle && empty($result) && !get_option('ewww_image_optimizer_disable_gifsicle')) {
				$result = '<em>gifsicle</em> is missing';
				break;
			}
			$gif_converted = FALSE;
			$orig_size = filesize($file);
			// TO DO: need to compare the converted version to the gifsicle optimized version too
			if(get_option('ewww_image_optimizer_gif_to_png') && !ewww_image_optimizer_is_animated($file)) {
			//	$pngfile = substr_replace($file, 'png', -3);
			//	exec("convert $file $pngfile");
				if(!get_option('ewww_image_optimizer_disable_pngout')) {
					$pngout_level = get_option('ewww_image_optimizer_pngout_level');
					exec("$pngout_path -s$pngout_level -q $file");
					$pngfile = substr_replace($file, 'png', -3);
				}
				// TO DO: figure out what to do if pngout is enabled, and we should run optipng on the PNG, not the GIF
				if(!get_option('ewww_image_optimizer_disable_optipng')) {
					$optipng_level = get_option('ewww_image_optimizer_optipng_level');
					if (isset($pngfile)) {
						exec("$optipng_path -o$optipng_level -quiet $pngfile");
					} else {
						exec("$optipng_path -o$optipng_level -quiet $file");
						$pngfile = substr_replace($file, 'png', -3);
					}
				}
				clearstatcache();
				if (isset($pngfile)) {
					$png_size = filesize($pngfile);
					if ($orig_size > $png_size && $png_size != 0) {
						$gif_converted = TRUE;
					}
				}
			} 
			exec("$gifsicle_path -b -O3 --careful $file");
			clearstatcache();
			$new_size = filesize($file);
			if ($gif_converted && $new_size > $png_size && $png_size != 0) {
				$new_size = $png_size;
				$file = $pngfile;
				if ( FALSE === has_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment') )
	                        	add_filter('wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10, 2);
			} elseif ($gif_converted) {
				$gif_converted = FALSE;
				unlink($pngfile);
			}
			if ($orig_size > $new_size) {
				$result = "$file: $orig_size vs. $new_size";
			} else {
				$result = "$file: unchanged";
			}
			break;
		default:
			return array($file, __('Unknown type: ' . $type, EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}

	$result = str_replace($file . ': ', '', $result);

	if($result == 'unchanged') {
		return array($file, __('No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN));
	}
	if(strpos($result, ' vs. ') !== false) {
		$s = explode(' vs. ', $result);
		$savings = intval($s[0]) - intval($s[1]);
		$savings_str = ewww_image_optimizer_format_bytes($savings, 1);
		$savings_str = str_replace(' ', '&nbsp;', $savings_str);
		$percent = 100 - (100 * ($s[1] / $s[0]));
		$results_msg = sprintf(__("Reduced by %01.1f%% (%s)", EWWW_IMAGE_OPTIMIZER_DOMAIN),
			$percent,
			$savings_str);
		return array($file, $results_msg);
	}
	return array($file, $result);
}

/**
 * Update the attachment's meta data after being converted from GIF to PNG 
 */
function ewww_image_optimizer_update_attachment($data, $ID) {
	$orig_file = get_attached_file($ID);
	update_attached_file($ID, $data['file']);
	$post = get_post($ID);
	$guid = preg_replace('/.gif$/i', '.png', $post->guid);
	wp_update_post( array('ID' => $ID,
			      'post_mime_type' => 'image/png',
			      'guid' => $guid) );
	return $data;
}

/**
 * Check the submitted GIF to see if it is animated
 */
function ewww_image_optimizer_is_animated($filename) {
    if(!($fh = @fopen($filename, 'rb')))
        return false;
    $count = 0;
    //an animated gif contains multiple "frames", with each frame having a
    //header made up of:
    // * a static 4-byte sequence (\x00\x21\xF9\x04)
    // * 4 variable bytes
    // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)
   
    // We read through the file til we reach the end of the file, or we've found
    // at least 2 frame headers
    while(!feof($fh) && $count < 2) {
        $chunk = fread($fh, 1024 * 100); //read 100kb at a time
        $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
   }
   
    fclose($fh);
    return $count > 1;
}

/**
 * Read the image paths from an attachment's meta data and process each image
 * with ewww_image_optimizer().
 *
 * This method also adds a `ewww_image_optimizer` meta key for use in the media library.
 *
 * Called after `wp_generate_attachment_metadata` is completed.
 */
function ewww_image_optimizer_resize_from_meta_data($meta, $ID = null) {
	$file_path = $meta['file'];
	$store_absolute_path = true;
	$upload_dir = wp_upload_dir();
	$upload_path = trailingslashit( $upload_dir['basedir'] );

	// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
	if ( FALSE === strpos($file_path, WP_CONTENT_DIR) ) {
		$store_absolute_path = false;
		$file_path =  $upload_path . $file_path;
	}

	list($file, $msg) = ewww_image_optimizer($file_path);

	$meta['file'] = $file;
	$meta['ewww_image_optimizer'] = $msg;

	// strip absolute path for Wordpress >= 2.6.2
	if ( FALSE === $store_absolute_path ) {
		$meta['file'] = str_replace($upload_path, '', $meta['file']);
	}
	// no resized versions, so we can exit
	if ( !isset($meta['sizes']) )
		return $meta;

	// meta sizes don't contain a path, so we calculate one
	$base_dir = dirname($file_path) . '/';

	foreach($meta['sizes'] as $size => $data) {
		list($optimized_file, $results) = ewww_image_optimizer($base_dir . $data['file']);

		$meta['sizes'][$size]['file'] = str_replace($base_dir, '', $optimized_file);
		$meta['sizes'][$size]['ewww_image_optimizer'] = $results;
	}
	return $meta;
}

/**
 * Print column header for optimizer results in the media library using
 * the `manage_media_columns` hook.
 */
function ewww_image_optimizer_columns($defaults) {
	$defaults['ewww-image-optimizer'] = 'Image Optimizer';
	return $defaults;
}

/**
 * Return the filesize in a humanly readable format.
 * Taken from http://www.php.net/manual/en/function.filesize.php#91477
 */
function ewww_image_optimizer_format_bytes($bytes, $precision = 2) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB');
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Print column data for optimizer results in the media library using
 * the `manage_media_custom_column` hook.
 */
function ewww_image_optimizer_custom_column($column_name, $id) {
	if( $column_name == 'ewww-image-optimizer' ) {
		$data = wp_get_attachment_metadata($id);

		if(!isset($data['file'])){
			$msg = '<br>Metadata is missing file path.';
			print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
			return;
		}

		$file_path = $data['file'];
		$upload_dir = wp_upload_dir();
		$upload_path = trailingslashit( $upload_dir['basedir'] );

		// WordPress >= 2.6.2: determine the absolute $file_path (http://core.trac.wordpress.org/changeset/8796)
		if ( FALSE === strpos($file_path, WP_CONTENT_DIR) ) {
			$file_path =  $upload_path . $file_path;
		}

		$msg = '';

		if(function_exists('getimagesize')){
			$type = getimagesize($file_path);
			if(false !== $type){
				$type = $type['mime'];
			}
		} elseif(function_exists('mime_content_type')) {
			$type = mime_content_type($file_path);
		} else {
			$type = false;
			$msg = '<br>getimagesize() and mime_content_type() PHP functions are missing';
		}
		$file_size = ewww_image_optimizer_format_bytes(filesize($file_path));

		$valid = true;
		switch($type) {
			case 'image/jpeg':
				if(EWWW_IMAGE_OPTIMIZER_JPEGTRAN == false) {
					$valid = false;
					$msg = '<br>' . __('<em>jpegtran</em> is missing');
				}
				break; 
			case 'image/png':
				if(EWWW_IMAGE_OPTIMIZER_PNGOUT == false && EWWW_IMAGE_OPTIMIZER_OPTIPNG == false) {
					$valid = false;
					$msg = '<br>' . __('<em>optipng/pngout</em> is missing');
				}
				break;
			case 'image/gif':
				if(EWWW_IMAGE_OPTIMIZER_GIFSICLE == false) {
					$valid = false;
					$msg = '<br>' . __('<em>gifsicle</em> is missing');
				}
				break;
			default:
				$valid = false;
		}

		if($valid == false) {
			print __('Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN) . $msg;
			return;
		}

		if ( isset($data['ewww_image_optimizer']) && !empty($data['ewww_image_optimizer']) ) {
			print $data['ewww_image_optimizer'];
			print "<br>Image Size: $file_size";
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN));
		} else {
			print __('Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
			print "<br>Image Size: $file_size";
			printf("<br><a href=\"admin.php?action=ewww_image_optimizer_manual&amp;attachment_ID=%d\">%s</a>",
				$id,
				__('Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN));
		}
	}
}

// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/ 
function ewww_image_optimizer_add_bulk_actions_via_javascript() { ?> 
	<script type="text/javascript"> 
		jQuery(document).ready(function($){ 
			$('select[name^="action"] option:last-child').before('<option value="bulk_optimize">Bulk Optimize</option>'); 
		}); 
	</script>
<?php } 

// Handles the bulk actions POST 
// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/ 
function ewww_image_optimizer_bulk_action_handler() { 
	if ( empty( $_REQUEST['action'] ) || ( 'bulk_optimize' != $_REQUEST['action'] && 'bulk_optimize' != $_REQUEST['action2'] ) ) {
		return;
	}
	if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) ) {
		return; 
	}
	check_admin_referer( 'bulk-media' ); 
	$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) ); 
	// Can't use wp_nonce_url() as it escapes HTML entities 
	wp_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'ewww-image-optimizer-bulk' ), admin_url( 'upload.php?page=ewww-image-optimizer-bulk&goback=1&ids=' . $ids ) ) ); 
	exit(); 
}

function ewww_image_optimizer_install_pngout() {
	$wget_command = "wget -nc -O " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static.tar.gz http://static.jonof.id.au/dl/kenutils/pngout-20120530-linux-static.tar.gz";
	exec ($wget_command);
	exec ("tar xvzf " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static.tar.gz -C " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
	$arch_type = $_REQUEST['arch'];
	switch ($arch_type) {
		case 'i386':
			exec ("cp " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/i386/pngout-static " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
			break;
		case 'i686':
			exec ("cp " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/i686/pngout-static " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
			break;
		case 'athlon':
			exec ("cp " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/athlon/pngout-static " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
			break;
		case 'pentium4':
			exec ("cp " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/pentium4/pngout-static " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
			break;
		case 'x64':
			exec ("cp " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . "pngout-20120530-linux-static/x86_64/pngout-static " . EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH);
			break;
	}
	$sendback = wp_get_referer();
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	wp_redirect($sendback);
	exit(0);
}

function ewww_image_optimizer_options () {
	list ($jpegtran_path, $optipng_path, $gifsicle_path) = ewww_image_optimizer_path_check();
	?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br /></div>
		<h2>EWWW Image Optimizer Settings</h2>
		<p><a href="http://shanebishop.net/ewww-image-optimizer/">Plugin Home Page</a> |
		<a href="http://wordpress.org/extend/plugins/ewww-image-optimizer/installation/">Installation Instructions</a> | 
		<a href="http://wordpress.org/support/plugin/ewww-image-optimizer">Plugin Support</a></p>
		<div id="right_panel" style="float: right">
		<div id="poll" style="margin: 8px"><script type="text/javascript" charset="utf-8" src="http://static.polldaddy.com/p/6602406.js"></script>
		<noscript><a href="http://polldaddy.com/poll/6602406/">EWWW IO Feedback</a></noscript></div>
		<div id="debug" style="border: 1px solid #ccc; padding: 0 8px; margin: 8px; width: 284px; border-radius: 12px;">
			<h3>Debug information</h3>
			<div style="border-top: 1px solid #e8e8e8; padding: 10px 0"><p>computed jpegtran path: <?php echo $jpegtran_path; ?><br />
			jpegtran location (using 'which'): <?php echo trim(exec('which ' . $jpegtran_path)); ?><br />
			computed optipng path: <?php echo $optipng_path; ?><br />
			optipng location (using 'which'): <?php echo trim(exec('which ' . $optipng_path)); ?><br />
			optipng version: <?php exec($optipng_path . ' -v', $optipng_version); echo $optipng_version[0]; ?><br />
			computed gifsicle path: <?php echo $gifsicle_path; ?><br />
			gifsicle location (using 'which'): <?php echo trim(exec('which ' . $gifsicle_path)); ?><br />
			gifsicle version: <?php exec($gifsicle_path . ' --version', $gifsicle_version); echo $gifsicle_version[0]; ?><br />
			<?php if( ini_get('safe_mode') ){
				echo "safe mode: On<br />";
			} else {
				echo "safe mode: Off<br />";
			}
			echo "Operating System: " . PHP_OS . "<br>";
			$disabled = explode(', ', ini_get('disable_functions'));
			if(in_array('exec', $disabled)){
				echo "exec(): disabled<br>";
			} else {
				echo "exec(): enabled<br>";
			}
			if(function_exists('getimagesize')){
				echo "getimagesize(): OK<br>";
			} else {
				echo "getimagesize(): missing<br>";
			}
			if(function_exists('mime_content_type')){
				echo "mime_content_type(): OK<br>";
			} else {
				echo "mime_content_type(): missing<br>";
			}
			?></p></div>
		</div>
		</div>
		<h3>Installation</h3>
		<p><b>If you are on shared hosting, and have installed the utilities in your home folder, you can provide the paths below.</b></p>
		<p><b>NEW:</b> I have compiled static binaries for <b>gifsicle</b> and <b>optipng</b> for those who don't have access to a shell or build utilities. If all goes well, these will become one-click installs in the future:<br /><a href="http://shanebishop.net/uploads/gifsicle.zip">gifsicle</a> | <a href="http://shanebishop.net/uploads/optipng.zip">optipng</a><br />
		<b>Install pngout</b> - Click the link below that corresponds to the architecture of your server. If in doubt, try the i386 or ask your webhost. Pngout is free closed-source software that can produce drastically reduced filesizes for PNGs, but can be very time consuming to process images<br />
<a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=i386">i386</a> - <a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=athlon">athlon</a> - <a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=pentium4">pentium4</a> - <a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=i686">i686</a> - <a href="admin.php?action=ewww_image_optimizer_install_pngout&arch=x64">64-bit</a></p>
		<form method="post" action="options.php">
			<?php settings_fields('ewww_image_optimizer_options'); ?>
			<h3>General Settings</h3>
			<p>The plugin performs a check to make sure your system has the programs we use for optimization: jpegtran, optipng, and gifsicle. In some rare cases, these checks may erroneously report that you are missing the required utilities even though you have them installed.</p>
			<p><b>Do you want to skip the utils check?</b> <i>*Only do this if you are SURE that you have the utilities installed, or you don't care about the missing ones. Checking this option also bypasses our basic security checks on the paths entered below.</i><br />
			<table class="form-table" style="display: inline">
				<tr><th><label for="ewww_image_optimizer_skip_check">Skip utils check</label></th><td><input type="checkbox" id="ewww_image_optimizer_skip_check" name="ewww_image_optimizer_skip_check" value="true" <?php if (get_option('ewww_image_optimizer_skip_check') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_jpegtran">disable jpegtran</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_jpegtran" name="ewww_image_optimizer_disable_jpegtran" <?php if (get_option('ewww_image_optimizer_disable_jpegtran') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_optipng">disable optipng</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_optipng" name="ewww_image_optimizer_disable_optipng" <?php if (get_option('ewww_image_optimizer_disable_optipng') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
				<tr><th><label for="ewww_image_optimizer_disable_pngout">disable pngout</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_pngout" name="ewww_image_optimizer_disable_pngout" <?php if (get_option('ewww_image_optimizer_disable_pngout') == TRUE) { ?>checked="true"<?php } ?> /></td><tr>
				<tr><th><label for="ewww_image_optimizer_disable_gifsicle">disable gifsicle</label></th><td><input type="checkbox" id="ewww_image_optimizer_disable_gifsicle" name="ewww_image_optimizer_disable_gifsicle" <?php if (get_option('ewww_image_optimizer_disable_gifsicle') == TRUE) { ?>checked="true"<?php } ?> /></td></tr>
			</table>
			<h3>Path Settings</h3>
			<table class="form-table" style="display: inline">
				<tr><th><label for="ewww_image_optimizer_jpegtran_path">jpegtran path</label></th><td><input type="text" style="width: 400px" id="ewww_image_optimizer_jpegtran_path" name="ewww_image_optimizer_jpegtran_path" value="<?php echo get_option('ewww_image_optimizer_jpegtran_path'); ?>" /></td></tr>
				<tr><th><label for="ewww_image_optimizer_optipng_path">optipng path</label></th><td><input type="text" style="width: 400px" id="ewww_image_optimizer_optipng_path" name="ewww_image_optimizer_optipng_path" value="<?php echo get_option('ewww_image_optimizer_optipng_path'); ?>" /></td></tr>
				<tr><th><label for="ewww_image_optimizer_gifsicle_path">gifsicle path</label></th><td><input type="text" style="width: 400px" id="ewww_image_optimizer_gifsicle_path" name="ewww_image_optimizer_gifsicle_path" value="<?php echo get_option('ewww_image_optimizer_gifsicle_path'); ?>" /></td></tr>
			</table>
			<h3>Conversion Settings</h3>
			<table class="form-table" style="display: inline">
				<tr><th><label for="ewww_image_optimizer_jpg_to_png">enable JPG to PNG conversion</label></th><td><input type="checkbox" id="ewww_image_optimizer_jpg_to_png" name="ewww_image_optimizer_jpg_to_png" <?php if (get_option('ewww_image_optimizer_jpg_to_png') == TRUE) { ?>checked="true"<?php } ?> /> <i>PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will likely slow down JPG processing significantly, and you may want to enable it only temporarily. Original JPGs are left in place, not deleted.</i></td></tr>
				<tr><th><label for="ewww_image_optimizer_png_to_jpg">enable PNG to JPG conversion</label></th><td><input type="checkbox" id="ewww_image_optimizer_png_to_jpg" name="ewww_image_optimizer_png_to_jpg" <?php if (get_option('ewww_image_optimizer_png_to_jpg') == TRUE) { ?>checked="true"<?php } ?> /> <i>JPG is generally much better than PNG for photographic use, but doesn't support transparency, so we don't convert PNGs with transparency. Original GIFs are left in place, not deleted.</i></td></tr>
				<tr><th><label for="ewww_image_optimizer_gif_to_png">enable GIF to PNG conversion</label></th><td><input type="checkbox" id="ewww_image_optimizer_gif_to_png" name="ewww_image_optimizer_gif_to_png" <?php if (get_option('ewww_image_optimizer_gif_to_png') == TRUE) { ?>checked="true"<?php } ?> /> <i>PNG is generally much better than GIF, but doesn't support animated images, so we don't convert those. Original GIFs are left in place, not deleted.</i></td></tr>
			</table>
			<h3>Advanced options</h3>
			<table class="form-table" style="display: inline">
				<tr><th><label for="ewww_image_optimizer_jpegtran_copy">Remove JPG metadata</label></th><td><input type="checkbox" id="ewww_image_optimizer_jpegtran_copy" name="ewww_image_optimizer_jpegtran_copy" value="true" <?php if (get_option('ewww_image_optimizer_jpegtran_copy') == TRUE) { ?>checked="true"<?php } ?> /> <i>This wil remove ALL metadata (EXIF and comments)</i></td></tr>
				<tr><th><label for="ewww_image_optimizer_optipng_level">optipng optimization level</label></th>
				<td><select id="ewww_image_optimizer_optipng_level" name="ewww_image_optimizer_optipng_level">
				<option value="1"<?php if (get_option('ewww_image_optimizer_optipng_level') == 1) { echo ' selected="selected"'; } ?>>Level 1: 1 trial</option>
				<option value="2"<?php if (get_option('ewww_image_optimizer_optipng_level') == 2) { echo ' selected="selected"'; } ?>>Level 2: 8 trials</option>
				<option value="3"<?php if (get_option('ewww_image_optimizer_optipng_level') == 3) { echo ' selected="selected"'; } ?>>Level 3: 16 trials</option>
				<option value="4"<?php if (get_option('ewww_image_optimizer_optipng_level') == 4) { echo ' selected="selected"'; } ?>>Level 4: 24 trials</option>
				<option value="5"<?php if (get_option('ewww_image_optimizer_optipng_level') == 5) { echo ' selected="selected"'; } ?>>Level 5: 48 trials</option>
				<option value="6"<?php if (get_option('ewww_image_optimizer_optipng_level') == 6) { echo ' selected="selected"'; } ?>>Level 6: 120 trials</option>
				<option value="7"<?php if (get_option('ewww_image_optimizer_optipng_level') == 7) { echo ' selected="selected"'; } ?>>Level 7: 240 trials</option>
				</select> (default=2) - <i>According to the author of optipng, 10 trials should satisfy most people, 30 trials should satisfy everyone.</i></td></tr>
				<tr><th><label for="ewww_image_optimizer_pngout_level">pngout optimization level</label></th>
				<td><select id="ewww_image_optimizer_pngout_level" name="ewww_image_optimizer_pngout_level">
				<option value="0"<?php if (get_option('ewww_image_optimizer_pngout_level') == 0) { echo ' selected="selected"'; } ?>>Level 0: Xtreme! (Slowest)</option>
				<option value="1"<?php if (get_option('ewww_image_optimizer_pngout_level') == 1) { echo ' selected="selected"'; } ?>>Level 1: Intense (Slow)</option>
				<option value="2"<?php if (get_option('ewww_image_optimizer_pngout_level') == 2) { echo ' selected="selected"'; } ?>>Level 2: Longest Match (Fast)</option>
				<option value="3"<?php if (get_option('ewww_image_optimizer_pngout_level') == 3) { echo ' selected="selected"'; } ?>>Level 3: Huffman Only (Faster)</option>
			</select> (default=2) - <i>If you have CPU cycles to spare, go with level 0</i></td></tr>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="Save Changes" /></p>
		</form>
	</div>
	<?php
}

