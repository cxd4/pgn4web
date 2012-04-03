<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);

$base = get_param("baseUrl", "bu", "http://twiclive.com/silverlive.htm");

$filter = get_param("filter", "f", "live.*\.pgn$");

$html = file_get_contents($base);

$dom = new DOMDocument();
@$dom->loadHTML($html);

// grab all the on the page
$xpath = new DOMXPath($dom);
$hrefs = $xpath->evaluate("/html/body//a");

$urls = array();
for ($i = 0; $i < $hrefs->length; $i++) {
    $href = $hrefs->item($i);
    $url = $href->getAttribute('href');
    $absolute = make_absolute($url, $base);
    if (preg_match("@".$filter."@i", parse_url($absolute, PHP_URL_PATH))) {
      array_push($urls, $absolute);
    }
}
$urls = array_unique($urls);
sort($urls);

print "<title>links</title>" . "\n";
print "<link rel='shortcut icon' href='../pawn.ico' />" . "\n";
print "<style tyle='text/css'> body { font-family: sans-serif; padding: 2em; line-height: 1.5em; } a { color: black; text-decoration: none; } </style>" . "\n";

print "<b>baseUrl = " . $base . "</b><br />" . "\n";
print "<b>filter = " . $filter . "</b><br />" . "\n";

print "<ol>" . "\n";
for ($i = 0; $i < count($urls); $i++) {
    print("<li><a href='" . $urls[$i] . "'>" . $urls[$i] . "</a>" . "</li>" . "\n");
}
print "</ol>" . "\n";
print "<a href='live-grab.php'>grab</a>" . "\n";

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
