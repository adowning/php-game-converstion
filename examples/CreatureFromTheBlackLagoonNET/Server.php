<?php

namespace app\games\NET\CreatureFromTheBlackLagoonNET;

set_time_limit(5); // Adjust time limit as needed

class Server
{
    public function handle()
    {
        $jsonState = file_get_contents('php://input');
        $gameStateData = json_decode($jsonState, true);

        $action = $gameStateData['action'] ?? '';
        $postData = $gameStateData['postData'] ?? [];

        $slotSettings = new SlotSettings($gameStateData);

        $responseState = [];
        $stringResponsePart = '';

        $finalReelsSymbols = [];
        $responseTotalWin = 0; // In coins
        $responseSlotLines = 20;
        $responseSlotBet = 1;
        $responseWinLines = [];
        $responseJsJack = json_encode($slotSettings->Jackpots);
        $responseFreeStateString = '';
        $responseBonusWin = 0; // Total win in a bonus round (coins)
        $responseTotalFreeGames = 0;
        $responseCurrentFreeGames = 0;
        $balanceInCents = 0;

        try {
            if (!$slotSettings->is_active()) {
                throw new \Exception('Game is disabled');
            }

            $currentSlotEvent = $postData['slotEvent'] ?? 'bet';
            $aid = $action;

            // Determine action type for cleaner logic
            if ($action == 'freespin') {
                $currentSlotEvent = 'freespin';
                $aid = 'spin';
            } else if ($action == 'respin') {
                $currentSlotEvent = 'respin';
                $aid = 'spin'; // Respin is also a type of spin
            } else if ($action == 'init' || $action == 'reloadbalance') {
                $aid = 'init';
                $currentSlotEvent = 'init';
            } else if ($action == 'paytable') {
                $currentSlotEvent = 'paytable';
            } else if ($action == 'initfreespin') {
                $currentSlotEvent = 'initfreespin';
            }
            $postData['slotEvent'] = $currentSlotEvent;

            $responseSlotBet = $postData['bet_betlevel'] ?? ($slotSettings->GetGameData($slotSettings->slotId . 'Bet') ?? 1);
            if (isset($postData['bet_denomination']) && $postData['bet_denomination'] >= 0.01) { // Denom is float
                $slotSettings->CurrentDenom = $postData['bet_denomination'];
                 $slotSettings->SetGameData($slotSettings->slotId . 'GameDenom', $postData['bet_denomination']);
            } else if ($slotSettings->HasGameData($slotSettings->slotId . 'GameDenom')) {
                 $slotSettings->CurrentDenom = $slotSettings->GetGameData($slotSettings->slotId . 'GameDenom');
            }
            $slotSettings->CurrentDenomination = $slotSettings->CurrentDenom;


            if ($currentSlotEvent == 'bet') {
                $lines = 20;
                $betline = $responseSlotBet;
                if ($lines <= 0 || $betline <= 0.0001) {
                    throw new \Exception('Invalid bet state from client');
                }
                if ($slotSettings->GetBalance() < ($lines * $betline)) {
                    throw new \Exception('Invalid balance for bet');
                }
            }

            $fsLeft = ($slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') ?? 0) - ($slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') ?? 0);
            if ($currentSlotEvent == 'freespin' && $fsLeft <=0) {
                 throw new \Exception('Invalid bonus state: no free spins left.');
            }


            switch ($aid) {
                case 'init':
                    $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETBonusWin', 0);
                    $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETFreeGames', 0);
                    $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETCurrentFreeGame', 0);
                    $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETTotalWin', 0);
                    $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETFreeBalance', 0);
                    $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETMonsterHealth', 0);
                    $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETFreeLevel', 0);

                    $reels = $slotSettings->GetReelStrips('none', $currentSlotEvent);
                    $finalReelsSymbols = $reels;

                    $curReelsString = '&rs.i0.r.i0.syms=SYM' . ($reels['reel1'][0] ?? 0) . '%2CSYM' . ($reels['reel1'][1] ?? 0) . '%2CSYM' . ($reels['reel1'][2] ?? 0);
                    $curReelsString .= ('&rs.i0.r.i1.syms=SYM' . ($reels['reel2'][0] ?? 0) . '%2CSYM' . ($reels['reel2'][1] ?? 0) . '%2CSYM' . ($reels['reel2'][2] ?? 0));
                    $curReelsString .= ('&rs.i0.r.i2.syms=SYM' . ($reels['reel3'][0] ?? 0) . '%2CSYM' . ($reels['reel3'][1] ?? 0) . '%2CSYM' . ($reels['reel3'][2] ?? 0));
                    $curReelsString .= ('&rs.i0.r.i3.syms=SYM' . ($reels['reel4'][0] ?? 0) . '%2CSYM' . ($reels['reel4'][1] ?? 0) . '%2CSYM' . ($reels['reel4'][2] ?? 0));
                    $curReelsString .= ('&rs.i0.r.i4.syms=SYM' . ($reels['reel5'][0] ?? 0) . '%2CSYM' . ($reels['reel5'][1] ?? 0) . '%2CSYM' . ($reels['reel5'][2] ?? 0));
                    $curReelsString .= ('&rs.i0.r.i0.pos=' . ($reels['rp'][0] ?? rand(0,5)));
                    $curReelsString .= ('&rs.i0.r.i1.pos=' . ($reels['rp'][1] ?? rand(0,5)));
                    $curReelsString .= ('&rs.i0.r.i2.pos=' . ($reels['rp'][2] ?? rand(0,5)));
                    $curReelsString .= ('&rs.i0.r.i3.pos=' . ($reels['rp'][3] ?? rand(0,5)));
                    $curReelsString .= ('&rs.i0.r.i4.pos=' . ($reels['rp'][4] ?? rand(0,5)));

                    $denomStrings = [];
                    if(is_array($slotSettings->Denominations)){
                        foreach($slotSettings->Denominations as $denomVal) { $denomStrings[] = $denomVal * 100; }
                    }
                    $balanceInCents = round($slotSettings->GetBalance() * ($slotSettings->CurrentDenom ?: 1) * 100);
                    $stringResponsePart = 'clientaction=init&game.win.cents=0&game.win.coins=0&denomination.all=' . implode('%2C', $denomStrings) . '&credit=' . $balanceInCents . $curReelsString;

                    $responseTotalFreeGames = $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') ?? 0;
                    $responseCurrentFreeGames = $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') ?? 0;
                    $responseBonusWin = $slotSettings->GetGameData($slotSettings->slotId . 'BonusWin') ?? 0;
                    break;

                case 'paytable':
                    $stringResponsePart = 'pt.i0.comp.i19.symbol=SYM9&...'; // Placeholder from original
                    break;

                case 'initfreespin':
                    $finalReelsSymbols = $slotSettings->GetReelStrips('bonus', $currentSlotEvent);
                    $responseTotalFreeGames = $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') ?? 0;
                    $responseCurrentFreeGames = $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') ?? 0;
                    $responseBonusWin = $slotSettings->GetGameData($slotSettings->slotId . 'BonusWin') ?? 0;
                    $balanceInCents = round($slotSettings->GetBalance() * ($slotSettings->CurrentDenom ?: 1) * 100);
                    $stringResponsePart = 'clientaction=initfreespin&credit=' . $balanceInCents . '&nextaction=freespin&freespins.left=' . ($responseTotalFreeGames - $responseCurrentFreeGames) . '&...';
                    break;

                case 'spin':
                    $linesId = [[2,2,2,2,2], [1,1,1,1,1], [3,3,3,3,3], [1,2,3,2,1], [3,2,1,2,3], [1,1,2,1,1], [3,3,2,3,3], [2,3,3,3,2], [2,1,1,1,2], [2,1,2,1,2], [2,3,2,3,2], [1,2,1,2,1], [3,2,3,2,3], [2,2,1,2,2], [2,2,3,2,2], [1,2,2,2,1], [3,2,2,2,3], [1,3,1,3,1], [3,1,3,1,3], [1,3,3,3,1]];
                    $lines = $responseSlotLines;
                    $betline = $responseSlotBet;
                    $allbetCoins = $betline * $lines;
                    $allbetCurrency = $allbetCoins * $slotSettings->CurrentDenom;
                    $bonusMpl = 1;

                    if ($currentSlotEvent != 'freespin' && $currentSlotEvent != 'respin') {
                        $slotSettings->SetBalance(-1 * $allbetCoins, $currentSlotEvent);
                        $bankAmountInCurrency = $allbetCurrency * ($slotSettings->GetPercent() / 100);
                        $slotSettings->SetBank($currentSlotEvent, $bankAmountInCurrency, $currentSlotEvent);
                        $slotSettings->UpdateJackpots($allbetCurrency);

                        $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', 0);
                        $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', 0);
                        $slotSettings->SetGameData($slotSettings->slotId . 'Bet', $betline);
                        $slotSettings->SetGameData($slotSettings->slotId . 'Denom', $slotSettings->CurrentDenom);
                    } else {
                        if($currentSlotEvent == 'freespin'){
                            $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', ($slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') ?? 0) + 1);
                        }
                        $bonusMpl = $slotSettings->slotFreeMpl ?? 1;
                        $betline = $slotSettings->GetGameData($slotSettings->slotId . 'Bet') ?? 1;
                        $slotSettings->CurrentDenom = $slotSettings->GetGameData($slotSettings->slotId . 'Denom') ?? $slotSettings->CurrentDenom;
                        $allbetCoins = $betline * $lines;
                        $responseSlotBet = $betline;
                    }

                    $desiredWinType = $gameStateData['desiredWinType'] ?? 'none';
                    // $slotSettings->AllBet = $allbetCoins; // Set if any internal PHP logic relies on it. Removed as per instructions.

                    $reels = []; $totalWin = 0; $lineWins = [];

                    for ($i = 0; $i <= 500; $i++) {
                        $totalWin = 0; $lineWinsThisLoop = []; $cWins = array_fill(0, $lines, 0);
                        $wild = ['1']; $scatter = '0';
                        $tempReels = $slotSettings->GetReelStrips($desiredWinType, $currentSlotEvent);

                        if ($currentSlotEvent == 'freespin' && rand(1, 5) == 1 && ($slotSettings->GetGameData('CreatureFromTheBlackLagoonNETMonsterHealth') ?? 0) < 10) {
                            $tempReels['reel5'][rand(0, 2)] = 2;
                        }
                        if ($currentSlotEvent == 'respin') {
                            $overlayWildsArrLast = $slotSettings->GetGameData('CreatureFromTheBlackLagoonNEToverlayWildsArr') ?? [];
                            foreach ($overlayWildsArrLast as $wsp) {
                                if(isset($tempReels['reel' . $wsp[0]][$wsp[1]])) $tempReels['reel' . $wsp[0]][$wsp[1]] = 1;
                            }
                        }

                        for ($k = 0; $k < $lines; $k++) {
                            for ($j = 0; $j < count($slotSettings->SymbolGame); $j++) {
                                $csym = (string)$slotSettings->SymbolGame[$j];
                                if ($csym == $scatter || !isset($slotSettings->Paytable['SYM_' . $csym])) continue;
                                $s = [];
                                for($r=0; $r<5; $r++) $s[$r] = $tempReels['reel'.($r+1)][$linesId[$k][$r]-1];
                                $winLength = 0;
                                if (($s[0] == $csym || in_array($s[0], $wild)) && ($s[1] == $csym || in_array($s[1], $wild)) && ($s[2] == $csym || in_array($s[2], $wild))) $winLength = 3;
                                if ($winLength == 3 && ($s[3] == $csym || in_array($s[3], $wild))) $winLength = 4;
                                if ($winLength == 4 && ($s[4] == $csym || in_array($s[4], $wild))) $winLength = 5;

                                if ($winLength >= 3) {
                                    $subWildCount = 0;
                                    for($sw=0; $sw < $winLength; $sw++) if(in_array($s[$sw], $wild)) $subWildCount++;
                                    $mpl = ($subWildCount == $winLength && $csym != '1') ? 1 : ($slotSettings->slotWildMpl ?? 1);
                                    if($csym == '1' && $subWildCount == $winLength) $mpl = 1;
                                    $tmpWin = ($slotSettings->Paytable['SYM_' . $csym][$winLength] ?? 0) * $betline * $mpl * $bonusMpl;
                                    if ($cWins[$k] < $tmpWin) {
                                        $cWins[$k] = $tmpWin;
                                        $winPositions = []; for($p=0; $p<$winLength; $p++) $winPositions[] = $p . ',' . ($linesId[$k][$p]-1);
                                        $lineWinsThisLoop[$k."_".$csym] = ['line' => $k, 'symbol' => 'SYM'.$csym, 'winCoins' => $tmpWin, 'winCents' => $tmpWin*$slotSettings->CurrentDenom*100, 'positions' => implode(';',$winPositions), 'count' => $winLength];
                                    }
                                }
                            }
                            if ($cWins[$k] > 0) $totalWin += $cWins[$k];
                        }
                        $scattersCount = 0; $wildsRespinCount = 0; $overlayWildsArr = []; $isMonsterShoot = false;
                        for ($r = 1; $r <= 5; $r++) { for ($p = 0; $p <= 2; $p++) {
                            if ($tempReels['reel'.$r][$p] == $scatter) $scattersCount++;
                            if ($tempReels['reel'.$r][$p] == 1 && $currentSlotEvent!='respin') {$wildsRespinCount++; $overlayWildsArr[]=[$r,$p];}
                            if ($tempReels['reel'.$r][$p] == 2) $isMonsterShoot = true;
                        }}
                        if (($slotSettings->MaxWin > 0 && ($totalWin * $slotSettings->CurrentDenom) > $slotSettings->MaxWin)) { continue; }

                        $bonusTriggered = $scattersCount >= 3 || $wildsRespinCount >= 1 || ($currentSlotEvent == 'freespin' && $isMonsterShoot);

                        if ($desiredWinType == 'bonus') {
                            if ($bonusTriggered) {
                                $reels = $tempReels; $lineWins = $lineWinsThisLoop; break;
                            }
                        } else if ($desiredWinType == 'win') {
                            if ($totalWin > 0 && !$bonusTriggered) {
                                $reels = $tempReels; $lineWins = $lineWinsThisLoop; break;
                            }
                        } else { // 'none' or any other case
                            if ($totalWin == 0 && !$bonusTriggered) {
                                $reels = $tempReels; $lineWins = $lineWinsThisLoop; break;
                            }
                        }
                        if ($i == 500) {
                            $reels = $tempReels; $lineWins = $lineWinsThisLoop; break;
                        }
                    }
                    if(empty($reels) && isset($tempReels)) { $reels = $tempReels; $lineWins = $lineWinsThisLoop; }

                    $finalReelsSymbols = $reels; $responseTotalWin = $totalWin; $responseWinLines = array_values($lineWins);

                    if ($responseTotalWin > 0 && $currentSlotEvent != 'freespin') {
                        $slotSettings->SetBalance($responseTotalWin);
                        $slotSettings->SetBank($currentSlotEvent, -1 * ($responseTotalWin * $slotSettings->CurrentDenom));
                    }
                    if ($currentSlotEvent == 'freespin' || $currentSlotEvent == 'respin') {
                        $currentBonusWin = ($slotSettings->GetGameData($slotSettings->slotId . 'BonusWin') ?? 0) + $responseTotalWin;
                        $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', $currentBonusWin);
                        $responseBonusWin = $currentBonusWin;
                        if($currentSlotEvent == 'freespin' && $responseTotalWin > 0){
                             $slotSettings->SetBalance($responseTotalWin);
                             $slotSettings->SetBank($currentSlotEvent, -1 * ($responseTotalWin * $slotSettings->CurrentDenom));
                        }
                    } else { $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', $responseTotalWin); }

                    if ($scattersCount >= 3 && $currentSlotEvent != 'freespin' && $currentSlotEvent != 'respin') {
                        $responseTotalFreeGames = $slotSettings->slotFreeCount[$scattersCount] ?? 10;
                        $slotSettings->SetGameData($slotSettings->slotId . 'FreeGames', $responseTotalFreeGames);
                        $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', 0);
                        $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETMonsterHealth', 0);
                        $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETFreeLevel', 0);
                        $slotSettings->SetGameData($slotSettings->slotId . 'FreeBalance', $slotSettings->GetBalance());
                        $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', 0);
                    } else { $responseTotalFreeGames = $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') ?? 0; }
                    $responseCurrentFreeGames = $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') ?? 0;

                    if ($currentSlotEvent == 'freespin' && $isMonsterShoot) {
                        $newHealth = ($slotSettings->GetGameData('CreatureFromTheBlackLagoonNETMonsterHealth') ?? 0) + 1;
                        $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETMonsterHealth', $newHealth);
                        if ($newHealth % 3 == 0 && $newHealth > 0 && $newHealth <=9) {
                           $newLevel = ($slotSettings->GetGameData('CreatureFromTheBlackLagoonNETFreeLevel') ?? 0) + 1;
                           $slotSettings->SetGameData('CreatureFromTheBlackLagoonNETFreeLevel', $newLevel);
                        }
                    }

                    $stringResponsePart = 'credit=' . round($slotSettings->GetBalance() * $slotSettings->CurrentDenom * 100);

                    $logData = [ "responseEvent" => "spin", "responseType" => $currentSlotEvent, "serverResponse" => [ /* ... data ... */ ] ];
                    $slotSettings->SaveLogReport(json_encode($logData), $allbetCoins * $slotSettings->CurrentDenom, $lines, $responseTotalWin * $slotSettings->CurrentDenom, $currentSlotEvent);
                    break;
            }

            $balanceInCents = round($slotSettings->GetBalance() * ($slotSettings->CurrentDenom ?: 1) * 100);

            $responseState = [
                'newBalance' => $slotSettings->GetBalance(),
                'newBalanceCents' => $balanceInCents,
                'newBank' => $slotSettings->GetBank(''),
                'totalWin' => $responseTotalWin,
                'totalWinCents' => $responseTotalWin * ($slotSettings->CurrentDenom ?: 1) * 100,
                'reels' => $finalReelsSymbols,
                'newGameData' => $slotSettings->gameData,
                'bonusWin' => $responseBonusWin,
                'totalFreeGames' => $responseTotalFreeGames,
                'currentFreeGames' => $responseCurrentFreeGames,
                'slotLines' => $responseSlotLines,
                'slotBet' => $responseSlotBet,
                'winLines' => $responseWinLines,
                'Jackpots' => json_decode($responseJsJack, true),
                'stringResponse' => $stringResponsePart,
                'currency' => $slotSettings->currency,
                'denomination' => $slotSettings->CurrentDenom,
                'betLevel' => $responseSlotBet,
                'gameAction' => $aid,
                'slotEvent' => $currentSlotEvent,
            ];

            if ($currentSlotEvent == 'freespin' || $responseTotalFreeGames > 0 || $currentSlotEvent == 'initfreespin' || $currentSlotEvent == 'respin' ) {
                 $responseState['freeSpinState'] = [
                    'totalFreeSpins' => $responseTotalFreeGames,
                    'remainingFreeSpins' => $responseTotalFreeGames - $responseCurrentFreeGames,
                    'currentWinCoins' => $responseBonusWin,
                    'currentWinCents' => $responseBonusWin * ($slotSettings->CurrentDenom ?: 1) * 100,
                    'monsterHealth' => $slotSettings->GetGameData('CreatureFromTheBlackLagoonNETMonsterHealth') ?? 0,
                    'freeLevel' => $slotSettings->GetGameData('CreatureFromTheBlackLagoonNETFreeLevel') ?? 0,
                 ];
                 if ($currentSlotEvent == 'respin') {
                    $responseState['isRespin'] = true;
                 }
            }

        } catch (\Exception $e) {
            $errorResponse = [
                'responseEvent' => 'error',
                'responseType' => $action ?? 'unknown',
                'serverResponse' => $e->getMessage(),
                'errorDetails' => $e->getFile() . ':' . $e->getLine(),
            ];
            if (isset($slotSettings) && method_exists($slotSettings, 'InternalErrorSilent')) {
                 $slotSettings->InternalErrorSilent($e->getMessage() . ' AT ' . $e->getFile() . ':' . $e->getLine());
            }
            header('Content-Type: application/json');
            echo json_encode($errorResponse);
            exit; // Terminate script after error
        }

        header('Content-Type: application/json');
        echo json_encode($responseState);
        exit; // Terminate script after successful response
    }
}
?>
