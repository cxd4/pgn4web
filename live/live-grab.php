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

function validate_lastPgnUrlModification($lastPgnUrlModification) {
  if ($lastPgnUrlModification) { return $lastPgnUrlModification; }
  else { return "Thu, 01 Jan 1970 00:00:00 GMT"; }
}

function validate_pgnText($pgnText) {
  return $pgnText;
}

$secret = $_REQUEST["secret"];
$secretHash = hash("sha256", str_rot13($secret));

$localPgnFile = validate_localPgnFile($localPgnFile);

$action = validate_action($_REQUEST["action"]);

$pgnUrl = validate_pgnUrl($_REQUEST["pgnUrl"]);
$refreshSeconds = validate_refreshSeconds($_REQUEST["refreshSeconds"]);
$refreshSteps = validate_refreshSteps($_REQUEST["refreshSteps"]);
$lastPgnUrlModification = validate_lastPgnUrlModification($_REQUEST["lastPgnUrlModification"]);

$pgnText = validate_pgnText($_REQUEST["pgnText"]);

?>

<!--<?echo $secretHash?>-->

<script type='text/javascript'>grabTimeout = null;</script>

<form name='mainForm' method='post' action='<?echo basename(__FILE__)?>'>
<hr>
Password
<input name='secret' type='password' id='secret' value='<?echo $secret?>'
onchange='validate_and_set_secret(this.value);'>
<input type='submit' name='action' value='clear password'
onclick='document.getElementById("secret").value=""; return false;'>
<hr>
local PGN file 
<input type='text' id='localPgnFile' name='localPgnFile' value='<?print($localPgnFile)?>' 
onchange='validate_and_set_localPgnFile(this.value);'>
<hr>
<input type='submit' id='submitPgnUrl' name='action' value='grab PGN URL'
onclick='return askUserToGrabPgnUrl ? confirm("grab PGN URL as local file") : true;'>
<input type='submit' id='stopGrabbingPgnUrl' name='action' value='stop grabbing PGN URL'
onclick='return disableStopGrabButton();' disabled='true'>
<br/>PGN URL
<input type='text' name='pgnUrl' value='<?echo $pgnUrl?>'>
<input type='hidden' name='lastPgnUrlModification' value='<?echo $lastPgnUrlModification?>'>
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

<?

function deleteFile($myFile) {
  if (!is_file($myFile)) { return "file " . $myFile . " not found"; }
  if (unlink($myFile)) { return "file " . $myFile . " deleted"; }
  else { return "error deleting file " . $myFile; };
}

if ($secretHash == $storedSecretHash) { 

  switch ($action) {

    case "grab PGN URL":
      $message = logMsg("<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile . 
                 "<br/>pgnUrl=" . $pgnUrl . "<br/>refreshSeconds=" . $refreshSeconds . 
                 "<br/>refreshSteps=" . $refreshSteps);
      if (--$refreshSteps > 0) {
        print("<script type='text/javascript'>" . 
              "if (grabTimeout) { clearTimeout(grabTimeout); } " .
              "grabTimeout = setTimeout('grabPgnUrl()'," . (1000 * $refreshSeconds) . "); " .
              "if (grabTimeout) { document.getElementById('stopGrabbingPgnUrl').disabled = false; } " .
              "</script>");
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
            $message = $message . "<br>no new PGN content read from URL" .
                       " (timestamp " . $newLastPgnUrlModification . ")";
          } else {
            $pgnData = file_get_contents($pgnUrl, false);
            if (! $pgnData) { 
              $message = $message . "<br>failed reading PGN URL";
            } else {
              if (! file_put_contents($localPgnFile . "_tmp", $pgnData)) {
                $message = $message . "<br/>" . "failed saving updated " . $localPgnFile . "_tmp";
              } else {
                if (! copy($localPgnFile . "_tmp", $localPgnFile)) {
                  $message = $message . "<br/>" . "failed copying new data to " . $localPgnFile;
                } else {
                  $message = $message . "<br/>" . "updated " . $localPgnFile;
                  if ($newLastPgnUrlModification != "") { 
                    $message = $message . " (timestamp " . $newLastPgnUrlModification . ")";
                    $lastPgnUrlModification = $newLastPgnUrlModification; 
                  }
                }
              }
            }
          }
        }
      }
      break;

    case "save PGN text":
      $message = logMsg("<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile .
                 "<br/>pgnText=" . $pgnText); 
      if (file_put_contents($localPgnFile, $pgnText)) { 
        $message = $message . "<br/>file " . $localPgnFile . " updated";
      } else {
        $message = $message . "<br/>failed updating file " . $localPgnFile;
      }
      $message = $message . "<br/>" . deleteFile($localPgnFile . "_tmp");
      $message = $message . "<br/>" . deleteFile($localPgnFile . "_log");
      break;

    case "delete local PGN file":
      $message = logMsg("<br/>action=" . $action . "<br/>localPgnFile=" . $localPgnFile);
      $message = $message . "<br/>" . deleteFile($localPgnFile);
      $message = $message . "<br/>" . deleteFile($localPgnFile . "_tmp");
      $message = $message . "<br/>" . deleteFile($localPgnFile . "_log");
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

askUserToGrabPgnUrl = true;
function grabPgnUrl() {
  askUserToGrabPgnUrl = false;
  document.getElementById('submitPgnUrl').click();
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

<?echo $message?>

<?php

?>
