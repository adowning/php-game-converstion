<?php
namespace app\games\NET\WildWaterNET;

use app\games\NET\Base\BaseSlotSettings;
use app\games\NET\WildWaterNET\GameReel;

class SlotSettings extends BaseSlotSettings
{
    public function __construct($gameStateData)
    {
        parent::__construct($gameStateData);

        // WildWaterNET Specific Paytable from unfinished/WildWaterNET/SlotSettings.php
        $this->Paytable = [
            'SYM_0' => [0,0,0,0,0,0], // Scatter in original, handle separately if needed for wins
            'SYM_1' => [0,0,0,0,0,0], // Wild in original
            'SYM_2' => [0,0,0,40,400,2000], // Gold Surfer (Highest Paying) - Mapped from SYM_2 (200 in original, assuming x10 for NET style)
            'SYM_3' => [0,0,0,15,75,500],   // Red Surfer
            'SYM_4' => [0,0,0,10,40,250],   // Yellow Surfer
            'SYM_5' => [0,0,0,5,30,100],    // Green Surfer
            'SYM_6' => [0,0,0,4,20,75],     // Blue Surfer
            'SYM_7' => [0,0,0,4,20,75]      // Guitar (was SYM_7, assuming it's a non-surfer symbol)
        ];
        // Note: Original WildWaterNET had SYM_2 as 2000 for 5oak. Creature had SYM_3 (top) as 750.
        // I've kept SYM_2 as the top symbol for WildWater. If SYM_0 was a paying scatter, that logic needs to be added to Server.php.

        // WildWaterNET Specific SymbolGame
        // Based on original: '0', '2', '3', '4', '5', '6', '7'. '1' is Wild.
        // For pay calculations, we usually list non-scatter, non-wild symbols that form lines.
        // If '0' (scatter) or '1' (wild) also form their own paylines (e.g. 5 wilds = X coins), they should be in Paytable and SymbolGame.
        // Assuming '1' (Wild) can substitute but doesn't form its own specific payline like "5 Wilds pay X".
        // Assuming '0' (Scatter) triggers features but doesn't have its own line pay.
        $this->SymbolGame = $gameStateData['game']['SymbolGame'] ?? ['2', '3', '4', '5', '6', '7'];


        // WildWaterNET Specific slotFreeCount from original
        $this->slotFreeCount = $gameStateData['game']['slotFreeCount'] ?? [0,0,0,15,30,60]; // For 3, 4, 5 scatters

        // WildWaterNET GameReel instantiation and population (5 reels)
        // The original GameReel loads 6 strips, but slotReelsConfig only defines 5 reels.
        // We will assume 5 reels for WildWaterNET based on slotReelsConfig and typical NETENT style.
        $reel = new GameReel(); // GameReel will be created in a later step
        $reelStripsToProcess = ['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5'];
        foreach ($reelStripsToProcess as $reelStripName) {
            if (isset($reel->reelsStrip[$reelStripName]) && count($reel->reelsStrip[$reelStripName]) > 0) {
                $this->$reelStripName = $reel->reelsStrip[$reelStripName];
            } else {
                 // Initialize with empty array if not found, to prevent property not existing errors
                $this->$reelStripName = [];
            }
        }
        // Bonus reels if they exist in WildWaterNET's GameReel.php (assuming 5 bonus reels if applicable)
        // Original WildWaterNET GameReel did not differentiate bonus strips in the same way as Creature.
        // We will assume base game reels are used for free spins unless GameReel provides specific bonus strips.
        $reelStripBonusToProcess = ['reelStripBonus1', 'reelStripBonus2', 'reelStripBonus3', 'reelStripBonus4', 'reelStripBonus5'];
         foreach ($reelStripBonusToProcess as $reelStripBonusName) {
            if (isset($reel->reelsStripBonus[$reelStripBonusName]) && count($reel->reelsStripBonus[$reelStripBonusName]) > 0) {
                $this->$reelStripBonusName = $reel->reelsStripBonus[$reelStripBonusName];
            } else {
                // If bonus strips are not distinct, they might fallback to regular strips or be handled by game logic.
                // For now, initialize as empty or potentially copy from regular strips if that's the game's behavior.
                $correspondingRegularStrip = str_replace('Bonus', '', $reelStripBonusName);
                if (isset($this->$correspondingRegularStrip)) {
                    $this->$reelStripBonusName = $this->$correspondingRegularStrip;
                } else {
                    $this->$reelStripBonusName = [];
                }
            }
        }


        // WildWaterNET specific keyController (from original)
        $this->keyController = $gameStateData['game']['keyController'] ?? [
            '13' => 'uiButtonSpin,uiButtonSkip', '49' => 'uiButtonInfo', '50' => 'uiButtonCollect',
            '51' => 'uiButtonExit2', '52' => 'uiButtonLinesMinus', '53' => 'uiButtonLinesPlus',
            '54' => 'uiButtonBetMinus', '55' => 'uiButtonBetPlus', '56' => 'uiButtonGamble',
            '57' => 'uiButtonRed', '48' => 'uiButtonBlack', '189' => 'uiButtonAuto', '187' => 'uiButtonSpin'
        ];

        // WildWaterNET specific slotReelsConfig (from original, 5 reels)
        $this->slotReelsConfig = $gameStateData['game']['slotReelsConfig'] ?? [
            [425, 142, 3], [669, 142, 3], [913, 142, 3], [1157, 142, 3], [1401, 142, 3]
        ];

        // Specific settings from original WildWaterNET SlotSettings
        $this->slotBonusType = $gameStateData['game']['slotBonusType'] ?? 1; // Original value
        $this->slotScatterType = $gameStateData['game']['slotScatterType'] ?? 0; // Original value
        $this->slotWildMpl = $gameStateData['game']['slotWildMpl'] ?? 1; // Original value
        $this->slotFreeMpl = $gameStateData['game']['slotFreeMpl'] ?? 1; // Original value

        // Lines are fixed at 20 for WildWaterNET, as per typical NETENT style for this game type.
        // Original had dynamic lines up to 15, but reference Creature has fixed lines.
        // The server logic will use 20 lines.
        $this->Line = $gameStateData['game']['lines'] ?? [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20];
        $this->gameLine = $this->Line;

        // Bet levels are usually 1-10 for NETENT. Original Bet array was different.
        // Will be overridden by gameStateData['game']['bet_values'] via parent constructor.
        // If fixed bet array is needed: $this->Bet = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        // MaxWin, Denominations, CurrentDenom, Balance, Currency are handled by parent constructor from $gameStateData.
    }

