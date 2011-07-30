#
#  pgn4web javascript chessboard
#  copyright (C) 2009, 2011 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details
#

Chess games viewer extension for Google Chrome, providing 
an interactive chessboard showing chess games from links to
PGN URLs, from local PGN files and from PGN text, using 
context menus and page actions.

Part of the pgn4web project http://pgn4web.casaschi.net

See about.html and help.html for more details, including
full credits and license.


Additional credits:

the GarboChess chess engine code is licensed by Gary Linscott under
BSD license. See garbochess/README.txt for more info.
Homepage: http://forwardcoding.com/projects/ajaxchess/chess.html
Repository: https://github.com/glinscott/Garbochess-JS/

the download.png and refresh.png icons licensed by interactivemania
http://www.interactivemania.com under a Creative Commons Attribution-No 
Derivative Works 3.0 license http://creativecommons.org/licenses/by-nd/3.0/


Enhancements:

background.html, manifest.json: webRequest 
enable the webRequest extension API when stable
info http://code.google.com/chrome/extensions/api_index.html
http://crbug.com/60101

background.html: 
register the extension as content handler for application/x-chess-pgn
when registerContentHandler support is available; hopefullt this allows
opening the chess games viewer by doubleclicking items in the chrome
downloads page; needs assessing impact on the plain download of a PGN file:
check what happens when clicking a link on the page or the download icon
of the popup.
http://crbug.com/86115 (and other, search for registerContentHandler)

about.html: demos
enable the demo paragraphs in about.html by removing the "display: none;"
css attribute of "div.try", demo paragraphs being disabled at the moment
because extension pages dont get context menus. 
http://crbug.com/51461
Fixed in google chrome (dev) v14.0.825.0, but needs to verify that a "link"
context menu appears for links to chrome-extension://*/*.pgn files.

Bugs:

background.html: "about" context menu item
until chromium bug 63545 is resolved, two entries are required
and adding the about menu to the "selection" context would result in
duplicated items when a selected link is right-clicked.
http://crbug.com/63545
Verify in background.html
Fixed in google chrome (dev) v13.0.782.10

background.html: "link" context menu
until chromium bug 63965 is resolved the context menu will not appear for
links shown as images like this <a href=game.pgn><img src=image.jpeg/></a>
http://crbug.com/63965
Verify this on the page (after login):
http://www.chessgames.com/perl/chessplayer?pid=18000
Fixed in google chrome (dev) v13.0.782.10

background.html and manifest.json match pattern definition:
because of how match patterns can be defined, some URLs might be identified
as PGN chess games URL even if they should not, for instance
http://host/file.html?pgnData=games.pgn would match *://*/*.pgn
This will lead to a context menu appearing when it should not (but the
mouse pointer will not change and the viewer will not open the link).
http://crbug.com/84024
Verify this on the page:
http://code.google.com/p/pgn4web/wiki/BrowserExtension_GoogleChrome

popup.html: popup css
popup.html is affected by following issues:
- wrong height assigned by default (especially on small screen netbooks),
with as workaround setting maxHeight of pgnLinkList
http://crbug.com/76899
- min-/max-/height attribute ignored for the body element, otherwise the
above workaround could be simpler
http://crbug.com/50192
- scrollbar width is not taken into account for margins/padding, no
workaround implemented (anymore since pgn4web r6921)
http://crbug.com/31494

about.html: help page
this page is currently assigned as the "options" page while there are not
any configurable options; it should rather assigned as "help" page.
http://crbug.com/29849
