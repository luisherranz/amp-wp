<?php
/**
 * Tests for AMP_Onboarding_Wizard class.
 *
 * @package AMP
 */

/**
 * Tests for AMP_Onboarding_Wizard  class.
 *
 * @group onboarding
 *
 * @since @todo NEW_ONBOARDING_RELEASE_VERSION
 *
 * @covers AMP_Onboarding_Wizard
 */
class Test_AMP_Onboarding_Wizard  extends WP_UnitTestCase {

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->old_wp_scripts = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		remove_action( 'wp_default_scripts', 'wp_default_packages' );
		$GLOBALS['wp_scripts'] = new WP_Scripts();
	}

	/**
	 * Tear down.
	 *
	 * @inheritdoc
	 */
	public function tearDown() {
		parent::tearDown();

		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		add_action( 'wp_default_scripts', 'wp_default_scripts' );
	}

	/**
	 * Tests AMP_Onboarding_Wizard::init
	 *
	 * @covers AMP_Onboarding_Wizard::init
	 */
	public function test_init() {
		$wizard = new AMP_Onboarding_Wizard();

		$wizard->init();

		$this->assertEquals( 10, has_action( 'admin_menu', [ $wizard, 'add_onboarding_screen' ] ) );
		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', [ $wizard, 'override_scripts' ] ) );
		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', [ $wizard, 'enqueue_assets' ] ) );
	}

	/**
	 * Tests AMP_Onboarding_Wizard::add_onboarding_screen
	 *
	 * @covers AMP_Onboarding_Wizard::add_onboarding_screen
	 */
	public function test_add_onboarding_screen() {
		global $submenu;

		wp_set_current_user( 1 );

		$wizard = new AMP_Onboarding_Wizard();

		$wizard->add_onboarding_screen();

		$this->assertEquals( end( $submenu['amp-options'] )[2], 'amp-onboarding' );
	}

	/**
	 * Tests AMP_Onboarding_Wizard::render_onboarding_screen
	 *
	 * @covers AMP_Onboarding_Wizard::render_onboarding_screen
	 */
	public function test_render_onboarding_screen() {
		$wizard = new AMP_Onboarding_Wizard();

		ob_start();

		$wizard->render_onboarding_screen();

		$this->assertEquals( trim( ob_get_clean() ), '<div id="amp-onboarding"></div>' );
	}

	/**
	 * Tests AMP_Onboarding_Wizard::screen_handle
	 *
	 * @covers AMP_Onboarding_Wizard::screen_handle
	 */
	public function test_screen_handle() {
		$wizard = new AMP_Onboarding_Wizard();

		$this->assertEquals( $wizard->screen_handle(), 'amp_page_amp-onboarding' );
	}

	/**
	 * Provides test data for test_add_onboarding_script.
	 *
	 * @return array
	 */
	public function get_test_onboarding_scripts() {
		return [
			[
				'asset-1',
				false,
			],
			[
				'asset-2',
				true,
			],
		];
	}

	/**
	 * Tests AMP_Onboarding_Wizard::add_onboarding_script
	 *
	 * @covers AMP_Onboarding_Wizard::add_onboarding_script
	 *
	 * @dataProvider get_test_onboarding_scripts
	 *
	 * @param string  $handle   Script handle
	 * @param boolean $enqueued Whether to enqueue the script.
	 */
	public function test_add_onboarding_script( $handle, $enqueued ) {
		$wizard = new AMP_Onboarding_Wizard();

		$filter_asset = function( $asset, $asset_handle ) use ( $handle ) {
			if ( $handle !== $asset_handle ) {
				return $asset;
			}

			return [
				'dependencies' => [],
				'version'      => '1.0',
			];
		};

		add_filter( 'amp_onboarding_asset', $filter_asset, 10, 2 );
		$wizard->add_onboarding_script( $handle, $enqueued );
		remove_filter( 'amp_onboarding_asset', $filter_asset );

		$this->assertTrue( wp_script_is( $handle, $enqueued ? 'enqueued' : 'registered' ) );
	}

	/**
	 * Tests AMP_Onboarding_Wizard::get_asset
	 *
	 * @covers AMP_Onboarding_Wizard::get_asset
	 */
	public function test_get_asset() {
		$wizard = new AMP_Onboarding_Wizard();

		$test_data = [
			'dependencies' => [],
			'version'      => '1.0',
		];

		$filter_asset = function() use ( $test_data ) {
			return $test_data;
		};

		add_filter( 'amp_onboarding_asset', $filter_asset, 10, 2 );
		$asset = $wizard->get_asset( 'my-handle' );
		remove_filter( 'amp_onboarding_asset', $filter_asset );

		$this->assertEquals( $asset, $test_data );
	}

	/**
	 * Tests AMP_Onboarding_Wizard::enqueue_assets
	 *
	 * @covers AMP_Onboarding_Wizard::enqueue_assets
	 */
	public function test_enqueue_assets() {
		$wizard = new AMP_Onboarding_Wizard();

		$handle = 'amp-onboarding';

		$wizard->enqueue_assets( 'some-screen' );
		$this->assertFalse( wp_script_is( $handle ) );

		$wizard->enqueue_assets( $wizard->screen_handle() );
		$this->assertTrue( wp_script_is( $handle ) );
	}

	/**
	 * Provides WP versions to test in test_override_scripts.
	 *
	 * @return array
	 */
	public function get_wp_version() {
		return [
			[ '4.9.0' ],
			[ '5.4.1' ],
		];
	}

	/**
	 * Tests AMP_Onboarding_Wizard::override_scripts
	 *
	 * @covers AMP_Onboarding_Wizard::override_scripts
	 *
	 * @dataProvider get_wp_version
	 */
	public function test_override_scripts( $test_wp_version ) {
		global $wp_version;

		$original_wp_version = $wp_version;
		$wp_version          = $test_wp_version;

		$wizard = new AMP_Onboarding_Wizard();

		$filter_asset = function( $asset, $handle ) {
			if ( 'amp-onboarding' !== $handle ) {
				return $asset;
			}

			return [
				'dependencies' => [
					'wp-components',
					'wp-polyfill',
					'react',
				],
				'version'      => '1.0',
			];
		};

		add_filter( 'amp_onboarding_asset', $filter_asset, 10, 2 );
		$wizard->override_scripts( $wizard->screen_handle() );
		remove_filter( 'amp_onboarding_asset', $filter_asset );

		$this->assertTrue( wp_script_is( 'wp-components', 'registered' ) );
		$this->assertTrue( wp_script_is( 'react', 'registered' ) );
		$this->assertTrue( wp_script_is( 'wp-polyfill', 'registered' ) );

		$wp_version = $original_wp_version;
	}
}
