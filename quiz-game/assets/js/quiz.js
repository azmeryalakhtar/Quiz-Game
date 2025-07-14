let currentQuestionId = null;
let currentIndex = 0;
let timerInterval;
let timeLeft = 10;
let answered = false;
let sessionCoins = window.quizConfig.userCoins || 0;
let correctCount = 0;
let currentQuestionAttemptId = null;
let currentToken = null;
let startTime = null;
let isFetchingNext = false;

const { currentLevel, category, difficulty, maxQuestions, username, attemptId, attemptStatus, userCoins, passThreshold, userId } = window.quizConfig;

document.addEventListener("DOMContentLoaded", () => {
    console.log("DOMContentLoaded: Initializing quiz, attemptStatus:", attemptStatus, "attemptId:", attemptId);
    
    // Initialize coin display
    document.getElementById("coin-count").innerText = sessionCoins;

    // Send refresh status to server on page unload
    window.addEventListener('beforeunload', () => {
        if (timeLeft > 0 && !answered && currentQuestionAttemptId && currentToken) {
            // Use navigator.sendBeacon for reliable request on unload
            navigator.sendBeacon("api/next-question", new URLSearchParams({
                question_attempt_id: currentQuestionAttemptId,
                token: currentToken
            }));
        }
    });

    // Check attempt progress to set currentIndex
    fetch("api/check-attempt", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `category=${category}&difficulty=${difficulty}&level=${currentLevel}`
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        return res.json();
    })
    .then(data => {
        console.log("check-attempt response:", data);
        if (data.status === "success") {
            currentIndex = data.answered_count || 0;
            window.quizConfig.attemptId = data.attempt_id; // Update attemptId
            if (data.attempt_status === 'completed' || data.attempt_status === 'failed') {
                console.log("Attempt already completed or failed, updating progress");
                updateProgress(data.attempt_status);
            } else if (currentIndex >= maxQuestions) {
                console.log("Last question reached, calling update-progress");
                updateProgress('completed');
            } else {
                fetchQuestion();
            }
        } else {
            console.log("No attempt found, starting new quiz");
            fetchQuestion();
        }
    })
    .catch(err => {
        console.error("check-attempt error:", err.message);
        fetchQuestion();
    });

    document.getElementById("restart-btn")?.addEventListener("click", () => {
        currentIndex = 0;
        correctCount = 0;
        answered = false;
        clearInterval(timerInterval);
        document.getElementById("question-text").innerText = "Loading question...";
        document.getElementById("feedback-box").style.display = "none";
        document.getElementById("restart-btn").style.display = "none";
        document.getElementById("next-level-btn").style.display = "none";
        document.getElementById("next-btn").style.display = "none";
        document.querySelector(".options").style.display = "block";
        document.getElementById("timer").style.display = "block";
        window.location.href = `/quiz-game/quiz?category=${category}&difficulty=${difficulty}&level=${currentLevel}&restart=true`;
    });

    document.getElementById("next-level-btn")?.addEventListener("click", () => {
        const nextLevel = currentLevel + 1;
        window.location.href = `/quiz-game/quiz?category=${category}&difficulty=${difficulty}&level=${nextLevel}`;
    });

    document.getElementById("next-btn")?.addEventListener("click", () => {
        if (isFetchingNext) return;
        isFetchingNext = true;
        if (timeLeft > 0) {
            document.getElementById("next-btn").disabled = true;
            document.getElementById("next-btn").classList.add("dull");
            isFetchingNext = false;
            return;
        }
        clearInterval(timerInterval);
        if (answered) {
            currentIndex++;
            document.getElementById("feedback-box").style.display = "none";
            document.getElementById("feedback-message").textContent = "";
            currentQuestionId = null;
            currentQuestionAttemptId = null;
            currentToken = null;
            startTime = null;
            answered = false;
            if (currentIndex >= maxQuestions) {
                updateProgress('completed');
            } else {
                fetchQuestion();
            }
            isFetchingNext = false;
        } else {
            handleTimeUp();
            fetch("api/next-question", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `question_attempt_id=${currentQuestionAttemptId}&token=${encodeURIComponent(currentToken)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    currentIndex++;
                    document.getElementById("feedback-box").style.display = "none";
                    document.getElementById("feedback-message").textContent = "";
                    currentQuestionId = null;
                    currentQuestionAttemptId = null;
                    currentToken = null;
                    startTime = null;
                    answered = false;
                    if (currentIndex >= maxQuestions) {
                        updateProgress('completed');
                    } else {
                        fetchQuestion();
                    }
                } else {
                    showFeedback(`❌ Error: ${data.message}`);
                }
            })
            .catch(err => {
                showFeedback(`❌ Error proceeding to next question: ${err.message}`);
            })
            .finally(() => {
                isFetchingNext = false;
            });
        }
    });

    document.getElementById("btn-withdraw")?.addEventListener("click", () => {
        const coinValuePKR = 0.0308;
        const earnedPKR = (sessionCoins * coinValuePKR).toFixed(2);
        alert(`🏦 You've earned ${sessionCoins} coins (~PKR ${earnedPKR}).\nWithdraw available after 3000 coins.`);
    });
});

function fetchQuestion() {
    document.getElementById("next-btn").style.display = "none";
    document.getElementById("next-btn").disabled = true;
    document.getElementById("next-btn").classList.add("dull");

    const postData = `category=${window.quizConfig.category}&difficulty=${window.quizConfig.difficulty}&level=${window.quizConfig.currentLevel}&attempt_id=${window.quizConfig.attemptId}`;
    console.log("fetchQuestion: Sending POST data:", postData);

    fetch("api/get-questions", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: postData
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        return res.json();
    })
    .then(data => {
        console.log("fetchQuestion: Response:", data);
        if (data.status === "success") {
            currentQuestionId = data.question_id;
            currentQuestionAttemptId = data.question_attempt_id;
            currentToken = data.token;
            document.getElementById("question-text").innerHTML = decodeHTML(data.question_text);
            const buttons = document.querySelectorAll(".option-btn");
            if (buttons.length !== 4 || !data.options || data.options.length !== 4) {
                console.error("fetchQuestion: Invalid number of options or buttons:", buttons.length, data.options);
                showFeedback("❌ Error: Invalid number of options.");
                return;
            }
            buttons.forEach((btn, index) => {
                btn.innerHTML = decodeHTML(data.options[index]);
                btn.disabled = false;
                btn.classList.remove("correct", "wrong", "dull");
                btn.removeEventListener("click", btn._clickHandler);
                btn._clickHandler = () => {
                    console.log("Option button clicked:", btn.innerHTML, "Index:", index);
                    submitAnswer(currentQuestionId, btn.innerHTML, currentQuestionAttemptId, currentToken);
                };
                btn.addEventListener("click", btn._clickHandler);
            });
            answered = false;
            startTimer();
        } else if (data.status === "complete") {
            console.log("fetchQuestion: Quiz completed, calling update-progress");
            updateProgress('completed');
        } else {
            console.error("fetchQuestion: Error response:", data.message);
            showFeedback(`❌ Error: ${data.message}`);
        }
    })
    .catch(err => {
        console.error("fetchQuestion: Error:", err.message);
        showFeedback(`❌ Error fetching question: ${err.message}`);
    });
}

