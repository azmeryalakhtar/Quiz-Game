const canvas = document.getElementById('stars-canvas');
const ctx = canvas.getContext('2d');

// Set canvas size to match window
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

// Handle window resize
window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    initStars();
});

// Star object
class Star {
    constructor() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.size = Math.random() * 2 + 1;
        this.speedX = Math.random() * 0.5 - 0.25;
        this.speedY = Math.random() * 0.5 - 0.25;
        this.opacity = Math.random() * 0.5 + 0.5;
    }

    update() {
        this.x += this.speedX;
        this.y += this.speedY;

        // Bounce off edges
        if (this.x < 0 || this.x > canvas.width) this.speedX *= -1;
        if (this.y < 0 || this.y > canvas.height) this.speedY *= -1;

        // Twinkle effect
        this.opacity += Math.random() * 0.02 - 0.01;
        if (this.opacity < 0.2) this.opacity = 0.2;
        if (this.opacity > 1) this.opacity = 1;
    }

    draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
        ctx.fill();
    }
}

// Gradient animation
let gradientTime = 0;
const gradientColors = [
    { r: 30, g: 30, b: 60 },  // Dark blue
    { r: 60, g: 30, b: 90 },  // Purple
    { r: 20, g: 40, b: 70 },  // Deep indigo
    { r: 40, g: 20, b: 80 }   // Bluish-purple
];

function createGradient() {
    const t = (Math.sin(gradientTime) + 1) / 2; // Smooth oscillation between 0 and 1
    const index = Math.floor(t * (gradientColors.length - 1));
    const frac = (t * (gradientColors.length - 1)) % 1;
    const color1 = gradientColors[index];
    const color2 = gradientColors[(index + 1) % gradientColors.length];

    // Interpolate colors
    const r = color1.r + frac * (color2.r - color1.r);
    const g = color1.g + frac * (color2.g - color1.g);
    const b = color1.b + frac * (color2.b - color1.b);

    const gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
    gradient.addColorStop(0, `rgb(${r}, ${g}, ${b})`);
    gradient.addColorStop(1, `rgb(${r * 0.7}, ${g * 0.7}, ${b * 0.7})`);
    return gradient;
}

// Initialize stars
const stars = [];
function initStars() {
    stars.length = 0;
    const numStars = Math.floor((canvas.width * canvas.height) / 5000); // Adjust density
    for (let i = 0; i < numStars; i++) {
        stars.push(new Star());
    }
}

// Animation loop
function animate() {
    // Update gradient
    gradientTime += 0.005; // Slow transition speed
    ctx.fillStyle = createGradient();
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Draw stars
    stars.forEach(star => {
        star.update();
        star.draw();
    });
    requestAnimationFrame(animate);
}

// Start animation
initStars();
animate();

// submit button vibration
document.querySelectorAll('button[type="submit"]').forEach(button => {
    button.addEventListener('click', () => {
        if (navigator.vibrate) {
            navigator.vibrate(50); // Short vibration on tap
        }
    });
});