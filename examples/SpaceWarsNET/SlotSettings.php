<?php
namespace app\games\NET\SpaceWarsNET;

use app\games\NET\Base\BaseSlotSettings;
use app\games\NET\SpaceWarsNET\GameReel; // Will be created next

class SlotSettings extends BaseSlotSettings
{
    // Public Balance property as required by new instructions
    public $Balance;

    public function __construct($gameStateData)
    {
        parent::__construct($gameStateData);

        // Initialize public Balance property from $gameStateData['balance']
        // BaseSlotSettings already initializes its own Balance property from $gameStateData['player']['balance']
        // The instruction "The public 'Balance' property MUST be correctly initialized from '$gameStateData['balance']'"
        // might imply a direct top-level 'balance' key in gameStateData, or that this class's specific Balance
        // property needs to be set. Assuming it's for this class's own public $Balance if it's meant to be distinct
        // from the one in BaseSlotSettings, or if $gameStateData has a top-level 'balance'.
        // If it's the same as parent's, this line might be redundant or need adjustment based on exact $gameStateData structure.
        // For now, ensuring this class's public $Balance is set.
        $this->Balance = $gameStateData['balance'] ?? ($this->Balance ?? 0); // Prioritize direct, fallback to parent's

        // SpaceWarsNET Specific Paytable
        // SYM_0 will be our Scatter, as per new scatter-based free spin requirement.
        // SYM_1 is Wild (non-paying on its own as per original)
        $this->Paytable = [
            'SYM_0' => [0,0,0,0,0,0],      // Scatter symbol (newly defined for scatter pays/triggers)
            'SYM_1' => [0,0,0,0,0,0],      // Wild symbol
            'SYM_2' => [0,0,0,30,250,1000], // Red Crystal Alien
            'SYM_3' => [0,0,0,20,125,400],  // Orange Alien
            'SYM_4' => [0,0,0,15,75,200],   // Yellow Alien
            'SYM_5' => [0,0,0,10,60,175],   // Green Alien
            'SYM_6' => [0,0,0,10,50,150],   // Blue Alien
            // Small aliens
            'SYM_7' => [0,0,0,10,40,125],   // Small Red
            'SYM_8' => [0,0,0,4,20,60],    // Small Orange
            'SYM_9' => [0,0,0,4,20,50],    // Small Yellow
            'SYM_10' => [0,0,0,3,15,40],   // Small Green
            'SYM_11' => [0,0,0,2,15,40],   // Small Blue (Original SYM_11)
            'SYM_12' => [0,0,0,2,15,40]    // Small Purple (Original SYM_12) - Note: Same payout as SYM_11 in original
        ];

        // SpaceWarsNET Specific SymbolGame
        // Includes SYM_0 (Scatter) and SYM_1 (Wild) if they appear on reels.
        // Original SymbolGame was [2,3,4,5,6,7,8,9,10,11,12]
        // Assuming SYM_0 and SYM_1 will be on reels.
        $this->SymbolGame = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

        // slotFreeCount for scatter-triggered free spins (new requirement)
        // Assuming 3 scatters = 10 FS, 4 = 15 FS, 5 = 20 FS. This can be adjusted.
        // Index is scatter count.
        $this->slotFreeCount = [
            0,  // 0 scatters
            0,  // 1 scatter
            0,  // 2 scatters
            10, // 3 scatters
            15, // 4 scatters
            20  // 5 scatters
        ];
        $this->slotFreeMpl = $gameStateData['game']['slotFreeMpl'] ?? 1; // Default Free Spin Multiplier

        // SpaceWarsNET GameReel instantiation (5 reels, 4 rows visible - handled by Server display logic)
        $reel = new GameReel();
        // Assuming GameReel.php populates $reel->reelsStrip['reelStripX']
        foreach (['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5'] as $reelStripKey) {
            if (isset($reel->reelsStrip[$reelStripKey]) && count($reel->reelsStrip[$reelStripKey])) {
                $this->$reelStripKey = $reel->reelsStrip[$reelStripKey];
            } else {
                $this->$reelStripKey = []; // Ensure property exists
            }
        }
        // SpaceWarsNET doesn't seem to have distinct bonus reels in original, respin was on main reels.
        // If scatter free spins need different reels, GameReel.php and reels.txt would define them.

        // Other game-specific settings from original, adapted or using BaseSlotSettings defaults
        $this->slotBonusType = $gameStateData['game']['slotBonusType'] ?? 1; // Original: 1 (but slotBonus was false)
        $this->slotScatterType = $gameStateData['game']['slotScatterType'] ?? 0; // Original: 0. We are using SYM_0 as scatter.
        $this->slotBonus = $gameStateData['game']['slotBonus'] ?? true; // Enabling for scatter free spins
        $this->slotGamble = $gameStateData['game']['slotGamble'] ?? false; // Original was true, but SpaceWars usually no gamble
        $this->slotWildMpl = $gameStateData['game']['slotWildMpl'] ?? 1;

        // Denominations and Bets are handled by BaseSlotSettings from $gameStateData

        // slotReelsConfig might be needed if visual representation is part of this layer
        $this->slotReelsConfig = $gameStateData['game']['slotReelsConfig'] ?? [
            // Typical 5-reel config, values might need adjustment for 4 rows if used for display calcs
            [425,142,4], [669,142,4], [913,142,4], [1157,142,4], [1401,142,4]
        ];
    }