    // Retain WildWaterNET-specific methods if they are purely computational and don't rely on external state/DB.
    // Most methods from original SlotSettings.php were stateful or DB-dependent.
    // GetSpinSettings, GetGambleSettings, GetReelStrips will be simplified here or logic moved to Server.php

    public function CheckBonusWin()
    {
        // This method calculated an average payout. For stateless, this might not be directly used
        // or would need to be re-evaluated based on available data in $gameStateData if needed for some logic.
        // For now, returning a default or simplified calculation.
        $allRateCnt = 0;
        $allRate = 0;
        foreach ($this->Paytable as $symbol => $pays) {
            if ($symbol == 'SYM_0' || $symbol == 'SYM_1') continue; // Skip scatter/wild for this avg calc
            foreach ($pays as $pay) {
                if ($pay > 0) {
                    $allRateCnt++;
                    $allRate += $pay;
                    break;
                }
            }
        }
        return $allRateCnt > 0 ? $allRate / $allRateCnt : 0;
    }

    public function GetRandomPay()
    {
        // This method was used for RTP control in the original, which is complex and stateful.
        // In a stateless system, such RTP control needs to be re-architected if still required,
        // possibly by passing more detailed bank/RTP info in gameStateData.
        // For now, returning a random pay from paytable or 0.
        $allRate = [];
        foreach ($this->Paytable as $symbol => $pays) {
             if ($symbol == 'SYM_0' || $symbol == 'SYM_1') continue;
            foreach ($pays as $payValue) {
                if ($payValue > 0) $allRate[] = $payValue;
            }
        }
        if(empty($allRate)) return 0;
        shuffle($allRate);
        return $allRate[0] ?? 0;
    }

