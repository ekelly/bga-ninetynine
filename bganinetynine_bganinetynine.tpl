{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- BgaNinetyNine implementation : © Gregory Isabelli <gisabelli@boardgamearena.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    bganinetynine_bganinetynine.tpl
    
    This is the HTML template of your game.
    
    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.
    
    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format
    
    See your "view" PHP file to check how to set variables and control blocks
-->
<div id="table">
    <div id="playertables">

        <!-- BEGIN player -->
        <div class="playertable whiteblock playertable_{DIR}">
            <div class="playertablename" style="color:#{PLAYER_COLOR}">
                {PLAYER_NAME}
            </div>
            <div class="playertablecard" id="playertablecard_{PLAYER_ID}">
            </div>
        </div>
        <!-- END player -->

    </div>
    <div id="declarerevealtable">
        <h3 class="whiteblock">
            <span class="to_translate">Revealing/Declaring Player: </span> 
            <span class="to_translate" id="decrev_player_name">None</span> <!-- PLAYER_NAME should say none for rounds with no declaring/revealing player -->
        </h3>
        <div id="declaretable" class="whiteblock">
            <span>Declared Bid:</span>
            <span id="declaredBidValue"></span>
            <div id="declaredBid"></div>
        </div>
        <div id="revealtable" class="whiteblock">
            <span>Revealed Hand:</span>
            <div id="revealedHand"></div>
        </div>
    </div>
</div>

<div id="myhand_wrap" class="whiteblock" style="height: 130px;">
    <h3>
        <span class="to_translate" style="width: 80%;display: inline-block;">My Hand</span>
        <span class="to_translate" style="/*! float: right; */display: inline-block;">My Bid: </span>
        <span id="bidValue"></span> 
    </h3>
    <div id="myhand"></div>
    <div id="mybid"></div>
</div>




<script type="text/javascript">

var jstpl_cardontable = '<div class="cardontable suit_${suit} value_${value}" id="cardontable_${card_id}"></div>';
var jstpl_card = '<div class="cardontable suit_${suit} rank_${rank}" id="${card_id}"></div>';

</script>  

{OVERALL_GAME_FOOTER}
