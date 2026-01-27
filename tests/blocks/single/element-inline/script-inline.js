import confetti from "blockstudio/canvas-confetti@1.6.0";
import htm from "blockstudio/htm@3.1.1";
import { h, render } from "blockstudio/preact@10.15.1";
import counter from "blockstudio/@srexi/purecounterjs@1.5.0";
import lenis from "blockstudio/@studio-freight/lenis@1.0.27";
import { averageIntensity } from "blockstudio/@tgwf/co2@0.13.8";
import Swiper from "blockstudio/swiper@11.0.3";
import "blockstudio/swiper@11.0.3/swiper-bundle.css";
import "blockstudio/swiper@11.0.3/swiper.min.css";
import "blockstudio/swiper@11.0.3/modules/zoom.min.css";

console.log(Swiper);
console.log(counter);
console.log(lenis);
console.log(averageIntensity);

const html = htm.bind(h);
const styles = `display: block;
      font-family: sans-serif;
      color: black;
      background: #f1f1f1;
      padding: 1rem;
      border-radius: 0.25rem;
      margin: 1rem;
      font-family: sans-serif;`;

function App(props) {
  confetti();
  console.log(confetti);

  return html`<h1 style="${styles}">Hello ${props.name}!</h1>`;
}

render(
  html`<${App} name="Inline: Hello from Preact!" />`,
  document.querySelector("#element-inline")
);
