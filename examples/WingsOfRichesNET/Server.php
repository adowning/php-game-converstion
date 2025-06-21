<?php
namespace app\games\NET\WingsOfRichesNET;

set_time_limit(10); // Increased time limit for potentially complex spin loops

class Server
{
    private $slotSettings;

    public function handle(array $gameStateData)
    {
        $this->slotSettings = new SlotSettings($gameStateData);
        $action = $gameStateData['action'] ?? 'init'; // Default to 'init' if no action specified
        $postData = $gameStateData['postData'] ?? [];

        // Initialize response structure
        $responseState = [
            'newBalance' => $this->slotSettings->GetBalance(),
            'newBalanceCents' => round($this->slotSettings->GetBalance() * $this->slotSettings->CurrentDenom * 100),
            'newBank' => $this->slotSettings->GetBank(''), // Assuming GetBank('') gets total bank
            'totalWin' => 0, // In coins
            'totalWinCents' => 0,
            'reels' => [],
            'winLines' => [],
            'Jackpots' => $this->slotSettings->Jackpots, // From BaseSlotSettings, populated if gameStateData has it
            'currency' => $this->slotSettings->currency,
            'denomination' => $this->slotSettings->CurrentDenom,
            'betLevel' => $this->slotSettings->Bet[0] ?? 1, // Default bet level
            'gameAction' => $action,
            'slotEvent' => $postData['slotEvent'] ?? 'bet',
            'freeSpinState' => null,
            'isRespin' => false, // Wings of Riches doesn't seem to have respins like Creature
            'newGameData' => [] // To pass back any persistent state changes if needed (though aiming for stateless)
        ];

        $currentSlotEvent = $postData['slotEvent'] ?? 'bet';
        if ($action == 'freespin') {
            $currentSlotEvent = 'freespin';
            $action = 'spin'; // Freespin is a type of spin
        } elseif ($action == 'init' || $action == 'reloadbalance') {
            $action = 'init';
            $currentSlotEvent = 'init';
        } elseif ($action == 'paytable') {
            $currentSlotEvent = 'paytable';
        } elseif ($action == 'initfreespin') { // Client might request this to set up UI for FS
            $action = 'initfreespin';
            $currentSlotEvent = 'initfreespin';
        }
        $responseState['slotEvent'] = $currentSlotEvent;
        $responseState['gameAction'] = $action;


        // Update settings from postData if available
        if (isset($postData['bet_betlevel'])) {
            $responseState['betLevel'] = (int)$postData['bet_betlevel'];
        } else if ($this->slotSettings->HasGameData($this->slotSettings->slotId . 'Bet')) {
            $responseState['betLevel'] = (int)$this->slotSettings->GetGameData($this->slotSettings->slotId . 'Bet');
        }

        if (isset($postData['bet_denomination']) && (float)$postData['bet_denomination'] >= 0.01) {
            $this->slotSettings->CurrentDenom = (float)$postData['bet_denomination'];
            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'GameDenom', $this->slotSettings->CurrentDenom);
        } elseif ($this->slotSettings->HasGameData($this->slotSettings->slotId . 'GameDenom')) {
            $this->slotSettings->CurrentDenom = (float)$this->slotSettings->GetGameData($this->slotSettings->slotId . 'GameDenom');
        }
        $this->slotSettings->CurrentDenomination = $this->slotSettings->CurrentDenom; // Ensure this is also updated
        $responseState['denomination'] = $this->slotSettings->CurrentDenom;


        // Balance checks (simplified, assuming index.php might pre-validate or gameStateData is trusted)
        if ($currentSlotEvent == 'bet') {
            $lines = 20; // Wings of Riches has 20 fixed lines
            $betLine = $responseState['betLevel'];
            if ($lines <= 0 || $betLine <= 0) {
                throw new \Exception('Invalid bet state.');
            }
            if ($this->slotSettings->GetBalance() < ($lines * $betLine)) {
                 // This should ideally be caught by the caller or handled gracefully
                 // For now, we'll let it proceed and SlotSettings::SetBalance will manage if it goes negative
                 // Or, throw new \Exception('Insufficient balance for bet.');
            }
        }

