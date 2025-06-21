<?php
namespace app\games\NET\WingsOfRichesNET;

use app\games\NET\Base\BaseSlotSettings;
use app\games\NET\WingsOfRichesNET\GameReel;

class SlotSettings extends BaseSlotSettings
{
    public function __construct($gameStateData)
    {
        parent::__construct($gameStateData);

        // WingsOfRichesNET Specific Paytable
        $this->Paytable = [
            'SYM_0' => [0,0,0,0,0,0], // Scatter - typically handles its own logic
            'SYM_1' => [0,0,0,0,0,0], // Wild - typically substitutes, might have own payout if line of wilds
            'SYM_2' => [0,0,0,0,0,0], // Special Symbol for multiplier in FS? (Original server code mentions reel5[rand(0,2)]=2 for MplInc)
            'SYM_3' => [0,0,0,40,150,1500], // Fairy Red
            'SYM_4' => [0,0,0,30,125,1200], // Fairy Orange
            'SYM_5' => [0,0,0,25,100,700],  // Fairy Yellow
            'SYM_6' => [0,0,0,20,75,500],   // Fairy Blue
            'SYM_7' => [0,0,0,10,50,150],   // Beetle Red
            'SYM_8' => [0,0,0,8,30,125],    // Beetle Green
            'SYM_9' => [0,0,0,6,25,100],    // Beetle Blue
            'SYM_10' => [0,0,0,5,20,75],   // Flower Red (Mistake in original, was SYM_1)
            'SYM_11' => [0,0,0,4,15,50],   // Flower Yellow
            'SYM_12' => [0,0,0,3,10,25]    // Flower Blue
        ];
        // Correcting SYM_1 to SYM_10 based on typical paytable structures and values
        // Original had SYM_1 defined twice with different payouts. Assuming the second was SYM_10.

        // WingsOfRichesNET Specific SymbolGame
        // Original: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13']
        // '0' is Scatter, '1' is Wild, '2' is Dandelion (multiplier symbol in FS)
        // '3' to '12' are regular paying symbols. '13' is not in paytable.
        $this->SymbolGame = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];


        // WingsOfRichesNET Specific slotFreeCount
        // Original: [0,0,0,10,10,10] for 3, 4, 5 scatters.
        // Assuming scatters are SYM_0
        $this->slotFreeCount = [
            0, // 0 scatters
            0, // 1 scatter
            0, // 2 scatters
            10, // 3 scatters
            10, // 4 scatters
            10  // 5 scatters
        ];
        // $this->slotFreeCountExtra = [0,0,0,10,10,10]; // If extra spins can be triggered

        // WingsOfRichesNET GameReel instantiation and population (5 reels)
        $reel = new GameReel(); // Will need to ensure GameReel.php is created correctly
        foreach (['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5'] as $reelStripKey) {
            if (isset($reel->reelsStrip[$reelStripKey]) && count($reel->reelsStrip[$reelStripKey])) {
                $this->$reelStripKey = $reel->reelsStrip[$reelStripKey];
            }
        }
        // Bonus reels if they exist and are different for WingsOfRichesNET
        // Original GameReel loads 'reelStripBonus1' etc. if defined in reels.txt
        // For now, assuming bonus reels are handled by the same property if $slotEvent == 'freespin' changes them.
        // If GameReel.php has distinct bonus strip properties, they should be loaded here based on game state.
        // Example:
        // if (($gameStateData['postData']['slotEvent'] ?? '') == 'freespin') {
        //     foreach (['reelStripBonus1', ..., 'reelStripBonus5'] as $reelStripKey) { ... }
        // }


        // WingsOfRichesNET specific keyController (can be overridden by gameStateData if provided)
        $this->keyController = $gameStateData['game']['keyController'] ?? [
            '13' => 'uiButtonSpin,uiButtonSkip', '49' => 'uiButtonInfo', '50' => 'uiButtonCollect',
            '51' => 'uiButtonExit2', '52' => 'uiButtonLinesMinus', '53' => 'uiButtonLinesPlus',
            '54' => 'uiButtonBetMinus', '55' => 'uiButtonBetPlus', '56' => 'uiButtonGamble',
            '57' => 'uiButtonRed', '48' => 'uiButtonBlack', '189' => 'uiButtonAuto', '187' => 'uiButtonSpin'
        ];

        // WingsOfRichesNET specific slotReelsConfig (can be overridden by gameStateData)
        $this->slotReelsConfig = $gameStateData['game']['slotReelsConfig'] ?? [
            [425, 142, 3], [669, 142, 3], [913, 142, 3], [1157, 142, 3], [1401, 142, 3]
        ];

