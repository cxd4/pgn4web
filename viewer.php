<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

// error_reporting(E_ERROR | E_PARSE);

$tmpDir = "viewer";

get_pgn();
print_header();
print_form();
check_tmpDir();
print_chessboard();
print_footer();


function get_pgn() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  $fileUploadLimit = "4M";

  if ($pgnText = $_REQUEST["pgnText"]) {
    $pgnStatus = "PGN from direct user input";
    $pgnBoxText = $pgnText;
    return TRUE;
  } else if ($pgnUrl = $_REQUEST["pgnUrl"]) {
    $pgnStatus = "PGN from URL <a href='" . $pgnUrl . "'>" . $pgnUrl . "</a>";
    $isPgn = preg_match("/\.pgn$/i",$pgnUrl);
    $isZip = preg_match("/\.zip$/i",$pgnUrl);
    if ($isZip) {
      $tempZipName = tempnam($tmpDir, "pgn4webViewer");
      if (copy($pgnUrl,$tempZipName)) {
        $pgnSource = $tempZipName;
      } else {
	$pgnStatus = "Failed to get remote zipfile";
        if ($tempZipName) { unlink($tempZipName); }
        return FALSE;   
      }
    } else {
      $pgnSource = $pgnUrl;
    }
  } elseif ($_FILES['pgnFile']['error'] === UPLOAD_ERR_OK) {
    $pgnFileName = $_FILES['pgnFile']['name'];
    $pgnStatus = "PGN from user file " . $pgnFileName;
    $pgnFileSize = $_FILES['userfile']['size'];
    $isPgn = preg_match("/\.pgn$/i",$pgnFileName);
    $isZip = preg_match("/\.zip$/i",$pgnFileName);
    $pgnSource = $_FILES['pgnFile']['tmp_name'];
  } elseif ($_FILES['pgnFile']['error'] === (UPLOAD_ERR_INI_SIZE | UPLOAD_ERR_FORM_SIZE)) {
    $pgnStatus = "Uploaded file exceeds " . $fileUploadLimit . " limit";
  } elseif ($_FILES['pgnFile']['error'] === (UPLOAD_ERR_PARTIAL | UPLOAD_ERR_NO_FILE | UPLOAD_ERR_NO_TMP_DIR | UPLOAD_ERR_CANT_WRITE | UPLOAD_ERR_EXTENSION)) {
    $pgnStatus = "Error uploading PGN data";
    return FALSE;
  } else {
    $pgnStatus = "Please provide PGN data";
    return FALSE;
  }

  if ($isZip) {
    $pgnZip = zip_open($pgnSource);
    if (is_resource($pgnZip)) {
      while (is_resource($zipEntry = zip_read($pgnZip))) {
	if (zip_entry_open($pgnZip, $zipEntry)) {
	  if (preg_match("/\.pgn$/i",zip_entry_name($zipEntry))) {
	    $pgnText = $pgnText . zip_entry_read($zipEntry, zip_entry_filesize($zipEntry)) . "\n\n\n";
          }
          zip_entry_close($zipEntry);
	} else {
          $pgnStatus = "Failed reading zipfile content";
          zip_close($pgnZip);
          if ($tempZipName) { unlink($tempZipName); }
          return FALSE;
        }
      }
      zip_close($pgnZip);
      if ($tempZipName) { unlink($tempZipName); }
      if (!$pgnText) {
        $pgnStatus = "No PGN data found in zipfile";
        return FALSE;
      } else {
        return TRUE;
      }
    } else {
      $pgnStatus = "Failed opening zipfile";
      return FALSE;
    }
  }

  if($isPgn) {
    $pgnText = file_get_contents($pgnSource);
    if (!$pgnText) {
      $pgnStatus = "Failed reading PGN data";
      return FALSE;
    }
    return TRUE;
  } 

  if($pgnSource) {
    $pgnStatus = "Only PGN and ZIP (zipped pgn) files are supported";
    return FALSE;
  }

  return TRUE;
}

function check_tmpDir() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  $tmpDirHandle = opendir($tmpDir);
  while($entryName = readdir($tmpDirHandle)) {
    if (($entryName !== ".") & ($entryName !== "..")) {
      $unexpectedFiles = $unexpectedFiles . " " . $entryName;
    }
  }
  closedir($tmpDirHandle);

  if ($unexpectedFiles) {
    $pgnDebugInfo = $pgnDebugInfo . "temporary directory " . $tmpDir . " not empty:" . $unexpectedFiles; 
  }
}

function print_header() {

  print <<<END

<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"> 

<title>pgn4web PGN viewer</title> 

<style type="text/css">

body
{color: black; background: white; font-family: sans-serif; padding: 20px;}
 
a:link, a:visited, a:hover, a:active
{ color: black; text-decoration: none; }

</style>

</head>

<body>

<table border="0" cellpadding="0" cellspacing="0" width="100%"><tbody><tr>
<td align="left" valign="middle"> 
<h1 style="font-family: sans-serif; color: red;"><a style="color: red;" href=.>pgn4web</a> PGN viewer</h1> 
</td>
<td align="right" valign="middle">
<a href=.><img src=pawns.png border=0></a>
</td>
</tr></tbody></table>

<div style="height: 1em;">&nbsp;</div>

END;
}

function print_form() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  print <<<END

<form id="textForm" action="$PHP_SELF" method="POST">
<table width="100%" cellspacing=0 cellpadding=3 border=0><tbody><tr><td>
<textarea id="pgnText" name="pgnText" rows=6 style="width:100%;">$pgnBoxText</textarea>
</td></tr><tr><td>
<input id="enterTextButton" type="submit" value="show games from PGN text box" style="width:100%;">
</td></tr/></tbody></table>
</form>

<form id="urlForm" action="$PHP_SELF" method="POST">
<table width="100%" cellspacing=0 cellpadding=3 border=0><tbody><tr><td>
<input id="fetchButton" type="submit" value="fetch PGN (or zipped PGN) URL and show games">
</td><td width="100%">
<input id="pgnUrl" name="pgnUrl" type="text" value="$pgnUrl" style="width:100%">
</td><td>
<input id="clearButton" type="submit" value="clear">
</td><td>
<input id="nicButton" type="submit" value="latest NIC">
</td><td>
<input id="twicButton" type="submit" value="latest TWIC">
</td></tr/></tbody></table>
</form>

<form id="uploadForm" enctype="multipart/form-data" action="$PHP_SELF" method="POST">
<table width="100%" cellspacing=0 cellpadding=3 border=0><tbody><tr><td>
<input id="uploadButton" type="submit" value="upload PGN (or zipped PGN) file and show games">
</td><td width="100%">
<input type="hidden" name="MAX_FILE_SIZE" value="4194304">
<input id="pgnFile" name="pgnFile" type="file" style="width:100%">
</td></tr/></tbody></table>
</form>

END;
}

function print_chessboard() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  print <<<END

<hr>
<div style="font-weight: bold; margin-top: 1em; margin-bottom: 1em;">$pgnStatus</div>

<pre>$pgnText</pre>

END;
}

function print_footer() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  print <<<END

<hr>
<div style="color: red; font-weight: bold; margin-top: 1em; margin-bottom: 1em;">$pgnDebugInfo</div>

</body>

</html>

END;
}

?>
