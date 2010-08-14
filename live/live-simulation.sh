#!/bin/bash

#  pgn4web javascript chessboard
#  copyright (C) 2009, 2010 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

# bash script to create a pgn file over time, same as a live broadcast
# more realistic than simulating the live broadcast within pgn4web

if [ "$1" == "--help" ]
then
	echo
	echo "$(basename $0)"
	echo
	echo "Shell script to create a pgn file over time, same as a live broadcast"
	echo "and more realistic than simulating the live broadcast within pgn4web"
	echo
	echo "Needs to be run using bash"
	echo
	exit
fi

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

pgn_file=live.pgn
pgn_file_tmp=live-tmp.pgn
delay=17

# dont touch after this line

game1_header="[Event \"Tilburg Fontys\"]\n[Site \"Tilburg\"]\n[Date \"1998.10.24\"]\n[Round \"2\"]\n[White \"Anand, Viswanathan\"]\n[Black \"Kramnik, Vladimir\"]\n[WhiteClock \"2:00:00\"]\n[BlackClock \"2:00:00\"]"
game1_header_live="$game1_header\n[Result \"*\"]\n"
game1_header_end="$game1_header\n[Result \"1-0\"]\n"

game1_moves[0]="1.e4 {1:59:59}"
game1_moves[1]="e5 {1:58:58}"
game1_moves[2]="2.Nf3 {1:57:57}"
game1_moves[3]="Nf6 {1:56:56}"
game1_moves[4]="3.Nxe5 {1:55:55}"
game1_moves[5]=""
game1_moves[6]="d6 {1:54:54}"
game1_moves[7]="4.Nf3 {1:53:53}"
game1_moves[8]="Nxe4 {1:52:52}"
game1_moves[9]="5.d4 {1:51:51}"
game1_moves[10]="d5 {1:50:50}"
game1_moves[11]=""
game1_moves[12]="6.Bd3 {1:49:49}"
game1_moves[13]="Nc6 {1:48:48}"
game1_moves[14]="7.O-O {1:47:47} Be7 {1:46:46}"
game1_moves[15]="8.Re1 {1:45:45}"
game1_moves[16]="Bg4 {1:44:44}" 
game1_moves[17]="9.c3 {1:43:43} f5 {1:42:42}"
game1_moves[18]=""
game1_moves[19]="10.Qb3 {1:41:41} O-O {1:40:40}"
game1_moves[20]="11.Nbd2 {1:39:39}"
game1_moves[21]="Na5 {1:38:38}"
game1_moves[22]="12.Qa4 {1:37:37} Nc6 {1:36:36}"
game1_moves[23]="13.Bb5 {1:35:35}"
game1_moves[24]=""
game1_moves[25]="Nxd2 {1:34:34} 14.Nxd2 {1:33:33}"
game1_moves[26]="Qd6 {1:32:32}"
game1_moves[27]="15.h3 {1:31:31} Bh5 {1:30:30}"
game1_moves[28]=""
game1_moves[29]="16.Nb3 {1:29:29} Bh4 {1:28:28}"
game1_moves[30]="17.Nc5 {1:27:27}"
game1_moves[31]="Bxf2+ {1:26:26}"
game1_moves[32]="18.Kxf2 {1:25:25} Qh2 {1:24:24}"
game1_moves[33]="19.Bxc6 {1:23:23}"
game1_moves[34]="bxc6 {1:22:22} 20.Qxc6 {1:21:21}" 
game1_moves[35]="f4 {1:20:20}"
game1_moves[36]="21.Qxd5+ {1:19:19}"
game1_moves[37]=""
game1_moves[38]="Kh8 {1:18:18} 22.Qxh5 {1:17:17}"
game1_moves[39]="f3 {1:16:16}"
game1_moves[40]="23.Qxf3 {1:15:15} Rxf3+ {1:14:14}"
game1_moves[41]="24.Kxf3 {1:13:13}"
game1_moves[42]="Rf8+ {1:12:12}"
game1_moves[43]="25.Ke2 {1:11:11} Qxg2+ {1:10:10}"
game1_moves[44]="26.Kd3 {1:09:09}"
game1_moves[45]="Qxh3+ {1:08:08} 27.Kc2 {1:07:07}"
game1_moves[46]="Qg2+ {1:06:06}"
game1_moves[47]=""
game1_moves[48]="28.Bd2 {1:05:05} Qg6+ {1:04:04}"
game1_moves[49]="29.Re4 {1:03:03} h5 {1:02:02}"
game1_moves[50]=""
game1_moves[51]="30.Re1 {1:01:01}"
game1_moves[52]="Re8 {1:00:00}"
game1_moves[53]="31.Kc1 {59:59} Rxe4 {58:58}"
game1_moves[54]="Nxe4 {57:57}"
game1_moves[55]="h4 {56:56}" 
game1_moves[56]="33.Ng5 {55:55}"
game1_moves[57]=""
game1_moves[58]="Qh5 {54:54}"
game1_moves[59]="34.Re3 {53:53}"
game1_moves[60]="Kg8 {52:52}"
game1_moves[61]="35.c4 {51:51}"
game1_moves[62]=""
game1_moves[63]=""
game1_moves[64]=""
game1_moves[65]=""
game1_moves[66]=""

