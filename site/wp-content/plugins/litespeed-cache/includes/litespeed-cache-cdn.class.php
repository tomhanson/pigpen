<?php

/**
 * The CDN class.
 *
 * @since      	1.2.3
 * @since  		1.5 Moved into /inc
 * @package    	LiteSpeed_Cache
 * @subpackage 	LiteSpeed_Cache/inc
 * @author     	LiteSpeed Technologies <info@litespeedtech.com>
 */

class LiteSpeed_Cache_CDN
{
	private static $_instance ;

	const BYPASS = 'LITESPEED_BYPASS_CDN' ;

	const TYPE_CLOUDFLARE_PURGE_ALL = 'cloudflare_purge_all' ;
	const TYPE_CLOUDFLARE_GET_DEVMODE = 'cloudflare_get_devmode' ;
	const TYPE_CLOUDFLARE_SET_DEVMODE_ON = 'cloudflare_set_devmode_on' ;
	const TYPE_CLOUDFLARE_SET_DEVMODE_OFF = 'cloudflare_set_devmode_off' ;

	private $content ;

	private $cfg_cdn ;
	private $cfg_url_ori ;
	private $cfg_cdn_mapping = array() ;
	private $cfg_cdn_exclude ;
	private $cfg_cdn_remote_jquery ;

	private $cdn_mapping_hosts = array() ;

	/**
	 * Init
	 *
	 * @since  1.2.3
	 * @access private
	 */
	private function __construct()
	{
		LiteSpeed_Cache_Log::debug2( 'CDN init' ) ;

		if ( ! $this->can_cdn() ) {
			if ( ! defined( self::BYPASS ) ) {
				define( self::BYPASS, true ) ;
			}
			return ;
		}

		/**
		 * Remotely load jQuery
		 * This is separate from CDN on/off
		 * @since 1.5
		 */
		$this->cfg_cdn_remote_jquery = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CDN_REMOTE_JQUERY ) ;
		if ( $this->cfg_cdn_remote_jquery ) {
			add_action( 'init', array( $this, 'load_jquery_remotely' ) ) ;
		}

