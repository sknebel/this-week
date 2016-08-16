<?php
use \FeedWriter\RSS2;

chdir(dirname(__FILE__));
require_once('vendor/autoload.php');
require_once('functions.php');

$startDate = strtotime('-7 days');
$endDate = time();

$date = IndieWeb\DateFormatter::format(date('Y-m-d', $startDate), date('Y-m-d', $endDate), false);

ob_start();
require('generate-header.php');
require('generate-events-summary.php');
require('generate-indienews.php');
require('generate-wiki-summary.php');
$html = ob_get_clean();

// Write the file that will be used as the source for the RSS feed email
$tmpfile = sys_get_temp_dir() . '/IndieWebCamp ' . $date . '.html';
file_put_contents($tmpfile, $html);

// Wrap it in an HTML wrapper for the web archive
ob_start();
require('html-wrapper.php');
$html = ob_get_clean();

$archivefile = Config::$publicPath . date('Y-m-d', $endDate) . '.html';
file_put_contents($archivefile, $html);

// Generate the RSS feed
$feedfile = Config::$publicPath . 'feed.xml';
$feed = new RSS2();
$feed->setTitle('This Week in the IndieWeb');
$feed->setLink('https://indieweb.org/this-week');
$feed->setDescription('See what\'s been happening this week in the IndieWeb');
$feed->setChannelElement('language', 'en-US');
$feed->setDate(date(DATE_RSS));
$feed->setChannelElement('pubDate', date(DATE_RSS));

$item = $feed->createNewItem();
$item->setTitle('This Week in the IndieWeb');
$item->setLink(Config::$baseURL . date('Y-m-d', $endDate) . '.html');
$item->setId(Config::$baseURL . date('Y-m-d', $endDate) . '.html', true);
$item->setDescription(file_get_contents($tmpfile));
$item->setDate(date(DATE_RSS));

$feed->addItem($item);

file_put_contents($feedfile, $feed->generateFeed());


$localtime = new DateTime();
$localtime->setTimeZone(new DateTimeZone('America/Los_Angeles'));

// Send an IRC reminder when this runs at the 0:30 mark
$msg = false;

if(isset($argv) && is_array($argv) && isset($argv[1])) {
	switch($argv[1]) {
		case 1:
			$msg = 'Just generated the first draft of this week\'s newsletter! '.Config::$baseURL.date('Y-m-d', $endDate).'.html I\'ll generate a draft again tomorrow, so please add to it before then! https://indieweb.org/this-week#How_to';
			break;
		case 2:
			$msg = 'Just generated this week\'s newsletter! You still have a few minutes to make changes, and I\'ll re-generate it 10 minutes before it gets sent out at 2pm Pacific time. '.Config::$baseURL.date('Y-m-d', $endDate).'.html';
			break;
		case 3:
			$msg = 'Generated the final version of the newsletter! This will be sent out at 2pm Pacific time. '.Config::$baseURL.date('Y-m-d', $endDate).'.html';
			break;
	}
}

if($msg) {
  $ch = curl_init(Config::$ircURL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'channel' => Config::$ircChannel,
    'content' => $msg
  ]));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer '.Config::$ircToken
  ]);
  curl_exec($ch);
}