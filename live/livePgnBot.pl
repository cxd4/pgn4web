#! /usr/bin/perl -w

#  pgn4web javascript chessboard
#  copyright (C) 2009, 2012 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

# livePgnBot script saving PGN data from live games on frechess.org
# code based on Marcin Kasperski's tutorial availabale at
# http://blog.mekk.waw.pl/series/how_to_write_fics_bot/


use strict;
use Net::Telnet;


our $FICS_HOST = "freechess.org";
our $FICS_PORT = 5000;

our $BOT_HANDLE = $ARGV[0] || "";
our $BOT_PASSWORD = $ARGV[1] || "";

our $OPERATOR_HANDLE = $ARGV[2] || "";

our $STARTUP_FILE_DEFAULT = "livePgnBot.ini";
our $STARTUP_FILE = $ARGV[3] || $STARTUP_FILE_DEFAULT;

if ($BOT_HANDLE eq "" | $OPERATOR_HANDLE eq "") {
  die "\n$0 BOT_HANDLE BOT_PASSWORD OPERATOR_HANDLE [STARTUP_FILE]\n\nBOT_HANDLE = handle for the bot account\nBOT_PASSWORD = password for the both account, use \"\" for a guest account\nOPERATOR_HANDLE = handle for the bot operator to send commands\nSTARTUP_FILE = filename for reading startup commands (default $STARTUP_FILE_DEFAULT)\n\nbot saving PGN data from live games on frechess.org\nmore help available from the operator account with \"tell BOT_HANDLE help\"\n\n";
}


our $PGN_FILE = "live.pgn";

our $VERBOSE = 0;

our $PROTECT_LOGOUT_FREQ = 45 * 60;
our $CHECK_RELAY_FREQ = 3 * 60;
our $OPEN_TIMEOUT = 30;
our $LINE_WAIT_TIMEOUT = 60;
# $LINE_WAIT_TIMEOUT must be smaller than half of $PROTECT_LOGOUT_FREQ and $CHECK_RELAY_FREQ


our $telnet;
our $username;
our $last_cmd_time = 0;
our $last_check_relay_time = 0;

sub cmd_run {
  my ($cmd) = @_;
  print STDERR "info: running ics command: $cmd\n" if $VERBOSE;
  my $output = $telnet->cmd($cmd);
  $last_cmd_time = time();
}

our $pgn = "";
our $lastPgn = "";

our $maxGamesNumDefault = 30; # frechess.org limit
our $maxGamesNum = $maxGamesNumDefault;
our @games_num = ();
our @games_white = ();
our @games_black = ();
our @games_whiteElo = ();
our @games_blackElo = ();
our @games_movesText = ();
our @games_result = ();

our @GAMES_event = ();
our @GAMES_site = ();
our @GAMES_date = ();
our @GAMES_round = ();
our @GAMES_eco = ();
our @GAMES_timeLeft = ();

our $newGame_num = -1;
our $newGame_white;
our $newGame_black;
our $newGame_whiteElo;
our $newGame_blackElo;
our @newGame_moves;
our $newGame_movesText;
our $newGame_result;
our $newGame_event = "";
our $newGame_site = "";
our $newGame_date = "";
our $newGame_round = "";

our $followMode = 0;
our $followLast = "";
our $relayMode = 0;
our $autorelayMode = 0;
our @GAMES_autorelayRunning = ();

our $autorelayEvent;
our $autorelayRound;

sub reset_games {
  cmd_run("follow");
  cmd_run("unobserve");
  $maxGamesNum = $maxGamesNumDefault;
  @games_num = ();
  @games_white = ();
  @games_black = ();
  @games_whiteElo = ();
  @games_blackElo = ();
  @games_movesText = ();
  @games_result = ();
  @GAMES_event = ();
  @GAMES_site = ();
  @GAMES_date = ();
  @GAMES_round = ();
  @GAMES_eco = ();
  @GAMES_timeLeft = ();
  $newGame_event = "";
  $newGame_site = "";
  $newGame_date = "";
  $newGame_round = "";
  $followMode = 0;
  $followLast = "";
  $relayMode = 0;
  $autorelayMode = 0;
  @GAMES_autorelayRunning = ();

  refresh_pgn();
}

sub find_gameIndex {
  my ($thisGameNum) = @_;

  for (my $i=0; $i<$maxGamesNum; $i++) {
    if (($games_num[$i]) && ($games_num[$i] eq $thisGameNum)) {
      return $i;
    }
  }

  return -1;
}

