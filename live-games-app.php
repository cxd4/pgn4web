<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2013 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ALL | E_STRICT);


$html = @file_get_contents("dynamic-frame.html");


function errorExit($errorNum) {
  $html = <<<END
<!DOCTYPE HTML>
<html>
<head>
<title>web app error</title>
</head>
<body style="color: white; background: black; font-family: sans-serif;">
web app error: $errorNum
</body>
</html>
END;
  print $html;
  exit;
}


$actionNum = 0;
if (!$html) { errorExit($actionNum); }


$text = '("?l=t&" + window.location.hash + "&pf=a&ct=wood&bch=000000&fch=FFEEDD&hch=996633&scf=t" + (window.navigator.standalone ? "&hc=t" : ""))';
$oldText = "window.location.search";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = '<html manifest="live-games-app.appcache">';
$oldText = "<html>";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = "<title>Live Games</title>";
$oldText = "<title>chess games</title>";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = "liveStatusTickerString";
$oldText = "document.title";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<script type="text/javascript">
"use strict";
window['defaultOpen'] = window.open;
window.open = function (winUrl, winTarget, winParam) {
  if ((winUrl) && (winUrl.match(/(^|\/)live-games-app-engine\.php/))) {
     window.location.href = winUrl;
     return null;
  } else if (!window.navigator.standalone) {
     return window.defaultOpen(winUrl, winTarget, winParam || "");
  } else if (winUrl) {
     //
     // patch for iOS7
     //
     // var a = document.createElement("a");
     // a.setAttribute("href", winUrl);
     // a.setAttribute("target", winTarget ? winTarget : "_blank");
     // var e = document.createEvent("HTMLEvents");
     // e.initEvent("click", true, true);
     // a.dispatchEvent(e);
     //
     // end of patch for iOS7
     //
     return null;
  }
  return null;
};
</script>
END;
$oldText = "<!-- AppCheck: meta -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


// $text = "";
// $oldText = "href='javascript:void(0);'";
// $actionNum += 1;
// if (!strstr($html, $oldText)) { errorExit($actionNum); }
// $html = str_replace($oldText, $text, $html);


$text = "";
$oldText = 'href="javascript:void(0);"';
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = "gameListLineHeight = Math.floor(2.1 * gameListFontSize);";
$oldText = "gameListLineHeight = Math.floor(1.4 * gameListFontSize);";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = 'myInsertRule(sheet, ".gameListBodyItems", "min-height: " + (gameListBodyHeight + (window.navigator.standalone ? 2 : 0)) + "px; min-width: " + (ww - 2 * framePadding + (window.navigator.standalone ? 1 : 0)) + "px;");';
$oldText = 'myInsertRule(sheet, ".gameListBodyItems", "min-height: " + gameListBodyHeight + "px; min-width: " + (ww - 2 * framePadding) + "px;");';
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


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
  document.title = titleString;
END;
$oldText = "<!-- AppCheck: customFunctionOnPgnTextLoad -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
  if (appInitialized) { localStorage[lsId + "lastGameKey"] = gameKey(gameEvent[currentGame], gameSite[currentGame], gameDate[currentGame], gameRound[currentGame], gameWhite[currentGame], gameBlack[currentGame]); }
END;
$oldText = "<!-- AppCheck: customFunctionOnPgnGameLoad -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
  if (appInitialized) {
    localStorage[lsId + "lastGameVar"] = CurrentVar;
    localStorage[lsId + "lastGamePly"] = CurrentPly;
    localStorage[lsId + "lastGameAutoplay"] = ((isAutoPlayOn) || (CurrentPly === StartPly + PlyNumber));
  }
