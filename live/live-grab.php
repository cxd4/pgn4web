<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2010 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);



// configuration section 

// set this to true to enable the script, set to false by default
$enableScript = true; 
$enableScript = false;

// set this to the sha256 hash of your password of choice;
// you can calculate the sha256 of your password of choice by
// entering that passowrd in the form, submitting it and then
// looking at the invalid password error message; 
$storedSecretHash = "346e85156ba458d324507f0d4cfa40286d4c052d2640cf6dd2321aa6cfcdcb07";

// end of configuration section, dont modify below this line



if (!$enableScript) {
  print("script " . basename(__FILE__) . " disabled by default<br>please contact your system adminitrator to enable this script");
  exit();
}

function curPageURL() {
 $pageURL = 'http';
 if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
 $pageURL .= "://";
 if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 } else {
  $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 }
 return $pageURL;
}

function logMsg($msg) {
  return date("M d H:i:s e") . " " . $msg;
}

function logToFile($msg, $append) {
  global $localPgnLogFile, $refreshSteps, $refreshSeconds, $localPgnFile, $pgnUrl;

  $head = date("M d H:i:s") . " " . $_SERVER["HTTP_HOST"] . " " . basename(__FILE__) . " [?]: ";
  $msg = $head . $msg . "\n";
  if ($append) {
    file_put_contents($localPgnLogFile, $msg, FILE_APPEND);
  } else {
    $msg = $head . "refreshSteps: " . $refreshSteps . "\n" . $msg;
    $msg = $head . "refreshSeconds: " . $refreshSeconds . "\n" . $msg;
    $msg = $head . "localPgnFile: " . $localPgnFile . "\n" . $msg;
    $msg = $head . "remoteUrl: " . $pgnUrl . "\n" . $msg;
    $msg = $head . "start\n" . $msg;
    file_put_contents($localPgnLogFile, $msg);
  }
}

