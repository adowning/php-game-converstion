<?php

namespace app\games\NET\DazzleMeNET;

use app\games\NET\DazzleMeNET\SlotSettings;
use app\games\NET\DazzleMeNET\GameReel;
use Exception;

/**
 * Server class for handling DazzleMe slot game requests
 */
class Server
{
    /**
     * @var SlotSettings
     */
    private $slotSettings;

    /**
     * Server constructor.
     * 
     * @param SlotSettings $slotSettings The slot settings instance
     */
    public function __construct(SlotSettings $slotSettings = null)
    {
        $this->slotSettings = $slotSettings;
    }

    /**
     * Handle the game request
     * 
     * @param array $request The request data
     * @return array The response data
     */
    public function handle($request)
    {
        try {
            // Parse the request data
            $action = $request['action'] ?? 'init';
            $postData = $request['postData'] ?? [];
            $gameState = $request['gameState'] ?? [];
            
            // Get desiredWinType from the request (set by TypeScript)
            $desiredWinType = $gameState['desiredWinType'] ?? 'none';
            
            // Use provided slot settings or create a new one if not provided
            if ($this->slotSettings === null) {
                if (!isset($gameState['slotId'], $gameState['playerId'])) {
                    throw new \InvalidArgumentException('Missing required game state: slotId and playerId are required');
                }
                $this->slotSettings = new SlotSettings($gameState['slotId'], $gameState['playerId']);
                
                // Set the desired win type for this spin
                $this->slotSettings->setDesiredWinType($desiredWinType);
            }
            
            // Process the action
            switch ($action) {
                case 'init':
                    return $this->handleInit($this->slotSettings, $postData);
                    
                case 'spin':
                    return $this->handleSpin($this->slotSettings, $postData);
                    
                case 'freespin':
                    return $this->handleFreeSpin($this->slotSettings, $postData);
                    
                case 'paytable':
                    return $this->handlePaytable($this->slotSettings);
                    
                case 'initfreespin':
                    return $this->handleInitFreeSpin($this->slotSettings);
                    
                case 'reloadbalance':
                    return $this->handleReloadBalance($this->slotSettings, $postData);
                    
                default:
                    throw new \Exception('Invalid action: ' . $action);
            }
        } catch (Exception $e) {
            return [
                'responseEvent' => 'error',
                'responseType' => 'error',
                'serverResponse' => $e->getMessage(),
                'errorDetails' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }
    
    /**
     * Handle the init action
     * 
     * @param SlotSettings $slotSettings The slot settings instance
     * @param array $postData The post data from the request
     * @return array The response data
     */
    protected function handleInit($slotSettings, $postData)
    {
        // Set initial game state
        $slotSettings->SetGameData($slotSettings->slotId . 'BonusWin', 0);
        $slotSettings->SetGameData($slotSettings->slotId . 'FreeGames', 0);
        $slotSettings->SetGameData($slotSettings->slotId . 'CurrentFreeGame', 0);
        $slotSettings->SetGameData($slotSettings->slotId . 'TotalWin', 0);
        $slotSettings->SetGameData($slotSettings->slotId . 'FreeBalance', 0);
        
        // Set initial bet if not set
        if ($slotSettings->CurrentBet <= 0) {
            $slotSettings->CurrentBet = $slotSettings->Bet[0] ?? 0.10;
        }
        
        // Set initial lines if not set
        if (empty($slotSettings->CurrentLines)) {
            $slotSettings->CurrentLines = 20; // Default to maximum lines
        }
        
        // Prepare response
        return [
            'responseEvent' => 'init',
            'responseType' => 'init',
            'serverResponse' => json_encode([
                'balance' => $slotSettings->GetBalance(),
                'bet' => $slotSettings->CurrentBet,
                'lines' => $slotSettings->CurrentLines,
                'denomination' => $slotSettings->CurrentDenom,
                'maxWin' => $slotSettings->MaxWin,
                'currency' => $slotSettings->currency,
                'isFreeSpin' => $slotSettings->isFreeSpin,
                'freeSpinsRemaining' => $slotSettings->freeSpinsRemaining,
                'freeSpinsTotal' => $slotSettings->freeSpinsTotal,
                'totalWin' => $slotSettings->totalWin,
                'baseWin' => $slotSettings->baseWin,
                'bonusWin' => $slotSettings->bonusWin
            ])
        ];
    }
    
    /**
     * Handle the spin action
     * 
     * @param SlotSettings $slotSettings The slot settings instance
     * @param array $postData The post data from the request
     * @return array The response data
     * @throws Exception If bet or lines are invalid or insufficient balance
     */
    protected function handleSpin($slotSettings, $postData)
    {
        // Process bet and lines
        $bet = $postData['bet'] ?? $slotSettings->CurrentBet;
        $lines = $postData['lines'] ?? $slotSettings->CurrentLines;
        $desiredWinType = $postData['desiredWinType'] ?? 'none'; // 'none', 'win', or 'bonus'
        
        // Validate bet and lines
        if ($bet <= 0 || $lines <= 0) {
            throw new Exception('Invalid bet or lines');
        }
        
        // Check if player has enough balance
        $totalBet = $bet * $lines;
        if ($slotSettings->GetBalance() < $totalBet) {
            throw new Exception('Insufficient balance');
        }
        
        // Update current bet and lines
        $slotSettings->CurrentBet = $bet;
        $slotSettings->CurrentLines = $lines;
        
        // Deduct bet from balance
        $slotSettings->Balance -= $totalBet;
        
        // Generate reels based on desired win type
        $gameReel = new GameReel();
        $reels = $gameReel->generateReelsForWinType($desiredWinType, $bet, $lines);
        
        // Process the spin result without RTP control
        $result = $slotSettings->processSpinResult($reels, $bet, $lines, false);
        
        // Update balance with winnings
        $slotSettings->Balance += $result['totalWin'];
        
        // Prepare response
        return [
            'responseEvent' => 'spin',
            'responseType' => 'spin',
            'serverResponse' => json_encode([
                'reels' => $result['reels'],
                'winLines' => $result['winLines'],
                'totalWin' => $result['totalWin'],
                'balance' => $slotSettings->GetBalance(),
                'isFreeSpin' => $slotSettings->isFreeSpin,
                'freeSpinsRemaining' => $slotSettings->freeSpinsRemaining,
                'freeSpinsTotal' => $slotSettings->freeSpinsTotal,
                'bonus' => $result['bonus'] ?? null
            ])
        ];
    }
    
    /**
     * Handle the free spin action
     * 
     * @param SlotSettings $slotSettings The slot settings instance
     * @param array $postData The post data from the request
     * @return array The response data
     * @throws Exception If no free spins are available
     */
    protected function handleFreeSpin($slotSettings, $postData)
    {
        // Check if we have free spins available
        if ($slotSettings->freeSpinsRemaining <= 0) {
            throw new Exception('No free spins available');
        }
        
        // Use the same bet and lines as the triggering spin
        $bet = $slotSettings->CurrentBet;
        $lines = $slotSettings->CurrentLines;
        
        // Generate reels
        $gameReel = new GameReel();
        $reels = $gameReel->spin();
        
        // Process the free spin result
        $result = $slotSettings->processSpinResult($reels, $bet, $lines);
        
        // Update balance with winnings (no bet deduction for free spins)
        $slotSettings->Balance += $result['totalWin'];
        
        // Decrement free spins counter
        $slotSettings->freeSpinsRemaining--;
        
        // If this was the last free spin, update the game state
        if ($slotSettings->freeSpinsRemaining <= 0) {
            $slotSettings->isFreeSpin = false;
        }
        
        // Prepare response
        return [
            'responseEvent' => 'freespin',
            'responseType' => 'freespin',
            'serverResponse' => json_encode([
                'reels' => $result['reels'],
                'winLines' => $result['winLines'],
                'totalWin' => $result['totalWin'],
                'balance' => $slotSettings->GetBalance(),
                'isFreeSpin' => $slotSettings->isFreeSpin,
                'freeSpinsRemaining' => $slotSettings->freeSpinsRemaining,
                'freeSpinsTotal' => $slotSettings->freeSpinsTotal,
                'bonus' => $result['bonus'] ?? null
            ])
        ];
    }
    
    /**
     * Handle the paytable action
     * 
     * @param SlotSettings $slotSettings The slot settings instance
     * @return array The response data
     */
    protected function handlePaytable($slotSettings)
    {
        // Prepare paytable response
        return [
            'responseEvent' => 'paytable',
            'responseType' => 'paytable',
            'serverResponse' => json_encode([
                'paytable' => $slotSettings->Paytable,
                'symbols' => $slotSettings->SymbolGame,
                'wildSymbols' => $slotSettings->WildSymbols,
                'scatterSymbols' => $slotSettings->ScatterSymbols,
                'bonusSymbols' => $slotSettings->BonusSymbols
            ])
        ];
    }
    
    /**
     * Handle the init free spin action
     * 
     * @param SlotSettings $slotSettings The slot settings instance
     * @return array The response data
     */
    protected function handleInitFreeSpin($slotSettings)
    {
        // Reset free spin related data
        $slotSettings->isFreeSpin = true;
        $slotSettings->freeSpinsTotal = $slotSettings->freeSpinsAwarded;
        $slotSettings->freeSpinsRemaining = $slotSettings->freeSpinsAwarded;
        $slotSettings->freeSpinTotalWin = 0;
        
        // Prepare response
        return [
            'responseEvent' => 'initfreespin',
            'responseType' => 'initfreespin',
            'serverResponse' => json_encode([
                'freeSpinsAwarded' => $slotSettings->freeSpinsAwarded,
                'freeSpinsRemaining' => $slotSettings->freeSpinsRemaining,
                'freeSpinTotalWin' => $slotSettings->freeSpinTotalWin,
                'isFreeSpin' => true
            ])
        ];
    }
    
    /**
     * Handle the reload balance action
     * 
     * @param SlotSettings $slotSettings The slot settings instance
     * @param array $postData The post data from the request
     * @return array The response data
     */
    protected function handleReloadBalance($slotSettings, $postData)
    {
        // Reload balance from the provided data or use default
        if (isset($postData['balance'])) {
            $slotSettings->Balance = (float)$postData['balance'];
        }
        
        // Prepare response
        return [
            'responseEvent' => 'reloadbalance',
            'responseType' => 'reloadbalance',
            'serverResponse' => json_encode([
                'balance' => $slotSettings->GetBalance(),
                'status' => 'success'
            ])
        ];
    }
}