function submitAnswer(questionId, selectedAnswer, questionAttemptId, token) {
    if (answered) {
        console.log("submitAnswer: Answer already submitted, ignoring");
        return;
    }
    answered = true;

    console.log("submitAnswer: Sending POST data:", { question_id: questionId, selected_answer: selectedAnswer, question_attempt_id: questionAttemptId, token });

    if (!questionId || !selectedAnswer || !questionAttemptId || !token) {
        console.error("submitAnswer: Missing parameters:", { questionId, selectedAnswer, questionAttemptId, token });
        showFeedback("❌ Error: Missing answer data. Please try again.");
        document.getElementById("next-btn").style.display = "inline-block";
        document.getElementById("next-btn").disabled = false;
        document.getElementById("next-btn").classList.remove("dull");
        return;
    }

    fetch("api/submit-answer", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `question_id=${questionId}&selected_answer=${encodeURIComponent(selectedAnswer)}&token=${encodeURIComponent(token)}&question_attempt_id=${questionAttemptId}`,
        signal: AbortSignal.timeout(5000)
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        return res.json();
    })
    .then(data => {
        console.log("submitAnswer: Response:", data);
        const buttons = document.querySelectorAll(".option-btn");
        buttons.forEach(btn => {
            btn.disabled = true;
            if (data.status === "success" && btn.innerHTML && data.correct_answer) {
                if (btn.innerHTML === decodeHTML(data.correct_answer)) {
                    btn.classList.add("correct");
                } else if (btn.innerHTML === decodeHTML(selectedAnswer)) {
                    btn.classList.add(data.is_correct ? "correct" : "wrong");
                }
            }
        });

        if (data.status === "success") {
            if (data.is_correct) {
                correctCount++;
                showFeedback(`✅ Correct! ${username}.`);
                confetti?.({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
                const correctSound = document.getElementById("correct-sound");
                conditionalSoundPlay(correctSound);
            } else {
                showFeedback(`❌ Wrong! Correct answer: ${decodeHTML(data.correct_answer)}`);
                const wrongSound = document.getElementById("wrong-sound");
                conditionalSoundPlay(wrongSound);
                triggerBrokenHeartRain();
                const box = document.querySelector(".question-box");
                box.classList.add("shake");
                setTimeout(() => box.classList.remove("shake"), 400);
            }
            document.getElementById("next-btn").style.display = "inline-block";
            document.getElementById("next-btn").disabled = false;
            document.getElementById("next-btn").classList.remove("dull");
        } else {
            console.error("submitAnswer: Error response:", data.message);
            if (data.message === "Time limit exceeded.") {
                showFeedback(`⏰ Time's up! Please try the next question.`);
                handleTimeUp();
            } else {
                showFeedback(`❌ Error submitting answer: ${data.message}`);
            }
            document.getElementById("next-btn").style.display = "inline-block";
            document.getElementById("next-btn").disabled = false;
            document.getElementById("next-btn").classList.remove("dull");
        }
    })
    .catch(err => {
        console.error("submitAnswer: Fetch error:", err.message);
        showFeedback(`❌ Error submitting answer: ${err.message}`);
        document.getElementById("next-btn").style.display = "inline-block";
        document.getElementById("next-btn").disabled = false;
        document.getElementById("next-btn").classList.remove("dull");
    });
}

function showResults(isPassed, correctCount, totalQuestions, coinsAdded = 0) {
    clearInterval(timerInterval);
    console.log(`showResults: isPassed=${isPassed}, correctCount=${correctCount}, totalQuestions=${totalQuestions}, coinsAdded=${coinsAdded}`);
    const questionText = document.getElementById("question-text");
    questionText.style.color = isPassed ? "green" : "red";
    questionText.innerText = isPassed 
        ? `You got ${correctCount} out of ${totalQuestions} correct. ${coinsAdded > 0 ? `Earned ${coinsAdded} coins!` : ''}`
        : `😔 Game Over! You got ${correctCount} out of ${totalQuestions} correct.`;
    document.getElementById(isPassed ? "next-level-btn" : "restart-btn").style.display = "inline-block";
    document.getElementById("feedback-box").style.display = "block";
    document.getElementById("feedback-message").textContent = "";
    document.getElementById("next-btn").style.display = "none";
    document.querySelector(".options").style.display = "none";
    document.getElementById("timer").style.display = "none";

    // Update coin display immediately
    if (isPassed && coinsAdded > 0) {
        sessionCoins += coinsAdded;
        const coinCountEl = document.getElementById("coin-count");
        const fromBox = document.getElementById("question-text"); // or change if needed
        coinCountEl.innerText = sessionCoins;
    
        // ✅ Debug log to verify coin animation is being triggered
        console.log("✅ Triggering flying coin animation:", {
            coinsAdded,
            fromBoxExists: !!fromBox,
            toBoxExists: !!coinCountEl
        });
    
        animateMultipleCoins(fromBox, coinCountEl, Math.min(coinsAdded / 10, 10)); // 1 coin per 10 added, up to 10
    
        console.log(`showResults: Updated sessionCoins to ${sessionCoins} with coinsAdded=${coinsAdded}`);
    }
    
    
// Fetch updated coins to confirm (with slight delay to wait for DB update)
setTimeout(() => {
    fetch("api/get-user-coins", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: ""
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        return res.json();
    })
    .then(data => {
        if (data.status === "success") {
            sessionCoins = data.coins;
            document.getElementById("coin-count").innerText = sessionCoins;
            console.log(`✅ Coins updated: ${sessionCoins}`);
        } else {
            console.error("get-user-coins error:", data.message);
        }
    })
    .catch(err => {
        console.error("get-user-coins fetch error:", err.message);
    });
}, 500); // Delay ensures DB finishes first

if (isPassed) {
    const badge = document.getElementById("level-badge");
    if (badge) badge.style.display = "block";
}
}

