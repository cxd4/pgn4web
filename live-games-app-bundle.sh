# pgn4web javascript chessboard
# copyright (C) 2009-2014 Paolo Casaschi
# see README file and http://pgn4web.casaschi.net
# for credits, license and more details

# bash script to create a custom live games web application bundle
# run as "bash script.sh"

set +o posix

pre="lga"

if [ "$1" == "--delete" ] || [ "$1" == "-d" ]; then
  id=$2
  if [[ $id =~ [^a-zA-Z0-9] ]]; then
    echo "error: id must be only letters and numbers: $id"
    exit 2
  fi
  del="$pre-$id"*
  if ls -1 $del 2> /dev/null; then
    echo
    read -p "Delete the $del file listed above (type YES to proceed)? " -r
    if [ "$REPLY" == "YES" ]; then
      rm -f $del
      echo "info: deleted $del files"
    else
      echo "info: $del files not deleted"
    fi
  else
    echo "info: $del files not found"
  fi
  exit 0
fi

id=$1
if [ -z "$id" ] || [ "$id" == "--help" ]; then
  echo "usage: $(basename $0) [--delete] id [pgn] [name]"
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
# if [ ! -f "$pgn" ]; then
#  echo "error: pgn file not found: $pgn"
#  exit 3
# fi

name=$3
if [[ $name =~ [^a-zA-Z0-9\ \'#-] ]]; then
  echo "error: name must be only letters, numbers and spaces: $name"
  exit 3
fi

cp live-games-app.php "$pre-$id.php"
sed -i.bak 's/live-games-app.appcache/'"$pre-$id.appcache"'/g' "$pre-$id.php"
sed -i.bak 's/live-games-app-engine/'"$pre-$id-engine"'/g' "$pre-$id.php"
sed -i.bak 's/live-games-app-icon-\([0-9]\+\)x\([0-9]\+\).\(png\|ico\)/'"$pre-$id-icon-\1x\2.\3"'/g' "$pre-$id.php"
sed -i.bak 's/live-games-app.pgn/'"$pre-$id.pgn"'/g' "$pre-$id.php"
if [[ -n $name ]]; then
  sed -i.bak 's/Live Games/'"$name"'/g' "$pre-$id.php"
fi
sed -i.bak 's/enableLogging = false;/enableLogging = true;/g' "$pre-$id.php"
rm -f "$pre-$id.php.bak"

set TZ=UTC
echo $(date +"%Y-%m-%d %H:%M:%S +") >> "$pre-$id.log"

cp live-games-app-engine.php "$pre-$id-engine.php"
sed -i.bak 's/live-games-app-icon-\([0-9]\+\)x\([0-9]\+\).\(png\|ico\)/'"$pre-$id-icon-\1x\2.\3"'/g' "$pre-$id-engine.php"
rm -f "$pre-$id-engine.php.bak"

cp live-games-app.appcache "$pre-$id.appcache"
sed -i.bak 's/live-games-app.php/'"$pre-$id.php"'/g' "$pre-$id.appcache"
sed -i.bak 's/live-games-app-engine.php/'"$pre-$id-engine.php"'/g' "$pre-$id.appcache"
sed -i.bak 's/live-games-app-icon-\([0-9]\+\)x\([0-9]\+\).\(png\|ico\)/'"$pre-$id-icon-\1x\2.\3"'/g' "$pre-$id.appcache"
echo "# $(date)" >> "$pre-$id.appcache"
if [[ -n $name ]]; then
  sed -i.bak 's/Live Games/'"$name"'/g' "$pre-$id.appcache"
fi
rm -f "$pre-$id.appcache.bak"

cp live-games-app.webapp "$pre-$id.webapp"
sed -i.bak 's/live-games-app.php/'"$pre-$id.php"'/g' "$pre-$id.webapp"
sed -i.bak 's/live-games-app-icon-\([0-9]\+\)x\([0-9]\+\).\(png\|ico\)/'"$pre-$id-icon-\1x\2.\3"'/g' "$pre-$id.webapp"
if [[ -n $name ]]; then
  sed -i.bak 's/Live Games/'"$name"'/g' "$pre-$id.webapp"
fi
rm -f "$pre-$id.webapp.bak"

cp live-games-app-icon-128x128.png "$pre-$id-icon-128x128.png"
cp live-games-app-icon-16x16.ico "$pre-$id-icon-16x16.ico"
cp live-games-app-icon-60x60.png "$pre-$id-icon-60x60.png"

rm -f "$pre-$id.pgn"
ln -s $pgn "$pre-$id.pgn"

echo "info: done $pre-$id.php with id=$id pgn=$pgn name=$name"

