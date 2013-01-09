<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2013 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);

/*
 *  URL parameters:
 *
 *  headlessPage = true | false (default false)
 *  hideForm = true | false (default false)
 *  pgnData (or pgnUrl) = (default null)
 *  pgnText = (default null)
 *
 */

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

$headlessPage = strtolower(get_param("headlessPage", "hp", ""));

$hideForm = strtolower(get_param("hideForm", "hf", ""));
$hideFormCss = ($hideForm == "true") || ($hideForm == "t") ? "display: none;" : "";

$startPosition = '[Event ""] [Site ""] [Date ""] [Round ""] [White ""] [Black ""] [Result ""] ' . ((($hideForm == "true") || ($hideForm == "t")) ? '' : ' { please enter chess games in PGN format using the form at the top of the page }');

if (!($goToView = get_pgn())) {
  $pgnText = preg_match("/^error:/", $pgnStatus) ? '[Event ""] [Site ""] [Date ""] [Round ""] [White ""] [Black ""] [Result ""] { error loading PGN data, click square A8 for more details }' : $startPosition;
}


$presetURLsArray = array();
function addPresetURL($label, $javascriptCode) {
  global $presetURLsArray;
  array_push($presetURLsArray, array('label' => $label, 'javascriptCode' => $javascriptCode));
}

// modify the viewer-preset-URLs.php file to add preset URLs for the viewer's form
include 'viewer-preset-URLs.php';


print_header();
print_form();
check_tmpDir();
print_menu("board");
print_chessboard_one();
print_menu("moves");
print_chessboard_two();
print_footer();
print_menu("bottom");
print_html_close();


function get_param($param, $shortParam, $default) {
  if (isset($_REQUEST[$param]) && stripslashes(rawurldecode($_REQUEST[$param]))) { return stripslashes(rawurldecode($_REQUEST[$param])); }
  if (isset($_REQUEST[$shortParam]) && stripslashes(rawurldecode($_REQUEST[$shortParam]))) { return stripslashes(rawurldecode($_REQUEST[$shortParam])); }
  return $default;
}

function http_response_header_isInvalid($response) {
   return isset($response[0]) ? preg_match("/\b[45]\d\d\b/", $response[0]) : FALSE;
}

function get_pgn() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $startPosition, $goToView, $zipSupported;

  $pgnDebugInfo = $pgnDebugInfo . get_param("debug", "d", "");

  $pgnText = get_param("pgnText", "pt", "");

  $pgnUrl = get_param("pgnData", "pd", "");
  if ($pgnUrl == "") { $pgnUrl = get_param("pgnUrl", "pu", ""); }

  if ($pgnText) {
    $pgnStatus = "info: games from textbox input";
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
    $pgnStatus = "info: games from $pgnUrl";
    $isPgn = preg_match("/\.(pgn|txt)$/i",$pgnUrl);
    $isZip = preg_match("/\.zip$/i",$pgnUrl);
    if ($isZip) {
      if (!$zipSupported) {
        $pgnStatus = "error: zipfile support unavailable, unable to open $pgnUrl";
        return FALSE;
      } else {
        $tempZipName = tempnam($tmpDir, "pgn4webViewer_");
        // $pgnUrlOpts tries forcing following location redirects
        // depending on server configuration, the script might still fail if the ZIP URL is redirected
        $pgnUrlOpts = array("http" => array("follow_location" => TRUE, "max_redirects" => 20));
        $pgnUrlHandle = fopen($pgnUrl, "rb", false, stream_context_create($pgnUrlOpts));
        $tempZipHandle = fopen($tempZipName, "wb");
        $copiedBytes = stream_copy_to_stream($pgnUrlHandle, $tempZipHandle, $fileUploadLimitBytes + 1, 0);
        fclose($pgnUrlHandle);
        fclose($tempZipHandle);
        if ((($copiedBytes > 0) && ($copiedBytes <= $fileUploadLimitBytes)) && (!http_response_header_isInvalid($http_response_header))) {
          $pgnSource = $tempZipName;
        } else {
          $pgnStatus = "error: failed to get $pgnUrl: " . (http_response_header_isInvalid($http_response_header) ? "server error: $http_response_header[0]" : "file not found, file size exceeds $fileUploadLimitText form limit, $fileUploadLimitIniText server limit or server error");
          if (($tempZipName) && (file_exists($tempZipName))) { unlink($tempZipName); }
          return FALSE;
        }
      }
    } else {
      $pgnSource = $pgnUrl;
    }
  } elseif (count($_FILES) == 0) {
    $pgnStatus = "info: no games supplied";
    return FALSE;
  } elseif ($_FILES['pgnFile']['error'] === UPLOAD_ERR_OK) {
    $pgnFileName = $_FILES['pgnFile']['name'];
    $pgnStatus = "info: games from file $pgnFileName";
    $pgnFileSize = $_FILES['pgnFile']['size'];
    if ($pgnFileSize == 0) {
      $pgnStatus = "info: failed uploading games: file not found, file empty or upload error";
      return FALSE;
    } elseif ($pgnFileSize > $fileUploadLimitBytes) {
      $pgnStatus = "error: failed uploading games: file size exceeds $fileUploadLimitText limit";
      return FALSE;
    } else {
      $isPgn = preg_match("/\.(pgn|txt)$/i",$pgnFileName);
      $isZip = preg_match("/\.zip$/i",$pgnFileName);
      $pgnSource = $_FILES['pgnFile']['tmp_name'];
    }
  } else {
    $pgnStatus = "error: failed uploading games: ";
    switch ($_FILES['pgnFile']['error']) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        $pgnStatus = $pgnStatus . "file size exceeds $fileUploadLimitText form limit or $fileUploadLimitIniText server limit";
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
      if ($pgnUrl) { $zipFileString = $pgnUrl; }
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
            $pgnStatus = "error: failed reading $zipFileString content";
            zip_close($pgnZip);
            if (($tempZipName) && (file_exists($tempZipName))) { unlink($tempZipName); }
            return FALSE;
          }
        }
        zip_close($pgnZip);
        if (($tempZipName) && (file_exists($tempZipName))) { unlink($tempZipName); }
        if (!$pgnText) {
          $pgnStatus = "error: games not found in $zipFileString";
         return FALSE;
        } else {
          return TRUE;
        }
      } else {
        if (($tempZipName) && (file_exists($tempZipName))) { unlink($tempZipName); }
        $pgnStatus = "error: failed opening $zipFileString";
        return FALSE;
      }
    } else {
      $pgnStatus = "error: ZIP support unavailable from this server, only PGN files are supported";
      return FALSE;
    }
  }

  if ($isPgn) {
    if ($pgnUrl) { $pgnFileString = $pgnUrl; }
    else { $pgnFileString = "pgn file"; }
    $pgnText = file_get_contents($pgnSource, NULL, NULL, 0, $fileUploadLimitBytes + 1);
    if ((!$pgnText) || (($pgnUrl) && (http_response_header_isInvalid($http_response_header)))) {
       $pgnStatus = "error: failed reading $pgnFileString: " . (http_response_header_isInvalid($http_response_header) ? "server error: $http_response_header[0]" : "file not found or server error");
       return FALSE;
    }
    if ((strlen($pgnText) == 0) || (strlen($pgnText) > $fileUploadLimitBytes)) {
      $pgnStatus = "error: failed reading $pgnFileString: file size exceeds $fileUploadLimitText form limit, $fileUploadLimitIniText server limit or server error";
      return FALSE;
    }
    return TRUE;
  }

  if ($pgnSource) {
    if ($zipSupported) {
      $pgnStatus = "error: only PGN and ZIP (zipped pgn) files are supported";
    } else {
      $pgnStatus = "error: only PGN files are supported, ZIP support unavailable from this server";
    }
    return FALSE;
  }

  return TRUE;
}

