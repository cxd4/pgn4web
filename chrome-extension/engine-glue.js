/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2011 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

// code to integrate the pgn4web chess viewer extension for Google Chrome with
// the GarboChess javascript code from Gary Linscott http://forwardcoding.com/projects/ajaxchess/chess.html

// important: in order to avoid that multiple web workers instances of garbochess hog google chrome,
// only one instance of GarboChess is allowed and managed by the extension's background page.

var g_analysis_status = "stop"; // "analysis", "pause", "stop", "error"
var g_FEN = "";
var g_tabId = null;

function setAnalysisStatus(newStatus, newTabId, newFEN) {
   switch (newStatus) {
      case "analysis":
         if ((newStatus == g_analysis_status) && (newTabId === g_tabId) && (newFEN === g_FEN)) { return; }
         if ((g_analysis_status == "analysis") && g_backgroundEngine) {
            g_backgroundEngine.terminate();
            g_backgroundEngine = null;
         }
         if ((g_tabId !== null) && (newTabId !== g_tabId)) { notifyAnalysis(g_tabId, "", "", "", "", ""); }
         if (InitializeBackgroundEngine()) {
            g_tabId = newTabId;
            ResetGame();
            InitializeFromFen(g_FEN = newFEN);
            g_backgroundEngine.postMessage("position " + GetFen());
            g_backgroundEngine.postMessage("analyze");
            g_analysis_status = "analysis";
            setAnalysisTimeout(g_tabId);
         }
         break;
      case "pause":
      case "stop":
         if ((newTabId !== null) && (newTabId !== g_tabId)) { return; }
         if ((g_analysis_status == "analysis") && g_backgroundEngine) {
            g_backgroundEngine.terminate();
            g_backgroundEngine = null;
         }
         clearAnalysisTimeout();
         if ((newStatus == "stop") && (g_tabId !== null)) {
            notifyAnalysis(g_tabId, "", "", "", "", "");
            g_FEN = "";
            g_tabId = null;
         }
         g_analysis_status = newStatus;
         break;
      default:
         g_FEN = "";
         g_tabId = null;
         g_analysis_status = "error";
         break;
   }
}

var analysisTimeoutDelayMinutes = 5;
var analysisTimeout = null;
function setAnalysisTimeout(tabId) {
   if (analysisTimeout !== null) { clearAnalysisTimeout(); }
   analysisTimeout = setTimeout('setAnalysisStatus("pause", ' + tabId + ', "")', analysisTimeoutDelayMinutes * 60 * 1000);
}

function clearAnalysisTimeout() {
   if (analysisTimeout === null) { return; }
   clearTimeout(analysisTimeout);
   analysisTimeout = null;
}

var g_backgroundEngineValid = true;
var g_backgroundEngine;

function InitializeBackgroundEngine() {
   if (!g_backgroundEngineValid) { return false; }

   if (!g_backgroundEngine) {
      g_backgroundEngineValid = true;
      try {
          g_backgroundEngine = new Worker("garbochess/garbochess.js");
          g_backgroundEngine.onmessage = function (e) {
             if (e.data.match("^pv") == "pv") {
                if (matches = e.data.substr(3, e.data.length - 3).match(/Ply:(\d+) Score:(-*\d+) Nodes:(\d+) NPS:(\d+) (.+)/)) {
                   ply = matches[1];
                   ev = Math.floor(matches[2] / 100) / 10;
                   if (g_FEN.indexOf(" b ") !== -1) { ev = - ev; }
                   ev = (ev < 0 ? "" : "+") + ev; 
                   if (ev.indexOf(".") == -1) { ev += ".0"; }
                   nodes = matches[3];
                   nodesPerSecond = matches[4];
                   pv = matches[5];
                   notifyAnalysis(g_tabId, ply, ev, nodes, nodesPerSecond, pv);
                }
             }
          };
      } catch (error) { g_backgroundEngineValid = false; }
   }
   return g_backgroundEngineValid;
}

function notifyAnalysis(tabId, ply, ev, nodes, nodesPerSecond, pv) {
   chrome.extension.sendRequest({tabId: tabId, analysisPly: ply, analysisEval: ev, analysisNodes: nodes, analysisnodesPerSecond: nodesPerSecond, analysisPv: pv}, function(response) {});
}

function analysisRequestHandler(request, sender, sendResponse) {
  if (typeof(request.analysisCommand) == "undefined") { return; }
  setAnalysisStatus(request.analysisCommand, sender.tab.id, request.FEN);
}

chrome.extension.onRequest.addListener(analysisRequestHandler);

function stopAnalysisOnRemoved(tabId) { setAnalysisStatus("stop", sender.tabId, ""); }

function stopAnalysisOnUpdated(tabId) { 
   chrome.tabs.get(tabId, function (tab) {
      if (tab.url.indexOf(chrome.extension.getURL("chess-games-viewer.html")) == -1) { setAnalysisStatus("stop", tab.id, ""); }
   });
}

chrome.tabs.onRemoved.addListener(stopAnalysisOnRemoved);
chrome.tabs.onUpdated.addListener(stopAnalysisOnUpdated);