function triggerBrokenHeartRain(count = 15) {
    for (let i = 0; i < count; i++) {
        const heart = document.createElement("div");
        heart.classList.add("falling-heart");
        heart.innerHTML = "💔";
        heart.style.left = Math.random() * 100 + "vw";
        heart.style.animationDuration = 1.5 + Math.random() * 1.5 + "s";
        document.body.appendChild(heart);
        setTimeout(() => heart.remove(), 3000);
    }
}

function animateMultipleCoins(fromEl, toEl, count = 6) {
    if (!fromEl || !toEl) return;

    const fromRect = fromEl.getBoundingClientRect();
    const toRect = toEl.getBoundingClientRect();

    for (let i = 0; i < count; i++) {
        const coin = document.createElement("img");
        coin.src = "/quiz-game/images/gold_coin.svg";
        coin.className = "flying-coin";
        coin.style.left = `${fromRect.left + fromRect.width / 2}px`;
        coin.style.top = `${fromRect.top + fromRect.height / 2}px`;
        document.body.appendChild(coin);

        const dx = (toRect.left + toRect.width / 2) - (fromRect.left + fromRect.width / 2);
        const dy = (toRect.top + toRect.height / 2) - (fromRect.top + fromRect.height / 2);
        const spreadX = (Math.random() - 0.5) * 100; // coin spread
        const spreadY = (Math.random() - 0.5) * 100;

        coin.animate([
            { transform: `translate(0, 0) scale(1)`, opacity: 1 },
            { transform: `translate(${spreadX}px, ${spreadY}px) scale(1.2)`, opacity: 1, offset: 0.5 },
            { transform: `translate(${dx}px, ${dy}px) scale(0.5)`, opacity: 0 }
        ], {
            duration: 1000 + Math.random() * 300,
            easing: 'ease-in-out'
        });

        setTimeout(() => coin.remove(), 1300);
    }
}

