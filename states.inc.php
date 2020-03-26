<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * heartsla implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * states.inc.php
 *
 * heartsla game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

 
$machinestates = array(
        
        // The initial state. Please do not modify.
        1 => array(
                "name" => "gameSetup",
                "description" => clienttranslate("Game setup"),
                "type" => "manager",
                "action" => "stGameSetup",
                "transitions" => array( "" => 10 )
        ),
        
        // stGameSetup manages the state of the game
        
        // New Round
        10 => array(
                "name" => "newRound",
                "description" => clienttranslate("Starting the round"),
                "type" => "game",
                "action" => "stNewRound",
                "updateGameProgression" => true,
                "transitions" => array( "" => 12 )
        ),
        
        // New Hand (each game will have an arbitrary number of rounds / hands)
        
        12 => array(
                "name" => "newHand",
                "description" => clienttranslate("Starting the hand"),
                "type" => "game",
                "action" => "stNewHand",
                "updateGameProgression" => true,
                "transitions" => array( "" => 13 )
        ),
        
        // Bidding
        
        13 => array(
                "name" => "bidding",
                "description" => clienttranslate("Waiting for other players to bid"),
                "descriptionmyturn" => clienttranslate("You must choose 3 cards to bid"),
                "type" => "multipleactiveplayer",
                "action" => "stBidding",
                "possibleactions" => array( "submitBid" ),
                "updateGameProgression" => false,
                "transitions" => array( "submitBid" => 14 )
        ),
        14 => array(
                "name" => "checkBids",
                "description" => "",
                "type" => "game",
                "action" => "stCheckBids",
                "updateGameProgression" => false,
                "transitions" => array( "startTrickTaking" => 30 )
        ),
        
        // Trick
        
        30 => array(
                "name" => "newTrick",
                "description" => "",
                "type" => "game",
                "action" => "stNewTrick",
                "transitions" => array( "" => 31 )
        ),
        31 => array(
                "name" => "playerTurn",
                "description" => clienttranslate('${actplayer} must play a card'),
                "descriptionmyturn" => clienttranslate('${you} must play a card'),
                "type" => "activeplayer",
                "possibleactions" => array( "playCard" ),
                "transitions" => array( "playCard" => 32 )
        ),
        32 => array(
                "name" => "nextPlayer",
                "description" => "",
                "type" => "game",
                "action" => "stNextPlayer",
                "transitions" => array( "nextPlayer" => 31, "nextTrick" => 30, "endHand" => 40 )
        ),
        
        
        // End of the hand (scoring, etc...)
        40 => array(
                "name" => "endHand",
                "description" => "",
                "type" => "game",
                "action" => "stEndHand",
                "transitions" => array( "newHand" => 12, "endRound" => 50, )
        ),

        50 => array(
                "name" => "endOfCurrentRound",
                "description" => "",
                "type" => "game",
                "action" => "stEndOfCurrentRound",
                "transitions" => array( "nextRound" => 10, "endGame" => 99 )
        ),
        
        // Final state.
        // Please do not modify.
        99 => array(
                "name" => "gameEnd",
                "description" => clienttranslate("End of game"),
                "type" => "manager",
                "action" => "stGameEnd",
                "args" => "argGameEnd"
        )
        
);



