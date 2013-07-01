<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2013 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ALL | E_STRICT);


$html = @file_get_contents("engine.html");


if (!$html) {
  $html = <<<END
<!DOCTYPE HTML>
<html>
<head>
<title>Live Games</title>
</head>
<body style="font-family: sans-serif;">
Live Games app error
</body>
</html>
END;
  print $html;
  exit;
}


$text = <<<END
<meta name="viewport" content="initial-scale=1, maximum-scale=1">
END;
$html = str_replace("<!-- AppCheck: meta -->", $text, $html);


$text = "var thisParamString = (window.location.search || window.location.hash) + '&fpis=96&pf=a&lch=FFCC99&dch=CC9966&bch=000000&hch=996633&fmch=FFEEDD&ctch=FFEEDD&fpr=0.5&els=t';";
$html = str_replace("var thisParamString = window.location.search || window.location.hash;", $text, $html);


$text = <<<END
var lastOrientation;
var lastOrientationTimeout = null;
END;
$html = str_replace("<!-- AppCheck: before myOnOrientationchange -->", $text, $html);


$text = <<<END
   if (typeof(window.orientation) != "undefined") {
      if (window.orientation === lastOrientation) { return; }
      lastOrientation = window.orientation;

      if (history.length > 1) {
         if (lastOrientationTimeout) { history.back(); }
         else { lastOrientationTimeout = setTimeout("lastOrientationTimeout = null;", 1800); }
      }
   }
END;
$html = str_replace("<!-- AppCheck: myOnOrientationchange -->", $text, $html);


$text = <<<END
   } else if (history.length > 1) {
      history.back();
END;
$html = str_replace("<!-- AppCheck: clickedGameAutoUpdateFlag -->", $text, $html);


$text = <<<END
      } else if (history.length > 1) {
         theObj.innerHTML = "&crarr;";
         theObj.title = "close analysis board";
END;
$html = str_replace("<!-- AppCheck: updateGameAutoUpdateFlag -->", $text, $html);


$text = <<<END
if (window.navigator.standalone) {
  window.open = function (winUrl, winTarget, winParam) {
    if (winUrl) {
      var a = document.createElement("a");
      a.setAttribute("href", winUrl);
      a.setAttribute("taget", winTarget ? winTarget : "_blank");
      var e = document.createEvent("HTMLEvents");
      e.initEvent("click", true, true);
      a.dispatchEvent(e);
    }
    return null;
  };

  simpleAddEvent(document.body, "touchmove", function(e) { e.preventDefault(); });
}

function pgn4web_handleTouchEnd_body(e) {
  e.stopPropagation();
  var jj, deltaX, deltaY;
  for (var ii = 0; ii < e.changedTouches.length; ii++) {
    if ((jj = pgn4webOngoingTouchIndexById(e.changedTouches[ii].identifier)) != -1) {
      deltaX = e.changedTouches[ii].clientX - pgn4webOngoingTouches[jj].clientX;
      deltaY = e.changedTouches[ii].clientY - pgn4webOngoingTouches[jj].clientY;
      if (Math.max(Math.abs(deltaX), Math.abs(deltaY)) >= 13) {
/*jsl:ignore*/
        if (Math.abs(deltaY) > 1.5 * Math.abs(deltaX)) {
          if (deltaY > 0) { // vertical down
            if ((!openerCheck()) && (history.length > 1)) { history.back(); }
          } else { // vertical up
          }
        } else if (Math.abs(deltaX) > 1.5 * Math.abs(deltaY)) {
          if (deltaX > 0) { // horizontal right
          } else { // horizontal left
            if ((!openerCheck()) && (history.length > 1)) { history.back(); }
          }
        }
/*jsl:end*/
      }
      pgn4webOngoingTouches.splice(jj, 1);
    }
  }
  clearSelectedText();
}

simpleAddEvent(document.body, "touchstart", pgn4web_handleTouchStart);
simpleAddEvent(document.body, "touchmove", pgn4web_handleTouchMove);
simpleAddEvent(document.body, "touchend", pgn4web_handleTouchEnd_body);
simpleAddEvent(document.body, "touchleave", pgn4web_handleTouchEnd_body);
simpleAddEvent(document.body, "touchcancel", pgn4web_handleTouchCancel);
END;
$html = str_replace("<!-- AppCheck: footer -->", $text, $html);


print $html;


?>