function check_tmpDir() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $startPosition, $goToView, $zipSupported;

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

function print_menu($item) {

  print <<<END

<div style="height: 0.2em; overflow: hidden;"><a name="$item">&nbsp;</a></div>
<div style="width: 100%; text-align: right; font-size: 66%; padding-bottom: 0.5em;">
&nbsp;&nbsp;&nbsp;&nbsp;<a href="#bottom" style="color: gray;" onclick="this.blur();">bottom</a>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="#moves" style="color: gray;" onclick="this.blur();">moves</a>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="#board" style="color: gray;" onclick="this.blur();">board</a>
&nbsp;&nbsp;&nbsp;&nbsp;<a href="#top" style="color: gray;" onclick="this.blur();">top</a>
</div>

END;
}

function print_header() {

  global $headlessPage;

  if (($headlessPage == "true") || ($headlessPage == "t")) {
     $headClass = "  display: none;";
  } else {
     $headClass = "";
  }

  print <<<END
<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">

<meta name="viewport" content="width=800">

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
  overflow-y: scroll;
}

div, span, table, tr, td {
  font-family: 'pgn4web Liberation Sans', sans-serif; /* fixes IE9 body css issue */
  font-size: 16px; /* fixes Opera table css issue */
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

<body onresize="if (typeof(updateAnnotationGraph) != 'undefined') { updateAnnotationGraph(); }">

<table class="headClass" border="0" cellpadding="0" cellspacing="0" width="100%"><tbody><tr>
<td align="left" valign="middle">
<h1 style="font-family: sans-serif; color: red;"><a style="color: red;" href=.>pgn4web</a> games viewer</h1>
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
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $startPosition, $goToView, $zipSupported;
  global $headlessPage, $hideFormCss, $presetURLsArray;

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
    document.getElementById("pgnFormButton").title = "view games from textbox: PGN textbox size is " + document.getElementById("pgnFormText").value.length;
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

    document.getElementById('uploadFormFile').value = "";
    document.getElementById('urlFormText').value = "";

    if (analysisStarted) { stopAnalysis(); }
    firstStart = true;
    start_pgn4web();
    resetAlert();
    myAlert("info: games from textbox input", false, true);

    goToHash("board");
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
  document.getElementById("pgnText").value = '$startPosition';

  if (analysisStarted) { stopAnalysis(); }
  firstStart = true;
  start_pgn4web();
  resetAlert();

  goToHash("top");
}

// fake functions to avoid warnings before pgn4web.js is loaded
function disableShortcutKeysAndStoreStatus() {}
function restoreShortcutKeysStatus() {}

</script>

<table style="margin-bottom: 1.5em; $hideFormCss" width="100%" cellspacing="0" cellpadding="3" border="0"><tbody>

  <tr>
    <td align="left" valign="middle">
      <form id="uploadForm" action="$thisScript" enctype="multipart/form-data" method="POST" style="display: inline;">
        <input id="uploadFormSubmitButton" type="submit" class="formControl" value=" view games from local file " style="width:100%;" title="view games from local file: PGN and ZIP files must be smaller than $fileUploadLimitText (form limit) and $fileUploadLimitIniText (server limit); $debugHelpText" onClick="this.blur(); return checkPgnFile();">
    </td>
    <td colspan="$formVariableColspan" width="100%" align="left" valign="middle">
        <input type="hidden" name="MAX_FILE_SIZE" value="$fileUploadLimitBytes">
        <input id="uploadFormFile" name="pgnFile" type="file" class="formControl" style="width:100%;" title="view games from local file: PGN and ZIP files must be smaller than $fileUploadLimitText (form limit) and $fileUploadLimitIniText (server limit); $debugHelpText" onClick="this.blur();">
      </form>
    </td>
  </tr>

  <tr>
    <td align="left" valign="middle">
      <form id="urlForm" action="$thisScript" method="POST" style="display: inline;">
        <input id="urlFormSubmitButton" type="submit" class="formControl" value=" view games from remote URL " title="view games from remote URL: PGN and ZIP files must be smaller than $fileUploadLimitText (form limit) and $fileUploadLimitIniText (server limit); $debugHelpText" onClick="this.blur(); return checkPgnUrl();">
    </td>
    <td width="100%" align="left" valign="middle">
        <input id="urlFormText" name="pgnUrl" type="text" class="formControl" value="" style="width:100%;" onFocus="disableShortcutKeysAndStoreStatus();" onBlur="restoreShortcutKeysStatus();" title="view games from remote URL: PGN and ZIP files must be smaller than $fileUploadLimitText (form limit) and $fileUploadLimitIniText (server limit); $debugHelpText">
      </form>
    </td>
