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

            // Setting up player boards
            if (this.gamedatas.usesRounds) {
                for (var player_id in gamedatas.players) {
                    var player = gamedatas.players[player_id];

                    // Setting up players boards if needed
                    var player_score_div = $('player_board_'+player_id);
                    dojo.place(this.format_block('jstpl_player_round_score', player), player_score_div);
                }
                this.addTooltipToClass("bgann_round_score", _("Round Score"), '');
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

            // First player
            this.showFirstPlayer(this.gamedatas.firstPlayer);

            // Current trump
            this.showTrump(this.gamedatas.trump);

            // Set trick counts
            this.updateTrickCounts(this.gamedatas.trickCounts,
                                   this.gamedatas.declareReveal.playerId);

            // Set scores
            this.updateRoundScores(this.gamedatas.roundScores);
            this.updateGameScores(this.gamedatas.gameScores);

            this.addTooltipToClass("bgann_playertablecard", _("Card played on the table"), '');
            this.addTooltip("declaretable", _("Opponent's declared bid"), '');
            this.addTooltip("revealtable", _("Opponent's revealed hand"), '');
            this.addTooltipToClass("player_score", _("Game Score"), '');

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            this.ensureSpecificImageLoading(['../common/point.png']);
        },

        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //

        onEnteringState: function(stateName, args) {
            switch (stateName) {
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
                    this.addTooltip('mybid', _('Cards in my bid'), _('Remove from bid'));
                    this.playerBid.setSelectionMode(1);
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function(stateName) {
            switch (stateName) {
                case 'bidding':
                    this.playerBid.setSelectionMode(0);
                    this.displayTricksWon();
                    this.addTooltip('mybid', _('Cards in my bid'), '');
                    break;
            }
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function(stateName, args) {
            if (this.isCurrentPlayerActive()) {
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
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /*

            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.

        */

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
                for (var rank = 2; rank <= 14; rank++) {
                    // Build card type id
                    var card_type_id = this.getCardUniqueId(color, rank);
                    stock.addItemType(card_type_id, card_type_id, g_gamethemeurl+'img/cards.jpg', card_type_id);
                }
            }
            return stock;
        },

        showDealer: function(dealer_id) {
            dojo.query(".bgann_dealerindicator").addClass("bgann_hidden");
            this.setNodeHidden("dealerindicator_" + dealer_id, false);
        },

        showFirstPlayer: function(first_player) {
            dojo.query(".bgann_playertable").removeClass("bgann_firstplayer");
            dojo.addClass("playertable_" + first_player, "bgann_firstplayer");
        },

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
            } else {
                trumpSuitSpan.textContent = "none";
                dojo.query("#trumpSuit").removeClass("bgann_trump_red");
                dojo.query("#trumpSuit").removeClass("bgann_trump_black");
                dojo.addClass(trumpSuitSpan, "bgann_trump_none")
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

        updateTrickCounts: function(trickCounts, declaringPlayerId) {
            for (var playerId in trickCounts) {
                this.updateCurrentTricksWon(playerId,
                                            trickCounts[playerId],
                                            trickCounts[declaringPlayerId]);
            }
        },

        clearDeclareTrickCount: function() {
            this.updateValueInNode("declaredTricksWon", 0);
        },

        updateCurrentTricksWon: function(playerId, tricksWon, declaringPlayerTricks) {
            if (playerId == this.player_id) {
                this.updateValueInNode("myTricksWon", tricksWon);
            }
            this.updateValueInNode("declaredTricksWon", declaringPlayerTricks);
            this.updateValueInNode("tricks_" + playerId, tricksWon);
        },

        updateValueInNode: function(nodeId, value) {
            var node = dojo.byId(nodeId);
            if (node != null) {
                node.textContent = value;
            }
        },

        displayTricksWon: function() {
            dojo.query(".bgann_tricks").removeClass("bgann_hidden");
        },

        clearTricksWon: function() {
            dojo.query(".bgann_tricks").addClass("bgann_hidden");
            dojo.byId("myTricksWon").textContent = 0;
            for (var playerId in this.gamedatas.players) {
                this.updateValueInNode("tricks_" + playerId, 0);
            }
            this.clearDeclareTrickCount();
        },

        updateGameScores: function(gameScores) {
            for (var playerId in gameScores) {
                this.updatePlayerScore(parseInt(playerId), parseInt(gameScores[parseInt(playerId)]));
            }
        },

        clearRoundScores: function() {
            for (var playerId in this.gamedatas.players) {
                this.updateRoundScore(parseInt(playerId), 0);
            }
        },

        updateRoundScores: function(roundScores) {
            for (var playerId in roundScores) {
                this.updateRoundScore(parseInt(playerId), parseInt(roundScores[parseInt(playerId)]));
            }
        },

        updateRoundScore: function(playerId, playerRoundScore) {
            var roundScoreSpan = dojo.byId("player_round_score_" + playerId);
            if (roundScoreSpan) {
                roundScoreSpan.textContent = playerRoundScore;
            }
        },

        updatePlayerScore: function(playerId, playerScore) {
            if (this.scoreCtrl[playerId]) {
                this.scoreCtrl[playerId].toValue(playerScore);
            }
        },

        // Get card unique identifier based on its color and value
        getCardUniqueId: function(color, value) {
            return parseInt(color) * 13 + (parseInt(value) - 2);
        },

        getCardSuit: function(suit) {
            return ["club", "diamond", "spade", "heart"][suit];
        },

        playCardOnTable: function(player_id, suit, value, card_id) {

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

        setNodeHidden: function(nodeId, hidden) {
            if (hidden) {
                dojo.addClass(nodeId, "bgann_hidden");
            } else {
                dojo.removeClass(nodeId, "bgann_hidden");
            }
        },

        informUsersPlayerDeclaredOrRevealed: function(decRevInfo) {
            if (!decRevInfo.playerId) {
                return;
            }
            var reveal = Object.keys(decRevInfo.cards).length > 0;
            var message;
            if (decRevInfo.playerId != this.player_id) {
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

        showActiveDeclareOrReveal: function(decRevInfo) {
            var playerNameSpan = dojo.byId("decrev_player_name");
            if (decRevInfo.playerId) {
                if (decRevInfo.playerId != this.player_id) {
                    dojo.query(".bgann_declare").removeClass("bgann_hidden");
                    if (Object.keys(decRevInfo.cards).length > 0) {
                        dojo.query(".bgann_reveal").removeClass("bgann_hidden");
                    }
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
                    if (Object.keys(decRevInfo.cards).length > 0) {
                        this.setNodeHidden("reveal_label", false);
                    }
                }
                playerNameSpan.textContent = decRevInfo.playerName;
                var playerColor = decRevInfo.playerColor;
                domStyle.set(playerNameSpan, "color", "#" + playerColor);
            } else {
                playerNameSpan.textContent = "None";
                domStyle.set(playerNameSpan, "color", "#000000");
                dojo.query(".bgann_declare").addClass("bgann_hidden");
                dojo.query(".bgann_reveal").addClass("bgann_hidden");
                // Hide Declare and reveal if there isn't a declaring or revealing player
                this.setNodeHidden("declare_label", true);
                this.setNodeHidden("reveal_label", true);
            }
            this.updateCurrentBidFromBidStock(this.declaredBid, "declaredBidValue");
        },

        clearActiveDeclareOrReveal: function() {
            this.showActiveDeclareOrReveal({});
            this.declaredBid.removeAll();
            this.revealedHand.removeAll();
            this.updateCurrentBidFromBidStock(this.declaredBid, "declaredBidValue");
            this.setNodeHidden("declare_label", true);
            this.setNodeHidden("reveal_label", true);
            dojo.query(".bgann_declare").addClass("bgann_hidden");
            dojo.query(".bgann_reveal").addClass("bgann_hidden");
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
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                if (this.checkAction('playCard', true)) {
                    // Can play a card
                    var card_id = items[0].id;
                    this.ajaxcall("/ninetynine/ninetynine/playCard.html", {
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
            if (!this.checkAction('submitBid')) {
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
                this.ajaxcall( "/ninetynine/ninetynine/submitBid.html", {
                    cards: to_give,
                    lock: true
                }, this, function (result) {
                }, function(is_error) {
                });
            }
        },

        onNoDeclare: function() {
            this.submitDeclareOrReveal(0);
        },

        onDeclare: function() {
            this.confirmationDialog(_('Are you sure you want to declare your bid?'),
                                    dojo.hitch(this, function() {
                this.submitDeclareOrReveal(1);
            }));
        },

        onReveal: function() {
            this.confirmationDialog(_('Are you sure you want to reveal your hand?'),
                                    dojo.hitch(this, function() {
                this.submitDeclareOrReveal(2);
            }));
        },

        // decrev should be 0 = none, 1 = declare, 2 = reveal
        submitDeclareOrReveal: function(decrev) {
            if (this.checkAction('submitDeclareOrReveal')) {
                this.ajaxcall( "/ninetynine/ninetynine/submitDeclareOrReveal.html", {
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

        // From this point and below, you can write your game notifications handling methods

        notif_newRound: function(notif) {
            this.showDealer(notif.args.dealer);
            this.showFirstPlayer(notif.args.firstPlayer);
            this.showTrump(null);
            this.clearRoundScores();
        },

        notif_newHand: function(notif) {
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
            this.showFirstPlayer(notif.args.firstPlayer);
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.suit,
                                 notif.args.rank, notif.args.card_id);
        },

        notif_trickWin: function(notif) {
            // We do nothing here (just wait in order players can view the cards played before they're gone.)
        },

        notif_points: function(notif) {
            var playerId = notif.args.player_id;
            var score = notif.args.roundScore;
            if (score) {
                this.updateRoundScore(playerId, score);
            }
        },

        notif_giveAllCardsToPlayer: function(notif) {
            // Move all cards on table to given table, then destroy them
            var winner_id = notif.args.playerId;
            this.showFirstPlayer(winner_id);
            this.updateTrickCounts(notif.args.playerTrickCounts, notif.args.decRevPlayerId);

            for (var player_id in this.gamedatas.players) {
                // There's a race condition between cards leaving the table and cards
                // being placed on the table. In order to avoid that, we clone the original
                // card and replace it with one that has a different id.
                var node = dojo.byId('cardontable_'+player_id);
                var newnode = lang.clone(node);
                attr.set(newnode, "id", 'cardfromtable_'+player_id);
                dojo.place(newnode, 'playertablecard_'+player_id);
                dojo.destroy(node);

                var anim = this.slideToObject('cardfromtable_'+player_id, 'overall_player_board_'+winner_id);
                dojo.connect(anim, 'onEnd', function(node) { dojo.destroy(node);});
                anim.play();
            }
        },

        notif_newScores: function(notif) {
            // Update players' scores
            this.updateRoundScores(notif.args.newScores);
            this.updateGameScores(notif.args.gameScores);
        },

        notif_bidCards: function(notif) {
            // Remove cards from the hand (they have been given)
            for (var i in notif.args.cards) {
                var card_id = notif.args.cards[i];
                this.playerHand.removeFromStockById(card_id);
            }
        },

        notif_biddingComplete: function(notif) {
            this.showActiveDeclareOrReveal(notif.args.declareReveal);
            this.informUsersPlayerDeclaredOrRevealed(notif.args.declareReveal);
        }
   });
});


