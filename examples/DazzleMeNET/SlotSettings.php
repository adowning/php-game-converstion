<?php

namespace app\games\NET\DazzleMeNET;

use app\games\NET\Base\BaseSlotSettings;
use app\games\NET\DazzleMeNET\GameReel;

class SlotSettings extends BaseSlotSettings
{
    // Dazzle Me specific properties
    public $slotId = 'DazzleMeNET';
    public $slotBonus = true;
    public $slotGamble = true;
    public $GambleType = 1;
    protected $desiredWinType = 'none';
    public $slotViewState = 'Normal';
    public $slotBonusType = 1;
    public $slotScatterType = 0;
    public $slotFreeMpl = 1;
    public $slotWildMpl = 1;
    public $slotExitUrl = '/';
    public $slotReelsConfig = [
        [425, 142, 3],
        [669, 142, 3],
        [913, 142, 3],
        [1157, 142, 3],
        [1401, 142, 3]
    ];
    
    // Key controller mappings
    public $keyController = [
        '13' => 'uiButtonSpin,uiButtonSkip',
        '49' => 'uiButtonInfo',
        '50' => 'uiButtonCollect',
        '51' => 'uiButtonExit2',
        '52' => 'uiButtonLinesMinus',
        '53' => 'uiButtonLinesPlus',
        '54' => 'uiButtonBetMinus',
        '55' => 'uiButtonBetPlus',
        '56' => 'uiButtonGamble',
        '57' => 'uiButtonRed',
        '48' => 'uiButtonBlack',
        '189' => 'uiButtonAuto',
        '187' => 'uiButtonSpin'
    ];
    
    // Dazzle Me specific game settings
    protected $scatterSymbol = 1; // Assuming 1 is the scatter symbol
    protected $wildSymbol = 0;    // Assuming 0 is the wild symbol
    protected $freeSpinSymbol = 2; // Assuming 2 is the free spin symbol
    
    /**
     * Initialize game-specific settings
     */
    protected function initializeGameSettings()
    {
        parent::initializeGameSettings();
        
        // Dazzle Me specific paytable
        $this->Paytable = [
            'SYM_0' => [0, 0, 0, 0, 0, 0],  // Wild
            'SYM_1' => [0, 0, 0, 2, 20, 100],  // Scatter (Dazzle Me logo)
            'SYM_2' => [0, 0, 0, 0, 0, 0],  // Free Spin
            'SYM_3' => [0, 0, 0, 25, 250, 750],  // Ace
            'SYM_4' => [0, 0, 0, 20, 200, 600],  // King
            'SYM_5' => [0, 0, 0, 15, 150, 500],  // Queen
            'SYM_6' => [0, 0, 0, 10, 100, 400],  // Jack
            'SYM_7' => [0, 0, 0, 5, 40, 125],    // Ten
            'SYM_8' => [0, 0, 0, 5, 40, 125],    // Nine
            'SYM_9' => [0, 0, 0, 4, 30, 100],    // Eight
            'SYM_10' => [0, 0, 0, 4, 30, 100]    // Seven
        ];
        
        // Symbol mappings (0-10)
        $this->SymbolGame = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
        
        // Free spin counts for 3, 4, 5+ scatters
        $this->slotFreeCount = [0, 0, 0, 10, 15, 20];
        
        // Default bets (can be overridden by game state)
        $this->Bet = [0.01, 0.02, 0.05, 0.10, 0.20, 0.30, 0.50, 1.00, 2.00, 5.00];
        
        // Game lines (20 lines)
        $this->Line = range(1, 20);
        $this->gameLine = range(1, 20);
    }
    /**
 * Set the desired win type for the next spin
 * 
 * @param string $winType The desired win type ('none', 'win', or 'bonus')
 */
public function setDesiredWinType($winType)
{
    $this->desiredWinType = in_array($winType, ['none', 'win', 'bonus']) ? $winType : 'none';
}
    /**
     * Calculate the average bonus win rate
     * 
     * @return float The average bonus win rate
     */
    public function calculateBonusWinRate($reels, $bet, $lines)
    {
        $scatterCount = 0;
        
        // Count scatter symbols on the reels
        foreach ($reels as $reel) {
            if (in_array($this->scatterSymbol, $reel)) {
                $scatterCount++;
            }
        }
        
        // Check for free spins (3+ scatters)
        if ($scatterCount >= 3) {
            $freeSpins = $this->slotFreeCount[$scatterCount] ?? 10;
            $this->isFreeSpin = true;
            $this->freeSpinsRemaining = $freeSpins;
            $this->freeSpinsTotal = $freeSpins;
            
            // Calculate scatter win (bet * 2 per scatter)
            $scatterWin = $bet * $lines * 2 * $scatterCount;
            $this->bonusWin = $scatterWin;
            
            return [
                'type' => 'free_spins',
                'count' => $freeSpins,
                'win' => $scatterWin
            ];
        }
        
        return null;
    }
    
