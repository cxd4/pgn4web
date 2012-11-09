<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);

$targetUrl = get_param("targetUrl", "tu", "");
$linkFilterDefault = ".+\.pgn$";
$linkFilter = get_param("linkFilter", "lf", $linkFilterDefault);
$frameDepthDefault = 0;
$frameDepth = get_param("frameDepth", "fd", $frameDepthDefault);
$viewerUrlDefault = "viewer.php?pd=";
$viewerUrl = get_param("viewerUrl", "vu", $viewerUrlDefault);
$showDownload = get_param("showDownload", "sd", "false");
$showDownload = (($showDownload == "true") || ($showDownload == "t"));
$headlessPage = get_param("headlessPage", "hp", "false");
$headlessPage = (($headlessPage == "true") || ($headlessPage == "t"));
$help = get_param("help", "h", "false");
$help = (($help == "true") || ($help == "t"));
if ((! is_numeric($frameDepth)) || ($frameDepth < 0) || ($frameDepth > 5)) { $frameDepth = $frameDepthDefault; }
$actualFrameDepth = 0;
$urls = array();
get_links($targetUrl, $frameDepth);
print_links();

function get_links($targetUrl, $depth) {
    global $urls, $linkFilter, $frameDepth, $actualFrameDepth;

    if (! $targetUrl) { return; }

    if ($frameDepth - $depth > $actualFrameDepth) { $actualFrameDepth = $frameDepth - $depth; }

    $html = file_get_contents($targetUrl);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $bases = $xpath->evaluate("/html/head//base");
    if ($bases->length > 0) {
        $baseItem = $bases->item($bases->length - 1);
        $base = $baseItem->getAttribute('href');
    } else {
        $base = $targetUrl;
    }

    if ($depth > 0) {
        $frames = $xpath->evaluate("/html/body//iframe");
        for ($i = 0; $i < $frames->length; $i++) {
            $frame = $frames->item($i);
            $url = make_absolute($frame->getAttribute('src'), $base);
            if ($url != $targetUrl) { get_links($url, $depth -1); }
        }
        $frames = $xpath->evaluate("/html/body//frame");
        for ($i = 0; $i < $frames->length; $i++) {
            $frame = $frames->item($i);
            $url = make_absolute($frame->getAttribute('src'), $base);
            if ($url != $targetUrl) { get_links($url, $depth -1); }
        }
    }

    $hrefs = $xpath->evaluate("/html/body//a");
    for ($i = 0; $i < $hrefs->length; $i++) {
        $href = $hrefs->item($i);
        $url = $href->getAttribute('href');
        $absolute = make_absolute($url, $base);
        if (preg_match("@".$linkFilter."@i", parse_url($absolute, PHP_URL_PATH))) {
            array_push($urls, $absolute);
        }
    }
}

function print_links() {
    global $urls, $targetUrl, $linkFilter, $frameDepth, $viewerUrl, $showDownload, $headlessPage, $help, $actualFrameDepth;

    $urls = array_unique($urls);
    sort($urls);

    print "<title>links</title>" . "\n";
    print "<link rel='shortcut icon' href='pawn.ico' />" . "\n";
    print "<style tyle='text/css'> body { font-family: sans-serif; padding: 2em; line-height: 1.5em; } a { color: black; text-decoration: none; } </style>" . "\n";

    if ($help) {
        print("<pre>" . "\n");
        print("targetUrl = target url to scan for links" . "\n");
        print("linkFilter = filter for selecting links" . "\n");
        print("frameDepth = maximum recursive depth to scan frames" . "\n");
        print("viewerUrl = viewer url to open links" . "\n");
        print("showDownload = true|false" . "\n");
        print("headlessPage = true|false" . "\n");
        print("help = true" . "\n");
        print("\n");
        print("</pre>" . "\n");
    }

    if (!$headlessPage) {
        print "targetUrl: &nbsp; &nbsp; <b><a href='" . $targetUrl . "' target='_blank'>" . $targetUrl . "</a></b><br />" . "\n";
        print "linkFilter: &nbsp; &nbsp; <b>" . $linkFilter . "</b><br />" . "\n";
        if ($frameDepth > 0) { print "frameDepth: &nbsp; &nbsp; <b>" . $frameDepth . "</b> &nbsp; &nbsp; <span style='opacity: 0.2;'>" . $actualFrameDepth . "</span><br />" . "\n"; }
    }
    if (count($urls) > 0) {
        if (!$headlessPage) { print("<div>&nbsp;</div>" . "\n"); }
        print("<ol>" . "\n");
        for ($i = 0; $i < count($urls); $i++) {
            print("<li>");
            if ($showDownload) { print("&nbsp;&nbsp;<a href='" . $urls[$i] . "'><b>D</b></a>&nbsp;&nbsp;&nbsp;"); }
            print("<a href='" . $viewerUrl . rawurlencode($urls[$i]) . "' target='pgn4web_link_viewer'>");
            if ($showDownload) { print("<b>V</b>&nbsp;&nbsp;&nbsp;"); }
            print($urls[$i] . "</a>" . "</li>" . "\n");
        }
        print "</ol><div>&nbsp;</div>" . "\n";
    } else {
        print("<div>&nbsp;</div><ul><li><i>no links found</i></li></ul><div>&nbsp;</div>" . "\n");
    }

}

function get_param($param, $shortParam, $default) {
  if (isset($_REQUEST[$param]) && stripslashes(rawurldecode($_REQUEST[$param]))) { return stripslashes(rawurldecode($_REQUEST[$param])); }
  if (isset($_REQUEST[$shortParam]) && stripslashes(rawurldecode($_REQUEST[$shortParam]))) { return stripslashes(rawurldecode($_REQUEST[$shortParam])); }
  return $default;
}

function make_absolute($url, $base) {

    // Return base if no url
    if( ! $url) return $base;

    // Return if already absolute URL
    if(parse_url($url, PHP_URL_SCHEME) != '') return $url;

    // Urls only containing query or anchor
    if($url[0] == '#' || $url[0] == '?') return $base.$url;

    // Parse base URL and convert to local variables: $scheme, $host, $path
    extract(parse_url($base));

    // If no path, use /
    if( ! isset($path)) $path = '/';

    // Remove non-directory element from path
    $path = preg_replace('#/[^/]*$#', '', $path);

    // Destroy path if relative url points to root
    if($url[0] == '/') $path = '';

    // Dirty absolute URL
    $abs = "$host$path/$url";

    // Replace '//' or '/./' or '/foo/../' with '/'
    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
    for($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}

    // Absolute URL is ready!
    return $scheme.'://'.$abs;
}

?>