		$this->cfg_cdn = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CDN ) ;
		if ( ! $this->cfg_cdn ) {
			if ( ! defined( self::BYPASS ) ) {
				define( self::BYPASS, true ) ;
			}
			return ;
		}

		$this->cfg_url_ori = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CDN_ORI ) ;
		$cfg_cdn_url = get_option( LiteSpeed_Cache_Config::ITEM_CDN_MAPPING, array() ) ;
		// Parse cdn mapping data to array( 'filetype' => 'url' )
		$mapping_to_check = array(
			LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_IMG,
			LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_CSS,
			LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_JS
		) ;
		foreach ( $cfg_cdn_url as $v ) {
			if ( ! $v[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_URL ] ) {
				continue ;
			}
			$this_url = $v[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_URL ] ;
			$this_host = parse_url( $this_url, PHP_URL_HOST ) ;
			foreach ( $mapping_to_check as $to_check ) {
				if ( $v[ $to_check ] ) {
					LiteSpeed_Cache_Log::debug2( 'CDN: mapping ' . $to_check . ' -> ' . $this_url ) ;
					$this->cfg_cdn_mapping[ $to_check ] = $this_url ;
					if ( ! in_array( $this_host, $this->cdn_mapping_hosts ) ) {
						$this->cdn_mapping_hosts[] = $this_host ;
					}
				}
			}
			if ( $v[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_FILETYPE ] ) {
				$filetypes = array_map( 'trim', explode( "\n", $v[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_FILETYPE ] ) ) ;
				foreach ( $filetypes as $v2 ) {
					if ( $v2 ) {
						$this->cfg_cdn_mapping[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_FILETYPE ] = true ;
						$this->cfg_cdn_mapping[ $v2 ] = $this_url ;
						if ( ! in_array( $this_host, $this->cdn_mapping_hosts ) ) {
							$this->cdn_mapping_hosts[] = $this_host ;
						}
					}
				}
				LiteSpeed_Cache_Log::debug2( 'CDN: mapping ' . implode( ',', $filetypes ) . ' -> ' . $this_url ) ;
			}
		}
		if ( ! $this->cfg_url_ori || ! $this->cfg_cdn_mapping ) {
			if ( ! defined( self::BYPASS ) ) {
				define( self::BYPASS, true ) ;
			}
			return ;
		}

		// Check if need preg_replace
		if ( strpos( $this->cfg_url_ori, '*' ) !== false ) {
			LiteSpeed_Cache_Log::debug( 'CDN: wildcard rule in ' . $this->cfg_url_ori ) ;
			$this->cfg_url_ori = preg_quote( $this->cfg_url_ori, '#' ) ;
			$this->cfg_url_ori = str_replace( '\*', '.*', $this->cfg_url_ori ) ;
			LiteSpeed_Cache_Log::debug2( 'CDN: translated rule is ' . $this->cfg_url_ori ) ;
		}

		$this->cfg_cdn_exclude = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CDN_EXCLUDE ) ;
		$this->cfg_cdn_exclude = explode( "\n", $this->cfg_cdn_exclude ) ;

		if ( ! empty( $this->cfg_cdn_mapping[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_IMG ] ) ) {
			// Hook to srcset
			if ( function_exists( 'wp_calculate_image_srcset' ) ) {
				add_filter( 'wp_calculate_image_srcset', array( $this, 'srcset' ), 999 ) ;
			}
			// Hook to mime icon
			add_filter( 'wp_get_attachment_image_src', array( $this, 'attach_img_src' ), 999 ) ;
			add_filter( 'wp_get_attachment_url', array( $this, 'url_img' ), 999 ) ;
		}

		if ( ! empty( $this->cfg_cdn_mapping[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_CSS ] ) ) {
			add_filter( 'style_loader_src', array( $this, 'url_css' ), 999 ) ;
		}

		if ( ! empty( $this->cfg_cdn_mapping[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_JS ] ) ) {
			add_filter( 'script_loader_src', array( $this, 'url_js' ), 999 ) ;
		}

	}

	/**
	 * Handle all request actions from main cls
	 *
	 * @since  1.7.2
	 * @access public
	 */
	public static function handler()
	{
		$instance = self::get_instance() ;

		$type = LiteSpeed_Cache_Router::verify_type() ;

		switch ( $type ) {
			case self::TYPE_CLOUDFLARE_PURGE_ALL :
				$instance->_cloudflare_purge_all() ;
				break ;

			case self::TYPE_CLOUDFLARE_GET_DEVMODE :
				$instance->_cloudflare_get_devmode() ;
				break ;

			case self::TYPE_CLOUDFLARE_SET_DEVMODE_ON :
			case self::TYPE_CLOUDFLARE_SET_DEVMODE_OFF :
				$instance->_cloudflare_set_devmode( $type ) ;
				break ;

			default:
				break ;
		}

		LiteSpeed_Cache_Admin::redirect() ;
	}

	/**
	 * If include css/js in CDN
	 *
	 * @since  1.6.2.1
	 * @return bool true if included in CDN
	 */
	public static function inc_type( $type )
	{
		$instance = self::get_instance() ;

		if ( $type == 'css' && ! empty( $instance->cfg_cdn_mapping[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_CSS ] ) ) {
			return true ;
		}

		if ( $type == 'js' && ! empty( $instance->cfg_cdn_mapping[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_JS ] ) ) {
			return true ;
		}

		return false ;
	}

	/**
	 * Check if the host is the CDN internal host
	 *
	 * @since  1.2.3
	 *
	 */
	public static function internal( $host )
	{
		if ( defined( self::BYPASS ) ) {
			return false ;
		}

		$instance = self::get_instance() ;

		return in_array( $host, $instance->cdn_mapping_hosts ) ;
	}

	/**
	 * Run CDN process
	 * NOTE: As this is after cache finalized, can NOT set any cache control anymore
	 *
	 * @since  1.2.3
	 * @access public
	 * @return  string The content that is after optimization
	 */
	public static function finalize( $content )
	{
		$instance = self::get_instance() ;
		$instance->content = $content ;

		$instance->_finalize() ;
		return $instance->content ;
	}

	/**
	 * Check if it can use CDN replacement
	 *
	 * @since  1.2.3
	 * @access public
	 */
	public function can_cdn()
	{
		if ( is_admin() ) {
			return false ;
		}

		if ( is_feed() ) {
			return false ;
		}

		if ( is_preview() ) {
			return false ;
		}

		/**
		 * Bypass login/reg page
		 * @since  1.6
		 */
		if ( in_array( $GLOBALS[ 'pagenow' ], array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			LiteSpeed_Cache_Log::debug( 'CDN bypassed as is login/reg page' ) ;
			return false ;
		}

		return true ;
	}

	/**
	 * Replace CDN url
	 *
	 * @since  1.2.3
	 * @access private
	 */
	private function _finalize()
	{
		if ( defined( self::BYPASS ) ) {
			LiteSpeed_Cache_Log::debug2( 'CDN bypass' ) ;
			return ;
		}

		LiteSpeed_Cache_Log::debug( 'CDN _finalize' ) ;

		// Start replacing img src
		if ( ! empty( $this->cfg_cdn_mapping[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_IMG ] ) ) {
			$this->_replace_img() ;
			$this->_replace_inline_css() ;
		}

		if ( ! empty( $this->cfg_cdn_mapping[ LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_FILETYPE ] ) ) {
			$this->_replace_file_types() ;
		}

	}

	/**
	 * Parse all file types
	 *
	 * @since  1.2.3
	 * @access private
	 */
	private function _replace_file_types()
	{
		preg_match_all( '#(src|data-src|href)\s*=\s*[\'"]([^\'"]+)[\'"]#i', $this->content, $matches ) ;
		if ( empty( $matches[ 2 ] ) ) {
			return ;
		}

		$filetypes = array_keys( $this->cfg_cdn_mapping ) ;
		foreach ( $matches[ 2 ] as $k => $url ) {
			$url_parsed = parse_url( $url ) ;
			if ( empty( $url_parsed[ 'path' ] ) ) {
				continue ;
			}
			$postfix = substr( $url_parsed[ 'path' ], strrpos( $url_parsed[ 'path' ], '.' ) ) ;
			if ( ! in_array( $postfix, $filetypes ) ) {
				continue ;
			}

			LiteSpeed_Cache_Log::debug2( 'CDN matched file_type ' . $postfix . ' : ' . $url ) ;

			if( ! $url2 = $this->rewrite( $url, LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_FILETYPE, $postfix ) ) {
				continue ;
			}

			$attr = str_replace( $url, $url2, $matches[ 0 ][ $k ] ) ;
			$this->content = str_replace( $matches[ 0 ][ $k ], $attr, $this->content ) ;
		}
	}

	/**
	 * Parse all images
	 *
	 * @since  1.2.3
	 * @access private
	 */
	private function _replace_img()
	{
		preg_match_all( '#<img([^>]+?)src=([\'"\\\]*)([^\'"\s\\\>]+)([\'"\\\]*)([^>]*)>#i', $this->content, $matches ) ;
		foreach ( $matches[ 3 ] as $k => $url ) {
			// Check if is a DATA-URI
			if ( strpos( $url, 'data:image' ) !== false ) {
				continue ;
			}

			if ( ! $url2 = $this->rewrite( $url, LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_IMG ) ) {
				continue ;
			}

			$html_snippet = sprintf(
				'<img %1$s src=%2$s %3$s>',
				$matches[ 1 ][ $k ],
				$matches[ 2 ][ $k ] . $url2 . $matches[ 4 ][ $k ],
				$matches[ 5 ][ $k ]
			) ;
			$this->content = str_replace( $matches[ 0 ][ $k ], $html_snippet, $this->content ) ;
		}
	}

	/**
	 * Parse and replace all inline styles containing url()
	 *
	 * @since  1.2.3
	 * @access private
	 */
	private function _replace_inline_css()
	{
		// preg_match_all( '/url\s*\(\s*(?!["\']?data:)(?![\'|\"]?[\#|\%|])([^)]+)\s*\)([^;},\s]*)/i', $this->content, $matches ) ;
		preg_match_all( '#url\((?![\'"]?data)[\'"]?([^\)\'"]+)[\'"]?\)#i', $this->content, $matches ) ;
		foreach ( $matches[ 1 ] as $k => $url ) {
			$url = str_replace( array( ' ', '\t', '\n', '\r', '\0', '\x0B', '"', "'", '&quot;', '&#039;' ), '', $url ) ;

			if ( ! $url2 = $this->rewrite( $url, LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_IMG ) ) {
				continue ;
			}
			$attr = str_replace( $matches[ 1 ][ $k ], $url2, $matches[ 0 ][ $k ] ) ;
			$this->content = str_replace( $matches[ 0 ][ $k ], $attr, $this->content ) ;
		}
	}

	/**
	 * Hook to wp_get_attachment_image_src
	 *
	 * @since  1.2.3
	 * @since  1.7 Removed static from function
	 * @access public
	 * @param  array $img The URL of the attachment image src, the width, the height
	 * @return array
	 */
	public function attach_img_src( $img )
	{
		if ( $img && $url = $this->rewrite( $img[ 0 ], LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_IMG ) ) {
			$img[ 0 ] = $url ;
		}
		return $img ;
	}

	/**
	 * Try to rewrite one URL with CDN
	 *
	 * @since  1.7
	 * @access public
	 */
	public function url_img( $url )
	{
		if ( $url && $url2 = $this->rewrite( $url, LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_IMG ) ) {
			$url = $url2 ;
		}
		return $url ;
	}

	/**
	 * Try to rewrite one URL with CDN
	 *
	 * @since  1.7
	 * @access public
	 */
	public function url_css( $url )
	{
		if ( $url && $url2 = $this->rewrite( $url, LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_CSS ) ) {
			$url = $url2 ;
		}
		return $url ;
	}

	/**
	 * Try to rewrite one URL with CDN
	 *
	 * @since  1.7
	 * @access public
	 */
	public function url_js( $url )
	{
		if ( $url && $url2 = $this->rewrite( $url, LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_JS ) ) {
			$url = $url2 ;
		}
		return $url ;
	}

	/**
	 * Hook to replace WP responsive images
	 *
	 * @since  1.2.3
	 * @since  1.7 Removed static from function
	 * @access public
	 * @param  array $srcs
	 * @return array
	 */
	public function srcset( $srcs )
	{
		if ( $srcs ) {
			foreach ( $srcs as $w => $data ) {
				if( ! $url = $this->rewrite( $data[ 'url' ], LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_INC_IMG ) ) {
					continue ;
				}
				$srcs[ $w ][ 'url' ] = $url ;
			}
		}
		return $srcs ;
	}

	/**
	 * Replace URL to CDN URL
	 *
	 * @since  1.2.3
	 * @access public
	 * @param  string $url
	 * @return string        Replaced URL
	 */
	public function rewrite( $url, $mapping_kind, $postfix = false )
	{
		LiteSpeed_Cache_Log::debug2( 'CDN: try rewriting ' . $url ) ;
		$url_parsed = parse_url( $url ) ;

		// Only images under wp-cotnent/wp-includes can be replaced
		if ( stripos( $url_parsed[ 'path' ], LSCWP_CONTENT_FOLDER ) === false && stripos( $url_parsed[ 'path' ], 'wp-includes' ) === false  && stripos( $url_parsed[ 'path' ], '/min/' ) === false ) {
			if ( ! defined( 'UPLOADS' ) || stripos( $url_parsed[ 'path' ], UPLOADS ) === false ) {
				LiteSpeed_Cache_Log::debug2( 'CDN:    rewriting failed: path not match: ' . LSCWP_CONTENT_FOLDER ) ;
				return false ;
			}
		}

		// Check if is external url
		if ( ! empty( $url_parsed[ 'host' ] ) && ! LiteSpeed_Cache_Utility::internal( $url_parsed[ 'host' ] ) ) {
			LiteSpeed_Cache_Log::debug2( 'CDN:    rewriting failed: host not internal' ) ;
			return false ;
		}

		if ( $this->cfg_cdn_exclude ) {
			foreach ( $this->cfg_cdn_exclude as $exclude ) {
				if ( stripos( $url, $exclude ) !== false ) {
					LiteSpeed_Cache_Log::debug2( 'CDN:    Abort excludes ' . $exclude ) ;
					return false ;
				}
			}
		}

		// Fill full url before replacement
		if ( empty( $url_parsed[ 'host' ] ) ) {
			$url = LiteSpeed_Cache_Utility::uri2url( $url ) ;
			LiteSpeed_Cache_Log::debug2( 'CDN:    fill before rewritten: ' . $url ) ;

			$url_parsed = parse_url( $url ) ;
		}

		$scheme = ! empty( $url_parsed[ 'scheme' ] ) ? $url_parsed[ 'scheme' ] . ':' : '' ;
		if ( $scheme ) {
			LiteSpeed_Cache_Log::debug2( 'CDN:    scheme from url: ' . $scheme ) ;
		}

		// Find the mapping url to be replaced to
		if ( empty( $this->cfg_cdn_mapping[ $mapping_kind ] ) ) {
			return false ;
		}
		if ( $mapping_kind !== LiteSpeed_Cache_Config::ITEM_CDN_MAPPING_FILETYPE ) {
			$final_url = $this->cfg_cdn_mapping[ $mapping_kind ] ;
		}
		else {
			// select from file type
			$final_url = $this->cfg_cdn_mapping[ $postfix ] ;
		}

		// Now lets replace CDN url
		if ( strpos( $this->cfg_url_ori, '*' ) !== false ) {
			$url = preg_replace( '#' . $scheme . $this->cfg_url_ori . '#iU', $final_url, $url ) ;
		}
		else {
			$url = str_replace( $scheme . $this->cfg_url_ori, $final_url, $url ) ;
		}
		LiteSpeed_Cache_Log::debug2( 'CDN:    after rewritten: ' . $url ) ;

		return $url ;
	}

	/**
	 * Remote load jQuery remotely
	 *
	 * @since  1.5
	 * @access public
	 */
	public function load_jquery_remotely()
	{
		// default jq version
		$v = '1.12.4' ;

		// load wp's jq version
		global $wp_scripts ;
		if ( isset( $wp_scripts->registered[ 'jquery' ]->ver ) ) {
			$v = $wp_scripts->registered[ 'jquery' ]->ver ;
		}

		$src = $this->cfg_cdn_remote_jquery === LiteSpeed_Cache_Config::VAL_ON ? "//ajax.googleapis.com/ajax/libs/jquery/$v/jquery.min.js" : "//cdnjs.cloudflare.com/ajax/libs/jquery/$v/jquery.min.js" ;

		LiteSpeed_Cache_Log::debug2( 'CDN: load_jquery_remotely: ' . $src ) ;

		wp_deregister_script( 'jquery' ) ;

		wp_register_script( 'jquery', $src, false, $v ) ;
	}

	/**
	 * Get Cloudflare development mode
	 *
	 * @since  1.7.2
	 * @access private
	 */
	private function _cloudflare_get_devmode( $show_msg = true )
	{
		LiteSpeed_Cache_Log::debug( 'CDN: _cloudflare_get_devmode' ) ;

		$zone = $this->_cloudflare_zone() ;
		if ( ! $zone ) {
			return ;
		}

		$url = 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/settings/development_mode' ;
		$res = $this->_cloudflare_call( $url, 'GET', false, false, $show_msg ) ;

		if ( ! $res ) {
			return ;
		}
		LiteSpeed_Cache_Log::debug( 'CDN: _cloudflare_get_devmode result ', $res ) ;

		$curr_status = get_option( LiteSpeed_Cache_Config::ITEM_CLOUDFLARE_STATUS, array() ) ;
		$curr_status[ 'devmode' ] = $res[ 'value' ] ;
		$curr_status[ 'devmode_expired' ] = $res[ 'time_remaining' ] + time() ;

		// update status
		update_option( LiteSpeed_Cache_Config::ITEM_CLOUDFLARE_STATUS, $curr_status ) ;

	}

	/**
	 * Set Cloudflare development mode
	 *
	 * @since  1.7.2
	 * @access private
	 */
	private function _cloudflare_set_devmode( $type )
	{
		LiteSpeed_Cache_Log::debug( 'CDN: _cloudflare_set_devmode' ) ;

		$zone = $this->_cloudflare_zone() ;
		if ( ! $zone ) {
			return ;
		}

		$url = 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/settings/development_mode' ;
		$new_val = $type == self::TYPE_CLOUDFLARE_SET_DEVMODE_ON ? 'on' : 'off' ;
		$data = array( 'value' => $new_val ) ;
		$res = $this->_cloudflare_call( $url, 'PATCH', $data ) ;

		if ( ! $res ) {
			return ;
		}

		$res = $this->_cloudflare_get_devmode( false ) ;

		if ( $res ) {
			$msg = sprintf( __( 'Notified Cloudflare to set development mode to %s successfully.', 'litespeed-cache' ), strtoupper( $new_val ) ) ;
			LiteSpeed_Cache_Admin_Display::succeed( $msg ) ;
		}

	}

	/**
	 * Purge Cloudflare cache
	 *
	 * @since  1.7.2
	 * @access private
	 */
	private function _cloudflare_purge_all()
	{
		LiteSpeed_Cache_Log::debug( 'CDN: _cloudflare_purge_all' ) ;

		$cf_on = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CDN_CLOUDFLARE ) ;
		if ( ! $cf_on ) {
			$msg = __( 'Cloudflare API is set to off.', 'litespeed-cache' ) ;
			LiteSpeed_Cache_Admin_Display::error( $msg ) ;
			return ;
		}

		$zone = $this->_cloudflare_zone() ;
		if ( ! $zone ) {
			return ;
		}

		$url = 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/purge_cache' ;
		$data = array( 'purge_everything' => true ) ;

		$res = $this->_cloudflare_call( $url, 'DELETE', $data ) ;

		if ( $res ) {
			$msg = __( 'Notified Cloudflare to purge all successfully.', 'litespeed-cache' ) ;
			LiteSpeed_Cache_Admin_Display::succeed( $msg ) ;
		}
	}

	/**
	 * Get current Cloudflare zone from cfg
	 *
	 * @since  1.7.2
	 * @access private
	 */
	private function _cloudflare_zone()
	{
		$zone = LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CDN_CLOUDFLARE_ZONE ) ;
		if ( ! $zone ) {
			$msg = __( 'No available Cloudflare zone', 'litespeed-cache' ) ;
			LiteSpeed_Cache_Admin_Display::error( $msg ) ;
			return false ;
		}

		return $zone ;
	}

	/**
	 * Get Cloudflare zone settings
	 *
	 * @since  1.7.2
	 * @access public
	 */
	public function cloudflare_fetch_zone( $options )
	{
		$kw = $options[ LiteSpeed_Cache_Config::OPID_CDN_CLOUDFLARE_NAME ] ;

		$url = 'https://api.cloudflare.com/client/v4/zones?status=active&match=all' ;

		// Try exact match first
		if ( $kw && strpos( $kw, '.' ) ) {
			$zones = $this->_cloudflare_call( $url . '&name=' . $kw, 'GET', false, $options, false ) ;
			if ( $zones ) {
				LiteSpeed_Cache_Log::debug( 'CDN: cloudflare_fetch_zone exact matched' ) ;
				return $zones[ 0 ] ;
			}
		}

		// Can't find, try to get default one
		$zones = $this->_cloudflare_call( $url, 'GET', false, $options, false ) ;

		if ( ! $zones ) {
			LiteSpeed_Cache_Log::debug( 'CDN: cloudflare_fetch_zone no zone' ) ;
			return false ;
		}

		if ( ! $kw ) {
			LiteSpeed_Cache_Log::debug( 'CDN: cloudflare_fetch_zone no set name, use first one by default' ) ;
			return $zones[ 0 ] ;
		}

		foreach ( $zones as $v ) {
			if ( strpos( $v[ 'name' ], $kw ) !== false ) {
				LiteSpeed_Cache_Log::debug( 'CDN: cloudflare_fetch_zone matched ' . $kw . ' [name] ' . $v[ 'name' ] ) ;
				return $v ;
			}
		}

		// Can't match current name, return default one
		LiteSpeed_Cache_Log::debug( 'CDN: cloudflare_fetch_zone failed match name, use first one by default' ) ;
		return $zones[ 0 ] ;
	}

	/**
	 * Cloudflare API
	 *
	 * @since  1.7.2
	 * @access private
	 */
	private function _cloudflare_call( $url, $method = 'GET', $data = false, $token = false, $show_msg = true )
	{
		LiteSpeed_Cache_Log::debug( "CDN: _cloudflare_call \t\t[URL] $url" ) ;

		$header = array(
			'Content-Type: application/json',
		) ;
		if ( $token ) {
			LiteSpeed_Cache_Log::debug2( 'CDN: _cloudflare_call use param token' ) ;
			$header[] = 'X-Auth-Email: ' . $token[ LiteSpeed_Cache_Config::OPID_CDN_CLOUDFLARE_EMAIL ] ;
			$header[] = 'X-Auth-Key: ' . $token[ LiteSpeed_Cache_Config::OPID_CDN_CLOUDFLARE_KEY ] ;
		}
		else {
			$header[] = 'X-Auth-Email: ' . LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CDN_CLOUDFLARE_EMAIL ) ;
			$header[] = 'X-Auth-Key: ' . LiteSpeed_Cache::config( LiteSpeed_Cache_Config::OPID_CDN_CLOUDFLARE_KEY ) ;
		}

		$ch = curl_init() ;
		curl_setopt( $ch, CURLOPT_URL, $url ) ;
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method ) ;
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header ) ;
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ) ;
		if ( $data ) {
			if ( is_array( $data ) ) {
				$data = json_encode( $data ) ;
			}
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data ) ;
		}
		$result = curl_exec( $ch ) ;

		$json = json_decode( $result, true ) ;

		if ( $json && $json[ 'success' ] && $json[ 'result' ] ) {
			LiteSpeed_Cache_Log::debug( "CDN: _cloudflare_call called successfully" ) ;
			if ( $show_msg ) {
				$msg = __( 'Communicated with Cloudflare successfully.', 'litespeed-cache' ) ;
				LiteSpeed_Cache_Admin_Display::succeed( $msg ) ;
			}

			return $json[ 'result' ] ;
		}

		LiteSpeed_Cache_Log::debug( "CDN: _cloudflare_call called failed: $result" ) ;
		if ( $show_msg ) {
			$msg = __( 'Failed to communicate with Cloudflare', 'litespeed-cache' ) ;
			LiteSpeed_Cache_Admin_Display::error( $msg ) ;
		}

		return false ;
	}

	/**
	 * Get the current instance object.
	 *
	 * @since 1.2.3
	 * @access public
	 * @return Current class instance.
	 */
	public static function get_instance()
	{
		if ( ! isset(self::$_instance) ) {
			self::$_instance = new self() ;
		}

		return self::$_instance ;
	}

}



