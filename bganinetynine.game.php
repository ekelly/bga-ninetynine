<?php
/**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * BgaNinetyNine implementation : © Gregory Isabelli <gisabelli@boardgamearena.com>
  *
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  *
  * bganinetynine.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */

require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class BgaNinetyNine extends Table {
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
                         "firstPlayer" => 15,
                         "currentDealer" => 16,
                         "gameStyle" => 100));

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
    }

    protected function getGameName() {
        return "bganinetynine";
    }

    /*
        setupNewGame:

        This method is called 1 time when a new game is launched.
        In this method, you must setup the game according to game rules, in order
        the game is ready to be played.

    */
    protected function setupNewGame($players, $options = array()) {
        self::warn("setupNewGame");
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

        /************ Start the game initialization *****/
        // Init global values with their initial values

        // Note: hand types: -1 = starting type (no trump)
        //
        self::setGameStateInitialValue('currentHandTrump', -1);

        // Set current trick suit to 4 (= no trick color)
        self::setGameStateInitialValue('trickSuit', -1);

        // Previous Hand Winner Count
        self::setGameStateInitialValue('previousHandWinnerCount', -1);

        // Current Round
        self::setGameStateInitialValue('currentRound', 0);

        // First dealer
        $dealer = array_keys($players)[0];
        $firstPlayer = self::getPlayerAfter($dealer);

        self::setGameStateInitialValue('firstDealer', $dealer);
        self::setGameStateInitialValue('currentDealer', $dealer);

        // Player with the first action (starts left of dealer, then winner of trick)
        self::setGameStateInitialValue('firstPlayer', $firstPlayer);

        // Init game statistics
        self::initStat("table", "handCount", 0);
        self::initStat("table", "total3WinnerHands", 0);
        self::initStat("table", "total2WinnerHands", 0);
        self::initStat("table", "total1WinnerHands", 0);
        self::initStat("table", "total0WinnerHands", 0);
        self::initStat("player", "tricksWon", 0);
        self::initStat("player", "trickWinPercentage", 0);
        self::initStat("player", "roundsWon", 0);
        self::initStat("player", "roundWinPercentage", 0);
        self::initStat("player", "declareCount", 0);
        self::initStat("player", "revealCount", 0);
        self::initStat("player", "declareSuccess", 0);
        self::initStat("player", "declareSuccessPercentage", 0);
        self::initStat("player", "revealSuccess", 0);
        self::initStat("player", "revealSuccessPercentage", 0);
        self::initStat("player", "successBidCount", 0);
        self::initStat("player", "successBidPercentage", 0);

        // Create cards
        $cards = array();
        // $suits = array( "club", "diamond", "spade", "heart" );
        for ($suit_id = 0; $suit_id < 4; $suit_id++) {
            //  2, 3, 4, ... K, A
            for ($value = 6; $value <= 14; $value++) {
                $cards[] = array('type' => $suit_id, 'type_arg' => $value, 'nbr' => 1);
            }
        }

        $this->cards->createCards($cards, 'deck');

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas:

        Gather all informations about current game situation (visible by the current player).

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refresh the game page (F5)
    */
    protected function getAllDatas() {
        self::warn("getAllDatas");
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
        $result['firstPlayer'] = $this->getFirstPlayer();
        $result['trickCounts'] = $this->getTrickCounts();
        $result['roundScores'] = $this->getCurrentRoundScores();

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

    /*
        At this place, you can put any utility methods useful for your game logic
    */

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
        Gets the current dealer
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

    function getDealer() {
        return self::getGameStateValue("currentDealer");
    }

    function setDealer($dealer) {
        self::setGameStateValue("currentDealer", $dealer);
    }

    function nextDealer() {
        $dealer = $this->getPlayerAfter($this->getDealer());
        self::setGameStateValue("currentDealer", $dealer);
    }

    /**
        Gets the first player to play a card
    **/
    function getFirstPlayer() {
        return intval(self::getGameStateValue("firstPlayer"));
    }

    /**
        Sets the first player to play a card
    **/
    function setFirstPlayer($playerID) {
        self::setGameStateValue("firstPlayer", $playerID);
    }

    /**
        Gets whether or not the current hand has a trump

        returns:
          0 = clubs
          1 = diamonds
          2 = spades
          3 = hearts
          null = none
    **/
    function getCurrentHandTrump() {
        $prevWinnerCount = self::getGameStateValue("previousHandWinnerCount");
        if ($prevWinnerCount < 0 || $prevWinnerCount > 3) {
            return null;
        }
        return ($prevWinnerCount + 1) % 4;
    }

    function setPreviousWinnerCount($prevWinnerCount) {
        self::setGameStateValue("previousHandWinnerCount", $prevWinnerCount);
    }

    function clearPreviousWinnerCount() {
        self::setGameStateValue("previousHandWinnerCount", -1);
    }

    /**
        Clears whether or not the current hand has trump.
    **/
    function clearCurrentHandTrump() {
        self::setGameStateValue("currentHandTrump" , -1);
    }

    function getCurrentTrickSuit() {
        return self::getGameStateValue("trickSuit");
    }

    function clearCurrentTrickSuit() {
        self::setGameStateValue("trickSuit", -1);
    }

    /**
        Set the trick suit.
    **/
    function setCurrentTrickSuit($trickSuit) {
        self::setGameStateValue("trickSuit", $trickSuit);
    }

    /**
        Gets whether or not the current hand has a trump
    **/
    function setHandWinnerCount($winnerCountOfPreviousHand) {
        self::setGameStateValue("currentHandTrump", 1);
        self::setGameStateValue("previousHandWinnerCount", $winnerCountOfPreviousHand);
    }

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

    function getSuitName($suit) {
        return $this->suits[$suit]['nametr'];
    }

    /**
        Get the current round number. 0 indexed.
    **/
    function getCurrentRound() {
        return self::getGameStateValue("currentRound");
    }

    /**
        Set the current round number. 0 indexed.
    **/
    function setCurrentRound($roundNum) {
        self::setGameStateValue("currentRound", $roundNum);
    }

    /**
        Persist the player's bid to the players table
    **/
    function persistPlayerBid($playerId, $bid) {
        $sql = "UPDATE player SET player_bid=$bid WHERE player_id='$playerId'";
        $this->DbQuery($sql);
    }

    function clearBids() {
        $sql = "UPDATE player SET player_bid=0 WHERE 1";
        $this->DbQuery($sql);
    }

    function getPlayerColor($playerId) {
        return $this->getUniqueValueFromDB("SELECT player_color FROM player WHERE player_id='$playerId'");
    }

    /**
        Get the player's bid from the players table
    **/
    function getPlayerBid($playerId) {
        return $this->getUniqueValueFromDB("SELECT player_bid FROM player WHERE player_id='$playerId'");
    }

    function persistPlayerDeclareReveal($playerId, $decRev) {
        $sql = "UPDATE player SET player_declare_reveal=$decRev WHERE player_id='$playerId'";
        $this->DbQuery( $sql );
    }

    function clearAllDeclareReveal() {
        $sql = "UPDATE player SET player_declare_reveal=0 WHERE 1";
        $this->DbQuery( $sql );
    }

    function setDeclareReveal($playerId, $decRev) {
        $this->clearAllDeclareReveal();
        $sql = "UPDATE player SET player_declare_reveal=$decRev WHERE player_id='$playerId'";
        $this->DbQuery($sql);
    }

    function getCurrentRoundScores() {
        return $this->dbGetRoundScores($this->getCurrentRound());
    }

    function dbGetRoundScores($round) {
        if ($round < 0 || $round > 2) {
            throw new feException(_("Invalid round"));
        }
        $roundScores = $this->getCollectionFromDB("SELECT player_id, player_score_round$round score FROM player", true);
        $result = array();
        foreach ($roundScores as $playerId => $score) {
            $result[$playerId] = intval($score);
        }
        return $result;
    }

    function dbGetScores() {
        $scores = $this->getCollectionFromDB("SELECT player_id, player_score FROM player", true);
        $result = array();
        foreach ($scores as $playerId => $score) {
            $result[$playerId] = intval($score);
        }
        return $result;
    }

    function dbGetRoundScore($playerId, $round) {
        return $this->dbGetRoundScores($round)[$playerId];
    }

    // set score
    function dbSetScore($playerId, $count) {
        $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$playerId'");
    }

    function dbClearScores() {
        $this->DbQuery("UPDATE player SET player_score='0' WHERE 1");
    }

    // get score
    function dbGetScore($playerId) {
        return intval($this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$playerId'"));
    }

    // set score
    function dbSetRoundScore($playerId, $score) {
        $round = $this->getCurrentRound();
        $this->DbQuery("UPDATE player SET player_score_round$round='$score' WHERE player_id='$playerId'");
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

    function assignDeclareRevealPlayer() {
        $result = $this->getNonEmptyCollectionFromDB("SELECT player_id id, player_declare_reveal decrev FROM player");

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
    }

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

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    /*
        Each time a player is doing some game action, one of this method below is called.
        (note: each method below correspond to an input method in bganinetynine.action.php)
    */

    function submitBid($card_ids) {
        self::warn("submitBid");
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

        $this->gamestate->setPlayerNonMultiactive($player_id, "biddingDone");
    }

    function declareOrReveal($declareOrReveal) {
        self::warn("declareOrReveal");
        self::checkAction( "submitDeclareOrReveal" );

        if ($declareOrReveal < 0 || $declareOrReveal > 2) {
            throw new feException(sprintf(_("Invalid declare or reveal: %d"), $declareOrReveal));
        }

        // Check that the cards are actually in the current user's hands.
        $playerId = self::getCurrentPlayerId();

        $this->persistPlayerDeclareReveal($playerId, $declareOrReveal);

        $this->gamestate->setPlayerNonMultiactive($playerId, "declaringOrRevealingDone");
    }

    // Play a card from player hand
    function playCard($card_id) {
        self::checkAction("playCard");

        $player_id = self::getActivePlayerId();

        // Get all cards in player hand
        // (note: we must get ALL cards in player's hand in order to check if the card played is correct)

        $playerhand = $this->cards->getPlayerHand($player_id);

        // This line may not be needed
        // $currentTrickSuit = $this->getCurrentTrickSuit();
        $currentTrump = $this->getCurrentHandTrump();

        // Returns the 'bottom' card of the location
        $firstPlayedSuit = null;
        $firstCardOfTrick = $this->cards->countCardInLocation('cardsontable') == 0;

        if (!$firstCardOfTrick) {
            $firstPlayedSuit = $this->getCurrentTrickSuit();
        }

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
            'suit_displayed' => $this->suits[$currentCard['type']]['name'],
            'firstPlayer' => $this->getFirstPlayer()
        ));

        // Next player
        $this->gamestate->nextState('playCard');
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defines as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stGameSetup() {
        self::warn("stGameSetup");
        $this->gamestate->nextState("");
    }

    function stNewRound() {
        self::warn("stNewRound");
        $this->dbClearScores();
        $this->setDealer($this->getRoundDealer());
        $dealer = $this->getDealer();
        $firstPlayer = $this->getPlayerAfter($dealer);
        $this->setFirstPlayer($firstPlayer);
        self::notifyAllPlayers('newRound', clienttranslate('Starting a new round'), array(
            'dealer' => $dealer,
            'firstPlayer' => $firstPlayer
        ));
        $this->gamestate->nextState("");
    }

    function stNewHand() {
        self::warn("stNewHand");

        self::incStat(1, "handCount");

        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');
        // Deal 12 cards to each players
        // Create deck, shuffle it and give 12 initial cards
        $players = self::loadPlayersBasicInfos();
        $dealer = $this->getDealer();
        $firstPlayer = $this->getPlayerAfter($dealer);
        $this->setFirstPlayer($firstPlayer);
        $trump = $this->getCurrentHandTrump();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(12, 'deck', $player_id);
            // Notify player about his cards
            self::notifyPlayer($player_id, 'newHand', '', array('cards' => $cards,
              'dealer' => $dealer, 'firstPlayer' => $firstPlayer, 'trump' => $trump));
        }

        $this->gamestate->nextState("");
    }

    function stBidding() {
        self::warn("stBidding");
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $this->giveExtraTime($player_id);
        }
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stCheckBids() {
        self::warn("stCheckBids");
        $this->gamestate->nextState("declareOrReveal");
    }

    function stDeclareOrReveal() {
        self::warn("stDeclareOrReveal");
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $this->giveExtraTime($player_id);
        }
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stCheckDeclareOrReveal() {
        self::warn("stCheckDeclareOrReveal");

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
        $this->setFirstPlayer($firstPlayer);

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
                ),
                "declareReveal" => $declareReveal,
                "dealer" => $dealerId,
                "firstPlayer" => $firstPlayer
            ));
        }

        $this->gamestate->changeActivePlayer($firstPlayer);
        $this->giveExtraTime($firstPlayer);

        $this->gamestate->nextState("startTrickTaking");
    }

    function stNewTrick() {
        self::warn("stNewTrick");

        $this->clearCurrentTrickSuit();

        $this->gamestate->nextState("");
    }

    function stNextPlayer() {
        self::warn("stNextPlayer");
        // Active next player OR end the trick and go to the next trick OR end the hand
        if ($this->cards->countCardInLocation('cardsontable') == 3) {
            $winningCard = $this->getTrickWinner();
            $winningPlayer = $winningCard['location_arg'];

            // Winning tricks statistics
            self::incStat(1, "tricksWon", $winningPlayer);

            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer($winningPlayer);
            $this->setFirstPlayer($winningPlayer);

            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $winningPlayer);

            $trickCounts = $this->getTrickCounts();
            $declareReveal = $this->getDeclareOrRevealInfo();
            $decRevPlayerId = $declareReveal['playerId'];

            // Notify
            // Note: we use 2 notifications here in order we can pause the display during the first notification
            //  before we move all cards to the winner (during the second)
            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers('trickWin', clienttranslate('${player_name} wins the trick'), array(
                    'player_id' => $winningPlayer,
                    'player_name' => $players[$winningPlayer]['player_name']
            ));
            self::notifyAllPlayers('giveAllCardsToPlayer','', array(
                'playerId' => $winningPlayer,
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
            $this->setFirstPlayer($player_id);
            self::notifyAllPlayers('currentPlayer', '', array(
                'currentPlayer' => $this->getFirstPlayer()
            ));
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stEndHand() {
        self::warn("stEndHand");

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
            if ($points != 0) {

                // Add the round bonus to the total score
                $playerRoundScore = $handScoreInfo['currentScore'][$player_id] +
                    $handScoreInfo['total'][$player_id];
                if ($playerRoundScore >= 100) {
                    self::incStat(1, "roundsWon", $player_id);
                    $points += (40 - ($countPlayersExceeded100 * 10));
                }

                $this->dbSetScore($player_id, $playerRoundScore);
                $this->dbSetRoundScore($player_id, $playerRoundScore);

                self::notifyAllPlayers("points", clienttranslate('${player_name} gets ${points} points'), array(
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'points' => $points,
                    'roundScore' => $playerRoundScore
                ));
            } else {
                // No point lost (just notify)
                self::notifyAllPlayers("points", clienttranslate('${player_name} did not get any points'), array (
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name']));
            }
        }
        $newScores = $this->getCurrentRoundScores();
        self::notifyAllPlayers("newScores", '', array('newScores' => $newScores));

        // Test if this is the end of the round
        if ($countPlayersExceeded100 > 0) {
            $this->gamestate->nextState("endRound");
        } else {
            // Display the score for the hand
            $handScoreInfo = $this->generateScoreInfo();
            $scoreTable = $this->createHandScoringTable($handScoreInfo);
            $this->notifyScore($scoreTable, clienttranslate('Hand Score'));

            $this->clearAllDeclareReveal();
            $this->clearBids();

            $this->nextDealer();
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
        if ($round == 2) {
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

    function finalizeGameEndState() {
        self::warn("finalizeGameEndState");

        // This will get the scores from Round 3
        $roundScoreInfo = $this->generateRoundScoreInfo();

        foreach ($roundScoreInfo['gameScore'] as $playerId => $score) {
            $this->dbSetScore($playerId, $score);
        }

        $newScores = $this->dbGetScores();
        self::notifyAllPlayers("newScores", '', array('newScores' => $newScores));

        // Calculate statistics
        $handCount = self::getStat("handCount");
        $totalTrickCount = $handCount * 9;
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            // trickWinPercentage
            $tricksTaken = self::getStat("tricksWon", $playerId);
            $trickWinPerc = round(($tricksTaken / $totalTrickCount) * 100, 1);
            self::setStat($trickWinPerc, "trickWinPercentage", $playerId);

            // roundWinPercentage
            $roundsWon = self::getStat("roundsWon", $playerId);
            $roundWinPerc = round(($roundsWon / 3) * 100, 1);
            self::setStat($roundWinPerc, "roundWinPercentage", $playerId);

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
            $result['bid'][$playerId] = $playerBid;
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

        $roundScoreRow = array(clienttranslate("Round Score"));
        foreach ($players as $player_id => $player) {
            $roundScoreRow[] = $scoreInfo['currentScore'][$player_id];
        }
        $table[] = $roundScoreRow;
        return $table;
    }

    function notifyScore($table, $message) {
        $this->notifyAllPlayers("tableWindow", '', array(
            "id" => 'scoreView',
            "title" => $message,
            "table" => $table,
            "closing" => clienttranslate("Continue")
        ));
    }

    function generateRoundScoreInfo() {
        $players = self::loadPlayersBasicInfos();
        $result = array();
        $result['name'] = array();
        $result['roundScore'] = array();
        $result['gameScore'] = array();
        $round = $this->getCurrentRound();
        $playerBroke100 = array();
        $countBroke100 = array();
        foreach ($players as $playerId => $player) {
            $result['name'][$playerId] = $player['player_name'];
            $result['roundScore'][$playerId] = array();
            $playerBroke100[$playerId] = array();
            for ($i = 0; $i < $round + 1; $i++) {
                $roundScore = $this->dbGetRoundScore($playerId, $i);
                if (!array_key_exists($i, $countBroke100)) {
                    $countBroke100[$i] = 0;
                }
                if ($roundScore >= 100) {
                    $playerBroke100[$playerId][$i] = true;
                    $countBroke100[$i]++;
                } else {
                    $playerBroke100[$playerId][$i] = false;
                }
                $result['roundScore'][$playerId][$i] = $roundScore;
            }
        }
        // Calculate round bonuses
        $result['roundBonus'] = array();
        $result['roundTotal'] = array();
        foreach ($players as $playerId => $player) {
            $result['roundBonus'][$playerId] = array();
            $result['roundTotal'][$playerId] = array();
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
                $result['roundTotal'][$playerId][$i] =
                    $result['roundScore'][$playerId][$i] +
                        $result['roundBonus'][$playerId][$i];
                $playerGameScoreTotal += $result['roundTotal'][$playerId][$i];
            }
            $result['gameScore'][$playerId] = $playerGameScoreTotal;
        }
        return $result;
    }

    function createRoundScoringTable($handScoreInfo, $roundScoreInfo) {

        $table = $this->createHandScoringTable($handScoreInfo);

        $players = self::loadPlayersBasicInfos();
        $round = $this->getCurrentRound();

        $table[] = array('', '', '', '');

        for ($i = 0; $i < $round + 1; $i++) {
            $roundName = $i + 1;

            $translatedRoundName = sprintf(clienttranslate("Round %d"), $roundName);
            $roundScoreRow = array($translatedRoundName);
            foreach ($players as $player_id => $player) {
                $roundScore = $roundScoreInfo['roundScore'][$player_id][$i];
                // Since we're not showing the bonuses unless it's the current round,
                // we need to add them to the previous round's score here
                if ($i != $round) {
                    $roundScore += $roundScoreInfo['roundBonus'][$player_id][$i];
                }
                $roundScoreRow[] = $roundScore;
            }
            $table[] = $roundScoreRow;

            if ($i == $round) {
                $translatedRoundBonus = sprintf(clienttranslate("Round %d bonus"), $roundName);
                $roundBonusRow = array($translatedRoundBonus);
                foreach ($players as $player_id => $player) {
                    $roundBonusRow[] = $roundScoreInfo['roundBonus'][$player_id][$i];
                }
                $table[] = $roundBonusRow;
            }
        }
        $totalRow = array(clienttranslate("Game Total"));
        foreach ($players as $player_id => $player) {
            $totalRow[] = $roundScoreInfo['gameScore'][$player_id];
        }
        $table[] = $totalRow;
        return $table;
    }

    function debug($val) {
        $strVal = print_r($val, true);
        $this->notifyAllPlayers("debugLog", "$strVal", array());
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:

        This method is called each time it is the turn of a player that quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player will end
        (ex: pass).
    */

    function zombieTurn($state, $active_player) {
        // Note: zombie mode has not be realized for BgaNinetyNine, as it is an example game and
        //       that it can be complex to choose a right card to play.
        throw new feException(_("Zombie mode not supported for BgaNinetyNine"));
    }
}