    /**
     * Calculate win for a specific line
     */
    public function calculateLineWin($line, $symbols, $betPerLine, $lineNumber)
    {
        $symbol = $symbols[0];
        $count = 1;
        
        // Count consecutive matching symbols (or wilds)
        for ($i = 1; $i < count($symbols); $i++) {
            if ($symbols[$i] == $symbol || $symbols[$i] == $this->wildSymbol) {
                $count++;
            } elseif ($symbol == $this->wildSymbol) {
                $symbol = $symbols[$i];
                $count++;
            } else {
                break;
            }
        }
        
        // Check if we have a winning combination
        if ($count >= 3 && isset($this->Paytable['SYM_' . $symbol][$count - 1])) {
            $multiplier = $this->Paytable['SYM_' . $symbol][$count - 1];
            $win = $betPerLine * $multiplier;
            
            return [
                'line' => $lineNumber,
                'symbol' => $symbol,
                'count' => $count,
                'win' => $win,
                'positions' => array_slice($line, 0, $count)
            ];
        }
        
        return null;
    }
    
    /**
     * Process a spin result
     */
    public function processSpinResult($reels, $bet, $lines)
    {
        $winLines = [];
        $totalWin = 0;
        $betPerLine = $bet / $lines;
        
        // Check each line for wins
        foreach ($this->Line as $lineNumber => $line) {
            $lineSymbols = [];
            
            // Get symbols for this line
            foreach ($line as $reelPos => $row) {
                if (isset($reels[$reelPos][$row])) {
                    $lineSymbols[] = $reels[$reelPos][$row];
                }
            }
            
            // Check for line win
            $lineWin = $this->calculateLineWin($line, $lineSymbols, $betPerLine, $lineNumber);
            if ($lineWin) {
                $winLines[] = $lineWin;
                $totalWin += $lineWin['win'];
            }
        }
        
        // Check for bonus wins (scatters)
        $bonusWin = $this->checkBonusWin($reels, $bet, $lines);
        if ($bonusWin) {
            $totalWin += $bonusWin['win'];
        }
        
        // Update game state
        $this->totalWin = $totalWin;
        $this->baseWin = $totalWin - ($bonusWin['win'] ?? 0);
        
        return [
            'reels' => $reels,
            'winLines' => $winLines,
            'totalWin' => $totalWin,
            'bonus' => $bonusWin,
            'freeSpinsRemaining' => $this->freeSpinsRemaining,
            'freeSpinsTotal' => $this->freeSpinsTotal,
            'balance' => $this->Balance
        ];
    }
        public function __construct($sid, $playerId)
        {
            $this->slotId = $sid;
            $this->playerId = $playerId;
            $user = \VanguardLTE\User::lockForUpdate()->find($this->playerId);
            $this->user = $user;
            $this->shop_id = $user->shop_id;
            $gamebank = \VanguardLTE\GameBank::where(['shop_id' => $this->shop_id])->lockForUpdate()->get();
            $game = \VanguardLTE\Game::where([
                'name' => $this->slotId, 
                'shop_id' => $this->shop_id
            ])->lockForUpdate()->first();
            $this->shop = \VanguardLTE\Shop::find($this->shop_id);
            $this->game = $game;
            $this->MaxWin = $this->shop->max_win;
            $this->increaseRTP = 1;
            $this->CurrentDenom = $this->game->denomination;
            $this->scaleMode = 0;
            $this->numFloat = 0;
            $this->Paytable['SYM_0'] = [
                0, 
                0, 
                0, 
                0, 
                0, 
                0
            ];
            $this->Paytable['SYM_1'] = [
                0, 
                0, 
                0, 
                0, 
                0, 
                0
            ];
            $this->Paytable['SYM_2'] = [
                0, 
                0, 
                0, 
                0, 
                0, 
                0
            ];
            $this->Paytable['SYM_3'] = [
                0, 
                0, 
                1, 
                12, 
                30, 
                200
            ];
            $this->Paytable['SYM_4'] = [
                0, 
                0, 
                1, 
                8, 
                15, 
                100
            ];
            $this->Paytable['SYM_5'] = [
                0, 
                0, 
                0, 
                4, 
                8, 
                30
            ];
            $this->Paytable['SYM_6'] = [
                0, 
                0, 
                0, 
                4, 
                8, 
                30
            ];
            $this->Paytable['SYM_7'] = [
                0, 
                0, 
                0, 
                3, 
                5, 
                20
            ];
            $this->Paytable['SYM_8'] = [
                0, 
                0, 
                0, 
                3, 
                5, 
                20
            ];
            $reel = new GameReel();
            foreach( [
                'reelStrip1', 
                'reelStrip2', 
                'reelStrip3', 
                'reelStrip4', 
                'reelStrip5', 
                'reelStrip6'
            ] as $reelStrip ) 
            {
                if( count($reel->reelsStrip[$reelStrip]) ) 
                {
                    $this->$reelStrip = $reel->reelsStrip[$reelStrip];
                }
            }
            $this->keyController = [
                '13' => 'uiButtonSpin,uiButtonSkip', 
                '49' => 'uiButtonInfo', 
                '50' => 'uiButtonCollect', 
                '51' => 'uiButtonExit2', 
                '52' => 'uiButtonLinesMinus', 
                '53' => 'uiButtonLinesPlus', 
                '54' => 'uiButtonBetMinus', 
                '55' => 'uiButtonBetPlus', 
                '56' => 'uiButtonGamble', 
                '57' => 'uiButtonRed', 
                '48' => 'uiButtonBlack', 
                '189' => 'uiButtonAuto',
                '187' => 'uiButtonSpin'
            ];
            $this->slotReelsConfig = [
                [
                    425, 
                    142, 
                    3
                ], 
                [
                    669, 
                    142, 
                    3
                ], 
                [
                    913, 
                    142, 
                    3
                ], 
                [
                    1157, 
                    142, 
                    3
                ], 
                [
                    1401, 
                    142, 
                    3
                ]
            ];
            $this->slotBonusType = 1;
            $this->slotScatterType = 0;
            $this->splitScreen = false;
            $this->slotBonus = true;
            $this->slotGamble = true;
            $this->slotFastStop = 1;
            $this->slotExitUrl = '/';
            $this->slotWildMpl = 1;
            $this->GambleType = 1;
            $this->Denominations = \VanguardLTE\Game::$values['denomination'];
            $this->CurrentDenom = $this->Denominations[0];
            $this->CurrentDenomination = $this->Denominations[0];
            $this->slotFreeCount = [
                0, 
                0, 
                0, 
                8, 
                12, 
                16
            ];
            $this->slotFreeMpl = 1;
            $this->slotViewState = ($game->slotViewState == '' ? 'Normal' : $game->slotViewState);
            $this->hideButtons = [];
            $this->jpgs = \VanguardLTE\JPG::where('shop_id', $this->shop_id)->lockForUpdate()->get();
            $this->slotJackPercent = [];
            $this->slotJackpot = [];
            for( $jp = 1; $jp <= 4; $jp++ ) 
            {
                $this->slotJackpot[] = $game->{'jp_' . $jp};
                $this->slotJackPercent[] = $game->{'jp_' . $jp . '_percent'};
            }
            $this->Line = [
                1, 
                2, 
                3, 
                4, 
                5, 
                6, 
                7, 
                8, 
                9, 
                10, 
                11, 
                12, 
                13, 
                14, 
                15
            ];
            $this->gameLine = [
                1, 
                2, 
                3, 
                4, 
                5, 
                6, 
                7, 
                8, 
                9, 
                10, 
                11, 
                12, 
                13, 
                14, 
                15
            ];
            $this->Bet = explode(',', $game->bet);
            $this->Balance = $user->balance;
            $this->SymbolGame = [
                '2', 
                '3', 
                '4', 
                '5', 
                '6', 
                '7', 
                '8'
            ];
            $this->Bank = $game->get_gamebank();
            $this->Percent = $this->shop->percent;
            $this->WinGamble = $game->rezerv;
            $this->slotDBId = $game->id;
            $this->slotCurrency = $user->shop->currency;
            $this->count_balance = $user->count_balance;
            if( $user->address > 0 && $user->count_balance == 0 ) 
            {
                $this->Percent = 0;
                $this->jpgPercentZero = true;
            }
            else if( $user->count_balance == 0 ) 
            {
                $this->Percent = 100;					
										   
											
            }
            if( !isset($this->user->session) || strlen($this->user->session) <= 0 ) 
            {
                $this->user->session = serialize([]);
            }
            $this->gameData = unserialize($this->user->session);
            if( count($this->gameData) > 0 ) 
            {
                foreach( $this->gameData as $key => $vl ) 
                {
                    if( $vl['timelife'] <= time() ) 
                    {
                        unset($this->gameData[$key]);
                    }
                }
            }
            if( !isset($this->game->advanced) || strlen($this->game->advanced) <= 0 ) 
            {
                $this->game->advanced = serialize([]);
            }
            $this->gameDataStatic = unserialize($this->game->advanced);
            if( count($this->gameDataStatic) > 0 ) 
            {
                foreach( $this->gameDataStatic as $key => $vl ) 
                {
                    if( $vl['timelife'] <= time() ) 
                    {
                        unset($this->gameDataStatic[$key]);
                    }
                }
            }
        }
        public function is_active()
        {
            if( $this->game && $this->shop && $this->user && (!$this->game->view || $this->shop->is_blocked || $this->user->is_blocked || $this->user->status == \VanguardLTE\Support\Enum\UserStatus::BANNED) ) 
            {
                \VanguardLTE\Session::where('user_id', $this->user->id)->delete();
                $this->user->update(['remember_token' => null]);
                return false;
            }
            if( !$this->game->view ) 
            {
                return false;
            }
            if( $this->shop->is_blocked ) 
            {
                return false;
            }
            if( $this->user->is_blocked ) 
            {
                return false;
            }
            if( $this->user->status == \VanguardLTE\Support\Enum\UserStatus::BANNED ) 
            {
                return false;
            }
            return true;
        }
        public function SetGameData($key, $value)
        {
            $timeLife = 86400;
            $this->gameData[$key] = [
                'timelife' => time() + $timeLife, 
                'payload' => $value
            ];
        }
        public function GetGameData($key)
        {
            if( isset($this->gameData[$key]) ) 
            {
                return $this->gameData[$key]['payload'];
            }
            else
            {
                return 0;
            }
        }
        public function FormatFloat($num)
        {
            $str0 = explode('.', $num);
            if( isset($str0[1]) ) 
            {
                if( strlen($str0[1]) > 4 ) 
                {
                    return round($num * 100) / 100;
                }
                else if( strlen($str0[1]) > 2 ) 
                {
                    return floor($num * 100) / 100;
                }
                else
                {
                    return $num;
                }
            }
            else
            {
                return $num;
            }
        }
        public function SaveGameData()
        {
            $this->user->session = serialize($this->gameData);
            $this->user->save();
        }
        /**
     * Calculate the average win rate from the paytable
     * 
     * @return float The average win rate
     */
    public function calculateAverageWinRate()
    {
        $allRateCnt = 0;
        $allRate = 0;
        
        if (empty($this->Paytable)) {
            return 0;
        }
        
        foreach ($this->Paytable as $vl) {
            if (!is_array($vl)) continue;
            
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
            if( $this->game->stat_in < ($this->game->stat_out + ($allRate[0] * $this->AllBet)) ) 
            {
                $allRate[0] = 0;
            }
            return $allRate[0];
        }
        public function HasGameDataStatic($key)
        {
            if( isset($this->gameDataStatic[$key]) ) 
            {
                return true;
            }
            else
            {
                return false;
            }
        }
        public function SaveGameDataStatic()
        {
            $this->game->advanced = serialize($this->gameDataStatic);
            $this->game->save();
            $this->game->refresh();
        }
        public function SetGameDataStatic($key, $value)
        {
            $timeLife = 86400;
            $this->gameDataStatic[$key] = [
                'timelife' => time() + $timeLife, 
                'payload' => $value
            ];
        }
        public function GetGameDataStatic($key)
        {
            if( isset($this->gameDataStatic[$key]) ) 
            {
                return $this->gameDataStatic[$key]['payload'];
            }
            else
            {
                return 0;
            }
        }
        public function HasGameData($key)
        {
            if( isset($this->gameData[$key]) ) 
            {
                return true;
            }
            else
            {
                return false;
            }
        }
        /**
         * History is now managed by the TypeScript side
         */
        public function GetHistory()
        {
            // History management has been moved to TypeScript
            return 'NULL';
        }
        /**
         * Jackpot updates are now managed by the TypeScript side
         */
        public function UpdateJackpots($bet)
        {
            // Jackpot management has been moved to TypeScript
            return true;
        }
        public function GetBank($slotState = '')
        {
            if( $this->isBonusStart || $slotState == 'bonus' || $slotState == 'freespin' || $slotState == 'respin' ) 
            {
                $slotState = 'bonus';
            }
            else
            {
                $slotState = '';
            }
            $game = $this->game;
            $this->Bank = $game->get_gamebank($slotState);
            return $this->Bank / $this->CurrentDenom;
        }
        public function GetPercent()
        {
            return $this->Percent;
        }
        public function GetCountBalanceUser()
        {
            return $this->user->count_balance;
        }
        public function SetBank($slotState = '', $sum, $slotEvent = '')
        {
            if( $this->isBonusStart || $slotState == 'bonus' || $slotState == 'freespin' || $slotState == 'respin' ) 
            {
                $slotState = 'bonus';
            }
            else
            {
                $slotState = '';
            }
            if( $this->GetBank($slotState) + $sum < 0 ) 
            {
                $this->InternalError('Bank_   ' . $sum . '  CurrentBank_ ' . $this->GetBank($slotState) . ' CurrentState_ ' . $slotState . ' Trigger_ ' . ($this->GetBank($slotState) + $sum));
            }
            $sum = $sum * $this->CurrentDenom;
            $game = $this->game;
            $bankBonusSum = 0;
            if( $sum > 0 && $slotEvent == 'bet' ) 
            {
                $this->toGameBanks = 0;
                $this->toSlotJackBanks = 0;
                $this->toSysJackBanks = 0;
                $this->betProfit = 0;
                $prc = $this->GetPercent();
                $prc_b = 10;
                if( $prc <= $prc_b ) 
                {
                    $prc_b = 0;
                }
                $count_balance = $this->count_balance;
                $gameBet = $sum / $this->GetPercent() * 100;
                if( $count_balance < $gameBet && $count_balance > 0 ) 
                {
                    $firstBid = $count_balance;
                    $secondBid = $gameBet - $firstBid;
                    if( isset($this->betRemains0) ) 
                    {
                        $secondBid = $this->betRemains0;
                    }
                    $bankSum = $firstBid / 100 * $this->GetPercent();
					$sum = $bankSum + $secondBid;												 
                    $bankBonusSum = $firstBid / 100 * $prc_b;
                }
                else if( $count_balance > 0 ) 
                {
                    $bankBonusSum = $gameBet / 100 * $prc_b;
                }
                for( $i = 0; $i < count($this->jpgs); $i++ ) 
                {
                    if( !$this->jpgPercentZero ) 
                    {
                        if( $count_balance < $gameBet && $count_balance > 0 ) 

                    {
                        $this->toSlotJackBanks += ($count_balance / 100 * $this->jpgs[$i]->percent);
                    }
                    else if( $count_balance > 0 ) 
                    {
                        $this->toSlotJackBanks += ($gameBet / 100 * $this->jpgs[$i]->percent);
					}
                    }
                }
                $this->toGameBanks = $sum;

                $this->betProfit = $gameBet - $this->toGameBanks - $this->toSlotJackBanks - $this->toSysJackBanks;
            }
            if( $sum > 0 ) 
            {
                $this->toGameBanks = $sum;
            }
            if( $bankBonusSum > 0 ) 
            {
                $sum -= $bankBonusSum;
                $game->set_gamebank($bankBonusSum, 'inc', 'bonus');
            }
            if( $sum == 0 && $slotEvent == 'bet' && isset($this->betRemains) ) 
            {
                $sum = $this->betRemains;
            }
            $game->set_gamebank($sum, 'inc', $slotState);
            $game->save();
            return $game;
        }
        public function SetBalance($sum, $slotEvent = '')
        {
            if( $this->GetBalance() + $sum < 0 ) 
            {
                $this->InternalError('Balance_   ' . $sum);
            }
            $sum = $sum * $this->CurrentDenom;
								
            if( $sum < 0 && $slotEvent == 'bet' ) 
            {
                $user = $this->user;
                if( $user->count_balance == 0 ) 
                {
                    $remains = [];
                    $this->betRemains = 0;
                    $sm = abs($sum);
                    if( $user->address < $sm && $user->address > 0 ) 
                    {
                        $remains[] = $sm - $user->address;
                    }
                    for( $i = 0; $i < count($remains); $i++ ) 
																
									  
                    {
                        if( $this->betRemains < $remains[$i] ) 
                        {
                            $this->betRemains = $remains[$i];
				   
                        }
                    }
                }
                if( $user->count_balance > 0 && $user->count_balance < abs($sum) ) 
                {
                    $remains0 = [];
                    $sm = abs($sum);
                    $tmpSum = $sm - $user->count_balance;
                    $this->betRemains0 = $tmpSum;
                    if( $user->address > 0 ) 
                    {
                        $this->betRemains0 = 0;
                        if( $user->address < $tmpSum && $user->address > 0 ) 
                        {
                            $remains0[] = $tmpSum - $user->address;
                        }
                        for( $i = 0; $i < count($remains0); $i++ ) 
                        {
                            if( $this->betRemains0 < $remains0[$i] ) 
                            {
                                $this->betRemains0 = $remains0[$i];
				   
                            }
                        }
                    }
                }
                $sum0 = abs($sum);
                if( $user->count_balance == 0 ) 
                {
                    $sm = abs($sum);
                    if( $user->address < $sm && $user->address > 0 ) 
                    {
                        $user->address = 0;
                    }
                    else if( $user->address > 0 ) 
                    {
                        $user->address -= $sm;
                    }
                }
                else if( $user->count_balance > 0 && $user->count_balance < $sum0 ) 
                {
                    $sm = $sum0 - $user->count_balance;
                    if( $user->address < $sm && $user->address > 0 ) 
                    {
                        $user->address = 0;
                    }
                    else if( $user->address > 0 ) 
                    {
                        $user->address -= $sm;
                    }
                }
                $this->user->count_balance = $this->user->updateCountBalance($sum, $this->count_balance);
                $this->user->count_balance = $this->FormatFloat($this->user->count_balance);
            }
            $this->user->increment('balance', $sum);
            $this->user->balance = $this->FormatFloat($this->user->balance);
            $this->user->save();
            return $this->user;
        }
        public function GetBalance()
        {
            $user = $this->user;
            $this->Balance = $user->balance / $this->CurrentDenom;
            return $this->Balance;
        }
        public function SaveLogReport($spinSymbols, $bet, $lines, $win, $slotState)
        {
            $reportName = $this->slotId . ' ' . $slotState;
            if( $slotState == 'freespin' ) 
            {
                $reportName = $this->slotId . ' FG';
            }
            else if( $slotState == 'bet' ) 
            {
                $reportName = $this->slotId . '';
            }
            else if( $slotState == 'slotGamble' ) 
            {
                $reportName = $this->slotId . ' DG';
            }
            $game = $this->game;
            if( $slotState == 'bet' ) 
            {
                $this->user->update_level('bet', $bet * $this->CurrentDenom);
            }
            if( $slotState != 'freespin' ) 
            {
                $game->increment('stat_in', $bet * $this->CurrentDenom);
            }
            $game->increment('stat_out', $win * $this->CurrentDenom);
            $game->tournament_stat($slotState, $this->user->id, $bet * $this->CurrentDenom, $win * $this->CurrentDenom);
            $this->user->update(['last_bid' => \Carbon\Carbon::now()]);
            if( !isset($this->betProfit) ) 
            {
                $this->betProfit = 0;
                $this->toGameBanks = 0;
                $this->toSlotJackBanks = 0;
                $this->toSysJackBanks = 0;
            }
            if( !isset($this->toGameBanks) ) 
            {
                $this->toGameBanks = 0;
            }
            $this->game->increment('bids');
            $this->game->refresh();
            $gamebank = \VanguardLTE\GameBank::where(['shop_id' => $game->shop_id])->first();
            if( $gamebank ) 
            {
                list($slotsBank, $bonusBank, $fishBank, $tableBank, $littleBank) = \VanguardLTE\Lib\Banker::get_all_banks($game->shop_id);
            }
            else
            {
                $slotsBank = $game->get_gamebank('', 'slots');
                $bonusBank = $game->get_gamebank('bonus', 'bonus');
                $fishBank = $game->get_gamebank('', 'fish');
                $tableBank = $game->get_gamebank('', 'table_bank');
                $littleBank = $game->get_gamebank('', 'little');
            }
            $totalBank = $slotsBank + $bonusBank + $fishBank + $tableBank + $littleBank;
            \VanguardLTE\GameLog::create([
                'game_id' => $this->slotDBId, 
                'user_id' => $this->playerId, 
                'ip' => $_SERVER['REMOTE_ADDR'], 
                'str' => $spinSymbols, 
                'shop_id' => $this->shop_id
            ]);
            \VanguardLTE\StatGame::create([
                'user_id' => $this->playerId, 
                'balance' => $this->Balance * $this->CurrentDenom, 
                'bet' => $bet * $this->CurrentDenom, 
                'win' => $win * $this->CurrentDenom, 
                'game' => $reportName, 
                'in_game' => $this->toGameBanks, 
                'in_jpg' => $this->toSlotJackBanks, 
                'in_profit' => $this->betProfit, 
                'denomination' => $this->CurrentDenom, 
                'shop_id' => $this->shop_id, 
                'slots_bank' => (double)$slotsBank, 
                'bonus_bank' => (double)$bonusBank, 
                'fish_bank' => (double)$fishBank, 
                'table_bank' => (double)$tableBank, 
                'little_bank' => (double)$littleBank, 
                'total_bank' => (double)$totalBank, 
                'date_time' => \Carbon\Carbon::now()
            ]);
        }
        public function GetSpinSettings($garantType = 'bet', $bet, $lines)
        {
            $this->AllBet = $bet * $lines;
            
            // If we have a desired win type from TypeScript, use it
            if ($this->desiredWinType !== 'none') {
                $winType = $this->desiredWinType;
                $winAmount = 0;
                
                // Reset the desired win type after using it
                $this->desiredWinType = 'none';
                
                // Calculate win amount based on the win type
                if ($winType === 'win') {
                    // For a standard win, calculate based on bet and lines
                    $winAmount = $this->calculateWinAmount($bet, $lines);
                } elseif ($winType === 'bonus' && $this->slotBonus) {
                    // For a bonus, get the bonus bank amount
                    $winAmount = $this->GetBank('bonus');
                    $this->isBonusStart = true;
                }
                
                return [$winType, $winAmount];
            }
            
            // Fallback to random win type if no desired win type is set
            $random = mt_rand(1, 100);
            
            // Default win chances (can be adjusted)
            if ($random <= 5) { // 5% chance for bonus
                return ['bonus', $this->GetBank('bonus')];
            } elseif ($random <= 30) { // 25% chance for win
                return ['win', $this->calculateWinAmount($bet, $lines)];
            }
            
            // 70% chance for no win
            return ['none', 0];
        }
        
        /**
         * Calculate a win amount based on bet and lines
         */
        protected function calculateWinAmount($bet, $lines)
        {
            // This is a simplified example - you should implement your actual win calculation logic here
            // based on your game's paytable and rules
            
            // For now, return a random win amount between 0.5x and 10x the total bet
            $multiplier = 0.5 + (mt_rand() / mt_getrandmax()) * 9.5; // Random between 0.5 and 10.0
            return $bet * $lines * $multiplier;
        }
        public function getNewSpin($game, $spinWin = 0, $bonusWin = 0, $lines, $garantType = 'bet')
        {
            $curField = 10;
            switch( $lines ) 
            {
                case 10:
                    $curField = 10;
                    break;
                case 9:
                case 8:
                    $curField = 9;
                    break;
                case 7:
                case 6:
                    $curField = 7;
                    break;
                case 5:
                case 4:
                    $curField = 5;
                    break;
                case 3:
                case 2:
                    $curField = 3;
                    break;
                case 1:
                    $curField = 1;
                    break;
                default:
                    $curField = 10;
                    break;
            }
            if( $garantType != 'bet' ) 
            {
                $pref = '_bonus';
            }
            else
            {
                $pref = '';
            }
            if( $spinWin ) 
            {
                $win = explode(',', $game->game_win->{'winline' . $pref . $curField});
            }
            if( $bonusWin ) 
            {
                $win = explode(',', $game->game_win->{'winbonus' . $pref . $curField});
            }
            $number = rand(0, count($win) - 1);
            return $win[$number];
        }
        public function GetRandomScatterPos($rp)
        {
            $rpResult = [];
            for( $i = 0; $i < count($rp); $i++ ) 
            {
                if( $rp[$i] == '0' ) 
                {
                    if( isset($rp[$i + 1]) && isset($rp[$i - 1]) ) 
                    {
                        array_push($rpResult, $i);
                    }
                    if( isset($rp[$i - 1]) && isset($rp[$i - 2]) ) 
                    {
                        array_push($rpResult, $i - 1);
                    }
                    if( isset($rp[$i + 1]) && isset($rp[$i + 2]) ) 
                    {
                        array_push($rpResult, $i + 1);
                    }
                }
            }
            shuffle($rpResult);
            if( !isset($rpResult[0]) ) 
            {
                $rpResult[0] = rand(2, count($rp) - 3);
            }
            return $rpResult[0];
        }
        public function GetGambleSettings()
        {
            $spinWin = rand(1, $this->WinGamble);
            return $spinWin;
        }
        public function GetReelStrips($winType, $slotEvent)
        {
            $game = $this->game;
            if( $winType != 'bonus' ) 
            {
                $prs = [];
                foreach( [
                    'reelStrip1', 
                    'reelStrip2', 
                    'reelStrip3', 
                    'reelStrip4', 
                    'reelStrip5', 
                    'reelStrip6'
                ] as $index => $reelStrip ) 
                {
                    if( is_array($this->$reelStrip) && count($this->$reelStrip) > 0 ) 
                    {
                        $prs[$index + 1] = mt_rand(0, count($this->$reelStrip) - 3);
                    }
                }
            }
            else
            {
                $reelsId = [];
                foreach( [
                    'reelStrip1', 
                    'reelStrip2', 
                    'reelStrip3', 
                    'reelStrip4', 
                    'reelStrip5', 
                    'reelStrip6'
                ] as $index => $reelStrip ) 
                {
                    if( is_array($this->$reelStrip) && count($this->$reelStrip) > 0 ) 
                    {
                        $prs[$index + 1] = $this->GetRandomScatterPos($this->$reelStrip);
                        $reelsId[] = $index + 1;
                    }
                }
                $scattersCnt = rand(3, count($reelsId));
                shuffle($reelsId);
                for( $i = 0; $i < count($reelsId); $i++ ) 
                {
                    if( $i < $scattersCnt ) 
                    {
                        $prs[$reelsId[$i]] = $this->GetRandomScatterPos($this->{'reelStrip' . $reelsId[$i]});
                    }
                    else
                    {
                        $prs[$reelsId[$i]] = rand(0, count($this->{'reelStrip' . $reelsId[$i]}) - 3);
                    }
                }
            }
            $reel = [
                'rp' => []
            ];
            foreach( $prs as $index => $value ) 
            {
                $key = $this->{'reelStrip' . $index};
                $cnt = count($key);
                $key[-1] = $key[$cnt - 1];
                $key[-2] = $key[$cnt - 2];
                $key[-3] = $key[$cnt - 3];
                $key[$cnt] = $key[0];
                $key[$cnt + 1] = $key[1];
                $key[$cnt + 2] = $key[2];
                if( $index == 1 || $index == 2 ) 
                {
                    $reel['reel' . $index][0] = $key[$value - 1];
                    $reel['reel' . $index][1] = $key[$value];
                    $reel['reel' . $index][2] = $key[$value + 1];
                    $reel['reel' . $index][3] = '';
                    $reel['reel' . $index][4] = '';
                    $reel['reel' . $index][5] = '';
                }
                if( $index == 3 || $index == 4 ) 
                {
                    $reel['reel' . $index][0] = $key[$value - 1];
                    $reel['reel' . $index][1] = $key[$value];
                    $reel['reel' . $index][2] = $key[$value + 1];
                    $reel['reel' . $index][3] = $key[$value + 2];
                    $reel['reel' . $index][4] = '';
                    $reel['reel' . $index][5] = '';
                }
                if( $index == 5 ) 
                {
                    $reel['reel' . $index][0] = $key[$value - 1];
                    $reel['reel' . $index][1] = $key[$value];
                    $reel['reel' . $index][2] = $key[$value + 1];
                    $reel['reel' . $index][3] = $key[$value + 2];
                    $reel['reel' . $index][4] = $key[$value + 3];
                    $reel['reel' . $index][5] = '';
                }
                $reel['rp'][] = $value;
            }
            return $reel;
        }
    }


