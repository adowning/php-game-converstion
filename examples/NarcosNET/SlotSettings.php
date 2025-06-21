<?php
namespace app\games\NET\NarcosNET;

use app\games\NET\Base\BaseSlotSettings;

class SlotSettings extends BaseSlotSettings
{
    // Properties specific to NarcosNET or that override base properties (if any)
    // Most common properties are now inherited from BaseSlotSettings.
    // Ensure reel strips are public if accessed directly by Server.php logic (they are in Base)

    public function __construct($gameStateData)
    {
        parent::__construct($gameStateData);

        // NarcosNET Specific Paytable
        $this->Paytable = [
            'SYM_0' => [0,0,0,0,0,0], // Usually Scatter or Bonus, payouts might be handled differently
            'SYM_1' => [0,0,0,20,80,300], // Example: High Value Symbol 1
            'SYM_2' => [0,0,0,0,0,0], // Wild - might not have direct payout or handled by substitution
            'SYM_3' => [0,0,0,20,80,300], // Example: High Value Symbol 2
            'SYM_4' => [0,0,0,20,80,300], // Example: High Value Symbol 3
            'SYM_5' => [0,0,0,15,60,250], // Example: Medium Value Symbol 1
            'SYM_6' => [0,0,0,15,60,250], // Example: Medium Value Symbol 2
            'SYM_7' => [0,0,0,10,30,120], // Example: Low Value Symbol 1
            'SYM_8' => [0,0,0,10,30,120], // Example: Low Value Symbol 2
            'SYM_9' => [0,0,0,5,15,60],   // Example: Low Value Symbol 3
            'SYM_10' => [0,0,0,5,15,60],  // Example: Low Value Symbol 4
            'SYM_11' => [0,0,0,5,10,40],  // Example: Low Value Symbol 5
            'SYM_12' => [0,0,0,5,10,40]   // Example: Low Value Symbol 6
        ];

        // NarcosNET Specific SymbolGame
        $this->SymbolGame = $gameStateData['game']['SymbolGame'] ?? ['1','2','3','4','5','6','7','8','9','10','11','12'];

        // NarcosNET Specific slotFreeCount
        $this->slotFreeCount = $gameStateData['game']['slotFreeCount'] ?? [0,0,0,10,10,10]; // Narcos specific free spin counts

        // NarcosNET GameReel instantiation and population
        // This assumes GameReel.php is available and correctly namespaced or included.
        // If GameReel itself needs to be namespaced, ensure it's `app\games\NET\NarcosNET\GameReel`.
        $reel = new GameReel(); // May need to be `new \app\games\NET\NarcosNET\GameReel();` if namespace not auto-resolved
        foreach (['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5', 'reelStrip6'] as $reelStrip) {
            if (isset($reel->reelsStrip[$reelStrip]) && count($reel->reelsStrip[$reelStrip])) {
                $this->$reelStrip = $reel->reelsStrip[$reelStrip];
            }
        }
        // Bonus reels if they exist in NarcosNET's GameReel.php
        foreach (['reelStripBonus1', 'reelStripBonus2', 'reelStripBonus3', 'reelStripBonus4', 'reelStripBonus5', 'reelStripBonus6'] as $reelStrip) {
             if (isset($reel->reelsStripBonus[$reelStrip]) && count($reel->reelsStripBonus[$reelStrip])) {
                 $this->$reelStrip = $reel->reelsStripBonus[$reelStrip];
             }
        }

        // NarcosNET specific keyController
        $this->keyController = $gameStateData['game']['keyController'] ?? [
            '13' => 'uiButtonSpin,uiButtonSkip', '49' => 'uiButtonInfo', '50' => 'uiButtonCollect',
            '51' => 'uiButtonExit2', '52' => 'uiButtonLinesMinus', '53' => 'uiButtonLinesPlus',
            '54' => 'uiButtonBetMinus', '55' => 'uiButtonBetPlus', '56' => 'uiButtonGamble',
            '57' => 'uiButtonRed', '48' => 'uiButtonBlack', '189' => 'uiButtonAuto', '187' => 'uiButtonSpin'
        ];

        // NarcosNET specific slotReelsConfig
        $this->slotReelsConfig = $gameStateData['game']['slotReelsConfig'] ?? [
            [425,142,3], [669,142,3], [913,142,3], [1157,142,3], [1401,142,3]
        ];

        // NarcosNET specific line/bet configurations (if not covered by general ones in Base)
        // BaseSlotSettings already initializes Line, gameLine, Bet from gameStateData['game'] keys
        // lines_values, gameLine_values, bet_values. Ensure these keys are provided in gameStateData
        // or override here if NarcosNET uses different keys or has fixed values.
        // For example, if NarcosNET uses fixed lines:
        // $this->Line = [1,2,3, ... , 243]; // for 243 ways
        // $this->gameLine = [1,2,3, ... , 243];

        // Other NarcosNET specific properties (already in BaseSlotSettings, but can be overridden if needed)
        $this->slotBonusType = $gameStateData['game']['slotBonusType'] ?? 1; // Example, if Narcos has a specific default
        $this->slotScatterType = $gameStateData['game']['slotScatterType'] ?? 0; // Example
        $this->splitScreen = $gameStateData['game']['splitScreen'] ?? false; // Example
        // $this->slotExitUrl = '/'; // Already in Base

        // The jpgPercentZero logic is identical to BaseSlotSettings, so it's inherited.
    }

