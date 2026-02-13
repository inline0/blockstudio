function init(imageComparison) {
  let position;
  let steps = 10;
  let start = Number(
    imageComparison.style
      .getPropertyValue("--blockstudio-element-image-comparison--start")
      .replace("px", "")
  );

  let active = false;

  let width = imageComparison.offsetWidth;
  const before = imageComparison.querySelector(
    ".blockstudio-element-image-comparison__container--before"
  );
  const beforeImg = before.querySelector("img");
  const after = imageComparison.querySelector(
    ".blockstudio-element-image-comparison__container--after"
  );
  const afterImg = after.querySelector("img");

  const scroller = imageComparison.querySelector(
    ".blockstudio-element-image-comparison__scroller"
  );

  window.addEventListener("resize", () => {
    width = imageComparison.offsetWidth;
    move(start);
  });

  ["mousedown", "touchstart"].forEach((item) => {
    scroller.addEventListener(item, () => {
      active = true;
    });
  });

  ["mouseup", "mouseleave", "touchend", "touchcancel"].forEach((item) => {
    document.body.addEventListener(item, () => {
      active = false;
    });
  });

  ["mousemove", "touchmove"].forEach((item) => {
    document.body.addEventListener(item, (e) => {
      mover(e);
    });
  });

  function move(x) {
    const transform = Math.max(0, Math.min(x, before.offsetWidth));
    after.style.width = transform + 2 + "px";
    scroller.style.left = transform - 25 + "px";
    afterImg.style.width = width + "px";

    if (x >= 2 && x <= width) {
      position = x;
    }
  }

  function mover(e) {
    if (!active) return;
    let x = e.pageX;
    x -= before.getBoundingClientRect().left;
    move(x);
  }

  function moveLeft() {
    if (position >= 2) {
      move(position - steps);
    }
  }

  function moveRight() {
    if (position <= width) {
      move(position + steps);
    }
  }

  move(start);
  setTimeout(move, 100);
  imageComparison.classList.add("loaded");
}

function initAll() {
  document
    .querySelectorAll(".blockstudio-element-image-comparison")
    .forEach(init);
}

[
  "DOMContentLoaded",
  "blockstudio/blockstudio-element/image-comparison/loaded",
].forEach((event) => document.addEventListener(event, initAll));
