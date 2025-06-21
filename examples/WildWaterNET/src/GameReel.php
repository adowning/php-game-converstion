<?php
namespace app\games\NET\WildWaterNET;

class GameReel
{
    public $reelsStrip = [
        'reelStrip1' => [],
        'reelStrip2' => [],
        'reelStrip3' => [],
        'reelStrip4' => [],
        'reelStrip5' => [],
        // WildWaterNET typically uses 5 reels, but original GameReel.php defined 6.
        // We will load all 6 if present in reels.txt, but SlotSettings/Server will likely only use 5.
        'reelStrip6' => []
    ];
    // WildWaterNET's original GameReel.php did not explicitly define separate bonus strips.
    // If reels.txt contains lines like reelStripBonus1=..., they will be loaded here.
    // Otherwise, these will remain empty, and free spins will use the normal reels.
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
        // The reels.txt file should be in the same directory as this GameReel.php file,
        // or in the parent 'examples/WildWaterNET/' directory.
        // For this refactor, we'll expect it in 'examples/WildWaterNET/reels.txt'.
        $reelsFilePath = __DIR__ . '/../../reels.txt'; // Adjusted path

        if (file_exists($reelsFilePath)) {
            $temp = file($reelsFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($temp as $str) {
                $str = explode('=', $str);
                if (count($str) == 2) {
                    $reelName = trim($str[0]);
                    $reelData = explode(',', trim($str[1]));

                    if (isset($this->reelsStrip[$reelName])) {
                        $this->reelsStrip[$reelName] = []; // Clear before populating
                        foreach ($reelData as $elem) {
                            $elem = trim($elem);
                            if ($elem !== '') {
                                $this->reelsStrip[$reelName][] = $elem;
                            }
                        }
                    } elseif (isset($this->reelsStripBonus[$reelName])) {
                        $this->reelsStripBonus[$reelName] = []; // Clear before populating
                        foreach ($reelData as $elem) {
                            $elem = trim($elem);
                            if ($elem !== '') {
                                $this->reelsStripBonus[$reelName][] = $elem;
                            }
                        }
                    }
                }
            }
        } else {
            // Fallback or error handling if reels.txt is not found
            // For now, strips will remain empty. Server/SlotSettings might need defaults.
            // Consider throwing an exception or logging an error.
            // error_log("reels.txt not found at " . $reelsFilePath);
        }
    }
}
?>
