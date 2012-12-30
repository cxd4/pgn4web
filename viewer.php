<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);

$pgnDebugInfo = "";

$tmpDir = "viewer";
$fileUploadLimitBytes = 4194304;
$fileUploadLimitText = round(($fileUploadLimitBytes / 1048576), 0) . "MB";
$fileUploadLimitIniText = ini_get("upload_max_filesize");
if ($fileUploadLimitIniText === "") { $fileUploadLimitIniText = "unknown"; }

// it would be nice here to evaluate ini_get('allow_fopen_url') and flag the issue (possibly disabling portions of the input forms), but the return values of ini_get() for boolean values are totally unreliable, so we have to leave with the generic server error message when trying to load a remote URL while allow_fopen_url is disabled in php.ini

$zipSupported = function_exists('zip_open');
if (!$zipSupported) { $pgnDebugInfo = $pgnDebugInfo . "ZIP support unavailable from server, missing php ZIP library<br/>"; }

$debugHelpText = "a flashing chessboard signals errors in the PGN data, click on the top left chessboard square for debug messages";

if (!($goToView = get_pgn())) { $pgnText = $krabbeStartPosition = get_krabbe_position(); }


$presetURLsArray = array();
function addPresetURL($label, $javascriptCode) {
  global $presetURLsArray;
  array_push($presetURLsArray, array('label' => $label, 'javascriptCode' => $javascriptCode));
}

// modify the viewer-preset-URLs.php file to add preset URLs for the viewer's form
include 'viewer-preset-URLs.php';


$headlessPage = strtolower(get_param("headlessPage", "hp", ""));

print_header();
print_form();
check_tmpDir();
print_chessboard();
print_footer();


function get_krabbe_position() {

  $krabbePositions = array('',
    '[Event "#1"][FEN "rnq2rk1/1pn3bp/p2p2p1/2pPp1PP/P1P1Pp2/2N2N2/1P1B1P2/R2QK2R b KQ - 1 16"] 16... Nc6',
    '[Event "#2"][FEN "8/8/4kpp1/3p1b2/p6P/2B5/6P1/6K1 b - - 2 47"] 47... Bh3',
    '[Event "#3"][FEN "5rk1/pp4pp/4p3/2R3Q1/3n4/2q4r/P1P2PPP/5RK1 b - - 1 23"] 23. Qg3',
    '[Event "#4"][FEN "1r6/4k3/r2p2p1/2pR1p1p/2P1pP1P/pPK1P1P1/P7/1B6 b - - 0 48"] 48... Rxb3+',
    '[Event "#5"][FEN "2k2b1r/pb1r1p2/5P2/1qnp4/Npp3Q1/4B1P1/1P3PBP/R4RK1 w - - 4 21"] 21. Qg7',
    '[Event "#6"][FEN "r1bq1rk1/1p3ppp/p1pp2n1/3N3Q/B1PPR2b/8/PP3PPP/R1B3K1 w - - 0 14"] 14. Rxh4',
    '[Event "#7"][FEN "r4k1r/1b2bPR1/p4n2/3p4/4P2P/1q2B2B/PpP5/1K4R1 w - - 0 26"] 26. Bh6',
    '[Event "#8"][FEN "r1b2r1k/4qp1p/p2ppb1Q/4nP2/1p1NP3/2N5/PPP4P/2KR1BR1 w - - 4 18"] 18. Nc6',
    '[Event "#9"][FEN "8/5B2/6Kp/6pP/5b2/p7/1k3P2/8 b - - 3 69"] 69... Be3',
    '[Event "#10"][FEN "4r1k1/q6p/2p4P/2P2QP1/1p6/rb2P3/1B6/1K4RR w - - 1 38"] 38. Qxh7+',
    '[Event "#11"][FEN "6k1/3Q4/5p2/5P2/8/1KP5/PP4qp/2B5 w - - 0 99"] 99. Bg5',
    '[Event "#12"][FEN "k4b1r/p3pppp/B1p2n2/3rB1N1/7q/8/PPP2P2/R2Q1RK1 w - - 1 18"] 18. c4',
    '[Event "#13"][FEN "1nbk1b1r/r3pQpp/pq2P3/1p1P2B1/2p5/2P5/5PPP/R3KB1R b KQ - 0 15"] 15... Rd7',
    '[Event "#14"][FEN "5r2/7k/1pPP3P/8/4p3/3p4/P4R1P/7K b - - 0 48"] 48... e3',
    '[Event "#15"][FEN "rnb1kr2/pp1p1pQp/6q1/4PpB1/1P6/8/1PP2PPP/2KR3R w q - 2 15"] 15. e6',
    '[Event "#16"][FEN "7k/1p1P2pp/p7/3P4/1Q5P/5pPK/PP3r2/1q5B b - - 1 37"] 37... h5',
    '[Event "#17"][FEN "r2q1rk1/pp2bpp1/4p2p/2pPB2P/2P3n1/3Q2N1/PP3PP1/2KR3R w - - 1 17"] 17. Bxg7',
    '[Event "#18"][FEN "r2qk2r/1b3ppp/p2p1b2/2nNp3/1R2P3/2P5/1PN2PPP/3QKB1R w Kkq - 3 17"] 17. Rxb7',
    '[Event "#19"][FEN "r3kbnr/p1pp1qpp/b1n1P3/6N1/1p6/8/Pp3PPP/RNBQR1K1 b kq - 0 12"] 12... O-O-O',
    '[Event "#20"][FEN "r2qkb1r/pb1p1p1p/1pn2np1/2p1p3/2P1P3/2NP1NP1/PP3PBP/R1BQ1RK1 w kq - 0 9"] 9. Nxe5',
    '');

  return $krabbePositions[rand(0, count($krabbePositions)-1)];
}

function get_param($param, $shortParam, $default) {
  if (isset($_REQUEST[$param]) && stripslashes(rawurldecode($_REQUEST[$param]))) { return stripslashes(rawurldecode($_REQUEST[$param])); }
  if (isset($_REQUEST[$shortParam]) && stripslashes(rawurldecode($_REQUEST[$shortParam]))) { return stripslashes(rawurldecode($_REQUEST[$shortParam])); }
  return $default;
}

