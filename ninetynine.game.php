<?php
/**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * NinetyNine implementation : © Eric Kelly <boardgamearena@useric.com>
  *
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  *
  * ninetynine.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */

require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class NinetyNine extends Table {
    function __construct() {

        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();self::initGameStateLabels(array(
                         "currentHandTrump" => 10,
                         "trickSuit" => 11,
                         "previousHandWinnerCount" => 12,
                         "currentRound" => 13,
                         "firstDealer" => 14,
                         "currentPlayer" => 15,
                         "currentDealer" => 16,
                         "handCount" => 17,
                         "playerCount" => 18,
                         "gameStyle" => 100,
                         "scoringStyle" => 101
        ));

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
    }

    protected function getGameName() {
        return "ninetynine";
    }

    /*
        setupNewGame:

        This method is called 1 time when a new game is launched.
        In this method, you must setup the game according to game rules, in order
        the game is ready to be played.

    */
    protected function setupNewGame($players, $options = array()) {
        $this->initializePlayers($players);

        /************ Start the game initialization *****/
        // Init global values with their initial values

        // Note: hand types: -1 = starting type (no trump)
        $this->clearCurrentHandTrump();

        // Set current trick suit to 4 (-1 = no trick color)
        $this->clearCurrentTrickSuit();

        // Previous Hand Winner Count
        $this->clearPreviousWinnerCount();

        // Current Round
        $this->setCurrentRound(0);

        // Hand count
        self::setGameStateInitialValue("handCount", 0);

        // Initialize dealer and first player
        $this->setupDealer($players);

        // Init game statistics
        $this->initializeStatistics();

        // Create cards
        $this->createCards();

        /************ End of the game initialization *****/
    }

    /************* Initialization helper functions ***************/

    function initializePlayers($players) {
        $sql = "DELETE FROM player WHERE 1 ";
        self::DbQuery($sql);

        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/yellow
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $default_color = array( "ff0000", "008000", "0000ff", "ffa500" );

        $start_points = 0;

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialized it there.
        $sql = "INSERT INTO player (player_id, player_score, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_color);
            $values[] = "('".$player_id."','$start_points','$color','".$player['player_canal']."','".addslashes($player['player_name'] )."','".addslashes($player['player_avatar'])."')";
        }
        $sql .= implode($values, ',');
        self::DbQuery($sql);
        self::reloadPlayersBasicInfos();
        self::setGameStateValue("playerCount", count($players));
    }

    function setupDealer($players) {
        $dealer = array_keys($players)[0];
        $firstPlayer = self::getPlayerAfter($dealer);

        self::setGameStateInitialValue('firstDealer', $dealer);
        self::setGameStateInitialValue('currentDealer', $dealer);

        // Player with the first action (starts left of dealer, then winner of trick)
        $this->setCurrentPlayer($firstPlayer);
    }

    // Initialize the statistics for the end of the game.
    // All used statistics have to be initialized.
    function initializeStatistics() {
        self::initStat("table", "handCount", 0);
        self::initStat("table", "total3WinnerHands", 0);
        self::initStat("table", "total2WinnerHands", 0);
        self::initStat("table", "total1WinnerHands", 0);
        self::initStat("table", "total0WinnerHands", 0);
        self::initStat("player", "tricksWon", 0);
        self::initStat("player", "trickWinPercentage", 0);
        if ($this->doesScoringVariantUseRounds()) {
            self::initStat("player", "roundsWon", 0);
            self::initStat("player", "roundWinPercentage", 0);
        }
        self::initStat("player", "declareCount", 0);
        self::initStat("player", "revealCount", 0);
        self::initStat("player", "declareSuccess", 0);
        self::initStat("player", "declareSuccessPercentage", 0);
        self::initStat("player", "revealSuccess", 0);
        self::initStat("player", "revealSuccessPercentage", 0);
        self::initStat("player", "successBidCount", 0);
        self::initStat("player", "successBidPercentage", 0);
    }

    // Create the deck
    function createCards() {
        $cards = array();
        // $suits = array( "club", "diamond", "spade", "heart" );
        for ($suit_id = 0; $suit_id < 4; $suit_id++) {
            // 2, 3, 4, ... K, A
            // Except we're starting with 6, as the array shows.
            // TODO: This needs to change for a 4 player game
            for ($value = 6; $value <= 14; $value++) {
                $cards[] = array('type' => $suit_id, 'type_arg' => $value, 'nbr' => 1);
            }
        }

        $this->cards->createCards($cards, 'deck');
    }

    /************** End Initialization helper functions ****************/

    /*
        getAllDatas:

        Gather all informations about current game situation (visible by the current player).

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refresh the game page (F5)
    */
    protected function getAllDatas() {
        $result = array( 'players' => array() );

        // !! We must only return informations visible by this player !!
        $player_id = self::getCurrentPlayerId();

        // Get information about players
        // Note: you can retrieve some extra field you add for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score ";
        $sql .= "FROM player ";
        $sql .= "WHERE 1 ";
        $dbres = self::DbQuery($sql);
        while ($player = mysql_fetch_assoc($dbres)) {
            $result['players'][intval($player['id'])] = $player;
        }

        // Cards in player hand
        $result['hand'] = $this->cards->getPlayerHand($player_id);
        $result['playableCards'] = $this->getPlayableCards($player_id);

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');

        // Result should be the following:
        // result = { 'players': [[id, score], ...], 'hand': [{card}, ...], 'cardsontable': [...]}

        $result['declareReveal'] = $this->getDeclareOrRevealInfo();
        $declaringOrRevealingPlayer = $result['declareReveal']['playerId'];

        $bidCardIds = $this->cards->getCardsInLocation('bid', $player_id);
        $bid = $this->getPlayerBid($player_id);

        $result['bid'] = array(
            "cards" => $bidCardIds,
            "bid" => $bid
        );

        $result['trump'] = $this->getCurrentHandTrump();
        $result['dealer'] = $this->getDealer();
        $result['currentPlayer'] = $this->getCurrentPlayer();
        $result['trickCounts'] = $this->getTrickCounts();
        $result['roundScores'] = $this->getCurrentRoundScores();
        $result['gameScores'] = $this->dbGetScores();
        $result['usesRounds'] = $this->doesScoringVariantUseRounds();
        $result['roundNum'] = $this->getCurrentRound() + 1;
        $result['handNum'] = $this->getHandCount();

        return $result;
    }

    /*
        getGameProgression:

        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).

        This method is called each time we are in a game state with "updateGameProgression" property (see states.inc.php)
    */
    function getGameProgression() {
        // Game progression: get player minimum score

        if (!$this->doesScoringVariantUseRounds()) {
            // If we're not using rounds, that means we're playing to 9 hands
            return (11 * ($this->getHandCount() - 1)) + 1;
        }

        if ($this->doesGamePlayToThreeRoundWins()) {
            // This means we're playing up to 3 rounds, not exactly 3 rounds
            $roundWins = $this->dbGetRoundWins();
            $maxWinCount = 0;
            foreach ($roundWins as $playerId => $winCount) {
                $maxWinCount = max($winCount, $maxWinCount);
            }
            return (33 * $maxWinCount) + 1;
        }

        $round = $this->getCurrentRound();
        $currentRoundScores = $this->getCurrentRoundScores();
        $maxScore = 0;
        foreach ($currentRoundScores as $playerId => $score) {
            $maxScore = max($maxScore, $score);
        }

        return (33 * $this->getCurrentRound()) + min(33, ($maxScore / 3)) + 1;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

/************** Game State helper functions ****************/

    /*
        At this place, you can put any utility methods useful for your game logic
    */

    // 1 = 3 rounds with round bonuses,
    // 2 = 3 rounds with no round bonuses
    // 3 = First to 3 rounds
    // 4 = 9 hands
    function getScoringVariant() {
        return $this->gamestate->table_globals[101];
    }

    // 1 = Junk the Joker
    // 2 = Junk the Joker (no starting trump_
    // 3 = Standard (trump chosen randomly)
    function getGameStyle() {
        return $this->gamestate->table_globals[100];
    }

    // True if the scoring variant uses round bonuses, false otherwise
    function doesGameUseRoundBonuses() {
        return $this->getScoringVariant() == 1;
    }

    // True if the scoring variant uses rounds, false otherwise
    function doesScoringVariantUseRounds() {
        return $this->getScoringVariant() != 4;
    }

    // True if the game style uses diamonds as default starting trump, false otherwise
    function doesGameUseDiamondsAsDefaultStartingTrump() {
        return $this->getGameStyle() == 1;
    }

    // True if the game style determines trump by random card draw
    function doesGameUseRandomCardDrawToDetermineTrump() {
        return $this->getGameStyle() == 3;
    }

    // True if the game style plays up to 3 round wins, not exactly 3 rounds
    function doesGamePlayToThreeRoundWins() {
        return $this->getScoringVariant() == 3;
    }

    // Increments the current hand count by one.
    function incrementHandCount() {
        self::incStat(1, "handCount");
        $this->incGameStateValue("handCount", 1);
    }

    // Gets the current hand number
    function getHandCount() {
        return $this->getGameStateValue("handCount");
    }

    // Gets the current dealer. This rotates after each hand.
    function getDealer() {
        return self::getGameStateValue("currentDealer");
    }

    // Set the current dealer.
    function setDealer($dealer) {
        self::setGameStateValue("currentDealer", $dealer);
    }

    // Rotates the dealer
    function rotateDealer() {
        $dealer = $this->getPlayerAfter($this->getDealer());
        $this->setDealer($dealer);
    }

    // Gets the current player whose turn it is to play a card
    function getPlayerCount() {
        return self::getGameStateValue("playerCount");
    }

    // Gets the current player whose turn it is to play a card
    function getCurrentPlayer() {
        return intval(self::getGameStateValue("currentPlayer"));
    }

    // Sets the current player
    function setCurrentPlayer($playerID) {
        self::setGameStateValue("currentPlayer", $playerID);
    }

    // Set the number of players who correctly bid the number of tricks they won
    function setHandWinnerCount($winnerCountOfPreviousHand) {
        $this->validatePlayerNum($winnerCountOfPreviousHand);
        self::setGameStateValue("previousHandWinnerCount", $winnerCountOfPreviousHand);
    }

    // Clears the number of previous winners
    function clearPreviousWinnerCount() {
        self::setGameStateValue("previousHandWinnerCount", -1);
    }

    // Clears whether or not the current hand has trump.
    function clearCurrentHandTrump() {
        self::setGameStateValue("currentHandTrump" , -1);
    }

    /**
        Sets trump to the given value
          0 = clubs
          1 = diamonds
          2 = spades
          3 = hearts
          -1 = none
    **/
    function setTrump($trumpSuit) {
        $this->validateSuit($trumpSuit);
        self::setGameStateValue("currentHandTrump" , $trumpSuit);
    }

    // Validates that the given argument is a valid suit number
    // Valid suits include 0-3, with -1 indicating no suit (which is also considered valid)
    function validateSuit($suit) {
        if ($suit > 3 || $suit < -1) {
            throw new feException(sprintf(_("Invalid suit: %d"), $suit));
        }
    }

    // Validate that the playerNum is within the number of valid players
    function validatePlayerNum($playerNum) {
        if ($playerNum < 0 || $playerNum > $this->getPlayerCount()) {
            throw new feException(sprintf(_("Invalid player num: %d"), $playerNum));
        }
    }

    // Get the suit that the trick was led with
    function getCurrentTrickSuit() {
        return self::getGameStateValue("trickSuit");
    }

    // Clear the suit that the trick was led with
    function clearCurrentTrickSuit() {
        self::setGameStateValue("trickSuit", -1);
    }

    // Set the trick suit.
    function setCurrentTrickSuit($trickSuit) {
        $this->validateSuit($trickSuit);
        self::setGameStateValue("trickSuit", $trickSuit);
    }

    // Get the current round number. 0 indexed.
    function getCurrentRound() {
        return self::getGameStateValue("currentRound");
    }

    // Set the current round number. 0 indexed.
    function setCurrentRound($roundNum) {
        self::setGameStateValue("currentRound", $roundNum);
    }

/************** End Game State helper functions ****************/

/************** Database access helper functions ****************/

    // Persist the player's bid to the players table
    function persistPlayerBid($playerId, $bid) {
        $sql = "UPDATE player SET player_bid=$bid WHERE player_id='$playerId'";
        $this->DbQuery($sql);
    }

    // Clear all player bids
    function clearBids() {
        $sql = "UPDATE player SET player_bid=0 WHERE 1";
        $this->DbQuery($sql);
    }

    // Get the color of a particular player
    function getPlayerColor($playerId) {
        return $this->getUniqueValueFromDB("SELECT player_color FROM player WHERE player_id='$playerId'");
    }

    // Get the player's bid from the players table
    // Returns an int representing the total number of tricks the player expected to win
    function getPlayerBid($playerId) {
        return $this->getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$playerId'");
    }

    // Persist the player's declare/reveal request to the database
    function persistPlayerDeclareRevealRequest($playerId, $decRev) {
        $sql = "UPDATE player SET player_declare_reveal_request=$decRev WHERE player_id='$playerId'";
        $this->DbQuery($sql);
    }

    // Get the player requests to declare or reveal
    // Returns:
    // { <player id> => { 'decrev': '2', 'id' => '<player id>', 'name' => 'Player Name' }}
    // Note that ALL returned values will be 'Strings', even if they are ints
    function getPlayerDeclareRevealRequests() {
        return $this->getNonEmptyCollectionFromDB("SELECT player_id id, player_name name, player_declare_reveal_request decrev FROM player");
    }

    // Clear all player's declare/reveal requests to the database
    function clearAllDeclareReveal() {
        $sql = "UPDATE player SET player_declare_reveal=0, player_declare_reveal_request=0 WHERE 1";
        $this->DbQuery($sql);
    }

    // Set the player who actually declares/reveals and persist it to the database
    function setDeclareReveal($playerId, $decRev) {
        $sql = "UPDATE player SET player_declare_reveal=$decRev WHERE player_id='$playerId'";
        $this->DbQuery($sql);
    }

    // Get the current round scores
    function getCurrentRoundScores() {
        return $this->dbGetRoundScores($this->getCurrentRound());
    }

    // Get the round scores for a particular player and round. This does
    // not include any round bonuses
    function dbGetRoundScore($playerId, $round) {
        return $this->dbGetRoundScores($round)[$playerId];
    }

    /**
        Get the round scores for a particular round. This does not include
        any round bonuses. However, round bonuses may be inferred.

        Output:
        { <player id> => 60, ... }
    **/
    function dbGetRoundScores($round) {
        if ($round < 0) {
            throw new feException(_("Invalid round"));
        }
        $roundScores = $this->getCollectionFromDB("SELECT player_id, score FROM round_scores WHERE round_number='$round'", true);
        $result = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($roundScores as $playerId => $score) {
            $result[$playerId] = intval($score);
        }
        // Default to 0 if we have no values in the db
        if (count($result) != count($players)) {
            foreach ($players as $playerId => $player) {
                $result[$playerId] = 0;
            }
        }
        return $result;
    }

    // Set the score of a player for the current round
    function dbSetRoundScore($playerId, $score) {
        $round = $this->getCurrentRound();
        $rowId = $this->getUniqueValueFromDB("SELECT id FROM round_scores WHERE player_id='$playerId' AND round_number='$round'");
        if ($rowId == null) {
            $this->DbQuery("INSERT INTO round_scores (round_number, player_id, score) VALUES ($round, $playerId, $score)");
        } else {
            $this->DbQuery("UPDATE round_scores SET score='$score' WHERE id='$rowId'");
        }
    }

    /**
        Get the game scores for each player. This returns the total score
        thus far. This includes round bonuses, if applicable.
    **/
    function dbGetScores() {
        $scores = $this->getCollectionFromDB("SELECT player_id, player_score FROM player", true);
        $result = array();
        foreach ($scores as $playerId => $score) {
            $result[$playerId] = intval($score);
        }
        return $result;
    }

    // Get a particular player's game score
    function dbGetScore($playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$playerId'"));
    }

    // Set the total game score for a particular player
    function dbSetScore($playerId, $count) {
        $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$playerId'");
    }

    // increment score (can be negative too)
    function dbIncScore($playerId, $inc) {
        $count = $this->dbGetScore($playerId);
        if ($inc != 0) {
            $count += $inc;
            $this->dbSetScore($playerId, $count);
        }
        return $count;
    }

    // Set aux score
    // This is the score used for tiebreaking
    function dbSetAuxScore($playerId, $count) {
        $this->DbQuery("UPDATE player SET player_score_aux='$count' WHERE player_id='$playerId'");
    }

    // This is used for determining how far into the game we are
    // Returns:
    // { <player id> => 2, ... }
    function dbGetRoundWins() {
        $result = array();
        $players = self::loadPlayersBasicInfos();
        $roundScores = $this->getObjectListFromDB("SELECT player_id, score, round_number round FROM round_scores WHERE 1");

        // Default to 0
        foreach ($players as $playerId => $player) {
            $result[$playerId] = 0;
        }

        foreach ($roundScores as $row) {
            if (intval($row['score']) >= 100) {
                // Increment the round win count
                $result[$row['player_id']] += 1;
            }
        }

        return $result;
    }

    /**
        Gets the 'final' declare/reveal information, after it has been decided.

        Returns:
        { <Player id> => { 'name' => 'Player name', 'decrev': 2 } }
    **/
    function getDeclareRevealPlayerInfo() {
        $output = array();
        $result = $this->getCollectionFromDB("SELECT player_id id, player_name name, player_declare_reveal decrev FROM player WHERE player_declare_reveal != 0");
        if (count($result) > 1) {
            throw new feException(_("Invalid game state - multiple declaring or revealing players"));
        } else if (count($result) == 1) {
            $playerId = array_keys($result)[0];
            $playerName = $result[$playerId]['name'];
            $decRev = intval($result[$playerId]['decrev']);
            $output[$playerId] = array(
                'name' => $playerName,
                'decrev' => $decRev
            );
        }
        return $output;
    }

/************** End Database access helper functions ****************/

/************** Card helper functions ****************/

    // Order of id: array( "club", "diamond", "spade", "heart" );
    function getCardBidValue($card) {
        switch ($card['type']) {
            case 0:
                return 3;
            case 1:
                return 0;
            case 2:
                return 1;
            case 3:
                return 2;
            default:
                throw new feException(sprintf(_("Unknown suit: %s"), $card['type']));
        }
    }

    function getCardValue($card, $suitLed, $trumpSuit) {
        if ($card['type'] != $suitLed) {
            if ($card['type'] == $trumpSuit) {
                return 100 + $card['type_arg'];
            }
            return 0;
        } else {
            return $card['type_arg'];
        }
    }

    // Get the name of the given suit
    // [0 = clubs, ... 3 = hearts]
    function getSuitName($suit) {
        $this->validateSuit($suit);
        return $this->suits[$suit]['nametr'];
    }

    // Get the pluralized name of the given suit
    // [0 = clubs, ... 3 = hearts]
    function getPluralSuitName($suit) {
        $this->validateSuit($suit);
        return $this->suits[$suit]['pluralname'];
    }

/************** End Card helper functions ****************/

/************** Other helper functions ****************/

    /**
        Get all that is needed to render the player declaring/revealing

        Returns:
        {
            "playerId" => 0,
            "playerName" => "Player name",
            "playerColor" => "",
            "cards" => [ <card array> ], // Empty if only declaring
            "bid" => [ <card array> ],
            "decRev" => 1 // 0 = none, 1 = declare, 2 = reveal

        }
    **/
    function getDeclareOrRevealInfo() {
        $result = $this->getDeclareRevealPlayerInfo();

        // Figure out who wants to declare or reveal
        $declareReveal = array(
            "playerId" => 0, // Player declaring or revealing
            "playerName" => "",
            "playerColor" => "",
            "cards" => array(), // If the player is revealing, this will have cards
            "bid" => array(), // If the player is only declaring, this will have cards
            "decRev" => 0
        );
        $declaringOrRevealingPlayer = 0;
        if (count($result) > 0) {
            $declaringOrRevealingPlayer = array_keys($result)[0];
            $declareReveal['playerId'] = $declaringOrRevealingPlayer;
            $declareReveal['playerName'] = $result[$declaringOrRevealingPlayer]['name'];
            $declareReveal['playerColor'] = $this->getPlayerColor($declaringOrRevealingPlayer);
            if ($result[$declaringOrRevealingPlayer]['decrev'] >= 1) {
                $declareReveal['bid'] =
                    $this->cards->getCardsInLocation('bid', $declaringOrRevealingPlayer);
                $decRev = 1;
            }
            if ($result[$declaringOrRevealingPlayer]['decrev'] == 2) {
                $declareReveal['cards'] =
                    $this->cards->getPlayerHand($declaringOrRevealingPlayer);
                $decRev = 2;
            }
            $declareReveal['decRev'] = $decRev;
        }

        return $declareReveal;
    }

    /**
         Returns { <player id> => 2, <player id> => ... }
         for the current trick
     **/
    function getTrickCounts() {
        $tricksWon = array();
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $cardsWon = $this->cards->countCardInLocation('cardswon', $playerId);
            $tricksWon[$playerId] = $cardsWon / 3;
        }
        return $tricksWon;
    }

    /**
      Gets the dealer for the start of the round. Returns the player id corresponding
      to the dealer for the current round.
    **/
    function getRoundDealer() {
        $dealer = self::getGameStateValue("firstDealer");
        $round = $this->getCurrentRound();
        $basicPlayerInfo = self::loadPlayersBasicInfos();

        $firstDealerPosition = $basicPlayerInfo[$dealer]['player_no'];

        // Stupidly, player position is 1 indexed. So I have to do this weird logic.
        $actualDealerPosition = 1 +
            (($round + ($firstDealerPosition - 1)) % count($basicPlayerInfo));

        foreach ($basicPlayerInfo as $playerId => $player) {
            if ($player['player_no'] == $actualDealerPosition) {
                return $playerId;
            }
        }

        throw new feException(sprintf(_("Incorrect calculation of dealer: %d"), $actualDealerPosition));
    }

    /**
        Get the trump suit for the current hand
        returns:
          0 = clubs
          1 = diamonds
          2 = spades
          3 = hearts
          null = none
    **/
    function getCurrentHandTrump() {
        if ($this->doesGameUseRandomCardDrawToDetermineTrump()) {
            $currentTrump = self::getGameStateValue("currentHandTrump");
            if ($currentTrump >= 4 || $currentTrump < 0) {
                return null;
            } else {
                return $currentTrump;
            }
        }
        $prevWinnerCount = self::getGameStateValue("previousHandWinnerCount");
        if ($prevWinnerCount < 0 || $prevWinnerCount > 3) {
            if ($this->doesGameUseDiamondsAsDefaultStartingTrump()) {
                return 1;
            } else {
                return null;
            }
        }
        return ($prevWinnerCount + 1) % 4;
    }

    // Chose a random trump suit. null is no trump. This will also notify all the
    // players that trump was selected.
    function setRandomTrump() {
        // There are 36 normal cards, and 1 joker
        // The first 9 normal cards are clubs, the second 9 normal cards are diamonds...
        $randomCard = bga_rand(0, 36);

        // Using 36 to represent the joker
        if ($randomCard == 36) {
            self::notifyAllPlayers('trumpSelected', clienttranslate('Joker was selected. This is a no trump hand.'), array());
            self::setGameStateValue("currentHandTrump", -1);
        } else {
            $suit = intdiv($randomCard, 9);
            $suitName = $this->getPluralSuitName($suit);
            $trump = $suitName;
            // 9s are also considered to be no trump
            $cardVal = ($randomCard % 9) + 6;
            $cardRank = $this->rank_name[$cardVal];
            if ($cardVal == 9) {
                self::setGameStateValue("currentHandTrump", -1);
                $trump = "None";
            } else {
                self::setGameStateValue("currentHandTrump", $suit);
            }
            self::notifyAllPlayers('trumpSelected', clienttranslate('${trump_rank} of ${trump_suit} selected. Trump is ${trump}'), array(
                'trump_suit' => $suitName,
                'trump_rank' => $cardRank,
                'trump' => $trump
            ));
        }
    }

    // Return the list of valid playable cards in the given player's hand
    function getPlayableCards($playerId) {
        $cardsInHand = $this->cards->getPlayerHand($playerId);
        $currentTrickSuit = $this->getFirstPlayedSuit();
        if ($currentTrickSuit == null) {
            // All cards in the hand are valid to play
            return $cardsInHand;
        }
        // If we have cards in our hand of the led suit, return those
        $cardsOfLedSuit = array_filter($cardsInHand, function ($card) use ($currentTrickSuit) {
            return $card['type'] == $currentTrickSuit;
        });
        if (count($cardsOfLedSuit) == 0) {
            return $cardsInHand;
        }
        return $cardsOfLedSuit;
    }

    // Return players => direction (N/S/E/W) from the point of view
    //  of current player (current player must be on south)
    function getPlayersToDirection() {
        $result = array();

        $players = self::loadPlayersBasicInfos();
        $nextPlayer = self::createNextPlayerTable(array_keys($players));

        $current_player = self::getCurrentPlayerId();

        $directions = array('S', 'W', 'E');

        if (!isset($nextPlayer[$current_player])) {
            // Spectator mode: take any player for south
            $player_id = $nextPlayer[0];
            $result[$player_id] = array_shift($directions);
        } else {
            // Normal mode: current player is on south
            $player_id = $current_player;
            $result[$player_id] = array_shift($directions);
        }

        while (count($directions) > 0) {
            $player_id = $nextPlayer[$player_id];
            $result[$player_id] = array_shift($directions);
        }
        return $result;
    }

