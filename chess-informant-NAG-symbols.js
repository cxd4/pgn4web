/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009, 2012 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

document.write('<style type="text/css">.NAGs, .NAGl { color:red; }</style>');

var basicNAGs = /^([\?!+#\s]|<span class="NAGs">[^<]*<\/span>)*(\s|$)/;

Ns = '<span class="NAGs">';
Nl = '<span class="NAGl">';
Ne = '</span>';

NAG[0] = '';
NAG[1] = '!';  // 'good move';
NAG[2] = '?';  // 'bad move';
NAG[3] = '!!'; // 'very good move';
NAG[4] = '??'; // 'very bad move';
NAG[5] = '!?'; // 'speculative move';
NAG[6] = '?!'; // 'questionable move';
NAG[7] = NAG[8] = Ns + '[]' + Ne; // 'forced move';
NAG[9] = '??'; // 'worst move';
NAG[10] = NAG[11] = NAG[12] = Ns + '=' + Ne; // 'drawish position';
NAG[13] = Ns + '~~' + Ne; // 'unclear position';
NAG[14] = Ns + '=/+' + Ne; // 'White has a slight advantage';
NAG[15] = Ns + '+/=' + Ne; // 'Black has a slight advantage';
NAG[16] = Ns + '-/+' + Ne; // 'White has a moderate advantage';
NAG[17] = Ns + '+/-' + Ne; // 'Black has a moderate advantage';
NAG[18] = NAG[20] = Ns + '+-' + Ne; // 'White has a decisive advantage';
NAG[19] = NAG[21] = Ns + '-+' + Ne; // 'Black has a decisive advantage';
NAG[22] = NAG[23] = Ns + '(.)' + Ne; // 'zugzwang';
NAG[24] = NAG[25] = NAG[26] = NAG[27] = NAG[28] = NAG[29] = Ns + '()' + Ne; // 'space advantage';
NAG[30] = NAG[31] = NAG[32] = NAG[33] = NAG[34] = NAG[35] = Ns + '@' + Ne; // 'time (development) advantage';
NAG[36] = NAG[37] = NAG[38] = NAG[39] = Ns + '|^' + Ne; // 'initiative';
NAG[40] = NAG[41] = Ns + '->' + Ne; // 'attack';
NAG[42] = NAG[43] = ''; // 'insufficient compensation for material deficit';
NAG[44] = NAG[45] = NAG[46] = NAG[47] = Ns + '~/=' + Ne; // 'sufficient compensation for material deficit';
NAG[48] = NAG[49] = NAG[50] = NAG[51] = NAG[52] = NAG[53] = Ns + '[+]' + Ne; // 'center control advantage';
for (ii = 54; ii <= 129; ii++) { NAG[ii] = 'ii'; }
NAG[130] = NAG[131] = NAG[132] = NAG[133] = NAG[134] = NAG[135] = Ns + '<=>' + Ne; // 'counterplay';
NAG[136] = NAG[137] = NAG[138] = NAG[139] = Ns + '(+)' + Ne; // 'time control pressure';

NAG[140] = Nl + '^' + Ne; // 'with the idea';
NAG[141] = ''; // 'aimed against';
NAG[142] = // 'better is';
NAG[143] = ''; // 'worse is';
NAG[144] = ''; // 'equivalent is';
NAG[145] = 'RR'; // 'editorial comment';
NAG[146] = 'N'; // 'novelty';
for (ii = 147; ii <= 237; ii++) { NAG[ii] = 'ii'; }
NAG[238] = Nl + '()' + Ne; // 'space advantage';
NAG[239] = Nl + '<->' + Ne; // 'file';
NAG[240] = Nl + '/' + Ne; // 'diagonal';
NAG[241] = Nl + '[+]' + Ne; // 'center';
NAG[242] = Nl + '>>' + Ne; // 'kingside';
NAG[243] = Nl + '<<' + Ne; // 'queenside';
NAG[244] = Nl + 'x' + Ne; // 'weak point';
NAG[245] = Nl + '_|_' + Ne; // 'ending';
NAG[246] = Nl + 'bp' + Ne; // 'bishop pair';
NAG[247] = Nl + 'bB' + Ne; // 'opposite bishops';
NAG[248] = Nl + 'bb' + Ne; // 'same bishops';
NAG[249] = Nl + 'oo' + Ne; // 'connected pawns';
NAG[250] = Nl + 'o..o' + Ne; // 'isolated pawns';
NAG[251] = Nl + '8' + Ne; // 'doubled pawns';
NAG[252] = Nl + 'o>' + Ne; // 'passed pawn';
NAG[253] = Nl + 'o+' + Ne; // 'pawn majority';
NAG[254] = Nl + '|_' + Ne; // 'with';
NAG[255] = Nl + '_|' + Ne; // 'without';

