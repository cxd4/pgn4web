#!/bin/bash

#  pgn4web javascript chessboard
#  copyright (C) 2009 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

# bash script to grab a PGN file from a remote location for a
# pgn4web live broadcast

localPgnFile_default=live.pgn
refreshSeconds_default=49
timeoutHours_default=12
logFile_default=/dev/stdout

print_help() {

  echo
  echo $(basename $0) remotePgnUrl localPgnFile refreshSeconds timeoutHours logFile
  echo 
  echo Periodically fetches a remote PGN file for a pgn4web live broadcast.
  echo
  echo Parameters:
  echo - remotePgnUrl: URL to fetch
  echo - localPgnFile: local PGN filename \(default: $localPgnFile_default\)
  echo - refreshSeconds: refresh rate in seconds \(default: $refreshSeconds_default\)
  echo - timeoutHours: timeout in hours for stopping the process \(default: $timeoutHours_default\)
  echo - logFile: log file name \(default /dev/stdout\)
  echo
}

if [ -z "$5" ]
then
	logFile=$logFile_default
else
	logFile=$5
fi
echo "pgn4web $(basename $0) logfile" > $logFile

if [ -z "$(which tee)" ]
then
	if [ "$logFile" != "$logFile_default" ]
	then
		echo $(basename $0) ERROR: missing utility tee \(execution aborted\) > $logFile
	fi
	echo $(basename $0) ERROR: missing utility tee \(execution aborted\) > /dev/stderr
	exit
fi

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

if [ -z "$2" ]
then
	localPgnFile=$localPgnFile_default
else
	localPgnFile=$2
fi
if [ -e "$localPgnFile" ]
then
	echo $(basename $0) ERROR: localPgnFile $localPgnFile exists | tee -a $logFile 
	echo Delete the file or choose another filename and restart $(basename $0) | tee -a $logFile 
	exit
fi
if [ $(echo "$localPgnFile" | grep "\*") ] 
then
	echo $(basename $0) ERROR: localPgnFile should not contain \"*\" | tee -a $logFile
	exit
fi
if [ $(echo "$localPgnFile" | grep "\?") ] 
then
	echo $(basename $0) ERROR: localPgnFile should not contain \"?\" | tee -a $logFile
	exit
fi
if [ $(echo "$localPgnFile" | grep "\[") ] 
then
	echo $(basename $0) ERROR: localPgnFile should not contain \"[\" | tee -a $logFile
	exit
fi
if [ $(echo "$localPgnFile" | grep "\]") ] 
then
	echo $(basename $0) ERROR: localPgnFile should not contain \"]\" | tee -a $logFile
	exit
fi
tmpLocalPgnFile=$localPgnFile.$RANDOM.pgn

if [ -z "$1" ]
then 
	print_help
	exit
else
	remotePgnUrl=$1
fi

if [ -z "$(which curl)" ]
then
	if [ -z "$(which wget)" ]
	then
		echo $(basename $0) ERROR: missing both curl and wget \(execution aborted\) | tee -a $logFile
	else
		grabCmdLine="wget -qrO $tmpLocalPgnFile $remotePgnUrl"
	fi
else 
	grabCmdLine="curl -so $tmpLocalPgnFile --url $remotePgnUrl"
fi

step=0
while [ $step -le $timeoutSteps ] 
do
	echo $(date) $(basename $0): step $step of $timeoutSteps \($remotePgnUrl, $localPgnFile, $refreshSeconds, $timeoutHours\) >> $logFile
	$grabCmdLine 
	if [ -e "$tmpLocalPgnFile" ]
	then
		mv "$tmpLocalPgnFile" "$localPgnFile" >> $logFile
	fi
	step=$(($step +1))
	sleep $refreshSeconds
done

echo $(date) $(basename $0): done >> $logFile