    // GetReelStrips: This crucial method generates reel positions.
    // It needs to be stateless. $winType will come from Server's spin loop.
    // $slotEvent ('bet', 'freespin') also from Server.
    public function GetReelStrips($winType, $slotEvent)
    {
        // WildWaterNET uses 5 reels.
        $prs = []; // Positions for Reels
        $reelStripsToUse = [];

        // Determine which set of reel strips to use (normal or bonus)
        // For WildWater, bonus reels might be the same as normal reels or defined in GameReel.
        // Assuming GameReel class handles providing the correct strips.
        $currentReelSet = ($slotEvent == 'freespin' && !empty($this->reelStripBonus1)) ? 'reelStripBonus' : 'reelStrip';

        for ($i = 1; $i <= 5; $i++) {
            $reelStripName = $currentReelSet . $i;
            $reelStripsToUse[$i] = $this->$reelStripName;
        }

        if ($winType != 'bonus') { // Not specifically aiming for a bonus trigger
            for ($i = 1; $i <= 5; $i++) {
                if (is_array($reelStripsToUse[$i]) && count($reelStripsToUse[$i]) > 2) {
                    $prs[$i] = mt_rand(0, count($reelStripsToUse[$i]) - 3); // -3 for 3 visible symbols
                } else {
                    $prs[$i] = 0; // Fallback if reel strip is too short
                }
            }
        } else { // Aiming for a bonus trigger (3+ scatters 'SYM_0')
            $reelsId = [1, 2, 3, 4, 5];
            $scatterSymbol = '0'; // As defined in original game (SYM_0)

            // Get random positions for non-scatter reels first
            for ($i = 1; $i <= 5; $i++) {
                 if (is_array($reelStripsToUse[$i]) && count($reelStripsToUse[$i]) > 2) {
                    $prs[$i] = mt_rand(0, count($reelStripsToUse[$i]) - 3);
                } else {
                    $prs[$i] = 0;
                }
            }

            // Force scatters onto reels
            $scatterPositions = []; // [reel_idx (0-4) => symbol_idx_on_reel (0-2)]
            $numScattersToPlace = rand(3, 5);
            shuffle($reelsId); // Shuffle reel order to place scatters randomly

            for ($k = 0; $k < $numScattersToPlace; $k++) {
                $reelIdx = $reelsId[$k]; // Reel number (1-5)
                $targetReelStrip = $reelStripsToUse[$reelIdx];
                if (is_array($targetReelStrip) && count($targetReelStrip) > 0) {
                    $foundScatterPos = -1;
                    // Try to find a scatter symbol at a valid position
                    for ($attempt = 0; $attempt < count($targetReelStrip); $attempt++) {
                        $tryPos = mt_rand(0, count($targetReelStrip) - 1);
                        // Check if placing scatter at $tryPos makes it visible (top, middle, or bottom)
                        // This requires knowing how reel strip indices map to visible symbols.
                        // Assuming $prs[$reelIdx] is the top visible symbol index.
                        // A scatter at $tryPos would be visible if $tryPos is $prs[$reelIdx], $prs[$reelIdx]+1, or $prs[$reelIdx]+2
                        // This is complex. Simpler: Get a random position that *can* show a scatter.
                        // The original GetRandomScatterPos was a bit convoluted.
                        // Let's try to find a position that *is* a scatter.
                        $scatterSymbolOnReel = array_search($scatterSymbol, $targetReelStrip);
                        if ($scatterSymbolOnReel !== false) {
                            // Ensure this position can be rolled to be one of the 3 visible symbols.
                            // If reel is [A, B, SCATTER, D, E], and we need 3 visible,
                            // SCATTER can be visible if reel stops at B, SCATTER, or D.
                            // $prs[$reelIdx] should be index of B, SCATTER, or D.
                            // Max stop pos for 3 visible: count($targetReelStrip) - 3
                            if (count($targetReelStrip) >=3 ) {
                                $stopPos = $scatterSymbolOnReel - rand(0,2); // Attempt to position scatter in view
                                $stopPos = max(0, $stopPos); // Not before start
                                $stopPos = min($stopPos, count($targetReelStrip) - 3); // Not after allowed end
                                $prs[$reelIdx] = $stopPos;
                                break; // Found a scatter for this reel
                            }
                        }
                    }
                     // If no scatter found or strip too short, it might not place all desired scatters.
                }
            }
        }

        $reel = ['rp' => []]; // rp are the final chosen stop positions for each reel
        for ($i = 1; $i <= 5; $i++) {
            $key = $reelStripsToUse[$i];
            $pos = $prs[$i];
            $reel['rp'][] = $pos;

            if (is_array($key) && count($key) > 0) {
                $cnt = count($key);
                // Ensure pos is valid after potential adjustments
                $pos = max(0, min($pos, $cnt - 1));

                // Adjust if reel strip is too short for 3 symbols from $pos
                if ($cnt < 3) {
                     $reel['reel' . $i][0] = $key[0];
                     $reel['reel' . $i][1] = $key[($cnt > 1) ? 1 : 0];
                     $reel['reel' . $i][2] = $key[($cnt > 2) ? 2 : (($cnt > 1) ? 1 : 0)];
                } else {
                    // Ensure $pos allows for 3 symbols: $pos, $pos+1, $pos+2
                    $safePos = min($pos, $cnt - 3);
                    $safePos = max(0, $safePos);

                    $reel['reel' . $i][0] = $key[($safePos) % $cnt]; // Top symbol
                    $reel['reel' . $i][1] = $key[($safePos + 1) % $cnt]; // Middle symbol
                    $reel['reel' . $i][2] = $key[($safePos + 2) % $cnt]; // Bottom symbol
                }
                $reel['reel' . $i][3] = ''; // Original structure had a 4th element, usually empty
            } else {
                // Fallback for empty or invalid reel strip
                $reel['reel' . $i] = ['0', '0', '0', ''];
            }
        }
        return $reel;
    }
}
?>
