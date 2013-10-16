# pgn4web javascript chessboard
# copyright (C) 2009-2013 Paolo Casaschi
# see README file and http://pgn4web.casaschi.net
# for credits, license and more details

# bash script to create a custom pgn4web package
# run as "bash script.sh"

set +o posix

pre="testapp"

if [ "$1" == "--clean" ]; then
  if ls -1 "$pre"* 2> /dev/null; then
    read -p "Delete the $pre* file listed above (type YES to proceed)? " -r
    if [ "$REPLY" == "YES" ]; then
      rm -f "$pre"*
      echo "info: deleted $pre* files"
    else
      echo "info: $pre* files not deleted"
    fi
  else
    echo "info: $pre* files not found"
  fi
  exit 0
fi

id=$1
if [ -z "$id" ]; then
  echo "usage: $0 id [pgn]"
  exit 1
fi
if [[ $id =~ [^a-zA-Z] ]]; then
  echo "error: id must be only letters: $id"
  exit 2
fi

pgn=$2
if [ -z "$pgn" ]; then
  pgn="live/live.pgn"
fi
if [ ! -f "$pgn" ]; then
  echo "error: pgn file not found: $pgn"
  exit 3
fi

cp live-games-app.php "$pre-$id.php"
sed -i.bak 's/live-games-app.appcache/'"$pre-$id.appcache"'/g' "$pre-$id.php"
sed -i.bak 's/live-games-app-engine/'"$pre-eng-$id"'/g' "$pre-$id.php"
needle='var titleString = "Live Games";'
grep -q "$needle" "$pre-$id.php"
if [ $? -ne 0 ]; then
  echo "warning: pgnData assignement check failed"
else
  sed -i.bak 's/'"$needle"'/'"$needle"' pgnData=\"'"$pre-$id.pgn"'\"\; SetPgnUrl(pgnData);/g' "$pre-$id.php"
fi
rm -f "$pre-$id.php.bak"

cp live-games-app-engine.php "$pre-eng-$id.php"

cp live-games-app.appcache "$pre-$id.appcache"
sed -i.bak 's/live-games-app.php/'"$pre-$id.php"'/g' "$pre-$id.appcache"
sed -i.bak 's/live-games-app-engine.php/'"$pre-eng-$id.php"'/g' "$pre-$id.appcache"
echo "# $(date)" >> "$pre-$id.appcache"
rm -f "$pre-$id.appcache.bak"

rm -f "$pre-$id.pgn"
ln -s $pgn "$pre-$id.pgn"

echo "info: done $pre-$id.php with id=$id pgn=$pgn"

