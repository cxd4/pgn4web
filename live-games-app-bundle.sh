# pgn4web javascript chessboard
# copyright (C) 2009-2014 Paolo Casaschi
# see README file and http://pgn4web.casaschi.net
# for credits, license and more details

# bash script to create a custom live games web application bundle
# run as "bash script.sh"

set +o posix

pre="lga"

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
if [ -z "$id" ] || [ "$id" == "--help" ]; then
  echo "usage: $(basename $0) id [pgn] [name]"
  echo "use --clean as first parameter to remove old bundles"
  echo "please note: you can only deploy one bundle per domain (more bundles on the same domain would conflict on local storage)"
  exit 1
fi
if [[ $id =~ [^a-zA-Z0-9] ]]; then
  echo "error: id must be only letters and numbers: $id"
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

name=$3
if [[ $name =~ [^a-zA-Z0-9\ \'#-] ]]; then
  echo "error: name must be only letters, numbers and spaces: $name"
  exit 3
fi

cp live-games-app.php "$pre-$id.php"
sed -i.bak 's/live-games-app.appcache/'"$pre-$id.appcache"'/g' "$pre-$id.php"
sed -i.bak 's/live-games-app-engine/'"$pre-$id-engine"'/g' "$pre-$id.php"
sed -i.bak 's/live-games-app.pgn/'"$pre-$id.pgn"'/g' "$pre-$id.php"
if [[ -n $name ]]; then
  sed -i.bak 's/Live Games/'"$name"'/g' "$pre-$id.php"
fi
rm -f "$pre-$id.php.bak"

cp live-games-app-engine.php "$pre-$id-engine.php"

cp live-games-app.appcache "$pre-$id.appcache"
sed -i.bak 's/live-games-app.php/'"$pre-$id.php"'/g' "$pre-$id.appcache"
sed -i.bak 's/live-games-app-engine.php/'"$pre-$id-engine.php"'/g' "$pre-$id.appcache"
echo "# $(date)" >> "$pre-$id.appcache"
rm -f "$pre-$id.appcache.bak"

cp live-games-app.webapp "$pre-$id.webapp"
sed -i.bak 's/live-games-app.php/'"$pre-$id.php"'/g' "$pre-$id.webapp"
if [[ -n $name ]]; then
  sed -i.bak 's/Live Games/'"$name"'/g' "$pre-$id.webapp"
fi
rm -f "$pre-$id.webapp.bak"

rm -f "$pre-$id.pgn"
ln -s $pgn "$pre-$id.pgn"

echo "info: done $pre-$id.php with id=$id pgn=$pgn name=$name"

