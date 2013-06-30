<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2013 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ALL | E_STRICT);


$html = @file_get_contents("dynamic-frame.html");


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


$text = '"?l=t&ct=wood&bch=000000&fch=FFEEDD&pf=a&scf=t"';
$html = str_replace("window.location.search", $text, $html);


$text = '<html manifest="live-games-app.appcache">';
$html = str_replace("<html>", $text, $html);


$text = <<<END
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<script type="text/javascript">
"use strict";
window['defaultOpen'] = window.open;
window.open = function (winUrl, winTarget, winParam) {
  if ((winUrl) && (winUrl.match(/(^|\/)live-games-app-engine\.php/))) {
     location.href = winUrl;
     return null;
  } else if (!window.navigator.standalone) {
     return window.defaultOpen(winUrl, winTarget, winParam);
  } else if (winUrl) {
     var a = document.createElement("a");
     a.setAttribute("href", winUrl);
     a.setAttribute("taget", winTarget ? winTarget : "_blank");
     var e = document.createEvent("HTMLEvents");
     e.initEvent("click", true, true);
     a.dispatchEvent(e);
     return null;
  }
  return null;
};
</script>
END;
$html = str_replace("<!-- AppCheck: meta -->", $text, $html);


$text = <<<END
  if (!appInitialized) {
    if (localStorage[lsId + "lastGameKey"]) {
      var lastGameKey = localStorage[lsId + "lastGameKey"];
      var lastGameVar = parseInt(localStorage[lsId + "lastGameVar"], 10) || 0;
      var lastGamePly = parseInt(localStorage[lsId + "lastGamePly"], 10);
      var lastGameAutoplay = localStorage[lsId + "lastGameAutoplay"] === "true";
      for (var gg = 0; gg < numberOfGames; gg++) {
        if (lastGameKey === gameKey(gameEvent[gg], gameSite[gg], gameDate[gg], gameRound[gg], gameWhite[gg], gameBlack[gg])) { break; }
      }
      if (gg < numberOfGames) {
        if (gg !== currentGame) { Init(gg); }
        if ((!isNaN(lastGamePly)) && ((lastGamePly !== CurrentPly) || (lastGameVar !== CurrentVar))) { GoToMove(lastGamePly, lastGameVar); }
        SetAutoPlay(lastGameAutoplay);
      }
    }
    appInitialized = true;
  }
  document.title = "Live Games";
END;
$html = str_replace("<!-- AppCheck: customFunctionOnPgnTextLoad -->", $text, $html);


$text = <<<END
  if (appInitialized) { localStorage[lsId + "lastGameKey"] = gameKey(gameEvent[currentGame], gameSite[currentGame], gameDate[currentGame], gameRound[currentGame], gameWhite[currentGame], gameBlack[currentGame]); }
END;
$html = str_replace("<!-- AppCheck: customFunctionOnPgnGameLoad -->", $text, $html);


$text = <<<END
  if (appInitialized) {
    localStorage[lsId + "lastGameVar"] = CurrentVar;
    localStorage[lsId + "lastGamePly"] = CurrentPly;
    localStorage[lsId + "lastGameAutoplay"] = ((isAutoPlayOn) || (CurrentPly === StartPly + PlyNumber));
  }
END;
$html = str_replace("<!-- AppCheck: customFunctionOnMove -->", $text, $html);


$text = <<<END
var appInitialized = false;

var lsId = "pgn4web_live_games_app_";

var storageId = "1";
if (localStorage[lsId + "storageId"] !== storageId) {
  window.localStorage.clear();
  localStorage[lsId + "storageId"] = storageId;
}

window['defaultSetAutoPlay'] = window['SetAutoPlay'];
window['SetAutoPlay'] = function(vv) {
  defaultSetAutoPlay(vv);
  if (appInitialized) {
    localStorage[lsId + "lastGameAutoplay"] = ((isAutoPlayOn) || (CurrentPly === StartPly + PlyNumber));
  }
};

window['defaultLoadPgnCheckingLiveStatus'] = window['loadPgnCheckingLiveStatus'];
window['loadPgnCheckingLiveStatus'] = function(res) {
  var theObj = document.getElementById("GameLiveStatusExtraInfoRight");
  if (theObj) {
    // 5h = 18000000ms
    theObj.style.textTransform = ((!localStorage[lsId + "lastGamesTime"]) || ((new Date()).getTime() - localStorage[lsId + "lastGamesTime"]) > 18000000) ? "uppercase" : "";
    theObj.style.visibility = (res === LOAD_PGN_FAIL ? "visible" : "hidden");
    var otherObj = document.getElementById("GameLiveStatusExtraInfoLeft");
    if (otherObj) { otherObj.style.textTransform = theObj.style.textTransform; }
  }
  if (res === LOAD_PGN_OK) {
    var text = "";
    for (var ii = 0; ii < numberOfGames; ++ii) { text += fullPgnGame(ii) + "\\n\\n"; }
    localStorage[lsId + "lastGamesPgnText"] = text;
    localStorage[lsId + "lastGamesTime"] = (new Date()).getTime();
  }
  defaultLoadPgnCheckingLiveStatus(res);
};

