<?php

namespace app\games\NET\Base;

/**
 * A helper class to calculate wins for a slot game.
 * This centralizes the win calculation logic, making the main Server class cleaner.
 */
class WinCalculator
{
    private $slotSettings;
    private $wildSymbol = '2';    // A common identifier for the Wild symbol.
    private $scatterSymbol = '0'; // A common identifier for the Scatter symbol.

    public function __construct(BaseSlotSettings $slotSettings)
    {
        $this->slotSettings = $slotSettings;
    }

    /**
     * Calculates all wins for a given set of reels.
     *
     * @param array $reels The reels layout from GetReelStrips.
     * @param float $betline The bet per line.
     * @return array An array containing totalWin, winLines, and scatterCount.
     */
    public function calculateWins(array $reels, float $betline): array
{
    $totalWin = 0;
    $winLinesResult = [];
    $scatterCount = 0;

    // This is the key change: Ensure '$this->slotSettings->Line' is treated as an array.
    // If it's not set or not an array, use the default paylines.
    $paylines = (isset($this->slotSettings->Line) && is_array($this->slotSettings->Line))
        ? $this->slotSettings->Line
        : $this->get_default_paylines();

    $paytable = $this->slotSettings->Paytable;

    // Calculate line wins
    foreach ($paylines as $lineIndex => $lineCoords) {
        $lineSymbols = [];
        // Important: Payline coordinates are 0-indexed, but our reels are 1-indexed.
        foreach ($lineCoords as $reelIndex => $pos) {
            if (isset($reels['reel' . ($reelIndex + 1)][$pos])) {
                $lineSymbols[] = $reels['reel' . ($reelIndex + 1)][$pos];
            }
        }

        $lineWin = $this->calculateLineWin($lineSymbols, $betline, $paytable);
        if ($lineWin && $lineWin['win'] > 0) {
            $winLinesResult[] = [
                'line' => $lineIndex + 1,
                'symbol' => $lineWin['symbol'],
                'count' => $lineWin['count'],
                'win' => $lineWin['win']
            ];
            $totalWin += $lineWin['win'];
        }
    }
    
    // Count scatters
    foreach ($reels as $reelKey => $reel) {
        if (strpos($reelKey, 'reel') === 0 && is_array($reel)) {
            foreach($reel as $symbol) {
                if ($symbol == $this->scatterSymbol) {
                    $scatterCount++;
                }
            }
        }
    }

    return [
        'totalWin' => $totalWin,
        'winLines' => $winLinesResult,
        'scatterCount' => $scatterCount,
    ];
}

    /**
     * Calculates the win for a single payline.
     */
    private function calculateLineWin(array $lineSymbols, float $betline, array $paytable): ?array
    {
        if (empty($lineSymbols)) {
            return null;
        }

        $firstSymbol = $lineSymbols[0];
        // Determine the symbol that forms the win line (it can't be a wild)
        $winSymbol = $firstSymbol;
        if ($winSymbol == $this->wildSymbol) {
            foreach ($lineSymbols as $symbol) {
                if ($symbol != $this->wildSymbol) {
                    $winSymbol = $symbol;
                    break;
                }
            }
        }
        
        // If the line is all wilds, the win symbol is the wild symbol itself
        if ($winSymbol == $this->wildSymbol) {
             $firstSymbol = $this->wildSymbol;
        }

        // Count consecutive symbols from the left
        $winLength = 0;
        foreach ($lineSymbols as $symbol) {
            if ($symbol == $winSymbol || $symbol == $this->wildSymbol) {
                $winLength++;
            } else {
                break;
            }
        }

        // Check for a win in the paytable (usually 3 or more symbols)
        if ($winLength >= 3) {
            $paytableKey = 'SYM_' . $winSymbol;
            if (isset($paytable[$paytableKey]) && isset($paytable[$paytableKey][$winLength])) {
                $payout = $paytable[$paytableKey][$winLength];
                if ($payout > 0) {
                    return ['win' => $payout * $betline, 'symbol' => (int)$winSymbol, 'count' => $winLength];
                }
            }
        }

        return null;
    }

    /**
     * Provides a default 20-line payline structure if the game doesn't define its own.
     * Assumes a 5-reel game with 3 rows (0, 1, 2).
     */
    private function get_default_paylines(): array
    {
        return [
            [0,0,0,0,0], [1,1,1,1,1], [2,2,2,2,2], [0,1,2,1,0], [2,1,0,1,2],
            [0,0,1,2,2], [2,2,1,0,0], [1,0,0,0,1], [1,2,2,2,1], [0,1,0,1,0],
            [2,1,2,1,2], [1,1,0,1,1], [1,1,2,1,1], [0,1,1,1,0], [2,1,1,1,2],
            [1,0,1,2,1], [1,2,1,0,1], [0,2,0,2,0], [2,0,2,0,2], [0,2,2,2,0]
        ];
    }
}