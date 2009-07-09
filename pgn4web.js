/*
 *   HOW TO USE pgn4web.js
 *
 *   add a SCRIPT instance at the top of your HTML file:
 *
 *      <script src="pgn4web.js" type="text/javascript"></script>
 *
 *   then add another SCRIPT instance with at least the call to SetPgnUrl("http://yoursite/yourpath/yourfile.pgn")
 *   and optionally any of the other calls listed below.  
 *   Example:
 *
 *      <script yype="text/javascript>
 *        SetPgnUrl("http://yoursite/yourpath/yourfile.pgn");
 *        SetImagePath (""); // use "" path if images are in the same folder as this javascript file
 *        SetImageType("png");
 *        SetHighlightOption(true) // true or false
 *        SetGameSelectorString("Select a game...");
 *        SetCommentsIntoMoveText(false);
 *        SetCommentsOnSeparateLines(false);
 *        SetAutoplayDelay(1000); // milliseconds
 *        SetAutostartAutoplay(false);
 *        SetInitialGame(1); // number of game to be shown at load, from 1
 *        SetInitialHalfmove(0); // halfmove number to be shown at load, 0 for start position
 *      </script>
 * 
 *   Then the script will automagically add content into your HTML file 
 *   to any <div> or <span> containers with the following IDs:
 *
 *      <div id="GameSelector"></div>
 *      <div id="GameLastMove"></div>
 *      <div id="GameNextMove"></div>
 *      <div id="GameSideToMove"></div>
 *      <div id="GameLastComment"></div>
 *      <div id="GameBoard"></div>
 *      <div id="GameButtons"></div>
 *      <div id="GameEvent"></div>
 *      <div id="GameSite"></div>
 *      <div id="GameDate"></div>
 *      <div id="GameWhite"></div>
 *      <div id="GameBlack"></div>
 *      <div id="GameResult"></div>
 *      <div id="GameText"></div>
 *
 *   The file pgn4web.css shows a list of customization style options.
 *
 *   See pgn4web.html file for an example.
 */

SetPgnUrl("");
// SetImagePath (""); // use "" path if images are in the same folder as this javascript file
// SetImageType("png");
// SetHighlightOption(true); // true or false
// SetGameSelectorString("Select a game...");
// SetCommentsIntoMoveText(true);
// SetCommentsOnSeparateLines(true);
// SetAutoplayDelay(1000); // milliseconds
// SetAutostartAutoplay(false);
// SetInitialGame(0); // number of game to be shown at load
// SetInitialHalfmove(0); // halfmove number to be shown at load


/*********************************************************************/

/* 
 * DONT CHANGE AFTER HERE 
 */

var version = '1.12+'
var about = '\tpgn4web v' + version + '\n\thttp://pgn4web.casaschi.net\n';
var help = '\th\tgame start' + '\n' +
           '\tj\tmove backward' + '\n' +
           '\tk\tmove forward' + '\n' +
           '\tl\tgame end' + '\n' +
           '\tu\tfind previous comment' + '\n' +
           '\ti\tfind next comment' + '\n' +
           '\n' +
           '\tv\tfirst game' + '\n' +
           '\tb\tprevious game' + '\n' +
           '\tn\tnext game' + '\n' +
           '\tm\tlast game' + '\n' +
           '\n' +
           '\ta\tstart autoplay' + '\n' +
           '\ts\tstop autoplay' + '\n' +
           '\t1\tautoplay 1s' + '\n' +
           '\t3\tautoplay 3s' + '\n' +
           '\t9\tautoplay 3s' + '\n' +
           '\n' +
           '\tf\tflip board' + '\n' +
           '\td\twhite on bottom' + '\n' +
           '\tg\ttoggle highlighting' + '\n' +
           '\tp\ttoggle showing comments' + '\n' +
           '\to\ttoggle showing comments on separate lines' + '\n' +
           '';

var credits = 'javascript modifications of Paolo Casaschi (pgn4web@casaschi.net) ' +
              'on code from the http://ficsgames.com database, ' +
              'in turn likely based on code from the LT PGN viewer at ' +
              'http://www.lutanho.net/pgn/pgnviewer.html' + '\n' + '\n' +
              'PNG images from http://ixian.com/chess/jin-piece-sets ' +
              '(creative commons attribution-share alike 3.0 unported license)' + '\n' +
              '';

function displayHelp(){
  text = about + '\nHELP\n\n' + help + '\nCREDITS\n\n' + credits + '\n';
  alert(text);
}

window.onload = createBoardFromPgnUrl;

document.onkeydown = handlekey;

function handlekey(e) { 
  var keycode;

  if (!e) e = window.event;
  keycode = e.keyCode
  
//  alert(keycode);

  switch(keycode)
  {
    case  8:  // backspace
    case  9:  // tab
    case 16:  // shift
    case 17:  // ctrl
    case 18:  // alt
    case 32:  // space
    case 33:  // page up
    case 34:  // page down
    case 35:  // end
    case 36:  // home
    case 45:  // insert
    case 46:  // delete
    case 92:  // super
    case 93:  // menu
      break;

    case 37:  // left arrow  
    case 74:  // k
      MoveBackward(1);
      break;

    case 38:  // up arrow
    case 72:  // h
      Init();
      break;

    case 39:  // right arrow
    case 75:  // k
      MoveForward(1);
      break;

    case 40:  // down arrow
    case 76:  // l
      MoveForward(1000);
      break;

    case 85:  // u
      MoveToPrevComment()
      break;

    case 73:  // i
      MoveToNextComment();
      break;

    case 65:  // a
      SetAutoPlay(true);
      break;

    case 13:  // enter
      SwitchAutoPlay();
      break;

    case 48:  // 0
    case 83:  // s
      SetAutoPlay(false);
      break;

    case 49:  // 1
      SetAutoplayDelay( 1*1000);
      SetAutoPlay(true);
      break;

    case 50:  // 2
      SetAutoplayDelay( 2*1000);
      SetAutoPlay(true);
      break;

    case 51:  // 3
      SetAutoplayDelay( 3*1000);
      SetAutoPlay(true);
      break;

    case 52:  // 4
      SetAutoplayDelay( 4*1000);
      SetAutoPlay(true);
      break;

    case 53:  // 5
      SetAutoplayDelay( 5*1000);
      SetAutoPlay(true);
      break;

    case 54:  // 6
      SetAutoplayDelay( 6*1000);
      SetAutoPlay(true);
      break;

    case 55:  // 7
      SetAutoplayDelay( 7*1000);
      SetAutoPlay(true);
      break;

    case 56:  // 8
      SetAutoplayDelay( 8*1000);
      SetAutoPlay(true);
      break;

    case 57:  // 9
      SetAutoplayDelay( 9*1000);
      SetAutoPlay(true);
      break;

    case 81:  // q
      SetAutoplayDelay(10*1000);
      SetAutoPlay(true);
      break;

    case 87:  // w
      SetAutoplayDelay(20*1000);
      SetAutoPlay(true);
      break;

    case 69:  // e
      SetAutoplayDelay(30*1000);
      SetAutoPlay(true);
      break;

    case 82:  // r
      SetAutoplayDelay(40*1000);
      SetAutoPlay(true);
      break;

    case 84:  // t
      SetAutoplayDelay(50*1000);
      SetAutoPlay(true);
      break;

    case 89:  // y
      SetAutoplayDelay(60*1000);
      SetAutoPlay(true);
      break;

    case 70:  // f
      FlipBoard();
      break;

    case 71:  // g
      SetHighlight(!highlightOption);
      break;

    case 68:  // d
      if (IsRotated) FlipBoard();
      break;

    case 86:  // v
      if (numberOfGames > 1){
	  currentGame = 0;
        Init();
      }
      break;

    case 66:  // b
      if (currentGame > 0){
	  currentGame--;
        Init();
      }
      break;

    case 78:  // n
      if (numberOfGames > currentGame){
	  currentGame++;
        Init();
      }
      break;

    case 77:  // m
      if (numberOfGames > 1){
	  currentGame = numberOfGames - 1;
        Init();
      }
      break;

    case 27: // escape
      displayHelp();
      break;

    case 90: // z
      break;

    case 67: // c
      break;

    case 88: // x
//PAOLO
text = '';
for(ii=0; ii<8; ii++){
  for(jj=0; jj<8; jj++){
    ID='tcol' + ii + 'trow' + jj;
    text += document.getElementById(ID).offsetHeight + ' ' +  document.getElementById(ID).offsetWidth + ' ';
  }
}
alert(text)
      break;

    case 79:  // o
      SetCommentsOnSeparateLines(!commentsOnSeparateLines);
      Init();
      break;

    case 80:  // p
      SetCommentsIntoMoveText(!commentsIntoMoveText);
      Init();
      break;

    default: 
    break;
  }

}

var pgnGame;
var numberOfGames = -1; 
var currentGame   = -1;

/*
 * Global variables holding game tags.
 */
var gameDate;
var gameWhite;
var gameBlack;
var gameEvent;
var gameSite;
var gameRound;
var gameResult;
var gameFEN;

var oldAnchor = -1;

var isAutoPlayOn = false;
var AutoPlayInterval;
var Delay = 1000;
var autostartAutoplay = false;

var initialGame = 0;
var initialHalfmove = 0;

var MaxMove = 500;

var castleRook    = -1;
var mvCapture     =  0;
var mvIsCastling  =  0;
var mvIsPromotion =  0;
var mvFromCol     = -1;
var mvFromRow     = -1;
var mvToCol       = -1;
var mvToRow       = -1;
var mvPiece       = -1;
var mvPieceId     = -1;
var mvPieceOnTo   = -1;
var mvCaptured    = -1;
var mvCapturedId  = -1;

var enPassant    =  false;
var enPassantCol = -1;

Board = new Array(8);
for(i=0; i<8; ++i){
 Board[i] = new Array(8);
}

// HistCol and HistRow contain move history up to the last replayed ply
// HistCol[0] and HistRow[0] contain the from square (0..7, 0..7 from square a1)
// HistCol[1] and HistRow[1] contain castling and capture info
// HistCol[2] and HistRow[2] contain the from square (0..7, 0..7 from square a1)

HistCol          = new Array(3);
HistRow          = new Array(3);
HistPieceId      = new Array(2);
HistType         = new Array(2);

PieceCol         = new Array(2);
PieceRow         = new Array(2);
PieceType        = new Array(2);
PieceMoveCounter = new Array(2);

for(i=0; i<2; ++i){
  PieceCol[i]         = new Array(16);
  PieceRow[i]         = new Array(16);
  PieceType[i]        = new Array(16);
  PieceMoveCounter[i] = new Array(16);
  HistType[i]    = new Array(MaxMove);
  HistPieceId[i] = new Array(MaxMove);
}

for(i=0; i<3; ++i){
  HistCol[i]     = new Array(MaxMove);
  HistRow[i]     = new Array(MaxMove);
}

startingSquareSize = -1;
startingImageSize = -1;

PiecePicture = new Array(2);
for(i=0; i<2; ++i) PiecePicture[i] = new Array(6);

PieceCode    = new Array(6);
PieceCode[0] = "K";
PieceCode[1] = "Q";
PieceCode[2] = "R";
PieceCode[3] = "B";
PieceCode[4] = "N";
PieceCode[5] = "P";

var FenString   = "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
var ImageOffset = -1; 
                                                
var ImagePath = '';                                                 
var ImagePathOld;
var imageType = 'png';

var defaultImagesSize = 40;

var highlightOption = true;

var commentsIntoMoveText = true;
var commentsOnSeparateLines = false;

var pgnUrl = '';

CastlingLong  = new Array(2);
CastlingShort = new Array(2);
Moves = new Array(MaxMove);
MoveComments = new Array(MaxMove);

var MoveColor;
var MoveCount;
var PlyNumber;
var StartPly;

var IsRotated = false;

ClearImg  = new Image();

DocumentImages = new Array();

var gameSelectorString='Select a game...';

