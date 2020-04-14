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
 * stats.inc.php
 *
 * BgaNinetyNine game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, and "float" for floating point values.
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
*/

//   !! It is not a good idea to modify this file when a game is running !!


$stats_type = array(

    // Statistics global to table
    "table" => array(

        "handCount" => array("id"=> 10,
                             "name" => totranslate("Number of hands"),
                             "type" => "int"),

        "total3WinnerHands" => array("id" => 11,
                                     "name" => totranslate("Total hands in which all players made their bids"),
                                     "type" => "int"),
        "total2WinnerHands" => array("id" => 12,
                                     "name" => totranslate("Total hands in which exactly two players made their bids"),
                                     "type" => "int"),
        "total1WinnerHands" => array("id" => 13,
                                     "name" => totranslate("Total hands in which only a single player made their bid"),
                                     "type" => "int"),
        "total0WinnerHands" => array("id" => 14,
                                     "name" => totranslate("Total hands in which only a single player made their bid"),
                                     "type" => "int")

    ),

    // Statistics existing for each player
    "player" => array(

        "tricksWon" => array("id" => 10,
                             "name" => totranslate("Total tricks won"),
                             "type" => "int"),

        "trickWinPercentage" => array("id" => 11,
                                      "name" => totranslate("Trick win %"),
                                      "type" => "float"),

        "roundsWon" => array("id" => 12,
                             "name" => totranslate("Total rounds won"),
                             "type" => "int"),

        "roundWinPercentage" => array("id" => 13,
                                      "name" => totranslate("Round win %"),
                                      "type" => "float"),

        "declareCount" => array("id" => 14,
                                "name" => totranslate("Total declares"),
                                "type" => "int"),

        "revealCount" => array("id" => 15,
                               "name" => totranslate("Total reveals"),
                               "type" => "int"),

        "declareSuccess" => array("id" => 16,
                                  "name" => totranslate("Total successful declares"),
                                  "type" => "int"),

        "declareSuccessPercentage" => array("id" => 17,
                                            "name" => totranslate("Successful declare %"),
                                            "type" => "float"),

        "revealSuccess" => array("id" => 18,
                                 "name" => totranslate("Total successful reveals"),
                                 "type" => "int"),

        "revealSuccessPercentage" => array("id" => 19,
                                           "name" => totranslate("Successful reveal %"),
                                           "type" => "float"),

        "successBidCount" => array("id" => 20,
                                   "name" => totranslate("Accurate bids"),
                                   "type" => "int"),

        "successBidPercentage" => array("id" => 21,
                                        "name" => totranslate("Accurate bid %"),
                                        "type" => "float")
    )

);