function get_pgn() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $krabbeStartPosition, $goToView, $zipSupported;

  $pgnDebugInfo = $pgnDebugInfo . get_param("debug", "d", "");

  $pgnText = get_param("pgnText", "pt", "");

  $pgnUrl = get_param("pgnData", "pd", "");
  if ($pgnUrl == "") { $pgnUrl = get_param("pgnUrl", "pu", ""); }

  if ($pgnText) {
    $pgnStatus = "PGN games from textbox input";
    $pgnTextbox = $pgnText = str_replace("\\\"", "\"", $pgnText);

    $pgnText = preg_replace("/\[/", "\n\n[", $pgnText);
    $pgnText = preg_replace("/\]/", "]\n\n", $pgnText);
    $pgnText = preg_replace("/([012\*])(\s*)(\[)/", "$1\n\n$3", $pgnText);
    $pgnText = preg_replace("/\]\s*\[/", "]\n[", $pgnText);
    $pgnText = preg_replace("/^\s*\[/", "[", $pgnText);
    $pgnText = preg_replace("/\n[\s*\n]+/", "\n\n", $pgnText);

    $pgnTextbox = $pgnText;

    return TRUE;
  } else if ($pgnUrl) {
    $pgnStatus = "PGN games from URL: <a href='" . $pgnUrl . "'>" . $pgnUrl . "</a>";
    $isPgn = preg_match("/\.(pgn|txt)$/i",$pgnUrl);
    $isZip = preg_match("/\.zip$/i",$pgnUrl);
    if ($isZip) {
      if (!$zipSupported) {
        $pgnStatus = "unable to open zipfile&nbsp; &nbsp;<span style='color: gray;'>please <a style='color: gray;' href='" . $pgnUrl. "'>download zipfile locally</a> and submit extracted PGN</span>";
        return FALSE;
      } else {
        $zipFileString = "<a href='" . $pgnUrl . "'>zip URL</a>";
        $tempZipName = tempnam($tmpDir, "pgn4webViewer_");
        // $pgnUrlOpts tries forcing following location redirects
        // depending on server configuration, the script might still fail if the ZIP URL is redirected
        $pgnUrlOpts = array("http" => array("follow_location" => TRUE, "max_redirects" => 20));
        $pgnUrlHandle = fopen($pgnUrl, "rb", false, stream_context_create($pgnUrlOpts));
        $tempZipHandle = fopen($tempZipName, "wb");
        $copiedBytes = stream_copy_to_stream($pgnUrlHandle, $tempZipHandle, $fileUploadLimitBytes + 1, 0);
        fclose($pgnUrlHandle);
        fclose($tempZipHandle);
        if (($copiedBytes > 0) && ($copiedBytes <= $fileUploadLimitBytes)) {
          $pgnSource = $tempZipName;
        } else {
          $pgnStatus = "failed to get " . $zipFileString . ": file not found, file size exceeds " . $fileUploadLimitText . " form limit, " . $fileUploadLimitIniText . " server limit or server error";
          if (($tempZipName) && (file_exists($tempZipName))) { unlink($tempZipName); }
          return FALSE;
        }
      }
    } else {
      $pgnSource = $pgnUrl;
    }
  } elseif (count($_FILES) == 0) {
    $pgnStatus = "please enter chess games in PGN format&nbsp; &nbsp;<span style='color: gray;'></span>";
    return FALSE;
  } elseif ($_FILES['pgnFile']['error'] === UPLOAD_ERR_OK) {
    $pgnFileName = $_FILES['pgnFile']['name'];
    $pgnStatus = "PGN games from file: " . $pgnFileName;
    $pgnFileSize = $_FILES['pgnFile']['size'];
    if ($pgnFileSize == 0) {
      $pgnStatus = "failed uploading PGN games: file not found, file empty or upload error";
      return FALSE;
    } elseif ($pgnFileSize > $fileUploadLimitBytes) {
      $pgnStatus = "failed uploading PGN games: file size exceeds " . $fileUploadLimitText . " limit";
      return FALSE;
    } else {
      $isPgn = preg_match("/\.(pgn|txt)$/i",$pgnFileName);
      $isZip = preg_match("/\.zip$/i",$pgnFileName);
      $pgnSource = $_FILES['pgnFile']['tmp_name'];
    }
  } else {
    $pgnStatus = "failed uploading PGN games: ";
    switch ($_FILES['pgnFile']['error']) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        $pgnStatus = $pgnStatus . "file size exceeds " . $fileUploadLimitText . " form limit or " . $fileUploadLimitIniText . " server limit";
        break;
      case UPLOAD_ERR_PARTIAL:
      case UPLOAD_ERR_NO_FILE:
        $pgnStatus = $pgnStatus . "file missing or truncated";
        break;
      case UPLOAD_ERR_NO_TMP_DIR:
      case UPLOAD_ERR_CANT_WRITE:
      case UPLOAD_ERR_EXTENSION:
        $pgnStatus = $pgnStatus . "server error";
        break;
      default:
        $pgnStatus = $pgnStatus . "unknown upload error";
        break;
    }
    return FALSE;
  }

  if ($isZip) {
    if ($zipSupported) {
      if ($pgnUrl) { $zipFileString = "<a href='" . $pgnUrl . "'>zip URL</a>"; }
      else { $zipFileString = "zip file"; }
      $pgnZip = zip_open($pgnSource);
      if (is_resource($pgnZip)) {
        while (is_resource($zipEntry = zip_read($pgnZip))) {
          if (zip_entry_open($pgnZip, $zipEntry)) {
            if (preg_match("/\.pgn$/i",zip_entry_name($zipEntry))) {
              $pgnText = $pgnText . zip_entry_read($zipEntry, zip_entry_filesize($zipEntry)) . "\n\n\n";
            }
            zip_entry_close($zipEntry);
          } else {
            $pgnStatus = "failed reading " . $zipFileString . " content";
            zip_close($pgnZip);
            if (($tempZipName) && (file_exists($tempZipName))) { unlink($tempZipName); }
            return FALSE;
          }
        }
        zip_close($pgnZip);
        if (($tempZipName) && (file_exists($tempZipName))) { unlink($tempZipName); }
        if (!$pgnText) {
          $pgnStatus = "PGN games not found in " . $zipFileString;
         return FALSE;
        } else {
          return TRUE;
        }
      } else {
        if (($tempZipName) && (file_exists($tempZipName))) { unlink($tempZipName); }
        $pgnStatus = "failed opening " . $zipFileString;
        return FALSE;
      }
    } else {
      $pgnStatus = "ZIP support unavailable from this server&nbsp; &nbsp;<span style='color: gray;'>only PGN files are supported</span>";
      return FALSE;
    }
  }

  if ($isPgn) {
    if ($pgnUrl) { $pgnFileString = "<a href='" . $pgnUrl . "'>pgn URL</a>"; }
    else { $pgnFileString = "pgn file"; }
    $pgnText = file_get_contents($pgnSource, NULL, NULL, 0, $fileUploadLimitBytes + 1);
    if (!$pgnText) {
      $pgnStatus = "failed reading " . $pgnFileString . ": file not found or server error";
      return FALSE;
    }
    if ((strlen($pgnText) == 0) || (strlen($pgnText) > $fileUploadLimitBytes)) {
      $pgnStatus = "failed reading " . $pgnFileString . ": file size exceeds " . $fileUploadLimitText . " form limit, " . $fileUploadLimitIniText . " server limit or server error";
      return FALSE;
    }
    return TRUE;
  }

  if ($pgnSource) {
    if ($zipSupported) {
      $pgnStatus = "only PGN and ZIP (zipped pgn) files are supported";
    } else {
      $pgnStatus = "only PGN files are supported&nbsp; &nbsp;<span style='color: gray;'>ZIP support unavailable from this server</span>";
    }
    return FALSE;
  }

  return TRUE;
}

function check_tmpDir() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $krabbeStartPosition, $goToView, $zipSupported;

  $tmpDirHandle = opendir($tmpDir);
  while($entryName = readdir($tmpDirHandle)) {
    if (($entryName !== ".") && ($entryName !== "..") && ($entryName !== "index.html")) {
      if ((time() - filemtime($tmpDir . "/" . $entryName)) > 3600) {
        $unexpectedFiles = $unexpectedFiles . " " . $entryName;
      }
    }
  }
  closedir($tmpDirHandle);

  if ($unexpectedFiles) {
    $pgnDebugInfo = $pgnDebugInfo . "clean temporary directory " . $tmpDir . "(" . $unexpectedFiles . ")<br>";
  }
}

function print_header() {

  global $headlessPage;

  if (($headlessPage == "true") || ($headlessPage == "t")) {
     $headClass = "display: none;";
  } else {
     $headClass = "";
  }

  print <<<END
<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">

<title>pgn4web games viewer</title>

<style type="text/css">

body {
  color: black;
  background: white;
  font-family: 'pgn4web Liberation Sans', sans-serif;
  font-size: 16px;
  line-height: 1.4em;
  padding: 20px;
  overflow-x: hidden;
}

div, span, table, tr, td {
  font-family: 'pgn4web Liberation Sans', sans-serif; /* fixes IE9 body css issue */
}

a {
  color: black;
  text-decoration: none;
}

.formControl {
  font-size: smaller;
  margin: 0;
}

.headClass {
  $headClass
}

</style>

</head>

<body>

<table class="headClass" border="0" cellpadding="0" cellspacing="0" width="100%"><tbody><tr>
<td align="left" valign="middle">
<h1 name="top" style="font-family: sans-serif; color: red;"><a style="color: red;" href=.>pgn4web</a> games viewer</h1>
</td>
<td align="right" valign="middle">
<a href=.><img src=pawns.png border=0></a>
</td>
</tr></tbody></table>

<div style="height: 1em;" class="headClass">&nbsp;</div>

END;
}


function print_form() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $krabbeStartPosition, $goToView, $zipSupported;
  global $headlessPage, $presetURLsArray;

  $thisScript = $_SERVER['SCRIPT_NAME'];
  if (($headlessPage == "true") || ($headlessPage == "t")) { $thisScript .= "?hp=t"; }

  print <<<END

<script type="text/javascript">

  function setPgnUrl(newPgnUrl) {
    if (!newPgnUrl) { newPgnUrl = ""; }
    document.getElementById("urlFormText").value = newPgnUrl;
    return false;
  }

  function checkPgnUrl() {
    theObj = document.getElementById("urlFormText");
    if (theObj === null) { return false; }
    if (!checkPgnExtension(theObj.value)) { return false; }
    else { return (theObj.value !== ""); }
  }

  function checkPgnFile() {
    theObj = document.getElementById("uploadFormFile");
    if (theObj === null) { return false; }
    if (!checkPgnExtension(theObj.value)) { return false; }
    else { return (theObj.value !== ""); }
  }

