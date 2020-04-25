<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BgaNinetyNine implementation : © Eric Kelly <boardgamearena@useric.com>
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
                1 => array(
                    'name' => totranslate('Junk the Joker'),
                    'tmdisplay' => totranslate('Junk the Joker'),
                    'description' => totranslate('The first deal in every round has diamonds as trump. Thereafter, the trump suit is determined by the number of players who correctly bid the last trick.')
                ),
                2 => array(
                    'name' => totranslate('Junk the Joker (no starting trump)'),
                    'tmdisplay' => totranslate('Junk the Joker (no starting trump)'),
                    'description' => totranslate('The first deal in every round has no trump. Thereafter, the trump suit is determined by the number of players who correctly bid the last trick.')
                )
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
    ),
		101 => array(
            'name' => totranslate('Scoring style'),
            'values' => array(
                1 => array(
                    'name' => totranslate('End of round bonuses'),
                    'tmdisplay' => totranslate('End of round bonuses'),
                    'description' => totranslate('Rounds end when at least one player reaches 100 points. Bonus points are awarded to the player(s) that reach or pass 100 (30, 20, or 10 points depending on number of players).')
                ),
                2 => array(
                    'name' => totranslate('No round bonuses'),
                    'tmdisplay' => totranslate('No round bonuses'),
                    'description' => totranslate('Rounds end when at least one player reaches 100 points.  No bonus points are awarded at end of round')
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

