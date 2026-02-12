<?php
/**
 * Shared placeholder image functions for Blockstudio themes.
 *
 * Returns inline SVGs using currentColor for accent highlights.
 * Set the accent via CSS: the SVG root uses color: var(--color-accent).
 *
 * @package Blockstudio
 */

/**
 * Returns a dark placeholder image as an inline SVG string.
 *
 * @param string $variant The placeholder variant: default, dashboard, chart, code, user, space, project, or product.
 * @return string Inline SVG markup.
 */
function blockstudio_placeholder_dark( string $variant = 'default' ): string {
	$svgs = array(
		'default'   => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<rect width="1200" height="800" fill="rgba(255,255,255,0.03)"/>
<rect x="540" y="340" width="120" height="120" rx="8" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5"/>
<circle cx="575" cy="375" r="10" fill="rgba(255,255,255,0.1)"/>
<path d="M545 440 L580 405 L610 430 L635 410 L655 440" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG
		,
		'dashboard' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)"><rect width="1200" height="48" fill="rgba(255,255,255,0.04)"/>
<circle cx="28" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<circle cx="48" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<circle cx="68" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<rect y="48" width="220" height="752" fill="rgba(255,255,255,0.02)"/>
<line x1="220" y1="48" x2="220" y2="800" stroke="rgba(255,255,255,0.06)"/>
<rect x="24" y="80" width="100" height="8" rx="4" fill="rgba(255,255,255,0.1)"/>
<rect x="24" y="108" width="160" height="8" rx="4" fill="currentColor" fill-opacity="0.4"/>
<rect x="24" y="136" width="130" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
<rect x="24" y="164" width="110" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
<rect x="24" y="192" width="140" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
<rect x="24" y="236" width="80" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="24" y="260" width="150" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
<rect x="24" y="288" width="120" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
<rect x="252" y="80" width="456" height="200" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="728" y="80" width="440" height="200" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="280" y="108" width="140" height="10" rx="5" fill="rgba(255,255,255,0.1)"/>
<rect x="280" y="136" width="300" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="280" y="156" width="260" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="280" y="192" width="100" height="60" rx="8" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-opacity="0.2"/>
<rect x="396" y="192" width="100" height="60" rx="8" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="756" y="108" width="120" height="10" rx="5" fill="rgba(255,255,255,0.1)"/>
<rect x="756" y="136" width="340" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="756" y="156" width="300" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="756" y="192" width="340" height="60" rx="8" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="252" y="308" width="916" height="200" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="280" y="336" width="200" height="10" rx="5" fill="rgba(255,255,255,0.1)"/>
<rect x="280" y="364" width="500" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="280" y="384" width="460" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="280" y="420" width="140" height="44" rx="8" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-opacity="0.25"/>
<rect x="252" y="536" width="296" height="180" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="564" y="536" width="296" height="180" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="876" y="536" width="292" height="180" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="280" y="564" width="80" height="8" rx="4" fill="rgba(255,255,255,0.08)"/>
<rect x="280" y="588" width="200" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="280" y="608" width="180" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="592" y="564" width="80" height="8" rx="4" fill="rgba(255,255,255,0.08)"/>
<rect x="592" y="588" width="200" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="592" y="608" width="180" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="904" y="564" width="80" height="8" rx="4" fill="rgba(255,255,255,0.08)"/>
<rect x="904" y="588" width="200" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="904" y="608" width="180" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
</svg>
SVG
		,
		'chart'     => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)"><rect width="1200" height="48" fill="rgba(255,255,255,0.04)"/>
