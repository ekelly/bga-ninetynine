/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * NinetyNine implementation : © Eric Kellye <boardgamearena@useric.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * ninetynine.js
 *
 * NinetyNine user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */
////////////////////////////////////////////////////////////////////////////////

/*
    In this file, you are describing the logic of your user interface, in Javascript language.
*/

define([
    "dojo",
    "dojo/_base/declare",
    "dojo/dom-style",
    "dojo/_base/lang",
    "dojo/dom-attr",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare, domStyle, lang, attr) {
    return declare("bgagame.ninetynine", ebg.core.gamegui, {

        constructor: function() {
            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;

            this.playerHand = null;
            this.cardwidth = 72;
            this.cardheight = 96;
            this.roundScoreCtrl = {};
            this.lastItemsSelected = [];

            // Globals
            this.shouldGiveCardsToWinner = false;
            this.autoplay = false;

            // Timeouts
            this.trickWinDelay = 500;
            this.winnerTakeDuration = 500;
            this.fadeOutDuration = 500;
            this.playCardDuration = 500;
            this.animateBidVisibilityDuration = 1500;
            this.playForcedCardDelay = 100;

            this.playForcedCardFuture = null;
        },

        /*
            setup:

            This method must set up the game user interface according to current game situation specified
            in parameter.

            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refresh the game page (F5)

            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        setup: function(gamedatas) {
            dojo.destroy('debug_output');

            // For debugging purposes
            window.keleric = this;

            // Setting up player boards
            if (this.gamedatas.usesRounds) {
                for (var player_id in gamedatas.players) {
                    var player = gamedatas.players[player_id];

                    // Setting up players boards if needed
                    var player_score_div = $('player_board_'+player_id);
                    dojo.place(this.format_block('jstpl_player_round_score', player), player_score_div);

                    var targetId = "player_round_score_" + player_id;
                    var counter = new ebg.counter();
                    counter.create(targetId);
                    this.roundScoreCtrl[player_id] = counter;
                }
                this.addTooltipToClass("bgann_round_score", _("Round Score"), _('See last score'));
                var displayLastScore = dojo.hitch(this, this.displayLastScore);
                dojo.query(".bgann_round_score").forEach(function(node) {
                    node.onclick = displayLastScore;
                });

                // Show round number
                this.setNodeInvisible("round_name_container", false);
                this.updateRoundNum(this.gamedatas.roundNum);
            } else {
                // Show hand number
                this.setNodeInvisible("round_name_container", false);
                this.updateRoundNum(this.gamedatas.handNum);
            }

            this.clearTricksWon();

            // Remove elements which spectators do not need
            if (this.isSpectator) {
                this.setNodeHidden("my_bid_container", true);
                this.setNodeHidden("my_hand_container", true);
            }

            // Player hand
            this.playerHand = this.setupCardStocks('myhand', 'onPlayerHandSelectionChanged');
            // Cards in player's hand
            this.addCardsToStock(this.playerHand, this.gamedatas.hand);
            this.unmarkUnplayableCards();
            this.handlePlayableCards(this.gamedatas.playableCards);

            // Player bid
            this.playerBid = this.setupCardStocks('mybid');

            // Declared Bid
            this.declaredBid = this.setupCardStocks('declaredBid');

            // Revealed Hand
            this.revealedHand = this.setupCardStocks('revealedHand');
            this.revealedHand.setOverlap(20, 0);

            // Cards played on table
            for (var i in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[i];
                var color = card.type;
                var value = card.type_arg;
                var player_id = card.location_arg;
                this.playCardOnTable(player_id, color, value, card.id);
            }

            // Cards in the player's bid
            this.addCardsToStock(this.playerBid, this.gamedatas.bid.cards);
            if (Object.entries(this.gamedatas.bid.cards).length == 3) {
                this.setNodeHidden("my_bid_container", false);
            }
            this.updateSelfBid();
            this.showActiveDeclareOrReveal(this.gamedatas.declareReveal);

            // Current dealer
            this.showDealer(this.gamedatas.dealer);

            // Current player
            this.showCurrentPlayer(this.gamedatas.currentPlayer);

            // Current trump
            this.showTrump(this.gamedatas.trump);

            // Set trick counts
            this.updateTrickCounts(this.gamedatas.trickCounts,
                                   this.gamedatas.declareReveal.playerId,
                                   false);

            // Set scores
            this.updateRoundScores(this.gamedatas.roundScores);
            this.updateGameScores(this.gamedatas.gameScores);

            this.addTooltip("declaretable", _("Opponent's declared bid"), '');
            this.addTooltip("revealtable", _("Opponent's revealed hand"), '');
            this.addTooltipToClass("player_score", _("Game Score"), _('See last score'));
            var displayLastScore = dojo.hitch(this, this.displayLastScore);
            dojo.query(".player_score").forEach(function(node) {
                node.onclick = displayLastScore;
            });

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            dojo.connect(window, "onresize", this, dojo.hitch(this, "adaptViewportSize"));

            this.ensureSpecificImageLoading(['../common/point.png']);
        },

        // Initialize a card stock
        // Arguments: div id, function which occurs when the card selection changes
        setupCardStocks: function(id, selectionChangeFunctionName) {
            var stock = new ebg.stock();
            stock.create(this, $(id), this.cardwidth, this.cardheight);
            stock.image_items_per_row = 13;
            stock.autowidth = true;
            if (selectionChangeFunctionName != undefined && selectionChangeFunctionName.length > 0) {
                stock.setSelectionMode(2);
                stock.setSelectionAppearance('class');
                dojo.connect(stock, 'onChangeSelection', this, selectionChangeFunctionName);
            } else {
                stock.setSelectionMode(0);
            }
            // Order of id: ["club", "diamond", "spade", "heart"];
            for (var color = 0; color < 4; color++) {
                for (var rank = 2; rank <= 14; rank++) {
                    // Build card type id
                    var card_type_id = this.getCardUniqueId(color, rank);
                    var card_weight = this.getCardWeight(color, rank);
                    stock.addItemType(card_type_id, card_weight, g_gamethemeurl+'img/cards.jpg', card_type_id);
                }
            }
            return stock;
        },

        // Take care of any logic related to resizing the window
        adaptViewportSize: function() {
            this.adjustCardOverlapToAvailableSpace();
        },

        // Adjust the overlap of cards in your hand
        adjustCardOverlapToAvailableSpace: function() {
            var bodycoords = dojo.marginBox("my_hand_container");
            var numberOfCardsWhichWrap = 0;
            var contentWidth = bodycoords.w - 20; // Minus 10 pixels of padding on either side
            var cardCountInHand = this.playerHand.getAllItems().length;
            var fullSize = cardCountInHand * 76 + (cardCountInHand / 2); // plus a little extra padding
            var cardsThatCanFit = contentWidth / 77;
            numberOfCardsWhichWrap = Math.max(0, cardCountInHand - cardsThatCanFit);
            this.playerHand.setOverlap(100 - Math.min(90, numberOfCardsWhichWrap * 10));
        },

        ///////////////////////////////////////////////////
        //// Utility functions for game preferences

        shouldAddCardHoverEffect: function() {
            return this.prefs[100].value == 1;
        },

        shouldSortCardsInHeartsOrder: function() {
            return this.prefs[101].value == 2;
        },

        shouldHighlightTrump: function() {
            return this.prefs[102].value == 1;
        },

        shouldHighlightPlayableCards: function() {
            return this.prefs[103].value == 1;
        },

        shouldPlayForcedCards: function() {
            return false; // this.prefs[104].value == 1 && !this.isReadOnly();
        },

        shouldHighlightTrickWins: function() {
            return this.prefs[106].value == 1 && !this.isReadOnly();
        },

        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //

        onEnteringState: function(stateName, args) {
            console.log("Entering state: " + stateName);
            switch (stateName) {
                case 'playerTurn':
                    this.addTooltip('myhand', _('Cards in my hand'), _('Play a card'));
                    this.playerHand.setSelectionMode(2);
                    this.addHoverEffectToCards("myhand", true);
                    this.showTrickLabels();
                    if (this.getActivePlayerId() != null) {
                        this.showCurrentPlayer(this.getActivePlayerId());
                    }
                    if (args && args.args && args.args._private && args.args._private.playableCards) {
                        this.handlePlayableCards(args.args._private.playableCards);
                    }
                    break;

                case 'bidding':
                    this.updateSelfBid();
                    // This also clears tricks won
                    this.clearActiveDeclareOrReveal();
                    // It looks nicer if we use ? instead of 0
                    dojo.byId("tricks_" + this.player_id).textContent = "?";
                    this.addHoverEffectToCards("myhand", true);
                    this.addTooltipsToEachCard(this.playerHand, _('Add to bid'));
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function(stateName) {
            console.log("Leaving state: " + stateName);
            switch (stateName) {
                case 'bidding':
                    this.clearTooltipsFromCards(this.playerHand);
                    this.clearTooltipsFromCards(this.playerBid);
                    this.addHoverEffectToCards("myhand", false);
                    this.showTrickLabels();
                    dojo.byId("tricks_" + this.player_id).textContent = "0";
                    this.playerHand.setSelectionMode(1);
                    break;
            }
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function(stateName, args) {
            console.log("Updating action buttons with state: " + stateName + ". Current player active? " + this.isCurrentPlayerActive());
            if (this.isCurrentPlayerActive()) {
                switch (stateName) {
                    case 'bidding':
                        this.addActionButton('reveal_button', _('Reveal'), 'onReveal');
                        this.addActionButton('declare_button', _('Declare'), 'onDeclare');
                        this.addActionButton('none_button', _('Neither'), 'onNoDeclare');
                        break;
                }
            } else if (!this.isSpectator) {
                switch (stateName) {
                    case 'bidding':
                        this.addActionButton('undo_button', _('Undo bid'), 'onUndoBid');
                        break;
                }
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /*
           General Utility methods
         */

        // Returns true for spectators, instant replay (during game), archive mode (after game end)
        isReadOnly: function () {
            return this.isSpectator || typeof g_replayFrom != 'undefined' || g_archive_mode;
        },

        // Get the number of players in the game
        getPlayerCount: function() {
            return this.gamedatas.playerorder.length;
        },

        /*
           Card Utility methods
         */

        // Return the 'type id' of the card object given to us by the server
        getCardType: function(serverCard) {
            var color = serverCard.type;
            var rank = serverCard.type_arg;
            return this.getCardUniqueId(color, rank);
        },

        // Get card unique identifier based on its suit and rank
        getCardUniqueId: function(suit, rank) {
            return parseInt(suit) * 13 + (parseInt(rank) - 2);
        },

        // Return the string suit from the card id (also known as the card type)
        getCardSuitFromId: function(card_id) {
            return this.getCardSuit(this.getCardSuitNumFromId(card_id));
        },

        // Return the card suit string from the numerical representation
        getCardSuit: function(suit) {
            return ["club", "diamond", "spade", "heart"][suit];
        },

        // Return the numerical value of the suit from the card id (also known as the card type)
        getCardSuitNumFromId: function(card_id) {
            return Math.floor(card_id / 13);
        },

        // Return the rank from the card id (also known as the card type)
        getCardRankFromId: function(card_id) {
            return (card_id % 13) + 2;
        },

        // Given the numerical value of the suit, return the number of
        // points that it is worth in bidding
        getBidValueFromSuit: function(suit) {
            return {club: 3, diamond: 0, spade: 1, heart: 2}[suit];
        },

        // Return true if both passed cards are the same
        // Only works for cards that are retrieved from the various stocks
        isCardSame: function(cardOne, cardTwo) {
            return cardOne.id == cardTwo.id && cardOne.type == cardTwo.type;
        },

        // Return true if a card is present in the given stock
        isCardInStock: function(stock, card) {
            var foundCard = false;
            var that = this;
            stock.getAllItems().forEach(function(stockCard) {
                if (that.isCardSame(stockCard, card)) {
                    foundCard = true;
                    return;
                }
            });
            return foundCard;
        },

        getCardsFromStockById: function(stock, cardIds) {
            return stock.getAllItems().filter(function (card) {
                return cardIds.includes(parseInt(card.id));
            });
        },

        // Map cards given to us by the server into cards that stocks deal with
        serverCardsToClientCards: function(serverCards) {
            var that = this;
            return Object.entries(serverCards).map(function(serverCard) {
                var card = serverCard[1];
                var color = card.type;
                var rank = card.type_arg;
                return {
                  type: that.getCardType(card),
                  id: card.id
                };
            });
        },

        // Add an array of server cards to a particular stock
        addCardsToStock: function(stock, cards) {
            this.serverCardsToClientCards(cards).forEach(function(card) {
                stock.addToStockWithId(card.type, card.id);
            });
        },

        /*
           Card UI utility functions
         */

        // Add a particular tooltip to each card in the stock
        addTooltipsToEachCard: function(stock, actionString) {
            var allCards = stock.getAllItems();
            for (var i = 0; i < allCards.length; i++) {
                var cardData = allCards[i];
                this.addTooltipToCard(stock, cardData, actionString);
            }
        },

        // Add a tooltip to an individual card from a stock
        addTooltipToCard: function(stock, cardData, actionString) {
            var suit = this.getCardSuitFromId(cardData.type);
            var divId = stock.getItemDivId(cardData.id);
            var bidValue = this.getBidValueFromSuit(suit);
            this.addTooltip(divId, _('Bid value: ') + bidValue, actionString);
        },

        // Remove a tooltip from a card
        clearTooltipFromCard: function(stock, cardData) {
            var divId = stock.getItemDivId(cardData.id);
            this.removeTooltip(divId);
        },

        // Remove all tooltips from all cards in the stock
        clearTooltipsFromCards: function(stock) {
            var allCards = stock.getAllItems();
            for (var i = 0; i < allCards.length; i++) {
                var cardData = allCards[i];
                this.clearTooltipFromCard(stock, cardData);
            }
        },

        // Given a set of playable cards from the server, do all required actions
        handlePlayableCards: function(playableCards) {
            this.markCardsUnplayable(playableCards);
            if (!this.shouldPlayForcedCards()) {
                return;
            }
            var playableCardArray = Object.entries(playableCards).map(entry => entry[1])
            if (playableCardArray.length == 1 || this.autoplay) {
                var that = this;
                this.playForcedCardFuture = setTimeout(function() {
                    that.playCard(playableCardArray[0]);
                }, this.playForcedCardDelay);
            }
        },

        // Mark cards in your hand which are not in the playableCards array as unplayable
        // Note: playableCards is an map sent from the server - they are not client-side cards
        markCardsUnplayable: function(playableCards) {
            var playableCardArray = Object.entries(playableCards).map(entry => entry[1])
            var mappingFunction = this.getCardType.bind(this);
            var playableCardTypes = playableCardArray.map(mappingFunction);
            var allCards = this.playerHand.getAllItems();
            for (var i = 0; i < allCards.length; i++) {
                var cardData = allCards[i];
                if (!playableCardTypes.includes(cardData.type)) {
                    var divId = this.playerHand.getItemDivId(cardData.id);
                    dojo.addClass(divId, "bgann_unplayable");
                }
            }
        },

        // Cards in your hand which are marked as unplayable should be 'unmarked'
        unmarkUnplayableCards: function() {
            var allCards = this.playerHand.getAllItems();
            for (var i = 0; i < allCards.length; i++) {
                var cardData = allCards[i];
                var divId = this.playerHand.getItemDivId(cardData.id);
                dojo.removeClass(divId, "bgann_unplayable");
            }
        },

        // Add a hover effect to cards within the containing id
        addHoverEffectToCards: function(containingId, enable) {
            if (enable && this.shouldAddCardHoverEffect()) {
                dojo.addClass(containingId, "bgann_cardhover");
            } else {
                dojo.removeClass(containingId, "bgann_cardhover");
            }
        },

        // Highlight all the trump cards in your hand
        highlightTrump: function(enable, trumpSuit) {
            if (!this.shouldHighlightTrump()) {
                return;
            }
            var allCards = this.playerHand.getAllItems();
            for (var i = 0; i < allCards.length; i++) {
                var cardData = allCards[i];
                var suit = this.getCardSuitNumFromId(cardData.type);
                var divId = this.playerHand.getItemDivId(cardData.id);
                if (enable && suit == trumpSuit) {
                    dojo.addClass(divId, "bgann_trump");
                } else {
                    dojo.removeClass(divId, "bgann_trump");
                }
            }
        },

        // This is the order that cards are sorted
        // Order of color: ["club", "diamond", "spade", "heart"] (passed to this function as an int)
        getCardWeight: function(color, value) {
            var heartsOrder = this.shouldSortCardsInHeartsOrder();
            var adjustedColor = color;
            if (!heartsOrder) {
                adjustedColor = (color + 3) % 4;
            }
            return parseInt(adjustedColor) * 13 + (parseInt(value) - 2);
        },

        /*
           Generic UI Utility methods
         */

        // Make nodeId disappear
        setNodeHidden: function(nodeId, hidden) {
            if (hidden) {
                dojo.addClass(nodeId, "bgann_hidden");
            } else {
                dojo.removeClass(nodeId, "bgann_hidden");
            }
        },

        // Make nodeId disappear, but have it still be 'present' in the dom
        setNodeInvisible: function(nodeId, hidden) {
            if (hidden) {
                dojo.addClass(nodeId, "bgann_invisible");
            } else {
                dojo.removeClass(nodeId, "bgann_invisible");
            }
        },

        // Retrieve the contents of nodeId
        getValueFromNode: function(nodeId) {
            return dojo.attr(dojo.byId(nodeId), 'innerHTML');
        },

        // Update the contents of nodeId with the supplied value
        updateValueInNode: function(nodeId, value) {
            var node = dojo.byId(nodeId);
            if (node != null) {
                node.textContent = value;
            }
        },

        /*
           Bid UI utility methods
         */

        // Given the cards making up the bid, update the appropriate UI
        // Note: Only works for cards from the stock, not the server
        updateBidState: function(bidCards) {
            // Move those items to the bid
            this.setNodeHidden("my_bid_container", false);

            if (this.playerBid.getAllItems().length != 3) {
                this.playerBid.removeAll();
                var that = this;
                bidCards.forEach(function(card) {
                    var divId = that.playerHand.getItemDivId(card.id);
                    if (divId) {
                        that.playerBid.addToStockWithId(card.type, card.id, divId);
                    } else {
                        that.playerBid.addToStockWithId(card.type, card.id);
                    }
                    that.playerHand.removeFromStockById(card.id);
                    that.clearTooltipFromCard(that.playerHand, card);
                });
            }
            this.updateSelfBid();
            this.playerHand.unselectAll();
            this.adjustCardOverlapToAvailableSpace();
            this.lastItemsSelected = [];
        },

        // Update the computed value of the bid
        // This will prioritize cards in the playerBid stock, but if that
        // has no cards it will fall back to selected items from the playerHand
        updateSelfBid: function() {
            var cards = this.playerBid.getAllItems();
            if (cards.length == 0) {
                // If we still need to submit our bid
                cards = this.playerHand.getSelectedItems();
            }
            this.updateCurrentBidFromCards(cards, "bidValue");
            this.updateCurrentBidFromCards(cards, "bid_" + this.player_id);
        },

        // Given a stock containing bid cards, update the value of the
        // bid in divId
        updateCurrentBidFromBidStock: function(bidStock, divId) {
            this.updateCurrentBidFromCards(bidStock.getAllItems(), divId);
        },

        // Given a list of bid cards, update the value of the
        // bid in divId
        updateCurrentBidFromCards: function(cardList, divId) {
            var bid = this.getBidValueFromCards(cardList);
            var bidValueSpan = dojo.byId(divId);
            if (bidValueSpan) {
                bidValueSpan.textContent = bid;
            }
        },

        // Return the string representation of the value of a particular
        // set of cards representing a bid
        getBidValueFromCards: function(cardList) {
            if (cardList.length == 0) {
                return "?";
            }
            var bid = 0;
            for (var x = 0; x < cardList.length; x++) {
                var card = cardList[x];
                var id = card.type;
                var suit = this.getCardSuitFromId(id);
                var bidValue = this.getBidValueFromSuit(suit);
                bid += bidValue;
            }
            if (this.getPlayerCount() == 4 && bid == 0 && cardList.length == 3) {
                return "0/10";
            }
            return bid;
        },

        // Animate the display of the declared bid and revealed cards
        animateBidVisibility: function(visible, reveal) {
            if (visible) {
                this.setNodeInvisible("revealtable", !reveal);
                this.setNodeInvisible("declaretable", false);
                dojo.addClass("bids", "bgann_showbid");
            } else {
                dojo.removeClass("bids", "bgann_showbid");
                if (this.isReadOnly()) {
                    this.setNodeInvisible("revealtable", true);
                    this.setNodeInvisible("declaretable", true);
                } else {
                    // This timeout should match the transition speed of #bids
                    var that = this;
                    setTimeout(function() {
                        that.setNodeInvisible("revealtable", true);
                        that.setNodeInvisible("declaretable", true);
                    }, this.animateBidVisibilityDuration);
                }
            }
        },

        /*
           Scoring UI utility methods
         */

        // Show the trick counter information within the player tables
        showTrickCounters: function(showTrickCounters) {
            var counters = dojo.query(".bgann_playertable_tricks");
            if (showTrickCounters) {
                counters.removeClass("bgann_hidden");
            } else {
                counters.addClass("bgann_hidden");
            }
        },

        // Update the number of tricks won by a particular player
        // trickCounts: number of tricks each player won
        // declaringPlayerId: The id of the player that declared
        // animate: true if this occurred as a result of winning a trick
        updateTrickCounts: function(trickCounts, declaringPlayerId, animate) {
            for (var playerId in trickCounts) {
                this.updateCurrentTricksWon(playerId,
                                            trickCounts[playerId],
                                            trickCounts[declaringPlayerId],
                                            animate);
            }
        },

        // Update a specific player's tricks won counter
        updateCurrentTricksWon: function(playerId, tricksWon, declaringPlayerTricks, animate) {
            if (this.checkAction('submitBid', true)) {
                // We should skip this if we're bidding
                return;
            }
            // Only animate if the user has set their preferences to do so
            shouldAnimate = animate && this.shouldHighlightTrickWins();

            if (playerId == this.player_id) {
                if (shouldAnimate) {
                    this.animateScoreDisplay("myTricksWon", tricksWon);
                } else {
                    this.updateValueInNode("myTricksWon", tricksWon);
                }
            }
            if (shouldAnimate) {
                this.animateScoreDisplay("declaredTricksWon", declaringPlayerTricks);
                this.animateScoreDisplay("tricks_"+playerId, tricksWon);
            } else {
                this.updateValueInNode("declaredTricksWon", declaringPlayerTricks);
                this.updateValueInNode("tricks_" + playerId, tricksWon);
            }
        },

        // Animate an increase in scores
        animateScoreDisplay: function(nodeId, score) {
            if (dojo.byId(nodeId) != null) {
                var content = this.getValueFromNode(nodeId);
                if (content != score) {
                    this.displayScoring(nodeId, "ff0000", '+1', 750);
                    var that = this;
                    setTimeout(function() {
                        that.updateValueInNode(nodeId, score);
                    }, 800);
                }
            }
        },

        // Update the hand information for a particular player, highlighting
        // if they have declared or revealed, and their bid
        showTrickLabels: function(playerId, bidValue, reveal) {
            if (playerId) {
                this.updateValueInNode("bid_" + playerId, bidValue);
                dojo.addClass("trick_info_" + playerId, "bgann_declare");
                if (reveal) {
                    dojo.addClass("trick_info_" + playerId, "bgann_reveal");
                }
            }
        },

        // Hide the playertable hand information
        // Note: 'trick' label is a misnomer. It more accurately includes 'hand' info,
        // though it does contain the number of tricks a player has won
        resetTrickLabels: function() {
            // No labels should be declared or revealed
            dojo.query(".bgann_playertable_tricks").removeClass("bgann_declare bgann_reveal");

            // All bids should be reset to ?
            dojo.query(".bgann_bid_value").forEach(function(node) {
                node.innerHTML = "?";
            });
        },

        // Reset the number of tricks won by all players to 0
        clearTricksWon: function() {
            this.updateValueInNode("myTricksWon", "0");
            for (var playerId in this.gamedatas.players) {
                this.updateValueInNode("tricks_" + playerId, "0");
            }
            this.updateValueInNode("declaredTricksWon", "0");
            this.resetTrickLabels();
        },

        // Update the game scores of all players
        updateGameScores: function(gameScores) {
            for (var playerId in gameScores) {
                this.updatePlayerScore(parseInt(playerId), parseInt(gameScores[parseInt(playerId)]));
            }
        },

        // Update a particular player's total score
        updatePlayerScore: function(playerId, playerScore) {
            if (this.scoreCtrl[playerId]) {
                this.scoreCtrl[playerId].toValue(playerScore);
            }
        },

        // Update the round scores of all players
        updateRoundScores: function(roundScores) {
            for (var playerId in roundScores) {
                this.updateRoundScore(parseInt(playerId), parseInt(roundScores[parseInt(playerId)]));
            }
        },

        // Update the round scores for a particular player
        updateRoundScore: function(playerId, playerRoundScore) {
            if (this.roundScoreCtrl[playerId]) {
                this.roundScoreCtrl[playerId].toValue(playerRoundScore);
            }
        },

        // Reset the round scores to 0
        clearRoundScores: function() {
            for (var playerId in this.gamedatas.players) {
                this.updateRoundScore(parseInt(playerId), "0");
            }
        },

        // Update the round number tracker
        updateRoundNum: function(roundNum) {
            var roundNumSpan = dojo.byId("round_name");
            if (roundNumSpan) {
                roundNumSpan.textContent = roundNum;
            }
        },

        /*
           Declare/Reveal related UI utility methods
         */

        // Announce the information about who has declared/revealed
        informUsersPlayerDeclaredOrRevealed: function(decRevInfo) {
            if (!decRevInfo.playerId) {
                return;
            }
            var reveal = Object.keys(decRevInfo.cards).length > 0;
            var message;
            if (decRevInfo.playerId != this.player_id || this.isSpectator) {
                // Someone else declared or revealed
                if (reveal) {
                    message = decRevInfo.playerName + _(" has revealed");
                } else {
                    message = decRevInfo.playerName + _(" has declared");
                }
            } else {
                // You have declared or revealed
                if (reveal) {
                    message = _("You have revealed");
                } else {
                    message = _("You have declared");
                }
            }
            this.showMessage(message, "info");
        },

        // Update the UI with the information about who has
        // declared/revealed, and their exposed information
        showActiveDeclareOrReveal: function(decRevInfo) {
            var playerNameSpan = dojo.byId("decrev_player_name");
            if (decRevInfo.playerId) {
                var clientCards = this.serverCardsToClientCards(decRevInfo.bid);
                var bidValue = this.getBidValueFromCards(clientCards);
                var didReveal = Object.keys(decRevInfo.cards).length > 0;
                if (decRevInfo.playerId != this.player_id) {
                    this.animateBidVisibility(true, didReveal);
                    // Show Revealed cards
                    this.revealedHand.removeAll();
                    this.addCardsToStock(this.revealedHand, decRevInfo.cards);
                    // Show Declared bid
                    this.declaredBid.removeAll();
                    this.addCardsToStock(this.declaredBid, decRevInfo.bid);
                    // Hide declare/reveal label for myself
                    this.setNodeHidden("declare_label", true);
                    this.setNodeHidden("reveal_label", true);
                } else {
                    this.setNodeHidden("declare_label", false);
                    if (didReveal) {
                        this.setNodeHidden("reveal_label", false);
                    }
                }
                // Set the name in the banner for the player who declared/revealed
                playerNameSpan.textContent = decRevInfo.playerName;
                var playerColor = decRevInfo.playerColor;
                domStyle.set(playerNameSpan, "color", "#" + playerColor);

                // Put the player's bid in the player table
                this.showTrickLabels(decRevInfo.playerId, bidValue, didReveal);
                this.updateValueInNode("bid_" + decRevInfo.playerId, bidValue);
            } else {
                playerNameSpan.textContent = _("None");
                domStyle.set(playerNameSpan, "color", "#000000");
                this.animateBidVisibility(false);
                // Hide Declare and reveal if there isn't a declaring or revealing player
                this.showTrickLabels();
            }
            this.updateCurrentBidFromBidStock(this.declaredBid, "declaredBidValue");
        },

        // Reset the UI showing active declare/reveal information
        clearActiveDeclareOrReveal: function() {
            this.showActiveDeclareOrReveal({});
            this.declaredBid.removeAll();
            this.revealedHand.removeAll();
            this.updateCurrentBidFromBidStock(this.declaredBid, "declaredBidValue");
            this.animateBidVisibility(false);
            this.clearTricksWon();
            // Hide declare/reveal label for myself
            this.setNodeHidden("declare_label", true);
            this.setNodeHidden("reveal_label", true);
        },

        /*
           Trick-related UI utility methods
         */

        // Play a particular card from coming from player_id on the table
        // The card will come from the player boards, unless the card
        // already exists on the table in either the revealed player's hand
        // or 'my' hand
        playCardOnTable: function(player_id, suit, value, card_id) {
            dojo.place(
                this.format_block('jstpl_cardontable', {
                    card_id: card_id,
                    suit: this.getCardSuit(suit),
                    rank: value,
                    player_id: player_id
                }), 'playertablecard_'+player_id);

            var cardCameFromSomeoneElse = player_id != this.player_id || this.isSpectator;
            if (cardCameFromSomeoneElse) {
                // Some opponent played a card (or spectator is observing)
                if ($('revealedHand_item_'+card_id)) {
                    this.placeOnObject('cardontable_'+player_id, 'revealedHand_item_'+card_id);
                    this.revealedHand.removeFromStockById(card_id);
                } else {
                    // Move card from player panel
                    this.placeOnObject('cardontable_'+player_id, 'overall_player_board_'+player_id);
                }
            } else {
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item
                if ($('myhand_item_'+card_id)) {
                    this.placeOnObject('cardontable_'+player_id, 'myhand_item_'+card_id);
                    this.playerHand.removeFromStockById(card_id);
                }
            }
            // In any case: move it to its final destination
            this.slideToObject('cardontable_'+player_id, 'playertablecard_'+player_id).play();

            // Adjust card overlap now that there are fewer cards in hand
            if (!cardCameFromSomeoneElse) {
                this.adjustCardOverlapToAvailableSpace();
            }
        },

        // Move all the cards currently played on the table to the trick winner
        // and update relevant trick counts
        giveAllCardsToPlayer: function(winner_id, playerTrickCounts, decRevPlayerId) {
            // Move all cards on table to given table, then destroy them
            this.updateTrickCounts(playerTrickCounts, decRevPlayerId, true);

            for (var player_id in this.gamedatas.players) {
                // There's a race condition between cards leaving the table and cards
                // being placed on the table. In order to avoid that, we clone the original
                // card and replace it with one that has a different id.
                var node = dojo.byId('cardontable_'+player_id);
                var newnode = lang.clone(node);
                if (newnode) {
                   attr.set(newnode, "id", 'cardfromtable_'+player_id);
                   dojo.place(newnode, 'playertablecard_'+player_id);
                }
                dojo.destroy(node);

                this.moveCardToWinner(winner_id, player_id);
            }
        },

        // Move an individual card that player_id played to the trick winner
        moveCardToWinner: function(winner_id, player_id) {
            if (this.shouldGiveCardsToWinner) {
                // Animate cards to the winner player off screen
                var anim;
                if (winner_id == this.player_id && dojo.byId("maingameview_menufooter")) {
                    anim = this.slideToObject('cardfromtable_'+player_id, "maingameview_menufooter");
                } else {
                    anim = this.slideToObject('cardfromtable_'+player_id, 'overall_player_board_'+winner_id);
                }
                dojo.connect(anim, 'onEnd', function(node) { dojo.destroy(node);});
                anim.play();
            } else {
                // Animate cards to the card which won, not the player
                var anim = this.slideToObject('cardfromtable_' + player_id, 'playertablecard_' + winner_id, this.winnerTakeDuration);
                dojo.connect(anim, 'onEnd', this, 'fadeOutAndDestroy');
                anim.play();
            }
        },

        /*
           Other UI utility methods
         */

        // Show the trump suit
        // Input: [0 = clubs ... 3 = hearts]
        // If the input is anything other than 0..3, 'None' is displayed
        showTrump: function(trumpSuit) {
            var trumpSuitSpan = dojo.byId("trumpSuit");
            if (trumpSuit != undefined &&
                trumpSuit != null &&
                trumpSuit >= 0 &&
                trumpSuit < 4) {

                var redSuit = trumpSuit % 2 == 1;
                trumpSuitSpan.textContent = ["♣", "♦", "♠", "♥"][trumpSuit];
                dojo.query("#trumpSuit").removeClass("bgann_trump_red");
                dojo.query("#trumpSuit").removeClass("bgann_trump_black");
                dojo.query("#trumpSuit").removeClass("bgann_trump_none");
                dojo.addClass(trumpSuitSpan, redSuit ? "bgann_trump_red" : "bgann_trump_black")
                this.highlightTrump(true, trumpSuit);
            } else {
                trumpSuitSpan.textContent = _("None");
                dojo.query("#trumpSuit").removeClass("bgann_trump_red");
                dojo.query("#trumpSuit").removeClass("bgann_trump_black");
                dojo.addClass(trumpSuitSpan, "bgann_trump_none")

                this.highlightTrump(false);
            }
        },

        // Provide a visual indication as to who the dealer is
        showDealer: function(dealer_id) {
            dojo.query(".bgann_dealerindicator").addClass("bgann_hidden");
            this.setNodeHidden("dealerindicator_" + dealer_id, false);
        },

        // Provide a visual indication as to who's action it is
        showActivePlayer: function(expectedActivePlayer) {
            var activePlayer = this.getActivePlayerId();
            if (activePlayer == null) {
                activePlayer = expectedActivePlayer;
            }
            this.showCurrentPlayer(activePlayer);
        },

        // Provide a visual indication as to who's action it is
        // NOTE: bgann_firstplayer is a misnomer
        showCurrentPlayer: function(currentPlayer) {
            dojo.query(".bgann_playertable").removeClass("bgann_firstplayer");
            dojo.addClass("playertable_" + currentPlayer, "bgann_firstplayer");
        },

        ///////////////////////////////////////////////////
        //// Player's action

        /*

            Here, you are defining methods to handle player's action (ex: results of mouse click on
            game objects).

            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server

        */

        // Callback for when the player hand selection changes
        onPlayerHandSelectionChanged: function() {
            var items = this.playerHand.getSelectedItems();
            if (!this.isCurrentPlayerActive()) {
                console.log("Not your turn. Unselecting card");
                this.playerHand.unselectAll();
                this.lastItemsSelected = [];
            }
            if (this.checkAction('playCard', true)) {
                if (items.length > 0) {
                    // Can play a card
                    this.playCard(items[0]);
                }
            } else if (this.checkAction('submitBid', true)) {
                var that = this;
                if (items.length > 3) {
                    // Disallow adding more than three cards to the bid
                    items.forEach(function(item) {
                        if (!that.lastItemsSelected.includes(item)) {
                            that.playerHand.unselectItem(item.id);
                        }
                    });
                    return;
                }
                this.lastItemsSelected = items;
                // We still need to update the bid if we unselect all our cards
                this.updateSelfBid();

                // Add the proper tooltips
                items.forEach(function(item) {
                    that.clearTooltipFromCard(that.playerHand, item);
                    that.addTooltipToCard(that.playerHand, item, _('Remove from bid'));
                });
                this.playerHand.getUnselectedItems().forEach(function(item) {
                    that.clearTooltipFromCard(that.playerHand, item);
                    that.addTooltipToCard(that.playerHand, item, _('Add to bid'));
                });
            }
        },

        // Play an individual card
        playCard: function(card) {
            clearTimeout(this.playForcedCardFuture);
            this.ajaxCallWrapper('playCard', { id: card.id });
            this.playerHand.unselectAll();
        },

        // When the 'Neither' bid is selected
        onNoDeclare: function() {
            this.submitBid(0);
        },

        // When the 'Declare' bid is selected
        onDeclare: function() {
            this.confirmationDialog(_('Are you sure you want to declare your bid?'),
                                    dojo.hitch(this, function() {
                this.submitBid(1);
            }));
        },

        // When the 'Reveal' bid is selected
        onReveal: function() {
            this.confirmationDialog(_('Are you sure you want to reveal your hand?'),
                                    dojo.hitch(this, function() {
                this.submitBid(2);
            }));
        },

        // Trigger undoing a bid
        onUndoBid: function() {
            this.ajaxCallWrapper("undoBid", {}, true);

            // Move bid cards back to the hand
            var items = this.playerBid.getAllItems();
            var that = this;
            items.forEach(function(item) {
                var divId = that.playerBid.getItemDivId(item.id);
                that.playerHand.addToStockWithId(item.type, item.id, divId);
                that.playerBid.removeFromStockById(item.id);
            });
            this.playerHand.getAllItems().forEach(function(item) {
                that.clearTooltipFromCard(that.playerHand, item);
                that.addTooltipToCard(that.playerHand, item, _('Add to bid'));
            });
            this.setNodeHidden("my_bid_container", true);

            this.adjustCardOverlapToAvailableSpace();
            this.lastItemsSelected = [];
            this.updateSelfBid();
        },

        // Submit an individual bid
        // decrev should be 0 = none, 1 = declare, 2 = reveal
        submitBid: function(decrev) {
            if (this.checkAction('submitBid')) {
                var items = this.playerHand.getSelectedItems();

                if (items.length != 3) {
                    this.showMessage(_("You must select exactly 3 cards"), 'error');
                    return;
                }

                // Give these 3 cards
                var to_give = '';
                for (var i in items) {
                    to_give += items[i].id+';';
                }
                this.ajaxCallWrapper('submitBid', {
                    cards: to_give,
                    declareOrReveal: decrev,
                });
            }
        },

        // Display the last score table
        displayLastScore: function() {
            this.ajaxCallWrapper("displayScore", {}, true);
        },

        // Wrap making AJAX calls to the backend
        ajaxCallWrapper: function(action, args, skipActionCheck, handler) {
            if (!args) {
                args = {};
            }
            args.lock = true;

            if (skipActionCheck || this.checkAction(action)) {
                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html",
                              args, this, (result) => {}, handler);
            }
        },

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:

            In this method, you associate each of your game notifications with your local method to handle it.

            Note: game notification names correspond to your "notifyAllPlayers" and "notifyPlayer" calls in
                  your emptygame.game.php file.

        */

        setupNotifications: function() {
            dojo.subscribe('newRound', this, "notif_newRound");
            dojo.subscribe('newHand', this, "notif_newHand");
            dojo.subscribe('newHandState', this, "notif_newHandState");
            dojo.subscribe('playCard', this, "notif_playCard");
            this.notifqueue.setSynchronous('playCard', (this.playCardDuration));
            dojo.subscribe('currentPlayer', this, "notif_currentPlayer");
            dojo.subscribe('trickWin', this, "notif_trickWin");
            this.notifqueue.setSynchronous('trickWin', (this.trickWinDelay + this.winnerTakeDuration + this.fadeOutDuration));
            dojo.subscribe('points', this, "notif_points");
            dojo.subscribe('newScores', this, "notif_newScores");
            dojo.subscribe('bidCards', this, "notif_bidCards");
            dojo.subscribe('biddingCompleteState' , this, "notif_biddingCompleteState");
        },

        // From this point and below, you can write your game notifications handling methods

        // A new round has started
        notif_newRound: function(notif) {
            this.showDealer(notif.args.dealer);
            this.showActivePlayer(notif.args.firstPlayer);
            if (notif.args.round_num) {
                // if this game uses rounds
                this.setNodeInvisible("round_name_container", false);
                this.updateRoundNum(notif.args.round_num);
            } else if (notif.args.hand_num) {
                // if this game uses hands
                this.setNodeInvisible("round_name_container", false);
                this.updateRoundNum(notif.args.hand_num);
            }
            this.showTrump(null);
            this.clearRoundScores();
        },

        // We received a new full hand of 12 cards.
        // This message is sent to only you
        notif_newHand: function(notif) {
            if (this.isReadOnly()) {
                // Dismiss any score dialogs
                dojo.byId("close_btn").click();
            }

            // Just to be sure, clean up any old state
            this.playerHand.removeAll();
            this.playerBid.removeAll();
            this.animateBidVisibility(false);
            this.clearTricksWon();
            this.updateSelfBid();
            this.setNodeHidden("my_bid_container", true);

            for (var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
            this.adjustCardOverlapToAvailableSpace();

            if (this.autoplay) {
                var that = this;
                setTimeout(function() {
                    var cards = that.playerHand.getAllItems();
                    that.playerHand.selectItem(cards[0].id);
                    that.playerHand.selectItem(cards[1].id);
                    that.playerHand.selectItem(cards[2].id);
                    that.onNoDeclare();
                }, 500);
            }
        },

        // A new hand is starting
        // This message is sent to every player
        notif_newHandState: function(notif) {
            this.showDealer(notif.args.dealer);
            this.showActivePlayer(notif.args.firstPlayer);
            this.showTrump(notif.args.trump);

            if (!notif.args.usesRounds) {
                this.updateRoundNum(notif.args.hand_num);
            }
        },

        // A card was played
        // This is sent to all players
        notif_playCard: function(notif) {
            this.showActivePlayer(notif.args.currentPlayer);
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.suit,
                                 notif.args.rank, notif.args.card_id);
            this.unmarkUnplayableCards();
        },

        // The current player has shifted
        // This is sent to all players
        notif_currentPlayer: function(notif) {
            this.showActivePlayer(notif.args.currentPlayer);
            this.unmarkUnplayableCards();
        },

        // A trick was won
        // This information is sent to all players
        notif_trickWin: function(notif) {
            var winner_id = notif.args.player_id;
            var playerTrickCounts = notif.args.playerTrickCounts;
            var decRevPlayerId = notif.args.decRevPlayerId;
            if (this.isReadOnly()) {
                this.giveAllCardsToPlayer(winner_id, playerTrickCounts, decRevPlayerId);
            } else {
                // The timeout allows players to view the cards that are played before they're gone.
                var that = this;
                setTimeout(function() {
                    that.giveAllCardsToPlayer(winner_id, playerTrickCounts, decRevPlayerId);
                }, this.trickWinDelay);
            }
        },

        // Points were awarded for the hand
        // This will be called once for each player, and the information
        // broadcast to all players
        notif_points: function(notif) {
            var playerId = notif.args.player_id;
            var score = notif.args.roundScore;
            if (score) {
                this.updateRoundScore(playerId, score);
            }
        },

        // All players scores were updated
        // This is sent to all players
        notif_newScores: function(notif) {
            // Update players' scores
            this.updateRoundScores(notif.args.newScores);
            this.updateGameScores(notif.args.gameScores);
        },

        // This notifies the player to remove the cards in their bid
        // from their hand.
        notif_bidCards: function(notif) {
            var bidCards = notif.args.cards.map(function(cardId) {
                return parseInt(cardId);
            });
            this.updateBidState(this.getCardsFromStockById(this.playerHand, bidCards));
        },

        // Bidding is complete. Update the active declare/reveal information
        notif_biddingCompleteState: function(notif) {
            this.showActiveDeclareOrReveal(notif.args.declareReveal);
            this.informUsersPlayerDeclaredOrRevealed(notif.args.declareReveal);
        }
   });
});


