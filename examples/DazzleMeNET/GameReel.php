<?php 
namespace app\games\NET\DazzleMeNET;

class GameReel
{
    public $reelsStrip = [
        'reelStrip1' => [], 
        'reelStrip2' => [], 
        'reelStrip3' => [], 
        'reelStrip4' => [], 
        'reelStrip5' => [], 
        'reelStrip6' => []
    ];
    
    public $reelsStripBonus = [
        'reelStripBonus1' => [], 
        'reelStripBonus2' => [], 
        'reelStripBonus3' => [], 
        'reelStripBonus4' => [], 
        'reelStripBonus5' => [], 
        'reelStripBonus6' => []
    ];
    
    public function __construct()
    {
        $reelsFile = __DIR__ . '/reels.txt';
        if (!file_exists($reelsFile)) {
            throw new \RuntimeException("Reels file not found: " . $reelsFile);
        }
        
        $temp = file($reelsFile);
        if ($temp === false) {
            throw new \RuntimeException("Failed to read reels file: " . $reelsFile);
        }
        
        foreach ($temp as $str) {
            $str = explode('=', $str);
            if (isset($this->reelsStrip[$str[0]])) {
                $data = explode(',', $str[1]);
                foreach ($data as $elem) {
                    $elem = trim($elem);
                    if ($elem != '') {
                        $this->reelsStrip[$str[0]][] = $elem;
                    }
                }
            }
            
            if (isset($this->reelsStripBonus[$str[0]])) {
                $data = explode(',', $str[1]);
                foreach ($data as $elem) {
                    $elem = trim($elem);
                    if ($elem != '') {
                        $this->reelsStripBonus[$str[0]][] = $elem;
                    }
                }
            }
        }
    }
    
    /**
     * Spin the reels and return the result
     * 
     * @return array Array of reels with symbols
     */
    /**
     * Generate reels for a specific win type
     * 
     * @param string $winType The desired win type ('none', 'win', or 'bonus')
     * @param float $bet The bet amount
     * @param int $lines The number of lines
     * @return array The generated reels
     */
    public function generateReelsForWinType($winType, $bet, $lines)
    {
        switch (strtolower($winType)) {
            case 'win':
                return $this->generateWinningReels($bet, $lines);
            case 'bonus':
                return $this->generateBonusReels();
            case 'none':
            default:
                return $this->generateNonWinningReels();
        }
    }
    
    /**
     * Generate reels for a standard win
     */
    protected function generateWinningReels($bet, $lines)
    {
        // This is a simplified example - you'll need to implement actual win logic
        // based on your game's paytable and rules
        $result = [];
        
        // For now, just generate random reels
        // In a real implementation, you would ensure these reels produce a win
        for ($i = 1; $i <= 5; $i++) {
            $reelKey = 'reelStrip' . $i;
            if (isset($this->reelsStrip[$reelKey]) && !empty($this->reelsStrip[$reelKey])) {
                $result['reel' . $i] = [
                    $this->reelsStrip[$reelKey][array_rand($this->reelsStrip[$reelKey])],
                    $this->reelsStrip[$reelKey][array_rand($this->reelsStrip[$reelKey])],
                    $this->reelsStrip[$reelKey][array_rand($this->reelsStrip[$reelKey])]
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Generate reels for a bonus win
     */
    protected function generateBonusReels()
    {
        // This is a simplified example - you'll need to implement actual bonus logic
        $result = [];
        
        // For now, just generate random reels from the bonus strips
        // In a real implementation, you would ensure these reels trigger a bonus
        for ($i = 1; $i <= 5; $i++) {
            $reelKey = 'reelStripBonus' . $i;
            if (isset($this->reelsStripBonus[$reelKey]) && !empty($this->reelsStripBonus[$reelKey])) {
                $result['reel' . $i] = [
                    $this->reelsStripBonus[$reelKey][array_rand($this->reelsStripBonus[$reelKey])],
                    $this->reelsStripBonus[$reelKey][array_rand($this->reelsStripBonus[$reelKey])],
                    $this->reelsStripBonus[$reelKey][array_rand($this->reelsStripBonus[$reelKey])]
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Generate reels for a non-winning spin
     */
    protected function generateNonWinningReels()
    {
        // This is a simplified example - you'll need to implement actual logic
        // to ensure these reels don't produce any wins
        $result = [];
        
        // For now, just generate random reels
        // In a real implementation, you would ensure these reels don't produce any wins
        for ($i = 1; $i <= 5; $i++) {
            $reelKey = 'reelStrip' . $i;
            if (isset($this->reelsStrip[$reelKey]) && !empty($this->reelsStrip[$reelKey])) {
                $result['reel' . $i] = [
                    $this->reelsStrip[$reelKey][array_rand($this->reelsStrip[$reelKey])],
                    $this->reelsStrip[$reelKey][array_rand($this->reelsStrip[$reelKey])],
                    $this->reelsStrip[$reelKey][array_rand($this->reelsStrip[$reelKey])]
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Spin the reels and return the result
     * 
     * @deprecated Use generateReelsForWinType instead for more control
     * @return array Array of reels with symbols
     */
    public function spin()
    {
        return $this->generateNonWinningReels();
    }
}