<circle cx="28" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<circle cx="48" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<circle cx="68" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<rect x="40" y="80" width="260" height="100" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="320" y="80" width="260" height="100" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="600" y="80" width="260" height="100" rx="12" fill="currentColor" fill-opacity="0.08" stroke="currentColor" stroke-opacity="0.2"/>
<rect x="880" y="80" width="280" height="100" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<rect x="68" y="104" width="60" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
<rect x="68" y="128" width="100" height="14" rx="4" fill="rgba(255,255,255,0.12)"/>
<rect x="68" y="156" width="40" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="348" y="104" width="60" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
<rect x="348" y="128" width="80" height="14" rx="4" fill="rgba(255,255,255,0.12)"/>
<rect x="348" y="156" width="40" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="628" y="104" width="60" height="8" rx="4" fill="currentColor" fill-opacity="0.3"/>
<rect x="628" y="128" width="90" height="14" rx="4" fill="currentColor" fill-opacity="0.5"/>
<rect x="628" y="156" width="40" height="6" rx="3" fill="currentColor" fill-opacity="0.2"/>
<rect x="908" y="104" width="60" height="8" rx="4" fill="rgba(255,255,255,0.06)"/>
<rect x="908" y="128" width="120" height="14" rx="4" fill="rgba(255,255,255,0.12)"/>
<rect x="908" y="156" width="40" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="40" y="212" width="1120" height="548" rx="12" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.06)"/>
<line x1="120" y1="260" x2="120" y2="700" stroke="rgba(255,255,255,0.04)"/>
<line x1="120" y1="700" x2="1100" y2="700" stroke="rgba(255,255,255,0.06)"/>
<line x1="120" y1="360" x2="1100" y2="360" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4 4"/>
<line x1="120" y1="440" x2="1100" y2="440" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4 4"/>
<line x1="120" y1="520" x2="1100" y2="520" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4 4"/>
<line x1="120" y1="620" x2="1100" y2="620" stroke="rgba(255,255,255,0.03)" stroke-dasharray="4 4"/>
<rect x="68" y="276" width="30" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="68" y="356" width="30" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="68" y="436" width="30" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="68" y="516" width="30" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="68" y="616" width="30" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<path d="M120 600 C220 580 280 540 400 480 C520 420 580 400 700 360 C820 320 880 300 1000 280 L1100 260" fill="none" stroke="currentColor" stroke-opacity="0.5" stroke-width="2.5"/>
<path d="M120 600 C220 580 280 540 400 480 C520 420 580 400 700 360 C820 320 880 300 1000 280 L1100 260 V700 H120Z" fill="url(#cg)"/>
<defs><linearGradient id="cg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity="0.12"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
<path d="M120 650 C260 640 380 620 500 590 C620 560 740 540 860 520 C940 508 1020 500 1100 490" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="2"/>
<circle cx="400" cy="480" r="4" fill="currentColor"/>
<circle cx="700" cy="360" r="4" fill="currentColor"/>
<circle cx="1000" cy="280" r="4" fill="currentColor"/>
<circle cx="500" cy="590" r="3" fill="rgba(255,255,255,0.15)"/>
<circle cx="860" cy="520" r="3" fill="rgba(255,255,255,0.15)"/>
</svg>
SVG
		,
		'code'      => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)"><rect width="1200" height="48" fill="rgba(255,255,255,0.04)"/>