/************** End Other helper functions ****************/


//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    /*
        Each time a player is doing some game action, one of this method below is called.
        (note: each method below correspond to an input method in ninetynine.action.php)
    */

    function submitBid($card_ids, $decrev) {
        self::checkAction("submitBid");

        // Check that the cards are actually in the current user's hands.
        $player_id = self::getCurrentPlayerId();

        if (count($card_ids) != 3) {
            throw new feException(self::_("You must bid exactly 3 cards"));
        }

        $cards = $this->cards->getCards($card_ids);

        if (count($cards) != 3)
            throw new feException(self::_("Some of these cards don't exist"));

        // When a player plays a card in front of him on the table:
        $bidValue = 0;
        foreach ($cards as $bidCard) {
            if ($bidCard['location'] != 'hand' || $bidCard['location_arg'] != $player_id)
                throw new feException(self::_("Some of these cards are not in your hand"));

            $this->cards->moveCard($bidCard['id'], 'bid', $player_id);

            $bidValue += $this->getCardBidValue($bidCard);
        }

        $bidCards = $this->cards->getCardsInLocation('bid', $player_id);

        // Notify the player so we can make these cards disapear
        self::notifyPlayer($player_id, "bidCards", "", array(
            "cards" => $card_ids,
            "bidValue" => $bidValue
        ));

        $this->persistPlayerBid($player_id, $bidValue);
        $this->declareOrReveal($decrev);

        $this->gamestate->setPlayerNonMultiactive($player_id, "biddingDone");
    }

    function declareOrReveal($declareOrReveal) {
        if ($declareOrReveal < 0 || $declareOrReveal > 2) {
            throw new feException(sprintf(_("Invalid declare or reveal: %d"), $declareOrReveal));
        }

        // Check that the cards are actually in the current user's hands.
        $playerId = self::getCurrentPlayerId();

        $this->persistPlayerDeclareRevealRequest($playerId, $declareOrReveal);
    }

    // Play a card from the active player's hand
    function playCard($card_id) {
        $player_id = self::getActivePlayerId();
        $this->playCardFromPlayer($card_id, $player_id);
    }

    // Play a card from player hand
    function playCardFromPlayer($card_id, $player_id) {
        self::checkAction("playCard");

        // Get all cards in player hand
        // (note: we must get ALL cards in player's hand in order to check if the card played is correct)

        $playerhand = $this->cards->getPlayerHand($player_id);

        // This line may not be needed
        $currentTrump = $this->getCurrentHandTrump();

        // Returns the 'bottom' card of the location
        $firstPlayedSuit = $this->getFirstPlayedSuit();
        $firstCardOfTrick = $firstPlayedSuit == null;

        // Check that the card is in this hand
        $cardIsInPlayerHand = false;
        $currentCard = null;
        $atLeastOneCardOfCurrentTrickSuit = false;
        foreach ($playerhand as $card) {
            if ($card['id'] == $card_id) {
                $cardIsInPlayerHand = true;
                $currentCard = $card;
            }

            if ($card['type'] == $firstPlayedSuit) {
                $atLeastOneCardOfCurrentTrickSuit = true;
            }
        }
        if (!$cardIsInPlayerHand) {
            throw new feException(_("This card is not in your hand"));
        }

        if ($firstCardOfTrick) {
            $this->setCurrentTrickSuit($currentCard['type']);
            // If this is the first card of the trick, any cards can be played
        } else if ($atLeastOneCardOfCurrentTrickSuit &&
                   $currentCard['type'] != $firstPlayedSuit) {
            throw new feException(sprintf(self::_("You must play a %s"), $this->getSuitName($firstPlayedSuit)), true);
        }

        // Checks are done! now we can play our card
        $this->cards->moveCard($card_id, 'cardsontable', $player_id);

        // And notify
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${rank_displayed} ${suit_displayed}'), array(
            'i18n' => array('suit_displayed', 'rank_displayed'),
            'card_id' => $card_id,
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'rank' => $currentCard['type_arg'],
            'rank_displayed' => $this->rank_label[$currentCard['type_arg']],
            'suit' => $currentCard['type'],
            'suit_displayed' => $this->formatSuitText($currentCard),
            'currentPlayer' => $this->getCurrentPlayer()
        ));

        // Next player
        $this->gamestate->nextState('playCard');
    }

