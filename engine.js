/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

function showEngineAnalysisPopup() {
   if (gameIsNormalChess(currentGame)) {
      var engineWin = window.open("engine.html?fs=" + CurrentFEN(), "engine_analysis", "height=334,width=286,resizable=no,scrollbars=no,toolbar=no,location=no,menubar=no,status=no");
      if (window.focus && engineWin) { engineWin.focus(); }
   } else {
      alert("game analysis error: the garbochess engine only supports normal chess; the " + gameVariant[currentGame] + " variant is not supported");
   }
}

if (window.Worker) {
   boardShortcut("E8", "engine analysis popup", function(t,e){ if (e.shiftKey) { displayHelp("informant_symbols"); } else { showEngineAnalysisPopup(); } }, true);
}

function gameIsNormalChess(gameNum) {
   return ((typeof(gameVariant[gameNum]) == "undefined") || (gameVariant[gameNum].match(/^(chess|normal|standard|)$/i) !== null));
}

