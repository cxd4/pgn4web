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

if ($BOT_HANDLE eq "" | $OPERATOR_HANDLE eq "") {
  die "\n$0 BOT_HANDLE BOT_PASSWORD OPERATOR_HANDLE\n\nBOT_HANDLE = handle for the bot account\nBOT_PASSWORD = password for the both account, use \"\" for a guest account\nOPERATOR_HANDLE = handle for the bot operator to send commands\n\nbot saving PGN data from live games on frechess.org\nmore help available from the operator account with \"tell BOT_HANDLE help\"\n\n";
}


sub finger {
  my ($username) = (@_);

  return (
    "unattended bot operated by $OPERATOR_HANDLE",
  );
}

our $PGN_FILE = "live.pgn";

our $VERBOSE = 0;

our $PROTECT_LOGOUT_FREQ = 45 * 60;
our $OPEN_TIMEOUT = 30;
our $LINE_WAIT_TIMEOUT = 180;


our $telnet;
our $username;
our $last_cmd_time = 0;

sub cmd_run {
  my ($cmd) = @_;
  print STDERR "info: running ics command: $cmd\n" if $VERBOSE;
  my $output = $telnet->cmd($cmd);
  $last_cmd_time = time();
}

our $pgn = "";

our $maxGamesNumDefault = 64;
our $maxGamesNum = $maxGamesNumDefault;
our @games_num = ();
our @games_white = ();
our @games_black = ();
our @games_whiteElo = ();
our @games_blackElo = ();
our @games_initialtime = ();
our @games_increment = ();
our @games_movesText = ();
our @games_result = ();
our @games_event = ();
our @games_site = ();
our @games_date = ();
our @games_round = ();
our @games_clock = ();

our $newGame_num = -1;
our $newGame_white;
our $newGame_black;
our $newGame_whiteElo;
our $newGame_blackElo;
our @newGame_moves;
our $newGame_initialtime;
our $newGame_increment;
our @newGame_emt;
our $newGame_movesText;
our $newGame_result;
our $newGame_event = "?";
our $newGame_site = "?";
our $newGame_date = "????.??.??";
our $newGame_round = "?";
our $newGame_clock = 1;

our $followMode = 0;

sub reset_games {
  cmd_run("follow");
  cmd_run("unobserve");
  $maxGamesNum = $maxGamesNumDefault;
  @games_num = ();
  @games_white = ();
  @games_black = ();
  @games_whiteElo = ();
  @games_blackElo = ();
  @games_initialtime = ();
  @games_increment = ();
  @games_movesText = ();
  @games_result = ();
  @games_event = ();
  @games_site = ();
  @games_date = ();
  @games_round = ();
  @games_clock = ();
  $newGame_event = "?";
  $newGame_site = "?";
  $newGame_date = "????.??.??";
  $newGame_round = "?";
  $newGame_clock = 1;
  $followMode = 0;

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
    unshift(@games_num, $newGame_num);
    unshift(@games_white, $newGame_white);
    unshift(@games_black, $newGame_black);
    unshift(@games_whiteElo, $newGame_whiteElo);
    unshift(@games_blackElo, $newGame_blackElo);
    unshift(@games_initialtime, $newGame_initialtime);
    unshift(@games_increment, $newGame_increment);
    unshift(@games_movesText, $newGame_movesText);
    unshift(@games_result, $newGame_result);
    unshift(@games_event, $newGame_event);
    unshift(@games_site, $newGame_site);
    unshift(@games_date, $newGame_date);
    unshift(@games_round, $newGame_round);
    unshift(@games_clock, $newGame_clock);
  } else {
    if (($games_white[$thisGameIndex] ne $newGame_white) || ($games_black[$thisGameIndex] ne $newGame_black) || ($games_whiteElo[$thisGameIndex] ne $newGame_whiteElo) || ($games_blackElo[$thisGameIndex] ne $newGame_blackElo) || ($games_initialtime[$thisGameIndex] ne $newGame_initialtime) || ($games_increment[$thisGameIndex] ne $newGame_increment)) {
      print STDERR "error: game $newGame_num mismatch when saving\n";
    } else {
      if ($games_clock[$thisGameIndex] == 0) {
        $newGame_movesText =~ s/ {[^}]*}//g;
      }
      $games_movesText[$thisGameIndex] = $newGame_movesText;
      if ($games_result[$thisGameIndex] eq "*") {
        $games_result[$thisGameIndex] = $newGame_result;
      }
    }
  }
  refresh_pgn();
}

sub save_result {
  my ($thisGameNum, $thisResult) = @_;

  my $thisGameIndex = find_gameIndex($thisGameNum);
  if ($thisGameIndex < 0) {
    print STDERR "error: missing game $thisGameNum when saving result\n";
  } else {
    $games_result[$thisGameIndex] = $thisResult;
  }
  refresh_pgn();
}

