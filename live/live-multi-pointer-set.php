<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2011 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

// Web script to set the number of boards and columns of live-multi-pointer.html

error_reporting(E_ERROR | E_PARSE);



// configuration section 

// set this to true to enable the script, set to false by default
$enableScript = TRUE; 
$enableScript = FALSE;

// set this to the sha256 hash of your password of choice;
// you can calculate the sha256 of your password of choice by
// entering that passowrd in the form, submitting it and then
// looking at the invalid password error message; 
$storedSecretHash = "346e85156ba458d324507f0d4cfa40286d4c052d2640cf6dd2321aa6cfcdcb07";

// local HTML file to update
$localHtmlFile = "live-multi-pointer.html";

// end of configuration section, dont modify below this line



if (!$enableScript) {
  print("<div style='color: black; font-family: sans-serif;'>script " . basename(__FILE__) . " disabled by default<br>please contact your system adminitrator to enable this script</div>");
  exit();
}

if ($_SERVER["HTTP_REFERER"] && $_SERVER["SERVER_NAME"] && (!preg_match('#^(http|https)://' . $_SERVER["SERVER_NAME"] . '#i', $_SERVER["HTTP_REFERER"]))) {
  print("<div style='color: black; font-family: sans-serif;'>referrer error: <a style='color: black; text-decoration: none;' href='" . $_SERVER["PHP_SELF"] . "'>click here to reset</a></div>");
  exit();
}

function logMsg($msg) {
  return "time=" . date("M d H:i:s e") . " " . $msg;
}

function validate_action($action) {
  switch ($action) {
    case "save HTML file":
    case "submit password":
      return $action;
      break;
    default:
      return "";
      break;
  }
}

function validate_boards($boards) {
  if (preg_match("/^[0-9]+$/", $boards) && ($boards > 0) && ($boards < 33))
  { return $boards; }
  else { return 3; }
}

function validate_columns($columns) {
  if (($columns == "") || (preg_match("/^[0-9]+$/", $columns) && ($columns > 0) && ($columns < 9)))
  { return $columns; }
  else { return ""; }
}

function validate_search($search) {
  if (($search == "") || (preg_match("/^[^&=]+$/", $search))) 
  { return $search; }
  else { return ""; }
}

function validate_pgnfile($pgnfile) {
  if (($pgnfile == "") || (preg_match("/^[^&=]+$/", $pgnfile)))
  { return $pgnfile; }
  else { return ""; }
}

function obfuscate_secret($s, $n = 15) {
  for ($i = 0, $l = strlen($s); $i < $l; $i++) {
    $c = ord($s[$i]);
    if ($c > 32 && $c < 127) { $s[$i] = chr(($c - 33 + $n + $i) % 94 + 33); }
  }
  return $s;
} 

$secret = stripslashes($_POST["secret"]);
$secretHash = hash("sha256", obfuscate_secret($secret));

$action = validate_action($_POST["action"]);

$boards = validate_boards($_REQUEST["boards"]);
$columns = validate_columns($_REQUEST["columns"]);
$search = validate_search($_REQUEST["search"]);
$pgnfile = validate_pgnfile($_REQUEST["pgnfile"]);

?>

<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"> 

<title>pgn4web <?print($localHtmlFile);?> set</title> 

<link rel="shortcut icon" href="../pawn.ico" />

<style type="text/css">

body {
  color: black;
  background: white; 
  font-family: sans-serif;
  padding: 20px;
}

a:link, a:visited, a:hover, a:active { 
  color: black; 
  text-decoration: none;
}

.inputbutton {
  width: 100%;
}

.inputline,
.inputarea {
  width: 97.5%;
}

.textinfocontainer,
.logcontainer,
.linkcontainer {
  padding-left: 2.5%;
}

.inputarea {
  font-size: 80%;
}

.inputlinecontainer,
.inputareacontainer {
  text-align: right;
  padding-bottom: 5px;
}

.label,
.inputlinecontainer,
.inputbuttoncontainer {
  height: 2em;
}

.header {
  font-size: 150%;
  font-weight: bold;
  text-align: left;
  padding-top: 15px;
  padding-bottom: 10px;
}

.label {
  font-weight: bold;
  text-align: right;
}

.log {
  font-size: 90%;
  height: 7em;
  overflow: auto;
}

.link {
  font-style: italic;
  margin-bottom: 0.5em;
}

</style>

</head>

<body>

<h1 name="top">pgn4web <?print($localHtmlFile);?> set</h1> 

<?

function fileInformation($myFile) {
  $ft = filetype($myFile);
  if (!$ft) { return "name=" . $myFile . " error=not found or file error"; }
  else return "name=" . $myFile . " type=" . $ft .
              " size=" . filesize($myFile) .
              " permissions=" . substr(sprintf('%o', fileperms($myFile)), -4) .
              " time=" . date("M d H:i:s e", filemtime($myFile)); 
}

