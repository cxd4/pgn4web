/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

function tablebaseSupportsFen(fenString) { return tablebaseSupportsFenLokasoft(fenString); }

function probeTablebase(fenString, probeTablebaseCallback) { probeTablebaseLokasoft(fenString, probeTablebaseCallback); }

var maxMenInTablebase = maxMenInTablebaseLokasoft = 5;
var minMenInTablebaseLokasoft = 3;
function tablebaseSupportsFenLokasoft(fenString) {
   return (((l = fenString.replace(/\s.*$/, "").replace(/[0-9\/]/g, "").length) >= minMenInTablebaseLokasoft) && (l <= maxMenInTablebaseLokasoft));
}

var probeTablebaseXMLHTTPRequest = null;
function probeTablebaseLokasoft(fenString, probeTablebaseCallback) {
   if (!tablebaseSupportsFenLokasoft(fenString)) {
      probeTablebaseCallback("<span class='egtbEval'>&middot;</span>", fenString);
      return;
   }

   try {
      if ((probeTablebaseXMLHTTPRequest !== null) && (typeof(probeTablebaseXMLHTTPRequest.abort) != "undefined")) { probeTablebaseXMLHTTPRequest.abort(); }
      probeTablebaseXMLHTTPRequest = new XMLHttpRequest();
      probeTablebaseXMLHTTPRequest.open("POST", "http://www.lokasoft.nl/tbweb/tbapi.asp", true);
      probeTablebaseXMLHTTPRequest.setRequestHeader("Content-Type", "text/xml; charset=utf-8");
      probeTablebaseXMLHTTPRequest.setRequestHeader("SOAPAction", "http://lokasoft.org/action/TB2ComObj.ProbePosition");
      probeTablebaseXMLHTTPRequest.onreadystatechange = function() {
         if (probeTablebaseXMLHTTPRequest.readyState == 4) {
            if (probeTablebaseXMLHTTPRequest.status == 200) {
               if (assessment = probeTablebaseXMLHTTPRequest.responseXML.documentElement.getElementsByTagName("Result")[0].firstChild.nodeValue) {
                  if (assessment === "0") { probeTablebaseCallback("<span class='egtbEval'>$11</span>", fenString); }
                  else if (matches = assessment.match(/^(M|-M)(\d+)$/)) {
                     whiteToMove = (fenString.indexOf(" b ") == -1);
                     whiteWinning = ((matches[1] == "M") && (whiteToMove)) || (!(matches[1] == "M") && !(whiteToMove));
                     probeTablebaseCallback("<span class='egtbEval'>" + (whiteWinning ? "$18" : "$19") + "</span>" + (matches[2] === "0" ? "" : matches[2]), fenString);
                  } else { probeTablebaseCallback("<span class='egtbEval'>&middot;</span>", fenString); }
               } else { probeTablebaseCallback("<span class='egtbEval'>&middot;</span>", fenString); }
            } else { probeTablebaseCallback("<span class='egtbEval'>&middot;</span>", fenString); }
            probeTablebaseXMLHTTPRequest = null;
         }
      };
      request = '<SOAP-ENV:Envelope xmlns:ns3="http://www.w3.org/2001/XMLSchema" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" xmlns:ns0="http://schemas.xmlsoap.org/soap/encoding/" xmlns:ns1="http://lokasoft.org/message/" xmlns:ns2="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><SOAP-ENV:Header/><ns2:Body><ns1:ProbePosition><fen xsi:type="ns3:string">' + fenString + '</fen></ns1:ProbePosition></ns2:Body></SOAP-ENV:Envelope>';
      probeTablebaseXMLHTTPRequest.send(request);
   } catch (e) {
      probeTablebaseXMLHTTPRequest = null;
      probeTablebaseCallback("<span class='egtbEval'>&middot;&middot;</span>", fenString);
   }
}
