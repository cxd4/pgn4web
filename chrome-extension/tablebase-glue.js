/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

function tablebaseSupportsFen(fenString) { return tablebaseSupportsFenJaet(fenString); }

function probeTablebase(fenString, probeTablebaseCallback) { probeTablebaseJaet(fenString, probeTablebaseCallback); }

var maxMenInTablebase = maxMenInTablebaseJaet = 6;
var minMenInTablebaseJaet = 3;
function tablebaseSupportsFenJaet(fenString) {
   return (((l = fenString.replace(/\s.*$/, "").replace(/[0-9\/]/g, "").length) >= minMenInTablebaseJaet) && (l <= maxMenInTablebaseJaet));
}

var probeTablebaseXMLHTTPRequest = null;
function probeTablebaseJaet(fenString, probeTablebaseCallback) {
   if (!tablebaseSupportsFenJaet(fenString)) {
      probeTablebaseCallback("<span class='egtbEval'>&middot;</span>", fenString);
      return;
   }

   try {
      if ((probeTablebaseXMLHTTPRequest !== null) && (typeof(probeTablebaseXMLHTTPRequest.abort) != "undefined")) { probeTablebaseXMLHTTPRequest.abort(); }
      probeTablebaseXMLHTTPRequest = new XMLHttpRequest();
      probeTablebaseXMLHTTPRequest.open("GET", "http://www.logicalchess.com/cgi-bin/dtx-hayes?fen=" + fenString.replace(/\s/g, "+"), true);
      probeTablebaseXMLHTTPRequest.onreadystatechange = function() {
         if (probeTablebaseXMLHTTPRequest.readyState == 4) {
            if (probeTablebaseXMLHTTPRequest.status == 200) {
               var whiteToMove = (fenString.indexOf(" b ") == -1);
               var stmMetricsResult = ["stmDtmResult", "stmDtcResult", "stmDtzResult", "stmDtz50Result"];
               for (ii = 0; ii < stmMetricsResult.length; ii++) {
                  stmResult = probeTablebaseXMLHTTPRequest.responseXML.documentElement.getElementsByTagName(stmMetricsResult[ii]);
                  if (stmResult[0]) {
                     stmRes = stmResult[0].firstChild.nodeValue;
                     if ((stmRes == "W") || (stmRes == "D") || (stmRes == "L")) { break; }
                  }
               }
               if (stmRes == "W") {
                  probeTablebaseCallback("<span class='egtbEval'>" + (whiteToMove ? "$18" : "$19") + "</span>" + tablebaseJaetMatchingMoves(stmRes), fenString);
               } else if (stmRes == "D") {
                  probeTablebaseCallback("<span class='egtbEval'>$11</span>" + tablebaseJaetMatchingMoves(stmRes), fenString);
               } else if (stmRes == "L") {
                  probeTablebaseCallback("<span class='egtbEval'>" + (whiteToMove ? "$19" : "$18") + "</span>", fenString);
               } else {
                  probeTablebaseCallback("<span class='egtbEval'>&middot;</span>", fenString);
               }
            }
         else { probeTablebaseCallback("<span class='egtbEval'>&middot;&middot;</span>", fenString); }
            probeTablebaseXMLHTTPRequest = null;
         }
      };
      probeTablebaseXMLHTTPRequest.send(null);
   } catch (e) {
      probeTablebaseXMLHTTPRequest = null;
      probeTablebaseCallback("<span class='egtbEval'>&middot;&middot;</span>", fenString);
   }
}

function tablebaseJaetMatchingMoves(stmRes) {
   var resArray = new Array();
   var tablebaseMoves = probeTablebaseXMLHTTPRequest.responseXML.documentElement.getElementsByTagName("move");
   var moveMetricsResult = ["moveDtmResult", "moveDtcResult", "moveDtzResult", "moveDtz50Result"];
mainLoop:
   for (ii = 0; ii < tablebaseMoves.length; ii++) {
      for (jj = 0; jj < moveMetricsResult.length; jj++) {
         moveResult = tablebaseMoves[ii].getElementsByTagName(moveMetricsResult[jj]);
         if (moveResult[0] && (moveResult[0].firstChild.nodeValue === stmRes)) {
            moveSan = tablebaseMoves[ii].getElementsByTagName("moveSan");
            if (moveSan[0]) {
               resArray.push(moveSan[0].firstChild.nodeValue);
               continue mainLoop;
            }
         }
      }
   }
   var res = "";
   if (tablebaseMoves.length > resArray.length) {
     for (ii = 0; ii < resArray.length; ii++) {
       if (ii === 0) { res += "$254 "; }
       else if (ii > 0 && ii < resArray.length) { res += ", "; }
       res += "<span class='move'>" + resArray[ii].replace(/\+/, "") + "</span>";
     }
   }
   return res;
}

