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
<div id="table" class="player_count_{PLAYER_COUNT}">
    <div id="declarerevealtable">
        <h3 class="whiteblock">
            <span id="round_name_container" class="bgann_invisible">
                <span class="">{ROUND_LABEL} </span>
                <span id="round_name">1</span>
            </span>
            <span id="declaring_revealing_player_container">
                <span class="">{DECREV_PLAYER_LABEL}</span>
                <!-- PLAYER_NAME should say none for rounds with no declaring/revealing player -->
                <span class="" id="decrev_player_name">{NONE}</span>
            </span>
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
                <span class="bgann_playertable_tricks" id="trick_info_{PLAYER_ID}">
                    <span class="">Tricks taken: </span>
                    <span id="tricks_{PLAYER_ID}">0</span>
                    <span class="bgann_bid"> | Bid: </span>
                    <span id="bid_{PLAYER_ID}" class="bgann_bid bgann_bid_value">?</span>
                </span>
            </div>
            <!-- END player -->

            <div class="whiteblock" id="trumpContainer">
                <div class="">{TRUMP_LABEL}</div>
                <div class="bgann_trump_suit" id="trumpSuit">{NONE}</div>
            </div>
        </div>
        <div id="bids" class="">
            <div id="declaretable" class="whiteblock bgann_invisible bgann_bid_container">
                <h3 id="declared_label" class="bgann_decrev_label">{DECLARED_BID_LABEL}<span id="declaredBidValue"></span>
                </h3>
                <div id="declaredBid"></div>
            </div>
            <div id="revealtable" class="whiteblock bgann_invisible">
                <h3 id="revealed_label" class="bgann_decrev_label">{REVEALED_HAND_LABEL}</h3>
                <div id="revealedHand"></div>
            </div>
        </div>
    </div>
</div>

<div class="my_cards">
    <div class="whiteblock bgann_container" id="my_hand_container">
        <div class="bgann_section" style="flex-grow: 1;">
            <div style="width: auto; display: flex">
                <h3 id="myhandlabel" class="">{MY_HAND_LABEL}</h3>
                <h3 id="reveal_label" class="bgann_hidden">({REVEALED_LABEL})</h3>
            </div>
            <div id="myhand"></div>
        </div>
    </div>
    <div id="my_bid_container" class="bgann_section whiteblock bgann_bid_container bgann_hidden">
        <h3>
            <span class="" style="display: inline-block;">{MY_BID_LABEL}</span>
            <span id="bidValue"></span>
            <span id="declare_label" class="bgann_hidden">({DECLARED_LABEL})</span>
        </h3>
        <div id="mybid"></div>
    </div>
</div>



<script type="text/javascript">

var jstpl_cardontable = '<div class="bgann_cardontable bgann_suit_${suit} bgann_rank_${rank}" id="cardontable_${player_id}"></div>';
var jstpl_player_round_score = '\<div class="bgann_round_score">\
    \<span id="player_round_score_${id}" class="player_score_value">0\</span>\
    \<span class="fa fa-star bgann_round_score_icon"/>\
</div>';

</script>

{OVERALL_GAME_FOOTER}
