<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);

$targetUrl = get_param("targetUrl", "tu", "");
$linkFilter = get_param("linkFilter", "lf", ".+\.pgn$");
$urls = array();
get_links($targetUrl, 5);
print_links();

function get_links($targetUrl, $depth) {
    global $urls, $linkFilter;

    if (! $targetUrl) { return; }

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
            $url = $frame->getAttribute('src');
            get_links(make_absolute($url, $base), $depth -1);
        }
        $frames = $xpath->evaluate("/html/body//frame");
        for ($i = 0; $i < $frames->length; $i++) {
            $frame = $frames->item($i);
            $url = $frame->getAttribute('src');
            get_links(make_absolute($url, $base), $depth -1);
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
    global $urls, $targetUrl, $linkFilter;

    $urls = array_unique($urls);
    sort($urls);

    print "<title>links</title>" . "\n";
    print "<link rel='shortcut icon' href='../pawn.ico' />" . "\n";
    print "<style tyle='text/css'> body { font-family: sans-serif; padding: 2em; line-height: 1.5em; } a { color: black; text-decoration: none; } </style>" . "\n";

    print "targetUrl: <b><a href='" . $targetUrl . "' target='_blank'>" . $targetUrl . "</a></b><br />" . "\n";
    print "linkFilter: <b>" . $linkFilter . "</b><br />" . "\n";

    print "<ol>" . "\n";
    for ($i = 0; $i < count($urls); $i++) {
        print("<li><a href='" . $urls[$i] . "'>" . $urls[$i] . "</a>" . "</li>" . "\n");
    }
    print "</ol>" . "\n";
    print "<a href='live-grab.php' target='_blank'>grab</a>" . "\n";
}

function get_param($param, $shortParam, $default) {
    $out = stripslashes(rawurldecode($_REQUEST[$param]));
    if ($out != "") { return $out; }
    $out = stripslashes(rawurldecode($_REQUEST[$shortParam]));
    if ($out != "") { return $out; }
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
