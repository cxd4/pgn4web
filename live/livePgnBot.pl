
#  pgn4web javascript chessboard
#  copyright (C) 2009-2013 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

# livePgnBot script saving PGN data from live games on frechess.org
# code based on Marcin Kasperski's tutorial availabale at
# http://blog.mekk.waw.pl/series/how_to_write_fics_bot/


$| = 1;
use strict;
use Net::Telnet;
use File::Copy;
use POSIX qw(strftime);

our $FICS_HOST = "freechess.org";
our $FICS_PORT = 5000;

our $BOT_HANDLE = $ARGV[0] || "";
our $BOT_PASSWORD = $ARGV[1] || "";

our $OPERATOR_HANDLE = $ARGV[2] || "";

our $STARTUP_FILE_DEFAULT = "livePgnBot.ini";
our $STARTUP_FILE = $ARGV[3] || $STARTUP_FILE_DEFAULT;

if ($BOT_HANDLE eq "" || $OPERATOR_HANDLE eq "") {
  print "\n$0 BOT_HANDLE BOT_PASSWORD OPERATOR_HANDLE [STARTUP_FILE]\n\nBOT_HANDLE = handle for the bot account\nBOT_PASSWORD = password for the both account, use \"\" for a guest account\nOPERATOR_HANDLE = handle for the bot operator to send commands\nSTARTUP_FILE = filename for reading startup commands (default $STARTUP_FILE_DEFAULT)\n\nbot saving PGN data from live games on frechess.org\nmore help available from the operator account with \"tell BOT_HANDLE help\"\n\n";
  exit 0;
}


our $PGN_FILE = "live.pgn";

our $PGN_ARCHIVE = "";
our $archiveSelectFilter = "";

our $verbosity = 4; # info

our $PROTECT_LOGOUT_FREQ = 45 * 60;
our $CHECK_RELAY_FREQ = 3 * 60;
our $CHECK_RELAY_MIN_LAG = 20;
our $OPEN_TIMEOUT = 30;
our $LINE_WAIT_TIMEOUT = 60;
# $LINE_WAIT_TIMEOUT must be smaller than half of $PROTECT_LOGOUT_FREQ and $CHECK_RELAY_FREQ


our $telnet;
our $username;

our $starupTime = time();
our $roundsStartCount = 0;
our $gamesStartCount = 0;
our $pgnWriteCount = 0;
our $cmdRunCount = 0;
our $lineCount = 0;

our $last_cmd_time = 0;
our $last_check_relay_time = 0;
our $next_check_relay_time = 0;
our $short_relay_period = 0;
our $heartbeat_freq_hour = 8;
our $heartbeat_offset_hour = 5;

sub cmd_run {
  my ($cmd) = @_;
  log_terminal("debug: running ics command: $cmd");
  my $output = $telnet->cmd($cmd);
  $last_cmd_time = time();
  $cmdRunCount++;
}


our $lastPgn = "";

our $maxGamesNumDefault = 30; # frechess.org limit
our $maxGamesNum = $maxGamesNumDefault;
our $moreGamesThanMax;
our $prioritizedGames;
our $reportedNotFoundNonPrioritizedGame = 0;

our $relayOnline = 1;

our @games_num = ();
our @games_white = ();
our @games_black = ();
our @games_whiteElo = ();
our @games_blackElo = ();
our @games_movesText = ();
our @games_plyNum = ();
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
our $ignoreFilter = "";
our $prioritizeFilter = "";
our $autoPrioritize = "";
our $autoPrioritizeFilter = "";

our @currentRounds = ();

our $PGN_MEMORY = "";
our $memoryMaxGamesNum = $maxGamesNumDefault;
our @memory_games = ();
our @memory_games_sortkey = ();
our $memorySelectFilter = "";

sub reset_games {
  if ($PGN_ARCHIVE ne "") {
    for my $thisGameNum (@games_num) {
      archive_pgnGame($thisGameNum);
    }
  }
  cmd_run("follow");
  cmd_run("unobserve");
  $maxGamesNum = $maxGamesNumDefault;
  @games_num = ();
  @games_white = ();
  @games_black = ();
  @games_whiteElo = ();
  @games_blackElo = ();
  @games_movesText = ();
  @games_plyNum = ();
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
  $ignoreFilter = "";
  $prioritizeFilter = "";
  $autoPrioritize = "";
  $autoPrioritizeFilter = "";
  $reportedNotFoundNonPrioritizedGame = 0;

  @currentRounds = ();

  $archiveSelectFilter = "";

  $memoryMaxGamesNum = $maxGamesNumDefault;
  @memory_games = ();
  @memory_games_sortkey = ();
  $memorySelectFilter = "";

  log_terminal("debug: event/game all out");
  refresh_pgn();
}

sub headerForFilter {
  my ($event, $round, $white, $black) = @_;
  return "[Event \"$event\"][Round \"$round\"][White \"$white\"][Black \"$black\"]";
}

sub find_gameIndex {
  my ($thisGameNum) = @_;

  for (my $i=0; $i<=$#games_num; $i++) {
    if ((defined $games_num[$i]) && ($games_num[$i] == $thisGameNum)) {
      return $i;
    }
  }

  return -1;
}