        // Specific settings from original WingsOfRichesNET SlotSettings
        $this->slotBonusType = $gameStateData['game']['slotBonusType'] ?? 1; // Original: 1
        $this->slotScatterType = $gameStateData['game']['slotScatterType'] ?? 0; // Original: 0 (Scatter symbol is '0')
        // $this->splitScreen from base (false)
        $this->slotBonus = $gameStateData['game']['slotBonus'] ?? true; // Original: true
        $this->slotGamble = $gameStateData['game']['slotGamble'] ?? true; // Original: true
        // $this->slotFastStop from base (1)
        // $this->slotExitUrl from base ('/')
        $this->slotWildMpl = $gameStateData['game']['slotWildMpl'] ?? 1; // Original: 1
        // $this->GambleType from base (1)
        // $this->Denominations from base, loaded from $gameStateData['game']['denominations']
        // $this->CurrentDenom from base, loaded from $gameStateData['game']['currentDenom']
        $this->slotFreeMpl = $gameStateData['game']['slotFreeMpl'] ?? 1; // Initial free spin multiplier
        // $this->slotViewState from base ('Normal')
        // $this->hideButtons from base ([])

        // Lines for WingsOfRichesNET (seems to be fixed 20 lines from Server.php)
        // $this->Line is used for payline definitions. The original Server.php has hardcoded line IDs.
        // This will be handled in Server.php's line evaluation.
        // $this->gameLine from base, from $gameStateData['game']['gameLine']

        // $this->Bet from base, from $gameStateData['game']['bet_values']

        // Properties that were database/session backed, now need to come from $gameStateData if they vary per session/user
        // $this->Balance is handled by parent from $gameStateData['player']['balance']
        // $this->lastEvent might be part of $gameStateData['history'] if needed
        // $this->jpgs, $this->slotJackPercent, $this->slotJackpot are handled by parent if $gameStateData['jackpots'] exists

