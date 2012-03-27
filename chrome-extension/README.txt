#
#  pgn4web javascript chessboard
#  copyright (C) 2009, 2012 Paolo Casaschi
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

the endgame tablebase assessments are from John Tamplin's webservice
http://chess.jaet.org/endings/

the download.png, edit.png, file.png, files.png, help.png and refresh.png PNG
icons are licensed by Interactivemania http://www.interactivemania.com under a
Creative Commons Attribution-No Derivative Works 3.0 license
http://creativecommons.org/licenses/by-nd/3.0/
the refresh-mosaic.png PNG icon is derived from refresh.png


Beta and development versions:

check for testing_* variables used for testing new functionality in beta,
development and canary versions of Google Chrome; updates of some settings of
manifest.json might be required for testing.


Enhancements:

background.html, manifest.json, chess-games-viewer.html,
live-mosaic-viewer.html: webRequest API
http://crbug.com/60101
Verify on http://chesstempo.com/pgn-viewer.html and http://www.bennedik.de/Silverboard.html
Implemented as of Google Chrome 17.0.958.0.
Initially the extension preserves backward compatibility, once Google Chrome
stable version 17 is released, consider removing "try" statements and set
minimum_chrome_version to 17.

background.html, manifest.json: registerContentHandler
register the extension as content handler for application/x-chess-pgn
this should allow opening the chess games viewer by doubleclicking items in
the chrome downloads page; needs assessing impact on the plain download of
a PGN file: check what happens when clicking a link on the page or the
download icon of the popup.
http://crbug.com/86115 (and other, search for registerContentHandler)
Likely requires increasing minimum_chrome_version.

manifest.json: match mime-types in content scripts
enhance PGN/ZIP URL detection/validation by matching by mime-type when
supported.
http://crbug.com/35070

manifest.json, background.html chess-games-viewer.html,
live-mosaic-viewer.html: storage API
consider using the storage API, for storing local settings and/or to synch
settings (if any) across computers; check limitations in the amount of data
stored (the engine analysis data is about 1MB for 4000 entries); settings
synch might not be supported for self-hosted extensions.
http://crbug.com/47327

background.html: replace file download code with the downloads API
replace the file download code for popup.html witht he download API,
currently experimental; make sure it works with data URIs.
http://crbug.com/12133

background.html: help prompt after new install
use the extension API, currently experimental.
no crbug yet

tablebase-gue.js: support larger set of tablebase
replace the lokasoft tablebase with http://chess.jaet.org/endings/ once
the DTM metric is available.


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


Other:

about.html, help.html, manifest.json: help page
verify the naming convention ("help") and the manifest syntax for the upcoming
"help" page and update manifest.json, about.html and help.html as needed.
http://crbug.com/29849

about.html, background.html: context menu icons and filename
add icons to context menus (same menu icons of chess-games-viewer.html) and to
the help/practice text in about.html when referencing context menu items.
Maybe remove context menu separators other than the help one.
http://crbug.com/53820
Also consider adding filename to the context menu string once the feature is
available.
http://crbug.com/60758

popup.html, manifest.json: avoid using deprecated chrome.tabs.getSelected
chrome.tabs.getSelected is deprecated as of chrome 16; initially the extension
preserves backward compatibility, once Google Chrome stable version 18 is
released, consider removing "try" statements and set minimum_chrome_version to
18.

manifest.json: monitor requirement for setting "manifest_version" to 2 and for
setting related "content_security_policy" fields; currenly "manifest_version"
is set to 1.
http://crbug.com/105796
http://crbug.com/107538
http://code.google.com/p/pgn4web/issues/detail?id=111
