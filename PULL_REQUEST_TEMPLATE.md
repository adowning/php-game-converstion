## Game Conversion Pull Request

### Game Converted
* **Name:** `[Enter Game Name Here]`

---

### Summary of Changes
Please provide a brief overview of the changes made.
- Refactored `Server.php` to be stateless and return a structured JSON response.
- Updated `SlotSettings.php` to extend `BaseSlotSettings` and removed all direct database/framework dependencies.
- Confirmed `GameReel.php` and `reels.txt` are correctly implemented.
- Created `index.php` as the clean entry point.
- *(Add any other notable changes or challenges here)*

---

### ⚠️ Testing Checklist (Non-Negotiable)
I have manually tested the following scenarios using a local test script to ensure the game logic is correct before requesting a review.

- [ ] **Init Action:** The game initializes correctly, returning the default state, balance, and bet levels.
- [ ] **Regular Spin (No Win):** A standard spin with `desiredWinType: 'none'` completes and returns the correct reel positions and updated balance.
- [ ] **Standard Win Spin:** A spin with `desiredWinType: 'win'` correctly returns winning lines, calculates the `totalWin`, and updates the balance.
- [ ] **Bonus/Scatter Trigger:** A spin with `desiredWinType: 'bonus'` successfully triggers the free spin mode (or other bonus feature) and sets the correct game state data (`totalFreeGames`, etc.).
- [ ] **Free Spin Action:** A `freespin` action correctly uses the stored bet/lines, decrements `currentFreeGames`, and accumulates the `bonusWin`.
- [ ] **Feature-Specific Logic:** *(Optional)* If the game has unique features (e.g., respins, special wilds), I have tested them specifically.

