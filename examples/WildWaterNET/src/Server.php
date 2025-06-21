<?php
namespace app\games\NET\WildWaterNET;

use app\games\NET\WildWaterNET\SlotSettings; // Correct namespace

set_time_limit(10); // Increased time limit slightly for potentially complex spin loops

class Server
{
    public function handle(array $gameStateData)
    {
        // Initialize SlotSettings with the provided game state
        $slotSettings = new SlotSettings($gameStateData);

        // Prepare default response structure
        $responseState = [
            'newBalance' => $slotSettings->GetBalance(),
            'newBalanceCents' => round(($slotSettings->GetBalance() ?? 0) * ($slotSettings->CurrentDenom ?? 0.01) * 100),
            'newBank' => $slotSettings->GetBank(''), // This will be 0 unless bank is part of gameStateData
            'totalWin' => 0, // In coins
            'totalWinCents' => 0,
            'reels' => [],
            'winLines' => [],
            'Jackpots' => $slotSettings->Jackpots ?? [], // From BaseSlotSettings, populated if in gameStateData
            'bonusWin' => 0, // Total win in a bonus round (coins)
            'totalFreeGames' => 0,
            'currentFreeGames' => 0,
            'slotLines' => count($slotSettings->Line ?? [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20]), // Default 20 lines for WildWater
            'slotBet' => 1, // Default bet level
            'currency' => $slotSettings->currency,
            'denomination' => $slotSettings->CurrentDenom,
            'betLevel' => 1,
            'gameAction' => '',
            'slotEvent' => '',
            'stringResponse' => '', // For any legacy string data the client might expect (try to minimize)
            'newGameData' => $slotSettings->gameData, // Return updated gameData
            'newGameDataStatic' => $slotSettings->gameDataStatic, // Return updated gameDataStatic
        ];

        try {
            if (!$slotSettings->is_active()) {
                throw new \Exception('Game is disabled based on gameStateData.');
            }

            $action = $gameStateData['action'] ?? 'init'; // Default to 'init' if no action specified
            $postData = $gameStateData['postData'] ?? []; // Data from client like bet_level, denomination

            $currentSlotEvent = $postData['slotEvent'] ?? 'bet';
            $aid = $action;

            // Normalize action and slotEvent
            if ($action === 'freespin') {
                $currentSlotEvent = 'freespin';
                $aid = 'spin';
            } else if ($action === 'init' || $action === 'reloadbalance') {
                $aid = 'init';
                $currentSlotEvent = 'init';
            } else if ($action === 'paytable') {
                $currentSlotEvent = 'paytable';
                $aid = 'paytable';
            }
            // Ensure slotEvent is correctly set in responseState
            $responseState['gameAction'] = $aid;
            $responseState['slotEvent'] = $currentSlotEvent;


            // Update denomination and bet level from postData if present
            if (isset($postData['bet_denomination']) && is_numeric($postData['bet_denomination']) && $postData['bet_denomination'] >= 0.01) {
                $slotSettings->CurrentDenom = floatval($postData['bet_denomination']);
                $slotSettings->CurrentDenomination = floatval($postData['bet_denomination']);
                $slotSettings->SetGameData($slotSettings->slotId . 'GameDenom', $slotSettings->CurrentDenom);
            } else if ($slotSettings->HasGameData($slotSettings->slotId . 'GameDenom')) {
                $slotSettings->CurrentDenom = $slotSettings->GetGameData($slotSettings->slotId . 'GameDenom');
                $slotSettings->CurrentDenomination = $slotSettings->CurrentDenom;
            }
            $responseState['denomination'] = $slotSettings->CurrentDenom;

            $responseState['slotBet'] = $postData['bet_betlevel'] ?? ($slotSettings->GetGameData($slotSettings->slotId . 'Bet') ?? 1);
            $responseState['betLevel'] = $responseState['slotBet'];


            // Balance checks (only for 'bet' event, not 'freespin' or 'init')
            if ($currentSlotEvent === 'bet') {
                $lines = $responseState['slotLines'];
                $betline = $responseState['slotBet'];
                if ($lines <= 0 || $betline <= 0.0001) {
                    throw new \Exception('Invalid bet state from client data.');
                }
                $allbetCoins = $betline * $lines;
                if ($slotSettings->GetBalance() < $allbetCoins) {
                    throw new \Exception('Insufficient balance for the bet.');
                }
                 // Deduct bet from balance for 'bet' event
                $slotSettings->SetBalance(-1 * $allbetCoins); // newBalance will reflect this
                $responseState['newBalance'] = $slotSettings->GetBalance();
                $responseState['newBalanceCents'] = round($slotSettings->GetBalance() * $slotSettings->CurrentDenom * 100);
            }

            // Free spin state validation
            $fsTotal = $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') ?? 0;
            $fsCurrent = $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') ?? 0;
            if ($currentSlotEvent === 'freespin' && $fsCurrent >= $fsTotal) {
                 throw new \Exception('Invalid bonus state: no free spins left or count mismatch.');
            }

            switch ($aid) {
                case 'init':
                    // Reset persistent game data for a new session if needed, or load existing
                    if(!($gameStateData['persistence']['isRestore'] ?? false)){
                        $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', 0);
                        $slotSettings->SetGameData($slotSettings->slotId . 'FreeGames', 0);
                        $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', 0);
                        $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', 0);
                        // WildWater specific features might need reset
                        $slotSettings->SetGameData($slotSettings->slotId . 'SurfTeamWin', 0);
                        $slotSettings->SetGameData($slotSettings->slotId . 'SurfUpBonus', 0);
                    }

                    $reels = $slotSettings->GetReelStrips('none', 'init'); // Initial reel display
                    $responseState['reels'] = $reels;
                    $responseState['totalFreeGames'] = $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') ?? 0;
                    $responseState['currentFreeGames'] = $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') ?? 0;
                    $responseState['bonusWin'] = $slotSettings->GetGameData($slotSettings->slotId . 'BonusWin') ?? 0;
                    // Add any other init specific data to stringResponse if required by client
                    // $responseState['stringResponse'] = "some_init_string_data...";
                    break;

                case 'paytable':
                    // Paytable data is already in SlotSettings, client usually handles display
                    // $responseState['stringResponse'] = "paytable_data_if_needed_as_string";
                    $responseState['paytable'] = $slotSettings->Paytable; // Send paytable directly
                    break;

                case 'spin':
                    $linesId = []; // WildWater uses 20 fixed lines usually. Line patterns from reference:
                    // For simplicity, assuming line patterns are hardcoded or implicitly handled by client.
                    // If server needs to return specific line patterns for wins, they should be defined here or in SlotSettings.
                    // Example for 20 lines (symbol positions on each reel: 0=top, 1=middle, 2=bottom)
                    // Line 1: 1,1,1,1,1 (middle row) -> $linesId[0]=[1,1,1,1,1] (using 1-based indexing for symbol pos)
                    // For WildWater, win evaluation is often left-to-right on active paylines.
                    // The SlotSettings->Paytable and SymbolGame are primary for win calculation.

                    $lines = $responseState['slotLines'];
                    $betline = $responseState['slotBet'];
                    $allbetCoins = $betline * $lines;
                    $bonusMpl = 1; // Base multiplier

                    if ($currentSlotEvent === 'freespin') {
                        $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', $fsCurrent + 1);
                        $bonusMpl = $slotSettings->slotFreeMpl ?? 1; // Use free spin multiplier
                        // Bet and denom for free spins should be from when FS were triggered
                        $betline = $slotSettings->GetGameData($slotSettings->slotId . 'Bet') ?? $betline;
                        $currentDenom = $slotSettings->GetGameData($slotSettings->slotId . 'Denom') ?? $slotSettings->CurrentDenom;
                        $slotSettings->CurrentDenom = $currentDenom;
                        $slotSettings->CurrentDenomination = $currentDenom;
                        $responseState['slotBet'] = $betline;
                        $responseState['denomination'] = $currentDenom;
                        $allbetCoins = $betline * $lines; // Recalculate if betline/denom changed for FS
                    } else {
                        // For normal spins, ensure these are reset/set
                        $slotSettings->SetGameData($slotSettings->slotId . 'Bet', $betline);
                        $slotSettings->SetGameData($slotSettings->slotId . 'Denom', $slotSettings->CurrentDenom);
                        $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', 0); // Reset bonus win for new spin cycle
                    }

                    // Spin Loop Logic (modeled after reference)
                    $desiredWinType = $gameStateData['desiredWinType'] ?? 'none'; // 'none', 'win', 'bonus'
                    $spinReels = []; $totalWinThisSpin = 0; $lineWinsThisSpin = [];

                    // Max iterations to prevent infinite loops in bad configs
                    for ($i = 0; $i <= 500; $i++) {
                        $totalWinThisSpin = 0; $lineWinsThisLoop = [];
                        // cWins stores win per line to handle cases where multiple symbols could win on one line (e.g. Wilds creating a better win)
                        $cWins = array_fill(0, $lines, 0);

                        // Get a fresh set of reels based on the desired outcome for this iteration
                        $tempReels = $slotSettings->GetReelStrips($desiredWinType, $currentSlotEvent);

                        // --- WildWater Specific Feature Logic during spin ---
                        // Example: Sticky wilds, expanding wilds, reel modifiers would go here
                        // For WildWater, this might involve checking for stacked surfer symbols, applying multipliers, etc.
                        // This part needs to be derived from the original PHP game's logic for WildWater.
                        // For now, we proceed with basic line win calculation.

                        // --- Standard Line Win Calculation ---
                        $wildSymbols = ['1']; // Assuming '1' is the Wild symbol from original Paytable
                        $scatterSymbol = '0'; // Assuming '0' is the Scatter symbol

                        for ($k = 0; $k < $lines; $k++) { // Iterate through each defined payline
                            // Get the symbols on the current payline $k
                            // This requires $linesId to be defined mapping paylines to reel positions.
                            // For WildWater, let's assume $slotSettings->gameLine defines line patterns if needed by server
                            // Or, we assume client handles showing lines and server just calculates wins based on reel outcome.
                            // Let's use a simplified line evaluation for now, assuming standard 5x3 layout.
                            // A proper $linesId structure would be: $linesId[line_index] = [pos_reel1, pos_reel2, pos_reel3, pos_reel4, pos_reel5]
                            // where pos_reelX is 0 (top), 1 (middle), or 2 (bottom).
                            // For now, we'll simulate this by checking all symbols in SymbolGame against the reels.
                            // This is a placeholder for actual line evaluation. A full implementation needs the line definitions.

                            // Simplified win check (needs proper line definitions for full accuracy)
                            foreach ($slotSettings->SymbolGame as $symCheck) {
                                $s = []; // Symbols on the current (conceptual) line for symCheck
                                for($r=0; $r<5; $r++) {
                                    // This is a placeholder: needs actual line $k mapping to reel positions
                                    // Assuming line $k checks middle row for simplicity of this placeholder:
                                    $s[$r] = $tempReels['reel'.($r+1)][1]; // Middle symbol of each reel
                                }

                                $winLength = 0;
                                // Left to Right Check
                                if ($s[0] == $symCheck || in_array($s[0], $wildSymbols)) {
                                    if ($s[1] == $symCheck || in_array($s[1], $wildSymbols)) {
                                        if ($s[2] == $symCheck || in_array($s[2], $wildSymbols)) {
                                            $winLength = 3;
                                            if ($s[3] == $symCheck || in_array($s[3], $wildSymbols)) {
                                                $winLength = 4;
                                                if ($s[4] == $symCheck || in_array($s[4], $wildSymbols)) {
                                                    $winLength = 5;
                                                }
                                            }
                                        }
                                    }
                                }

                                if ($winLength >= 3) {
                                    $payAmount = $slotSettings->Paytable['SYM_'.$symCheck][$winLength] ?? 0;
                                    $currentWinOnLine = $payAmount * $betline * $bonusMpl;
                                     // Apply WildWater specific multipliers if any (e.g. from stacked symbols)
                                    // This is where logic for "Surf Up Bonus" (multiplier on surfer wins) or "Surf Team Bonus" (big win for 5 mixed surfers) would go.

                                    if ($cWins[$k] < $currentWinOnLine) {
                                        $cWins[$k] = $currentWinOnLine;
                                        // Store details of this winning line
                                        $winPositions = []; // Collect actual reel positions for this win
                                        // Example: $winPositions[] = "0,".$linesId[$k][0]; for reel 1
                                        $lineWinsThisLoop[$k."_".$symCheck] = [
                                            'line' => $k,
                                            'symbol' => 'SYM_'.$symCheck,
                                            'winCoins' => $currentWinOnLine,
                                            'winCents' => $currentWinOnLine * $slotSettings->CurrentDenom * 100,
                                            'positions' => implode(';', $winPositions), // e.g., "0,1;1,1;2,1"
                                            'count' => $winLength
                                        ];
                                    }
                                }
                            } // End symbol check for line k
                            if ($cWins[$k] > 0) $totalWinThisSpin += $cWins[$k];
                        } // End payline iteration

                        // --- Scatter Check for Free Spins ---
                        $scattersCount = 0;
                        $scatterPositionsOnReels = [];
                        for ($r = 1; $r <= 5; $r++) {
                            for ($p = 0; $p <= 2; $p++) { // Check all 3 positions on the reel
                                if ($tempReels['reel'.$r][$p] == $scatterSymbol) {
                                    $scattersCount++;
                                    $scatterPositionsOnReels[] = ($r-1).",".$p; // Store as "reelIndex,symbolIndex"
                                }
                            }
                        }

                        // --- WildWater Specific Bonus Triggers ---
                        // Surf Up Bonus: Any win with surfer symbols might get a random multiplier (x2 to x20 in original)
                        // This would modify $totalWinThisSpin or individual line wins.
                        // Surf Team Bonus: If all 5 reels contain any fully stacked surfer symbol, a large bonus (200x bet) is awarded.
                        // This check would need to analyze $tempReels for stacked surfers.

                        $bonusTriggeredThisSpin = ($scattersCount >= 3 && $currentSlotEvent !== 'freespin');

                        // Check if spin outcome matches desired type
                        $maxWinCoins = ($slotSettings->MaxWin > 0) ? $slotSettings->MaxWin / $slotSettings->CurrentDenom : -1;
                        if ($maxWinCoins != -1 && $totalWinThisSpin > $maxWinCoins) {
                            continue; // Exceeds max win, try again
                        }

                        if ($desiredWinType === 'bonus') {
                            if ($bonusTriggeredThisSpin) { $spinReels = $tempReels; $lineWinsThisSpin = $lineWinsThisLoop; break; }
                        } else if ($desiredWinType === 'win') {
                            if ($totalWinThisSpin > 0 && !$bonusTriggeredThisSpin) { $spinReels = $tempReels; $lineWinsThisSpin = $lineWinsThisLoop; break; }
                        } else { // 'none' or any other case
                            if ($totalWinThisSpin == 0 && !$bonusTriggeredThisSpin) { $spinReels = $tempReels; $lineWinsThisSpin = $lineWinsThisLoop; break; }
                        }
                        if ($i == 500) { // Max iterations reached, take current result
                            $spinReels = $tempReels; $lineWinsThisSpin = $lineWinsThisLoop; break;
                        }
                    } // End of spin loop

                    if(empty($spinReels)) { // Should not happen if loop breaks correctly
                        $spinReels = $slotSettings->GetReelStrips('none', $currentSlotEvent);
                    }

                    $responseState['reels'] = $spinReels;
                    $responseState['totalWin'] = $totalWinThisSpin;
                    $responseState['totalWinCents'] = $totalWinThisSpin * $slotSettings->CurrentDenom * 100;
                    $responseState['winLines'] = array_values($lineWinsThisSpin);

                    // Update balance with win
                    if ($totalWinThisSpin > 0) {
                        $slotSettings->SetBalance($totalWinThisSpin);
                    }

                    // Handle Free Spins Award
                    if ($scattersCount >= 3 && $currentSlotEvent !== 'freespin') {
                        $awardedFS = $slotSettings->slotFreeCount[$scattersCount] ?? 0;
                        if ($awardedFS > 0) {
                            $slotSettings->SetGameData($slotSettings->slotId . 'FreeGames', $awardedFS);
                            $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', 0);
                            // Store current bet/denom for FS, balance at FS start
                            $slotSettings->SetGameData($slotSettings->slotId . 'Bet', $betline);
                            $slotSettings->SetGameData($slotSettings->slotId . 'Denom', $slotSettings->CurrentDenom);
                            $slotSettings->SetGameData($slotSettings->slotId . 'FreeBalance', $slotSettings->GetBalance()); // Balance before this spin's win
                            $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', $totalWinThisSpin); // Initial win that triggered FS
                        }
                    }

                    // Update overall bonus win if in freespin mode
                    if ($currentSlotEvent === 'freespin') {
                        $currentOverallBonusWin = ($slotSettings->GetGameData($slotSettings->slotId . 'BonusWin') ?? 0) + $totalWinThisSpin;
                        $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', $currentOverallBonusWin);
                        $responseState['bonusWin'] = $currentOverallBonusWin;
                    } else {
                        // For normal spins, totalWin is just totalWinThisSpin
                        $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', $totalWinThisSpin);
                    }

                    $responseState['totalFreeGames'] = $slotSettings->GetGameData($slotSettings->slotId . 'FreeGames') ?? 0;
                    $responseState['currentFreeGames'] = $slotSettings->GetGameData($slotSettings->slotId . 'CurrentFreeGame') ?? 0;

                    // Check for WildWater specific bonus payouts (Surf Team, Surf Up) and add to totalWinThisSpin if applicable
                    // This needs detailed logic from the original game.
                    // Example: if (surf_team_condition_met($spinReels)) { $surfTeamWin = 200 * $allbetCoins; $totalWinThisSpin += $surfTeamWin; /* add to winlines */ }
                    // Example: if (surf_up_condition_met($lineWinsThisSpin)) { $multiplier = rand(2,20); $totalWinThisSpin = apply_multiplier($totalWinThisSpin, $multiplier); /* update winlines */ }
                    // After these, re-update balance and responseState['totalWin'] if they changed.

                    break; // End of 'spin' case
            }

            // Final updates to responseState
            $responseState['newBalance'] = $slotSettings->GetBalance();
            $responseState['newBalanceCents'] = round($slotSettings->GetBalance() * $slotSettings->CurrentDenom * 100);
            $responseState['newGameData'] = $slotSettings->gameData;
            $responseState['newGameDataStatic'] = $slotSettings->gameDataStatic;

            // Add free spin specific state if applicable
            if ($responseState['totalFreeGames'] > 0 || $currentSlotEvent === 'freespin') {
                $fsLeft = $responseState['totalFreeGames'] - $responseState['currentFreeGames'];
                $responseState['freeSpinState'] = [
                    'totalFreeSpins' => $responseState['totalFreeGames'],
                    'remainingFreeSpins' => max(0, $fsLeft), // Cannot be negative
                    'currentWinCoins' => $responseState['bonusWin'], // Cumulative win in FS mode
                    'currentWinCents' => round($responseState['bonusWin'] * $slotSettings->CurrentDenom * 100),
                    // Add any WildWater specific FS data, e.g. current multipliers
                ];
                if ($fsLeft <= 0 && $currentSlotEvent === 'freespin') {
                     $responseState['slotEvent'] = 'freespin_end'; // Or similar indicator
                }
            }

        } catch (\Exception $e) {
            // Log the error server-side if possible
            // error_log("WildWaterNET Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

            // Return a structured error response
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'details' => "Action: " . ($action ?? 'unknown') . ", File: " . basename($e->getFile()) . ", Line: " . $e->getLine(),
                'originalGameState' => $gameStateData, // For debugging
                'currentBalanceSnapshot' => $slotSettings->GetBalance() ?? $gameStateData['user']['balance'] ?? null
            ];
        }

        return $responseState;
    }
}
?>
