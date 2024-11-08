const floatingIcons = document.getElementById('floating-icons');

let isDragging = false;
let currentX;
let currentY;
let initialX;
let initialY;
let xOffset = 0;
let yOffset = 0;


floatingIcons.addEventListener('mousedown', dragStart);
floatingIcons.addEventListener('mouseup', dragEnd);
floatingIcons.addEventListener('mousemove', drag);

function dragStart(e) {
  if (e.target === floatingIcons) {
    isDragging = true;

    initialX = e.clientX - xOffset;
    initialY = e.clientY - yOffset;
  }
}

function dragEnd(e) {
  initialX = currentX;
  initialY = currentY;

  isDragging = false;
}

function drag(e) {
  if (isDragging) {
    e.preventDefault();
  
    currentX = e.clientX - initialX;
    currentY = e.clientY - initialY;

    xOffset = currentX;
    yOffset = currentY;

    setTranslate(currentX, currentY, floatingIcons);
  }
}

function setTranslate(xPos, yPos, el) {
  el.style.transform = "translate3d(" + xPos + "px, " + yPos + "px, 0)";
}

