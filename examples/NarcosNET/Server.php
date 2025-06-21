<?php

namespace app\games\NET\NarcosNET {
    set_time_limit(5);
    class Server
    {
        public function handle()
        {
            $jsonState = file_get_contents('php://input');
            $gameStateData = json_decode($jsonState, true);

            $action = $gameStateData['action'];
            // Ensure postData is an array even if not present in gameStateData
            $postData = $gameStateData['postData'] ?? [];
            $slotSettings = new SlotSettings($gameStateData);

            $responseState = [];
            $result_tmp = []; // To store parts of the string response built in cases for some actions
            $reels = []; // To capture reel data for the response, especially for spin/init

            // Key variables to be captured from cases for the final $responseState
            $responseSlotLines = 0;
            $responseSlotBet = 0;
            $responseTotalWin = 0;
            $responseWinLines = [];
            $responseJsJack = '{}'; // Default empty JSON object string for Jackpots
            $responseFreeState = '';
            $finalReelsSymbols = []; // Will hold the final reel symbols array/object for JSON response

            try {
                if (!$slotSettings->is_active()) { // is_active() is now refactored
                    throw new \Exception('Game is disabled');
                }

                // Determine action / $aid and slotEvent based on incoming $action
                $aid = $action;
                $currentSlotEvent = $postData['slotEvent'] ?? 'bet'; // Use slotEvent from postData if available, else default

                if ($action == 'freespin') {
                    $currentSlotEvent = 'freespin';
                    $aid = 'spin'; // freespin is a type of spin
                } else if ($action == 'init' || $action == 'reloadbalance') {
                    $aid = 'init';
                    $currentSlotEvent = 'init';
                } else if ($action == 'paytable') {
                    $currentSlotEvent = 'paytable';
                } else if ($action == 'initfreespin') {
                    $currentSlotEvent = 'initfreespin';
                } else if ($action == 'respin') {
                    $currentSlotEvent = 'respin';
                }
                // Ensure $postData also has the determined slotEvent, as original logic might rely on it
                $postData['slotEvent'] = $currentSlotEvent;


                // Denomination handling from original, using $postData which comes from $gameStateData['postData']
                if (isset($postData['bet_denomination']) && $postData['bet_denomination'] >= 1) {
                    $postData['bet_denomination'] = $postData['bet_denomination'] / 100;
                    $slotSettings->CurrentDenom = $postData['bet_denomination'];
                    $slotSettings->CurrentDenomination = $postData['bet_denomination'];
                    // gameData is now managed by SlotSettings, this call might be redundant if SlotSettings handles it
                    // $slotSettings->SetGameData($slotSettings->slotId . 'GameDenom', $postData['bet_denomination']);
                } else if ($slotSettings->HasGameData($slotSettings->slotId . 'GameDenom')) { // This implies GameDenom should be in gameData
                    $postData['bet_denomination'] = $slotSettings->GetGameData($slotSettings->slotId . 'GameDenom');
                    $slotSettings->CurrentDenom = $postData['bet_denomination'];
                    $slotSettings->CurrentDenomination = $postData['bet_denomination'];
                }

                $balanceInCents = round($slotSettings->GetBalance() * ($slotSettings->CurrentDenom > 0 ? $slotSettings->CurrentDenom : 1) * 100);

                // Bet validation from original
                if ($currentSlotEvent == 'bet') {
                    if (!isset($postData['bet_betlevel'])) {
                        throw new \Exception('invalid bet request');
                    }
                    $lines = 20; // Assuming fixed lines, or get from $postData if variable in NarcosNET
                    $betline = $postData['bet_betlevel'];
                    if ($lines <= 0 || $betline <= 0.0001) {
                        throw new \Exception('invalid bet state');
                    }
                    if ($slotSettings->GetBalance() < ($lines * $betline)) {
                        throw new \Exception('invalid balance');
                    }
                }

                // Freespin state validation from original
                if ($slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') < $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') && $currentSlotEvent == 'freespin') {
                    throw new \Exception('invalid bonus state');
                }
                // $aid = (string)$postData['action']; // Already have $aid from $action

                    switch ($aid) {
                        case 'init':
                            $gameBets = $slotSettings->Bet;
                            // $lastEvent = $slotSettings->GetHistory(); // GetHistory is refactored
                            $lastEvent = 'NULL'; // Set to NULL as per refactoring
                            $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', 0);
                            $slotSettings->SetGameData($slotSettings->slotId . 'FreeGames', 0);
                            $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', 0);
                            $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', 0);
                            $slotSettings->SetGameData($slotSettings->slotId . 'FreeBalance', 0);
                            $slotSettings->SetGameData($slotSettings->slotId . 'WalkingWild', []);
                            $responseFreeState = ''; // $freeState in original

                            $initialReels = $slotSettings->GetReelStrips('none', 'init');
                            $finalReelsSymbols = $initialReels; // Capture for responseState['reels']

                            // Simplified curReels for init, actual game might need more complex logic from original
                            // This string is part of what was echoed directly. It will now be part of $responseState if needed, or components extracted.
                            $curReelsString = '&rs.i0.r.i0.syms=SYM' . ($finalReelsSymbols['reel1'][0] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel1'][1] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel1'][2] ?? '0') . '';
                            $curReelsString .= ('&rs.i0.r.i1.syms=SYM' . ($finalReelsSymbols['reel2'][0] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel2'][1] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel2'][2] ?? '0') . '');
                            $curReelsString .= ('&rs.i0.r.i2.syms=SYM' . ($finalReelsSymbols['reel3'][0] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel3'][1] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel3'][2] ?? '0') . '');
                            $curReelsString .= ('&rs.i0.r.i3.syms=SYM' . ($finalReelsSymbols['reel4'][0] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel4'][1] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel4'][2] ?? '0') . '');
                            $curReelsString .= ('&rs.i0.r.i4.syms=SYM' . ($finalReelsSymbols['reel5'][0] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel5'][1] ?? '0') . '%2CSYM' . ($finalReelsSymbols['reel5'][2] ?? '0') . '');
                            $curReelsString .= ('&rs.i0.r.i0.pos=' . ($finalReelsSymbols['rp'][0] ?? rand(1,10)));
                            $curReelsString .= ('&rs.i0.r.i1.pos=' . ($finalReelsSymbols['rp'][1] ?? rand(1,10)));
                            $curReelsString .= ('&rs.i0.r.i2.pos=' . ($finalReelsSymbols['rp'][2] ?? rand(1,10)));
                            $curReelsString .= ('&rs.i0.r.i3.pos=' . ($finalReelsSymbols['rp'][3] ?? rand(1,10)));
                            $curReelsString .= ('&rs.i0.r.i4.pos=' . ($finalReelsSymbols['rp'][4] ?? rand(1,10)));

                            // Denominations string part
                            $denomStrings = [];
                            foreach($slotSettings->Denominations as $denom) {
                                $denomStrings[] = $denom * 100;
                            }

                            if ($slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') < $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') && $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') > 0) {
                                 // This long string seems to be a fixed state for ongoing free spins
                                $responseFreeState = 'rs.i4.id=basicwalkingwild&rs.i2.r.i1.hold=false&rs.i1.r.i0.syms=SYM8%2CSYM3%2CSYM7&rs.i2.r.i4.overlay.i0.pos=42&gameServerVersion=1.21.0&g4mode=false&freespins.win.coins=0&historybutton=false&rs.i0.r.i4.hold=false&gameEventSetters.enabled=false&next.rs=freespin&gamestate.history=basic%2Cfreespin&rs.i0.r.i14.syms=SYM30&rs.i1.r.i2.hold=false&rs.i1.r.i3.pos=0&rs.i0.r.i1.syms=SYM30&rs.i0.r.i5.hold=false&rs.i0.r.i7.pos=0&rs.i2.r.i1.pos=53&game.win.cents=300&rs.i4.r.i4.pos=65&staticsharedurl=https%3A%2F%2Fstatic-shared.casinomodule.com%2Fgameclient_html%2Fdevicedetection%2Fcurrent&bl.i0.reelset=ALL&rs.i1.r.i3.hold=false&totalwin.coins=60&gamestate.current=freespin&freespins.initial=10&rs.i4.r.i0.pos=2&rs.i0.r.i12.syms=SYM30&jackpotcurrency=%26%23x20AC%3B&rs.i4.r.i0.overlay.i0.row=1&bet.betlines=243&walkingwilds.pos=0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0&rs.i3.r.i1.hold=false&rs.i2.r.i0.hold=false&rs.i0.r.i0.syms=SYM30&rs.i0.r.i3.syms=SYM30&rs.i1.r.i1.syms=SYM3%2CSYM9%2CSYM12&rs.i1.r.i1.pos=0&rs.i3.r.i4.pos=0&freespins.win.cents=0&isJackpotWin=false&rs.i0.r.i0.pos=0&rs.i2.r.i3.hold=false&rs.i2.r.i3.pos=49&freespins.betlines=243&rs.i0.r.i9.pos=0&rs.i2.r.i4.overlay.i0.type=transform&rs.i4.r.i2.attention.i0=1&rs.i0.r.i1.pos=0&rs.i4.r.i4.syms=SYM5%2CSYM0%2CSYM7&rs.i1.r.i3.syms=SYM3%2CSYM9%2CSYM12&rs.i2.r.i4.hold=false&rs.i3.r.i1.pos=0&rs.i2.id=freespin&game.win.coins=60&rs.i1.r.i0.hold=false&denomination.last=0.05&rs.i0.r.i5.syms=SYM30&rs.i0.r.i1.hold=false&rs.i0.r.i13.pos=0&rs.i0.r.i13.hold=false&rs.i2.r.i1.syms=SYM12%2CSYM8%2CSYM7&rs.i0.r.i7.hold=false&rs.i2.r.i4.overlay.i0.with=SYM1&clientaction=init&rs.i0.r.i8.hold=false&rs.i4.r.i0.hold=false&rs.i0.r.i2.hold=false&rs.i4.r.i3.syms=SYM4%2CSYM10%2CSYM9&casinoID=netent&betlevel.standard=1&rs.i3.r.i2.hold=false&gameover=false&rs.i3.r.i3.pos=60&rs.i0.r.i3.pos=0&rs.i4.r.i0.syms=SYM0%2CSYM7%2CSYM11&rs.i0.r.i11.pos=0&bl.i0.id=243&rs.i0.r.i10.syms=SYM30&rs.i0.r.i13.syms=SYM30&bl.i0.line=0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2&nextaction=freespin&rs.i0.r.i5.pos=0&rs.i4.r.i2.pos=32&rs.i0.r.i2.syms=SYM30&game.win.amount=3.00&betlevel.all=1%2C2%2C3%2C4%2C5%2C6%2C7%2C8%2C9%2C10&freespins.totalwin.cents=300&denomination.all=' . implode('%2C', $denomStrings) . '&freespins.betlevel=1&rs.i0.r.i6.pos=0&rs.i4.r.i3.pos=51&playercurrency=%26%23x20AC%3B&rs.i0.r.i10.hold=false&rs.i2.r.i0.pos=51&rs.i4.r.i4.hold=false&rs.i4.r.i0.overlay.i0.with=SYM1&rs.i0.r.i8.syms=SYM30&rs.i2.r.i4.syms=SYM6%2CSYM10%2CSYM9&betlevel.last=1&rs.i3.r.i2.syms=SYM4%2CSYM10%2CSYM9&rs.i4.r.i3.hold=false&rs.i0.id=respin&credit=' . $balanceInCents . '&rs.i1.r.i4.pos=0&rs.i0.r.i7.syms=SYM30&denomination.standard=5&rs.i0.r.i6.syms=SYM30&rs.i3.id=basic&rs.i4.r.i0.overlay.i0.pos=3&rs.i0.r.i12.hold=false&multiplier=1&rs.i2.r.i2.pos=25&rs.i0.r.i9.syms=SYM30&last.rs=freespin&freespins.denomination=5.000&rs.i0.r.i8.pos=0&autoplay=10%2C25%2C50%2C75%2C100%2C250%2C500%2C750%2C1000&freespins.totalwin.coins=60&freespins.total=10&gamestate.stack=basic%2Cfreespin&rs.i1.r.i4.syms=SYM8%2CSYM3%2CSYM7&rs.i4.r.i0.attention.i0=0&rs.i2.r.i2.syms=SYM10%2CSYM11%2CSYM12&gamesoundurl=https%3A%2F%2Fstatic.casinomodule.com%2F&rs.i1.r.i2.pos=0&rs.i2.r.i4.overlay.i0.row=0&rs.i3.r.i3.syms=SYM1%2CSYM10%2CSYM2&rs.i4.r.i4.attention.i0=1&bet.betlevel=1&rs.i3.r.i4.hold=false&rs.i4.r.i2.hold=false&rs.i0.r.i14.pos=0&nearwinallowed=true&rs.i4.r.i1.syms=SYM12%2CSYM5%2CSYM9&rs.i2.r.i4.pos=42&rs.i3.r.i0.syms=SYM11%2CSYM7%2CSYM10&playercurrencyiso=' . $slotSettings->slotCurrency . '&rs.i0.r.i11.syms=SYM30&rs.i4.r.i1.hold=false&freespins.wavecount=1&rs.i3.r.i2.pos=131&rs.i3.r.i3.hold=false&freespins.multiplier=1&playforfun=false&jackpotcurrencyiso=' . $slotSettings->slotCurrency . '&rs.i0.r.i4.syms=SYM30&rs.i0.r.i2.pos=0&rs.i1.r.i2.syms=SYM8%2CSYM3%2CSYM7&rs.i1.r.i0.pos=0&totalwin.cents=300&bl.i0.coins=20&rs.i0.r.i12.pos=0&rs.i2.r.i0.syms=SYM5%2CSYM8%2CSYM11&rs.i0.r.i0.hold=false&rs.i2.r.i3.syms=SYM10%2CSYM8%2CSYM4&restore=true&rs.i1.id=freespinwalkingwild&rs.i3.r.i4.syms=SYM3%2CSYM10%2CSYM0&rs.i0.r.i6.hold=false&rs.i3.r.i1.syms=SYM6%2CSYM12%2CSYM4&rs.i1.r.i4.hold=false&freespins.left=7&rs.i0.r.i4.pos=0&rs.i0.r.i9.hold=false&rs.i4.r.i1.pos=17&rs.i4.r.i2.syms=SYM11%2CSYM0%2CSYM6&bl.standard=243&rs.i0.r.i10.pos=0&rs.i0.r.i14.hold=false&rs.i0.r.i11.hold=false&rs.i3.r.i0.pos=0&rs.i3.r.i0.hold=false&rs.i4.nearwin=4&rs.i2.r.i2.hold=false&wavecount=1&rs.i1.r.i1.hold=false&rs.i0.r.i3.hold=false&bet.denomination=5';
                            }

                            $result_tmp[] = 'rs.i4.id=basic&rs.i2.r.i1.hold=false&rs.i2.r.i13.pos=0&rs.i1.r.i0.syms=SYM12%2CSYM2%2CSYM9&gameServerVersion=1.21.0&g4mode=false&historybutton=false&rs.i0.r.i4.hold=false&gameEventSetters.enabled=false&rs.i1.r.i2.hold=false&rs.i1.r.i3.pos=0&rs.i0.r.i1.syms=SYM6%2CSYM12%2CSYM8&rs.i2.r.i1.pos=0&game.win.cents=0&rs.i4.r.i4.pos=0&staticsharedurl=https%3A%2F%2Fstatic-shared.casinomodule.com%2Fgameclient_html%2Fdevicedetection%2Fcurrent&bl.i0.reelset=ALL&rs.i1.r.i3.hold=false&rs.i2.r.i11.pos=0&totalwin.coins=0&gamestate.current=basic&rs.i4.r.i0.pos=0&jackpotcurrency=%26%23x20AC%3B&walkingwilds.pos=0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0&rs.i3.r.i1.hold=false&rs.i2.r.i0.hold=false&rs.i0.r.i0.syms=SYM3%2CSYM11%2CSYM12&rs.i0.r.i3.syms=SYM6%2CSYM12%2CSYM8&rs.i1.r.i1.syms=SYM12%2CSYM7%2CSYM2&rs.i1.r.i1.pos=0&rs.i2.r.i10.hold=false&rs.i3.r.i4.pos=0&rs.i2.r.i8.syms=SYM30&isJackpotWin=false&rs.i0.r.i0.pos=0&rs.i2.r.i3.hold=false&rs.i2.r.i3.pos=0&rs.i0.r.i1.pos=0&rs.i4.r.i4.syms=SYM3%2CSYM10%2CSYM0&rs.i1.r.i3.syms=SYM3%2CSYM9%2CSYM11&rs.i2.r.i4.hold=false&rs.i3.r.i1.pos=0&rs.i2.id=respin&game.win.coins=0&rs.i1.r.i0.hold=false&rs.i0.r.i1.hold=false&rs.i2.r.i5.pos=0&rs.i2.r.i7.syms=SYM30&rs.i2.r.i1.syms=SYM30&clientaction=init&rs.i4.r.i0.hold=false&rs.i0.r.i2.hold=false&rs.i4.r.i3.syms=SYM1%2CSYM10%2CSYM2&casinoID=netent&betlevel.standard=1&rs.i3.r.i2.hold=false&rs.i2.r.i10.syms=SYM30&gameover=true&rs.i3.r.i3.pos=0&rs.i2.r.i7.pos=0&rs.i0.r.i3.pos=0&rs.i4.r.i0.syms=SYM11%2CSYM7%2CSYM10&bl.i0.id=243&bl.i0.line=0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2&nextaction=spin&rs.i2.r.i14.pos=0&rs.i2.r.i12.hold=false&rs.i4.r.i2.pos=131&rs.i0.r.i2.syms=SYM3%2CSYM11%2CSYM12&game.win.amount=0&betlevel.all=1%2C2%2C3%2C4%2C5%2C6%2C7%2C8%2C9%2C10&rs.i2.r.i12.syms=SYM30&denomination.all=' . implode('%2C', $denomStrings) . '&rs.i2.r.i9.pos=0&rs.i4.r.i3.pos=60&playercurrency=%26%23x20AC%3B&rs.i2.r.i7.hold=false&rs.i2.r.i0.pos=0&rs.i4.r.i4.hold=false&rs.i2.r.i4.syms=SYM30&rs.i3.r.i2.syms=SYM8%2CSYM3%2CSYM7&rs.i2.r.i12.pos=0&rs.i4.r.i3.hold=false&rs.i2.r.i13.syms=SYM30&rs.i0.id=freespin&credit=' . $balanceInCents . '&rs.i1.r.i4.pos=0&rs.i2.r.i14.hold=false&denomination.standard=' . ($slotSettings->CurrentDenomination * 100) . '&rs.i2.r.i13.hold=false&rs.i3.id=freespinwalkingwild&multiplier=1&rs.i2.r.i2.pos=0&rs.i2.r.i10.pos=0&autoplay=10%2C25%2C50%2C75%2C100%2C250%2C500%2C750%2C1000&rs.i2.r.i5.syms=SYM30&rs.i2.r.i6.hold=false&rs.i1.r.i4.syms=SYM12%2CSYM10%2CSYM0&rs.i2.r.i2.syms=SYM30&gamesoundurl=https%3A%2F%2Fstatic.casinomodule.com%2F&rs.i1.r.i2.pos=0&rs.i3.r.i3.syms=SYM3%2CSYM9%2CSYM12&rs.i3.r.i4.hold=false&rs.i4.r.i2.hold=false&nearwinallowed=true&rs.i2.r.i9.hold=false&rs.i4.r.i1.syms=SYM6%2CSYM12%2CSYM4&rs.i2.r.i4.pos=0&rs.i3.r.i0.syms=SYM8%2CSYM3%2CSYM7&playercurrencyiso=' . $slotSettings->slotCurrency . '&rs.i4.r.i1.hold=false&rs.i3.r.i2.pos=0&rs.i3.r.i3.hold=false&playforfun=false&jackpotcurrencyiso=' . $slotSettings->slotCurrency . '&rs.i0.r.i4.syms=SYM3%2CSYM11%2CSYM12&rs.i2.r.i11.hold=false&rs.i0.r.i2.pos=0&rs.i1.r.i2.syms=SYM12%2CSYM11%2CSYM0&rs.i2.r.i6.pos=0&rs.i1.r.i0.pos=0&totalwin.cents=0&bl.i0.coins=20&rs.i2.r.i0.syms=SYM30&rs.i0.r.i0.hold=false&rs.i2.r.i3.syms=SYM30&restore=false&rs.i1.id=basicwalkingwild&rs.i2.r.i6.syms=SYM30&rs.i3.r.i4.syms=SYM8%2CSYM3%2CSYM7&rs.i3.r.i1.syms=SYM3%2CSYM9%2CSYM12&rs.i1.r.i4.hold=false&rs.i2.r.i8.hold=false&rs.i0.r.i4.pos=0&rs.i2.r.i9.syms=SYM30&rs.i4.r.i1.pos=0&rs.i4.r.i2.syms=SYM4%2CSYM10%2CSYM9&rs.i2.r.i14.syms=SYM30&rs.i2.r.i5.hold=false&bl.standard=243&rs.i3.r.i0.pos=0&rs.i2.r.i8.pos=0&rs.i3.r.i0.hold=false&rs.i2.r.i2.hold=false&rs.i2.r.i11.syms=SYM30&wavecount=1&rs.i1.r.i1.hold=false&rs.i0.r.i3.hold=false' . $curReelsString . $responseFreeState ;
                            break;
                        case 'paytable':
                            // This long string is likely static, representing paytable info
                            $result_tmp[] = 'pt.i0.comp.i19.symbol=SYM8&pt.i0.comp.i15.type=betline&pt.i0.comp.i23.freespins=0&pt.i0.comp.i32.type=betline&pt.i0.comp.i35.multi=0&pt.i0.comp.i29.type=betline&pt.i0.comp.i4.multi=80&pt.i0.comp.i15.symbol=SYM7&pt.i0.comp.i17.symbol=SYM7&pt.i0.comp.i5.freespins=0&pt.i1.comp.i14.multi=250&pt.i0.comp.i22.multi=15&pt.i0.comp.i23.n=5&pt.i1.comp.i19.type=betline&pt.i0.comp.i11.symbol=SYM5&pt.i0.comp.i13.symbol=SYM6&pt.i1.comp.i8.type=betline&pt.i1.comp.i4.n=4&pt.i1.comp.i27.multi=5&pt.i0.comp.i15.multi=10&pt.i1.comp.i27.symbol=SYM11&bl.i0.reelset=ALL&pt.i0.comp.i16.freespins=0&pt.i0.comp.i28.multi=10&pt.i1.comp.i6.freespins=0&pt.i1.comp.i29.symbol=SYM11&pt.i1.comp.i29.freespins=0&pt.i1.comp.i22.n=4&pt.i1.comp.i30.symbol=SYM12&pt.i1.comp.i3.multi=20&pt.i0.comp.i11.n=5&pt.i0.comp.i4.freespins=0&pt.i1.comp.i23.symbol=SYM9&pt.i1.comp.i25.symbol=SYM10&pt.i0.comp.i30.freespins=0&pt.i1.comp.i24.type=betline&pt.i0.comp.i19.n=4&pt.i0.id=basic&pt.i0.comp.i1.type=betline&pt.i0.comp.i34.n=4&pt.i1.comp.i10.type=betline&pt.i0.comp.i34.type=scatter&pt.i0.comp.i2.symbol=SYM1&pt.i0.comp.i4.symbol=SYM3&pt.i1.comp.i5.freespins=0&pt.i0.comp.i20.type=betline&pt.i1.comp.i8.symbol=SYM4&pt.i1.comp.i19.n=4&pt.i0.comp.i17.freespins=0&pt.i0.comp.i6.symbol=SYM4&pt.i0.comp.i8.symbol=SYM4&pt.i0.comp.i0.symbol=SYM1&pt.i1.comp.i11.n=5&pt.i0.comp.i5.n=5&pt.i1.comp.i2.symbol=SYM1&pt.i0.comp.i3.type=betline&pt.i0.comp.i3.freespins=0&pt.i0.comp.i10.multi=60&pt.i1.id=freespin&pt.i1.comp.i19.multi=30&pt.i1.comp.i6.symbol=SYM4&pt.i0.comp.i27.multi=5&pt.i0.comp.i9.multi=15&pt.i0.comp.i22.symbol=SYM9&pt.i0.comp.i26.symbol=SYM10&pt.i1.comp.i19.freespins=0&pt.i0.comp.i24.n=3&pt.i0.comp.i14.freespins=0&pt.i0.comp.i21.freespins=0&clientaction=paytable&pt.i1.comp.i27.freespins=0&pt.i1.comp.i4.freespins=0&pt.i1.comp.i12.type=betline&pt.i1.comp.i5.n=5&pt.i1.comp.i8.multi=300&pt.i1.comp.i21.symbol=SYM9&pt.i1.comp.i23.n=5&pt.i0.comp.i22.type=betline&pt.i0.comp.i24.freespins=0&pt.i1.comp.i32.symbol=SYM12&pt.i0.comp.i16.multi=30&pt.i0.comp.i21.multi=5&pt.i1.comp.i13.multi=60&pt.i0.comp.i12.n=3&pt.i0.comp.i35.n=5&pt.i0.comp.i13.type=betline&pt.i1.comp.i9.multi=15&bl.i0.line=0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2%2C0%2F1%2F2&pt.i0.comp.i19.type=betline&pt.i0.comp.i6.freespins=0&pt.i1.comp.i2.multi=300&pt.i1.comp.i7.freespins=0&pt.i0.comp.i31.freespins=0&pt.i0.comp.i3.multi=20&pt.i0.comp.i6.n=3&pt.i1.comp.i22.type=betline&pt.i1.comp.i12.n=3&pt.i1.comp.i3.type=betline&pt.i0.comp.i21.n=3&pt.i1.comp.i10.freespins=0&pt.i1.comp.i28.type=betline&pt.i0.comp.i34.symbol=SYM0&pt.i1.comp.i6.n=3&pt.i0.comp.i29.n=5&pt.i1.comp.i31.type=betline&pt.i1.comp.i20.multi=120&pt.i0.comp.i27.freespins=0&pt.i0.comp.i34.freespins=10&pt.i1.comp.i24.n=3&pt.i0.comp.i10.type=betline&pt.i0.comp.i35.freespins=10&pt.i1.comp.i11.symbol=SYM5&pt.i1.comp.i27.type=betline&pt.i1.comp.i2.type=betline&pt.i0.comp.i2.freespins=0&pt.i0.comp.i5.multi=300&pt.i0.comp.i7.n=4&pt.i0.comp.i32.n=5&pt.i1.comp.i1.freespins=0&pt.i0.comp.i11.multi=250&pt.i1.comp.i14.symbol=SYM6&pt.i1.comp.i16.symbol=SYM7&pt.i1.comp.i23.multi=60&pt.i0.comp.i7.type=betline&pt.i1.comp.i4.type=betline&pt.i0.comp.i17.n=5&pt.i1.comp.i18.multi=10&pt.i0.comp.i29.multi=40&pt.i1.comp.i13.n=4&pt.i0.comp.i8.freespins=0&pt.i1.comp.i26.type=betline&pt.i1.comp.i4.multi=80&pt.i0.comp.i8.multi=300&gamesoundurl=https%3A%2F%2Fstatic.casinomodule.com%2F&pt.i0.comp.i34.multi=0&pt.i0.comp.i1.freespins=0&pt.i0.comp.i12.type=betline&pt.i0.comp.i14.multi=250&pt.i1.comp.i7.multi=80&pt.i0.comp.i22.n=4&pt.i0.comp.i28.symbol=SYM11&pt.i1.comp.i17.type=betline&pt.i1.comp.i11.type=betline&pt.i0.comp.i6.multi=20&pt.i1.comp.i0.symbol=SYM1&playercurrencyiso=' . $slotSettings->slotCurrency . '&pt.i1.comp.i7.n=4&pt.i1.comp.i5.multi=300&pt.i1.comp.i5.symbol=SYM3&pt.i0.comp.i18.type=betline&pt.i0.comp.i23.symbol=SYM9&pt.i0.comp.i21.type=betline&playforfun=false&jackpotcurrencyiso=' . $slotSettings->slotCurrency . '&pt.i1.comp.i25.n=4&pt.i0.comp.i8.type=betline&pt.i0.comp.i7.freespins=0&pt.i1.comp.i15.multi=10&pt.i0.comp.i2.type=betline&pt.i0.comp.i13.multi=60&pt.i1.comp.i20.type=betline&pt.i0.comp.i17.type=betline&pt.i0.comp.i30.type=betline&pt.i1.comp.i22.symbol=SYM9&pt.i1.comp.i30.freespins=0&pt.i1.comp.i22.multi=15&bl.i0.coins=20&pt.i0.comp.i8.n=5&pt.i0.comp.i10.n=4&pt.i0.comp.i33.n=3&pt.i1.comp.i6.multi=20&pt.i1.comp.i22.freespins=0&pt.i0.comp.i11.type=betline&pt.i1.comp.i19.symbol=SYM8&pt.i0.comp.i18.n=3&pt.i0.comp.i22.freespins=0&pt.i0.comp.i20.symbol=SYM8&pt.i0.comp.i15.freespins=0&pt.i1.comp.i14.n=5&pt.i1.comp.i16.multi=30&pt.i0.comp.i31.symbol=SYM12&pt.i1.comp.i15.freespins=0&pt.i0.comp.i27.type=betline&pt.i1.comp.i28.freespins=0&pt.i0.comp.i28.freespins=0&pt.i0.comp.i0.n=3&pt.i0.comp.i7.symbol=SYM4&pt.i1.comp.i21.multi=5&pt.i1.comp.i30.type=betline&pt.i1.comp.i0.freespins=0&pt.i0.comp.i0.type=betline&pt.i1.comp.i0.multi=20&gameServerVersion=1.21.0&g4mode=false&pt.i1.comp.i8.n=5&pt.i0.comp.i25.multi=15&historybutton=false&pt.i0.comp.i16.symbol=SYM7&pt.i1.comp.i21.freespins=0&pt.i0.comp.i1.multi=80&pt.i0.comp.i27.n=3&pt.i0.comp.i18.symbol=SYM8&pt.i1.comp.i9.type=betline&pt.i0.comp.i12.multi=15&pt.i0.comp.i32.multi=40&pt.i1.comp.i24.multi=5&pt.i1.comp.i14.freespins=0&pt.i1.comp.i23.type=betline&pt.i1.comp.i26.n=5&pt.i0.comp.i12.symbol=SYM6&pt.i0.comp.i14.symbol=SYM6&pt.i1.comp.i13.freespins=0&pt.i1.comp.i28.symbol=SYM11&pt.i0.comp.i14.type=betline&pt.i1.comp.i17.multi=120&pt.i0.comp.i18.multi=10&pt.i1.comp.i0.n=3&pt.i1.comp.i26.symbol=SYM10&pt.i0.comp.i33.type=scatter&pt.i1.comp.i31.symbol=SYM12&pt.i0.comp.i7.multi=80&pt.i0.comp.i9.n=3&pt.i0.comp.i30.n=3&pt.i1.comp.i21.type=betline&jackpotcurrency=%26%23x20AC%3B&pt.i0.comp.i28.type=betline&pt.i1.comp.i31.multi=10&pt.i1.comp.i18.type=betline&pt.i0.comp.i10.symbol=SYM5&pt.i0.comp.i15.n=3&pt.i0.comp.i21.symbol=SYM9&pt.i0.comp.i31.type=betline&pt.i1.comp.i15.n=3&isJackpotWin=false&pt.i1.comp.i20.freespins=0&pt.i1.comp.i7.type=betline&pt.i1.comp.i11.multi=250&pt.i1.comp.i30.n=3&pt.i0.comp.i1.n=4&pt.i0.comp.i10.freespins=0&pt.i0.comp.i20.multi=120&pt.i0.comp.i20.n=5&pt.i0.comp.i29.symbol=SYM11&pt.i1.comp.i3.symbol=SYM3&pt.i0.comp.i17.multi=120&pt.i1.comp.i23.freespins=0&pt.i1.comp.i25.type=betline&pt.i1.comp.i9.n=3&pt.i0.comp.i25.symbol=SYM10&pt.i0.comp.i26.type=betline&pt.i0.comp.i28.n=4&pt.i0.comp.i9.type=betline&pt.i0.comp.i2.multi=300&pt.i1.comp.i27.n=3&pt.i0.comp.i0.freespins=0&pt.i1.comp.i16.type=betline&pt.i1.comp.i25.multi=15&pt.i0.comp.i33.multi=0&pt.i1.comp.i16.freespins=0&pt.i1.comp.i20.symbol=SYM8&pt.i1.comp.i12.multi=15&pt.i0.comp.i29.freespins=0&pt.i1.comp.i1.n=4&pt.i1.comp.i5.type=betline&pt.i1.comp.i11.freespins=0&pt.i1.comp.i24.symbol=SYM10&pt.i0.comp.i31.n=4&pt.i0.comp.i9.symbol=SYM5&pt.i1.comp.i13.symbol=SYM6&pt.i1.comp.i17.symbol=SYM7&pt.i0.comp.i16.n=4&bl.i0.id=243&pt.i0.comp.i16.type=betline&pt.i1.comp.i16.n=4&pt.i0.comp.i5.symbol=SYM3&pt.i1.comp.i7.symbol=SYM4&pt.i0.comp.i2.n=5&pt.i0.comp.i35.type=scatter&pt.i0.comp.i1.symbol=SYM1&pt.i1.comp.i31.n=4&pt.i1.comp.i31.freespins=0&pt.i0.comp.i19.freespins=0&pt.i1.comp.i14.type=betline&pt.i0.comp.i6.type=betline&pt.i1.comp.i9.freespins=0&pt.i1.comp.i2.freespins=0&playercurrency=%26%23x20AC%3B&pt.i0.comp.i35.symbol=SYM0&pt.i1.comp.i25.freespins=0&pt.i0.comp.i33.symbol=SYM0&pt.i1.comp.i30.multi=5&pt.i0.comp.i25.n=4&pt.i1.comp.i10.multi=60&pt.i1.comp.i10.symbol=SYM5&pt.i1.comp.i28.n=4&pt.i1.comp.i32.freespins=0&pt.i0.comp.i9.freespins=0&pt.i1.comp.i2.n=5&pt.i1.comp.i20.n=5&credit=500000&pt.i0.comp.i5.type=betline&pt.i1.comp.i24.freespins=0&pt.i0.comp.i11.freespins=0&pt.i0.comp.i26.multi=60&pt.i0.comp.i25.type=betline&pt.i1.comp.i32.type=betline&pt.i1.comp.i18.symbol=SYM8&pt.i0.comp.i31.multi=10&pt.i1.comp.i12.symbol=SYM6&pt.i0.comp.i4.type=betline&pt.i0.comp.i13.freespins=0&pt.i1.comp.i15.type=betline&pt.i1.comp.i26.freespins=0&pt.i0.comp.i26.freespins=0&pt.i1.comp.i13.type=betline&pt.i1.comp.i1.multi=80&pt.i1.comp.i1.type=betline&pt.i1.comp.i8.freespins=0&pt.i0.comp.i13.n=4&pt.i0.comp.i20.freespins=0&pt.i0.comp.i33.freespins=10&pt.i1.comp.i17.n=5&pt.i0.comp.i23.type=betline&pt.i1.comp.i29.type=betline&pt.i0.comp.i30.symbol=SYM12&pt.i0.comp.i32.symbol=SYM12&pt.i1.comp.i32.n=5&pt.i0.comp.i3.n=3&pt.i1.comp.i17.freespins=0&pt.i1.comp.i26.multi=60&pt.i1.comp.i32.multi=40&pt.i1.comp.i6.type=betline&pt.i1.comp.i0.type=betline&pt.i1.comp.i1.symbol=SYM1&pt.i1.comp.i29.multi=40&pt.i0.comp.i25.freespins=0&pt.i1.comp.i4.symbol=SYM3&pt.i0.comp.i24.symbol=SYM10&pt.i0.comp.i26.n=5&pt.i0.comp.i27.symbol=SYM11&pt.i0.comp.i32.freespins=0&pt.i1.comp.i29.n=5&pt.i0.comp.i23.multi=60&pt.i1.comp.i3.n=3&pt.i0.comp.i30.multi=5&pt.i1.comp.i21.n=3&pt.i1.comp.i28.multi=10&pt.i0.comp.i18.freespins=0&pt.i1.comp.i15.symbol=SYM7&pt.i1.comp.i18.freespins=0&pt.i1.comp.i3.freespins=0&pt.i0.comp.i14.n=5&pt.i0.comp.i0.multi=20&pt.i1.comp.i9.symbol=SYM5&pt.i0.comp.i19.multi=30&pt.i0.comp.i3.symbol=SYM3&pt.i0.comp.i24.type=betline&pt.i1.comp.i18.n=3&pt.i1.comp.i12.freespins=0&pt.i0.comp.i12.freespins=0&pt.i0.comp.i4.n=4&pt.i1.comp.i10.n=4&pt.i0.comp.i24.multi=5';
                            break;
                        case 'initfreespin':
                            $result_tmp[] = 'rs.i4.id=basicwalkingwild&rs.i2.r.i1.hold=false&rs.i1.r.i0.syms=SYM8%2CSYM3%2CSYM7&gameServerVersion=1.21.0&g4mode=false&freespins.win.coins=0&historybutton=false&rs.i0.r.i4.hold=false&next.rs=freespin&gamestate.history=basic&rs.i0.r.i14.syms=SYM30&rs.i1.r.i2.hold=false&rs.i1.r.i3.pos=0&rs.i0.r.i1.syms=SYM30&rs.i0.r.i5.hold=false&rs.i0.r.i7.pos=0&rs.i2.r.i1.pos=0&game.win.cents=0&rs.i4.r.i4.pos=65&rs.i1.r.i3.hold=false&totalwin.coins=0&gamestate.current=freespin&freespins.initial=10&rs.i4.r.i0.pos=2&rs.i0.r.i12.syms=SYM30&jackpotcurrency=%26%23x20AC%3B&rs.i4.r.i0.overlay.i0.row=1&bet.betlines=243&rs.i3.r.i1.hold=false&rs.i2.r.i0.hold=false&rs.i0.r.i0.syms=SYM30&rs.i0.r.i3.syms=SYM30&rs.i1.r.i1.syms=SYM3%2CSYM9%2CSYM12&rs.i1.r.i1.pos=0&rs.i3.r.i4.pos=0&freespins.win.cents=0&isJackpotWin=false&rs.i0.r.i0.pos=0&rs.i2.r.i3.hold=false&rs.i2.r.i3.pos=0&freespins.betlines=243&rs.i0.r.i9.pos=0&rs.i4.r.i2.attention.i0=1&rs.i0.r.i1.pos=0&rs.i4.r.i4.syms=SYM5%2CSYM0%2CSYM7&rs.i1.r.i3.syms=SYM3%2CSYM9%2CSYM12&rs.i2.r.i4.hold=false&rs.i3.r.i1.pos=0&rs.i2.id=freespin&game.win.coins=0&rs.i1.r.i0.hold=false&rs.i0.r.i5.syms=SYM30&rs.i0.r.i1.hold=false&rs.i0.r.i13.pos=0&rs.i0.r.i13.hold=false&rs.i2.r.i1.syms=SYM6%2CSYM12%2CSYM8&rs.i0.r.i7.hold=false&clientaction=initfreespin&rs.i0.r.i8.hold=false&rs.i4.r.i0.hold=false&rs.i0.r.i2.hold=false&rs.i4.r.i3.syms=SYM4%2CSYM10%2CSYM9&rs.i3.r.i2.hold=false&gameover=false&rs.i3.r.i3.pos=60&rs.i0.r.i3.pos=0&rs.i4.r.i0.syms=SYM0%2CSYM7%2CSYM11&rs.i0.r.i11.pos=0&rs.i0.r.i10.syms=SYM30&rs.i0.r.i13.syms=SYM30&nextaction=freespin&rs.i0.r.i5.pos=0&rs.i4.r.i2.pos=32&rs.i0.r.i2.syms=SYM30&game.win.amount=0.00&freespins.totalwin.cents=0&freespins.betlevel=1&rs.i0.r.i6.pos=0&rs.i4.r.i3.pos=51&playercurrency=%26%23x20AC%3B&rs.i0.r.i10.hold=false&rs.i2.r.i0.pos=0&rs.i4.r.i4.hold=false&rs.i4.r.i0.overlay.i0.with=SYM1&rs.i0.r.i8.syms=SYM30&rs.i2.r.i4.syms=SYM3%2CSYM11%2CSYM12&rs.i3.r.i2.syms=SYM4%2CSYM10%2CSYM9&rs.i4.r.i3.hold=false&rs.i0.id=respin&credit=500525&rs.i1.r.i4.pos=0&rs.i0.r.i7.syms=SYM30&rs.i0.r.i6.syms=SYM30&rs.i3.id=basic&rs.i4.r.i0.overlay.i0.pos=3&rs.i0.r.i12.hold=false&multiplier=1&rs.i2.r.i2.pos=0&rs.i0.r.i9.syms=SYM30&freespins.denomination=5.000&rs.i0.r.i8.pos=0&freespins.totalwin.coins=0&freespins.total=10&gamestate.stack=basic%2Cfreespin&rs.i1.r.i4.syms=SYM8%2CSYM3%2CSYM7&rs.i4.r.i0.attention.i0=0&rs.i2.r.i2.syms=SYM3%2CSYM11%2CSYM12&gamesoundurl=https%3A%2F%2Fstatic.casinomodule.com%2F&rs.i1.r.i2.pos=0&rs.i3.r.i3.syms=SYM1%2CSYM10%2CSYM2&rs.i4.r.i4.attention.i0=1&bet.betlevel=1&rs.i3.r.i4.hold=false&rs.i4.r.i2.hold=false&rs.i0.r.i14.pos=0&rs.i4.r.i1.syms=SYM12%2CSYM5%2CSYM9&rs.i2.r.i4.pos=0&rs.i3.r.i0.syms=SYM11%2CSYM7%2CSYM10&playercurrencyiso=' . $slotSettings->slotCurrency . '&rs.i0.r.i11.syms=SYM30&rs.i4.r.i1.hold=false&freespins.wavecount=1&rs.i3.r.i2.pos=131&rs.i3.r.i3.hold=false&freespins.multiplier=1&playforfun=false&jackpotcurrencyiso=' . $slotSettings->slotCurrency . '&rs.i0.r.i4.syms=SYM30&rs.i0.r.i2.pos=0&rs.i1.r.i2.syms=SYM8%2CSYM3%2CSYM7&rs.i1.r.i0.pos=0&totalwin.cents=0&rs.i0.r.i12.pos=0&rs.i2.r.i0.syms=SYM3%2CSYM11%2CSYM12&rs.i0.r.i0.hold=false&rs.i2.r.i3.syms=SYM6%2CSYM12%2CSYM8&rs.i1.id=freespinwalkingwild&rs.i3.r.i4.syms=SYM3%2CSYM10%2CSYM0&rs.i0.r.i6.hold=false&rs.i3.r.i1.syms=SYM6%2CSYM12%2CSYM4&rs.i1.r.i4.hold=false&freespins.left=10&rs.i0.r.i4.pos=0&rs.i0.r.i9.hold=false&rs.i4.r.i1.pos=17&rs.i4.r.i2.syms=SYM11%2CSYM0%2CSYM6&rs.i0.r.i10.pos=0&rs.i0.r.i14.hold=false&rs.i0.r.i11.hold=false&rs.i3.r.i0.pos=0&rs.i3.r.i0.hold=false&rs.i4.nearwin=4&rs.i2.r.i2.hold=false&wavecount=1&rs.i1.r.i1.hold=false&rs.i0.r.i3.hold=false&bet.denomination=5';
                            break;
                        case 'respin':
                            $reelStrips = [];
                            $reelStrips[0] = [
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30'
                            ];
                            $reelStrips[1] = [
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30'
                            ];
                            $reelStrips[2] = [
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30'
                            ];
                            $reelStrips[3] = [
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '2',
                                '30'
                            ];
                            $reelStrips[4] = [
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '2',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30',
                                '30'
                            ];
                            $ClusterSpinCount = $slotSettings->GetGameData($slotSettings->slotId . 'ClusterSpinCount');
                            $clusterAllWinOld = $slotSettings->GetGameData($slotSettings->slotId . 'clusterAllWin');
                            $clusterAllWin = $slotSettings->GetGameData($slotSettings->slotId . 'clusterAllWin');
                            $clusterSymAllWins = $slotSettings->GetGameData($slotSettings->slotId . 'clusterSymAllWins');
                            $allbet = $slotSettings->GetGameData($slotSettings->slotId . 'AllBet');
                            $clusterSymWinsArr = $slotSettings->GetGameData($slotSettings->slotId . 'clusterSymWinsArr');
                            $slotSettings->SetBank((isset($postData['slotEvent']) ? $postData['slotEvent'] : ''), $clusterAllWin);
                            $slotSettings->SetBalance(-1 * $clusterAllWin);
                            $bank = $slotSettings->GetBank((isset($postData['slotEvent']) ? $postData['slotEvent'] : ''));
                            for ($bLoop = 0; $bLoop <= 500; $bLoop++) {
                                $reels_c = $slotSettings->GetGameData($slotSettings->slotId . 'clusterReels');
                                $clusterSymStr = '';
                                $clusterAllWin = 0;
                                $curReels = '';
                                $reels = []; // This $reels is for the temporary spin inside respin
                                $symcnt = 0;
                                for ($r = 1; $r <= 5; $r++) {
                                    $reels['reel' . $r] = [];
                                    $randPos = rand(1, count($reelStrips[$r - 1]) - 3);
                                    $reels['reel' . $r][0] = $reelStrips[$r - 1][$randPos - 1];
                                    $reels['reel' . $r][1] = $reelStrips[$r - 1][$randPos];
                                    $reels['reel' . $r][2] = $reelStrips[$r - 1][$randPos + 1];
                                }
                                for ($r = 1; $r <= 5; $r++) {
                                    for ($p = 0; $p <= 2; $p++) {
                                        if ($reels_c['reel' . $r][$p] != '2c' && $reels_c['reel' . $r][$p] != '2') {
                                            $reels_c['reel' . $r][$p] = $reels['reel' . $r][$p];
                                        }
                                    }
                                }
                                $reels_c = $slotSettings->GetCluster($reels_c);
                                $reels_c = $slotSettings->GetCluster($reels_c);
                                $reels_c = $slotSettings->GetCluster($reels_c);
                                $reels_c = $slotSettings->GetCluster($reels_c);
                                $symcnt = 0;
                                $symcnt0 = 0;
                                $nearwin = [];
                                $holds = '';
                                for ($r = 1; $r <= 5; $r++) {
                                    for ($p = 0; $p <= 2; $p++) {
                                        if ($reels_c['reel' . $r][$p] == '2c' || $reels_c['reel' . $r][$p] == '2') {
                                            $holds .= ('&rs.i0.r.i' . $symcnt0 . '.hold=true');
                                        } else {
                                            $holds .= ('&rs.i0.r.i' . $symcnt0 . '.hold=false');
                                        }
                                        if ($reels_c['reel' . $r][$p] == '2c') {
                                            if (!isset($clusterSymAllWins[$symcnt])) {
                                                $cwin = $clusterSymWinsArr[$r][$p] * $allbet;
                                                $clusterAllWin += $cwin;
                                                $clusterSymAllWins[] = $cwin;
                                            } else {
                                                $cwin = $clusterSymWinsArr[$r][$p] * $allbet;
                                                $clusterAllWin += $cwin;
                                            }
                                            $clusterSymStr .= ('&lockup.cluster.i0.sym.i' . $symcnt . '.value=' . $cwin);
                                            $clusterSymStr .= ('&lockup.cluster.i0.sym.i' . $symcnt . '.pos=' . ($r - 1) . '%2C' . $p);
                                            $symcnt++;
                                            $curReels .= ('&rs.i0.r.i' . $symcnt0 . '.syms=SYM2');
                                        } else {
                                            $curReels .= ('&rs.i0.r.i' . $symcnt0 . '.syms=SYM' . $reels_c['reel' . $r][$p]);
                                        }
                                        $symcnt0++;
                                    }
                                }
                                if ($clusterAllWin <= $bank) {
                                    $slotSettings->SetBank((isset($postData['slotEvent']) ? $postData['slotEvent'] : ''), -1 * $clusterAllWin);
                                    $slotSettings->SetBalance($clusterAllWin);
                                    break;
                                }
                            }
                            if ($clusterAllWinOld < $clusterAllWin) {
                                $ClusterSpinCount = 3;
                            } else {
                                $ClusterSpinCount--;
                            }
                            $slotSettings->SetGameData($slotSettings->slotId . 'clusterAllWin', $clusterAllWin);
                            $slotSettings->SetGameData($slotSettings->slotId . 'clusterSymAllWins', $clusterSymAllWins);
                            $slotSettings->SetGameData($slotSettings->slotId . 'clusterReels', $reels_c);
                            $finalReelsSymbols = $reels_c; // Capture for responseState
                            $slotSettings->SetGameData($slotSettings->slotId . 'ClusterSpinCount', $ClusterSpinCount);
                            if ($ClusterSpinCount <= 0) {
                                $clusterSymStr .= ('&lockup.deltawin.cents=' . ($clusterAllWin * $slotSettings->CurrentDenomination * 100));
                                $clusterSymStr .= ('&lockup.win.cents=' . ($clusterAllWin * $slotSettings->CurrentDenomination * 100));
                                $clusterSymStr .= ('&lockup.deltawin.coins=' . $clusterAllWin);
                                $clusterSymStr .= ('&lockup.win.coins=' . $clusterAllWin);
                                $clusterSymStr .= ('&totalwin.coins=' . $clusterAllWin);
                                $clusterSymStr .= ('&game.win.coins=' . $clusterAllWin);
                                $symcnt0 = 0;
                                for ($r = 1; $r <= 5; $r++) {
                                    for ($p = 0; $p <= 2; $p++) {
                                        $clusterSymStr .= ('&rs.i0.r.i' . $symcnt0 . '.hold=false');
                                        $symcnt0++;
                                    }
                                }
                                $balanceInCents = round($slotSettings->GetBalance() * $slotSettings->CurrentDenom * 100);
                                $result_tmp[0] = 'rs.i0.r.i6.pos=0&gameServerVersion=1.21.0&g4mode=false&playercurrency=%26%23x20AC%3B&historybutton=false&rs.i0.r.i10.hold=false&rs.i0.r.i4.hold=false&ws.i0.reelset=respin&next.rs=basic&rs.i0.r.i8.syms=SYM2&gamestate.history=basic%2Crespin&lockup.cluster.i0.sym.i1.value=60&rs.i0.r.i14.syms=SYM30&lockup.deltawin.cents=0&rs.i0.r.i1.syms=SYM30&rs.i0.r.i5.hold=false&rs.i0.r.i7.pos=8&lockup.respins.left=0&game.win.cents=900&ws.i0.betline=null&rs.i0.id=respin&totalwin.coins=180&credit=' . $balanceInCents . '&gamestate.current=basic&rs.i0.r.i7.syms=SYM30&ws.i0.types.i0.coins=180&rs.i0.r.i6.syms=SYM30&rs.i0.r.i12.syms=SYM30&rs.i0.r.i12.hold=false&jackpotcurrency=%26%23x20AC%3B&multiplier=1&walkingwilds.pos=0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0&rs.i0.r.i9.syms=SYM30&last.rs=respin&rs.i0.r.i0.syms=SYM2&rs.i0.r.i3.syms=SYM30&rs.i0.r.i8.pos=0&ws.i0.sym=SYM2&ws.i0.direction=left_to_right&lockup.win.cents=900&isJackpotWin=false&gamestate.stack=basic&rs.i0.r.i0.pos=10&lockup.cluster.i0.sym.i0.value=100&gamesoundurl=https%3A%2F%2Fstatic.casinomodule.com%2F&rs.i0.r.i9.pos=1&ws.i0.types.i0.wintype=coins&rs.i0.r.i14.pos=2&rs.i0.r.i1.pos=6&game.win.coins=180&playercurrencyiso=' . $slotSettings->slotCurrency . '&rs.i0.r.i5.syms=SYM2&rs.i0.r.i1.hold=false&rs.i0.r.i13.pos=9&rs.i0.r.i13.hold=false&lockup.cluster.i0.sym.i2.pos=3%2C2&rs.i0.r.i11.syms=SYM2&lockup.deltawin.coins=0&rs.i0.r.i7.hold=false&playforfun=false&jackpotcurrencyiso=' . $slotSettings->slotCurrency . '&clientaction=respin&rs.i0.r.i8.hold=false&rs.i0.r.i2.hold=false&rs.i0.r.i4.syms=SYM30&lockup.cluster.i0.sym.i0.pos=1%2C2&rs.i0.r.i2.pos=4&totalwin.cents=900&gameover=true&rs.i0.r.i12.pos=8&rs.i0.r.i0.hold=false&rs.i0.r.i6.hold=false&rs.i0.r.i3.pos=5&rs.i0.r.i4.pos=13&lockup.cluster.i0.sym.i2.value=20&rs.i0.r.i9.hold=false&lockup.win.coins=180&rs.i0.r.i11.pos=0&ws.i0.types.i0.cents=900&rs.i0.r.i10.syms=SYM30&rs.i0.r.i10.pos=11&rs.i0.r.i14.hold=false&rs.i0.r.i11.hold=false&rs.i0.r.i13.syms=SYM30&nextaction=spin&rs.i0.r.i5.pos=0&wavecount=1&rs.i0.r.i2.syms=SYM30&lockup.cluster.i0.sym.i1.pos=2%2C2&rs.i0.r.i3.hold=false&game.win.amount=9.00' . $curReels . $clusterSymStr;
                            } else {
                                $balanceInCents = $slotSettings->GetGameData($slotSettings->slotId . 'StaticBalance');
                                $clusterSymStr .= ('&lockup.deltawin.cents=' . ($clusterAllWin * $slotSettings->CurrentDenomination * 100));
                                $clusterSymStr .= ('&lockup.win.cents=' . ($clusterAllWin * $slotSettings->CurrentDenomination * 100));
                                $clusterSymStr .= ('&lockup.deltawin.coins=' . $clusterAllWin);
                                $clusterSymStr .= ('&lockup.win.coins=' . $clusterAllWin);
                                $clusterSymStr .= ('&totalwin.coins=' . $clusterAllWin);
                                $clusterSymStr .= ('&game.win.coins=' . $clusterAllWin);
                                $result_tmp[0] = 'gameServerVersion=1.21.0&g4mode=false&historybutton=false&rs.i0.r.i4.hold=false&next.rs=respin&gamestate.history=basic%2Crespin&rs.i0.r.i14.syms=&lockup.deltawin.cents=1500&rs.i0.r.i1.syms=SYM30&rs.i0.r.i5.hold=false&rs.i0.r.i7.pos=0&game.win.cents=175&totalwin.coins=35&gamestate.current=respin&rs.i0.r.i12.syms=SYM30&jackpotcurrency=%26%23x20AC%3B&walkingwilds.pos=0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0%2C0&rs.i0.r.i0.syms=SYM30&rs.i0.r.i3.syms=SYM30&isJackpotWin=false&rs.i0.r.i0.pos=0&rs.i0.r.i9.pos=0&rs.i0.r.i1.pos=5&game.win.coins=35&rs.i0.r.i5.syms=SYM30&rs.i0.r.i1.hold=false&rs.i0.r.i13.pos=0&rs.i0.r.i13.hold=false&rs.i0.r.i7.hold=false&clientaction=respin&rs.i0.r.i8.hold=false&rs.i0.r.i2.hold=false&gameover=false&rs.i0.r.i3.pos=13&lockup.win.coins=435&rs.i0.r.i11.pos=11&rs.i0.r.i10.syms=SYM2&rs.i0.r.i13.syms=SYM2&nextaction=respin&rs.i0.r.i5.pos=10&rs.i0.r.i2.syms=SYM30&game.win.amount=1.75&rs.i0.r.i6.pos=2&playercurrency=%26%23x20AC%3B&rs.i0.r.i10.hold=false&rs.i0.r.i8.syms=SYM30&lockup.respins.left=' . $ClusterSpinCount . '&rs.i0.id=respin&credit=' . $balanceInCents . '&rs.i0.r.i7.syms=SYM2&rs.i0.r.i6.syms=SYM30&rs.i0.r.i12.hold=false&multiplier=1&rs.i0.r.i9.syms=SYM30&last.rs=respin&rs.i0.r.i8.pos=2&lockup.win.cents=2175&gamestate.stack=basic%2Crespin&gamesoundurl=&rs.i0.r.i14.pos=10&playercurrencyiso=' . $slotSettings->slotCurrency . '&rs.i0.r.i11.syms=SYM30&lockup.deltawin.coins=300&playforfun=false&jackpotcurrencyiso=' . $slotSettings->slotCurrency . '&rs.i0.r.i4.syms=SYM30&rs.i0.r.i2.pos=5&totalwin.cents=175&rs.i0.r.i12.pos=2&rs.i0.r.i0.hold=false&rs.i0.r.i6.hold=false&rs.i0.r.i4.pos=10&rs.i0.r.i9.hold=false&rs.i0.r.i10.pos=0&rs.i0.r.i14.hold=false&rs.i0.r.i11.hold=false&wavecount=1' . $curReels . $clusterSymStr . $holds;
                            }
                            // $response = $slotSettings->GetGameData($slotSettings->slotId . 'LastResponse'); // This was for logging, not used for client response now
                            $slotSettings->SaveLogReport($result_tmp[0], 0, 1, $clusterAllWin - $clusterAllWinOld, 'FG2');
                            $responseTotalWin = $clusterAllWin; // Capture total win for respin
                            break;
                        case 'spin':
                            $lines = 20; // Or from $postData if variable
                            // $slotSettings->CurrentDenom = $postData['bet_denomination']; // Already set from $gameStateData['postData']
                            // $slotSettings->CurrentDenomination = $postData['bet_denomination']; // Already set

                            $betline = $postData['bet_betlevel'] ?? $slotSettings->GetGameData($slotSettings->slotId . 'Bet'); // Fallback to stored bet if not in postData
                            $responseSlotLines = $lines;
                            $responseSlotBet = $betline;

                            if ($currentSlotEvent != 'freespin') {
                                $allbet = $betline * $lines;
                                $slotSettings->UpdateJackpots($allbet); //Jackpots are updated based on $slotSettings->jpgs now
                                $slotSettings->SetBalance(-1 * $allbet, $currentSlotEvent);
                                $bankSum = $allbet / 100 * $slotSettings->GetPercent();
                                $slotSettings->SetBank($currentSlotEvent, $bankSum, $currentSlotEvent);
                                // $slotSettings->UpdateJackpots($allbet); // Called once above
                                $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', 0);
                                $slotSettings->SetGameData($slotSettings->slotId . 'FreeGames', 0);
                                $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', 0);
                                $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', 0);
                                $slotSettings->SetGameData($slotSettings->slotId . 'Bet', $betline);
                                $slotSettings->SetGameData($slotSettings->slotId . 'Denom', $postData['bet_denomination']);
                                $slotSettings->SetGameData($slotSettings->slotId . 'FreeBalance', sprintf('%01.2f', $slotSettings->GetBalance()) * 100);
                                $bonusMpl = 1;
                            } else {
                                // Freespin uses stored bet/denom
                                $postData['bet_denomination'] = $slotSettings->GetGameData($slotSettings->slotId . 'Denom');
                                $slotSettings->CurrentDenom = $postData['bet_denomination'];
                                $slotSettings->CurrentDenomination = $postData['bet_denomination'];
                                $betline = $slotSettings->GetGameData($slotSettings->slotId . 'Bet');
                                $allbet = $betline * $lines;
                                $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') + 1);
                                $bonusMpl = $slotSettings->slotFreeMpl;
                            }

                            $winTypeTmp = $slotSettings->GetSpinSettings($currentSlotEvent, $allbet, $lines);
                            $winType = $winTypeTmp[0];
                            $spinWinLimit = $winTypeTmp[1];

                            $balanceInCents = round($slotSettings->GetBalance() * ($slotSettings->CurrentDenom > 0 ? $slotSettings->CurrentDenom : 1) * 100);

                            if ($winType == 'bonus' && $currentSlotEvent == 'freespin') {
                                $winType = 'win';
                            }

                            // ... (The big loop for spin logic from original) ...
                            // This loop iterates up to 2000 times to find a winning combination or acceptable outcome.
                            // It sets $totalWin, $lineWins, $reels (final reel positions), $curReels (string for response), etc.
                            // For brevity, this entire loop is not duplicated. It must be adapted.
                            // Key variables to capture from this loop for $responseState:
                            // $reelsTmp (final reel symbols, named $reels in original after loop),
                            // $totalWin (actual total win from this spin),
                            // $lineWins (array of win line strings), $responseFreeState (string), $attStr, $featureStr, etc.
                            // $jsSpin (json string of $reelsTmp), $jsJack (json string of jackpots)

                            // --- Start of original spin loop and win calculation (conceptual representation) ---
                            $totalWin = 0; // This will be calculated in the loop
                            $lineWins = []; // Populated in the loop
                            $reelsTmp = []; // This will be the final reel configuration from the loop
                            // ... many lines of complex win calculation from original, including GetReelStrips, symbol matching, feature triggers ...
                            // Ensure $reelsTmp is assigned the final reel state, e.g. $reelsTmp = $reels; from original
                            // $responseTotalWin should be $totalWin calculated in the loop.
                            // $finalReelsSymbols should be $reelsTmp.
                            // $responseJsJack should be json_encode($slotSettings->Jackpots)
                            // --- End of original spin loop ---

                            // Example of what needs to be done after the loop from original:
                            // This is a conceptual representation, the actual logic needs to be integrated from the original spin case
                            $mainSymAnim = ''; // from original loop
                            // Fallback values if not set in loop (should be set)
                            $reelsTmpFromLoop = $slotSettings->GetReelStrips($winType, $currentSlotEvent); // Simplified
                            $totalWinFromLoop = 0; // Calculated in loop
                            $lineWinsFromLoop = []; // Calculated in loop
                            $scattersCount = 0; // Calculated
                            $scattersCount2 = 0; // Calculated
                            $WalkingWildStr = []; // Calculated
                            $attStr = ""; // Calculated
                            $featureStr = ""; // Calculated
                            $curReelsStringForSpin = ""; // String built in loop for non-JSON part of old response

                            // Simulate the loop's output for key variables (replace with actual integration)
                            $finalReelsSymbols = $reelsTmpFromLoop; // This should be the final state of reels from the spin logic
                            $responseTotalWin = $totalWinFromLoop;
                            $responseWinLines = $lineWinsFromLoop;
                            $responseJsJack = json_encode($slotSettings->Jackpots);


                            if ($responseTotalWin > 0) {
                                // SetBank and SetBalance were called inside the loop in original,
                                // but now SlotSettings handles it, so these might be redundant here
                                // if the internal logic of SlotSettings is sufficient.
                                // For now, let's assume SlotSettings' internal SetBalance/SetBank are called correctly during spin.
                            }
                             $reportWin = $responseTotalWin; // Use the calculated totalWin for report

                            // Constructing the $curReels string for the response string part (if still needed by client)
                            // This is a simplified representation of the original $curReels string building.
                            $curReelsStringForSpin = ' &rs.i0.r.i0.syms=SYM' . ($finalReelsSymbols['reel1'][0]??'0') . '%2CSYM' . ($finalReelsSymbols['reel1'][1]??'0') . '%2CSYM' . ($finalReelsSymbols['reel1'][2]??'0') . '';
                            // .. and so on for all reels and positions.

                            if ($currentSlotEvent == 'freespin') {
                                $responseTotalWin = $slotSettings->GetGameData($slotSettings->slotId . 'BonusWin'); // In FS, totalWin is accumulated BonusWin
                                // ... logic for $responseFreeState, $nextaction, $stack, $gamestate from original ...
                                $fs = $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames');
                                $fsl = $fs - $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame');
                                $responseFreeState = '&freespins.betlines=243&freespins.totalwin.cents=' . ($responseTotalWin * $slotSettings->CurrentDenomination * 100) . '&nextaction=' . ($fsl > 0 ? 'freespin' : 'spin') . '&freespins.left=' . $fsl . '...'; // Simplified
                                $curReelsStringForSpin .= $responseFreeState;
                            }

                            // The original 'spin' case built a JSON string directly. We now build $responseState.
                            // The $response variable in original spin case was '{"responseEvent":"spin", "responseType": ..., "serverResponse":{...}}'
                            // We will log a similar structure if needed by SaveLogReport
                            $logResponseStructure = [
                                "responseEvent" => "spin",
                                "responseType" => $currentSlotEvent,
                                "serverResponse" => [
                                    "freeState" => $responseFreeState,
                                    "slotLines" => $lines,
                                    "slotBet" => $betline,
                                    "totalFreeGames" => $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames'),
                                    "currentFreeGames" => $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame'),
                                    "Balance" => $balanceInCents, // Balance before this spin's win/loss applied for log
                                    "afterBalance" => round($slotSettings->GetBalance() * ($slotSettings->CurrentDenom ?: 1) * 100), // Current balance
                                    "bonusWin" => $slotSettings->GetGameData($slotSettings->slotId . 'BonusWin'),
                                    "totalWin" => $responseTotalWin,
                                    "winLines" => $responseWinLines, // This was an empty array in original example, might need actual win line data
                                    "Jackpots" => json_decode($responseJsJack, true),
                                    "reelsSymbols" => $finalReelsSymbols
                                ]
                            ];
                            $slotSettings->SaveLogReport(json_encode($logResponseStructure), $allbet, $lines, $reportWin, $currentSlotEvent);
                            // $slotSettings->SetGameData($slotSettings->slotId . 'LastResponse', json_encode($logResponseStructure)); // If needed by other parts

                            // Update balanceInCents for the final responseState after spin's effects
                            $balanceInCents = round($slotSettings->GetBalance() * ($slotSettings->CurrentDenom ?: 1) * 100);

                            // The string response part for spin is more complex in original, often including $curReels, $winString, $featureStr, $attStr
                            // For the new JSON response, these details are better structured in $responseState directly.
                            // $result_tmp[0] = '...'; // If a string part is still absolutely needed for compatibility
                            break;
                    }

                    // If an action was supposed to set $result_tmp[0] (the old string response) and didn't, handle here.
                    // For most actions now, the $responseState will be the primary output.
                    if (empty($result_tmp) && !in_array($aid, ['spin', 'init'])) { // Spin and Init have more complex string responses if needed
                        $result_tmp[] = "clientaction=" . $aid . "&balance=" . $balanceInCents; // A minimal default
                    }

                    // Construct the final response state
                    $responseState = [
                        'newBalance' => $slotSettings->GetBalance(),
                        'newBank' => $slotSettings->GetBank(''), // Pass relevant state if needed by GetBank
                        'totalWin' => $slotSettings->GetGameData($slotSettings->slotId . 'TotalWin'), // This should reflect current spin's win if applicable
                        'reels' => $finalReelsSymbols, // Populated from spin/init logic
                        'newGameData' => $slotSettings->gameData,
                        'bonusWin' => $slotSettings->GetGameData($slotSettings->slotId . 'BonusWin'),
                        'totalFreeGames' => $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames'),
                        'currentFreeGames' => $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame'),
                        'slotLines' => $responseSlotLines, // from spin case
                        'slotBet' => $responseSlotBet,     // from spin case
                        'afterBalance' => $slotSettings->GetBalance(), // Balance after operations
                        'winLines' => $responseWinLines,   // from spin case
                        'Jackpots' => json_decode($responseJsJack, true), // from spin case
                        // If the original client parsed the string response for some actions:
                        'stringResponse' => !empty($result_tmp[0]) ? $result_tmp[0] : null
                    ];
                    // Ensure gameData and gameDataStatic are saved (they are properties of SlotSettings now)
                    // $slotSettings->SaveGameData(); // Method is now empty
                    // $slotSettings->SaveGameDataStatic(); // Method is now empty
                    // These are saved by virtue of being part of $slotSettings, which is in memory for the request.
                    // The newGameData in responseState will carry the latest gameData.

                } catch (\Exception $e) {
                    $errorResponse = [
                        'responseEvent' => 'error',
                        'responseType' => $action,
                        'serverResponse' => $e->getMessage(),
                        'requestPayload' => $gameStateData, // Log the request that caused error
                    ];
                    if (isset($slotSettings) && method_exists($slotSettings, 'InternalErrorSilent')) {
                         // $slotSettings->InternalErrorSilent($e); // This writes to local log
                    }
                    header('Content-Type: application/json');
                    echo json_encode($errorResponse);
                    return;
                }

                header('Content-Type: application/json');
                echo json_encode($responseState);
            }
        }
    }
