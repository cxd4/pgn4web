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


Beta and development versions:

for testing new functionality with the beta and development versions of Google
Chrome, the background.html variable extensionChannel can be set to "stable"
(default), "beta" or "development" in order to trigger testing code in the 
extension's HTML, CSS and JAVASCRIPT (remember to manually update manifest.json
if needed).


Enhancements:

background.html, manifest.json: webRequest
enable the webRequest extension API when stable
info http://code.google.com/chrome/extensions/api_index.html
http://crbug.com/60101
Implemented for extensionChannel "development" (also needs "experimental"
permission in manifest.json).
Verify on http://chesstempo.com/pgn-viewer.html and http://www.bennedik.de/Silverboard.html
Requires increasing minimum_chrome_version (new API and permission)

background.html, manifest.json: registerContentHandler
register the extension as content handler for application/x-chess-pgn
this should allow opening the chess games viewer by doubleclicking items in
the chrome downloads page; needs assessing impact on the plain download of
a PGN file: check what happens when clicking a link on the page or the
download icon of the popup.
http://crbug.com/86115 (and other, search for registerContentHandler)
Requires increasing minimum_chrome_version (new API and permission)

about.html, background.html: "try this" examples
enable the example paragraphs in about.html by removing the "display: none;"
css attribute of "div.try" and updating the pgnUrlPattern/zipUrlPattern
definitions in background.html; example paragraphs being disabled at the
moment because extension pages dont get context menus.
http://crbug.com/51461
Implemented for extensionChannel "beta" and "development"
Fixed in google chrome (dev) v14.0.835.15
Requires increasing minimum_chrome_version (new pgnUrlPattern/zipUrlPattern
definition not backward compatible)


Bugs:

background.html and manifest.json match pattern definition:
because of how match patterns can be defined, some URLs might be identified
as PGN chess games URL even if they should not, for instance
http://host/file.html?pgnData=games.pgn would match *://*/*.pgn
This will lead to a context menu appearing when it should not (but the
mouse pointer will not change and the viewer will not open the link).
http://crbug.com/84024
Possible workaround if context scripts could manipulate context menus, see
http://crbug.com/77023
Verify on http://code.google.com/p/pgn4web/wiki/SandBox

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

about.html, manifest.json: help page
this page is currently assigned as the "options" page while there are not
any configurable options; it should rather assigned as "help" page.
http://crbug.com/29849
Requires increasing minimum_chrome_version (new manifest entry)


Other:

chess-games-viewer.html, live-mosaic-viewer.html: forcing page action
currently chessboard pages force the page action in order to include the PGN URL
since chrome-extension://*.pgn pages do not trigger webRequests events;
if the underlying issue is fixed, consider not forcing the page action (this
might expose a noCache URL parameter)
http://crbug.com/92395
