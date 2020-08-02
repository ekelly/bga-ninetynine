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
 * material.inc.php
 *
 * NinetyNine game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */


$this->suits = array(
    0 => array('name' => clienttranslate('club'),
               'pluralname' => clienttranslate('Clubs'),
               'nametr' => self::_('club'),
               'symbol' => clienttranslate('♣')),
    1 => array('name' => clienttranslate('diamond'),
               'pluralname' => clienttranslate('Diamonds'),
               'nametr' => self::_('diamond'),
               'symbol' => clienttranslate('♦')),
    2 => array('name' => clienttranslate('spade'),
               'pluralname' => clienttranslate('Spades'),
               'nametr' => self::_('spade'),
               'symbol' => clienttranslate('♠')),
    3 => array('name' => clienttranslate('heart'),
               'pluralname' => clienttranslate('Hearts'),
               'nametr' => self::_('heart'),
               'symbol' => clienttranslate('♥'))
);

$this->rank_label = array(
    2 =>'2',
    3 => '3',
    4 => '4',
    5 => '5',
    6 => '6',
    7 => '7',
    8 => '8',
    9 => '9',
    10 => '10',
    11 => clienttranslate('J'),
    12 => clienttranslate('Q'),
    13 => clienttranslate('K'),
    14 => clienttranslate('A')
);

$this->rank_name = array(
    2 =>'2',
    3 => '3',
    4 => '4',
    5 => '5',
    6 => '6',
    7 => '7',
    8 => '8',
    9 => '9',
    10 => '10',
    11 => clienttranslate('Jack'),
    12 => clienttranslate('Queen'),
    13 => clienttranslate('King'),
    14 => clienttranslate('Ace')
);
