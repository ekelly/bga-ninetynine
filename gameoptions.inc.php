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
            'name' => totranslate('Scoring style'),
            'values' => array(
                1 => array(
                    'name' => totranslate('3 rounds with round bonuses'),
                    'tmdisplay' => totranslate('3 rounds with round bonuses'),
                    'description' => totranslate('Each round starts without trump,
                        with subsequent trump being decided by how many players won
                        the previous trick. A bonus will be given to each player
                        who passes 100pts in each round. Winner is decided by total score')
                ),
                2 => array(
                    'name' => totranslate('3 rounds'),
                    'tmdisplay' => totranslate('3 rounds'),
                    'description' => totranslate('Each round starts without trump,
                        with subsequent trump being decided by how many players won
                        the previous trick. Winner is the player to win the most
                        rounds, ties decided by total points won')
                ),
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

