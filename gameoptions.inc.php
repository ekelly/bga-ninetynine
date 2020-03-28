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
 * gameoptions.inc.php
 *
 * BgaNinetyNine game options description
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in emptygame.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = array(

		100 => array(
            'name' => totranslate('Game style'),
            'values' => array(
                    1 => array( 'name' => totranslate( 'Standard game (3 rounds)' ) ),
            ),
            'default' => 1,
            'startcondition' => array(
                1 => array(
                    array(
                        'type' => 'maxplayers',
                        'value' => 3,
                        'message' => totranslate('This game is only available for 3 players.')
                    ),
                    array(
                        'type' => 'minplayers',
                        'value' => 3,
                        'message' => totranslate('This game is only available for 3 players.')
                    )
                ),
            ),
		)

);