function CheckLegality(what, plyCount){
  var retVal;
  /*
   * Is it a castling move/
   */
  if (what == 'O-O'){
    retVal = CheckLegalityOO();
    var start = PieceCol[MoveColor][0];
    var end   = 6;
    while(start < end){
      var isCheck = IsCheck(start, MoveColor*7, MoveColor);
      if (isCheck) return false;
      ++start;
    }
    StoreMove(plyCount);
    return retVal;
  } else if (what == 'O-O-O'){
    retVal = CheckLegalityOOO();
    var start = PieceCol[MoveColor][0];
    var end   = 2;
    while(start > end){
      var isCheck = IsCheck(start, MoveColor*7, MoveColor);
      if (isCheck) return false;
      --start;
    }
    StoreMove(plyCount);
    return retVal;
  } 
  /*
   * Some checks common to all pieces:
   *
   * o If it is not a capture the square has to be empty.
   * o If it is a capture the TO square has to be occupied by a piece of the
   *   opposite color, with the exception of the en-passant capture.
   * o If the moved piece and the piece in the TO square are different then 
   *   the moved piece has to be a pawn promoting.
   *
   */
  if (!mvCapture){
    if (Board[mvToCol][mvToRow] !=0) return false;
  }
  if ((mvCapture) && (Color(Board[mvToCol][mvToRow]) != 1-MoveColor)){
    if ((mvPiece != 6) || (!enPassant) || (enPassantCol != mvToCol) ||
	(mvToRow != 5-3*MoveColor)) return false;
  }
  if (mvIsPromotion){
    if (mvPiece     != 6)               return false;
    if (mvPieceOnTo >= 6)               return false;
    if (mvToRow     != 7*(1-MoveColor)) return false;
  }
  /*
   * It is a piece move. Loop over all pieces and find the ones of the same
   * type as the one in the move. For each one of these check if they could 
   * have made the move.
   */
  var pieceId;
  for (pieceId = 0; pieceId < 16; ++pieceId){
     if (PieceType[MoveColor][pieceId] == mvPiece){
      if (mvPiece == 1){
	retVal = CheckLegalityKing(pieceId);
      } else if (mvPiece == 2){
        retVal = CheckLegalityQueen(pieceId);
      } else if (mvPiece == 3){
	retVal = CheckLegalityRook(pieceId);
      } else if (mvPiece == 4){
	retVal = CheckLegalityBishop(pieceId);
      } else if (mvPiece == 5){
	retVal = CheckLegalityKnight(pieceId);
      } else if (mvPiece == 6){
	retVal = CheckLegalityPawn(pieceId);
      }
      if (retVal){
	mvPieceId = pieceId;
       /*
        * Now that the board is updated check if the king is in check.
        */
        StoreMove(plyCount);
	var isCheck = IsCheck(PieceCol[MoveColor][0], PieceRow[MoveColor][0],
			      MoveColor);
	if (!isCheck){
	  return true;
	} else{
	  UndoMove(plyCount);
	}
      }
    }
  }
  return false;
}

function CheckLegalityKing(thisKing){
  if ((mvFromCol >= 0) &&
      (mvFromCol != PieceCol[MoveColor][thisKing])) return false;
  if ((mvFromRow > 0) &&
      (mvFromRow != PieceRow[MoveColor][thisKing])) return false;

  if (Math.abs(PieceCol[MoveColor][thisKing]-mvToCol) > 1) return false;
  if (Math.abs(PieceRow[MoveColor][thisKing]-mvToRow) > 1) return false;

  return true;
}

function CheckLegalityQueen(thisQueen){
  if ((mvFromCol >= 0) &&
      (mvFromCol != PieceCol[MoveColor][thisQueen])) return false;
  if ((mvFromRow >= 0) &&
      (mvFromRow != PieceRow[MoveColor][thisQueen])) return false;

  if (((PieceCol[MoveColor][thisQueen]-mvToCol)*
       (PieceRow[MoveColor][thisQueen]-mvToRow) != 0) &&
      (Math.abs(PieceCol[MoveColor][thisQueen]-mvToCol) !=
       Math.abs(PieceRow[MoveColor][thisQueen]-mvToRow))) return false;

  var clearWay = CheckClearWay(thisQueen);
  if (!clearWay) return false;

  return true;
}

function CheckLegalityRook(thisRook){
  if ((mvFromCol >= 0) &&
      (mvFromCol != PieceCol[MoveColor][thisRook])) return false;
  if ((mvFromRow >= 0) &&
      (mvFromRow != PieceRow[MoveColor][thisRook])) return false;

  if ((PieceCol[MoveColor][thisRook]-mvToCol)*
      (PieceRow[MoveColor][thisRook]-mvToRow) != 0) return false;

  var clearWay = CheckClearWay(thisRook);
  if (!clearWay) return false;

  return true;
}

function CheckLegalityBishop(thisBishop){
  if ((mvFromCol >= 0) &&
      (mvFromCol != PieceCol[MoveColor][thisBishop])) return false;
  if ((mvFromRow >= 0) &&
      (mvFromRow != PieceRow[MoveColor][thisBishop])) return false;

  if (Math.abs(PieceCol[MoveColor][thisBishop]-mvToCol) !=
      Math.abs(PieceRow[MoveColor][thisBishop]-mvToRow)) return false;

  var clearWay = CheckClearWay(thisBishop);
  if (!clearWay) return false;

  return true;
}

function CheckLegalityKnight(thisKnight){
  if ((mvFromCol >= 0) &&
      (mvFromCol != PieceCol[MoveColor][thisKnight])) return false;
  if ((mvFromRow >= 0) &&
      (mvFromRow != PieceRow[MoveColor][thisKnight])) return false;

  if (Math.abs(PieceCol[MoveColor][thisKnight]-mvToCol)*
      Math.abs(PieceRow[MoveColor][thisKnight]-mvToRow) != 2) return false;
  return true;
}
function CheckLegalityPawn(thisPawn){
  if ((mvFromCol >= 0) &&
      (mvFromCol != PieceCol[MoveColor][thisPawn])) return false;
  if ((mvFromRow >= 0) &&
      (mvFromRow != PieceRow[MoveColor][thisPawn])) return false;

  if (Math.abs(PieceCol[MoveColor][thisPawn]-mvToCol) != mvCapture)
     return false;

  if (mvCapture){
    if (PieceRow[MoveColor][thisPawn]-mvToRow != 2*MoveColor-1) return false;
  } else{
    if (PieceRow[MoveColor][thisPawn]-mvToRow == 4*MoveColor-2){
      if (PieceRow[MoveColor][thisPawn] != 1+5*MoveColor) return false;
      if (Board[mvToCol][mvToRow+2*MoveColor-1] != 0)     return false;
    } else{
      if (PieceRow[MoveColor][thisPawn]-mvToRow != 2*MoveColor-1) return false;
    }
  }
  return true;
}

function CheckLegalityOO(){
  if (CastlingShort[MoveColor] == 0) return false;
  if (PieceMoveCounter[MoveColor][0] > 0) return false;
  /*
   * Find which rook was involved in the castling.
   */
  var legal    = false;
  var thisRook = 0;
  while (thisRook < 16){
    if ((PieceCol[MoveColor][thisRook]  >  PieceCol[MoveColor][0]) &&
	(PieceRow[MoveColor][thisRook]  == MoveColor*7)            &&
        (PieceType[MoveColor][thisRook] == 3)){
      legal = true;
      break;
    }
    ++thisRook;
  }
  if (!legal) return false;
  if (PieceMoveCounter[MoveColor][thisRook] > 0) return false;
  /*
   * Check no piece is between the king and the rook. To make it compatible
   * with fisher-random rules clear the king and rook squares now.
   */
  Board[PieceCol[MoveColor][0]][MoveColor*7]        = 0;
  Board[PieceCol[MoveColor][thisRook]][MoveColor*7] = 0;
  var col = PieceRow[MoveColor][thisRook];
  if (col < 6) col = 6;
  while ((col > PieceCol[MoveColor][0]) || (col >= 5)){
    if (Board[col][MoveColor*7] != 0){
      return false;
    }
    --col;
  }
  castleRook = thisRook;
  return true;
}

function CheckLegalityOOO(){
  if (CastlingLong[MoveColor] == 0) return false;
  if (PieceMoveCounter[MoveColor][0] > 0) return false;
  /*
   * Find which rook was involved in the castling.
   */
  var legal    = false;
  var thisRook = 0;
  while (thisRook < 16){
    if ((PieceCol[MoveColor][thisRook]  <  PieceCol[MoveColor][0]) &&
	(PieceRow[MoveColor][thisRook]  == MoveColor*7)            &&
        (PieceType[MoveColor][thisRook] == 3)){
      legal = true;
      break;
    }
    ++thisRook;
  }
  if (!legal) return false;
  if (PieceMoveCounter[MoveColor][thisRook] > 0) return false;
  /*
   * Check no piece is between the king and the rook. To make it compatible
   * with fisher-random rules clear the king and rook squares now.
   */
  Board[PieceCol[MoveColor][0]][MoveColor*7]        = 0;
  Board[PieceCol[MoveColor][thisRook]][MoveColor*7] = 0;
  var col = PieceRow[MoveColor][thisRook];
  if (col > 2) col = 2;
  while ((col > PieceCol[MoveColor][0]) || (col <= 3)){
    if (Board[col][MoveColor*7] != 0){
      return false;
    }
    ++col;
  }
  castleRook = thisRook;
  return true;
}

function CheckClearWay(thisPiece){
  var stepCol = sign(mvToCol-PieceCol[MoveColor][thisPiece]);
  var stepRow = sign(mvToRow-PieceRow[MoveColor][thisPiece]);

  var startCol = PieceCol[MoveColor][thisPiece]+stepCol;
  var startRow = PieceRow[MoveColor][thisPiece]+stepRow;

  while ((startCol != mvToCol) || (startRow != mvToRow)){
    if (Board[startCol][startRow] != 0) return false;
    startCol += stepCol;
    startRow += stepRow;
  }
  return true;
}

function ClearMove(move){
  var ss = move.length;
  var cc = -1;
  var ii = 0;
  var mm = "";
  while(ii < ss){
    cc = move.charCodeAt(ii);
    if ((cc == 45) || ((cc >= 48) && (cc <= 57)) || (cc == 61) ||
	((cc >= 65) && (cc <= 90)) || ((cc >=97) && (cc <= 122))){
	  mm += move.charAt(ii);
    }
    ++ii;
  }
  return mm;
}

function GoToMove(thisMove){
  var currentPly = parseInt(document.HiddenBoardForm.CurrentPly.value);
  var diff       = thisMove - currentPly;
  if (diff > 0){
    MoveForward(diff);
  } else{
    MoveBackward(-diff);
  }
}

function SetCommentsIntoMoveText(onOff){
  commentsIntoMoveText = onOff;
}

function SetCommentsOnSeparateLines(onOff){ 
  commentsOnSeparateLines = onOff;
}

function SetAutostartAutoplay(onOff){
  autostartAutoplay = onOff;
}

function SetInitialHalfmove(number){
  initialHalfmove = number;
}

function SetInitialGame(number){
  initialGame = number - 1;
}

/******************************************************************************
 *                                                                            *
 * Function HighlightLastMove:                                                *
 *                                                                            *
 * Show a text with the last move played and highlight the anchor to it.      *
 *                                                                            *
 ******************************************************************************/
