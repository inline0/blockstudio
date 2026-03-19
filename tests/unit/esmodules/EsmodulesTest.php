<?php

use Blockstudio\ESModules;
use Blockstudio\ESModulesCSS;
use PHPUnit\Framework\TestCase;

class EsmodulesTest extends TestCase {

	// ESModules::get_blockstudio_regex()

	public function test_js_regex_matches_npm_prefix(): void {
		$pattern = ESModules::get_blockstudio_regex();
		$input   = 'import lodash from "npm:lodash@4.17.21"';

		$this->assertMatchesRegularExpression( $pattern, $input );
	}

	public function test_js_regex_matches_blockstudio_prefix(): void {
		$pattern = ESModules::get_blockstudio_regex();
		$input   = 'import lodash from "blockstudio/lodash@4.17.21"';

		$this->assertMatchesRegularExpression( $pattern, $input );
	}

	public function test_js_regex_does_not_match_regular_import(): void {
		$pattern = ESModules::get_blockstudio_regex();
		$input   = 'import lodash from "lodash"';

		$this->assertDoesNotMatchRegularExpression( $pattern, $input );
	}

	// ESModules::get_http_regex()

	public function test_http_regex_matches_export_statement(): void {
		$pattern = ESModules::get_http_regex();
		$input   = 'export * from "https://esm.sh/stable/lodash@4.17.21";';

		$this->assertMatchesRegularExpression( $pattern, $input );
	}

	public function test_http_regex_does_not_match_import(): void {
		$pattern = ESModules::get_http_regex();
		$input   = 'import * from "https://esm.sh/lodash@4.17.21";';

		$this->assertDoesNotMatchRegularExpression( $pattern, $input );
	}

	// ESModules::get_module_matches() with $obj = true

	public function test_get_module_matches_obj_npm_prefix(): void {
		$result = ESModules::get_module_matches( 'npm:lodash@4.17.21', true );

		$this->assertSame( 'lodash', $result['name'] );
		$this->assertSame( 'lodash', $result['nameTransformed'] );
		$this->assertSame( '4.17.21', $result['version'] );
		$this->assertSame( 'lodash@4.17.21', $result['nameVersion'] );
	}

	public function test_get_module_matches_obj_blockstudio_prefix(): void {
		$result = ESModules::get_module_matches( 'blockstudio/lodash@4.17.21', true );

		$this->assertSame( 'lodash', $result['name'] );
		$this->assertSame( '4.17.21', $result['version'] );
	}

	public function test_get_module_matches_obj_scoped_package(): void {
		$result = ESModules::get_module_matches( 'npm:@scope/package@1.0.0', true );

		$this->assertSame( '@scope/package', $result['name'] );
		$this->assertSame( '@scope-package', $result['nameTransformed'] );
		$this->assertSame( '1.0.0', $result['version'] );
		$this->assertSame( '@scope/package@1.0.0', $result['nameVersion'] );
	}

	// ESModules::get_module_matches() string replacement mode

	public function test_get_module_matches_replaces_npm_import(): void {
		$input  = 'import lodash from "npm:lodash@4.17.21"';
		$result = ESModules::get_module_matches( $input );

		$this->assertIsString( $result );
		$this->assertStringNotContainsString( '"npm:', $result );
	}

	public function test_get_module_matches_replaces_blockstudio_import(): void {
		$input  = 'import { motion } from "blockstudio/framer-motion@10.16.4"';
		$result = ESModules::get_module_matches( $input );

		$this->assertIsString( $result );
		$this->assertStringNotContainsString( '"blockstudio/', $result );
	}

