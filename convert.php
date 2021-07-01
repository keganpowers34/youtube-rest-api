<?php
require_once __DIR__ . '/vendor/autoload.php';

use YoutubeDl\YoutubeDl;

define("DOWNLOAD_FOLDER", "download/"); //Be sure the chmod the download folder
define("DOWNLOAD_MAX_LENGTH", 0); //max video duration (in seconds) to be able to download, set to 0 to disable
define("LOG", false); //enable logging

header("Content-Type: application/json");

const POSSIBLE_FORMATS = ['mp3', 'mp4'];

if(isset($_GET["youtubelink"]) && !empty($_GET["youtubelink"]))
{
	$youtubelink = $_GET["youtubelink"];
	$format = $_GET['format'] ?? 'mp3';

	if(!in_array($format, POSSIBLE_FORMATS))
		die(json_encode(array("error" => true, "message" => "Invalid format: only mp3 or mp4 are possible")));

	$success = preg_match('#(?<=v=)[a-zA-Z0-9-]+(?=&)|(?<=v\/)[^&\n]+|(?<=v=)[^&\n]+|(?<=youtu.be/)[^&\n]+#', $youtubelink, $matches);

	if(!$success)
		die(json_encode(array("error" => true, "message" => "No video specified")));

	$id = $matches[0];

	$exists = file_exists(DOWNLOAD_FOLDER.$id.".".$format);

	if(DOWNLOAD_MAX_LENGTH > 0 || $exists)
	{
		$dl = new YoutubeDl(['skip-download' => true]);
		$dl->setDownloadPath(DOWNLOAD_FOLDER);
	
		try	{
			$video = $dl->download($youtubelink);
	
			if($video->getDuration() > DOWNLOAD_MAX_LENGTH && DOWNLOAD_MAX_LENGTH > 0)
				throw new Exception("The duration of the video is {$video->getDuration()} seconds while max video length is ".DOWNLOAD_MAX_LENGTH." seconds.");
		}
		catch (Exception $ex)
		{
			die(json_encode(array("error" => true, "message" => $ex->getMessage())));
		}
	}

	if(!$exists)
	{
		if($format == 'mp3')
		{
			$options = array(
				'extract-audio' => true,
				'audio-format' => 'mp3',
				'audio-quality' => 0,
				'output' => '%(id)s.%(ext)s',
				//'ffmpeg-location' => '/usr/local/bin/ffmpeg'
			);
		}
		else
		{
			$options = array(
				'continue' => true,
				'format' => 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
				'output' => '%(id)s.%(ext)s',
			);
		}

		$dl = new YoutubeDl($options);
		$dl->setDownloadPath(DOWNLOAD_FOLDER);
	}


	try
	{
		$url = $_SERVER['REQUEST_SCHEME'] ."://". $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."".DOWNLOAD_FOLDER;
		if($exists)
			$file = $url.$id.".".$format;
		else 
		{
			$video = $dl->download($youtubelink);
			$file = $url.$video->getFilename();
		}
		$json = json_encode(array([
			"error" => false,
			"youtube_id" => $video->getId(),
			"title" => $video->getTitle(),
			"alt_title" => $video->getAltTitle(),
			"duration" => $video->getDuration(),
			"file" => $file,
			"uploaded_at" => $video->getUploadDate(),
			"thumbnails" => "https://img.youtube.com/vi/".$id."/0.jpg",	
			"vtime" => sprintf( "%02d:%02d:%02d", $video['duration'] / 3600, $video['duration'] / 60 % 60, $video['duration'] % 60 ),
			] 
		));

		if(LOG)
		{
			$now = new DateTime();
			$file = fopen('logs/'.$now->format('Ymd').'.log', 'a');
			fwrite($file, '[' . $now->format('H:i:s') . '] ' . $json . PHP_EOL);
			fclose($file);
		}

		echo $json;
	}
	catch (Exception $e)
	{
		echo json_encode(array("error" => true, "message" => $e->getMessage()));
	}
}
else
	echo json_encode(array("error" => true, "message" => "Invalid request"));
?>