function validate_action($action) {
  switch ($action) {
    case "grab PGN URL overwrite":
    case "grab PGN URL":
    case "save PGN text":
    case "delete local PGN file":
    case "submit password":
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

function validate_lastPgnUrlModification($lastPgnUrlModification) {
  if ($lastPgnUrlModification) { return $lastPgnUrlModification; }
  else { return "Thu, 01 Jan 1970 00:00:00 GMT"; }
}

function validate_pgnText($pgnText) {
  return $pgnText;
}

$secret = stripslashes($_POST["secret"]);
$secretHash = hash("sha256", str_rot13($secret));

$localPgnFile = validate_localPgnFile($localPgnFile);
$localPgnTmpFile = $localPgnFile . ".tmp";
$localPgnLogFile = $localPgnFile . ".log";

$action = validate_action($_POST["action"]);

$pgnUrl = validate_pgnUrl($_POST["pgnUrl"]);
$refreshSeconds = validate_refreshSeconds($_POST["refreshSeconds"]);
$refreshSteps = validate_refreshSteps($_POST["refreshSteps"]);
$lastPgnUrlModification = validate_lastPgnUrlModification($_POST["lastPgnUrlModification"]);

$pgnText = validate_pgnText(stripslashes($_POST["pgnText"]));

?>

<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"> 

<title>pgn4web live games grab</title> 

<style type="text/css">

body {
  color: black;
  background: white; 
  font-family: sans-serif;
  line-height: 1.3em;
  padding: 20px;
}

a:link, a:visited, a:hover, a:active { 
  color: black; 
  text-decoration: none;
}

.inputarea {
  font-size: 80%;
  width: 97.5%;
}

.inputareacontainer {
  text-align: right;
  padding-bottom: 5px;
}

.inputbutton {
  width: 100%;
}

.inputline {
  width: 97.5%;
}

.inputbuttoncontainer {
  height: 2em;
}

.inputlinecontainer {
  text-align: right;
  height: 2em;
}

.header {
  font-size: 150%;
  font-weight: bold;
  text-align: left;
  padding-top: 10px;
  padding-bottom: 5px;
}

.label {
  font-weight: bold;
  text-align: right;
  height: 2em;
}

.logcontainer {
  padding-left: 2.5%;
}

.log {
  height: 18em;
  overflow: auto;
  color: black;
  font-size: 75%
}

</style>

</head>

<body>

<h1 name="top" style="font-family: sans-serif; color: black;">pgn4web live games grab</h1> 

<script type='text/javascript'>grabTimeout = null;</script>

<?

function deleteFile($myFile) {
  if (!is_file($myFile)) { return "file " . $myFile . " not found or not a regular file"; }
  if (unlink($myFile)) { return "file " . $myFile . " deleted"; }
  else { return "error deleting file " . $myFile; };
}

function checkFileExisting($localPgnFile, $localPgnTmpFile, $localPgnLogFile) {
  if (file_exists($localPgnFile)) {
    return "<br/>" . $localPgnFile . " exists: aborting action";
  } elseif (file_exists($localPgnTmpFile)) {
    return "<br/>" . $localPgnTmpFile . " exists: aborting action";
  } elseif (file_exists($localPgnLogFile)) {
    return "<br/>" . $localPgnLogFile . " exists: aborting action";
  } else {
    return "";
  }
}

function fileInformation($myFile) {
  $ft = filetype($myFile);
  if (!$ft) { return $myFile . " not found or file error"; }
  else return $myFile . " type=" . $ft .
              " size=" . filesize($myFile) .
              " permissions=" . substr(sprintf('%o', fileperms($myFile)), -4) .
              " time=" . date("M d H:i:s e", filemtime($myFile)); 
}

if ($secretHash == $storedSecretHash) { 

  $overwrite = false;
  $message = logMsg("<br/>" . fileInformation(".") .
                    "<br/>" . fileInformation($localPgnFile) . 
                    "<br/>" . fileInformation($localPgnTmpFile) .
                    "<br/>" . fileInformation($localPgnLogFile));
  switch ($action) {

    case "grab PGN URL overwrite":
      $overwrite = true;
    case "grab PGN URL":
      $message = $message . "<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile . 
                 "<br/>pgnUrl=" . $pgnUrl . "<br/>refreshSeconds=" . $refreshSeconds . 
                 "<br/>refreshSteps=" . $refreshSteps;
      $errorMessage = checkFileExisting($localPgnFile, $localPgnTmpFile, $localPgnLogFile);
      if (!$overwrite && $errorMessage) {
        $message = $message . $errorMessage;
      } else {
        if (--$refreshSteps < 0) {
          $message = $message . "<br/>error: invalid refresh steps";
        } else {
          $logOk = false;
          $newLastPgnUrlModification = "";
          $pgnHeaders = get_headers($pgnUrl, 1); 
          if (! $pgnHeaders) { 
            $message = $message . "<br/>" . "failed getting PGN URL headers";
          } else {
            if (! $pgnHeaders['Last-Modified']) { 
              $message = $message . "<br/>" . "failed getting PGN URL last modified header"; 
            } else {
              $newLastPgnUrlModification = $pgnHeaders['Last-Modified'];
            }
            if ($newLastPgnUrlModification == $lastPgnUrlModification) {
              $message = $message . "<br/>no new PGN content read from URL" .
                         "<br/>timestamp=" . $newLastPgnUrlModification;
            } else {
              $pgnData = file_get_contents($pgnUrl, NULL, NULL, 0, 1048576);
              if (! $pgnData) { 
                $message = $message . "<br/>failed reading PGN URL";
              } else {
                if (! file_put_contents($localPgnTmpFile, $pgnData)) {
                  $message = $message . "<br/>" . "failed saving updated " . $localPgnTmpFile;
                } else {
                  if (! copy($localPgnTmpFile, $localPgnFile)) {
                    $message = $message . "<br/>" . "failed copying new data to " . $localPgnFile;
                  } else {
                    $message = $message . "<br/>" . "updated " . $localPgnFile;
                    if ($newLastPgnUrlModification != "") { 
                      $message = $message . "<br/>old timestamp=" . $lastPgnUrlModification;
                      $message = $message . "<br/>new timestamp=" . $newLastPgnUrlModification;
                      $lastPgnUrlModification = $newLastPgnUrlModification; 
                    }
                    $logOk = true;
                  }
                }
              }
            }
          }
          if ($logOk) { logToFile("step 1 of " . $refreshSteps . ", new PGN data found", $overwrite); }
          else { logToFile("step 1 of " . $refreshSteps . ", no new data", $overwrite); }
          if ($refreshSteps == 0) {
            $message = $message . "<br/>timer not restarted";
          } else {
            $message = $message . "<br/>timer restarted";
            print("<script type='text/javascript'>" . 
                  "if (grabTimeout) { clearTimeout(grabTimeout); } " .
                  "grabTimeout = setTimeout('grabPgnUrl()'," . (1000 * $refreshSeconds) . "); " .
                  "</script>");
          }
        } 
      }
      break;

    case "save PGN text":
      $message = $message . "<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile .
                 "<br/>pgnText=<pre>" . $pgnText . "</pre>";
      $errorMessage = checkFileExisting($localPgnFile, $localPgnTmpFile, $localPgnLogFile);
      if (!$overwrite && $errorMessage) {
        $message = $message . $errorMessage;
      } else {
        if ($pgnText == "") {
          $pgnTextToSave = $pgnText . "\n";
        } elseif (! preg_match('/\[\s*(\w+)\s*"([^"]*)"\s*\]/', $pgnText)) {
          $pgnTextToSave = "[x\"\"]\n" . $pgnText . "\n";
        } else {
          $pgnTextToSave = $pgnText . "\n";
        }
        if (file_put_contents($localPgnFile, $pgnTextToSave)) { 
          $message = $message . "<br/>file " . $localPgnFile . " updated";
        } else {
          $message = $message . "<br/>failed updating file " . $localPgnFile;
        }
      }
      $lastPgnUrlModification = validate_lastPgnUrlModification();
      break;

    case "delete local PGN file":
      $message = $message . "<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile;
      $message = $message . "<br/>" . deleteFile($localPgnFile);
      $message = $message . "<br/>" . deleteFile($localPgnTmpFile);
      $message = $message . "<br/>" . deleteFile($localPgnLogFile);
      $lastPgnUrlModification = validate_lastPgnUrlModification();
      break;

    case "submit password":
      $message = $message . "<br/>password accepted";
      break;

    default:
      $message = $message . "<br/>invalid action=" . $action;
      break;

  }

} else {

  $message = logMsg("<br/>invalid password<br/>the sha256 hash of the password you entered is:<br/>" . $secretHash);

}

?>

<script type="text/javascript">

function validate_and_set_secret(secret) {
  document.getElementById("secret").value = secret.replace(/[a-zA-Z]/g, function(c) {
    return String.fromCharCode((c <= "Z" ? 90 : 122) >= (c = c.charCodeAt(0) + 13) ? c : c - 26);
  });
};

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

function grabPgnUrl() {
  document.getElementById('submitPgnUrlOverwrite').click();
}

function disableStopGrabButton() {
  document.getElementById('stopGrabbingPgnUrl').disabled = true;
  if (grabTimeout) { 
    clearTimeout(grabTimeout); 
    grabTimeout = null; 
  } 
  return false;
}

</script>

<table cellspacing='3' cellpadding='0' width='100%'>
<tr valign='top'>
<td colspan='2'>
<div class='header'>log</div>
</td>
</tr>
<tr valign='top'>
<td width='25%'>
</td>
<td>
<div class='logcontainer'>
<div class='log'><?echo $message?></div>
</div>
</td>
</tr>
</table>

<form name='mainForm' method='post' action='<?echo basename(__FILE__)?>'>

<table cellspacing='3' cellpadding='0' width='100%'>
<tr valign='top'>
<td colspan='2'>
<div class='header'>authentication</div>
</td>
</tr>
<tr valign='top'>
<td width='25%'>
<div class='inputbuttoncontainer'>
<input type='submit' name='action' value='submit password' class='inputbutton' 
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
<div class='inputbuttoncontainer'>
<input type='submit' name='action' value='clear password' class='inputbutton' 
onclick='document.getElementById("secret").value=""; return false;'>
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
<input name='secret' type='password' id='secret' value='<?echo $secret?>'
class='inputline' onchange='validate_and_set_secret(this.value);'>
</div>
</td>
</tr>
</table>

<table cellspacing='3' cellpadding='0' width='100%'
<?
if ($secretHash == $storedSecretHash) { print(">"); }
else { print("style='visibility: hidden;'>"); }
?>
<tr valign='top'>
<td colspan='2'>
<div class='header'>local files</div>
</td>
</tr>
<tr valign='top'>
<td width='25%'>
<div class='label'>local PGN file</div>
</td>
<td>
<div class='inputlinecontainer'>
<input type='text' id='localPgnFile' name='localPgnFile' value='<?print($localPgnFile)?>' 
class='inputline' onchange='validate_and_set_localPgnFile(this.value);'>
</div>
</td>
</tr>

<tr valign='top'>
<td colspan='2'>
<div class='header'>actions</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='inputbuttoncontainer'>
<input type='submit' id='submitPgnUrl' name='action' value='grab PGN URL'
class='inputbutton' onclick='return confirm("grab PGN URL as local file");'>
<input type='submit' id='submitPgnUrlOverwrite' name='action' value='grab PGN URL overwrite'
style='display: none;'>
</div>
</td>
<td>
</td>
</tr>
<tr valign='top'>
<td>
<div class='inputbuttoncontainer'>
<input type='submit' id='stopGrabbingPgnUrl' name='action' value='stop grabbing PGN URL'
class='inputbutton' onclick='return disableStopGrabButton();' disabled='true'>
</div>
</td>
<td>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>PGN URL</div>
</td>
<td>
<div class='inputlinecontainer'>
<input type='text' name='pgnUrl' value='<?echo $pgnUrl?>'
class='inputline'>
<input type='hidden' name='lastPgnUrlModification' value='<?echo $lastPgnUrlModification?>'>
</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>refresh seconds</div>
</td>
<td>
<div class='inputlinecontainer'>
<input type='text' id='refreshSeconds' name='refreshSeconds' value='<?echo $refreshSeconds?>'
class='inputline' onchange='validate_and_set_refreshSeconds(this.value)'>
</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>refresh steps</div>
</td>
<td>
<div class='inputlinecontainer'>
<input type='text' id='refreshSteps' name='refreshSteps' value='<?echo $refreshSteps?>'
class='inputline' onchange='validate_and_set_refreshSteps(this.value)'>
</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='inputbuttoncontainer'>
<input type='submit' name='action' value='save PGN text'
class='inputbutton' onclick='return confirm("save PGN text as local file?");'>
</div>
</td>
<td>
</td>
</tr>
<tr valign='top'>
<td>
<div class='label'>PGN text</div>
</td>
<td>
<div class='inputareacontainer'>
<textarea name='pgnText' rows='4' class='inputarea'><?echo $pgnText?></textarea>
</div>
</td>
</tr>
<tr valign='top'>
<td>
<div class='inputbuttoncontainer'>
<input type='submit' name='action' value='delete local PGN file'
class='inputbutton' onclick='return confirm("deleting local PGN file?");'>
</div>
</td>
<td>
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
<div class='logcontainer'>
<a href='../live-compact.html?pd=<?print(str_replace(basename(__FILE__), $localPgnFile, curPageURL()))?>' 
target='_blank'>chess live broadcaset with single compact chessboard</a>
<br/><br/>
<a href='../live-multi.html?b=2&c=2&pd=<?print(str_replace(basename(__FILE__), $localPgnFile, curPageURL()))?>' 
target='_blank'>chess live broadcast with multiple chessboards</a>
</div>
</td>
</tr>

</table>

</form>

<script type="text/javascript">
if (grabTimeout) { document.getElementById('stopGrabbingPgnUrl').disabled = false; }
</script>

</body>

</html>

<?php

?>
