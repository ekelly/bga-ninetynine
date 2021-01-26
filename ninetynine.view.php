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
 * ninetynine.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in emptygame_emptygame.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */

  require_once( APP_BASE_PATH."view/common/game.view.php" );

  class view_ninetynine_ninetynine extends game_view
  {
    function getGameName() {
        return "ninetynine";
    }
  	function build_page($viewArgs)
  	{
  	    // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count($players);

        /*********** Place your code below:  ************/

        $this->tpl['PLAYER_COUNT'] = $players_nbr;

        // Arrange players so that I am on south
        $player_to_dir = $this->game->getPlayersToDirection();

        $this->page->begin_block("ninetynine_ninetynine", "player");
        foreach ($player_to_dir as $player_id => $dir) {
            $this->page->insert_block("player", array("PLAYER_ID" => $player_id,
                                                      "PLAYER_NAME" => $players[$player_id]['player_name'],
                                                      "PLAYER_COLOR" => $players[$player_id]['player_color'],
                                                      "DIR" => $dir));
        }

        $this->tpl['MY_HAND'] = self::_("My hand");

        // Translateable strings
        if ($this->game->doesScoringVariantUseRounds()) {
            $this->tpl['ROUND_LABEL'] = self::_("Round ");
        } else {
            $this->tpl['ROUND_LABEL'] = self::_("Hand ");
        }
        $this->tpl['DECREV_PLAYER_LABEL'] = self::_("Declaring/Revealing Player: ");
        $this->tpl['TRUMP_LABEL'] = self::_("Trump Suit:");
        $this->tpl['DECLARED_BID_LABEL'] = self::_("Declared Bid: ");
        $this->tpl['MY_BID_LABEL'] = self::_("My Bid: ");
        $this->tpl['TRICKS_WON_LABEL'] = self::_("Tricks Won: ");
        $this->tpl['REVEALED_HAND_LABEL'] = self::_("Revealed Hand:");
        $this->tpl['MY_HAND_LABEL'] = self::_("My Hand");
        $this->tpl['REVEALED_LABEL'] = self::_("Revealed");
        $this->tpl['DECLARED_LABEL'] = self::_("Declared");

        $this->tpl['NONE'] = self::_("None");

        /*********** Do not change anything below this line  ************/
  	}
  }


