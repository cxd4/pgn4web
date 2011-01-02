#!/bin/bash

#  pgn4web javascript chessboard
#  copyright (C) 2009, 2011 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

#  Shell script to set the number of boards and columns of ../live-multi-pointer.html

function print_help {
	echo
	echo "$(basename $0) boards columns"
	echo
	echo "Shell script to set the number of boards and columns of $live_multi_pointer_file"
	echo "boards must be an integer between 1 and 32"
	echo "columns must be an integer between 1 and 8"
	echo
	echo "Needs to be run using bash"
	echo
}

function print_error {
	echo
	echo "ERROR: missing or invalid command parameters"
	print_help
}

live_multi_pointer_file="../live-multi-pointer.html"

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

boards=$1
if [ -z "$boards" ]; then print_error; exit; fi 
if [ "$boards" -eq "$boards" 2> /dev/null ]; then echo -n; else print_error; exit; fi
if [ "$boards" -lt 1 ]; then print_error; exit; fi
if [ "$boards" -gt 32 ]; then print_error; exit; fi

columns=$2
if [ -z "$columns" ]; then
	columns="\"\""
else
	if [ "$columns" -eq "$columns" 2> /dev/null ]; then echo -n; else print_error; exit; fi
	if [ "$columns" -lt 1 ]; then print_error; exit; fi
	if [ "$columns" -gt 8 ]; then print_error; exit; fi
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

<script type="text/javascript">

// how many boards/columns to display on the live multi page
// boards must be set, columns can be blank for default
boards=$boards;
columns=$columns;

// dont edit below this point

newSearch = "?b=" + boards + "&c=" + columns;
if (window.location.search) { newSearch += window.location.search.replace(/^\?/, "&"); }
window.location.href = "live-multi.html" + newSearch + window.location.hash;

</script>
</head>
</html>
EOF
) > $live_multi_pointer_file

chmod 644 $live_multi_pointer_file

echo done setting $live_multi_pointer_file

