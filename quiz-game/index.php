<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Games - Phones Dukan</title>
  <style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: Arial, sans-serif;
  background-color: #f4f4f4;
  background-image: url('assets/images/bg_image.webp');
  background-repeat: repeat;
  background-size: auto;
  position: relative;
  z-index: 0;
}

/* Overlay */
body::before {
  content: "";
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.2);
  z-index: -1;
}

/* Heading */
h1 {
  text-align: center;
  color: #fff;
  font-size: 22px;
  margin-bottom: 20px;
}

/* Games container */
.games {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  padding: 10px;
}

/* Quiz game block */
.quiz-game {
  width: 100%;
  max-width: 320px;
  margin-bottom: 20px;
}
/* Link wrapper */
.game-card-link {
  text-decoration: none;
  color: inherit;
  display: inline-block;
  width: 100%;
  max-width: 320px;
  margin: 10px;
}

/* Game card */
.game-card {
  background-color: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  overflow: hidden;
  transition: transform 0.2s ease;
  display: flex;
  flex-direction: column;
}

.game-card:hover {
  transform: scale(1.03);
}

/* Game image */
.game-card img {
  width: 100%;
  height: auto;
  display: block;
  margin: 0;
  padding: 0;
}

/* Play button */
.game-title-button {
  background-color: #28a745;
  color: #fff;
  padding: 14px;
  text-align: center;
  font-size: 18px;
  font-weight: bold;
  border-top: 1px solid #ddd;
  margin: 0;
  border-bottom-left-radius: 12px;
  border-bottom-right-radius: 12px;
}

.game-title-button:hover {
  background-color: #218838;
}

/* ========================= */
/* 📱 Media Queries Section  */
/* ========================= */

/* Tablets (min-width: 600px) */
@media (min-width: 600px) {

  .head {
    background-color: #000;
    padding: 20px 10px;
}

  h1 {
    font-size: 51px;
    margin-bottom: 10px;
}

p {
    text-align: center;
    font-weight: bold;
    color: #FFC107;
    font-size: 36px;
    margin-bottom: 20px;
}

.games {
    padding: 5px;
    justify-content: flex-start;
}

.game-card {
    margin: 5px;
}

.quiz-game {
  width: 100%;
  max-width: 50%;
  margin-bottom: 20px;
}

  .game-card-link {
    max-width: 100%;
    margin: 0px;
  }

  .game-title-button {
    font-size: 30px;
    padding: 16px;
  }
}

/* Desktops (min-width: 992px) */
@media (min-width: 992px) {

  h1 {
    font-size: 30px;
  }

  .game-card-link {
    max-width: 280px;
  }

  .game-title-button {
    font-size: 20px;
    padding: 18px;
  }
}

  </style>
</head>
<body>
<div class="head">
  <p>No Investment Needed – Play & Earn Money!</p>
  </div>
  <div class="games">
    <div class="quiz-game">
  <a href="/play/quiz" class="game-card-link">
    <div class="game-card">
      <img src="assets/images/quiz-game.png" alt="Quiz Game">
      <div class="game-title-button">▶ Play Quiz Game</div>
    </div>
  </a>
  </div>
</div>

</body>
</html>