END;

  if ($presetURLsArray) {
    print('    <td align="right" valign="middle">' . "\n" . '        <select id="urlFormSelect" class="formControl" title="view games from remote URL: select the download URL from the preset options; please support the sites providing the PGN games downloads" onChange="this.blur(); urlFormSelectChange();">' . "\n" . '          <option value="header">preset URL</option>' . "\n");
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
        <input id="pgnFormButton" type="button" class="formControl" value=" view games from textbox " style="width:100%;" onClick="this.blur(); loadPgnFromForm();">
    </td>
    <td colspan="$formVariableColspan" rowspan="2" width="100%" align="right" valign="middle">
        <textarea id="pgnFormText" class="formControl" name="pgnTextbox" rows=4 style="width:100%;" onFocus="disableShortcutKeysAndStoreStatus();" onBlur="restoreShortcutKeysStatus();" onChange="checkPgnFormTextSize();">$pgnTextbox</textarea>
      </form>
    </td>
  </tr>

  <tr>
  <td align="left" valign="bottom">
    <input id="clearButton" type="button" class="formControl" value=" reset viewer " onClick="this.blur(); if (confirm('reset viewer: current PGN games and inputs will be lost')) { reset_viewer(); }" title="reset viewer: current PGN games and inputs will be lost">
  </td>
  </tr>

</tbody></table>

END;
}

function print_chessboard_one() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $startPosition, $goToView, $zipSupported;
  global $hideFormCss;

  print <<<END

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
  width: 100% !important;
  margin-top: 1em;
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
  width: 90% !important;
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

.selectSearchContainer {
  text-align: center;
  padding-bottom: 1.5em;
}