        $fsTotal = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeGames') ?? 0;
        $fsCurrent = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'CurrentFreeGame') ?? 0;
        if ($currentSlotEvent == 'freespin' && ($fsTotal - $fsCurrent <= 0)) {
            throw new \Exception('Invalid bonus state: no free spins left.');
        }


        try {
            switch ($action) {
                case 'init':
                    // Reset relevant game data for a new session or on init
                    $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', 0);
                    $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeGames', 0);
                    $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', 0);
                    $this->slotSettings->SetGameData($this->slotSettings->slotId . 'TotalWin', 0);
                    $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeMpl', 1); // Initial FS multiplier

                    $reels = $this->slotSettings->GetReelStrips('none', 'init');
                    $responseState['reels'] = $reels;
                    $responseState['totalWin'] = 0;
                    $responseState['totalWinCents'] = 0;
                    // Include initial free spin state if any pending (e.g. from a restored game state)
                    $this->updateFreeSpinState($responseState);
                    break;

                case 'paytable':
                    // Paytable data is mostly in SlotSettings.
                    // Client usually fetches this as static content or it's built into client.
                    // If specific string response needed, it would be constructed here.
                    // For now, Server doesn't output specific paytable strings.
                    break;

                case 'initfreespin':
                    // This action is for client to prepare for FS.
                    // Server should return current FS state.
                    $reels = $this->slotSettings->GetReelStrips('bonus', 'initfreespin'); // Show scatter-rich reels
                    $responseState['reels'] = $reels;
                    $this->updateFreeSpinState($responseState);
                    break;

                case 'spin':
                    $lines = 20; // Fixed lines
                    $betLine = $responseState['betLevel'];
                    $allBetCoins = $betLine * $lines;
                    $allBetCurrency = $allBetCoins * $this->slotSettings->CurrentDenom;
                    $currentMultiplier = 1; // Base multiplier

                    if ($currentSlotEvent != 'freespin') {
                        $this->slotSettings->SetBalance(-1 * $allBetCoins, $currentSlotEvent);
                        $bankAmountInCurrency = $allBetCurrency * ($this->slotSettings->GetPercent() / 100);
                        $this->slotSettings->SetBank($currentSlotEvent, $bankAmountInCurrency, $currentSlotEvent);
                        // UpdateJackpots is part of BaseSlotSettings, called if enabled
                        // $this->slotSettings->UpdateJackpots($allBetCurrency);

                        // Reset for normal spin
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', 0);
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'TotalWin', 0);
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeMpl', 1);
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'Bet', $betLine);
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'Denom', $this->slotSettings->CurrentDenom);
                        $currentMultiplier = 1;
                    } else { // Freespin
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', $fsCurrent + 1);
                        $currentMultiplier = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeMpl') ?? 1;
                        $betLine = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'Bet') ?? $betLine;
                        $this->slotSettings->CurrentDenom = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'Denom') ?? $this->slotSettings->CurrentDenom;
                        $allBetCoins = $betLine * $lines; // Recalculate allBetCoins for FS context
                    }

                    $desiredWinType = $postData['desiredWinType'] ?? 'none'; // 'none', 'win', 'bonus'

                    $finalReels = [];
                    $totalWinThisSpin = 0;
                    $winLinesThisSpin = [];
                    $scattersCount = 0;
                    $dandelionLanded = false; // SYM_2 on reel 5
                    $isSpreadingWildTriggered = false;
                    $spreadingWildDetails = []; // To store info for response if needed

                    for ($i = 0; $i <= 500; $i++) { // Spin loop
                        $totalWinThisSpin = 0;
                        $winLinesThisLoop = [];
                        $scattersCount = 0;
                        $dandelionLanded = false;
                        $isSpreadingWildTriggered = false;
                        $spreadingWildDetailsLoop = [];

                        $reels = $this->slotSettings->GetReelStrips($desiredWinType, $currentSlotEvent);
                        $reelsAfterSpread = $this->applySpreadingWilds($reels, $spreadingWildDetailsLoop, $currentSlotEvent);
                        if(!empty($spreadingWildDetailsLoop)) $isSpreadingWildTriggered = true;

                        // Check for Dandelion (SYM_2) on reel 5 during Free Spins
                        if ($currentSlotEvent == 'freespin') {
                            if ($reelsAfterSpread['reel5'][0] == '2' || $reelsAfterSpread['reel5'][1] == '2' || $reelsAfterSpread['reel5'][2] == '2') {
                                $dandelionLanded = true;
                            }
                        }

                        // Calculate line wins
                        list($totalWinThisSpin, $winLinesThisLoop) = $this->calculateLineWins($reelsAfterSpread, $betLine, $currentMultiplier, $lines);

                        // Count scatters (SYM_0)
                        for ($r = 1; $r <= 5; $r++) {
                            for ($p = 0; $p <= 2; $p++) {
                                if ($reelsAfterSpread['reel' . $r][$p] == '0') {
                                    $scattersCount++;
                                }
                            }
                        }

                        $bonusTriggeredThisLoop = ($scattersCount >= 3);
                        $maxWinCheck = $this->slotSettings->MaxWin > 0 && ($totalWinThisSpin * $this->slotSettings->CurrentDenom) > $this->slotSettings->MaxWin;

                        if ($maxWinCheck) continue;

                        if ($desiredWinType == 'bonus') {
                            if ($bonusTriggeredThisLoop) { $finalReels = $reelsAfterSpread; $winLinesThisSpin = $winLinesThisLoop; $spreadingWildDetails = $spreadingWildDetailsLoop; break; }
                        } elseif ($desiredWinType == 'win') {
                            if ($totalWinThisSpin > 0 && !$bonusTriggeredThisLoop) { $finalReels = $reelsAfterSpread; $winLinesThisSpin = $winLinesThisLoop; $spreadingWildDetails = $spreadingWildDetailsLoop; break; }
                        } else { // 'none'
                            if ($totalWinThisSpin == 0 && !$bonusTriggeredThisLoop) { $finalReels = $reelsAfterSpread; $winLinesThisSpin = $winLinesThisLoop; $spreadingWildDetails = $spreadingWildDetailsLoop; break; }
                        }
                        if ($i == 500) { // Fallback if no desired outcome met
                            $finalReels = $reelsAfterSpread; $winLinesThisSpin = $winLinesThisLoop; $spreadingWildDetails = $spreadingWildDetailsLoop; break;
                        }
                    }
                    if(empty($finalReels)) $finalReels = $reelsAfterSpread; // Ensure finalReels is set

                    $responseState['reels'] = $finalReels;
                    $responseState['totalWin'] = $totalWinThisSpin;
                    $responseState['totalWinCents'] = round($totalWinThisSpin * $this->slotSettings->CurrentDenom * 100);
                    $responseState['winLines'] = $winLinesThisSpin;
                    if($isSpreadingWildTriggered) $responseState['spreadingWilds'] = $spreadingWildDetails;


                    if ($totalWinThisSpin > 0) {
                        // For normal spins, SetBalance is called. For FS, it's accumulated to FS total.
                        if ($currentSlotEvent != 'freespin') {
                            $this->slotSettings->SetBalance($totalWinThisSpin);
                            $this->slotSettings->SetBank($currentSlotEvent, -1 * ($totalWinThisSpin * $this->slotSettings->CurrentDenom));
                        }
                    }

                    // Handle Free Spin state updates
                    if ($currentSlotEvent == 'freespin') {
                        $currentFsBonusWin = ($this->slotSettings->GetGameData($this->slotSettings->slotId . 'BonusWin') ?? 0) + $totalWinThisSpin;
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', $currentFsBonusWin);
                        if ($dandelionLanded) {
                            $newMultiplier = ($this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeMpl') ?? 1) + 1;
                            // Max multiplier from original logic seems to be x5
                            $newMultiplier = min($newMultiplier, 5);
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeMpl', $newMultiplier);
                        }
                         // If free spins retrigger with scatters during FS (original game awards +10 spins)
                        if ($scattersCount >=3) {
                            $fsTotal = ($this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeGames') ?? 0) + 10; // Add 10 more spins
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeGames', $fsTotal);
                            $responseState['freeSpinsRetriggered'] = 10;
                        }

                    } else { // Normal spin
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'TotalWin', $totalWinThisSpin);
                        if ($scattersCount >= 3) {
                            $initialFreeSpins = $this->slotSettings->slotFreeCount[$scattersCount] ?? 10;
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeGames', $initialFreeSpins);
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', 0);
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeMpl', 1); // Reset multiplier for new FS session
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', 0); // Reset bonus win for new FS
                            // $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeBalance', $this->slotSettings->GetBalance());
                        }
                    }
                    $this->updateFreeSpinState($responseState);
                    // Log after all updates
                    // $logBet = ($currentSlotEvent == 'freespin') ? 0 : $allBetCurrency;
                    // $this->slotSettings->SaveLogReport(json_encode($responseState), $logBet, $lines, $totalWinThisSpin * $this->slotSettings->CurrentDenom, $currentSlotEvent);
                    break;
                default:
                    throw new \Exception("Invalid action: " . $action);
            }

        } catch (\Exception $e) {
            // In a real scenario, you'd log this exception
             error_log("Error in Server::handle for WingsOfRichesNET: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // And return an error structure
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // For debugging, remove for production
            ];
        }

        $responseState['newBalance'] = $this->slotSettings->GetBalance();
        $responseState['newBalanceCents'] = round($this->slotSettings->GetBalance() * $this->slotSettings->CurrentDenom * 100);
        $responseState['newBank'] = $this->slotSettings->GetBank('');
        $responseState['newGameData'] = $this->slotSettings->gameData; // Pass back updated gameData

        return $responseState;
    }

    private function calculateLineWins($reels, $betLine, $multiplier, $numLines) {
        $totalWin = 0;
        $winLines = [];
        // Payline definitions (Positions: 0=top, 1=middle, 2=bottom)
        // Reels are 1-indexed in $reels['reelX'], symbols are 0-indexed in $reels['reelX'][0/1/2]
        $linesId = [
            [1,1,1,1,1], [0,0,0,0,0], [2,2,2,2,2],     // Lines 1,2,3 (NETENT common)
            [0,1,2,1,0], [2,1,0,1,2],                 // Lines 4,5
            [0,0,1,0,0], [2,2,1,2,2],                 // Lines 6,7 (variant)
            [1,2,2,2,1], [1,0,0,0,1],                 // Lines 8,9
            [1,0,1,0,1], [1,2,1,2,1],                 // Lines 10,11
            [0,1,0,1,0], [2,1,2,1,2],                 // Lines 12,13
            [1,1,0,1,1], [1,1,2,1,1],                 // Lines 14,15
            [0,1,1,1,0], [2,1,1,1,2],                 // Lines 16,17
            [0,2,0,2,0], [2,0,2,0,2],                 // Lines 18,19 (variant)
            [0,0,2,0,0]                               // Line 20 (variant)
        ];
        // Original game had line IDs 0-19. Above are 20 lines.

        $wildSymbol = '1';
        $scatterSymbol = '0'; // Scatter (SYM_0) doesn't pay on lines

        for ($lineIdx = 0; $lineIdx < $numLines; $lineIdx++) {
            $currentLine = $linesId[$lineIdx];
            $lineSymbols = [];
            for ($reelIdx = 0; $reelIdx < 5; $reelIdx++) {
                $lineSymbols[] = $reels['reel' . ($reelIdx + 1)][$currentLine[$reelIdx]];
            }

            $paytable = $this->slotSettings->Paytable;
            foreach ($this->slotSettings->SymbolGame as $symId) {
                if ($symId == $scatterSymbol) continue; // Scatters don't win on lines

                $symbolToMatch = 'SYM_' . $symId;
                if (!isset($paytable[$symbolToMatch])) continue;

                $consecutiveCount = 0;
                $winPositionsOnLine = []; // For highlighting winning symbols

                for ($k = 0; $k < 5; $k++) {
                    if ($lineSymbols[$k] == $symId || ($lineSymbols[$k] == $wildSymbol && $symId != $wildSymbol) ) { // Wild substitutes for non-wilds
                        $consecutiveCount++;
                        $winPositionsOnLine[] = ['reel' => $k, 'row' => $currentLine[$k]];
                    } elseif ($lineSymbols[$k] == $wildSymbol && $symId == $wildSymbol && $k==$consecutiveCount) { // Line of wilds
                         $consecutiveCount++;
                         $winPositionsOnLine[] = ['reel' => $k, 'row' => $currentLine[$k]];
                    }
                    else {
                        break; // Symbol sequence broken
                    }
                }

                // Wilds only pay as wilds if it's a line of wilds.
                // If wilds substitute, they take the value of the substituted symbol.
                // If $symId is Wild ('1'), payouts are from Paytable['SYM_1']
                $payoutKey = ($symId == $wildSymbol) ? 'SYM_1' : $symbolToMatch;


                if ($consecutiveCount >= 3) { // Minimum 3 for a win
                    $winAmount = $paytable[$payoutKey][$consecutiveCount] * $betLine * $multiplier;
                    if ($winAmount > 0) {
                        $totalWin += $winAmount;
                        $winLines[] = [
                            'line' => $lineIdx,
                            'symbol' => $payoutKey,
                            'count' => $consecutiveCount,
                            'winCoins' => $winAmount,
                            'winCents' => round($winAmount * $this->slotSettings->CurrentDenom * 100),
                            'positions' => $winPositionsOnLine
                        ];
                    }
                }
            }
        }
        return [$totalWin, $winLines];
    }

    private function applySpreadingWilds($reels, &$spreadingWildDetails, $slotEvent) {
        // Only apply spreading wilds in main game and free spins, not during init type events.
        if ($slotEvent == 'init' || $slotEvent == 'paytable' || $slotEvent == 'initfreespin') {
            return $reels;
        }

        $newReels = $reels; // Start with a copy
        $wildSymbol = '1';
        $dandelionSymbol = '2'; // Dandelion symbol for FS multiplier

        for ($r = 1; $r <= 5; $r++) {
            for ($p = 0; $p <= 2; $p++) {
                if ($reels['reel' . $r][$p] == $wildSymbol) { // Found an original Wild
                    $spreadCount = rand(2, 4); // Original game spreads 2-4 wilds
                    $potentialSpreads = [];

                    // Define adjacent positions (relative to current wild at [r,p])
                    // (reelOffset, rowOffset)
                    $adjacents = [
                        [-1, -1], [-1, 0], [-1, 1], // Left column
                        [0, -1],           [0, 1],  // Same column (above/below)
                        [1, -1], [1, 0], [1, 1]   // Right column
                    ];
                    shuffle($adjacents);

                    $actualSpreads = 0;
                    foreach ($adjacents as $offset) {
                        if ($actualSpreads >= $spreadCount) break;

                        $nr = $r + $offset[0]; // new reel index
                        $np = $p + $offset[1]; // new row index

                        // Check bounds for reel and row
                        if ($nr >= 1 && $nr <= 5 && $np >= 0 && $np <= 2) {
                            // Don't spread onto an existing Wild or Scatter ('0') or Dandelion ('2')
                            if ($newReels['reel' . $nr][$np] != $wildSymbol &&
                                $newReels['reel' . $nr][$np] != '0' &&
                                $newReels['reel' . $nr][$np] != $dandelionSymbol) {

                                $spreadingWildDetails[] = ['from_reel' => $r-1, 'from_row' => $p, 'to_reel' => $nr-1, 'to_row' => $np, 'symbol' => $wildSymbol];
                                $newReels['reel' . $nr][$np] = $wildSymbol; // Convert to Wild
                                $actualSpreads++;
                            }
                        }
                    }
                }
            }
        }
        return $newReels;
    }

    private function updateFreeSpinState(&$responseState) {
        $fsTotal = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeGames') ?? 0;
        if ($fsTotal > 0 || ($responseState['slotEvent'] ?? '') == 'freespin' || ($responseState['gameAction'] ?? '') == 'initfreespin') {
            $fsCurrent = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'CurrentFreeGame') ?? 0;
            $fsBonusWin = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'BonusWin') ?? 0;
            $fsMultiplier = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeMpl') ?? 1;

            $responseState['freeSpinState'] = [
                'totalFreeSpins' => $fsTotal,
                'remainingFreeSpins' => max(0, $fsTotal - $fsCurrent),
                'currentMultiplier' => $fsMultiplier,
                'currentWinCoins' => $fsBonusWin, // Total accumulated in this FS session
                'currentWinCents' => round($fsBonusWin * $this->slotSettings->CurrentDenom * 100),
            ];
        } else {
            $responseState['freeSpinState'] = null;
        }
    }
}
?>