sub save_game {

  if ($newGame_num < 0) {
    print STDERR "error: game not ready when saving\n";
    return;
  }

  my $thisGameIndex = find_gameIndex($newGame_num);
  if ($thisGameIndex < 0) {
    if ($#games_num >= $maxGamesNum) {
      remove_game(-1);
    }
    myAdd(\@games_num, $newGame_num);
    myAdd(\@games_white, $newGame_white);
    myAdd(\@games_black, $newGame_black);
    myAdd(\@games_whiteElo, $newGame_whiteElo);
    myAdd(\@games_blackElo, $newGame_blackElo);
    myAdd(\@games_movesText, $newGame_movesText);
    myAdd(\@games_result, $newGame_result);
    if ($autorelayMode == 0) {
      $GAMES_event[$newGame_num] = $newGame_event;
      $GAMES_site[$newGame_num] = $newGame_site;
      $GAMES_date[$newGame_num] = $newGame_date;
      $GAMES_round[$newGame_num] = $newGame_round;
      $GAMES_eco[$newGame_num] = "";
    }
  } else {
    if (($games_white[$thisGameIndex] ne $newGame_white) || ($games_black[$thisGameIndex] ne $newGame_black) || ($games_whiteElo[$thisGameIndex] ne $newGame_whiteElo) || ($games_blackElo[$thisGameIndex] ne $newGame_blackElo)) {
      print STDERR "error: game $newGame_num mismatch when saving\n";
    } else {
      $games_movesText[$thisGameIndex] = $newGame_movesText;
      if ($games_result[$thisGameIndex] eq "*") {
        $games_result[$thisGameIndex] = $newGame_result;
      }
    }
  }
  refresh_pgn();
}

sub myAdd {
  my ($arrRef, $val) = @_;
  if ($followMode == 1) {
    unshift(@{$arrRef}, $val);
  } else {
    push(@{$arrRef}, $val);
  }
}

sub save_result {
  my ($thisGameNum, $thisResult, $logMissing) = @_;

  my $thisGameIndex = find_gameIndex($thisGameNum);
  if ($thisGameIndex < 0) {
    if ($logMissing == 1) {
      print STDERR "error: missing game $thisGameNum when saving result\n";
    }
  } else {
    $games_result[$thisGameIndex] = $thisResult;
    refresh_pgn();
  }
}

