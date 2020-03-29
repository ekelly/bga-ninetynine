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
 * bganinetynine.action.php
 *
 * BgaNinetyNine main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/emptygame/emptygame/myAction.html", ...)
 *
 */
  
  class action_bganinetynine extends APP_GameAction
  { 
   	public function __default()
  	{
  	    if( self::isArg( 'notifwindow') )
  	    {
            $this->view = "common_notifwindow";
  	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
  	    }
  	    else
  	    {
            $this->view = "bganinetynine_bganinetynine";
            self::trace( "Complete reinitialization of board game" );
      }


  	} 
    public function playCard()
    {
        self::setAjaxMode();     
        $card_id = self::getArg( "id", AT_posint, true );
        $this->game->playCard( $card_id );
        self::ajaxResponse( );
    }
    
    public function submitBid()
    {
        self::setAjaxMode();     
        $cards_raw = self::getArg( "cards", AT_numberlist, true );
        
        // Removing last ';' if exists
        if( substr( $cards_raw, -1 ) == ';' )
            $cards_raw = substr( $cards_raw, 0, -1 );
        if( $cards_raw == '' )
            $cards = array();
        else
            $cards = explode( ';', $cards_raw );

        $this->game->submitBid( $cards );
        self::ajaxResponse( );    
    }
  
    public function submitDeclareOrReveal() {
        self::setAjaxMode();     
        $decrev = self::getArg( "declareOrReveal", AT_posint, true );
        $this->game->declareOrReveal($decrev);
        self::ajaxResponse( );
    }
  }
  