<circle cx="28" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<circle cx="48" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<circle cx="68" cy="24" r="5" fill="rgba(255,255,255,0.15)"/>
<rect x="100" y="12" width="80" height="26" rx="6" fill="rgba(255,255,255,0.06)"/>
<rect x="188" y="12" width="80" height="26" rx="6" fill="rgba(255,255,255,0.02)"/>
<rect x="276" y="12" width="80" height="26" rx="6" fill="rgba(255,255,255,0.02)"/>
<rect y="48" width="56" height="752" fill="rgba(255,255,255,0.015)"/>
<line x1="56" y1="48" x2="56" y2="800" stroke="rgba(255,255,255,0.04)"/>
<rect x="1140" y="48" width="60" height="752" fill="rgba(255,255,255,0.015)"/>
<line x1="1140" y1="48" x2="1140" y2="800" stroke="rgba(255,255,255,0.04)"/>
<rect x="1152" y="80" width="36" height="3" rx="1.5" fill="currentColor" fill-opacity="0.3"/>
<rect x="1152" y="100" width="36" height="2" rx="1" fill="rgba(255,255,255,0.06)"/>
<rect x="1152" y="116" width="36" height="4" rx="2" fill="rgba(255,255,255,0.04)"/>
<rect x="1152" y="136" width="36" height="2" rx="1" fill="rgba(255,255,255,0.06)"/>
<rect x="1152" y="152" width="36" height="3" rx="1.5" fill="rgba(255,255,255,0.04)"/>
<rect x="1152" y="172" width="36" height="2" rx="1" fill="currentColor" fill-opacity="0.2"/>
<rect x="1152" y="192" width="36" height="4" rx="2" fill="rgba(255,255,255,0.04)"/>
<rect x="1152" y="216" width="36" height="2" rx="1" fill="rgba(255,255,255,0.06)"/>
<rect x="1152" y="236" width="36" height="3" rx="1.5" fill="rgba(255,255,255,0.04)"/>
<rect x="1152" y="256" width="36" height="2" rx="1" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="78" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="102" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="126" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="150" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="174" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="198" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="222" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="246" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="270" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="294" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="318" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="342" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="366" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="390" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="414" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="438" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="462" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="486" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="510" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="534" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="558" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="582" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="606" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="20" y="630" width="20" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="76" y="78" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.35"/>
<rect x="164" y="78" width="200" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="76" y="102" width="40" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="124" y="102" width="180" height="6" rx="3" fill="rgba(255,255,255,0.1)"/>
<rect x="312" y="102" width="60" height="6" rx="3" fill="currentColor" fill-opacity="0.25"/>
<rect x="96" y="126" width="120" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="224" y="126" width="80" height="6" rx="3" fill="rgba(255,255,255,0.05)"/>
<rect x="96" y="150" width="60" height="6" rx="3" fill="currentColor" fill-opacity="0.3"/>
<rect x="164" y="150" width="260" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="96" y="174" width="300" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="96" y="198" width="160" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="264" y="198" width="100" height="6" rx="3" fill="currentColor" fill-opacity="0.2"/>
<rect x="76" y="222" width="40" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="76" y="270" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.35"/>
<rect x="164" y="270" width="160" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="76" y="294" width="40" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="124" y="294" width="220" height="6" rx="3" fill="rgba(255,255,255,0.1)"/>
<rect x="96" y="318" width="140" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="244" y="318" width="60" height="6" rx="3" fill="currentColor" fill-opacity="0.25"/>
<rect x="312" y="318" width="100" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="96" y="342" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.3"/>
<rect x="184" y="342" width="200" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="96" y="366" width="260" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="96" y="390" width="180" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="96" y="414" width="120" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="224" y="414" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.2"/>
<rect x="76" y="438" width="40" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="76" y="462" width="40" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="76" y="486" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.35"/>
<rect x="164" y="486" width="140" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="76" y="510" width="40" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="124" y="510" width="280" height="6" rx="3" fill="rgba(255,255,255,0.1)"/>
<rect x="96" y="534" width="200" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="96" y="558" width="60" height="6" rx="3" fill="currentColor" fill-opacity="0.3"/>
<rect x="164" y="558" width="180" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="96" y="582" width="240" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="96" y="606" width="160" height="6" rx="3" fill="rgba(255,255,255,0.08)"/>
<rect x="76" y="630" width="40" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="76" y="246" width="0" height="6" rx="3" fill="transparent"/>
</svg>
SVG
		,
		'user'  => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<rect width="800" height="1000" fill="rgba(255,255,255,0.03)"/>
<circle cx="400" cy="440" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5"/>
<path d="M340 560 C340 525 365 505 400 505 C435 505 460 525 460 560" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5" stroke-linecap="round"/>
</svg>
SVG
		,
		'space'     => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<path d="M0 500 L600 380 L1200 500 L1200 800 L0 800 Z" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
