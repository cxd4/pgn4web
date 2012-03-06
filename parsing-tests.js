/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

function testAllMoves() {
  resetAlert();
  for (var gg = 0; gg < numberOfGames; gg++) {
    Init(gg);
    for (var vv = 0; vv < numberOfVars; vv++) {
      for (var hh = StartPlyVar[vv]; hh <= StartPlyVar[vv] + PlyNumberVar[vv]; hh++) {
        GoToMove(hh, vv);
      }
    }
  }
  return alertNumSinceReset;
}

function testRandomMoves(nn, pv, pg) {
  resetAlert();
  if (typeof(nn) == "undefined") { nn = numberOfGames * 100; }
  if (typeof(pv) == "undefined") { pv = 0.5; }
  if (typeof(pg) == "undefined") { pg = 0.1; }
  var vv = 0;
  var gg = 0;
  for (var ii = 0; ii < nn; ii++) {
    if (Math.random() < pg) {
      gg = Math.floor(numberOfGames * Math.random());
      Init(gg);
      vv = 0;
    }
    if (Math.random() < pv) {
      vv = Math.floor(numberOfVars * Math.random());
    }
    var hh = StartPlyVar[vv] + Math.floor((PlyNumberVar[vv] + 1) * Math.random());
    GoToMove(hh, vv);
  }
  return alertNumSinceReset;
}