sub save_game {

  if ($newGame_num < 0) {
    log_terminal("error: game not ready when saving");
    return;
  }

  my $thisGameIndex = find_gameIndex($newGame_num);
  if ($thisGameIndex < 0) {
    if ($#games_num + 1 >= $maxGamesNum) {
      if (remove_game(-1) < 0) {
        return;
      }
    }
    myAdd(\@games_num, $newGame_num);
    myAdd(\@games_white, $newGame_white);
    myAdd(\@games_black, $newGame_black);
    myAdd(\@games_whiteElo, $newGame_whiteElo);
    myAdd(\@games_blackElo, $newGame_blackElo);
    myAdd(\@games_movesText, $newGame_movesText);
    myAdd(\@games_plyNum, $#newGame_moves + 1);
    myAdd(\@games_result, $newGame_result);
    if ($autorelayMode == 0) {
      $GAMES_event[$newGame_num] = $newGame_event;
      $GAMES_site[$newGame_num] = $newGame_site;
      $GAMES_date[$newGame_num] = $newGame_date;
      $GAMES_round[$newGame_num] = $newGame_round;
      $GAMES_eco[$newGame_num] = "";
    }
    $gamesStartCount++;
    log_terminal("debug: game new $newGame_num: " . headerForFilter($GAMES_event[$newGame_num], $GAMES_round[$newGame_num], $newGame_white, $newGame_black));
    memory_purge_game($GAMES_event[$newGame_num], $GAMES_round[$newGame_num], $newGame_white, $newGame_black);
  } else {
    if (($games_white[$thisGameIndex] ne $newGame_white) || ($games_black[$thisGameIndex] ne $newGame_black) || ($games_whiteElo[$thisGameIndex] ne $newGame_whiteElo) || ($games_blackElo[$thisGameIndex] ne $newGame_blackElo)) {
      log_terminal("debug: game $newGame_num mismatch when saving");
    } else {
      $games_movesText[$thisGameIndex] = $newGame_movesText;
      $games_plyNum[$thisGameIndex] = $#newGame_moves + 1;
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
      log_terminal("error: missing game $thisGameNum when saving result");
    }
  } elsif ((! defined $games_result[$thisGameIndex]) || ($thisResult ne $games_result[$thisGameIndex])) {
    log_terminal("debug: game $thisGameNum result: $thisResult");
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
      my $foundNonPrioritizedGame = 0;
      for (my $i=0; ($i<=$#games_num) && ($foundNonPrioritizedGame==0); $i++) {
        if ((defined $games_num[$i]) && ($games_num[$i] ne "") && (headerForFilter($GAMES_event[$games_num[$i]], $GAMES_round[$games_num[$i]], $games_white[$i], $games_black[$i]) !~ /$prioritizeFilter/i)) {
          $thisGameIndex = $i;
          $foundNonPrioritizedGame = 1;
        }
      }
      if ($foundNonPrioritizedGame == 0) {
        if ($reportedNotFoundNonPrioritizedGame == 0) {
          log_terminal("warning: too many prioritized games");
          $reportedNotFoundNonPrioritizedGame = 1;
        }
        return -1;
      } else {
        $reportedNotFoundNonPrioritizedGame = 0;
      }
    }
    if ((defined $games_num[$thisGameIndex]) && ($games_num[$thisGameIndex] ne "")) {
      $thisGameNum = $games_num[$thisGameIndex];
    } else {
      log_terminal("warning: missing game for removing");
      return -1;
    }
  } else {
    $thisGameIndex = find_gameIndex($thisGameNum);
    if ($thisGameIndex < 0) {
      log_terminal("error: missing game $thisGameNum for removing");
      return -1;
    }
  }

  if ($PGN_ARCHIVE ne "") {
    archive_pgnGame($thisGameIndex);
  }

  if ($PGN_MEMORY ne "") {
    if (($games_result[$thisGameIndex] eq "1-0") || ($games_result[$thisGameIndex] eq "0-1") || ($games_result[$thisGameIndex] eq "1/2-1/2")) {
      memory_add_pgnGame($thisGameIndex);
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
  @games_plyNum = @games_plyNum[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  @games_result = @games_result[0..($thisGameIndex-1), ($thisGameIndex+1)..$thisMax];
  delete $GAMES_event[$thisGameNum];
  delete $GAMES_site[$thisGameNum];
  delete $GAMES_date[$thisGameNum];
  delete $GAMES_round[$thisGameNum];
  delete $GAMES_eco[$thisGameNum];
  delete $GAMES_timeLeft[$thisGameNum];
  log_terminal("debug: game out $thisGameNum");
  refresh_pgn();
  return $thisGameIndex;
}

our $timeOffset = 0;
sub log_terminal {
  if ($verbosity == 0) {
    return;
  }
  my ($msg) = @_;
  my $thisVerbosity = 1; # defaulting to alert
  if ($msg =~ /^fyi:/) {
    $thisVerbosity = 6;
  } elsif ($msg =~ /^debug:/) {
    $thisVerbosity = 5;
  } elsif ($msg =~ /^info:/) {
    $thisVerbosity = 4;
  } elsif ($msg =~ /^warning:/) {
    $thisVerbosity = 3;
  } elsif ($msg =~ /^error:/) {
    $thisVerbosity = 2;
  }
  if ($thisVerbosity <= $verbosity) {
    print(strftime("%Y-%m-%d %H:%M:%S UTC", gmtime(time() + $timeOffset)) . " " . $msg . "\n");
  }
}

sub tell_operator_and_log_terminal {
  my ($msg) = @_;
  log_terminal($msg);
  tell_operator($msg);
}

our $tellOperator = 0;
sub tell_operator {
  if ($tellOperator == 0) {
    return;
  }
  my ($msg) = @_;
  my @msgParts = $msg =~ /(.{1,195})/g;
  for (my $i=0; $i<=$#msgParts; $i++) {
    if ($i > 0) {
      $msgParts[$i] = ".." . $msgParts[$i];
    }
    if (($#msgParts > 0) && ($i < $#msgParts)) {
      $msgParts[$i] = $msgParts[$i] . "..";
    }
    cmd_run("xtell $OPERATOR_HANDLE! " . $msgParts[$i]);
  }
}


sub process_line {
  my ($line) = @_;

  $line =~ s/[\r\n ]+$//;
  $line =~ s/^[\r\n ]+//;
  return unless $line;

  if ($line =~ /^([^\s()]+)(\(\S+\))* tells you: \s*(\S+)\s*(.*)$/) {
    if ($1 eq $OPERATOR_HANDLE) {
      process_master_command($3, $4);
    } else {
      log_terminal("fyi: ignoring tell from user $1");
    }
  } elsif ($line =~ /^<12> (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+) (\S+)/) {
    my $thisNC = $9; # Next move Color
    my $thisGN = $16; # GameNum
    my $thisW = $17; # White
    my $thisB = $18; # Black
    my $thisWC = $24; # WhiteClock
    my $thisBC = $25; # BlackClock
    my $thisNN = $26; # Next move Number
    my $thisPM = $29; # PreviousMove
    my $thisGI = find_gameIndex($thisGN);
    if (($thisGI < 0) || (($thisW eq $games_white[$thisGI]) && ($thisB eq $games_black[$thisGI]))) {
      $GAMES_timeLeft[$thisGN] = "{ White Time: " . sec2time($thisWC) . " Black Time: " . sec2time($thisBC) . " }";
      my $thisPlyNum;
      if ($thisNC eq "B") {
        $thisPlyNum = (2 * $thisNN) - 1;
      } else {
        $thisPlyNum = 2 * ($thisNN - 1);
      }
      if (($thisGI >= 0) && ($thisPlyNum > 0) && (defined $games_plyNum[$thisGI]) && (($games_plyNum[$thisGI] == $thisPlyNum) || ($games_plyNum[$thisGI] == $thisPlyNum - 1))) {
        # for known games, if up to a new ply is added, just stores the new move and clock info from the style 12 string
        if ($games_plyNum[$thisGI] == $thisPlyNum - 1) {
          if ($thisPM ne "none") {
            log_terminal("debug: update for game $thisGN: $thisPM");
            if ($thisNC eq "B") {
              if ($thisNN % 5 == 1) {
                $games_movesText[$thisGI] .= "\n";
              } else {
                $games_movesText[$thisGI] .= " ";
              }
              $games_movesText[$thisGI] .= "$thisNN.";
            }
            $games_movesText[$thisGI] .= " $thisPM";
            $games_plyNum[$thisGI] = $thisPlyNum;
          } else {
            log_terminal("debug: unexpected $thisPM move for game $thisGN");
          }
        } else {
          log_terminal("debug: update for game $thisGN");
        }
        refresh_pgn();
      } else {
        # for new games, or if more than one ply is added, or if a ply is removed then the whole move list is fetched from the server
        log_terminal("debug: fetching all moves for game $thisGN");
        cmd_run("moves $thisGN");
      }
    } else {
      log_terminal("debug: game $thisGN mismatch when receiving");
    }
  } elsif ($line =~ /^{Game (\d+) [^}]*} (\S+)/) {
    save_result($1, $2, 1); # from observed game
  } elsif ($line =~ /^:There .* in the (.*)/) {
    $autorelayEvent = $1;
    $autorelayEvent =~ s/[\[\]"]/'/g;
    $autorelayRound = "";
    if ($autorelayEvent =~ /(.*)\s+Round\s+(\d+)\s+Game\s+(\d+)/) {
      $autorelayRound = "$2.$3";
      $autorelayEvent = $1;
      $autorelayEvent =~ s/[\s-]+$//g;
    } elsif ($autorelayEvent =~ /(.*)\s+(Round|Game)\s+(\d+)/) {
      $autorelayRound = $3;
      $autorelayEvent = $1;
      $autorelayEvent =~ s/[\s-]+$//g;
    }
    declareRelayOnline();
  } elsif ($line =~ /^:(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/) {
    my $thisGameNum = $1;
    my $thisGameWhite = $2;
    my $thisGameBlack = $3;
    my $thisGameResult = $4;
    my $thisGameEco = $5;
    my $thisHeaderForFilter = headerForFilter($autorelayEvent, $autorelayRound, $thisGameWhite, $thisGameBlack);
    if (($autorelayMode == 1) && ((($ignoreFilter ne "") && ($thisHeaderForFilter =~ /$ignoreFilter/i)) || ($thisGameResult eq "abort"))) {
      my $skipReason;
      if ($thisGameResult eq "abort") {
        $skipReason = "aborted";
      } else {
        $skipReason = "ignored";
      }
      if (find_gameIndex($thisGameNum) != -1) {
        if (remove_game($thisGameNum) != -1) {
          $moreGamesThanMax = 0;
          $prioritizedGames = 0;
        }
        log_terminal("debug: removed $skipReason game $thisGameNum $thisHeaderForFilter");
      } else {
        log_terminal("debug: skipped $skipReason game $thisGameNum $thisHeaderForFilter");
      }
    } else {
      if ($autorelayMode == 1) {
        $GAMES_event[$thisGameNum] = $autorelayEvent;
        $GAMES_site[$thisGameNum] = $newGame_site;
        $GAMES_date[$thisGameNum] = $newGame_date;
        $GAMES_round[$thisGameNum] = $autorelayRound;
        $GAMES_eco[$thisGameNum] = $thisGameEco;
        $GAMES_autorelayRunning[$thisGameNum] = 1;
        if (($autoPrioritize ne "") && ($thisHeaderForFilter =~ /$autoPrioritize/i)) {
          (my $autorelayEventFilter = $autorelayEvent) =~ s/[^\w\s-]/./g;
          if ($autoPrioritizeFilter !~ /(\||^)$autorelayEventFilter(\||$)/) {
            if ($autoPrioritizeFilter eq "") {
              $autoPrioritizeFilter = $autorelayEventFilter;
            } else {
              $autoPrioritizeFilter .= "|" . $autorelayEventFilter;
            }
          }
        }
      }
      if (find_gameIndex($thisGameNum) != -1) {
        if ($thisGameResult ne "*") {
          save_result($thisGameNum, $thisGameResult, 0); # from relay list
        }
      } else {
        if ($autorelayMode == 1) {
          if ($#games_num + 1 >= $maxGamesNum) {
            if ($moreGamesThanMax == 0) {
              log_terminal("debug: more relayed games than max=$maxGamesNum");
              $moreGamesThanMax = 1;
            }
          }
          if ($moreGamesThanMax == 0) {
            cmd_run("observe $thisGameNum");
          } elsif (($prioritizeFilter ne "") && ($thisHeaderForFilter =~ /$prioritizeFilter/i)) {
            if ($prioritizedGames == 0) {
              log_terminal("debug: prioritized game $thisHeaderForFilter");
              $prioritizedGames = 1;
            }
            if (remove_game(-1) != -1) {
              cmd_run("observe $thisGameNum");
            }
          }
        }
      }
    }
  } elsif ($line =~ /^Game \d+: Game clock paused\.$/) {
    if ($relayMode == 1) {
      force_next_check_relay_time();
    }
  } elsif ($line =~ /^:Type "tell relay next" for more\.$/) {
    cmd_run("xtell relay! next");
  } elsif ($line =~ /^:There are no games in progress\.$/) {
    declareRelayOnline();
  } elsif ($line =~ /^((\d\d.\d\d_)?fics%)?\s*relay is not logged in\.$/) {
    declareRelayOffline();
  } elsif ($line =~ /^[\s*]*ANNOUNCEMENT[\s*]*from relay: FICS is relaying/) {
    if (($autorelayMode == 1) && ($#games_num < 0)) {
      force_next_check_relay_time();
    }
  } elsif ($newGame_num < 0) {
    if ($line =~ /^Movelist for game (\d+):/) {
      reset_newGame();
      $newGame_num = $1;
    } elsif ($line !~ /^\s*((\d\d.\d\d_)?fics%|:)?\s*$/) {
      log_terminal("fyi: ignored line: $line");
    }
  } else {
    if ($line =~ /^(\w+)\s+\((\S+)\)\s+vs\.\s+(\w+)\s+\((\S+)\).*/) {
      $newGame_white = $1;
      $newGame_whiteElo = $2;
      $newGame_black = $3;
      $newGame_blackElo = $4;
    } elsif ($line =~ /(.*) initial time: \d+ minutes.*increment: \d+/) {
      our $gameType = $1;
      if (!($gameType =~ /(standard|blitz|lightning|^Unrated untimed match,$)/)) {
        log_terminal("warning: unsupported game $newGame_num: $gameType");
        delete $GAMES_timeLeft[$newGame_num];
        delete $GAMES_event[$newGame_num];
        delete $GAMES_site[$newGame_num];
        delete $GAMES_date[$newGame_num];
        delete $GAMES_round[$newGame_num];
        delete $GAMES_eco[$newGame_num];
        cmd_run("unobserve $newGame_num");
        tell_operator_and_log_terminal("debug: unsupported game $newGame_num: $gameType");
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
    } elsif ($line !~ /^\s*((\d\d.\d\d_)?fics%|:)\s*$/) {
      log_terminal("fyi: ignored line: $line");
    }
  }
  $lineCount++;
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

  if ($t =~ /^(\d+):(\d+):(\d+):(\d+)$/) {
    return 86400 * $1 + $2 * 3600 + $3 * 60 + $4;
  } elsif ($t =~ /^(\d+):(\d+):(\d+)$/) {
    return $1 * 3600 + $2 * 60 + $3;
  } elsif ($t =~ /^(\d+):(\d+)$/) {
    return $1* 60 + $2;
  } elsif ($t =~ /^\d+$/) {
    return $1;
  } else {
    log_terminal("error: time2sec($t)");
    return 0;
  }
}

sub sec2time {
  my ($t) = @_;
  my ($sec, $min, $hr, $day);

  if ($t =~ /^\d+$/) {
    $sec = $t % 60;
    $t = ($t - $sec) / 60;
    $min = $t % 60;
    $t = ($t - $min) / 60;
    $hr = $t % 24;
    if ($t < 24) {
      return sprintf("%d:%02d:%02d", $hr, $min, $sec);
    } else {
      $day = ($t - $hr) / 24;
      return sprintf("%d:%02d:%02d:%02d", $day, $hr, $min, $sec);
    }
  } elsif ($t =~ /^-/) {
    return "0:00:00";
  } else {
    log_terminal("error: sec2time($t)");
    return 0;
  }
}


our $gameRunning;

sub save_pgnGame {
  my ($i) = @_;
  my ($thisPgn, $thisResult, $thisWhite, $thisBlack, $thisWhiteTitle, $thisBlackTitle);

  $thisPgn = "";
  if ((defined $games_num[$i]) && (defined $GAMES_event[$games_num[$i]]) && (defined $GAMES_site[$games_num[$i]]) && (defined $GAMES_date[$games_num[$i]]) && (defined $GAMES_round[$games_num[$i]]) && (defined $GAMES_eco[$games_num[$i]]) && (defined $GAMES_timeLeft[$games_num[$i]])) {

    if (($followMode == 1) && ($i == 0)) {
      $thisResult = "*";
    } else {
      $thisResult = $games_result[$i];
    }
    if ($thisResult eq "*") {
      $gameRunning = 1;
    }
    if (($relayMode == 1) && ($games_white[$i] =~ /^(W?[GIFC]M)([A-Z].*)$/)) {
      $thisWhiteTitle = $1;
      $thisWhite = $2;
    } else {
      $thisWhiteTitle = "";
      $thisWhite = $games_white[$i];
    }
    if (($relayMode == 1) && ($games_black[$i] =~ /^(W?[GIFC]M)([A-Z].*)$/)) {
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
    $thisPgn .= "[Event \"" . $GAMES_event[$games_num[$i]] . "\"]\n";
    $thisPgn .= "[Site \"" . $GAMES_site[$games_num[$i]] . "\"]\n";
    $thisPgn .= "[Date \"" . $GAMES_date[$games_num[$i]] . "\"]\n";
    $thisPgn .= "[Round \"" . $GAMES_round[$games_num[$i]] . "\"]\n";
    $thisPgn .= "[White \"" . $thisWhite . "\"]\n";
    $thisPgn .= "[Black \"" . $thisBlack . "\"]\n";
    $thisPgn .= "[Result \"" . $thisResult . "\"]\n";
    if ($games_whiteElo[$i] =~ /^\d+$/) {
      $thisPgn .= "[WhiteElo \"" . $games_whiteElo[$i] . "\"]\n";
    }
    if ($games_blackElo[$i] =~ /^\d+$/) {
      $thisPgn .= "[BlackElo \"" . $games_blackElo[$i] . "\"]\n";
    }
    if ($thisWhiteTitle ne "") {
      $thisPgn .= "[WhiteTitle \"" . $thisWhiteTitle . "\"]\n";
    }
    if ($thisBlackTitle ne "") {
      $thisPgn .= "[BlackTitle \"" . $thisBlackTitle . "\"]\n";
    }
    if ((defined $GAMES_eco[$games_num[$i]]) && ($GAMES_eco[$games_num[$i]] ne "")) {
      $thisPgn .= "[ECO \"" . $GAMES_eco[$games_num[$i]] . "\"]\n";
    }
    $thisPgn .= $games_movesText[$i];
    $thisPgn .= "\n$GAMES_timeLeft[$games_num[$i]]";
    if ($games_result[$i] =~ /^[012\/\*-]+$/) {
      $thisPgn .= " $games_result[$i]";
    }
    $thisPgn .= "\n\n";

  }

  return $thisPgn;
}

sub refresh_pgn {
  my $pgn = "";
  $gameRunning = 0;

  my @ordered = sort {
    if (($autorelayMode == 1) && ($prioritizeFilter ne "")) {
      my $aPrioritized = (headerForFilter($GAMES_event[$games_num[$a]], $GAMES_round[$games_num[$a]], $games_white[$a], $games_black[$a]) =~ /$prioritizeFilter/i);
      my $bPrioritized = (headerForFilter($GAMES_event[$games_num[$b]], $GAMES_round[$games_num[$b]], $games_white[$b], $games_black[$b]) =~ /$prioritizeFilter/i);
      if ($aPrioritized && !$bPrioritized) { return -1; }
      if (!$aPrioritized && $bPrioritized) { return 1; }
    }
    if (lc($GAMES_event[$games_num[$a]]) gt lc($GAMES_event[$games_num[$b]])) { return 1; }
    if (lc($GAMES_event[$games_num[$a]]) lt lc($GAMES_event[$games_num[$b]])) { return -1; }
    # my $aElo = 0;
    # if ($games_whiteElo[$a] =~ /^[0-9]+$/) { $aElo += $games_whiteElo[$a]; }
    # if ($games_blackElo[$a] =~ /^[0-9]+$/) { $aElo += $games_blackElo[$a]; }
    # my $bElo = 0;
    # if ($games_whiteElo[$b] =~ /^[0-9]+$/) { $bElo += $games_whiteElo[$b]; }
    # if ($games_blackElo[$b] =~ /^[0-9]+$/) { $bElo += $games_blackElo[$b]; }
    # if ($aElo > $bElo) { return -1; }
    # if ($aElo < $bElo) { return 1; }
    return $a <=> $b;
  } (0 .. ($maxGamesNum - 1));

  my $lastPgnNum = 0;
  my $newPgn;
  for (my $i=0; $i<$maxGamesNum; $i++) {
    $newPgn = save_pgnGame($ordered[$i]);
    if ($newPgn ne "") { $lastPgnNum++; }
    $pgn .= $newPgn;
  }

  if (($pgn eq "") || ($autorelayMode == 1)) { # consider || (($autorelayMode == 1) && ($gameRunning == 0))
    $pgn .= temp_pgn();
  }

  if ($pgn ne $lastPgn) {
    if (open(my $thisFile, ">$PGN_FILE")) {
      print $thisFile $pgn;
      close($thisFile);
      $pgnWriteCount++;
      $lastPgn = $pgn;
      refresh_memory($lastPgnNum);
    } else {
      log_terminal("error: failed writing $PGN_FILE");
    }
  }

  if ($autorelayMode == 1) {
    log_rounds();
  }
}

sub temp_pgn {
  return "[Event \"$newGame_event\"]\n" . "[Site \"$newGame_site\"]\n" . "[Date \"$newGame_date\"]\n" . "[Round \"$newGame_round\"]\n" . "[White \"\"]\n" . "[Black \"\"]\n" . "[Result \"*\"]\n\n*\n\n";
}

sub archive_pgnGame {
  my ($i) = @_;

  if ($PGN_ARCHIVE ne "") {
    my $pgn = save_pgnGame($i);
    if (($pgn ne "") && (($archiveSelectFilter eq "") || ($pgn =~ /$archiveSelectFilter/is))) {
      if (open(my $thisFile, ">>$PGN_ARCHIVE")) {
        print $thisFile $pgn;
        close($thisFile);
        log_terminal("debug: archive add game $games_num[$i]: " . headerForFilter($GAMES_event[$games_num[$i]], $GAMES_round[$games_num[$i]], $games_white[$i], $games_black[$i]));
      } else {
        log_terminal("error: failed writing $PGN_ARCHIVE");
      }
    }
  }
}

sub refresh_memory {
  my ($lastPgnNum) = @_;

  if ($PGN_MEMORY ne "") {
    my $memoryPgn = $lastPgn;
    my $memory_games_howmany = $memoryMaxGamesNum - $lastPgnNum;
    if ($memory_games_howmany > $#memory_games + 1) { $memory_games_howmany = $#memory_games + 1; }
    if ($memory_games_howmany > 0) {
      my @selected_memory_games = (0 .. ($memory_games_howmany - 1));
      if ($autorelayMode == 1) {
        @selected_memory_games = sort {
          if (lc($memory_games_sortkey[$a]) gt lc($memory_games_sortkey[$b])) { return 1; }
          if (lc($memory_games_sortkey[$a]) lt lc($memory_games_sortkey[$b])) { return -1; }
          return $b <=> $a;
        } @selected_memory_games;
      }
      for (my $i=0; $i<=$#selected_memory_games; $i++) {
        $memoryPgn .= $memory_games[$selected_memory_games[$i]];
      }
    }
    if ($memoryPgn ne "") {
      if (open(my $thisFile, ">$PGN_MEMORY")) {
        print $thisFile $memoryPgn;
        close($thisFile);
      } else {
        log_terminal("error: failed writing $PGN_MEMORY");
      }
    }
  }
}

sub memory_add_pgnGame {
  my ($i) = @_;

  if ($PGN_MEMORY ne "") {
    my $pgn = save_pgnGame($i);
    if (($memorySelectFilter eq "") || ($pgn =~ /$memorySelectFilter/is)) {
      if (($#memory_games + 1) >= $memoryMaxGamesNum) {
        pop(@memory_games);
        pop(@memory_games_sortkey);
      }
      unshift(@memory_games, $pgn);
      my $newSortkey = $GAMES_event[$games_num[$i]];
      if ($GAMES_round[$games_num[$i]] ne "") {
        $newSortkey .= " + Round ";
        if (length($GAMES_round[$games_num[$i]]) == 1) { $newSortkey .= "0"; }
        $newSortkey .= $GAMES_round[$games_num[$i]];
      }
      unshift(@memory_games_sortkey, $newSortkey);
      log_terminal("debug: memory add game $games_num[$i]: " . headerForFilter($GAMES_event[$games_num[$i]], $GAMES_round[$games_num[$i]], $games_white[$i], $games_black[$i]));
    }
  }
}

sub memory_purge_round {
  my ($thisEvent) = @_;
  my $purgedRound = 0;

  if ($PGN_MEMORY ne "") {
    $thisEvent =~ s/[\[\]"]/'/g;
    my $thisRound = "";
    if ($thisEvent =~ /(.*)\s+(Round|Game)\s+(\d+)/) {
      $thisRound = $3;
      $thisEvent = $1;
      $thisEvent =~ s/[\s-]+$//g;
    }
    $thisEvent =~ s/[^\s\w\d]/./g;
    $thisRound =~ s/[^\s\w\d]/./g;
    my $pattern = '\[Event "' . $thisEvent . '"\].*\[Round "' . $thisRound . '"\]';
    my $logged = 0;
    for (my $i=$#memory_games; $i>=0; $i--) {
      if ($memory_games[$i] =~ /$pattern/s) {
        @memory_games = @memory_games[0..($i-1), ($i+1)..$#memory_games];
        @memory_games_sortkey = @memory_games_sortkey[0..($i-1), ($i+1)..$#memory_games_sortkey];
        if ($logged == 0) {
          log_terminal('debug: memory purged event: [Event "' . $thisEvent . '"][Round "' . $thisRound . '"]');
          $logged = 1;
        }
        $purgedRound++;
      }
    }
  }
  return $purgedRound;
}

sub memory_purge_game {
  my ($thisEvent, $thisRound, $thisWhite, $thisBlack) = @_;
  my $purgedGame = 0;

  if ($PGN_MEMORY ne "") {
    if ($relayMode == 1) {
      if ($thisWhite =~ /^(W?[GIFC]M)([A-Z].*)$/) {
        $thisWhite = $2;
      }
      if ($thisBlack =~ /^(W?[GIFC]M)([A-Z].*)$/) {
        $thisBlack = $2;
      }
      $thisWhite =~ s/(?<=.)([A-Z])/ $1/g;
      $thisBlack =~ s/(?<=.)([A-Z])/ $1/g;
    }
    my $pattern = '\[Event "' . $thisEvent . '"\].*\[Round "' . $thisRound . '"\].*\[White "' . $thisWhite . '"\].*\[Black "' . $thisBlack . '"\]';
    for (my $i=$#memory_games; $i>=0; $i--) {
      if ($memory_games[$i] =~ /$pattern/s) {
        @memory_games = @memory_games[0..($i-1), ($i+1)..$#memory_games];
        @memory_games_sortkey = @memory_games_sortkey[0..($i-1), ($i+1)..$#memory_games_sortkey];
        log_terminal('debug: memory purged game: [Event "' . $thisEvent . '"][Round "' . $thisRound . '"][White "' . $thisWhite . '"][Black "' . $thisBlack . '"]');
        $purgedGame++;
      }
    }
  }
  return $purgedGame;
}

sub memory_load {
  if ($PGN_MEMORY eq "") {
    tell_operator_and_log_terminal("error: memory load requires a valid memory file");
  } else {
    my @candidate_memory_games = ();
    @memory_games = ();
    @memory_games_sortkey = ();
    my $newPgn;
    my $newSortkey;
    if (open(my $thisFile,  "<",  $PGN_MEMORY)) {
      my @lines = <$thisFile>;
      @candidate_memory_games = (join("", @lines) =~ /((?:\[\s*\w+\s*"[^"]*"\s*\]\s*)+[^[]+)/g);
      foreach (@candidate_memory_games) {
        if (($_ =~ /\[Result "(1-0|1\/2-1\/2|0-1)"\]/i) && (($memorySelectFilter eq "") || ($_ =~ /$memorySelectFilter/is))) {
          $newPgn = $_;
          unshift(@memory_games, $newPgn);
          $newSortkey = "";
          if ($newPgn =~ /\[Event "([^"]*)"\]/i) { $newSortkey = $1; }
          if ($newPgn =~ /\[Round "([^"]+)"\]/i) { $newSortkey .= " - Round " . $1; }
          unshift(@memory_games_sortkey, $newSortkey);
        }
      }
    }
    for (my $g=0; $g<=$#games_num; $g++) {
      memory_purge_game($GAMES_event[$games_num[$g]], $GAMES_round[$games_num[$g]], $games_white[$g], $games_black[$g]);
    }
    if ($#memory_games > $memoryMaxGamesNum - 1) {
      @memory_games = @memory_games[0..($memoryMaxGamesNum - 1)];
      @memory_games_sortkey = @memory_games_sortkey[0..($memoryMaxGamesNum - 1)];
    }
    if ($autorelayMode == 1) {
      my @newSortkey = ();
      for (my $m=$#memory_games_sortkey; $m>=0; $m--) {
        unless (($memory_games_sortkey[$m] ~~ @newSortkey) || ($memory_games_sortkey[$m] ~~ @currentRounds)) {
          log_terminal("info: event mem: $memory_games_sortkey[$m]");
          push(@newSortkey, $memory_games_sortkey[$m]);
        }
        $memory_games_sortkey[$m] =~ s/ - Round / + Round /;
        if ($memory_games_sortkey[$m] =~ /^(.* Round )(\S)$/) { $memory_games_sortkey[$m] = $1 . "0" . $2; }
      }
    }
    log_terminal("debug: memory load: " . ($#memory_games + 1));
  }
}


sub log_rounds {
  my @newRounds = ();
  my ($i, $thisRound);

  foreach (@games_num) {
    if (defined $GAMES_event[$_]) {
      $thisRound = $GAMES_event[$_];
      if ((defined $GAMES_round[$_]) && ($GAMES_round[$_] ne "")) {
        $thisRound .= " - Round " . $GAMES_round[$_];
      }
      unless ($thisRound ~~ @newRounds) {
        push(@newRounds, $thisRound);
      }
    }
  }

  foreach (@currentRounds) {
    unless ($_ ~~ @newRounds) {
      log_terminal("info: event out: $_");
    }
  }

  foreach (@newRounds) {
    unless ($_ ~~ @currentRounds) {
      log_terminal("info: event new: $_");
      $roundsStartCount++;
      memory_purge_round($_);
    }
  }

  @currentRounds = @newRounds;
}


our @master_commands = ();
our @master_commands_helptext = ();

sub add_master_command {
  my ($command, $helptext) = @_;
  push (@master_commands, $command);
  push (@master_commands_helptext, $helptext);
}

add_master_command ("archive", "archive [filename.pgn] (to get/set the filename for archiving PGN data)");
add_master_command ("archiveselect", "archiveselect [regexp|\"\"] (to get/set the regular expression to select games for archiving PGN data)");
add_master_command ("autoprioritize", "autoprioritize [regexp|\"\"] (to get/set the regular expression to prioritize entire events during autorelay; has precedence over prioritize)");
add_master_command ("autorelay", "autorelay [0|1] (to automatically observe all relayed games)");
add_master_command ("config", "config (to get config info)");
add_master_command ("date", "date [????.??.???|\"\"] (to get/set the PGN header tag date)");
add_master_command ("empty", "empty [1] (to save empty PGN data as placeholder file)");
add_master_command ("event", "event [string|\"\"] (to get/set the PGN header tag event)");
add_master_command ("file", "file [filename.pgn] (to get/set the filename for live PGN data)");
add_master_command ("follow", "follow [0|handle|/s|/b|/l] (to follow the user with given handle, /s for the best standard game, /b for the best blitz game, /l for the best lightning game, 0 to disable follow mode)");
add_master_command ("forget", "forget [game number list, such as: 12 34 56 ..] (to eliminate given past games from PGN data)");
add_master_command ("games", "games (to get list of observed games)");
add_master_command ("heartbeat", "heartbeat [frequency offset] (to get/set the timing of heartbeat log messages, in UTC hours)");
add_master_command ("help", "help [command] (to get commands help)");
add_master_command ("history", "history (to get history info)");
add_master_command ("ics", "ics [server command] (to run a custom command on the ics server)");
add_master_command ("ignore", "ignore [regexp|\"\"] (to get/set the regular expression to ignore events/players from the PGN header during autorelay; has precedence over prioritize; use ^(?:(?!regexp).)+\$ for negative lookup)");
add_master_command ("log", "log [string] (to print a string on the log terminal)");
add_master_command ("max", "max [number] (to get/set the maximum number of games for the PGN data)");
add_master_command ("memory", "memory [filename.pgn] (to get/set the filename for the PGN memory data)");
add_master_command ("memoryload", "memoryload [1] (to load PGN memroy data from memory file)");
add_master_command ("memorymax", "memorymax [number] (to get/set the maximum number of games for the PGN memory data)");
add_master_command ("memorypurgegame", "memorypurgegame [\"event\" \"round\" \"white\" \"black\"] (to purge a game from the PGN memory data)");
add_master_command ("memorypurgeround", "memorypurgeround [\"event\" \"round\"] (to purge a round from the PGN memory data)");
add_master_command ("memoryselect", "memoryselect [regexp|\"\"] (to get/set the regular expression to select games for the PGN memory data)");
add_master_command ("observe", "observe [game number list, such as: 12 34 56 ..] (to observe given games)");
add_master_command ("prioritize", "prioritize [regexp|\"\"] (to get/set the regular expression to prioritize events/players from the PGN header during autorelay; might be overruled by ignore)");
add_master_command ("quit", "quit [number] (to quit from the ics server, returning the given exit value)");
add_master_command ("relay", "relay [0|game number list, such as: 12 34 56 ..] (to observe given games from an event relay, 0 to disable relay mode)");
add_master_command ("reset", "reset [1] (to reset observed/followed games list and setting)");
add_master_command ("round", "round [string|\"\"] (to get/set the PGN header tag round)");
add_master_command ("site", "site [string|\"\"] (to get/set the PGN header tag site)");
add_master_command ("startup", "startup [command list, separated by semicolon] (to get/set startup commands file)");
add_master_command ("timeoffset", "timeoffset [[+|-]seconds] (to get/set the offset correcting the UTC time value)");
add_master_command ("verbosity", "verbosity [0-6] (to get/set log verbosity: 0=none, 1=alert, 2=error, 3=warning, 4=info, 5=debug, 6=fyi)");

sub detect_command {
  my ($command) = @_;
  my $guessedCommand = "";
  foreach (@master_commands) {
    if ($_ eq $command) {
      return $_;
    }
    if ($_ =~ /^$command/) {
      if ($guessedCommand ne "") {
        return "ambiguous command: $command";
      } else {
        $guessedCommand = $_;
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
    tell_operator("error: $command");
  } elsif ($command eq "archive") {
    if ($parameters =~ /^([\w\d\/\\.+=_-]*|"")$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $PGN_ARCHIVE = $parameters;
      }
      my $fileInfoText = "archive=$PGN_ARCHIVE";
      log_terminal("info: $fileInfoText");
      if ($PGN_ARCHIVE ne "") {
        my @fileInfo = stat($PGN_ARCHIVE);
        if (defined $fileInfo[9]) {
          $fileInfoText .= " modified=" . strftime("%Y-%m-%d %H:%M:%S UTC", gmtime($fileInfo[9]));
        }
        if (defined $fileInfo[7]) {
          $fileInfoText .= " size=$fileInfo[7]";
        }
        if (defined $fileInfo[2]) {
          $fileInfoText .= sprintf(" permissions=%04o", $fileInfo[2] & 07777);
        }
      }
      tell_operator($fileInfoText);
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "archiveselect") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        eval {
          "test" =~ /$parameters/;
          if ($parameters eq "\"\"") { $parameters = ""; }
          $archiveSelectFilter = $parameters;
          log_terminal("info: archiveselect=$archiveSelectFilter");
          1;
        } or do {
          tell_operator("error: invalid regular expression $parameters");
        };
      }
      tell_operator("archiveselect=$archiveSelectFilter");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "autoprioritize") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        eval {
          "test" =~ /$parameters/;
          if ($parameters eq "\"\"") { $parameters = ""; }
          if ($parameters ne $autoPrioritize) {
            $short_relay_period = 1;
          }
          $autoPrioritize = $parameters;
          if ($relayMode == 1) {
            force_next_check_relay_time();
          }
          $reportedNotFoundNonPrioritizedGame = 0;
          if ($autoPrioritize ne "") {
            log_terminal("info: autoprioritize=$autoPrioritize");
          } else {
            log_terminal("info: autoprioritize=$autoPrioritize prioritize=$prioritizeFilter");
          }
          1;
        } or do {
          tell_operator("error: invalid regular expression $parameters");
        };
      }
      tell_operator("autoprioritize=$autoPrioritize prioritize=$prioritizeFilter");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "autorelay") {
    if ($parameters =~ /^(0|1)$/) {
      if ($parameters == 0) {
        $autorelayMode = 0;
        @GAMES_autorelayRunning = ();
      } else {
        if ($followMode == 0) {
          $autorelayMode = 1;
          $relayMode = 1;
          force_next_check_relay_time();
        } else {
          tell_operator("error: disable follow before activating autorelay");
        }
      }
    } elsif ($parameters !~ /^\??$/) {
      tell_operator("error: invalid $command parameter");
    }
    tell_operator("autorelay=$autorelayMode");
    if (($autorelayMode == 1) && ($relayOnline == 0)) {
      tell_operator("warning: ics relay offline");
    }
  } elsif ($command eq "config") {
    tell_operator("config: max=$maxGamesNum file=$PGN_FILE archive=$PGN_ARCHIVE memory=$PGN_MEMORY memorymax=$memoryMaxGamesNum follow=$followMode relay=$relayMode autorelay=$autorelayMode ignore=$ignoreFilter autoprioritize=$autoPrioritize prioritize=$prioritizeFilter archiveselect=$archiveSelectFilter memoryselect=$memorySelectFilter event=$newGame_event site=$newGame_site date=$newGame_date round=$newGame_round heartbeat=$heartbeat_freq_hour/$heartbeat_offset_hour timeoffset=$timeOffset verbosity=$verbosity");
  } elsif ($command eq "date") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $newGame_date = $parameters;
      }
      tell_operator("date=$newGame_date");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "empty") {
    if ($parameters eq "1") {
      $lastPgn = temp_pgn();
      if (open(my $thisFile, ">$PGN_FILE")) {
        print $thisFile $lastPgn;
        close($thisFile);
      } else {
        log_terminal("error: failed writing $PGN_FILE");
      }
      if ($PGN_MEMORY ne "") { refresh_memory(0); }
      log_terminal("info: saved empty PGN data as placeholder file");
      tell_operator("OK $command");
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "event") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $newGame_event = $parameters;
      }
      tell_operator("event=$newGame_event");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "file") {
    if ($parameters =~ /^[\w\d\/\\.+=_-]*$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        $PGN_FILE = $parameters;
      }
      my $fileInfoText = "file=$PGN_FILE";
      log_terminal("info: $fileInfoText");
      my @fileInfo = stat($PGN_FILE);
      if (defined $fileInfo[9]) {
        $fileInfoText .= " modified=" . strftime("%Y-%m-%d %H:%M:%S UTC", gmtime($fileInfo[9]));
      }
      if (defined $fileInfo[7]) {
        $fileInfoText .= " size=$fileInfo[7]";
      }
      if (defined $fileInfo[2]) {
        $fileInfoText .= sprintf(" permissions=%04o", $fileInfo[2] & 07777);
      }
      tell_operator($fileInfoText);
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "follow") {
    if ($parameters =~ /^([a-zA-Z]+$|\/s|\/b|\/l)/) {
      if ($relayMode == 0) {
        $followMode = 1;
        cmd_run("follow $parameters");
        $followLast = $parameters;
      } else {
        tell_operator("error: disable relay before activating follow");
      }
    } elsif ($parameters =~ /^(0|1)$/) {
      if (($parameters == 0) || ($relayMode == 0)) {
        $followMode = $parameters;
        if ($parameters == 0) {
          $followLast = "";
          cmd_run("follow");
        }
      } else {
        tell_operator("error: disable relay before activating follow");
      }
    } elsif ($parameters ne "") {
      tell_operator("error: invalid $command parameter");
    }
    tell_operator("follow=$followMode last=$followLast");
  } elsif ($command eq "forget") {
    if ($parameters ne "") {
      my @theseGames = split(" ", $parameters);
      foreach (@theseGames) {
        if ($_ =~ /\d+/) {
          if (remove_game($_) < 0) {
            tell_operator("error: game $_ not found");
          }
        } else {
          tell_operator("error: invalid game $_");
        }
      }
      tell_operator("OK $command");
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "games") {
    my $roundsList = "";
    if ($autorelayMode == 1) {
      $roundsList = " rounds(" . ($#currentRounds + 1) . ")=";
      if ($#currentRounds > -1) {
        $roundsList .= "\"" . join("\", \"", (sort { lc($a) cmp lc($b) } @currentRounds)) . "\"";
      }
    }
    my $memoryList = "";
    if ($PGN_MEMORY ne "") {
      $memoryList = " memory(" . ($#memory_games + 1) . "/$memoryMaxGamesNum)";
    }
    tell_operator("games(" . ($#games_num + 1) . "/$maxGamesNum)=" . gameList() . $roundsList . $memoryList);
  } elsif ($command eq "heartbeat") {
    if (($parameters =~ /^(\d+(\.\d*)?)\s+(\d+(\.\d*)?)$/) && ($1 > 0) && ($3 < $1)) {
      $heartbeat_freq_hour = $1;
      $heartbeat_offset_hour = $3;
      update_heartbeat_time();
      tell_operator("OK $command");
    } elsif ($parameters eq "") {
      tell_operator("heartbeat: frequency=" . $heartbeat_freq_hour . " offset=" . $heartbeat_offset_hour);
    } else {
      tell_operator("error: invalid $command parameters");
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
      tell_operator("commands: " . join(", ", @master_commands));
    }
  } elsif ($command eq "history") {
    my $secTime = time() - $starupTime;
    my $hourTime = $secTime / 3600;
    my $dayTime = $hourTime / 24;
    my $thisInfo = sprintf("history: uptime=%s rounds=%d (r/d=%d) games=%d (g/d=%d)", sec2time($secTime), $roundsStartCount, $roundsStartCount / $dayTime, $gamesStartCount, $gamesStartCount / $dayTime);
    if ($verbosity >= 5) {
      $thisInfo = sprintf("%s pgn=%d (p/h=%d) cmd=%d (c/h=%d) lines=%d (l/h=%d)", $thisInfo, $pgnWriteCount, $pgnWriteCount / $hourTime, $cmdRunCount, $cmdRunCount / $hourTime, $lineCount, $lineCount / $hourTime);
    }
    $thisInfo = sprintf("%s %s", $thisInfo, strftime("now=%Y-%m-%d %H:%M:%S UTC", gmtime($starupTime + $secTime + $timeOffset)));
    tell_operator($thisInfo);
  } elsif ($command eq "ics") {
    if ($parameters !~ /^\??$/) {
      cmd_run($parameters);
      tell_operator("OK $command");
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "ignore") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        eval {
          "test" =~ /$parameters/;
          if ($parameters eq "\"\"") { $parameters = ""; }
          $ignoreFilter = $parameters;
          if ($relayMode == 1) {
            force_next_check_relay_time();
          }
          $reportedNotFoundNonPrioritizedGame = 0;
          log_terminal("info: ignore=$ignoreFilter");
          1;
        } or do {
          tell_operator("error: invalid regular expression $parameters");
        };
      }
      tell_operator("ignore=$ignoreFilter");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "log") {
    if ($parameters ne "") {
      log_terminal($parameters);
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "max") {
    if ($parameters =~ /^([1-9]\d*)?$/) {
      if ($parameters ne "") {
        if ($parameters > $maxGamesNumDefault) {
          tell_operator_and_log_terminal("warning: max number of games set above frechess.org observe limit of $maxGamesNumDefault");
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
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memory") {
    if ($parameters =~ /^([\w\d\/\\.+=_-]*|"")$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        if ($parameters eq "\"\"") {
           $parameters = "";
           @memory_games = ();
           @memory_games_sortkey = ();
        }
        $PGN_MEMORY = $parameters;
      }
      my $fileInfoText = "memory=$PGN_MEMORY";
      log_terminal("info: $fileInfoText");
      my @fileInfo = stat($PGN_MEMORY);
      if (defined $fileInfo[9]) {
        $fileInfoText .= " modified=" . strftime("%Y-%m-%d %H:%M:%S UTC", gmtime($fileInfo[9]));
      }
      if (defined $fileInfo[7]) {
        $fileInfoText .= " size=$fileInfo[7]";
      }
      if (defined $fileInfo[2]) {
        $fileInfoText .= sprintf(" permissions=%04o", $fileInfo[2] & 07777);
      }
      tell_operator($fileInfoText);
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memoryload") {
    if ($parameters eq "1") {
      memory_load();
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memorymax") {
    if ($parameters =~ /^([1-9]\d*)?$/) {
      if ($parameters ne "") {
        if ($parameters < $#memory_games + 1) {
          @memory_games = @memory_games[0..($parameters - 1)];
          @memory_games_sortkey = @memory_games_sortkey[0..($parameters - 1)];
        }
        $memoryMaxGamesNum = $parameters;
      }
      tell_operator("memorymax=$memoryMaxGamesNum");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memorypurgegame") {
    if ($parameters =~ /^\s*"(.*?)"\s*"(.*?)"\s*"(.*?)"\s*"(.*?)"\s*$/) {
      if (memory_purge_game($1, $2, $3, $4) > 0) {
        refresh_memory();
        tell_operator("purged memory game");
      } else {
        tell_operator("no memory game found for purge");
      }
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memorypurgeround") {
    if ($parameters =~ /^\s*"(.*?)"\s*"(.*?)"\s*$/) {
      my $forPurge = $1;
      if ($2 ne "") { $forPurge .= " - Round $2"; }
      if (memory_purge_round($forPurge) > 0) {
        refresh_memory();
        tell_operator("purged memory round");
      } else {
        tell_operator("no memory round found for purge");
      }
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memoryselect") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        eval {
          "test" =~ /$parameters/;
          if ($parameters eq "\"\"") { $parameters = ""; }
          $memorySelectFilter = $parameters;
          log_terminal("info: memoryselect=$memorySelectFilter");
          1;
        } or do {
          tell_operator("error: invalid regular expression $parameters");
        };
      }
      tell_operator("memoryselect=$memorySelectFilter");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "observe") {
    if ($parameters ne "") {
      observe($parameters);
      tell_operator("OK $command");
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "prioritize") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        eval {
          "test" =~ /$parameters/;
          if ($parameters eq "\"\"") { $parameters = ""; }
          $prioritizeFilter = $parameters;
          if ($relayMode == 1) {
            force_next_check_relay_time();
          }
          $reportedNotFoundNonPrioritizedGame = 0;
          log_terminal("info: prioritize=$prioritizeFilter");
          1;
        } or do {
          tell_operator("error: invalid regular expression $parameters");
        };
      }
      tell_operator("prioritize=$prioritizeFilter");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "quit") {
    if ($parameters =~ /^\d+$/) {
      tell_operator("OK $command($parameters)");
      log_terminal("info: quit with exit value $parameters");
      cmd_run("quit");
      exit($parameters);
    } elsif ($parameters =~ /^\??$/) {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
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
          tell_operator("error: disable follow before activating relay");
        }
      }
    } elsif ($parameters ne "") {
      tell_operator("error: invalid $command parameter");
    } else {
      tell_operator("relay=$relayMode");
    }
    if (($relayMode == 1) && ($relayOnline == 0)) {
      tell_operator("warning: ics relay offline");
    }
  } elsif ($command eq "reset") {
    if ($parameters eq "1") {
      reset_games();
      tell_operator("OK $command");
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "round") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $newGame_round = $parameters;
      }
      tell_operator("round=$newGame_round");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "site") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $newGame_site = $parameters;
      }
      tell_operator("site=$newGame_site");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "startup") {
    if ($parameters) {
      write_startupCommands(split(";", $parameters));
    }
    my $startupString = join("; ", read_startupCommands());
    $startupString =~ s/[\n\r]+//g;
    tell_operator("startup($STARTUP_FILE)=$startupString");
  } elsif ($command eq "timeoffset") {
    if ($parameters =~ /^([+-]?\d+)?$/) {
      if ($parameters ne "") {
        $timeOffset = $parameters;
        update_heartbeat_time();
      }
      tell_operator_and_log_terminal("alert: timeoffset=$timeOffset");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "verbosity") {
    if ($parameters =~ /^[0-6]?$/) {
      if ($parameters ne "") {
        $verbosity = $parameters;
      }
      tell_operator_and_log_terminal("alert: verbosity=$verbosity");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } else {
    tell_operator("error: invalid command: $command $parameters");
  }
}

sub observe {
  my ($gamesList) = @_;
  my @theseGames = split(" ", $gamesList);
  foreach (@theseGames) {
    if ($_ =~ /\d+/) {
      if (find_gameIndex($_) == -1) {
        cmd_run("observe $_");
      } else {
        tell_operator_and_log_terminal("debug: game $_ already observed");
      }
    } else {
      tell_operator("error: invalid game $_");
    }
  }
}

sub gameList {
  my $outputStr = "";
  for (my $i=0; $i<=$#games_num; $i++) {
    if (defined $games_num[$i]) {
      if ($outputStr ne "") { $outputStr .= " "; }
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
  } else {
    log_terminal("error: failed reading $STARTUP_FILE");
  }
  return @commandList;
}

sub write_startupCommands {
  my @commandList = @_;

  if (!copy("$STARTUP_FILE", "$STARTUP_FILE" . ".bak")) {
    tell_operator_and_log_terminal("error: failed backup, startup commands file $STARTUP_FILE not updated");
    return;
  }

  if (open(CMDFILE, ">" . $STARTUP_FILE)) {
    foreach my $cmd (@commandList) {
      $cmd =~ s/^\s*//;
      print CMDFILE $cmd . "\n";
    }
    close(CMDFILE);
    log_terminal("info: startup commands file $STARTUP_FILE written");
  } else {
    tell_operator_and_log_terminal("error: failed writing $STARTUP_FILE");
  }
}


sub declareRelayOffline() {
  if ($relayOnline == 1) {
    $relayOnline = 0;
    tell_operator_and_log_terminal("warning: ics relay offline");
  }
}

sub declareRelayOnline() {
  if ($relayOnline == 0) {
    $relayOnline = 1;
    tell_operator_and_log_terminal("warning: ics relay back online");
  }
}

sub xtell_relay_listgames {
  $moreGamesThanMax = 0;
  $prioritizedGames = 0;
  if ($autoPrioritize ne "") {
    if ($prioritizeFilter ne $autoPrioritizeFilter) {
      $prioritizeFilter = $autoPrioritizeFilter;
      log_terminal("info: prioritize=$prioritizeFilter");
    }
  }
  $autoPrioritizeFilter = "";
  cmd_run("xtell relay! listgames");
}

sub check_relay_results {
  if (($relayMode == 1) && (time() - $next_check_relay_time > 0)) {
    xtell_relay_listgames();
    $last_check_relay_time = time();
    if ($short_relay_period == 1) {
      $next_check_relay_time = $last_check_relay_time + $CHECK_RELAY_MIN_LAG;
      $short_relay_period = 0;
    } else {
      $next_check_relay_time = $last_check_relay_time + $CHECK_RELAY_FREQ;
    }
    if ($autorelayMode == 1) {
      my @gameNumForRemoval = ();
      my $thisGameNum;
      for $thisGameNum (@games_num) {
        if (! defined $GAMES_autorelayRunning[$thisGameNum]) {
          push(@gameNumForRemoval, $thisGameNum);
        }
      }
      for $thisGameNum (@gameNumForRemoval) {
        remove_game($thisGameNum);
      }
      @GAMES_autorelayRunning = ();
    }
  }
}

sub force_next_check_relay_time {
  $next_check_relay_time = time();
  if ($next_check_relay_time - $last_check_relay_time < $CHECK_RELAY_MIN_LAG) {
    $next_check_relay_time = $last_check_relay_time + $CHECK_RELAY_MIN_LAG;
  }
}

sub ensure_alive {
  if (time() - $last_cmd_time > $PROTECT_LOGOUT_FREQ) {
    cmd_run("date");
  }
}


our $next_heartbeat_time;
update_heartbeat_time();

sub heartbeat {
  if (time() + $timeOffset > $next_heartbeat_time) {
    my $thisInfo = sprintf("info: heartbeat: uptime=%s rounds=%d/%d games=%d/%d/%d", sec2time(time() - $starupTime), ($#currentRounds + 1), $roundsStartCount, ($#games_num + 1), $maxGamesNum, $gamesStartCount);
    if ($PGN_MEMORY ne "") {
      $thisInfo = sprintf("%s memory=%d/%d", $thisInfo, ($#memory_games + 1), $memoryMaxGamesNum);
    }
    if ($verbosity >= 5) {
      $thisInfo = sprintf("%s pgn=%d cmd=%d lines=%d", $thisInfo, $pgnWriteCount, $cmdRunCount, $lineCount);
    }
    tell_operator_and_log_terminal($thisInfo);
    update_heartbeat_time();
  }
}

sub update_heartbeat_time {
  my $thisTime = time() + $timeOffset;
  $next_heartbeat_time = $thisTime - ($thisTime % ($heartbeat_freq_hour * 3600)) + ($heartbeat_offset_hour * 3600);
  if ($next_heartbeat_time < $thisTime) {
    $next_heartbeat_time += ($heartbeat_freq_hour * 3600);
  }
}


sub setup {

  $telnet = new Net::Telnet(
    Timeout => $OPEN_TIMEOUT,
    Binmode => 1,
  );

  $telnet->errmode(sub {
    my $msg = shift;
    log_terminal("error: " . $msg);
    exit 1;
  });

  $telnet->open(
    Host => $FICS_HOST,
    Port => $FICS_PORT,
  );

  log_terminal("debug: connected to $FICS_HOST");

  if ($BOT_PASSWORD) {

    $telnet->login(Name => $BOT_HANDLE, Password => $BOT_PASSWORD);
    $username = $BOT_HANDLE;
    log_terminal("debug: logged in as user $BOT_HANDLE");

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
        log_terminal("error: failed login as $BOT_HANDLE: $1");
        exit 1;
      }
      log_terminal("fyi: ignored line: $line\n");
    }

    my($pre, $match) = $telnet->waitfor(
      Match => "/Starting FICS session as ([a-zA-Z0-9]+)/",
      Match => "/\\S+ is already logged in/",
      Timeout => $OPEN_TIMEOUT
    );
    if ($match =~ /Starting FICS session as ([a-zA-Z0-9]+)/ ) {
      $username = $1;
    } else {
      log_terminal("error: failed login as $BOT_HANDLE: $match");
      exit 1;
    }

    log_terminal("debug: logged in as guest $username");
  }

  $telnet->prompt("/^/");

  cmd_run("iset nowrap 1");
  cmd_run("iset defprompt 1");
  cmd_run("iset startpos 1");
  cmd_run("set width 240");
  cmd_run("set height 240");
  cmd_run("set echo 0");
  cmd_run("set bell 0");
  cmd_run("set seek 0");
  cmd_run("set shout 0");
  cmd_run("set cshout 0");
  cmd_run("set kibitz 0");
  cmd_run("set kiblevel 9000");
  cmd_run("set chanoff 1");
  cmd_run("set open 0");
  cmd_run("set style 12");
  cmd_run("set tolerance 5");
  log_terminal("debug: initialization done");

  my @startupCommands = read_startupCommands();
  foreach my $cmd (@startupCommands) {
    if ($cmd =~ /^\s*#/) {
      # skip comments
    } elsif ($cmd =~ /^\s*(\S+)\s*(.*)$/) {
      process_master_command($1, $2);
    } elsif ($cmd !~ /^\s*$/) {
      log_terminal("error: invalid startup command $cmd");
    }
  }
  log_terminal("debug: startup commands done");

  $tellOperator = 1;
  tell_operator("info: ready");
}

sub shut_down {
  $telnet->close;
}

sub main_loop {

  $telnet->prompt("/^/");
  $telnet->errmode(sub {
    return if $telnet->timed_out;
    my $msg = shift;
    log_terminal("error: " . $msg);
    exit 1;
  });

  while (1) {
    my $line = $telnet->getline(Timeout => $LINE_WAIT_TIMEOUT);
    if (($line) && ($line !~ /^$/)) {
      $line =~ s/[\r\n]*$//;
      $line =~ s/^[\r\n]*//;
      process_line($line);
    }

    ensure_alive();
    check_relay_results();
    heartbeat();
  }
}

sub handleTERM {
  log_terminal("warning: received TERM signal");
  exit(0);
}
$SIG{TERM}=\&handleTERM;

sub handleHUP {
  log_terminal("warning: received HUP signal");
  exit(0);
}
$SIG{HUP}=\&handleHUP;

sub handleINT {
  log_terminal("warning: received INT signal");
  exit(1);
}
$SIG{INT}=\&handleINT;

sub handleUSR1 {
  log_terminal("warning: received USR1 signal");
  exit(2);
}
$SIG{USR1}=\&handleUSR1;

sub handleUSR2 {
  log_terminal("warning: received USR2 signal");
  exit(3);
}
$SIG{USR2}=\&handleUSR2;

eval {
  log_terminal("info: starting $0");
  setup();
  main_loop();
  shut_down();
  exit(1);
};
if ($@) {
  log_terminal("error: failed: $@");
  exit(1);
}