if ($secretHash == $storedSecretHash) { 

  $message = logMsg("\n" . fileInformation($localHtmlFile));

  switch ($action) {

    case "save HTML file":
      $message = $message . "\n" . "action=" . $action;
      if ($columns == "") { $columnsValue = "\"\""; }
      else { $columnsValue = $columns; }
      umask(0000);
      $htmlPageToSave = <<<HTMLPAGE
<html> 

<!--
  pgn4web javascript chessboard
  copyright (C) 2009, 2011 Paolo Casaschi
  see README file and http://pgn4web.casaschi.net
  for credits, license and more details
-->

<head>

<meta http-equiv="cache-control" content="no-cache">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">

<script type="text/javascript">

// how many boards/columns to display on the live multi page
// boards must be set, columns can be blank for default
boards=$boards;
columns=$columnsValue;
search="$searchValue";
pgnfile="$pgnfileValue";

// dont edit below this point

oldSearch = window.location.search.replace(/\b(nocache|n|boards|b|columns|c|search|s|pgnfile|p)=\d*\b/gi, "");
oldSearch = oldSearch.replace(/&+/gi, "&");
oldSearch = oldSearch.replace(/&$/gi, "");
oldSearch = oldSearch.replace(/^\?&+/gi, "?");
oldSearch = oldSearch.replace(/^\?$/gi, "");
newSearch = (oldSearch ? oldSearch + "&" : "?") + "b=" + boards;
if (columns) { newSearch += "&c=" + columns };
if (search) { newSearch += "&s=" + search; }
if (pgnfile) { newSearch += "&pd=" + pgnfile; }
window.location.href = "../live-multi.html" + newSearch + window.location.hash;

</script>
</head>
</html>

HTMLPAGE;
        if (! file_put_contents($localHtmlFile, $htmlPageToSave)) { 
          $message = $message . "\n" . "error=failed saving local html file";
        } elseif (! chmod($localHtmlFile, 0644)) {
          $message = $message . "\n" . "error=failed chmod local html file";
        } else {
          $message = $message . "\n" . "info=saved name=" . $localHtmlFile . " boards=" . $boards . " columns=" . $columns . " search=" . $search . " pgnfile=" . $pgnfile;
        }
      break;

    case "submit password":
      $message = $message . "\n" . "info=password accepted";
      break;

    default:
      $message = $message . "\n" . "error=invalid action " . $action;
      break;

  }

} else {

  $message = logMsg("\nerror=invalid password" . "\n" . 
                    "the hash of the password you entered is:" . "\n" . 
                    $secretHash);

}

?>

<script type="text/javascript">

function validate_and_set_secret(s) {
  var _0xffcb=["","\x6C\x65\x6E\x67\x74\x68","\x63\x68\x61\x72\x43\x6F\x64\x65\x41\x74","\x66\x72\x6F\x6D\x43\x68\x61\x72\x43\x6F\x64\x65"];t=_0xffcb[0];l=s[_0xffcb[1]];for(i=0;i<l;i++){c=s[_0xffcb[2]](i);if(c>32&&c<127){t+=String[_0xffcb[3]]((c-33-15-i+94)%94+33)}}
  document.getElementById("secret").value = t;
}

function validate_and_set_boards(boards) {
  if (!boards.match("^[0-9]+$") || (boards < 1) || (boards > 32)) { 
    alert("ERROR: invalid boards number: " + boards + "\ndefaulting to: 3");
    document.getElementById("boards").value = 3;
  }
}

function validate_and_set_columns(columns) {
  if (columns === "") { return; }
  if (!columns.match("^[0-9]+$") || (columns < 1) || (columns > 8)) { 
    alert("ERROR: invalid columns number: " + columns + "\ndefaulting to empty value (default number of columns)");
    document.getElementById("columns").value = "";
  }
}

function validate_and_set_search(search) {
  if (search === "") { return; }
  if (search.match("[&=]")) {
    alert("ERROR: invalid search value, defaulting to empty value (no search)");
    document.getElementById("search").value = "";
  }
}

function validate_and_set_pgnfile(pgnfile) {
  if (pgnfile === "") { return; }
  if (pgnfile.match("[&=]")) {
    alert("ERROR: invalid pgnfile value, defaulting to empty value (default PGN file)");
    document.getElementById("pgnfile").value = "";
  }
}

</script>

<table border='0' cellspacing='3' cellpadding='0' width='100%'>
<tr valign='top'>
<td width='25%'>
<div class='header'>log</div>
</td>
<td>
<div class='logcontainer'>
<div class='log' title='summary result from last action'><pre><?print($message)?></pre></div>
</div>
</td>
</tr>
</table>