/************** Player Action helper functions ****************/

    // format an html string, ready to display in a notif_
    function formatSuitText($card) {
        return '<span class="bgann_icon bgann_suit'.$card['type'] . '"></span>';
    }

    // Return the suit of the card which was led for the trick
    function getFirstPlayedSuit() {
        $firstPlayedSuit = null;
        $firstCardOfTrick = $this->cards->countCardInLocation('cardsontable') == 0;

        if (!$firstCardOfTrick) {
            $firstPlayedSuit = $this->getCurrentTrickSuit();
        }
        return $firstPlayedSuit;
    }

    // Returns the card associated with the trick winner
    // The trick winner id is the location_arg of the card
    function getTrickWinner() {
        // This is the end of the trick
        $cardsOnTable = $this->cards->getCardsInLocation('cardsontable');

        if (count($cardsOnTable) != 3) {
            throw new feException(_("Invalid trick card count"));
        }

        $bestValue = 0;
        $bestValueCard = null;

        $currentTrickSuit = $this->getCurrentTrickSuit();
        $trumpSuit = $this->getCurrentHandTrump();

        foreach ($cardsOnTable as $card) {
            $cardVal = $this->getCardValue($card, $currentTrickSuit, $trumpSuit);
            if ($bestValue <= $cardVal) {
                $bestValue = $cardVal;
                $bestValueCard = $card;
            }
        }

        return $bestValueCard;
    }

