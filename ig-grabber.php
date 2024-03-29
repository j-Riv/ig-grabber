<?php
define('WP_USE_THEMES', false);
require($_SERVER['DOCUMENT_ROOT']. '/wp-blog-header.php');
require_once ( ABSPATH. '/wp-admin/includes/post.php' );
require_once ( ABSPATH. '/wp-admin/includes/image.php' );

set_time_limit(0); // script will run for an infinite amount of time
ini_set('default_socket_timeout', 600); // server settings
session_start(); // starts new or resume existing session

// MySQL settings - You can get this info from your web host
$database = include('ig-grabber-config.php');
define('DB_NAME', $database['DB_NAME']); // WordPress Database Name
define('DB_USER', $database['DB_USER']); // MySQL Database Username
define('DB_PASSWORD', $database['DB_PASSWORD']); // MySQL Database Password
define('DB_HOST', $database{'DB_HOST'}); // MySQL Hostname
define('DB_TABLE', $database{'DB_TABLE'}); // MySQL Table

/**
 * Gets the old token data from the database.
 * @returns {string} - The Access Token
 */
function getOldToken() {
  // Create connection
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT access_token, token_type, expires_in FROM ". DB_TABLE. " WHERE ID = 1";
  $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
  } else {
    echo '0 results';
  }
  $conn->close();

  return $data['access_token'];
}

/**
 * Updates the token data in the database.
 * @param {string} $token - The Access Token
 * @param {string} $type - The Token Type
 * @param {string} $expiration - The Token's Expiration
 */
function updateToken($token, $type, $expiration) {
  // Create connection
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "UPDATE ". DB_TABLE. " SET access_token='$token', token_type='$type', expires_in='$expiration' WHERE ID=1";

  if ($conn->query($sql) === TRUE) {
    echo 'New Token Saved Successfully';
  } else {
    echo 'Error: '. $sql. '<br>'. $conn->error;
  }

  $conn->close();
}

/** 
 * Gets a new 'Long-Lived Token' from Instagram. 
*/
function getNewToken() {
  $old_token = getOldToken();
  $url = 'https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token='. $old_token;

  $ch = curl_init(); //used to transfer data with a url
 	curl_setopt_array($ch, array( // sets options for a curl transfer
		CURLOPT_URL => $url, // the url
		CURLOPT_RETURNTRANSFER => true, // return the results if successful
		CURLOPT_SSL_VERIFYPEER => false, // we dont need to verify any certificates
		CURLOPT_SSL_VERIFYHOST => 2 // we wont verify host
	));

	$result = curl_exec($ch); // executue the transfer
	curl_close($ch); // close the curl session

  $data = json_decode($result);
  if (property_exists($data, "access_token") && !is_null($data->access_token) && !empty($data->access_token)) {
    updateToken($data->access_token, $data->token_type, $data->expires_in);
    return $data->access_token;
  }
  return false;
}

/**
 * Gets Instagram media.
 * @param {string} $url - The User Media Edge
 * @returns - {object} - Media Data Object
 */
function getInstagramMedia($url) {
  $ch = curl_init(); // used to transfer data with a url
 	curl_setopt_array($ch, array( // sets options for a curl transfer
		CURLOPT_URL => $url, // the url
		CURLOPT_RETURNTRANSFER => true, // return the results if successful
		CURLOPT_SSL_VERIFYPEER => false, // we dont need to verify any certificates
		CURLOPT_SSL_VERIFYHOST => 2 // we wont verify host
	));

	$result = curl_exec($ch); // executue the transfer
	curl_close($ch); // close the curl session

  return $result;
}

/**
 * Prints media to screen for verification while
 * posts and media are created and saved.
 */
