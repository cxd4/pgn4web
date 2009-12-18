<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

$pgnText = $_REQUEST["pgnText"];
if ($pgnText)
  $pgnBoxText = $pgnText;

$pgnUrl = $_REQUEST["pgnUrl"];
if ($pgnUrl)
  $pgnUrlText = file_get_contents($pgnUrl);

if ($_FILES['pgnFile']['error'] === UPLOAD_ERR_OK) {
  $pgnFileName = $_FILES['pgnFile']['name'];
  $pgnFileSize = $_FILES['userfile']['size'];
  if ($_FILES['pgnFile']['tmp_name'])
    $pgnFileText = file_get_contents($_FILES['pgnFile']['tmp_name']);
}

print <<<END

<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"> 

<title>pgn4web PGN viewer</title> 

<script src="pgn4web.js" type="text/javascript"></script>

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

<form id="textForm" action="$PHP_SELF" method="POST">
<table width="100%" cellspacing=0 cellpadding=3 border=0><tbody><tr><td>
<textarea id="pgnText" name="pgnText" rows=6 style="width:100%;">$pgnText</textarea>
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

<hr>
$pgnBoxText
<hr>
$pgnUrlText
<hr>
$pgnFileText
<hr>

</body>

</html>

END;
?>