function handleTimeUp() {
    clearInterval(timerInterval);
    answered = true;
    const buttons = document.querySelectorAll(".option-btn");
    buttons.forEach(btn => btn.disabled = true);

    document.getElementById("feedback-box").style.display = "block";
    document.getElementById("next-btn").style.display = "inline-block";
    document.getElementById("next-btn").disabled = false;
    document.getElementById("next-btn").classList.remove("dull");

    // Update question attempt status to timed_out
    fetch("api/check-timer", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `question_attempt_id=${currentQuestionAttemptId}&token=${encodeURIComponent(currentToken)}`
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        return res.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("check-timer: Invalid JSON response:", text);
                throw new Error(`Invalid JSON response: ${text}`);
            }
        });
    })
    .then(data => {
        if (data.status !== "success") {
            console.error("check-timer error:", data.message);
            showFeedback(`⏰ Time's up! Error updating status: ${data.message}`);
        } else {
            console.log("check-timer success: Question attempt marked as timed_out, time_left:", data.time_left);
        }
    })
    .catch(err => {
        console.error("check-timer fetch error:", err.message);
        showFeedback(`⏰ Time's up! Error updating status: ${err.message}`);
    });

    // Fetch correct answer
    fetch(`api/get-correct-answer?question_id=${currentQuestionId}`)
        .then(res => {
            if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
            return res.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("get-correct-answer: Invalid JSON response:", text);
                    throw new Error(`Invalid JSON response: ${text}`);
                }
            });
        })
        .then(data => {
            if (data.status === "success") {
                const correctAnswer = data.correct_answer;
                buttons.forEach(btn => {
                    if (btn.innerHTML === decodeHTML(correctAnswer)) {
                        btn.classList.add("correct");
                    }
                });
                const correctSound = document.getElementById("correct-auto");
                if (correctSound) {
                    clearInterval(timerInterval);
                    correctSound.currentTime = 0;
                    conditionalSoundPlay(correctSound);
                }
                showFeedback(`⏰ Time's up! Correct answer is: ${decodeHTML(correctAnswer)}`);
            } else {
                showFeedback(`⏰ Time's up! Error: ${data.message}`);
            }
        })
        .catch(err => {
            console.error("get-correct-answer fetch error:", err.message);
            showFeedback(`⏰ Time's up! Unable to fetch correct answer: ${err.message}`);
        });
}