/************** End Player Action helper functions ****************/

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defines as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stGameSetup() {
        $this->gamestate->nextState();
    }

    function stNewRound() {
        $this->setDealer($this->getRoundDealer());
        $dealer = $this->getDealer();
        $firstPlayer = $this->getPlayerAfter($dealer);
        $this->setCurrentPlayer($firstPlayer);
        if ($this->doesScoringVariantUseRounds()) {
            $currentRoundName = $this->getCurrentRound() + 1;
            self::notifyAllPlayers('newRound', clienttranslate('Starting round ${round_num}'), array(
                'dealer' => $dealer,
                'round_num' => $currentRoundName,
                'firstPlayer' => $firstPlayer
            ));
        } else {
            self::notifyAllPlayers('newRound', '', array(
                'dealer' => $dealer,
                'hand_num' => $this->getHandCount(),
                'firstPlayer' => $firstPlayer
            ));
        }
        $this->gamestate->nextState();
    }

    function stNewHand() {

        $this->incrementHandCount();

        $handCount = $this->getHandCount();

        if ($this->doesGameUseRandomCardDrawToDetermineTrump()) {
            $this->setRandomTrump();
        }

        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');
        // Deal 12 cards to each players
        // Create deck, shuffle it and give 12 initial cards
        $players = self::loadPlayersBasicInfos();
        $dealer = $this->getDealer();
        $firstPlayer = $this->getPlayerAfter($dealer);
        $this->setCurrentPlayer($firstPlayer);
        $trump = $this->getCurrentHandTrump();
        $usesRounds = $this->doesScoringVariantUseRounds();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(12, 'deck', $player_id);
            // Notify player about his cards
            self::notifyPlayer($player_id, 'newHand', '', array(
              'cards' => $cards,
              'dealer' => $dealer,
              'firstPlayer' => $firstPlayer,
              'hand_num' => $handCount,
              'usesRounds' => $usesRounds,
              'trump' => $trump));
        }
        self::notifyAllPlayers('newHandState', '', array(
              'dealer' => $dealer,
              'firstPlayer' => $firstPlayer,
              'hand_num' => $handCount,
              'usesRounds' => $usesRounds,
              'trump' => $trump
        ));

        $this->gamestate->nextState();
    }

    function stBidding() {
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $this->giveExtraTime($player_id);
        }
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stDeclareOrReveal() {
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $this->giveExtraTime($player_id);
        }
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stCheckDeclareOrReveal() {

        $this->assignDeclareRevealPlayer();
        $declareReveal = $this->getDeclareOrRevealInfo();
        $declaringOrRevealingPlayer = $declareReveal['playerId'];

        // Record declare/reveal stats
        $declareRevealType = $declareReveal['decRev'];
        if ($declareRevealType == 1) {
            self::incStat(1, "declareCount", $declaringOrRevealingPlayer);
        } else if ($declareRevealType == 2) {
            self::incStat(1, "revealCount", $declaringOrRevealingPlayer);
        }

        // Inform people who goes first
        $dealerId = $this->getDealer();
        $firstPlayer = $this->getPlayerAfter($dealerId);
        $this->setCurrentPlayer($firstPlayer);

        $players = self::loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $declaring = $playerId == $declaringOrRevealingPlayer && $declareReveal['decRev'] == 1;
            $revealing = $playerId == $declaringOrRevealingPlayer && $declareReveal['decRev'] == 2;

            $bidCardIds = $this->cards->getCardsInLocation('bid', $playerId);
            $cardIds = $this->cards->getPlayerHand($playerId);
            $bid = $this->getPlayerBid($playerId);

            // Update everyone with current cards & visibility
            // Notify the player so we can make these cards disapear
            self::notifyPlayer($playerId, "biddingComplete", "", array(
                "cards" => $cardIds,
                "bid" => array(
                    "cards" => $bidCardIds,
                    "bid" => $bid,
                    "declare" => $declaring,
                    "reveal" => $revealing
                )
            ));
        }

        // Update everyone with current cards & visibility
        self::notifyAllPlayers("biddingCompleteState", "", array(
            "declareReveal" => $declareReveal,
            "dealer" => $dealerId,
            "firstPlayer" => $firstPlayer
        ));

        $this->gamestate->changeActivePlayer($firstPlayer);
        $this->giveExtraTime($firstPlayer);

        $this->gamestate->nextState("startTrickTaking");
    }

    function stNextPlayer() {
        // Active next player OR end the trick and go to the next trick OR end the hand
        if ($this->cards->countCardInLocation('cardsontable') == 3) {
            $winningCard = $this->getTrickWinner();
            $winningPlayer = $winningCard['location_arg'];

            // Winning tricks statistics
            self::incStat(1, "tricksWon", $winningPlayer);

            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer($winningPlayer);
            $this->setCurrentPlayer($winningPlayer);

            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $winningPlayer);

            $trickCounts = $this->getTrickCounts();
            $declareReveal = $this->getDeclareOrRevealInfo();
            $decRevPlayerId = $declareReveal['playerId'];

            // Notify
            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers('trickWin', clienttranslate('${player_name} wins the trick'), array(
                    'player_id' => $winningPlayer,
                    'player_name' => $players[$winningPlayer]['player_name'],
                    'decRevPlayerId' => $decRevPlayerId,
                    'playerTrickCounts' => $trickCounts
            ));

            $this->clearCurrentTrickSuit();

            if ($this->cards->countCardInLocation('hand') == 0) {
                // End of the hand
                $this->gamestate->nextState("endHand");
            } else {
                // End of the trick
                $this->gamestate->nextState("nextTrick");
            }
        } else {
            // Standard case (not the end of the trick)
            // => just active the next player
            $player_id = self::activeNextPlayer();
            $this->setCurrentPlayer($player_id);
            self::notifyAllPlayers('currentPlayer', '', array(
                'currentPlayer' => $this->getCurrentPlayer()
            ));
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function argPlayableCards() {
        $player_id = self::getActivePlayerId();
        return array(
            '_private' => array(
                'active' => array(
                    'playableCards' => self::getPlayableCards($player_id)
                )
            )
        );
    }

    function stEndHand() {

        $handScoreInfo = $this->generateScoreInfo();
        $madeBidCount = $handScoreInfo['correctBidCount'];
        $this->setHandWinnerCount($madeBidCount);

        // Record statistics for how many hands had 0/1/2/3 winners
        self::incStat(1, "total{$madeBidCount}WinnerHands");

        // Count and score points, then end the round / game or go to the next hand.
        $players = self::loadPlayersBasicInfos();

        // Check if anyone exceeded 100
        $countPlayersExceeded100 = 0;
        foreach ($handScoreInfo['currentScore'] as $playerId => $currentScore) {
            if ($currentScore + $handScoreInfo['total'][$playerId] >= 100) {
                $countPlayersExceeded100++;
            }
        }

        $decRev = $this->getDeclareOrRevealInfo();
        foreach ($handScoreInfo['bonus'] as $playerId => $bonus) {
            if ($bonus > 0) {
                // Made the bid
                self::incStat(1, "successBidCount", $playerId);
                if ($playerId == $decRev['playerId']) {
                    if ($decRev['decRev'] == 2) {
                        self::incStat(1, "revealSuccess", $playerId);
                    } else if ($decRev['decRev'] == 1) {
                        self::incStat(1, "declareSuccess", $playerId);
                    }
                }
            }
        }

        // Apply scores to player
        foreach ($handScoreInfo['total'] as $player_id => $points) {
            // Calculate the round score
            $playerRoundScore = $handScoreInfo['currentScore'][$player_id] +
                $handScoreInfo['total'][$player_id];
            $this->dbSetRoundScore($player_id, $playerRoundScore);

            if ($points != 0) {
                if ($this->doesScoringVariantUseRounds()) {
                    if ($playerRoundScore >= 100) {
                        self::incStat(1, "roundsWon", $player_id);

                        if ($this->doesGameUseRoundBonuses()) {
                            $points += (40 - ($countPlayersExceeded100 * 10));
                        }
                    }
                }

                $this->dbIncScore($player_id, $points);

                self::notifyAllPlayers("points", clienttranslate('${player_name} bid ${bid} and gets ${points} points'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'bid' => $handScoreInfo['bid'][$player_id],
                    'points' => $points,
                    'roundScore' => $playerRoundScore
                ));
            } else {
                // No point lost (just notify)
                self::notifyAllPlayers("points", clienttranslate('${player_name} bid ${bid} but did not get any points'), array (
                    'player_id' => $player_id,
                    'bid' => $handScoreInfo['bid'][$player_id],
                    'player_name' => $players[$player_id]['player_name']));
            }
        }
        $newScores = $this->getCurrentRoundScores();
        $gameScores = $this->dbGetScores();
        self::notifyAllPlayers("newScores", '', array('newScores' => $newScores, 'gameScores' => $gameScores));

        // Test if this is the end of the round
        if ($countPlayersExceeded100 > 0 && $this->doesScoringVariantUseRounds()) {
            $this->gamestate->nextState("endRound");
        } else {
            // Display the score for the hand
            $handScoreInfo = $this->generateScoreInfo();
            $scoreTable = $this->createHandScoringTable($handScoreInfo);

            if (!$this->doesScoringVariantUseRounds() && $this->getHandCount() == 9) {
                $this->notifyScore($scoreTable, clienttranslate('Final Score'));
                $this->finalizeGameEndState();
                $this->gamestate->nextState("gameEnd");
                return;
            }

            $this->notifyScore($scoreTable, clienttranslate('Hand Score'));

            $this->clearAllDeclareReveal();
            $this->clearBids();

            $this->rotateDealer();
            $this->gamestate->nextState("newHand");
        }
    }

    function stEndRound() {
        $handScoreInfo = $this->generateScoreInfo();
        $roundScoreInfo = $this->generateRoundScoreInfo();

        // Display Round score
        $scoreTable = $this->createRoundScoringTable($handScoreInfo, $roundScoreInfo);
        $this->notifyScore($scoreTable, clienttranslate('End of Round Score'));

        $this->clearAllDeclareReveal();
        $this->clearBids();

        // Test if this is the end of the game
        $round = $this->getCurrentRound();

        $isEndOfGame = false;
        if ($this->doesGamePlayToThreeRoundWins()) {
            // I'm arbitrarily deciding that each round is worth exactly
            // 100 points here so that the scores between game variants are
            // somewhat similar. Ties are of course broken by total score
            $roundWins = $this->dbGetRoundWins();
            foreach ($roundWins as $playerId => $winCount) {
                $this->dbSetScore($playerId, $winCount * 100);
                if ($winCount == 3) {
                    $isEndOfGame = true;
                }
            }
        } else {
            // If we don't play to 3 rounds, that means we play exactly 3 rounds
            $isEndOfGame = $round == 2;
        }

        if ($isEndOfGame) {
            // End of the game
            $this->finalizeGameEndState();
            $this->gamestate->nextState("gameEnd");
            return;
        } else {
            $nextRound = $round + 1;
            $this->setCurrentRound($round + 1);
            $this->clearPreviousWinnerCount();
            $this->gamestate->nextState("newRound");
        }
    }

/************** Game state helper functions ****************/

    // Using all the player's declare/reveal requests, determine which player
    // 'wins' the declare/reveal
    function assignDeclareRevealPlayer() {
        $result = $this->getPlayerDeclareRevealRequests();

        $dealer = $this->getDealer();
        $firstPlayer = $this->getPlayerAfter($dealer);

        // This returns a table with an extra key at 0, which is the first
        // player to play
        $nextPlayerTable = $this->getNextPlayerTable();

        $firstPlayerToDeclare = 0;
        $firstPlayerToReveal = 0;

        $checkPlayer = $firstPlayer;
        for ($i = 0; $i < count($nextPlayerTable) - 1; $i++) {
            $decRevVal = intval($result[$checkPlayer]['decrev']);
            if ($decRevVal == 2) {
                $firstPlayerToReveal = $checkPlayer;
                break;
            } else if ($firstPlayerToDeclare == 0 &&
                       $firstPlayerToReveal == 0 &&
                       $decRevVal == 1) {
                $firstPlayerToDeclare = $checkPlayer;
            }
            $checkPlayer = $this->getPlayerAfter($checkPlayer);
        }

        if ($firstPlayerToReveal != 0) {
            $this->setDeclareReveal($firstPlayerToReveal, 2);
        } else if ($firstPlayerToDeclare != 0) {
            $this->setDeclareReveal($firstPlayerToDeclare, 1);
        }

        // Inform everyone who attempted a declare or reveal
        foreach ($result as $playerId => $row) {
            $decRevRequest = intval($row['decrev']);
            if ($decRevRequest <= 0) {
                continue;
            }
            if ($decRevRequest == 1) {
                if ($firstPlayerToDeclare != $playerId || $firstPlayerToReveal != 0) {
                    self::notifyAllPlayers('declareRevealResult', clienttranslate('${player_name} attempted to declare their bid'), array(
                        'player_name' => $row['name']
                    ));
                } else if ($firstPlayerToDeclare == $playerId && $firstPlayerToReveal == 0) {
                    self::notifyAllPlayers('declareRevealResult', clienttranslate('${player_name} declared his bid'), array(
                        'player_name' => $row['name']
                    ));
                }
            } else {
                if ($firstPlayerToReveal == $playerId) {
                    self::notifyAllPlayers('declareRevealResult', clienttranslate('${player_name} revealed their hand and bid'), array(
                        'player_name' => $row['name']
                    ));
                } else {
                    self::notifyAllPlayers('declareRevealResult', clienttranslate('${player_name} attempted to reveal'), array(
                        'player_name' => $row['name']
                    ));
                }
            }
        }
    }

    function finalizeGameEndState() {
        // This will get the scores from Round 3
        $roundScoreInfo = $this->generateRoundScoreInfo();

        $this->finalizeGameScore($roundScoreInfo);

        $roundScores = $this->getCurrentRoundScores();
        $gameScores = $this->dbGetScores();
        self::notifyAllPlayers("newScores", '', array('newScores' => $roundScores, 'gameScores' => $gameScores));

        // Calculate statistics
        $this->finalizeStatistics();
    }

    // Update the final game scores using the scoring information from the last round
    // (The scoring information for the last round contains the calculated total scores)
    function finalizeGameScore($roundScoreInfo) {
        if ($this->doesScoringVariantUseRounds()) {
            foreach ($roundScoreInfo['gameScore'] as $playerId => $score) {
                if ($this->doesGamePlayToThreeRoundWins()) {
                    $this->dbSetScore($playerId, 100 * $roundScoreInfo['roundWins'][$playerId]);
                    $this->dbSetAuxScore($playerId, $score);
                } else {
                    $this->dbSetScore($playerId, $score);
                }
            }
        }
    }

    /**
        Return an array containing all the information needed
        to display the score at the end of a hand. This includes
        trick information, bonus information, and total game score.

        Output:
        {
            'name': {
                <player id> => 'Player name'
                ...
            },
            'bid': {
                <player id> => 1
                ...
            },
            'tricks': {
                <player id> => 1
                ...
            },
            'correctBidCount': {
                <player id> => 1
                ...
            },
            'bonus': {
                <player id> => 20
                ...
            },
            'decrev': {
                <player id> => 30
                ...
            },
            'total': { // Total round score so far
                <player id> => 56
                ...
            },
            'currentScore': { // Total game score so far
                <player id> => 146
                ...
            }
        }
    **/
    function generateScoreInfo() {
        $players = self::loadPlayersBasicInfos();
        $playerBids = array();
        $playerNames = array();
        $roundScore = array();
        $tricksWon = $this->getTrickCounts();
        $round = $this->getCurrentRound();
        foreach ($players as $player_id => $player) {
            $bid = $this->getPlayerBid($player_id);
            $playerBids[$player_id] = $bid;
            $playerNames[$player_id] = $player['player_name'];
            $roundScore[$player_id] = $this->dbGetRoundScore($player_id, $round);
        }
        $decRev = $this->getDeclareOrRevealInfo();
        $decRevPlayer = null;
        $decRevVal = $decRev['decRev'];
        if ($decRevVal != 0) {
            $decRevPlayer = $decRev['playerId'];
        }
        return $this->generateScoreInfoHelper($playerNames, $playerBids,
            $tricksWon, $decRevVal, $decRevPlayer, $roundScore);
    }

    function generateScoreInfoHelper($playerNames, $bid, $tricks, $decRev,
            $decRevPlayer, $currentScores) {
        $result = array();
        $total = array();
        $result['name'] = array();
        foreach ($playerNames as $playerId => $name) {
            $result['name'][$playerId] = $name;
        }
        $result['bid'] = array();
        foreach ($bid as $playerId => $playerBid) {
            $result['bid'][$playerId] = intval($playerBid);
        }
        $madeBid = array();
        $result['tricks'] = array();
        foreach ($tricks as $playerId => $trickCount) {
            $result['tricks'][$playerId] = $trickCount;
            $total[$playerId] = $trickCount;
            if ($trickCount == $bid[$playerId]) {
                $madeBid[] = $playerId;
            }
        }
        $result['correctBidCount'] = count($madeBid);
        $handBonus = 40 - (count($madeBid) * 10);
        $result['bonus'] = array();
        foreach ($tricks as $playerId => $trickCount) {
            if ($trickCount == $bid[$playerId]) {
                $result['bonus'][$playerId] = $handBonus;
                $total[$playerId] += $handBonus;
            } else {
                $result['bonus'][$playerId] = 0;
            }
        }
        if ($decRevPlayer != null) {
            $result['decrev'] = array();
            $madeBid = $tricks[$decRevPlayer] == $bid[$decRevPlayer];
            $pointSwing = 30;
            if ($decRev == 2) {
                $pointSwing = 60;
            }
            foreach ($playerNames as $playerId => $name) {
                if ($playerId == $decRevPlayer) {
                    if ($madeBid) {
                        $result['decrev'][$playerId] = $pointSwing;
                        $total[$playerId] += $pointSwing;
                    } else {
                        $result['decrev'][$playerId] = 0;
                    }
                } else {
                    if (!$madeBid) {
                        $result['decrev'][$playerId] = $pointSwing;
                        $total[$playerId] += $pointSwing;
                    } else {
                        $result['decrev'][$playerId] = 0;
                    }
                }
            }
        }
        $result['total'] = $total;
        $result['currentScore'] = array();
        foreach ($currentScores as $playerId => $score) {
            $result['currentScore'][$playerId] = intval($score);
        }
        return $result;
    }

    /**
        Given the hand score information, create a table to display the
        scores.
    **/
    function createHandScoringTable($scoreInfo) {
        $players = self::loadPlayersBasicInfos();
        $table = array();
        $firstRow = array('');
        foreach ($players as $player_id => $player) {
            $firstRow[] = array('str' => '${player_name}',
                                'args' => array('player_name' => $player['player_name']),
                                'type' => 'header');
        }
        $table[] = $firstRow;

        $bidRow = array(clienttranslate("Bid"));
        foreach ($players as $player_id => $player) {
            $bidRow[] = $scoreInfo['bid'][$player_id];
        }
        $table[] = $bidRow;

        $tricksRow = array(clienttranslate("Tricks Taken"));
        foreach ($players as $player_id => $player) {
            $tricksRow[] = $scoreInfo['tricks'][$player_id];
        }
        $table[] = $tricksRow;

        $bonusRow = array(clienttranslate("Bonus"));
        foreach ($players as $player_id => $player) {
            $bonusRow[] = $scoreInfo['bonus'][$player_id];
        }
        $table[] = $bonusRow;

        if (array_key_exists("decrev", $scoreInfo)) {
            $decRevRow = array(clienttranslate("Declare/Reveal"));
            foreach ($players as $player_id => $player) {
                $decRevRow[] = $scoreInfo['decrev'][$player_id];
            }
            $table[] = $decRevRow;
        }

        $totalRow = array(clienttranslate("Total"));
        foreach ($players as $player_id => $player) {
            $totalRow[] = $scoreInfo['total'][$player_id];
        }
        $table[] = $totalRow;

        // Having a separater between hand total and round total is nice
        $table[] = array('', '', '', '');

        if ($this->doesScoringVariantUseRounds()) {
            $roundScoreRow = array(clienttranslate("Round Score"));
        } else {
            $roundScoreRow = array(clienttranslate("Game Score"));
        }
        foreach ($players as $player_id => $player) {
            $roundScoreRow[] = $scoreInfo['currentScore'][$player_id];
        }
        $table[] = $roundScoreRow;
        return $table;
    }

    // Display the score
    function notifyScore($table, $message) {
        $this->notifyAllPlayers("tableWindow", '', array(
            "id" => 'scoreView',
            "title" => $message,
            "table" => $table,
            "closing" => clienttranslate("Continue")
        ));
    }

    /**
        Return an array containing all the information needed
        to display the score at the end of a round. This includes
        trick information, bonus information, and total game score.

        Output:
        {
            'name': {
                <player id> => 'Player name'
                ...
            },
            'roundScore': {
                <player id> => [87, 48, 121]
                ...
            },
            'roundWins': {
                <player id> => 1
                ...
            },
            'gameScore': {
                <player id> => 276
                ...
            },
            'roundBonus': {
                <player id> => [0, 0, 20]
                ...
            },
            'roundTotal': {
                <player id> => 146
                ...
            }
        }
    **/
    function generateRoundScoreInfo() {
        $players = self::loadPlayersBasicInfos();
        $result = array();
        $result['name'] = array();
        $result['roundScore'] = array();
        $result['roundWins'] = array();
        $result['gameScore'] = array();
        $round = $this->getCurrentRound();
        $playerBroke100 = array();
        $countBroke100 = array();
        foreach ($players as $playerId => $player) {
            $result['name'][$playerId] = $player['player_name'];
            $result['roundScore'][$playerId] = array();
            $result['roundWins'][$playerId] = 0;
            $playerBroke100[$playerId] = array();
            for ($i = 0; $i < $round + 1; $i++) {
                $roundScore = $this->dbGetRoundScore($playerId, $i);
                if (!array_key_exists($i, $countBroke100)) {
                    $countBroke100[$i] = 0;
                }
                if ($roundScore >= 100) {
                    $playerBroke100[$playerId][$i] = true;
                    $countBroke100[$i]++;
                    $result['roundWins'][$playerId] += 1;
                } else {
                    $playerBroke100[$playerId][$i] = false;
                }
                $result['roundScore'][$playerId][$i] = $roundScore;
            }
        }
        // Calculate round bonuses
        if ($this->doesGameUseRoundBonuses()) {
            $result['roundBonus'] = array();
            foreach ($players as $playerId => $player) {
                $result['roundBonus'][$playerId] = array();
                $playerGameScoreTotal = 0;
                for ($i = 0; $i < $round + 1; $i++) {
                    $roundBonusPoints = 0;
                    if ($countBroke100[$i] > 0) {
                        $roundBonusPoints = 40 - ($countBroke100[$i] * 10);
                    }
                    if ($playerBroke100[$playerId][$i]) {
                        $result['roundBonus'][$playerId][$i] = $roundBonusPoints;
                    } else {
                        $result['roundBonus'][$playerId][$i] = 0;
                    }
                }
            }
        }
        $result['roundTotal'] = array();
        foreach ($players as $playerId => $player) {
            $result['roundTotal'][$playerId] = array();
            $playerGameScoreTotal = 0;
            for ($i = 0; $i < $round + 1; $i++) {
                $roundBonusPoints = 0;
                if ($this->doesGameUseRoundBonuses()) {
                    $roundBonusPoints = $result['roundBonus'][$playerId][$i];
                }
                $result['roundTotal'][$playerId][$i] =
                    $result['roundScore'][$playerId][$i] + $roundBonusPoints;
                $playerGameScoreTotal += $result['roundTotal'][$playerId][$i];
            }
            $result['gameScore'][$playerId] = $playerGameScoreTotal;
        }
        return $result;
    }

    /**
        Given the hand score information and round score information, create a
        table to display the scores.

        This requires both hand and round score information since the scoring
        table combines information from the last hand and the round.
    **/
    function createRoundScoringTable($handScoreInfo, $roundScoreInfo) {

        $table = $this->createHandScoringTable($handScoreInfo);

        $players = self::loadPlayersBasicInfos();
        $round = $this->getCurrentRound();

        // Add a blank link to separate the hand information from the round info
        $table[] = array('', '', '', '');

        for ($i = 0; $i < $round + 1; $i++) {
            $roundName = $i + 1;

            $translatedRoundName = sprintf(clienttranslate("Round %d"), $roundName);
            $roundScoreRow = array($translatedRoundName);
            foreach ($players as $player_id => $player) {
                $roundScore = $roundScoreInfo['roundScore'][$player_id][$i];

                if ($this->doesGameUseRoundBonuses()) {
                    // Since we're not showing the bonuses unless it's the current round,
                    // we need to add them to the previous round's score here
                    if ($i != $round) {
                        $roundScore += $roundScoreInfo['roundBonus'][$player_id][$i];
                    }
                }
                $roundScoreRow[] = $roundScore;
            }
            $table[] = $roundScoreRow;

            if ($this->doesGameUseRoundBonuses()) {
                if ($i == $round) {
                    $translatedRoundBonus = sprintf(clienttranslate("Round %d bonus"), $roundName);
                    $roundBonusRow = array($translatedRoundBonus);
                    foreach ($players as $player_id => $player) {
                        $roundBonusRow[] = $roundScoreInfo['roundBonus'][$player_id][$i];
                    }
                    $table[] = $roundBonusRow;
                }
            }
        }
        $totalRow = array(clienttranslate("Game Total"));
        foreach ($players as $player_id => $player) {
            $totalRow[] = $roundScoreInfo['gameScore'][$player_id];
        }
        $table[] = $totalRow;
        return $table;
    }

    // Calculates the statistics and records them
    function finalizeStatistics() {
        $handCount = self::getStat("handCount");
        $totalTrickCount = $handCount * 9;
        $roundCount = $this->getCurrentRound() + 1;
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            // trickWinPercentage
            $tricksTaken = self::getStat("tricksWon", $playerId);
            $trickWinPerc = round(($tricksTaken / $totalTrickCount) * 100, 1);
            self::setStat($trickWinPerc, "trickWinPercentage", $playerId);

            if ($this->doesScoringVariantUseRounds()) {
                // roundWinPercentage
                $roundsWon = self::getStat("roundsWon", $playerId);
                $roundWinPerc = round(($roundsWon / $roundCount) * 100, 1);
                self::setStat($roundWinPerc, "roundWinPercentage", $playerId);
            }

            // declareSuccessPercentage
            $declares = self::getStat("declareCount", $playerId);
            $declareSuccess = self::getStat("declareSuccess", $playerId);
            if ($declares != 0) {
                $declareWinPerc = round(($declareSuccess / $declares) * 100, 1);
                self::setStat($declareWinPerc, "declareSuccessPercentage", $playerId);
            }

            // revealSuccessPercentage
            $reveals = self::getStat("revealCount", $playerId);
            $revealSuccess = self::getStat("revealSuccess", $playerId);
            if ($reveals != 0) {
                $revealWinPerc = round(($revealSuccess / $reveals) * 100, 1);
                self::setStat($revealWinPerc, "revealSuccessPercentage", $playerId);
            }

            // successBidPercentage
            $successfulBids = self::getStat("successBidCount", $playerId);
            $successBidPerc = round(($successfulBids / $handCount) * 100, 1);
            self::setStat($successBidPerc, "successBidPercentage", $playerId);
        }
    }