function printImages($token){
	$url = 'https://graph.instagram.com/me/media?fields=id,media_type,media_url,thumbnail_url,caption,timestamp&access_token='. $token;
	$instagramData = getInstagramMedia($url);
	$result = json_decode($instagramData, true);

	foreach($result['data'] as $item){
		$image_url = $item['media_url'];
		$created_time = $item['timestamp'];
		$caption = $item['caption'];
		$caption = explode('#', $caption, 2);
		$caption = $caption[0];

    $created_time = date('m-d-Y_H-i-s-a', strtotime($created_time));
    echo '<h2>FORMATTED_TIME => '. $created_time .'</h2>';
		if (!post_exists( $created_time )){
			if($item['media_type'] == 'VIDEO'){
				$image_url = $item['thumbnail_url'];
				$video_url = $item['media_url'];
				echo '<embed src="'. $video_url. '" /> <br/>';
				savePicture($image_url, $created_time, $caption);
				saveVideo($video_url, $created_time, $caption);
			}elseif($item['media_type'] == "IMAGE"){
				$image_url = $item['media_url'];
				echo '<img src="'. $image_url. '" /> <br/>';
				savePicture($image_url, $created_time, $caption);
			}
		} else {
			echo '<br/><b>POST EXISTS SKIPPING</b><br/>';
		}
	}
}

/**
 * Saves images to database and creates blog post.
 */
function savePicture($image_url, $created_time, $caption){
	// Print to screen
	echo 'IMAGE_URL => '. $image_url. '<br />';
	echo 'CREATED_TIME => '. $created_time. '<br />';
	$filename = $created_time;
	$filenameWithExt = $filename .'.jpg';
	echo 'FILENAME => '. $filename. '<br />';
	echo 'FULL FILENAME => ' . $filenameWithExt. '<br />';
	echo 'CAPTION => '. $caption. '<br />';

	// Create post object
	$my_post_data = array(
	  'post_title'    => $created_time,
	  'post_content'  => $caption,
	  'post_status'   => 'publish',
	  'post_author'   => 1,
	  'post_category'  => array(32) // Default empty.
	);
	if (!post_exists( $created_time )){
		// Insert the post into the database
		$post_id = wp_insert_post( $my_post_data );

		// SELECT * FROM pics WHERE filename=$filename ---- if no matches, continue
		$upload_dir = wp_upload_dir();
		if(wp_mkdir_p($upload_dir['path']))
		    $file = $upload_dir['path']. '/'. $filenameWithExt;
		else
		    $file = $upload_dir['basedir']. '/'. $filenameWithExt;
		echo '<br />'. $file;
		file_put_contents($file, file_get_contents($image_url)); // Save Picture to Uploads Folder

		$wp_filetype = wp_check_filetype($filenameWithExt, null );
		$attachment = array(
		    'post_mime_type' => $wp_filetype['type'],
		    'post_title' => sanitize_file_name($filename),
		    'post_content' => '',
		    'post_status' => 'inherit'
		);
	 	// Attach Featured Image to Post
		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		wp_update_attachment_metadata( $attach_id,  $attach_data );
		update_post_meta($post_id, '_thumbnail_id', $attach_id);
		update_post_meta($attach_id, '_wp_attachment_image_alt', $caption);
		set_post_thumbnail( $post_id, $attach_id );
	} else {
		echo '<br/><b>POST EXISTS SKIPPING</b><br/>';
	}
}

/**
 * Saves videos to database. 
 */
function saveVideo($video_url, $created_time, $caption){
	// Print to screen
	echo 'VIDEO_URL => '. $video_url. '<br />';
	echo 'CREATED_TIME => '. $created_time. '<br />';
	$filename = $created_time. '.mp4';
	echo 'FILENAME => '. $filename. '<br />';
	echo 'CAPTION => '. $caption. '<br />';

		//SELECT * FROM pics WHERE filename=$filename ---- if no matches, continue
		$upload_dir = wp_upload_dir();
		if(wp_mkdir_p($upload_dir['path']))
		    $file = $upload_dir['path'] . '/' . $filename;
		else
		    $file = $upload_dir['basedir'] . '/' . $filename;
		echo '<br />' . $file;
		file_put_contents($file, file_get_contents($video_url)); // Save Video to Uploads Folder

		$wp_filetype = wp_check_filetype($filename, null );
		$attachment = array(
		    'post_mime_type' => $wp_filetype['type'],
		    'post_title' => sanitize_file_name($filename),
		    'post_content' => '',
		    'post_status' => 'inherit'
		);
}

// run
$code = getNewToken();
if ($code) {
  printImages($code);
} else {
  echo 'ERROR, NO ACCESS TOKEN';
}
?>