window['defaultLoadPgnFromPgnUrl'] = window['loadPgnFromPgnUrl'];
window['loadPgnFromPgnUrl'] = function(pgnUrl) {
  if (!appInitialized) {
    var initialPgnGames = localStorage[lsId + "lastGamesPgnText"] || '[Event "please wait..."]\\n[Site "live games app"]\\n[Date "startup"]\\n[Round ""]\\n[White ""]\\n[Black ""]\\n[Result "*"]\\n';
    if (!pgnGameFromPgnText(initialPgnGames)) {
      myAlert("error: invalid games cache");
    } else {
      firstStart = true;
      undoStackReset();
      Init();
      LiveBroadcastStarted = true;
      checkLiveBroadcastStatus();
      customFunctionOnPgnTextLoad();
    }
  }
  defaultLoadPgnFromPgnUrl(pgnUrl);
};

function detectEngineLocation() {
  return detectJavascriptLocation().replace(/(pgn4web|pgn4web-compacted)\\.js/, "live-games-app-engine.php");
}

engineWinParametersSeparator = "#?";

clearShortcutSquares("F", "8");

function gameKey(event, site, date, round, white, black) {
  var key = "";
  key += "[" + (typeof(event) == "string" ? event : "") + "]";
  key += "[" + (typeof(site) == "string" ? site : "") + "]";
  key += "[" + (typeof(round) == "string" ? round : "") + "]";
  key += "[" + (typeof(white) == "string" ? white : "") + "]";
  key += "[" + (typeof(black) == "string" ? black : "") + "]";
  return key;
}

function pgn4web_handleTouchEnd_HeaderContainer(e) {
  var jj, deltaX, deltaY;
  for (var ii = 0; ii < e.changedTouches.length; ii++) {
    if ((jj = pgn4webOngoingTouchIndexById(e.changedTouches[ii].identifier)) != -1) {
      deltaX = e.changedTouches[ii].clientX - pgn4webOngoingTouches[jj].clientX;
      deltaY = e.changedTouches[ii].clientY - pgn4webOngoingTouches[jj].clientY;
      if (Math.max(Math.abs(deltaX), Math.abs(deltaY)) >= 13) {
        if (Math.abs(deltaY) > 1.5 * Math.abs(deltaX)) {
          if (deltaY > 0) { // vertical down
            showEngineAnalysisBoard();
          } else { // vertical up
            showGameList();
          }
        }
      }
      pgn4webOngoingTouches.splice(jj, 1);
    }
  }
  clearSelectedText();
}

if ((theObj = document.getElementById("HeaderContainer")) && (touchEventEnabled)) {
  simpleAddEvent(theObj, "touchstart", pgn4web_handleTouchStart);
  simpleAddEvent(theObj, "touchmove", pgn4web_handleTouchMove);
  simpleAddEvent(theObj, "touchend", pgn4web_handleTouchEnd_HeaderContainer);
  simpleAddEvent(theObj, "touchleave", pgn4web_handleTouchEnd_HeaderContainer);
  simpleAddEvent(theObj, "touchcancel", pgn4web_handleTouchCancel);
}

simpleAddEvent(document.body, "touchmove", function(e) { e.preventDefault(); });
theObj = document.getElementById("GameListBody");
if (theObj) {
  simpleAddEvent(theObj, "touchstart", function(e) {
    this.allowUp = (this.scrollTop > 0);
    this.allowDown = (this.scrollTop < this.scrollHeight - this.clientHeight);
    this.prevTop = null;
    this.prevBot = null;
    this.lastY = e.pageY;
  });
  simpleAddEvent(theObj, "touchmove", function(e) {
    var up = (e.pageY > this.lastY);
    var down = (e.pageY < this.lastY);
    var flat = (e.pageY === this.lastY);
    this.lastY = e.pageY;
    if ((up && this.allowUp) || (down && this.allowDown) || (flat)) { e.stopPropagation(); }
    else { e.preventDefault(); }
  });
}

if (theObj = document.getElementById("GameLiveStatusExtraInfoLeft")) {
  theObj.innerHTML = "x";
}
if (theObj = document.getElementById("GameLiveStatusExtraInfoRight")) {
  theObj.innerHTML = "x";
  theObj.title = "games from application cache";
  theObj.style.visibility = "visible";
}

simpleAddEvent(window.applicationCache, "updateready", function(e) {
  window.applicationCache.swapCache();
  window.location.reload();
});
END;
$html = str_replace("<!-- AppCheck: footer -->", $text, $html);


print $html;


?>
