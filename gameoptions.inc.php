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
 * gameoptions.inc.php
 *
 * NinetyNine game options description
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
                ),
                3 => array(
                    'name' => totranslate('Standard'),
                    'tmdisplay' => totranslate('Standard'),
                    'description' => totranslate('The trump suit is determined by a random card draw. If the card is a nine or joker, the round has no trump.')
                ),
            ),
            'default' => 3,
            'startcondition' => array(
                1 => array(
                    array(
                        'type' => 'maxplayers',
                        'value' => 4,
                        'message' => totranslate('This game has a maximum of 4 players.')
                    ),
                    array(
                        'type' => 'minplayers',
                        'value' => 3,
                        'message' => totranslate('This game requires at least 3 players.')
                    )
                ),
            ),
    ),
		101 => array(
            'name' => totranslate('Scoring style'),
            'values' => array(
                1 => array(
                    'name' => totranslate('1 Round per player (with end of round bonuses)'),
                    'tmdisplay' => totranslate('1 Round per player (with end of round bonuses)'),
                    'description' => totranslate('A game consists of rounds. Rounds end when at least one player reaches 100 points. Bonus points are awarded to the player(s) that reach or pass 100 (30, 20, or 10 points depending on number of players).')
                ),
                2 => array(
                    'name' => totranslate('1 Round per player (no round bonuses)'),
                    'tmdisplay' => totranslate('1 Round per player (no round bonuses)'),
                    'description' => totranslate('A game consists of rounds. Rounds end when at least one player reaches 100 points. No bonus points are awarded at end of round.')
                ),
                3 => array(
                    'name' => totranslate('First to 3 rounds'),
                    'tmdisplay' => totranslate('First to 3 rounds'),
                    'description' => totranslate('Rounds end when at least one player reaches 100 points. The first player to win 3 rounds wins.')
                ),
                4 => array(
                    'name' => totranslate('9 hands'),
                    'tmdisplay' => totranslate('9 hands'),
                    'description' => totranslate('A game consists of 9 hands. Highest score at the end wins')
                ),
            ),
            'default' => 1,
            'startcondition' => array(
                1 => array(
                    array(
                        'type' => 'maxplayers',
                        'value' => 4,
                        'message' => totranslate('This game has a maximum of 4 players.')
                    ),
                    array(
                        'type' => 'minplayers',
                        'value' => 3,
                        'message' => totranslate('This game requires at least 3 players.')
                    )
                ),
            ),
    )
);


$game_preferences = array(
    100 => array(
			  'name' => totranslate('Card hover effect'),
			  'needReload' => true, // after user changes this preference game interface would auto-reload
			  'values' => array(
            1 => array('name' => totranslate('None')),
            2 => array('name' => totranslate('Raise card'), 'cssPref' => 'bgann_cardhover')
			  )
    ),
    101 => array(
			  'name' => totranslate('Card sort order'),
			  'needReload' => true, // after user changes this preference game interface would auto-reload
			  'values' => array(
            1 => array('name' => totranslate('Bid Value Order')),
            2 => array('name' => totranslate('Hearts Order'))
			  )
    ),
    102 => array(
			  'name' => totranslate('Highlight trump'),
			  'needReload' => true, // after user changes this preference game interface would auto-reload
			  'values' => array(
            1 => array('name' => totranslate('Enabled'), 'cssPref' => 'bgann_highlight_trump'),
            2 => array('name' => totranslate('Disabled'))
			  )
  	),
    103 => array(
			  'name' => totranslate('Highlight playable cards'),
			  'needReload' => true, // after user changes this preference game interface would auto-reload
			  'values' => array(
            1 => array('name' => totranslate('Enabled'), 'cssPref' => 'bgann_highlight_playable'),
            2 => array('name' => totranslate('Disabled'))
			  )
  	),
    104 => array(
			  'name' => totranslate('Play forced cards'),
			  'needReload' => false,
			  'values' => array(
            1 => array('name' => totranslate('Enabled')),
            2 => array('name' => totranslate('Disabled'))
			  )
  	),
    105 => array(
			  'name' => totranslate('Bid selection effect'),
			  'needReload' => true, // after user changes this preference game interface would auto-reload
			  'values' => array(
            1 => array('name' => totranslate('Raise card'), 'cssPref' => 'bgann_raise_selected'),
            2 => array('name' => totranslate('Highlight card'), 'cssPref' => 'bgann_highlight_selected')
			  )
    ),
);

