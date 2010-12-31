/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2011 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

/*

HTML, CSS and Javascript code optimized for use as extension
of Google Chrome v6 or later. Don't use with any other browser.

*/

var pgn4web_pgnLinks = new Array();

var pgn4web_cursorDef = "url(" + chrome.extension.getURL("cursor-small.png") + ") 1 6, auto";
// var pgn4web_cursorDef = "url(" + chrome.extension.getURL("cursor-large.png") + ") 1 6, auto";

for(l in document.links) {
  if (validatePgnUrl(document.links[l].href)) {
    document.links[l].addEventListener("mouseover", function(){this.style.cursor = pgn4web_cursorDef;}, false);
    if (pgn4web_pgnLinks.indexOf(document.links[l].href) == -1) { 
      pgn4web_pgnLinks.push(document.links[l].href);
    }
  }
}

if (pgn4web_pgnLinks.length > 0) {
  chrome.extension.sendRequest({pgnLinks: pgn4web_pgnLinks}, function(response) {}); 
}

function validatePgnUrl(pgnUrl) {
  return (pgnUrl && (pgnUrl.match(/^(http|https):\/\/[^?#]+\.pgn($|\?.*$|#.*$)/i) !== null));
}

