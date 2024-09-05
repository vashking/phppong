<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        canvas {
            position: absolute;
            top: 0;
            left: 0;
            transform: scaleX(-1);
        }
    </style>
    <script src="/test/files/tfjs.js"></script>
    <script src="/test/files/hands.js"></script>
    <script src="/test/files/camera_utils.js"></script>
</head>
<body>
    <video id="video" width="640" height="480" autoplay></video>
    <canvas id="canvas" width="720" height="500"></canvas>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');

        let ball = {
            x: 320, 
            y: 240,
            radius: 10, 
            dx: 2,
            dy: 2
        };

        async function setupCamera() {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: true
            });
            video.srcObject = stream;

            return new Promise((resolve) => {
                video.onloadedmetadata = () => {
                    resolve(video);
                };
            });
        }

        async function runHandPose() {
            const hands = new Hands({
                locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`
            });

            hands.setOptions({
                maxNumHands: 2,
                modelComplexity: 1,
                minDetectionConfidence: 0.7,
                minTrackingConfidence: 0.5
            });

            hands.onResults(onResults);

            await setupCamera();
            video.play();

            const camera = new Camera(video, {
                onFrame: async () => {
                    await hands.send({ image: video });
                },
                width: 640,
                height: 480
            });
            camera.start();
        }

        function onResults(results) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(results.image, 0, 0, canvas.width, canvas.height);
            drawBall();
            updateBallPosition(null);

            if (results.multiHandLandmarks) {
                results.multiHandLandmarks.forEach(handLandmarks => {
                    drawHand(handLandmarks);
                });

                if (results.multiHandLandmarks.length >= 2) {
                    const hand1 = results.multiHandLandmarks[0];
                    const hand2 = results.multiHandLandmarks[1];
                    if (hand1[8] && hand2[8]) {
                        drawLineBetweenFingers(hand1, hand2);
                    }

                    const line = {
                        startX: results.multiHandLandmarks[0][8].x * canvas.width,
                        startY: results.multiHandLandmarks[0][8].y * canvas.height,
                        endX: results.multiHandLandmarks[1][8].x * canvas.width,
                        endY: results.multiHandLandmarks[1][8].y * canvas.height,
                    };
                    updateBallPosition(line);
                }
            }
        }

        function drawHand(handLandmarks) {
            for (let i = 0; i < handLandmarks.length; i++) {
                const {x, y} = handLandmarks[i];

                ctx.beginPath();
                ctx.arc(x * canvas.width, y * canvas.height, 5, 0, 2 * Math.PI);
                ctx.fillStyle = 'blue';
                ctx.fill();

                if (i < handLandmarks.length - 1) {
                    ctx.beginPath();
                    ctx.moveTo(handLandmarks[i].x * canvas.width, handLandmarks[i].y * canvas.height);
                    ctx.lineTo(handLandmarks[i + 1].x * canvas.width, handLandmarks[i + 1].y * canvas.height);
                    ctx.strokeStyle = 'blue';
                    ctx.lineWidth = 2;
                    ctx.stroke();
                }
            }
        }

        function drawLineBetweenFingers(hand1, hand2) {
            const finger1 = hand1[8];
            const finger2 = hand2[8];

            ctx.beginPath();
            ctx.moveTo(finger1.x * canvas.width, finger1.y * canvas.height);
            ctx.lineTo(finger2.x * canvas.width, finger2.y * canvas.height);
            ctx.strokeStyle = 'red';
            ctx.lineWidth = 2;
            ctx.stroke();
        }

        function drawBall() {
            ctx.beginPath();
            ctx.arc(ball.x, ball.y, ball.radius, 0, 2 * Math.PI);
            ctx.fillStyle = 'green';
            ctx.fill();
            ctx.closePath();
        }

        function updateBallPosition(line) {
            ball.x += ball.dx;
            ball.y += ball.dy;

            if (ball.x + ball.radius > canvas.width || ball.x - ball.radius < 0) {
                ball.dx *= -1; 
            }
            if (ball.y + ball.radius > canvas.height || ball.y - ball.radius < 0) {
                ball.dy *= -1;
            }

            if (line) {
                if (isCollidingWithLine(ball, line)) {
                    ball.dy *= -1;
                }
            }
        }

        function isCollidingWithLine(ball, line) {
            const {startX, startY, endX, endY} = line;

            const lineLength = Math.hypot(endX - startX, endY - startY);
            const projection = ((ball.x - startX) * (endX - startX) + (ball.y - startY) * (endY - startY)) / (lineLength * lineLength);
            const closestX = startX + projection * (endX - startX);
            const closestY = startY + projection * (endY - startY);

            const distance = Math.hypot(ball.x - closestX, ball.y - closestY);

            return distance < ball.radius;
        }

        runHandPose();
    </script>
</body>
</html>