game2_header="[Event \"London Chess Classic\"]\n[Site \"London\"]\n[Date \"2009.12.13\"]\n[Round \"5\"]\n[White \"Howell, David\"]\n[Black \"Kramnik, Vladimir\"]\n[WhiteClock \"2:00:00\"]\n[BlackClock \"2:00:00\"]"
game2_header_live="$game2_header\n[Result \"*\"]\n"
game2_header_end="$game2_header\n[Result \"1/2-1/2\"]\n"

game2_moves[0]="1.e4 {[%clk 1:59:59]} e5 {[%clk 1:59:58]} 2.Nf3 {[%clk 1:58:57]}"
game2_moves[1]="Nf6 {[%clk 1:58:56]} 3.Nxe5 {[%clk 1:57:55]}"
game2_moves[2]="d6 {[%clk 1:57:54]}"
game2_moves[3]="4.Nf3 {[%clk 1:56:53]}"
game2_moves[4]="Nxe4 {[%clk 1:56:52]}"
game2_moves[5]="5.d4 {[%clk 1:55:51]}"
game2_moves[6]="d5 {[%clk 1:55:50]} 6.Bd3 {[%clk 1:54:49]}"
game2_moves[7]="Nc6 {[%clk 1:54:48]}"
game2_moves[8]="7.O-O {[%clk 1:53:47]} Be7 {[%clk 1:53:46]}"
game2_moves[9]=""
game2_moves[10]="8.Re1 {[%clk 1:52:45]}"
game2_moves[11]="Bg4 {[%clk 1:52:44]}"
game2_moves[12]="9.c3 {[%clk 1:51:43]} f5 {[%clk 1:51:42]}"
game2_moves[13]="10.Qb3 {[%clk 1:50:41]} O-O {[%clk 1:50:40]} 11.Nbd2 {[%clk 1:49:39]}"
game2_moves[14]="Na5 {[%clk 1:49:38]} 12.Qa4 {[%clk 1:48:37]}"
game2_moves[15]="Nc6 {[%clk 1:48:36]}"
game2_moves[16]=""
game2_moves[17]="13.Qb3 {[%clk 1:47:35]} Na5 {[%clk 1:47:34]}"
game2_moves[18]="14.Qc2 {[%clk 1:46:33]}"
game2_moves[19]="Nc6 {[%clk 1:46:32]} 15.b4 {[%clk 1:45:31]}"
game2_moves[20]="a6 {[%clk 1:45:30]}"
game2_moves[21]=""
game2_moves[22]="16.Rb1 {[%clk 1:44:29]} b5 {[%clk 1:44:28]}"
game2_moves[23]="17.a4 {[%clk 1:43:27]} Rb8 {[%clk 1:43:26]} 18.axb5 {[%clk 1:42:25]}"
game2_moves[24]="axb5 {[%clk 1:42:24]} 19. Ne5 {[%clk 1:41:23]}"
game2_moves[25]="Nxe5 {[%clk 1:41:22]}"
game2_moves[26]="20.dxe5 {[%clk 1:40:21]} Nxf2 {[%clk 1:40:20]} 21.Kxf2 {[%clk 1:39:19]}"
game2_moves[27]="Bh4+ {[%clk 1:39:18]} 22.Kf1 {[%clk 1:38:17]}"
game2_moves[28]="Bxe1 {[%clk 1:38:16]}"
game2_moves[29]="23.Kxe1 {[%clk 1:37:15]}"
game2_moves[30]=""
game2_moves[31]="Qh4+  {[%clk 1:37:14]} 24.g3 {[%clk 1:36:13]}"
game2_moves[32]="Qxh2  {[%clk 1:36:12]}"
game2_moves[33]="25.Nf1 {[%clk 1:35:11]}"
game2_moves[34]="Qxc2 {[%clk 1:35:10]}"
game2_moves[35]="26.Bxc2 {[%clk 1:34:09]} Rbe8 {[%clk 1:34:08]} 27.Bd3 {[%clk 1:33:07]}"
game2_moves[36]="Rxe5+ {[%clk 1:33:06]} 28.Kf2 {[%clk 1:32:05]} f4 {[%clk 1:32:04]}"
game2_moves[37]="29.gxf4 {[%clk 1:31:03]} Bf5 {[%clk 1:31:02]} 30.Bxf5 {[%clk 1:30:01]}"
game2_moves[38]="Rexf5 {[%clk 1:30:00]}"
game2_moves[39]=""
game2_moves[40]="31.Ng3 {[%clk 1:29:59]}"
game2_moves[42]="R5f6 {[%clk 1:29:58]} 32.Kf3 {[%clk 1:28:57]}"
game2_moves[43]="Rc6 {[%clk 1:28:56]}"
game2_moves[44]="33.Bd2 {[%clk 1:27:55]}"
game2_moves[45]="g5 {[%clk 1:27:54]}"
game2_moves[46]="34.Ne2 {[%clk 1:26:53]} gxf4 {[%clk 1:26:52]} 35.Nd4 {[%clk 1:25:51]}"
game2_moves[47]=""
game2_moves[48]="Rg6 {[%clk 1:25:50]} 36.Nxb5 {[%clk 1:24:49]} Rg3+ {[%clk 1:24:48]}"
game2_moves[49]="37.Kf2 {[%clk 1:23:47]}"
game2_moves[50]="Rd3 {[%clk 1:23:46]}"
game2_moves[51]="38.Rg1+ {[%clk 1:22:45]} Kh8 {[%clk 1:22:44]} 39.Ke2 {[%clk 1:21:43]}"
game2_moves[52]="Rg3 {[%clk 1:21:42]} 40.Kf2 {[%clk 1:20:41]}"
game2_moves[53]=""
game2_moves[54]="Rxg1 {[%clk 1:20:40]} 41.Kxg1 {[%clk 1:19:39]} c5 {[%clk 1:19:38]}"
game2_moves[55]="42.Nd6 {[%clk 1:18:38]} cxb4 {[%clk 1:18:37]}"
game2_moves[56]="43.cxb4 {[%clk 1:17:36]} Kg7 {[%clk 1:17:35]} 44.Bc3+ {[%clk 1:16:34]}"
game2_moves[57]="Kg6 {[%clk 1:16:33]} 45.b5 {[%clk 1:15:32]} Rd8 {[%clk 1:15:31]}"
game2_moves[58]="46.Be5 {[%clk 1:14:30]} Rb8 {[%clk 1:14:29]} 47.Bd4 {[%clk 1:13:28]}"
game2_moves[59]="Rd8 {[%clk 1:13:27]} 48.Be5 {[%clk 1:12:26]} Rb8 {[%clk 1:12:25]}"
game2_moves[60]="49.Kf2 {[%clk 1:11:24]}"
game2_moves[61]="Rb6 {[%clk 1:11:23]}"
game2_moves[63]="50.Kf3 {[%clk 1:10:22]} Kg5 {[%clk 1:10:21]} 51.Nf7+ {[%clk 1:09:20]}"
game2_moves[64]="Kg6 {[%clk 1:09:19]} 52.Nd6 {[%clk 1:08:18]}"
game2_moves[65]="Kg5 {[%clk 1:08:17]} 53.Nf7+ {[%clk 1:07:16]}"
game2_moves[66]="Kg6 {[%clk 1:07:15]}"

