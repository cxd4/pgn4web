# bash script to zip the pgn4web chrome extension for packaging

pgn4webVer=$(grep "var pgn4web_version = " ../pgn4web.js | awk -F "\'" '{print$2}')
chromeExtVer=$(grep '"version":' manifest.json | awk -F "\"" '{print$4}')

pgn4webChromeExtFilename="Chess-games-viewer-pgn4web-chrome-extension-$chromeExtVer-$pgn4webVer.zip"

if [ -e ../../"$pgn4webChromeExtFilename" ]; then
  echo "Error: pgn4web package already exists (../../$pgn4webChromeExtFilename)"
  exit 1
fi

zip -9r ../../"$pgn4webChromeExtFilename" * -x *.svn/* -x zip-chrome-extension.sh -x updateInfo.xml

cd ..
zip -9r ../"$pgn4webChromeExtFilename" demoGames.pgn demoLiveGames.pgn fide-lookup.js js-unzip/* license-gpl-2.0.txt live-mosaic-tile.html live-mosaic-viewer.html pawn.ico pawns.png pgn4web.js pgn4web-help.html zipped.zip alpha/README.txt alpha/index.html alpha/24/* alpha/48/bp.png alpha/48/index.html alpha/128/* fonts/README.txt fonts/index.html fonts/pgn4web-font-LiberationSans.css fonts/LiberationSans-Regular.woff fonts/LiberationSans-Bold.woff fonts/pgn4web-font-ChessSansUsual.css fonts/ChessSansUsual.woff -x *.svn/*

