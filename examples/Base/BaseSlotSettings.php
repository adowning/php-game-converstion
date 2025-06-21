<?php

namespace app\games\NET\Base;

// Base class for slot game settings
class BaseSlotSettings
{
    // Common properties
    public $playerId = null;
    public $slotId = '';
    public $slotDBId = '';
    public $Balance = 0;
    protected $Bank = 0; // Changed to protected for potential access in derived if necessary, though GetBank is public
    protected $Percent = 0; // Changed to protected
    public $currency = null;
    public $user = null;
    public $game = null;
    public $shop = null;
    public $gameData = [];
    public $gameDataStatic = [];
    public $MaxWin = 0;
    public $CurrentDenom = 1;
    public $Denominations = [];
    public $CurrentDenomination = 1;
    public $jpgs = [];
    public $shop_id = null;
    public $count_balance = null;
    public $isBonusStart = false;
    public $Paytable = [];
    public $SymbolGame = [];
    public $Line = [];
    public $gameLine = [];
    public $Bet = [];
    public $slotFreeCount = [];
    public $slotFreeMpl = 1;
    public $slotWildMpl = 1;
    public $GambleType = 1;
    public $WinGamble = 0; // From game->rezerv

    public $reelStrip1 = [];
    public $reelStrip2 = [];
    public $reelStrip3 = [];
    public $reelStrip4 = [];
    public $reelStrip5 = [];
    public $reelStrip6 = []; // For games with 6 reels
    public $reelStripBonus1 = [];
    public $reelStripBonus2 = [];
    public $reelStripBonus3 = [];
    public $reelStripBonus4 = [];
    public $reelStripBonus5 = [];
    public $reelStripBonus6 = [];

    public $scaleMode = 0;
    public $numFloat = 0;
    public $splitScreen = false;
    public $lastEvent = null;
    public $Jackpots = [];
    public $keyController = [];
    public $slotViewState = 'Normal';
    public $hideButtons = [];
    public $slotReelsConfig = [];
    public $slotExitUrl = '/';
    public $slotBonus = true; // Common default, can be overridden by game-specific settings
    public $slotBonusType = 1;
    public $slotScatterType = 0;
    public $slotGamble = true; // Common default
    public $slotSounds = [];
    public $jpgPercentZero = false;
    public $increaseRTP = 1; // Retained as it's in constructors, though main use in GetSpinSettings is gone
    public $slotFastStop = 1;
    public $slotJackPercent = [];
    public $slotJackpot = [];
    public $slotCurrency = '';
    public $AllBet = 0; // Its main setter GetSpinSettings is gone. Server.php might need to set this if methods like GetRandomPay are kept.

    /**
     * Log an internal error and exit
     * 
     * @param string $errcode The error code
     * @param bool $silent If true, don't output anything, just log
     */
public function InternalError($errcode, $silent = false)
{
    $strLog = "\n";
    $strLog .= date('Y-m-d H:i:s') . ' - ' . $errcode;
    $strLog .= "\n";

    // Log to a 'logs' directory relative to this Base class.
    // Ensure the 'logs' directory exists and is writable.
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/' . ($this->slotId ?? 'general_errors') . '.log';
    
    file_put_contents($logFile, $strLog, FILE_APPEND);
    
    if (!$silent) {
        // In a stateless model, we shouldn't exit. We should let the main handler return an error response.
        // For now, we'll comment this out to prevent the script from dying.
        // exit('');
    }
}
    
    /**
     * Log a silent internal error (doesn't exit)
     * 
     * @param string $errcode The error code
     */
    public function InternalErrorSilent($errcode)
    {
        $this->InternalError($errcode, true);
    }
    
