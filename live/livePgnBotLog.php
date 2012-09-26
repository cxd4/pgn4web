<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);

$logFile = "livePgnBot.log";

print <<<END
<html>

<head>

<title>pgn4web $logFile</title>

<style type="text/css">

body { margin:0; padding:2em; font-family:sans-serif; color:black; }

h1 { }

pre { }

a { color:black; text-decoration:none; }
a.link:hover { color:red; }

</style>

<link rel="shortcut icon" href="../pawn.ico" />

</head>

<body>

<table name="top" border="0" cellpadding="0" cellspacing="0" width="99.9%"><tbody><tr>
<td align="left" valign="middle">
<h1>
<a href="#bottom" onclick="this.blur();">pgn4web</a>
<a href="$logFile" onclick="this.blur();">$logFile</a>
</h1>
</td>
<td align="right" valign="middle">
<img src="../pawns.png" border="0">
</td>
</tr>
</table>

<pre>
END;

if ($handle = fopen($logFile, "rb")) {
  $contents = fread($handle, filesize($logFile));
  fclose($handle);
  print(preg_replace("/(https?:\/\/\S+)/", '<a class="link" href="$1" target="_blank">$1</a>', $contents));
  print('<a name="bottom" href="#top" onclick="this.blur();">---- -- --</a>');
} else {
  print("$logFile not found");
}

print <<<END

</pre>

</body>

</html>
END;

?>
