import Swiper from "blockstudio/swiper@8.4.5";

function init(slider) {
  const options = JSON.parse(slider.getAttribute("data-options"));

  const swiper = new Swiper(slider, {
    wrapperClass: "blockstudio-element-slider__wrapper",
    containerModifierClass: "blockstudio-element-slider--",
    slideClass: "blockstudio-element-slider__slide",
    ...options,
  });

  const prev = slider.querySelector(
    ".blockstudio-element-slider__navigation--prev"
  );
  const next = slider.querySelector(
    ".blockstudio-element-slider__navigation--next"
  );

  function setPagination(index) {
    const bullets = slider.querySelectorAll(
      ".blockstudio-element-slider__pagination-bullet"
    );
    bullets.forEach((item) => {
      item.classList.remove("is-active");
    });
    bullets[index].classList.add("is-active");
  }

  function setNavigation() {
    const isLoop = swiper.params.loop;

    if (isLoop) {
      return;
    }

    if (swiper.isBeginning) {
      prev.classList.add("is-disabled");
    } else {
      prev.classList.remove("is-disabled");
    }

    if (swiper.isEnd) {
      next.classList.add("is-disabled");
    } else {
      next.classList.remove("is-disabled");
    }
  }

  swiper.on("slideChange", (swiper) => {
    setNavigation();
    setPagination(swiper.realIndex);
  });

  prev.addEventListener("click", () => swiper.slidePrev());
  next.addEventListener("click", () => swiper.slideNext());

  slider.classList.add("loaded");
}

function initAll() {
  document.querySelectorAll(".blockstudio-element-slider").forEach(init);
}

["DOMContentLoaded", "blockstudio/blockstudio-element/slider/loaded"].forEach(
  (event) => document.addEventListener(event, initAll)
);
