<?php
// Placeholder app/games/NET/Base/BaseSlotSettings.php
namespace app\games\NET\Base;

class BaseSlotSettings
{
    public $playerId = null;
    public $slotId = '';
    public $Line = null;
    public $gameLine = null;
    public $Bet = null;
    public $Balance = null;
    public $SymbolGame = null;
    public $GambleType = null;
    public $slotFreeCount = null;
    public $slotFreeMpl = null;
    public $slotWildMpl = null;
    public $slotExitUrl = null;
    public $slotBonus = null;
    public $slotBonusType = null;
    public $slotScatterType = null;
    public $slotGamble = null;
    public $Paytable = [];
    public $currency = null;
    public $CurrentDenom = 0.01; // Default denomination
    public $CurrentDenomination = 0.01; // Default denomination
    public $Denominations = [0.01, 0.02, 0.05, 0.10, 0.20, 0.50, 1.00]; // Example denominations
    public $MaxWin = 0; // Max win, can be overridden
    public $slotViewState = 'Normal';
    public $hideButtons = [];
    public $slotReelsConfig = [];
    public $slotJackPercent = [];
    public $slotJackpot = [];
    public $Jackpots = []; // For storing current jackpot values if applicable
    public $keyController = [];
    public $splitScreen = false;
    public $gameData = []; // For storing session-like game data
    public $AllBet = 0; // Total bet for the current spin
    public $reelStrip1, $reelStrip2, $reelStrip3, $reelStrip4, $reelStrip5, $reelStrip6;
    public $reelStripBonus1, $reelStripBonus2, $reelStripBonus3, $reelStripBonus4, $reelStripBonus5, $reelStripBonus6;
    public $shop_id = null; // from gameStateData['user']['shop_id']
    public $user_id = null; // from gameStateData['user']['id']
    public $game = null; // from gameStateData['game']
    public $user = null; // from gameStateData['user']
    public $shop = null; // from gameStateData['shop']
    public $jpgs = []; // from gameStateData['jpgs']
    public $count_balance = null; // from gameStateData['user']['count_balance']
    public $gameDataStatic = []; // for game-specific static data if needed

    public function __construct($gameStateData)
    {
        $this->slotId = $gameStateData['game']['name'] ?? 'WildWaterNET';
        $this->playerId = $gameStateData['user']['id'] ?? null;
        $this->user_id = $this->playerId;

        $this->Line = $gameStateData['game']['lines'] ?? [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20]; // Default lines
        $this->gameLine = $this->Line;
        $this->Bet = $gameStateData['game']['bet_values'] ?? [1,2,3,4,5,10]; // Default bet levels

        $this->Balance = $gameStateData['user']['balance'] ?? 0;
        $this->currency = $gameStateData['shop']['currency'] ?? 'USD';
        $this->CurrentDenom = $gameStateData['game']['current_deno'] ?? 0.01;
        $this->CurrentDenomination = $this->CurrentDenom;
        $this->Denominations = $gameStateData['game']['denominations'] ?? [0.01, 0.02, 0.05, 0.10, 0.20, 0.50, 1.00];
        $this->MaxWin = $gameStateData['shop']['max_win'] ?? 0;

        $this->slotBonus = $gameStateData['game']['slotBonus'] ?? true;
        $this->slotGamble = $gameStateData['game']['slotGamble'] ?? true;
        $this->GambleType = $gameStateData['game']['GambleType'] ?? 1;
        $this->slotWildMpl = $gameStateData['game']['slotWildMpl'] ?? 1;
        $this->slotFreeMpl = $gameStateData['game']['slotFreeMpl'] ?? 1;
        $this->slotBonusType = $gameStateData['game']['slotBonusType'] ?? 1;
        $this->slotScatterType = $gameStateData['game']['slotScatterType'] ?? 0;
        $this->slotExitUrl = $gameStateData['game']['slotExitUrl'] ?? '/';
        $this->splitScreen = $gameStateData['game']['splitScreen'] ?? false;

        $this->keyController = $gameStateData['game']['keyController'] ?? [];
        $this->slotReelsConfig = $gameStateData['game']['slotReelsConfig'] ?? [];
        $this->hideButtons = $gameStateData['game']['hideButtons'] ?? [];
        $this->slotViewState = $gameStateData['game']['slotViewState'] ?? 'Normal';

        // Initialize gameData from gameStateData if provided
        $this->gameData = $gameStateData['persistence']['gameData'] ?? [];
        $this->gameDataStatic = $gameStateData['persistence']['gameDataStatic'] ?? [];

        // Simulate game, user, shop, jpgs from gameStateData
        $this->game = (object)($gameStateData['game'] ?? []);
        $this->user = (object)($gameStateData['user'] ?? []);
        $this->shop = (object)($gameStateData['shop'] ?? []);
        $this->jpgs = (array)($gameStateData['jpgs'] ?? []);
        $this->shop_id = $this->shop->id ?? ($gameStateData['user']['shop_id'] ?? null);
        $this->count_balance = $this->user->count_balance ?? $this->Balance; // Fallback for count_balance
    }

    // Common methods that might be in a base class, to be made stateless
    public function GetBalance() { return $this->Balance; }
    public function SetBalance($amount, $event = '') { $this->Balance += $amount; } // Simplified: actual balance update happens outside

    public function GetBank($slotState = '') { return $this->game->bank ?? 0; } // Simplified
    public function SetBank($slotState = '', $sum, $slotEvent = '') {  } // Simplified: actual bank update happens outside

    public function GetPercent() { return $this->shop->percent ?? 100; }

    public function GetGameData($key) { return $this->gameData[$key] ?? null; }
    public function SetGameData($key, $value) { $this->gameData[$key] = $value; }
    public function HasGameData($key) { return isset($this->gameData[$key]); }

    public function GetGameDataStatic($key) { return $this->gameDataStatic[$key] ?? null; }
    public function SetGameDataStatic($key, $value) { $this->gameDataStatic[$key] = $value; }
    public function HasGameDataStatic($key) { return isset($this->gameDataStatic[$key]); }

    public function FormatFloat($num) { return floor($num * 100) / 100; }

    public function is_active() { // Simplified, assumes data from gameStateData implies active state
        if(empty($this->game) || empty($this->shop) || empty($this->user)) return false;
        if(!($this->game->view ?? true)) return false;
        if($this->shop->is_blocked ?? false) return false;
        if($this->user->is_blocked ?? false) return false;
        // Assuming 'BANNED' status might be represented as a specific value in $gameStateData['user']['status']
        // For simplicity, we'll assume if we got this far and have user data, they are not banned in a way that blocks game init.
        return true;
    }

    public function UpdateJackpots($bet) { /* Logic to calculate jackpot contributions if any, without DB write */ }
    public function SaveLogReport($spinSymbols, $bet, $lines, $win, $slotState) { /* Logging is now external */ }

    // Placeholder for spin settings, actual logic will be in game-specific SlotSettings or Server
    public function GetSpinSettings($garantType = 'bet', $bet, $lines) {
        return ['none', 0]; // Default: no guaranteed win, no specific win limit from base
    }

    // Placeholder for gamble settings
    public function GetGambleSettings() { return 1; } // Default: win

    public function InternalError($errcode) { throw new \Exception($errcode); }
    public function InternalErrorSilent($errcode) { /* Log or handle silently if needed, but primary error handling in Server */ }

}
