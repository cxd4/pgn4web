<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2010 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);

$storedSecretHash = "346e85156ba458d324507f0d4cfa40286d4c052d2640cf6dd2321aa6cfcdcb07";

function logMsg($msg) {
  return date("M d H:i:s e") . " " . $msg;
}

function validate_action($action) {
  switch ($action) {
    case "grab PGN URL":
    case "save PGN text":
    case "delete local PGN file":
      return $action;
      break;
    default:
      return "";
      break;
  }
}

function validate_localPgnFile($localPgnFile) {
  if (preg_match("/^[A-Za-z0-9_\-]+\.pgn$/", $localPgnFile)) { return $localPgnFile; }
  else { return "live.pgn"; }
}

function validate_pgnUrl($pgnUrl) {
  return $pgnUrl;
}

function validate_refreshSeconds($refreshSeconds) {
  if (preg_match("/^[0-9]+$/", $refreshSeconds) && ($refreshSeconds > 9) && ($refreshSeconds < 3601)) { return $refreshSeconds; }
  else { return 49; }
}

function validate_refreshSteps($refreshSteps) {
  if (preg_match("/^[0-9]+$/", $refreshSteps)) { return $refreshSteps; }
  else { return 1000; }
}

function validate_pgnText($pgnText) {
  return $pgnText;
}

$secret = $_REQUEST["secret"];
$secretHash = hash("sha256", $secret);

$localPgnFile = validate_localPgnFile($localPgnFile);

$action = validate_action($_REQUEST["action"]);

$pgnUrl = validate_pgnUrl($_REQUEST["pgnUrl"]);
$refreshSeconds = validate_refreshSeconds($_REQUEST["refreshSeconds"]);
$refreshSteps = validate_refreshSteps($_REQUEST["refreshSteps"]);

$pgnText = validate_pgnText($_REQUEST["pgnText"]);

?>

<!--
<?echo $secretHash?>
-->

<?

if ($secretHash == $storedSecretHash) { 

  switch ($action) {

    case "grab PGN URL":
      $message = logMsg("<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile . 
                 "<br/>pgnUrl=" . $pgnUrl . "<br/>refreshSeconds=" . $refreshSeconds . 
                 "<br/>refreshSteps=" . $refreshSteps);
      if (--$refreshSteps > 0) {
        print("<script type='text/javascript'>setTimeout('grabPgnUrl()'," . (1000 * $refreshSeconds) . ");</script>");
      }
      break;

    case "save PGN text":
      $message = logMsg("<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile .
                 "<br/>pgnText=" . $pgnText); 
      break;

    case "delete local PGN file":
      $message = logMsg("<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile);
      break;

    default:
      $message = logMsg("<br/>invalid action=" . $action);
      break;

  }

} else {

  $message = logMsg("<br/>invalid password");
}

?>

<script type="text/javascript">

function validate_and_set_localPgnFile(localPgnFile) {
  if (!localPgnFile.match("^[A-Za-z0-9_\-]+\.pgn$")) { 
    alert("ERROR: invalid local PGN file: " + localPgnFile + "\ndefaulting to: live.pgn");
    document.getElementById("localPgnFile").value = "live.pgn";
  }
}

function validate_and_set_refreshSeconds(refreshSeconds) {
  if (!refreshSeconds.match("^[0-9]+$") || (refreshSeconds < 10) || (refreshSeconds > 3600)) { 
    alert("ERROR: invalid refresh seconds: " + refreshSeconds + "\ndefaulting to: 49");
    document.getElementById("refreshSeconds").value = 49;
  }
}

function validate_and_set_refreshSteps(refreshSteps) {
  if (!refreshSteps.match("^[0-9]+$")) { 
    alert("ERROR: invalid refresh steps: " + refreshSteps + "\ndefaulting to: 1000");
    document.getElementById("refreshSteps").value = 1000;
  }
}

askUserToGrabPgnUrl = true;
function grabPgnUrl() {
  askUserToGrabPgnUrl = false;
  document.getElementById('submitPgnUrl').click();
}

</script>

<form name='mainForm' method='post' action='<?echo basename(__FILE__)?>'>
<hr>
Password
<input name='secret' type='password' id='secret' value='<?echo $secret?>'>
<input type='submit' name='action' value='clear password'
onclick='document.getElementById("secret").value=""; return false;'>
<hr>
local PGN file 
<input type='text' id='localPgnFile' name='localPgnFile' value='<?print($localPgnFile)?>' 
onchange='validate_and_set_localPgnFile(this.value);'>
<hr>
<input type='submit' id='submitPgnUrl' name='action' value='grab PGN URL'
onclick='return askUserToGrabPgnUrl ? confirm("grab PGN URL as local file") : true;'>
<br/>PGN URL
<input type='text' name='pgnUrl' value='<?echo $pgnUrl?>'>
<br/>refresh seconds
<input type='text' id='refreshSeconds' name='refreshSeconds' value='<?echo $refreshSeconds?>'
onchange='validate_and_set_refreshSeconds(this.value)'>
<br/>refresh steps
<input type='text' id='refreshSteps' name='refreshSteps' value='<?echo $refreshSteps?>'
onchange='validate_and_set_refreshSteps(this.value)'>
<hr>
<input type='submit' name='action' value='save PGN text'
onclick='return confirm("save PGN text as local file?");'>
<br/>PGN text
<textarea name='pgnText' rows='4'><?echo $pgnText?></textarea>
<hr>
<input type='submit' name='action' value='delete local PGN file' 
onclick='return confirm("deleting local PGN file?");'>
<hr>
</form>

<?echo $message?>

<a href="javascript:grabPgnUrl();">test</a>
<?php

?>
