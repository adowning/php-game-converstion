<?php
namespace app\games\NET\WingsOfRichesNET;

class GameReel
{
    public $reelsStrip = [
        'reelStrip1' => [],
        'reelStrip2' => [],
        'reelStrip3' => [],
        'reelStrip4' => [],
        'reelStrip5' => [],
        // Wings of Riches has 5 reels, so no reelStrip6 for main game
    ];
    public $reelsStripBonus = [
        'reelStripBonus1' => [],
        'reelStripBonus2' => [],
        'reelStripBonus3' => [],
        'reelStripBonus4' => [],
        'reelStripBonus5' => [],
        // And 5 for bonus
    ];

    public function __construct()
    {
        $filePath = __DIR__ . '/reels.txt';
        if (!file_exists($filePath)) {
            // Log error or throw exception if reels.txt is critical and not found
            // For now, properties will remain empty if file doesn't exist.
            // This might happen during initial setup before reels.txt is copied.
            error_log("reels.txt not found in " . __DIR__);
            return;
        }

        $fileContent = file($filePath);
        if ($fileContent === false) {
            error_log("Could not read reels.txt in " . __DIR__);
            return;
        }

        foreach ($fileContent as $string) {
            $string = trim($string);
            if (empty($string)) {
                continue;
            }

            $parts = explode('=', $string);
            if (count($parts) !== 2) {
                // Log malformed line
                error_log("Malformed line in reels.txt: " . $string);
                continue;
            }

            $reelKey = trim($parts[0]);
            $reelValues = explode(',', trim($parts[1]));

            $targetProperty = null;
            if (array_key_exists($reelKey, $this->reelsStrip)) {
                $targetProperty = 'reelsStrip';
            } elseif (array_key_exists($reelKey, $this->reelsStripBonus)) {
                $targetProperty = 'reelsStripBonus';
            }

            if ($targetProperty) {
                foreach ($reelValues as $value) {
                    $trimmedValue = trim($value);
                    if ($trimmedValue !== '') {
                        $this->{$targetProperty}[$reelKey][] = $trimmedValue;
                    }
                }
            }
        }
    }
}
?>
