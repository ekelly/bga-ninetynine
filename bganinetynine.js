/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BgaNinetyNine implementation : © Gregory Isabelli <gisabelli@boardgamearena.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * bganinetynine.js
 *
 * BgaNinetyNine user interface script
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
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare, domStyle) {
    return declare("bgagame.bganinetynine", ebg.core.gamegui, {

        constructor: function() {
            console.log('bganinetynine constructor');

            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;

            this.playerHand = null;
            //this.playerBid = null;
            this.cardwidth = 72;
            this.cardheight = 96;
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
            console.log("start creating player boards");
            for (var player_id in gamedatas.players) {
                var player = gamedatas.players[player_id];
            }

            // Player hand
            this.playerHand = this.setupCardStocks('myhand', 'onPlayerHandSelectionChanged');

            // Player bid
            this.playerBid = this.setupCardStocks('mybid', 'onBidSelectionChanged');
            // The selection mode should start as 0 and only become
            // selectable during bidding
            this.playerBid.setSelectionMode(0);

            // Declared Bid
            this.declaredBid = this.setupCardStocks('declaredBid');

            // Revealed Hand
            this.revealedHand = this.setupCardStocks('revealedHand');
            this.revealedHand.setOverlap(20, 0);

            console.log("Initial hand: " + this.gamedatas.hand);
            console.log(this.gamedatas.hand);

            // Cards in player's hand
            this.addCardsToStock(this.playerHand, this.gamedatas.hand);

            // Cards played on table
            for (i in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[i];
                var color = card.type;
                var value = card.type_arg;
                var player_id = card.location_arg;
                this.playCardOnTable(player_id, color, value, card.id);
            }

            // Cards in the player's bid
            this.addCardsToStock(this.playerBid, this.gamedatas.bid.cards);
            this.updateCurrentBidFromBidStock(this.playerBid, "bidValue");
            this.showActiveDeclareOrReveal(this.gamedatas.declareReveal);

            // Current dealer
            this.showDealer(this.gamedatas.dealer);
            console.log("Current dealer: " + this.gamedatas.dealer);

            // First player
            this.showFirstPlayer(this.gamedatas.firstPlayer);
            console.log("Current player: " + this.gamedatas.firstPlayer);

            // Current trump
            this.showTrump(this.gamedatas.trump);
            console.log("Current trump: " + this.gamedatas.trump);

            // Set trick counts
            this.updateTrickCounts(this.gamedatas.trickCounts);

            // Set scores
            this.updateRoundScores(this.gamedatas.roundScores);

            this.addTooltipToClass("playertablecard", _("Card played on the table"), '');

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log('Done setting up notifications');

            this.ensureSpecificImageLoading(['../common/point.png']);
        },

        addCardsToStock: function(stock, cards) {
            for (var i in cards) {
                var card = cards[i];
                var color = card.type;
                var rank = card.type_arg;
                // card.id is the SERVER's id of the card
                // getCardUniqueId is the ID composed of the rank and suit
                stock.addToStockWithId(this.getCardUniqueId(color, rank), card.id);
            }
        },

        setupCardStocks: function(id, selectionChangeFunctionName) {
            var stock = new ebg.stock();
            stock.create(this, $(id), this.cardwidth, this.cardheight);
            stock.image_items_per_row = 13;
            if (selectionChangeFunctionName != undefined && selectionChangeFunctionName.length > 0) {
                stock.setSelectionMode(1);
                stock.setSelectionAppearance('disappear');
                dojo.connect(stock, 'onChangeSelection', this, selectionChangeFunctionName);
            } else {
                stock.setSelectionMode(0);
            }
            // Order of id: ["club", "diamond", "spade", "heart"];
            for (var color = 0; color < 4; color++) {
                for (var rank=2; rank <= 14; rank++) {
                    // Build card type id
                    var card_type_id = this.getCardUniqueId(color, rank);
                    stock.addItemType(card_type_id, card_type_id, g_gamethemeurl+'img/cards.jpg', card_type_id);
                }
            }
            return stock;
        },

        showDealer: function(dealer_id) {
            console.log("Showing dealer: " + dealer_id);
            dojo.query(".dealerindicator")
                .style("display", "none");
            domStyle.set("dealerindicator_" + dealer_id,
                        "display", "inline-block");
        },

        showFirstPlayer: function(first_player) {
            console.log("First player: " + first_player);
            dojo.query(".playertable").removeClass("firstplayer");
            dojo.addClass("playertable_" + first_player, "firstplayer");
        },

        showTrump: function(trumpSuit) {
            console.log("Showing trump: " + trumpSuit);
            var trumpSuitSpan = dojo.byId("trumpSuit");
            if (trumpSuit != undefined &&
                trumpSuit != null &&
                trumpSuit >= 0 &&
                trumpSuit < 4) {

                var redSuit = trumpSuit % 2 == 1;
                trumpSuitSpan.textContent = ["♣", "♦", "♠", "♥"][trumpSuit];
                domStyle.set(trumpSuitSpan, "color", redSuit ? "red" : "black");
            } else {
                trumpSuitSpan.textContent = "none";
                domStyle.set(trumpSuitSpan, "color", "black");
            }
        },

        getCardSuitFromId: function(card_id) {
            return ["club", "diamond", "spade", "heart"][Math.floor(card_id / 13)];
        },

        getCardRankFromId: function(card_id) {
            return (card_id % 13) + 2;
        },

        getBidValueFromSuit: function(suit) {
            return {club: 3, diamond: 0, spade: 1, heart: 2}[suit];
        },

        //getBidValueFromId: function(card_id) {
        //    return this.getBidValueFromSuit(this.getCardSuitFromId(card_id));
        //},

        updateCurrentBidFromBidStock: function(bidStock, divId) {
            var bid = 0;
            var cardList = bidStock.getAllItems();
            for (var x = 0; x < cardList.length; x++) {
                var card = cardList[x];
                var id = card.type;
                var suit = this.getCardSuitFromId(id);
                var bidValue = this.getBidValueFromSuit(suit);
                bid += bidValue;
            }
            var bidValueSpan = dojo.byId(divId);
            bidValueSpan.textContent = bid;
        },

        updateTrickCounts: function(trickCounts) {
            for (var playerId in trickCounts) {
                this.updateCurrentTricksWon(playerId, trickCounts[playerId]);
            }
        },

        updateCurrentTricksWon: function(playerId, tricksWon) {
            console.log("Updating tricks won: " + playerId + " - " + tricksWon);
            if (playerId == this.player_id) {
                this.updateValueInNode("myTricksWon", tricksWon);
            }
            this.updateValueInNode("tricks_" + playerId, tricksWon);
        },

        updateValueInNode: function(nodeId, value) {
            var node = dojo.byId(nodeId);
            if (node != null) {
                node.textContent = value;
            }
        },

        clearTricksWon: function() {
            console.log("hiding trick count");
            dojo.query(".tricks").style("display", "none");
            dojo.byId("myTricksWon").textContent = 0;
            for (var playerId in this.gamedatas.players) {
                this.updateValueInNode("tricks_" + playerId, 0);
            }
        },

        displayTricksWon: function() {
            console.log("showing trick count");
            dojo.query(".tricks").style("display", "inline-block");
        },

        updateRoundScores: function(roundScores) {
            for (var playerId in roundScores) {
                this.updatePlayerScore(parseInt(playerId), parseInt(roundScores[parseInt(playerId)]));
            }
        },

        updatePlayerScore: function(playerId, playerScore) {
            console.log("Player " + playerId + " current round score is " + playerScore);
            // this.scoreCtrl[playerId].setValue(playerScore);
        },

        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //

        onEnteringState: function(stateName, args) {
           console.log('Entering state: '+stateName);

            switch(stateName) {
                case 'newHand':
                    this.updateCurrentBidFromBidStock(this.playerBid, "bidValue");
                    this.clearTricksWon();
                    this.clearActiveDeclareOrReveal();
                    break;

                case 'playerTurn':
                    this.addTooltip('myhand', _('Cards in my hand'), _('Play a card'));
                    this.displayTricksWon();
                    if (this.getActivePlayerId() != null) {
                        this.showFirstPlayer(this.getActivePlayerId());
                    }
                    break;

                case 'bidding':
                    this.addTooltip( 'myhand', _('Cards in my hand'), _('Select a card') );
                    console.log('Added bidding tooltip');
                    this.playerBid.setSelectionMode(1);
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function(stateName) {
            console.log('Leaving state: '+stateName);

            switch (stateName) {
                case 'bidding':
                    this.playerBid.setSelectionMode(0);
                    this.displayTricksWon();
                    break;
            }
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function(stateName, args) {
            console.log('onUpdateActionButtons: ' + stateName);

            if (this.isCurrentPlayerActive()) {
                console.log("Current player is active");
                switch (stateName) {
                    case 'bidding':
                        this.addActionButton('bidCards_button', _('Bid selected cards'), 'onBidCards');
                        break;
                    case 'declareOrReveal':
                        this.addActionButton('reveal_button', _('Reveal'), 'onReveal'); 
                        this.addActionButton('declare_button', _('Declare'), 'onDeclare'); 
                        this.addActionButton('none_button', _('Neither'), 'onNoDeclare');
                        break;
                }
            } else {
                console.log("Current player is not active");
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /*

            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.

        */

        // Get card unique identifier based on its color and value
        getCardUniqueId: function(color, value) {
            return parseInt(color)*13+(parseInt(value)-2);
        },

        getCardSuit: function(suit) {
            return ["club", "diamond", "spade", "heart"][suit];
        },

        stockContains: function(stock, el) {
            var stockContents = stock.getAllItems();
            for (var i in stockContents) {
                var card = stockContents[i];
                if (card.type == el) {
                    return true;
                }
            }
            return false;
        },

        stockContainsId: function(stock, id) {
            var stockContents = stock.getAllItems();
            for (var i in stockContents) {
                var card = stockContents[i];
                if (card.id == id) {
                    return true;
                }
            }
            return false;
        },

        playCardOnTable: function(player_id, suit, value, card_id) {
            console.log('playCardOnTable');

            dojo.place(
                this.format_block('jstpl_cardontable', {
                    card_id: card_id,
                    suit: this.getCardSuit(suit),
                    rank: value,
                    player_id: player_id
                }), 'playertablecard_'+player_id);

            if (player_id != this.player_id) {
                // Some opponent played a card
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
        },

        showActiveDeclareOrReveal: function(decRevInfo) {
            var playerNameSpan = dojo.byId("decrev_player_name");
            if (decRevInfo.playerId) {
                dojo.query(".declaringplayer").style("display", "block");
                if (decRevInfo.playerId != this.player_id) {
                    dojo.query(".declare").style("display", "block");
                    if (Object.keys(decRevInfo.cards).length > 0) {
                        dojo.query(".reveal").style("display", "block");
                    }
                    // Show Revealed cards
                    this.revealedHand.removeAll();
                    this.addCardsToStock(this.revealedHand, decRevInfo.cards);
                    // Show Declared bid
                    this.declaredBid.removeAll();
                    this.addCardsToStock(this.declaredBid, decRevInfo.bid);
                }
                playerNameSpan.textContent = decRevInfo.playerName;
                var playerColor = decRevInfo.playerColor;
                domStyle.set(playerNameSpan, "color", "#" + playerColor);
            } else {
                playerNameSpan.textContent = "None";
                domStyle.set(playerNameSpan, "color", "#000000");
                //dojo.query(".declare").style("display", "none");
                //dojo.query(".reveal").style("display", "none");
            }
            this.updateCurrentBidFromBidStock(this.declaredBid, "declaredBidValue");
        },

        clearActiveDeclareOrReveal: function() {
            this.showActiveDeclareOrReveal({});
            this.declaredBid.removeAll();
            this.revealedHand.removeAll();
            this.updateCurrentBidFromBidStock(this.declaredBid, "declaredBidValue");
            //dojo.query(".declare").style("display", "none");
            //dojo.query(".reveal").style("display", "none");
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

        onPlayerHandSelectionChanged: function() {
            console.log('onPlayerHandSelectionChanged');
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                if (this.checkAction('playCard', true)) {
                    // Can play a card
                    var card_id = items[0].id;
                    this.ajaxcall("/bganinetynine/bganinetynine/playCard.html", {
                        id: card_id,
                        lock: true
                    }, this, function(result) {}, function(is_error) {});

                    this.playerHand.unselectAll();
                } else if (this.checkAction('submitBid')) {

                    if (this.playerBid.getAllItems().length == 3) {
                        // Disallow adding more than three cards to the bid
                        this.playerHand.unselectAll();
                        return;
                    }

                    var divId = this.playerHand.getItemDivId(items[0].id);

                    // Remove that card from the bid and return it to the hand
                    this.playerBid.addToStockWithId(items[0].type, items[0].id, divId);
                    this.playerHand.removeFromStockById(items[0].id);

                    this.playerHand.unselectAll();

                    this.updateCurrentBidFromBidStock(this.playerBid, "bidValue");

                } else {
                    this.playerHand.unselectAll();
                }
            }
        },

        onBidSelectionChanged: function() {
            console.log('onBidSelectionChanged');
            if (!this.checkAction('submitBid')) {
                console.log("Cannot make changes to bid now");
                this.playerBid.unselectAll();
                return;
            }
            var items = this.playerBid.getSelectedItems();
            var divId = this.playerBid.getItemDivId(items[0].id);

            // Remove that card from the bid and return it to the hand
            this.playerHand.addToStockWithId(items[0].type, items[0].id, divId);
            this.playerBid.removeFromStockById(items[0].id);

            this.playerBid.unselectAll();

            this.updateCurrentBidFromBidStock(this.playerBid, "bidValue");
        },

        onBidCards: function() {
            console.log('onBidCards');
            if (this.checkAction('submitBid')) {
                var items = this.playerBid.getAllItems();

                if (items.length != 3) {
                    this.showMessage(_("You must select exactly 3 cards"), 'error');
                    return;
                }

                // Give these 3 cards
                var to_give = '';
                for (var i in items) {
                    to_give += items[i].id+';';
                }
                this.ajaxcall( "/bganinetynine/bganinetynine/submitBid.html", {
                    cards: to_give,
                    lock: true
                }, this, function (result) {
                }, function(is_error) {
                });
            }
        },

        onNoDeclare: function() {
            console.log('onNoDeclare');
            this.submitDeclareOrReveal(0);
        },

        onDeclare: function() {
            console.log('onDeclare');
            this.confirmationDialog(_('Are you sure you want to declare?'),
                                    dojo.hitch(this, function() {
                this.submitDeclareOrReveal(1);
            }));
        },

        onReveal: function() {
            console.log('onReveal');
            this.confirmationDialog(_('Are you sure you want to reveal?'),
                                    dojo.hitch(this, function() {
                this.submitDeclareOrReveal(2);
            }));
        },

        // decrev should be 0 = none, 1 = declare, 2 = reveal
        submitDeclareOrReveal: function(decrev) {
            if (this.checkAction('submitDeclareOrReveal')) {
                this.ajaxcall( "/bganinetynine/bganinetynine/submitDeclareOrReveal.html", {
                    declareOrReveal: decrev,
                    lock: true
                }, this, function(result) {
                }, function(is_error) {
                });
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
            console.log( 'notifications subscriptions setup');

            dojo.subscribe('newRound', this, "notif_newRound");
            dojo.subscribe('newHand', this, "notif_newHand");
            dojo.subscribe('playCard', this, "notif_playCard");
            dojo.subscribe('trickWin', this, "notif_trickWin");
            this.notifqueue.setSynchronous('trickWin', 1000);
            dojo.subscribe('points', this, "notif_points");
            dojo.subscribe('giveAllCardsToPlayer', this, "notif_giveAllCardsToPlayer");
            dojo.subscribe('newScores', this, "notif_newScores");
            dojo.subscribe('bidCards', this, "notif_bidCards");
            dojo.subscribe('biddingComplete' , this, "notif_biddingComplete");
        },

        // TODO: from this point and below, you can write your game notifications handling methods

        notif_newRound: function(notif) {
            console.log('notif_newRound');
            this.showDealer(notif.args.dealer);
            this.showFirstPlayer(notif.args.firstPlayer);
            this.showTrump(null);

            // Clear everyone's scores
            for (var player_id in this.gamedatas.players) {
                this.scoreCtrl[player_id].toValue(0);
            }
        },

        notif_newHand: function(notif) {
            console.log('notif_newHand');
            // We received a new full hand of 12 cards.
            this.playerHand.removeAll();
            this.playerBid.removeAll();
            this.updateCurrentBidFromBidStock(this.playerBid, "bidValue");
            this.showDealer(notif.args.dealer);
            this.showFirstPlayer(notif.args.firstPlayer);
            this.showTrump(notif.args.trump);

            for (var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },

        notif_playCard: function(notif) {
            console.log('notif_playCard');
            // Play a card on the table

            // If I've revealed, remove the card from the revealed card stock
            if (this.stockContainsId(this.playerHand, notif.args.card_id)) {
                if (this.stockContainsId(this.revealedHand, notif.args.card_id)) {
                    this.revealedHand.removeFromStockById(notif.args.card_id);
                }
            }

            this.showFirstPlayer(notif.args.firstPlayer);
            this.playCardOnTable(notif.args.player_id, notif.args.suit,
                                 notif.args.rank, notif.args.card_id);
        },

        notif_trickWin: function(notif) {
            console.log('notif_trickWin');
            // We do nothing here (just wait in order players can view the 4 cards played before they're gone.
        },

        notif_points: function(notif) {
            console.log('notif_points');
            var playerId = notif.args.player_id;
            var score = notif.args.roundScore;
            if (score) {
                this.updatePlayerScore(playerId, score);
            }
        },

        notif_giveAllCardsToPlayer: function(notif) {
            console.log('notif_giveAllCardsToPlayer');
            // Move all cards on table to given table, then destroy them
            var winner_id = notif.args.playerId;
            this.showFirstPlayer(winner_id);
            this.updateTrickCounts(notif.args.playerTrickCounts);
            for (var player_id in this.gamedatas.players) {
                var anim = this.slideToObject('cardontable_'+player_id, 'overall_player_board_'+winner_id);
                dojo.connect(anim, 'onEnd', function(node) { dojo.destroy(node);});
                anim.play();
            }
        },

        notif_newScores: function(notif) {
            console.log('notif_newScores');
            // Update players' scores
            for (var player_id in notif.args.newScores) {
                this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
            }
        },

        notif_bidCards: function(notif) {
            console.log("Bid value: " + notif.args.bidValue);
            // Remove cards from the hand (they have been given)
            for (var i in notif.args.cards) {
                var card_id = notif.args.cards[i];
                this.playerHand.removeFromStockById(card_id);
            }
        },

        notif_biddingComplete: function(notif) {
            console.log("Bidding complete");
            console.log(notif.args);
            // My cards
            for (var i in notif.args.cards) {
                var card_id = notif.args.cards[i];
            }
            // My bid
            console.log("My bid? " + parseInt(notif.args.bid.bid));
            for (var i in notif.args.bid.cards) {
                var card_id = notif.args.bid.cards[i];
            }
            console.log("Declare? " + notif.args.bid.declare);
            console.log("Reveal? " + notif.args.bid.reveal);

            this.showActiveDeclareOrReveal(notif.args.declareReveal);
        }
   });
});