    // Retained NarcosNET-specific methods
    public function CheckBonusWin()
    {
        $allRateCnt = 0;
        $allRate = 0;
        foreach( $this->Paytable as $vl )
        {
            foreach( $vl as $vl2 )
            {
                if( $vl2 > 0 )
                {
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
        // This method's logic seems to rely on $this->game->stat_in and $this->game->stat_out
        // which are part of the $this->game object initialized from $gameStateData.
        // It also uses $this->AllBet which is currently not set as GetSpinSettings was removed.
        // If this method is to be kept functional, $this->AllBet needs to be set by Server.php.
        // For now, porting as is, but it's likely deprecated RTP logic.
        $allRate = [];
        foreach( $this->Paytable as $vl )
        {
            foreach( $vl as $vl2 )
            {
                if( $vl2 > 0 )
                {
                    $allRate[] = $vl2;
                }
            }
        }
        shuffle($allRate);
        if(empty($allRate)) return 0; // Prevent error on empty paytable

        if( ($this->game->stat_in ?? 0) < (($this->game->stat_out ?? 0) + ($allRate[0] * $this->AllBet)) )
        {
            $allRate[0] = 0;
        }
        return $allRate[0];
    }

    public function getNewSpin($game, $spinWin = 0, $bonusWin = 0, $lines, $garantType = 'bet')
    {
        // This method uses $game parameter which is $this->game.
        // It also uses $game->game_win which should be passed in gameStateData['game']['game_win']
        $curField = 243; // Narcos default for ways games often

        if( $garantType != 'bet' ) { $pref = '_bonus'; }
        else { $pref = ''; }

        $winConfig = (array)($this->game->game_win ?? []);
        $win = [];

        if( $spinWin && isset($winConfig['winline' . $pref . $curField]) )
        {
            $win = explode(',', $winConfig['winline' . $pref . $curField]);
        }
        else if( $bonusWin && isset($winConfig['winbonus' . $pref . $curField]) )
        {
            $win = explode(',', $winConfig['winbonus' . $pref . $curField]);
        }

        if (!empty($win)) {
            $number = rand(0, count($win) - 1);
            return $win[$number];
        }
        return 0; // Default return if no win lines found
    }

    public function GetRandomScatterPos($rp, $rsym) // $rsym seems Narcos specific
    {
        $rpResult = [];
        if(!is_array($rp)) return rand(0,2); // Fallback

        for( $i = 0; $i < count($rp); $i++ )
        {
            if( $rp[$i] == $rsym )
            {
                if( $rsym == '2' ) // Specific to Narcos Wild symbol?
                {
                    if( isset($rp[$i + 1]) && isset($rp[$i - 1]) )
                    {
                        array_push($rpResult, $i + 1);
                    }
                }
                else
                {
                    if( isset($rp[$i + 1]) && isset($rp[$i - 1]) ) { array_push($rpResult, $i); }
                    if( isset($rp[$i - 1]) && isset($rp[$i - 2]) ) { array_push($rpResult, $i - 1); }
                    if( isset($rp[$i + 1]) && isset($rp[$i + 2]) ) { array_push($rpResult, $i + 1); }
                }
            }
        }
        shuffle($rpResult);
        if( !isset($rpResult[0]) )
        {
            // Ensure count($rp) is greater than 2 to avoid error in rand
            return (count($rp) > 2) ? rand(1, count($rp) - 2) : 0;
        }
        return $rpResult[0];
    }

    public function GetCluster($reels) // Narcos specific cluster logic
    {
        for( $p = 0; $p <= 2; $p++ )
        {
            for( $r = 1; $r <= 5; $r++ )
            {
                if( $reels['reel' . $r][$p] == '2' )
                {
                    if( $p == 0 && $r == 1 )
                    {
                        $reels['reel' . $r][$p] = '2c';
                    }
                    else
                    {
                        if( isset($reels['reel' . ($r - 1)][$p]) && $reels['reel' . ($r - 1)][$p] == '2c' )
                        {
                            $reels['reel' . $r][$p] = '2c';
                        }
                        if( isset($reels['reel' . $r][$p - 1]) && $reels['reel' . $r][$p - 1] == '2c' )
                        {
                            $reels['reel' . $r][$p] = '2c';
                        }
                    }
                }
            }
        }
        return $reels;
    }

    public function GetReelStrips($winType, $slotEvent)
    {
        // Narcos specific reel strip logic
        if( $slotEvent == 'freespin' )
        {
            $reel = new GameReel(); // Assumes GameReel has reelsStripBonus
            if(isset($reel->reelsStripBonus) && is_array($reel->reelsStripBonus)){
                // Logic to assign bonus reels to $this->reelStrip1 etc.
                // This was simplified in Base, might need Narcos specific details.
                // For example, if Narcos bonus reels are named differently or structure is different.
                // For now, assuming base class reel strip properties are sufficient and populated by GameReel
                // If Narcos GameReel->reelsStripBonus is numerically indexed array of reel strips:
                $stripKeys = ['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5', 'reelStrip6'];
                $idx = 0;
                foreach($reel->reelsStripBonus as $strip){
                    if(isset($stripKeys[$idx]) && is_array($strip) && count($strip) > 0){
                         $this->{$stripKeys[$idx]} = $strip;
                         $idx++;
                    }
                    if($idx >= count($stripKeys)) break;
                }
            }
        }

        $prs = [];
        $reelStripsToProcess = ['reelStrip1', 'reelStrip2', 'reelStrip3', 'reelStrip4', 'reelStrip5'];
        if(isset($this->reelStrip6) && !empty($this->reelStrip6)) { // If game has 6 reels
            $reelStripsToProcess[] = 'reelStrip6';
        }

        if( $winType != 'bonus' )
        {
            foreach( $reelStripsToProcess as $index => $reelStripName )
            {
                if( is_array($this->$reelStripName) && count($this->$reelStripName) > 2 )
                {
                    $prs[$index + 1] = mt_rand(0, count($this->$reelStripName) - 3);
                } else {
                    $prs[$index + 1] = 0;
                }
            }
        }
        else // bonus winType for Narcos
        {
            $randomBonusType = rand(1, 2); // Narcos specific logic for bonus reel positions
            $reelsId = range(1, count($reelStripsToProcess));

            if( $randomBonusType == 1 )
            {
                for( $i = 0; $i < count($reelsId); $i++ ) {
                    $reelStripName = 'reelStrip' . $reelsId[$i];
                    if( $i == 0 || $i == 2 || $i == 4 ) { // Specific reels get scatters
                        $prs[$reelsId[$i]] = $this->GetRandomScatterPos($this->$reelStripName, '0'); // '0' for scatter
                    } else {
                        $prs[$reelsId[$i]] = (is_array($this->$reelStripName) && count($this->$reelStripName) > 2) ? rand(0, count($this->$reelStripName) - 3) : 0;
                    }
                }
            }
            else
            {
                $sCnt = rand(3, count($reelsId));
                shuffle($reelsId);
                for( $i = 0; $i < count($reelsId); $i++ ) {
                     $reelStripName = 'reelStrip' . $reelsId[$i];
                    if( $i < $sCnt ) { // Place scatters on $sCnt reels
                        $prs[$reelsId[$i]] = $this->GetRandomScatterPos($this->$reelStripName, '2'); // '2' for another type of scatter/feature
                    } else {
                        $prs[$reelsId[$i]] = (is_array($this->$reelStripName) && count($this->$reelStripName) > 2) ? rand(0, count($this->$reelStripName) - 3) : 0;
                    }
                }
            }
        }

        $reel = ['rp' => []];
        foreach( $prs as $index => $value )
        {
            $reelStripName = 'reelStrip' . $index;
            $key = $this->$reelStripName;
            if(is_array($key) && count($key) > 0){
                $cnt = count($key);
                $safe_key_access = function($k_arr, $idx) use ($cnt) {
                    if ($idx < 0) return $k_arr[$cnt + $idx];
                    if ($idx >= $cnt) return $k_arr[$idx % $cnt];
                    return $k_arr[$idx];
                };
                $value = max(0, min($value, $cnt - 1));
                if($cnt >=3){
                    $actual_value_for_rp = ($value == 0) ? 1 : (($value == $cnt-1) ? $cnt-2 : $value);
                    $reel['reel' . $index][0] = $safe_key_access($key, $actual_value_for_rp - 1);
                    $reel['reel' . $index][1] = $safe_key_access($key, $actual_value_for_rp);
                    $reel['reel' . $index][2] = $safe_key_access($key, $actual_value_for_rp + 1);
                } else {
                    $reel['reel' . $index][0] = $key[0]; $reel['reel' . $index][1] = $key[0]; $reel['reel' . $index][2] = $key[0];
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
