const levelOptions = {
    1: "Level 1 (10 Questions)",
    2: "Level 2 (20 Questions)",
    3: "Level 3 (30 Questions)",
    4: "Level 4 (40 Questions)",
    5: "Level 5 (50 Questions)"
  };
  
  function updateLevelDropdown() {
    const category = document.getElementById("category").value;
    const difficulty = document.getElementById("difficulty").value;
  
    fetch(`/quiz-game/api/get-unlocked-levels?category=${category}&difficulty=${difficulty}`)
      .then(response => response.json())
      .then(unlockedLevels => {
        console.log("Unlocked levels:", unlockedLevels);
  
        const levelSelect = document.getElementById("level");
        levelSelect.innerHTML = "";
  
        Object.entries(levelOptions).forEach(([level, label]) => {
          const option = document.createElement("option");
          option.value = level;
          const isUnlocked = unlockedLevels.includes(parseInt(level));
          option.textContent = `${isUnlocked ? "🔓" : "🔒"} ${label}`;
          if (!isUnlocked) option.disabled = true;
          levelSelect.appendChild(option);
        });
      });
  }
  
  // Load levels initially and on change
  document.getElementById("category").addEventListener("change", updateLevelDropdown);
  document.getElementById("difficulty").addEventListener("change", updateLevelDropdown);
  window.addEventListener("DOMContentLoaded", updateLevelDropdown);