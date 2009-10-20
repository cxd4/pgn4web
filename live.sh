#!/bin/bash

# bash script to create a pgn file over time, same as a live broadcast
# more realistic than simulating the live broadcast within pgn4web

pgn_file=live.pgn
pgn_file_tmp=live_tmp.pgn
delay=3

# dont touch after this line

game1_header="[Event \"Tilburg Fontys\"]\n[Site \"Tilburg\"]\n[Date \"1998.10.24\"]\n[Round \"2\"]\n[White \"Anand, Viswanathan\"]\n[Black \"Kramnik, Vladimir\"]"
game1_header_live="$game1_header\n[Result \"*\"]\n"
game1_header_end="$game1_header\n[Result \"1-0\"]\n"

game1_moves[0]="1.e4 e5"
game1_moves[1]="2.Nf3 Nf6 3.Nxe5"
game1_moves[2]="d6"
game1_moves[3]="4.Nf3 Nxe4"
game1_moves[4]="5.d4 d5 6.Bd3"
game1_moves[5]="Nc6 7.O-O"
game1_moves[6]="Be7 8.Re1"
game1_moves[7]="Bg4 9.c3 f5"
game1_moves[8]=""
game1_moves[9]="10.Qb3 O-O 11.Nbd2"
game1_moves[11]="Na5"
game1_moves[12]="12.Qa4 Nc6 13.Bb5"
game1_moves[13]="Nxd2 14.Nxd2 Qd6"
game1_moves[14]="15.h3 Bh5"
game1_moves[15]=""
game1_moves[16]="16.Nb3"
game1_moves[17]="Bh4 17.Nc5 Bxf2+"
game1_moves[18]="18.Kxf2"
game1_moves[19]="Qh2 19.Bxc6 bxc6"
game1_moves[20]="20.Qxc6 f4"
game1_moves[21]="21.Qxd5+"
game1_moves[22]="Kh8 22.Qxh5"
game1_moves[23]="f3"
game1_moves[24]="23.Qxf3 Rxf3+"
game1_moves[25]="24.Kxf3 Rf8+ 25.Ke2"
game1_moves[26]="Qxg2+ 26.Kd3"
game1_moves[27]="Qxh3+ 27.Kc2 Qg2+"
game1_moves[28]="28.Bd2 Qg6+"
game1_moves[29]="29.Re4 h5 30.Re1"
game1_moves[30]="Re8 31.Kc1 Rxe4"
game1_moves[31]="32.Nxe4 h4 33.Ng5"
game1_moves[32]="Qh5 34.Re3 Kg8"
game1_moves[33]="35.c4"

game2_header="[Event \"Tilburg Fontys\"]\n[Site \"Tilburg\"]\n[Date \"1998.10.24\"]\n[Round \"2\"]\n[White \"Lautier, Joel\"]\n[Black \"Van Wely, Loek\"]"
game2_header_live="$game2_header\n[Result \"*\"]\n"
game2_header_end="$game2_header\n[Result \"1/2-1/2\"]\n"

game2_moves[0]="1.d4 Nf6 2.c4"
game2_moves[1]="c5 3.d5"
game2_moves[2]="b5"
game2_moves[3]="4.Nf3"
game2_moves[4]="Bb7"
game2_moves[5]="5.a4"
game2_moves[6]="Qa5+"
game2_moves[7]="6.Bd2"
game2_moves[8]="b4"
game2_moves[9]="7.Bg5 d6"
game2_moves[10]=""
game2_moves[11]="8.Nbd2"
game2_moves[12]="Nbd7"
game2_moves[13]="9.h3 g6"
game2_moves[14]="10.e4 Bg7 11.Bd3"
game2_moves[15]="O-O 12.O-O"
game2_moves[16]="Rae8"
game2_moves[17]=""
game2_moves[18]="13.Re1 e5"
game2_moves[19]="14.Nf1"
game2_moves[20]="Nh5 15.g3"
game2_moves[21]="Bc8"
game2_moves[22]=""
game2_moves[23]="16.Kh2 Kh8"
game2_moves[24]="17.b3"
game2_moves[25]="Qc7"
game2_moves[26]=""
game2_moves[27]="18.Ra2"
game2_moves[28]="Ndf6"
game2_moves[29]="19.Ng1"
game2_moves[30]=""
game2_moves[31]="Ng8"
game2_moves[32]=""
game2_moves[33]="20.Bc1"

steps=33

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
		let "move+=1"
	done

	echo >> $pgn_file_tmp

	echo -e $game2_header_live >> $pgn_file_tmp
	move=0
	while [ $move -le $upto ]
	do
		echo ${game2_moves[$move]} >> $pgn_file_tmp
		let "move+=1"
	done

	mv $pgn_file_tmp $pgn_file
	sleep $delay

	let "upto+=1"
done

echo > $pgn_file_tmp
echo -e $game1_header_end >> $pgn_file_tmp
move=0
while [ $move -le $upto ]
do
	echo ${game1_moves[$move]} >> $pgn_file_tmp
	let "move+=1"
done
echo >> $pgn_file_tmp
echo -e $game2_header_end >> $pgn_file_tmp
move=0
while [ $move -le $upto ]
do
	echo ${game2_moves[$move]} >> $pgn_file_tmp
	let "move+=1"
done
mv $pgn_file_tmp $pgn_file
echo done

