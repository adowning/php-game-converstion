<?php
namespace app\games\NET\CreatureFromTheBlackLagoonNET;

use app\games\NET\Base\BaseSlotSettings; // Corrected use statement
use app\games\NET\CreatureFromTheBlackLagoonNET\GameReel; // Assuming GameReel is in the same namespace

class SlotSettings extends BaseSlotSettings
{
    // Properties specific to CreatureFromTheBlackLagoonNET or that override base properties (if any)
    // Most common properties are now inherited from BaseSlotSettings.

    public function __construct($gameStateData)
    {
        parent::__construct($gameStateData);

        // CreatureFromTheBlackLagoonNET Specific Paytable
        $this->Paytable = [
            'SYM_0' => [0,0,0,0,0,0],
            'SYM_1' => [0,0,0,0,0,0],
            'SYM_2' => [0,0,0,0,0,0],
            'SYM_3' => [0,0,0,25,250,750],
            'SYM_4' => [0,0,0,20,200,600],
            'SYM_5' => [0,0,0,15,150,500],
            'SYM_6' => [0,0,0,10,100,400],
            'SYM_7' => [0,0,0,5,40,125],
            'SYM_8' => [0,0,0,5,40,125],
            'SYM_9' => [0,0,0,4,30,100],
            'SYM_10' => [0,0,0,4,30,100]
        ];

        // CreatureFromTheBlackLagoonNET Specific SymbolGame (overrides if different from gameStateData['game']['SymbolGame'])
        $this->SymbolGame = ['1','2','3','4','5','6','7','8','9','10','11','12','13'];

        // CreatureFromTheBlackLagoonNET Specific slotFreeCount (overrides if different)
        $this->slotFreeCount = [0,0,0,10,15,20];

        // CreatureFromTheBlackLagoonNET GameReel instantiation and population (5 reels)
        $reel = new GameReel();
        foreach (['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5'] as $reelStrip) {
            if (isset($reel->reelsStrip[$reelStrip]) && count($reel->reelsStrip[$reelStrip])) {
                $this->$reelStrip = $reel->reelsStrip[$reelStrip];
            }
        }
        // Bonus reels if they exist in Creature's GameReel.php (assuming 5 bonus reels)
        foreach (['reelStripBonus1', 'reelStripBonus2', 'reelStripBonus3', 'reelStripBonus4', 'reelStripBonus5'] as $reelStrip) {
             if (isset($reel->reelsStripBonus[$reelStrip]) && count($reel->reelsStripBonus[$reelStrip])) {
                 $this->$reelStrip = $reel->reelsStripBonus[$reelStrip];
             }
        }

        // CreatureFromTheBlackLagoonNET specific keyController (overrides if different)
        $this->keyController = $gameStateData['game']['keyController'] ?? [
            '13' => 'uiButtonSpin,uiButtonSkip', '49' => 'uiButtonInfo', '50' => 'uiButtonCollect',
            '51' => 'uiButtonExit2', '52' => 'uiButtonLinesMinus', '53' => 'uiButtonLinesPlus',
            '54' => 'uiButtonBetMinus', '55' => 'uiButtonBetPlus', '56' => 'uiButtonGamble',
            '57' => 'uiButtonRed', '48' => 'uiButtonBlack', '189' => 'uiButtonAuto', '187' => 'uiButtonSpin'
        ];

        // CreatureFromTheBlackLagoonNET specific slotReelsConfig (overrides if different)
        $this->slotReelsConfig = $gameStateData['game']['slotReelsConfig'] ?? [
            [425,142,3], [669,142,3], [913,142,3], [1157,142,3], [1401,142,3]
        ];

        // Specific settings for Creature (can override BaseSlotSettings defaults or gameStateData if needed)
        $this->slotBonusType = $gameStateData['game']['slotBonusType'] ?? 1;
        $this->slotScatterType = $gameStateData['game']['slotScatterType'] ?? 0;
        // $this->splitScreen already in base, defaults to false
        // $this->slotExitUrl already in base, defaults to '/'
        // $this->slotWildMpl already in base, defaults to 1
        // $this->GambleType already in base, defaults to 1
        // $this->slotFreeMpl already in base, defaults to 1

        // Fixed Line/Bet for Creature if not meant to be dynamic from gameStateData via base
        $this->Line = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15];
        $this->gameLine = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15];
        // $this->Bet is initialized by parent from gameStateData['game']['bet_values']
        // If Creature has a fixed bet array, uncomment and set it here:
        // $this->Bet = [1,2,3,4,5,10,15,20,25,30,40,50,60,70,80,90,100,125,150,175,200,250];

        // jpgPercentZero logic is handled by parent constructor.
    }

    // Retained CreatureFromTheBlackLagoonNET-specific methods
    public function CheckBonusWin()
    {
        $allRateCnt = 0;
        $allRate = 0;
        foreach ($this->Paytable as $vl) {
            foreach ($vl as $vl2) {
                if ($vl2 > 0) {
                    $allRateCnt++;
                    $allRate += $vl2;
                    break;
                }
            }
        }
        return $allRateCnt > 0 ? $allRate / $allRateCnt : 0;
    }

    public function GetRandomPay()
    {
        $allRate = [];
        foreach ($this->Paytable as $vl) {
            foreach ($vl as $vl2) {
                if ($vl2 > 0) {
                    $allRate[] = $vl2;
                }
            }
        }
        shuffle($allRate);
        if(empty($allRate)) return 0;

        $statIn = (isset($this->game->stat_in) ? $this->game->stat_in : 0);
        $statOut = (isset($this->game->stat_out) ? $this->game->stat_out : 0);

        // AllBet should be set by Server.php if this method is to be used.
        // Defaulting to 0 if not set to prevent errors, though this makes the condition less meaningful.
        $currentAllBet = $this->AllBet ?? 0;

        if ($statIn < ($statOut + ($allRate[0] * $currentAllBet))) {
            $allRate[0] = 0;
        }
        return $allRate[0] ?? 0;
    }

    public function getNewSpin($game, $spinWin = 0, $bonusWin = 0, $lines, $garantType = 'bet')
    {
        $curField = 10; // Default based on original structure, though lines might be fixed at 20 for Creature
        switch ($lines) { // This switch might be redundant if lines are fixed
            case 20: $curField = 20; break; // Assuming 20 lines for Creature
            case 10: $curField = 10; break;
            case 9: case 8: $curField = 9; break;
            case 7: case 6: $curField = 7; break;
            case 5: case 4: $curField = 5; break;
            case 3: case 2: $curField = 3; break;
            case 1: $curField = 1; break;
            default: $curField = 20; // Default to 20 lines for Creature
        }

        $pref = ($garantType != 'bet') ? '_bonus' : '';
        $win = [];

        $gameWinConfig = (array)($this->game->game_win ?? []);

        if ($spinWin && isset($gameWinConfig['winline' . $pref . $curField])) {
            $win = explode(',', $gameWinConfig['winline' . $pref . $curField]);
        } elseif ($bonusWin && isset($gameWinConfig['winbonus' . $pref . $curField])) {
            $win = explode(',', $gameWinConfig['winbonus' . $pref . $curField]);
        }

        if (!empty($win)) {
            $number = rand(0, count($win) - 1);
            return $win[$number];
        }
        return 0;
    }

    public function GetRandomScatterPos($rp)
    {
        $rpResult = [];
        if(!is_array($rp) || count($rp) < 3) return rand(0, 2); // Basic fallback if $rp is not an array or too short

        for ($i = 0; $i < count($rp); $i++) {
            if ($rp[$i] == '0') { // Assuming '0' is the scatter symbol identifier
                if (isset($rp[$i + 1]) && isset($rp[$i - 1])) { array_push($rpResult, $i); }
                if (isset($rp[$i - 1]) && isset($rp[$i - 2])) { array_push($rpResult, $i - 1); }
                if (isset($rp[$i + 1]) && isset($rp[$i + 2])) { array_push($rpResult, $i + 1); }
            }
        }
        shuffle($rpResult);
        if (!isset($rpResult[0])) {
            return rand(1, count($rp) - 2); // Ensure index is valid for middle of reel
        }
        return $rpResult[0];
    }

    public function GetReelStrips($winType, $slotEvent)
    {
        // Creature uses 5 reels. reelStrip6 and reelStripBonus6 from Base are not used.
        if ($slotEvent == 'freespin') {
            $reel = new GameReel();
            if (isset($reel->reelsStripBonus) && is_array($reel->reelsStripBonus)) {
                $stripKeys = ['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5'];
                $idx = 0;
                // Assuming reelsStripBonus is numerically indexed array of actual reel strips for bonus
                foreach($reel->reelsStripBonus as $stripArray){
                    if(isset($stripKeys[$idx]) && is_array($stripArray) && count($stripArray) > 0){
                         $this->{$stripKeys[$idx]} = $stripArray;
                         $idx++;
                    }
                     if($idx >= count($stripKeys)) break;
                }
            }
        }

        $prs = [];
        $reelStripsToProcess = ['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5'];

        if ($winType != 'bonus') {
            foreach ($reelStripsToProcess as $index => $reelStripName) {
                if (is_array($this->$reelStripName) && count($this->$reelStripName) > 2) {
                    $prs[$index + 1] = mt_rand(0, count($this->$reelStripName) - 3);
                } else {
                    $prs[$index + 1] = 0;
                }
            }
        } else {
            $reelsId = [];
            foreach ($reelStripsToProcess as $index => $reelStripName) {
                 if (is_array($this->$reelStripName) && count($this->$reelStripName) > 2) {
                    $prs[$index + 1] = $this->GetRandomScatterPos($this->$reelStripName);
                    $reelsId[] = $index + 1;
                } else {
                    $prs[$index + 1] = 0;
                }
            }

            if (!empty($reelsId)) {
                $scattersCnt = rand(3, min(count($reelsId), 5)); // Max 5 scatters for 5 reels
                shuffle($reelsId);
                for ($i = 0; $i < count($reelsId); $i++) {
                    $currentReelId = $reelsId[$i];
                    $currentReelStripName = 'reelStrip' . $currentReelId;
                    if ($i < $scattersCnt) {
                        $prs[$currentReelId] = $this->GetRandomScatterPos($this->$currentReelStripName);
                    } else {
                        if (is_array($this->$currentReelStripName) && count($this->$currentReelStripName) > 2) {
                            $prs[$currentReelId] = rand(0, count($this->$currentReelStripName) - 3);
                        } else {
                             $prs[$currentReelId] = 0;
                        }
                    }
                }
            }
        }

        $reel = ['rp' => []];
        foreach ($prs as $index => $value) {
            $reelStripName = 'reelStrip' . $index;
            $key = $this->$reelStripName;
            if (is_array($key) && count($key) > 0) {
                $cnt = count($key);
                $safe_key_access = function($k_arr, $idx) use ($cnt) {
                    if ($idx < 0) return $k_arr[$cnt + $idx];
                    if ($idx >= $cnt) return $k_arr[$idx % $cnt];
                    return $k_arr[$idx];
                };
                $value = max(0, min($value, $cnt - 1));
                if($cnt >=3){
                    $actual_value_for_rp = ($value == 0 && $cnt > 1) ? 1 : (($value == $cnt-1 && $cnt > 1) ? $cnt-2 : $value);
                    // Ensure actual_value_for_rp is valid before accessing array
                    if($actual_value_for_rp < 0) $actual_value_for_rp = 0;
                    if($actual_value_for_rp >= $cnt) $actual_value_for_rp = $cnt -1;

                    $reel['reel' . $index][0] = $safe_key_access($key, $actual_value_for_rp - 1);
                    $reel['reel' . $index][1] = $safe_key_access($key, $actual_value_for_rp);
                    $reel['reel' . $index][2] = $safe_key_access($key, $actual_value_for_rp + 1);
                } else { // Reel has 1 or 2 symbols
                    $reel['reel' . $index][0] = $key[0];
                    $reel['reel' . $index][1] = $key[0];
                    $reel['reel' . $index][2] = $key[0];
                }
                $reel['reel' . $index][3] = '';
                $reel['rp'][] = $value;
            } else {
                 $reel['reel' . $index][0] = 0; $reel['reel' . $index][1] = 0; $reel['reel' . $index][2] = 0; $reel['reel' . $index][3] = '';
                 $reel['rp'][] = 0;
            }
        }
        return $reel;
    }
}
?>
