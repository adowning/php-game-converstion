<?php
namespace app\games\NET\SpaceWarsNET;

class GameReel
{
    public $reelsStrip = [
        'reelStrip1' => [],
        'reelStrip2' => [],
        'reelStrip3' => [],
        'reelStrip4' => [],
        'reelStrip5' => [],
        // SpaceWars has 5 reels, so no reelStrip6 for main game
    ];
    public $reelsStripBonus = [
        'reelStripBonus1' => [],
        'reelStripBonus2' => [],
        'reelStripBonus3' => [],
        'reelStripBonus4' => [],
        'reelStripBonus5' => [],
        // And 5 for bonus, if defined in reels.txt
    ];

    public function __construct()
    {
        $filePath = __DIR__ . '/reels.txt';
        if (!file_exists($filePath)) {
            error_log("CRITICAL: reels.txt not found in " . __DIR__ . " for SpaceWarsNET. Reels will be empty.");
            return;
        }

        $fileContent = file($filePath);
        if ($fileContent === false) {
            error_log("CRITICAL: Could not read reels.txt in " . __DIR__ . " for SpaceWarsNET. Reels will be empty.");
            return;
        }

        foreach ($fileContent as $string) {
            $string = trim($string);
            if (empty($string) || strpos($string, '=') === false) {
                if(!empty($string)) error_log("Malformed line or missing '=' in SpaceWarsNET reels.txt: " . $string);
                continue;
            }

            list($reelKey, $reelValuesString) = explode('=', $string, 2);
            $reelKey = trim($reelKey);
            $reelValues = explode(',', trim($reelValuesString));

            $targetPropertyArray = null;
            if (array_key_exists($reelKey, $this->reelsStrip)) {
                $targetPropertyArray = &$this->reelsStrip[$reelKey];
            } elseif (array_key_exists($reelKey, $this->reelsStripBonus)) {
                $targetPropertyArray = &$this->reelsStripBonus[$reelKey];
            } else {
                // Optional: Log if a key in reels.txt doesn't match expected properties
                // error_log("Unknown reel key in SpaceWarsNET reels.txt: " . $reelKey);
            }

            if ($targetPropertyArray !== null) {
                foreach ($reelValues as $value) {
                    $trimmedValue = trim($value);
                    if ($trimmedValue !== '') {
                        $targetPropertyArray[] = $trimmedValue;
                    }
                }
            }
        }
    }
}
?>
