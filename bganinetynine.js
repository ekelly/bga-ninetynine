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
                dojo.connect( stock, 'onChangeSelection', this, selectionChangeFunctionName );    
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

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        
        onEnteringState: function(stateName, args) {
           console.log('Entering state: '+stateName);
            
            switch(stateName) {
                case 'playerTurn':
                    this.addTooltip( 'myhand', _('Cards in my hand'), _('Play a card') );
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
                    break;
            }         
        }, 
        
        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //                
        onUpdateActionButtons: function(stateName, args) {
            console.log('onUpdateActionButtons: ' + stateName);
                      
            if( this.isCurrentPlayerActive() ) {
                console.log("Current player is active");
                switch (stateName) {
                    case 'bidding':
                        this.addActionButton( 'bidCards_button', _('Bid selected cards'), 'onBidCards' ); 
                        break;
                    case 'declareOrReveal':
                        this.addActionButton( 'reveal_button', _('Reveal'), 'onReveal' ); 
                        this.addActionButton( 'declare_button', _('Declare'), 'onDeclare' ); 
                        this.addActionButton( 'none_button', _('Neither'), 'onNoDeclare' );
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
        
        getCardSuit: function (suit) {
            return ["club", "diamond", "spade", "heart"][suit];
        },
        
        playCardOnTable: function(player_id, suit, value, card_id) {
            console.log('playCardOnTable');
            // player_id => direction
            dojo.place(
                this.format_block('jstpl_cardontable', {
                    card_id: card_id,
                    suit: this.getCardSuit(suit),
                    rank: value,
                    player_id: player_id                
                }), 'playertablecard_'+player_id);
                
            if (player_id != this.player_id) {
                // Some opponent played a card
                // Move card from player panel
                this.placeOnObject('cardontable_'+player_id, 'overall_player_board_'+player_id);
            } else {
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item
                if ($('myhand_item_'+card_id)) {
                    this.placeOnObject('cardontable_'+player_id, 'myhand_item_'+card_id);
                    this.playerHand.removeFromStockById( card_id );
                }
            }

            // In any case: move it to its final destination
            this.slideToObject('cardontable_'+player_id, 'playertablecard_'+player_id).play();
        },

        showActiveDeclareOrReveal: function (decRevInfo) {
            var playerNameSpan = dojo.byId("decrev_player_name");
            if (decRevInfo.playerId != 0) {
                playerNameSpan.textContent = decRevInfo.playerName;
                var playerColor = decRevInfo.playerColor;
                domStyle.set(playerNameSpan, "color", "#" + playerColor);

                // Show Revealed cards
                this.revealedHand.removeAll();
                this.addCardsToStock(this.revealedHand, decRevInfo.cards);
                // Show Declared bid
                this.declaredBid.removeAll();
                this.addCardsToStock(this.declaredBid, decRevInfo.bid);

            } else {
                playerNameSpan.textContent = "None";
                domStyle.set(playerNameSpan, "color", "#000000");
            }
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
            this.submitDeclareOrReveal(1);
        },

        onReveal: function() {
            console.log('onReveal');
            this.submitDeclareOrReveal(2);
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
            
            dojo.subscribe('newHand', this, "notif_newHand");
            dojo.subscribe('playCard', this, "notif_playCard");
            dojo.subscribe('trickWin', this, "notif_trickWin");
            this.notifqueue.setSynchronous('trickWin', 1000);
            dojo.subscribe('giveAllCardsToPlayer', this, "notif_giveAllCardsToPlayer");
            dojo.subscribe('newScores', this, "notif_newScores");
            dojo.subscribe('bidCards', this, "notif_bidCards");
            dojo.subscribe('biddingComplete' , this, "notif_biddingComplete");
        },
        
        // TODO: from this point and below, you can write your game notifications handling methods
                
        notif_newHand: function(notif) {
            console.log('notif_newHand');
            // We received a new full hand of 13 cards.
            this.playerHand.removeAll();

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
            this.playCardOnTable(notif.args.player_id, notif.args.suit, 
                                 notif.args.rank, notif.args.card_id);
        },
        
        notif_trickWin: function(notif) {
            console.log('notif_trickWin');
            // We do nothing here (just wait in order players can view the 4 cards played before they're gone.
        },
        
        notif_giveAllCardsToPlayer: function(notif) {
            console.log('notif_giveAllCardsToPlayer');
            // Move all cards on table to given table, then destroy them
            var winner_id = notif.args.player_id;
            for (var player_id in this.gamedatas.players) {
                var anim = this.slideToObject('cardontable_'+player_id, 'overall_player_board_'+winner_id);
                dojo.connect(anim, 'onEnd', function(node) { dojo.destroy(node); });
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