function HighlightLastMove(){
  var anchorName;

  /*
   * Remove the highlighting from the old anchor if any.
   */
  if (oldAnchor >= 0){
    var anchorName      = 'Mv'+oldAnchor;
    theAnchor           = document.getElementById(anchorName);
    if (theAnchor != null) theAnchor.className = 'move';
  }
  /*
   * Find which move has to be highlighted. If the move number is negative
   * we are at the starting position and nothing is to be highlighted and
   * the header on top of the board is removed.
   */
  var showThisMove = parseInt(document.HiddenBoardForm.CurrentPly.value) - 1;
  if (showThisMove > StartPly + PlyNumber) showThisMove = StartPly + PlyNumber;

  var theShowCommentTextObject = document.getElementById("GameLastComment");
  if (theShowCommentTextObject != null){
    if ((MoveComments[showThisMove+1] != '') && (MoveComments[showThisMove+1] != undefined))
      theShowCommentTextObject.innerHTML = MoveComments[showThisMove+1]; 
    else
      theShowCommentTextObject.innerHTML = '-';
    theShowCommentTextObject.className = 'GameLastComment';
  }
  
  /*
   * Show the side to move
   */ 
  if ((showThisMove+1)%2==0) text='white';
  else text='black';
 
  theObject = document.getElementById("GameSideToMove");
  if (theObject != null) theObject.innerHTML = text; 

  var theShowMoveTextObject = document.getElementById("GameNextMove");
  if (theShowMoveTextObject != null){
    if (showThisMove+1 >= (StartPly+PlyNumber)){
      text = gameResult[currentGame];
    }else{
      move = Moves[showThisMove+1];
      var text = '';
      var mvNum = Math.floor((showThisMove+1)/2) + 1;
      if ((showThisMove+1) % 2 == 0){
        text += mvNum + '. ';
      } else{
        text += mvNum + '... ';
      }
      text += move;
    }
    theShowMoveTextObject.innerHTML = text; 
    theShowMoveTextObject.className = 'GameNextMove';
  }

  var theShowMoveTextObject = document.getElementById("GameLastMove");
  if (theShowMoveTextObject != null){
    if (showThisMove < StartPly){
      text = '-';
    }else{
      move = Moves[showThisMove];
      var text = '';
      var mvNum = Math.floor(showThisMove/2) + 1;
      if (showThisMove % 2 == 0){
        text += mvNum + '. ';
      } else{
        text += mvNum + '... ';
      }
      text += move;
    }
    theShowMoveTextObject.innerHTML = text; 
    theShowMoveTextObject.className = 'GameLastMove';
  }

  if (showThisMove >= (StartPly-1)){
    showThisMove++;
    anchorName          = 'Mv' + showThisMove;
    theAnchor           = document.getElementById(anchorName);
    if (theAnchor != null) theAnchor.className = 'move moveOn';
    oldAnchor           = showThisMove;

    if (highlightOption){
      highlightColFrom = HistCol[0][showThisMove - 1];
      if (highlightColFrom == undefined) highlightColFrom = -1;
      highlightRowFrom = HistRow[0][showThisMove - 1];
      if (highlightRowFrom == undefined) highlightRowFrom = -1;
      highlightColTo = HistCol[2][showThisMove - 1];
      if (highlightColTo == undefined) highlightColTo = -1;
      highlightRowTo = HistRow[2][showThisMove - 1];
      if (highlightRowTo == undefined) highlightRowTo = -1;

      highlightMove(highlightColFrom, highlightRowFrom, highlightColTo, highlightRowTo);
    }
  }
}

function SetHighlightOption(on){
  highlightOption = on;
}

/******************************************************************************
 *                                                                            *
 * Function SetHighlight                                                      *
 *                                                                            *
 * sets the option switch to highlight moves on the chessboard                *
 * and removes highlighting from previously highlighted squares               *
 * if row/col From/To are -1 only removes the previous highlighting           *
 *                                                                            *
 ******************************************************************************/
function SetHighlight(on){
  SetHighlightOption(on);
  if (on)
    HighlightLastMove();
  else
    highlightMove(-1, -1, -1, -1);
}

// global vars to remember last highlighted square
var lastColFromHighlighted = -1;
var lastRowFromHighlighted = -1;
var lastColToHighlighted = -1;
var lastRowToHighlighted = -1;
/******************************************************************************
 *                                                                            *
 * Function highlightMove:                                                    *
 *                                                                            *
 * switches on the highlighting for the given move                            *
 * and removes highlighting from previously highlighted squares               *
 * if row/col From/To are -1 only removes the previous highlighting           *
 *                                                                            *
 ******************************************************************************/
function highlightMove(colFrom, rowFrom, colTo, rowTo){

  highlightSquare(lastColFromHighlighted, lastRowFromHighlighted, false);
  highlightSquare(lastColToHighlighted, lastRowToHighlighted, false);

  if ( highlightSquare(colFrom, rowFrom, true) ){
    lastColFromHighlighted = colFrom;
    lastRowFromHighlighted = rowFrom;
  } else {
    lastColFromHighlighted = -1;
    lastRowFromHighlighted = -1;
  }

  if ( highlightSquare(colTo, rowTo, true) ){
    lastColToHighlighted = colTo;
    lastRowToHighlighted = rowTo;
  } else {
    lastColToHighlighted = -1;
    lastRowToHighlighted = -1;
  }
}

/******************************************************************************
 *                                                                            *
 * Function highlightSquare:                                                  *
 *                                                                            *
 * switches on/off the highlighting for the given square                      *
 * and removes highlighting from previously highlighted squares               *
 * if row/col From/To are -1 or undefined returns false                       *
 *                                                                            *
 ******************************************************************************/
function highlightSquare(col, row, on){
  if ((col == undefined) || (row == undefined)) return false;
  if (! SquareOnBoard(col, row)) return false;

  // locates coordinates on the HTML table
  if (IsRotated){trow = row; tcol = 7 - col;}
  else{trow = 7 - row; tcol = col;}

  if (on) {
    if ((trow+tcol)%2 == 0)
      document.getElementById('tcol' + tcol + 'trow' + trow).className = "highlightWhiteSquare";
    else
      document.getElementById('tcol' + tcol + 'trow' + trow).className = "highlightBlackSquare";
  } else {
    if ((trow+tcol)%2 == 0)
      document.getElementById('tcol' + tcol + 'trow' + trow).className = "whiteSquare";
    else
      document.getElementById('tcol' + tcol + 'trow' + trow).className = "blackSquare";
  }
  return true;
}

/******************************************************************************
 *                                                                            *
 * Function pgnGameFromPgnText:                                               *
 *                                                                            *
 * Load the games into the array pgnGame.                                     *
 *                                                                            *
 ******************************************************************************/
function pgnGameFromPgnText(pgnText){
  pgnGame = new Array();
  lines=pgnText.split("\n");
  inGameHeader = false;
  inGameBody = false;
  gameIndex = 0;
  pgnGame[gameIndex]='';
  for(ii in lines){

    // according to the PGN standard lines starting with % should be ignored
    if(lines[ii].charAt(0) == '%') continue;

    if(lines[ii].charAt(0) == '['){
      if(inGameBody){
        gameIndex++;
        pgnGame[gameIndex]='';
      }
      inGameHeader=true
      inGameBody=false
    }else{
      if(inGameHeader){
        inGameHeader=false
        inGameBody=true
      }
    }
    if (gameIndex >= 0)
      pgnGame[gameIndex] += lines[ii] + ' \n'; 
  }  

  return (gameIndex >= 0);
}

/******************************************************************************
 *                                                                            *
 * Function loadPgnFromPgnUrl:                                                *
 *                                                                            *
 * Load the games from the specified URL into the array pgnGame.              *
 *                                                                            *
 ******************************************************************************/
