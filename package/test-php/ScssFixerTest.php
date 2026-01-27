<?php

use Blockstudio\Assets;
use PHPUnit\Framework\TestCase;

require_once '../includes/classes/assets.php';
require_once '../vendor/autoload.php';

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

class ScssFixerTest extends TestCase
{
    protected function assertEqualsIgnoringWhitespace($expected, $actual)
    {
        $expected = preg_replace('/\s+/', ' ', $expected);
        $expected = trim($expected);
        $actual = preg_replace('/\s+/', ' ', $actual);
        $actual = trim($actual);

        $this->assertEquals($expected, $actual);
    }

    public function queryProvider(): array
    {
        return [
            // Basic usage with calculation in the middle
            [
                '.example { width: clamp(10px, 10vh + 20px, 30px); }',
                '.example { width: clamp(10px, 10vh + 20px, 30px); }',
            ],

            // Calculations in all arguments
            [
                '.example { width: clamp(10% - 5px, 10vh + 20px, 30em / 2); }',
                '.example { width: clamp(10% - 5px, 10vh + 20px, 30em / 2); }',
            ],

            // No calculations
            [
                '.example { width: clamp(10px, 15px, 20px); }',
                '.example { width: clamp(10px, 15px, 20px); }',
            ],

            // Complex calculation
            [
                '.example { width: clamp(10px, 10vh + 20px - 5%, 30px); }',
                '.example { width: clamp(10px, 10vh + 20px - 5%, 30px); }',
            ],

            // Nested clamp functions
            [
                '.example { width: clamp(10px, clamp(5px, 2vh, 10px) + 20px, 30px); }',
                '.example { width: clamp(10px, clamp(5px, 2vh, 10px) + 20px, 30px); }',
            ],

            // clamp with other CSS functions
            [
                '.example { width: clamp(10px, min(100px, 20vh), 30px); }',
                '.example { width: clamp(10px, min(100px, 20vh), 30px); }',
                // no change expected
            ],

            // Multiple clamp in one declaration
            [
                '.example { padding: clamp(1rem, 2vh, 3rem) clamp(1rem, 5%, 2rem) clamp(1rem, 2vh, 3rem) clamp(1rem, 5%, 2rem); }',
                '.example { padding: clamp(1rem, 2vh, 3rem) clamp(1rem, 5%, 2rem) clamp(1rem, 2vh, 3rem) clamp(1rem, 5%, 2rem); }',
            ],

            // clamp in media queries
            [
                '@media screen and (max-width: 600px) { .example { width: clamp(10px, 5vh, 30px); } }',
                '@media screen and (max-width: 600px) { .example { width: clamp(10px, 5vh, 30px); } }',
            ],

            // clamp with varied argument types
            [
                '.example { width: clamp(5%, 10em + 15%, 80vh); }',
                '.example { width: clamp(5%, 10em + 15%, 80vh); }',
            ],

            // Arithmetic operations outside calc() within clamp()
            [
                '.example { width: clamp(10px + 5px, 10vh, 30px - 5px); }',
                '.example { width: clamp(10px + 5px, 10vh, 30px - 5px); }',
            ],

            // Deeply Nested clamp() Functions
            [
                '.example { width: clamp(10px, clamp(5px, clamp(1em, 2vh, 3em) + 2vh, 10px) + 20px, 30px); }',
                '.example { width: clamp(10px, clamp(5px, clamp(1em, 2vh, 3em) + 2vh, 10px) + 20px, 30px); }',
            ],

            // Deeply Nested clamp() Functions
            [
                '.example { width: clamp(10px, clamp(5px, clamp(1em, 2vh, 3em) + 2vh, 10px) + clamp(10px, clamp(5px, clamp(1em, 2vh, 3em) + 2vh, 10px) + 20px, 30px)); }',
                '.example { width: clamp(10px, clamp(5px, clamp(1em, 2vh, 3em) + 2vh, 10px) + clamp(10px, clamp(5px, clamp(1em, 2vh, 3em) + 2vh, 10px) + 20px, 30px) ); }',
            ],

            // clamp() Within Other CSS Functions
            [
                '.example { width: max(100px, clamp(10px, 50%, 100px)); }',
                '.example { width: max(100px, clamp(10px, 50%, 100px)); }',
            ],

            // Complex Arithmetic Inside clamp()
            [
                '.example { height: clamp(10px + (5% - 3px), 10vh + (2em * 3), 30px / 2); }',
                '.example { height: clamp(10px + (5% - 3px), 10vh + (2em * 3), 30px / 2); }',
            ],

            // clamp() in Combined Selectors and Pseudo-Classes
            [
                '.example:hover, .alternative { margin: clamp(1rem, 5vh + 10px, 10rem); }',
                '.example:hover, .alternative { margin: clamp(1rem, 5vh + 10px, 10rem); }',
            ],

            // clamp() in Complex Media Queries
            [
                '@media screen and (min-width: 600px) and (orientation: landscape) { .example { font-size: clamp(1rem, 2vw, 3rem); } }',
                '@media screen and (min-width: 600px) and (orientation: landscape) { .example { font-size: clamp(1rem, 2vw, 3rem); } }',
            ],

            // clamp() with Various Units and Calculations
            [
                '.example { padding: clamp(1rem + 10%, calc(2vh - 3px), 5em * 2); }',
                '.example { padding: clamp(1rem + 10%, calc(2vh - 3px), 5em * 2); }',
            ],

            // Intermingled clamp() and calc() Functions
            [
                '.example { border-width: clamp(calc(1px + 2em), 5vh, calc(10px + 3%)); }',
                '.example { border-width: clamp(calc(1px + 2em), 5vh, calc(10px + 3%)); }',
            ],

            // Complex nested Intermingled clamp() and calc() Functions
            [
                '.example { border-width: clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px + 3%)))))))); }',
                '.example { border-width: clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px +clamp(calc(1px + 2em), 5vh, calc(10px + 3%)))))))); }',
            ],

            // Complex nested Intermingled clamp() and calc() Functions in unquote
            [
                '.example { border-width: unquote("clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px + 3%))))))))"); }',
                '.example { border-width: clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px + clamp(calc(1px + 2em), 5vh, calc(10px +clamp(calc(1px + 2em), 5vh, calc(10px + 3%)))))))); }',
            ],
        ];
    }

    /**
     * @dataProvider queryProvider
     */
    public function testQueryPrefixing($css, $expected)
    {
        $result = Assets::compileScss($css, '');
        $this->assertEqualsIgnoringWhitespace($expected, $result);
    }
}