    public function __construct($gameStateData)
    {
        $this->playerId = $gameStateData['playerId'] ?? null;
        $this->user = (object) ($gameStateData['user'] ?? []);
        $this->game = (object) ($gameStateData['game'] ?? []);
        $this->shop = (object) ($gameStateData['shop'] ?? []);

        $this->Bank = $gameStateData['bank'] ?? 0;
        $this->Balance = $gameStateData['balance'] ?? 0;
        $this->Percent = $gameStateData['shop']['percent'] ?? 0;
        $this->gameData = $gameStateData['gameData'] ?? [];
        $this->gameDataStatic = $gameStateData['gameDataStatic'] ?? [];
        $this->currency = $gameStateData['currency'] ?? '';
        $this->slotId = $gameStateData['game']['name'] ?? '';
        $this->slotDBId = $gameStateData['game']['id'] ?? '';
        $this->MaxWin = $gameStateData['shop']['max_win'] ?? 0;
        $this->shop_id = $gameStateData['user']['shop_id'] ?? 0;
        $this->count_balance = $gameStateData['user']['count_balance'] ?? 0;

        $this->Denominations = isset($gameStateData['game']['denominations_list']) && is_array($gameStateData['game']['denominations_list'])
            ? $gameStateData['game']['denominations_list']
            : (isset($gameStateData['game']['denominations_list']) ? explode(',', $gameStateData['game']['denominations_list']) : []);

        $this->CurrentDenom = $gameStateData['game']['denomination'] ?? (!empty($this->Denominations) ? $this->Denominations[0] : 1);
        $this->CurrentDenomination = $this->CurrentDenom;

        $this->jpgs = $gameStateData['jpgs'] ?? [];
        $this->WinGamble = $gameStateData['game']['rezerv'] ?? 0;

        // Game-specific settings that might be overridden or extended by derived classes
        $this->increaseRTP = $gameStateData['game']['increaseRTP'] ?? 1;
        $this->slotFastStop = $gameStateData['game']['slotFastStop'] ?? 1;
        $this->slotJackPercent = $gameStateData['game']['slotJackPercent'] ?? [];
        $this->slotJackpot = $gameStateData['game']['slotJackpot'] ?? [];
        $this->slotCurrency = $gameStateData['currency'] ?? ($this->shop->currency ?? '');
        $this->slotBonus = $gameStateData['game']['slotBonus'] ?? true;
        $this->slotGamble = $gameStateData['game']['slotGamble'] ?? true;
        $this->slotWildMpl = $gameStateData['game']['slotWildMpl'] ?? 1;
        $this->slotFreeMpl = $gameStateData['game']['slotFreeMpl'] ?? 1;
        $this->GambleType = $gameStateData['game']['GambleType'] ?? 1;
        $this->slotViewState = $gameStateData['game']['slotViewState'] ?? 'Normal';

        // Initialize common structure but expect game-specific values from derived constructor or gameStateData
        $this->Paytable = $gameStateData['game']['paytable'] ?? []; // Now prefers paytable from gameStateData
        $this->SymbolGame = $gameStateData['game']['SymbolGame'] ?? [];
        $this->Line = isset($gameStateData['game']['lines_values']) ? (is_array($gameStateData['game']['lines_values']) ? $gameStateData['game']['lines_values'] : explode(',', $gameStateData['game']['lines_values'])) : []; // Renamed from 'lines' to avoid conflict if 'lines' means count
        $this->gameLine = isset($gameStateData['game']['gameLine_values']) ? (is_array($gameStateData['game']['gameLine_values']) ? $gameStateData['game']['gameLine_values'] : explode(',', $gameStateData['game']['gameLine_values'])) : []; // Renamed
        $this->Bet = isset($gameStateData['game']['bet_values']) ? (is_array($gameStateData['game']['bet_values']) ? $gameStateData['game']['bet_values'] : explode(',', $gameStateData['game']['bet_values'])) : []; // Renamed


        if (($this->user->address ?? 0) > 0 && $this->count_balance == 0) {
            $this->Percent = 0;
            $this->jpgPercentZero = true;
        } elseif (isset($this->user->count_balance) && $this->user->count_balance == 0) { // Check isset for safety
            $this->Percent = 100;
        }
    }

    public function is_active()
    {
        return true;
    }

    public function SetGameData($key, $value)
    {
        $timeLife = 86400; // 24 hours
        $this->gameData[$key] = [
            'timelife' => time() + $timeLife,
            'payload' => $value
        ];
    }

    public function GetGameData($key)
    {
        if (isset($this->gameData[$key])) {
            // Optional: Check timelife, though usually done when gameData is loaded/unserialized
            // if ($this->gameData[$key]['timelife'] <= time()) { unset($this->gameData[$key]); return 0; }
            return $this->gameData[$key]['payload'];
        }
        return 0;
    }

    public function HasGameData($key)
    {
        return isset($this->gameData[$key]);
    }

    public function FormatFloat($num)
    {
        $num = floatval($num); // Ensure it's a float
        return floor($num * 100) / 100; // Simple truncation to 2 decimal places
    }

    public function SaveGameData()
    {
        // Does nothing in stateless model; data is returned to TypeScript facade.
    }

    public function HasGameDataStatic($key)
    {
        return isset($this->gameDataStatic[$key]);
    }

    public function SetGameDataStatic($key, $value)
    {
        $timeLife = 86400; // 24 hours
        $this->gameDataStatic[$key] = [
            'timelife' => time() + $timeLife,
            'payload' => $value
        ];
    }

    public function GetGameDataStatic($key)
    {
        if (isset($this->gameDataStatic[$key])) {
            return $this->gameDataStatic[$key]['payload'];
        }
        return 0;
    }

    public function SaveGameDataStatic()
    {
        // Does nothing in stateless model.
    }

    public function GetHistory()
    {
        return 'NULL'; // History is managed by the TypeScript facade.
    }

    public function UpdateJackpots($bet)
    {
        $this->Jackpots = []; // Jackpots are managed by the TypeScript facade.
    }

    public function GetBank($slotState = '')
    {
        return $this->Bank;
    }

    public function GetPercent()
    {
        return $this->Percent;
    }

    public function GetCountBalanceUser()
    {
        return $this->count_balance;
    }

    // Error handling methods are now defined at the beginning of the class

public function SetBank($sum, $slotState = '', $slotEvent = '')
{
    if (!is_numeric($sum)) {
        $this->InternalErrorSilent("SetBank: non-numeric sum provided.");
        return;
    }
    $this->Bank += $sum;
}

    public function SetBalance($sum, $slotEvent = '')
    {
        if (!is_numeric($sum)) {
            $this->InternalErrorSilent("SetBalance: non-numeric sum provided.");
            return;
        }
        $this->Balance += $sum;
    }

    public function GetBalance()
    {
        return $this->Balance;
    }

    public function SaveLogReport($spinSymbols, $bet, $lines, $win, $slotState)
    {
        // Logging is managed by the TypeScript facade.
    }

    public function GetGambleSettings()
    {
        // This is a simple gamble setting, might be common enough.
        // WinGamble should be set from gameStateData['game']['rezerv']
        return rand(1, max(1, (int)$this->WinGamble));
    }
}
