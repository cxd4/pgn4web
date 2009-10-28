#!/bin/bash

#  pgn4web javascript chessboard
#  copyright (C) 2009 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

# bash script to check status of live-grab.sh

pgn4web_pid=$(ps -wo pid,command | awk '$3=="live-grab.sh" {print $1}')
pgn4web_log=$(ps -wo pid,command | awk '$3=="live-grab.sh" {print $8}')
if [ -n "$pgn4web_log" ]
then
	if [ -e "$pgn4web_log" ]
	then
		pgn4web_steps=$(cat $pgn4web_log | awk 'END { print "step:" $11 "/" $13 }')
	fi
	if [ -n "$pgn4web_pid" ]
	then
		 echo "pgn4web live-grab running; pid:$pgn4web_pid $pgn4web_steps"
	fi
fi