function conditionalSoundPlay(sound) {
    sound.play().catch(() => {});
}

function showFeedback(message) {
    document.getElementById("feedback-message").textContent = message;
    document.getElementById("feedback-box").style.display = "block";
}

function decodeHTML(html) {
    const txt = document.createElement("textarea");
    txt.innerHTML = html;
    return txt.value;
}

function startTimer() {
    if (!currentQuestionAttemptId || !currentToken) {
        console.error("Timer error: Missing question attempt ID or token", { currentQuestionAttemptId, currentToken });
        showFeedback("❌ Timer error: Invalid question attempt or token.");
        return;
    }

    timeLeft = 10;
    startTime = new Date().getTime();
    updateTimer();
    clearInterval(timerInterval);
    console.log("Starting timer for question_attempt_id:", currentQuestionAttemptId);
    timerInterval = setInterval(() => {
        const elapsed = (new Date().getTime() - startTime) / 1000;
        timeLeft = Math.max(0, 10 - elapsed);
        updateTimer();

        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            timeLeft = 0;
            updateTimer();
            document.getElementById("next-btn").disabled = false;
            document.getElementById("next-btn").classList.remove("dull");
            if (!answered) {
                handleTimeUp();
            }
        } else {
            document.getElementById("next-btn").disabled = true;
            document.getElementById("next-btn").classList.add("dull");
        }
    }, 100);
}

function updateTimer() {
    document.getElementById("timer").textContent = `⏱️ ${Math.floor(timeLeft)}s`;
}

function updateProgress(status) {
    console.log("updateProgress: Sending status=", status);
    fetch("api/update-progress", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `category=${category}&difficulty=${difficulty}&level=${currentLevel}&status=${status}&attempt_id=${window.quizConfig.attemptId}`
    })
    .then(res => res.json())
    .then(data => {
        console.log("update-progress response:", data);
        if (data.status === "success") {
            correctCount = data.correct_count;

            // ✅ Pass coins_added (default to 0 if missing)
            const coinsEarned = data.coins_added || 0;

            showResults(data.is_passed, data.correct_count, data.total_questions, coinsEarned);
        } else {
            showFeedback(`❌ Error saving progress: ${data.message}`);
        }
    })
    .catch(err => {
        console.error("updateProgress: Error:", err.message);
        showFeedback(`❌ Error updating progress: ${err.message}`);
    });
}


if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/quiz-game/service-worker.js');
}

document.addEventListener('DOMContentLoaded', () => {
    const installBtn = document.getElementById("install-app");
    if (installBtn) {
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.style.display = 'block';
            installBtn.addEventListener('click', () => {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                    }
                    deferredPrompt = null;
                });
            });
        });
    }
});


// full screen
const fullscreenBtn = document.getElementById('fullscreen-btn');
  const fullscreenIcon = document.getElementById('fullscreen-icon');

  if (fullscreenBtn && fullscreenIcon) {
    fullscreenBtn.addEventListener('click', () => {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen()
          .then(() => {
            fullscreenIcon.src = "/quiz-game/images/exit-fullscreen-icon.svg";
            fullscreenIcon.alt = "Exit Fullscreen";
          })
          .catch(err => {
            alert(`Error attempting to enable full-screen mode: ${err.message}`);
          });
      } else {
        document.exitFullscreen()
          .then(() => {
            fullscreenIcon.src = "/quiz-game/images/fullscreen-icon.svg";
            fullscreenIcon.alt = "Enter Fullscreen";
          });
      }
    });

    // Handle Esc key or browser exit fullscreen
    document.addEventListener('fullscreenchange', () => {
      if (!document.fullscreenElement) {
        fullscreenIcon.src = "/quiz-game/images/fullscreen-icon.svg";
        fullscreenIcon.alt = "Enter Fullscreen";
      }
    });
  } else {
    console.warn("Fullscreen button or icon not found in DOM.");
  }
