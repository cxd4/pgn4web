/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2011 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

// this code integrates the chess viewer extension for Google Chrome
// with the GarboChess javascript code from Gary Linscott at 
// http://forwardcoding.com/projects/ajaxchess/chess.html and
// it's derived from the code at that page.

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
         if ((g_tabId !== null) && (newTabId !== g_tabId)) {
            chrome.extension.sendRequest({tabId: g_tabId, analysisNotification: "stopped"}, function(response) {});
         }
         if (InitializeBackgroundEngine()) {
            g_FEN = newFEN;
            g_tabId = newTabId;
            g_backgroundEngine.postMessage("position " + g_FEN);
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

var egStored_max = 10000;
var egStored_index;
var egStored_FEN;
var egStored_ev;
var egStored_pv;
var egStored_nodes;
var egStored_maxNodesPerSecond;
resetAnalysisData();

function storeAnalysis(FEN, ev, pv, nodes) {

   maxEv = 99.9;
   minNodesForAnnotation = 12345;

   ev = Math.floor(matches[2] / 100) / 10;
   if (ev < -maxEv) { ev = -maxEv; } else if (ev > maxEv) { ev = maxEv; }

   if ((nodes < minNodesForAnnotation) && (ev < 99.9) && (ev > -99.9) && (ev !== 0)) { return false; }

   index = egStored_FEN.indexOf(FEN);
   if ((index != -1) && (nodes > egStored_nodes[index])) {
      delete egStored_FEN[index];
      delete egStored_ev[index];
      delete egStored_pv[index];
      delete egStored_nodes[index];
      index = -1;
   }
   if (index == -1) {
      if (FEN.indexOf(" b ") !== -1) { ev = -ev; }
      ev = (ev > 0 ? "+" : "") + ev; 
      if (ev.indexOf(".") == -1) { ev += ".0"; }

      egStored_FEN[egStored_index] = FEN;
      egStored_ev[egStored_index] = ev;
      egStored_pv[egStored_index] = pv;
      egStored_nodes[egStored_index] = nodes;
      egStored_index = (egStored_index + 1) % egStored_max;
      return true;
   }
   return false;
}

function getAnalysisIndexFromFEN(FEN) { return egStored_FEN.indexOf(FEN); }

function getAnalysisEvFromIndex(index) { return egStored_ev[index]; }

function getAnalysisPvFromIndex(index) { return egStored_pv[index]; }

function getAnalysisMaxNodesPerSecond() { return egStored_maxNodesPerSecond; }

function resetAnalysisData() {
   egStored_index = 0;
   egStored_FEN = new Array();
   egStored_ev = new Array();
   egStored_pv = new Array();
   egStored_nodes = new Array();
   egStored_maxNodesPerSecond = 0;
}

var analysisTimeoutDelayMinutes = 10;
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
                if (matches = e.data.substr(3, e.data.length - 3).match(/Ply:(\d+) Score:(-*\d+) Nodes:(\d+) NPS:(\d+) (.*)/)) {
                   ply = matches[1];
                   ev = matches[2];
                   nodes = matches[3];
                   nodesPerSecond = matches[4];
                   egStored_maxNodesPerSecond = Math.max(egStored_maxNodesPerSecond, nodesPerSecond);
                   pv = matches[5].replace(/(\+|#|checkmate|stalemate)/g, "");
                   if (storeAnalysis(g_FEN, ev, pv, nodes)) {
                      chrome.extension.sendRequest({tabId: g_tabId, analysisNotification: "newData"}, function(response) {});
                   }
                }
             }
          };
      } catch (error) { g_backgroundEngineValid = false; }
   }
   return g_backgroundEngineValid;
}

function analysisRequestHandler(request, sender, sendResponse) {
  if (typeof(request.analysisCommand) != "undefined") { 
     setAnalysisStatus(request.analysisCommand, sender.tab.id, request.FEN);
  }
  sendResponse({});
}

chrome.extension.onRequest.addListener(analysisRequestHandler);

function stopAnalysisOnUpdated(tabId) { 
   chrome.tabs.get(tabId, function (tab) {
      if (tab.url.indexOf(chrome.extension.getURL("chess-games-viewer.html")) == -1) { setAnalysisStatus("stop", tab.id, ""); }
   });
}

chrome.tabs.onUpdated.addListener(stopAnalysisOnUpdated);