	public function test_get_module_matches_returns_string_type(): void {
		$input  = 'import x from "npm:pkg@1.0.0"';
		$result = ESModules::get_module_matches( $input );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'esm.sh', $result );
	}

	public function test_get_module_matches_no_change_for_regular_import(): void {
		$input  = 'import React from "react"';
		$result = ESModules::get_module_matches( $input );

		$this->assertSame( $input, $result );
	}

	// ESModules::get_module_strings()

	public function test_get_module_strings_finds_all_imports(): void {
		$input = implode(
			"\n",
			array(
				'import lodash from "npm:lodash@4.17.21";',
				'import { motion } from "npm:framer-motion@10.16.4";',
				'import React from "react";',
			)
		);

		$result = ESModules::get_module_strings( $input );

		$this->assertCount( 2, $result );
		$this->assertContains( 'npm:lodash@4.17.21', $result );
		$this->assertContains( 'npm:framer-motion@10.16.4', $result );
	}

	public function test_get_module_strings_returns_empty_for_no_matches(): void {
		$input  = 'import React from "react";';
		$result = ESModules::get_module_strings( $input );

		$this->assertSame( array(), $result );
	}

	public function test_get_module_strings_finds_blockstudio_prefix(): void {
		$input  = 'import x from "blockstudio/pkg@1.0.0";';
		$result = ESModules::get_module_strings( $input );

		$this->assertCount( 1, $result );
		$this->assertContains( 'blockstudio/pkg@1.0.0', $result );
	}

	// ESModulesCSS::get_blockstudio_regex()

	public function test_css_regex_matches_npm_css_import(): void {
		$pattern = ESModulesCSS::get_blockstudio_regex();
		$input   = 'import "npm:swiper@10.0.0/swiper-bundle.min.css";';

		$this->assertMatchesRegularExpression( $pattern, $input );
	}

	public function test_css_regex_matches_blockstudio_css_import(): void {
		$pattern = ESModulesCSS::get_blockstudio_regex();
		$input   = 'import "blockstudio/swiper@10.0.0/swiper.css";';

		$this->assertMatchesRegularExpression( $pattern, $input );
	}

	public function test_css_regex_does_not_match_js_import(): void {
		$pattern = ESModulesCSS::get_blockstudio_regex();
		$input   = 'import "npm:lodash@4.17.21";';

		$this->assertDoesNotMatchRegularExpression( $pattern, $input );
	}

	public function test_css_regex_does_not_match_regular_css_import(): void {
		$pattern = ESModulesCSS::get_blockstudio_regex();
		$input   = 'import "./styles.css";';

		$this->assertDoesNotMatchRegularExpression( $pattern, $input );
	}

	// ESModulesCSS::replace_module_references()

	public function test_replace_module_references_removes_css_imports(): void {
		$input = implode(
			"\n",
			array(
				'import "npm:swiper@10.0.0/swiper.css";',
				'console.log("hello");',
			)
		);

		$result = ESModulesCSS::replace_module_references( $input );

		$this->assertStringNotContainsString( 'swiper', $result );
		$this->assertStringContainsString( 'console.log("hello")', $result );
	}

	public function test_replace_module_references_preserves_non_css_imports(): void {
		$input  = 'import lodash from "npm:lodash@4.17.21";';
		$result = ESModulesCSS::replace_module_references( $input );

		$this->assertSame( $input, $result );
	}

	public function test_replace_module_references_removes_multiple_css_imports(): void {
		$input = implode(
			"\n",
			array(
				'import "npm:swiper@10.0.0/swiper.css";',
				'import "npm:other@1.0.0/style.css";',
				'const x = 1;',
			)
		);

		$result = ESModulesCSS::replace_module_references( $input );

		$this->assertStringNotContainsString( 'swiper', $result );
		$this->assertStringNotContainsString( 'other', $result );
		$this->assertStringContainsString( 'const x = 1;', $result );
	}

	// ESModulesCSS::get_module_matches()

	public function test_css_get_module_matches_parses_npm_import(): void {
		$input  = 'import "npm:swiper@10.0.0/swiper-bundle.min.css";';
		$result = ESModulesCSS::get_module_matches( $input );

		$this->assertCount( 1, $result );
		$this->assertSame( 'swiper', $result[0]['name'] );
		$this->assertSame( 'swiper', $result[0]['nameTransformed'] );
		$this->assertSame( '10.0.0', $result[0]['version'] );
		$this->assertSame( 'swiper@10.0.0', $result[0]['nameVersion'] );
		$this->assertSame( 'swiper-bundle.min.css', $result[0]['filename'] );
	}

	public function test_css_get_module_matches_parses_scoped_package(): void {
		$input  = 'import "npm:@fancyapps/ui@5.0.0/dist/fancybox.css";';
		$result = ESModulesCSS::get_module_matches( $input );

		$this->assertCount( 1, $result );
		$this->assertSame( '@fancyapps/ui', $result[0]['name'] );
		$this->assertSame( '@fancyapps-ui', $result[0]['nameTransformed'] );
		$this->assertSame( '5.0.0', $result[0]['version'] );
		$this->assertSame( 'dist/fancybox.css', $result[0]['filename'] );
	}

	public function test_css_get_module_matches_parses_blockstudio_prefix(): void {
		$input  = 'import "blockstudio/swiper@10.0.0/swiper.css";';
		$result = ESModulesCSS::get_module_matches( $input );

		$this->assertCount( 1, $result );
		$this->assertSame( 'swiper', $result[0]['name'] );
		$this->assertSame( '10.0.0', $result[0]['version'] );
		$this->assertSame( 'swiper.css', $result[0]['filename'] );
	}

	public function test_css_get_module_matches_returns_empty_for_no_css(): void {
		$input  = 'import lodash from "npm:lodash@4.17.21";';
		$result = ESModulesCSS::get_module_matches( $input );

		$this->assertSame( array(), $result );
	}

	public function test_css_get_module_matches_finds_multiple(): void {
		$input = implode(
			"\n",
			array(
				'import "npm:swiper@10.0.0/swiper.css";',
				'import "npm:pkg@1.0.0/style.css";',
			)
		);

		$result = ESModulesCSS::get_module_matches( $input );

		$this->assertCount( 2, $result );
		$this->assertSame( 'swiper', $result[0]['name'] );
		$this->assertSame( 'pkg', $result[1]['name'] );
	}
}