.gameSearch {
  white-space: nowrap;
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

.nextVariations {
  padding-right: 1em;
}

.backButton {
  display: inline-block;
  width: 1em;
  padding-left: 1em;
  color: #808080;
  text-decoration: none;
  text-align: right;
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

<!-- paste your PGN below and make sure you dont specify an external source with SetPgnUrl() -->
<form style="display: none;"><textarea style="display: none;" id="pgnText">

$pgnText

</textarea></form>
<!-- paste your PGN above and make sure you dont specify an external source with SetPgnUrl() -->

<script type="text/javascript">

   pgn4web_engineWindowUrlParameters = "pf=m";

   var highlightOption_default = true;
   var commentsOnSeparateLines_default = false;
   var commentsIntoMoveText_default = true;

   SetImagePath("merida/36");
   SetImageType("png");
   SetHighlightOption(getHighlightOptionFromLocalStorage());
   SetCommentsIntoMoveText(getCommentsIntoMoveTextFromLocalStorage());
   SetCommentsOnSeparateLines(getCommentsOnSeparateLinesFromLocalStorage());
   SetInitialGame(1);
   SetInitialVariation(0);
   SetInitialHalfmove(0);
   SetGameSelectorOptions(null, true, 12, 12, 2, 15, 15, 3, 10);
   SetAutostartAutoplay(false);
   SetAutoplayNextGame(false);
   SetAutoplayDelay(2000);
   SetShortcutKeysEnabled(true);

   function getHighlightOptionFromLocalStorage() {
      try { ho = (localStorage.getItem("pgn4web_chess_viewer_highlightOption") != "false"); }
      catch(e) { return highlightOption_default; }
      return ho === null ? highlightOption_default : ho;
   }
   function setHighlightOptionToLocalStorage(ho) {
      try { localStorage.setItem("pgn4web_chess_viewer_highlightOption", ho ? "true" : "false"); }
      catch(e) { return false; }
      return true;
   }

   function getCommentsIntoMoveTextFromLocalStorage() {
      try { cimt = !(localStorage.getItem("pgn4web_chess_viewer_commentsIntoMoveText") == "false"); }
      catch(e) { return commentsIntoMoveText_default; }
      return cimt === null ? commentsIntoMoveText_default : cimt;
   }
   function setCommentsIntoMoveTextToLocalStorage(cimt) {
      try { localStorage.setItem("pgn4web_chess_viewer_commentsIntoMoveText", cimt ? "true" : "false"); }
      catch(e) { return false; }
      return true;
   }

   function getCommentsOnSeparateLinesFromLocalStorage() {
      try { cosl = (localStorage.getItem("pgn4web_chess_viewer_commentsOnSeparateLines") == "true"); }
      catch(e) { return commentsOnSeparateLines_default; }
      return cosl === null ? commentsOnSeparateLines_default : cosl;
   }
   function setCommentsOnSeparateLinesToLocalStorage(cosl) {
      try { localStorage.setItem("pgn4web_chess_viewer_commentsOnSeparateLines", cosl ? "true" : "false"); }
      catch(e) { return false; }
      return true;
   }

   function searchTag(tag, key, event) {
      searchPgnGame('\\\\[\\\\s*' + tag + '\\\\s*"' + fixRegExp(key) + '"\\\\s*\\\\]', event.shiftKey);
   }
   function searchTagDifferent(tag, key, event) {
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
         stopAnnotateGame();
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

      if (theObj = document.getElementById("GameNumCurrent")) {
         theObj.innerHTML = currentGame + 1;
         theObj.title = "current game: " + (currentGame + 1);
      }

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
      gameLoadStatus = "$pgnStatus";
      if (gameLoadStatus) {  myAlert(gameLoadStatus, gameLoadStatus.match(/^error:/), !gameLoadStatus.match(/^error:/)); }
      if (theObj = document.getElementById("GameNumInfo")) {
         theObj.style.display = numberOfGames > 1 ? "block" : "none";
      }
      if (theObj = document.getElementById("GameNumTotal")) {
         theObj.innerHTML = numberOfGames;
         theObj.title = "number of games: " + numberOfGames;
      }
   }

   function searchPlayer(name, FideId, event) {
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
         case "#top": goToHash("board"); break;
         case "#board": goToHash("moves"); break;
         case "#zoom": goToHash("moves"); break;
         case "#moves": goToHash("bottom"); break;
         case "#bottom": goToHash("top"); break;
         default: goToHash("board"); break;
      }
   }

   function goToHash(hash) {
      if (hash) { location.hash = ""; }
      else { location.hash = "#board"; }
      location.hash = "#" + hash;
   }

   boardZoomTimeout = null;

   // customShortcutKey_Shift_1 defined by fide-lookup.js
   // customShortcutKey_Shift_2 defined by fide-lookup.js

   function customShortcutKey_Shift_3() { if (boardZoomTimeout) { goToHash("zoom"); } else { boardZoomTimeout = setTimeout("boardZoomTimeout = null;", 333); goToHash("board"); } }
   function customShortcutKey_Shift_4() { cycleHash(); }

   function customShortcutKey_Shift_5() { cycleLastCommentArea(); }

   function customShortcutKey_Shift_6() { if (annotationSupported) { userToggleAnalysis(); } }
   function customShortcutKey_Shift_7() { if (annotationSupported) { goToMissingAnalysis(true); } }

   // customShortcutKey_Shift_8 defined by engine.js
   // customShortcutKey_Shift_9 defined by engine.js
   // customShortcutKey_Shift_0 defined by engine.js


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

   function resetLastCommentArea() {
      if (theObj = document.getElementById("GameLastComment")) {
         theObj.style.height = "";
      }
   }

   function fitLastCommentArea() {
      if (theObj = document.getElementById("GameLastComment")) {
         theObj.style.height = "";
         theObj.style.height = theObj.scrollHeight + "px";
      }
   }

   function maximizeLastCommentArea() {
      if (theObj = document.getElementById("GameLastComment")) {
         theObj.style.height = "21em";
      }
   }

</script>

<div class="selectSearchContainer">
<table border="0" cellpadding="0" cellspacing="0" width="100%"><tbody><tr>
<td colspan="2" align="left" valign="bottom">
<div id="GameSelector" class="gameSelector"></div>
</td>
</tr><tr>
<td width="100%" align="left" valign="top">
<div id="GameSearch" class="gameSearch"></div>
</td><td align="right" valign="bottom">
<div id="GameNumInfo" style="width: 15ex; margin-right: 0.5ex; color: gray; display: none;"><span id="GameNumCurrent" title="current game"></span>&nbsp;/&nbsp;<span id="GameNumTotal" title="number of games"></span></div>
</td>
</tr></tbody></table>
<div id="emMeasure" style="height: 1em;"><a name="zoom" href="#zoom" onclick="this.blur();" style="display: inline-block; width: 392px;">&nbsp;</a></div>
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
<div class="headerItem"><a class="innerHeaderItem" id="GameDate" href="javascript:void(0);" onclick="searchTagDifferent('Date', this.innerHTML, event); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem"><a class="innerHeaderItem" id="GameSite" href="javascript:void(0);" onclick="searchTagDifferent('Site', this.innerHTML, event); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><a class="innerHeaderItem" id="GameEvent" href="javascript:void(0);" onclick="searchTagDifferent('Event', this.innerHTML, event); this.blur();"></a><a class="innerHeaderItem" id="GameSection" href="javascript:void(0);" onclick="searchTagDifferent('Section', this.innerHTML, event); this.blur();"></a><a class="innerHeaderItem" id="GameStage" href="javascript:void(0);" onclick="searchTagDifferent('Stage', this.innerHTML, event); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem"><a class="innerHeaderItem" id="GameRound" href="javascript:void(0);" onclick="searchTagDifferent('Round', this.innerHTML.replace('round ', ''), event); this.blur();"></a><a class="innerHeaderItem" id="GameBoardNum" href="javascript:void(0);" onclick="searchTagDifferent('Board', this.innerHTML, event); this.blur();"></a><a class="innerHeaderItem" id="GameTimeControl"  href="javascript:void(0);" onclick="searchTagDifferent('TimeControl', this.innerHTML, event); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><a class="innerHeaderItem" id="GameECO" href="javascript:void(0);" onclick="searchTag('ECO', this.innerHTML, event); this.blur();"></a><a class="innerHeaderItem" id="GameOpening" href="javascript:void(0);" onclick="searchTag('Opening', customPgnHeaderTag('Opening'), event); this.blur();"></a><a class="innerHeaderItem" id="GameVariation" href="javascript:void(0);" onclick="searchTag('Variation', customPgnHeaderTag('Variation'), event); this.blur();"></a><a class="innerHeaderItem" id="GameSubVariation" href="javascript:void(0);" onclick="searchTag('SubVariation', customPgnHeaderTag('SubVariation'), event); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><span class="innerHeaderItem" id="GameWhiteClock"></span><b>&nbsp;</b></div>
<div class="headerItem"><b><a href="javascript:void(0);" onclick="searchPlayer(this.innerHTML, customPgnHeaderTag('WhiteFideId'), event); this.blur();" class="innerHeaderItem" id="GameWhite"></a></b><span class="innerHeaderItem" id="GameWhiteTitle"></span><span class="innerHeaderItem" id="GameWhiteElo"></span><a class="innerHeaderItem" id="GameWhiteTeam" href="javascript:void(0);" onclick="searchTeam(this.innerHTML); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem"><b><a href="javascript:void(0);" onclick="searchPlayer(this.innerHTML, customPgnHeaderTag('BlackFideId'), event); this.blur();" class="innerHeaderItem" id="GameBlack"></a></b><span class="innerHeaderItem" id="GameBlackTitle"></span><span class="innerHeaderItem" id="GameBlackElo"></span><a class="innerHeaderItem" id="GameBlackTeam" href="javascript:void(0);" onclick="searchTeam(this.innerHTML); this.blur();"></a><b>&nbsp;</b></div>
<div class="headerItem"><span class="innerHeaderItem" id="GameBlackClock"></span><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><b><a href="javascript:void(0);" onclick="searchPgnGame(lastSearchPgnExpression, !event.shiftKey); this.blur();" class="innerHeaderItem" id="GameResult"></a></b><span class="innerHeaderItem" id="GameTermination"></span><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem headerSpacer"><b>&nbsp;</b></div>
<div class="headerItem"><span class="innerHeaderItem analysisMove move notranslate" id="GameAnalysisMove"></span><a href="javascript:void(0);" onclick="if (event.shiftKey) { displayHelp('informant_symbols'); } else { showExtraAnalysisInfo(); } this.blur();" onmouseout="hideExtraAnalysisInfo(event);" class="innerHeaderItem analysisEval" id="GameAnalysisEval"></a><a href="javascript:void(0);" onclick="goToMissingAnalysis(!event.shiftKey); this.blur();" class="innerHeaderItem move analysisPv notranslate" id="GameAnalysisPv"></a><b>&nbsp;</b></div>
<div class="headerItem headerSpacer" id="GameAnnotationMeasure"><b>&nbsp;</b></div>
<canvas class="gameAnnotationGraph" id="GameAnnotationGraph" height="1" width="1" onclick="annotationGraphClick(event); this.blur();" onmousemove="annotationGraphMousemove(event);" onmouseover="annotationGraphMouseover(event);" onmouseout="annotationGraphMouseout(event);" title="engine annotation graph"></canvas>
</div>

</div>

<div class="toggleAnalysis" id="toggleAnalysis"><a class="toggleAnalysisLink" style="visibility: hidden;" id="toggleAnalysisLink" href="javascript:void(0);" onclick="userToggleAnalysis(); this.blur();" title="toggle engine analysis">+</a></div>
<div class="toggleComments" id="toggleComments"><a class="toggleCommentsLink" id="toggleCommentsLink" href="javascript:void(0);" onClick="if (event.shiftKey && commentsIntoMoveText) { cycleLastCommentArea(); } else { SetCommentsIntoMoveText(!commentsIntoMoveText); var oldPly = CurrentPly; var oldVar = CurrentVar; Init(); GoToMove(oldPly, oldVar); } this.blur();" title="toggle show comments in game text for this page; click square F7 instead to save setting"></a></div>

<div class="lastMoveAndComment" id="lastMoveAndComment">
<div class="lastMoveAndVariations">
<span class="lastMove" id="GameLastMove" title="last move"></span>
<span class="lastVariations" id="GameLastVariations" title="last move alternatives"></span>&nbsp;
</div>
<div class="nextMoveAndVariations">
<span class="nextVariations" id="GameNextVariations" title="next move alternatives"></span>&nbsp;
<span class="nextMove" id="GameNextMove" title="next move"></span><a class="backButton" href="javascript:void(0);" onclick="backButton(event); this.blur();" title="move backward">&lt;</a>
</div>
<div>&nbsp;</div>
<div class="lastComment" title="current position comment" id="GameLastComment"></div>
</div>
</div>

END;
}

