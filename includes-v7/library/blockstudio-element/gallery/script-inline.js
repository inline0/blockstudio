document.addEventListener("DOMContentLoaded", () => {
  document
    .querySelectorAll(".blockstudio-element-gallery")
    .forEach((masonry) => {
      init(masonry);
    });
});

function loadImages(ref) {
  return new Promise(function (resolve) {
    ref.img = new Image();

    ref.img.onload = () => {
      ref.status = "OK";
      resolve();
    };
    ref.img.onerror = () => {
      ref.status = "bad";
      resolve();
    };

    ref.img.src = ref.path;
  });
}

function resizeGridItem(grid, item) {
  const image = item.querySelector(".blockstudio-element-gallery__image");
  const rowHeight = parseInt(
    window.getComputedStyle(grid).getPropertyValue("grid-auto-rows")
  );
  const rowGap = parseInt(
    window.getComputedStyle(grid).getPropertyValue("grid-row-gap")
  );
  const rowSpan = Math.ceil(
    (image.getBoundingClientRect().height + rowGap) / (rowHeight + rowGap)
  );
  item.style.gridRowEnd = "span " + rowSpan;
}

function resizeAllGridItems(grid) {
  const allItems = grid.getElementsByClassName(
    "blockstudio-element-gallery__content"
  );

  for (let x = 0; x < allItems.length; x++) {
    resizeGridItem(grid, allItems[x]);
  }
}

function init(gallery) {
  if (!gallery.classList.contains("blockstudio-element-gallery--masonry"))
    return false;

  window.addEventListener("resize", () => resizeAllGridItems(gallery));

  const images = gallery.querySelectorAll(
    ".blockstudio-element-gallery__image"
  );
  const imageUrls = [];
  images.forEach((item) => {
    imageUrls.push(
      loadImages({
        img: null,
        path: item.getAttribute("src"),
        status: "none",
      })
    );
  });

  Promise.all(imageUrls).then(function () {
    resizeAllGridItems(gallery);
    images.forEach((image) => {
      image.style.height = "100%";
    });
    gallery.classList.add("loaded");
  });
}

function initAll() {
  document.querySelectorAll(".blockstudio-element-gallery").forEach(init);
}

["DOMContentLoaded", "blockstudio/blockstudio-element/gallery/loaded"].forEach(
  (event) => document.addEventListener(event, initAll)
);
