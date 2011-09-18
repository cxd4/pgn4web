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

the download.png, edit.png, files.png, help.png and refresh PNG icons are
licensed by Interactivemania http://www.interactivemania.com under a Creative
Commons Attribution-No Derivative Works 3.0 license
http://creativecommons.org/licenses/by-nd/3.0/

the endgame tablebase assessments are from the Lokasoft tablebase
webservice http://www.lokasoft.nl/tbweb.htm


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
with as workaround setting max-height of the pgnLinkList element and hiding
overflow on the body element.
http://crbug.com/76899
Verify inspecting the popup window, disabling the max-height property of
pgnLinkLists and the overflow property of the body element.
- min-/max-/height attribute ignored for the body element, otherwise the
above workaround could be simpler
http://crbug.com/50192


Other:

about.html, help.html, manifest.json: help page
verify the naming convention ("help") and the manifest syntax for the upcoming
"help" page and update manifest.json, about.html and help.html if needed.
http://crbug.com/29849

background.html: context menu icons
add icons to context menus (same menu icons of chess-games-viewer.html)
http://crbug.com/53820

chess-games-viewer.html, live-mosaic-viewer.html: force page action
currently chrome-extension://*.pgn pages do not trigger webRequests events;
hence chessboard pages need to force the page action in order to include the
loaded PGN URL; if the underlying issue is fixed, consider not forcing the page
action (allthough this might expose a noCache URL parameter)
http://crbug.com/92395 (see also webRequest http://crbug.com/60101 above)
