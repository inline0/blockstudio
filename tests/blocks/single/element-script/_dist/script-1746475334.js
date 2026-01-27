import htm from "./modules/htm/3.1.1.js";import{h,render}from "./modules/preact/10.18.2.js";import counter from "./modules/@srexi-purecounterjs/1.5.0.js";import lenis from "./modules/@studio-freight-lenis/1.0.27.js";import{averageIntensity}from "./modules/@tgwf-co2/0.13.8.js";import Swiper from "./modules/swiper/11.0.3.js";console.log(counter);console.log(lenis);console.log(averageIntensity);console.log(Swiper);const html=htm.bind(h);const styles=`display: block;
      font-family: sans-serif; 
      color: black;
      background: #f1f1f1; 
      padding: 1rem;
      border-radius: 0.25rem; 
      margin: 1rem;
      font-family: sans-serif;`;function App(props){return html`<h1 style="${styles}">Hello ${props.name}!</h1>`}
render(html`<${App} name="Script: Hello from Preact!" />`,document.querySelector("#element-script"))