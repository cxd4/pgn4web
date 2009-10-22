#!/bin/sh

# bash script to grab a PGN file from a remote location for a
# pgn4web live broadcast

localPgnFile_default=live.pgn
refreshSeconds_default=29
timeoutHours_default=12

function print_help() {

  echo
  echo $(basename $0) remotePgnUrl localPgnFile refreshSeconds timeoutHours
  echo 
  echo Periodically fetches a remote PGN file for a pgn4web live broadcast.
  echo
  echo Parameters:
  echo - remotePgnUrl: URL to fetch
  echo - localPgnFile: local PGN filename \(default: $pgnFile_default\)
  echo - refreshSeconds: refresh rate in seconds \(default: $refreshSeconds_default\)
  echo - timeoutHours: timeout in hours for stopping the process \(default: $timeoutHours_default\)
  echo
}

if [ -z "$1" ]
then 
	print_help
	exit
else
	remotePgnUrl=$1
fi

if [ -z "$2" ]
then
	localPgnFile=$localPgnFile_default
else
	localPgnFile=$2
fi
if [ -e "$localPgnFile" ]
then
	echo $(basename $0) ERROR: localPgnFile $localPgnFile exists
	echo Delete the file or choose another filename and restart $(basename $0)
	exit
fi
if [ $(echo "$localPgnFile" | grep "\*") ] 
then
	echo $(basename $0) ERROR: localPgnFile should not contain \"*\"
	exit
fi
if [ $(echo "$localPgnFile" | grep "\?") ] 
then
	echo $(basename $0) ERROR: localPgnFile should not contain \"?\"
	exit
fi
if [ $(echo "$localPgnFile" | grep "\[") ] 
then
	echo $(basename $0) ERROR: localPgnFile should not contain \"[\"
	exit
fi
if [ $(echo "$localPgnFile" | grep "\]") ] 
then
	echo $(basename $0) ERROR: localPgnFile should not contain \"]\"
	exit
fi
tmpLocalPgnFile=$localPgnFile.$RANDOM.pgn

if [ -z "$3" ]
then
	refreshSeconds=$refreshSeconds_default
else
	refreshSeconds=$3
fi

if [ -z "$4" ]
then
	timeoutHours=$timeoutHours_default
else
	timeoutHours=$4
fi
timeoutSteps=$((3600*$timeoutHours/$refreshSeconds))

if [ -z "$(which curl)" ]
then
	if [ -z "$(which wget)" ]
	then
		echo $(basename $0) ERROR: missing both curl and wget \(execution aborted\)
	else
		grabCmdLine="wget -qrO $tmpLocalPgnFile $remotePgnUrl"
	fi
else 
	grabCmdLine="curl -so $tmpLocalPgnFile --url $remotePgnUrl"
fi

step=0
while [ $step -le $timeoutSteps ] 
do
	echo $(basename $0): step $step of $timeoutSteps \($remotePgnUrl, $localPgnFile, $refreshSeconds, $timeoutHours\)
	$grabCmdLine
	if [ -e "$tmpLocalPgnFile" ]
	then
		mv "$tmpLocalPgnFile" "$localPgnFile"
	fi
	let "step+=1"
	sleep $refreshSeconds
done

