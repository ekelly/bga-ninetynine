{OVERALL_GAME_HEADER}

<!--
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- NinetyNine implementation : © Eric Kelly <boardgamearena@useric.com> & Alex Greenberg
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    ninetynine_ninetynine.tpl

    This is the HTML template of your game.

    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.

    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format

    See your "view" PHP file to check how to set variables and control blocks
-->
<div id="table">
    <div id="declarerevealtable">
        <h3 class="whiteblock">
            <span class="to_translate">Revealing/Declaring Player: </span>
            <!-- PLAYER_NAME should say none for rounds with no declaring/revealing player -->
            <span class="to_translate" id="decrev_player_name">None</span>
        </h3>
    </div>
    <div id="middleRow">
        <div id="playertables">

            <!-- BEGIN player -->
            <div class="bgann_playertable whiteblock bgann_playertable_{DIR}" id="playertable_{PLAYER_ID}">
                <div class="bgann_playertablename" style="color:#{PLAYER_COLOR}">
                    <span id="dealerindicator_{PLAYER_ID}" class="bgann_dealerindicator bgann_hidden">(D)</span>
                    {PLAYER_NAME}
                </div>
                <div class="bgann_playertablecard" id="playertablecard_{PLAYER_ID}">
                </div>
                <span class="bgann_tricks bgann_hidden">(<span id="tricks_{PLAYER_ID}" class="bgann_tricks bgann_hidden">0</span>)</span>
            </div>
            <!-- END player -->

            <div class="whiteblock" id="trumpContainer">
                <div class="to_translate">Trump Suit:</div>
                <div class="to_translate" id="trumpSuit">none</div>
            </div>
        </div>
        <div id="bids">
            <div id="declaretable" class="whiteblock bgann_bid_container bgann_declare bgann_hidden">
                <h3 class="to_translate">Declared Bid: <span id="declaredBidValue"></span>
                    <span class="bgann_tricks to_translate"> / Tricks Won: </span>
                    <span id="declaredTricksWon" class="bgann_tricks">0</span>
                </h3>
                <div id="declaredBid"></div>
            </div>
            <div id="revealtable" class="whiteblock bgann_reveal bgann_hidden">
                <h3 id="revealed_label" class="to_translate">Revealed Hand:</h3>
                <div id="revealedHand"></div>
            </div>

        </div>
    </div>
</div>

<div class="whiteblock bgann_container">
    <div class="bgann_section" style="flex-grow: 1;">
        <div style="width: auto; display: flex">
            <h3 id="myhandlabel" class="to_translate">My Hand</h3>
            <h3 id="reveal_label" class="bgann_hidden to_translate" style="margin-left: 5px;color: red;">(Revealed)</h3>
        </div>
        <div id="myhand"></div>
    </div>
</div>
<div id="my_bid_container" class="bgann_section whiteblock bgann_bid_container">
    <h3>
        <span class="to_translate" style="display: inline-block;">My Bid: </span>
        <span id="bidValue"></span>
        <span class="bgann_tricks bgann_hidden to_translate"> / Tricks Taken: </span>
        <span id="myTricksWon" class="bgann_tricks bgann_hidden"></span>
        <span id="declare_label" class="bgann_hidden to_translate" style="margin-left: 5px;color: red;">(Declared)</span>
    </h3>
    <div id="mybid"></div>
</div>



<script type="text/javascript">

var jstpl_cardontable = '<div class="bgann_cardontable bgann_suit_${suit} bgann_rank_${rank}" id="cardontable_${player_id}"></div>';
var jstpl_player_round_score = '\<div class="bgann_round_score">\
    \<span id="player_round_score_${id}" class="player_score_value">0\</span>\
    \<span class="fa fa-star bgann_round_score_icon"/>\
</div>';

</script>

{OVERALL_GAME_FOOTER}