END;

  if ($zipSupported) { print <<<END

  function checkPgnExtension(uri) {
    if (uri.match(/\\.(zip|pgn|txt)\$/i)) {
      return true;
    } else if (uri !== "") {
      alert("only PGN and ZIP (zipped pgn) files are supported");
    }
    return false;
  }

END;

  } else { print <<<END

  function checkPgnExtension(uri) {
    if (uri.match(/\\.(pgn|txt)\$/i)) {
      return true;
    } else if (uri.match(/\\.zip\$/i)) {
      alert("ZIP support unavailable from this server, only PGN files are supported\\n\\nplease submit locally extracted PGN");
    } else if (uri !== "") {
      alert("only PGN files are supported (ZIP support unavailable from this server)");
    }
    return false;
  }

END;

  }

  print <<<END

  function checkPgnFormTextSize() {
    document.getElementById("pgnFormButton").title = "PGN textbox size is " + document.getElementById("pgnFormText").value.length;
    if (document.getElementById("pgnFormText").value.length == 1) {
      document.getElementById("pgnFormButton").title += " char;";
    } else {
      document.getElementById("pgnFormButton").title += " chars;";
    }
    document.getElementById("pgnFormButton").title += " $debugHelpText";
    document.getElementById("pgnFormText").title = document.getElementById("pgnFormButton").title;
  }

  function loadPgnFromForm() {
    theObjPgnFormText = document.getElementById('pgnFormText');
    if (theObjPgnFormText === null) { return; }
    if (theObjPgnFormText.value === "") { return; }

    theObjPgnText = document.getElementById('pgnText');
    if (theObjPgnText === null) { return; }

    theObjPgnText.value = theObjPgnFormText.value;

    theObjPgnText.value = theObjPgnText.value.replace(/\\[/g,'\\n\\n[');
    theObjPgnText.value = theObjPgnText.value.replace(/\\]/g,']\\n\\n');
    theObjPgnText.value = theObjPgnText.value.replace(/([012\\*])(\\s*)(\\[)/g,'\$1\\n\\n\$3');
    theObjPgnText.value = theObjPgnText.value.replace(/\\]\\s*\\[/g,']\\n[');
    theObjPgnText.value = theObjPgnText.value.replace(/^\\s*\\[/g,'[');
    theObjPgnText.value = theObjPgnText.value.replace(/\\n[\\s*\\n]+/g,'\\n\\n');

    document.getElementById('pgnStatus').innerHTML = "PGN games from textbox input";
    document.getElementById('uploadFormFile').value = "";
    document.getElementById('urlFormText').value = "";

    if (analysisStarted) { stopAnalysis(); }
    firstStart = true;
    start_pgn4web();
    if (window.location.hash == "view") { window.location.reload(); }
    else { window.location.hash = "view"; }

    return;
  }

  function urlFormSelectChange() {
    theObj = document.getElementById("urlFormSelect");
    if (theObj === null) { return; }

    targetPgnUrl = "";
    switch (theObj.value) {

END;

  foreach($presetURLsArray as $value) {
    print("\n" . '      case "' . $value['label'] . '":' . "\n" . '        targetPgnUrl = (function(){ ' . $value['javascriptCode'] . '})();' . "\n" . '      break;' . "\n");
  }

  $formVariableColspan = $presetURLsArray ? 2: 1;
  print <<<END

      default:
      break;
    }
    setPgnUrl(targetPgnUrl);
    theObj.value = "header";
  }

function reset_viewer() {
   document.getElementById("uploadFormFile").value = "";
   document.getElementById("urlFormText").value = "";
   document.getElementById("pgnFormText").value = "";
   checkPgnFormTextSize();
   document.getElementById("pgnStatus").innerHTML = "please enter chess games in PGN format&nbsp; &nbsp;<span style='color: gray;'></span>";
   document.getElementById("pgnText").value = '$krabbeStartPosition';

   if (analysisStarted) { stopAnalysis(); }
   firstStart = true;
   start_pgn4web();
   if (window.location.hash == "top") { window.location.reload(); }
   else {window.location.hash = "top"; }
}

// fake functions to avoid warnings before pgn4web.js is loaded
function disableShortcutKeysAndStoreStatus() {}
function restoreShortcutKeysStatus() {}

</script>

<table width="100%" cellspacing="0" cellpadding="3" border="0"><tbody>

  <tr>
    <td align="left" valign="top">
      <form id="uploadForm" action="$thisScript" enctype="multipart/form-data" method="POST" style="display: inline;">
        <input id="uploadFormSubmitButton" type="submit" class="formControl" value="show games from PGN (or zipped PGN) file" style="width:100%" title="PGN and ZIP files must be smaller than $fileUploadLimitText (form limit) and $fileUploadLimitIniText (server limit); $debugHelpText" onClick="this.blur(); return checkPgnFile();">
    </td>
    <td colspan="$formVariableColspan" width="100%" align="left" valign="top">
        <input type="hidden" name="MAX_FILE_SIZE" value="$fileUploadLimitBytes">
        <input id="uploadFormFile" name="pgnFile" type="file" class="formControl" style="width:100%" title="PGN and ZIP files must be smaller than $fileUploadLimitText (form limit) and $fileUploadLimitIniText (server limit); $debugHelpText" onClick="this.blur();">
      </form>
    </td>
  </tr>

  <tr>
    <td align="left" valign="top">
      <form id="urlForm" action="$thisScript" method="POST" style="display: inline;">
        <input id="urlFormSubmitButton" type="submit" class="formControl" value="show games from PGN (or zipped PGN) URL" title="PGN and ZIP files must be smaller than $fileUploadLimitText (form limit) and $fileUploadLimitIniText (server limit); $debugHelpText" onClick="this.blur(); return checkPgnUrl();">
    </td>
    <td width="100%" align="left" valign="top">
        <input id="urlFormText" name="pgnUrl" type="text" class="formControl" value="" style="width:100%" onFocus="disableShortcutKeysAndStoreStatus();" onBlur="restoreShortcutKeysStatus();" title="PGN and ZIP files must be smaller than $fileUploadLimitText (form limit) and $fileUploadLimitIniText (server limit); $debugHelpText">
      </form>
    </td>
END;

  if ($presetURLsArray) {
    print('    <td align="right" valign="top">' . "\n" . '        <select id="urlFormSelect" class="formControl" title="select the download URL from the preset options; please support the sites providing the PGN downloads" onChange="this.blur(); urlFormSelectChange();">' . "\n" . '          <option value="header">preset URL</option>' . "\n");
    foreach($presetURLsArray as $value) {
      print('          <option value="' . $value['label'] . '">' . $value['label'] . '</option>' . "\n");
    }
    print('          <option value="clear">clear URL</option>' . "\n" . '        </select>' . "\n" . '    </td>' . "\n");
  }

  print <<<END
  </tr>

  <tr>
    <td align="left" valign="top">
      <form id="textForm" style="display: inline;">
        <input id="pgnFormButton" type="button" class="formControl" value="show games from PGN textbox" style="width:100%;" onClick="this.blur(); loadPgnFromForm();">
    </td>
    <td colspan="$formVariableColspan" rowspan="2" width="100%" align="right" valign="top">
        <textarea id="pgnFormText" class="formControl" name="pgnTextbox" rows=4 style="width:100%;" onFocus="disableShortcutKeysAndStoreStatus();" onBlur="restoreShortcutKeysStatus();" onChange="checkPgnFormTextSize();">$pgnTextbox</textarea>
      </form>
    </td>
  </tr>

  <tr>
  <td align="left" valign="bottom">
    <input id="clearButton" type="button" class="formControl" value="reset PGN viewer" onClick="this.blur(); if (confirm('reset PGN viewer, current games and inputs will be lost')) { reset_viewer(); }" title="reset PGN viewer, current games and inputs will be lost">
  </td>
  </tr>

</tbody></table>

END;
}

function print_chessboard() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $krabbeStartPosition, $goToView, $zipSupported;

  $pieceSize = 38;
  $pieceType = "merida";
  $pieceSizeCss = $pieceSize . "px";

  print <<<END

<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td valign="top" align="left">
<a name="view">&nbsp;</a><div id="pgnStatus" style="font-weight: bold; margin-top: 2em; margin-bottom: 1em;">$pgnStatus</div>
</td><td valign="top" align="right">
<div style="padding-top: 1em;">
&nbsp;&nbsp;&nbsp;<a href="#moves" style="color: gray; font-size: 66%;">moves</a>&nbsp;&nbsp;&nbsp;<a href="#view" style="color: gray; font-size: 66%;">board</a>&nbsp;&nbsp;&nbsp;<a href="#top" style="color: gray; font-size: 66%;">form</a>
</div>
</tr></table>

<style type="text/css">

@import url("fonts/pgn4web-font-LiberationSans.css");
@import url("fonts/pgn4web-font-ChessSansUsual.css");

.gameBoard, .boardTable {
  width: 392px !important;
  height: 392px !important;
}

.boardTable {
  border-style: solid;
  border-color: #663300;
  border-width: 4px;
  box-shadow: 0 0 20px #663300;
}

.pieceImage {
  width: 36px;
  height: 36px;
}

.whiteSquare,
.blackSquare,
.highlightWhiteSquare,
.highlightBlackSquare {
  width: 44px;
  height: 44px;
  border-style: solid;
  border-width: 2px;
}

.whiteSquare,
.highlightWhiteSquare {
  border-color: #FFCC99;
  background: #FFCC99;
}

.blackSquare,
.highlightBlackSquare {
  border-color: #CC9966;
  background: #CC9966;
}

.highlightWhiteSquare,
.highlightBlackSquare {
  border-color: #663300;
}

.selectControl {
/* a "width" attribute here must use the !important flag to override default settings */
  width: 99% !important;
}

.optionSelectControl {
}

.gameButtons {
  width: 392px;
}

.buttonControlPlay,
.buttonControlStop,
.buttonControl {
/* a "width" attribute here must use the !important flag to override default settings */
  width: 75.2px !important;
  margin-top: 20px;
}

.buttonControlSpace {
/* a "width" attribute here must use the !important flag to override default settings */
  width: 4px !important;
}

.searchPgnButton {
/* a "width" attribute here must use the !important flag to override default settings */
  width: 10% !important;
}

.searchPgnExpression {
/* a "width" attribute here must use the !important flag to override default settings */
  width: 89% !important;
}

.move,
.variation,
.comment {
  line-height: 1.4em;
  font-weight: normal;
}

.move,
.variation,
.commentMove {
  font-family: 'pgn4web ChessSansUsual', 'pgn4web Liberation Sans', sans-serif;
}

.move,
.variation {
  text-decoration: none;
}

.move {
  color: black;
}

.moveText {
  clear: both;
  padding-top: 0.5em;
  padding-bottom: 1em;
  text-align: justify;
}

.comment,
.variation {
  color: #808080;
}

a.variation {
  color: #808080;
}

.moveOn,
.variationOn {
  background-color: #FFCC99;
}

.topMenu {
  text-align: center;
  padding-top: 1em;
  padding-bottom: 1.5em;
}

.mainContainer {
  padding-top: 0.5em;
}

.columnsContainer {
  float: left;
  width: 100%;
}

.boardColumn {
  float: left;
  width: 60%;
}

.headerColumn {
  margin-left: 60%;
}

.headerItem {
  width: 100%;
  height: 1.4em;
  white-space: nowrap;
  overflow: hidden;
}

.innerHeaderItem,
.innerHeaderItemNoMargin {
  color: black;
  text-decoration: none;
}

.innerHeaderItem {
  margin-right: 1.25em;
}

.innerHeaderItemNoMargin {
  margin-right: 0;
}

.headerSpacer {
  height: 0.7em;
}

.toggleComments, .toggleAnalysis {
  clear: both;
  text-align: right;
  width: 100%;
  height: 1em;
  margin-bottom: 0.25em;
}

.toggleCommentsLink, .toggleAnalysisLink {
  color: #808080;
}

.lastMoveAndVariations {
  float: left;
}

.lastMove {
}

.lastVariations {
  padding-left: 1em;
}

.nextMoveAndVariations {
  float: right;
}

.nextMove {
}

.nextButton {
  display: inline-block;
  width: 1em;
  padding-left: 1em;
  color: #808080;
  text-decoration: none;
  text-align: right;
}

.nextVariations {
  padding-right: 1em;
}

.lastMoveAndComment {
  clear: both;
  line-height: 1.4em;
  display: none;
}

.lastComment {
  clear: both;
  resize: vertical;
  overflow-y: auto;
  height: 4.2em;
  min-height: 1.4em;
  max-height: 21em;
  padding-right: 1em;
  margin-top: 0.5em;
  margin-bottom: 1.5em;
  text-align: justify;
}

.analysisMove {
  display: none;
  min-width: 6em;
}

.analysisEval {
  display: inline-block;
  min-width: 3em;
}

.analysisPv {
  display: inline-block;
}

.analysisExtraInfo {
  font-family: 'pgn4web Liberation Sans', sans-serif;
}

.NAGs {
  font-size: 19px;
}

</style>

<script src="pgn4web.js" type="text/javascript"></script>
<script src="engine.js" type="text/javascript"></script>
<script src="chess-informant-NAG-symbols.js" type="text/javascript"></script>
<script src="fide-lookup.js" type="text/javascript"></script>

</head>

<body onresize="if (typeof(updateAnnotationGraph) != 'undefined') { updateAnnotationGraph(); }">

<!-- paste your PGN below and make sure you dont specify an external source with SetPgnUrl() -->
<form style="display: none;"><textarea style="display: none;" id="pgnText">

$pgnText

</textarea></form>
<!-- paste your PGN above and make sure you dont specify an external source with SetPgnUrl() -->

<script type="text/javascript">

   pgn4web_engineWindowUrlParameters = "pf=$pieceType";

   var highlightOption_default = true;
   var commentsOnSeparateLines_default = false;
   var commentsIntoMoveText_default = true;

   SetImagePath("$pieceType/36");
   SetImageType("png");
   SetHighlightOption(getHighlightOptionFromLocalStorage());
   SetGameSelectorOptions("", true, 12, 12, 2, 15, 15, 3, 10);
   SetCommentsIntoMoveText(getCommentsIntoMoveTextFromLocalStorage());
   SetCommentsOnSeparateLines(getCommentsOnSeparateLinesFromLocalStorage());
   SetAutostartAutoplay(false);
   SetAutoplayNextGame(false);
   SetAutoplayDelay(2000);
   SetShortcutKeysEnabled(true);

   function getHighlightOptionFromLocalStorage() {
      try { ho = (localStorage.getItem("pgn4web_chess_viewer_highlightOption") != "false"); }
      catch (e) { return highlightOption_default; }
      return ho;
   }
   function setHighlightOptionToLocalStorage() {
      try { localStorage.setItem("pgn4web_chess_viewer_highlightOption", highlightOption ? "true" : "false"); }
      catch (e) { return false; }
      return true;
   }

   function getCommentsIntoMoveTextFromLocalStorage() {
      try { cimt = !(localStorage.getItem("pgn4web_chess_viewer_commentsIntoMoveText") == "false"); }
      catch (e) { return commentsIntoMoveText_default; }
      return cimt;
   }
   function setCommentsIntoMoveTextToLocalStorage() {
      try { localStorage.setItem("pgn4web_chess_viewer_commentsIntoMoveText", commentsIntoMoveText ? "true" : "false"); }
      catch (e) { return false; }
      return true;
   }

   function getCommentsOnSeparateLinesFromLocalStorage() {
      try { cosl = (localStorage.getItem("pgn4web_chess_viewer_commentsOnSeparateLines") == "true"); }
      catch (e) { return commentsOnSeparateLines_default; }
      return cosl;
   }
   function setCommentsOnSeparateLinesToLocalStorage() {
      try { localStorage.setItem("pgn4web_chess_viewer_commentsOnSeparateLines", commentsOnSeparateLines ? "true" : "false"); }
      catch (e) { return false; }
      return true;
   }

   function searchTag(tag, key) {
      searchPgnGame('\\\\[\\\\s*' + tag + '\\\\s*"' + fixRegExp(key) + '"\\\\s*\\\\]', event.shiftKey);
   }
   function searchTagDifferent(tag, key) {
      searchPgnGame('\\\\[\\\\s*' + tag + '\\\\s*"(?!' + fixRegExp(key) + '"\\\\s*\\\\])', event.shiftKey);
   }

   function fixHeaderTag(elementId) {
      var headerId = ["GameEvent", "GameSite", "GameDate", "GameRound", "GameWhite", "GameBlack", "GameResult", "GameSection", "GameStage", "GameBoardNum", "Timecontrol", "GameWhiteTeam", "GameBlackTeam", "GameWhiteTitle", "GameBlackTitle", "GameWhiteElo", "GameBlackElo", "GameECO", "GameOpening", "GameVariation", "GameSubVariation", "GameTermination", "GameWhiteClock", "GameBlackClock", "GameTimeControl"];
      var headerLabel = ["event", "site", "date", "round", "white player", "black player", "result", "section", "stage", "board", "time control", "white team", "black team", "white title", "black title", "white elo", "black elo", "eco", "opening", "variation", "subvariation", "termination", "white clock", "black clock", "time control"];
      theObj = document.getElementById(elementId);
      if (theObj) {
        theObj.className = (theObj.innerHTML === "") ? "innerHeaderItemNoMargin" : "innerHeaderItem";
        for (ii = 0; ii < headerId.length; ii++) {
            if (headerId[ii] === elementId) { break; }
        }
        theObj.title = (ii < headerId.length ? headerLabel[ii] : elementId) + ": " + theObj.innerHTML;
      }
   }

   function customPgnHeaderTagWithFix(tag, elementId, fixForDisplay) {
      var theObj;
      customPgnHeaderTag(tag, elementId);
      fixHeaderTag(elementId);
      if (fixForDisplay && (theObj = document.getElementById(elementId)) && theObj.innerHTML) {
         theObj.innerHTML = fixCommentForDisplay(theObj.innerHTML);
      }
   }

   var previousCurrentVar = -1;
   function customFunctionOnMove() {

      if (analysisStarted) {
         if (engineUnderstandsGame(currentGame)) {
            if (previousCurrentVar !== CurrentVar) { scanGameForFen(); }
            restartAnalysis();
         }
         else { stopAnalysis(); }
      } else {
         clearAnalysisHeader();
         clearAnnotationGraph();
      }
      previousCurrentVar = CurrentVar;

      fixHeaderTag('GameWhiteClock');
      fixHeaderTag('GameBlackClock');

      if ((annotateInProgress) && (CurrentPly === StartPly + PlyNumber)) {
         annotateInProgress = false;
         SetAutoplayDelay(2000);
      }
   }

   var PlyNumberMax;
   function customFunctionOnPgnGameLoad() {
      fixHeaderTag('GameDate');
      fixHeaderTag('GameSite');
      fixHeaderTag('GameEvent');
      customPgnHeaderTagWithFix('Section', 'GameSection');
      customPgnHeaderTagWithFix('Stage', 'GameStage');
      fixHeaderTag('GameRound');
      if (theObj = document.getElementById("GameRound")) {
         if (theObj.innerHTML) {
            theObj.innerHTML = "round " + theObj.innerHTML;
         }
      }
      customPgnHeaderTagWithFix('Board', 'GameBoardNum');
      if (theObj = document.getElementById("GameBoardNum")) {
         if (theObj.innerHTML) {
            theObj.innerHTML = "board " + theObj.innerHTML;
         }
      }
      customPgnHeaderTagWithFix('TimeControl', 'GameTimeControl');
      fixHeaderTag('GameWhite');
      fixHeaderTag('GameBlack');
      customPgnHeaderTagWithFix('WhiteTeam', 'GameWhiteTeam');
      customPgnHeaderTagWithFix('BlackTeam', 'GameBlackTeam');
      customPgnHeaderTagWithFix('WhiteTitle', 'GameWhiteTitle');
      customPgnHeaderTagWithFix('BlackTitle', 'GameBlackTitle');
      customPgnHeaderTagWithFix('WhiteElo', 'GameWhiteElo');
      customPgnHeaderTagWithFix('BlackElo', 'GameBlackElo');
      customPgnHeaderTagWithFix('ECO', 'GameECO');
      customPgnHeaderTagWithFix('Opening', 'GameOpening', true);
      customPgnHeaderTagWithFix('Variation', 'GameVariation', true);
      customPgnHeaderTagWithFix('SubVariation', 'GameSubVariation', true);
      fixHeaderTag('GameResult');
      customPgnHeaderTagWithFix('Termination', 'GameTermination');
      if (PlyNumber > 0) { customPgnHeaderTag('Result', 'ResultAtGametextEnd'); }
      else { if (theObj = document.getElementById('ResultAtGametextEnd')) { theObj.innerHTML = ""; } }

      if (theObj = document.getElementById('lastMoveAndComment')) {
         if ((PlyNumber === 0) && (gameFEN[currentGame])) {
            lastDisplayStyle = "block";
         } else if (commentsIntoMoveText && ((PlyNumber > 0) || (gameFEN[currentGame]))) {
            lastDisplayStyle = GameHasComments ? "block" : "none";
         } else {
            lastDisplayStyle = "none";
         }
         theObj.style.display = lastDisplayStyle;
      }
      if (theObj = document.getElementById("toggleCommentsLink")) {
         if (GameHasComments) {
            theObj.innerHTML = commentsIntoMoveText ? "&times;" : "+";
         } else {
            theObj.innerHTML = "";
         }
      }

      PlyNumberMax = 0;
      for (ii = 0; ii < numberOfVars; ii++) {
         PlyNumberMax = Math.max(PlyNumberMax, StartPlyVar[ii] + PlyNumberVar[ii] - StartPly);
      }

      if (analysisStarted) {
         if (engineUnderstandsGame(currentGame)) { scanGameForFen(); }
         else { stopAnalysis(); }
      }
      if (theObj = document.getElementById("toggleAnalysisLink")) {
         theObj.style.visibility = (annotationSupported && engineUnderstandsGame(currentGame)) ? "visible" : "hidden";
      }
   }

   function customFunctionOnPgnTextLoad() {
   }

   function searchPlayer(name, FideId) {
      if (name) {
         if (event.shiftKey) {
            if (typeof(openFidePlayerUrl) == "function") { openFidePlayerUrl(name, FideId); }
         } else {
            searchPgnGame('\\\\[\\\\s*(White|Black)\\\\s*"' + fixRegExp(name) + '"\\\\s*\\\\]', false);
         }
      }
   }

   function searchTeam(name) {
      searchPgnGame('\\\\[\\\\s*(White|Black)Team\\\\s*"' + fixRegExp(name) + '"\\\\s*\\\\]', false);
   }

   function cycleHash() {
      switch (location.hash) {
         case "#top": goToHash("view"); break;
         case "#view": goToHash("moves"); break;
         case "#moves": goToHash("bottom"); break; // PAOLO make sure all hash are there
         case "#bottom": goToHash("top"); break;
         default: goToHash("view"); break;
      }
   }

   function goToHash(hash) {
      if (hash) { location.hash = ""; }
      else { location.hash = "board"; }
      location.hash = hash;
   }


   function customShortcutKey_Shift_3() { cycleHash(); }

   function customShortcutKey_Shift_4() { cycleLastCommentArea(); }

   function customShortcutKey_Shift_5() { if (annotationSupported) { userToggleAnalysis(); } }
   function customShortcutKey_Shift_6() { if (annotationSupported) { goToMissingAnalysis(true); } }

   function gameIsNormalChess(gameNum) {
      return ((typeof(gameVariant[gameNum]) == "undefined") || (gameVariant[gameNum].match(/^(chess|normal|standard|)$/i) !== null));
   }


   function emPixels(em) { return em * document.getElementById("emMeasure").offsetHeight; }

   var cycleLCA = 0;
   function cycleLastCommentArea() {
      if (theObj = document.getElementById("GameLastComment")) {
         switch (cycleLCA++ % 3) {
            case 0:
               if (theObj.scrollHeight === theObj.clientHeight) { cycleLastCommentArea(); }
               else { fitLastCommentArea(); }
               break;
            case 1:
               if (theObj.offsetHeight == emPixels(21)) { cycleLastCommentArea(); }
               else { maximizeLastCommentArea(); }
               break;
            case 2:
               if (theObj.offsetHeight == emPixels(4.2)) { cycleLastCommentArea(); }
               else { resetLastCommentArea(); }
               break;
            default:
               break;
         }
      }
   }

   function toggleFitResetLastCommentArea() {
      if (theObj = document.getElementById("GameLastComment")) {
         if ((theObj.scrollHeight === theObj.clientHeight) || (theObj.offsetHeight == emPixels(21))) { resetLastCommentArea(); }
         else { fitLastCommentArea(); }
      }
   }

   function resetLastCommentArea() {
      if (theObj = document.getElementById("GameLastComment")) {
         theObj.style.height = "";
      }
   }

   function fitLastCommentArea() {
      if (theObj = document.getElementById("GameLastComment")) {
         theObj.style.height = "";
         theObj.style.height = theObj.scrollHeight;
      }
   }

   function maximizeLastCommentArea() {
      if (theObj = document.getElementById("GameLastComment")) {
         theObj.style.height = "21em";
      }
   }

</script>

<div class="topMenu">
<div id="GameSelector" class="gameSelector"></div>
<div id="GameSearch" class="gameSearch"></div>
<div id="emMeasure" style="height: 1em;">&nbsp;</div>
</div>

<div class="mainContainer">

<div class="columnsContainer">

<div class="boardColumn">
<center>
<div id="GameBoard" class="gameBoard"></div>
<div id="GameButtons" class="gameButtons"></div>
</center>
</div>

<div class="headerColumn">
<div class="headerItem"><a class="innerHeaderItem" id="GameDate" href="javascript:void(0);" onclick="searchTagDifferent('Date', this.innerHTML); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem"><a class="innerHeaderItem" id="GameSite" href="javascript:void(0);" onclick="searchTagDifferent('Site', this.innerHTML); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><a class="innerHeaderItem" id="GameEvent" href="javascript:void(0);" onclick="searchTagDifferent('Event', this.innerHTML); this.blur();"></a><a class="innerHeaderItem" id="GameSection" href="javascript:void(0);" onclick="searchTagDifferent('Section', this.innerHTML); this.blur();"></a><a class="innerHeaderItem" id="GameStage" href="javascript:void(0);" onclick="searchTagDifferent('Stage', this.innerHTML); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem"><a class="innerHeaderItem" id="GameRound" href="javascript:void(0);" onclick="searchTagDifferent('Round', this.innerHTML.replace('round ', '')); this.blur();"></a><a class="innerHeaderItem" id="GameBoardNum" href="javascript:void(0);" onclick="searchTagDifferent('Board', this.innerHTML); this.blur();"></a><a class="innerHeaderItem" id="GameTimeControl"  href="javascript:void(0);" onclick="searchTagDifferent('TimeControl', this.innerHTML); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><a class="innerHeaderItem" id="GameECO" href="javascript:void(0);" onclick="searchTag('ECO', this.innerHTML); this.blur();"></a><a class="innerHeaderItem" id="GameOpening" href="javascript:void(0);" onclick="searchTag('Opening', customPgnHeaderTag('Opening')); this.blur();"></a><a class="innerHeaderItem" id="GameVariation" href="javascript:void(0);" onclick="searchTag('Variation', customPgnHeaderTag('Variation')); this.blur();"></a><a class="innerHeaderItem" id="GameSubVariation" href="javascript:void(0);" onclick="searchTag('SubVariation', customPgnHeaderTag('SubVariation')); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><span class="innerHeaderItem" id="GameWhiteClock"></span><b>&nbsp;</b></div>
<div class="headerItem"><b><a href="javascript:void(0);" onclick="searchPlayer(this.innerHTML, customPgnHeaderTag('WhiteFideId')); this.blur();" class="innerHeaderItem" id="GameWhite"></a></b><span class="innerHeaderItem" id="GameWhiteTitle"></span><span class="innerHeaderItem" id="GameWhiteElo"></span><a class="innerHeaderItem" id="GameWhiteTeam" href="javascript:void(0);" onclick="searchTeam(this.innerHTML); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem"><b><a href="javascript:void(0);" onclick="searchPlayer(this.innerHTML, customPgnHeaderTag('BlackFideId')); this.blur();" class="innerHeaderItem" id="GameBlack"></a></b><span class="innerHeaderItem" id="GameBlackTitle"></span><span class="innerHeaderItem" id="GameBlackElo"></span><a class="innerHeaderItem" id="GameBlackTeam" href="javascript:void(0);" onclick="searchTeam(this.innerHTML); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem"><span class="innerHeaderItem" id="GameBlackClock"></span><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><b><a href="javascript:void(0);" onclick="searchPgnGame(lastSearchPgnExpression, !event.shiftKey); this.blur();" class="innerHeaderItem" id="GameResult"></a></b><span class="innerHeaderItem" id="GameTermination"></span><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><span class="innerHeaderItem analysisMove move notranslate" id="GameAnalysisMove"></span><a href="javascript:void(0);" onclick="showExtraAnalysisInfo(); this.blur();" onmouseout="hideExtraAnalysisInfo();" class="innerHeaderItem analysisEval" id="GameAnalysisEval"></a><a href="javascript:void(0);" onclick="goToMissingAnalysis(); this.blur();" class="innerHeaderItem move analysisPv notranslate" id="GameAnalysisPv"></a><b>&nbsp;</b></div>
<div class="headerItem headerSpacer" id="GameAnnotationMeasure"><b>&nbsp;</b></div>
<canvas class="gameAnnotationGraph" id="GameAnnotationGraph" height="1" width="1" onclick="annotationGraphClick(); this.blur();" onmousemove="annotationGraphMousemove();" onmouseover="annotationGraphMouseover();" onmouseout="annotationGraphMouseout();" title="engine annotation graph"></canvas>
</div>

</div>

<div class="toggleAnalysis" id="toggleAnalysis"><a class="toggleAnalysisLink" style="visibility: hidden;" id="toggleAnalysisLink" href="javascript:void(0);" onclick="userToggleAnalysis(); this.blur();" title="toggle engine analysis">+</a></div>
<div class="toggleComments" id="toggleComments"><a class="toggleCommentsLink" id="toggleCommentsLink" href="javascript:void(0);" onClick="SetCommentsIntoMoveText(!commentsIntoMoveText); var oldPly = CurrentPly; var oldVar = CurrentVar; Init(); GoToMove(oldPly, oldVar); this.blur();" title="toggle show comments in game text for this page; click square F7 instead to save setting"></a></div>

<div class="lastMoveAndComment" id="lastMoveAndComment">
<div class="lastMoveAndVariations">
<span class="lastMove" id="GameLastMove" title="last move"></span>
<span class="lastVariations" id="GameLastVariations" title="last move alternatives"></span>&nbsp;
</div>
<div class="nextMoveAndVariations">
<span class="nextVariations" id="GameNextVariations" title="next move alternatives"></span>&nbsp;
<span class="nextMove" id="GameNextMove" title="next move"></span><a class="nextButton" href="javascript:void(0);" onclick="GoToMove(event.shiftKey ? StartPlyVar[CurrentVar] : CurrentPly - 1); this.blur();" title="move backward">&lt;</a>
</div>
<div>&nbsp;</div>
<div class="lastComment" title="current position comment" id="GameLastComment"></div>
</div>
</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td valign="bottom" align="right">
&nbsp;&nbsp;&nbsp;<a name="moves" href="#moves" style="color: gray; font-size: 66%;">moves</a>&nbsp;&nbsp;&nbsp;<a href="#view" style="color: gray; font-size: 66%;">board</a>&nbsp;&nbsp;&nbsp;<a href="#top" style="color: gray; font-size: 66%;">form</a>
</tr></table>

<div class="mainContainer">
<div id="moveText" class="moveText"><span id="GameText"></span> <span class="move" id="ResultAtGametextEnd"></span></div>
</div>


<script type="text/javascript">

   var annotationSupported = (window.Worker && document.getElementById("GameAnnotationGraph").getContext);

   var analysisStarted = false;
   function toggleAnalysis() {
      if (analysisStarted) { stopAnalysis(); }
      else { restartAnalysis(); }
   }

   function restartAnalysis() {
      StartEngineAnalysis();
      analysisStarted = true;
      if (theObj = document.getElementById("toggleAnalysisLink")) { theObj.innerHTML = "&times;"; }
      updateAnnotationGraph();
      updateAnalysisHeader();
   }

   function stopAnalysis() {
      StopBackgroundEngine();
      analysisStarted = false;
      if (theObj = document.getElementById("toggleAnalysisLink")) { theObj.innerHTML = "+"; }
      clearAnnotationGraph();
      clearAnalysisHeader();
   }

   var fenPositions;
   resetFenPositions(-1);

   function resetFenPositions(annGame) {
      if (annGame == -1) { fenPositions = new Array(); }
      else { fenPositions[annGame] = new Array(); }
   }

   var annotationBarWidth;
   function updateAnnotationGraph() {
      if (!annotationSupported) { return; }
      var index;
      if (!analysisStarted) { clearAnnotationGraph(); }
      else if (theObj = document.getElementById("GameAnnotationGraph")) {
         annEval = new Array();
         for (annPly = StartPly; annPly <= StartPly+PlyNumber; annPly++) {
            annEval[annPly] = annPly === CurrentPly ? 0 : null;
            if ((typeof(fenPositions[currentGame]) != "undefined") && (typeof(fenPositions[currentGame][annPly]) != "undefined")) {
               index = cache_fen_indexOf(fenPositions[currentGame][annPly]);
               if (index != -1) { annEval[annPly] = cache_ev[index]; }
            }
            if (annEval[annPly] !== null) { annEval[annPly] = annEval[annPly] < 0 ? -1 + Math.pow(2, annEval[annPly]) : 1 - Math.pow(2, -annEval[annPly]); }
         }

         theObj.width = canvasWidth = graphCanvasWidth();
         theObj.height = canvasHeight = graphCanvasHeight();

         annotationPlyBlock = 20;
         annotationBarWidth = canvasWidth / (Math.ceil((PlyNumberMax + 2) / annotationPlyBlock) * annotationPlyBlock);
         barOverlap = Math.ceil(annotationBarWidth / 20);
         lineHeight = Math.ceil(canvasHeight / 100);
         lineTop = Math.floor((canvasHeight - lineHeight) / 2);
         lineBottom = lineTop + lineHeight;
         maxBarHeight = lineTop + barOverlap;

         context = theObj.getContext("2d");
         context.beginPath();
         thisBarTopLeftX = 0;
         thisBarHeight = lineHeight;
         thisBarTopLeftY = lineTop;
         context.rect(thisBarTopLeftX, thisBarTopLeftY, (PlyNumber + 1) * annotationBarWidth + barOverlap, thisBarHeight);
         context.fillStyle = "#D9D9D9";
         context.fill();
         context.fillStyle = "#666666";
         highlightTopLeftX = highlightTopLeftY = highlightBarHeight = null;
         for (annPly = StartPly; annPly <= StartPly + PlyNumber; annPly++) {
            if ((annEval[annPly] !== null) || (annPly === CurrentPly)) {
               thisBarTopLeftX = (annPly - StartPly) * annotationBarWidth;
               if (annEval[annPly] >= 0) {
                  thisBarHeight = Math.max(  annEval[annPly] * maxBarHeight, lineHeight);
                  thisBarTopLeftY = lineBottom - thisBarHeight;
               } else {
                  thisBarHeight = Math.max(- annEval[annPly] * maxBarHeight, lineHeight);
                  thisBarTopLeftY = lineTop;
               }
               if (annPly !== CurrentPly) {
                  context.beginPath();
                  context.rect(thisBarTopLeftX, thisBarTopLeftY, annotationBarWidth + barOverlap, thisBarHeight);
                  context.fill();
               } else {
                  highlightTopLeftX = thisBarTopLeftX;
                  highlightTopLeftY = thisBarTopLeftY;
                  highlightBarHeight = thisBarHeight;
               }
            }
         }
         if (highlightBarHeight !== null) {
            context.beginPath();
            context.rect(highlightTopLeftX, highlightTopLeftY, annotationBarWidth + barOverlap, highlightBarHeight);
            context.fillStyle = "#FF6633";
            context.fill();
         }
      }
   }

   function clearAnnotationGraph() {
      if (!annotationSupported) { return; }
      if (theObj = document.getElementById("GameAnnotationGraph")) {
         context = theObj.getContext("2d");
         theObj.width = graphCanvasWidth();
         theObj.height = graphCanvasHeight();
         context.clearRect(0, 0, theObj.width, theObj.height);
      }
   }

   // set canvas size, check calculations if headerItem and headerSpacer are changed in chess-games-viewer.css
   function graphCanvasWidth() {
      if (theMeasureObject = document.getElementById("GameAnnotationMeasure")) {
         return theMeasureObject.offsetWidth;
      } else { return 300; }
   }
   function graphCanvasHeight() {
      if (theMeasureObject = document.getElementById("GameAnnotationMeasure")) {
         return (theObj.height = 8 * theMeasureObject.offsetHeight);
      } else { return 100; }
   }

   function updateAnalysisHeader() {
      if (freezeAnalysisHeader) { return; }
      if (!analysisStarted) { clearAnalysisHeader(); return; }

      annPly = (lastMousemoveAnnPly == -1) ? CurrentPly : lastMousemoveAnnPly;

      if (theObj = document.getElementById("GameAnalysisMove")) {
         if ((annPly > StartPly) && (annPly <= StartPly + PlyNumber)) {
            annMove = (Math.floor(annPly / 2) + (annPly % 2)) + (annPly % 2 ? ". " : "... ") + Moves[annPly - 1];
         } else {
            annMove = "&middot;";
         }
         theObj.innerHTML = annMove;
      }

      annEval = (lastMousemoveAnnPly == -1) ? "&middot;" : "";
      annPv = "";
      if ((typeof(fenPositions[currentGame]) != "undefined") && (typeof(fenPositions[currentGame][annPly]) != "undefined")) {
         var index = cache_fen_indexOf(fenPositions[currentGame][annPly]);
         if (index != -1) {
            annEval = cache_ev[index];
            annPv = cache_pv[index];
         }
      }

      if (theObj = document.getElementById("GameAnalysisEval")) {
         theObj.innerHTML = (annEval || annEval === 0) ? annEvalNag(annEval) : "";
         theObj.title = (annEval || annEval === 0) ? "engine evaluation: " + (annEval > 0 ? "+" : "") + annEval : "";
      }
      if (theObj = document.getElementById("GameAnalysisPv")) {
         theObj.innerHTML = annPv ? annPv : "";
         theObj.title = annPv ? "engine principal variation: " + annPv : "";
      }
   }

   function annEvalNag(ev) {
     if ((ev === null) || (ev === "") || (isNaN(ev = parseFloat(ev)))) { return ""; }
     if (ev < -3.95) { return NAG[19]; } // -+
     if (ev >  3.95) { return NAG[18]; } // +-
     if (ev < -1.35) { return NAG[17]; } // -/+
     if (ev >  1.35) { return NAG[16]; } // +/-
     if (ev < -0.35) { return NAG[15]; } // =/+
     if (ev >  0.35) { return NAG[14]; } // +/=
     return NAG[11];                     // =
   }

   function clearAnalysisHeader() {
      if (freezeAnalysisHeader) { return; }
      if (theObj = document.getElementById("GameAnalysisMove")) { theObj.innerHTML = ""; }
      if (theObj = document.getElementById("GameAnalysisEval")) { theObj.innerHTML = ""; }
      if (theObj = document.getElementById("GameAnalysisPv")) { theObj.innerHTML = ""; }
   }


   lastMousemoveAnnPly = -1;
   lastMousemoveAnnGame = -1;

   function annotationGraphMouseover() {
   }

   function annotationGraphMouseout() {
      if (theObj = document.getElementById("GameAnalysisMove")) { theObj.style.display = ""; }
      if (theObj = document.getElementById("GameAnalysisEval")) { theObj.style.fontWeight = ""; }
      lastMousemoveAnnPly = -1;
      lastMousemoveAnnGame = -1;
      if (analysisStarted) { updateAnalysisHeader(); }
   }

   function annotationGraphMousemove() {
      newMousemoveAnnPly = StartPly + Math.floor((window.event.pageX - document.getElementById("GameAnnotationGraph").offsetLeft) / annotationBarWidth);
      if ((newMousemoveAnnPly !== lastMousemoveAnnPly) || (currentGame !== lastMousemoveAnnGame)) {
         lastMousemoveAnnPly = newMousemoveAnnPly <= StartPly + PlyNumber ? newMousemoveAnnPly : -1;
         lastMousemoveAnnGame = currentGame;
         onGraph = ((lastMousemoveAnnPly >= StartPly) && (lastMousemoveAnnPly <= StartPly + PlyNumber));
         if (theObj = document.getElementById("GameAnalysisMove")) { theObj.style.display = onGraph ? "inline-block" : ""; }
         if (theObj = document.getElementById("GameAnalysisEval")) { theObj.style.fontWeight = onGraph ? "normal" : ""; }
         if (analysisStarted) { updateAnalysisHeader(); }
      }
   }

   function annotationGraphClick() {
      if ((analysisStarted) && (typeof(annotationBarWidth) != "undefined")) {
         annPly = StartPly + Math.floor((window.event.pageX - document.getElementById("GameAnnotationGraph").offsetLeft) / annotationBarWidth);
         if ((annPly >= StartPly) && (annPly <= StartPly + PlyNumber)) {
            if (event.shiftKey) { save_cache_to_localStorage(); }
            else { GoToMove(annPly); }
         }
      }
   }

   var freezeAnalysisHeader = false;
   function showExtraAnalysisInfo() {
      if (theObj = document.getElementById("GameAnalysisPv")) {
         freezeAnalysisHeader = true;
         var index = cache_fen_indexOf(fenPositions[currentGame][CurrentPly]);
         theObj.innerHTML = "<span class='analysisExtraInfo'>" + (index != -1 ? "eval " + (cache_ev[index] > 0 ? "+" : "") + cache_ev[index] + "<span class='move'>p</span>": "&middot;") + "<span style='margin-left:2em;'>nps &le; " + num2string(g_topNodesPerSecond) + "</span></span>";
         if (theObj = document.getElementById("GameAnalysisEval")) { theObj.style.color = "transparent"; }
      }
   }

   function hideExtraAnalysisInfo() {
      if (freezeAnalysisHeader) {
         freezeAnalysisHeader = false;
         if (theObj = document.getElementById("GameAnalysisEval")) { theObj.style.color = ""; }
         updateAnalysisHeader();
      }
   }

   function num2string(num) {
      if (num >= Math.pow(10, 9)) { num = Math.floor(num / Math.pow(10, 9)) + "G"; }
      else if (num >= Math.pow(10, 6)) { num = Math.floor(num / Math.pow(10, 6)) + "M"; }
      else if (num >= Math.pow(10, 3)) { num = Math.floor(num / Math.pow(10, 3)) + "K"; }
      else { num = num + ""; }
      return num;
   }


   annotateInProgress = false;
   minAnnotationDelay = minAutoplayDelay;
   maxAnnotationDelay = maxAutoplayDelay;
   annotationDelayDefault = 15;
   function annotateGame() {
      if ((checkEngineUnderstandsGameAndWarn()) && (annotationDelay = prompt("Automatic game annotation from the current position, please do not interact with the chessboard until the analysis has reached the last available move.\\n\\nEnter engine analysis time per move, in seconds, between " + (minAnnotationDelay/1000) + " and " + (maxAnnotationDelay/1000) + ":", annotationDelayDefault))) {
         if (isNaN(annotationDelay = parseInt(annotationDelay, 10))) { annotationDelay = annotationDelayDefault; }
         else { annotationDelay = annotationDelay * 1000; }
         annotationDelay = Math.min(maxAnnotationDelay, Math.max(minAnnotationDelay, annotationDelay));
         SetAutoPlay(false);
         if (!analysisStarted) {
           scanGameForFen();
           toggleAnalysis();
         }
         SetAutoplayDelay(annotationDelay);
         SetAutoPlay(true);
         annotateInProgress = true;
      }
   }

   function engineUnderstandsGame(gameNum) {
      return gameIsNormalChess(gameNum);
   }

   function checkEngineUnderstandsGameAndWarn() {
      retVal = engineUnderstandsGame(currentGame);
      if (!retVal) { alert("warning: engine analysis not available for chess variants"); }
      return retVal;
   }

   function userToggleAnalysis() {
      if (checkEngineUnderstandsGameAndWarn()) {
         if (!analysisStarted) { scanGameForFen(); }
         toggleAnalysis();
      }
   }

   function scanGameForFen() {
      savedCurrentPly = CurrentPly;
      savedCurrentVar = CurrentVar;
      if (wasAutoPlayOn = isAutoPlayOn) { SetAutoPlay(false); }
      MoveForward(StartPly + PlyNumber - savedCurrentPly, CurrentVar, true);
      resetFenPositions(currentGame);
      while (true) {
         fenPositions[currentGame][CurrentPly] = CurrentFEN();
         if (CurrentPly === StartPly) { break; }
         MoveBackward(1, true);
      }
      MoveForward(savedCurrentPly - StartPly, savedCurrentVar, true);
      updateAnnotationGraph();
      updateAnalysisHeader();
      if (wasAutoPlayOn) { SetAutoPlay(true); }
   }

   function goToMissingAnalysis(forward) {
      if (!analysisStarted) { return; }
      if ((typeof(fenPositions[currentGame]) == "undefined") || (typeof(fenPositions[currentGame][CurrentPly]) == "undefined")) { return; }
      if (cache_fen_indexOf(fenPositions[currentGame][CurrentPly]) == -1) { return; }

      if (typeof(forward) == "undefined") {
         forward = ((typeof(event) != "undefined") && (typeof(event.shiftKey) != "undefined")) ? !event.shiftKey : true;
      }
      if (wasAutoPlayOn = isAutoPlayOn) { SetAutoPlay(false); }
      for (var thisPly = CurrentPly + (forward ? 1 : -1); ; thisPly = thisPly + (forward ? 1 : -1)) {
         if (forward) { if (thisPly > StartPly + PlyNumber) { thisPly = StartPly; } }
         else { if (thisPly < StartPly) { thisPly = StartPly + PlyNumber; } }
         if (thisPly === CurrentPly) { break; }
         if ((typeof(fenPositions[currentGame]) == "undefined") || (typeof(fenPositions[currentGame][thisPly]) == "undefined")) { break; }
         if (cache_fen_indexOf(fenPositions[currentGame][thisPly]) == -1) { GoToMove(thisPly); break; }
      }
      if (wasAutoPlayOn) { SetAutoPlay(true); }
   }


   // D7
   boardShortcut("D7", "toggle highlight last move and save setting", function(t,e){ SetHighlight(!highlightOption); setHighlightOptionToLocalStorage(); });
   // F7
   boardShortcut("F7", "toggle show comments in game text and save setting", function(t,e){ if (e.shiftKey) { SetCommentsOnSeparateLines(!commentsOnSeparateLines); } else { SetCommentsIntoMoveText(!commentsIntoMoveText); } oldPly = CurrentPly; Init(); GoToMove(oldPly); if (e.shiftKey) { setCommentsOnSeparateLinesToLocalStorage(); } else { setCommentsIntoMoveTextToLocalStorage(); } });
   // F5
   boardShortcut("F5", "adjust last move and current comment text area, if present", function(t,e){ toggleFitResetLastCommentArea(); });

   if (annotationSupported) {
      // G5
      boardShortcut("G5", "annotate game", function(t,e){ annotateGame(); });
      // H5
      boardShortcut("H5", "toggle engine analysis", function(t,e){ userToggleAnalysis(); });
   }


   var pgn4web_chess_engine_id = "garbochess-pgn4web-" + pgn4web_version;

   var engineWorker = "garbochess/garbochess.js";

   var g_backgroundEngine;
   var g_topNodesPerSecond = 0;
   var g_ev = "";
   var g_maxEv = 99.9;
   var g_pv = "";
   var g_nodes = "";
   var g_initError;
   var g_lastFenError = "";

   function InitializeBackgroundEngine() {

      if (!g_backgroundEngine) {
         try {
            g_backgroundEngine = new Worker(engineWorker);
            g_backgroundEngine.addEventListener("message", function (e) {
               if ((e.data.match("^pv")) && (fenString == CurrentFEN())) {
                  if (matches = e.data.substr(3, e.data.length - 3).match(/Ply:(\d+) Score:(-*\d+) Nodes:(\d+) NPS:(\d+) (.*)/)) {
                     ply = parseInt(matches[1], 10);
                     if (isNaN(g_ev = parseInt(matches[2], 10))) {
                        g_ev = "";
                     } else {
                        g_ev = Math.round(g_ev / 100) / 10;
                        if (g_ev < -g_maxEv) { g_ev = -g_maxEv; } else if (g_ev > g_maxEv) { g_ev = g_maxEv; }
                        if (fenString.indexOf(" b ") !== -1) { g_ev = -g_ev; }
                     }
                     g_nodes = parseInt(matches[3], 10);
                     nodesPerSecond = parseInt(matches[4], 10);
                     g_topNodesPerSecond = Math.max(nodesPerSecond, g_topNodesPerSecond);
                     g_pv = matches[5].replace(/(^\s+|\s*\+|\s+$)/g, "").replace(/\s*stalemate/, "=").replace(/\s*checkmate/, "#");
                     if (validateSearchWithCache()) {
                        updateAnnotationGraph();
                        updateAnalysisHeader();
                     }
                     if (theObject = document.getElementById("GameEval")) {
                        theObject.innerHTML = ev2NAG(g_ev);
                        theObject.title = (g_ev > 0 ? " +" : " ") + g_ev + (g_ev == Math.floor(g_ev) ? ".0 " : " ");
                     }
                     if (theObject = document.getElementById("GameMoves")) {
                        theObject.innerHTML = g_pv;
                        theObject.title = g_pv;
                     }
                     if (detectGameEnd(g_pv, "")) { StopBackgroundEngine(); }
                  }
               } else if (e.data.match("^message Invalid FEN")) {
                  if (theObject = document.getElementById("GameEval")) {
                     theObject.innerHTML = NAG[2];
                     theObject.title = "?";
                  }
                  if (theObject = document.getElementById("GameMoves")) {
                     theObject.innerHTML = "invalid position";
                     theObject.title = e.data.replace(/^message /, "");
                  }
                  if (fenString != g_lastFenError) {
                     g_lastFenError = fenString;
                     myAlert("error (engine): " + e.data.replace(/^message /, "") + "\\n" + fenString, false);
                  }
               }
            });
            g_initError = false;
            return true;
         } catch(e) {
            if (theObject = document.getElementById("GameEval")) {
               theObject.innerHTML = translateNAGs("$255") + "<span class='NAGs'>&nbsp;&nbsp;&nbsp;</span>" + translateNAGs("$147");
               theObject.title = "engine analysis unavailable";
            }
            if (theObject = document.getElementById("GameMoves")) {
               theObject.innerHTML = "&nbsp;";
               theObject.title = "";
            }
            if (!g_initError) {
               g_initError = true;
               myAlert("warning: engine exception " + e);
            }
            return false;
         }
      }
   }

   var localStorage_supported;
   try { localStorage_supported = (("localStorage" in window) && (window["localStorage"] !== null)); }
   catch (e) { localStorage_supported = false; }

   function load_cache_from_localStorage() {
      if (!localStorage_supported) { return; }
      if (pgn4web_chess_engine_id != localStorage["pgn4web_chess_viewer_engine_id"]) {
         clear_cache_from_localStorage();
         localStorage["pgn4web_chess_viewer_engine_id"] = pgn4web_chess_engine_id;
         return;
      }
      if (cache_pointer = localStorage["pgn4web_chess_viewer_engine_cache_pointer"]) { cache_pointer = parseInt(cache_pointer, 10) % cache_max; }
      else { cache_pointer = -1; }
      if (cache_fen = localStorage["pgn4web_chess_viewer_engine_cache_fen"]) { cache_fen = cache_fen.split(","); }
      else { cache_fen = new Array(); }
      if (cache_ev = localStorage["pgn4web_chess_viewer_engine_cache_ev"]) { cache_ev = cache_ev.split(","); }
      else { cache_ev = new Array(); }
      if (cache_pv = localStorage["pgn4web_chess_viewer_engine_cache_pv"]) { cache_pv = cache_pv.split(","); }
      else { cache_pv = new Array(); }
      if (cache_nodes = localStorage["pgn4web_chess_viewer_engine_cache_nodes"]) { cache_nodes = cache_nodes.split(","); }
      else { cache_nodes = new Array(); }
      cache_needs_sync = 0;
      if ((cache_fen.length !== cache_ev.length) || (cache_fen.length !== cache_pv.length) || (cache_fen.length !== cache_nodes.length)) {
         clear_cache_from_localStorage();
         cache_clear();
      }
   }

   function save_cache_to_localStorage() {
      if (!localStorage_supported) { return; }
      if (!cache_needs_sync) { return; }
      localStorage["pgn4web_chess_viewer_engine_cache_pointer"] = cache_pointer;
      localStorage["pgn4web_chess_viewer_engine_cache_fen"] = cache_fen.toString();
      localStorage["pgn4web_chess_viewer_engine_cache_ev"] = cache_ev.toString();
      localStorage["pgn4web_chess_viewer_engine_cache_pv"] = cache_pv.toString();
      localStorage["pgn4web_chess_viewer_engine_cache_nodes"] = cache_nodes.toString();
      cache_needs_sync = 0;
   }

   function clear_cache_from_localStorage() {
      if (!localStorage_supported) { return; }
      localStorage.removeItem("pgn4web_chess_viewer_engine_cache_pointer");
      localStorage.removeItem("pgn4web_chess_viewer_engine_cache_fen");
      localStorage.removeItem("pgn4web_chess_viewer_engine_cache_ev");
      localStorage.removeItem("pgn4web_chess_viewer_engine_cache_pv");
      localStorage.removeItem("pgn4web_chess_viewer_engine_cache_nodes");
      cache_needs_sync++;
   }

   function cacheDebugInfo() {
      var dbg = "";
      if (localStorage_supported) {
         dbg += " cache=";
         try {
            dbg += num2string(localStorage["pgn4web_chess_viewer_engine_cache_pointer"].length + localStorage["pgn4web_chess_viewer_engine_cache_fen"].length + localStorage["pgn4web_chess_viewer_engine_cache_ev"].length + localStorage["pgn4web_chess_viewer_engine_cache_pv"].length + localStorage["pgn4web_chess_viewer_engine_cache_nodes"].length);
         } catch(e) {
            dbg += "0";
         }
      }
      return dbg;
   }

   var cache_pointer = -1;
   var cache_max = 8000; // ~ 64 games of 60 moves ~ 1MB of local storage
   var cache_fen = new Array();
   var cache_ev = new Array();
   var cache_pv = new Array();
   var cache_nodes = new Array();

   var cache_needs_sync = 0;

   load_cache_from_localStorage();

   function validateSearchWithCache() {
      var retVal = false;
      var minNodesForAnnotation = 12345;
      if ((g_nodes < minNodesForAnnotation) && (g_ev < g_maxEv) && (g_ev > -g_maxEv) && (g_ev !== 0)) { return retVal; }
      var id = cache_fen_indexOf(fenString);
      if (id == -1) {
         cache_last = cache_pointer = (cache_pointer + 1) % cache_max;
         cache_fen[cache_pointer] = fenString;
         cache_ev[cache_pointer] = g_ev;
         cache_pv[cache_pointer] = g_pv;
         cache_nodes[cache_pointer] = g_nodes;
         cache_needs_sync++;
         retVal = true;
      } else {
         if (g_nodes > cache_nodes[id]) {
            cache_ev[id] = g_ev;
            cache_pv[id] = g_pv;
            cache_nodes[id] = g_nodes;
            cache_needs_sync++;
            retVal = true;
         } else {
            g_ev = parseInt(cache_ev[id], 10);
            g_pv = cache_pv[id];
            g_nodes = parseInt(cache_nodes[id], 10);
         }
      }
      if (cache_needs_sync > 3) { save_cache_to_localStorage(); }
      return retVal;
   }

   var cache_last = 0;
   function cache_fen_indexOf(fenString) {
      if (fenString === cache_fen[cache_last]) { return cache_last; }
      if (typeof(cache_fen.indexOf) == "function") { return (cache_last = cache_fen.indexOf(fenString)); }
      var l = cache_fen.length;
      for (var n = 0; n < l; n++) {
         if (fenString === cache_fen[n]) { return (cache_last = n); }
      }
      return -1;
   }

   function cache_clear() {
      cache_pointer = -1;
      cache_fen = new Array();
      cache_ev = new Array();
      cache_pv = new Array();
      cache_nodes = new Array();
   }


   function StopBackgroundEngine() {
      if (analysisTimeout) { clearTimeout(analysisTimeout); }
      if (g_backgroundEngine) {
         g_backgroundEngine.terminate();
         g_backgroundEngine = null;
      }
   }

   var analysisTimeout;
   function setAnalysisTimeout(seconds) {
      if (analysisTimeout) { clearTimeout(analysisTimeout); }
      analysisTimeout = setTimeout("analysisTimeout = null; save_cache_to_localStorage(); StopBackgroundEngine();", seconds * 1000);
   }

   function StartEngineAnalysis() {
      StopBackgroundEngine();
      if (InitializeBackgroundEngine()) {
         fenString = CurrentFEN();
         g_backgroundEngine.postMessage("position " + fenString);
         g_backgroundEngine.postMessage("analyze");
         setAnalysisTimeout(analysisSeconds);
      }
   }

   var analysisSeconds = 300;

   function detectGameEnd(pv, FEN) {
      if ((pv !== "") && (pv.match(/^[#=]/))) { return true; }
      if (matches = FEN.match(/\s*\S+\s+\S+\s+\S+\s+\S+\s+(\d+)\s+\S+\s*/)) {
         if (parseInt(matches[1], 10) > 100) { return true; }
      }
      return false;
   }

</script>

</body>

</html>


END;
}

function print_footer() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $krabbeStartPosition, $goToView, $zipSupported;

  if ($goToView) { $hashStatement = "window.location.hash = 'view';"; }
  else { $hashStatement = ""; }

  if (($pgnDebugInfo) != "") { $pgnDebugMessage = "message for sysadmin: " . $pgnDebugInfo; }
  else {$pgnDebugMessage = ""; }

  print <<<END

<div><a name="bottom">&nbsp;</a></div>
<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td valign=bottom align=left>
<div style="color: gray; margin-top: 1em; margin-bottom: 1em;">$pgnDebugMessage</div>
</td><td valign=bottom align="right">
&nbsp;&nbsp;&nbsp;<a href="#moves" style="color: gray; font-size: 66%;">moves</a>&nbsp;&nbsp;&nbsp;<a href="#view" style="color: gray; font-size: 66%;">board</a>&nbsp;&nbsp;&nbsp;<a href="#top" style="color: gray; font-size: 66%;">form</a>
</td></tr></table>

<script type="text/javascript">

function pgn4web_onload(e) {
  setPgnUrl("$pgnUrl");
  checkPgnFormTextSize();
  start_pgn4web();
  $hashStatement
}

</script>

</body>

</html>
END;
}

?>