<line x1="0" y1="580" x2="1200" y2="580" stroke="rgba(255,255,255,0.03)"/>
<line x1="0" y1="660" x2="1200" y2="660" stroke="rgba(255,255,255,0.03)"/>
<line x1="0" y1="740" x2="1200" y2="740" stroke="rgba(255,255,255,0.03)"/>
<line x1="200" y1="500" x2="300" y2="800" stroke="rgba(255,255,255,0.02)"/>
<line x1="400" y1="440" x2="450" y2="800" stroke="rgba(255,255,255,0.02)"/>
<line x1="800" y1="440" x2="750" y2="800" stroke="rgba(255,255,255,0.02)"/>
<line x1="1000" y1="500" x2="900" y2="800" stroke="rgba(255,255,255,0.02)"/>
<rect x="100" y="100" width="30" height="400" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
<rect x="1070" y="100" width="30" height="400" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
<line x1="0" y1="80" x2="1200" y2="80" stroke="rgba(255,255,255,0.04)"/>
<line x1="200" y1="0" x2="200" y2="80" stroke="rgba(255,255,255,0.03)"/>
<line x1="400" y1="0" x2="400" y2="80" stroke="rgba(255,255,255,0.03)"/>
<line x1="600" y1="0" x2="600" y2="80" stroke="rgba(255,255,255,0.03)"/>
<line x1="800" y1="0" x2="800" y2="80" stroke="rgba(255,255,255,0.03)"/>
<line x1="1000" y1="0" x2="1000" y2="80" stroke="rgba(255,255,255,0.03)"/>
<circle cx="300" cy="60" r="12" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-opacity="0.25"/>
<circle cx="600" cy="60" r="14" fill="currentColor" fill-opacity="0.2" stroke="currentColor" stroke-opacity="0.3"/>
<circle cx="900" cy="60" r="12" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-opacity="0.25"/>
<path d="M600 74 L500 380 L700 380 Z" fill="currentColor" fill-opacity="0.03"/>
<path d="M300 72 L240 380 L360 380 Z" fill="rgba(255,255,255,0.015)"/>
<path d="M900 72 L840 380 L960 380 Z" fill="rgba(255,255,255,0.015)"/>
<rect x="180" y="120" width="840" height="260" rx="4" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
</svg>
SVG
		,
		'project'   => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<rect x="200" y="80" width="800" height="640" fill="rgba(255,255,255,0.03)" stroke="rgba(255,255,255,0.06)" stroke-width="1.5"/>
<line x1="0" y1="720" x2="1200" y2="720" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
<line x1="200" y1="240" x2="1000" y2="240" stroke="rgba(255,255,255,0.04)"/>
<line x1="200" y1="400" x2="1000" y2="400" stroke="rgba(255,255,255,0.04)"/>
<line x1="200" y1="560" x2="1000" y2="560" stroke="rgba(255,255,255,0.04)"/>
<line x1="400" y1="80" x2="400" y2="720" stroke="rgba(255,255,255,0.04)"/>
<line x1="600" y1="80" x2="600" y2="720" stroke="rgba(255,255,255,0.04)"/>
<line x1="800" y1="80" x2="800" y2="720" stroke="rgba(255,255,255,0.04)"/>
<rect x="240" y="120" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="440" y="120" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="640" y="120" width="120" height="80" rx="2" fill="currentColor" fill-opacity="0.08" stroke="currentColor" stroke-opacity="0.2"/>
<rect x="840" y="120" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="240" y="280" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="440" y="280" width="120" height="80" rx="2" fill="currentColor" fill-opacity="0.06" stroke="currentColor" stroke-opacity="0.15"/>
<rect x="640" y="280" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="840" y="280" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="240" y="440" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="440" y="440" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="640" y="440" width="120" height="80" rx="2" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.06)"/>
<rect x="840" y="440" width="120" height="80" rx="2" fill="currentColor" fill-opacity="0.06" stroke="currentColor" stroke-opacity="0.15"/>
<rect x="520" y="600" width="160" height="120" rx="4" fill="currentColor" fill-opacity="0.06" stroke="currentColor" stroke-opacity="0.2"/>
<line x1="600" y1="600" x2="600" y2="720" stroke="currentColor" stroke-opacity="0.15"/>
<line x1="200" y1="80" x2="1000" y2="80" stroke="currentColor" stroke-opacity="0.25" stroke-width="2"/>
</svg>
SVG
		,
		'product'   => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<line x1="100" y1="620" x2="1100" y2="620" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
