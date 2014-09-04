
#  pgn4web javascript chessboard
#  copyright (C) 2009-2014 Paolo Casaschi
#  see README file and http://pgn4web.casaschi.net
#  for credits, license and more details

# livePgnBot script saving PGN data from live games on frechess.org
# code based on Marcin Kasperski's tutorial availabale at
# http://blog.mekk.waw.pl/series/how_to_write_fics_bot/

# warning: this is experimental code, developed and tested only for
# a very specific purpose within the pgn4web project; this is not
# intended for general availability as part of the pgn4web project


$| = 1;
use strict;
use Net::Telnet;
use File::Copy;
use POSIX qw(strftime);
use POSIX qw(tzset);
use Safe;

our $FICS_HOST = "freechess.org";
our $FICS_PORT = 5000;

our $BOT_HANDLE = $ARGV[0] || "";
our $BOT_PASSWORD = $ARGV[1] || "";

our $OPERATOR_HANDLE = $ARGV[2] || "";

our $STARTUP_FILE_DEFAULT = "livePgnBot.ini";
our $STARTUP_FILE = $ARGV[3] || $STARTUP_FILE_DEFAULT;

our $FLAGS = $ARGV[4] || "";
our $TEST_FLAG = ($FLAGS =~ /\bTEST\b/);

if ($BOT_HANDLE eq "" || $OPERATOR_HANDLE eq "") {
  print("\n$0 BOT_HANDLE BOT_PASSWORD OPERATOR_HANDLE [STARTUP_FILE]\n\nBOT_HANDLE = handle for the bot account\nBOT_PASSWORD = password for the both account, use \"\" for a guest account\nOPERATOR_HANDLE = handle for the bot operator to send commands\nSTARTUP_FILE = filename for reading startup commands (default $STARTUP_FILE_DEFAULT)\n\nbot saving PGN data from live games on frechess.org\nmore help available from the operator account with \"tell BOT_HANDLE help\"\n\n");
  myExit(0);
}


our $PGN_LIVE = "";

our $PGN_ARCHIVE = "";
our $archiveSelectFilter = "";

our $verbosity = 4; # info

our $PROTECT_LOGOUT_FREQ = 45 * 60;
our $CHECK_RELAY_FREQ = 3 * 60;
our $CHECK_RELAY_MIN_LAG = 20;
our $MEMORY_LOAD_CHECK_FREQ = 3 * $CHECK_RELAY_FREQ;

our $OPEN_TIMEOUT = 30;
our $LINE_WAIT_TIMEOUT = 30;
# $LINE_WAIT_TIMEOUT must be smaller than half of $PROTECT_LOGOUT_FREQ and $CHECK_RELAY_FREQ


our $telnet;
our $username;

sub setup_time {
  $ENV{TZ} = 'UTC';
  tzset();
}

our $startupTime = time();
our $timeOffset = 0;

sub o_time() {
  my ($t) = @_;
  return ($t || time()) + $timeOffset;
}

sub o_gmtime {
  my ($t) = @_;
  return gmtime(($t || time()) + $timeOffset);
}

sub simpleStringCrc {
  my ($s) = @_;
  my $t = 0;
  foreach (unpack("W*", $s)) { $t += $_ }
  return $t % 1000;
}

our $roundsStartCount = 0;
our $gamesStartCount = 0;
our $pgnWriteCount = 0;
our $cmdRunCount = 0;
our $lineCount = 0;

our $last_cmd_time = 0;
our $last_check_relay_time = 0;
our $next_check_relay_time = 0;
our $short_relay_period = 1;
our $heartbeat_freq_hour = 8;
our $heartbeat_offset_hour = 5;

sub cmd_run {
  my ($cmd) = @_;
  log_terminal("debug: ics command input: $cmd");
  my $output = $telnet->cmd($cmd);
  $last_cmd_time = time();
  $cmdRunCount++;
}

our $lastPgn = "";
our $lastPgnNum = 0;
our $lastPgnForce = "% force updating PGN data";
our $lastPgnRefresh = 0;

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
our @GAMES_sortkey = ();
our @GAMES_forgetTag = ();
our @GAMES_timeLeft = ();
our @GAMES_headerForFilter = ();

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

our $archive_date = "";
our $memory_date = "";

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
our $prioritizeOnly = 0;

our $EloIgnoreString = "";
our $EloAutoprioritizeString = "";
our $safevalElo = new Safe;
$safevalElo->share($EloIgnoreString, $EloAutoprioritizeString);

our $eventAutocorrectRegexp = "";
our $eventAutocorrectString = "";
our $safevalEvent = new Safe;
$safevalEvent->share($eventAutocorrectString);

our $roundAutocorrectRegexp = "";
our $roundAutocorrectString = "";
our $safevalRound = new Safe;
$safevalRound->share($roundAutocorrectString);

our $placeholderGame = "auto";
our $placeholder_date = "";
our $placeholder_result = "*";
our $placeholderPgnNum = "?";

our $roundReverse = 0;
our $roundReverseAgtB = $roundReverse ? -1 : 1;
our $roundReverseAltB = -$roundReverseAgtB;

our @currentRounds = ();

our $PGN_MEMORY = "";
our $memoryMaxGamesNum = $maxGamesNumDefault;
our $memoryMaxGamesNumBuffer = 2;
our @memory_games = ();
our @memory_games_sortkey = ();
our $memorySelectFilter = "";
our $memoryAutopurgeEvent = 0;

our $PGN_TOP = "";
our $lastTopPgn = "";


sub reset_live {
  for my $thisGameNum (@games_num) {
    remove_game($thisGameNum);
  }
  cmd_run("follow");
  cmd_run("unobserve");
  $lastPgn = "";
  $lastPgnNum = 0;
  $lastPgnRefresh = 0;
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
  @GAMES_sortkey = ();
  @GAMES_forgetTag = ();
  @GAMES_timeLeft = ();
  @GAMES_headerForFilter = ();
  @GAMES_autorelayRunning = ();
  @currentRounds = ();
  $reportedNotFoundNonPrioritizedGame = 0;
  $lastTopPgn = $lastPgnForce;
  log_terminal("debug: live games reset");
}

sub reset_memory {
  @memory_games = ();
  @memory_games_sortkey = ();
  log_terminal("debug: memory games reset");
}


sub reset_games {
  reset_live();
  reset_memory();
}

sub reset_config {
  $maxGamesNum = $maxGamesNumDefault;
  $newGame_event = "";
  $newGame_site = "";
  $newGame_date = "";
  $newGame_round = "";
  $archive_date = "";
  $memory_date = "";
  $followMode = 0;
  $followLast = "";
  $relayMode = 0;
  $autorelayMode = 0;
  $eventAutocorrectRegexp = "";
  $eventAutocorrectString = "";
  $roundAutocorrectRegexp = "";
  $roundAutocorrectString = "";
  $placeholderGame = "auto";
  $placeholder_date = "";
  $placeholder_result = "*";
  $placeholderPgnNum = "?";
  $roundReverse = 0;
  $roundReverseAgtB = $roundReverse ? -1 : 1;
  $roundReverseAltB = -$roundReverseAgtB;
  $ignoreFilter = "";
  $prioritizeFilter = "";
  $autoPrioritize = "";
  $autoPrioritizeFilter = "";
  $prioritizeOnly = 0;
  $EloIgnoreString = "";
  $EloAutoprioritizeString = "";
  $archiveSelectFilter = "";
  $memoryMaxGamesNum = $maxGamesNumDefault;
  $memorySelectFilter = "";
  $memoryAutopurgeEvent = 0;
  log_terminal("debug: configuration reset");
}

sub reset_all {
  reset_games();
  reset_config();
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

sub eventRound {
  my ($thisEvent, $thisRound) = @_;
  $thisRound =~ s/\b(\d)\b/00$1/g;
  $thisRound =~ s/\b(\d\d)\b/0$1/g;
  $thisRound =~ s/(^|\.)(?=[^\dr]|$)/$1r/g;
  return "\"$thisEvent\" \"$thisRound\"";
}

# sprintf_eventRound is for output display only, since in some unexpected occurrences it might not reverse accurately eventRound
sub sprintf_eventRound {
  my ($thisEventRound) = @_;
  $thisEventRound =~ s/([".])(0+(?=\d+[".])|r)/$1/g;
  return $thisEventRound;
}

sub cleanup_failed_save_game {
  my ($gameNum) = @_;
  if ($autorelayMode == 1) {
    delete $GAMES_event[$gameNum];
    delete $GAMES_site[$gameNum];
    delete $GAMES_date[$gameNum];
    delete $GAMES_round[$gameNum];
    delete $GAMES_eco[$gameNum];
    delete $GAMES_sortkey[$gameNum];
    delete $GAMES_forgetTag[$gameNum];
    delete $GAMES_timeLeft[$gameNum];
    delete $GAMES_headerForFilter[$gameNum];
    delete $GAMES_autorelayRunning[$gameNum];
  }
}

