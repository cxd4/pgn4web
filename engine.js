/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

function showEngineAnalysisPopup(engineUrlOptions) {
   if (window.Worker) {
      if ((typeof(gameVariant[currentGame]) == "undefined") || (gameVariant[currentGame].match(/^(chess|normal|standard|)$/i) !== null)) {
         var engineWin = window.open("engine.html?fs=" + CurrentFEN() + (engineUrlOptions ? "&" + engineUrlOptions : ""), "pgn4web_engine_analysis", "height=334,width=286,resizable=no,scrollbars=no,toolbar=no,location=no,menubar=no,status=no");
         if (window.focus && engineWin) { engineWin.focus(); }
      } else {
         alert("game analysis error: the garbochess engine only supports normal chess; the " + gameVariant[currentGame] + " variant is not supported");
      }
   }
}

var pgn4web_engineUrlOptions = "";

if (window.Worker) {
   boardShortcut("E8", "engine analysis popup", function(t,e){ if (e.shiftKey) { displayHelp("informant_symbols"); } else { showEngineAnalysisPopup(pgn4web_engineUrlOptions); } }, true);
}

