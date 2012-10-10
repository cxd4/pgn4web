/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009-2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

var pgn4web_engineWindowTarget = "pgn4web_engine_analysis";
var pgn4web_engineWindowUrlParameters = "";
var pgn4web_engineWindowHeight = 30 * 12; // window height/width corresponding to default squareSize = 30
var pgn4web_engineWindowWidth = 30 * 10;

// note: all pages on the same site will use the same analysis board popup; if the analysis board is embedded as iframe within a page (see the live-results-viewer.html example) the pgn4web_engineWindowTarget variable should be customized in order to prevent conflicts


var pgn4web_engineWinSignature = Math.ceil(987654321 * Math.random());

var engineWin;
function showEngineAnalysisBoard(engineDisabled) {
   if ((typeof(gameVariant[currentGame]) == "undefined") || (gameVariant[currentGame].match(/^(chess|normal|standard|)$/i) !== null)) {
      var doneAccessingDOM = false;
      try {
         if ((typeof(engineWin) != "undefined") && (!engineWin.closed) && (typeof(engineWin.engineSignature) != "undefined") && (pgn4web_engineWinSignature === engineWin.engineSignature) && (engineWin.sameEngineDisabled(engineDisabled))) {
            engineWin.updateFEN(CurrentFEN());
            doneAccessingDOM = true;
         }
      } catch(e) {}
      if (!doneAccessingDOM) {
         var parameters = "fs=" + encodeURIComponent(CurrentFEN()) + "&es=" + pgn4web_engineWinSignature;
         if (engineDisabled) { parameters += "&de=a"; }
         if (pgn4web_engineWindowUrlParameters) { parameters += "&" + pgn4web_engineWindowUrlParameters; }
         var options = "resizable=no,scrollbars=no,toolbar=no,location=no,menubar=no,status=no";
         if (pgn4web_engineWindowHeight) { options = "height=" + pgn4web_engineWindowHeight + "," + options; }
         if (pgn4web_engineWindowWidth) { options = "width=" + pgn4web_engineWindowWidth + "," + options; }
         engineWin = window.open("engine.html?" + parameters, pgn4web_engineWindowTarget, options);
      }
      if ((typeof(engineWin) != "undefined") && (engineWin.top === engineWin.self) && (window.focus)) { engineWin.focus(); }
      return engineWin;
   } else {
      myAlert("warning: the pgn4web analysis board supports only normal chess; the " + gameVariant[currentGame] + " variant is not supported", true);
   }
   return null;
}

boardShortcut("E8", "show/update analysis board", function(t,e){ showEngineAnalysisBoard(e.shiftKey); }, true);

