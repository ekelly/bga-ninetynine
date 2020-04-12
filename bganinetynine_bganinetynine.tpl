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
    <div id="declarerevealtable" class="declaringplayer">
        <h3 class="whiteblock declaringplayer">
            <span class="to_translate">Revealing/Declaring Player: </span>
            <!-- PLAYER_NAME should say none for rounds with no declaring/revealing player -->
            <span class="to_translate" id="decrev_player_name">None</span>
        </h3>
        <div id="revealtable" class="whiteblock reveal">
            <h3>Revealed Hand:</h3>
            <div id="revealedHand"></div>
        </div>
    </div>
    <div id="middleRow">
        <div id="playertables">

            <!-- BEGIN player -->
            <div class="playertable whiteblock playertable_{DIR}" id="playertable_{PLAYER_ID}">
                <div class="playertablename" style="color:#{PLAYER_COLOR}">
                    <span id="dealerindicator_{PLAYER_ID}" class="dealerindicator">(D)</span>
                    {PLAYER_NAME}
                </div>
                <div class="playertablecard" id="playertablecard_{PLAYER_ID}">
                </div>
                <span class="tricks">(<span id="tricks_{PLAYER_ID}" class="tricks">0</span>)</span>
            </div>
            <!-- END player -->

            <div class="whiteblock" id="trumpContainer">
                <div>Trump Suit:</div>
                <div id="trumpSuit">none</div>
            </div>
        </div>
        <div class="bids">
            <div id="declaretable" class="whiteblock bid_container declare">
                <h3>Declared Bid: <span id="declaredBidValue"></span></h3>
                <div id="declaredBid"></div>
            </div>
        </div>
    </div>
</div>

<div class="whiteblock container">
    <div class="section" style="flex-grow: 1;">
        <div class="to_translate" style="width: auto;"><h3>My Hand</h3></div>
        <div id="myhand"></div>
    </div>
    
</div>
<div class="section whiteblock bid_container">
                <h3>
                    <span class="to_translate" style="display: inline-block;">My Bid: 
                        <span id="bidValue"></span>
                        <span class="tricks"> / Tricks Taken: </span>
                        <span id="myTricksWon" class="tricks"></span>
                    </span>
                </h3>
                <div id="mybid"></div>
            </div>



<script type="text/javascript">

var jstpl_cardontable = '<div class="cardontable suit_${suit} rank_${rank}" id="cardontable_${player_id}"></div>';

</script>

{OVERALL_GAME_FOOTER}