<ellipse cx="600" cy="620" rx="200" ry="20" fill="rgba(255,255,255,0.03)"/>
<path d="M440 620 C440 620 430 380 450 300 C460 260 480 240 520 230 L680 230 C720 240 740 260 750 300 C770 380 760 620 760 620 Z" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.08)" stroke-width="1.5"/>
<rect x="480" y="340" width="240" height="160" rx="8" fill="rgba(255,255,255,0.03)" stroke="rgba(255,255,255,0.06)"/>
<rect x="520" y="380" width="80" height="8" rx="4" fill="currentColor" fill-opacity="0.3"/>
<rect x="520" y="404" width="160" height="6" rx="3" fill="rgba(255,255,255,0.06)"/>
<rect x="520" y="424" width="120" height="6" rx="3" fill="rgba(255,255,255,0.04)"/>
<rect x="520" y="460" width="60" height="10" rx="5" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-opacity="0.25"/>
<path d="M480 230 L480 190 C480 170 510 160 600 160 C690 160 720 170 720 190 L720 230" fill="rgba(255,255,255,0.03)" stroke="rgba(255,255,255,0.06)" stroke-width="1.5"/>
<circle cx="260" cy="400" r="60" fill="currentColor" fill-opacity="0.02" stroke="currentColor" stroke-opacity="0.06"/>
<circle cx="940" cy="350" r="40" fill="currentColor" fill-opacity="0.02" stroke="currentColor" stroke-opacity="0.06"/>
<ellipse cx="320" cy="580" rx="12" ry="8" fill="rgba(255,255,255,0.04)" transform="rotate(-30 320 580)"/>
<ellipse cx="860" cy="560" rx="10" ry="6" fill="rgba(255,255,255,0.03)" transform="rotate(20 860 560)"/>
<ellipse cx="900" cy="590" rx="12" ry="8" fill="rgba(255,255,255,0.04)" transform="rotate(-15 900 590)"/>
</svg>
SVG
		,
	);

	return $svgs[ $variant ] ?? $svgs['default'];
}

/**
 * Returns a light placeholder image as an inline SVG string.
 *
 * @param string $variant The placeholder variant: default, dashboard, chart, code, user, space, project, or product.
 * @return string Inline SVG markup.
 */
function blockstudio_placeholder_light( string $variant = 'default' ): string {
	$svgs = array(
		'default'   => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<rect width="1200" height="800" fill="rgba(0,0,0,0.03)"/>
<rect x="540" y="340" width="120" height="120" rx="8" fill="none" stroke="rgba(0,0,0,0.1)" stroke-width="1.5"/>
<circle cx="575" cy="375" r="10" fill="rgba(0,0,0,0.08)"/>
<path d="M545 440 L580 405 L610 430 L635 410 L655 440" fill="none" stroke="rgba(0,0,0,0.1)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG
		,
		'dashboard' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)"><rect width="1200" height="48" fill="rgba(0,0,0,0.04)"/>
<circle cx="28" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<circle cx="48" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<circle cx="68" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<rect y="48" width="220" height="752" fill="rgba(0,0,0,0.02)"/>
<line x1="220" y1="48" x2="220" y2="800" stroke="rgba(0,0,0,0.06)"/>
<rect x="24" y="80" width="100" height="8" rx="4" fill="rgba(0,0,0,0.1)"/>
<rect x="24" y="108" width="160" height="8" rx="4" fill="currentColor" fill-opacity="0.4"/>
<rect x="24" y="136" width="130" height="8" rx="4" fill="rgba(0,0,0,0.06)"/>
<rect x="24" y="164" width="110" height="8" rx="4" fill="rgba(0,0,0,0.06)"/>
<rect x="24" y="192" width="140" height="8" rx="4" fill="rgba(0,0,0,0.06)"/>
<rect x="252" y="80" width="456" height="200" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<rect x="728" y="80" width="440" height="200" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<rect x="280" y="108" width="140" height="10" rx="5" fill="rgba(0,0,0,0.12)"/>
<rect x="280" y="136" width="300" height="6" rx="3" fill="rgba(0,0,0,0.06)"/>
<rect x="280" y="156" width="260" height="6" rx="3" fill="rgba(0,0,0,0.06)"/>
<rect x="280" y="192" width="100" height="60" rx="8" fill="currentColor" fill-opacity="0.08" stroke="currentColor" stroke-opacity="0.2"/>
<rect x="396" y="192" width="100" height="60" rx="8" fill="rgba(0,0,0,0.02)" stroke="rgba(0,0,0,0.06)"/>
<rect x="252" y="308" width="916" height="200" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<rect x="280" y="336" width="200" height="10" rx="5" fill="rgba(0,0,0,0.12)"/>
<rect x="280" y="364" width="500" height="6" rx="3" fill="rgba(0,0,0,0.06)"/>
<rect x="280" y="420" width="140" height="44" rx="8" fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-opacity="0.25"/>
<rect x="252" y="536" width="296" height="180" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<rect x="564" y="536" width="296" height="180" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<rect x="876" y="536" width="292" height="180" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
</svg>
SVG
		,
		'chart'     => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)"><rect width="1200" height="48" fill="rgba(0,0,0,0.04)"/>
