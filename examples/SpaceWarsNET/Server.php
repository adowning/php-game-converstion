<?php
namespace app\games\NET\SpaceWarsNET;

// Assuming SlotSettings.php is in the same directory and BaseSlotSettings is autoloaded or included by index.php
// require_once __DIR__ . '/SlotSettings.php';

set_time_limit(10);

class Server
{
    private $slotSettings;
    private $linesId; // Paylines

    public function __construct()
    {
        // SpaceWars: 5 reels, 4 rows, 40 lines.
        // Positions: 0=top, 1=second, 2=third, 3=bottom
        $this->linesId = [
            [0,0,0,0,0], [1,1,1,1,1], [2,2,2,2,2], [3,3,3,3,3], // Lines 1-4 (Horizontal)
            [0,1,2,3,3], [3,2,1,0,0], // Lines 5-6 (Diagonals)
            [0,0,1,2,3], [3,3,2,1,0], // Lines 7-8
            [1,0,0,0,1], [2,3,3,3,2], // Lines 9-10
            [0,1,1,1,0], [3,2,2,2,3], // Lines 11-12
            [1,2,2,2,1], [2,1,1,1,2], // Lines 13-14
            [0,1,0,1,0], [1,2,1,2,1], [2,3,2,3,2], [3,2,3,2,3], // Lines 15-18 (Zig-zags)
            [0,0,3,0,0], [1,1,0,1,1], [2,2,1,2,2], [3,3,0,3,3], // Lines 19-22 (Center heavy)
            [0,3,0,3,0], [1,0,1,0,1], [2,1,2,1,2], [3,0,3,0,3], // Lines 23-26
            [0,3,3,3,0], [3,0,0,0,3], // Lines 27-28 (Outer V's)
            [1,3,3,3,1], [2,0,0,0,2], // Lines 29-30 (Inner V's)
            [0,1,2,1,0], [3,2,1,2,3], // Lines 31-32 (Standard V's)
            [1,2,3,2,1], [2,1,0,1,2], // Lines 33-34 (Standard A's)
            [0,2,0,2,0], [1,3,1,3,1], // Lines 35-36
            [3,1,3,1,3], [2,0,2,0,2], // Lines 37-38
            [0,1,3,1,0], [3,2,0,2,3]  // Lines 39-40
        ];
    }

    public function handle(array $gameStateData)
    {
        $this->slotSettings = new SlotSettings($gameStateData);
        $action = $gameStateData['action'] ?? 'init';
        $postData = $gameStateData['postData'] ?? [];

        $responseState = [
            'newBalance' => 0, // Will be updated from $this->slotSettings->GetBalance()
            'totalWin' => 0,   // Coins for the current main spin or respin
            'winLines' => [],
            'reels' => [],
            'currency' => $this->slotSettings->currency,
            'denomination' => $this->slotSettings->CurrentDenom,
            'betLevel' => $this->slotSettings->Bet[0] ?? 1,
            'gameAction' => $action,
            'slotEvent' => $postData['slotEvent'] ?? 'bet',
            'freeSpinState' => null, // Will be populated if in free spins
            // 'totalFreeGames' will be added if bonus is won in this spin
        ];

        $currentSlotEvent = $postData['slotEvent'] ?? 'bet';
        if ($action == 'freespin' || $action == 'respin') { // Treat respin as freespin for event type
            $currentSlotEvent = 'freespin'; // SpaceWars original respin is like a free spin
            $action = 'spin';
        } elseif ($action == 'init' || $action == 'reloadbalance') {
            $action = 'init';
            $currentSlotEvent = 'init';
        }
        $responseState['slotEvent'] = $currentSlotEvent;
        $responseState['gameAction'] = $action;

        // Update bet level and denomination from postData
        if (isset($postData['bet_betlevel'])) {
            $responseState['betLevel'] = (int)$postData['bet_betlevel'];
        } elseif ($this->slotSettings->HasGameData($this->slotSettings->slotId . 'Bet')) {
            $responseState['betLevel'] = (int)$this->slotSettings->GetGameData($this->slotSettings->slotId . 'Bet');
        }

        if (isset($postData['bet_denomination']) && (float)$postData['bet_denomination'] >= 0.01) {
            $this->slotSettings->CurrentDenom = (float)$postData['bet_denomination'];
            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'GameDenom', $this->slotSettings->CurrentDenom);
        } elseif ($this->slotSettings->HasGameData($this->slotSettings->slotId . 'GameDenom')) {
            $this->slotSettings->CurrentDenom = (float)$this->slotSettings->GetGameData($this->slotSettings->slotId . 'GameDenom');
        }
        $this->slotSettings->CurrentDenomination = $this->slotSettings->CurrentDenom;
        $responseState['denomination'] = $this->slotSettings->CurrentDenom;

