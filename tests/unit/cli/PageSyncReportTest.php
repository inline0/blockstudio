<?php
/**
 * Page sync CLI report tests.
 *
 * @package Blockstudio
 */

use Blockstudio\CLI;
use PHPUnit\Framework\TestCase;

class PageSyncReportTest extends TestCase {

	public function test_cli_names_locked_preserved_overwritten_and_removed_pages(): void {
		$report   = array(
			'pages'        => array(
				'locked-page'    => array(
					'status' => 'locked',
					'postId' => 10,
				),
				'keyed-page'     => array(
					'status'    => 'unchanged',
					'postId'    => 11,
					'preserved' => true,
				),
				'changed-page'   => array(
					'status'      => 'updated',
					'postId'      => 12,
					'overwritten' => array( 'post_title', 'post_name' ),
				),
				'unchanged-page' => array(
					'status' => 'unchanged',
					'postId' => 13,
				),
			),
			'removedPages' => array(
				array(
					'name'   => 'old-page',
					'key'    => 'old',
					'postId' => 14,
					'action' => 'trash',
				),
				array(
					'name'   => '',
					'key'    => 'deleted-page',
					'postId' => 15,
					'action' => 'delete',
				),
			),
		);
		$method   = new ReflectionMethod( CLI::class, 'page_report_lines' );
		$lines    = $method->invoke( null, $report );
		$messages = array_column( $lines, 'message' );
		$this->assertContains( 'locked-page: locked (post 10)', $messages );
		$this->assertContains( 'keyed-page: unchanged (post 11); keyed editor content preserved', $messages );
		$this->assertContains( 'old-page: trash (post 14)', $messages );
		$this->assertContains( 'deleted-page: delete (post 15)', $messages );
		$this->assertContains(
			array(
				'level'   => 'warning',
				'message' => 'changed-page: file-owned fields overwritten: post_title, post_name',
			),
			$lines
		);
		$this->assertCount( 6, $lines );
	}

	public function test_cli_names_the_content_type_of_reported_pages(): void {
		$method = new ReflectionMethod( CLI::class, 'page_report_lines' );
		$lines  = $method->invoke(
			null,
			array(
				'pages' => array(
					'legal-notice' => array(
						'status'      => 'updated',
						'contentType' => 'component',
						'postId'      => 21,
					),
				),
			)
		);

		$this->assertSame( 'legal-notice: updated (post 21, component)', $lines[0]['message'] );
	}
}