<circle cx="28" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<circle cx="48" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<circle cx="68" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<rect x="40" y="80" width="260" height="100" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<rect x="320" y="80" width="260" height="100" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<rect x="600" y="80" width="260" height="100" rx="12" fill="currentColor" fill-opacity="0.06" stroke="currentColor" stroke-opacity="0.2"/>
<rect x="880" y="80" width="280" height="100" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<rect x="40" y="212" width="1120" height="548" rx="12" fill="white" stroke="rgba(0,0,0,0.08)"/>
<line x1="120" y1="700" x2="1100" y2="700" stroke="rgba(0,0,0,0.06)"/>
<line x1="120" y1="440" x2="1100" y2="440" stroke="rgba(0,0,0,0.04)" stroke-dasharray="4 4"/>
<line x1="120" y1="520" x2="1100" y2="520" stroke="rgba(0,0,0,0.04)" stroke-dasharray="4 4"/>
<line x1="120" y1="620" x2="1100" y2="620" stroke="rgba(0,0,0,0.04)" stroke-dasharray="4 4"/>
<path d="M120 600 C220 580 280 540 400 480 C520 420 580 400 700 360 C820 320 880 300 1000 280 L1100 260" fill="none" stroke="currentColor" stroke-opacity="0.5" stroke-width="2.5"/>
<path d="M120 600 C220 580 280 540 400 480 C520 420 580 400 700 360 C820 320 880 300 1000 280 L1100 260 V700 H120Z" fill="currentColor" fill-opacity="0.06"/>
<circle cx="400" cy="480" r="4" fill="currentColor"/>
<circle cx="700" cy="360" r="4" fill="currentColor"/>
<circle cx="1000" cy="280" r="4" fill="currentColor"/>
</svg>
SVG
		,
		'code'      => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)"><rect width="1200" height="48" fill="rgba(0,0,0,0.04)"/>
<circle cx="28" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<circle cx="48" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<circle cx="68" cy="24" r="5" fill="rgba(0,0,0,0.12)"/>
<rect x="100" y="12" width="80" height="26" rx="6" fill="rgba(0,0,0,0.06)"/>
<rect x="188" y="12" width="80" height="26" rx="6" fill="rgba(0,0,0,0.02)"/>
<rect y="48" width="56" height="752" fill="rgba(0,0,0,0.02)"/>
<rect x="76" y="78" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.35"/>
<rect x="164" y="78" width="200" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
<rect x="76" y="102" width="40" height="6" rx="3" fill="rgba(0,0,0,0.08)"/>
<rect x="124" y="102" width="180" height="6" rx="3" fill="rgba(0,0,0,0.12)"/>
<rect x="96" y="126" width="120" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
<rect x="96" y="150" width="60" height="6" rx="3" fill="currentColor" fill-opacity="0.3"/>
<rect x="164" y="150" width="260" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
<rect x="96" y="174" width="300" height="6" rx="3" fill="rgba(0,0,0,0.08)"/>
<rect x="76" y="222" width="40" height="6" rx="3" fill="rgba(0,0,0,0.08)"/>
<rect x="76" y="246" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.35"/>
<rect x="164" y="246" width="160" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
<rect x="96" y="270" width="140" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
<rect x="96" y="294" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.3"/>
<rect x="184" y="294" width="200" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
<rect x="96" y="318" width="260" height="6" rx="3" fill="rgba(0,0,0,0.08)"/>
<rect x="76" y="366" width="80" height="6" rx="3" fill="currentColor" fill-opacity="0.35"/>
<rect x="164" y="366" width="140" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
<rect x="96" y="390" width="200" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
<rect x="96" y="414" width="60" height="6" rx="3" fill="currentColor" fill-opacity="0.3"/>
<rect x="164" y="414" width="180" height="6" rx="3" fill="rgba(0,0,0,0.1)"/>
</svg>
SVG
		,
		'user'  => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<rect width="800" height="1000" fill="rgba(0,0,0,0.03)"/>