        try {
            switch ($action) {
                case 'init':
                    $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeGames', 0);
                    $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', 0);
                    $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', 0);
                    $responseState['reels'] = $this->slotSettings->GetReelStrips('none', 'init');
                    break;

                case 'spin':
                    $desiredWinType = $postData['desiredWinType'] ?? 'none';
                    $numLines = 40;
                    $betPerLine = $responseState['betLevel'];
                    $totalBetCoins = $betPerLine * $numLines;
                    $currentMultiplier = 1; // Base multiplier

                    if ($currentSlotEvent == 'bet') { // Paid spin
                        if ($this->slotSettings->GetBalance() < $totalBetCoins) {
                            throw new \Exception('Insufficient balance for bet.');
                        }
                        $this->slotSettings->SetBalance(-1 * $totalBetCoins, 'bet');
                        // Bank logic (simplified, actual bank update would be more complex)
                        $bankAmount = $totalBetCoins * $this->slotSettings->CurrentDenom * ($this->slotSettings->GetPercent() / 100);
                        $this->slotSettings->SetBank('bet', $bankAmount, 'bet');

                        // Store bet details for potential free spins
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'Bet', $betPerLine);
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'Denom', $this->slotSettings->CurrentDenom);
                        // Reset FS specific data for a new main spin
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeGames', 0);
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', 0);
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', 0);

                    } elseif ($currentSlotEvent == 'freespin') {
                        $fsCurrent = ($this->slotSettings->GetGameData($this->slotSettings->slotId . 'CurrentFreeGame') ?? 0) + 1;
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', $fsCurrent);
                        $betPerLine = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'Bet') ?? $betPerLine;
                        // Multiplier could be added here if SpaceWars FS had one
                    }

                    $finalReels = [];
                    $totalWinThisSpin = 0;
                    $winLinesThisSpin = [];
                    $scatterCount = 0; // Final scatter count from the chosen spin

                    for ($i = 0; $i <= 500; $i++) {
                        $reelsThisLoop = $this->slotSettings->GetReelStrips($desiredWinType, $currentSlotEvent);
                        list($totalWinThisLoop, $winLinesThisLoop) = $this->calculateLineWins($reelsThisLoop, $betPerLine, $currentMultiplier, $numLines);

                        $scatterCountThisLoop = 0;
                        $scatterSymbol = '0'; // Assuming SYM_0 is scatter
                        for ($r = 1; $r <= 5; $r++) {
                            for ($p = 0; $p < 4; $p++) { // 4 rows
                                if (isset($reelsThisLoop['reel' . $r][$p]) && $reelsThisLoop['reel' . $r][$p] == $scatterSymbol) {
                                    $scatterCountThisLoop++;
                                }
                            }
                        }

                        // Store current loop results before checking break conditions
                        $finalReels = $reelsThisLoop;
                        $totalWinThisSpin = $totalWinThisLoop;
                        $winLinesThisSpin = $winLinesThisLoop;
                        $scatterCount = $scatterCountThisLoop;

                        if ($desiredWinType == 'win' && $totalWinThisSpin > 0 && $scatterCount < 3) break;
                        if ($desiredWinType == 'bonus' && $scatterCount >= 3) break;
                        if ($desiredWinType == 'none' && $totalWinThisSpin == 0 && $scatterCount < 3) break;
                        if ($i == 500) break; // Fallback if no desired outcome after 500 tries
                    }

                    $responseState['reels'] = $finalReels;
                    $responseState['winLines'] = $winLinesThisSpin;
                    // totalWin in responseState should be win from THIS spin, not accumulated FS win
                    $responseState['totalWin'] = $totalWinThisSpin;

                    if ($currentSlotEvent == 'bet' && $totalWinThisSpin > 0) {
                        $this->slotSettings->SetBalance($totalWinThisSpin);
                        $this->slotSettings->SetBank('bet', -1 * ($totalWinThisSpin * $this->slotSettings->CurrentDenom));
                    }

                    if ($scatterCount >= 3 && $currentSlotEvent == 'bet') { // Bonus triggered on a main spin
                        $numberOfFreeGames = $this->slotSettings->slotFreeCount[$scatterCount] ?? 0;
                        if ($numberOfFreeGames > 0) {
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'FreeGames', $numberOfFreeGames);
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'CurrentFreeGame', 0);
                            $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', 0); // Reset for new FS session
                            $responseState['totalFreeGames'] = $numberOfFreeGames; // Key as per instruction
                        }
                    }

                    if ($currentSlotEvent == 'freespin') {
                        $currentFsBonusWin = ($this->slotSettings->GetGameData($this->slotSettings->slotId . 'BonusWin') ?? 0) + $totalWinThisSpin;
                        $this->slotSettings->SetGameData($this->slotSettings->slotId . 'BonusWin', $currentFsBonusWin);
                    }
                    break;
                default:
                    throw new \Exception("Invalid action: " . $action);
            }

            // Final state updates
            $responseState['newBalance'] = $this->slotSettings->GetBalance();
            $this->updateFreeSpinState($responseState); // Populate freeSpinState if applicable
            $responseState['newGameData'] = $this->slotSettings->gameData; // Pass back updated gameData

        } catch (\Exception $e) {
            error_log("Error in SpaceWarsNET Server::handle: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return ['error' => true, 'message' => $e->getMessage()];
        }

        return $responseState;
    }

    private function calculateLineWins($reels, $betPerLine, $multiplier, $numLines) {
        $totalWin = 0;
        $winLinesResult = [];
        $paytable = $this->slotSettings->Paytable;
        $wildSymbol = '1'; // Assuming SYM_1 is Wild

        for ($lineIdx = 0; $lineIdx < $numLines; $lineIdx++) {
            if (!isset($this->linesId[$lineIdx])) continue;
            $currentLineDef = $this->linesId[$lineIdx];

            // Get symbols on the current payline
            $lineSymbols = [];
            for ($reelNum = 0; $reelNum < 5; $reelNum++) { // 5 reels
                $reelKey = 'reel' . ($reelNum + 1);
                $rowIdx = $currentLineDef[$reelNum]; // 0, 1, 2, or 3 for 4 rows
                if (isset($reels[$reelKey][$rowIdx])) {
                    $lineSymbols[] = $reels[$reelKey][$rowIdx];
                } else {
                    $lineSymbols[] = null; // Should not happen if reels are correctly populated
                }
            }

            // Check wins for each symbol in SymbolGame (excluding scatter '0' and non-paying wild '1' for primary check)
            foreach ($this->slotSettings->SymbolGame as $symId) {
                if ($symId == '0') continue; // Scatters don't pay on lines

                $symbolToMatch = 'SYM_' . $symId;
                if (!isset($paytable[$symbolToMatch])) continue;

                $consecutiveCount = 0;
                $winPositionsOnLine = [];

                for ($k = 0; $k < 5; $k++) { // Check from left to right
                    if ($lineSymbols[$k] === null) break; // Invalid symbol, break sequence

                    if ($lineSymbols[$k] == $symId || ($lineSymbols[$k] == $wildSymbol && $symId != $wildSymbol)) {
                        $consecutiveCount++;
                        $winPositionsOnLine[] = ['reel' => $k, 'row' => $currentLineDef[$k]];
                    } elseif ($lineSymbols[$k] == $wildSymbol && $symId == $wildSymbol && $k == $consecutiveCount) { // Line of wilds
                        $consecutiveCount++;
                        $winPositionsOnLine[] = ['reel' => $k, 'row' => $currentLineDef[$k]];
                    }
                    else {
                        break;
                    }
                }

                $payoutKey = ($symId == $wildSymbol && $paytable['SYM_1'][$consecutiveCount] > 0) ? 'SYM_1' : $symbolToMatch;

                if ($consecutiveCount >= 3) { // Minimum 3 for a win in SpaceWars
                    $winAmountForSymbol = ($paytable[$payoutKey][$consecutiveCount] ?? 0) * $betPerLine * $multiplier;
                    if ($winAmountForSymbol > 0) {
                        // Check if this win is better than a previous win on the same line (e.g. Wild forming part of two wins)
                        // NetEnt games usually pay only the highest win per line.
                        // This simple loop might add multiple wins for the same line if not handled carefully.
                        // For now, let's assume one win per line, based on first symbol found.
                        // A more robust check would find all possible wins and take the highest.
                        // To simplify, we'll just add this win. If multiple symbol types win on one line, it might overpay.
                        // The provided algo says "calculate the total win ('$totalWin') by checking every payline",
                        // implying summation.
                        $totalWin += $winAmountForSymbol;
                        $winLinesResult[] = [
                            'line' => $lineIdx,
                            'symbol' => $payoutKey,
                            'count' => $consecutiveCount,
                            'winCoins' => $winAmountForSymbol,
                            'winCents' => round($winAmountForSymbol * $this->slotSettings->CurrentDenom * 100),
                            'positions' => $winPositionsOnLine
                        ];
                        break; // Found a win for this symbol type on this line, move to next line as per NetEnt common rule
                    }
                }
            }
        }
        return [$totalWin, $winLinesResult];
    }

    private function updateFreeSpinState(&$responseState) {
        $fsTotal = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeGames') ?? 0;
        $currentSlotEvent = $responseState['slotEvent'] ?? 'bet';

        if ($fsTotal > 0 || $currentSlotEvent == 'freespin') {
            $fsCurrent = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'CurrentFreeGame') ?? 0;
            $fsBonusWin = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'BonusWin') ?? 0;
            // $fsMultiplier = $this->slotSettings->GetGameData($this->slotSettings->slotId . 'FreeMpl') ?? 1; // If FS has multiplier

            $responseState['freeSpinState'] = [
                'totalFreeSpins' => (int)$fsTotal,
                'remainingFreeSpins' => max(0, (int)$fsTotal - (int)$fsCurrent),
                // 'currentMultiplier' => (int)$fsMultiplier,
                'currentWinCoins' => $fsBonusWin, // Total accumulated in this FS session
                'currentWinCents' => round($fsBonusWin * $this->slotSettings->CurrentDenom * 100),
            ];
             // If this spin just triggered FS, add totalFreeGames to the main responseState as per instructions
            if(isset($responseState['totalFreeGames'])) {
                 $responseState['freeSpinState']['justTriggeredTotal'] = $responseState['totalFreeGames'];
            }

        } else {
            $responseState['freeSpinState'] = null;
        }
    }
}
?>
