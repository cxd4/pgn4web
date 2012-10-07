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

// note: all pages on the same site will use the same engine analysis popup; if the engine analysis is embedded as iframe within a page (see the live-results-viewer.html example) the pgn4web_engineWindowTarget variable should be customized in order to avoid conflicts


var engineWin;
function showEngineAnalysisBoard(urlParameters, target, ww, hh) {
   var retVal = false;
   if (window.Worker) {
      if ((typeof(gameVariant[currentGame]) == "undefined") || (gameVariant[currentGame].match(/^(chess|normal|standard|)$/i) !== null)) {
         try {
            if ((typeof(engineWin) != "undefined") && (engineWin.closed === false) && (window.self === engineWin.opener)) {
               engineWin.replaceFEN(CurrentFEN());
               retVal = true;
            } else {
               retVal = openEngineWin(urlParameters, target, ww, hh);
            }
         } catch(e) {
           retVal = openEngineWin(urlParameters, target, ww, hh);
         }
         if ((typeof(engineWin) != "undefined") && (engineWin.top === engineWin.self) && (window.focus)) { engineWin.focus(); }
      } else {
         myAlert("pgn4web engine analysis warning: the engine supports only normal chess; the " + gameVariant[currentGame] + " variant is not supported", true);
      }
   } else {
      myAlert("pgn4web engine analysis warning: missing web worker functionality from the web browser", true);
   }
   return retVal;
}

function openEngineWin(urlParameters, target, ww, hh) {
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
         return true;
      } else {
         myAlert("pgn4web engine analysis warning: the engine supports only normal chess; the " + gameVariant[currentGame] + " variant is not supported", true);
         return false;
      }
   } else {
      myAlert("pgn4web engine analysis warning: missing web worker functionality from the web browser", true);
      return false;
   }
}

if (window.Worker) {
   boardShortcut("E8", "show/update engine analysis board", function(t,e){ if (e.shiftKey) { displayHelp("informant_symbols"); } else { showEngineAnalysisBoard(); } }, true);
}

