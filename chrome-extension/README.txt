#
#  pgn4web javascript chessboard
#  copyright (C) 2009, 2011 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details
#

Chess games viewer with interactive chessboard, showing chess 
games from PGN URLs and from PGN text, using context menus.

Part of the pgn4web project http://pgn4web.casaschi.net

See help.html for credits, license and more details.

Additional credits:
the download.png and refresh.png icons licensed by interactivemania
http://www.interactivemania.com under a Creative Commons Attribution-No 
Derivative Works 3.0 license http://creativecommons.org/licenses/by-nd/3.0/

Bugs:

background.html, about context menu item:
until chromium bug 63545 is resolved, four entries are required
and adding the about menu to the "selection" context would result in
duplicated items when a selected link is right-clicked
see http://code.google.com/p/chromium/issues/detail?id=63545

background.html, "link" context menu:
until chromium bug 63965 is resolved the context menu will not appear for
links shown as images like this <a href=game.pgn><img src=image.jpeg/></a>
see http://code.google.com/p/chromium/issues/detail?id=63965

background.html and manifest.json match pattern definition:
because of how match patterns can be defined, some URLs might be identified
as PGN chess games URL even if they should not, for instance
http://host/file.html?pgnData=games.pgn would match *://*/*.pgn
This will lead to a context menu appearing when it should not (but the
mouse pointer will not change and the viewer will not open the link). 

