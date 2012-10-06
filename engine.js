/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

var pgn4web_engineWindowUrlParameters = "";
var pgn4web_engineWindowTarget = "pgn4web_engine_analysis";
var pgn4web_engineWindowHeight = 30 * 12;
var pgn4web_engineWindowWidth = 30 * 10;

var engineWin;
function showEngineAnalysisBoard(urlParameters, target, ww, hh) {
   if (window.Worker) {
      if ((typeof(gameVariant[currentGame]) == "undefined") || (gameVariant[currentGame].match(/^(chess|normal|standard|)$/i) !== null)) {
         if (typeof(urlParameters) == "undefined") { urlParameters = pgn4web_engineWindowUrlParameters; }
         if (typeof(target) == "undefined") { target = pgn4web_engineWindowTarget; }
         if (typeof(ww) == "undefined") { ww = pgn4web_engineWindowWidth; }
         if (typeof(hh) == "undefined") { hh = pgn4web_engineWindowHeight; }
         var options = "resizable=no,scrollbars=no,toolbar=no,location=no,menubar=no,status=no";
         if (hh !== "") { options = "height=" + hh + "," + options; }
         if (ww !== "") { options = "width=" + ww + "," + options; }
         engineWin = window.open("engine.html?fs=" + CurrentFEN() + (urlParameters ? "&" + urlParameters : ""), target, options);
         if ((engineWin) && (engineWin.top === engineWin.self) && (window.focus)) { engineWin.focus(); }
      } else {
         alert("game analysis error: the garbochess engine only supports normal chess; the " + gameVariant[currentGame] + " variant is not supported");
      }
   }
}

if (window.Worker) {
   boardShortcut("E8", "show engine analysis board", function(t,e){ if (e.shiftKey) { displayHelp("informant_symbols"); } else { showEngineAnalysisBoard(); } }, true);
}

