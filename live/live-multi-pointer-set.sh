#!/bin/bash

#  pgn4web javascript chessboard
#  copyright (C) 2009, 2011 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

#  Shell script to set the number of boards and columns of live-multi-pointer.html

function print_help {
	echo
	echo "$(basename $0) boards columns search pgnfile"
	echo
	echo "Shell script to set the number of boards and columns of $live_multi_pointer_file"
	echo "- boards must be an integer between 1 and 32 (defaulting to 3 if both boards and search are unassigned)"
	echo "- columns must be an integer between 1 and 8"
	echo "- search is a comma separated list of game search items for each board (must not contain \"&\" and \"=\")"
	echo "- pgnfile is the local PGN filename (must not contain \"&\" and \"=\")"
        echo "To leave a parameter unassingned use \"\""
	echo
	echo "Needs to be run using bash"
	echo
}

function print_error {
	echo
	echo "ERROR: missing or invalid command parameters"
	print_help
}

live_multi_pointer_file="live-multi-pointer.html"

if [ "$1" == "--help" ]; then print_help; exit; fi

if [ "$1" == "--no-shell-check" ]
then
        shift 1
else
        if [ "$(basename $SHELL)" != "bash" ]
        then
                echo "ERROR: $(basename $0) should be run with bash. Prepend --no-shell-check as first parameters to skip checking the shell type."
                exit
        fi
fi

boards="$1"
if [ -n "$boards" ]; then
	if [ "$boards" -eq "$boards" 2> /dev/null ]; then echo -n; else print_error; exit; fi
	if [ "$boards" -lt 1 ]; then print_error; exit; fi
	if [ "$boards" -gt 32 ]; then print_error; exit; fi
fi

columns="$2"
if [ -n "$columns" ]; then
	if [ "$columns" -eq "$columns" 2> /dev/null ]; then echo -n; else print_error; exit; fi
	if [ "$columns" -lt 1 ]; then print_error; exit; fi
	if [ "$columns" -gt 8 ]; then print_error; exit; fi
fi

search="$3"
if [ $(echo "$search" | grep "&") ]; then print_error; exit; fi
if [ $(echo "$search" | grep "=") ]; then print_error; exit; fi

pgnfile="$4"
if [ $(echo "$pgnfile" | grep "&") ]; then print_error; exit; fi
if [ $(echo "$pgnfile" | grep "=") ]; then print_error; exit; fi

if [ -z "$search" ] && [ -z "$boards" ]; then
	boards=3
	echo "WARNING: both boards and search are unassigned, defaulting boards to 3"
fi

(
cat << EOF 
<html> 

<!--
  pgn4web javascript chessboard
  copyright (C) 2009, 2011 Paolo Casaschi
  see README file and http://pgn4web.casaschi.net
  for credits, license and more details
-->

<head>

<meta http-equiv="cache-control" content="no-cache">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">

<script type="text/javascript">

// how many boards/columns to display on the live multi page
// boards must be set, columns can be blank for default
boards="$boards";
columns="$columns";
search="$search";
pgnfile="$pgnfile";

// dont edit below this point

locSearch = window.location.search.replace(/\b(nocache|n|boards|b|columns|c|search|s|pgnfile|p|pgndata|pf|pd)=[^&]*/gi, "");
locSearch = locSearch.replace(/&+/gi, "&");
locSearch = locSearch.replace(/&$/gi, "");
locSearch = locSearch.replace(/^\?&+/gi, "?");
locSearch = locSearch.replace(/^\?$/gi, "");
if (boards) { locSearch = (locSearch ? locSearch + "&" : "?") + "b=" + boards; }
if (columns) { locSearch = (locSearch ? locSearch + "&" : "?") + "c=" + columns; }
if (search) { locSearch = (locSearch ? locSearch + "&" : "?") + "s=" + search; }
if (pgnfile) { locSearch = (locSearch ? locSearch + "&" : "?") + "pd=" + pgnfile; }
window.location.href = "../live-multi.html" + locSearch + window.location.hash;

</script>
</head>
</html>
EOF
) > $live_multi_pointer_file

chmod 644 $live_multi_pointer_file

echo "INFO: done setting $live_multi_pointer_file"