END;
$oldText = "<!-- AppCheck: customFunctionOnMove -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = '<body onResize="myOnResize();" onLoad="setTimeout(\'myOnResize();\', 33);">'; # patch iphone landscape startup bug with placeholder game
$oldText = '<body onResize="myOnResize();" onLoad="myOnResize();">';
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


$text = <<<END
var titleString = "Live Games";
thisRegExp = /(&|\?)(title|t)=([^&]*)(&|$)/i;
if (thisParamString.match(thisRegExp) !== null) {
  titleString = unescape(thisParamString.match(thisRegExp)[3]);
}

var appInitialized = false;

var liveStatusTickerString = "";

var lsId = "pgn4web_live_games_app_";

var storageId = "3";
if ((localStorage[lsId + "storageId"] !== storageId) || (localStorage[lsId + "locationHref"] !== window.location.href)) {
  window.localStorage.clear();
  localStorage[lsId + "storageId"] = storageId;
  localStorage[lsId + "locationHref"] = window.location.href;
}

window['defaultSetAutoPlay'] = window['SetAutoPlay'];
window['SetAutoPlay'] = function(vv) {
  defaultSetAutoPlay(vv);
  if (appInitialized) {
    localStorage[lsId + "lastGameAutoplay"] = ((isAutoPlayOn) || (CurrentPly === StartPly + PlyNumber));
  }
};

window['defaultSetAutoplayDelay'] = window['SetAutoplayDelay'];
window['SetAutoplayDelay'] = function(vv) {
  defaultSetAutoplayDelay(vv);
  localStorage[lsId + "Delay"] = Delay;
};
if (typeof(localStorage[lsId + "Delay"]) == "string") {
  var newDelay = parseInt(localStorage[lsId + "Delay"], 10);
  if (!isNaN(newDelay)) { Delay = newDelay; }
}

window['defaultSetHighlightOption'] = window['SetHighlightOption'];
window['SetHighlightOption'] = function(on) {
  defaultSetHighlightOption(on);
  localStorage[lsId + "highlightOption"] = highlightOption;
};
if (typeof(localStorage[lsId + "highlightOption"]) == "string") {
  highlightOption = (localStorage[lsId + "highlightOption"] == "true");
}

window['defaultToggleShowEco'] = window['toggleShowEco'];
window['toggleShowEco'] = function() {
  defaultToggleShowEco();
  localStorage[lsId + "showEco"] = showEco;
};
if (typeof(localStorage[lsId + "showEco"]) == "string") {
  showEco = (localStorage[lsId + "showEco"] == "true");
}

window['defaultToggleColorFlag'] = window['toggleColorFlag'];
window['toggleColorFlag'] = function() {
  defaultToggleColorFlag();
  localStorage[lsId + "showColorFlag"] = showColorFlag;
};
if (typeof(localStorage[lsId + "showColorFlag"]) == "string") {
  showColorFlag = (localStorage[lsId + "showColorFlag"] == "true");
}

window['defaultPauseLiveBroadcast'] = window['pauseLiveBroadcast'];
window['pauseLiveBroadcast'] =  function() {
  defaultPauseLiveBroadcast();
  localStorage[lsId + "LiveBroadcastPaused"] = LiveBroadcastPaused;
  fixGameLiveStatusExtraInfo();
};
window['defaultRestartLiveBroadcast'] = window['restartLiveBroadcast'];
window['restartLiveBroadcast'] =  function() {
  defaultRestartLiveBroadcast();
  localStorage[lsId + "LiveBroadcastPaused"] = LiveBroadcastPaused;
};
if (typeof(localStorage[lsId + "LiveBroadcastPaused"]) == "string") {
  LiveBroadcastPaused = (localStorage[lsId + "LiveBroadcastPaused"] == "true");
}

var lastGameLiveStatusExtraInfoRes = LOAD_PGN_FAIL;
window['defaultCustomFunctionOnCheckLiveBroadcastStatus'] = window['customFunctionOnCheckLiveBroadcastStatus'];
window['customFunctionOnCheckLiveBroadcastStatus'] = function() {
  defaultCustomFunctionOnCheckLiveBroadcastStatus();
  fixGameLiveStatusExtraInfo();
};

function fixGameLiveStatusExtraInfo(res) {
  if (typeof(res) != "undefined") {
    lastGameLiveStatusExtraInfoRes = res;
  }
  var newExtraText = "";
  if (LiveBroadcastDelay && LiveBroadcastDemo) { newExtraText += "<span title='this is a broadcast simulation'>demo</span>"; }
  if (lastGameLiveStatusExtraInfoRes === LOAD_PGN_FAIL) {
    if (newExtraText) { newExtraText += "<span style='margin-left:1.25em;'"; } // 1.25em corresponds to fontSizeRatio = 0.8
    else { newExtraText += "<span"; }
    newExtraText += " title='games from application cache'>";
    newExtraText += ((!localStorage[lsId + "lastGamesValidationTime"]) || ((new Date()).getTime() - localStorage[lsId + "lastGamesValidationTime"]) > 18000000) ? "X" : "x";
    newExtraText += "</span>";
    // 5h = 18000000ms
  }
  if (LiveBroadcastDelay && (LiveBroadcastPaused || LiveBroadcastEnded)) {
    if (newExtraText) { newExtraText += "<a style='margin-left:1.25em;'"; } // 1.25em corresponds to fontSizeRatio = 0.8
    else { newExtraText += "<a"; }
    if (LiveBroadcastEnded) {
      newExtraText += " onclick='refreshPgnSource(); this.blur();' title='live broadcast ended'>O</a>";
    } else {
      newExtraText += " onclick='restartLiveBroadcast(); this.blur();' title='live broadcast automatic games refresh paused'>o</a>";
    }
  }
  var theObj = document.getElementById("GameLiveStatusExtraInfoRight");
  if (theObj) {
    theObj.style.visibility = newExtraText ? "visible" : "hidden";
    var otherObj = document.getElementById("GameLiveStatusExtraInfoLeft");
    if (otherObj) { otherObj.innerHTML = theObj.innerHTML = newExtraText; }
  }
}

window['defaultLoadPgnCheckingLiveStatus'] = window['loadPgnCheckingLiveStatus'];
window['loadPgnCheckingLiveStatus'] = function(res) {
  fixGameLiveStatusExtraInfo(res);
  if (res === LOAD_PGN_OK) {
    var text = "";
    for (var ii = 0; ii < numberOfGames; ++ii) { text += fullPgnGame(ii) + "\\n\\n"; }
    localStorage[lsId + "lastGamesPgnText"] = simpleHtmlentitiesDecode(text);
    localStorage[lsId + "lastGamesLastModifiedHeader"] = LiveBroadcastLastModifiedHeader;
    localStorage[lsId + "lastGamesLastReceivedLocal"] = LiveBroadcastLastReceivedLocal;
  }
  if ((res === LOAD_PGN_OK) || (res === LOAD_PGN_UNMODIFIED)) {
    localStorage[lsId + "lastGamesValidationTime"] = (new Date()).getTime();
  }
  defaultLoadPgnCheckingLiveStatus(res);
};

window['defaultLoadPgnFromPgnUrl'] = window['loadPgnFromPgnUrl'];
window['loadPgnFromPgnUrl'] = function(pgnUrl) {
  var rememberAppInitialized = appInitialized;
  if (!appInitialized) {
    var theObj = document.getElementById("GameLiveStatusExtraInfoRight");
    if (theObj) { theObj.style.visibility = "visible"; }
    var initialPgnGames = localStorage[lsId + "lastGamesPgnText"] || '[Event "please wait..."]\\n[Site "live games app"]\\n[Date "startup"]\\n[Round ""]\\n[White ""]\\n[Black ""]\\n[Result "*"]\\n';
    if (!pgnGameFromPgnText(initialPgnGames)) {
      myAlert("error: invalid games cache");
    } else {
      if (typeof(localStorage[lsId + "lastGamesLastModifiedHeader"]) == "string") {
        LiveBroadcastLastModifiedHeader = localStorage[lsId + "lastGamesLastModifiedHeader"];
        LiveBroadcastLastModified = new Date(LiveBroadcastLastModifiedHeader);
      }
      if (typeof(localStorage[lsId + "lastGamesLastReceivedLocal"]) == "string") {
        LiveBroadcastLastReceivedLocal = localStorage[lsId + "lastGamesLastReceivedLocal"];
      }
      firstStart = true;
      undoStackReset();
      Init();
      LiveBroadcastStarted = true;
      checkLiveBroadcastStatus();
      customFunctionOnPgnTextLoad();
    }
  }
  if (rememberAppInitialized || !LiveBroadcastPaused) { defaultLoadPgnFromPgnUrl(pgnUrl); }
  else { fixGameLiveStatusExtraInfo(); }
};

function detectEngineLocation() {
  return detectJavascriptLocation().replace(/(pgn4web|pgn4web-compacted)\\.js/, "live-games-app-engine.php");
}

engineWinParametersSeparator = "#?";

boardShortcut("F8", "live games web application wiki", function(t,e){ window.open("https://code.google.com/p/pgn4web/wiki/WebApp_LiveGames", "pgn4web_webAppWiki"); });

boardShortcut("H5", "reset live games web application", function(t,e){ if (confirm("Reset live games web application to default configuration?\\n\\nWarning: application settings customizations, games data and engine analysis data will be lost.")) { window.localStorage.clear(); window.location.reload(); } });

function gameKey(event, site, date, round, white, black) {
  var key = "";
  key += "[" + (typeof(event) == "string" ? event : "") + "]";
  key += "[" + (typeof(site) == "string" ? site : "") + "]";
  key += "[" + (typeof(date) == "string" ? date : "") + "]";
  key += "[" + (typeof(round) == "string" ? round : "") + "]";
  key += "[" + (typeof(white) == "string" ? white : "") + "]";
  key += "[" + (typeof(black) == "string" ? black : "") + "]";
  return key;
}

function pgn4web_handleTouchEnd_HeaderContainer(e) {
  e.stopPropagation();
  var jj, deltaX, deltaY;
  for (var ii = 0; ii < e.changedTouches.length; ii++) {
    if ((jj = pgn4webOngoingTouchIndexById(e.changedTouches[ii].identifier)) != -1) {
      if (pgn4webOngoingTouches.length == 1) {
        deltaX = e.changedTouches[ii].clientX - pgn4webOngoingTouches[jj].clientX;
        deltaY = e.changedTouches[ii].clientY - pgn4webOngoingTouches[jj].clientY;
        if (Math.max(Math.abs(deltaX), Math.abs(deltaY)) >= 13) {
          if (Math.abs(deltaY) > 1.5 * Math.abs(deltaX)) {
            if (deltaY > 0) { // vertical down
              showEngineAnalysisBoard();
            } else { // vertical up
              showGameList();
            }
          } else if (Math.abs(deltaX) > 1.5 * Math.abs(deltaY)) { // horizontal left or right
            GoToMove(CurrentPly + sign(deltaX));
          }
        }
        pgn4webMaxTouches = 0;
      }
      pgn4webOngoingTouches.splice(jj, 1);
    }
  }
  clearSelectedText();
}

function pgn4web_handleTouchEnd_Header(e) {
  e.stopPropagation();
  var jj, deltaX, deltaY, theObj;
  for (var ii = 0; ii < e.changedTouches.length; ii++) {
    if ((jj = pgn4webOngoingTouchIndexById(e.changedTouches[ii].identifier)) != -1) {
      if (pgn4webOngoingTouches.length == 1) {
        deltaX = e.changedTouches[ii].clientX - pgn4webOngoingTouches[jj].clientX;
        deltaY = e.changedTouches[ii].clientY - pgn4webOngoingTouches[jj].clientY;
        if (Math.max(Math.abs(deltaX), Math.abs(deltaY)) >= 13) {
          if (Math.abs(deltaX) > 1.5 * Math.abs(deltaY)) { // horizontal left or right
            selectGameList(-1);
          }
        }
        pgn4webMaxTouches = 0;
      }
      pgn4webOngoingTouches.splice(jj, 1);
    }
  }
  clearSelectedText();
}

function pgn4web_handleTouchStart_scroll(e) {
  if (window.navigator.standalone) {
    if (this.scrollTop === 0) { this.scrollTop += 1; }
    if (this.scrollTop === this.scrollHeight - this.clientHeight) { this.scrollTop -= 1; }
  }
  this.allowUp = (this.scrollTop > 0);
  this.allowDown = (this.scrollTop < this.scrollHeight - this.clientHeight);
  this.lastY = e.pageY;
}

function pgn4web_handleTouchMove_scroll(e) {
  var up = (e.pageY > this.lastY);
  var down = (e.pageY < this.lastY);
  var flat = (e.pageY === this.lastY);
  this.lastY = e.pageY;
  if ((up && this.allowUp) || (down && this.allowDown) || (flat)) { e.stopPropagation(); }
  else { e.preventDefault(); }
}

if (touchEventEnabled) {
  if (theObj = document.getElementById("HeaderContainer")) {
    simpleAddEvent(theObj, "touchstart", pgn4web_handleTouchStart);
    simpleAddEvent(theObj, "touchmove", pgn4web_handleTouchMove);
    simpleAddEvent(theObj, "touchend", pgn4web_handleTouchEnd_HeaderContainer);
    simpleAddEvent(theObj, "touchleave", pgn4web_handleTouchEnd_HeaderContainer);
    simpleAddEvent(theObj, "touchcancel", pgn4web_handleTouchCancel);
  }

  if (theObj = document.getElementById("GameListHeader")) {
    simpleAddEvent(theObj, "touchstart", pgn4web_handleTouchStart);
    simpleAddEvent(theObj, "touchmove", pgn4web_handleTouchMove);
    simpleAddEvent(theObj, "touchend", pgn4web_handleTouchEnd_Header);
    simpleAddEvent(theObj, "touchleave", pgn4web_handleTouchEnd_Header);
    simpleAddEvent(theObj, "touchcancel", pgn4web_handleTouchCancel);
  }

  simpleAddEvent(document.body, "touchmove", function(e) { e.preventDefault(); });

  if (theObj = document.getElementById("GameListBody")) {
    simpleAddEvent(theObj, "touchstart", pgn4web_handleTouchStart_scroll);
    simpleAddEvent(theObj, "touchmove", pgn4web_handleTouchMove_scroll);
  }
}

simpleAddEvent(window.applicationCache, "updateready", function(e) {
  window.applicationCache.swapCache();
  window.location.reload();
});
END;
$oldText = "<!-- AppCheck: footer -->";
$actionNum += 1;
if (!strstr($html, $oldText)) { errorExit($actionNum); }
$html = str_replace($oldText, $text, $html);


print $html;


?>
