#!/bin/bash

#  pgn4web javascript chessboard
#  copyright (C) 2009 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

# bash script to check status of live-grab.sh

if [ -z "$1" ] 
then
	echo
	echo "$(basename $0) [ logFile | --guess ]"
	echo 
	echo "Checks live-gram.sh logfile"
	echo
	echo "Parameters:"
	echo "  logFile: full filename and path of the logfile created by live-grab.sh"
	echo "  --guess: if specified, $(basename $0) will try to guess logFile"
	echo
	exit
fi

if [ -z "$(which awk)" ]
then
	echo "ERROR: missing awk"
fi

if [ "$1" == "--guess" ]
then
	pgn4web_log=$(ps -wo pid,command | awk '$3=="live-grab.sh" {print $8; exit}')
	pgn4web_dir=$(dirname $0)
	if [ -n "$pgn4web_dir" ]
	then
		pgn4web_log=$pgn4web_dir"/"$pgn4web_log
	fi
else
	pgn4web_log=$1
fi

pgn4web_pid=$(ps -wo pid,command | awk '$3=="live-grab.sh" {print $1; exit}')

if [ -f "$pgn4web_log" ]
then
	if [ -n "$pgn4web_pid" ]
	then
		pgn4web_steps=$(cat $pgn4web_log | awk 'END { print "step:" $11 "/" $13 }')
		echo "pgn4web live-grab running; pid:$pgn4web_pid; $pgn4web_steps"
	else
		echo "pgn4web live-grab not running anymore"
	fi
else
	if [ -n "$pgn4web_pid" ]
	then
		if [ "$1" == "--guess" ] 
		then
			echo "pgn4web live-grab running; pid:$pgn4web_pid; failed to guess logFile"
		else
			echo "pgn4web live-grab running; pid:$pgn4web_pid; logFile $pgn4web_log not found"
		fi
	else
		echo "pgn4web live-grab not found"
	fi
fi

