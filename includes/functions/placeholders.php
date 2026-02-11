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
 * @param string $variant The placeholder variant: dashboard, chart, or code.
 * @return string Inline SVG markup.
 */
function blockstudio_placeholder_dark( string $variant = 'dashboard' ): string {
	$svgs = array(
		'dashboard' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" style="display:block;width:100%;height:auto;color:var(--color-accent)">
<rect width="1200" height="800" fill="#111"/>
<rect width="1200" height="48" fill="rgba(255,255,255,0.04)"/>
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
SVG,
		'chart'     => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" style="display:block;width:100%;height:auto;color:var(--color-accent)">
<rect width="1200" height="800" fill="#111"/>
<rect width="1200" height="48" fill="rgba(255,255,255,0.04)"/>
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
SVG,
		'code'      => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" style="display:block;width:100%;height:auto;color:var(--color-accent)">
<rect width="1200" height="800" fill="#111"/>
<rect width="1200" height="48" fill="rgba(255,255,255,0.04)"/>
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
SVG,
	);

	return $svgs[ $variant ] ?? $svgs['dashboard'];
}

/**
 * Returns a light placeholder image as an inline SVG string.
 *
 * @param string $variant The placeholder variant: dashboard, chart, or code.
 * @return string Inline SVG markup.
 */
function blockstudio_placeholder_light( string $variant = 'dashboard' ): string {
	$svgs = array(
		'dashboard' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" style="display:block;width:100%;height:auto;color:var(--color-accent)">
<rect width="1200" height="800" fill="#f5f5f5"/>
<rect width="1200" height="48" fill="rgba(0,0,0,0.04)"/>
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
SVG,
		'chart'     => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" style="display:block;width:100%;height:auto;color:var(--color-accent)">
<rect width="1200" height="800" fill="#f5f5f5"/>
<rect width="1200" height="48" fill="rgba(0,0,0,0.04)"/>
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
SVG,
		'code'      => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" style="display:block;width:100%;height:auto;color:var(--color-accent)">
<rect width="1200" height="800" fill="#f5f5f5"/>
<rect width="1200" height="48" fill="rgba(0,0,0,0.04)"/>
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
SVG,
	);

	return $svgs[ $variant ] ?? $svgs['dashboard'];
}