    // GetReelStrips - crucial for Server.php's spin loop
    // This version is simplified; original SpaceWars had a complex respin mechanic
    // with symbol cloning. New instructions imply standard reel generation.
    public function GetReelStrips($winType, $slotEvent)
    {
        // $winType can be 'bonus' (for scatter hunting), 'win', or 'none'.
        // $slotEvent can be 'bet', 'freespin'.

        $prs = []; // Reel positions to stop at (index of the first visible symbol)
        $numReels = 5;

        if ($winType == 'bonus') {
            // Try to land 3+ scatters (SYM_0)
            $scatterSymbol = '0';
            $scattersToPlace = rand(3, $numReels); // Aim for 3 to 5 scatters
            $placedOnReel = array_fill(1, $numReels, false);

            // Distribute scatters randomly across reels
            $reelsWithScatters = (array)array_rand(array_flip(range(1, $numReels)), $scattersToPlace);

            for ($r = 1; $r <= $numReels; $r++) {
                $reelStripName = 'reelStrip' . $r;
                $strip = $this->$reelStripName ?? [];
                if (empty($strip)) { $prs[$r] = 0; continue; }

                if (in_array($r, $reelsWithScatters)) {
                    $scatterPositionsOnStrip = array_keys($strip, $scatterSymbol);
                    if (!empty($scatterPositionsOnStrip)) {
                        $prs[$r] = $scatterPositionsOnStrip[array_rand($scatterPositionsOnStrip)];
                    } else { // Scatter not on this strip, place randomly or try to avoid if possible
                        $prs[$r] = mt_rand(0, count($strip) - 1);
                    }
                } else { // No scatter on this reel
                    $nonScatterPositions = [];
                    foreach($strip as $pos => $sym) { if($sym != $scatterSymbol) $nonScatterPositions[] = $pos; }
                    if(!empty($nonScatterPositions)){
                         $prs[$r] = $nonScatterPositions[array_rand($nonScatterPositions)];
                    } else {
                         $prs[$r] = mt_rand(0, count($strip) - 1);
                    }
                }
            }
        } else { // For 'win' or 'none', generate random positions
            for ($r = 1; $r <= $numReels; $r++) {
                $reelStripName = 'reelStrip' . $r;
                $strip = $this->$reelStripName ?? [];
                if (empty($strip) || count($strip) < 4) { // Need at least 4 symbols for 4 rows
                    $prs[$r] = 0;
                } else {
                     // Position so that 4 symbols are visible. Stop on $prs[$r], then $prs[$r]+1, $prs[$r]+2, $prs[$r]+3
                    $prs[$r] = mt_rand(0, count($strip) - 4);
                }
            }
        }

        $reelsOutput = ['rp' => []];
        for ($r = 1; $r <= $numReels; $r++) {
            $reelStripName = 'reelStrip' . $r;
            $strip = $this->$reelStripName ?? [];
            $pos = $prs[$r] ?? 0;
            $cnt = count($strip);

            if ($cnt > 0) {
                // Helper to safely get symbols, wrapping around the reel strip
                $safe_get = function($arr, $idx, $count) {
                    $actual_idx = $idx % $count;
                    if ($actual_idx < 0) $actual_idx += $count;
                    return $arr[$actual_idx];
                };

                // SpaceWars is 5 reels, 4 rows.
                $reelsOutput['reel' . $r][0] = $safe_get($strip, $pos, $cnt);
                $reelsOutput['reel' . $r][1] = $safe_get($strip, $pos + 1, $cnt);
                $reelsOutput['reel' . $r][2] = $safe_get($strip, $pos + 2, $cnt);
                $reelsOutput['reel' . $r][3] = $safe_get($strip, $pos + 3, $cnt);
                // $reelsOutput['reel' . $r][4] = ''; // Original Server had a 5th element, usually empty for display
                $reelsOutput['rp'][] = $pos;
            } else {
                $reelsOutput['reel' . $r] = ['', '', '', '']; // 4 empty symbols for 4 rows
                $reelsOutput['rp'][] = 0;
            }
        }
        return $reelsOutput;
    }
}
?>