<circle cx="400" cy="440" r="40" fill="none" stroke="rgba(0,0,0,0.1)" stroke-width="1.5"/>
<path d="M340 560 C340 525 365 505 400 505 C435 505 460 525 460 560" fill="none" stroke="rgba(0,0,0,0.1)" stroke-width="1.5" stroke-linecap="round"/>
</svg>
SVG
		,
		'space'     => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<path d="M0 500 L600 380 L1200 500 L1200 800 L0 800 Z" fill="rgba(0,0,0,0.02)" stroke="rgba(0,0,0,0.04)" stroke-width="1"/>
<line x1="0" y1="580" x2="1200" y2="580" stroke="rgba(0,0,0,0.03)"/>
<line x1="0" y1="660" x2="1200" y2="660" stroke="rgba(0,0,0,0.03)"/>
<line x1="0" y1="740" x2="1200" y2="740" stroke="rgba(0,0,0,0.03)"/>
<line x1="200" y1="500" x2="300" y2="800" stroke="rgba(0,0,0,0.02)"/>
<line x1="400" y1="440" x2="450" y2="800" stroke="rgba(0,0,0,0.02)"/>
<line x1="800" y1="440" x2="750" y2="800" stroke="rgba(0,0,0,0.02)"/>
<line x1="1000" y1="500" x2="900" y2="800" stroke="rgba(0,0,0,0.02)"/>
<rect x="100" y="100" width="30" height="400" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)" stroke-width="1"/>
<rect x="1070" y="100" width="30" height="400" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)" stroke-width="1"/>
<line x1="0" y1="80" x2="1200" y2="80" stroke="rgba(0,0,0,0.04)"/>
<line x1="200" y1="0" x2="200" y2="80" stroke="rgba(0,0,0,0.03)"/>
<line x1="400" y1="0" x2="400" y2="80" stroke="rgba(0,0,0,0.03)"/>
<line x1="600" y1="0" x2="600" y2="80" stroke="rgba(0,0,0,0.03)"/>
<line x1="800" y1="0" x2="800" y2="80" stroke="rgba(0,0,0,0.03)"/>
<line x1="1000" y1="0" x2="1000" y2="80" stroke="rgba(0,0,0,0.03)"/>
<rect x="250" y="140" width="140" height="200" rx="4" fill="currentColor" fill-opacity="0.04" stroke="currentColor" stroke-opacity="0.12"/>
<rect x="530" y="140" width="140" height="200" rx="4" fill="currentColor" fill-opacity="0.06" stroke="currentColor" stroke-opacity="0.15"/>
<rect x="810" y="140" width="140" height="200" rx="4" fill="currentColor" fill-opacity="0.04" stroke="currentColor" stroke-opacity="0.12"/>
<line x1="320" y1="140" x2="320" y2="340" stroke="currentColor" stroke-opacity="0.08"/>
<line x1="250" y1="240" x2="390" y2="240" stroke="currentColor" stroke-opacity="0.08"/>
<line x1="600" y1="140" x2="600" y2="340" stroke="currentColor" stroke-opacity="0.08"/>
<line x1="530" y1="240" x2="670" y2="240" stroke="currentColor" stroke-opacity="0.08"/>
<line x1="880" y1="140" x2="880" y2="340" stroke="currentColor" stroke-opacity="0.08"/>
<line x1="810" y1="240" x2="950" y2="240" stroke="currentColor" stroke-opacity="0.08"/>
<rect x="180" y="120" width="840" height="260" rx="4" fill="rgba(0,0,0,0.01)" stroke="rgba(0,0,0,0.04)" stroke-width="1"/>
</svg>
SVG
		,
		'project'   => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<rect x="200" y="80" width="800" height="640" fill="rgba(0,0,0,0.02)" stroke="rgba(0,0,0,0.06)" stroke-width="1.5"/>