steps=66

if [ -e "$pgn_file" ]
then
	echo "ERROR: $(basename $0): $pgn_file exists"
        echo "Delete the file or choose another filename and restart $(basename $0)"
        exit
fi

echo Generating PGN file $pgn_file simulating live game broadcast

echo > $pgn_file_tmp
echo -e $game1_header_live >> $pgn_file_tmp
echo >> $pgn_file_tmp
echo -e $game2_header_live >> $pgn_file_tmp
mv $pgn_file_tmp $pgn_file
sleep $delay

upto=0;
while [ $upto -le $steps ]
do
	echo " step $upto of $steps"
	echo > $pgn_file_tmp

	echo -e $game1_header_live >> $pgn_file_tmp
	move=0
	while [ $move -le $upto ]
	do
		echo ${game1_moves[$move]} >> $pgn_file_tmp
		move=$(($move + 1))
	done

	echo >> $pgn_file_tmp

	echo -e $game2_header_live >> $pgn_file_tmp
	move=0
	while [ $move -le $upto ]
	do
		echo ${game2_moves[$move]} >> $pgn_file_tmp
		move=$(($move + 1))
	done

	mv $pgn_file_tmp $pgn_file
	sleep $delay

	upto=$(($upto + 1))
done

echo > $pgn_file_tmp
echo -e $game1_header_end >> $pgn_file_tmp
move=0
while [ $move -le $upto ]
do
	echo ${game1_moves[$move]} >> $pgn_file_tmp
	move=$(($move + 1))
done
echo >> $pgn_file_tmp
echo -e $game2_header_end >> $pgn_file_tmp
move=0
while [ $move -le $upto ]
do
	echo ${game2_moves[$move]} >> $pgn_file_tmp
	move=$(($move + 1))
done
mv $pgn_file_tmp $pgn_file
echo done with games... waiting for a while before deleting $pgn_file

sleep 3600
rm $pgn_file