/************** End Game state helper functions ****************/

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:

        This method is called each time it is the turn of a player that quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player will end
        (ex: pass).
    */

    function zombieTurn($state, $activePlayer) {
        $statename = $state['name'];

        if ($statename == 'bidding') {
            // Bid a random 3 cards
            $this->cards->moveAllCardsInLocation('hand', 'zombiehand', $activePlayer, $activePlayer);
            $this->cards->shuffle('zombiehand');
            $bidCards = $this->cards->pickCards(3, 'zombiehand', $activePlayer);
            $bidValue = 0;
            foreach ($bidCards as $bidCard) {
                $this->cards->moveCard($bidCard['id'], 'bid', $activePlayer);
                $bidValue += $this->getCardBidValue($bidCard);
            }
            $this->cards->moveAllCardsInLocation('zombiehand', 'hand', null, $activePlayer);
            $this->persistPlayerBid($activePlayer, $bidValue);
            $this->persistPlayerDeclareRevealRequest($activePlayer, 0);
            $this->gamestate->setPlayerNonMultiactive($activePlayer, "biddingDone");
        } else if ($statename == 'playerTurn') {
            // Play a card
            $playableCards = $this->getPlayableCards($activePlayer);
            $randomCard = bga_rand(0, count($playableCards) - 1);
            $keys = array_keys($playableCards);
            $cardId = $playableCards[$keys[$randomCard]]['id'];

            $this->playCardFromPlayer($cardId, $activePlayer);
        }
    }
}