<line x1="0" y1="720" x2="1200" y2="720" stroke="rgba(0,0,0,0.06)" stroke-width="1"/>
<line x1="200" y1="240" x2="1000" y2="240" stroke="rgba(0,0,0,0.04)"/>
<line x1="200" y1="400" x2="1000" y2="400" stroke="rgba(0,0,0,0.04)"/>
<line x1="200" y1="560" x2="1000" y2="560" stroke="rgba(0,0,0,0.04)"/>
<line x1="400" y1="80" x2="400" y2="720" stroke="rgba(0,0,0,0.04)"/>
<line x1="600" y1="80" x2="600" y2="720" stroke="rgba(0,0,0,0.04)"/>
<line x1="800" y1="80" x2="800" y2="720" stroke="rgba(0,0,0,0.04)"/>
<rect x="240" y="120" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="440" y="120" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="640" y="120" width="120" height="80" rx="2" fill="currentColor" fill-opacity="0.06" stroke="currentColor" stroke-opacity="0.15"/>
<rect x="840" y="120" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="240" y="280" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="440" y="280" width="120" height="80" rx="2" fill="currentColor" fill-opacity="0.05" stroke="currentColor" stroke-opacity="0.12"/>
<rect x="640" y="280" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="840" y="280" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="240" y="440" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="440" y="440" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="640" y="440" width="120" height="80" rx="2" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.06)"/>
<rect x="840" y="440" width="120" height="80" rx="2" fill="currentColor" fill-opacity="0.05" stroke="currentColor" stroke-opacity="0.12"/>
<rect x="520" y="600" width="160" height="120" rx="4" fill="currentColor" fill-opacity="0.05" stroke="currentColor" stroke-opacity="0.15"/>
<line x1="600" y1="600" x2="600" y2="720" stroke="currentColor" stroke-opacity="0.12"/>
<line x1="200" y1="80" x2="1000" y2="80" stroke="currentColor" stroke-opacity="0.2" stroke-width="2"/>
</svg>
SVG
		,
		'product'   => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" style="display:block;width:100%;height:100%;color:var(--color-accent)">
<line x1="100" y1="620" x2="1100" y2="620" stroke="rgba(0,0,0,0.06)" stroke-width="1"/>
<ellipse cx="600" cy="620" rx="200" ry="20" fill="rgba(0,0,0,0.02)"/>
<path d="M440 620 C440 620 430 380 450 300 C460 260 480 240 520 230 L680 230 C720 240 740 260 750 300 C770 380 760 620 760 620 Z" fill="rgba(0,0,0,0.03)" stroke="rgba(0,0,0,0.08)" stroke-width="1.5"/>
<rect x="480" y="340" width="240" height="160" rx="8" fill="rgba(0,0,0,0.02)" stroke="rgba(0,0,0,0.06)"/>
<rect x="520" y="380" width="80" height="8" rx="4" fill="currentColor" fill-opacity="0.25"/>
<rect x="520" y="404" width="160" height="6" rx="3" fill="rgba(0,0,0,0.08)"/>
<rect x="520" y="424" width="120" height="6" rx="3" fill="rgba(0,0,0,0.05)"/>
<rect x="520" y="460" width="60" height="10" rx="5" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-opacity="0.2"/>
<path d="M480 230 L480 190 C480 170 510 160 600 160 C690 160 720 170 720 190 L720 230" fill="rgba(0,0,0,0.02)" stroke="rgba(0,0,0,0.06)" stroke-width="1.5"/>
<circle cx="260" cy="400" r="60" fill="currentColor" fill-opacity="0.02" stroke="currentColor" stroke-opacity="0.05"/>
<circle cx="940" cy="350" r="40" fill="currentColor" fill-opacity="0.02" stroke="currentColor" stroke-opacity="0.05"/>
<ellipse cx="320" cy="580" rx="12" ry="8" fill="rgba(0,0,0,0.04)" transform="rotate(-30 320 580)"/>
<ellipse cx="860" cy="560" rx="10" ry="6" fill="rgba(0,0,0,0.03)" transform="rotate(20 860 560)"/>
<ellipse cx="900" cy="590" rx="12" ry="8" fill="rgba(0,0,0,0.04)" transform="rotate(-15 900 590)"/>
</svg>
SVG
		,
	);

	return $svgs[ $variant ] ?? $svgs['default'];
}
