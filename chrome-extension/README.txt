#
#  pgn4web javascript chessboard
#  copyright (C) 2009, 2011 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details
#

Chess games viewer with interactive chessboard, showing chess 
games from PGN URLs and from PGN text, using context menus.

Part of the pgn4web project http://pgn4web.casaschi.net

See about.html and help.html for credits, license and more details.


Additional credits:

the download.png and refresh.png icons licensed by interactivemania
http://www.interactivemania.com under a Creative Commons Attribution-No 
Derivative Works 3.0 license http://creativecommons.org/licenses/by-nd/3.0/


Enhancements:

background.html, manifest.json: webRequest 
enable the webRequest extension API when stable. Info on this page:
http://code.google.com/chrome/extensions/api_index.html
when adding the webRequest API permission, consider adding the bookmarks
permission also and offer a one time popup to add a link to the bookmarks
bar;and have a link to do the same from the about page; some code for that:
//
// bookmarks[0].children[0].id is the bookmarks bar id
chrome.bookmarks.getTree(function(bookmarks) { chrome.bookmarks.create({'parentId': bookmarks[0].children[0].id, 'title': 'chess games viewer', 'url': 'chess-games-viewer.html#bottom'}); });
//
Otherwise, it might be enough to highlight better in the about.html page
how such a bookmark could be created.
This is a workaround for the extensions missing a launch page/icon, see
http://code.google.com/p/chromium/issues/detail?id=85735

about.html: examples
currently the about.html is very basic; ideally it should be a showcase of
the extension itself, offering a PGN text and some links to test the context
menus and showing the page icon. Possibly also a simulation of the live pages
too. This is not possible at the moment because extension pages apparently
dont get context menus:
http://code.google.com/p/chromium/issues/detail?id=51461
If this is ever fixed, then about.html should be enhanced. 
Please note that extension pages dont get content scripts either (see bug
#84843), but about.html explicitely loads the script at the end of the page.


Bugs:

background.html: "about" context menu item
until chromium bug 63545 is resolved, two entries are required
and adding the about menu to the "selection" context would result in
duplicated items when a selected link is right-clicked
see http://code.google.com/p/chromium/issues/detail?id=63545
Verify in background.html
Fixed in google chrome v13.0.782.10

background.html: "link" context menu
until chromium bug 63965 is resolved the context menu will not appear for
links shown as images like this <a href=game.pgn><img src=image.jpeg/></a>
see http://code.google.com/p/chromium/issues/detail?id=63965
Verify this on the page (after login):
http://www.chessgames.com/perl/chessplayer?pid=18000
Fixed in google chrome v13.0.782.10

background.html and manifest.json match pattern definition:
because of how match patterns can be defined, some URLs might be identified
as PGN chess games URL even if they should not, for instance
http://host/file.html?pgnData=games.pgn would match *://*/*.pgn
This will lead to a context menu appearing when it should not (but the
mouse pointer will not change and the viewer will not open the link).
see http://code.google.com/p/chromium/issues/detail?id=84024
Verify this on the page:
http://code.google.com/p/pgn4web/wiki/BrowserExtension_GoogleChrome

popup.html: popup css
popup.html is over-engineered to cope with following issues:
- wrong height assigned by default (on small screen netbooks)
http://code.google.com/p/chromium/issues/detail?id=76899
- min-/max-/height attribute ignored for the body element
http://code.google.com/p/chromium/issues/detail?id=50192
- scrollbar width is not taken into account for margins/padding
http://code.google.com/p/chromium/issues/detail?id=31494

about.html: help page
this page is currently assigned as the "options" page while there are not
any configurable options; it should rather assigned as "help" page once
available, see this bug:
http://code.google.com/p/chromium/issues/detail?id=29849

background.html: check removePgnLinksOnUpdated()
needs validation whether in removePgnLinksOnUpdated() the call to
mergePgnLinksAndShowIcon(tabId) is required to fix an obscure issue 
with clicking twice on a hash link.
Verify by removing that call and using
http://pgn4web.devio.us/pgn4web/testHashclick.html
