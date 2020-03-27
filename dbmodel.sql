
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- BgaNinetyNine implementation : © Gregory Isabelli <gisabelli@boardgamearena.com>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

-- dbmodel.sql

-- This is the file where your are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- these export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here
--

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "bganinetynine"):

-- CREATE TABLE IF NOT EXISTS `card` (
--   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
--   `card_type` varchar(16) NOT NULL,
--   `card_type_arg` int(11) NOT NULL,
--   `card_location` varchar(16) NOT NULL,
--   `card_location_arg` int(11) NOT NULL,
--   PRIMARY KEY (`card_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- Default Player Table columns:
-- player_no: int player number
-- player_id: int id representing player
-- player_name: varchar player name
-- player_avater
-- player_color
-- player_score: int player score
-- player_score_aux: player score for ties (unused)
-- player_zombie: 1 if player is a zombie, 0 if live
-- player_ai: 1 if player is ai, 0 if live
-- player_enter_game: Whether or not the player has actually loaded the game

-- Keep track of how many tricks the player has taken so far this hand
ALTER TABLE `player` ADD `tricks_taken` int(11) NOT NULL DEFAULT 0;
-- Keep track of a player's bid for this hand
ALTER TABLE `player` ADD `bid` int(11) DEFAULT 0;
-- Keep track of whether or not the player has declared or revealed
--     Valid options: 0 (none), 1 (declare), 2 (reveal)
ALTER TABLE `player` ADD `declare_reveal` int(11) DEFAULT 0;
ALTER TABLE `player` ADD `player_score_round0` int(11) DEFAULT 0;
ALTER TABLE `player` ADD `player_score_round1` int(11) DEFAULT 0;
ALTER TABLE `player` ADD `player_score_round2` int(11) DEFAULT 0;

CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- card locations:
-- DECK, DISCARD, HAND, BID
-- both bid and hand require player id as a card_location_arg