sub remove_game {
  my ($thisGameNum) = @_;
  my $thisGameIndex;

  if ($thisGameNum < 0) {
    $thisGameIndex = $maxGamesNum - 1;
  } else {
    $thisGameIndex = find_gameIndex($thisGameNum);
  }
  if ($thisGameIndex < 0) {
    print STDERR "error: missing game $thisGameNum when removing\n";
  } else {
    @games_num = @games_num[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_white = @games_white[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_black = @games_black[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_whiteElo = @games_whiteElo[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_blackElo = @games_blackElo[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_initialtime = @games_initialtime[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_increment = @games_increment[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_movesText = @games_movesText[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_result = @games_result[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_event = @games_event[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_site = @games_site[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_date = @games_date[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_round = @games_round[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    @games_clock = @games_clock[0..($thisGameIndex-1), ($thisGameIndex+1)..$maxGamesNum];
    refresh_pgn();
  }
  return $thisGameIndex;
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
    cmd_run("moves $16");
  } elsif ($line =~ /^{Game (\d+) [^}]*} (\S+)/) {
    save_result($1, $2);
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
    } elsif ($line =~ /(.*) initial time: (\d+) minutes.*increment: (\d+)/) {
      our $gameType = $1;
      $newGame_initialtime = $2 * 60;
      $newGame_increment = $3;
      if (!($gameType =~ /(standard|blitz|lightning)/)) {
        print STDERR "warning: ignored game type: $gameType\n" if $VERBOSE;
        reset_newGame();
      }
    } elsif ($line =~ /^\s*\d+\.[\s]*([^(\s]+)\s*\(([^)]+)\)[\s]+([^(\s]+)\s*\(([^)]+)\)/) {
      push(@newGame_moves, $1);
      push(@newGame_emt, time2sec($2));
      push(@newGame_moves, $3);
      push(@newGame_emt, time2sec($4));
    } elsif ($line =~ /^\s*\d+\.[\s]*([^(\s]+)\s*\(([^)]+)\)/) {
      push(@newGame_moves, $1);
      push(@newGame_emt, time2sec($2));
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
  my ($whiteClk, $blackClk, $moveNum, $i);

  $newGame_movesText = "";
  $whiteClk = $newGame_initialtime;
  $blackClk = $newGame_initialtime;
  for ($i=0; $i<=$#newGame_moves; $i++) {
    if ($i % 2 == 0) {
      $moveNum = ($i / 2) + 1;
      $whiteClk = $whiteClk + $newGame_increment - $newGame_emt[$i];
      $newGame_movesText .= "\n$moveNum. " . $newGame_moves[$i] . " {[%clk " . sec2time($whiteClk) . "]} ";
    } else {
      $blackClk = $blackClk + $newGame_increment - $newGame_emt[$i];
      $newGame_movesText .= $newGame_moves[$i] . " {[%clk " . sec2time($whiteClk) . "]}";
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
  $newGame_initialtime = "";
  $newGame_increment = "";
  @newGame_emt = ();
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
  my ($i, $thisResult, $thisWhite, $thisBlack);

  $pgn = "";
  for ($i=0; $i<$maxGamesNum; $i++) {
    if ($games_num[$i]) {
      if (($followMode == 1) && ($i == 0)) {
        $thisResult = "*";
      } else {
        $thisResult = $games_result[$i];
      }
      $thisWhite = $games_white[$i];
      $thisBlack = $games_black[$i];
      if ($thisResult eq "*") {
        $thisWhite .= " ";
        $thisBlack .= " ";
      }
      $pgn .= "[Event \"" . $games_event[$i]  . "\"]\n";
      $pgn .= "[Site \"" . $games_site[$i]  . "\"]\n";
      $pgn .= "[Date \"" . $games_date[$i]  . "\"]\n";
      $pgn .= "[Round \"" . $games_round[$i]  . "\"]\n";
      $pgn .= "[White \"" . $thisWhite . "\"]\n";
      $pgn .= "[Black \"" . $thisBlack . "\"]\n";
      $pgn .= "[Result \"" . $thisResult . "\"]\n";
      if ($games_clock[$i] == 1) {
        $pgn .= "[WhiteClock \"" . sec2time($games_initialtime[$i])  . "\"]\n";
        $pgn .= "[BlackClock \"" . sec2time($games_initialtime[$i])  . "\"]\n";
      }
      $pgn .= "[WhiteElo \"" . $games_whiteElo[$i]  . "\"]\n";
      $pgn .= "[BlackElo \"" . $games_blackElo[$i]  . "\"]\n";
      $pgn .= $games_movesText[$i];
      $pgn .= "\n$games_result[$i]\n\n";
    }
  }

  open(thisFile, ">$PGN_FILE");
  print thisFile $pgn;
  close(thisFile);
}

our @master_commands = ();
our @master_commands_helptext = ();

sub add_master_command {
  my ($command, $helptext) = @_;
  push (@master_commands, $command);
  push (@master_commands_helptext, $helptext);
}

add_master_command ("clock", "clock 0|1 (enables saving clock informantion in the PGN data)");
add_master_command ("date", "date 2012.11.10 (sets the PGN header tag date)");
add_master_command ("event", "event World Championship (sets the PGN header tag event)");
add_master_command ("file", "file live.pgn (set the filename for saving PGN data)");
add_master_command ("follow", "follow handle|/s|/b|/l (see freechess.org follow command)");
add_master_command ("forget", "forget 12 34 56 (eliminate games from PGN data)");
add_master_command ("help", "help command (show commands help)");
add_master_command ("ics", "ics server_command (runs a command on freechess.org)");
add_master_command ("list", "list (show lists of observed games)");
add_master_command ("logout", "logout 0|1 (logout from freechess.org returning the given exit value)");
add_master_command ("max", "max 64 (sets the maximum number of games for the PGN data)");
add_master_command ("observe", "observe 12 34 56 (observe games)");
add_master_command ("reset", "reset (resets observed/followed games list and setting)");
add_master_command ("round", "round 9 (sets the PGN header tag round)");
add_master_command ("site", "site Moscow RUS (sets the PGN header tag site)");
add_master_command ("status", "status (shows status summary info)");
add_master_command ("verbose", "verbose 0|1 (sets verbosity of the bot log terminal)");

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
    cmd_run("tell $OPERATOR_HANDLE error: $command");
  } elsif ($command eq "clock") {
    if ($parameters =~ /^(0|1|)$/) {
      if ($parameters =~ /^(0|1)$/) {
        $newGame_clock = $parameters;
      }
      cmd_run("tell $OPERATOR_HANDLE clock=$newGame_clock");
    } else {
      cmd_run("tell $OPERATOR_HANDLE error: invalid clock parameter");
    }
  } elsif ($command eq "date") {
    if ($parameters) {
      $newGame_date = $parameters;
      if ($newGame_date eq "\"\"") { $newGame_date = ""; }
    }
    cmd_run("tell $OPERATOR_HANDLE date=$newGame_date");
  } elsif ($command eq "event") {
    if ($parameters) {
      $newGame_event = $parameters;
      if ($newGame_event eq "\"\"") { $newGame_event = ""; }
    }
    cmd_run("tell $OPERATOR_HANDLE event=$newGame_event");
  } elsif ($command eq "file") {
    if ($parameters =~ /^[\w\d\/\\.+=_-]*$/) { # for portability only a subset of filename chars is allowed
      if ($parameters ne "") {
        $PGN_FILE = $parameters;
      }
      cmd_run("tell $OPERATOR_HANDLE file=$PGN_FILE");
    } else {
      cmd_run("tell $OPERATOR_HANDLE error: invalid file parameter");
    }
  } elsif ($command eq "follow") {
    if ($parameters =~ /^([a-zA-Z]+$|\/s|\/b|\/l)/) {
      $followMode = 1;
      cmd_run("follow $parameters");
    } elsif ($parameters eq "") {
      $followMode = 0;
      cmd_run("follow");
    } elsif ($parameters =~ /^(0|1)$/) {
      $followMode = $1;
    } elsif ($parameters ne "?") {
      cmd_run("tell $OPERATOR_HANDLE error: invalid follow parameter");
    }
    cmd_run("tell $OPERATOR_HANDLE follow=$followMode");
  } elsif ($command eq "forget") {
    my @theseGames = split(" ", $parameters);
    for (my $i=0; $i<=$#theseGames; $i++) {
      if ($theseGames[$i] =~ /\d+/) {
        if (remove_game($theseGames[$i]) < 0) {
          cmd_run("tell $OPERATOR_HANDLE error: game $theseGames[$i] not found");
        }
      } else {
        cmd_run("tell $OPERATOR_HANDLE error: invalid game $theseGames[$i]");
      }
    }
    cmd_run("tell $OPERATOR_HANDLE OK forget");
  } elsif ($command eq "help") {
    if ($parameters =~ /\S/) {
      my $par;
      my @pars = split(" ", $parameters);
      foreach $par (@pars) {
        if ($par =~ /\S/) {
          cmd_run("tell $OPERATOR_HANDLE " . detect_command_helptext(detect_command($par)));
        }
      }
    } else {
      cmd_run("tell $OPERATOR_HANDLE available commands: " . join(", ", @master_commands));
    }
  } elsif ($command eq "ics") {
    cmd_run($parameters);
    cmd_run("tell $OPERATOR_HANDLE OK ics");
  } elsif ($command eq "list") {
    cmd_run("tell $OPERATOR_HANDLE games=" . gameList());
  } elsif ($command eq "logout") {
    if ($parameters =~ /^(0|1|)$/) {
      if ($parameters eq "") {
        $parameters = 0;
      }
      cmd_run("tell $OPERATOR_HANDLE OK quit");
      cmd_run("quit");
      print STDERR "info: logout with exit value $parameters\n";
      exit $parameters;
    } else {
      cmd_run("tell $OPERATOR_HANDLE error: invalid logout parameter");
    }
  } elsif ($command eq "max") {
    if ($parameters =~ /^\d*$/) {
      if ($parameters ne "") {
        if ($parameters < $maxGamesNum) {
          for (my $i=$parameters; $i<$maxGamesNum; $i++) {
            if ($games_num[$i]) { remove_game($games_num[$i]); }
          }
        }
        $maxGamesNum = $parameters;
      }
      cmd_run("tell $OPERATOR_HANDLE maxGamesNum=$maxGamesNum");
    } else {
      cmd_run("tell $OPERATOR_HANDLE error: invalid max parameter");
    }
  } elsif ($command eq "observe") {
    my @theseGames = split(" ", $parameters);
    for (my $i=0; $i<=$#theseGames; $i++) {
      if ($theseGames[$i] =~ /\d+/) {
        if (find_gameIndex($theseGames[$i]) == -1) {
          cmd_run("observe $theseGames[$i]");
        } else {
          cmd_run("tell $OPERATOR_HANDLE error: game $theseGames[$i] already observed");
        }
      } else {
        cmd_run("tell $OPERATOR_HANDLE error: invalid game $theseGames[$i]");
      }
    }
    cmd_run("tell $OPERATOR_HANDLE OK observe");
  } elsif ($command eq "reset") {
    reset_games();
    cmd_run("tell $OPERATOR_HANDLE OK reset");
  } elsif ($command eq "round") {
    if ($parameters) {
      $newGame_round = $parameters;
      if ($newGame_round eq "\"\"") { $newGame_round = ""; }
    }
    cmd_run("tell $OPERATOR_HANDLE round=$newGame_round");
  } elsif ($command eq "site") {
    if ($parameters) {
      $newGame_site = $parameters;
      if ($newGame_site eq "\"\"") { $newGame_site = ""; }
    }
    cmd_run("tell $OPERATOR_HANDLE site=$newGame_site");
  } elsif ($command eq "status") {
    cmd_run("tell $OPERATOR_HANDLE games=" . gameList() . " maxGamesNum=$maxGamesNum file=$PGN_FILE follow=$followMode verbose=$VERBOSE event=$newGame_event site=$newGame_site date=$newGame_date round=$newGame_round clock=$newGame_clock");
  } elsif ($command eq "test") {
    print STDERR "info: executing test command\n" if $VERBOSE;
    cmd_run("tell $OPERATOR_HANDLE OK test");
  } elsif ($command eq "verbose") {
    if ($parameters =~ /^(0|1|)$/) {
      if ($parameters ne "") {
        $VERBOSE = $parameters;
      }
      cmd_run("tell $OPERATOR_HANDLE verbose=$VERBOSE");
    }
    cmd_run("tell $OPERATOR_HANDLE error: invalid verbose parameter");
  } else {
    print STDERR "warning: invalid command: $command $parameters\n" if $VERBOSE;
    cmd_run("tell $OPERATOR_HANDLE error: invalid command: $command $parameters");
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

  my @finger = finger($username);
  for (my $i=1; $i<=10; $i++) {
    cmd_run("set $i " .  ($finger[$i-1] || ""));
  }

  cmd_run("iset nowrap 1");
  cmd_run("set shout 0");
  cmd_run("set cshout 0");
  cmd_run("set open 0");
  cmd_run("set seek 0");
  cmd_run("set gin 0");
  cmd_run("set pin 0");
  cmd_run("set mailmess 0");
  cmd_run("set style 12");
  cmd_run("- channel 4");
  cmd_run("- channel 53");

  cmd_run("tell $OPERATOR_HANDLE ready");
  print STDERR "info: finished initialization\n" if $VERBOSE;
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
  }
}

eval {
  print STDERR "\n$0\n\n";
  setup();
  main_loop();
  shut_down();
};
if ($@) {
  print STDERR "error: failed: $@\n";
  exit(1);
}
