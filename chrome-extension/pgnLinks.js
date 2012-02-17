/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

/*

HTML, CSS and Javascript code optimized for use as extension
of Google Chrome. Don't use with any other browser.

*/

function notifyPgnHrefLinks() {

  var pgnLinkRegExp = new RegExp("^((http|https):\\/\\/|" + chrome.extension.getURL("") + ")[^?#]+\\.pgn($|\\?.*$|#.*$)", "i");
  function validatePgnUrl(pgnUrl) {
    return (pgnUrl && pgnLinkRegExp.test(pgnUrl));
  }

  var pgn4web_pgnHrefLinks = new Array();

  var pgn4web_cursorDef = "url(" + chrome.extension.getURL("cursor-small.png") + ") 1 6, auto";

  for(l in document.links) {
    if (validatePgnUrl(document.links[l].href)) {
      document.links[l].addEventListener("mouseover", function(){this.style.cursor = pgn4web_cursorDef;}, false);
      if (pgn4web_pgnHrefLinks.indexOf(document.links[l].href) == -1) {
        pgn4web_pgnHrefLinks.push(document.links[l].href);
      }
    }
  }

  if (pgn4web_pgnHrefLinks.length > 0) {
    chrome.extension.sendRequest({pgnHrefLinks: pgn4web_pgnHrefLinks}, function(res){});
  }

}

//
// if any dynamically added links are missing, the code below could replace the
// plain call to notifyPgnHrefLinks() in order to be sure notifyPgnHrefLinks()
// is executed after page load; normally it should not be necessary
//
// if (document.readyState == "complete") { notifyPgnHrefLinks(); }
// else { window.addEventListener("load", notifyPgnHrefLinks, false); }
//
notifyPgnHrefLinks();

