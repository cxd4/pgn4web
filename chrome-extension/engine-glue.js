/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2011 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

// code to integrate the pgn4web chess viewer extension for Google Chrome with
// the garbochess javascript code from Gary Linscott http://forwardcoding.com/projects/ajaxchess/chess.html

// important: in order to avoid that multiple web workers instances of garbochess hog google chrome,
// only one instance of garbochess is allowed and managed by the background page

var g_analyzing = false;
var g_FEN = "";
var g_tabId = null;

function AnalysisStart(tabId, FEN) {
   if (g_analyzing && (tabId === g_tabId) && (FEN === g_FEN)) { return; }
   if (g_analyzing) {
      AnalysisStop(null);
      if ((g_tabId !== null) && (tabId !== g_tabId)) { notifyAnalysisStop(g_tabId); }
   }
   if (InitializeBackgroundEngine()) {
      g_tabId = tabId;
      ResetGame();
      g_FEN = FEN;
      InitializeFromFen(g_FEN);
      g_backgroundEngine.postMessage("position " + GetFen());
      g_backgroundEngine.postMessage("analyze");
      g_analyzing = true;
   }
}

function AnalysisStop(tabId) {
   if ((tabId !== null) && (tabId !== g_tabId)) { return; }
   if (g_analyzing && g_backgroundEngine != null) {
      g_backgroundEngine.terminate();
      g_backgroundEngine = null;
   }
   g_analyzing = false;
   if (g_tabId !== null) { notifyAnalysisStop(g_tabId); }
   g_FEN = "";
   g_tabId = null;
}

var g_backgroundEngineValid = true;
var g_backgroundEngine;

function InitializeBackgroundEngine() {
   if (!g_backgroundEngineValid) { return false; }

   if (g_backgroundEngine == null) {
      g_backgroundEngineValid = true;
      try {
          g_backgroundEngine = new Worker("garbochess/garbochess.js");
          g_backgroundEngine.onmessage = function (e) {
             if (e.data.match("^pv") == "pv") {
                if (matches = e.data.substr(3, e.data.length - 3).match(/Ply:(\d+) Score:(-*\d+) Nodes:(\d+) NPS:(\d+) (.+)/)) {
                   ply = matches[1];
                   score = Math.floor(matches[2] / 100) / 10;
                   if (g_FEN.indexOf(" b ") !== -1) { score = - score; }
                   if (score - Math.floor(score) == 0) { score += ".0"; }
                   nodes = matches[3];
                   nodesPerSecond = matches[4];
                   pv = matches[5];
                   notifyAnalysis(g_tabId, ply, score, nodes, nodesPerSecond, pv);
                }
             }
          }
      } catch (error) { g_backgroundEngineValid = false; }
   }
   return g_backgroundEngineValid;
}

function notifyAnalysis(tabId, ply, score, nodes, nodesPerSecond, pv) {
   chrome.extension.sendRequest({tabId: tabId, analysisPly: ply, analysisScore: score, analysisNodes: nodes, analysisnodesPerSecond: nodesPerSecond, analysisPv: pv}, function(response) {});
}

function notifyAnalysisStop(tabId) {
   chrome.extension.sendRequest({tabId: tabId, analysisPly: "", analysisScore: "", analysisNodes: "", analysisnodesPerSecond: "", analysisPv: ""}, function(response) {});
}

function analysisRequestHandler(request, sender, sendResponse) {
  if (typeof(request.analysisCommand) == "undefined") { return; }
  if (request.analysisCommand == "start") { AnalysisStart(sender.tab.id, request.FEN); }
  if (request.analysisCommand == "stop") { AnalysisStop(sender.tab.id); }
}

chrome.extension.onRequest.addListener(analysisRequestHandler);

function stopAnalysisOnRemovedAndUpdated(tabId) { AnalysisStop(tabId); }

chrome.tabs.onRemoved.addListener(stopAnalysisOnRemovedAndUpdated);
chrome.tabs.onUpdated.addListener(stopAnalysisOnRemovedAndUpdated);
