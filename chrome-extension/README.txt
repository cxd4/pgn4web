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

the download.png, edit.png, file.png, files.png, help.png and refresh.png PNG
icons are licensed by Interactivemania http://www.interactivemania.com under a
Creative Commons Attribution-No Derivative Works 3.0 license
http://creativecommons.org/licenses/by-nd/3.0/
the refresh-mosaic.png PNG icon is derived from refresh.png

the endgame tablebase assessments are from the Lokasoft tablebase
webservice http://www.lokasoft.nl/tbweb.htm


Beta and development versions:

check for testing_* variables used for testing new functionality in beta,
development and canary versions of Google Chrome; updates of some settings of
manifest.json might be required for testing.


Enhancements:

background.html, manifest.json, chess-games-viewer.html,
live-mosaic-viewer.html: webRequest API
info http://code.google.com/chrome/extensions/api_index.html
http://crbug.com/60101
Implemented for the experimental API, see testing_webRequest in background.html,
also needs "experimental" permission in manifest.json.
Verify on http://chesstempo.com/pgn-viewer.html and http://www.bennedik.de/Silverboard.html
Requires increasing minimum_chrome_version, unless the new API call
chrome.webRequest.onBeforeRequest.addListener is within a "try".

background.html, manifest.json: registerContentHandler
register the extension as content handler for application/x-chess-pgn
this should allow opening the chess games viewer by doubleclicking items in
the chrome downloads page; needs assessing impact on the plain download of
a PGN file: check what happens when clicking a link on the page or the
download icon of the popup.
http://crbug.com/86115 (and other, search for registerContentHandler)
Requires increasing minimum_chrome_version (new API).

manifest.json: match mime-types in content scripts
enhance PGN/ZIP URL detection/validation by matching by mime-type when
supported.
http://crbug.com/35070


Bugs:

background.html and manifest.json match pattern definition:
because of how match patterns can be defined, some URLs might be identified
as PGN chess games URL even if they should not, for instance
http://host/file.html?pgnData=games.pgn would match *://*/*.pgn
This will lead to a context menu appearing when it should not (but the
mouse pointer will not change and the viewer will not open the link).
http://crbug.com/84024
Possible workaround exist if context scripts could manipulate context menus.
http://crbug.com/77023
Verify on http://code.google.com/p/pgn4web/wiki/SandBox

manifest.json, background.html and pgnLinks.js: access to file:// URLs
XHR access to file:// URLs is broken; once this is fixed consider extending
the PGN URL definition to include file:// URLs (in the URL validation of
background.html and pgnLinks.js) and injecting the content script into
file:// pages (replace the match pattern *://*/* with <all_urls> in
manifest.json). Relevant for testing sites locally.
http://crbug.com/41024
monitor similar bug for ftp:// URLs, allthough less relevant.
http://crbug.com/64826

popup.html: hardcoded maximum popup height
- Google Chrome has an hardcoded maximums popup height of 600px (width is 800px);
Monitor the bug report below and update the popup.html code in case of any
changes to the hardcoded maximum popup height.
http://crbug.com/36080
http://src.chromium.org/viewvc/chrome/trunk/src/chrome/browser/ui/views/extensions/extension_popup.cc?view=markup
Verify the hardcoded popup height by inpsecting the popup from a page with a 
large number of PGN links such as http://pgnmentor.com/files.html, clearing 
document.getElementById("pgnLinkList").style.maxHeight and reading the
popup outerHeight value.

popup.html: scrollbar width ignored
no fix implemented/required, just monitor the bug report below
http://crbug.com/31494

Other:

about.html, help.html, manifest.json: help page
verify the naming convention ("help") and the manifest syntax for the upcoming
"help" page and update manifest.json, about.html and help.html if needed.
http://crbug.com/29849

about.html, background.html: context menu icons and filename
add icons to context menus (same menu icons of chess-games-viewer.html) and to
the help/practice text in about.html when referencing context menu items.
Maybe remove context menu separators other than the help one.
http://crbug.com/53820
Also consider adding filename to the context menu string once the feature is
available.
http://crbug.com/60758

chess-games-viewer.html: fixed-width font size change issue
monitor fixed-width font size change issue and check layout of the game
selection dropdown menu once the bug is fixed.
http://crbug.com/91922
bug fixed in google chrome 17.0.937.0, extension looks ok
