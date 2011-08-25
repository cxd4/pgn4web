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
            chrome.tabs.sendRequest(g_tabId, {analysisNotification: "stopped"}, function(res){});
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
var egStored_FEN;
var egStored_ev;
var egStored_pv;
var egStored_nodes;
var egStored_maxNodesPerSecond;
var egStored_newAnalysisAdded = false;
resetAnalysisData();

function storeAnalysis(FEN, ev, pv, nodes) {

   maxEv = 99.9;
   minNodesForAnnotation = 12345;

   ev = Math.round(ev / 100) / 10;
   if (ev < -maxEv) { ev = -maxEv; } else if (ev > maxEv) { ev = maxEv; }

   if ((nodes < minNodesForAnnotation) && (ev < maxEv) && (ev > -maxEv) && (ev !== 0)) { return false; }

   additionNeeded = false;
   deletionNeeded = false;

   FEN = FEN.replace(/\s+\d+\s+\d+\s*$/, "");
   index = egStored_FEN.indexOf(FEN);
   
   if (index == -1) {
      additionNeeded = true;
      if (egStored_FEN.length >= egStored_max) {
         deletionNeeded = true;
         index = 0;
      }
   } else {
      if (nodes > egStored_nodes[index]) {
         additionNeeded = true;
         deletionNeeded = true;
      }
   }

   if (deletionNeeded) {
      egStored_FEN.splice(index,1);
      egStored_ev.splice(index,1);
      egStored_pv.splice(index,1);
      egStored_nodes.splice(index,1);
   }

   if (additionNeeded) {
      if (FEN.indexOf(" b ") !== -1) { ev = -ev; }
      ev = (ev > 0 ? "+" : "") + ev; 
      if (ev.indexOf(".") == -1) { ev += ".0"; }

      egStored_FEN.push(FEN);
      egStored_ev.push(ev);
      egStored_pv.push(pv);
      egStored_nodes.push(nodes);
      
      egStored_newAnalysisAdded = true;
   }
   return additionNeeded;
}

function getAnalysisIndexFromFEN(FEN) { return egStored_FEN.indexOf(FEN.replace(/\s+\d+\s+\d+\s*$/, "")); }

function getAnalysisEvFromIndex(index) { return egStored_ev[index]; }

function getAnalysisPvFromIndex(index) { return egStored_pv[index]; }

function getAnalysisMaxNodesPerSecond() { return egStored_maxNodesPerSecond; }

function getStoredPositions() { return egStored_FEN.length; }

function resetAnalysisData() {
   egStored_FEN = new Array("rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1".replace(/\s+\d+\s+\d+\s*$/, ""));
   egStored_ev = new Array("0.0");
   egStored_pv = new Array(" e4 e5 Nf3 Nf6 Nc3 Nc6 d4 exd4 Nxd4");
   egStored_nodes = new Array(); egStored_nodes[0] = 123456789;
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
                   ply = parseInt(matches[1], 10);
                   ev = parseInt(matches[2], 10);
                   nodes = parseInt(matches[3], 10);
                   nodesPerSecond = parseInt(matches[4], 10);
                   egStored_maxNodesPerSecond = Math.max(egStored_maxNodesPerSecond, nodesPerSecond);
                   pv = matches[5].replace(/(\+|#|checkmate|stalemate)/g, "");
                   if (storeAnalysis(g_FEN, ev, pv, nodes)) {
                      chrome.tabs.sendRequest(g_tabId, {analysisNotification: "newData"}, function(res){});
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


function deleteAnalysisInLocalStorage() {
   try {
      localStorage.removeItem("pgn4web_engine_glue_egSaved_FEN");
      localStorage.removeItem("pgn4web_engine_glue_egSaved_ev");
      localStorage.removeItem("pgn4web_engine_glue_egSaved_pv");
      localStorage.removeItem("pgn4web_engine_glue_egSaved_nodes");
   } catch (e) { return false; }
   return true;
}

function loadAnalysisFromLocalStorage() {
   try {
      val_FEN = JSON.parse(localStorage.getItem("pgn4web_engine_glue_egSaved_FEN"));
      val_ev = JSON.parse(localStorage.getItem("pgn4web_engine_glue_egSaved_ev"));
      val_pv = JSON.parse(localStorage.getItem("pgn4web_engine_glue_egSaved_pv"));
      val_nodes = JSON.parse(localStorage.getItem("pgn4web_engine_glue_egSaved_nodes"));
      if ((typeof(val_FEN.length) != "undefined") && (typeof(val_ev.length) != "undefined") && (typeof(val_pv.length) != "undefined") && (typeof(val_nodes.length) != "undefined") && (val_FEN.length === val_ev.length) && (val_FEN.length === val_pv.length) && (val_FEN.length === val_nodes.length)) {
         egStored_FEN = val_FEN;
         egStored_ev = val_ev;
         egStored_pv = val_pv;
         egStored_nodes = val_nodes;
      } else { return false; }
   } catch (e) { return false; }
   return true;
}

function saveAnalysisToLocalStorage() {
   if (egStored_newAnalysisAdded) {   
      try {
         localStorage.setItem("pgn4web_engine_glue_egSaved_FEN", JSON.stringify(egStored_FEN));
         localStorage.setItem("pgn4web_engine_glue_egSaved_ev", JSON.stringify(egStored_ev));
         localStorage.setItem("pgn4web_engine_glue_egSaved_pv", JSON.stringify(egStored_pv));
         localStorage.setItem("pgn4web_engine_glue_egSaved_nodes", JSON.stringify(egStored_nodes));
         egStored_newAnalysisAdded = false;
      } catch (e) {
         deleteAnalysisInLocalStorage();
         return false;
      }
   }
   return true;
}