function print_chessboard_two() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $startPosition, $goToView, $zipSupported;

  print <<<END

<div class="mainContainer">
<div id="moveText" class="moveText"><span id="GameText"></span> <span class="move" id="ResultAtGametextEnd"></span></div>
</div>


<script type="text/javascript">

   var annotationSupported = !!window.Worker;
   try {
      document.getElementById("GameAnnotationGraph").getContext("2d");
   } catch(e) { annotationSupported = false; }

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
      save_cache_to_localStorage();
   }

   var fenPositions;
   var fenPositionsEval;
   var fenPositionsNodes;
   resetFenPositions();

   function resetFenPositions() {
      fenPositions = new Array();
      fenPositionsEval = new Array();
      fenPositionsNodes = new Array();
   }

   var annotationBarWidth;
   function updateAnnotationGraph() {
      if (!annotationSupported) { return; }
      var index;
      if (!analysisStarted) { clearAnnotationGraph(); }
      else if (theObj = document.getElementById("GameAnnotationGraph")) {

         theObj.width = canvasWidth = graphCanvasWidth();
         theObj.height = canvasHeight = graphCanvasHeight();

         annotationPlyBlock = 40;
         annotationBarWidth = canvasWidth / (Math.max(Math.ceil(PlyNumberMax / annotationPlyBlock) * annotationPlyBlock, 2 * annotationPlyBlock) + 2);
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
            annGraphEval = typeof(fenPositionsEval[annPly]) != "undefined" ? fenPositionsEval[annPly] : (annPly === CurrentPly ? 0 : null);
            if (annGraphEval !== null) {
               thisBarTopLeftX = (annPly - StartPly) * annotationBarWidth;
               if (annGraphEval >= 0) {
                  thisBarHeight = Math.max((1 - Math.pow(2, -annGraphEval)) * maxBarHeight, lineHeight);
                  thisBarTopLeftY = lineBottom - thisBarHeight;
               } else {
                  thisBarHeight = Math.max((1 - Math.pow(2,  annGraphEval)) * maxBarHeight, lineHeight);
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
            if (isBlunder(annPly, blunderThreshold)) { annMove += translateNAGs("$4"); }
            else if (isBlunder(annPly, mistakeThreshold)) { annMove += translateNAGs("$2"); }
         } else {
            annMove = "&middot;";
         }
         theObj.innerHTML = annMove;
      }

      annEval = (lastMousemoveAnnPly == -1) ? "&middot;" : "";
      annPv = "";
      if (typeof(fenPositions[annPly]) != "undefined") {
         var index = cache_fen_lastIndexOf(fenPositions[annPly]);
         if (index != -1) {
            annEval = cache_ev[index];
            annPv = cache_pv[index];
         }
      }

      if (theObj = document.getElementById("GameAnalysisEval")) {
         theObj.innerHTML = (annEval || annEval === 0) ? ev2NAG(annEval) : "";
         theObj.title = (annEval || annEval === 0) ? "engine evaluation: " + (annEval > 0 ? "+" : "") + annEval + (annEval == Math.floor(annEval) ? ".0 " : " ") : "";
      }
      if (theObj = document.getElementById("GameAnalysisPv")) {
         theObj.innerHTML = annPv ? annPv : "";
         theObj.title = annPv ? "engine principal variation: " + annPv : "";
      }
   }


   var moderateDefiniteThreshold = 1.85;
   var slightModerateThreshold = 0.85;
   var equalSlightThreshold = 0.25;

   function ev2NAG(ev) {
      if ((ev === null) || (ev === "") || (isNaN(ev = parseFloat(ev)))) { return ""; }
      if (ev < -moderateDefiniteThreshold) { return NAG[19]; } // -+
      if (ev >  moderateDefiniteThreshold) { return NAG[18]; } // +-
      if (ev < -slightModerateThreshold)   { return NAG[17]; } // -/+
      if (ev >  slightModerateThreshold)   { return NAG[16]; } // +/-
      if (ev < -equalSlightThreshold)      { return NAG[15]; } // =/+
      if (ev >  equalSlightThreshold)      { return NAG[14]; } // +/=
      return NAG[11];                                          // =
   }

   function clearAnalysisHeader() {
      if (freezeAnalysisHeader) { return; }
      if (theObj = document.getElementById("GameAnalysisMove")) { theObj.innerHTML = ""; }
      if (theObj = document.getElementById("GameAnalysisEval")) { theObj.innerHTML = ""; }
      if (theObj = document.getElementById("GameAnalysisPv")) { theObj.innerHTML = ""; }
   }


   lastMousemoveAnnPly = -1;
   lastMousemoveAnnGame = -1;

   function annotationGraphMouseover(e) {
   }

   function annotationGraphMouseout(e) {
      if (theObj = document.getElementById("GameAnalysisMove")) { theObj.style.display = ""; }
      if (theObj = document.getElementById("GameAnalysisEval")) { theObj.style.fontWeight = ""; }
      lastMousemoveAnnPly = -1;
      lastMousemoveAnnGame = -1;
      if (analysisStarted) { updateAnalysisHeader(); }
   }

   function annotationGraphMousemove(e) {
      newMousemoveAnnPly = StartPly + Math.floor((e.pageX - document.getElementById("GameAnnotationGraph").offsetLeft) / annotationBarWidth);
      if ((newMousemoveAnnPly !== lastMousemoveAnnPly) || (currentGame !== lastMousemoveAnnGame)) {
         lastMousemoveAnnPly = newMousemoveAnnPly <= StartPly + PlyNumber ? newMousemoveAnnPly : -1;
         lastMousemoveAnnGame = currentGame;
         onGraph = ((lastMousemoveAnnPly >= StartPly) && (lastMousemoveAnnPly <= StartPly + PlyNumber));
         if (theObj = document.getElementById("GameAnalysisMove")) { theObj.style.display = onGraph ? "inline-block" : ""; }
         if (theObj = document.getElementById("GameAnalysisEval")) { theObj.style.fontWeight = onGraph ? "normal" : ""; }
         if (analysisStarted) { updateAnalysisHeader(); }
      }
   }

   function annotationGraphClick(e) {
      if ((analysisStarted) && (typeof(annotationBarWidth) != "undefined")) {
         annPly = StartPly + Math.floor((e.pageX - document.getElementById("GameAnnotationGraph").offsetLeft) / annotationBarWidth);
         if ((annPly >= StartPly) && (annPly <= StartPly + PlyNumber)) {
            if (e.shiftKey) { save_cache_to_localStorage(); }
            else { GoToMove(annPly); }
         }
      }
   }

   var freezeAnalysisHeader = false;
   function showExtraAnalysisInfo() {
      if (theObj = document.getElementById("GameAnalysisPv")) {
         freezeAnalysisHeader = true;
         var thisEval = typeof(fenPositionsEval[CurrentPly]) !== "undefined" ? fenPositionsEval[CurrentPly] : null;
         var thisNodes = typeof(fenPositionsNodes[CurrentPly]) !== "undefined" ? fenPositionsNodes[CurrentPly] : 0;
         var thisHTML = "<span class='analysisExtraInfo'>";
         if (thisEval === null) {
            thisHTML += "&middot;";
         } else {
            thisHTML += "<span class='move' style='margin-right:2em;'>";
            if (CurrentPly === StartPly) {
            thisHTML += "&middot;";
            } else {
               thisHTML += ((Math.floor(CurrentPly / 2) + (CurrentPly % 2)) + (CurrentPly % 2 ? ". " : "... ") + Moves[CurrentPly - 1]);
               thisHTML += (isBlunder(CurrentPly, blunderThreshold) ? translateNAGs("$4") : (isBlunder(CurrentPly, mistakeThreshold) ? translateNAGs("$2") : ""));
            }
            thisHTML += "</span>";
            thisHTML += (thisEval > 0 ? "+" : "") + thisEval + (thisEval == Math.floor(thisEval) ? ".0 " : "") + "<span class='move'>p</span>";
            thisHTML += "<span style='margin-left:2em'>" + num2string(thisNodes) + " nodes</span>";
         }
         thisHTML += "</span>";
         theObj.innerHTML = thisHTML;
         if (theObj = document.getElementById("GameAnalysisEval")) { theObj.style.color = "transparent"; }
      }
   }

   function hideExtraAnalysisInfo(e) {
      if (freezeAnalysisHeader) {
         freezeAnalysisHeader = false;
         if (theObj = document.getElementById("GameAnalysisEval")) { theObj.style.color = ""; }
         updateAnalysisHeader();
      }
   }

   function num2string(num) {
      if (num >= Math.pow(10, 12)) { num = Math.round(num / Math.pow(10, 11)) / 10;  unit = "T"; }
      else if (num >= Math.pow(10, 9)) { num = Math.round(num / Math.pow(10, 8)) / 10;  unit = "G"; }
      else if (num >= Math.pow(10, 6)) { num = Math.round(num / Math.pow(10, 5)) / 10; unit = "M"; }
      else if (num >= Math.pow(10, 3)) { num = Math.round(num / Math.pow(10, 2)) / 10; unit = "K"; }
      else { unit = ""; }
      if ((unit !== "") && (num === Math.floor(num))) { num += ".0"; }
      return num + unit;
   }


   annotateInProgress = false;
   minAnnotationDelay = minAutoplayDelay;
   maxAnnotationDelay = maxAutoplayDelay;
   annotationDelay_default = 15;

   function getAnnotationDelayFromLocalStorage() {
      try { ad = localStorage.getItem("pgn4web_chess_viewer_annotationDelay"); }
      catch(e) { return annotationDelay_default; }
      return ad === null ? annotationDelay_default : ad;
   }
   function setAnnotationDelayToLocalStorage(ad) {
      try { localStorage.setItem("pgn4web_chess_viewer_annotationDelay", ad); }
      catch(e) { return false; }
      return true;
   }

   function annotateGame() {
      if ((checkEngineUnderstandsGameAndWarn()) && (annotationDelay = prompt("Automatic game annotation from the current position, please do not interact with the chessboard until the analysis has reached the last available move.\\n\\nEnter engine analysis time per move, in seconds, between " + (minAnnotationDelay/1000) + " and " + (maxAnnotationDelay/1000) + ":", getAnnotationDelayFromLocalStorage()))) {
         if (isNaN(annotationDelay = parseFloat(annotationDelay))) { annotationDelay = getAnnotationDelayFromLocalStorage(); }
         annotationDelay = annotationDelay * 1000;
         annotationDelay = Math.min(maxAnnotationDelay, Math.max(minAnnotationDelay, annotationDelay));
         setAnnotationDelayToLocalStorage(annotationDelay/1000);
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

   function stopAnnotateGame() {
      if (annotateInProgress) {
         annotateInProgress = false;
         SetAutoplayDelay(2000);
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
      resetFenPositions();
      while (true) {
         fenPositions[CurrentPly] = CurrentFEN();
         if ((index = cache_fen_lastIndexOf(fenPositions[CurrentPly])) != -1) {
            fenPositionsEval[CurrentPly] = cache_ev[index];
            fenPositionsNodes[CurrentPly] = cache_nodes[index];
         }
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
      if (typeof(fenPositions[CurrentPly]) == "undefined") { return; }
      if (typeof(fenPositionsEval[CurrentPly]) == "undefined") { return; }

      if (typeof(forward) == "undefined") {
         forward = ((typeof(event) != "undefined") && (typeof(event.shiftKey) != "undefined")) ? !event.shiftKey : true;
      }
      if (wasAutoPlayOn = isAutoPlayOn) { SetAutoPlay(false); }
      for (var thisPly = CurrentPly + (forward ? 1 : -1); ; thisPly = thisPly + (forward ? 1 : -1)) {
         if (forward) { if (thisPly > StartPly + PlyNumber) { thisPly = StartPly; } }
         else { if (thisPly < StartPly) { thisPly = StartPly + PlyNumber; } }
         if (thisPly === CurrentPly) { break; }
         if (typeof(fenPositions[thisPly]) == "undefined") { break; }
         if (typeof(fenPositionsEval[thisPly]) == "undefined") { GoToMove(thisPly); break; }
      }
      if (wasAutoPlayOn) { SetAutoPlay(true); }
   }


   var blunderThreshold = 1.15;
   var mistakeThreshold = 0.55;
   function isBlunder(thisPly, threshold) {
      return (((typeof(fenPositionsEval[thisPly]) !== "undefined") && (typeof(fenPositionsEval[thisPly - 1]) !== "undefined")) && (((thisPly % 2 ? -1 : 1) * (fenPositionsEval[thisPly] - fenPositionsEval[thisPly - 1])) > threshold));
   }

   function blunderCheck(threshold, backwards) {
      thisPly = StartPly + ((CurrentPly - StartPly + (backwards ? -1 : 1) + (PlyNumber + 1)) % (PlyNumber + 1));
      while (thisPly !== CurrentPly) {
         if (isBlunder(thisPly, threshold)) {
            GoToMove(thisPly);
            break;
         }
         thisPly = StartPly + ((thisPly - StartPly + (backwards ? -1 : 1) + (PlyNumber + 1)) % (PlyNumber + 1));
      }
   }

   function annotationSupportedCheckAndWarnUser() {
      if (!annotationSupported) { alert("warning: engine annotation not available"); }
      return annotationSupported;
   }


   // D7
   boardShortcut("D7", "toggle highlight last move and save setting", function(t,e){ SetHighlight(!highlightOption); setHighlightOptionToLocalStorage(highlightOption); });
   // F7
   boardShortcut("F7", "toggle show comments in game text and save setting", function(t,e){ if (e.shiftKey) { SetCommentsOnSeparateLines(!commentsOnSeparateLines); } else { SetCommentsIntoMoveText(!commentsIntoMoveText); } oldPly = CurrentPly; Init(); GoToMove(oldPly); if (e.shiftKey) { setCommentsOnSeparateLinesToLocalStorage(commentsOnSeparateLines); } else { setCommentsIntoMoveTextToLocalStorage(commentsIntoMoveText); } });
   // F5
   boardShortcut("F5", "adjust last move and current comment text area, if present", function(t,e){ if (e.shiftKey) { resetLastCommentArea(); } else { cycleLastCommentArea(); } });

   // A6
   boardShortcut("A6", "go to previous annotated blunder", function(t,e){ if (annotationSupportedCheckAndWarnUser()) { if (e.shiftKey) { GoToMove(CurrentPly - 1); } else { if (!analysisStarted) { userToggleAnalysis(); } blunderCheck(blunderThreshold, false); } } });
   // B6
   boardShortcut("B6", "go to previous annotated mistake", function(t,e){ if (annotationSupportedCheckAndWarnUser()) { if (e.shiftKey) { GoToMove(CurrentPly - 1); } else { if (!analysisStarted) { userToggleAnalysis(); } blunderCheck(mistakeThreshold, true); } } });
   // G6
   boardShortcut("G6", "go to next annotated mistake", function(t,e){ if (annotationSupportedCheckAndWarnUser()) { if (e.shiftKey) { GoToMove(CurrentPly - 1); } else { if (!analysisStarted) { userToggleAnalysis(); } blunderCheck(mistakeThreshold, false); } } });
   // H6
   boardShortcut("H6", "go to next annotated blunder", function(t,e){ if (annotationSupportedCheckAndWarnUser()) { if (e.shiftKey) { GoToMove(CurrentPly - 1); } else { if (!analysisStarted) { userToggleAnalysis(); } blunderCheck(blunderThreshold, false); } } });

   // G5
   boardShortcut("G5", "automated game annotation", function(t,e){ if (annotationSupportedCheckAndWarnUser()) { if (e.shiftKey) { stopAnnotateGame(); } else { annotateGame(); } } });
   // H5
   boardShortcut("H5", "start/stop annotation", function(t,e){ if (annotationSupportedCheckAndWarnUser()) { if (e.shiftKey) { if (confirm("clear annotation cache, all current and stored annotation data will be lost")) { clear_cache_from_localStorage(); cache_clear(); if (analysisStarted) { updateAnnotationGraph(); updateAnalysisHeader(); } } } else { userToggleAnalysis(); } } });


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
                        fenPositionsEval[CurrentPly] = g_ev;
                        fenPositionsNodes[CurrentPly] = g_nodes;
                        updateAnnotationGraph();
                        updateAnalysisHeader();
                     }
                     if (detectGameEnd(g_pv, "")) { StopBackgroundEngine(); }
                  }
               } else if (e.data.match("^message Invalid FEN")) {
                  stopAnalysis();
                  if (fenString != g_lastFenError) {
                     g_lastFenError = fenString;
                     myAlert("error: engine: " + e.data.replace(/^message /, "") + "\\n" + fenString);
                  }
               }
            }, false);
            g_initError = false;
            return true;
         } catch(e) {
            stopAnalysis();
            if (!g_initError) {
               g_initError = true;
               myAlert("error: engine exception " + e);
            }
            return false;
         }
      }
   }

   var localStorage_supported;
   try { localStorage_supported = (("localStorage" in window) && (window["localStorage"] !== null)); }
   catch(e) { localStorage_supported = false; }

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
      var id = cache_fen_lastIndexOf(fenString);
      if (id == -1) {
         cache_last = cache_pointer = (cache_pointer + 1) % cache_max;
         cache_fen[cache_pointer] = fenString.replace(/\s+\d+\s+\d+\s*$/, "");
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
   function cache_fen_lastIndexOf(fenString) {
      fenString = fenString.replace(/\s+\d+\s+\d+\s*$/, "");
      if (fenString === cache_fen[cache_last]) { return cache_last; }
      if (typeof(cache_fen.lastIndexOf) == "function") { return (cache_last = cache_fen.lastIndexOf(fenString)); }
      for (var n = cache_fen.length - 1; n >= 0; n--) {
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

   function customDebugInfo() {
      var dbg = "annotation=";
      if (!annotationSupported) { dbg += "unavailable"; }
      else if (!analysisStarted) { dbg += "disabled"; }
      else { dbg += (g_backgroundEngine ? ( annotateInProgress ? "gameInProgress" : "pondering") : "idle") + " analysisSeconds=" + analysisSeconds + " topNodesPerSecond=" + num2string(g_topNodesPerSecond) + cacheDebugInfo(); }
      return dbg;
   }

   window.onunload = function() {
      if (analysisStarted) { stopAnalysis(); }
   };

</script>


END;
}

function print_footer() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $debugHelpText, $pgnDebugInfo;
  global $fileUploadLimitIniText, $fileUploadLimitText, $fileUploadLimitBytes, $startPosition, $goToView, $zipSupported;

  if ($goToView) { $hashStatement = "goToHash('board');"; }
  else { $hashStatement = ""; }

  if (($pgnDebugInfo) != "") { $pgnDebugMessage = "message for sysadmin: " . $pgnDebugInfo; }
  else {$pgnDebugMessage = ""; }

  print <<<END

<div style="color: gray; margin-top: 1em; margin-bottom: 1em;">$pgnDebugMessage</div>

<script type="text/javascript">

function pgn4web_onload(e) {
  setPgnUrl("$pgnUrl");
  checkPgnFormTextSize();
  start_pgn4web();
  $hashStatement
}

</script>

END;
}

function print_html_close() {

  print <<<END

</body>

</html>
END;
}

?>
