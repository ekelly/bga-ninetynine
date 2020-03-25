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


class BgaNinetyNine extends Table
{
	function __construct( )
	{
        	

        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();self::initGameStateLabels( array( 
                         "currentHandTrump" => 10, 
                         "trickSuit" => 11,
						 "previousHandWinnerCount" => 12,
                         "currentRound" => 13,
                         "firstDealer" => 14,
                         "firstPlayer" => 15,
                         "gameLength" => 100 ) );

        $this->cards = self::getNew( "module.common.deck" );
        $this->cards->init( "card" );
	}
	
    protected function getGameName( )
    {
        return "bganinetynine";
    }	

    /*
        setupNewGame:
        
        This method is called 1 time when a new game is launched.
        In this method, you must setup the game according to game rules, in order
        the game is ready to be played.    
    
    */
    protected function setupNewGame( $players, $options = array() )
    {
		self::trace("setupNewGame");
        $sql = "DELETE FROM player WHERE 1 ";
        self::DbQuery( $sql ); 
 
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/yellow
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $default_color = array( "ff0000", "008000", "0000ff", "ffa500" );

        $start_points = self::getGameStateValue( 'gameLength' ) == 1 ? 75 : 100;

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialized it there.
        $sql = "INSERT INTO player (player_id, player_score, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_color );
            $values[] = "('".$player_id."','$start_points','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/
        // Init global values with their initial values

        // Note: hand types: 0 = starting type (no trump)
	    //                   
        self::setGameStateInitialValue( 'currentHandTrump', 0 );
        
        // Set current trick color to zero (= no trick color)
        self::setGameStateInitialValue( 'trickSuit', 0 );
        
        // Previous Hand Winner Count
		self::setGameStateInitialValue( 'previousHandWinnerCount', 0 );
        
        // Current Round
        self::setGameStateInitialValue( 'currentRound', 0 );
        
        // First dealer
        $dealer = bga_rand( 1, self::getPlayersNumber() );
        self::setGameStateInitialValue( 'firstDealer', $dealer );
        
        // Player with the first action (starts left of dealer, then winner of trick)
        self::setGameStateInitialValue( 'currentRound', 0 );

        // Init game statistics
        // (note: statistics are defined in your stats.inc.php file)
        /*self::initStat( "table", "handNbr", 0 );
        self::initStat( "player", "getQueenOfSpade", 0 );
        self::initStat( "player", "getBgaNinetyNine", 0 );
        self::initStat( "player", "getAllPointCards", 0 );
        self::initStat( "player", "getNoPointCards", 0 );*/

        // Create cards
        $cards = array();
        self::dump("this->colors", $this->colors);
        foreach( $this->colors as  $color_id => $color ) // spade, heart, diamond, club
        {
            for( $value=6; $value<=14; $value++ )   //  2, 3, 4, ... K, A
            {
                $cards[] = array( 'type' => $color_id, 'type_arg' => $value, 'nbr' => 1);
            }
        }

        $this->cards->createCards( $cards, 'deck' );

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refresh the game page (F5)
    */
    protected function getAllDatas()
    {
		self::trace("getAllDatas");
        $result = array( 'players' => array() );

        $player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you add for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score ";
        $sql .= "FROM player ";
        $sql .= "WHERE 1 ";
        $dbres = self::DbQuery( $sql );
        while( $player = mysql_fetch_assoc( $dbres ) )
        {
            $result['players'][ $player['id'] ] = $player;
        }
  
        // Cards in player hand      
        $result['hand'] = $this->cards->getCardsInLocation( 'hand', $player_id );
  
        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation( 'cardsontable' );
  
        self::dump("result for player $player_id", $result);
        
        return $result;
    }


    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with "updateGameProgression" property (see states.inc.php)
    */
    function getGameProgression()
    {
        // Game progression: get player minimum score
        
        $minimumScore = self::getUniqueValueFromDb( "SELECT MIN( player_score ) FROM player" );
        
        return max( 0, min( 100, 100-$minimumScore ) );   // Note: 0 => 100
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        At this place, you can put any utility methods useful for your game logic
    */
    
    /**
		Gets the current dealer
	**/
	function getDealer() {
		$dealer = self::getGameStateValue( "firstDealer" );
        $round = self::getGameStateValue( "currentRound" );
        // Stupidly, player # is 1 indexed. So I have to do this weird logic.
        $actualDealer = 1 + (($round + ($dealer - 1)) % 3);
        self::trace("Current dealer: $actualDealer");
		return $dealer;
	}
	
	/**
		Gets the first player to play a card
	**/
	function getFirstPlayer() {
		self::getGameStateValue( "firstPlayer" );
	}
	
	/**
		Sets the first player to play a card
	**/
	function setFirstPlayer( $playerID ) {
		self::setGameStateValue( "firstPlayer", $playerID );
	}

    /**
        Gets whether or not the current hand has a trump
    **/
    function getCurrentHandTrump() {
        self::getGameStateValue( "currentHandTrump" ) == 1;
    }
    
    /**
        Clears whether or not the current hand has trump.
    **/
    function clearCurrentHandTrump() {
        self::setGameStateValue( "currentHandTrump" , 0 );
    }
    
    /**
        Gets whether or not the current hand has a trump
    **/
    function setHandWinnerCount( $winnerCountOfPreviousHand ) {
        self::setGameStateValue( "currentHandTrump", 1 );
        self::setGameStateValue( "previousHandWinnerCount", $winnerCountOfPreviousHand );
    }
    
    /**
        Set the trick suit.
        1 = spades
        2 = hearts
        3 = diamond
        4 = club
        
    **/
    function setTrickSuit( $trickSuit ) {
        self::setGameStateValue( "trickSuit", $trickColor );
    }
    
    /**
        Get the current round number. 0 indexed.
    **/
    function getCurrentRound() {
        self::getGameStateValue( "currentRound" );
    }
    
    /**
        Set the current round number. 0 indexed.
    **/
    function setCurrentRound( $roundNum ) {
        self::setGameStateValue( "currentRound", $roundNum );
    }

    // Return players => direction (N/S/E/W) from the point of view
    //  of current player (current player must be on south)
    function getPlayersToDirection()
    {
        $result = array();
    
        $players = self::loadPlayersBasicInfos();
        $nextPlayer = self::createNextPlayerTable( array_keys( $players ) );

        $current_player = self::getCurrentPlayerId();
        
        $directions = array( 'S', 'W', 'E' );
        
        if( ! isset( $nextPlayer[ $current_player ] ) )
        {
            // Spectator mode: take any player for south
            $player_id = $nextPlayer[0];
            $result[ $player_id ] = array_shift( $directions );
        }
        else
        {
            // Normal mode: current player is on south
            $player_id = $current_player;
            $result[ $player_id ] = array_shift( $directions );
        }
        
        while( count( $directions ) > 0 )
        {
            $player_id = $nextPlayer[ $player_id ];
            $result[ $player_id ] = array_shift( $directions );
        }
        return $result;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of this method below is called.
        (note: each method below correspond to an input method in bganinetynine.action.php)
    */

    function submitBid( $card_ids ) {
        self::trace("submitBid");
        self::checkAction( "submitBid" );
        
        // Check that the cards are actually in the current user's hands.
        $player_id = self::getCurrentPlayerId();
        
        if( count( $card_ids ) != 3 ) {
            throw new feException( self::_("You must bid exactly 3 cards") );
        }
        
        $cards = $this->cards->getCards( $card_ids );
        
        if( count( $cards ) != 3 )
            throw new feException( self::_("Some of these cards don't exist") );
        
        // When a player plays a card in front of him on the table:
        foreach ($bid as $bidCard) {
            
            if( $card['location'] != 'hand' || $card['location_arg'] != $player_id )
                throw new feException( self::_("Some of these cards are not in your hand" ) );
            
            $this->cards->moveCard( $bidCard, 'bid', $player_id );
        }
        
        // Notify the player so we can make these cards disapear
        self::notifyPlayer( $player_id, "bidCards", "", array(
            "cards" => $card_ids
        ) );
        
        $this->gamestate->setPlayerNonMultiactive( $player_id, "submitBid" );
    }

    // Play a card from player hand
    function playCard( $card_id )
    {
        self::checkAction( "playCard" );
        
        $player_id = self::getActivePlayerId();
        
        // Get all cards in player hand
        // (note: we must get ALL cards in player's hand in order to check if the card played is correct)
        
        $playerhands = $this->cards->getCardsInLocation( 'hand', $player_id );

        $bFirstCard = ( count( $playerhands ) == 13 );
                
        $currentTrickColor = self::getGameStateValue( 'trickColor' ) ;
                
        // Check that the card is in this hand
        $bIsInHand = false;
        $currentCard = null;
        $bAtLeastOneCardOfCurrentTrickColor = false;
        $bAtLeastOneCardWithoutPoints = false;
        $bAtLeastOneCardNotHeart = false;
        foreach( $playerhands as $card )
        {
            if( $card['id'] == $card_id )
            {
                $bIsInHand = true;
                $currentCard = $card;
            }
            
            if( $card['type'] == $currentTrickColor )
                $bAtLeastOneCardOfCurrentTrickColor = true;

            if( $card['type'] != 2 )
                $bAtLeastOneCardNotHeart = true;
                
            if( $card['type'] == 2 || ( $card['type'] == 1 && $card['type_arg'] == 12  ) )
            {
                // This is a card with point
            }
            else
                $bAtLeastOneCardWithoutPoints = true;
        }
        if( ! $bIsInHand )
            throw new feException( "This card is not in your hand" );
            
        if( $this->cards->countCardInLocation( 'hand' ) == 52 )
        {
            // If this is the first card of the hand, it must be 2-club
            // Note: first card of the hand <=> cards on hands == 52

            if( $currentCard['type'] != 3 || $currentCard['type_arg'] != 2 ) // Club 2
                throw new feException( self::_("You must play the Club-2"), true );                
        }
        else if( $currentTrickColor == 0 )
        {
            // Otherwise, if this is the first card of the trick, any cards can be played
            // except a Heart if:
            // _ no heart has been played, and
            // _ player has at least one non-heart
            if( self::getGameStateValue( 'alreadyPlayedBgaNinetyNine')==0
             && $currentCard['type'] == 2   // this is a heart
             && $bAtLeastOneCardNotHeart )
            {
                throw new feException( self::_("You can't play a heart to start the trick if no heart has been played before"), true );
            }
        }
        else
        {
            // The trick started before => we must check the color
            if( $bAtLeastOneCardOfCurrentTrickColor )
            {
                if( $currentCard['type'] != $currentTrickColor )
                    throw new feException( sprintf( self::_("You must play a %s"), $this->colors[ $currentTrickColor ]['nametr'] ), true );
            }
            else
            {
                // The player has no card of current trick color => he can plays what he want to
                
                if( $bFirstCard && $bAtLeastOneCardWithoutPoints )
                {
                    // ...except if it is the first card played by this player during this hand
                    // (it is forbidden to play card with points during the first trick)
                    // (note: if player has only cards with points, this does not apply)
                    
                    if( $currentCard['type'] == 2 || ( $currentCard['type'] == 1 && $currentCard['type_arg'] == 12  ) )
                    {
                        // This is a card with point                  
                        throw new feException( self::_("You can't play cards with points during the first trick"), true );
                    }
                }
            }
        }
        
        // Checks are done! now we can play our card
        $this->cards->moveCard( $card_id, 'cardsontable', $player_id );
        
        // Set the trick color if it hasn't been set yet
        if( $currentTrickColor == 0 )
            self::setGameStateValue( 'trickColor', $currentCard['type'] );
        
        // And notify
        self::notifyAllPlayers( 'playCard', clienttranslate('${player_name} plays ${value_displayed} ${color_displayed}'), array(
            'i18n' => array( 'color_displayed', 'value_displayed' ),
            'card_id' => $card_id,
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'value' => $currentCard['type_arg'],
            'value_displayed' => $this->values_label[ $currentCard['type_arg'] ],
            'color' => $currentCard['type'],
            'color_displayed' => $this->colors[ $currentCard['type'] ]['name']
        ) );
        
        // Next player
        $this->gamestate->nextState( 'playCard' );
    }
    
    // Give some cards (before the hands begin)
    function giveCards( $card_ids )
    {
        self::checkAction( "giveCards" );
        
        // !! Here we have to get CURRENT player (= player who send the request) and not
        //    active player, cause we are in a multiple active player state and the "active player"
        //    correspond to nothing.
        $player_id = self::getCurrentPlayerId();
        
        if( count( $card_ids ) != 3 )
            throw new feException( self::_("You must give exactly 3 cards") );
    
        // Check if these cards are in player hands
        $cards = $this->cards->getCards( $card_ids );
        
        if( count( $cards ) != 3 )
            throw new feException( self::_("Some of these cards don't exist") );
        
        foreach( $cards as $card )
        {
            if( $card['location'] != 'hand' || $card['location_arg'] != $player_id )
                throw new feException( self::_("Some of these cards are not in your hand") );
        }
        
        // To which player should I give these cards ?
        $player_to_give_cards = null;
        $player_to_direction = self::getPlayersToDirection();   // Note: current player is on the south
        $handType = self::getGameStateValue( "currentHandType" );
        if( $handType == 0 )
            $direction = 'W';
        else if( $handType == 1 )
            $direction = 'N';
        else if( $handType == 2 )
            $direction = 'E';
        foreach( $player_to_direction as $opponent_id => $opponent_direction )
        {
            if( $opponent_direction == $direction )
                $player_to_give_cards = $opponent_id;
        }
        if( $player_to_give_cards === null )
            throw new feException( self::_("Error while determining to who give the cards") );
        
        // Allright, these cards can be given to this player
        // (note: we place the cards in some temporary location in order he can't see them before the hand starts)
        $this->cards->moveCards( $card_ids, "temporary", $player_to_give_cards );

        // Notify the player so we can make these cards disapear
        self::notifyPlayer( $player_id, "giveCards", "", array(
            "cards" => $card_ids
        ) );

        // Make this player unactive now
        // (and tell the machine state to use transtion "giveCards" if all players are now unactive
        $this->gamestate->setPlayerNonMultiactive( $player_id, "giveCards" );
    }
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defines as "game state arguments" (see "args" property in states.inc.php).
        These methods are returning some additional informations that are specific to the current
        game state.
    */

    function argGiveCards()
    {
        $handType = self::getGameStateValue( "currentHandType" );
        $direction = "";
        if( $handType == 0 )
            $direction = clienttranslate( "the player on the left" );
        else if( $handType == 1 )
            $direction = clienttranslate( "the player accros the table" );
        else if( $handType == 2 )
            $direction = clienttranslate( "the player on the right" );

        return array(
            "i18n" => array( 'direction'),
            "direction" => $direction
        );
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defines as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stGameSetup() {
		self::trace("stGameSetup");
		$this->gamestate->nextState("");
	}
	
	function stNewRound() {
		self::trace("stNewRound");
		$this->gamestate->nextState("");
	}
	
	function stNewHand() {
		self::trace("stNewHand");

        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');
        // Deal 12 cards to each players
        // Create deck, shuffle it and give 12 initial cards
        $players = self::loadPlayersBasicInfos();
        foreach ( $players as $player_id => $player ) {
            $cards = $this->cards->pickCards(13, 'deck', $player_id);
            // Notify player about his cards
            self::notifyPlayer($player_id, 'newHand', '', array ('cards' => $cards ));
        }
        $this->gamestate->setAllPlayersMultiactive();
    }
	
	function stBidding() {
		self::trace("stBidding");
	}
	
	function stCheckBids() {
		self::trace("stCheckBids");
        
        // TODO: Figure out who wants to declare / reveal
        
        // TODO: Update everyone with current cards & visibility
        
        $this->gamestate->nextState("startTrickTaking");
	}

    function stNewTrick() {
		self::trace("stNewTrick");
        // New trick: active the player who wins the last trick, or the player who own the club-2 card
        // Reset trick color to 0 (= no color)
        self::setGameStateInitialValue('trickColor', 0);
        $this->gamestate->nextState();
    }

    function stNextPlayer() {
		self::trace("stNextPlayer");
        // Active next player OR end the trick and go to the next trick OR end the hand
        if ($this->cards->countCardInLocation('cardsontable') == 4) {
            // This is the end of the trick
            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value = 0;
            $best_value_player_id = null;
            $currentTrickColor = self::getGameStateValue('trickColor');
            foreach ( $cards_on_table as $card ) {
                // Note: type = card color
                if ($card ['type'] == $currentTrickColor) {
                    if ($best_value_player_id === null || $card ['type_arg'] > $best_value) {
                        $best_value_player_id = $card ['location_arg']; // Note: location_arg = player who played this card on table
                        $best_value = $card ['type_arg']; // Note: type_arg = value of the card
                    }
                }
            }
            
            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer( $best_value_player_id );
            
            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $best_value_player_id);
        
            // Notify
            // Note: we use 2 notifications here in order we can pause the display during the first notification
            //  before we move all cards to the winner (during the second)
            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers( 'trickWin', clienttranslate('${player_name} wins the trick'), array(
                    'player_id' => $best_value_player_id,
                    'player_name' => $players[ $best_value_player_id ]['player_name']
            ) );
            self::notifyAllPlayers( 'giveAllCardsToPlayer','', array(
                    'player_id' => $best_value_player_id
            ) );
            
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
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stEndHand() {
		self::trace("stEndHand");
            // Count and score points, then end the game or go to the next hand.
        $players = self::loadPlayersBasicInfos();
        // Gets all "hearts" + queen of spades

        $player_to_points = array ();
        foreach ( $players as $player_id => $player ) {
            $player_to_points [$player_id] = 0;
        }
        $cards = $this->cards->getCardsInLocation("cardswon");
        foreach ( $cards as $card ) {
            $player_id = $card ['location_arg'];
            // Note: 2 = heart
            if ($card ['type'] == 2) {
                $player_to_points [$player_id] ++;
            }
        }
        // Apply scores to player
        foreach ( $player_to_points as $player_id => $points ) {
            if ($points != 0) {
                $sql = "UPDATE player SET player_score=player_score-$points  WHERE player_id='$player_id'";
                self::DbQuery($sql);
                $heart_number = $player_to_points [$player_id];
                self::notifyAllPlayers("points", clienttranslate('${player_name} gets ${nbr} hearts and looses ${nbr} points'), array (
                        'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'],
                        'nbr' => $heart_number ));
            } else {
                // No point lost (just notify)
                self::notifyAllPlayers("points", clienttranslate('${player_name} did not get any hearts'), array (
                        'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'] ));
            }
        }
        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true );
        self::notifyAllPlayers( "newScores", '', array( 'newScores' => $newScores ) );
            
        ///// Test if this is the end of the game
        foreach ( $newScores as $player_id => $score ) {
            if ($score <= -100) {
                // Trigger the end of the game !
                $this->gamestate->nextState("endGame");
                return;
            }
        }
        
        
        $this->gamestate->nextState("nextHand");
    }
    
	function stEndOfCurrentRound() {
		self::trace("stEndOfCurrentRound");
	}
	
	function stGameEnd() {
		self::trace("stGameEnd");
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

    function zombieTurn( $state, $active_player )
    {
        // Note: zombie mode has not be realized for BgaNinetyNine, as it is an example game and
        //       that it can be complex to choose a right card to play.
        throw new feException( "Zombie mode not supported for BgaNinetyNine" );
    }
   
   
}
  

