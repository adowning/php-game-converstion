<?php
namespace app\games\NET\WingsOfRichesNET;

set_time_limit(10); // Increased time limit for potentially complex spin loops

class Server
{
    private $slotSettings;

    public function handle(array $topLevelPayload) // Changed parameter name
    {
        // Instantiation as per instruction 1
        $this->slotSettings = new SlotSettings($topLevelPayload['gameState']);
        $action = $topLevelPayload['action'] ?? 'init'; // Default to 'init' if no action specified
        $postData = $topLevelPayload['postData'] ?? [];

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
                    $lines = $this->slotSettings->gameLine ? count($this->slotSettings->gameLine) : 20;
                    $betline = $postData['bet_betlevel'] ?? 1;

                    if ($currentSlotEvent !== 'freespin') {
                        $allbet = $betline * $lines;
                        if ($this->slotSettings->GetBalance() < $allbet) {
                            throw new \Exception('Not enough balance for the bet.');
                        }
                        $this->slotSettings->SetBalance(-$allbet, 'bet');
                        $bankAmount = $allbet * ($this->slotSettings->GetPercent() / 100);
                        $this->slotSettings->SetBank($bankAmount, 'bet'); // Assuming SetBank takes amount in currency and event type
                    } else {
                        $betline = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'Bet');
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', $this->slotSettings->GetGameData($this->slotSettings->slotId . 'CurrentFreeGame') + 1);
                    }
                    // Ensure $topLevelPayload is used here as per instruction 1.e.
                    $desiredWinType = $topLevelPayload['gameState']['desiredWinType'] ?? 'none';

                    // Initialize response variables to ensure they always exist
                    $responseTotalWin = 0;
                    $responseWinLines = [];
                    $finalReels = [];
                    $scatterCount = 0; // Initialize scatterCount from the chosen spin

                    for ($i = 0; $i <= 500; $i++) {
                        $reels = $this->slotSettings->GetReelStrips($desiredWinType, $currentSlotEvent);
                        // The WinCalculator needs to be available or its logic incorporated.
                        // Assuming WinCalculator is in the Base game directory as per typical structure.
                        $winCalculator = new \app\games\NET\Base\WinCalculator($this->slotSettings);
                        $winResult = $winCalculator->calculateWins($reels, $betline, $this->slotSettings->Paytable, $this->slotSettings->SymbolGame, $this->linesId, '0', '1');


                        $totalWinInLoop = $winResult['totalWin'];
                        $scatterCountInLoop = $winResult['scatterCount']; // scatterCount from current loop iteration

                        // This is the critical logic that guarantees the outcome
                        if ($desiredWinType === 'win' && $totalWinInLoop > 0 && $scatterCountInLoop < 3) {
                            $responseTotalWin = $totalWinInLoop;
                            $responseWinLines = $winResult['winLines'];
                            $finalReels = $reels;
                            $scatterCount = $scatterCountInLoop; // Final scatter count for this spin
                            break;
                        } elseif ($desiredWinType === 'bonus' && $scatterCountInLoop >= 3) {
                            $responseTotalWin = $totalWinInLoop;
                            $responseWinLines = $winResult['winLines'];
                            $finalReels = $reels;
                            $scatterCount = $scatterCountInLoop; // Final scatter count for this spin
                            break;
                        } elseif ($desiredWinType === 'none' && $totalWinInLoop == 0 && $scatterCountInLoop < 3) {
                            $responseTotalWin = 0; // Ensure it's zero for 'none'
                            $responseWinLines = []; // Ensure empty for 'none'
                            $finalReels = $reels;
                            $scatterCount = $scatterCountInLoop; // Final scatter count for this spin
                            break;
                        }

                        // If the loop is about to end, take the last result
                        if ($i == 500) {
                            $responseTotalWin = $totalWinInLoop;
                            $responseWinLines = $winResult['winLines'];
                            $finalReels = $reels;
                            $scatterCount = $scatterCountInLoop; // Final scatter count for this spin
                        }
                    }

                    $totalFreeGames = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeGames') ?? 0;
                    // Scatter check for triggering free spins should only apply if it's NOT already a free spin event
                    if ($scatterCount >= 3 && $currentSlotEvent !== 'freespin') {
                        // scatterCount here is the final one from the chosen spin result
                        $totalFreeGames = $this->slotSettings->slotFreeCount[$scatterCount] ?? 10;
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeGames', $totalFreeGames);
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', 0);
                        // Also reset BonusWin when new FS are triggered
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', 0);
                         $responseState['totalFreeGames'] = (int)$totalFreeGames; // Add to response if triggered now
                    }

                    if ($responseTotalWin > 0) {
                        // Balance update only for main game wins or if FS wins should also update main balance directly
                        // The provided logic implies direct balance update.
                        $this->slotSettings->SetBalance($responseTotalWin);
                        // Bank update for wins (deduct from bank)
                        // $this->slotSettings->SetBank(-$responseTotalWin * $this->slotSettings->CurrentDenom, $currentSlotEvent);
                    }

                    // Accumulate wins during free spins into BonusWin
                    if ($currentSlotEvent === 'freespin' && $responseTotalWin > 0) {
                        $currentBonusWin = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'BonusWin') ?? 0;
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', $currentBonusWin + $responseTotalWin);
                    }

                    // Update the main $responseState array members
                    $responseState['totalWin'] = $responseTotalWin;
                    $responseState['reels'] = $finalReels;
                    $responseState['winLines'] = $responseWinLines;
                    // Other elements like freeSpinState, newBalance will be set/updated later by common code
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