        // $this->increaseRTP - this was a dynamic property based on user stats.
        // In a stateless model, this kind of dynamic RTP adjustment based on past play is complex.
        // For now, this logic is omitted. If needed, it would require more info in $gameStateData.
        // $this->MaxWin from base, from $gameStateData['game']['max_win']
    }

    // Retain game-specific methods if they are purely computational or rely on properties set by constructor
    // Most methods from original SlotSettings were heavily tied to DB/session state.

    public function CheckBonusWin()
    {
        // This method was used to get an average payout for bonus triggering.
        // It might not be directly applicable in the new stateless structure unless $this->AllBet is passed or set.
        // For now, returning a fixed or simple calculation if needed by spin settings logic.
        $allRateCnt = 0;
        $allRate = 0;
        foreach ($this->Paytable as $symbol => $pays) {
            if($symbol == 'SYM_0' || $symbol == 'SYM_1' || $symbol == 'SYM_2') continue; // Skip non-paying or special symbols for this calc
            foreach ($pays as $pay) {
                if ($pay > 0) {
                    $allRateCnt++;
                    $allRate += $pay;
                    break; // Consider only the first significant payout for a symbol for simplicity
                }
            }
        }
        return $allRateCnt > 0 ? $allRate / $allRateCnt : 50; // Return average or a default value
    }

    public function GetRandomPay()
    {
        // This method was used for RTP control, trying to ensure bank doesn't deplete too fast.
        // In stateless, this is harder. Could be simplified or made dependent on $gameStateData flags.
        // For now, a simplified version.
        $allRate = [];
        foreach ($this->Paytable as $symbol => $pays) {
             if($symbol == 'SYM_0' || $symbol == 'SYM_1' || $symbol == 'SYM_2') continue;
            foreach ($pays as $pay) {
                if ($pay > 0) {
                    $allRate[] = $pay;
                }
            }
        }
        shuffle($allRate);
        return $allRate[0] ?? 0; // Return a random pay amount or 0
    }


    // getNewSpin, GetRandomScatterPos, GetReelStrips were very specific to the old framework's way of
    // determining spin outcomes. The new Server.php will have its own loop to find outcomes.
    // However, GetReelStrips is crucial for generating the actual reel symbols.

    public function GetReelStrips($winType, $slotEvent)
    {
        // Adapted from original WingsOfRichesNET SlotSettings & Server logic for reel generation
        // $this->reelStrip1, reelStrip2 etc. are populated by the constructor from GameReel

        $reelsConfig = [];
        if ($slotEvent == 'freespin') {
            // Potentially use different reel strips for free spins if GameReel loads them as reelStripBonusX
            // For now, assume base strips are used, or GameReel constructor handles loading bonus strips if available.
            // If GameReel has $this->reelsStripBonus, this logic would need to select them.
            // Example: $reel = new GameReel(); $activeReelSet = $reel->reelsStripBonus;
            // Then populate $this->reelStripX from $activeReelSet.
            // For simplicity, current GameReel structure in plan uses same properties.
        }

        $prs = []; // Reel positions

        // Determine reel positions. Scatter (SYM_0) is key for bonus.
        // Wild (SYM_1) is for spreading wilds.
        // Dandelion (SYM_2) is for multiplier increase in free spins on reel 5.

        if ($winType == 'bonus') { // Try to force 3+ scatters (SYM_0)
            $scatterSymbol = '0';
            $numReels = 5;
            $scatterPositions = []; // [reelIdx, symbolPosOnReel]

            // Find all possible scatter positions
            $possibleScatterPositions = [];
            for ($r = 1; $r <= $numReels; $r++) {
                $reelStripName = 'reelStrip' . $r;
                if (is_array($this->$reelStripName) && count($this->$reelStripName) > 0) {
                    foreach ($this->$reelStripName as $pos => $sym) {
                        if ($sym == $scatterSymbol) {
                            $possibleScatterPositions[] = ['reel' => $r, 'strip_pos' => $pos];
                        }
                    }
                }
            }
            shuffle($possibleScatterPositions);

            $scattersToPlace = rand(3, min(5, count($possibleScatterPositions)));
            $placedOnReel = array_fill(1, $numReels, false);
            $actualScatterPlacements = []; // Store final strip positions for scatters

            for($i=0; $i < $scattersToPlace; $i++){
                if(empty($possibleScatterPositions)) break;
                $chosenPos = array_shift($possibleScatterPositions);
                if(!$placedOnReel[$chosenPos['reel']]){
                    $actualScatterPlacements[$chosenPos['reel']] = $chosenPos['strip_pos'];
                    $placedOnReel[$chosenPos['reel']] = true;
                } else { // Try to find another position for this scatter or skip
                    $foundAlt = false;
                    foreach($possibleScatterPositions as $idx => $altPos){
                        if(!$placedOnReel[$altPos['reel']]){
                             $actualScatterPlacements[$altPos['reel']] = $altPos['strip_pos'];
                             $placedOnReel[$altPos['reel']] = true;
                             unset($possibleScatterPositions[$idx]);
                             $foundAlt = true;
                             break;
                        }
                    }
                    if(!$foundAlt) $scattersToPlace++; // Decrement effective scatters or try again (can lead to infinite loop if not careful)
                }
            }

            for ($r = 1; $r <= $numReels; $r++) {
                $reelStripName = 'reelStrip' . $r;
                 if (isset($actualScatterPlacements[$r])) {
                    $prs[$r] = $actualScatterPlacements[$r];
                } else {
                    // Fill non-scatter reels randomly, avoiding scatter if possible
                    $strip = $this->$reelStripName;
                    $nonScatterPositions = [];
                    foreach($strip as $pos => $sym){ if($sym != $scatterSymbol) $nonScatterPositions[] = $pos; }
                    if(!empty($nonScatterPositions)){
                         $prs[$r] = $nonScatterPositions[array_rand($nonScatterPositions)];
                    } else { // Reel only has scatters or is empty
                         $prs[$r] = (is_array($strip) && count($strip) > 0) ? array_rand($strip) : 0;
                    }
                }
            }

        } else { // Not specifically aiming for bonus, could be 'win' or 'none'
            for ($r = 1; $r <= 5; $r++) {
                $reelStripName = 'reelStrip' . $r;
                if (is_array($this->$reelStripName) && count($this->$reelStripName) > 2) {
                    $prs[$r] = mt_rand(0, count($this->$reelStripName) - 3); // Ensure 3 symbols can be shown
                } else {
                    $prs[$r] = 0; // Default for short or empty strips
                }
            }
        }

        $reelsOutput = ['rp' => []];
        for ($r = 1; $r <= 5; $r++) {
            $reelStripName = 'reelStrip' . $r;
            $strip = $this->$reelStripName;
            $pos = $prs[$r] ?? 0;

            if (is_array($strip) && count($strip) > 0) {
                $cnt = count($strip);
                // Ensure $pos is valid for the strip
                $pos = max(0, min($pos, $cnt - 1));

                // Helper to safely get symbols, wrapping around the reel strip
                $safe_get = function($arr, $idx, $count) {
                    if ($count == 0) return ''; // Should not happen if strip is not empty
                    $actual_idx = $idx % $count;
                    if ($actual_idx < 0) $actual_idx += $count;
                    return $arr[$actual_idx];
                };

                // Adjust $pos to be the middle symbol's strip index if possible
                // The $pos from mt_rand(0, count - 3) means $pos is the first visible symbol.
                // To make $pos the *actual* stop position for the middle symbol on screen:
                // If reel view is [strip[pos], strip[pos+1], strip[pos+2]]
                // then the "stop position" could be considered pos+1.
                // The original server used rp[] for the first symbol. Let's stick to that.
                $reelsOutput['reel' . $r][0] = $safe_get($strip, $pos, $cnt);
                $reelsOutput['reel' . $r][1] = $safe_get($strip, $pos + 1, $cnt);
                $reelsOutput['reel' . $r][2] = $safe_get($strip, $pos + 2, $cnt);
                $reelsOutput['reel' . $r][3] = ''; // Often unused, depends on game visuals
                $reelsOutput['rp'][] = $pos;
            } else {
                $reelsOutput['reel' . $r] = ['', '', '', ''];
                $reelsOutput['rp'][] = 0;
            }
        }
        return $reelsOutput;
    }
}
?>