function loadPgnFromPgnUrl(pgnUrl){
  
  var http_request = false;
    if (window.XMLHttpRequest) { // Mozilla, Safari, ...
      http_request = new XMLHttpRequest();
      if (http_request.overrideMimeType) {
        http_request.overrideMimeType('text/xml');
      }
    } else if (window.ActiveXObject) { // IE
      try {
        http_request = new ActiveXObject("Msxml2.XMLHTTP");
      } catch (e) {
        try {
          http_request = new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {}
      }
    }
  if (!http_request){
    alert('Error reading PGN file from URL');
    return false; 
  }
  http_request.open("GET", pgnUrl, false); 
  http_request.send(null);
  if((http_request.readyState  == 4) && ((http_request.status  == 200) || (http_request.status  == 0))){ 
    return pgnGameFromPgnText(http_request.responseText);
  }else{ 
    alert('Error reading PGN file from URL: ' + http_request.status);
    return false;
  }
}

function SetPgnUrl(url){
  pgnUrl = url;
}

/******************************************************************************
 *                                                                            *
 * Function createBoardFromPgnUrl:                                            *
 *                                                                            *
 * Load the games from the specified URL.                                     *
 *                                                                            *
 ******************************************************************************/
function createBoardFromPgnUrl(){
  if ( pgnUrl == ''){
    alert('Error: missing PGN URL location.\n\nUse:\n\n  SetPgnUrl("http://yoursite/yourpath/yourfile.pgn")\n\n in a SCRIPT statement of your HTML file');
    return 
  }

  theObject = document.getElementById("GameBoard");
  if (theObject != null) theObject.innerHTML = "Please wait while loading PGN file..."; 

  if ( loadPgnFromPgnUrl(pgnUrl) ) 
    Init();
}

/******************************************************************************
 *                                                                            *
 * Function Init:                                                             *
 *                                                                            *
 * Load the games.                                                            *
 *                                                                            *
 ******************************************************************************/
function Init(){
  if (currentGame < 0) firstStart = true;
  else firstStart = false;

  InitImages();
  if (isAutoPlayOn) SetAutoPlay(false);

  if (firstStart){
    numberOfGames = pgnGame.length;
    LoadGameHeaders();
    if (initialGame < 0) initialGame = 0;
    if (initialGame < numberOfGames) currentGame = initialGame;
    else currentGame = numberOfGames - 1;
  }

  InitFEN(gameFEN[currentGame]);
  OpenGame(currentGame);
  
  /*
   * Find the index of the first square image if needed.
   */
  if (ImageOffset < 0){
    for (ii = 0; ii < document.images.length; ++ii){
      if (document.images[ii].src == ClearImg.src){
        ImageOffset = ii;
        break;
      }
    }
  }

  RefreshBoard();
  document.HiddenBoardForm.CurrentPly.value = StartPly;
  HighlightLastMove();
  if (firstStart){
    if (initialHalfmove < 0) initialHalfmove = 0;
    GoToMove(initialHalfmove);
    if (autostartAutoplay) SetAutoPlay(true);
  }
}
/******************************************************************************
 *                                                                            *
 * Function InitFEN:                                                          *
 *                                                                            *
 * Prepare the starting position from the FEN.                                *
 *                                                                            *
 ******************************************************************************/
function InitFEN(startingFEN){
  if (startingFEN != undefined){
    FenString = startingFEN;
  }else{
    FenString = "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
  }
  /*
   * Reset the board;
   */
  var ii, jj;
  for (ii = 0; ii < 8; ++ii){
    for (jj = 0; jj < 8; ++jj){
      Board[ii][jj] = 0;
    }
  }
  /*
   * Set the initial position. As of now only the normal starting position.
   */
  var color, pawn;
  StartPly  = 0;
  MoveCount = StartPly;
  MoveColor = StartPly % 2;
  enPassant = false;
  for (ii = 0; ii < 2; ii++){
    CastlingLong[ii]  = 1;
    CastlingShort[ii] = 1;
  }
  HalfMove=0;

  if (FenString == "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1"){
    for (color = 0; color < 2; ++color){
      PieceType[color][0] = 1;  // King
      PieceCol[color][0]  = 4;
      PieceType[color][1] = 2;  // Queen
      PieceCol[color][1]  = 3;
      PieceType[color][6] = 3;  // Rooks
      PieceType[color][7] = 3;
      PieceCol[color][6]  = 0;
      PieceCol[color][7]  = 7;
      PieceType[color][4] = 4;  // Bishops
      PieceType[color][5] = 4;
      PieceCol[color][4]  = 2;
      PieceCol[color][5]  = 5;
      PieceType[color][2] = 5;  // Knights
      PieceType[color][3] = 5;
      PieceCol[color][2]  = 1;
      PieceCol[color][3]  = 6;
      for (pawn = 0; pawn < 8; ++pawn){
	PieceType[color][pawn+8] = 6;
	PieceCol[color][pawn+8]  = pawn;
      }
      for (ii = 0; ii < 16; ++ii){
	PieceMoveCounter[color][ii] = 0;
	PieceRow[color][ii]         = (1-color) * Math.floor(ii/8) +
 	                                 color  * (7-Math.floor(ii/8));
      }
      for (ii = 0; ii < 16; ii++){
        var col = PieceCol[color][ii];
        var row = PieceRow[color][ii];
        Board[col][row] = (1-2*color)*PieceType[color][ii];
        // alert("Standard FEN: Setting "+(1-2*color)*PieceType[color][ii]+ " at "+col+" / "+row);
      }
    }
  } else{
    var cc, ii, jj, kk, ll, nn, mm;
    for (ii=0; ii<2; ii++)
      { for (jj=0; jj<16; jj++)
        { PieceType[ii][jj]=-1;
          PieceCol[ii][jj]=0;
          PieceRow[ii][jj]=0;
          PieceMoveCounter[ii][jj]=0;
        }
      }
      ii=0; jj=7; ll=0; nn=1; mm=1; cc=FenString.charAt(ll++);
      while (cc!=" ")
      { if (cc=="/")
        { if (ii!=8)
          { alert("Invalid FEN [1]: char "+ll+" in "+FenString);
            Init('standard');
            return;
          }
          ii=0;
          jj--;
        }
        if (ii==8) 
        { alert("Invalid FEN [2]: char "+ll+" in "+FenString);
          Init('standard');
          return;
        }
        if (! isNaN(cc))
        { ii+=parseInt(cc);
          if ((ii<0)||(ii>8))
          { alert("Invalid FEN [3]: char "+ll+" in "+FenString);
            return;
          }
        }
        var PieceName = "KQRBNP";
        if (cc.charCodeAt(0)==PieceName.toUpperCase().charCodeAt(0))
        { if (PieceType[0][0]!=-1)
          { alert("Invalid FEN [4]: char "+ll+" in "+FenString);
            return;
          }     
          PieceType[0][0]=1;
          PieceCol[0][0]=ii;
          PieceRow[0][0]=jj;
          ii++;
        }
        if (cc.charCodeAt(0)==PieceName.toLowerCase().charCodeAt(0))
        { if (PieceType[1][0]!=-1)
          { alert("Invalid FEN [5]: char "+ll+" in "+FenString);
            return;
          }  
          PieceType[1][0]=1;
          PieceCol[1][0]=ii;
          PieceRow[1][0]=jj;
          ii++;
        }
        for (kk=1; kk<6; kk++)
        { if (cc.charCodeAt(0)==PieceName.toUpperCase().charCodeAt(kk))
          { if (nn==16)
            { alert("Invalid FEN [6]: char "+ll+" in "+FenString);
              return;
            }          
            PieceType[0][nn]=kk+1;
            PieceCol[0][nn]=ii;
            PieceRow[0][nn]=jj;
            nn++;
            ii++;
          }
          if (cc.charCodeAt(0)==PieceName.toLowerCase().charCodeAt(kk))
          { if (mm==16)
            { alert("Invalid FEN [7]: char "+ll+" in "+FenString);
              return;
            }  
            PieceType[1][mm]=kk+1;
            PieceCol[1][mm]=ii;
            PieceRow[1][mm]=jj;
            mm++;
            ii++;
          }
        }
        if (ll<FenString.length)
          cc=FenString.charAt(ll++);
        else cc=" ";
      }
      if ((ii!=8)||(jj!=0))
      { alert("Invalid FEN [8]: char "+ll+" in "+FenString);
        return;
      }
      if ((PieceType[0][0]==-1)||(PieceType[1][0]==-1))
      { alert("Invalid FEN [9]: char "+ll+" missing king");
        return;
      }
      if (ll==FenString.length)
      { FenString+=" w ";
        FenString+=PieceName.toUpperCase().charAt(0);
        FenString+=PieceName.toUpperCase().charAt(1);
        FenString+=PieceName.toLowerCase().charAt(0);
        FenString+=PieceName.toLowerCase().charAt(1);      
        FenString+=" - 0 1";
        ll++;
      }
      cc=FenString.charAt(ll++);
      if ((cc=="w")||(cc=="b"))
      { if (cc=="w") { 
          StartMove=0;
        }else{ 
          StartMove=1;
          StartPly += 1;
          MoveColor = 1;
        }
      }
      else
      { alert("Invalid FEN [11]: char "+ll+" invalid active color");
        Init('standard');
        return;
      }

      ll++;
      if (ll>=FenString.length)
      { alert("Invalid FEN [12]: char "+ll+" missing castling availability");
        Init('standard');
        return;
      }
      CastlingShort[0]=0; CastlingLong[0]=0; CastlingShort[1]=0; CastlingLong[1]=0;
      cc=FenString.charAt(ll++);
      while (cc!=" ")
      { if (cc.charCodeAt(0)==PieceName.toUpperCase().charCodeAt(0))
          CastlingShort[0]=1; 
        if (cc.charCodeAt(0)==PieceName.toUpperCase().charCodeAt(1))
          CastlingLong[0]=1; 
        if (cc.charCodeAt(0)==PieceName.toLowerCase().charCodeAt(0))
          CastlingShort[1]=1; 
        if (cc.charCodeAt(0)==PieceName.toLowerCase().charCodeAt(1))
          CastlingLong[1]=1; 
        if ((cc=="E")||(cc=="F")||(cc=="G")||(cc=="H")) //for Chess960
          CastlingShort[0]=1;
        if ((cc=="A")||(cc=="B")||(cc=="C")||(cc=="D"))
          CastlingLong[0]=1;
        if ((cc=="e")||(cc=="f")||(cc=="g")||(cc=="h"))
          CastlingShort[1]=1;
        if ((cc=="a")||(cc=="b")||(cc=="c")||(cc=="d"))
          CastlingLong[1]=1;      
        if (ll<FenString.length)
          cc=FenString.charAt(ll++);
        else cc=" ";
      }

      /*
       * Set Board
       * 
      */
      for (color = 0; color < 2; ++color){
       for (ii = 0; ii < 16; ii++){
          if (PieceType[color][ii]!=-1){
   	     var col = PieceCol[color][ii];
	     var row = PieceRow[color][ii];
	     // alert("given FEN: Setting "+(1-2*color)*(PieceType[color][ii])+ " at "+col+" / "+row);
	     Board[col][row] = (1-2*color)*(PieceType[color][ii]);
	  }
       }
      }
          
      if (ll==FenString.length)
      { alert("Invalid FEN [13]: char "+ll+" missing en passant target square");
        Init('standard');
        return;
      }
      enPassant=false;
      cc=FenString.charAt(ll++);
      while (cc!=" ")
      { if ((cc.charCodeAt(0)-97>=0)&&(cc.charCodeAt(0)-97<=7))
          enPassantCol=cc.charCodeAt(0)-97; 
        if (ll<FenString.length)
          cc=FenString.charAt(ll++);
        else cc=" ";
      }
      if (ll==FenString.length)
      { alert("Invalid FEN [14]: char "+ll+" missing halfmove clock");
        return;
      }
      HalfMove=0;
      cc=FenString.charAt(ll++);
      while (cc!=" ")
      { if (isNaN(cc))
        { alert("Invalid FEN [15]: char "+ll+" invalid halfmove clock");
          return;
        }
        HalfMove=HalfMove*10+parseInt(cc);
        if (ll<FenString.length)
          cc=FenString.charAt(ll++);
        else cc=" ";
      }
      if (ll==FenString.length)
      { alert("Invalid FEN [16]: char "+ll+" missing fullmove number");
        return;
      }
      cc=FenString.substring(ll++);
      if (isNaN(cc))
      { alert("Invalid FEN [17]: char "+ll+" invalid fullmove number");
        return;
      }
      if (cc<=0)
      { alert("Invalid FEN [18]: char "+ll+" invalid fullmove number");
        return;
      }
      StartPly+=2*(parseInt(cc)-1);
  }
}

function SetImageType(extension){
  imageType = extension;
}

/******************************************************************************
 *                                                                            *
 * Function InitImages:                                                       *
 *                                                                            *
 * Load all chess men images.                                                 *
 *                                                                            *
 ******************************************************************************/
function InitImages(){
  /* 
   * Reset the array describing what image is in each square.
   */
  DocumentImages.length = 0;
  /*
   * No need if the directory where we pick images is not changed.
   */
  if (ImagePathOld == ImagePath) return;

  /* adds a trailing / to ImagePath if missing and if path not blank */
  if ((ImagePath.length > 0) && (ImagePath[ImagePath.length-1] != '/'))
    ImagePath += '/';

  /*
   * No image.
   */
  ClearImg.src = ImagePath+'clear.'+imageType;

  /*
   * Load the images.
   */
  var color;
  ColorName = new Array ("w", "b");
  for (color = 0; color < 2; ++color){
    PiecePicture[color][1]     = new Image();
    PiecePicture[color][1].src = ImagePath + ColorName[color] + 'k.'+imageType;
    PiecePicture[color][2]     = new Image();
    PiecePicture[color][2].src = ImagePath + ColorName[color] + 'q.'+imageType;
    PiecePicture[color][3]     = new Image();
    PiecePicture[color][3].src = ImagePath + ColorName[color] + 'r.'+imageType;
    PiecePicture[color][4]     = new Image();
    PiecePicture[color][4].src = ImagePath + ColorName[color] + 'b.'+imageType;
    PiecePicture[color][5]     = new Image();
    PiecePicture[color][5].src = ImagePath + ColorName[color] + 'n.'+imageType;
    PiecePicture[color][6]     = new Image();
    PiecePicture[color][6].src = ImagePath + ColorName[color] + 'p.'+imageType;
  }
  ImagePathOld          = ImagePath;
}

function IsCheck(col, row, color){
  var ii, jj;
  var sign = 2*color-1; // white or black
  /*
   * Is the other king giving check?
   */
  if ((Math.abs(PieceCol[1-color][0]-col)<=1) &&
      (Math.abs(PieceRow[1-color][0]-row)<=1)) return true;
  /*
   * Any knight giving check?
   */
  for (ii = -2; ii <= 2; ii += 4){
    for(jj = -1; jj <= 1; jj += 2){
      if (SquareOnBoard(col+ii, row+jj)){
	if (Board[col+ii][row+jj] == sign*5) return true;
      }
      if (SquareOnBoard(col+jj, row+ii)){
	if (Board[col+jj][row+ii] == sign*5) return true;
      }
    }
  }
  /*
   * Any pawn giving check?
   */
  for (ii = -1; ii <= 1; ii += 2){
    if (SquareOnBoard(col+ii, row+sign)){
      if (Board[col+ii][row-sign] == sign*6) return true;
    }
  }
  /*
   * Now queens, rooks and bishops.
   */
  for (ii = -1; ii <= 1; ++ii){
    for (jj = -1; jj <= 1; ++jj){
      if ((ii != 0) || (jj != 0)){
	var checkCol  = col+ii;
	var checkRow  = row+jj;
	var thisPiece = 0;

	while (SquareOnBoard(checkCol, checkRow) && (thisPiece == 0)){
	  thisPiece = Board[checkCol][checkRow];
	  if (thisPiece == 0){
	    checkCol += ii;
	    checkRow += jj;
	  } else{
	    if (thisPiece  == sign*2)                              return true;
	    if ((thisPiece == sign*3) && ((ii == 0) || (jj == 0))) return true;
	    if ((thisPiece == sign*4) && ((ii != 0) && (jj != 0))) return true;
	  }
	}
      }
    }
  }
  return false;
}
/******************************************************************************
 *                                                                            *
 * Function LoadGameHeaders:                                                  *
 *                                                                            *
 * Parse the string containing the PGN score of the game and extract the      *
 * event name, the date, the white and black players and the result. Store    *
 * them in global arrays.                                                     *
 *                                                                            *
 ******************************************************************************/
function LoadGameHeaders(){
  var ii;
  var tag = /\[(\w+)\s+\"([^\"]+)\"\]/g;
  /*
   * Initialize the global arrays to the number of games length.
   */
  gameDate        = new Array(numberOfGames); 
  gameWhite       = new Array(numberOfGames);
  gameBlack       = new Array(numberOfGames);
  gameFEN         = new Array(numberOfGames);
  gameEvent       = new Array(numberOfGames);
  gameSite        = new Array(numberOfGames);
  gameRound       = new Array(numberOfGames);
  gameResult      = new Array(numberOfGames);
  /*
   * Read the headers of all games and store the information in te global
   * arrays.
   */
  for (ii = 0; ii < numberOfGames; ++ii){
    var ss      = pgnGame[ii];
    var lastKet = ss.lastIndexOf(']');
    var header  = ss.substring(0, ++lastKet);
    var parse;
    while ((parse = tag.exec(ss)) != null){
      if (parse[1] == 'Event'){
	gameEvent[ii]  = parse[2];
      } else if  (parse[1] == 'Site'){
	gameSite[ii]   = parse[2];
      } else if  (parse[1] == 'Round'){
	gameRound[ii]   = parse[2];
      } else if  (parse[1] == 'Date'){
	gameDate[ii]   = parse[2];
      } else if  (parse[1] == 'White'){
	gameWhite[ii]  = parse[2];
      } else if  (parse[1] == 'Black'){
	gameBlack[ii]  = parse[2];
      } else if  (parse[1] == 'FEN'){
	gameFEN[ii]  = parse[2];
      } else if  (parse[1] == 'Result'){
	gameResult[ii] = parse[2];
      }
    }
  }
  return;
}
/******************************************************************************
 *                                                                            *
 * Function MoveBackward:                                                     *
 *                                                                            *
 * Move back in the game by "diff" moves. The old position is reconstructed   *
 * using the various "Hist" arrays.                                           *
 *                                                                            *
 ******************************************************************************/
function MoveBackward(diff){
  /*
   * First of all find to which ply we have to go back. Remember that
   * document.HiddenBoardForm.CurrentPly.value contains the ply number counting
   * from 1.
   */
  var currentPly = parseInt(document.HiddenBoardForm.CurrentPly.value);
  var goFromPly  = currentPly - 1;
  var goToPly    = goFromPly  - diff;
  if (goToPly < StartPly) goToPly = StartPly-1;
  /*
   * Loop back to reconstruct the old position one ply at the time.
   */
  var thisPly;
  for(thisPly = goFromPly; thisPly > goToPly; --thisPly){
    currentPly--;
    MoveColor = 1-MoveColor;
    /*
     * Reposition the moved piece on the original square.
     */
    var chgPiece = HistPieceId[0][thisPly];
    Board[PieceCol[MoveColor][chgPiece]][PieceRow[MoveColor][chgPiece]] = 0;

    Board[HistCol[0][thisPly]][HistRow[0][thisPly]] = HistType[0][thisPly]*
	(1-2*MoveColor);
    PieceType[MoveColor][chgPiece] = HistType[0][thisPly];
    PieceCol[MoveColor][chgPiece]  = HistCol[0][thisPly];
    PieceRow[MoveColor][chgPiece]  = HistRow[0][thisPly];
    PieceMoveCounter[MoveColor][chgPiece]--;
    /*
     * If the move was a castling reposition the rook on its original square.
     */
    chgPiece = HistPieceId[1][thisPly];
    if ((chgPiece >= 0) && (chgPiece < 16)){
       Board[PieceCol[MoveColor][chgPiece]][PieceRow[MoveColor][chgPiece]] = 0;
       Board[HistCol[1][thisPly]][HistRow[1][thisPly]] = HistType[1][thisPly]*
	(1-2*MoveColor);
       PieceType[MoveColor][chgPiece] = HistType[1][thisPly];
       PieceCol[MoveColor][chgPiece]  = HistCol[1][thisPly];
       PieceRow[MoveColor][chgPiece]  = HistRow[1][thisPly];
       PieceMoveCounter[MoveColor][chgPiece]--;
    } 
    /*
     * If the move was a capture reposition the captured piece on its
     * original square.
     */
    chgPiece -= 16;
    if ((chgPiece >= 0) && (chgPiece < 16)){
       Board[PieceCol[1-MoveColor][chgPiece]][PieceRow[1-MoveColor][chgPiece]] = 0;
       Board[HistCol[1][thisPly]][HistRow[1][thisPly]] = HistType[1][thisPly]*
	(2*MoveColor-1);
       PieceType[1-MoveColor][chgPiece] = HistType[1][thisPly];
       PieceCol[1-MoveColor][chgPiece]  = HistCol[1][thisPly];
       PieceRow[1-MoveColor][chgPiece]  = HistRow[1][thisPly];
       PieceMoveCounter[1-MoveColor][chgPiece]--;
    } 
  }
  /*
   * Now that we have the old position refresh the board and update the 
   * ply count on the HTML.
   */
  document.HiddenBoardForm.CurrentPly.value = currentPly;
  RefreshBoard();
  HighlightLastMove(); 
  /*
   * Set a new timeout if in autoplay mode.
   */
  if (AutoPlayInterval) clearTimeout(AutoPlayInterval);
  if (isAutoPlayOn) AutoPlayInterval=setTimeout("MoveBackward(1)", Delay);
}
/******************************************************************************
 *                                                                            *
 * Function MoveForward:                                                      *
 *                                                                            *
 * Move forward in the game by "diff" moves. The new position is found        *
 * parsing the array containing the moves and "executing" them.               *
 *                                                                            *
 ******************************************************************************/
function MoveForward(diff){
  /*
   * First of all find to which ply we have to go back. Remember that
   * document.HiddenBoardForm.CurrentPly.value contains the ply number counting
   * from 1.
   */
  var currentPly = parseInt(document.HiddenBoardForm.CurrentPly.value);
  goToPly        = currentPly + parseInt(diff);

  if (goToPly > (StartPly+PlyNumber)) goToPly = StartPly+PlyNumber;
  var ii;
  /*
   * Loop over all moves till the selected one is reached. Check that
   * every move is legal and if yes update the board.
   */
  for(ii = currentPly; ii < goToPly; ++ii){
    var move  = Moves[ii];
    var parse = ParseMove(move, ii);
    if (!parse) {
      alert('Error on ply ' + move);
      return;
    }
    MoveColor = 1-MoveColor; 
  }
  /*
   * Once the desired position is reached refresh the board and update the 
   * ply count on the HTML.
   */
  document.HiddenBoardForm.CurrentPly.value = goToPly;
  RefreshBoard();
  HighlightLastMove(); 
  /*
   * Set a new timeout if in autoplay mode.
   */
  if (AutoPlayInterval) clearTimeout(AutoPlayInterval);
  if (isAutoPlayOn) AutoPlayInterval=setTimeout("MoveForward(1)", Delay);
}

/******************************************************************************
 *                                                                            *
 * Function MoveToNextComment                                                 *
 *                                                                            *
 * moves to the next ply that has a comment. if no comment found, dont move   *
 *                                                                            *
 ******************************************************************************/
function MoveToNextComment()
{
  var currentPly = parseInt(document.HiddenBoardForm.CurrentPly.value);
  for(ii=currentPly+1; ii<=StartPly+PlyNumber; ii++){
    if (MoveComments[ii] != '') {
      GoToMove(ii);
      break;
    }
  }
}

/******************************************************************************
 *                                                                            *
 * Function MoveToPrevComment                                                 *
 *                                                                            *
 * moves to the prev ply that has a comment. if no comment found, dont move   *
 *                                                                            *
 ******************************************************************************/
function MoveToPrevComment()
{
  var currentPly = parseInt(document.HiddenBoardForm.CurrentPly.value);
  for(ii=(currentPly-1); ii>=0; ii--){
    if (MoveComments[ii] != '') {
      GoToMove(ii);
      break;
    }
  }
}

/******************************************************************************
 *                                                                            *
 * Function OpenGame(gameId)                                                  *
 *                                                                            *
 * opens game with assigned number                                            *
 *                                                                            *
 ******************************************************************************/
function OpenGame(gameId){
  ParsePGNGameString(pgnGame[gameId]);
  currentGame = gameId; 
  PrintHTML();
}

/******************************************************************************
 *                                                                            *
 * Function ParseHTMLGameString:                                              *
 *                                                                            *
 * Extract all moves from the HTML string and store them. Moves are           *
 * identified by a string like:                                               *
 *                                                                            *
 * <A HREF="javascript:GoToMove(16)" CLASS="move" Id="MvXXX">h6</A>           *
 *                                                                            *
 ******************************************************************************/
function ParseHTMLGameString(gameString){
  var ss = gameString;
  
  var end, move, length;
  var start       = 0;
  var searchStart = '>';    // Start of move marker
  var searchEnd   = '</';  // End of move marker
  PlyNumber = 0;
  while(1){
    start = ss.indexOf(searchStart, start);
    if (start < 0) break;                     // Nothing more found --> leave.
    end   = ss.indexOf(searchEnd, start);
    if (end   < 0) break;                     // Nothing more found --> leave.
    length = end-start-1;
    if (length <= 0) break;                   // Nothing more found --> leave.
    move = ss.substr(start+1, length);
    move = move.replace(/\s+/g, '');
    Moves[StartPly+PlyNumber++] = ClearMove(move);

    start = end+4;
    if (start > ss.length) break;             // End of string --> leave.
  }
}

/******************************************************************************
 *                                                                            *
 * Function ParsePGNGameString:                                               *
 *                                                                            *
 * Extract all moves from the PGN string and store them.                      *
 *                                                                            *
 ******************************************************************************/

function ParsePGNGameString(gameString){

  var ss      = gameString;
  var lastKet = ss.lastIndexOf(']');
  /*
   * Get rid of the PGN tags and remove the result at the end. 
   */
  ss = ss.substring(++lastKet, ss.length);
// ss = ss.replace(/\s+/g, ' ');
  ss = ss.replace(/^\s/, '');
//  ss = ss.replace(/1-0/, '');
//  ss = ss.replace(/0-1/, '');
//  ss = ss.replace(/1\/2-1\/2/, '');
//  ss = ss.replace(/\*/, '');
  ss = ss.replace(/\s$/, '');

  PlyNumber = 0;
  for (ii=0; ii<StartPly; ii++) Moves[ii]='';
  MoveComments[StartPly+PlyNumber]='';

  for (start=0; start<ss.length; start++){
  
    switch (ss.charAt(start)){

      case ' ':
      case '\b':
      case '\f':
      case '\n':
      case '\r':
      case '\t':
        break;

      case '$':
        commentStart = start;
        commentEnd = commentStart + 1;
        while ('0123456789'.indexOf(ss.charAt(commentEnd)) >= 0) {
          commentEnd++;
          if (commentEnd == ss.length) break;
        }
        if (MoveComments[StartPly+PlyNumber].length>0) MoveComments[StartPly+PlyNumber] += ' ';
        MoveComments[StartPly+PlyNumber] += ss.substring(commentStart, commentEnd);
        start = commentEnd;
        break;

      case '{':
        commentStart = start+1;
        commentEnd = ss.indexOf('}',start+1);
        if (commentEnd > 0){
          if (MoveComments[StartPly+PlyNumber].length>0) MoveComments[StartPly+PlyNumber] += ' ';
          MoveComments[StartPly+PlyNumber] += ss.substring(commentStart, commentEnd); 
          start = commentEnd;
        }else{
          alert('Error parsing PGN: missing end comment char }');
          return;
        }
        break;

      case ';':
        commentStart = start+1;
        commentEnd = ss.indexOf('\n',start+1);
        if (commentEnd < 0) {commentEnd = ss.length}
        if (MoveComments[StartPly+PlyNumber].length>0) MoveComments[StartPly+PlyNumber] += ' ';
        MoveComments[StartPly+PlyNumber] += ss.substring(commentStart, commentEnd); 
        start = commentEnd;
        break;

      case '(':
        openVariation = 1;
        variationStart = start;
        variationEnd = start+1;
        while ((openVariation > 0) && (variationEnd<ss.length)) {
          nextOpen = ss.indexOf('(', variationEnd);
          nextClosed = ss.indexOf(')', variationEnd);
          if (nextClosed < 0) {
            alert('Error parsing PGN: missing end variation char }');
            return
          }
          if ((nextOpen >= 0) && (nextOpen < nextClosed)) {
            openVariation++;
            variationEnd = nextOpen+1;
          }else{
            openVariation--;
            variationEnd = nextClosed+1;
          }
        }
        MoveComments[StartPly+PlyNumber] = ss.substring(variationStart, variationEnd+1); 
        start = variationEnd;
        break;

      default:
        
        searchThis = '1-0';
        if (ss.indexOf(searchThis,start)==start){
          start += searchThis.length;
          MoveComments[StartPly+PlyNumber] += ss.substring(start, ss.length);
          start = ss.length;
          break;
        }
        
        searchThis = '0-1';
        if (ss.indexOf(searchThis,start)==start){
          start += searchThis.length;
          MoveComments[StartPly+PlyNumber] += ss.substring(start, ss.length);
          start = ss.length;
          break;
        }
        
        searchThis = '1/2-1/2';
        if (ss.indexOf(searchThis,start)==start){
          start += searchThis.length;
          MoveComments[StartPly+PlyNumber] += ss.substring(start, ss.length);
          start = ss.length;
          break;
        }
        
        searchThis = '*';
        if (ss.indexOf(searchThis,start)==start){
          start += searchThis.length;
          MoveComments[StartPly+PlyNumber] += ss.substring(start, ss.length);
          start = ss.length;
          break;
        }
        
        moveCount = Math.floor((StartPly+PlyNumber)/2)+1;
        searchThis = moveCount+'.';
        if(ss.indexOf(searchThis,start)==start){
          start += searchThis.length;
          while ((ss.charAt(start) == '.') || (ss.charAt(start) == ' ')  || (ss.charAt(start) == '\n') || (ss.charAt(start) == '\r')){start++};
        }
        end = ss.indexOf(' ',start);
        end2 = ss.indexOf('$',start); if ((end2 > 0) && (end2 < end)) end = end2;
        end2 = ss.indexOf('{',start); if ((end2 > 0) && (end2 < end)) end = end2;
        end2 = ss.indexOf(';',start); if ((end2 > 0) && (end2 < end)) end = end2;
        end2 = ss.indexOf('(',start); if ((end2 > 0) && (end2 < end)) end = end2;
        if (end < 0) end = ss.length;
        move = ss.substring(start,end);
        Moves[StartPly+PlyNumber] = ClearMove(move);
        if (ss.charAt(end) == ' ') start = end; else start = end - 1;
        MoveComments[StartPly+PlyNumber] = MoveComments[StartPly+PlyNumber].replace(/[ \b\f\n\r\t]+$/, '');
        MoveComments[StartPly+PlyNumber] = translateNAGs(MoveComments[StartPly+PlyNumber]);
        PlyNumber++;
        MoveComments[StartPly+PlyNumber]='';
        break;
    }
  }
  MoveComments[StartPly+PlyNumber]=''; 
}

var NAG = new Array();
NAG[0] = ''       
NAG[1] = 'good move'        
NAG[2] = 'bad move'        
NAG[3] = 'very good move'       
NAG[4] = 'very bad move'       
NAG[5] = 'speculative move'        
NAG[6] = 'questionable move'        
NAG[7] = 'forced move'      
NAG[8] = 'singular move'       
NAG[9] = 'worst move'
NAG[10] = 'drawish position'          
NAG[11] = 'equal chances, quiet position'        
NAG[12] = 'equal chances, active position'       
NAG[13] = 'unclear position'          
NAG[14] = 'White has a slight advantage'      
NAG[15] = 'Black has a slight advantage'    
NAG[16] = 'White has a moderate advantage'       
NAG[17] = 'Black has a moderate advantage'       
NAG[18] = 'White has a decisive advantage'       
NAG[19] = 'Black has a decisive advantage'       
NAG[20] = 'White has a crushing advantage'    
NAG[21] = 'Black has a crushing advantage'    
NAG[22] = 'White is in zugzwang'        
NAG[23] = 'Black is in zugzwang'        
NAG[24] = 'White has a slight space advantage'      
NAG[25] = 'Black has a slight space advantage'
NAG[26] = 'White has a moderate space advantage'      
NAG[27] = 'Black has a moderate space advantage'      
NAG[28] = 'White has a decisive space advantage'      
NAG[29] = 'Black has a decisive space advantage'      
NAG[30] = 'White has a slight time (development) advantage'     
NAG[31] = 'Black has a slight time (development) advantage'     
NAG[32] = 'White has a moderate time (development) advantage'     
NAG[33] = 'Black has a moderate time (development) advantage'     
NAG[34] = 'White has a decisive time (development) advantage'     
NAG[35] = 'Black has a decisive time (development) advantage'     
NAG[36] = 'White has the initiative'        
NAG[37] = 'Black has the initiative'        
NAG[38] = 'White has a lasting initiative'       
NAG[39] = 'Black has a lasting initiative'       
NAG[40] = 'White has the attack'        
NAG[41] = 'Black has the attack'        
NAG[42] = 'White has insufficient compensation for material deficit'     
NAG[43] = 'Black has insufficient compensation for material deficit'     
NAG[44] = 'White has sufficient compensation for material deficit'     
NAG[45] = 'Black has sufficient compensation for material deficit'     
NAG[46] = 'White has more than adequate compensation for material deficit'   
NAG[47] = 'Black has more than adequate compensation for material deficit'   
NAG[48] = 'White has a slight center control advantage'     
NAG[49] = 'Black has a slight center control advantage'     
NAG[50] = 'White has a moderate center control advantage'     
NAG[51] = 'Black has a moderate center control advantage'     
NAG[52] = 'White has a decisive center control advantage'     
NAG[53] = 'Black has a decisive center control advantage'     
NAG[54] = 'White has a slight kingside control advantage'     
NAG[55] = 'Black has a slight kingside control advantage'     
NAG[56] = 'White has a moderate kingside control advantage'     
NAG[57] = 'Black has a moderate kingside control advantage'     
NAG[58] = 'White has a decisive kingside control advantage'     
NAG[59] = 'Black has a decisive kingside control advantage'     
NAG[60] = 'White has a slight queenside control advantage'     
NAG[61] = 'Black has a slight queenside control advantage'     
NAG[62] = 'White has a moderate queenside control advantage'     
NAG[63] = 'Black has a moderate queenside control advantage'     
NAG[64] = 'White has a decisive queenside control advantage'     
NAG[65] = 'Black has a decisive queenside control advantage'     
NAG[66] = 'White has a vulnerable first rank'      
NAG[67] = 'Black has a vulnerable first rank'      
NAG[68] = 'White has a well protected first rank'     
NAG[69] = 'Black has a well protected first rank'     
NAG[70] = 'White has a poorly protected king'      
NAG[71] = 'Black has a poorly protected king'      
NAG[72] = 'White has a well protected king'      
NAG[73] = 'Black has a well protected king'      
NAG[74] = 'White has a poorly placed king'      
NAG[75] = 'Black has a poorly placed king'      
NAG[76] = 'White has a well placed king'      
NAG[77] = 'Black has a well placed king'    
NAG[78] = 'White has a very weak pawn structure'     
NAG[79] = 'Black has a very weak pawn structure'     
NAG[80] = 'White has a moderately weak pawn structure'     
NAG[81] = 'Black has a moderately weak pawn structure'     
NAG[82] = 'White has a moderately strong pawn structure'     
NAG[83] = 'Black has a moderately strong pawn structure'     
NAG[84] = 'White has a very strong pawn structure'     
NAG[85] = 'Black has a very strong pawn structure'     
NAG[86] = 'White has poor knight placement'       
NAG[87] = 'Black has poor knight placement'       
NAG[88] = 'White has good knight placement'       
NAG[89] = 'Black has good knight placement'       
NAG[90] = 'White has poor bishop placement'       
NAG[91] = 'Black has poor bishop placement'       
NAG[92] = 'White has good bishop placement'       
NAG[93] = 'Black has good bishop placement'       
NAG[84] = 'White has poor rook placement'       
NAG[85] = 'Black has poor rook placement'       
NAG[86] = 'White has good rook placement'       
NAG[87] = 'Black has good rook placement'       
NAG[98] = 'White has poor queen placement'       
NAG[99] = 'Black has poor queen placement'      
NAG[100] = 'White has good queen placement'       
NAG[101] = 'Black has good queen placement'       
NAG[102] = 'White has poor piece coordination'       
NAG[103] = 'Black has poor piece coordination' 
NAG[104] = 'White has good piece coordination'       
NAG[105] = 'Black has good piece coordination'       
NAG[106] = 'White has played the opening very poorly'     
NAG[107] = 'Black has played the opening very poorly'     
NAG[108] = 'White has played the opening poorly'      
NAG[109] = 'Black has played the opening poorly'      
NAG[110] = 'White has played the opening well'      
NAG[111] = 'Black has played the opening well'      
NAG[112] = 'White has played the opening very well'     
NAG[113] = 'Black has played the opening very well'     
NAG[114] = 'White has played the middlegame very poorly'     
NAG[115] = 'Black has played the middlegame very poorly'     
NAG[116] = 'White has played the middlegame poorly'     
NAG[117] = 'Black has played the middlegame poorly'      
NAG[118] = 'White has played the middlegame well'      
NAG[119] = 'Black has played the middlegame well'     
NAG[120] = 'White has played the middlegame very well'     
NAG[121] = 'Black has played the middlegame very well'     
NAG[122] = 'White has played the ending very poorly'     
NAG[123] = 'Black has played the ending very poorly'     
NAG[124] = 'White has played the ending poorly'      
NAG[125] = 'Black has played the ending poorly'      
NAG[126] = 'White has played the ending well'      
NAG[127] = 'Black has played the ending well'      
NAG[128] = 'White has played the ending very well'     
NAG[129] = 'Black has played the ending very well' 
NAG[130] = 'White has slight counterplay'        
NAG[131] = 'Black has slight counterplay'        
NAG[132] = 'White has moderate counterplay'        
NAG[133] = 'Black has moderate counterplay'        
NAG[134] = 'White has decisive counterplay'        
NAG[135] = 'Black has decisive counterplay'        
NAG[136] = 'White has moderate time control pressure'      
NAG[137] = 'Black has moderate time control pressure'      
NAG[138] = 'White has severe time control pressure'     
NAG[139] = 'Black has severe time control pressure'      

function translateNAGs(comment){
  var jj, ii = 0;
  numString = "01234567890";
  while ((ii = comment.indexOf('$', ii)) >= 0) {
    jj=ii+1;
    while(('0123456789'.indexOf(comment.charAt(jj)) >= 0) && (jj<comment.length)) { jj++; if (jj == comment.length) break}
    nag = parseInt(comment.substring(ii+1,jj));
    if ((nag != undefined) && (NAG[nag] != undefined))
      comment = comment.replace(comment.substring(ii,jj), '<SPAN CLASS="nag">' + NAG[nag] + '</SPAN>');
    ii++;  
  }
  return comment;
}


/******************************************************************************
 *                                                                            *
 * Function ParseMove:                                                        *
 *                                                                            *
 * Given a move exctract which piece moves, from which square and to which    *
 * square. Check if the move is legal, but do not check just yet of the       *
 * king is left in check. Take into account castling, promotion and captures  *
 * including the en passant capture.                                          *
 *                                                                            *
 ******************************************************************************/
function ParseMove(move, plyCount){
  var ii, ll;
  var remainder;
  var toRowMarker = -1;
  /*
   * Reset the global move variables.
   */
  castleRook    = -1;
  mvIsCastling  =  0;
  mvIsPromotion =  0;
  mvCapture     =  0;
  mvFromCol     = -1;
  mvFromRow     = -1;
  mvToCol       = -1;
  mvToRow       = -1;
  mvPiece       = -1;
  mvPieceId     = -1;
  mvPieceOnTo   = -1;
  mvCaptured    = -1;
  mvCapturedId  = -1;
  /*
   * Given the move as something like Rdxc3 or exf8=Q+ extract the destination
   * column and row and remember whatever is left of the string.
   */
  ii = 1;
  while(ii < move.length){
    if (!isNaN(move.charAt(ii))){
      mvToCol     = move.charCodeAt(ii-1) - 97;
      mvToRow     = move.charAt(ii)       -  1;
      reminder    = move.substring(0, ii-1);
      toRowMarker = ii;
    }
    ++ii;
  }
  /*
   * The final square did not make sense, maybe it is a castle.
   */
  if ((mvToCol < 0) || (mvToCol > 7) || (mvToRow < 0) || (mvToRow > 7)){
    if ((move.indexOf('O') >= 0) || (move.indexOf('0') >= 0)){
      /*
       * Do long castling first since looking for o-o will get it too.
       */
      if ((move.indexOf('O-O-O') >= 0) || (move.indexOf('0-0-0') >= 0)){
	mvIsCastling = 1;
        mvPiece      = 1;
        mvPieceId    = 0;
        mvPieceOnTo  = 1;
	  mvFromCol    = 4;
	  mvToCol      = 2;
        mvFromRow    = 7*MoveColor;
        mvToRow      = 7*MoveColor;
	if (CheckLegality('O-O-O', plyCount)){
	  return true;
	} else{
	  return false;
	}
      }
      if ((move.indexOf('O-O') >= 0) || (move.indexOf('0-0') >= 0)){
	  mvIsCastling = 1;
        mvPiece      = 1;
        mvPieceId    = 0;
        mvPieceOnTo  = 1;
	  mvFromCol    = 4;
	  mvToCol      = 6;
        mvFromRow    = 7*MoveColor;
        mvToRow      = 7*MoveColor;
	if (CheckLegality('O-O', plyCount)){
	  return true;
	} else{
	  return false;
	}
      }
    }
  }
  /*
   * Now extract the piece and the origin square. If it is a capture (the 'x'
   * is present) mark the as such.
   */
  mvPiece = 6;
  ll = reminder.length; 
  if (ll > 0){
    for(ii = 1; ii < 6; ++ii){
      if (reminder.charAt(0) == PieceCode[ii-1]) mvPiece = ii;
    }

    if (reminder.charAt(ll-1) == 'x') mvCapture = 1;

    if (isNaN(move.charAt(ll-1-mvCapture))){
      mvFromCol = move.charCodeAt(ll-1-mvCapture) - 97;
      if ((mvFromCol < 0) || (mvFromCol > 7)) mvFromCol = -1;
    } else{
      mvFromRow = move.charAt(ll-1-mvCapture) - 1;
      if ((mvFromRow < 0) || (mvFromRow > 7)) mvFromRow = -1;
    }
  }
  mvPieceOnTo = mvPiece;
  /*
   * If the to square is occupied mark the move as capture. Take care of
   * the special en passant case.
   */
  if (Board[mvToCol][mvToRow] != 0){
    mvCapture = 1;
  } else{
    if ((mvPiece == 6) && (enPassant) && (mvToCol == enPassantCol) &&
	(mvToRow == 5-3*MoveColor)){
      mvCapture = 1;
    }
  }
  /*
   * Take care of promotions. If there is a '=' in the move or if the
   * destination row is not the last character in the move, then it may be a
   * pawn promotion.
   */
  ii = move.indexOf('=');
  if (ii < 0) ii = toRowMarker;
  if ((ii > 0) && (ii < move.length-1)){
    if (mvPiece == 6){
      var newPiece = move.charAt(ii+1);
      if (newPiece == PieceCode[1]){
	  mvPieceOnTo = 2;
      } else if (newPiece == PieceCode[2]){
	  mvPieceOnTo = 3;
      } else if (newPiece == PieceCode[3]){
	  mvPieceOnTo = 4;
      } else if (newPiece == PieceCode[4]){
	  mvPieceOnTo = 5;
      }
      mvIsPromotion = 1;
    }
  }
  /*
   * Find which piece was captured. The first part checks normal captures.
   * If nothing is found then it has to be a pawn making an en-passant
   * capture.
   */
  if (mvCapture){
    mvCapturedId = 15;
    while((mvCapturedId >= 0) && (mvCaptured < 0)){
      if ((PieceType[1-MoveColor][mvCapturedId] >  0)       &&
	  (PieceCol[1-MoveColor][mvCapturedId]  == mvToCol) &&
	  (PieceRow[1-MoveColor][mvCapturedId]  == mvToRow)){
	mvCaptured = PieceType[1-MoveColor][mvCapturedId];
      } else{
	--mvCapturedId;
      }
    }
    if ((mvPiece == 6) && (mvCapturedId < 1) && (enPassant)){
      mvCapturedId = 15;
      while((mvCapturedId >= 0) && (mvCaptured < 0)){
        if ((PieceType[1-MoveColor][mvCapturedId] == 6)       &&
	    (PieceCol[1-MoveColor][mvCapturedId]  == mvToCol) &&
	    (PieceRow[1-MoveColor][mvCapturedId]  == 4-MoveColor)){
	  mvCaptured = PieceType[1-MoveColor][mvCapturedId];
	} else{
	  --mvCapturedId;
	}
      }
    }
  }
  /*
   * Check the move legality.
   */
  var retVal;
  retVal = CheckLegality(PieceCode[mvPiece-1], plyCount);
  if (!retVal) return false;
  /*
   * If a pawn was moved check if it enables the en-passant capture on next
   * move;
   */
  enPassant    = false;
  enPassantCol = -1;
  if (mvPiece == 6){
     if (Math.abs(HistRow[0][plyCount]-mvToRow) == 2){
       enPassant    = true;
       enPassantCol = mvToCol;
     }
  }
  return true;
}

function SetGameSelectorString(string){
  gameSelectorString = string;
}

var gamesLimitForSelectFormatting = 500;
var textSelectOptions = '';
/******************************************************************************
 *                                                                            *
 * Function PrintHTML:                                                        *
 *                                                                            *
 ******************************************************************************/
function PrintHTML(){
  var ii, jj;
  var text;

  /*
   * Show the board as a 8x8 table.
   */
  text =  '<FORM NAME="HiddenBoardForm">' +
          '<INPUT TYPE="HIDDEN" VALUE="" NAME="CurrentPly">' +
          '</FORM>' +
          '<TABLE CLASS="boardTable" ID="boardTable" CELLSPACING=0 CELLPADDING=0>';

  for (ii = 0; ii < 8; ++ii){
    text += '<TR>';
    for (jj = 0; jj < 8; ++jj){
      squareId = 'tcol' + jj + 'trow' + ii;
      imageId = 'img_' + squareId;
      if ((ii+jj)%2 == 0){
	text += '<TD CLASS="whiteSquare" ID="' + squareId + '" BGCOLOR="white" ALIGN="center" VALIGN="middle">';
      } else{
	text += '<TD CLASS="blackSquare" ID="' + squareId + '" BGCOLOR="gray" ALIGN="center" VALIGN="middle">';
      } //PAOLO
      text += '<IMG CLASS="pieceImage" ID="' + imageId + '" SRC="'+ImagePath+'clear.'+imageType+'"></TD>';
    }
    text += '</TR>';
  }
  text += '</TABLE>';

  /*
   * Show the HTML for the chessboard
   */
  theObject = document.getElementById("GameBoard");
  if (theObject != null) theObject.innerHTML = text; 
   
  tableSize = document.getElementById("boardTable").offsetWidth;
 
//PAOLO
text = '';
for(ii=0; ii<8; ii++){
  for(jj=0; jj<8; jj++){
    ID='tcol' + ii + 'trow' + jj;
    text += document.getElementById(ID).offsetHeight + ' ' +  document.getElementById(ID).offsetWidth + ' ';
  }
}
alert(text)
 
  numberOfButtons=5;
  spaceSize=3;
  buttonSize=(tableSize - spaceSize*(numberOfButtons - 1))/numberOfButtons;
  text =  '<FORM NAME="GameButtonsForm">' +
          '<TABLE BORDER=0 CELLPADDING=0 CELLSPACING=0>' +
          '<TR>' +
          '<TD>' +
          '<INPUT TYPE="BUTTON" VALUE="&#124;&lt;" STYLE="width: ' + buttonSize + '"; CLASS="buttonControl" ' +
          ' ID="btnInit" onClick="javascript:Init()">' +
          '<TD CLASS="buttonControlSpace" WIDTH="' + spaceSize + '">' +
          '<TD>' +
          '<INPUT TYPE="BUTTON" VALUE="&lt;" STYLE="width: ' + buttonSize + '"; CLASS="buttonControl" ' +
          ' ID="btnMB1" onClick="javascript:MoveBackward(1)">' +
          '<TD CLASS="buttonControlSpace" WIDTH="' + spaceSize + '">' +
          '<TD>' +
          '<INPUT TYPE="BUTTON" VALUE="play" STYLE="width: ' + buttonSize + '"; CLASS="buttonControl" ' +
          ' ID="btnPlay" NAME="AutoPlay" onClick="javascript:SwitchAutoPlay()">' +
          '<TD CLASS="buttonControlSpace" WIDTH="' + spaceSize + '">' +
          '<TD>' +
          '<INPUT TYPE="BUTTON" VALUE="&gt;" STYLE="width: ' + buttonSize + '"; CLASS="buttonControl" ' +
          ' ID="btnMF1" onClick="javascript:MoveForward(1)">' +
          '<TD CLASS="buttonControlSpace" WIDTH="' + spaceSize + '">' +
          '<TD>' +
          '<INPUT TYPE="BUTTON" VALUE="&gt;&#124;" STYLE="width: ' + buttonSize + '"; CLASS="buttonControl" ' +
          ' ID="btnMF1000" onClick="javascript:MoveForward(1000)">' +
          '</TR>' + 
          '</TABLE>' +
          '</FORM>';
  /*
   * Show the HTML for the control buttons
   */
  theObject = document.getElementById("GameButtons");
  if (theObject != null) theObject.innerHTML = text; 

  /*
   * Show the HTML for the Game Selector
   */
  theObject = document.getElementById("GameSelector");
  if (theObject != null){
    if (numberOfGames > 1){
      nameLength = 15;
      blanks = '';
      for (ii=0; ii<nameLength; ii++)
        blanks+='&nbsp';
      text = '<FORM NAME="GameSel"> ' +
             '<SELECT STYLE="font-family: monospace; width: ' + tableSize + ';" CLASS="selectControl" ' +
             'ONCHANGE="if(this.value >= 0) { currentGame=this.value; Init(); }">' +
             '<OPTION value=-1>' + gameSelectorString;
      if(textSelectOptions == ''){
        for (ii=0; ii<numberOfGames; ii++){
          textSelectOptions += '<OPTION value=' + ii + '>';
          if (numberOfGames < gamesLimitForSelectFormatting){
            textSelectOptions += gameWhite[ii].substring(0, nameLength);
            howManyBlanks = nameLength - gameWhite[ii].length;
            if (howManyBlanks > 0) textSelectOptions += blanks.substring(0, 5*howManyBlanks);
            textSelectOptions += '&nbsp;-&nbsp;' + gameBlack[ii].substring(0, nameLength);
            howManyBlanks = nameLength - gameBlack[ii].length;
            if (howManyBlanks > 0) textSelectOptions += blanks.substring(0, 5*howManyBlanks);
            textSelectOptions += '&nbsp;&nbsp;' + gameDate[ii]; 
          }else{
            textSelectOptions += gameWhite[ii] + '&nbsp;-&nbsp;' + gameBlack[ii] + '&nbsp;&nbsp;' + gameDate[ii];
          }
        }
      }
      text += textSelectOptions + '</SELECT></FORM>';
      theObject.innerHTML = text; 
    }
  }

  /*
   * Show the HTML for the Game Event
   */
  theObject = document.getElementById("GameEvent");
  if (theObject != null) theObject.innerHTML = gameEvent[currentGame].replace("-", "&#8209;").replace(" ", "&nbsp;"); 

  /*
   * Show the HTML for the Game Site
   */
  theObject = document.getElementById("GameSite");
  if (theObject != null) theObject.innerHTML = gameSite[currentGame].replace("-", "&#8209;").replace(" ", "&nbsp;"); 

  /*
   * Show the HTML for the Game Round
   */
  theObject = document.getElementById("GameRound");
  if (theObject != null) theObject.innerHTML = gameRound[currentGame].replace("-", "&#8209;").replace(" ", "&nbsp;"); 

  /*
   * Show the HTML for the Game Date
   */
  theObject = document.getElementById("GameDate");
  if (theObject != null) theObject.innerHTML = gameDate[currentGame].replace("-", "&#8209;").replace(" ", "&nbsp;"); 

  /*
   * Show the HTML for the Game White Player
   */
  theObject = document.getElementById("GameWhite");
  if (theObject != null) theObject.innerHTML = gameWhite[currentGame].replace("-", "&#8209;").replace(" ", "&nbsp;"); 

  /*
   * Show the HTML for the Game Black Player
   */
  theObject = document.getElementById("GameBlack");
  if (theObject != null) theObject.innerHTML = gameBlack[currentGame].replace("-", "&#8209;").replace(" ", "&nbsp;"); 

  /*
   * Show the HTML for the Game Result
   */
  theObject = document.getElementById("GameResult");
  if (theObject != null) theObject.innerHTML = gameResult[currentGame].replace("-", "&#8209;").replace(" ", "&nbsp;"); 
  
  text = '<DIV ID="ShowPgnText">';
  for (ii = StartPly; ii < StartPly+PlyNumber; ++ii){
    printedComment = false;
    if (commentsIntoMoveText && (MoveComments[ii] != '')){
      if (commentsOnSeparateLines) text += '<P>';
      text += '<SPAN CLASS="comment">' + MoveComments[ii] + '&nbsp;</SPAN>';
      if (commentsOnSeparateLines) text += '<P>';
      printedComment = true;
    }
    var moveCount = Math.floor(ii/2)+1;
    if (ii%2 == 0){
      text += '<SPAN CLASS="move">' + moveCount + '.&nbsp;</SPAN>';
    }else{
      if ((printedComment) || (ii == StartPly)) text += '<SPAN CLASS="move">' + moveCount + '...&nbsp;</SPAN>';
    }
    jj = ii+1;
    text += '<A HREF="javascript:GoToMove(' + jj + 
      ')" CLASS="move" ID="Mv' + jj + '">' + Moves[ii].replace("-", "&#8209;") + '</A> ';
  }
  if (commentsIntoMoveText && (MoveComments[StartPly+PlyNumber] != '')){
    if (commentsOnSeparateLines) text += '<P>';
    text += '<SPAN CLASS="comment">' + MoveComments[StartPly+PlyNumber] + '</SPAN>';
  }
  text += '</DIV>';

  /*
   * Show the HTML for the Game Text
   */
  theObject = document.getElementById("GameText");
  if (theObject != null) theObject.innerHTML = text; 
}


function FlipBoard(){
  tmpHighlightOption = highlightOption;
  if (tmpHighlightOption) SetHighlight(false);
  IsRotated = !IsRotated;
  RefreshBoard();
  if (tmpHighlightOption) SetHighlight(true);
}

/******************************************************************************
 *                                                                            *
 * Function RefreshBoard:                                                     *
 *                                                                            *
 * Update the images of all pieces on the board.                              *
 *                                                                            *
 ******************************************************************************/
function RefreshBoard(){
  /*
   * Check if we need a new set of pieces.
   */
  InitImages();
  /*
   * Display all empty squares.
   */
  var col, row;
  for (col = 0; col < 8;++col){
    for (row = 0; row < 8; ++row){
      if (Board[col][row] == 0){
        var square;
	if (IsRotated){
	  square= 63-col-(7-row)*8;
	} else{
        square = col+(7-row)*8;
	}
	SetImage(square, ClearImg.src);
      }
    }
  }
  /*
   * Display all pieces.
   */
  var color, ii;
  for (color = 0; color < 2; ++color){
    for (ii = 0; ii < 16; ++ii){
      if (PieceType[color][ii] > 0){
        var square;
        if (IsRotated){
          square = 63-PieceCol[color][ii] - (7-PieceRow[color][ii])*8;
	} else{
	  square = PieceCol[color][ii] + (7-PieceRow[color][ii])*8;
	}
        SetImage(square, PiecePicture[color][PieceType[color][ii]].src);
      }
    }
  }
}
/******************************************************************************
 *                                                                            *
 * Function SetAutoPlay:                                                      *
 *                                                                            *
 * Start the autoplay or stop it depending on the user input.                 *
 *                                                                            *
 ******************************************************************************/
function SetAutoPlay(vv){
  isAutoPlayOn = vv;
  /*
   * No matter what clear the timeout.
   */
  if (AutoPlayInterval) clearTimeout(AutoPlayInterval);
  /*
   * If switched on start  moving forward. Also change the button value.
   */
  if (isAutoPlayOn){
    if ((document.GameButtonsForm) && (document.GameButtonsForm.AutoPlay)){
      document.GameButtonsForm.AutoPlay.value="stop";
    }
    MoveForward(1);
  } else { 
    if ((document.GameButtonsForm)&&(document.GameButtonsForm.AutoPlay))
      document.GameButtonsForm.AutoPlay.value="play";
  }
}
/******************************************************************************
 *                                                                            *
 * Function SetAutoplayDelay:                                                 *
 *                                                                            *
 * Change the delay of autplay.                                               *
 *                                                                            *
 ******************************************************************************/
function SetAutoplayDelay(vv){
  Delay = vv;
}
/******************************************************************************
 *                                                                            *
 * Function SetImage:                                                         *
 *                                                                            *
 * Given a square and an image show it on the board. To make it faster check  *
 * if the image corresponds to the one already there and update it only if    *
 * necessary.                                                                 *
 *                                                                            *
 ******************************************************************************/
function SetImage(square, image){
  if (DocumentImages[square] == image) return;
  document.images[square+ImageOffset].src = image;
  DocumentImages[square]                  = image;   // Store the new image.
}
/******************************************************************************
 *                                                                            *
 * Function SetImagePath:                                                     *
 *                                                                            *
 * Define the path to the directory containing the chess men images.          *
 *                                                                            *
 ******************************************************************************/
function SetImagePath(path){ 
  ImagePath=path;
}
/******************************************************************************
 *                                                                            *
 * Function SwitchAutoPlay:                                                   *
 *                                                                            *
 * Receive user enable/disable autoplay.                                      *
 *                                                                            *
 ******************************************************************************/
function SwitchAutoPlay(){
  if (isAutoPlayOn){
    SetAutoPlay(false);
  } else{
    SetAutoPlay(true);
  }
}
/******************************************************************************
 *                                                                            *
 * Function StoreMove:                                                        *
 *                                                                            *
 * Update the Board array describing the position of each piece, and the      *
 * "History" arrays describing the movement of the pieces during the game.    *
 *                                                                            *
 ******************************************************************************/
function StoreMove(thisPly){

  /*
   * Store the moved piece int he history arrays.
   */

// Stores "square from" history information
  HistPieceId[0][thisPly] = mvPieceId;
  HistCol[0][thisPly]     = PieceCol[MoveColor][mvPieceId];
  HistRow[0][thisPly]     = PieceRow[MoveColor][mvPieceId];
  HistType[0][thisPly]    = PieceType[MoveColor][mvPieceId];

// Stores "square to" history information
  HistCol[2][thisPly] = mvToCol;
  HistRow[2][thisPly] = mvToRow;

  if (mvIsCastling){
     HistPieceId[1][thisPly] = castleRook;
     HistCol[1][thisPly]     = PieceCol[MoveColor][castleRook];
     HistRow[1][thisPly]     = PieceRow[MoveColor][castleRook];
     HistType[1][thisPly]    = PieceType[MoveColor][castleRook];
  } else if (mvCapturedId >= 0){
     HistPieceId[1][thisPly] = mvCapturedId+16;
     HistCol[1][thisPly]     = PieceCol[1-MoveColor][mvCapturedId];
     HistRow[1][thisPly]     = PieceRow[1-MoveColor][mvCapturedId];
     HistType[1][thisPly]    = PieceType[1-MoveColor][mvCapturedId];
  } else{
    HistPieceId[1][thisPly] = -1;
  }

  /*
   * Update the from square and the captured square. Remember that the
   * captured square is not necessarely the to square because of the en-passant.
   */
  Board[PieceCol[MoveColor][mvPieceId]][PieceRow[MoveColor][mvPieceId]] = 0;
  /*
   * Mark the captured piece as such.
   */  
  if (mvCapturedId >=0){
     PieceType[1-MoveColor][mvCapturedId] = -1;
     PieceMoveCounter[1-MoveColor][mvCapturedId]++;
     Board[PieceCol[1-MoveColor][mvCapturedId]][PieceRow[1-MoveColor][mvCapturedId]] = 0;
  }
  /*
   * Update the piece arrays. Don't forget to update the type array, since a
   * pawn might have been replaced by a piece in a promotion.
   *
   */
  PieceType[MoveColor][mvPieceId] = mvPieceOnTo;
  PieceMoveCounter[MoveColor][mvPieceId]++;
  PieceCol[MoveColor][mvPieceId]  = mvToCol;
  PieceRow[MoveColor][mvPieceId]  = mvToRow;
  if (mvIsCastling){
    PieceMoveCounter[MoveColor][castleRook]++;
    if (mvToCol == 2){
      PieceCol[MoveColor][castleRook] = 3;
    } else{
      PieceCol[MoveColor][castleRook] = 5;
    }
    PieceRow[MoveColor][castleRook] = mvToRow;
  }
  /*
   * Update the board.
   */
  Board[mvToCol][mvToRow] = PieceType[MoveColor][mvPieceId]*(1-2*MoveColor);
  if (mvIsCastling){
    Board[PieceCol[MoveColor][castleRook]][PieceRow[MoveColor][castleRook]] =
      PieceType[MoveColor][castleRook]*(1-2*MoveColor);
  }
  return;
}
function UndoMove(thisPly){
  /*
   * Bring the moved piece back.
   */
  Board[mvToCol][mvToRow] = 0;
  Board[HistCol[0][thisPly]][HistRow[0][thisPly]] =
    HistType[0][thisPly]*(1-2*MoveColor);

  PieceCol[MoveColor][mvPieceId]  = HistCol[0][thisPly];
  PieceRow[MoveColor][mvPieceId]  = HistRow[0][thisPly];
  PieceType[MoveColor][mvPieceId] = HistType[0][thisPly];
  PieceMoveCounter[MoveColor][mvPieceId]--;
  /*
   * If capture or castle bring the captured piece or the rook back.
   */
  if (mvCapturedId >=0){
     PieceType[1-MoveColor][mvCapturedId] = mvCapturedId;
     PieceCol[1-MoveColor][mvCapturedId]  = HistCol[1][thisPly];
     PieceRow[1-MoveColor][mvCapturedId]  = HistRow[1][thisPly];
     PieceCol[1-MoveColor][mvCapturedId]  = HistCol[1][thisPly];
  } else if (mvIsCastling){
     PieceCol[MoveColor][castleRook] = HistCol[1][thisPly];
     PieceRow[MoveColor][castleRook] = HistRow[1][thisPly];
     PieceMoveCounter[MoveColor][castleRook]--;
  }

}
function Color(nn){
  if (nn < 0) return 1;
  if (nn > 0) return 0;
  return 2;
}
function sign(nn){
  if (nn > 0) return  1;
  if (nn < 0) return -1;
  return 0;
}

function SquareOnBoard(col, row){
  if ((col < 0) || (col > 7)) return false;
  if ((row < 0) || (row > 7)) return false;
  return true;
}