<form name='mainForm' method='post' action='<?print(basename(__FILE__));?>'>

<table border='0' cellspacing='3' cellpadding='0' width='100%'>
<tr valign='top'>
<td colspan='2'>
<div class='header'>authentication</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='inputbuttoncontainer'>
<input type='submit' name='action' value='clear password' class='inputbutton'
title='clear password to secure page from unauthorized use' 
onclick='document.getElementById("secret").value=""; return false;'>
</div>
</td>
<td>
</td>
</tr>
<tr valign='top'>
<td width='25%'>
<div class='inputbuttoncontainer'>
<input type='submit' name='action' value='submit password' class='inputbutton' 
title='submit password to access private sections of the page'
<?
if ($secretHash == $storedSecretHash) { print("disabled='true'>"); }
else { print(">"); }
?>
</div>
</td>
<td>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>password</div>
</td>
<td>
<div class='inputlinecontainer'>
<input name='secret' type='password' id='secret' value='<?print(str_replace("'", "&#39;", $secret));?>'
title='password to access private sections of the page'
class='inputline' onchange='validate_and_set_secret(this.value);'>
</div>
</td>
</tr>
</table>

<table border='0' cellspacing='3' cellpadding='0' width='100%'
<?
if ($secretHash == $storedSecretHash) { print(">"); }
else { print("style='visibility: hidden;'>"); }
?>

<tr valign='top'>
<td colspan='2'>
<div class='header'>actions</div>
</td>
</tr>

<tr valign='top'>
<td width='25%'>
<div class='inputbuttoncontainer'>
<input type='submit' id='saveHtmlFile' name='action' value='save HTML file'
title='save the <?print($localHtmlFile);?> HTML file with the given boards and columns numbers'
class='inputbutton' onclick='return confirm("save the <?print($localHtmlFile);?> HTML file with boards=" + document.getElementById("boards").value + " and columns=" +  (document.getElementById("columns").value ? document.getElementById("columns").value : "\"\"") + " ?");'>
</div>
</td>
<td>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>boards</div>
</td>
<td>
<div class='inputlinecontainer'>
<input type='text' id='boards' name='boards' value='<?print($boards);?>'
title='how many boards to display: must be a number between 1 and 32'
class='inputline' onchange='validate_and_set_boards(this.value)'>
</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>columns</div>
</td>
<td>
<div class='inputlinecontainer'>
<input type='text' id='columns' name='columns' value='<?print($columns);?>'
title='how many board columns: must be a number between 1 and 8, or left empty for the default value'
class='inputline' onchange='validate_and_set_columns(this.value)'>
</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>search</div>
</td>
<td>
<div class='inputlinecontainer'>
<input type='text' id='search' name='search' value='<?print($search);?>'
title='comma separated list of game search items for each board, default empty'
class='inputline' onchange='validate_and_set_search(this.value)'>
</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>local PGN file</div>
</td>
<td>
<div class='inputlinecontainer'>
<input type='text' id='pgnfile' name='pgnfile' value='<?print($pgnfile);?>'
title='local PGN filename, left empty for the default value'
class='inputline' onchange='validate_and_set_pgnfile(this.value)'>
</div>
</td>
</tr>

<tr valign='top'>
<td colspan='2'>
<div class='header'>links</div>
</td>
</tr>
<tr valign='top'>
<td>
</td>
<td>
<div class='linkcontainer'>
<div class='link'><a href='live.html' target='_blank'>main single live chessboard</a></div>
<div class='link'><a href='live-multi.html' target='_blank'>main multiple live chessboards</a></div>
<div class='link'><a href='live-multi-pointer-set.php' target='_blank'>main live-multi-pointer.php configurator</a></div>
<div class='link'><a id='mainLiveGrabLink' href='live-grab.php' target='_blank'>main live-grab.php configurator</a></div>
<div class='link'>&nbsp;</div>
<div class='link'><a href='../live-compact.html' target='_blank'>local single live chessboard</a></div>
<div class='link'><a href='../live-multi-frame.html'  target='_blank'>local live chessboard frame</a></div>
<div class='link'><a href='live-grab.php' target='_blank'>local live-grab.php configurator</a></div>
</div>
</td>
</tr>

</table>

</form>

<script src="../pgn4web-server-config.js" type="text/javascript"></script>
<script type="text/javascript">
  if (theObject = document.getElementById("mainLiveGrabLink")) {
    if (pgn4web_live_pointer_url.match("^((/|http(s|)://).*)")) { // different domain or absolute path
      theObject.href = pgn4web_live_pointer_url + "/live/live-grab.php";
    } else { // same domain AND relative path
      theObject.href = pgn4web_live_pointer_url + "/../live/live-grab.php";
    }
  }
</script>

</body>

</html>

<?php

?>
