/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2010 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

/*

HTML, CSS and Javascript code optimized for use as extension
of Google Chrome v6 or later. Don't use with any other browser.

*/

var pgn4web_pgnLinks = new Array();
var pgn4web_pgnLinksNum = 0;

for(i=0; i<document.links.length; i++) {
  if (document.links[i].href.match(/\.pgn$/i)) {
    pgn4web_pgnLinks[pgn4web_pgnLinksNum++] = document.links[i].href;
  }
}
if (pgn4web_pgnLinksNum > 0) {
  chrome.extension.sendRequest({pgnLinks: pgn4web_pgnLinks}, function(response) {}); 
}

