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

See about.html and help.html for credits, license and more details.


Additional credits:

the download.png and refresh.png icons licensed by interactivemania
http://www.interactivemania.com under a Creative Commons Attribution-No 
Derivative Works 3.0 license http://creativecommons.org/licenses/by-nd/3.0/


Enhancements:

background.html, manifest.json: webRequest 
enable the webRequest extension API when stable.
http://code.google.com/chrome/extensions/api_index.html
http://crbug.com/60101

about.html: demos
enable the "try this" demo paragraphs in about.html by removing the
"display: none;" css attribute of "div.try". Demo paragraphs are
disabled at the moment because extension pages dont get context menus:
http://crbug.com/51461


Bugs:

background.html: "about" context menu item
until chromium bug 63545 is resolved, two entries are required
and adding the about menu to the "selection" context would result in
duplicated items when a selected link is right-clicked
see http://crbug.com/63545
Verify in background.html
Fixed in google chrome v13.0.782.10

background.html: "link" context menu
until chromium bug 63965 is resolved the context menu will not appear for
links shown as images like this <a href=game.pgn><img src=image.jpeg/></a>
see http://crbug.com/63965
Verify this on the page (after login):
http://www.chessgames.com/perl/chessplayer?pid=18000
Fixed in google chrome v13.0.782.10

background.html and manifest.json match pattern definition:
because of how match patterns can be defined, some URLs might be identified
as PGN chess games URL even if they should not, for instance
http://host/file.html?pgnData=games.pgn would match *://*/*.pgn
This will lead to a context menu appearing when it should not (but the
mouse pointer will not change and the viewer will not open the link).
see http://crbug.com/84024
Verify this on the page:
http://code.google.com/p/pgn4web/wiki/BrowserExtension_GoogleChrome

popup.html: popup css
popup.html is over-engineered to cope with following issues:
- wrong height assigned by default (on small screen netbooks)
http://crbug.com/76899
- min-/max-/height attribute ignored for the body element
http://crbug.com/50192
- scrollbar width is not taken into account for margins/padding
http://crbug.com/31494

about.html: help page
this page is currently assigned as the "options" page while there are not
any configurable options; it should rather assigned as "help" page once
available, see this bug:
http://crbug.com/29849