sub save_game {

  if ($newGame_num < 0) {
    log_terminal("error: game not ready when saving");
    return;
  }

  my $thisGameIndex = find_gameIndex($newGame_num);
  if ($thisGameIndex < 0) {
    my $thisHeaderForFilter = headerForFilter($autorelayMode ? $GAMES_event[$newGame_num] : $newGame_event, $autorelayMode ? $GAMES_round[$newGame_num] : $newGame_round, $newGame_white, $newGame_black);
    if (($autorelayMode == 1) && (($ignoreFilter ne "") && ($thisHeaderForFilter =~ /$ignoreFilter/i))) {
      log_terminal("debug: save requested for ignored game $newGame_num: $thisHeaderForFilter");
      cmd_run("unobserve $newGame_num");
      cleanup_failed_save_game($newGame_num);
      return;
    }
    if (($autorelayMode == 1) && ($prioritizeOnly == 1) && (($prioritizeFilter eq "") || ($thisHeaderForFilter !~ /$prioritizeFilter/i))) {
      log_terminal("debug: while prioritizeonly=$prioritizeOnly, save requested for non prioritized game $newGame_num: $thisHeaderForFilter");
      cmd_run("unobserve $newGame_num");
      cleanup_failed_save_game($newGame_num);
      return;
    }
    if ($#games_num + 1 >= $maxGamesNum) {
      if (($autorelayMode == 1) && ($thisHeaderForFilter !~ /$prioritizeFilter/i)) {
        log_terminal("debug: too many games for adding non prioritized game $newGame_num: $thisHeaderForFilter");
        cmd_run("unobserve $newGame_num");
        cleanup_failed_save_game($newGame_num);
        return;
      }
      if (remove_game(-1) < 0) {
        log_terminal("debug: failed removing game for new game $newGame_num: $thisHeaderForFilter");
        cmd_run("unobserve $newGame_num");
        cleanup_failed_save_game($newGame_num);
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
      $GAMES_date[$newGame_num] = strftime($newGame_date, o_gmtime());
      $GAMES_round[$newGame_num] = $newGame_round;
      $GAMES_eco[$newGame_num] = "";
      $GAMES_sortkey[$newGame_num] = eventRound($newGame_event, $newGame_round);
      $GAMES_forgetTag[$newGame_num] = sprintf("%012d%03d", o_time(),  simpleStringCrc($GAMES_sortkey[$newGame_num]));
      $GAMES_headerForFilter[$newGame_num] = headerForFilter($newGame_event, $newGame_round, $newGame_white, $newGame_black);
    }
    $gamesStartCount++;
    log_terminal("debug: game new $newGame_num: $thisHeaderForFilter");
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
  delete $GAMES_sortkey[$thisGameNum];
  delete $GAMES_forgetTag[$thisGameNum];
  delete $GAMES_timeLeft[$thisGameNum];
  delete $GAMES_headerForFilter[$thisGameNum];
  log_terminal("debug: game out $thisGameNum");
  refresh_pgn();
  return $thisGameIndex;
}

sub event_autocorrect {
  my ($event) = @_;
  if (($eventAutocorrectRegexp) && ($event =~ /$eventAutocorrectRegexp/i)) {
    my $oldEvent = $event;
    $event =~ s/$eventAutocorrectRegexp/$safevalEvent->reval($eventAutocorrectString)/egi;
    if ($@) { log_terminal("warning: event autocorrect failed"); }
    $event =~ s/\s+/ /g;
    $event =~ s/^\s|\s$//g;
    log_terminal("debug: event autocorrected: \"$oldEvent\" \"$event\"");
  }
  return $event;
}

sub round_autocorrect {
  my ($round, $event) = @_;
  if (($roundAutocorrectRegexp) && ($round =~ /$roundAutocorrectRegexp/i)) {
    my $oldRound = $round;
    ${$safevalRound->varglob("event")} = $event;
    $round =~ s/$roundAutocorrectRegexp/$safevalRound->reval($roundAutocorrectString)/egi;
    if ($@) { log_terminal("warning: round autocorrect failed"); }
    $round =~ s/\s+/ /g;
    $round =~ s/^\s|\s$//g;
    log_terminal("debug: round autocorrected: \"$oldRound\" \"$round\"");
  }
  return $round;
}

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
    print(strftime("%Y-%m-%d %H:%M:%S", o_gmtime()) . " " . $msg . "\n");
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

  if ($line =~ /^([^\s()]+)(?:\(\S+\))* tells you: \s*(.*)$/) {
    if ($1 eq $OPERATOR_HANDLE) {
      my $thisTell = $2;
      if ($thisTell =~ /^([^\s=]+)=?\s*(.*)$/) {
        process_master_command($1, $2);
      } else {
        tell_operator("error: invalid tell: $thisTell");
      }
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
    if ($autorelayEvent =~ /(\b(Game\s+\d+|Last\s+Game)\b.*){2,}/i) {
      $autorelayRound = "?";
      $autorelayEvent =~ s/Game\s+\d+|Last\s+Game/ /g;
    } elsif ($autorelayEvent =~ /^(.*)\bGame\s+(\d+)\b(.*)$/i) {
      $autorelayRound = $2;
      $autorelayEvent = $1 . " " . $3;
    } elsif ($autorelayEvent =~ /^(.*)\bLast\s+Game\b(.*)$/i) {
      $autorelayRound = "#";
      $autorelayEvent = $1 . " " . $2;
    }
    if ($autorelayEvent =~ /(\b(Round\s+\d+|Last\s+Round)\b.*){2,}/i) {
      $autorelayRound = $autorelayRound ne "" ? "?." . $autorelayRound : "?";
      $autorelayEvent =~ s/Round\s+\d+|Last\s+Round/ /g;
    } elsif ($autorelayEvent =~ /^(.*)\bRound\s+(\d+)\b(.*)$/i) {
      $autorelayRound = $autorelayRound ne "" ? $2 . "." . $autorelayRound : $2;
      $autorelayEvent = $1 . " " . $3;
    } elsif ($autorelayEvent =~ /^(.*)\bLast\s+Round\b(.*)$/i) {
      $autorelayRound = $autorelayRound ne "" ? "#." . $autorelayRound : "#";
      $autorelayEvent = $1 . " " . $2;
    }
    $autorelayEvent =~ s/^\s+|[\s-]+$//g;
    $autorelayEvent =~ s/\s+/ /g;
    if ($eventAutocorrectRegexp) { $autorelayEvent = event_autocorrect($autorelayEvent); }
    if ($autorelayRound eq "") { $autorelayRound = "-"; }
    if ($roundAutocorrectRegexp) { $autorelayRound = round_autocorrect($autorelayRound, $autorelayEvent); }
    declareRelayOnline();
  } elsif ($line =~ /^:(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/) {
    my $thisGameNum = $1;
    my $thisGameWhite = $2;
    my $thisGameBlack = $3;
    my $thisGameResult = $4;
    my $thisGameEco = $5;
    if ((find_gameIndex($thisGameNum) != -1) && ($thisGameResult ne "*")) {
      save_result($thisGameNum, $thisGameResult, 0); # from relay list
    }
    if ($autorelayMode == 1) {
      my $thisHeaderForFilter = headerForFilter($autorelayEvent, $autorelayRound, $thisGameWhite, $thisGameBlack);
      if ((($ignoreFilter ne "") && ($thisHeaderForFilter =~ /$ignoreFilter/i)) || ($thisGameResult eq "abort")) {
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
        $GAMES_event[$thisGameNum] = $autorelayEvent;
        $GAMES_site[$thisGameNum] = $newGame_site;
        $GAMES_date[$thisGameNum] = strftime($newGame_date, o_gmtime());
        $GAMES_round[$thisGameNum] = $autorelayRound;
        $GAMES_eco[$thisGameNum] = $thisGameEco;
        $GAMES_sortkey[$thisGameNum] = eventRound($autorelayEvent, $autorelayRound);
        $GAMES_forgetTag[$thisGameNum] = sprintf("%012d%03d", o_time(),  simpleStringCrc($GAMES_sortkey[$thisGameNum]));
        $GAMES_headerForFilter[$thisGameNum] = $thisHeaderForFilter;
        $GAMES_autorelayRunning[$thisGameNum] = 1;
        if (($autoPrioritize ne "") && ($thisHeaderForFilter =~ /$autoPrioritize/i)) {
          autoprioritize_add_event($autorelayEvent);
        }
        my $thisGameIndex = find_gameIndex($thisGameNum);
        if ($thisGameIndex == -1) {
          cmd_run("games $thisGameNum");
        } else {
          if (Elo_eval_autoprioritize($games_whiteElo[$thisGameIndex], $games_blackElo[$thisGameIndex]) == 1) {
            autoprioritize_add_event($GAMES_event[$thisGameNum]);
          }
        }
      }
    }
  } elsif ($line =~ /^\s*(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+\[.*\].*$/) {
    my $thisGameNum = $1;
    my $thisWhiteElo = $2;
    my $thisShortWhite = $3;
    my $thisBlackElo = $4;
    my $thisShortBlack = $5;
    if ($autorelayMode == 1) {
      if ((defined $GAMES_event[$thisGameNum]) && (defined $GAMES_site[$thisGameNum]) && (defined $GAMES_date[$thisGameNum]) && (defined $GAMES_round[$thisGameNum]) && (defined $GAMES_eco[$thisGameNum]) && (defined $GAMES_sortkey[$thisGameNum]) && (defined $GAMES_forgetTag[$thisGameNum]) && (defined $GAMES_headerForFilter[$thisGameNum])) {
        if ($GAMES_headerForFilter[$thisGameNum] =~ /White\s+"$thisShortWhite.*Black\s+"$thisShortBlack/) {
          if (Elo_eval_ignore($thisWhiteElo, $thisBlackElo) == 0) {
            if (Elo_eval_autoprioritize($thisWhiteElo, $thisBlackElo) == 1) {
              autoprioritize_add_event($GAMES_event[$thisGameNum]);
            }
            if ($#games_num + 1 >= $maxGamesNum) {
              if ($moreGamesThanMax == 0) {
                log_terminal("debug: more relayed games than max=$maxGamesNum");
                $moreGamesThanMax = 1;
              }
            }
            if ($moreGamesThanMax == 0) {
              cmd_run("observe $thisGameNum");
            } elsif (($prioritizeFilter ne "") && ($GAMES_headerForFilter[$thisGameNum] =~ /$prioritizeFilter/i)) {
              if ($prioritizedGames == 0) {
                log_terminal("debug: prioritized game $GAMES_headerForFilter[$thisGameNum]");
                $prioritizedGames = 1;
              }
              if (remove_game(-1) != -1) {
                cmd_run("observe $thisGameNum");
              } else {
                cleanup_failed_save_game($thisGameNum);
              }
            } else {
              cleanup_failed_save_game($thisGameNum);
            }
          } else {
            log_terminal("debug: skipped ignored game $thisGameNum " . $GAMES_headerForFilter[$thisGameNum] . "[WhiteElo \"$thisWhiteElo\"][BlackElo \"$thisBlackElo\"]");
            cleanup_failed_save_game($thisGameNum);
          }
        } else {
          log_terminal("debug: game $thisGameNum mismatch when checking Elo");
          cleanup_failed_save_game($thisGameNum);
        }
      } else {
        log_terminal("debug: missing game $thisGameNum when checking Elo");
      }
    } else {
      log_terminal("debug: Elo check while autorelayMode=$autorelayMode");
    }
  } elsif ($line =~ /^Game \d+: Game clock paused\.$/) {
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
        cleanup_failed_save_game($newGame_num);
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


sub autoprioritize_add_event {
  my ($thisEvent) = @_;
  (my $autorelayEventFilter = $thisEvent) =~ s/[^\w\s-]/./g;
  if ($autoPrioritizeFilter !~ /(\||^)$autorelayEventFilter(\||$)/) {
    if ($autoPrioritizeFilter eq "") {
      $autoPrioritizeFilter = $autorelayEventFilter;
    } else {
      $autoPrioritizeFilter .= "|" . $autorelayEventFilter;
    }
  }
}


sub update_safevalElo {
  my ($whiteElo, $blackElo) = @_;
  $whiteElo =~ s/\D//g;
  if ($whiteElo eq "") { $whiteElo = 0; }
  $blackElo =~ s/\D//g;
  if ($blackElo eq "") { $blackElo = 0; }
  ${$safevalElo->varglob("minElo")} = $whiteElo < $blackElo ? $whiteElo : $blackElo;
  ${$safevalElo->varglob("maxElo")} = $whiteElo > $blackElo ? $whiteElo : $blackElo;
  ${$safevalElo->varglob("avgElo")} = ($whiteElo + $blackElo) / 2;
}

sub Elo_eval_ignore {
  my ($whiteElo, $blackElo) = @_;
  if ($EloIgnoreString eq "") {
    return 0;
  } else {
    update_safevalElo($whiteElo, $blackElo);
    my $retVal = $safevalElo->reval($EloIgnoreString);
    if ($@) {
      log_terminal("error: invalid eloignore=$EloIgnoreString");
      $retVal = 0;
    } elsif ($retVal != 1) { $retVal = 0; }
    return $retVal;
  }
}

sub Elo_eval_autoprioritize {
  my ($whiteElo, $blackElo) = @_;
  if ($EloAutoprioritizeString eq "") {
    return 0;
  } else {
    update_safevalElo($whiteElo, $blackElo);
    my $retVal = $safevalElo->reval($EloAutoprioritizeString);
    if ($@) {
      log_terminal("error: invalid eloautoprioritize=$EloAutoprioritizeString");
      $retVal = 0;
    } elsif ($retVal != 1) { $retVal = 0; }
    return $retVal;
  }
}


our $gameRunning;

sub save_pgnGame {
  my ($i) = @_;
  my ($thisPgn, $thisResult, $thisWhite, $thisBlack, $thisWhiteTitle, $thisBlackTitle);

  $thisPgn = "";
  if ((defined $games_num[$i]) && (defined $GAMES_event[$games_num[$i]]) && (defined $GAMES_site[$games_num[$i]]) && (defined $GAMES_date[$games_num[$i]]) && (defined $GAMES_round[$games_num[$i]]) && (defined $GAMES_eco[$games_num[$i]]) && (defined $GAMES_forgetTag[$games_num[$i]]) && (defined $GAMES_timeLeft[$games_num[$i]])) {

    if (($followMode == 1) && ($i == 0)) {
      $thisResult = "*";
    } else {
      $thisResult = $games_result[$i];
    }
    if ($thisResult eq "*") {
      $gameRunning = 1;
    }
    if (($relayMode == 1) && ($games_white[$i] =~ /^(W?[GIFC]M|COMP)([A-Z].*)$/)) {
      $thisWhiteTitle = $1;
      $thisWhite = $2;
    } else {
      $thisWhiteTitle = "";
      $thisWhite = $games_white[$i];
    }
    if (($relayMode == 1) && ($games_black[$i] =~ /^(W?[GIFC]M|COMP)([A-Z].*)$/)) {
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
    $thisPgn .= "[LivePgnBotM \"" . $GAMES_forgetTag[$games_num[$i]] . "\"]\n";
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
    if (lc($GAMES_sortkey[$games_num[$a]]) gt lc($GAMES_sortkey[$games_num[$b]])) { return $roundReverseAgtB; }
    if (lc($GAMES_sortkey[$games_num[$a]]) lt lc($GAMES_sortkey[$games_num[$b]])) { return $roundReverseAltB; }
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

  my $newPgnNum = 0;
  my $newPgn;
  for (my $i=0; $i<$maxGamesNum; $i++) {
    $newPgn = save_pgnGame($ordered[$i]);
    if ($newPgn ne "") { $newPgnNum++; }
    $pgn .= $newPgn;
  }

  if (($placeholderGame eq "always") || (($placeholderGame eq "auto") && ($gameRunning == 0))) {
    $pgn .= placeholder_pgn();
    $placeholderPgnNum = 1;
    $newPgnNum++;
  } else {
    $placeholderPgnNum = 0;
  }

  if ($pgn ne $lastPgn) {
    $lastPgn = $pgn;
    $lastPgnNum = $newPgnNum;
    $lastPgnRefresh = time();
    $pgnWriteCount++;
    if ($PGN_LIVE ne "") {
      if (open(my $thisFile, ">", $PGN_LIVE)) {
        $pgn =~ s/\[LivePgnBotM "\d*"\]\n//g;
        print $thisFile $pgn;
        close($thisFile);
      } else {
        log_terminal("error: failed writing $PGN_LIVE");
      }
    }
    if ($autorelayMode == 1) {
      log_rounds();
    }
    refresh_memory();
    refresh_top();
  }
}

sub placeholder_pgn {
  return "[Event \"$newGame_event\"]\n" . "[Site \"$newGame_site\"]\n" . "[Date \"" . strftime($placeholder_date, o_gmtime()) . "\"]\n" . "[Round \"$newGame_round\"]\n" . "[White \"\"]\n" . "[Black \"\"]\n" . "[Result \"$placeholder_result\"]\n\n*\n\n";
}

sub archive_pgnGame {
  my ($i) = @_;

  if ($PGN_ARCHIVE ne "") {
    my $pgn = save_pgnGame($i);
    if (($pgn ne "") && (($archiveSelectFilter eq "") || ($pgn =~ /$archiveSelectFilter/is))) {
      $pgn =~ s/\[Date "([^\[\]"]*)"\]/'[Date "' . strftime($archive_date, o_gmtime()) . '"]'/e;
      $pgn =~ s/\[LivePgnBotM "\d*"\]\n//g;
      if (open(my $thisFile, ">>", $PGN_ARCHIVE)) {
        print $thisFile $pgn;
        close($thisFile);
        log_terminal("debug: archive add game $games_num[$i]: " . headerForFilter($GAMES_event[$games_num[$i]], $GAMES_round[$games_num[$i]], $games_white[$i], $games_black[$i]));
      } else {
        log_terminal("error: failed writing $PGN_ARCHIVE");
      }
    }
  }
}

sub refresh_top {
  if ($PGN_TOP ne "") {
    my ($thisTop, $topPrioritized, $topElo, $thisPrioritized, $thisElo, $newTopPgn);

    $thisTop = $topPrioritized = $topElo = -1;
    for (my $g=0; $g<=$#games_num; $g++) {
      if ($games_result[$g] eq "*") {
        $thisPrioritized = (($autorelayMode == 1) && ($prioritizeFilter ne "") && (headerForFilter($GAMES_event[$games_num[$g]], $GAMES_round[$games_num[$g]], $games_white[$g], $games_black[$g]) =~ /$prioritizeFilter/i)) ? 1 : 0;
        $thisElo = 0;
        if ($games_whiteElo[$g] =~ /^[0-9]+$/) { $thisElo += $games_whiteElo[$g]; }
        if ($games_blackElo[$g] =~ /^[0-9]+$/) { $thisElo += $games_blackElo[$g]; }
        if (($thisPrioritized > $topPrioritized) || (($thisPrioritized == $topPrioritized) && ($thisElo > $topElo))) {
          $thisTop = $g;
          $topPrioritized = $thisPrioritized;
          $topElo = $thisElo;
        }
      }
    }

    if ($thisTop >= 0) {
      $newTopPgn = save_pgnGame($thisTop);
      $newTopPgn =~ s/\[\s*Result\s+"([^"]*)"\s*\]/[Result "*"]/;
    } elsif (($lastTopPgn eq "") && ($#memory_games >= 0)) {
      $newTopPgn = $memory_games[0];
      $newTopPgn =~ s/\[\s*Result\s+"([^"]*)"\s*\]/[Result "*"]/;
    } else {
      $newTopPgn = $lastTopPgn;
    }

    if (($newTopPgn ne "") && ($newTopPgn ne $lastTopPgn)) {
      if (open(my $thisFile, ">", $PGN_TOP)) {
        print $thisFile $newTopPgn;
        close($thisFile);
        $lastTopPgn = $newTopPgn;
      } else {
        log_terminal("error: failed writing $PGN_TOP");
      }
    }
  }
}


sub refresh_memory {
  if ($PGN_MEMORY ne "") {
    my $memoryPgn = $lastPgn;
    my $memory_games_howmany = $memoryMaxGamesNum - $lastPgnNum;
    if ($memory_games_howmany > $#memory_games + 1) { $memory_games_howmany = $#memory_games + 1; }
    if ($memory_games_howmany > 0) {
      my @selected_memory_games = (0 .. ($memory_games_howmany - 1));
      if ($autorelayMode == 1) {
        @selected_memory_games = sort {
          if (lc($memory_games_sortkey[$a]) gt lc($memory_games_sortkey[$b])) { return $roundReverseAgtB; }
          if (lc($memory_games_sortkey[$a]) lt lc($memory_games_sortkey[$b])) { return $roundReverseAltB; }
          return $b <=> $a;
        } @selected_memory_games;
      }
      for (my $i=0; $i<=$#selected_memory_games; $i++) {
        $memoryPgn .= $memory_games[$selected_memory_games[$i]];
      }
    }
    if ($memoryPgn ne "") {
      if (open(my $thisFile, ">", $PGN_MEMORY)) {
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
      $pgn =~ s/\[Date "([^\[\]"]*)"\]/'[Date "' . strftime($memory_date, o_gmtime()) . '"]'/e;
      if (($#memory_games + 1) >= int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum)) {
        pop(@memory_games);
        pop(@memory_games_sortkey);
      }
      unshift(@memory_games, $pgn);
      unshift(@memory_games_sortkey, $GAMES_sortkey[$games_num[$i]]);
      log_terminal("debug: memory add game $games_num[$i]: " . headerForFilter($GAMES_event[$games_num[$i]], $GAMES_round[$games_num[$i]], $games_white[$i], $games_black[$i]));
    }
  }
}

sub fixTagForPurge {
  my ($thisTag) = @_;
  $thisTag =~ s/[^\s\w\d]/./g;
  return $thisTag;
}

sub memory_purge_event {
  my ($thisEvent) = @_;
  my $purgedEvent = 0;

  if ($PGN_MEMORY ne "") {
    my $logged = 0;
    for (my $i=$#memory_games; $i>=0; $i--) {
      if ($memory_games_sortkey[$i] =~ /^"$thisEvent" ".*"$/) {
        @memory_games = @memory_games[0..($i-1), ($i+1)..$#memory_games];
        @memory_games_sortkey = @memory_games_sortkey[0..($i-1), ($i+1)..$#memory_games_sortkey];
        if ($logged == 0) {
          log_terminal('debug: memory purged event: ' . $thisEvent);
          $logged = 1;
        }
        $purgedEvent++;
      }
    }
  }
  return $purgedEvent;
}

sub memory_purge_round {
  my ($thisEventRound) = @_;
  my $purgedRound = 0;

  if ($PGN_MEMORY ne "") {
    my $logged = 0;
    for (my $i=$#memory_games; $i>=0; $i--) {
      if ($thisEventRound eq $memory_games_sortkey[$i]) {
        @memory_games = @memory_games[0..($i-1), ($i+1)..$#memory_games];
        @memory_games_sortkey = @memory_games_sortkey[0..($i-1), ($i+1)..$#memory_games_sortkey];
        if ($logged == 0) {
          log_terminal('debug: memory purged round: ' . sprintf_eventRound($thisEventRound));
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
    $thisWhite = fixTagForPurge($thisWhite);
    $thisBlack = fixTagForPurge($thisBlack);
    my $pattern = '\[White "' . $thisWhite . '"\].*\[Black "' . $thisBlack . '"\]';
    for (my $i=$#memory_games; $i>=0; $i--) {
      if ((eventRound($thisEvent, $thisRound) eq $memory_games_sortkey[$i]) && ($memory_games[$i] =~ /$pattern/s)) {
        @memory_games = @memory_games[0..($i-1), ($i+1)..$#memory_games];
        @memory_games_sortkey = @memory_games_sortkey[0..($i-1), ($i+1)..$#memory_games_sortkey];
        log_terminal('debug: memory purged game: [Event "' . $thisEvent . '"][Round "' . $thisRound . '"][White "' . $thisWhite . '"][Black "' . $thisBlack . '"]');
        $purgedGame++;
      }
    }
  }
  return $purgedGame;
}

sub memory_correct_result {
  my ($searchEvent, $searchRound, $searchWhite, $searchBlack, $searchResult, $replacementResult) = @_;
  my $correctedResult = 0;

  if ($PGN_MEMORY ne "") {
    my $gamePattern = '\[Event "' . $searchEvent . '"\].*\[Round "' . $searchRound . '"\].*\[White "' . $searchWhite . '"\].*\[Black "' . $searchBlack . '"\]';
    my $pattern = '\[Result "' . $searchResult . '"\]';
    my $replacement = '[Result "' . $replacementResult . '"]';
    for (my $i=$#memory_games; $i>=0; $i--) {
      if (($memory_games[$i] =~ /$gamePattern/s) && ($memory_games[$i] =~ /$pattern/)) {
        $memory_games[$i] =~ s/$pattern/$replacement/;
        $correctedResult++;
      }
    }
  }
  if ($correctedResult > 0) { log_terminal('debug: corrected memory result: "' . $searchEvent . '" "' . $searchRound . '" "' . $searchWhite . '" "' . $searchBlack . '" "' . $searchResult . '" "' . $replacementResult . '"'); }
  return $correctedResult;
}

sub memory_rename_event {
  my ($searchEvent, $replacementEvent) = @_;
  my $renamedEvent = 0;

  if ($PGN_MEMORY ne "") {
    my $pattern = '\[Event "' . $searchEvent . '"\]';
    my $replacement = '[Event "' . $replacementEvent . '"]';
    my $thisEvent;
    my $thisRound;
    for (my $i=$#memory_games; $i>=0; $i--) {
      if ($memory_games[$i] =~ /$pattern/) {
        $memory_games[$i] =~ s/$pattern/$replacement/;
        if ($memory_games[$i] =~ /\[Event "([^"]+)"\]/i) { $thisEvent = $1; } else { $thisEvent = ""; }
        if ($memory_games[$i] =~ /\[Round "([^"]+)"\]/i) { $thisRound = $1; } else { $thisRound = ""; }
        $memory_games_sortkey[$i] = eventRound($thisEvent, $thisRound);
        $renamedEvent++;
      }
    }
  }
  if ($renamedEvent > 0) { log_terminal('debug: renamed memory event: "' . $searchEvent . '" "'. $replacementEvent . '"'); }
  return $renamedEvent;
}

sub memory_rename_round {
  my ($searchEvent, $searchRound, $replacementRound) = @_;
  my $renamedRound = 0;

  if ($PGN_MEMORY ne "") {
    my $eventPattern = '\[Event "' . $searchEvent . '"\]';
    my $pattern = '\[Round "' . $searchRound . '"\]';
    my $replacement = '[Round "' . $replacementRound . '"]';
    my $thisEvent;
    my $thisRound;
    for (my $i=$#memory_games; $i>=0; $i--) {
      if (($memory_games[$i] =~ /$eventPattern/) && ($memory_games[$i] =~ /$pattern/)) {
        $memory_games[$i] =~ s/$pattern/$replacement/;
        if ($memory_games[$i] =~ /\[Event "([^"]+)"\]/i) { $thisEvent = $1; } else { $thisEvent = ""; }
        if ($memory_games[$i] =~ /\[Round "([^"]+)"\]/i) { $thisRound = $1; } else { $thisRound = ""; }
        $memory_games_sortkey[$i] = eventRound($thisEvent, $thisRound);
        $renamedRound++;
      }
    }
  }
  if ($renamedRound > 0) { log_terminal('debug: renamed memory round: "' . $searchEvent . '" "' . $searchRound . '" "'. $replacementRound . '"'); }
  return $renamedRound;
}

our $memory_load_time = -1;

sub memory_load {
  if ($PGN_MEMORY eq "") {
    tell_operator_and_log_terminal("error: memory load requires a valid memory file");
  } else {
    my @candidate_memory_games = ();
    @memory_games = ();
    @memory_games_sortkey = ();
    my $newEvent;
    if (open(my $thisFile, "<", $PGN_MEMORY)) {
      my @lines = <$thisFile>;
      @candidate_memory_games = (join("", @lines) =~ /((?:\[\s*\w+\s*"[^"]*"\s*\]\s*)+(?:[^[]|\[%)+)/g);
      foreach (@candidate_memory_games) {
        if (($_ =~ /\[Result "(1-0|1\/2-1\/2|0-1)"\]/i) && (($memorySelectFilter eq "") || ($_ =~ /$memorySelectFilter/is))) {
          if ($_ =~ /\[Event "([^"]+)"\]/i) { $newEvent = $1; } else { $newEvent = ""; }
          if ($newEvent ne "") {
            unshift(@memory_games, $_);
          } else {
            log_terminal("warning: memory load: skipped game with empty event");
          }
        }
      }
    }
    @memory_games = sort {
      my $aForgetTag = ($a =~ /\[LivePgnBotM "([^"]+)"\]/i) ? $1 : "";
      my $bForgetTag = ($b =~ /\[LivePgnBotM "([^"]+)"\]/i) ? $1 : "";
      return $bForgetTag cmp $aForgetTag;
    } @memory_games;
    my $newRound;
    foreach (@memory_games) {
      if ($_ =~ /\[Event "([^"]+)"\]/i) { $newEvent = $1; } else { $newEvent = ""; }
      if ($_ =~ /\[Round "([^"]+)"\]/i) { $newRound = $1; } else { $newRound = ""; }
      push(@memory_games_sortkey, eventRound($newEvent, $newRound));
    }
    for (my $g=0; $g<=$#games_num; $g++) {
      memory_purge_game($GAMES_event[$games_num[$g]], $GAMES_round[$games_num[$g]], $games_white[$g], $games_black[$g]);
    }
    if (($#memory_games + 1) > int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum)) {
      @memory_games = @memory_games[0..(int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum) - 1)];
      @memory_games_sortkey = @memory_games_sortkey[0..(int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum) - 1)];
    }
    if ($autorelayMode == 1) {
      my @newSortkey = ();
      for (my $m=$#memory_games_sortkey; $m>=0; $m--) {
        unless ($memory_games_sortkey[$m] ~~ @newSortkey) {
          log_terminal("info: event mem: "  . sprintf_eventRound($memory_games_sortkey[$m]));
          push(@newSortkey, $memory_games_sortkey[$m]);
        }
      }
    }
    $memory_load_time = time();
    log_terminal("debug: memory load: " . ($#memory_games + 1));
  }
}

sub memory_load_check {
  if (($memory_load_time >= 0) && ($PGN_MEMORY ne "") && (time() - $memory_load_time > $MEMORY_LOAD_CHECK_FREQ)) {
    if (($lastPgnNum == 0) && ($#memory_games + 1 > $memoryMaxGamesNum)) {
      refresh_pgn();
      log_terminal("debug: pgn refresh after load");
    }
    $memory_load_time = -1;
  }
}


sub log_rounds {
  my @newRounds = ();
  my ($i, $thisRound, $thisEvent);

  foreach (@games_num) {
    if (defined $GAMES_event[$_]) {
      $thisRound = eventRound($GAMES_event[$_], $GAMES_round[$_]);
      unless ($thisRound ~~ @newRounds) {
        push(@newRounds, $thisRound);
      }
    }
  }

  foreach (@currentRounds) {
    unless ($_ ~~ @newRounds) {
      log_terminal("info: event out: " . sprintf_eventRound($_));
    }
  }

  foreach (@newRounds) {
    unless ($_ ~~ @currentRounds) {
      log_terminal("info: event new: " . sprintf_eventRound($_));
      $roundsStartCount++;
      if ($memoryAutopurgeEvent > 0) {
        $thisEvent = $_;
        $thisEvent =~ s/^"(.*)" ".*"$/$1/;
        if (($memoryAutopurgeEvent > 1) || ((headerForFilter($thisEvent, "", "", "") !~ /$prioritizeFilter/i) && (headerForFilter($thisEvent, "", "", "") !~ /$autoPrioritizeFilter/i))) {
          memory_purge_event($thisEvent);
          next;
        }
      }
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

add_master_command ("archivefile", "archivefile [filename.pgn] (to get/set the filename for archiving PGN data)");
add_master_command ("archivedate", "archivedate [strftime_string|\"\"] (to get/set the PGN header tag date for the PGN archive)");
add_master_command ("archiveselect", "archiveselect [regexp|\"\"] (to get/set the regular expression to select games for archiving PGN data)");
add_master_command ("autoprioritize", "autoprioritize [regexp|\"\"] (to get/set the regular expression to prioritize entire events during autorelay; has precedence over prioritize)");
add_master_command ("autorelay", "autorelay [0|1] (to automatically observe all relayed games)");
add_master_command ("checkrelay", "checkrelay [!] (to check relayed games during relay and autorelay)");
add_master_command ("config", "config (to get config info)");
if ($TEST_FLAG) { add_master_command ("evaluate", "evaluate [evalexp] (to evaluate an arbitrary internal command: for debug use only)"); }
add_master_command ("eloautoprioritize", "eloautoprioritize [evalexp|\"\"] (to get/set the eval expression returning 1|0 from \$minElo, \$maxElo and \$avgElo to prioritize entire events during autorelay; has precedence over prioritize)");
add_master_command ("eloignore", "eloignore [evalexp|\"\"] (to get/set the eval expression returning 1|0 from \$minElo, \$maxElo and \$avgElo to ignore games during autorelay; has precedence over prioritize)");
add_master_command ("event", "event [string|\"\"] (to get/set the PGN header tag event)");
add_master_command ("eventautocorrect", "eventautocorrect [/regexp/evalexp/|\"\"] (to get/set the regular expression and the eval expression returning a string that corrects event tags during autorelay)");
add_master_command ("follow", "follow [0|handle|/s|/b|/l] (to follow the user with given handle, /s for the best standard game, /b for the best blitz game, /l for the best lightning game, 0 to disable follow mode)");
add_master_command ("games", "games (to get games summary info)");
add_master_command ("heartbeat", "heartbeat [frequency offset] (to get/set the timing of heartbeat log messages, in hours)");
add_master_command ("help", "help [command] (to get commands help)");
add_master_command ("history", "history (to get history info)");
add_master_command ("ics", "ics [server command] (to run a custom command on the ics server)");
add_master_command ("ignore", "ignore [regexp|\"\"] (to get/set the regular expression to ignore events/players from the PGN header during autorelay; has precedence over prioritize; use ^(?:(?!regexp).)+\$ for negative lookup)");
add_master_command ("livedate", "livedate [strftime_string|\"\"] (to get/set the PGN header tag date for live PGN data)");
add_master_command ("livefile", "livefile [filename.pgn] (to get/set the filename for live PGN data)");
add_master_command ("livelist", "livelist [events|rounds|games] (to get live events/rounds/games lists)");
add_master_command ("livemax", "max [number] (to get/set the maximum number of games for the live PGN data)");
add_master_command ("livepurgegames", "livepurgegames [game number list, such as: 12 34 56 ..] (to purge given past games from live PGN data)");
add_master_command ("log", "log [string] (to print a string on the log terminal)");
add_master_command ("memoryautopurgeevent", "memoryautopurgeevent [0|1|2] (to automatically purge new live events from the PGN memory data)");
add_master_command ("memorycorrectresult", "memorycorrectresult [\"event\" \"round\" \"white\" \"black\" \"search\" \"replacement\"] (to correct a result in the PGN memory data)");
add_master_command ("memorydate", "memorydate [strftime_string|\"\"] (to get/set the PGN header tag date for the PGN memory data)");
add_master_command ("memoryfile", "memoryfile [filename.pgn] (to get/set the filename for the PGN memory data)");
add_master_command ("memorylist", "memorylist [events|rounds|games] (to get memory events/rounds/games lists)");
add_master_command ("memoryload", "memoryload [1] (to load PGN memroy data from memory file)");
add_master_command ("memorymax", "memorymax [number] (to get/set the maximum number of games for the PGN memory data)");
add_master_command ("memorypurgegame", "memorypurgegame [\"event\" \"round\" \"white\" \"black\"] (to purge a game from the PGN memory data)");
add_master_command ("memorypurgeevent", "memorypurgeevent [\"event\"] (to purge an event from the PGN memory data)");
add_master_command ("memorypurgeround", "memorypurgeround [\"event\" \"round\"] (to purge a round from the PGN memory data)");
add_master_command ("memoryrenameevent", "memoryrenameevent [\"search\" \"replacement\"] (to rename an event in the PGN memory data)");
add_master_command ("memoryrenameround", "memoryrenameround [\"event\" \"search\" \"replacement\"] (to rename a round in the PGN memory data)");
add_master_command ("memoryselect", "memoryselect [regexp|\"\"] (to get/set the regular expression to select games for the PGN memory data)");
add_master_command ("observe", "observe [game number list, such as: 12 34 56 ..] (to observe given games)");
add_master_command ("placeholderdate", "placeholderdate [strftime_string|\"\"] (to get/set the PGN header tag date for the PGN placeholder game)");
add_master_command ("placeholdergame", "placeholdergame [always|auto|never] (to get/set the PGN placeholder game behaviour during autorelay)");
add_master_command ("placeholderresult", "placeholderresult [string|\"\"] (to get/set the PGN header tag result for the PGN placeholder game)");
add_master_command ("prioritize", "prioritize [regexp|\"\"] (to get/set the regular expression to prioritize events/players from the PGN header during autorelay; might be overridden by autoprioritize; might be overruled by ignore)");
add_master_command ("prioritizeonly", "prioritizeonly [0|1] (to get/set the prioritized games only mode during autorelay)");
add_master_command ("quit", "quit [number] (to quit from the ics server, returning the given exit value)");
add_master_command ("relay", "relay [0|game number list, such as: 12 34 56 ..] (to observe given games from an event relay, 0 to disable relay mode)");
add_master_command ("reset", "reset [all|config|games|live|memory] (to reset games and configuration)");
add_master_command ("round", "round [string|\"\"] (to get/set the PGN header tag round)");
add_master_command ("roundautocorrect", "roundautocorrect [/regexp/evalexp/|\"\"] (to get/set the regular expression and the eval expression returning a string from \$event that corrects round tags during autorelay)");
add_master_command ("roundreverse", "roundreverse [0|1] (to use reverse alphabetical ordering of rounds)");
add_master_command ("site", "site [string|\"\"] (to get/set the PGN header tag site)");
add_master_command ("startup", "startup [command list, separated by semicolon] (to get/set startup commands file)");
add_master_command ("timeoffset", "timeoffset [[+|-]seconds] (to get/set the offset correcting the time value from the UTC time used by default)");
add_master_command ("topfile", "topfile [filename.pgn] (to get/set the filename for the top PGN data)");
add_master_command ("verbosity", "verbosity [0-7] (to get/set log verbosity: 0=none, 1=alert, 2=error, 3=warning, 4=info, 5=debug, 6=fyi 7=output)");
add_master_command ("write", "write [!] (to force writing updated PGN data according to the latest configuration)");

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
  } elsif ($command eq "archivedate") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $archive_date = $parameters;
      }
      tell_operator("archivedate=$archive_date");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "archivefile") {
    if ($parameters =~ /^([\w\d\/\\.+=_-]*|"")$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $PGN_ARCHIVE = $parameters;
        log_terminal("info: archivefile=$PGN_ARCHIVE");
      }
      tell_operator("archivefile=$PGN_ARCHIVE" . fileInfo($PGN_ARCHIVE));
      check_pgn_files();
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "archiveselect") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        eval {
          "test" =~ /$parameters/;
          if ($@) { pgn4webError(); }
          if ($parameters eq "\"\"") { $parameters = ""; }
          $archiveSelectFilter = $parameters;
          log_terminal("info: archiveselect=$archiveSelectFilter");
          1;
        } or do {
          tell_operator("error: invalid regular expression: $parameters");
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
          if ($@) { pgn4webError(); }
          if ($parameters eq "\"\"") { $parameters = ""; }
          if (($parameters ne $autoPrioritize) && ($relayMode == 1)) {
            force_next_check_relay_time($CHECK_RELAY_MIN_LAG);
          }
          $autoPrioritize = $parameters;
          $reportedNotFoundNonPrioritizedGame = 0;
          if ($autoPrioritize ne "") {
            log_terminal("info: autoprioritize=$autoPrioritize");
          } else {
            log_terminal("info: autoprioritize=$autoPrioritize prioritize=$prioritizeFilter");
          }
          1;
        } or do {
          tell_operator("error: invalid regular expression: $parameters");
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
          force_next_check_relay_time($CHECK_RELAY_MIN_LAG);
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
  } elsif ($command eq "checkrelay") {
    if ($parameters eq "!") {
      if ($relayMode == 1) {
        force_next_check_relay_time();
        # check_relay_results();
        tell_operator("OK $command");
      } else {
        tell_operator("warning: checkrelay command while relayMode=$relayMode");
      }
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "config") {
    my $cfg = "config:";
    $cfg .= " livefile=$PGN_LIVE livemax=$maxGamesNum livedate=$newGame_date";
    $cfg .= " topfile=$PGN_TOP";
    $cfg .= " memoryfile=$PGN_MEMORY";
    if ($PGN_MEMORY ne "") { $cfg .= " memorymax=$memoryMaxGamesNum memorydate=$memory_date memoryselect=$memorySelectFilter memoryautopurgeevent=$memoryAutopurgeEvent"; }
    $cfg .= " archivefile=$PGN_ARCHIVE";
    if ($PGN_ARCHIVE ne "") { $cfg .= " archivedate=$archive_date archiveselect=$archiveSelectFilter"; }
    $cfg .= " placeholdergame=$placeholderGame";
    if ($placeholderGame ne "never") { $cfg .= " placeholderdate=$placeholder_date placeholderresult=$placeholder_result"; }
    $cfg .= " follow=$followMode";
    $cfg .= " relay=$relayMode";
    if ($relayMode == 1) { $cfg .= " autorelay=$autorelayMode";
      if ($autorelayMode == 1) { $cfg .= " ignore=$ignoreFilter eloignore=$EloIgnoreString prioritize=$prioritizeFilter autoprioritize=$autoPrioritize eloautoprioritize=$EloAutoprioritizeString prioritizeonly=$prioritizeOnly"; }
    }
    $cfg .= " event=$newGame_event eventautocorrect=";
    if ($eventAutocorrectRegexp ne "") { $cfg .= "/$eventAutocorrectRegexp/$eventAutocorrectString/"; }
    $cfg .= " round=$newGame_round roundautocorrect=";
    if ($roundAutocorrectRegexp ne "") { $cfg .= "/$roundAutocorrectRegexp/$roundAutocorrectString/"; }
    $cfg .= " roundreverse=$roundReverse";
    $cfg .= " site=$newGame_site";
    $cfg .= " heartbeat=$heartbeat_freq_hour/$heartbeat_offset_hour";
    $cfg .= " timeoffset=$timeOffset";
    $cfg .= " verbosity=$verbosity";
    tell_operator($cfg);
    check_pgn_files();
  } elsif (($TEST_FLAG) && ($command eq "evaluate")) {
    if ($parameters ne "") {
      eval {
        eval($parameters);
        if ($@) { pgn4webError(); }
        tell_operator("OK $command");
        1;
      } or do {
        tell_operator("error: invalid command string: $parameters");
      };
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "eloautoprioritize") {
    eval {
      my $oldEloAutoprioritizeString = $EloAutoprioritizeString;
      if ($parameters ne "") {
        if ($parameters eq "\"\"") {
          $EloAutoprioritizeString = "";
        } else {
          update_safevalElo(2000, 0);
          my $test = $safevalElo->reval($parameters);
          if ($@) { pgn4webError(); }
          $EloAutoprioritizeString = $parameters;
        }
        if (($EloAutoprioritizeString ne $oldEloAutoprioritizeString) && ($relayMode == 1)) {
          force_next_check_relay_time($CHECK_RELAY_MIN_LAG);
        }
        if ($autoPrioritize ne "") {
          log_terminal("info: eloautoprioritize=$EloAutoprioritizeString prioritize=$prioritizeFilter");
        } else {
          log_terminal("info: eloautoprioritize=$EloAutoprioritizeString");
        }
      }
      tell_operator("eloautoprioritize=$EloAutoprioritizeString prioritize=$prioritizeFilter");
      1;
    } or do {
      tell_operator("error: invalid regular expression: $parameters");
    };
  } elsif ($command eq "eloignore") {
    eval {
      my $oldEloIgnoreString = $EloIgnoreString;
      if ($parameters ne "") {
        if ($parameters eq "\"\"") {
          $EloIgnoreString = "";
        } else {
          update_safevalElo(2000, 0);
          my $test = $safevalElo->reval($parameters);
          if ($@) { pgn4webError(); }
          $EloIgnoreString = $parameters;
        }
        if (($EloIgnoreString ne $oldEloIgnoreString) && ($relayMode == 1)) {
          if ($autorelayMode == 1) {
            for (my $i=$#games_num; $i>=0; $i--) {
              if ((defined $games_num[$i])  && (defined $games_whiteElo[$i]) && (defined $games_blackElo[$i])) {
                if (Elo_eval_ignore($games_whiteElo[$i], $games_blackElo[$i]) == 1) {
                  remove_game($games_num[$i]);
                }
              }
            }
          }
          force_next_check_relay_time($CHECK_RELAY_MIN_LAG);
        }
        log_terminal("info: eloignore=$EloIgnoreString");
      }
      tell_operator("info: eloignore=$EloIgnoreString");
      1;
    } or do {
      tell_operator("error: invalid regular expression: $parameters");
    };
  } elsif ($command eq "event") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $newGame_event =~ s/"/'/g;
        $newGame_event = $parameters;
      }
      tell_operator("event=$newGame_event");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "eventautocorrect") {
    if ($parameters =~ /^(\/([^\/]+)\/(.*)\/|"")?$/) {
      eval {
        if ($parameters ne "") {
          if ($parameters eq "\"\"") {
            $eventAutocorrectRegexp = "";
            $eventAutocorrectString = "";
          } else {
            my $newEventAutocorrectRegexp = $2;
            my $newEventAutocorrectString = $3;
            my $newEventAutocorrectTest = "test";
            $newEventAutocorrectTest =~ s/$newEventAutocorrectRegexp/$safevalEvent->reval($newEventAutocorrectString)/egi;
            if ($@) { pgn4webError(); }
            $newEventAutocorrectTest =~ s/$newEventAutocorrectTest/$safevalEvent->reval($newEventAutocorrectString)/egi;
            if ($@) { pgn4webError(); }
            $eventAutocorrectRegexp = $newEventAutocorrectRegexp;
            $eventAutocorrectString = $newEventAutocorrectString;
            my $eventAutocorrectChanges = 0;
            for my $thisGameNum (@games_num) {
              if ($GAMES_event[$thisGameNum] =~ /$eventAutocorrectRegexp/i) {
                $GAMES_event[$thisGameNum] = event_autocorrect($GAMES_event[$thisGameNum]);
                $GAMES_sortkey[$thisGameNum] = eventRound($GAMES_event[$thisGameNum], $GAMES_round[$thisGameNum]);
                $eventAutocorrectChanges = 1;
              }
            }
            if ($eventAutocorrectChanges == 1) { refresh_pgn(); }
          }
          log_terminal("info: eventautocorrect=" . ($eventAutocorrectRegexp ? "/$eventAutocorrectRegexp/$eventAutocorrectString/" : ""));
        }
        tell_operator("eventautocorrect=" . ($eventAutocorrectRegexp ? "/$eventAutocorrectRegexp/$eventAutocorrectString/" : ""));
        1;
      } or do {
        tell_operator("error: invalid regular expression: $parameters");
      };
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
  } elsif ($command eq "games") {
    my $memoryList = "";
    if ($PGN_MEMORY ne "") {
      $memoryList = " memory(" . ($#memory_games + 1) . "/$memoryMaxGamesNum/" . int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum) . ")";
    }
    my $gameList = "";
    for (my $i=0; $i<=$#games_num; $i++) {
      if (defined $games_num[$i]) {
        $gameList .= " $games_num[$i]";
        if ($games_result[$i] eq "1-0") { $gameList .= "+"; }
        elsif ($games_result[$i] eq "1/2-1/2") { $gameList .= "="; }
        elsif ($games_result[$i] eq "0-1") { $gameList .= "-"; }
        else { $gameList .= "*"; }
      }
    }
    tell_operator("games:$memoryList placeholder($placeholderPgnNum) liverounds(" . ($#currentRounds + 1) . ") livegames(" . ($#games_num + 1) . "/$maxGamesNum)$gameList");
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
      tell_operator("info: non-beautified players names required");
    }
  } elsif ($command eq "history") {
    tell_operator("history: " . h_info());
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
          if ($@) { pgn4webError(); }
          if ($parameters eq "\"\"") { $parameters = ""; }
          $ignoreFilter = $parameters;
          if ($relayMode == 1) {
            force_next_check_relay_time($CHECK_RELAY_MIN_LAG);
          }
          $reportedNotFoundNonPrioritizedGame = 0;
          log_terminal("info: ignore=$ignoreFilter");
          1;
        } or do {
          tell_operator("error: invalid regular expression: $parameters");
        };
      }
      tell_operator("ignore=$ignoreFilter");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "livedate") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $newGame_date = $parameters;
      }
      tell_operator("livedate=$newGame_date");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "livefile") {
    if ($parameters =~ /^([\w\d\/\\.+=_-]*|"")$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $PGN_LIVE = $parameters;
        log_terminal("info: livefile=$PGN_LIVE");
      }
      tell_operator("livefile=$PGN_LIVE" . fileInfo($PGN_LIVE));
      check_pgn_files();
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "livelist") {
    if ($parameters =~ /^(event|round|game)s?$/) {
      $parameters = $1 . "s";
      my @liveList = ();
      my $liveListItem;
      my $minElo = 99999;
      my $maxElo = 0;
      my $totElo = 0;
      my $numElo = 0;
      for (my $i = 0; $i <= $#games_num; $i++) {
        $liveListItem = "\"" . $GAMES_event[$games_num[$i]] . "\"";
        if (($parameters eq "rounds") || ($parameters eq "games")) {
          $liveListItem .= " \"" . $GAMES_round[$games_num[$i]] . "\"";
        }
        if ($parameters eq "games") {
          $liveListItem .= " \"" . $games_white[$i] . "\" \"" . $games_black[$i] . "\" \"" . $games_result[$i] . "\"";
        }
        if ($games_whiteElo[$i] ne "") {
          if ($games_whiteElo[$i] < $minElo) { $minElo = $games_whiteElo[$i]; }
          if ($games_whiteElo[$i] > $maxElo) { $maxElo = $games_whiteElo[$i]; }
          $totElo += $games_whiteElo[$i];
          $numElo++;
        }
        if ($games_blackElo[$i] ne "") {
          if ($games_blackElo[$i] < $minElo) { $minElo = $games_blackElo[$i]; }
          if ($games_blackElo[$i] > $maxElo) { $maxElo = $games_blackElo[$i]; }
          $totElo += $games_blackElo[$i];
          $numElo++;
        }
        unless ($liveListItem ~~ @liveList) {
          push(@liveList, $liveListItem);
        }
      }
      tell_operator("livelist: $parameters(" . ($#liveList + 1) . "/" . ($#games_num + 1) . "/$maxGamesNum)" . ($numElo > 0 ? sprintf(" elo(%d/%d/%d)", $minElo, $totElo/$numElo, $maxElo) : "") . ($#liveList >= 0 ? " " . join(", ", @liveList) . ";" : ""));
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "livemax") {
    if ($parameters =~ /^([1-9]\d*)?$/) {
      if ($parameters ne "") {
        if ($parameters > $maxGamesNumDefault) {
          tell_operator_and_log_terminal("warning: max number of live games set above server observe limit of $maxGamesNumDefault");
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
      tell_operator("livemax=$maxGamesNum");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "livepurgegames") {
    if ($parameters ne "") {
      if ($autorelayMode == 1) {
        tell_operator("warning: using $command during autorelay");
      }
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
  } elsif ($command eq "log") {
    if ($parameters ne "") {
      log_terminal($parameters);
    } else {
      tell_operator(detect_command_helptext($command));
    }
  } elsif ($command eq "memoryautopurgeevent") {
    if ($parameters =~ /^(0|1|2)$/) {
      $memoryAutopurgeEvent = $parameters;
      my $purgedEvent = 0;
      if ($memoryAutopurgeEvent > 0) {
        my @theseEvents = ();
        for (my $i=0; $i<$maxGamesNum; $i++) {
          if ((defined $games_num[$i]) && (defined $GAMES_event[$games_num[$i]])) {
            unless ($GAMES_event[$games_num[$i]] ~~ @theseEvents) {
              if (($memoryAutopurgeEvent > 1) || ((headerForFilter($GAMES_event[$games_num[$i]], "", "", "") !~ /$prioritizeFilter/i) && (headerForFilter($GAMES_event[$games_num[$i]], "", "", "") !~ /$autoPrioritizeFilter/i))) {
                $purgedEvent += memory_purge_event($GAMES_event[$games_num[$i]]);
                push(@theseEvents, $GAMES_event[$games_num[$i]]);
              }
            }
          }
        }
        if ($purgedEvent > 0) {
          $lastPgn = $lastPgnForce;
          refresh_pgn();
        }
      }
    } elsif ($parameters !~ /^\??$/) {
      tell_operator("error: invalid $command parameter");
    }
    tell_operator("memoryautopurgeevent=$memoryAutopurgeEvent");
  } elsif ($command eq "memorydate") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $memory_date = $parameters;
      }
      tell_operator("memorydate=$memory_date");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memorycorrectresult") {
    if ($parameters =~ /^\s*"(.+?)"\s*"(.+?)"\s*"(.+?)"\s*"(.+?)"\s*"(.+?)"\s*"(.+?)"\s*$/) {
      my $searchEvent = $1;
      my $searchRound = $2;
      my $searchWhite = $3;
      my $searchBlack = $4;
      my $searchResult = $5;
      my $replacementResult = $6;
      eval {
        "test" =~ /$searchEvent/;
        if ($@) { pgn4webError(); }
        "test" =~ /$searchRound/;
        if ($@) { pgn4webError(); }
        "test" =~ /$searchWhite/;
        if ($@) { pgn4webError(); }
        "test" =~ /$searchBlack/;
        if ($@) { pgn4webError(); }
        "test" =~ /$searchResult/;
        if ($@) { pgn4webError(); }
        if (memory_correct_result($searchEvent, $searchRound, $searchWhite, $searchBlack, $searchResult, $replacementResult) > 0) {
          $lastPgn = $lastPgnForce;
          refresh_pgn();
          tell_operator("corrected result");
        } else {
          tell_operator("memory result not found for correct");
        }
        1;
      } or do {
        tell_operator("error: invalid regular expression");
      };
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memoryfile") {
    if ($parameters =~ /^([\w\d\/\\.+=_-]*|"")$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        if ($parameters eq "\"\"") {
           $parameters = "";
           @memory_games = ();
           @memory_games_sortkey = ();
        }
        $PGN_MEMORY = $parameters;
        log_terminal("info: memoryfile=$PGN_MEMORY");
      }
      tell_operator("memoryfile=$PGN_MEMORY" . fileInfo($PGN_MEMORY));
      check_pgn_files();
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memorylist") {
    if ($parameters =~ /^(event|round|game)s?$/) {
      $parameters = $1 . "s";
      if ($PGN_MEMORY eq "") {
        log_terminal("warning: PGN memory data disabled");
      }
      my $memoryListRegexp = '\[Event "([^"]*)"\].*';
      my $memoryListReplacement = '"\"$1\"';
      if (($parameters eq "rounds") || ($parameters eq "games")) {
        $memoryListRegexp .= '\[Round "([^"]*)"\].*';
        $memoryListReplacement .= ' \"$2\"';
      }
      if ($parameters eq "games") {
        $memoryListRegexp .= '\[White "([^"]*)"\].*\[Black "([^"]*)"\].*\[Result "([^"]*)"\].*';
        $memoryListReplacement .= ' \"$3\" \"$4\" \"$5\"';
      }
      $memoryListReplacement .= '"';
      my @memoryList = @memory_games;
      foreach (@memoryList) {
        if ($_ =~ /$memoryListRegexp/s) {
          $_ =~ s/$memoryListRegexp/$memoryListReplacement/ees;
        } else {
          $_ = "";
        }
      }
      for (my $i = $#memoryList; $i > 0; $i--) {
        if (($memoryList[$i] eq $memoryList[$i - 1]) || ($memoryList[$i] eq "")) {
          @memoryList = @memoryList[0..($i-1), ($i+1)..$#memoryList];
        }
      }
      if (($#memoryList + 1 > 0) && ($memoryList[0] eq "")) {
        @memoryList = @memoryList[1..$#memoryList];
      }
      tell_operator("memorylist: $parameters(" . ($#memoryList + 1) . "/" . ($#memory_games + 1) . "/$memoryMaxGamesNum/" . int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum) .")" . ($#memoryList >= 0 ? " " . join(", ", @memoryList) . ";" : ""));
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
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
        $memoryMaxGamesNum = $parameters;
        if (($#memory_games + 1) > int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum)) {
          @memory_games = @memory_games[0..(int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum) - 1)];
          @memory_games_sortkey = @memory_games_sortkey[0..(int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum) - 1)];
        }
      }
      tell_operator("memorymax=$memoryMaxGamesNum");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memorypurgegame") {
    if ($parameters =~ /^\s*"(.*?)"\s*"(.*?)"\s*"(.*?)"\s*"(.*?)"\s*$/) {
      if (memory_purge_game($1, $2, $3, $4) > 0) {
        $lastPgn = $lastPgnForce;
        refresh_pgn();
        tell_operator("purged memory game");
      } else {
        tell_operator("memory game not found for purge");
      }
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memorypurgeevent") {
    if ($parameters =~ /^\s*"(.*?)"\s*$/) {
      if (memory_purge_event($1) > 0) {
        $lastPgn = $lastPgnForce;
        refresh_pgn();
        tell_operator("purged memory event");
      } else {
        tell_operator("memory event not found for purge");
      }
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memorypurgeround") {
    if ($parameters =~ /^\s*"(.*?)"\s*"(.*?)"\s*$/) {
      if (memory_purge_round(eventRound($1, $2)) > 0) {
        $lastPgn = $lastPgnForce;
        refresh_pgn();
        tell_operator("purged memory round");
      } else {
        tell_operator("memory round not found for purge");
      }
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memoryrenameevent") {
    if ($parameters =~ /^\s*"(.+?)"\s*"(.+?)"\s*$/) {
      my $searchEvent = $1;
      my $replacementEvent = $2;
      eval {
        "test" =~ /$searchEvent/;
        if ($@) { pgn4webError(); }
        if (memory_rename_event($searchEvent, $replacementEvent) > 0) {
          $lastPgn = $lastPgnForce;
          refresh_pgn();
          tell_operator("renamed event");
        } else {
          tell_operator("memory event not found for rename");
        }
        1;
      } or do {
        tell_operator("error: invalid regular expression: $searchEvent");
      };
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "memoryrenameround") {
    if ($parameters =~ /^\s*"(.+?)"\s*"(.+?)"\s*"(.+?)"\s*$/) {
      my $searchEvent = $1;
      my $searchRound = $2;
      my $replacementRound = $3;
      eval {
        "test" =~ /$searchEvent/;
        if ($@) { pgn4webError(); }
        "test" =~ /$searchRound/;
        if ($@) { pgn4webError(); }
        if (memory_rename_round($searchEvent, $searchRound, $replacementRound) > 0) {
          $lastPgn = $lastPgnForce;
          refresh_pgn();
          tell_operator("renamed round");
        } else {
          tell_operator("memory round not found for rename");
        }
        1;
      } or do {
        tell_operator("error: invalid regular expression");
      };
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
          if ($@) { pgn4webError(); }
          if ($parameters eq "\"\"") { $parameters = ""; }
          $memorySelectFilter = $parameters;
          log_terminal("info: memoryselect=$memorySelectFilter");
          1;
        } or do {
          tell_operator("error: invalid regular expression: $parameters");
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
  } elsif ($command eq "placeholderdate") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $placeholder_date = $parameters;
      }
      tell_operator("placeholderdate=$placeholder_date");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "placeholdergame") {
    if (($parameters eq "always") || ($parameters eq "auto") || ($parameters eq "never")) {
      $placeholderGame = $parameters;
      tell_operator("placeholdergame=$placeholderGame");
      log_terminal("info: placeholdergame=$placeholderGame");
    } elsif ($parameters eq "") {
      tell_operator("placeholdergame=$placeholderGame");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "placeholderresult") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        if ($parameters eq "\"\"") { $parameters = ""; }
        $placeholder_result = $parameters;
      }
      tell_operator("placeholderresult=$placeholder_result");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "prioritize") {
    if ($parameters =~ /^([^\[\]"]+|"")?$/) {
      if ($parameters ne "") {
        eval {
          "test" =~ /$parameters/;
          if ($@) { pgn4webError(); }
          if ($parameters eq "\"\"") { $parameters = ""; }
          $prioritizeFilter = $parameters;
          if ($relayMode == 1) {
            force_next_check_relay_time($CHECK_RELAY_MIN_LAG);
          }
          $reportedNotFoundNonPrioritizedGame = 0;
          log_terminal("info: prioritize=$prioritizeFilter");
          if ($autoPrioritize ne "") {
            tell_operator("warning: autoprioritize overrides the manually set prioritize regular expression");
          }
          1;
        } or do {
          tell_operator("error: invalid regular expression: $parameters");
        };
      }
      tell_operator("prioritize=$prioritizeFilter");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "prioritizeonly") {
    if ($parameters =~ /^(0|1)$/) {
      if ($parameters != $prioritizeOnly) {
        $prioritizeOnly = $parameters;
        if ($relayMode == 1) {
          force_next_check_relay_time($CHECK_RELAY_MIN_LAG);
        }
        if ($prioritizeOnly == 1) {
          tell_operator_and_log_terminal("warning: prioritizeonly=$prioritizeOnly should be avoided and replaced by more efficient prioritize/ignore options");
        }
      }
    } elsif ($parameters !~ /^\??$/) {
      tell_operator("error: invalid $command parameter");
    }
    tell_operator("prioritizeonly=$prioritizeOnly");
  } elsif ($command eq "quit") {
    if ($parameters =~ /^\d+$/) {
      tell_operator("OK $command($parameters)");
      log_terminal("info: quit with exit value $parameters");
      # cmd_run("quit");
      myExit($parameters);
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
    }
    tell_operator("relay=$relayMode");
    if (($relayMode == 1) && ($relayOnline == 0)) {
      tell_operator("warning: ics relay offline");
    }
  } elsif ($command eq "reset") {
    if ($parameters =~ /^(all|config|games|live|memory)$/) {
      if ($parameters eq "all") { reset_all(); }
      elsif ($parameters eq "config") { reset_config(); }
      elsif ($parameters eq "games") { reset_games(); }
      elsif ($parameters eq "live") { reset_live(); }
      elsif ($parameters eq "memory") { reset_memory(); }
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
        $newGame_round =~ s/"/'/g;
      }
      tell_operator("round=$newGame_round");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "roundautocorrect") {
    if ($parameters =~ /^(\/([^\/]+)\/(.*)\/|"")?$/) {
      eval {
        if ($parameters ne "") {
          if ($parameters eq "\"\"") {
            $roundAutocorrectRegexp = "";
            $roundAutocorrectString = "";
          } else {
            my $newRoundAutocorrectRegexp = $2;
            my $newRoundAutocorrectString = $3;
            my $newRoundAutocorrectTest = "test";
            ${$safevalRound->varglob("event")} = "Test Event";
            $newRoundAutocorrectTest =~ s/$newRoundAutocorrectRegexp/$safevalRound->reval($newRoundAutocorrectString)/egi;
            if ($@) { pgn4webError(); }
            $newRoundAutocorrectTest =~ s/$newRoundAutocorrectTest/$safevalRound->reval($newRoundAutocorrectString)/egi;
            if ($@) { pgn4webError(); }
            $roundAutocorrectRegexp = $newRoundAutocorrectRegexp;
            $roundAutocorrectString = $newRoundAutocorrectString;
            my $roundAutocorrectChanges = 0;
            for my $thisGameNum (@games_num) {
              if ($GAMES_round[$thisGameNum] =~ /$roundAutocorrectRegexp/i) {
                $GAMES_round[$thisGameNum] = round_autocorrect($GAMES_round[$thisGameNum], $GAMES_event[$thisGameNum]);
                $GAMES_sortkey[$thisGameNum] = eventRound($GAMES_event[$thisGameNum], $GAMES_round[$thisGameNum]);
                $roundAutocorrectChanges = 1;
              }
            }
            if ($roundAutocorrectChanges == 1) { refresh_pgn(); }
          }
          log_terminal("info: roundautocorrect=" . ($roundAutocorrectRegexp ? "/$roundAutocorrectRegexp/$roundAutocorrectString/" : ""));
        }
        tell_operator("roundautocorrect=" . ($roundAutocorrectRegexp ? "/$roundAutocorrectRegexp/$roundAutocorrectString/" : ""));
        1;
      } or do {
        tell_operator("error: invalid regular expression: $parameters");
      };
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "roundreverse") {
    if ($parameters =~ /^(0|1)$/) {
      $roundReverse = $parameters;
      $roundReverseAgtB = $roundReverse ? -1 : 1;
      $roundReverseAltB = -$roundReverseAgtB;
      # if ($#games_num > 0) { refresh_pgn(); }
    } elsif ($parameters !~ /^\??$/) {
      tell_operator("error: invalid $command parameter");
    }
    tell_operator("roundreverse=$roundReverse");
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
    tell_operator("startup($STARTUP_FILE) $startupString");
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
  } elsif ($command eq "topfile") {
    if ($parameters =~ /^([\w\d\/\\.+=_-]*|"")$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        if ($parameters eq "\"\"") {
           $parameters = "";
           $lastTopPgn = "";
        }
        $PGN_TOP = $parameters;
        log_terminal("info: topfile=$PGN_TOP");
      }
      tell_operator("topfile=$PGN_TOP" . fileInfo($PGN_TOP));
      check_pgn_files();
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "verbosity") {
    if ($parameters =~ /^[0-7]?$/) {
      if ($parameters ne "") {
        $verbosity = $parameters;
      }
      tell_operator_and_log_terminal("alert: verbosity=$verbosity");
    } else {
      tell_operator("error: invalid $command parameter");
    }
  } elsif ($command eq "write") {
    if ($parameters eq "!") {
      $lastPgn = $lastPgnForce;
      refresh_pgn();
      tell_operator("OK $command");
    } elsif ($parameters eq "") {
      tell_operator(detect_command_helptext($command));
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

sub fileInfo {
  my ($filename) = @_;
  my $infoText = "";
  if ($filename ne "") {
     my @info = stat($filename);
     if (defined $info[9]) {
       $infoText .= " modified=" . strftime("%Y-%m-%d %H:%M:%S", o_gmtime($info[9]));
     }
     if (defined $info[7]) {
       $infoText .= " size=$info[7]";
     }
     if (defined $info[2]) {
       $infoText .= sprintf(" permissions=%04o", $info[2] & 07777);
     }
  }
  return $infoText;
}

sub check_pgn_files {
  if (($PGN_LIVE eq "") && ($PGN_MEMORY eq "") && ($PGN_TOP eq "") && ($PGN_ARCHIVE eq "")) {
    tell_operator_and_log_terminal("warning: all output files disabled");
  }
}

sub read_startupCommands {
  my @commandList = ();
  if (open(CMDFILE, "<" , $STARTUP_FILE)) {
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

  if (open(CMDFILE, ">" , $STARTUP_FILE)) {
    foreach my $cmd (@commandList) {
      $cmd =~ s/^\s*//;
      $cmd =~ s/\s*$/\n/;
      print CMDFILE $cmd;
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
  if (($autoPrioritize ne "") || ($EloAutoprioritizeString ne "")) {
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
  my ($delay) = @_;
  if ($delay eq "") { $delay = -1; }
  $next_check_relay_time = time() + $delay;
  $short_relay_period = 1;
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
    tell_operator_and_log_terminal("heartbeat: " . h_info());
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

sub h_info {
  my $secTime = time() - $startupTime;
  my $hourTime = $secTime / 3600;
  my $dayTime = $hourTime / 24;
  my $thisInfo .= sprintf("rounds=%d/%d r/d=%d", ($#currentRounds + 1), $roundsStartCount, $roundsStartCount / $dayTime);
  $thisInfo .= sprintf(" games=%d/%d/%d g/d=%d", ($#games_num + 1), $maxGamesNum, $gamesStartCount, $gamesStartCount / $dayTime);
  if ($PGN_MEMORY ne "") {
    $thisInfo .= sprintf(" memory=%d/%d/%d", ($#memory_games + 1), $memoryMaxGamesNum, int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum));
  }
  if ($verbosity >= 5) {
    $thisInfo .= sprintf(" pgn=%d p/h=%d cmd=%d c/h=%d lines=%d l/h=%d", $pgnWriteCount, $pgnWriteCount / $hourTime, $cmdRunCount, $cmdRunCount / $hourTime, $lineCount, $lineCount / $hourTime);
    $thisInfo .= " sys=" . sys_info();
  }
  $thisInfo .= sprintf(" last=%s", $lastPgnRefresh ? strftime("%Y-%m-%d %H:%M:%S", o_gmtime($lastPgnRefresh)) : "?");
  $thisInfo .= sprintf(" now=%s uptime=%s", strftime("%Y-%m-%d %H:%M:%S", o_gmtime($startupTime + $secTime)), sec2time($secTime));
  return $thisInfo;
}

sub sys_info {
  open(STAT, "<", "/proc/$$/stat") or return "?";
  my @stat = split(/\s+/, <STAT>);
  close(STAT);
  return $stat[3] . "/" . $stat[0] . "/" . $stat[22] . "/" . $stat[23];
}


sub setup {

  $telnet = new Net::Telnet(
    Timeout => $OPEN_TIMEOUT,
    Binmode => 1,
  );

  $telnet->errmode(sub {
    my $msg = shift;
    log_terminal("error: " . $msg);
    myExit(1);
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
        myExit(1);
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
      myExit(1);
    }

    log_terminal("debug: logged in as guest $username");
  }

  $telnet->prompt("/^/");

  cmd_run("iset defprompt 1");
  cmd_run("iset nowrap 1");
  cmd_run("iset startpos 1");
  cmd_run("set bell 0");
  cmd_run("set chanoff 1");
  cmd_run("set cshout 0");
  cmd_run("set echo 0");
  cmd_run("set height 240");
  cmd_run("set kibitz 0");
  cmd_run("set kiblevel 9000");
  cmd_run("set open 0");
  cmd_run("set ptime 0");
  cmd_run("set seek 0");
  cmd_run("set shout 0");
  cmd_run("set style 12");
  cmd_run("set tolerance 5");
  cmd_run("set tzone UTC");
  cmd_run("set width 240");
  log_terminal("debug: initialization done");

  my @startupCommands = read_startupCommands();
  foreach my $cmd (@startupCommands) {
    if ($cmd =~ /^\s*#/) {
      # skip comments
    } elsif ($cmd =~ /^\s*([^\s=]+)=?\s*(.*)$/) {
      process_master_command($1, $2);
    } elsif ($cmd !~ /^\s*$/) {
      log_terminal("error: invalid startup command $cmd");
    }
  }
  log_terminal("debug: startup commands done");

  $tellOperator = 1;
  tell_operator("info: ready");
  if ($FLAGS) { tell_operator("alert: flags: $FLAGS"); }
  check_pgn_files();
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
    myExit(1);
  });

  while (1) {
    my $line = $telnet->getline(Timeout => $LINE_WAIT_TIMEOUT);
    if (($line) && ($line !~ /^$/)) {
      $line =~ s/[\r\n]*$//;
      $line =~ s/^[\r\n]*//;                                  # same length as " debug: ics command input: "
      if ($verbosity >= 7) { print(strftime("%Y-%m-%d %H:%M:%S", o_gmtime()) . " output: ics comms output: $line\n"); }
      process_line($line);
    }

    ensure_alive();
    memory_load_check();
    check_relay_results();
    heartbeat();
  }
}

sub handleTERM {
  log_terminal("warning: received TERM signal");
  myExit(0);
}
$SIG{TERM}=\&handleTERM;

sub handleHUP {
  log_terminal("warning: received HUP signal");
  myExit(0);
}
$SIG{HUP}=\&handleHUP;

sub handleINT {
  log_terminal("warning: received INT signal");
  myExit(1);
}
$SIG{INT}=\&handleINT;

sub handleUSR1 {
  log_terminal("warning: received USR1 signal");
  myExit(2);
}
$SIG{USR1}=\&handleUSR1;

sub handleUSR2 {
  log_terminal("warning: received USR2 signal");
  myExit(3);
}
$SIG{USR2}=\&handleUSR2;

sub myExit {
  my ($exitVal) = @_;                                      # 2 = memoryGamesCardinality + potentialPlaceholderGame
  if (($PGN_MEMORY ne "") && ($lastPgnNum + $#memory_games + 2 > $memoryMaxGamesNum)) {
    $memoryMaxGamesNum = int($memoryMaxGamesNumBuffer * $memoryMaxGamesNum);
    refresh_pgn();
    log_terminal("debug: pgn refresh before exit");
  }
  exit($exitVal);
}

eval {
  setup_time();
  log_terminal("info: starting $0");
  if ($FLAGS) { log_terminal("alert: flags: $FLAGS"); }
  setup();
  main_loop();
  shut_down();
  myExit(1);
};
if ($@) {
  log_terminal("error: failed: $@");
  myExit(1);
}