sub remove_game {
  my ($thisGameNum) = @_;
  my $thisGameIndex;

  if ($thisGameNum < 0) {
    if ($followMode == 1) {
      $thisGameIndex = $maxGamesNum - 1;
    } else {
      $thisGameIndex = 0;
    }
    if ($games_num[$thisGameIndex] ne "") {
      $thisGameNum = $games_num[$thisGameIndex];
    } else {
      print STDERR "warning: missing game for removing\n";
      return -1;
    }
  } else {
    $thisGameIndex = find_gameIndex($thisGameNum);
    if ($thisGameIndex < 0) {
      print STDERR "error: missing game $thisGameNum for removing\n";
      return -1;
    }
  }

  if (($games_result[$thisGameIndex] eq "*") || ($relayMode == 1)) {
    cmd_run("unobserve $thisGameNum");
  }
  my $thisMax = $#games_num;
  @games_num = @games_num[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  @games_white = @games_white[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  @games_black = @games_black[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  @games_whiteElo = @games_whiteElo[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  @games_blackElo = @games_blackElo[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  @games_movesText = @games_movesText[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  @games_result = @games_result[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  delete $GAMES_event[$thisGameNum];
  delete $GAMES_site[$thisGameNum];
  delete $GAMES_date[$thisGameNum];
  delete $GAMES_round[$thisGameNum];
  delete $GAMES_eco[$thisGameNum];
  delete $GAMES_timeLeft[$thisGameNum];
  refresh_pgn();
  return $thisGameIndex;
}

sub tell_operator {
  my ($msg) = @_;
  my @msgParts = $msg =~ /(.{1,195})/g;
  for (my $i=0; $i<=$#msgParts; $i++) {
    if ($i > 0) {
      $msgParts[$i] = ".." . $msgParts[$i];
    }
    if (($#msgParts > 0) && ($i < $#msgParts)) {
      $msgParts[$i] = $msgParts[$i] . "..";
    }
    cmd_run("tell $OPERATOR_HANDLE " . $msgParts[$i]);
  }
}

sub process_line {
  my ($line) = @_;

  $line =~ s/[\r\n ]+$//;
  $line =~ s/^[\r\n ]+//;
  return unless $line;

  if ($line =~ /^([^\s()]+)(\(\S+\))* tells you: (\S+)\s*(.*)$/) {
    if ($1 eq $OPERATOR_HANDLE) {
      process_master_command($3, $4);
    } else {
      print STDERR "info: ignoring tell from user $1\n" if $VERBOSE;
    }
  } elsif ($line =~ /^<12> (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+)/) {
    # in order to avoid keeping a game state, each time a board update is
    # received the whole game score is refreshed from the server; this might
    # result in missing the last move(s) of games that end immediately after
    # a board update and do not return a movelist anymore; only an issue for
    # very fast games, not an issue for broadcasts of live events;
    my $thisGN = $16; # GameNum
    my $thisW = $17; # White
    my $thisB = $18; # Black
    my $thisWC = $24; # WhiteClock
    my $thisBC = $25; # BlackClock
    my $thisGI = find_gameIndex($thisGN);
    if (($thisGI < 0) || (($thisW eq $games_white[$thisGI]) && ($thisB eq $games_black[$thisGI]))) {
      $GAMES_timeLeft[$thisGN] = "{ White Time: " . sec2time($thisWC) . " Black Time: " . sec2time($thisBC) . " }";
      cmd_run("moves $thisGN");
    } else {
      print STDERR "error: game $thisGN mismatch when receiving\n";
    }
  } elsif ($line =~ /^{Game (\d+) [^}]*} (\S+)/) {
    save_result($1, $2, 1); # from observed game
  } elsif ($line =~ /^:There .* in the (.*)/) {
    $autorelayEvent = $1;
    $autorelayRound = "";
    if ($autorelayEvent =~ /(.*)\s+Round\s+(\d+)/) {
      $autorelayRound = $2;
      $autorelayEvent = $1;
      $autorelayEvent =~ s/[\s-]+$//g;
    }
  } elsif ($line =~ /^:(\d+)\s+\S+\s+\S+\s+(\S+)\s+(\S+)/) {
    my $thisGameNum = $1;
    my $thisGameResult = $2;
    my $thisGameEco = $3;
    if ($autorelayMode == 1) {
      $GAMES_event[$thisGameNum] = $autorelayEvent;
      $GAMES_site[$thisGameNum] = $newGame_site;
      $GAMES_date[$thisGameNum] = $newGame_date;
      $GAMES_round[$thisGameNum] = $autorelayRound;
      $GAMES_eco[$thisGameNum] = $thisGameEco;
      $GAMES_autorelayRunning[$thisGameNum] = 1;
    }
    if (find_gameIndex($thisGameNum) != -1) {
      if ($thisGameResult ne "*") {
        save_result($thisGameNum, $thisGameResult, 0); # from relay list
      }
    } else {
      if ($autorelayMode == 1) {
        if ($#games_num + 1 < $maxGamesNum) {
          cmd_run("observe $thisGameNum");
        } else {
          print STDERR "warning: more relayed games than max=$maxGamesNum\n" if $VERBOSE;
          tell_operator("warning: more relayed games than max=$maxGamesNum");
        }
      }
    }
  } elsif ($line =~ /^..ANNOUNCEMENT.. from relay: FICS is relaying/) {
    if (($autorelayMode == 1) && ($#games_num < 0)) {
      cmd_run("xtell relay listgames");
    }
  } elsif ($newGame_num < 0) {
    if ($line =~ /^Movelist for game (\d+):/) {
      reset_newGame();
      $newGame_num = $1;
    } else {
      print STDERR "info: ignored line: $line\n" if $VERBOSE;
    }
  } else {
    if ($line =~ /^(\w+)\s+\((\S+)\)\s+vs\.\s+(\w+)\s+\((\S+)\).*/) {
      $newGame_white = $1;
      $newGame_whiteElo = $2;
      $newGame_black = $3;
      $newGame_blackElo = $4;
    } elsif ($line =~ /(.*) initial time: \d+ minutes.*increment: \d+/) {
      our $gameType = $1;
      if (!($gameType =~ /(standard|blitz|lightning)/)) {
        print STDERR "warning: unsupported game $newGame_num: $gameType\n" if $VERBOSE;
        delete $GAMES_timeLeft[$newGame_num];
        delete $GAMES_event[$newGame_num];
        delete $GAMES_site[$newGame_num];
        delete $GAMES_date[$newGame_num];
        delete $GAMES_round[$newGame_num];
        delete $GAMES_eco[$newGame_num];
        cmd_run("unobserve $newGame_num");
        tell_operator("warning: unsupported game $newGame_num: $gameType");
        reset_newGame();
      }
    } elsif ($line =~ /^\s*\d+\.[\s]*([^(\s]+)\s*\([^)]+\)[\s]+([^(\s]+)\s*\([^)]+\)/) {
      push(@newGame_moves, $1);
      push(@newGame_moves, $2);
    } elsif ($line =~ /^\s*\d+\.[\s]*([^(\s]+)\s*\([^)]+\)/) {
      push(@newGame_moves, $1);
    } elsif ($line =~ /^\{[^}]*\}\s+(\S+)/) {
      $newGame_result = $1;
      process_newGame();
    } elsif ($line =~ /^Move\s+/) {
    } elsif ($line =~ /^[\s-]*$/) {
    } else {
      print STDERR "info: ignored line: $line\n" if $VERBOSE;
    }
  }
}

sub process_newGame() {
  my ($moveNum, $i);

  $newGame_movesText = "";
  for ($i=0; $i<=$#newGame_moves; $i++) {
    if ($i % 2 == 0) {
      $moveNum = ($i / 2) + 1;
      if (($moveNum % 5) == 1) {
        $newGame_movesText .= "\n";
      } else {
        $newGame_movesText .= " ";
      }
      $newGame_movesText .= "$moveNum. " . $newGame_moves[$i];
    } else {
      $newGame_movesText .= " " . $newGame_moves[$i];
    }
  }
  save_game();
  reset_newGame();
}

sub reset_newGame() {
  $newGame_num = -1;
  $newGame_white = "";
  $newGame_black = "";
  $newGame_whiteElo = "";
  $newGame_blackElo = "";
  @newGame_moves = ();
  $newGame_result = "";
}

sub time2sec {
  my ($t) = @_;

  if ($t =~ /^(\d+):(\d+):(\d+)$/) {
    return $1 * 3600 + $2 * 60 + $3;
  } elsif ($t =~ /^(\d+):(\d+)$/) {
    return $1* 60 + $2;
  } elsif ($t =~ /^\d+$/) {
    return $1;
  } else {
    print STDERR "error: time2sec($t)\n" if $VERBOSE;
    return 0;
  }
}

sub sec2time {
  my ($t) = @_;
  my ($sec, $min, $hr);

  if ($t =~ /^\d+$/) {
    $sec = $t % 60;
    $t = ($t - $sec) / 60;
    $min = $t % 60;
    $hr = ($t - $min) / 60;
    return sprintf("%d:%02d:%02d", $hr, $min, $sec);
  } elsif ($t =~ /^-/) {
    return "0:00:00";
  } else {
    print STDERR "error: sec2time($t)\n" if $VERBOSE;
    return 0;
  }
}

sub refresh_pgn {
  my ($i, $thisResult, $thisWhite, $thisBlack, $thisWhiteTitle, $thisBlackTitle);

  $pgn = "";
  for ($i=0; $i<$maxGamesNum; $i++) {
    if ($games_num[$i]) {
      if (($followMode == 1) && ($i == 0)) {
        $thisResult = "*";
      } else {
        $thisResult = $games_result[$i];
      }
      if (($relayMode == 1) && ($games_white[$i] =~ /^(GM|IM|FM|WGM|WIM|WFM)([A-Z].*)$/)) {
        $thisWhiteTitle = $1;
        $thisWhite = $2;
      } else {
        $thisWhiteTitle = "";
        $thisWhite = $games_white[$i];
      }
      if (($relayMode == 1) && ($games_black[$i] =~ /^(GM|IM|FM|WGM|WIM|WFM)([A-Z].*)$/)) {
        $thisBlackTitle = $1;
        $thisBlack = $2;
      } else {
        $thisBlackTitle = "";
        $thisBlack = $games_black[$i];
      }
      if ($relayMode == 1) {
        $thisWhite =~ s/(?<=.)([A-Z])/ $1/g;
        $thisBlack =~ s/(?<=.)([A-Z])/ $1/g;
      }
      if (($followMode == 1) && ($thisResult eq "*")) {
        $thisWhite .= " ";
        $thisBlack .= " ";
      }
      $pgn .= "[Event \"" . $GAMES_event[$games_num[$i]] . "\"]\n";
      $pgn .= "[Site \"" . $GAMES_site[$games_num[$i]] . "\"]\n";
      $pgn .= "[Date \"" . $GAMES_date[$games_num[$i]] . "\"]\n";
      $pgn .= "[Round \"" . $GAMES_round[$games_num[$i]] . "\"]\n";
      $pgn .= "[White \"" . $thisWhite . "\"]\n";
      $pgn .= "[Black \"" . $thisBlack . "\"]\n";
      $pgn .= "[Result \"" . $thisResult . "\"]\n";
      if ($games_whiteElo[$i] =~ /^\d+$/) {
        $pgn .= "[WhiteElo \"" . $games_whiteElo[$i] . "\"]\n";
      }
      if ($games_blackElo[$i] =~ /^\d+$/) {
        $pgn .= "[BlackElo \"" . $games_blackElo[$i] . "\"]\n";
      }
      if ($thisWhiteTitle ne "") {
        $pgn .= "[WhiteTitle \"" . $thisWhiteTitle . "\"]\n";
      }
      if ($thisBlackTitle ne "") {
        $pgn .= "[BlackTitle \"" . $thisBlackTitle . "\"]\n";
      }
      if ($GAMES_eco[$games_num[$i]] ne "") {
        $pgn .= "[ECO \"" . $GAMES_eco[$games_num[$i]] . "\"]\n";
      }
      $pgn .= $games_movesText[$i];
      $pgn .= "\n$GAMES_timeLeft[$games_num[$i]]";
      if ($games_result[$i] =~ /^[012\/\*-]+$/) {
        $pgn .= " $games_result[$i]";
      }
      $pgn .= "\n\n";
    }
  }

  if ($pgn eq "") {
    $pgn = temp_pgn();
  }

  if ($pgn ne $lastPgn) {
    open(thisFile, ">$PGN_FILE");
    print thisFile $pgn;
    close(thisFile);
    $lastPgn = $pgn;
  }
}

sub temp_pgn {
  return "[Event \"$newGame_event\"]\n" . "[Site \"$newGame_site\"]\n" . "[Date \"$newGame_date\"]\n" . "[Round \"$newGame_round\"]\n" . "[White \"\"]\n" . "[Black \"\"]\n" . "[Result \"*\"]\n\n*\n\n";
}

our @master_commands = ();
our @master_commands_helptext = ();

sub add_master_command {
  my ($command, $helptext) = @_;
  push (@master_commands, $command);
  push (@master_commands_helptext, $helptext);
}

add_master_command ("autorelay", "autorelay 0|1 (to automatically observe all relayed games)");
add_master_command ("date", "date 2012.11.10 (to set the PGN header tag date)");
add_master_command ("event", "event World Championship (to set the PGN header tag event)");
add_master_command ("file", "file live.pgn (to set the filename for saving PGN data)");
add_master_command ("follow", "follow handle|/s|/b|/l (see freechess.org follow command)");
add_master_command ("forget", "forget 12 34 56 .. (to eliminate given past games from PGN data)");
add_master_command ("help", "help command (to show commands help)");
add_master_command ("ics", "ics server_command (to run a custom command on freechess.org)");
add_master_command ("list", "list (to show lists of observed games)");
add_master_command ("logout", "logout 1 (to logout from freechess.org, returning the given exit value)");
add_master_command ("max", "max 30 (to set the maximum number of games for the PGN data)");
add_master_command ("observe", "observe 12 34 56 .. (to observe given games)");
add_master_command ("relay", "relay 12 34 56 .. (to observe given games from an event relay)");
add_master_command ("reset", "reset 1 (to reset observed/followed games list and setting)");
add_master_command ("round", "round 9 (to set the PGN header tag round)");
add_master_command ("site", "site Moscow RUS (to set the PGN header tag site)");
add_master_command ("startup", "startup verbose 1; autorelay 1; (to read/write startup commands file)");
add_master_command ("status", "status (to show status summary info)");
add_master_command ("temp", "temp (to save temporary PGN data)");
add_master_command ("verbose", "verbose 0|1 (to set verbosity of the bot log terminal)");

sub detect_command {
  my ($command) = @_;
  my $guessedCommand = "";
  for (my $i=0; $i<=$#master_commands; $i++) {
    if ($master_commands[$i] eq $command) {
      return $command;
    }
    if ($master_commands[$i] =~ /^$command/) {
      if ($guessedCommand ne "") {
        return "ambiguous command: $command";
      } else {
        $guessedCommand = $master_commands[$i]
      }
    }
  }
  if ($guessedCommand ne "") {
    return $guessedCommand;
  } else {
    return $command;
  }
}

sub detect_command_helptext {
  my ($command) = @_;
  my $detectedCommand = detect_command($command);
  if ($detectedCommand =~ /^ambiguous command: /) {
    return $detectedCommand;
  }
  for (my $i=0; $i<=$#master_commands; $i++) {
    if ($master_commands[$i] eq $detectedCommand) {
      return $master_commands_helptext[$i];
    }
  }
  return "invalid command";
}

sub process_master_command {
  my ($command, $parameters) = @_;

  $command = detect_command($command);

  if ($command eq "") {
  } elsif ($command =~ /^ambiguous command: /) {
    print STDERR "warning: $command\n" if $VERBOSE;
    tell_operator("error: $command");
  } elsif ($command eq "autorelay") {
    if ($parameters =~ /^(0|1)$/) {
      if ($parameters == 0) {
        $autorelayMode = 0;
        @GAMES_autorelayRunning = ();
      } else {
        if ($followMode == 0) {
          $autorelayMode = 1;
          $relayMode = 1;
          cmd_run("xtell relay listgames");
        } else {
          tell_operator("error: reset follow before activating autorelay");
        }
      }
    } elsif ($parameters !~ /^(?|)$/) {
      tell_operator("error: invalid autorelay parameter");
    }
    tell_operator("autorelay=$autorelayMode");
  } elsif ($command eq "date") {
    if ($parameters =~ /^([^\[\]"]+|""|)$/) {
      if ($parameters ne "") {
        $newGame_date = $parameters;
        if ($newGame_date eq "\"\"") { $newGame_date = ""; }
      }
      tell_operator("date=$newGame_date");
    } else {
      tell_operator("error: invalid date parameter");
    }
  } elsif ($command eq "event") {
    if ($parameters =~ /^([^\[\]"]+|""|)$/) {
      if ($parameters ne "") {
        $newGame_event = $parameters;
        if ($newGame_event eq "\"\"") { $newGame_event = ""; }
      }
      tell_operator("event=$newGame_event");
    } else {
      tell_operator("error: invalid event parameter");
    }
  } elsif ($command eq "file") {
    if ($parameters =~ /^[\w\d\/\\.+=_-]*$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        $PGN_FILE = $parameters;
      }
      tell_operator("file=$PGN_FILE");
    } else {
      tell_operator("error: invalid file parameter");
    }
  } elsif ($command eq "follow") {
    if ($parameters =~ /^([a-zA-Z]+$|\/s|\/b|\/l)/) {
      if ($relayMode == 0) {
        $followMode = 1;
        cmd_run("follow $parameters");
        $followLast = $parameters;
      } else {
        tell_operator("error: reset relay before activating follow");
      }
    } elsif ($parameters eq "") {
      $followMode = 0;
      $followLast = "";
      cmd_run("follow");
    } elsif ($parameters =~ /^(0|1)$/) {
      if (($parameters == 0) || ($relayMode == 0)) {
        $followMode = $parameters;
        if ($parameters == 0) {
          $followLast = "";
        }
      } else {
        tell_operator("error: reset relay before activating follow");
      }
    } elsif ($parameters ne "?") {
      tell_operator("error: invalid follow parameter");
    }
    tell_operator("follow=$followMode last=$followLast");
  } elsif ($command eq "forget") {
    if ($parameters ne "") {
      my @theseGames = split(" ", $parameters);
      for (my $i=0; $i<=$#theseGames; $i++) {
        if ($theseGames[$i] =~ /\d+/) {
          if (remove_game($theseGames[$i]) < 0) {
            tell_operator("error: game $theseGames[$i] not found");
          }
        } else {
          tell_operator("error: invalid game $theseGames[$i]");
        }
      }
      tell_operator("OK forget");
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "help") {
    if ($parameters =~ /\S/) {
      my $par;
      my @pars = split(" ", $parameters);
      foreach $par (@pars) {
        if ($par =~ /\S/) {
          tell_operator(detect_command_helptext(detect_command($par)));
        }
      }
    } else {
      tell_operator("available commands: " . join(", ", @master_commands));
    }
  } elsif ($command eq "ics") {
    if ($parameters !~ /^(?|)$/) {
      cmd_run($parameters);
      tell_operator("OK ics");
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "list") {
    tell_operator(($#games_num + 1) . "/$maxGamesNum games=" . gameList());
  } elsif ($command eq "logout") {
    if ($parameters =~ /^\d+$/) {
      tell_operator("OK logout($parameters)");
      cmd_run("quit");
      print STDERR "info: logout with exit value $parameters\n";
      exit($parameters);
    } elsif ($parameters =~ /^(?|)$/) {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid logout parameter");
    }
  } elsif ($command eq "max") {
    if ($parameters =~ /^([1-9]\d*|)$/) {
      if ($parameters ne "") {
        if ($parameters > $maxGamesNumDefault) {
          print STDERR "warning: max number of games set above frechess.org observe limit of $maxGamesNumDefault\n";
          tell_operator("warning: max number of games set above frechess.org observe limit of $maxGamesNumDefault");
        }
        if ($parameters < $maxGamesNum) {
          for (my $i=$parameters; $i<$maxGamesNum; $i++) {
            if ($games_num[$i]) {
              remove_game($games_num[$i]);
            }
          }
        }
        $maxGamesNum = $parameters;
      }
      tell_operator("max=$maxGamesNum");
    } else {
      tell_operator("error: invalid max parameter");
    }
  } elsif ($command eq "observe") {
    if ($parameters ne "") {
      observe($parameters);
      tell_operator("OK observe");
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "relay") {
    if ($parameters =~ /^([\d\s]+)$/) {
      if ($parameters == 0) {
        $relayMode = 0;
        $autorelayMode = 0;
        @GAMES_autorelayRunning = ();
      } else {
        if ($followMode == 0) {
          $relayMode = 1;
          observe($parameters);
        } else {
          tell_operator("error: reset follow before activating relay");
        }
      }
    } elsif ($parameters eq "") {
      $relayMode = 0;
      $autorelayMode = 0;
      @GAMES_autorelayRunning = ();
    } elsif ($parameters ne "?") {
      tell_operator("error: invalid relay parameter");
    }
    tell_operator("relay=$relayMode");
  } elsif ($command eq "reset") {
    if ($parameters eq "1") {
      reset_games();
      tell_operator("OK reset");
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid reset parameter");
    }
  } elsif ($command eq "round") {
    if ($parameters =~ /^([^\[\]"]+|""|)$/) {
      if ($parameters ne "") {
        $newGame_round = $parameters;
        if ($newGame_round eq "\"\"") { $newGame_round = ""; }
      }
      tell_operator("round=$newGame_round");
    } else {
      tell_operator("error: invalid round parameter");
    }
  } elsif ($command eq "site") {
    if ($parameters =~ /^([^\[\]"]+|""|)$/) {
      if ($parameters ne "") {
        $newGame_site = $parameters;
        if ($newGame_site eq "\"\"") { $newGame_site = ""; }
      }
      tell_operator("site=$newGame_site");
    } else {
      tell_operator("error: invalid site parameter");
    }
  } elsif ($command eq "startup") {
    if ($parameters) {
      write_startupCommands(split(";", $parameters));
    }
    my $startupString = join("; ", read_startupCommands());
    $startupString =~ s/[\n\r]+//g;
    tell_operator("startup($STARTUP_FILE)=$startupString");
  } elsif ($command eq "status") {
    tell_operator(($#games_num + 1) . "/$maxGamesNum games=" . gameList() . " max=$maxGamesNum file=$PGN_FILE follow=$followMode relay=$relayMode autorelay=$autorelayMode verbose=$VERBOSE event=$newGame_event site=$newGame_site date=$newGame_date round=$newGame_round");
  } elsif ($command eq "temp") {
    open(thisFile, ">$PGN_FILE");
    print thisFile temp_pgn();
    close(thisFile);
    print STDERR "info: saved temporary PGN data\n" if $VERBOSE;
    tell_operator("OK temp");
  } elsif ($command eq "verbose") {
    if ($parameters =~ /^(0|1|)$/) {
      if ($parameters ne "") {
        $VERBOSE = $parameters;
      }
      tell_operator("verbose=$VERBOSE");
    } else {
      tell_operator("error: invalid verbose parameter");
    }
  } else {
    print STDERR "warning: invalid command: $command $parameters\n" if $VERBOSE;
    tell_operator("error: invalid command: $command $parameters");
  }
}

sub observe {
  my ($gameList) = @_;
  my @theseGames = split(" ", $gameList);
  for (my $i=0; $i<=$#theseGames; $i++) {
    if ($theseGames[$i] =~ /\d+/) {
      if (find_gameIndex($theseGames[$i]) == -1) {
        cmd_run("observe $theseGames[$i]");
      } else {
        tell_operator("warning: game $theseGames[$i] already observed");
      }
    } else {
      tell_operator("error: invalid game $theseGames[$i]");
    }
  }
}

sub gameList {
  my $outputStr = "";
  for (my $i=0; $i<$maxGamesNum; $i++) {
    if ($games_num[$i]) {
      if ($outputStr ne "") { $outputStr .= ","; }
      $outputStr .= $games_num[$i];
      if ($games_result[$i] eq "1-0") { $outputStr .= "+"; }
      elsif ($games_result[$i] eq "1/2-1/2") { $outputStr .= "="; }
      elsif ($games_result[$i] eq "0-1") { $outputStr .= "-"; }
      else { $outputStr .= "*"; }
    }
  }
  return $outputStr;
}

sub read_startupCommands {
  my @commandList = ();
  if (open(CMDFILE, "<" . $STARTUP_FILE)) {
    @commandList = <CMDFILE>;
    close(CMDFILE);
  }
  return @commandList;
}

sub write_startupCommands {
  my @commandList = @_;
  if (open(CMDFILE, ">" . $STARTUP_FILE)) {
    foreach my $cmd (@commandList) {
      $cmd =~ s/^\s*//;
print STDERR "PAOLO " . $cmd . "\n";
      print CMDFILE $cmd . "\n";
    }
    close(CMDFILE);
    print STDERR "info: startup commands file $STARTUP_FILE written\n";
  } else {
    print STDERR "error: failed writing startup commands file $STARTUP_FILE\n";
    tell_operator("error: failed writing startup commands file $STARTUP_FILE");
  }
}

sub check_releay_results {
  if (($relayMode == 1) && (time - $last_check_relay_time > $CHECK_RELAY_FREQ)) {
    cmd_run("xtell relay listgames");
    $last_check_relay_time = time();
    if ($autorelayMode == 1) {
      for my $thisGameNum (@games_num) {
        if (!$GAMES_autorelayRunning[$thisGameNum]) {
          remove_game($thisGameNum);
        }
      }
      @GAMES_autorelayRunning = ();
    }
  }
}

sub ensure_alive {
  if (time - $last_cmd_time > $PROTECT_LOGOUT_FREQ) {
    cmd_run("date");
  }
}

sub setup {

  $telnet = new Net::Telnet(
    Timeout => $OPEN_TIMEOUT,
    Binmode => 1,
    Errmode => "die",
  );

  $telnet->open(
    Host => $FICS_HOST,
    Port => $FICS_PORT,
  );

  print STDERR "info: connected to $FICS_HOST\n" if $VERBOSE;

  if ($BOT_PASSWORD) {

    $telnet->login(Name => $BOT_HANDLE, Password => $BOT_PASSWORD);
    $username = $BOT_HANDLE;
    print STDERR "info: successfully logged as user $BOT_HANDLE\n";

  } else {

    $telnet->waitfor(
      Match => '/login[: ]*$/i',
      Match => '/username[: ]*$/i',
      Timeout => $OPEN_TIMEOUT,
    );

    $telnet->print($BOT_HANDLE);

    while (1) {
      my $line = $telnet->getline(Timeout => $LINE_WAIT_TIMEOUT);
      next if $line =~ /^[\s\r\n]*$/;
      if ($line =~ /Press return to enter/) {
        $telnet->print();
        last;
      }
      if ($line =~ /("[^"]*" is a registered name|\S+ is already logged in)/) {
        die "Can not login as $BOT_HANDLE: $1\n";
      }
      print STDERR "info: ignored line: $line\n" if $VERBOSE;
    }

    my($pre, $match) = $telnet->waitfor(
      Match => "/Starting FICS session as ([a-zA-Z0-9]+)/",
      Match => "/\\S+ is already logged in/",
      Timeout => $OPEN_TIMEOUT
    );
    if ($match =~ /Starting FICS session as ([a-zA-Z0-9]+)/ ) {
      $username = $1;
    } else {
      die "Can not login as $BOT_HANDLE: $match\n";
    }

    print STDERR "info: successfully logged as guest $username\n";
  }

  $telnet->prompt("/^/");

  cmd_run("iset nowrap 1");
  cmd_run("set 1 unattended livePgnBot operated by $OPERATOR_HANDLE");
  cmd_run("set bell 0");
  cmd_run("set highlight 0");
  cmd_run("set tzone GMT");
  cmd_run("set ptime 1");
  cmd_run("set style 12");
  cmd_run("set seek 0");
  cmd_run("set shout 0");
  cmd_run("set cshout 0");
  cmd_run("set kibitz 0");
  cmd_run("set kiblevel 9000");
  cmd_run("set chanoff 1");
  cmd_run("set open 0");
  cmd_run("set gin 0");
  cmd_run("set pin 0");
  cmd_run("set mailmess 0");
  print STDERR "info: finished initialization\n" if $VERBOSE;

  my @startupCommands = read_startupCommands();
  foreach my $cmd (@startupCommands) {
    if ($cmd =~ /^\s*#/) {
      # skip comments
    } elsif ($cmd =~ /^\s*(\S+)\s*(.*)$/) {
      process_master_command($1, $2);
    } elsif ($cmd) {
      print STDERR "error: invalid startup command $cmd\n" if $VERBOSE;
    }
  }
  print STDERR "info: finished startup commands\n" if $VERBOSE;

  tell_operator("ready");
}

sub shut_down {
  $telnet->close;
}

sub main_loop {

  $telnet->prompt("/^/");
  $telnet->errmode(sub {
    return if $telnet->timed_out;
    my $msg = shift;
    die $msg;
  });

  while (1) {
    my $line = $telnet->getline(Timeout => $LINE_WAIT_TIMEOUT);
    if (($line) && ($line !~ /^$/)) {
      $line =~ s/[\r\n]*$//;
      $line =~ s/^[\r\n]*//;
      process_line($line);
    }

    ensure_alive();
    check_releay_results();
  }
}

eval {
  print STDERR "\n$0\n\n";
  setup();
  main_loop();
  shut_down();
  exit(1);
};
if ($@) {
  print STDERR "error: failed: $@\n";
  exit(1);
}
